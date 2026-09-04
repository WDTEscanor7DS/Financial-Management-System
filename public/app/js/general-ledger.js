/* ==========================================================================
   GENERAL LEDGER
   ========================================================================== */

let _glAccountsForForm = [];
let _glLineCount = 0;

document.addEventListener('DOMContentLoaded', async () => {
  if (!(await initShell('general-ledger'))) return;

  _glAccountsForForm = await getGLAccounts();
  await renderGLTable();

  document.getElementById('addEntryBtn').addEventListener('click', openGLModal);
  document.getElementById('glAddLineBtn').addEventListener('click', () => addGLLineRow());
  document.getElementById('glForm').addEventListener('submit', handleGLSubmit);
  document.getElementById('glFilterBtn').addEventListener('click', renderGLTable);
  document.getElementById('glClearFilterBtn').addEventListener('click', () => {
  document.getElementById('glDateFrom').value = '';
  document.getElementById('glDateTo').value = '';
  renderGLTable();
  });
  document.getElementById('glSearchBox').addEventListener('input', applyGLSearch);
  document.getElementById('trialBalanceBtn').addEventListener('click', openTrialBalance);
  document.getElementById('tbCloseBtn').addEventListener('click', () => closeModal('tbModal'));
  document.getElementById('tbCloseBtn2').addEventListener('click', () => closeModal('tbModal'));
});

/* -------------------------- table (list view) -------------------------- */

async function renderGLTable() {
  const dateFrom = document.getElementById('glDateFrom').value;
  const dateTo = document.getElementById('glDateTo').value;
  const rows = await getJournalEntries({ date_from: dateFrom, date_to: dateTo });

  const totalDebit = rows.reduce((s, r) => s + r.lines.reduce((ls, l) => ls + l.debit, 0), 0);

  document.getElementById('glKpis').innerHTML = [
    { label: 'Total Journal Entries', value: rows.length },
    { label: 'Total Posted (Debit)', value: formatPeso(totalDebit) }
  ].map(k => '<div class="card kpi-box"><div class="summary-label">' + k.label + '</div><div class="summary-value">' + k.value + '</div></div>').join('');

  const tbody = document.querySelector('#glTable tbody');
  if (!rows.length) {
    tbody.innerHTML = '<tr><td colspan="8">' + emptyState('No journal entries recorded yet.') + '</td></tr>';
  } else {
    tbody.innerHTML = rows.map(e => {
      const debitTotal = e.lines.reduce((s, l) => s + l.debit, 0);
      const creditTotal = e.lines.reduce((s, l) => s + l.credit, 0);
      return (
        '<tr>' +
          '<td>' + e.id + '</td><td>' + formatDate(e.entryDate) + '</td><td>' + escapeHtml(e.referenceNo || '\u2014') + '</td>' +
          '<td>' + escapeHtml(e.description) + '</td>' +
          '<td class="amount peso">' + formatPeso(debitTotal) + '</td><td class="amount peso">' + formatPeso(creditTotal) + '</td>' +
          '<td>' + escapeHtml(e.createdBy || '\u2014') + '</td>' +
          '<td><div class="row-actions"><button class="btn btn-ghost btn-sm" onclick="viewGLEntry(\'' + e.id + '\')">View</button></div></td>' +
        '</tr>'
      );
    }).join('');
  }
  document.getElementById('glCount').textContent = rows.length + ' entr' + (rows.length === 1 ? 'y' : 'ies');
  applyGLSearch();
}

function applyGLSearch() {
  const query = document.getElementById('glSearchBox').value.trim().toLowerCase();
  const rows = document.querySelectorAll('#glTable tbody tr');
  rows.forEach(row => {
    const text = row.textContent.toLowerCase();
    row.style.display = (!query || text.includes(query)) ? '' : 'none';
  });
}

/* --------------------------- modal (create form) --------------------------- */

function openGLModal() {
  const form = document.getElementById('glForm');
  form.reset();
  showFormErrors(form, []);
  document.getElementById('glEntryDate').value = todayISO();
  document.getElementById('glLinesBody').innerHTML = '';
  _glLineCount = 0;
  addGLLineRow();
  addGLLineRow();
  updateGLTotals();
  openModal('glModal');
}

function addGLLineRow() {
  _glLineCount++;
  const rowId = 'glLine' + _glLineCount;
  const accountOptions = _glAccountsForForm.map(a =>
    '<option value="' + a.id + '">' + a.account_code + ' — ' + escapeHtml(a.account_name) + '</option>'
  ).join('');

  const row = document.createElement('tr');
  row.id = rowId;
  row.innerHTML =
    '<td><select class="gl-line-account" required><option value="">Select account</option>' + accountOptions + '</select></td>' +
    '<td><input type="number" class="gl-line-debit" min="0" step="0.01" value="0" data-positive-number></td>' +
    '<td><input type="number" class="gl-line-credit" min="0" step="0.01" value="0" data-positive-number></td>' +
    '<td><input type="text" class="gl-line-desc" placeholder="optional"></td>' +
    '<td><button type="button" class="btn btn-ghost btn-sm" onclick="removeGLLineRow(\'' + rowId + '\')">Remove</button></td>';

  document.getElementById('glLinesBody').appendChild(row);

  row.querySelector('.gl-line-debit').addEventListener('input', updateGLTotals);
  row.querySelector('.gl-line-credit').addEventListener('input', updateGLTotals);
}

function removeGLLineRow(rowId) {
  const body = document.getElementById('glLinesBody');
  if (body.children.length <= 2) { toast('A journal entry needs at least 2 lines.', 'danger'); return; }
  document.getElementById(rowId).remove();
  updateGLTotals();
}

function updateGLTotals() {
  const rows = document.querySelectorAll('#glLinesBody tr');
  let totalDebit = 0, totalCredit = 0;
  rows.forEach(r => {
    totalDebit += Number(r.querySelector('.gl-line-debit').value) || 0;
    totalCredit += Number(r.querySelector('.gl-line-credit').value) || 0;
  });

  document.getElementById('glTotalDebit').textContent = formatPeso(totalDebit);
  document.getElementById('glTotalCredit').textContent = formatPeso(totalCredit);

  const balanced = Math.abs(totalDebit - totalCredit) < 0.005 && totalDebit > 0;
  const statusEl = document.getElementById('glBalanceStatus');
  statusEl.textContent = balanced ? 'Balanced ✓' : 'Unbalanced ✗';
  statusEl.style.color = balanced ? 'var(--success, green)' : 'var(--danger, red)';
}

async function handleGLSubmit(e) {
  e.preventDefault();
  const form = e.target;

  const lineRows = document.querySelectorAll('#glLinesBody tr');
  const lines = Array.from(lineRows).map(r => ({
    account_id: Number(r.querySelector('.gl-line-account').value),
    debit: Number(r.querySelector('.gl-line-debit').value) || 0,
    credit: Number(r.querySelector('.gl-line-credit').value) || 0,
    description: r.querySelector('.gl-line-desc').value.trim() || null
  }));

  const errors = [];
  if (!document.getElementById('glEntryDate').value) errors.push('Entry date is required.');
  if (!document.getElementById('glDescription').value.trim()) errors.push('Description is required.');
  if (lines.some(l => !l.account_id)) errors.push('Every line must have an account selected.');

  const totalDebit = lines.reduce((s, l) => s + l.debit, 0);
  const totalCredit = lines.reduce((s, l) => s + l.credit, 0);
  if (Math.abs(totalDebit - totalCredit) >= 0.005) errors.push('Total debit must equal total credit before saving.');

  if (errors.length) { showFormErrors(form, errors); return; }

  const data = {
    entryDate: document.getElementById('glEntryDate').value,
    referenceNo: document.getElementById('glReference').value.trim(),
    description: document.getElementById('glDescription').value.trim(),
    lines
  };

  try {
    await createJournalEntry(data);
    toast('Journal entry recorded successfully.', 'success');
    closeModal('glModal');
    await renderGLTable();
  } catch (err) {
    showFormErrors(form, [err.message]);
  }
}

/* ----------------------------- view (read-only) ----------------------------- */

function viewGLEntry(id) {
  const e = getJournalEntry(id);
  if (!e) return;

  document.getElementById('glViewSub').textContent = e.id + ' \u00b7 ' + formatDate(e.entryDate);

  const linesHtml = e.lines.map(l =>
    '<div class="form-static-row"><span>' + escapeHtml(l.accountCode + ' — ' + l.accountName) + '</span>' +
    '<span class="val">' + (l.debit > 0 ? 'Dr ' + formatPeso(l.debit) : 'Cr ' + formatPeso(l.credit)) + '</span></div>'
  ).join('');

  const statusHtml = e.isReversed
    ? '<div class="form-static-row"><span>Status</span><span class="val" style="color:var(--danger,red)">Reversed</span></div>'
    : e.reversesEntryId
      ? '<div class="form-static-row"><span>Status</span><span class="val">Reversal of ' + e.reversesEntryId + '</span></div>'
      : '';

  document.getElementById('glViewBody').innerHTML =
    '<div class="form-static-row"><span>Reference No.</span><span class="val">' + escapeHtml(e.referenceNo || '\u2014') + '</span></div>' +
    '<div class="form-static-row"><span>Description</span><span class="val">' + escapeHtml(e.description) + '</span></div>' +
    '<div class="form-static-row"><span>Created By</span><span class="val">' + escapeHtml(e.createdBy || '\u2014') + '</span></div>' +
    statusHtml + '<hr style="margin:12px 0;">' + linesHtml;

  const footer = document.querySelector('#glViewModal .modal-foot');
  footer.innerHTML = '<button class="btn btn-outline" id="glViewCloseBtn">Close</button>';
  document.getElementById('glViewCloseBtn').onclick = () => closeModal('glViewModal');

  if (!e.isReversed && !e.reversesEntryId) {
    const reverseBtn = document.createElement('button');
    reverseBtn.className = 'btn btn-danger';
    reverseBtn.textContent = 'Reverse Entry';
    reverseBtn.onclick = async () => {
      if (!confirm('Reverse ' + e.id + '? This will create a new offsetting entry.')) return;
      try {
        await reverseJournalEntry(e.id);
        toast('Entry reversed successfully.', 'success');
        closeModal('glViewModal');
        await renderGLTable();
      } catch (err) {
        toast(err.message, 'danger');
      }
    };
    footer.appendChild(reverseBtn);
  }

  openModal('glViewModal');
}

async function openTrialBalance() {
  const res = await getTrialBalance();

  const tbody = document.querySelector('#tbTable tbody');
  tbody.innerHTML = res.data.map(a =>
    '<tr><td>' + a.accountCode + '</td><td>' + escapeHtml(a.accountName) + '</td>' +
    '<td class="amount peso">' + formatPeso(a.totalDebit) + '</td>' +
    '<td class="amount peso">' + formatPeso(a.totalCredit) + '</td>' +
    '<td class="amount peso">' + formatPeso(a.netBalance) + '</td></tr>'
  ).join('');

  document.getElementById('tbGrandTotal').textContent =
    'Dr ' + formatPeso(res.grandTotalDebit) + '  /  Cr ' + formatPeso(res.grandTotalCredit);

  const statusEl = document.getElementById('tbStatus');
  statusEl.textContent = res.isBalanced ? 'Balanced ✓' : 'Unbalanced ✗ (check for a bug)';
  statusEl.style.color = res.isBalanced ? 'var(--success, green)' : 'var(--danger, red)';

  openModal('tbModal');
}