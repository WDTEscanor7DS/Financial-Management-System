/* ==========================================================================
   REVENUE MANAGEMENT
   ========================================================================== */

document.addEventListener('DOMContentLoaded', async () => {
  if (!(await initShell('revenue'))) return;

  await populateRevenueFilters();
  await renderRevenueTable();

  document.getElementById('addRevenueBtn').addEventListener('click', () => openRevenueModal());
  document.getElementById('revenueForm').addEventListener('submit', handleRevenueSubmit);
  document.getElementById('exportRevenueBtn').addEventListener('click', exportRevenueCSV);
  ['revenueSearch', 'revenueTypeFilter', 'revenueDeptFilter', 'revenueStatusFilter', 'revenueDateFilter'].forEach(id => {
    document.getElementById(id).addEventListener('input', renderRevenueTable);
  });
  document.getElementById('revenueResetFilters').addEventListener('click', () => {
    ['revenueSearch', 'revenueTypeFilter', 'revenueDeptFilter', 'revenueStatusFilter', 'revenueDateFilter'].forEach(id => document.getElementById(id).value = '');
    renderRevenueTable();
  });
});

async function populateRevenueFilters() {
  const deptSel = document.getElementById('revenueDepartment');
  const deptFilter = document.getElementById('revenueDeptFilter');
  (await getDepartments()).forEach(d => {
    deptSel.insertAdjacentHTML('beforeend', '<option value="' + d + '">' + d + '</option>');
    deptFilter.insertAdjacentHTML('beforeend', '<option value="' + d + '">' + d + '</option>');
  });
  ['Tuition', 'Miscellaneous Fees', 'Service Income', 'Other Institutional Revenue'].forEach(t => {
    document.getElementById('revenueTypeFilter').insertAdjacentHTML('beforeend', '<option value="' + t + '">' + t + '</option>');
  });
  ['Received', 'Pending'].forEach(s => {
    document.getElementById('revenueStatusFilter').insertAdjacentHTML('beforeend', '<option value="' + s + '">' + s + '</option>');
  });
}

async function getFilteredRevenues() {
  const search = document.getElementById('revenueSearch').value;
  const type = document.getElementById('revenueTypeFilter').value;
  const dept = document.getElementById('revenueDeptFilter').value;
  const status = document.getElementById('revenueStatusFilter').value;
  const date = document.getElementById('revenueDateFilter').value;

  const all = await getRevenues();
  return filterRows(all, search, ['description', 'payer', 'referenceNo'], (r) =>
    (!type || r.revenueType === type) && (!dept || r.department === dept) && (!status || r.status === status) && (!date || r.date === date)
  ).sort((a, b) => new Date(b.date) - new Date(a.date));
}

async function renderRevenueTable() {
  const rows = await getFilteredRevenues();
  const total = rows.reduce((s, r) => s + Number(r.amount), 0);
  const all = await getRevenues();

  document.getElementById('revenueKpis').innerHTML = [
    { label: 'Total Revenue (Filtered)', value: formatPeso(total) },
    { label: 'Transactions', value: rows.length },
    { label: 'All-Time Total Revenue', value: formatPeso(all.reduce((s, r) => s + Number(r.amount), 0)) },
    { label: 'This Month', value: formatPeso(sumThisMonth(all)) }
  ].map(k => '<div class="card kpi-box"><div class="summary-label">' + k.label + '</div><div class="summary-value">' + k.value + '</div></div>').join('');

  const tbody = document.querySelector('#revenueTable tbody');
  if (!rows.length) {
    tbody.innerHTML = '<tr><td colspan="10">' + emptyState('No revenue records match your filters.') + '</td></tr>';
  } else {
    tbody.innerHTML = rows.map(r => (
      '<tr>' +
        '<td>' + r.id + '</td><td>' + formatDate(r.date) + '</td><td>' + escapeHtml(r.revenueType) + '</td>' +
        '<td>' + escapeHtml(r.description) + '</td><td>' + escapeHtml(r.department) + '</td>' +
        '<td>' + escapeHtml(r.payer) + '</td><td>' + escapeHtml(r.referenceNo || '\u2014') + '</td>' +
        '<td class="amount peso">' + formatPeso(r.amount) + '</td><td>' + statusBadge(r.status) + '</td>' +
        '<td><div class="row-actions">' +
          '<button class="btn btn-ghost btn-sm" onclick="viewRevenue(\'' + r.id + '\')">View</button>' +
          (userCan('edit_revenue') ? '<button class="btn btn-outline btn-sm" onclick="openRevenueModal(\'' + r.id + '\')">Edit</button>' : '') +
          (userCan('delete_revenue') ? '<button class="btn btn-danger btn-sm" onclick="handleDeleteRevenue(\'' + r.id + '\')">Delete</button>' : '') +
        '</div></td>' +
      '</tr>'
    )).join('');
  }
  document.getElementById('revenueCount').textContent = rows.length + ' record' + (rows.length === 1 ? '' : 's');
}

function sumThisMonth(records) {
  const now = new Date();
  return records.filter(r => { const d = new Date(r.date); return d.getFullYear() === now.getFullYear() && d.getMonth() === now.getMonth(); })
    .reduce((s, r) => s + Number(r.amount), 0);
}

function openRevenueModal(id) {
  const form = document.getElementById('revenueForm');
  form.reset();
  showFormErrors(form, []);
  if (id) {
    const r = getRevenue(id);
    document.getElementById('revenueModalTitle').textContent = 'Edit Revenue';
    document.getElementById('revenueId').value = r.id;
    document.getElementById('revenueDate').value = r.date;
    document.getElementById('revenueType').value = r.revenueType;
    document.getElementById('revenueDescription').value = r.description;
    document.getElementById('revenueDepartment').value = r.department;
    document.getElementById('revenuePayer').value = r.payer;
    document.getElementById('revenueReference').value = r.referenceNo || '';
    document.getElementById('revenueAmount').value = r.amount;
    document.getElementById('revenuePaymentMethod').value = r.paymentMethod;
    document.getElementById('revenueStatus').value = r.status;
  } else {
    document.getElementById('revenueModalTitle').textContent = 'Add Revenue';
    document.getElementById('revenueId').value = '';
    document.getElementById('revenueDate').value = todayISO();
  }
  openModal('revenueModal');
}

async function handleRevenueSubmit(e) {
  e.preventDefault();
  const form = e.target;
  const errors = validateForm(form);
  if (errors.length) { showFormErrors(form, errors); return; }

  const id = document.getElementById('revenueId').value;
  const data = {
    date: document.getElementById('revenueDate').value,
    revenueType: document.getElementById('revenueType').value,
    description: document.getElementById('revenueDescription').value.trim(),
    department: document.getElementById('revenueDepartment').value,
    payer: document.getElementById('revenuePayer').value.trim(),
    referenceNo: document.getElementById('revenueReference').value.trim(),
    amount: Number(document.getElementById('revenueAmount').value),
    paymentMethod: document.getElementById('revenuePaymentMethod').value,
    status: document.getElementById('revenueStatus').value
  };

  try {
    if (id) { await updateRevenue(id, data); toast('Revenue updated successfully.', 'success'); }
    else { await createRevenue(data); toast('Revenue recorded successfully.', 'success'); }

    closeModal('revenueModal');
    await renderRevenueTable();
  } catch (err) {
    showFormErrors(form, [err.message]);
  }
}

async function handleDeleteRevenue(id) {
  if (!confirm('Delete revenue record ' + id + '? This cannot be undone.')) return;
  try {
    await deleteRevenue(id);
    toast('Revenue record deleted.', 'success');
    await renderRevenueTable();
  } catch (err) {
    toast(err.message, 'danger');
  }
}

function viewRevenue(id) {
  const r = getRevenue(id);
  document.getElementById('revenueViewSub').textContent = r.id + ' \u00b7 ' + formatDate(r.date);
  document.getElementById('revenueViewBody').innerHTML = [
    ['Revenue Type', r.revenueType], ['Description', r.description], ['Department', r.department],
    ['Payer / Source', r.payer], ['Reference No.', r.referenceNo || '\u2014'],
    ['Amount', formatPeso(r.amount)], ['Payment Method', r.paymentMethod], ['Status', r.status]
  ].map(row => '<div class="form-static-row"><span>' + row[0] + '</span><span class="val">' + escapeHtml(String(row[1])) + '</span></div>').join('');
  openModal('revenueViewModal');
}

async function exportRevenueCSV() {
  const rows = await getFilteredRevenues();
  exportCSV('revenue-export.csv',
    ['Revenue ID', 'Date', 'Type', 'Description', 'Department', 'Payer', 'Reference No.', 'Amount', 'Payment Method', 'Status'],
    rows.map(r => [r.id, r.date, r.revenueType, r.description, r.department, r.payer, r.referenceNo || '', r.amount, r.paymentMethod, r.status])
  );
}
