/* ==========================================================================
   APPROVAL ENGINE
   ========================================================================== */

document.addEventListener('DOMContentLoaded', async () => {
  if (!(await initShell('approvals'))) return;

  await renderApprovalsTable();

  document.getElementById('addRequestBtn').addEventListener('click', openRequestModal);
  document.getElementById('requestForm').addEventListener('submit', handleRequestSubmit);
  document.getElementById('detailCloseBtn').addEventListener('click', () => closeModal('detailModal'));
  document.getElementById('detailCloseBtn2').addEventListener('click', () => closeModal('detailModal'));
});

async function renderApprovalsTable() {
  const requests = await getApprovalRequests();
  const tbody = document.querySelector('#approvalsTable tbody');

  if (!requests.length) {
    tbody.innerHTML = '<tr><td colspan="7">' + emptyState('No approval requests yet.') + '</td></tr>';
  } else {
    const statusColor = { Pending: 'warning', Approved: 'success', Rejected: 'danger' };
    tbody.innerHTML = requests.map(r => {
      const currentStep = r.steps.find(s => s.sequence === r.currentSequence);
      const stepLabel = r.status === 'Pending' ? (currentStep ? currentStep.stepName : '\u2014') : '\u2014';
      return (
        '<tr><td>' + r.id + '</td><td>' + escapeHtml(r.workflowName) + '</td>' +
        '<td>' + escapeHtml(r.description) + '</td><td>' + escapeHtml(stepLabel) + '</td>' +
        '<td><span class="badge badge-' + statusColor[r.status] + '">' + r.status + '</span></td>' +
        '<td>' + escapeHtml(r.requestedBy || '\u2014') + '</td>' +
        '<td><button class="btn btn-ghost btn-sm" onclick="openDetail(\'' + r.id + '\')">View</button></td></tr>'
      );
    }).join('');
  }
  document.getElementById('approvalsCount').textContent = requests.length + ' request' + (requests.length === 1 ? '' : 's');
}

function openRequestModal() {
  const form = document.getElementById('requestForm');
  form.reset();
  showFormErrors(form, []);
  openModal('requestModal');
}

async function handleRequestSubmit(e) {
  e.preventDefault();
  const form = e.target;

  const data = {
    workflowCode: document.getElementById('reqWorkflow').value,
    description: document.getElementById('reqDescription').value.trim(),
  };

  try {
    await createApprovalRequest(data);
    toast('Approval request submitted.', 'success');
    closeModal('requestModal');
    await renderApprovalsTable();
  } catch (err) {
    showFormErrors(form, [err.message]);
  }
}

async function openDetail(id) {
  const requests = await getApprovalRequests();
  const request = requests.find(r => r.id === id);
  if (!request) return;

  document.getElementById('detailTitle').textContent = request.id + ' \u2014 ' + request.workflowName;
  document.getElementById('detailSub').textContent = request.description;

  const stepsHtml = request.steps.map(s => {
    const badgeColor = s.status === 'Approved' ? 'success' : s.status === 'Rejected' ? 'danger' : 'neutral';
    const actedInfo = s.actedBy ? ' \u00b7 ' + escapeHtml(s.actedBy) + (s.remarks ? ' \u2014 "' + escapeHtml(s.remarks) + '"' : '') : '';
    return (
      '<div class="form-static-row">' +
        '<span>Step ' + s.sequence + ': ' + escapeHtml(s.stepName) + actedInfo + '</span>' +
        '<span class="badge badge-' + badgeColor + '">' + s.status + '</span>' +
      '</div>'
    );
  }).join('');

  document.getElementById('detailBody').innerHTML = stepsHtml;

  const footer = document.getElementById('detailFooter');
  footer.innerHTML = '<button class="btn btn-outline" id="detailCloseBtn3">Close</button>';
  document.getElementById('detailCloseBtn3').onclick = () => closeModal('detailModal');

  const currentStep = request.steps.find(s => s.sequence === request.currentSequence);
  const canAct = request.status === 'Pending' && currentStep && userCan(currentStep.requiredPermission);

  if (canAct) {
    const rejectBtn = document.createElement('button');
    rejectBtn.className = 'btn btn-danger';
    rejectBtn.textContent = 'Reject';
    rejectBtn.onclick = () => handleDecision(request.id, 'Rejected');
    footer.appendChild(rejectBtn);

    const approveBtn = document.createElement('button');
    approveBtn.className = 'btn btn-primary';
    approveBtn.textContent = 'Approve';
    approveBtn.onclick = () => handleDecision(request.id, 'Approved');
    footer.appendChild(approveBtn);
  }

  openModal('detailModal');
}

async function handleDecision(id, decision) {
  const remarks = prompt(decision + ' this step -- optional remarks:') || null;

  try {
    await actOnApprovalRequest(id, decision, remarks);
    toast('Step ' + decision.toLowerCase() + '.', 'success');
    closeModal('detailModal');
    await renderApprovalsTable();
  } catch (err) {
    toast(err.message, 'danger');
  }
}