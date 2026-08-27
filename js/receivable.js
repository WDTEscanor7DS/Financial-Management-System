/* ==========================================================================
   ACCOUNTS RECEIVABLE MANAGEMENT
   ========================================================================== */

document.addEventListener('DOMContentLoaded', async () => {
  if (!(await initShell('accounts-receivable'))) return;

  await renderArTable();

  document.getElementById('addReceivableBtn').addEventListener('click', () => openArModal());
  document.getElementById('arForm').addEventListener('submit', handleArSubmit);
  document.getElementById('arPaymentForm').addEventListener('submit', handleArPaymentSubmit);
  ['arSearch', 'arStatusFilter'].forEach(id => document.getElementById(id).addEventListener('input', renderArTable));
  document.getElementById('arResetFilters').addEventListener('click', () => {
    ['arSearch', 'arStatusFilter'].forEach(id => document.getElementById(id).value = '');
    renderArTable();
  });
});

async function getFilteredReceivables() {
  const search = document.getElementById('arSearch').value;
  const status = document.getElementById('arStatusFilter').value;
  const all = await getReceivables();
  return filterRows(all, search, ['customer', 'referenceNo', 'description'], (r) => (!status || r.status === status))
    .sort((a, b) => new Date(a.dueDate) - new Date(b.dueDate));
}

async function renderArTable() {
  const receivables = await getReceivables();
  const buckets = { 'Current': 0, '1-30 Days': 0, '31-60 Days': 0, '61-90 Days': 0, '90+ Days': 0 };
  receivables.filter(r => r.status !== 'Paid').forEach(r => { const b = receivableAgingBucket(r); if (buckets.hasOwnProperty(b)) buckets[b] += Number(r.balance); });

  document.getElementById('arAgingGrid').innerHTML =
    ['Current', '1-30 Days', '31-60 Days', '61-90 Days', '90+ Days'].map((label, i) => (
      '<div class="card aging-box ' + ['current', 'b1', 'b2', 'b3', 'b4'][i] + '"><div class="bucket-label">' + label + '</div><div class="bucket-value peso">' + formatPeso(buckets[label]) + '</div></div>'
    )).join('');

  const rows = await getFilteredReceivables();
  const tbody = document.querySelector('#arTable tbody');
  if (!rows.length) {
    tbody.innerHTML = '<tr><td colspan="9">' + emptyState('No receivables match your filters.') + '</td></tr>';
  } else {
    tbody.innerHTML = rows.map(r => {
      const overdue = r.status !== 'Paid' && receivableAgingBucket(r) !== 'Current';
      const displayStatus = overdue ? 'Overdue' : r.status;
      return (
        '<tr>' +
          '<td>' + r.id + '</td><td>' + escapeHtml(r.customer) + '</td><td>' + escapeHtml(r.referenceNo || '\u2014') + '</td>' +
          '<td>' + formatDate(r.dueDate) + '</td>' +
          '<td class="amount peso">' + formatPeso(r.amount) + '</td><td class="amount peso">' + formatPeso(r.amountPaid) + '</td>' +
          '<td class="amount peso">' + formatPeso(r.balance) + '</td><td>' + statusBadge(displayStatus) + '</td>' +
          '<td><div class="row-actions">' +
            (r.status !== 'Paid' && userCan('record_receivable_payment') ? '<button class="btn btn-success btn-sm" onclick="openArPaymentModal(\'' + r.id + '\')">Collect</button>' : '') +
            (userCan('delete_accounts_receivable') ? '<button class="btn btn-danger btn-sm" onclick="handleDeleteReceivable(\'' + r.id + '\')">Delete</button>' : '') +
          '</div></td>' +
        '</tr>'
      );
    }).join('');
  }
  document.getElementById('arCount').textContent = rows.length + ' record' + (rows.length === 1 ? '' : 's');
}

function openArModal() {
  const form = document.getElementById('arForm');
  form.reset();
  showFormErrors(form, []);
  document.getElementById('arInvoiceDate').value = todayISO();
  openModal('arModal');
}

async function handleArSubmit(e) {
  e.preventDefault();
  const form = e.target;
  const errors = validateForm(form);
  if (errors.length) { showFormErrors(form, errors); return; }

  try {
    await createReceivable({
      customer: document.getElementById('arCustomer').value.trim(),
      referenceNo: document.getElementById('arReference').value.trim(),
      invoiceDate: document.getElementById('arInvoiceDate').value,
      dueDate: document.getElementById('arDueDate').value,
      description: document.getElementById('arDescription').value.trim(),
      amount: Number(document.getElementById('arAmount').value)
    });
    toast('Receivable added successfully.', 'success');
    closeModal('arModal');
    await renderArTable();
  } catch (err) {
    showFormErrors(form, [err.message]);
  }
}

async function handleDeleteReceivable(id) {
  if (!confirm('Delete receivable ' + id + '? This cannot be undone.')) return;
  try {
    await deleteReceivable(id);
    toast('Receivable deleted.', 'success');
    await renderArTable();
  } catch (err) {
    toast(err.message, 'danger');
  }
}

function openArPaymentModal(id) {
  const r = getReceivable(id);
  document.getElementById('arPaymentForm').reset();
  showFormErrors(document.getElementById('arPaymentForm'), []);
  document.getElementById('arPaymentId').value = r.id;
  document.getElementById('arPaymentSub').textContent = r.id + ' \u00b7 ' + r.customer;
  document.getElementById('arPaymentBalance').textContent = formatPeso(r.balance);
  document.getElementById('arPaymentAmount').max = r.balance;
  openModal('arPaymentModal');
}

async function handleArPaymentSubmit(e) {
  e.preventDefault();
  const form = e.target;
  const errors = validateForm(form);
  const id = document.getElementById('arPaymentId').value;
  const r = getReceivable(id);
  const amount = Number(document.getElementById('arPaymentAmount').value);
  if (amount > r.balance) errors.push('Collection amount cannot exceed the outstanding balance of ' + formatPeso(r.balance) + '.');
  if (errors.length) { showFormErrors(form, errors); return; }

  try {
    const result = await recordReceivablePayment(id, amount);
    if (result && result.error) { showFormErrors(form, [result.error]); return; }

    toast('Collection recorded for ' + id + '.', 'success');
    closeModal('arPaymentModal');
    await renderArTable();
  } catch (err) {
    showFormErrors(form, [err.message]);
  }
}
