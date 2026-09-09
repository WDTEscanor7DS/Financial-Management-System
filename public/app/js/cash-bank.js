/* ==========================================================================
   CASH AND BANK
   ========================================================================== */

let _cbAccounts = [];
let _cbGLAccounts = [];

document.addEventListener('DOMContentLoaded', async () => {
  if (!(await initShell('cash-bank'))) return;

  _cbGLAccounts = await getGLAccounts();
  await loadCashBankData();

  document.getElementById('addAccountBtn').addEventListener('click', openAccountModal);
  document.getElementById('addTransactionBtn').addEventListener('click', openTxModal);
  document.getElementById('cbAccountForm').addEventListener('submit', handleAccountSubmit);
  document.getElementById('cbTxForm').addEventListener('submit', handleTxSubmit);
  document.getElementById('cbAccType').addEventListener('change', toggleAccountTypeFields);
  document.getElementById('cbTxType').addEventListener('change', toggleTxTypeFields);
  document.getElementById('cbAccountFilter').addEventListener('change', renderTransactionsTable);
});


async function loadCashBankData() {
  _cbAccounts = await getCashBankAccounts();
  renderAccountsTable();
  populateAccountFilter();
  await renderTransactionsTable();
}

function populateAccountFilter() {
  const select = document.getElementById('cbAccountFilter');
  const currentValue = select.value;
  select.innerHTML = '<option value="">All Accounts</option>' + _cbAccounts.map(a =>
    '<option value="' + a.id + '">' + escapeHtml(a.accountName) + '</option>'
  ).join('');
  select.value = currentValue;
}

/* ------------------------------- accounts table ------------------------------- */

function renderAccountsTable() {
  const totalBalance = _cbAccounts.reduce((s, a) => s + a.currentBalance, 0);

  document.getElementById('cbKpis').innerHTML = [
    { label: 'Total Accounts', value: _cbAccounts.length },
    { label: 'Combined Balance', value: formatPeso(totalBalance) }
  ].map(k => '<div class="card kpi-box"><div class="summary-label">' + k.label + '</div><div class="summary-value">' + k.value + '</div></div>').join('');

  const tbody = document.querySelector('#cbAccountsTable tbody');
  if (!_cbAccounts.length) {
    tbody.innerHTML = '<tr><td colspan="5">' + emptyState('No bank or cash accounts yet.') + '</td></tr>';
    return;
  }
  tbody.innerHTML = _cbAccounts.map(a =>
    '<tr><td>' + escapeHtml(a.accountName) + '</td><td>' + a.accountType + '</td>' +
    '<td>' + escapeHtml(a.bankName || '\u2014') + '</td><td>' + escapeHtml(a.accountNumber || '\u2014') + '</td>' +
    '<td class="amount peso">' + formatPeso(a.currentBalance) + '</td></tr>'
  ).join('');
}

/* ---------------------------- transactions table ---------------------------- */

async function renderTransactionsTable() {
  const filterAccountId = document.getElementById('cbAccountFilter').value || null;
  const transactions = await getCashTransactions(filterAccountId);
  const tbody = document.querySelector('#cbTransactionsTable tbody');
  if (!transactions.length) {
    tbody.innerHTML = '<tr><td colspan="7">' + emptyState('No transactions recorded yet.') + '</td></tr>';
  } else {
    tbody.innerHTML = transactions.map(t => {
      const accountLabel = t.type === 'Transfer'
        ? t.bankAccountName + ' \u2192 ' + t.transferToAccountName
        : t.bankAccountName;
      return (
        '<tr><td>' + t.id + '</td><td>' + formatDate(t.transactionDate) + '</td><td>' + t.type + '</td>' +
        '<td>' + escapeHtml(accountLabel) + '</td><td>' + escapeHtml(t.description) + '</td>' +
        '<td class="amount peso">' + formatPeso(t.amount) + '</td>' +
        '<td>' + (t.journalEntryId || '\u2014') + '</td></tr>'
      );
    }).join('');
  }
  document.getElementById('cbTxCount').textContent = transactions.length + ' transaction' + (transactions.length === 1 ? '' : 's');
}

/* --------------------------- account modal (create) --------------------------- */

function openAccountModal() {
  const form = document.getElementById('cbAccountForm');
  form.reset();
  showFormErrors(form, []);

  const coaSelect = document.getElementById('cbAccChartOfAccount');
  coaSelect.innerHTML = _cbGLAccounts.map(a =>
    '<option value="' + a.id + '">' + a.account_code + ' — ' + escapeHtml(a.account_name) + '</option>'
  ).join('');

  toggleAccountTypeFields();
  openModal('cbAccountModal');
}

function toggleAccountTypeFields() {
  const isCash = document.getElementById('cbAccType').value === 'Cash';
  document.getElementById('cbBankNameField').style.display = isCash ? 'none' : '';
  document.getElementById('cbAccNumberField').style.display = isCash ? 'none' : '';
}

async function handleAccountSubmit(e) {
  e.preventDefault();
  const form = e.target;

  const data = {
    accountName: document.getElementById('cbAccName').value.trim(),
    accountType: document.getElementById('cbAccType').value,
    bankName: document.getElementById('cbAccBankName').value.trim(),
    accountNumber: document.getElementById('cbAccNumber').value.trim(),
    chartOfAccountId: Number(document.getElementById('cbAccChartOfAccount').value),
    openingBalance: Number(document.getElementById('cbAccOpeningBalance').value) || 0,
  };

  try {
    await createCashBankAccount(data);
    toast('Account created successfully.', 'success');
    closeModal('cbAccountModal');
    await loadCashBankData();
  } catch (err) {
    showFormErrors(form, [err.message]);
  }
}

/* ------------------------- transaction modal (create) ------------------------- */

function openTxModal() {
  const form = document.getElementById('cbTxForm');
  form.reset();
  showFormErrors(form, []);
  document.getElementById('cbTxDate').value = todayISO();

  populateAccountDropdown('cbTxAccount');
  populateAccountDropdown('cbTxTransferTo');
  document.getElementById('cbTxContraAccount').innerHTML = _cbGLAccounts.map(a =>
    '<option value="' + a.id + '">' + a.account_code + ' — ' + escapeHtml(a.account_name) + '</option>'
  ).join('');

  toggleTxTypeFields();
  openModal('cbTxModal');
}

function populateAccountDropdown(selectId) {
  document.getElementById(selectId).innerHTML = _cbAccounts.map(a =>
    '<option value="' + a.id + '">' + escapeHtml(a.accountName) + ' (' + formatPeso(a.currentBalance) + ')</option>'
  ).join('');
}

function toggleTxTypeFields() {
  const type = document.getElementById('cbTxType').value;
  const isTransfer = type === 'Transfer';

  document.getElementById('cbTxContraField').style.display = isTransfer ? 'none' : '';
  document.getElementById('cbTxTransferToField').hidden = !isTransfer;
  document.getElementById('cbTxAccountLabel').textContent = isTransfer ? 'From Account' : 'Account';
  document.getElementById('cbTxAccountLabel').innerHTML += '<span class="req">*</span>';
}

async function handleTxSubmit(e) {
  e.preventDefault();
  const form = e.target;
  const type = document.getElementById('cbTxType').value;

  const data = {
    type,
    transactionDate: document.getElementById('cbTxDate').value,
    bankAccountId: Number(document.getElementById('cbTxAccount').value),
    amount: Number(document.getElementById('cbTxAmount').value) || 0,
    referenceNo: document.getElementById('cbTxReference').value.trim(),
    description: document.getElementById('cbTxDescription').value.trim(),
  };

  if (type === 'Transfer') {
    data.transferToAccountId = Number(document.getElementById('cbTxTransferTo').value);
  } else {
    data.contraAccountId = Number(document.getElementById('cbTxContraAccount').value);
  }

  const errors = [];
  if (!data.bankAccountId) errors.push('Please select an account.');
  if (data.amount <= 0) errors.push('Amount must be greater than zero.');
  if (type === 'Transfer' && data.transferToAccountId === data.bankAccountId) {
    errors.push('Transfer destination must be different from the source account.');
  }
  if (errors.length) { showFormErrors(form, errors); return; }

  try {
    await createCashTransaction(data);
    toast('Transaction recorded and posted to General Ledger.', 'success');
    closeModal('cbTxModal');
    await loadCashBankData();
  } catch (err) {
    showFormErrors(form, [err.message]);
  }
}