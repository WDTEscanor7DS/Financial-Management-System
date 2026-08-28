/* ==========================================================================
   FMS DATA LAYER — API CLIENT
   This file used to read/write localStorage directly. It now talks to the
   Laravel + MySQL backend under /api/* over fetch(), using the same
   session cookie the browser already holds after signing in at /login.
   Every exported function keeps its original name and return shape
   (getX()/createX()/updateX()/deleteX()) so the per-module scripts
   (budget.js, revenue.js, ...) only needed to add async/await, not a
   rewrite -- see the backend delivery's docs/ARCHITECTURE_ASSESSMENT.md
   for the reasoning behind this.

   Every function here is now async and returns a Promise.
   ========================================================================== */

/* ------------------------------ low-level fetch ------------------------------ */

let _csrfReady = false;

function appUrl(path) {
  const pathname = window.location.pathname;
  const publicMarker = '/public/';
  const markerIndex = pathname.indexOf(publicMarker);
  const basePath = markerIndex === -1 ? '/' : pathname.slice(0, markerIndex + publicMarker.length);
  const normalizedPath = String(path).replace(/^\/+/, '');
  if (markerIndex !== -1 && !normalizedPath.startsWith('index.php/')) {
    return basePath + 'index.php/' + normalizedPath;
  }
  return basePath + normalizedPath;
}

function _readCookie(name) {
  const match = document.cookie.match('(^|;)\\s*' + name + '\\s*=\\s*([^;]+)');
  return match ? decodeURIComponent(match.pop()) : null;
}

async function _ensureCsrfCookie() {
  if (_csrfReady) return;
  await fetch(appUrl('sanctum/csrf-cookie'), { credentials: 'include' });
  _csrfReady = true;
}

class ApiError extends Error {
  constructor(message, status, errors) {
    super(message);
    this.status = status;
    this.errors = errors || {};
  }
}

async function _api(method, path, body) {
  if (method !== 'GET') await _ensureCsrfCookie();

  const headers = { Accept: 'application/json' };
  if (body !== undefined) headers['Content-Type'] = 'application/json';
  const token = _readCookie('XSRF-TOKEN');
  if (token) headers['X-XSRF-TOKEN'] = token;

  const response = await fetch(appUrl(path), {
    method,
    headers,
    credentials: 'include',
    body: body !== undefined ? JSON.stringify(body) : undefined
  });

  if (response.status === 401) {
    window.location.href = appUrl('index.php/login');
    throw new ApiError('Not authenticated', 401);
  }

  if (response.status === 204) return null;

  let json = null;
  try { json = await response.json(); } catch (e) { /* empty body */ }

  if (!response.ok) {
    const firstFieldError = json && json.errors ? Object.values(json.errors)[0] : null;
    const message = (json && json.message) || (firstFieldError && firstFieldError[0]) || 'Something went wrong. Please try again.';
    throw new ApiError(message, response.status, json && json.errors);
  }

  return json;
}

/* ------------------------------- formatting ------------------------------- */

function formatPeso(amount) {
  const n = Number(amount) || 0;
  return '\u20b1' + n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatDate(dateStr) {
  if (!dateStr) return '\u2014';
  const d = new Date(dateStr + (dateStr.length <= 10 ? 'T00:00:00' : ''));
  if (isNaN(d)) return dateStr;
  return d.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
}

function todayISO() {
  return new Date().toISOString().slice(0, 10);
}

function daysBetween(a, b) {
  const msPerDay = 1000 * 60 * 60 * 24;
  return Math.round((new Date(b) - new Date(a)) / msPerDay);
}

// Formatted display IDs (e.g. "BUD-00001") are what every module's HTML
// already keys its edit/delete buttons on. The backend keeps returning
// them (see each Api\*Controller::transform()), so this only needs to
// convert back to a raw numeric ID when calling a /api/.../{id} route.
function _rawId(formattedId) {
  return parseInt(String(formattedId).split('-').pop(), 10);
}

/* ------------------------------ departments ------------------------------ */

let _departmentCache = null; // [{id, name}]

async function _loadDepartments() {
  if (!_departmentCache) {
    const res = await _api('GET', '/api/departments');
    _departmentCache = res.data;
  }
  return _departmentCache;
}

async function getDepartments() {
  const departments = await _loadDepartments();
  return departments.map(d => d.name);
}

async function _departmentIdByName(name) {
  const departments = await _loadDepartments();
  const match = departments.find(d => d.name === name);
  return match ? match.id : null;
}

/* ---------------------------- generic list cache ---------------------------- */
// Mirrors the "read from the in-memory array" pattern the old localStorage
// version used for single-record lookups (getBudget(id), etc.) -- each
// getX() list call refreshes this cache, and getX(id) reads from it rather
// than issuing a second network request, since every module already calls
// the list function before it needs a single record (to render the table
// the Edit/Pay/Allocate button lives in).

const _cache = { budgets: [], revenues: [], expenses: [], payables: [], receivables: [], funds: [], procurement: [], assets: [] };

/* -------------------------------- budgets -------------------------------- */

async function getBudgets() {
  const res = await _api('GET', '/api/budgets');
  _cache.budgets = res.data;
  return res.data;
}
function getBudget(id) { return _cache.budgets.find(b => b.id === id) || null; }

async function createBudget(data) {
  const departmentId = await _departmentIdByName(data.department);
  const res = await _api('POST', '/api/budgets', {
    department_id: departmentId,
    fiscal_year: data.fiscalYear,
    category: data.category,
    allocated: data.allocated,
    status: data.status
  });
  return res.data;
}

async function updateBudget(id, patch) {
  const raw = _rawId(id);
  const payload = {};
  if (patch.department) payload.department_id = await _departmentIdByName(patch.department);
  if (patch.fiscalYear) payload.fiscal_year = patch.fiscalYear;
  if (patch.category) payload.category = patch.category;
  if (patch.allocated !== undefined) payload.allocated = patch.allocated;
  if (patch.status) payload.status = patch.status;
  const res = await _api('PUT', `/api/budgets/${raw}`, payload);
  return res.data;
}

async function deleteBudget(id) {
  await _api('DELETE', `/api/budgets/${_rawId(id)}`);
  return true;
}

function budgetUtilization(b) { return b.utilization ?? 0; }

/* -------------------------------- revenue -------------------------------- */

async function getRevenues() {
  const res = await _api('GET', '/api/revenues');
  _cache.revenues = res.data;
  return res.data;
}
function getRevenue(id) { return _cache.revenues.find(r => r.id === id) || null; }

async function createRevenue(data) {
  const res = await _api('POST', '/api/revenues', {
    date: data.date,
    revenue_type: data.revenueType,
    description: data.description,
    department_id: await _departmentIdByName(data.department),
    payer: data.payer,
    reference_no: data.referenceNo,
    amount: data.amount,
    payment_method: data.paymentMethod,
    status: data.status
  });
  return res.data;
}

async function updateRevenue(id, data) {
  const res = await _api('PUT', `/api/revenues/${_rawId(id)}`, {
    date: data.date,
    revenue_type: data.revenueType,
    description: data.description,
    department_id: await _departmentIdByName(data.department),
    payer: data.payer,
    reference_no: data.referenceNo,
    amount: data.amount,
    payment_method: data.paymentMethod,
    status: data.status
  });
  return res.data;
}

async function deleteRevenue(id) {
  await _api('DELETE', `/api/revenues/${_rawId(id)}`);
  return true;
}

/* -------------------------------- expenses -------------------------------- */

async function getExpenses() {
  const res = await _api('GET', '/api/expenses');
  _cache.expenses = res.data;
  return res.data;
}
function getExpense(id) { return _cache.expenses.find(e => e.id === id) || null; }

async function createExpense(data) {
  const res = await _api('POST', '/api/expenses', {
    date: data.date,
    department_id: await _departmentIdByName(data.department),
    expense_category: data.expenseCategory,
    description: data.description,
    vendor: data.vendor,
    reference_no: data.referenceNo,
    amount: data.amount,
    payment_method: data.paymentMethod,
    status: data.status,
    budget_id: data.budgetId ? _rawId(data.budgetId) : null
  });
  return res.data;
}

async function updateExpense(id, data) {
  const res = await _api('PUT', `/api/expenses/${_rawId(id)}`, {
    date: data.date,
    department_id: await _departmentIdByName(data.department),
    expense_category: data.expenseCategory,
    description: data.description,
    vendor: data.vendor,
    reference_no: data.referenceNo,
    amount: data.amount,
    payment_method: data.paymentMethod,
    status: data.status,
    budget_id: data.budgetId ? _rawId(data.budgetId) : null
  });
  return res.data;
}

async function deleteExpense(id) {
  await _api('DELETE', `/api/expenses/${_rawId(id)}`);
  return true;
}

/* ----------------------------- accounts payable ----------------------------- */

async function getPayables() {
  const res = await _api('GET', '/api/accounts-payable');
  _cache.payables = res.data;
  return res.data;
}
function getPayable(id) { return _cache.payables.find(p => p.id === id) || null; }

async function createPayable(data) {
  const res = await _api('POST', '/api/accounts-payable', {
    vendor: data.vendor,
    invoice_no: data.invoiceNo,
    invoice_date: data.invoiceDate,
    due_date: data.dueDate,
    description: data.description,
    department_id: await _departmentIdByName(data.department),
    amount: data.amount
  });
  return res.data;
}

async function recordPayablePayment(id, amount) {
  try {
    const res = await _api('POST', `/api/accounts-payable/${_rawId(id)}/payments`, { amount });
    return res.data;
  } catch (err) {
    if (err instanceof ApiError && err.status === 422) return { error: err.message };
    throw err;
  }
}

async function deletePayable(id) {
  await _api('DELETE', `/api/accounts-payable/${_rawId(id)}`);
  return true;
}

function payableAgingBucket(p) {
  if (p.status === 'Paid') return 'Paid';
  const overdue = daysBetween(p.dueDate, todayISO());
  if (overdue <= 0) return 'Current';
  if (overdue <= 30) return '1-30 Days';
  if (overdue <= 60) return '31-60 Days';
  if (overdue <= 90) return '61-90 Days';
  return '90+ Days';
}

/* ---------------------------- accounts receivable ---------------------------- */

async function getReceivables() {
  const res = await _api('GET', '/api/accounts-receivable');
  _cache.receivables = res.data;
  return res.data;
}
function getReceivable(id) { return _cache.receivables.find(r => r.id === id) || null; }

async function createReceivable(data) {
  const res = await _api('POST', '/api/accounts-receivable', {
    customer: data.customer,
    reference_no: data.referenceNo,
    description: data.description,
    invoice_date: data.invoiceDate,
    due_date: data.dueDate,
    amount: data.amount
  });
  return res.data;
}

async function recordReceivablePayment(id, amount) {
  try {
    const res = await _api('POST', `/api/accounts-receivable/${_rawId(id)}/payments`, { amount });
    return res.data;
  } catch (err) {
    if (err instanceof ApiError && err.status === 422) return { error: err.message };
    throw err;
  }
}

async function deleteReceivable(id) {
  await _api('DELETE', `/api/accounts-receivable/${_rawId(id)}`);
  return true;
}

function receivableAgingBucket(r) {
  if (r.status === 'Paid') return 'Paid';
  const overdue = daysBetween(r.dueDate, todayISO());
  if (overdue <= 0) return 'Current';
  if (overdue <= 30) return '1-30 Days';
  if (overdue <= 60) return '31-60 Days';
  if (overdue <= 90) return '61-90 Days';
  return '90+ Days';
}

/* --------------------------------- funds --------------------------------- */

async function getFunds() {
  const res = await _api('GET', '/api/funds');
  _cache.funds = res.data;
  return res.data;
}
function getFund(id) { return _cache.funds.find(f => f.id === id) || null; }

async function createFund(data) {
  const res = await _api('POST', '/api/funds', {
    name: data.name,
    type: data.type,
    department_id: await _departmentIdByName(data.department),
    allocation: data.allocation
  });
  return res.data;
}

async function allocateFromFund(id, amount) {
  try {
    const res = await _api('POST', `/api/funds/${_rawId(id)}/allocate`, { amount });
    return res.data;
  } catch (err) {
    if (err instanceof ApiError && err.status === 422) return { error: err.message };
    throw err;
  }
}

async function deleteFund(id) {
  await _api('DELETE', `/api/funds/${_rawId(id)}`);
  return true;
}

/* ------------------------------ procurement ------------------------------ */

async function getProcurementRequests() {
  const res = await _api('GET', '/api/procurement');
  _cache.procurement = res.data;
  return res.data;
}
function getProcurementRequest(id) { return _cache.procurement.find(p => p.id === id) || null; }

async function createProcurementRequest(data) {
  const res = await _api('POST', '/api/procurement', {
    department_id: await _departmentIdByName(data.department),
    request_type: data.requestType,
    description: data.description,
    quantity: data.quantity,
    estimated_cost: data.estimatedCost,
    priority: data.priority
  });
  return res.data;
}

async function reviewProcurementRequest(id, decision, reviewer, remarks) {
  const res = await _api('POST', `/api/procurement/${_rawId(id)}/review`, { decision, remarks });
  return res.data;
}

async function advanceProcurementRequest(id, newStatus) {
  const res = await _api('POST', `/api/procurement/${_rawId(id)}/advance`, { status: newStatus });
  return res.data;
}

async function deleteProcurementRequest(id) {
  await _api('DELETE', `/api/procurement/${_rawId(id)}`);
  return true;
}

/* --------------------------------- assets --------------------------------- */

async function getAssets() {
  const res = await _api('GET', '/api/assets');
  _cache.assets = res.data;
  return res.data;
}
function getAsset(id) { return _cache.assets.find(a => a.id === id) || null; }

// Depreciation is now computed server-side (Asset::annualDepreciation()
// etc.) and returned as annualDepreciation/accumulatedDepreciation/
// bookValue on every asset object -- this stays as a thin adapter so
// assets.js's existing assetDepreciation(a) calls need no changes.
function assetDepreciation(asset) {
  return {
    annual: asset.annualDepreciation,
    accumulated: asset.accumulatedDepreciation,
    bookValue: asset.bookValue
  };
}

async function createAsset(data) {
  const res = await _api('POST', '/api/assets', {
    asset_name: data.assetName,
    category: data.category,
    serial_no: data.serialNo,
    purchase_date: data.purchaseDate,
    purchase_cost: data.purchaseCost,
    useful_life: data.usefulLife,
    salvage_value: data.salvageValue,
    department_id: await _departmentIdByName(data.department),
    location: data.location,
    status: data.status
  });
  return res.data;
}

async function updateAsset(id, data) {
  const res = await _api('PUT', `/api/assets/${_rawId(id)}`, {
    asset_name: data.assetName,
    category: data.category,
    serial_no: data.serialNo,
    purchase_date: data.purchaseDate,
    purchase_cost: data.purchaseCost,
    useful_life: data.usefulLife,
    salvage_value: data.salvageValue,
    department_id: await _departmentIdByName(data.department),
    location: data.location,
    status: data.status
  });
  return res.data;
}

async function deleteAsset(id) {
  await _api('DELETE', `/api/assets/${_rawId(id)}`);
  return true;
}

/* ---------------------------------- audit ---------------------------------- */

async function getAuditLog() {
  const res = await _api('GET', '/api/audit-logs');
  // Paginated (see AuditLogController) -- the Audit page shows the most
  // recent page's worth, same as the old prototype's in-memory list.
  return (res.data || []).map(a => ({
    id: 'AUD-' + String(a.id).padStart(5, '0'),
    user: a.user ? a.user.name : (a.role || 'System'),
    role: a.role || 'System',
    action: a.action,
    module: a.module,
    recordId: a.record_id || '\u2014',
    timestamp: a.created_at,
    description: a.description,
    status: a.status
  }));
}

/* ------------------------------ notifications ------------------------------ */

async function getNotifications() {
  const res = await _api('GET', '/api/notifications');
  return res.data.map(n => ({ id: n.id, message: n.message, timestamp: n.created_at, read: n.read }));
}

async function markNotificationRead(id) {
  await _api('POST', `/api/notifications/${id}/read`);
}

async function markAllNotificationsRead() {
  await _api('POST', '/api/notifications/read-all');
}

async function unreadNotificationCount() {
  const notifications = await getNotifications();
  return notifications.filter(n => !n.read).length;
}

/* ------------------------------ dashboard totals ------------------------------ */

async function getDashboardTotals() {
  const res = await _api('GET', '/api/dashboard/summary');
  return res.data;
}

async function getRecentTransactions() {
  const res = await _api('GET', '/api/dashboard/recent-transactions');
  return res.data;
}

async function getPendingActions() {
  const res = await _api('GET', '/api/dashboard/pending-actions');
  return res.data;
}

/* -------------------------------- reports -------------------------------- */

async function getIncomeStatement() {
  const res = await _api('GET', '/api/reports/income-statement');
  return res.data;
}

async function getBudgetVsActualReport() {
  const res = await _api('GET', '/api/reports/budget-vs-actual');
  return res.data;
}

async function getAgingReport() {
  const res = await _api('GET', '/api/reports/aging');
  return res.data;
}

async function getCashFlowReport(beginningBalance) {
  const res = await _api('GET', `/api/reports/cash-flow?beginning_balance=${beginningBalance || 0}`);
  return res.data;
}

async function getExpenseReport(filters) {
  const params = new URLSearchParams();
  if (filters.date) params.set('date', filters.date);
  if (filters.departmentId) params.set('department_id', filters.departmentId);
  if (filters.category) params.set('category', filters.category);
  const res = await _api('GET', `/api/reports/expenses?${params.toString()}`);
  return res.data;
}

async function getRevenueReport(filters) {
  const params = new URLSearchParams();
  if (filters.date) params.set('date', filters.date);
  if (filters.departmentId) params.set('department_id', filters.departmentId);
  if (filters.type) params.set('type', filters.type);
  const res = await _api('GET', `/api/reports/revenues?${params.toString()}`);
  return res.data;
}
