/* ==========================================================================
   AUTH / ROLE — NOW BACKED BY THE LARAVEL SESSION
   The old prototype kept a hardcoded ROLES object and wrote {role, name}
   straight into localStorage from loginAs() -- anyone could open the
   console and grant themselves Administrator. That is gone. Signing in
   now happens server-side at POST /login (see fms-backend's
   Auth\LoginController), which sets a real session cookie; this file's
   job is just to ask the server who is signed in (GET /api/me) and cache
   the answer for the lifetime of the page.
   ========================================================================== */

// Sidebar module key -> the permission slug that must be present for the
// module to be reachable. This is a convenience list for the UI only --
// see EnsurePermission (fms-backend) for the actual enforcement, which
// does not trust anything this file decides.
const MODULE_PERMISSIONS = {
  dashboard: 'view_dashboard',
  budget: 'view_budget',
  revenue: 'view_revenue',
  expenses: 'view_expenses',
  'accounts-payable': 'view_accounts_payable',
  'accounts-receivable': 'view_accounts_receivable',
  funds: 'view_funds',
  procurement: 'view_procurement',
  assets: 'view_assets',
  reports: 'view_reports',
  audit: 'view_audit_logs',
  'general-ledger': 'view_general_ledger'
};

let _currentUser = null; // { id, name, email, department, role, roleSlug, permissions: Set }

async function fetchCurrentUser() {
  if (_currentUser) return _currentUser;

  const response = await fetch(appUrl('api/me'), { credentials: 'include', headers: { Accept: 'application/json' } });

  if (response.status === 401) {
    window.location.href = appUrl('index.php/login');
    return null;
  }

  const json = await response.json();
  _currentUser = {
    id: json.data.id,
    name: json.data.name,
    email: json.data.email,
    department: json.data.department,
    role: json.data.role,
    roleSlug: json.data.roleSlug,
    permissions: new Set(json.data.permissions)
  };
  return _currentUser;
}

// requireAuth() used to be synchronous (reading localStorage); every
// caller in navigation.js already awaits it, so this can become async
// without touching any call site's surrounding logic beyond that.
async function requireAuth() {
  return fetchCurrentUser();
}

function getCurrentUser() {
  // Synchronous accessor for code that runs after requireAuth()/
  // fetchCurrentUser() has already resolved once this page load (which is
  // every page's initShell() call before anything else runs).
  return _currentUser;
}

function currentRole() {
  if (!_currentUser) return null;
  return {
    label: _currentUser.role,
    permissions: _currentUser.permissions
  };
}

function canAccessModule(moduleKey) {
  if (!_currentUser) return false;
  const permission = MODULE_PERMISSIONS[moduleKey];
  return permission ? _currentUser.permissions.has(permission) : false;
}

// Fine-grained checks used inside module scripts (e.g. "can this user see
// the Approve button") -- also purely a UI convenience; the corresponding
// /api/* route re-checks the same permission slug server-side regardless.
function userCan(permissionSlug) {
  return !!(_currentUser && _currentUser.permissions.has(permissionSlug));
}

function resolveRootPath() {
  return window.location.pathname.indexOf('/pages/') !== -1 ? '../' : '';
}

async function enforceModuleAccess(moduleKey) {
  const user = await requireAuth();
  if (!user) return false;

  if (!canAccessModule(moduleKey)) {
    document.body.innerHTML =
      '<div class="access-denied">' +
      '<h1>Access Restricted</h1>' +
      '<p>Your role (' + user.role + ') does not have permission to view this module.</p>' +
      '<a class="btn btn-primary" href="' + resolveRootPath() + 'pages/dashboard.html">Return to Dashboard</a>' +
      '</div>';
    return false;
  }
  return true;
}

async function logoutUser() {
  await _ensureCsrfCookie();
  const token = _readCookie('XSRF-TOKEN');
  await fetch(appUrl('logout'), {
    method: 'POST',
    credentials: 'include',
    headers: { Accept: 'application/json', 'X-XSRF-TOKEN': token || '' }
  });
  _currentUser = null;
}
