/* ==========================================================================
   BUDGET PLANNING AND ALLOCATION
   ========================================================================== */

document.addEventListener('DOMContentLoaded', async () => {
  if (!(await initShell('budget'))) return;

  await populateDepartmentSelect();
  await populateYearFilter();
  await renderBudgets();

  document.getElementById('addBudgetBtn').addEventListener('click', () => openBudgetModal());
  document.getElementById('budgetForm').addEventListener('submit', handleBudgetSubmit);
  ['budgetSearch', 'budgetYearFilter', 'budgetDeptFilter', 'budgetStatusFilter'].forEach(id => {
    document.getElementById(id).addEventListener('input', renderBudgets);
  });
  document.getElementById('budgetResetFilters').addEventListener('click', () => {
    document.getElementById('budgetSearch').value = '';
    document.getElementById('budgetYearFilter').value = '';
    document.getElementById('budgetDeptFilter').value = '';
    document.getElementById('budgetStatusFilter').value = '';
    renderBudgets();
  });
});

async function populateDepartmentSelect() {
  const sel = document.getElementById('budgetDepartment');
  const deptFilter = document.getElementById('budgetDeptFilter');
  const departments = await getDepartments();
  departments.forEach(d => {
    sel.insertAdjacentHTML('beforeend', '<option value="' + d + '">' + d + '</option>');
    deptFilter.insertAdjacentHTML('beforeend', '<option value="' + d + '">' + d + '</option>');
  });
}
async function populateYearFilter() {
  const budgets = await getBudgets();
  const years = Array.from(new Set(budgets.map(b => b.fiscalYear))).sort();
  const sel = document.getElementById('budgetYearFilter');
  sel.innerHTML = '<option value="">All Fiscal Years</option>';
  years.forEach(y => sel.insertAdjacentHTML('beforeend', '<option value="' + y + '">' + y + '</option>'));
}

async function renderBudgets() {
  const budgets = await getBudgets();
  const search = document.getElementById('budgetSearch').value;
  const year = document.getElementById('budgetYearFilter').value;
  const dept = document.getElementById('budgetDeptFilter').value;
  const status = document.getElementById('budgetStatusFilter').value;

  const filtered = filterRows(budgets, search, ['department', 'category'], (b) =>
    (!year || b.fiscalYear === year) && (!dept || b.department === dept) && (!status || b.status === status)
  );

  renderBudgetKpis(budgets);

  const grid = document.getElementById('budgetGrid');
  if (!filtered.length) {
    grid.innerHTML = emptyState('No budgets match your filters.', 'Try clearing filters or create a new budget.');
    return;
  }

  grid.innerHTML = filtered.map(b => {
    const util = budgetUtilization(b);
    const remaining = b.allocated - b.actualSpending;
    const barClass = util >= 100 ? 'danger' : (util >= 80 ? 'warning' : '');
    return (
      '<div class="card record-card">' +
        '<div class="record-card-head"><strong>' + escapeHtml(b.category) + '</strong>' + statusBadge(b.status) + '</div>' +
        '<div class="record-card-meta">' + escapeHtml(b.department) + ' &middot; FY ' + b.fiscalYear + ' &middot; ' + b.id + '</div>' +
        '<div class="record-card-figures"><span>Allocated</span><span class="val peso">' + formatPeso(b.allocated) + '</span></div>' +
        '<div class="record-card-figures"><span>Actual Spending</span><span class="val peso">' + formatPeso(b.actualSpending) + '</span></div>' +
        '<div class="record-card-figures"><span>Remaining</span><span class="val peso ' + (remaining < 0 ? 'text-danger' : '') + '">' + formatPeso(remaining) + '</span></div>' +
        '<div class="progress-row" style="margin-top:8px;">' +
          '<div class="progress-track"><div class="progress-fill ' + barClass + '" style="width:' + Math.min(100, util) + '%"></div></div>' +
          '<span class="progress-pct">' + util + '%</span>' +
        '</div>' +
        (util >= 80 ? '<p class="text-danger" style="font-size:11.5px;margin-top:8px;">' + (util >= 100 ? 'Over budget — actual spending has exceeded allocation.' : 'Warning: utilization has crossed 80% of allocation.') + '</p>' : '') +
        '<div class="record-card-actions">' +
          (userCan('edit_budget') ? '<button class="btn btn-outline btn-sm" onclick="openBudgetModal(\'' + b.id + '\')">Edit</button>' : '') +
          (userCan('delete_budget') ? '<button class="btn btn-danger btn-sm" onclick="handleDeleteBudget(\'' + b.id + '\')">Delete</button>' : '') +
        '</div>' +
      '</div>'
    );
  }).join('');
}

function renderBudgetKpis(budgets) {
  const totalAllocated = budgets.reduce((s, b) => s + Number(b.allocated || 0), 0);
  const totalActual = budgets.reduce((s, b) => s + Number(b.actualSpending || 0), 0);
  const overBudgetCount = budgets.filter(b => budgetUtilization(b) >= 100).length;
  const warningCount = budgets.filter(b => { const u = budgetUtilization(b); return u >= 80 && u < 100; }).length;

  document.getElementById('budgetKpis').innerHTML = [
    { label: 'Total Allocated', value: formatPeso(totalAllocated) },
    { label: 'Total Actual Spending', value: formatPeso(totalActual) },
    { label: 'Remaining Across All Budgets', value: formatPeso(totalAllocated - totalActual) },
    { label: 'Budgets Needing Attention', value: (overBudgetCount + warningCount) + ' of ' + budgets.length }
  ].map(k => '<div class="card kpi-box"><div class="summary-label">' + k.label + '</div><div class="summary-value">' + k.value + '</div></div>').join('');
}

function openBudgetModal(id) {
  const form = document.getElementById('budgetForm');
  form.reset();
  showFormErrors(form, []);
  if (id) {
    const b = getBudget(id);
    document.getElementById('budgetModalTitle').textContent = 'Edit Budget';
    document.getElementById('budgetId').value = b.id;
    document.getElementById('budgetFiscalYear').value = b.fiscalYear;
    document.getElementById('budgetDepartment').value = b.department;
    document.getElementById('budgetCategory').value = b.category;
    document.getElementById('budgetAllocated').value = b.allocated;
    document.getElementById('budgetStatus').value = b.status;
  } else {
    document.getElementById('budgetModalTitle').textContent = 'Create Budget';
    document.getElementById('budgetId').value = '';
  }
  openModal('budgetModal');
}

async function handleBudgetSubmit(e) {
  e.preventDefault();
  const form = e.target;
  const errors = validateForm(form);
  if (errors.length) { showFormErrors(form, errors); return; }

  const id = document.getElementById('budgetId').value;
  const data = {
    fiscalYear: document.getElementById('budgetFiscalYear').value,
    department: document.getElementById('budgetDepartment').value,
    category: document.getElementById('budgetCategory').value.trim(),
    allocated: Number(document.getElementById('budgetAllocated').value),
    status: document.getElementById('budgetStatus').value
  };

  try {
    if (id) {
      await updateBudget(id, data);
      toast('Budget updated successfully.', 'success');
    } else {
      await createBudget(data);
      toast('Budget created successfully.', 'success');
    }
    closeModal('budgetModal');
    await populateYearFilter();
    await renderBudgets();
  } catch (err) {
    // Server-side validation (e.g. a duplicate department/year/category
    // combination) surfaces here even though the client-side check above
    // already passed -- the database's unique constraint is authoritative.
    showFormErrors(form, [err.message]);
  }
}

async function handleDeleteBudget(id) {
  if (!confirm('Delete this budget? This cannot be undone.')) return;
  try {
    await deleteBudget(id);
    toast('Budget deleted.', 'success');
    await renderBudgets();
  } catch (err) {
    toast(err.message, 'danger');
  }
}
