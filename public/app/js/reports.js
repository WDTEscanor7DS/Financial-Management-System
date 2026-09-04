/* ==========================================================================
   FINANCIAL REPORTING AND COMPLIANCE
   ========================================================================== */

document.addEventListener('DOMContentLoaded', async () => {
  if (!(await initShell('reports'))) return;

  await populateReportFilters();
  await renderIncomeStatement();
  await renderBudgetReport();
  await renderAgingReports();
  await renderCashFlow();
  await renderExpenseReport();
  await renderRevenueReport();

  document.querySelectorAll('#reportTabs .tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('#reportTabs .tab-btn').forEach(b => b.classList.remove('active'));
      document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
      btn.classList.add('active');
      document.getElementById('panel-' + btn.getAttribute('data-tab')).classList.add('active');
    });
  });

  document.getElementById('printReportBtn').addEventListener('click', () => window.print());

  ['expReportDate', 'expReportDept', 'expReportCategory'].forEach(id => document.getElementById(id).addEventListener('input', renderExpenseReport));
  document.getElementById('expReportReset').addEventListener('click', () => {
    ['expReportDate', 'expReportDept', 'expReportCategory'].forEach(id => document.getElementById(id).value = '');
    renderExpenseReport();
  });
  document.getElementById('expReportExport').addEventListener('click', async () => {
    const rows = await getFilteredExpenseReport();
    exportCSV('expense-report.csv', ['Date', 'Department', 'Category', 'Description', 'Amount'], rows.map(r => [r.date, r.department, r.category, r.description, r.amount]));
  });

  ['revReportDate', 'revReportDept', 'revReportType'].forEach(id => document.getElementById(id).addEventListener('input', renderRevenueReport));
  document.getElementById('revReportReset').addEventListener('click', () => {
    ['revReportDate', 'revReportDept', 'revReportType'].forEach(id => document.getElementById(id).value = '');
    renderRevenueReport();
  });
  document.getElementById('revReportExport').addEventListener('click', async () => {
    const rows = await getFilteredRevenueReport();
    exportCSV('revenue-report.csv', ['Date', 'Department', 'Type', 'Description', 'Amount'], rows.map(r => [r.date, r.department, r.type, r.description, r.amount]));
  });
});

async function populateReportFilters() {
  const departments = await getDepartments();
  ['expReportDept', 'revReportDept'].forEach(id => {
    const sel = document.getElementById(id);
    departments.forEach(d => sel.insertAdjacentHTML('beforeend', '<option value="' + d + '">' + d + '</option>'));
  });
}

async function renderIncomeStatement() {
  const t = await getIncomeStatement();
  document.getElementById('incRevenue').textContent = formatPeso(t.revenue);
  document.getElementById('incExpenses').textContent = '(' + formatPeso(t.expenses) + ')';
  document.getElementById('incNet').textContent = formatPeso(t.net);
}

async function renderBudgetReport() {
  const rows = await getBudgetVsActualReport();
  document.querySelector('#budgetReportTable tbody').innerHTML = rows.map(b => {
    const util = b.utilization_pct;
    return (
      '<tr><td>' + escapeHtml(b.department) + '</td><td>' + escapeHtml(b.category) + '</td>' +
      '<td class="amount peso">' + formatPeso(b.allocated) + '</td><td class="amount peso">' + formatPeso(b.actual) + '</td>' +
      '<td class="amount peso ' + (b.variance < 0 ? 'text-danger' : 'text-success') + '">' + formatPeso(b.variance) + '</td>' +
      '<td><div class="progress-row"><div class="progress-track"><div class="progress-fill ' + (util >= 100 ? 'danger' : util >= 80 ? 'warning' : '') + '" style="width:' + Math.min(100, util) + '%"></div></div><span class="progress-pct">' + util + '%</span></div></td></tr>'
    );
  }).join('');
}

async function renderAgingReports() {
  const aging = await getAgingReport();

  const renderGrid = (buckets) => ['Current', '1-30 Days', '31-60 Days', '61-90 Days', '90+ Days'].map((label, i) => (
    '<div class="card aging-box ' + ['current', 'b1', 'b2', 'b3', 'b4'][i] + '"><div class="bucket-label">' + label + '</div><div class="bucket-value peso">' + formatPeso(buckets[label]) + '</div></div>'
  )).join('');

  document.getElementById('apAgingReport').innerHTML = renderGrid(aging.ap);
  document.getElementById('arAgingReport').innerHTML = renderGrid(aging.ar);
}

async function renderCashFlow() {
  const cf = await getCashFlowReport(0);
  document.getElementById('cfBeginning').textContent = formatPeso(cf.beginning_balance);
  document.getElementById('cfInflows').textContent = formatPeso(cf.inflows);
  document.getElementById('cfOutflows').textContent = '(' + formatPeso(cf.outflows) + ')';
  document.getElementById('cfEnding').textContent = formatPeso(cf.ending_balance);
}

async function getFilteredExpenseReport() {
  const date = document.getElementById('expReportDate').value;
  const deptName = document.getElementById('expReportDept').value;
  const category = document.getElementById('expReportCategory').value;
  const departmentId = deptName ? await _departmentIdByName(deptName) : '';
  const rows = await getExpenseReport({ date, departmentId, category });
  return rows;
}
async function renderExpenseReport() {
  const rows = await getFilteredExpenseReport();
  document.querySelector('#expReportTable tbody').innerHTML = rows.length
    ? rows.map(e => '<tr><td>' + formatDate(e.date) + '</td><td>' + escapeHtml(e.department) + '</td><td>' + escapeHtml(e.category) + '</td><td>' + escapeHtml(e.description) + '</td><td class="amount peso">' + formatPeso(e.amount) + '</td></tr>').join('')
    : '<tr><td colspan="5">' + emptyState('No expenses match your filters.') + '</td></tr>';
  document.getElementById('expReportTotal').textContent = rows.length + ' record(s) — total ' + formatPeso(rows.reduce((s, r) => s + Number(r.amount), 0));
}

async function getFilteredRevenueReport() {
  const date = document.getElementById('revReportDate').value;
  const deptName = document.getElementById('revReportDept').value;
  const type = document.getElementById('revReportType').value;
  const departmentId = deptName ? await _departmentIdByName(deptName) : '';
  return getRevenueReport({ date, departmentId, type });
}
async function renderRevenueReport() {
  const rows = await getFilteredRevenueReport();
  document.querySelector('#revReportTable tbody').innerHTML = rows.length
    ? rows.map(r => '<tr><td>' + formatDate(r.date) + '</td><td>' + escapeHtml(r.department) + '</td><td>' + escapeHtml(r.type) + '</td><td>' + escapeHtml(r.description) + '</td><td class="amount peso">' + formatPeso(r.amount) + '</td></tr>').join('')
    : '<tr><td colspan="5">' + emptyState('No revenue records match your filters.') + '</td></tr>';
  document.getElementById('revReportTotal').textContent = rows.length + ' record(s) — total ' + formatPeso(rows.reduce((s, r) => s + Number(r.amount), 0));
}
