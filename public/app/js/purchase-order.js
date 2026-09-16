/* ==========================================================================
   PURCHASE ORDER
   ========================================================================== */

let _poInventoryItems = [];
let _poLineCount = 0;

document.addEventListener('DOMContentLoaded', async () => {
  if (!(await initShell('purchase-order'))) return;

  _poInventoryItems = await getInventoryItems();
  await renderPoTable();

  document.getElementById('addPoBtn').addEventListener('click', openPoModal);
  document.getElementById('poAddLineBtn').addEventListener('click', () => addPoLineRow());
  document.getElementById('poForm').addEventListener('submit', handlePoSubmit);
  document.getElementById('poDetailCloseBtn').addEventListener('click', () => closeModal('poDetailModal'));
  document.getElementById('poDetailCloseBtn2').addEventListener('click', () => closeModal('poDetailModal'));
});

/* --------------------------------- list --------------------------------- */

async function renderPoTable() {
  const orders = await getPurchaseOrders();
  const tbody = document.querySelector('#poTable tbody');

  if (!orders.length) {
    tbody.innerHTML = '<tr><td colspan="7">' + emptyState('No purchase orders yet.') + '</td></tr>';
  } else {
    const statusColor = { Draft: 'neutral', 'Pending Approval': 'warning', Approved: 'info', Rejected: 'danger', Received: 'success' };
    tbody.innerHTML = orders.map(po =>
      '<tr><td>' + escapeHtml(po.poNumber) + '</td><td>' + escapeHtml(po.supplierName) + '</td>' +
      '<td>' + formatDate(po.orderDate) + '</td><td class="amount peso">' + formatPeso(po.totalAmount) + '</td>' +
      '<td><span class="badge badge-' + statusColor[po.status] + '">' + po.status + '</span></td>' +
      '<td>' + (po.journalEntryId || '\u2014') + '</td>' +
      '<td><button class="btn btn-ghost btn-sm" onclick="openPoDetail(' + po.id + ')">View</button></td></tr>'
    ).join('');
  }
  document.getElementById('poCount').textContent = orders.length + ' purchase order' + (orders.length === 1 ? '' : 's');
}

/* --------------------------- create modal --------------------------- */

function openPoModal() {
  const form = document.getElementById('poForm');
  form.reset();
  showFormErrors(form, []);
  document.getElementById('poDate').value = todayISO();
  document.getElementById('poItemsBody').innerHTML = '';
  _poLineCount = 0;
  addPoLineRow();
  updatePoTotal();
  openModal('poModal');
}

function addPoLineRow() {
  _poLineCount++;
  const rowId = 'poLine' + _poLineCount;
  const itemOptions = _poInventoryItems.map(i =>
    '<option value="' + i.id + '" data-cost="' + i.unitCost + '">' + escapeHtml(i.itemCode + ' — ' + i.itemName) + '</option>'
  ).join('');

  const row = document.createElement('tr');
  row.id = rowId;
  row.innerHTML =
    '<td><select class="po-line-item" required><option value="">Select item</option>' + itemOptions + '</select></td>' +
    '<td><input type="number" class="po-line-qty" min="1" value="1" required></td>' +
    '<td><input type="number" class="po-line-cost" min="0" step="0.01" value="0" required></td>' +
    '<td class="amount po-line-total peso">₱0.00</td>' +
    '<td><button type="button" class="btn btn-ghost btn-sm" onclick="removePoLineRow(\'' + rowId + '\')">Remove</button></td>';

  document.getElementById('poItemsBody').appendChild(row);

  const itemSelect = row.querySelector('.po-line-item');
  const costInput = row.querySelector('.po-line-cost');
  itemSelect.addEventListener('change', () => {
    const selected = itemSelect.options[itemSelect.selectedIndex];
    costInput.value = selected.dataset.cost || 0;
    updatePoTotal();
  });
  row.querySelector('.po-line-qty').addEventListener('input', updatePoTotal);
  costInput.addEventListener('input', updatePoTotal);
}

function removePoLineRow(rowId) {
  const body = document.getElementById('poItemsBody');
  if (body.children.length <= 1) { toast('A purchase order needs at least 1 line.', 'danger'); return; }
  document.getElementById(rowId).remove();
  updatePoTotal();
}

function updatePoTotal() {
  let grandTotal = 0;
  document.querySelectorAll('#poItemsBody tr').forEach(row => {
    const qty = Number(row.querySelector('.po-line-qty').value) || 0;
    const cost = Number(row.querySelector('.po-line-cost').value) || 0;
    const lineTotal = qty * cost;
    row.querySelector('.po-line-total').textContent = formatPeso(lineTotal);
    grandTotal += lineTotal;
  });
  document.getElementById('poTotalAmount').textContent = formatPeso(grandTotal);
}

async function handlePoSubmit(e) {
  e.preventDefault();
  const form = e.target;

  const items = Array.from(document.querySelectorAll('#poItemsBody tr')).map(row => ({
    inventory_item_id: Number(row.querySelector('.po-line-item').value),
    quantity: Number(row.querySelector('.po-line-qty').value) || 0,
    unit_cost: Number(row.querySelector('.po-line-cost').value) || 0,
  }));

  if (items.some(i => !i.inventory_item_id)) {
    showFormErrors(form, ['Every line must have an item selected.']);
    return;
  }

  const data = {
    supplierName: document.getElementById('poSupplier').value.trim(),
    orderDate: document.getElementById('poDate').value,
    items,
  };

  try {
    await createPurchaseOrder(data);
    toast('Purchase order saved as draft.', 'success');
    closeModal('poModal');
    await renderPoTable();
  } catch (err) {
    showFormErrors(form, [err.message]);
  }
}

/* --------------------------------- detail --------------------------------- */

async function openPoDetail(id) {
  const orders = await getPurchaseOrders();
  const po = orders.find(o => o.id === id);
  if (!po) return;
  renderPoDetail(po);
  openModal('poDetailModal');
}

function renderPoDetail(po) {
  document.getElementById('poDetailTitle').textContent = po.poNumber + ' \u2014 ' + po.supplierName;
  document.getElementById('poDetailSub').textContent = formatDate(po.orderDate) + ' \u00b7 ' + po.status;

  const itemsHtml = po.items.map(i =>
    '<div class="form-static-row"><span>' + escapeHtml(i.itemName) + ' \u00d7 ' + i.quantity + '</span>' +
    '<span class="val">' + formatPeso(i.lineTotal) + '</span></div>'
  ).join('');

  const stepsHtml = po.approvalSteps.length
    ? '<hr style="margin:12px 0;"><strong style="font-size:12.5px;">Approval Progress</strong>' +
      po.approvalSteps.map(s => {
        const badgeColor = s.status === 'Approved' ? 'success' : s.status === 'Rejected' ? 'danger' : 'neutral';
        return '<div class="form-static-row"><span>' + escapeHtml(s.stepName) + (s.actedBy ? ' \u00b7 ' + escapeHtml(s.actedBy) : '') + '</span>' +
          '<span class="badge badge-' + badgeColor + '">' + s.status + '</span></div>';
      }).join('')
    : '';

  document.getElementById('poDetailBody').innerHTML =
    '<div class="form-static-row"><span>Total Amount</span><span class="val">' + formatPeso(po.totalAmount) + '</span></div>' +
    '<div class="form-static-row"><span>Created By</span><span class="val">' + escapeHtml(po.createdBy || '\u2014') + '</span></div>' +
    '<hr style="margin:12px 0;">' + itemsHtml + stepsHtml;

  const footer = document.getElementById('poDetailFooter');
  footer.innerHTML = '<button class="btn btn-outline" id="poDetailCloseBtn3">Close</button>';
  document.getElementById('poDetailCloseBtn3').onclick = () => closeModal('poDetailModal');

  if (po.status === 'Draft') {
    const submitBtn = document.createElement('button');
    submitBtn.className = 'btn btn-primary';
    submitBtn.textContent = 'Submit for Approval';
    submitBtn.onclick = async () => {
      try {
        await submitPurchaseOrder(po.id);
        toast('Submitted for approval.', 'success');
        closeModal('poDetailModal');
        await renderPoTable();
      } catch (err) {
        toast(err.message, 'danger');
      }
    };
    footer.appendChild(submitBtn);
  } else if (po.status === 'Approved') {
    const receiveBtn = document.createElement('button');
    receiveBtn.className = 'btn btn-primary';
    receiveBtn.textContent = 'Receive Items';
    receiveBtn.onclick = async () => {
      if (!confirm('Receive this purchase order? This will add stock to Inventory and post to the General Ledger.')) return;
      try {
        await receivePurchaseOrder(po.id);
        toast('Items received. Inventory and General Ledger updated.', 'success');
        closeModal('poDetailModal');
        await renderPoTable();
      } catch (err) {
        toast(err.message, 'danger');
      }
    };
    footer.appendChild(receiveBtn);
  }
}