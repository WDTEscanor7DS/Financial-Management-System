/* ==========================================================================
   ASSET AND DEPRECIATION MANAGEMENT
   ========================================================================== */

document.addEventListener('DOMContentLoaded', async () => {
  if (!(await initShell('assets'))) return;

  await populateAssetFilters();
  await renderAssetTable();

  document.getElementById('addAssetBtn').addEventListener('click', () => openAssetModal());
  document.getElementById('assetForm').addEventListener('submit', handleAssetSubmit);
  ['assetSearch', 'assetCategoryFilter', 'assetDeptFilter'].forEach(id => document.getElementById(id).addEventListener('input', renderAssetTable));
  document.getElementById('assetResetFilters').addEventListener('click', () => {
    ['assetSearch', 'assetCategoryFilter', 'assetDeptFilter'].forEach(id => document.getElementById(id).value = '');
    renderAssetTable();
  });
});

async function populateAssetFilters() {
  const deptSel = document.getElementById('assetDepartment');
  const deptFilter = document.getElementById('assetDeptFilter');
  (await getDepartments()).forEach(d => {
    deptSel.insertAdjacentHTML('beforeend', '<option value="' + d + '">' + d + '</option>');
    deptFilter.insertAdjacentHTML('beforeend', '<option value="' + d + '">' + d + '</option>');
  });
  ['IT Equipment', 'Office Equipment', 'Transportation', 'Facilities', 'Furniture & Fixtures'].forEach(c => {
    document.getElementById('assetCategoryFilter').insertAdjacentHTML('beforeend', '<option value="' + c + '">' + c + '</option>');
  });
}

async function getFilteredAssets() {
  const search = document.getElementById('assetSearch').value;
  const category = document.getElementById('assetCategoryFilter').value;
  const dept = document.getElementById('assetDeptFilter').value;
  const all = await getAssets();
  return filterRows(all, search, ['assetName', 'serialNo'], (a) =>
    (!category || a.category === category) && (!dept || a.department === dept)
  );
}

async function renderAssetTable() {
  const all = await getAssets();
  const totalCost = all.reduce((s, a) => s + Number(a.purchaseCost), 0);
  const totalBook = all.reduce((s, a) => s + a.bookValue, 0);
  const totalAnnualDep = all.reduce((s, a) => s + a.annualDepreciation, 0);

  document.getElementById('assetKpis').innerHTML = [
    { label: 'Total Purchase Cost', value: formatPeso(totalCost) },
    { label: 'Total Book Value', value: formatPeso(totalBook) },
    { label: 'Annual Depreciation (All Assets)', value: formatPeso(totalAnnualDep) },
    { label: 'Assets Recorded', value: all.length }
  ].map(k => '<div class="card kpi-box"><div class="summary-label">' + k.label + '</div><div class="summary-value">' + k.value + '</div></div>').join('');

  const rows = await getFilteredAssets();
  const tbody = document.querySelector('#assetTable tbody');
  if (!rows.length) {
    tbody.innerHTML = '<tr><td colspan="10">' + emptyState('No assets match your filters.') + '</td></tr>';
  } else {
    tbody.innerHTML = rows.map(a => (
      '<tr>' +
        '<td>' + a.id + '</td><td>' + escapeHtml(a.assetName) + '</td><td>' + escapeHtml(a.category) + '</td>' +
        '<td>' + escapeHtml(a.department) + '</td><td>' + formatDate(a.purchaseDate) + '</td>' +
        '<td class="amount peso">' + formatPeso(a.purchaseCost) + '</td>' +
        '<td class="amount peso">' + formatPeso(a.accumulatedDepreciation) + '</td>' +
        '<td class="amount peso">' + formatPeso(a.bookValue) + '</td>' +
        '<td>' + statusBadge(a.status) + '</td>' +
        '<td><div class="row-actions">' +
          '<button class="btn btn-ghost btn-sm" onclick="viewAsset(\'' + a.id + '\')">View</button>' +
          (userCan('edit_asset') ? '<button class="btn btn-outline btn-sm" onclick="openAssetModal(\'' + a.id + '\')">Edit</button>' : '') +
          (userCan('delete_asset') ? '<button class="btn btn-danger btn-sm" onclick="handleDeleteAsset(\'' + a.id + '\')">Delete</button>' : '') +
        '</div></td>' +
      '</tr>'
    )).join('');
  }
  document.getElementById('assetCount').textContent = rows.length + ' asset' + (rows.length === 1 ? '' : 's');
}

function openAssetModal(id) {
  const form = document.getElementById('assetForm');
  form.reset();
  showFormErrors(form, []);
  if (id) {
    const a = getAsset(id);
    document.getElementById('assetModalTitle').textContent = 'Edit Asset';
    document.getElementById('assetId').value = a.id;
    document.getElementById('assetName').value = a.assetName;
    document.getElementById('assetCategory').value = a.category;
    document.getElementById('assetSerial').value = a.serialNo || '';
    document.getElementById('assetPurchaseDate').value = a.purchaseDate;
    document.getElementById('assetCost').value = a.purchaseCost;
    document.getElementById('assetUsefulLife').value = a.usefulLife;
    document.getElementById('assetSalvage').value = a.salvageValue;
    document.getElementById('assetDepartment').value = a.department;
    document.getElementById('assetLocation').value = a.location || '';
    document.getElementById('assetStatus').value = a.status;
  } else {
    document.getElementById('assetModalTitle').textContent = 'Add Asset';
    document.getElementById('assetId').value = '';
    document.getElementById('assetPurchaseDate').value = todayISO();
    document.getElementById('assetSalvage').value = 0;
  }
  openModal('assetModal');
}

async function handleAssetSubmit(e) {
  e.preventDefault();
  const form = e.target;
  const errors = validateForm(form);
  if (errors.length) { showFormErrors(form, errors); return; }

  const id = document.getElementById('assetId').value;
  const data = {
    assetName: document.getElementById('assetName').value.trim(),
    category: document.getElementById('assetCategory').value,
    serialNo: document.getElementById('assetSerial').value.trim(),
    purchaseDate: document.getElementById('assetPurchaseDate').value,
    purchaseCost: Number(document.getElementById('assetCost').value),
    usefulLife: Number(document.getElementById('assetUsefulLife').value),
    salvageValue: Number(document.getElementById('assetSalvage').value || 0),
    department: document.getElementById('assetDepartment').value,
    location: document.getElementById('assetLocation').value.trim(),
    status: document.getElementById('assetStatus').value
  };

  try {
    if (id) { await updateAsset(id, data); toast('Asset updated successfully.', 'success'); }
    else { await createAsset(data); toast('Asset added successfully.', 'success'); }

    closeModal('assetModal');
    await renderAssetTable();
  } catch (err) {
    showFormErrors(form, [err.message]);
  }
}

async function handleDeleteAsset(id) {
  if (!confirm('Delete asset ' + id + '? This cannot be undone.')) return;
  try {
    await deleteAsset(id);
    toast('Asset deleted.', 'success');
    await renderAssetTable();
  } catch (err) {
    toast(err.message, 'danger');
  }
}

function viewAsset(id) {
  const a = getAsset(id);
  document.getElementById('assetViewSub').textContent = a.id + ' \u00b7 ' + a.assetName;
  document.getElementById('assetViewBody').innerHTML = [
    ['Category', a.category], ['Serial No.', a.serialNo || '\u2014'], ['Department', a.department], ['Location', a.location || '\u2014'],
    ['Purchase Date', formatDate(a.purchaseDate)], ['Purchase Cost', formatPeso(a.purchaseCost)],
    ['Useful Life', a.usefulLife + ' years'], ['Salvage Value', formatPeso(a.salvageValue)],
    ['Annual Depreciation', formatPeso(a.annualDepreciation)], ['Accumulated Depreciation', formatPeso(a.accumulatedDepreciation)],
    ['Current Book Value', formatPeso(a.bookValue)], ['Status', a.status]
  ].map(row => '<div class="form-static-row"><span>' + row[0] + '</span><span class="val">' + escapeHtml(String(row[1])) + '</span></div>').join('');
  openModal('assetViewModal');
}
