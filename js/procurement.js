/* ==========================================================================
   PROCUREMENT AND FINANCIAL REQUESTS
   ========================================================================== */

let prActiveTab = '';

document.addEventListener('DOMContentLoaded', async () => {
  if (!(await initShell('procurement'))) return;

  const user = getCurrentUser();
  // The Requester field is display-only now: the backend always records
  // the authenticated user as requester_id and ignores any "requester"
  // value a client might send (see ProcurementRequestStoreRequest), so an
  // Employee can no longer submit a request "as" someone else even by
  // editing this field in devtools.
  document.getElementById('prRequester').value = user.name;
  document.getElementById('prRequester').setAttribute('readonly', 'readonly');

  await populatePrFilters();
  await renderPrGrid();

  document.getElementById('addRequestBtn').addEventListener('click', () => openModal('prModal'));
  document.getElementById('prForm').addEventListener('submit', handlePrSubmit);
  document.getElementById('prSearch').addEventListener('input', renderPrGrid);
  document.getElementById('prTypeFilter').addEventListener('input', renderPrGrid);
  document.getElementById('prResetFilters').addEventListener('click', () => {
    document.getElementById('prSearch').value = '';
    document.getElementById('prTypeFilter').value = '';
    renderPrGrid();
  });
  document.querySelectorAll('#prTabs .tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('#prTabs .tab-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      prActiveTab = btn.getAttribute('data-status');
      renderPrGrid();
    });
  });
  document.getElementById('prApproveBtn').addEventListener('click', () => submitReview('approve'));
  document.getElementById('prRejectBtn').addEventListener('click', () => submitReview('reject'));
});

async function populatePrFilters() {
  const sel = document.getElementById('prDepartment');
  (await getDepartments()).forEach(d => sel.insertAdjacentHTML('beforeend', '<option value="' + d + '">' + d + '</option>'));
}

async function renderPrGrid() {
  // Visibility (an Employee/College Administrator only ever sees their own
  // submissions) is enforced server-side in ProcurementController::index()
  // -- what this endpoint returns IS what the current user is allowed to
  // see, so no additional client-side filtering is needed here.
  const all = await getProcurementRequests();

  document.getElementById('prKpis').innerHTML = [
    { label: 'Total Requests', value: all.length },
    { label: 'Pending Review', value: all.filter(p => p.status === 'Pending Review').length },
    { label: 'Approved / Processing', value: all.filter(p => p.status === 'Approved' || p.status === 'Procurement Processing').length },
    { label: 'Estimated Value (Open)', value: formatPeso(all.filter(p => p.status !== 'Rejected' && p.status !== 'Completed').reduce((s, p) => s + Number(p.estimatedCost), 0)) }
  ].map(k => '<div class="card kpi-box"><div class="summary-label">' + k.label + '</div><div class="summary-value">' + k.value + '</div></div>').join('');

  const search = document.getElementById('prSearch').value;
  const type = document.getElementById('prTypeFilter').value;
  const rows = filterRows(all, search, ['requester', 'description', 'department'], (p) =>
    (!type || p.requestType === type) && (!prActiveTab || p.status === prActiveTab)
  ).sort((a, b) => new Date(b.dateSubmitted) - new Date(a.dateSubmitted));

  const grid = document.getElementById('prGrid');
  if (!rows.length) { grid.innerHTML = emptyState('No requests match your filters.'); return; }

  grid.innerHTML = rows.map(p => (
    '<div class="card record-card">' +
      '<div class="record-card-head"><strong>' + p.id + '</strong>' + statusBadge(p.status) + '</div>' +
      '<div class="record-card-meta">' + escapeHtml(p.requester) + ' &middot; ' + escapeHtml(p.department) + ' &middot; ' + formatDate(p.dateSubmitted) + '</div>' +
      renderWorkflow(p.status) +
      '<div class="record-card-figures"><span>Type</span><span class="val">' + escapeHtml(p.requestType) + '</span></div>' +
      '<div class="record-card-figures"><span>Priority</span><span>' + priorityBadge(p.priority) + '</span></div>' +
      '<div class="record-card-figures"><span>Estimated Cost</span><span class="val peso">' + formatPeso(p.estimatedCost) + '</span></div>' +
      '<p class="text-muted" style="font-size:12.3px;margin-top:8px;">' + escapeHtml(p.description) + '</p>' +
      (p.remarks ? '<p style="font-size:12px;margin-top:6px;"><strong>Remarks:</strong> ' + escapeHtml(p.remarks) + '</p>' : '') +
      '<div class="record-card-actions">' + renderPrActions(p) + '</div>' +
    '</div>'
  )).join('');
}

function renderWorkflow(status) {
  const steps = ['Pending Review', 'Approved', 'Procurement Processing', 'Completed'];
  if (status === 'Rejected') {
    return '<div class="workflow-track">' +
      '<span class="workflow-step done">Submitted</span><span class="workflow-arrow">→</span>' +
      '<span class="workflow-step rejected">Rejected</span></div>';
  }
  const idx = steps.indexOf(status);
  return '<div class="workflow-track">' + steps.map((s, i) => {
    const cls = i < idx ? 'done' : (i === idx ? 'current' : '');
    return '<span class="workflow-step ' + cls + '">' + s + '</span>' + (i < steps.length - 1 ? '<span class="workflow-arrow">→</span>' : '');
  }).join('') + '</div>';
}

function renderPrActions(p) {
  let actions = '';
  if (userCan('approve_procurement_request') && p.status === 'Pending Review') {
    actions += '<button class="btn btn-primary btn-sm" onclick="openReviewModal(\'' + p.id + '\')">Review</button>';
  }
  if (userCan('advance_procurement_request') && p.status === 'Approved') {
    actions += '<button class="btn btn-outline btn-sm" onclick="advanceRequest(\'' + p.id + '\', \'Procurement Processing\')">Start Processing</button>';
  }
  if (userCan('advance_procurement_request') && p.status === 'Procurement Processing') {
    actions += '<button class="btn btn-success btn-sm" onclick="advanceRequest(\'' + p.id + '\', \'Completed\')">Mark Completed</button>';
  }
  if (userCan('delete_procurement_request')) {
    actions += '<button class="btn btn-danger btn-sm" onclick="handleDeleteRequest(\'' + p.id + '\')">Delete</button>';
  }
  return actions;
}

async function handlePrSubmit(e) {
  e.preventDefault();
  const form = e.target;
  const errors = validateForm(form);
  if (errors.length) { showFormErrors(form, errors); return; }

  try {
    await createProcurementRequest({
      department: document.getElementById('prDepartment').value,
      requestType: document.getElementById('prType').value,
      priority: document.getElementById('prPriority').value,
      description: document.getElementById('prDescription').value.trim(),
      quantity: document.getElementById('prQuantity').value.trim(),
      estimatedCost: Number(document.getElementById('prEstimatedCost').value)
    });
    toast('Request submitted successfully.', 'success');
    closeModal('prModal');
    document.getElementById('prDescription').value = '';
    document.getElementById('prQuantity').value = '';
    document.getElementById('prEstimatedCost').value = '';
    await renderPrGrid();
  } catch (err) {
    showFormErrors(form, [err.message]);
  }
}

async function handleDeleteRequest(id) {
  if (!confirm('Delete request ' + id + '? This cannot be undone.')) return;
  try {
    await deleteProcurementRequest(id);
    toast('Request deleted.', 'success');
    await renderPrGrid();
  } catch (err) {
    toast(err.message, 'danger');
  }
}

async function advanceRequest(id, status) {
  try {
    await advanceProcurementRequest(id, status);
    toast('Request ' + id + ' moved to ' + status + '.', 'success');
    await renderPrGrid();
  } catch (err) {
    toast(err.message, 'danger');
  }
}

function openReviewModal(id) {
  const p = getProcurementRequest(id);
  document.getElementById('prReviewId').value = p.id;
  document.getElementById('prReviewSub').textContent = p.id + ' \u00b7 ' + p.requester;
  document.getElementById('prReviewRemarks').value = '';
  showFormErrors(document.getElementById('prReviewForm'), []);
  document.getElementById('prReviewDetails').innerHTML = [
    ['Department', p.department], ['Request Type', p.requestType], ['Description', p.description],
    ['Quantity', p.quantity || '\u2014'], ['Estimated Cost', formatPeso(p.estimatedCost)], ['Priority', p.priority]
  ].map(row => '<div class="form-static-row"><span>' + row[0] + '</span><span class="val">' + escapeHtml(String(row[1])) + '</span></div>').join('');
  openModal('prReviewModal');
}

async function submitReview(decision) {
  const id = document.getElementById('prReviewId').value;
  const remarks = document.getElementById('prReviewRemarks').value.trim();

  try {
    await reviewProcurementRequest(id, decision, null, remarks);
    toast('Request ' + id + ' ' + (decision === 'approve' ? 'approved' : 'rejected') + '.', decision === 'approve' ? 'success' : 'danger');
    closeModal('prReviewModal');
    await renderPrGrid();
  } catch (err) {
    showFormErrors(document.getElementById('prReviewForm'), [err.message]);
  }
}
