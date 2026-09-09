/* ==========================================================================
   PAYROLL
   ========================================================================== */

let _departmentsList = [];

document.addEventListener('DOMContentLoaded', async () => {
  if (!(await initShell('payroll'))) return;

  _departmentsList = await getDepartments();
  await renderEmployeesTable();
  await renderPeriodsTable();

  document.getElementById('addEmployeeBtn').addEventListener('click', openEmployeeModal);
  document.getElementById('addPeriodBtn').addEventListener('click', openPeriodModal);
  document.getElementById('employeeForm').addEventListener('submit', handleEmployeeSubmit);
  document.getElementById('periodForm').addEventListener('submit', handlePeriodSubmit);
  document.getElementById('pdCloseBtn').addEventListener('click', () => closeModal('periodDetailModal'));
  document.getElementById('pdCloseBtn2').addEventListener('click', () => closeModal('periodDetailModal'));

  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => switchTab(btn.dataset.tab));
  });
});

function switchTab(tab) {
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
  document.getElementById('employeesTab').hidden = tab !== 'employees';
  document.getElementById('periodsTab').hidden = tab !== 'periods';
}

/* ------------------------------- employees ------------------------------- */

async function renderEmployeesTable() {
  const employees = await getEmployees();
  const tbody = document.querySelector('#employeesTable tbody');

  if (!employees.length) {
    tbody.innerHTML = '<tr><td colspan="7">' + emptyState('No employees added yet.') + '</td></tr>';
  } else {
    tbody.innerHTML = employees.map(e =>
      '<tr><td>' + escapeHtml(e.employeeNo) + '</td><td>' + escapeHtml(e.fullName) + '</td>' +
      '<td>' + escapeHtml(e.department || '\u2014') + '</td><td>' + escapeHtml(e.position) + '</td>' +
      '<td>' + e.employmentType + '</td><td class="amount peso">' + formatPeso(e.monthlyRate) + '</td>' +
      '<td><span class="badge badge-' + (e.status === 'Active' ? 'success' : 'neutral') + '">' + e.status + '</span></td></tr>'
    ).join('');
  }
  document.getElementById('employeesCount').textContent = employees.length + ' employee' + (employees.length === 1 ? '' : 's');
}

function openEmployeeModal() {
  const form = document.getElementById('employeeForm');
  form.reset();
  showFormErrors(form, []);

  const deptSelect = document.getElementById('empDepartment');
  deptSelect.innerHTML = '<option value="">— None —</option>' + _departmentsList.map(d =>
    '<option value="' + escapeHtml(d) + '">' + escapeHtml(d) + '</option>'
  ).join('');

  openModal('employeeModal');
}

async function handleEmployeeSubmit(e) {
  e.preventDefault();
  const form = e.target;

  const deptName = document.getElementById('empDepartment').value;
  const data = {
    employeeNo: document.getElementById('empNo').value.trim(),
    fullName: document.getElementById('empName').value.trim(),
    departmentId: null,
    position: document.getElementById('empPosition').value.trim(),
    employmentType: document.getElementById('empType').value,
    monthlyRate: Number(document.getElementById('empRate').value) || 0,
    hireDate: document.getElementById('empHireDate').value,
  };

  try {
    if (deptName) data.departmentId = await _departmentIdByName(deptName);
    await createEmployee(data);
    toast('Employee added successfully.', 'success');
    closeModal('employeeModal');
    await renderEmployeesTable();
  } catch (err) {
    showFormErrors(form, [err.message]);
  }
}

/* ---------------------------- payroll periods ---------------------------- */

async function renderPeriodsTable() {
  const periods = await getPayrollPeriods();
  const tbody = document.querySelector('#periodsTable tbody');

  if (!periods.length) {
    tbody.innerHTML = '<tr><td colspan="7">' + emptyState('No payroll runs yet.') + '</td></tr>';
    return;
  }

  const statusColor = { Draft: 'neutral', Processed: 'warning', Posted: 'success' };

  tbody.innerHTML = periods.map(p =>
    '<tr><td>' + escapeHtml(p.periodLabel) + '</td><td>' + formatDate(p.startDate) + '</td><td>' + formatDate(p.endDate) + '</td>' +
    '<td><span class="badge badge-' + statusColor[p.status] + '">' + p.status + '</span></td>' +
    '<td>' + p.payslipCount + '</td><td>' + (p.journalEntryId || '\u2014') + '</td>' +
    '<td><button class="btn btn-ghost btn-sm" onclick="openPeriodDetail(' + p.id + ')">View</button></td></tr>'
  ).join('');
}

function openPeriodModal() {
  const form = document.getElementById('periodForm');
  form.reset();
  showFormErrors(form, []);
  openModal('periodModal');
}

async function handlePeriodSubmit(e) {
  e.preventDefault();
  const form = e.target;

  const data = {
    periodLabel: document.getElementById('periodLabel').value.trim(),
    startDate: document.getElementById('periodStart').value,
    endDate: document.getElementById('periodEnd').value,
  };

  try {
    await createPayrollPeriod(data);
    toast('Payroll run created.', 'success');
    closeModal('periodModal');
    await renderPeriodsTable();
  } catch (err) {
    showFormErrors(form, [err.message]);
  }
}

/* ----------------------------- period detail ----------------------------- */

async function openPeriodDetail(id) {
  const result = await getPayrollPeriodDetail(id);
  renderPeriodDetail(result);
  openModal('periodDetailModal');
}

function renderPeriodDetail(result) {
  const period = result.data;
  const payslips = result.payslips;

  document.getElementById('pdTitle').textContent = period.periodLabel;
  document.getElementById('pdSub').textContent = period.startDate + ' \u2013 ' + period.endDate + ' \u00b7 ' + period.status;

  const tbody = document.querySelector('#payslipsTable tbody');
  if (!payslips.length) {
    tbody.innerHTML = '<tr><td colspan="7">' + emptyState('No payslips generated yet. Click "Generate Payslips" to compute this run.') + '</td></tr>';
  } else {
    tbody.innerHTML = payslips.map(p =>
      '<tr><td>' + escapeHtml(p.employeeName) + '</td>' +
      '<td class="amount peso">' + formatPeso(p.basicPay) + '</td>' +
      '<td class="amount peso">' + formatPeso(p.sssDeduction) + '</td>' +
      '<td class="amount peso">' + formatPeso(p.philhealthDeduction) + '</td>' +
      '<td class="amount peso">' + formatPeso(p.pagibigDeduction) + '</td>' +
      '<td class="amount peso">' + formatPeso(p.withholdingTax) + '</td>' +
      '<td class="amount peso">' + formatPeso(p.netPay) + '</td></tr>'
    ).join('');
  }

  const footer = document.getElementById('pdFooter');
  footer.innerHTML = '<button class="btn btn-outline" id="pdCloseBtn3">Close</button>';
  document.getElementById('pdCloseBtn3').onclick = () => closeModal('periodDetailModal');

  if (period.status === 'Draft') {
    const genBtn = document.createElement('button');
    genBtn.className = 'btn btn-primary';
    genBtn.textContent = 'Generate Payslips';
    genBtn.onclick = async () => {
      try {
        await generatePayslips(period.id);
        toast('Payslips generated.', 'success');
        const refreshed = await getPayrollPeriodDetail(period.id);
        renderPeriodDetail(refreshed);
        await renderPeriodsTable();
      } catch (err) {
        toast(err.message, 'danger');
      }
    };
    footer.appendChild(genBtn);
  } else if (period.status === 'Processed') {
    const postBtn = document.createElement('button');
    postBtn.className = 'btn btn-primary';
    postBtn.textContent = 'Post to General Ledger';
    postBtn.onclick = async () => {
      if (!confirm('Post this payroll run to the General Ledger? This cannot be undone.')) return;
      try {
        await postPayrollToLedger(period.id);
        toast('Payroll posted to General Ledger.', 'success');
        const refreshed = await getPayrollPeriodDetail(period.id);
        renderPeriodDetail(refreshed);
        await renderPeriodsTable();
      } catch (err) {
        toast(err.message, 'danger');
      }
    };
    footer.appendChild(postBtn);
  }
}