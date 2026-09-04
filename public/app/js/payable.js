/* ==========================================================================
   ACCOUNTS PAYABLE MANAGEMENT
   ========================================================================== */

document.addEventListener('DOMContentLoaded', async () => {
  if (!(await initShell('accounts-payable'))) return;

  await populateApFilters();
  await renderApTable();

  document.getElementById('addPayableBtn').addEventListener('click', () => openApModal());
  document.getElementById('apForm').addEventListener('submit', handleApSubmit);
  document.getElementById('apPaymentForm').addEventListener('submit', handleApPaymentSubmit);
  ['apSearch', 'apDeptFilter', 'apStatusFilter'].forEach(id => document.getElementById(id).addEventListener('input', renderApTable));
  document.getElementById('apResetFilters').addEventListener('click', () => {
    ['apSearch', 'apDeptFilter', 'apStatusFilter'].forEach(id => document.getElementById(id).value = '');
    renderApTable();
  });
});

async function populateApFilters() {
  const deptSel = document.getElementById('apDepartment');
  const deptFilter = document.getElementById('apDeptFilter');
  (await getDepartments()).forEach(d => {
    deptSel.insertAdjacentHTML('beforeend', '<option value="' + d + '">' + d + '</option>');
    deptFilter.insertAdjacentHTML('beforeend', '<option value="' + d + '">' + d + '</option>');
  });
}

async function getFilteredPayables() {
  const search = document.getElementById('apSearch').value;
  const dept = document.getElementById('apDeptFilter').value;
  const status = document.getElementById('apStatusFilter').value;
  const all = await getPayables();
  return filterRows(all, search, ['vendor', 'invoiceNo', 'description'], (p) =>
    (!dept || p.department === dept) && (!status || p.status === status)
  ).sort((a, b) => new Date(a.dueDate) - new Date(b.dueDate));
}

async function renderApTable() {
  const payables = await getPayables();
  const buckets = { 'Current': 0, '1-30 Days': 0, '31-60 Days': 0, '61-90 Days': 0, '90+ Days': 0 };
  payables.filter(p => p.status !== 'Paid').forEach(p => { const b = payableAgingBucket(p); if (buckets.hasOwnProperty(b)) buckets[b] += Number(p.balance); });

  document.getElementById('apAgingGrid').innerHTML =
    ['Current', '1-30 Days', '31-60 Days', '61-90 Days', '90+ Days'].map((label, i) => (
      '<div class="card aging-box ' + ['current', 'b1', 'b2', 'b3', 'b4'][i] + '"><div class="bucket-label">' + label + '</div><div class="bucket-value peso">' + formatPeso(buckets[label]) + '</div></div>'
    )).join('');

  const rows = await getFilteredPayables();
  const tbody = document.querySelector('#apTable tbody');
  if (!rows.length) {
    tbody.innerHTML = '<tr><td colspan="10">' + emptyState('No payables match your filters.') + '</td></tr>';
  } else {
    tbody.innerHTML = rows.map(p => {
      const overdue = p.status !== 'Paid' && payableAgingBucket(p) !== 'Current';
      const displayStatus = overdue ? 'Overdue' : p.status;
      return (
        '<tr>' +
          '<td>' + p.id + '</td><td>' + escapeHtml(p.vendor) + '</td><td>' + escapeHtml(p.invoiceNo) + '</td>' +
          '<td>' + formatDate(p.dueDate) + '</td><td>' + escapeHtml(p.department) + '</td>' +
          '<td class="amount peso">' + formatPeso(p.amount) + '</td><td class="amount peso">' + formatPeso(p.amountPaid) + '</td>' +
          '<td class="amount peso">' + formatPeso(p.balance) + '</td><td>' + statusBadge(displayStatus) + '</td>' +
          '<td><div class="row-actions">' +
            (p.status !== 'Paid' && userCan('record_payable_payment') ? '<button class="btn btn-success btn-sm" onclick="openApPaymentModal(\'' + p.id + '\')">Pay</button>' : '') +
            (userCan('delete_accounts_payable') ? '<button class="btn btn-danger btn-sm" onclick="handleDeletePayable(\'' + p.id + '\')">Delete</button>' : '') +
          '</div></td>' +
        '</tr>'
      );
    }).join('');
  }
  document.getElementById('apCount').textContent = rows.length + ' record' + (rows.length === 1 ? '' : 's');
}

function openApModal() {
  const form = document.getElementById('apForm');
  form.reset();
  showFormErrors(form, []);
  document.getElementById('apInvoiceDate').value = todayISO();
  openModal('apModal');
}

async function handleApSubmit(e) {
  e.preventDefault();
  const form = e.target;
  const errors = validateForm(form);
  if (errors.length) { showFormErrors(form, errors); return; }

  try {
    await createPayable({
      vendor: document.getElementById('apVendor').value.trim(),
      invoiceNo: document.getElementById('apInvoiceNo').value.trim(),
      invoiceDate: document.getElementById('apInvoiceDate').value,
      dueDate: document.getElementById('apDueDate').value,
      description: document.getElementById('apDescription').value.trim(),
      department: document.getElementById('apDepartment').value,
      amount: Number(document.getElementById('apAmount').value)
    });
    toast('Payable added successfully.', 'success');
    closeModal('apModal');
    await renderApTable();
  } catch (err) {
    showFormErrors(form, [err.message]);
  }
}

async function handleDeletePayable(id) {
  if (!confirm('Delete payable ' + id + '? This cannot be undone.')) return;
  try {
    await deletePayable(id);
    toast('Payable deleted.', 'success');
    await renderApTable();
  } catch (err) {
    toast(err.message, 'danger');
  }
}

function openApPaymentModal(id) {
  const p = getPayable(id);
  document.getElementById('apPaymentForm').reset();
  showFormErrors(document.getElementById('apPaymentForm'), []);
  document.getElementById('apPaymentId').value = p.id;
  document.getElementById('apPaymentSub').textContent = p.id + ' \u00b7 ' + p.vendor;
  document.getElementById('apPaymentBalance').textContent = formatPeso(p.balance);
  document.getElementById('apPaymentAmount').max = p.balance;
  openModal('apPaymentModal');
}

async function handleApPaymentSubmit(e) {
  e.preventDefault();
  const form = e.target;
  const errors = validateForm(form);
  const id = document.getElementById('apPaymentId').value;
  const p = getPayable(id);
  const amount = Number(document.getElementById('apPaymentAmount').value);
  if (amount > p.balance) errors.push('Payment amount cannot exceed the outstanding balance of ' + formatPeso(p.balance) + '.');
  if (errors.length) { showFormErrors(form, errors); return; }

  try {
    const result = await recordPayablePayment(id, amount);
    if (result && result.error) { showFormErrors(form, [result.error]); return; }

    toast('Payment recorded for ' + id + '.', 'success');
    closeModal('apPaymentModal');
    await renderApTable();
  } catch (err) {
    showFormErrors(form, [err.message]);
  }
}
