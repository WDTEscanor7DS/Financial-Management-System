/* ==========================================================================
   DASHBOARD LOGIC
   ========================================================================== */

document.addEventListener('DOMContentLoaded', async () => {
  if (!(await initShell('dashboard'))) return;

  const user = getCurrentUser();
  document.getElementById('welcomeName').textContent = ', ' + user.name.split(' ')[0];

  await Promise.all([
    renderSummaryCards(),
    renderCharts(),
    renderRecentTransactions(),
    renderPendingActions()
  ]);
});

async function renderSummaryCards() {
  const t = await getDashboardTotals();
  const cards = [
    { label: 'Total Revenue', value: t.totalRevenue, icon: 'trend-up' },
    { label: 'Total Expenses', value: t.totalExpenses, icon: 'trend-down' },
    { label: 'Net Income', value: t.netIncome, icon: 'grid' },
    { label: 'Outstanding Receivables', value: t.outstandingReceivables, icon: 'inbox' },
    { label: 'Outstanding Payables', value: t.outstandingPayables, icon: 'outbox' },
    { label: 'Available Funds', value: t.availableFunds, icon: 'vault' },
    { label: 'Total Assets (Book Value)', value: t.totalAssets, icon: 'box' },
    { label: 'Budget Utilization', value: t.budgetUtilizationPct + '%', icon: 'pie', raw: true }
  ];
  document.getElementById('summaryGrid').innerHTML = cards.map(c => (
    '<div class="card summary-card">' +
      '<div class="summary-label">' + c.label + icon(c.icon) + '</div>' +
      '<div class="summary-value">' + (c.raw ? c.value : formatPeso(c.value)) + '</div>' +
    '</div>'
  )).join('');
}

async function renderCharts() {
  const [revenues, expenses, funds, budgets, receivables, payables] = await Promise.all([
    getRevenues(), getExpenses(), getFunds(), getBudgets(), getReceivables(), getPayables()
  ]);

  const months = last6Months();
  const revByMonth = months.map(m => sumByMonth(revenues, m));
  const expByMonth = months.map(m => sumByMonth(expenses, m));

  new Chart(document.getElementById('chartRevExp'), {
    type: 'bar',
    data: {
      labels: months.map(m => m.label),
      datasets: [
        { label: 'Revenue', data: revByMonth, backgroundColor: '#1F8A5F', borderRadius: 4, maxBarThickness: 28 },
        { label: 'Expenses', data: expByMonth, backgroundColor: '#C0392B', borderRadius: 4, maxBarThickness: 28 }
      ]
    },
    options: chartBaseOptions({ stacked: false })
  });

  new Chart(document.getElementById('chartFunds'), {
    type: 'doughnut',
    data: {
      labels: funds.map(f => f.name),
      datasets: [{ data: funds.map(f => f.remaining), backgroundColor: ['#1C6E76', '#B8863A', '#2A5578', '#1F8A5F', '#8695A5'] }]
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10.5 } } } } }
  });

  new Chart(document.getElementById('chartBudget'), {
    type: 'bar',
    data: {
      labels: budgets.map(b => b.department),
      datasets: [
        { label: 'Allocated', data: budgets.map(b => b.allocated), backgroundColor: '#CDD5DE', borderRadius: 3 },
        { label: 'Actual', data: budgets.map(b => b.actualSpending), backgroundColor: '#12293F', borderRadius: 3 }
      ]
    },
    options: chartBaseOptions({ small: true })
  });

  const arBuckets = agingBuckets(receivables, receivableAgingBucket);
  new Chart(document.getElementById('chartAR'), {
    type: 'pie',
    data: { labels: Object.keys(arBuckets), datasets: [{ data: Object.values(arBuckets), backgroundColor: ['#1F8A5F', '#2A5578', '#B8792B', '#C0392B', '#7A2418'] }] },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10.5 } } } } }
  });

  const apBuckets = agingBuckets(payables, payableAgingBucket);
  new Chart(document.getElementById('chartAP'), {
    type: 'pie',
    data: { labels: Object.keys(apBuckets), datasets: [{ data: Object.values(apBuckets), backgroundColor: ['#1F8A5F', '#2A5578', '#B8792B', '#C0392B', '#7A2418'] }] },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10.5 } } } } }
  });
}

function chartBaseOptions(opts) {
  opts = opts || {};
  return {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10.5 } } } },
    scales: {
      x: { grid: { display: false }, ticks: { font: { size: opts.small ? 10 : 10.5 } } },
      y: { grid: { color: '#EEF1F4' }, ticks: { font: { size: 10 }, callback: v => '\u20b1' + (v / 1000) + 'k' } }
    }
  };
}

function last6Months() {
  const arr = [];
  const now = new Date();
  for (let i = 5; i >= 0; i--) {
    const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
    arr.push({ year: d.getFullYear(), month: d.getMonth(), label: d.toLocaleDateString('en-PH', { month: 'short' }) });
  }
  return arr;
}
function sumByMonth(records, m) {
  return records.filter(r => {
    const d = new Date(r.date);
    return d.getFullYear() === m.year && d.getMonth() === m.month;
  }).reduce((s, r) => s + Number(r.amount || 0), 0);
}
function agingBuckets(records, bucketFn) {
  const buckets = { 'Current': 0, '1-30 Days': 0, '31-60 Days': 0, '61-90 Days': 0, '90+ Days': 0 };
  records.forEach(r => {
    const b = bucketFn(r);
    if (buckets.hasOwnProperty(b)) buckets[b] += Number(r.balance || 0);
  });
  return buckets;
}

async function renderRecentTransactions() {
  const combined = await getRecentTransactions();

  const tbody = document.querySelector('#recentTxTable tbody');
  if (!combined.length) {
    tbody.innerHTML = '<tr><td colspan="7">' + emptyState('No transactions recorded yet.') + '</td></tr>';
    return;
  }
  tbody.innerHTML = combined.map(tx => (
    '<tr>' +
      '<td>' + tx.id + '</td>' +
      '<td>' + formatDate(tx.date) + '</td>' +
      '<td>' + tx.type + '</td>' +
      '<td>' + escapeHtml(tx.description) + '</td>' +
      '<td>' + escapeHtml(tx.department) + '</td>' +
      '<td class="amount peso">' + formatPeso(tx.amount) + '</td>' +
      '<td>' + statusBadge(tx.status) + '</td>' +
    '</tr>'
  )).join('');
}

async function renderPendingActions() {
  const items = await getPendingActions();

  const list = document.getElementById('pendingList');
  if (!items.length) {
    list.innerHTML = '<div class="pending-empty">All caught up — no pending actions right now.</div>';
    return;
  }
  list.innerHTML = items.slice(0, 8).map(it => (
    '<div class="pending-item"><span class="pending-dot ' + it.tone + '"></span>' +
    '<span class="pending-item-text">' + escapeHtml(it.text) + '<span>' + escapeHtml(it.sub) + '</span></span></div>'
  )).join('');
}
