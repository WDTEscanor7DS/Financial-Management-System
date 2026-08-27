/* ==========================================================================
   APP UTILITIES
   Small, dependency-free helpers reused by every module script: toasts,
   modal open/close, form validation, table filtering, and status badges.
   ========================================================================== */

/* ---------------------------------- toasts ---------------------------------- */

function toast(message, type) {
  let host = document.getElementById('toastHost');
  if (!host) {
    host = document.createElement('div');
    host.id = 'toastHost';
    host.className = 'toast-host';
    document.body.appendChild(host);
  }
  const el = document.createElement('div');
  el.className = 'toast toast-' + (type || 'info');
  el.textContent = message;
  host.appendChild(el);
  requestAnimationFrame(() => el.classList.add('show'));
  setTimeout(() => {
    el.classList.remove('show');
    setTimeout(() => el.remove(), 250);
  }, 3200);
}

/* ---------------------------------- modals ---------------------------------- */

function openModal(id) {
  const modal = document.getElementById(id);
  if (!modal) return;
  modal.classList.add('open');
  document.body.classList.add('modal-open');
}

function closeModal(id) {
  const modal = document.getElementById(id);
  if (!modal) return;
  modal.classList.remove('open');
  document.body.classList.remove('modal-open');
}

function wireModalDismiss() {
  document.querySelectorAll('[data-close-modal]').forEach(el => {
    el.addEventListener('click', () => closeModal(el.getAttribute('data-close-modal')));
  });
  document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(modal.id); });
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      document.querySelectorAll('.modal.open').forEach(m => closeModal(m.id));
    }
  });
}

/* -------------------------------- validation -------------------------------- */

function validateForm(formEl) {
  const errors = [];
  formEl.querySelectorAll('[required]').forEach(field => {
    const value = (field.value || '').trim();
    if (!value) {
      errors.push(fieldLabel(field) + ' is required.');
      field.classList.add('invalid');
    } else {
      field.classList.remove('invalid');
    }
  });
  formEl.querySelectorAll('[data-positive-number]').forEach(field => {
    const value = Number(field.value);
    if (field.value !== '' && (isNaN(value) || value <= 0)) {
      errors.push(fieldLabel(field) + ' must be a positive number.');
      field.classList.add('invalid');
    }
  });
  formEl.querySelectorAll('[data-min-date]').forEach(field => {
    const minAttr = field.getAttribute('data-min-date');
    const minField = document.getElementById(minAttr);
    if (minField && field.value && minField.value && field.value < minField.value) {
      errors.push(fieldLabel(field) + ' cannot be earlier than ' + fieldLabel(minField) + '.');
      field.classList.add('invalid');
    }
  });
  return errors;
}

function fieldLabel(field) {
  const label = field.closest('.form-field');
  if (label) {
    const lbl = label.querySelector('label');
    if (lbl) return lbl.textContent.replace('*', '').trim();
  }
  return field.name || 'This field';
}

function showFormErrors(container, errors) {
  const box = container.querySelector('.form-errors');
  if (!box) return;
  if (!errors.length) { box.hidden = true; box.innerHTML = ''; return; }
  box.hidden = false;
  box.innerHTML = '<strong>Please fix the following:</strong><ul>' + errors.map(e => '<li>' + escapeHtml(e) + '</li>').join('') + '</ul>';
}

/* ---------------------------------- badges ---------------------------------- */

function statusBadge(status) {
  const map = {
    'Active': 'badge-success', 'Paid': 'badge-success', 'Approved': 'badge-success',
    'Completed': 'badge-success', 'Received': 'badge-success', 'Success': 'badge-success',
    'In Use': 'badge-success',
    'Pending': 'badge-warning', 'Partially Paid': 'badge-warning', 'Pending Review': 'badge-warning',
    'Outstanding': 'badge-warning', 'Procurement Processing': 'badge-info',
    'Overdue': 'badge-danger', 'Rejected': 'badge-danger', 'Failed': 'badge-danger'
  };
  const cls = map[status] || 'badge-neutral';
  return '<span class="badge ' + cls + '">' + escapeHtml(status) + '</span>';
}

function priorityBadge(priority) {
  const map = { Urgent: 'badge-danger', High: 'badge-warning', Medium: 'badge-info', Low: 'badge-neutral' };
  return '<span class="badge ' + (map[priority] || 'badge-neutral') + '">' + escapeHtml(priority) + '</span>';
}

/* ------------------------------- table helpers ------------------------------- */

function filterRows(rows, searchTerm, searchFields, filterMatches) {
  const term = (searchTerm || '').toLowerCase().trim();
  return rows.filter(row => {
    const matchesSearch = !term || searchFields.some(f => String(row[f] || '').toLowerCase().includes(term));
    const matchesFilters = filterMatches ? filterMatches(row) : true;
    return matchesSearch && matchesFilters;
  });
}

function emptyState(message, sub) {
  return '<div class="empty-state"><p class="empty-title">' + escapeHtml(message) + '</p>' +
    (sub ? '<p class="empty-sub">' + escapeHtml(sub) + '</p>' : '') + '</div>';
}

/* --------------------------------- CSV export --------------------------------- */

function exportCSV(filename, headers, rows) {
  const escapeCsv = (val) => {
    const s = String(val === undefined || val === null ? '' : val);
    return /[",\n]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s;
  };
  const lines = [headers.map(escapeCsv).join(',')];
  rows.forEach(r => lines.push(r.map(escapeCsv).join(',')));
  const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(url);
  toast('Exported ' + filename, 'success');
}

/* ------------------------------ generic bootstrap ------------------------------ */

document.addEventListener('DOMContentLoaded', () => {
  wireModalDismiss();
});
