/* ==========================================================================
   FUND MANAGEMENT AND ALLOCATION
   ========================================================================== */

document.addEventListener('DOMContentLoaded', async () => {
  if (!(await initShell('funds'))) return;

  await populateFundDeptSelect();
  await renderFunds();

  document.getElementById('addFundBtn').addEventListener('click', () => openModal('fundModal'));
  document.getElementById('fundForm').addEventListener('submit', handleFundSubmit);
  document.getElementById('fundAllocateForm').addEventListener('submit', handleFundAllocateSubmit);
});

async function populateFundDeptSelect() {
  const sel = document.getElementById('fundDepartment');
  (await getDepartments()).forEach(d => sel.insertAdjacentHTML('beforeend', '<option value="' + d + '">' + d + '</option>'));
}

async function renderFunds() {
  const funds = await getFunds();
  const totalAllocation = funds.reduce((s, f) => s + Number(f.allocation), 0);
  const totalUsed = funds.reduce((s, f) => s + Number(f.used), 0);
  const totalRemaining = funds.reduce((s, f) => s + Number(f.remaining), 0);

  document.getElementById('fundKpis').innerHTML = [
    { label: 'Total Allocation', value: formatPeso(totalAllocation) },
    { label: 'Total Used', value: formatPeso(totalUsed) },
    { label: 'Total Remaining', value: formatPeso(totalRemaining) },
    { label: 'Active Funds', value: funds.filter(f => f.status === 'Active').length + ' of ' + funds.length }
  ].map(k => '<div class="card kpi-box"><div class="summary-label">' + k.label + '</div><div class="summary-value">' + k.value + '</div></div>').join('');

  const grid = document.getElementById('fundGrid');
  if (!funds.length) { grid.innerHTML = emptyState('No funds created yet.'); return; }

  grid.innerHTML = funds.map(f => {
    const util = f.allocation ? Math.round((f.used / f.allocation) * 100) : 0;
    const barClass = util >= 100 ? 'danger' : (util >= 80 ? 'warning' : '');
    return (
      '<div class="card record-card">' +
        '<div class="record-card-head"><strong>' + escapeHtml(f.name) + '</strong>' + statusBadge(f.status) + '</div>' +
        '<div class="record-card-meta">' + f.type + ' &middot; ' + escapeHtml(f.department) + ' &middot; ' + f.id + '</div>' +
        '<div class="record-card-figures"><span>Allocation</span><span class="val peso">' + formatPeso(f.allocation) + '</span></div>' +
        '<div class="record-card-figures"><span>Used</span><span class="val peso">' + formatPeso(f.used) + '</span></div>' +
        '<div class="record-card-figures"><span>Remaining</span><span class="val peso">' + formatPeso(f.remaining) + '</span></div>' +
        '<div class="progress-row" style="margin-top:8px;">' +
          '<div class="progress-track"><div class="progress-fill ' + barClass + '" style="width:' + Math.min(100, util) + '%"></div></div>' +
          '<span class="progress-pct">' + util + '%</span>' +
        '</div>' +
        '<div class="record-card-actions">' +
          (userCan('allocate_funds') ? '<button class="btn btn-outline btn-sm" onclick="openFundAllocateModal(\'' + f.id + '\')">Allocate</button>' : '') +
          (userCan('delete_fund') ? '<button class="btn btn-danger btn-sm" onclick="handleDeleteFund(\'' + f.id + '\')">Delete</button>' : '') +
        '</div>' +
      '</div>'
    );
  }).join('');
}

async function handleFundSubmit(e) {
  e.preventDefault();
  const form = e.target;
  const errors = validateForm(form);
  if (errors.length) { showFormErrors(form, errors); return; }

  try {
    await createFund({
      name: document.getElementById('fundName').value.trim(),
      type: document.getElementById('fundType').value,
      department: document.getElementById('fundDepartment').value,
      allocation: Number(document.getElementById('fundAllocation').value)
    });
    toast('Fund created successfully.', 'success');
    closeModal('fundModal');
    form.reset();
    await renderFunds();
  } catch (err) {
    showFormErrors(form, [err.message]);
  }
}

async function handleDeleteFund(id) {
  if (!confirm('Delete this fund? This cannot be undone.')) return;
  try {
    await deleteFund(id);
    toast('Fund deleted.', 'success');
    await renderFunds();
  } catch (err) {
    toast(err.message, 'danger');
  }
}

function openFundAllocateModal(id) {
  const f = getFund(id);
  document.getElementById('fundAllocateForm').reset();
  showFormErrors(document.getElementById('fundAllocateForm'), []);
  document.getElementById('fundAllocateId').value = f.id;
  document.getElementById('fundAllocateSub').textContent = f.name;
  document.getElementById('fundAllocateRemaining').textContent = formatPeso(f.remaining);
  document.getElementById('fundAllocateAmount').max = f.remaining;
  openModal('fundAllocateModal');
}

async function handleFundAllocateSubmit(e) {
  e.preventDefault();
  const form = e.target;
  const errors = validateForm(form);
  const id = document.getElementById('fundAllocateId').value;
  const f = getFund(id);
  const amount = Number(document.getElementById('fundAllocateAmount').value);
  if (amount > f.remaining) errors.push('Allocation cannot exceed the remaining balance of ' + formatPeso(f.remaining) + '.');
  if (errors.length) { showFormErrors(form, errors); return; }

  try {
    const result = await allocateFromFund(id, amount);
    if (result && result.error) { showFormErrors(form, [result.error]); return; }

    toast('Allocation recorded for ' + f.name + '.', 'success');
    closeModal('fundAllocateModal');
    await renderFunds();
  } catch (err) {
    showFormErrors(form, [err.message]);
  }
}
