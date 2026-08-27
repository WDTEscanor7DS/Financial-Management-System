/* ==========================================================================
   NAVIGATION
   Builds the persistent sidebar and topbar for every authenticated page,
   filtered by the signed-in role, and wires up the collapse/notification/
   logout behaviour shared across the whole app.
   ========================================================================== */

const NAV_SECTIONS = [
  {
    heading: 'Financial Management',
    items: [
      { key: 'dashboard', label: 'Dashboard', href: 'dashboard.html', icon: 'grid' },
      { key: 'budget', label: 'Budget Planning', href: 'budget.html', icon: 'pie' },
      { key: 'revenue', label: 'Revenue Management', href: 'revenue.html', icon: 'trend-up' },
      { key: 'expenses', label: 'Expenses & Disbursements', href: 'expenses.html', icon: 'trend-down' },
      { key: 'accounts-payable', label: 'Accounts Payable', href: 'accounts-payable.html', icon: 'outbox' },
      { key: 'accounts-receivable', label: 'Accounts Receivable', href: 'accounts-receivable.html', icon: 'inbox' },
      { key: 'funds', label: 'Fund Management', href: 'funds.html', icon: 'vault' },
      { key: 'procurement', label: 'Procurement & Requests', href: 'procurement.html', icon: 'cart' },
      { key: 'assets', label: 'Assets & Depreciation', href: 'assets.html', icon: 'box' },
      { key: 'reports', label: 'Financial Reports', href: 'reports.html', icon: 'file' }
    ]
  },
  {
    heading: 'System',
    items: [
      { key: 'audit', label: 'Security & Audit Trail', href: 'audit.html', icon: 'shield' }
    ]
  }
];

const ICONS = {
  grid: '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
  pie: '<path d="M12 2v10l8.5 5A10 10 0 0 0 12 2Z"/><path d="M12 12 3.5 17A10 10 0 1 1 20.5 7"/>',
  'trend-up': '<polyline points="3 17 9 11 13 15 21 6"/><polyline points="15 6 21 6 21 12"/>',
  'trend-down': '<polyline points="3 7 9 13 13 9 21 18"/><polyline points="15 18 21 18 21 12"/>',
  outbox: '<path d="M4 12h5l2 3h2l2-3h5"/><path d="M4 12 6 5h12l2 7"/><path d="M4 12v6h16v-6"/>',
  inbox: '<path d="M4 12h5l2 3h2l2-3h5"/><path d="M4 12 6 19h12l2-7"/><path d="M4 12V6h16v6"/>',
  vault: '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="12" cy="12" r="3.2"/><path d="M12 8.8v.01M8.5 12h.01M12 15.2v.01M15.5 12h.01"/>',
  cart: '<circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2 3h3l2.4 12.2a2 2 0 0 0 2 1.6h8.2a2 2 0 0 0 2-1.6L21 7H6"/>',
  box: '<path d="M21 8 12 3 3 8l9 5 9-5Z"/><path d="M3 8v9l9 5 9-5V8"/><path d="M12 13v9"/>',
  file: '<path d="M6 2h9l5 5v15H6Z"/><path d="M15 2v5h5"/><path d="M9 13h6M9 17h6"/>',
  shield: '<path d="M12 2 4 5v6c0 5 3.4 8.7 8 11 4.6-2.3 8-6 8-11V5Z"/>',
  bell: '<path d="M6 8a6 6 0 0 1 12 0c0 5 2 6 2 7H4c0-1 2-2 2-7Z"/><path d="M10 20a2 2 0 0 0 4 0"/>',
  logout: '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><path d="M21 12H9"/>',
  chevron: '<polyline points="9 18 15 12 9 6"/>'
};

function icon(name, extraClass) {
  return '<svg class="icon ' + (extraClass || '') + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' + (ICONS[name] || '') + '</svg>';
}

function currentPageKey() {
  const path = window.location.pathname;
  const file = path.substring(path.lastIndexOf('/') + 1).replace('.html', '');
  return file === '' || file === 'index' ? 'dashboard' : file;
}

function buildSidebar() {
  const role = currentRole();
  const user = getCurrentUser();
  if (!role || !user) return;

  const activeKey = currentPageKey();
  // Note: the sidebar brand (logo + college name) is static markup already
  // present once in each page's HTML — it is not rebuilt here to avoid
  // rendering it twice.
  let html = '';
  html += '<a class="nav-item' + (activeKey === 'dashboard' ? ' active' : '') + '" href="dashboard.html">' + icon('grid') + '<span>Dashboard</span></a>';

  NAV_SECTIONS.forEach(section => {
    const visibleItems = section.items.filter(it => it.key === 'dashboard' ? false : canAccessModule(it.key));
    if (!visibleItems.length) return;
    html += '<div class="nav-heading">' + section.heading + '</div>';
    visibleItems.forEach(it => {
      html += '<a class="nav-item' + (activeKey === it.key ? ' active' : '') + '" href="' + it.href + '">' + icon(it.icon) + '<span>' + it.label + '</span></a>';
    });
  });

  document.getElementById('sidebarNav').innerHTML = html;
}

async function buildTopbar() {
  const role = currentRole();
  const user = getCurrentUser();
  if (!role || !user) return;

  const initials = user.name.split(' ').map(p => p[0]).slice(0, 2).join('');
  const unread = await unreadNotificationCount();

  document.getElementById('topbarUserName').textContent = user.name;
  document.getElementById('topbarUserInitials').textContent = initials;
  document.querySelectorAll('.js-role-label').forEach(el => { el.textContent = role.label; });

  const badge = document.getElementById('notifBadge');
  if (badge) {
    if (unread > 0) { badge.textContent = unread; badge.hidden = false; }
    else { badge.hidden = true; }
  }

  await renderNotificationPanel();
}

async function renderNotificationPanel() {
  const panel = document.getElementById('notifList');
  if (!panel) return;
  const items = await getNotifications();
  if (!items.length) {
    panel.innerHTML = '<div class="notif-empty">No notifications yet.</div>';
    return;
  }
  panel.innerHTML = items.slice(0, 12).map(n => (
    '<div class="notif-item' + (n.read ? '' : ' unread') + '" data-id="' + n.id + '">' +
      '<p>' + escapeHtml(n.message) + '</p>' +
      '<span>' + timeAgo(n.timestamp) + '</span>' +
    '</div>'
  )).join('');
}

function timeAgo(iso) {
  const diffMs = Date.now() - new Date(iso).getTime();
  const mins = Math.round(diffMs / 60000);
  if (mins < 1) return 'just now';
  if (mins < 60) return mins + 'm ago';
  const hrs = Math.round(mins / 60);
  if (hrs < 24) return hrs + 'h ago';
  return Math.round(hrs / 24) + 'd ago';
}

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

async function initShell(activeModuleKey) {
  if (!(await enforceModuleAccess(activeModuleKey))) return false;
  buildSidebar();
  await buildTopbar();
  wireShellEvents();
  return true;
}

function wireShellEvents() {
  const sidebar = document.getElementById('appSidebar');
  const toggleBtn = document.getElementById('sidebarToggle');
  if (toggleBtn && sidebar) {
    toggleBtn.addEventListener('click', () => {
      sidebar.classList.toggle('collapsed');
      document.body.classList.toggle('sidebar-collapsed');
    });
  }

  const mobileToggle = document.getElementById('mobileNavToggle');
  if (mobileToggle && sidebar) {
    mobileToggle.addEventListener('click', () => sidebar.classList.toggle('open'));
  }

  const notifBtn = document.getElementById('notifButton');
  const notifPanel = document.getElementById('notifPanel');
  if (notifBtn && notifPanel) {
    notifBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      notifPanel.classList.toggle('open');
    });
    document.addEventListener('click', (e) => {
      if (!notifPanel.contains(e.target) && e.target !== notifBtn) notifPanel.classList.remove('open');
    });
    const markAllBtn = document.getElementById('notifMarkAll');
    if (markAllBtn) {
      markAllBtn.addEventListener('click', async () => {
        await markAllNotificationsRead();
        await buildTopbar();
      });
    }
  }

  const logoutBtn = document.getElementById('logoutButton');
  if (logoutBtn) {
    logoutBtn.addEventListener('click', async () => {
      await logoutUser();
      window.location.href = resolveRootPath() + 'index.html';
    });
  }

  const userMenuBtn = document.getElementById('userMenuButton');
  const userMenu = document.getElementById('userMenu');
  if (userMenuBtn && userMenu) {
    userMenuBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      userMenu.classList.toggle('open');
    });
    document.addEventListener('click', (e) => {
      if (!userMenu.contains(e.target) && e.target !== userMenuBtn) userMenu.classList.remove('open');
    });
  }
}
