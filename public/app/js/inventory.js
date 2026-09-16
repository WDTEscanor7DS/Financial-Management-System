/* ==========================================================================
   INVENTORY
   ========================================================================== */

let _inventoryItems = [];

document.addEventListener('DOMContentLoaded', async () => {
  if (!(await initShell('inventory'))) return;

  await loadInventoryData();

  document.getElementById('addItemBtn').addEventListener('click', openItemModal);
  document.getElementById('addMovementBtn').addEventListener('click', openMovementModal);
  document.getElementById('itemForm').addEventListener('submit', handleItemSubmit);
  document.getElementById('movementForm').addEventListener('submit', handleMovementSubmit);
  document.getElementById('mvType').addEventListener('change', updateQuantityLabel);

  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => switchTab(btn.dataset.tab));
  });
});

function switchTab(tab) {
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
  document.getElementById('itemsTab').hidden = tab !== 'items';
  document.getElementById('movementsTab').hidden = tab !== 'movements';
}

async function loadInventoryData() {
  _inventoryItems = await getInventoryItems();
  renderItemsTable();
  await renderMovementsTable();
}

/* ---------------------------------- items ---------------------------------- */

function renderItemsTable() {
  const lowStockCount = _inventoryItems.filter(i => i.isLowStock).length;

  document.getElementById('invKpis').innerHTML = [
    { label: 'Total Items', value: _inventoryItems.length },
    { label: 'Low Stock Items', value: lowStockCount }
  ].map(k => '<div class="card kpi-box"><div class="summary-label">' + k.label + '</div><div class="summary-value">' + k.value + '</div></div>').join('');

  const tbody = document.querySelector('#itemsTable tbody');
  if (!_inventoryItems.length) {
    tbody.innerHTML = '<tr><td colspan="7">' + emptyState('No inventory items yet.') + '</td></tr>';
  } else {
    tbody.innerHTML = _inventoryItems.map(i => {
      const qtyDisplay = i.isLowStock
        ? '<span style="color:var(--danger,red); font-weight:600;">' + i.quantityOnHand + ' \u26a0</span>'
        : i.quantityOnHand;
      return (
        '<tr><td>' + escapeHtml(i.itemCode) + '</td><td>' + escapeHtml(i.itemName) + '</td>' +
        '<td>' + escapeHtml(i.category || '\u2014') + '</td><td>' + escapeHtml(i.unitOfMeasure) + '</td>' +
        '<td class="amount peso">' + formatPeso(i.unitCost) + '</td><td class="amount">' + qtyDisplay + '</td>' +
        '<td><span class="badge badge-' + (i.status === 'Active' ? 'success' : 'neutral') + '">' + i.status + '</span></td></tr>'
      );
    }).join('');
  }
  document.getElementById('itemsCount').textContent = _inventoryItems.length + ' item' + (_inventoryItems.length === 1 ? '' : 's');
}

function openItemModal() {
  const form = document.getElementById('itemForm');
  form.reset();
  showFormErrors(form, []);
  openModal('itemModal');
}

async function handleItemSubmit(e) {
  e.preventDefault();
  const form = e.target;

  const data = {
    itemCode: document.getElementById('itemCode').value.trim(),
    itemName: document.getElementById('itemName').value.trim(),
    category: document.getElementById('itemCategory').value.trim(),
    unitOfMeasure: document.getElementById('itemUom').value.trim(),
    unitCost: Number(document.getElementById('itemUnitCost').value) || 0,
    reorderLevel: Number(document.getElementById('itemReorderLevel').value) || 0,
  };

  try {
    await createInventoryItem(data);
    toast('Item added successfully.', 'success');
    closeModal('itemModal');
    await loadInventoryData();
  } catch (err) {
    showFormErrors(form, [err.message]);
  }
}

/* -------------------------------- movements -------------------------------- */

async function renderMovementsTable() {
  const movements = await getStockMovements();
  const tbody = document.querySelector('#movementsTable tbody');

  if (!movements.length) {
    tbody.innerHTML = '<tr><td colspan="7">' + emptyState('No stock movements recorded yet.') + '</td></tr>';
    return;
  }

  tbody.innerHTML = movements.map(m =>
    '<tr><td>' + m.id + '</td><td>' + formatDate(m.movedAt) + '</td>' +
    '<td>' + escapeHtml(m.itemName) + '</td><td>' + m.type + '</td>' +
    '<td class="amount">' + m.quantity + '</td><td>' + escapeHtml(m.description) + '</td>' +
    '<td>' + escapeHtml(m.createdBy || '\u2014') + '</td></tr>'
  ).join('');
}

function openMovementModal() {
  const form = document.getElementById('movementForm');
  form.reset();
  showFormErrors(form, []);
  document.getElementById('mvDate').value = todayISO();

  document.getElementById('mvItem').innerHTML = _inventoryItems.map(i =>
    '<option value="' + i.id + '">' + escapeHtml(i.itemCode + ' — ' + i.itemName) + ' (' + i.quantityOnHand + ' on hand)</option>'
  ).join('');

  updateQuantityLabel();
  openModal('movementModal');
}

function updateQuantityLabel() {
  const type = document.getElementById('mvType').value;
  const label = document.getElementById('mvQtyLabel');
  const input = document.getElementById('mvQuantity');

  if (type === 'Adjustment') {
    label.innerHTML = 'Adjustment (+/-)<span class="req">*</span>';
    input.removeAttribute('min');
  } else {
    label.innerHTML = 'Quantity<span class="req">*</span>';
    input.setAttribute('min', '1');
  }
}

async function handleMovementSubmit(e) {
  e.preventDefault();
  const form = e.target;

  const data = {
    inventoryItemId: Number(document.getElementById('mvItem').value),
    type: document.getElementById('mvType').value,
    quantity: Number(document.getElementById('mvQuantity').value),
    movedAt: document.getElementById('mvDate').value,
    referenceNo: document.getElementById('mvReference').value.trim(),
    description: document.getElementById('mvDescription').value.trim(),
  };

  try {
    await createStockMovement(data);
    toast('Stock movement recorded.', 'success');
    closeModal('movementModal');
    await loadInventoryData();
  } catch (err) {
    showFormErrors(form, [err.message]);
  }
}