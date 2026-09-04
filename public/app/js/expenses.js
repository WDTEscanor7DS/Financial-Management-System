/* ==========================================================================
   EXPENSE AND DISBURSEMENT TRACKING
   ========================================================================== */

document.addEventListener('DOMContentLoaded', async () => {
  if (!(await initShell('expenses'))) return;

  await populateExpenseFilters();
  await renderExpenseTable();

  document.getElementById('addExpenseBtn').addEventListener('click', () => openExpenseModal());
  document.getElementById('expenseForm').addEventListener('submit', handleExpenseSubmit);
  document.getElementById('exportExpenseBtn').addEventListener('click', exportExpenseCSV);
  ['expenseSearch', 'expenseCategoryFilter', 'expenseDeptFilter', 'expenseStatusFilter'].forEach(id => {
    document.getElementById(id).addEventListener('input', renderExpenseTable);
  });
  document.getElementById('expenseResetFilters').addEventListener('click', () => {
    ['expenseSearch', 'expenseCategoryFilter', 'expenseDeptFilter', 'expenseStatusFilter'].forEach(id => document.getElementById(id).value = '');
    renderExpenseTable();
  });
});

async function populateExpenseFilters() {
  const deptSel = document.getElementById('expenseDepartment');
  const deptFilter = document.getElementById('expenseDeptFilter');
  (await getDepartments()).forEach(d => {
    deptSel.insertAdjacentHTML('beforeend', '<option value="' + d + '">' + d + '</option>');
    deptFilter.insertAdjacentHTML('beforeend', '<option value="' + d + '">' + d + '</option>');
  });
  ['Salaries', 'Utilities', 'Supplies', 'Maintenance', 'Procurement', 'Transportation', 'Equipment', 'Other Operating Expenses'].forEach(c => {
    document.getElementById('expenseCategoryFilter').insertAdjacentHTML('beforeend', '<option value="' + c + '">' + c + '</option>');
  });
  ['Paid', 'Pending'].forEach(s => document.getElementById('expenseStatusFilter').insertAdjacentHTML('beforeend', '<option value="' + s + '">' + s + '</option>'));

  const budgetSel = document.getElementById('expenseBudget');
  (await getBudgets()).forEach(b => {
    budgetSel.insertAdjacentHTML('beforeend', '<option value="' + b.id + '">' + b.department + ' — ' + b.category + ' (FY' + b.fiscalYear + ')</option>');
  });
}

async function getFilteredExpenses() {
  const search = document.getElementById('expenseSearch').value;
  const category = document.getElementById('expenseCategoryFilter').value;
  const dept = document.getElementById('expenseDeptFilter').value;
  const status = document.getElementById('expenseStatusFilter').value;

  const all = await getExpenses();
  return filterRows(all, search, ['description', 'vendor', 'referenceNo'], (e) =>
    (!category || e.expenseCategory === category) && (!dept || e.department === dept) && (!status || e.status === status)
  ).sort((a, b) => new Date(b.date) - new Date(a.date));
}

async function renderExpenseTable() {
  const rows = await getFilteredExpenses();
  const total = rows.reduce((s, r) => s + Number(r.amount), 0);
  const all = await getExpenses();

  document.getElementById('expenseKpis').innerHTML = [
    { label: 'Total Expenses (Filtered)', value: formatPeso(total) },
    { label: 'Transactions', value: rows.length },
    { label: 'All-Time Total Expenses', value: formatPeso(all.reduce((s, r) => s + Number(r.amount), 0)) },
    { label: 'Linked to a Budget', value: all.filter(e => e.budgetId).length + ' of ' + all.length }
  ].map(k => '<div class="card kpi-box"><div class="summary-label">' + k.label + '</div><div class="summary-value">' + k.value + '</div></div>').join('');

  const tbody = document.querySelector('#expenseTable tbody');
  if (!rows.length) {
    tbody.innerHTML = '<tr><td colspan="9">' + emptyState('No expense records match your filters.') + '</td></tr>';
  } else {
    tbody.innerHTML = rows.map(e => (
      '<tr>' +
        '<td>' + e.id + '</td><td>' + formatDate(e.date) + '</td><td>' + escapeHtml(e.department) + '</td>' +
        '<td>' + escapeHtml(e.expenseCategory) + '</td><td>' + escapeHtml(e.description) + '</td>' +
        '<td>' + escapeHtml(e.vendor) + '</td><td class="amount peso">' + formatPeso(e.amount) + '</td><td>' + statusBadge(e.status) + '</td>' +
        '<td><div class="row-actions">' +
          '<button class="btn btn-ghost btn-sm" onclick="viewExpense(\'' + e.id + '\')">View</button>' +
          (userCan('edit_expense') ? '<button class="btn btn-outline btn-sm" onclick="openExpenseModal(\'' + e.id + '\')">Edit</button>' : '') +
          (userCan('delete_expense') ? '<button class="btn btn-danger btn-sm" onclick="handleDeleteExpense(\'' + e.id + '\')">Delete</button>' : '') +
        '</div></td>' +
      '</tr>'
    )).join('');
  }
  document.getElementById('expenseCount').textContent = rows.length + ' record' + (rows.length === 1 ? '' : 's');
}

function openExpenseModal(id) {
  const form = document.getElementById('expenseForm');
  form.reset();
  showFormErrors(form, []);
  if (id) {
    const e = getExpense(id);
    document.getElementById('expenseModalTitle').textContent = 'Edit Expense';
    document.getElementById('expenseId').value = e.id;
    document.getElementById('expenseDate').value = e.date;
    document.getElementById('expenseDepartment').value = e.department;
    document.getElementById('expenseCategory').value = e.expenseCategory;
    document.getElementById('expenseDescription').value = e.description;
    document.getElementById('expenseVendor').value = e.vendor;
    document.getElementById('expenseReference').value = e.referenceNo || '';
    document.getElementById('expenseAmount').value = e.amount;
    document.getElementById('expensePaymentMethod').value = e.paymentMethod;
    document.getElementById('expenseStatus').value = e.status;
    document.getElementById('expenseBudget').value = e.budgetId || '';
  } else {
    document.getElementById('expenseModalTitle').textContent = 'Add Expense';
    document.getElementById('expenseId').value = '';
    document.getElementById('expenseDate').value = todayISO();
  }
  openModal('expenseModal');
}

async function handleExpenseSubmit(e) {
  e.preventDefault();
  const form = e.target;
  const errors = validateForm(form);
  if (errors.length) { showFormErrors(form, errors); return; }

  const id = document.getElementById('expenseId').value;
  const data = {
    date: document.getElementById('expenseDate').value,
    department: document.getElementById('expenseDepartment').value,
    expenseCategory: document.getElementById('expenseCategory').value,
    description: document.getElementById('expenseDescription').value.trim(),
    vendor: document.getElementById('expenseVendor').value.trim(),
    referenceNo: document.getElementById('expenseReference').value.trim(),
    amount: Number(document.getElementById('expenseAmount').value),
    paymentMethod: document.getElementById('expensePaymentMethod').value,
    status: document.getElementById('expenseStatus').value,
    budgetId: document.getElementById('expenseBudget').value || null
  };

  try {
    if (id) { await updateExpense(id, data); toast('Expense updated successfully.', 'success'); }
    else { await createExpense(data); toast('Expense recorded successfully.', 'success'); }

    closeModal('expenseModal');
    await renderExpenseTable();
  } catch (err) {
    showFormErrors(form, [err.message]);
  }
}

async function handleDeleteExpense(id) {
  if (!confirm('Delete expense record ' + id + '? This cannot be undone.')) return;
  try {
    await deleteExpense(id);
    toast('Expense record deleted.', 'success');
    await renderExpenseTable();
  } catch (err) {
    toast(err.message, 'danger');
  }
}

function viewExpense(id) {
  const e = getExpense(id);
  const budget = e.budgetId ? getBudget(e.budgetId) : null;
  document.getElementById('expenseViewSub').textContent = e.id + ' \u00b7 ' + formatDate(e.date);
  document.getElementById('expenseViewBody').innerHTML = [
    ['Department', e.department], ['Category', e.expenseCategory], ['Description', e.description],
    ['Vendor / Payee', e.vendor], ['Reference No.', e.referenceNo || '\u2014'],
    ['Amount', formatPeso(e.amount)], ['Payment Method', e.paymentMethod], ['Status', e.status],
    ['Linked Budget', budget ? (budget.department + ' — ' + budget.category) : 'Not linked']
  ].map(row => '<div class="form-static-row"><span>' + row[0] + '</span><span class="val">' + escapeHtml(String(row[1])) + '</span></div>').join('');
  openModal('expenseViewModal');
}

async function exportExpenseCSV() {
  const rows = await getFilteredExpenses();
  exportCSV('expense-export.csv',
    ['Expense ID', 'Date', 'Department', 'Category', 'Description', 'Vendor', 'Amount', 'Payment Method', 'Status'],
    rows.map(r => [r.id, r.date, r.department, r.expenseCategory, r.description, r.vendor, r.amount, r.paymentMethod, r.status])
  );
}
