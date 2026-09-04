/* ==========================================================================
   SECURITY, ACCESS, AND AUDIT TRAIL
   Read-only by design -- this page has no create/edit/delete controls, and
   neither does the backend: AuditLogController exposes only index(), and
   AuditLog::update()/delete() throw if anything ever tries to call them
   (see fms-backend/app/Models/AuditLog.php).
   ========================================================================== */

document.addEventListener('DOMContentLoaded', async () => {
  if (!(await initShell('audit'))) return;

  await populateAuditFilters();
  await renderAuditTable();

  document.getElementById('exportAuditBtn').addEventListener('click', exportAuditCSV);
  ['auditSearch', 'auditModuleFilter', 'auditRoleFilter'].forEach(id => document.getElementById(id).addEventListener('input', renderAuditTable));
  document.getElementById('auditResetFilters').addEventListener('click', () => {
    ['auditSearch', 'auditModuleFilter', 'auditRoleFilter'].forEach(id => document.getElementById(id).value = '');
    renderAuditTable();
  });
});

async function populateAuditFilters() {
  const all = await getAuditLog();
  const modules = Array.from(new Set(all.map(a => a.module))).sort();
  const roles = Array.from(new Set(all.map(a => a.role))).sort();
  const modSel = document.getElementById('auditModuleFilter');
  const roleSel = document.getElementById('auditRoleFilter');
  modules.forEach(m => modSel.insertAdjacentHTML('beforeend', '<option value="' + m + '">' + m + '</option>'));
  roles.forEach(r => roleSel.insertAdjacentHTML('beforeend', '<option value="' + r + '">' + r + '</option>'));
}

async function getFilteredAuditLog() {
  const search = document.getElementById('auditSearch').value;
  const module = document.getElementById('auditModuleFilter').value;
  const role = document.getElementById('auditRoleFilter').value;
  const all = await getAuditLog();
  return filterRows(all, search, ['user', 'action', 'module', 'recordId', 'description'], (a) =>
    (!module || a.module === module) && (!role || a.role === role)
  );
}

async function renderAuditTable() {
  const all = await getAuditLog();
  const today = todayISO();
  document.getElementById('auditKpis').innerHTML = [
    { label: 'Total Log Entries', value: all.length },
    { label: 'Actions Today', value: all.filter(a => a.timestamp.slice(0, 10) === today).length },
    { label: 'Successful Actions', value: all.filter(a => a.status === 'Success').length },
    { label: 'Unique Users', value: new Set(all.map(a => a.user)).size }
  ].map(k => '<div class="card kpi-box"><div class="summary-label">' + k.label + '</div><div class="summary-value">' + k.value + '</div></div>').join('');

  const rows = await getFilteredAuditLog();
  const tbody = document.querySelector('#auditTable tbody');
  tbody.innerHTML = rows.length
    ? rows.map(a => (
        '<tr><td>' + a.id + '</td><td>' + escapeHtml(a.user) + '</td><td>' + escapeHtml(a.role) + '</td>' +
        '<td>' + escapeHtml(a.action) + '</td><td>' + escapeHtml(a.module) + '</td><td>' + escapeHtml(a.recordId) + '</td>' +
        '<td>' + new Date(a.timestamp).toLocaleString('en-PH') + '</td><td>' + statusBadge(a.status) + '</td></tr>'
      )).join('')
    : '<tr><td colspan="8">' + emptyState('No audit entries match your filters.') + '</td></tr>';
  document.getElementById('auditCount').textContent = rows.length + ' entr' + (rows.length === 1 ? 'y' : 'ies');
}

async function exportAuditCSV() {
  const rows = await getFilteredAuditLog();
  exportCSV('audit-log.csv',
    ['Log ID', 'User', 'Role', 'Action', 'Module', 'Record ID', 'Timestamp', 'Description', 'Status'],
    rows.map(a => [a.id, a.user, a.role, a.action, a.module, a.recordId, a.timestamp, a.description, a.status])
  );
}
