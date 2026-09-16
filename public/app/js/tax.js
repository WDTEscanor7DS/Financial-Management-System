/* ==========================================================================
   TAX
   ========================================================================== */

let _taxTypesList = [];
let _taxBankAccounts = [];

document.addEventListener('DOMContentLoaded', async () => {
  if (!(await initShell('tax'))) return;

  _taxTypesList = await getTaxTypes();
  _taxBankAccounts = await getCashBankAccounts();

  renderTaxTypesTable();
  await renderRemittancesTable();

  document.getElementById('addRemittanceBtn').addEventListener('click', openRemittanceModal);
  document.getElementById('remittanceForm').addEventListener('submit', handleRemittanceSubmit);
});

function renderTaxTypesTable() {
  const tbody = document.querySelector('#taxTypesTable tbody');
  if (!_taxTypesList.length) {
    tbody.innerHTML = '<tr><td colspan="4">' + emptyState('No tax types configured.') + '</td></tr>';
    return;
  }
  tbody.innerHTML = _taxTypesList.map(t =>
    '<tr><td>' + escapeHtml(t.code) + '</td><td>' + escapeHtml(t.name) + '</td>' +
    '<td>' + escapeHtml(t.birFormNo || '\u2014') + '</td><td>' + escapeHtml(t.accountCode) + '</td></tr>'
  ).join('');
}

async function renderRemittancesTable() {
  const remittances = await getTaxRemittances();
  const tbody = document.querySelector('#remittancesTable tbody');

  if (!remittances.length) {
    tbody.innerHTML = '<tr><td colspan="8">' + emptyState('No tax remittances recorded yet.') + '</td></tr>';
  } else {
    tbody.innerHTML = remittances.map(r =>
      '<tr><td>' + r.id + '</td><td>' + formatDate(r.remittanceDate) + '</td>' +
      '<td>' + escapeHtml(r.taxTypeName) + '</td><td>' + escapeHtml(r.periodCovered) + '</td>' +
      '<td>' + escapeHtml(r.birReferenceNo || '\u2014') + '</td><td>' + escapeHtml(r.bankAccountName) + '</td>' +
      '<td class="amount peso">' + formatPeso(r.amount) + '</td><td>' + (r.journalEntryId || '\u2014') + '</td></tr>'
    ).join('');
  }
  document.getElementById('remittancesCount').textContent = remittances.length + ' remittance' + (remittances.length === 1 ? '' : 's');
}

function openRemittanceModal() {
  const form = document.getElementById('remittanceForm');
  form.reset();
  showFormErrors(form, []);
  document.getElementById('rtDate').value = todayISO();

  document.getElementById('rtTaxType').innerHTML = _taxTypesList.map(t =>
    '<option value="' + t.id + '">' + escapeHtml(t.name) + ' (' + t.accountCode + ')</option>'
  ).join('');

  document.getElementById('rtBankAccount').innerHTML = _taxBankAccounts.map(a =>
    '<option value="' + a.id + '">' + escapeHtml(a.accountName) + ' (' + formatPeso(a.currentBalance) + ')</option>'
  ).join('');

  openModal('remittanceModal');
}

async function handleRemittanceSubmit(e) {
  e.preventDefault();
  const form = e.target;

  const data = {
    taxTypeId: Number(document.getElementById('rtTaxType').value),
    periodCovered: document.getElementById('rtPeriod').value.trim(),
    remittanceDate: document.getElementById('rtDate').value,
    amount: Number(document.getElementById('rtAmount').value) || 0,
    birReferenceNo: document.getElementById('rtReference').value.trim(),
    bankAccountId: Number(document.getElementById('rtBankAccount').value),
  };

  try {
    await createTaxRemittance(data);
    toast('Tax remittance recorded and posted to the ledger.', 'success');
    closeModal('remittanceModal');
    _taxBankAccounts = await getCashBankAccounts(); // refresh balances shown next time modal opens
    await renderRemittancesTable();
  } catch (err) {
    showFormErrors(form, [err.message]);
  }
}