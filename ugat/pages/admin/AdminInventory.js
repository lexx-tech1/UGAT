/**
 * AdminInventory.js — UGAT TrainTrack
 * Full inventory management — connected to real database.
 * All UI, buttons and modals preserved exactly as original.
 */

/* =============================================================================
   LIVE DATA — fetched from DB
   ============================================================================= */
let ITEMS         = [];
let TRANSACTIONS  = [];
let PURCHASE_ORDERS = [];
let activeInvTab  = 'overview';
let SHOP_ORDERS = [];

/* =============================================================================
   HELPERS
   ============================================================================= */
function getStatus(item) {
  if (parseInt(item.stock) === 0) return 'out';
  if (parseInt(item.stock) <= parseInt(item.reorder_point)) return 'low';
  return 'instock';
}

function fmtPeso(n) {
  return '₱' + Number(n).toLocaleString('en-PH', { minimumFractionDigits:2, maximumFractionDigits:2 });
}

function nowStr() {
  const d = new Date();
  return d.toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'})
    + ' · ' + d.toLocaleTimeString('en-US',{hour:'2-digit',minute:'2-digit'});
}

/* =============================================================================
   DATA LOADERS
   ============================================================================= */
async function loadItems() {
  try {
    const r = await fetch('get_inventory.php?action=items', { credentials:'same-origin' });
    const d = await r.json();
    if (d.success) {
      ITEMS = d.items.map(i => ({
        ...i,
        stock:         parseInt(i.stock)         || 0,
        max_stock:     parseInt(i.max_stock)     || 50,
        reorder_point: parseInt(i.reorder_point) || 5,
        unit_price:    parseFloat(i.unit_price)  || 0,
      }));
    }
  } catch(e) { console.error('loadItems error:', e); }
}

async function loadTransactions() {
  try {
    const r = await fetch('get_inventory.php?action=transactions', { credentials:'same-origin' });
    const d = await r.json();
    if (d.success) TRANSACTIONS = d.transactions;
  } catch(e) { console.error('loadTransactions error:', e); }
}

async function loadPurchaseOrders() {
  try {
    const r = await fetch('get_inventory.php?action=purchase_orders', { credentials:'same-origin' });
    const d = await r.json();
    if (d.success) PURCHASE_ORDERS = d.orders;
  } catch(e) { console.error('loadPurchaseOrders error:', e); }
}

async function reloadAll() {
  await Promise.all([loadItems(), loadTransactions(), loadPurchaseOrders()]);
}

/* =============================================================================
   TAB SWITCHING
   ============================================================================= */
function switchInvTab(tab, btn) {
  document.querySelectorAll('.inv-tab-bar > .inv-tab').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.querySelectorAll('.inv-tab-content.top-tab').forEach(el => el.style.display = 'none');
  document.getElementById('tab-' + tab).style.display = '';
  activeInvTab = tab;

  const ha = document.getElementById('inv-header-actions');
  ha.innerHTML = '';
  if (tab === 'overview' || tab === 'products') {
    ha.innerHTML = `<button class="btn-secondary" onclick="openModal('po-modal')">🛒 Raise PO</button>
                    <button class="btn-primary" onclick="openModal('add-item-modal')">+ Add Item</button>`;
    } else if (tab === 'shoporders') {
    ha.innerHTML = '';
  } else if (tab === 'transactions') {
    ha.innerHTML = `<button class="btn-secondary" onclick="exportTransactions()">Export CSV</button>`;
  } else if (tab === 'orders') {
    ha.innerHTML = `<button class="btn-primary" onclick="openModal('po-modal')">+ New Purchase Order</button>`;
  } else if (tab === 'reports') {
    ha.innerHTML = `<button class="btn-secondary" onclick="exportAllReports()">Export Report</button>`;
  }

  const renderers = { overview:renderOverview, products:renderProducts, shoporders:renderShopOrders, transactions:renderTransactions, orders:renderOrders, reports:renderReports };
  if (renderers[tab]) renderers[tab]();
}

function renderShopOrders() {
    loadShopOrders().then(() => {
        renderSOOverview();
        renderSOTable();
        renderSOStatusList('pending',          'so-list-pending');
        renderSOStatusList('confirmed',        'so-list-confirmed');
        renderSOStatusList('preparing',        'so-list-preparing');
        renderSOStatusList('out_for_delivery', 'so-list-otd');
        renderSOStatusList('delivered',        'so-list-delivered');
        renderSOStatusList('cancelled',        'so-list-cancelled');
        updateSOBadges();
    });
}
/* =============================================================================
   ALERT BANNER
   ============================================================================= */
function checkAlerts() {
  const el       = document.getElementById('stock-alert');
  const critical = ITEMS.filter(i => getStatus(i) === 'out');
  const low      = ITEMS.filter(i => getStatus(i) === 'low');
  const parts    = [];
  if (critical.length) parts.push(`<strong>Out of stock:</strong> ${critical.map(i=>i.name).join(', ')}`);
  if (low.length)      parts.push(`<strong>Low stock:</strong> ${low.map(i=>`${i.name} (${i.stock} left)`).join(', ')}`);
  if (parts.length) { el.innerHTML = '⚠️ ' + parts.join(' &nbsp;·&nbsp; '); el.style.display = ''; }
  else               { el.style.display = 'none'; }
}

/* =============================================================================
   TAB 1 — OVERVIEW
   ============================================================================= */
function renderOverview() {
  const total   = ITEMS.length;
  const instock = ITEMS.filter(i => getStatus(i) === 'instock').length;
  const low     = ITEMS.filter(i => getStatus(i) === 'low').length;
  const out     = ITEMS.filter(i => getStatus(i) === 'out').length;
  const value   = ITEMS.reduce((s,i) => s + i.unit_price * i.stock, 0);

  document.getElementById('ov-total').textContent   = total;
  document.getElementById('ov-instock').textContent = instock;
  document.getElementById('ov-low').textContent     = low;
  document.getElementById('ov-out').textContent     = out;
  document.getElementById('ov-value').textContent   = fmtPeso(value);

  // Stock health
  const sorted = [...ITEMS].sort((a,b) => (a.stock/a.max_stock) - (b.stock/b.max_stock));
  document.getElementById('ov-health-list').innerHTML = sorted.map(item => {
    const pct    = Math.round((item.stock / item.max_stock) * 100);
    const status = getStatus(item);
    const clr    = status === 'out' ? 'var(--color-danger)' : status === 'low' ? 'var(--color-warning)' : 'var(--color-primary)';
    return `
      <div class="ov-health-item">
        <div class="ov-health-row">
          <span class="ov-health-name">${item.name}</span>
          <span class="ov-health-qty">${item.stock} / ${item.max_stock} ${item.unit}</span>
        </div>
        <div class="progress-bar" style="height:7px">
          <div class="progress-fill" style="width:${pct}%;background:${clr}"></div>
        </div>
      </div>`;
  }).join('');

  // Recent movements (last 5)
  const recent = TRANSACTIONS.slice(0, 5);
  document.getElementById('ov-recent-list').innerHTML = recent.length ? recent.map(tx => {
    const isIn  = tx.type === 'in' || tx.type === 'po';
    const date  = tx.created_at ? new Date(tx.created_at).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}) : '—';
    return `
      <div class="ov-tx-row">
        <div class="ov-tx-icon ${isIn ? 'in' : 'out'}">${isIn ? '↑' : '↓'}</div>
        <div class="ov-tx-info">
          <div class="ov-tx-name">${tx.item_name}</div>
          <div class="ov-tx-meta">${date}</div>
        </div>
        <div class="ov-tx-qty" style="color:${isIn?'#4B8423':'#e65100'}">
          ${isIn?'+':'-'}${tx.qty} ${tx.unit||''}
        </div>
      </div>`;
  }).join('') : '<p class="light-txt" style="padding:1rem">No recent movements.</p>';

  // Reorder alerts
  const alerts = ITEMS.filter(i => getStatus(i) !== 'instock');
  document.getElementById('ov-alerts-list').innerHTML = alerts.length === 0
    ? `<p class="light-txt" style="padding:1rem 0">✅ All items are at healthy stock levels.</p>`
    : alerts.map(item => {
        const status   = getStatus(item);
        const suggest  = Math.max(item.max_stock - item.stock, item.reorder_point * 2);
        const badgeCls = status === 'out' ? 'badge-outstock' : 'badge-lowstock';
        const badgeTxt = status === 'out' ? 'Out of stock'   : 'Low stock';
        return `
          <div class="ov-alert-row">
            <div class="ov-alert-info">
              <div class="ov-alert-name">${item.name} <span style="font-size:var(--text-tiny);color:var(--color-text-mid)">${item.sku||''}</span></div>
              <div class="ov-alert-meta">
                Current: ${item.stock} ${item.unit} &nbsp;·&nbsp;
                Reorder point: ${item.reorder_point} &nbsp;·&nbsp;
                Supplier: ${item.supplier||'—'}
              </div>
            </div>
            <span class="badge ${badgeCls}">${badgeTxt}</span>
            <span class="light-txt">Suggested order: <strong>${suggest}</strong></span>
            <button class="btn-sm" onclick="openPOForItem(${item.id})">🛒 Order Now</button>
          </div>`;
      }).join('');

  checkAlerts();
}

/* =============================================================================
   TAB 2 — PRODUCTS
   ============================================================================= */
function renderProducts() {
  const search = document.getElementById('prod-search')?.value.trim().toLowerCase() || '';
  const cat    = document.getElementById('prod-cat')?.value    || '';
  const status = document.getElementById('prod-status')?.value || '';
  const sort   = document.getElementById('prod-sort')?.value   || 'name';

  let list = ITEMS.filter(item => {
    const ms  = !search || item.name.toLowerCase().includes(search) || (item.sku||'').toLowerCase().includes(search) || (item.description||'').toLowerCase().includes(search);
    const mc  = !cat    || item.category === cat;
    const mst = !status || getStatus(item) === status;
    return ms && mc && mst;
  });

if      (sort === 'newest')      list = [...list].sort((a,b) => b.id - a.id);
else if (sort === 'stock-desc')  list = [...list].sort((a,b) => b.stock - a.stock);
else if (sort === 'stock-asc')   list = [...list].sort((a,b) => a.stock - b.stock);
else if (sort === 'value-desc')  list = [...list].sort((a,b) => (b.unit_price*b.stock)-(a.unit_price*a.stock));
else if (sort === 'price-desc')  list = [...list].sort((a,b) => b.unit_price - a.unit_price);
else                             list = [...list].sort((a,b) => a.name.localeCompare(b.name));

const totalValue = ITEMS.reduce((s,i) => s + i.unit_price * i.stock, 0);

  const tbody = document.getElementById('products-tbody');
  tbody.innerHTML = list.map(item => {
    const st   = getStatus(item);
    const pct  = Math.min(100, Math.round((item.stock / item.max_stock) * 100));
    const clr  = st==='out'?'var(--color-danger)':st==='low'?'var(--color-warning)':'var(--color-primary)';
    const bCls = st==='instock'?'badge-instock':st==='low'?'badge-lowstock':'badge-outstock';
    const bTxt = st==='instock'?'In Stock':st==='low'?'Low Stock':'Out of Stock';
    const val  = item.unit_price * item.stock;
    const pctOfTotal = totalValue > 0 ? ((val/totalValue)*100).toFixed(1)+'%' : '—';
    const soDisabled = item.stock === 0 ? 'disabled style="opacity:0.4;cursor:not-allowed"' : '';
const imgHtml = item.image
  ? `<img src="../../${item.image}" style="width:48px;height:48px;object-fit:cover;border-radius:6px;border:1px solid #eee">`
  : `<div style="width:48px;height:48px;background:#f5f5f5;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;color:#ccc">📷</div>`;
return `
  <tr>
    <td>${imgHtml}</td>
    <td>
      <div style="display:flex;flex-direction:column;gap:0.1rem">
        <span class="trainee-name">${item.name}</span>
        <span class="light-txt">${item.description||''}</span>
      </div>
    </td>
    <td><code style="font-size:var(--text-caption);background:#f5f5f5;padding:0.15rem 0.4rem;border-radius:3px">${item.sku||'—'}</code></td>
        <td>${item.category === 'farm' ? 'Farm Product' : 'Training Kit'}</td>
        <td>${fmtPeso(item.unit_price)} / ${item.unit}</td>
        <td>
          <strong>${item.stock}</strong> <span class="light-txt">${item.unit}</span>
          ${item.stock <= item.reorder_point && item.stock > 0 ? `<span style="color:var(--color-warning);font-size:var(--text-tiny);display:block">↓ Below reorder pt (${item.reorder_point})</span>` : ''}
        </td>
        <td><span class="light-txt">${item.weight_kg ? item.weight_kg + ' kg' : '—'}</span></td>
        <td><span class="light-txt">${item.reorder_point} ${item.unit}</span></td>
        <td>${fmtPeso(val)}<br><span class="light-txt">${pctOfTotal} of total</span></td>
        <td style="min-width:90px">
          <div class="progress-bar" style="height:7px">
            <div class="progress-fill" style="width:${pct}%;background:${clr}"></div>
          </div>
          <span class="light-txt" style="font-size:var(--text-tiny)">${pct}%</span>
        </td>
        <td><span class="badge ${bCls}">${bTxt}</span></td>
        <td>
          <div style="display:flex;gap:0.3rem;flex-wrap:wrap">
            <button class="btn-xs green" onclick="openStockIn(${item.id})" title="Stock In">↑ In</button>
            <button class="btn-xs" onclick="openStockOut(${item.id})" ${soDisabled} title="Stock Out">↓ Out</button>
            <button class="btn-xs" onclick="openEditItem(${item.id})" title="Edit">✏️</button>
            <button class="btn-xs red" onclick="openDeleteItem(${item.id})" title="Delete">🗑</button>
          </div>
        </td>
      </tr>`;
  }).join('') || `<tr><td colspan="11" style="text-align:center;padding:2rem;color:#aaa">No items found.</td></tr>`;

  document.getElementById('products-count').textContent = `Showing ${list.length} of ${ITEMS.length} items`;
}

/* =============================================================================
   TAB 3 — TRANSACTIONS  (replace the entire renderTransactions function)
   
   Adjustment type now correctly shows:
     - Green  + when after_qty > before_qty  (stock increased)
     - Red    - when after_qty < before_qty  (stock decreased)
     - Gray   ± when after_qty === before_qty (no change / verification)
   
   ============================================================================= */
function renderTransactions() {
  const search = document.getElementById('tx-search')?.value.trim().toLowerCase() || '';
  const type   = document.getElementById('tx-type')?.value  || '';
  const itemF  = document.getElementById('tx-item')?.value  || '';
  const sort   = document.getElementById('tx-sort')?.value  || 'newest';

  // Populate item filter dropdown once
  const txItemSel = document.getElementById('tx-item');
  if (txItemSel && txItemSel.options.length <= 1) {
    ITEMS.forEach(i => {
      const opt = document.createElement('option');
      opt.value = i.id; opt.textContent = i.name;
      txItemSel.appendChild(opt);
    });
  }

  let list = TRANSACTIONS.filter(tx => {
    const ms = !search || (tx.item_name||'').toLowerCase().includes(search) || (tx.note||'').toLowerCase().includes(search);
    const mt = !type   || tx.type === type;
    const mi = !itemF  || tx.item_id == parseInt(itemF);
    return ms && mt && mi;
  });

  if (sort === 'oldest')      list = [...list].sort((a,b) => new Date(a.created_at) - new Date(b.created_at));
  else if (sort === 'qty-desc') list = [...list].sort((a,b) => b.qty - a.qty);
  // newest is default (already ordered by DB DESC)

  // KPIs
  const allTx  = TRANSACTIONS;
  const inTx   = allTx.filter(t => t.type === 'in' || t.type === 'po');
  const outTx  = allTx.filter(t => t.type === 'out');
  const outVal = outTx.reduce((s,t) => s + (parseInt(t.qty)||0) * (parseFloat(t.unit_price)||0), 0);

  document.getElementById('tx-total-count').textContent = allTx.length;
  document.getElementById('tx-in-count').textContent    = inTx.length;
  document.getElementById('tx-out-count').textContent   = outTx.length;
  document.getElementById('tx-value').textContent       = fmtPeso(outVal);

  const typeMap = { in:'tx-type-in', out:'tx-type-out', po:'tx-type-po', adj:'tx-type-adj' };
  const typeLbl = { in:'↑ Stock In', out:'↓ Stock Out', po:'↑ PO Receipt', adj:'⚙ Adjustment' };

  document.getElementById('tx-tbody').innerHTML = list.map(tx => {
    const val      = (parseInt(tx.qty)||0) * (parseFloat(tx.unit_price)||0);
    const before   = parseInt(tx.before_qty) || 0;
    const after    = parseInt(tx.after_qty)  || 0;

    const date = tx.created_at
      ? new Date(tx.created_at).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'})
        + ' · ' + new Date(tx.created_at).toLocaleTimeString('en-US',{hour:'2-digit',minute:'2-digit'})
      : '—';

    // ── Determine direction, sign, and color ─────────────────
    // Standard types: in/po = always green+, out = always red-
    // Adjustment:     check actual before→after change instead of assuming red-
    let isIn, sign, qtyColor;

    if (tx.type === 'in' || tx.type === 'po') {
      isIn      = true;
      sign      = '+';
      qtyColor  = '#4B8423';                  // green

    } else if (tx.type === 'out') {
      isIn      = false;
      sign      = '-';
      qtyColor  = 'var(--color-danger)';      // red

    } else {
      // Adjustment — look at what actually happened
      if (after > before) {
        isIn     = true;
        sign     = '+';
        qtyColor = '#4B8423';                 // green  — stock went up
      } else if (after < before) {
        isIn     = false;
        sign     = '-';
        qtyColor = 'var(--color-danger)';     // red    — stock went down
      } else {
        isIn     = null;
        sign     = '±';
        qtyColor = '#888';                    // gray   — no change (verification)
      }
    }

    // Display qty: for no-change adjustments show 0, otherwise show tx.qty
    const displayQty = (after === before) ? '0' : tx.qty;

    return `
      <tr>
        <td class="light-txt">${date}</td>
        <td><code style="font-size:var(--text-caption)">TXN-${String(tx.id).padStart(4,'0')}</code></td>
        <td>${tx.item_name||'—'}</td>
        <td><span class="${typeMap[tx.type]||'tx-type-adj'}">${typeLbl[tx.type]||tx.type}</span></td>
        <td>
          <strong style="color:${qtyColor}">${sign}${displayQty}</strong>
        </td>
        <td>${before}</td>
        <td>${after}</td>
        <td>${fmtPeso(tx.unit_price||0)}</td>
        <td>${fmtPeso(val)}</td>
        <td>
          <div style="font-size:var(--text-caption)">${tx.note||'—'}</div>
          ${tx.reference ? `<div class="light-txt">${tx.reference}</div>` : ''}
        </td>
        <td class="light-txt">${tx.recorded_by_name||'Admin'}</td>
      </tr>`;
  }).join('') || `<tr><td colspan="11" style="text-align:center;padding:2rem;color:#aaa">No transactions yet.</td></tr>`;

  document.getElementById('tx-count').textContent = `Showing ${list.length} of ${TRANSACTIONS.length} transactions`;
}

/* =============================================================================
   TAB 4 — PURCHASE ORDERS
   ============================================================================= */
function renderOrders() {
  const pending  = PURCHASE_ORDERS.filter(p => p.status === 'pending').length;
  const received = PURCHASE_ORDERS.filter(p => p.status === 'received').length;
  const totalVal = PURCHASE_ORDERS.reduce((s,p) => s + (parseInt(p.qty_ordered)||0) * (parseFloat(p.unit_price)||0), 0);

  document.getElementById('po-total').textContent    = PURCHASE_ORDERS.length;
  document.getElementById('po-pending').textContent  = pending;
  document.getElementById('po-received').textContent = received;
  document.getElementById('po-value').textContent    = fmtPeso(totalVal);

  const stMap = { pending:'po-pending', received:'po-received', partial:'po-partial', cancelled:'po-cancelled' };
  const stLbl = { pending:'Pending', received:'Received', partial:'Partial', cancelled:'Cancelled' };

  document.getElementById('po-tbody').innerHTML = PURCHASE_ORDERS.map(po => {
    const total    = (parseInt(po.qty_ordered)||0) * (parseFloat(po.unit_price)||0);
    const date     = po.created_at ? new Date(po.created_at).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}) : '—';
    const exp_date = po.expected_date ? new Date(po.expected_date).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}) : '—';
    const actions  = po.status === 'pending'
      ? `<button class="btn-sm" onclick="openReceivePO(${po.id},'${po.po_number}',${po.qty_ordered},${po.qty_received})">✓ Receive</button>
         <button class="btn-xs red" onclick="cancelPO(${po.id})">Cancel</button>`
      : `<span class="light-txt">Received ${po.qty_received} ${po.unit||''}</span>`;
    return `
      <tr>
        <td><code style="font-size:var(--text-caption)">${po.po_number}</code>${po.priority==='urgent'?` <span class="po-urgent">Urgent</span>`:''}</td>
        <td class="light-txt">${date}</td>
        <td>${po.item_name||'—'}</td>
        <td><strong>${po.qty_ordered}</strong></td>
        <td>${fmtPeso(po.unit_price||0)}</td>
        <td>${fmtPeso(total)}</td>
        <td>${po.supplier}</td>
        <td class="light-txt">${exp_date}</td>
        <td><span class="${stMap[po.status]||'po-pending'}">${stLbl[po.status]||po.status}</span></td>
        <td><div style="display:flex;gap:0.4rem;align-items:center">${actions}</div></td>
      </tr>`;
  }).join('') || `<tr><td colspan="10" style="text-align:center;padding:2rem;color:#aaa">No purchase orders yet.</td></tr>`;
}

/* =============================================================================
   TAB 5 — REPORTS
   ============================================================================= */
function renderReports() {
  const totalValue = ITEMS.reduce((s,i) => s + i.unit_price * i.stock, 0);
  const farmValue  = ITEMS.filter(i=>i.category==='farm').reduce((s,i)=>s+i.unit_price*i.stock,0);
  const kitValue   = ITEMS.filter(i=>i.category==='kit') .reduce((s,i)=>s+i.unit_price*i.stock,0);
  const atRisk     = ITEMS.filter(i=>getStatus(i)!=='instock').reduce((s,i)=>s+i.unit_price*i.stock,0);

  document.getElementById('rpt-total-value').textContent = fmtPeso(totalValue);
  document.getElementById('rpt-farm-value').textContent  = fmtPeso(farmValue);
  document.getElementById('rpt-kit-value').textContent   = fmtPeso(kitValue);
  document.getElementById('rpt-at-risk').textContent     = fmtPeso(atRisk);

  // Stock value breakdown
  const sorted = [...ITEMS].sort((a,b)=>(b.unit_price*b.stock)-(a.unit_price*a.stock));
  document.getElementById('rpt-value-tbody').innerHTML = sorted.map(item => {
    const val = item.unit_price * item.stock;
    const pct = totalValue > 0 ? ((val/totalValue)*100).toFixed(1) : '0';
    return `
      <tr>
        <td>${item.name}</td>
        <td>${item.category==='farm'?'Farm Product':'Training Kit'}</td>
        <td>${item.stock} ${item.unit}</td>
        <td>${fmtPeso(item.unit_price)}</td>
        <td><strong>${fmtPeso(val)}</strong></td>
        <td>
          <div style="display:flex;align-items:center;gap:0.5rem">
            <div class="progress-bar" style="width:80px"><div class="progress-fill" style="width:${pct}%"></div></div>
            <span class="light-txt">${pct}%</span>
          </div>
        </td>
      </tr>`;
  }).join('');

  // Reorder summary
  const needReorder = ITEMS.filter(i => i.stock <= i.reorder_point);
  document.getElementById('rpt-reorder-tbody').innerHTML = needReorder.length === 0
    ? `<tr><td colspan="6" style="text-align:center;padding:1rem;color:var(--color-text-light)">All items above reorder points ✅</td></tr>`
    : needReorder.map(item => {
        const suggest = Math.max(item.max_stock - item.stock, item.reorder_point * 2);
        const cost    = suggest * item.unit_price;
        const priority= item.stock === 0 ? '<span class="prio-high">🔴 Critical</span>' : '<span class="prio-medium">🟡 Medium</span>';
        return `
          <tr>
            <td>${item.name}</td>
            <td><strong style="color:${item.stock===0?'var(--color-danger)':'var(--color-warning)'}">${item.stock} ${item.unit}</strong></td>
            <td>${item.reorder_point} ${item.unit}</td>
            <td>${suggest} ${item.unit}</td>
            <td>${fmtPeso(cost)}</td>
            <td>${priority}</td>
          </tr>`;
      }).join('');

  // Stock movement summary
  document.getElementById('rpt-movement-tbody').innerHTML = ITEMS.map(item => {
    const txIn  = TRANSACTIONS.filter(t=>(t.type==='in'||t.type==='po') && t.item_id==item.id).reduce((s,t)=>s+(parseInt(t.qty)||0),0);
    const txOut = TRANSACTIONS.filter(t=>t.type==='out' && t.item_id==item.id).reduce((s,t)=>s+(parseInt(t.qty)||0),0);
    const net   = txIn - txOut;
    const turnover = txIn > 0 ? ((txOut/txIn)*100).toFixed(0)+'%' : '—';
    return `
      <tr>
        <td>${item.name}</td>
        <td><span style="color:#4B8423;font-weight:700">+${txIn}</span></td>
        <td><span style="color:var(--color-danger);font-weight:700">-${txOut}</span></td>
        <td><strong>${net >= 0 ? '+' : ''}${net}</strong></td>
        <td>${turnover}</td>
      </tr>`;
  }).join('');
}

/* =============================================================================
   ADD ITEM
   ============================================================================= */

// Preview what SKU will look like based on selected category
function previewSKU() {
  const cat    = document.getElementById('ai-cat')?.value || 'farm';
  const prefix = cat === 'kit' ? 'KIT' : 'FARM';
  const count  = ITEMS.filter(i => i.category === cat).length + 1;
  const preview = `${prefix}-${String(count).padStart(3, '0')} (auto)`;
  const el = document.getElementById('ai-sku-preview');
  if (el) el.textContent = preview;
}

function previewItemImage(prefix) {
  const input = document.getElementById(prefix + '-img-input');
  if (!input.files || !input.files[0]) return;
  const file = input.files[0];
  if (file.size > 2 * 1024 * 1024) { showToast('Image must be under 2MB.'); return; }

  const reader = new FileReader();
  reader.onload = function(e) {
    const preview = document.getElementById(prefix + '-img-preview');
    const placeholder = document.getElementById(prefix + '-img-placeholder');
    const thumb = document.getElementById(prefix + '-img-thumb');

    if (prefix === 'ai') {
      preview.innerHTML = `<img src="${e.target.result}" style="width:100%;height:100%;object-fit:cover">`;
    } else {
      if (placeholder) placeholder.style.display = 'none';
      if (thumb) { thumb.src = e.target.result; thumb.style.display = 'block'; }
    }
  };
  reader.readAsDataURL(file);
}

async function uploadItemImage(prefix) {
  const input = document.getElementById(prefix + '-img-input');
  if (!input.files || !input.files[0]) return '';
  const formData = new FormData();
  formData.append('image', input.files[0]);
  try {
    const r = await fetch('upload_item_image.php', { method:'POST', credentials:'same-origin', body: formData });
    const d = await r.json();
    return d.success ? d.path : '';
  } catch(e) { return ''; }
}

async function submitNewItem() {
  const name      = document.getElementById('ai-name').value.trim();
  const desc      = document.getElementById('ai-desc').value.trim();
  const cat       = document.getElementById('ai-cat').value;
  const unit      = document.getElementById('ai-unit').value;
  const price     = parseFloat(document.getElementById('ai-price').value);
  const stock     = parseInt(document.getElementById('ai-stock').value) || 0;
  const maxStock  = parseInt(document.getElementById('ai-maxstock').value) || 50;
  const reorderPt = parseInt(document.getElementById('ai-reorder').value) || 10;
  const supplier  = document.getElementById('ai-supplier').value.trim();
  const weight_kg = parseFloat(document.getElementById('ai-weight')?.value) || 0;
  const errEl     = document.getElementById('ai-error');

  if (!name || isNaN(price)) {
    errEl.textContent = 'Please fill in all required fields.'; errEl.style.display = 'block'; return;
  }
  errEl.style.display = 'none';

  try {
const imgPath = await uploadItemImage('ai');
const r = await fetch('save_inventory.php', {
  method:'POST', credentials:'same-origin',
  headers:{'Content-Type':'application/json'},
  body: JSON.stringify({ action:'add_item', name, description:desc, image:imgPath, category:cat, unit, unit_price:price, opening_stock:stock, max_stock:maxStock, reorder_point:reorderPt, supplier, weight_kg }),
});
    const d = await r.json();
    if (d.success) {
      closeModal('add-item-modal');
      showToast(` ${d.message}${d.sku ? ' (SKU: ' + d.sku + ')' : ''}`);
      await reloadAll();
      refreshCurrentTab();
      checkAlerts();
    } else {
      errEl.textContent = d.message; errEl.style.display = 'block';
    }
  } catch(e) { showToast('Could not add item.'); }
}

/* =============================================================================
   EDIT ITEM
   ============================================================================= */
function openEditItem(id) {
  const item = ITEMS.find(x => x.id == id); if (!item) return;
  document.getElementById('ei-name').value     = item.name;
  document.getElementById('ei-sku').value      = item.sku || '';
  document.getElementById('ei-desc').value     = item.description || '';
  document.getElementById('ei-cat').value      = item.category;
  document.getElementById('ei-unit').value     = item.unit;
  document.getElementById('ei-price').value    = item.unit_price;
  document.getElementById('ei-maxstock').value = item.max_stock;
  document.getElementById('ei-reorder').value  = item.reorder_point;
  document.getElementById('ei-supplier').value = item.supplier || '';
document.getElementById('ei-id').value       = id;
const storedKg = parseFloat(item.weight_kg) || 0;
document.getElementById('ei-weight').value = storedKg.toFixed(3);
if (storedKg > 0) {
    if (storedKg < 1) {
        document.getElementById('ei-weight-qty').value  = (storedKg * 1000).toFixed(0);
        document.getElementById('ei-weight-unit').value = 'g';
    } else {
        document.getElementById('ei-weight-qty').value  = storedKg.toFixed(3);
        document.getElementById('ei-weight-unit').value = 'kg';
    }
    document.getElementById('ei-weight-result').textContent = storedKg.toFixed(3);
    // Update the hint text
    const hint = document.getElementById('ei-weight-hint');
    if (hint) {
        hint.textContent = '✓ ' + storedKg.toFixed(3) + ' kg will be used for shipping calculation.';
        hint.style.color = '#4B8423';
    }
} else {
    // Reset display fields if weight is 0
    document.getElementById('ei-weight-qty').value  = '';
    document.getElementById('ei-weight-unit').value = 'g';
    document.getElementById('ei-weight-result').textContent = '0.000';
}
document.getElementById('ei-img-path').value = item.image || '';
document.getElementById('ei-error').style.display = 'none';
// Show existing image
const thumb = document.getElementById('ei-img-thumb');
const ph    = document.getElementById('ei-img-placeholder');
if (item.image) {
  thumb.src = '../../' + item.image;
  thumb.style.display = 'block';
  if (ph) ph.style.display = 'none';
} else {
  thumb.style.display = 'none';
  if (ph) ph.style.display = '';
}
openModal('edit-item-modal');
}

async function saveEditItem() {
  const id    = parseInt(document.getElementById('ei-id').value);
  const name  = document.getElementById('ei-name').value.trim();
  const sku   = document.getElementById('ei-sku').value.trim();
  const errEl = document.getElementById('ei-error');
  if (!name || !sku) { errEl.textContent='Required fields missing.'; errEl.style.display='block'; return; }
  errEl.style.display = 'none';

  try {
    const newImg  = await uploadItemImage('ei');
    const imgPath = newImg || document.getElementById('ei-img-path').value || '';

    const r = await fetch('save_inventory.php', {
      method:'POST', credentials:'same-origin',
      headers:{'Content-Type':'application/json'},
        body: JSON.stringify({
          action:        'edit_item',
          id,
          name,
          sku,
          image:         imgPath,
          description:   document.getElementById('ei-desc').value.trim(),
          category:      document.getElementById('ei-cat').value,
          unit:          document.getElementById('ei-unit').value,
          unit_price:    parseFloat(document.getElementById('ei-price').value),
          max_stock:     parseInt(document.getElementById('ei-maxstock').value) || 50,
          reorder_point: parseInt(document.getElementById('ei-reorder').value)  || 10,
          supplier:      document.getElementById('ei-supplier').value.trim(),
          weight_kg:     parseFloat(document.getElementById('ei-weight')?.value) || 0,
        }),
    });
    const d = await r.json();
    if (d.success) {
      closeModal('edit-item-modal');
      showToast(`✅ ${d.message}`);
      await loadItems();
      refreshCurrentTab();
      checkAlerts();
    } else { errEl.textContent = d.message; errEl.style.display = 'block'; }
  } catch(e) { showToast('Could not save changes.'); }
}

/* =============================================================================
   STOCK IN
   ============================================================================= */
function openStockIn(id) {
  const item = ITEMS.find(x => x.id == id); if (!item) return;
  document.getElementById('si-item-id').value    = id;
  document.getElementById('si-sub').textContent  = item.name;
  document.getElementById('si-qty').value        = '';
  document.getElementById('si-unit-price').value = item.unit_price;
  document.getElementById('si-supplier').value   = item.supplier || '';
  document.getElementById('si-po-ref').value     = '';
  document.getElementById('si-note').value       = '';
  document.getElementById('si-summary').innerHTML =
    `<span>Current stock: <strong>${item.stock} ${item.unit}</strong></span>
     <span>Unit price: <strong>${fmtPeso(item.unit_price)}</strong></span>`;
  document.getElementById('si-preview').textContent = '';
  openModal('stockin-modal');
}

document.addEventListener('input', function(e) {
  if (e.target.id === 'si-qty') {
    const id   = parseInt(document.getElementById('si-item-id').value);
    const item = ITEMS.find(x => x.id == id);
    const qty  = parseInt(e.target.value) || 0;
    if (item && qty > 0)
      document.getElementById('si-preview').textContent = `New stock after: ${item.stock + qty} ${item.unit}`;
  }
  if (e.target.id === 'so-qty') {
    const id   = parseInt(document.getElementById('so-item-id').value);
    const item = ITEMS.find(x => x.id == id);
    const qty  = parseInt(e.target.value) || 0;
    const errEl= document.getElementById('so-error');
    if (item && qty > 0) {
      if (qty > item.stock) {
        errEl.textContent = `Only ${item.stock} ${item.unit} in stock.`; errEl.style.display = 'block';
        document.getElementById('so-preview').textContent = '';
      } else {
        errEl.style.display = 'none';
        document.getElementById('so-preview').textContent = `New stock after: ${item.stock - qty} ${item.unit}`;
      }
    }
  }
});

async function confirmStockIn() {
  const id       = parseInt(document.getElementById('si-item-id').value);
  const qty      = parseInt(document.getElementById('si-qty').value);
  const price    = parseFloat(document.getElementById('si-unit-price').value);
  const supplier = document.getElementById('si-supplier').value.trim();
  const ref      = document.getElementById('si-po-ref').value.trim();
  const note     = document.getElementById('si-note').value.trim();
  const item     = ITEMS.find(x => x.id == id);
  if (!item || !qty || qty < 1) { showToast('Enter a valid quantity.'); return; }

  try {
    const r = await fetch('save_inventory.php', {
      method:'POST', credentials:'same-origin',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ action:'stock_in', item_id:id, qty, unit_price:price||item.unit_price, supplier, reference:ref, note:note||'Stock In' }),
    });
    const d = await r.json();
    if (d.success) {
      closeModal('stockin-modal');
      showToast(` +${qty} added to "${item.name}". Stock: ${d.new_stock} ${item.unit}`);
      await reloadAll(); refreshCurrentTab(); checkAlerts();
    } else { showToast(`❌ ${d.message}`); }
  } catch(e) { showToast('Could not record stock in.'); }
}

/* =============================================================================
   STOCK OUT
   ============================================================================= */
function openStockOut(id) {
  const item = ITEMS.find(x => x.id == id);
  if (!item || item.stock === 0) { showToast('No stock available.'); return; }
  document.getElementById('so-item-id').value    = id;
  document.getElementById('so-sub').textContent  = item.name;
  document.getElementById('so-qty').value        = '';
  document.getElementById('so-ref').value        = '';
  document.getElementById('so-note').value       = '';
  document.getElementById('so-error').style.display = 'none';
  document.getElementById('so-summary').innerHTML =
    `<span>Current stock: <strong>${item.stock} ${item.unit}</strong></span>
     <span>Unit price: <strong>${fmtPeso(item.unit_price)}</strong></span>`;
  document.getElementById('so-preview').textContent = '';
  openModal('stockout-modal');
}

async function confirmStockOut() {
  const id     = parseInt(document.getElementById('so-item-id').value);
  const qty    = parseInt(document.getElementById('so-qty').value);
  const reason = document.getElementById('so-reason').value;
  const ref    = document.getElementById('so-ref').value.trim();
  const note   = document.getElementById('so-note').value.trim();
  const errEl  = document.getElementById('so-error');
  const item   = ITEMS.find(x => x.id == id);
  if (!item || !qty || qty < 1) { showToast('Enter a valid quantity.'); return; }
  if (qty > item.stock) { errEl.textContent=`Only ${item.stock} ${item.unit} in stock.`; errEl.style.display='block'; return; }
  errEl.style.display = 'none';

  try {
    const r = await fetch('save_inventory.php', {
      method:'POST', credentials:'same-origin',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ action:'stock_out', item_id:id, qty, reason, reference:ref, note }),
    });
    const d = await r.json();
    if (d.success) {
      closeModal('stockout-modal');
      showToast(` -${qty} removed from "${item.name}". Stock: ${d.new_stock} ${item.unit}`);
      await reloadAll(); refreshCurrentTab(); checkAlerts();
    } else { errEl.textContent = d.message; errEl.style.display = 'block'; }
  } catch(e) { showToast('Could not record stock out.'); }
}

/* =============================================================================
   PURCHASE ORDERS
   ============================================================================= */
function populatePODropdown() {
  const sel = document.getElementById('po-item');
  sel.innerHTML = '<option value="">Select item</option>';
  ITEMS.forEach(i => {
    const opt = document.createElement('option');
    opt.value = i.id; opt.textContent = `${i.name} (${i.sku||'—'})`;
    sel.appendChild(opt);
  });
}

function fillPOPrice() {
  const id   = parseInt(document.getElementById('po-item').value);
  const item = ITEMS.find(x => x.id == id);
  if (item) document.getElementById('po-unit-price').value = item.unit_price;
  calcPOTotal();
}

function calcPOTotal() {
  const qty   = parseInt(document.getElementById('po-qty').value) || 0;
  const price = parseFloat(document.getElementById('po-unit-price').value) || 0;
  document.getElementById('po-total-display').textContent = fmtPeso(qty * price);
}

function openPOForItem(itemId) {
  populatePODropdown();
  document.getElementById('po-item').value = itemId;
  fillPOPrice();
  const item = ITEMS.find(x => x.id == itemId);
  if (item) document.getElementById('po-supplier').value = item.supplier || '';
  openModal('po-modal');
}

async function submitPO() {
  const item_id  = parseInt(document.getElementById('po-item').value);
  const supplier = document.getElementById('po-supplier').value.trim();
  const qty      = parseInt(document.getElementById('po-qty').value);
  const price    = parseFloat(document.getElementById('po-unit-price').value);
  const date     = document.getElementById('po-date').value;
  const priority = document.getElementById('po-priority').value;
  const note     = document.getElementById('po-note').value.trim();
  const errEl    = document.getElementById('po-error');
  const item     = ITEMS.find(x => x.id == item_id);

if (!item || !qty || !price || !date) {
    errEl.textContent = 'Please fill in all required fields.'; errEl.style.display = 'block'; return;
  }
  errEl.style.display = 'none';

  try {
    const r = await fetch('save_inventory.php', {
      method:'POST', credentials:'same-origin',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ action:'submit_po', item_id, supplier, qty, unit_price:price, expected_date:date, priority, note }),
    });
    const d = await r.json();
    if (d.success) {
      closeModal('po-modal');
      showToast(` ${d.message}`);
      await reloadAll();
      switchInvTab('orders', document.querySelector('[data-tab=orders]'));
    } else { errEl.textContent = d.message; errEl.style.display = 'block'; }
  } catch(e) { showToast('Could not submit PO.'); }
}

function openReceivePO(poId, poNumber, qtyOrdered, qtyReceived) {
  document.getElementById('recv-po-id').value      = poId;
  document.getElementById('recv-sub').textContent  = `${poNumber}`;
  document.getElementById('recv-qty').value        = qtyOrdered - qtyReceived;
  document.getElementById('recv-note').value       = '';
  document.getElementById('recv-error').style.display = 'none';
  document.getElementById('recv-summary').innerHTML =
    `<span>Ordered: <strong>${qtyOrdered}</strong></span>
     <span>Already received: <strong>${qtyReceived}</strong></span>`;
  openModal('receive-po-modal');
}

async function confirmReceivePO() {
  const po_id = parseInt(document.getElementById('recv-po-id').value);
  const qty   = parseInt(document.getElementById('recv-qty').value);
  const note  = document.getElementById('recv-note').value.trim();
  const errEl = document.getElementById('recv-error');

  if (!qty || qty < 1) { showToast('Enter a valid quantity.'); return; }

  try {
    const r = await fetch('save_inventory.php', {
      method:'POST', credentials:'same-origin',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ action:'receive_po', po_id, qty, note }),
    });
    const d = await r.json();
    if (d.success) {
      closeModal('receive-po-modal');
      showToast(` ${d.message}`);
      await reloadAll(); renderOrders(); checkAlerts();
    } else { errEl.textContent = d.message; errEl.style.display = 'block'; }
  } catch(e) { showToast('Could not receive PO.'); }
}

async function cancelPO(poId) {
  try {
    const r = await fetch('save_inventory.php', {
      method:'POST', credentials:'same-origin',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ action:'cancel_po', po_id:poId }),
    });
    const d = await r.json();
    showToast(d.success ? `PO cancelled.` : `❌ ${d.message}`);
    if (d.success) { await loadPurchaseOrders(); renderOrders(); }
  } catch(e) { showToast('Could not cancel PO.'); }
}

/* =============================================================================
   DELETE ITEM
   ============================================================================= */
function openDeleteItem(id) {
  const item = ITEMS.find(x => x.id == id); if (!item) return;
  document.getElementById('delete-item-id').value       = id;
  document.getElementById('delete-item-msg').textContent =
    `Permanently remove "${item.name}" (${item.sku||'—'}) from inventory? This cannot be undone.`;
  openModal('delete-item-modal');
}

async function confirmDeleteItem() {
  const id   = parseInt(document.getElementById('delete-item-id').value);
  const item = ITEMS.find(x => x.id == id);
  try {
    const r = await fetch('save_inventory.php', {
      method:'POST', credentials:'same-origin',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ action:'delete_item', id }),
    });
    const d = await r.json();
    closeModal('delete-item-modal');
    showToast(d.success ? `🗑️ "${item?.name}" removed.` : `❌ ${d.message}`);
    if (d.success) { await reloadAll(); refreshCurrentTab(); checkAlerts(); }
  } catch(e) { showToast('Could not delete item.'); }
}

/* =============================================================================
   EXPORT
   ============================================================================= */
function exportTransactions() {
  showToast('📥 Downloading CSV...');
  const a = document.createElement('a');
  a.href = 'export_inventory.php?type=movements&format=csv';
  a.download = 'UGAT_Transactions.csv';
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
}

function exportAllReports() {
  const existing = document.getElementById('inv-export-modal');
  if (existing) existing.remove();

  const overlay = document.createElement('div');
  overlay.id = 'inv-export-modal';
  overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:9999;display:flex;align-items:center;justify-content:center';
  overlay.innerHTML = `
    <div style="background:#fff;border-radius:14px;padding:2rem;max-width:360px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.2)">
      <h3 style="font-size:1.05rem;font-weight:700;margin-bottom:0.25rem">Export All Reports</h3>
      <p style="font-size:0.83rem;color:#888;margin-bottom:1.5rem">Stock Value · Reorder Summary · Stock Movements</p>
      <div style="display:flex;flex-direction:column;gap:0.65rem">
        <button id="inv-exp-all-pdf" style="background:#4B8423;color:#fff;border:none;padding:0.75rem 1rem;border-radius:8px;cursor:pointer;font-size:0.9rem;font-weight:600">🖨 Open & Print / Save as PDF</button>
        <button id="inv-exp-all-csv" style="background:#1a56db;color:#fff;border:none;padding:0.75rem 1rem;border-radius:8px;cursor:pointer;font-size:0.9rem;font-weight:600">📊 Download CSV (All)</button>
      </div>
      <button id="inv-exp-all-cancel" style="margin-top:1rem;width:100%;background:none;border:none;color:#aaa;cursor:pointer;font-size:0.82rem;padding:0.4rem">Cancel</button>
    </div>`;
  document.body.appendChild(overlay);
  overlay.querySelector('#inv-exp-all-pdf').onclick = () => {
    overlay.remove();
    showToast('📥 Opening full report...');
    window.open('export_inventory.php?type=all&format=pdf', '_blank');
  };
  overlay.querySelector('#inv-exp-all-csv').onclick = () => {
    overlay.remove();
    showToast('📥 Downloading CSV...');
    window.open('export_inventory.php?type=all&format=csv', '_blank');
  };
  overlay.querySelector('#inv-exp-all-cancel').onclick = () => overlay.remove();
  overlay.addEventListener('click', e => { if (e.target === overlay) overlay.remove(); });
}

function exportReport(scope) {
  const typeMap = { value:'value', reorder:'reorder', movements:'movements', all:'all' };
  const type = typeMap[scope] || scope;
  window.open('export_inventory.php?type=' + type + '&format=pdf', '_blank');
}

function exportAllReports() {
  window.open('export_inventory.php?type=all&format=pdf', '_blank');
}

/* =============================================================================
   UTILITIES
   ============================================================================= */
function refreshCurrentTab() {
const renderers = { overview:renderOverview, products:renderProducts, shoporders:renderShopOrders, transactions:renderTransactions, orders:renderOrders, reports:renderReports };
  if (renderers[activeInvTab]) renderers[activeInvTab]();
}

function openModal(id) {
  if (id === 'po-modal') populatePODropdown();
  if (id === 'add-item-modal') setTimeout(previewSKU, 50);
  document.getElementById(id)?.classList.add('open');
}
function closeModal(id) { document.getElementById(id)?.classList.remove('open'); }
function closeModalOutside(e, id) { if (e.target.id === id) closeModal(id); }

function showToast(msg) {
  const t = document.getElementById('toast');
  if (!t) return;
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 3000);
}

/* =============================================================================
   INIT
   ============================================================================= */
document.addEventListener('DOMContentLoaded', async function () {
  await reloadAll();
  switchInvTab('overview', document.querySelector('[data-tab=overview]'));

  // Auto-refresh shop orders every 30 seconds
  setInterval(() => {
    if (activeInvTab === 'shoporders') {
      loadShopOrders().then(() => {
        renderSOOverview();
        renderSOTable();
        renderSOStatusList('pending',          'so-list-pending');
        renderSOStatusList('confirmed',        'so-list-confirmed');
        renderSOStatusList('preparing',        'so-list-preparing');
        renderSOStatusList('out_for_delivery', 'so-list-otd');
        renderSOStatusList('delivered',        'so-list-delivered');
        renderSOStatusList('cancelled',        'so-list-cancelled');
        updateSOBadges();
      });
    }
  }, 30000); // every 30 seconds
});

/* =============================================================
   WEIGHT CONVERTER
   Converts admin-entered quantity + unit → kg
   Stores result in hidden #ai-weight / #ei-weight field
   ============================================================= */
function convertWeight(prefix) {
    const qty    = parseFloat(document.getElementById(prefix + '-weight-qty')?.value) || 0;
    const unit   = document.getElementById(prefix + '-weight-unit')?.value || 'g';
    const result = document.getElementById(prefix + '-weight-result');
    const hidden = document.getElementById(prefix + '-weight');
    const hint   = document.getElementById(prefix + '-weight-hint');
 
    // Conversion factors to kg
    const toKg = {
        'g':  qty / 1000,
        'kg': qty,
        'ml': qty / 1000,   // approximation: 1ml ≈ 1g for most liquids
        'L':  qty,           // approximation: 1L ≈ 1kg for most liquids
        'lb': qty * 0.453592,
        'oz': qty * 0.0283495,
    };
 
    const kg = toKg[unit] ?? 0;
    const rounded = Math.round(kg * 1000) / 1000; // 3 decimal places
 
    if (result) result.textContent = rounded.toFixed(3);
    if (hidden) hidden.value = rounded.toFixed(3);
 
    // Update hint with helpful context
    if (hint && qty > 0) {
        const examples = {
            'g':  `${qty}g = ${rounded.toFixed(3)} kg`,
            'kg': `${qty} kg`,
            'ml': `${qty}ml ≈ ${rounded.toFixed(3)} kg`,
            'L':  `${qty}L ≈ ${rounded.toFixed(3)} kg`,
            'lb': `${qty} lb = ${rounded.toFixed(3)} kg`,
            'oz': `${qty} oz = ${rounded.toFixed(3)} kg`,
        };
        hint.textContent = '✓ ' + (examples[unit] || '') + ' will be used for shipping calculation.';
        hint.style.color = '#4B8423';
    } else if (hint) {
        hint.textContent = 'Enter the physical weight of one unit. This determines the shipping fee.';
        hint.style.color = '#888';
    }
}

async function loadShopOrders() {
    try {
        const r = await fetch('get_shop_orders.php', { credentials: 'same-origin' });
        const d = await r.json();
        if (d.success) SHOP_ORDERS = d.orders;
    } catch(e) { console.error('loadShopOrders error:', e); }
}

function switchSOTab(tabId, btn) {
    document.querySelectorAll('[data-sotab]').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    document.querySelectorAll('#tab-shoporders .inv-tab-content').forEach(el => el.style.display = 'none');
    // tabId is like 'so-overview', 'so-all', 'so-pending', etc.
    const el = document.getElementById('so-tab-' + tabId.replace('so-', ''));
    if (el) el.style.display = '';
}
function updateSOBadges() {
    const badge = (id, status) => {
        const count = SHOP_ORDERS.filter(o => o.status === status).length;
        const el = document.getElementById(id);
        if (!el) return;
        if (count > 0) { el.textContent = count; el.style.display = 'inline-flex'; }
        else { el.style.display = 'none'; }
    };
    badge('badge-pending',   'pending');
    badge('badge-confirmed', 'confirmed');
    badge('badge-preparing', 'preparing');
    badge('badge-otd',       'out_for_delivery');
    badge('badge-delivered', 'delivered');
    badge('badge-cancelled', 'cancelled');

}

const SO_STATUS_LABELS = {
    pending:          { label:'Pending',         cls:'badge-pending'   },
    confirmed:        { label:'Confirmed',        cls:'badge-upcoming'  },
    preparing:        { label:'Preparing',        cls:'badge-ongoing'   },
    out_for_delivery: { label:'Out for Delivery', cls:'badge-ongoing'   },
    delivered:        { label:'Delivered',        cls:'badge-completed' },
    cancelled:        { label:'Cancelled',        cls:'badge-cancelled' },
};

function renderSOOverview() {
    const counts = { pending:0, confirmed:0, preparing:0, out_for_delivery:0, delivered:0 };
    let totalValue = 0, deliveredValue = 0, shippingCollected = 0;
    SHOP_ORDERS.forEach(o => {
        if (counts[o.status] !== undefined) counts[o.status]++;
        totalValue += parseFloat(o.total) || 0;
        if (o.status === 'delivered') {
            deliveredValue  += parseFloat(o.subtotal)     || 0;
            shippingCollected += parseFloat(o.shipping_fee) || 0;
        }
    });


    ['so-ov-pending','so-ov-confirmed','so-ov-preparing','so-ov-otd','so-ov-delivered'].forEach((id, i) => {
        const el = document.getElementById(id);
        if (el) el.textContent = Object.values(counts)[i];
    });

    document.getElementById('so-ov-revenue').innerHTML = `
        <div class="stat-cards" style="grid-template-columns:repeat(3,1fr);gap:0.75rem">
            <div class="stat-card"><div class="stat-num" style="font-size:1.2rem">${fmtPeso(totalValue)}</div><div class="stat-label">Total Order Value</div></div>
            <div class="stat-card highlight"><div class="stat-num" style="font-size:1.2rem">${fmtPeso(deliveredValue)}</div><div class="stat-label">Collected (Delivered)</div></div>
            <div class="stat-card"><div class="stat-num" style="font-size:1.2rem">${fmtPeso(shippingCollected)}</div><div class="stat-label">Shipping Collected</div></div>
        </div>`;

    // Recent orders
    const recent = [...SHOP_ORDERS].slice(0, 5);
    document.getElementById('so-ov-recent').innerHTML = recent.length ? recent.map(o => {
        const st = SO_STATUS_LABELS[o.status] || { label: o.status, cls: 'badge-upcoming' };
        return `<div style="display:flex;justify-content:space-between;align-items:center;padding:0.6rem 0;border-bottom:1px solid var(--color-border)">
            <div>
                <div style="font-weight:700;font-size:var(--text-body-sm)">${o.order_code} — ${o.trainee_name}</div>
                <div style="font-size:var(--text-tiny);color:#888">${new Date(o.created_at).toLocaleDateString('en-PH',{month:'short',day:'numeric',year:'numeric'})}</div>
            </div>
            <div style="display:flex;align-items:center;gap:0.5rem">
                <span class="badge ${st.cls}">${st.label}</span>
                <strong>${fmtPeso(o.total)}</strong>
            </div>
        </div>`;
    }).join('') : '<p class="light-txt" style="padding:1rem">No orders yet.</p>';

    // Pending list
    const pending = SHOP_ORDERS.filter(o => o.status === 'pending');
    document.getElementById('so-ov-pending-list').innerHTML = pending.length === 0
        ? '<p class="light-txt" style="padding:1rem">No pending orders. ✅</p>'
        : pending.map(o => `
            <div style="display:flex;justify-content:space-between;align-items:center;padding:0.75rem 0;border-bottom:1px solid var(--color-border)">
                <div>
                    <div style="font-weight:700">${o.order_code} — ${o.trainee_name}</div>
                    <div style="font-size:var(--text-tiny);color:#888">
                        ${new Date(o.created_at).toLocaleDateString('en-PH',{month:'short',day:'numeric',year:'numeric'})}
                        · ${o.items?.length || 0} item(s) · ${fmtPeso(o.total)}
                        · <span style="text-transform:uppercase;font-weight:600">${o.payment_method}</span>
                    </div>
                </div>
                <div style="display:flex;gap:0.5rem">
                    <button class="btn-outline-sm" onclick="soViewOrder(${o.id})">👁 View</button>
                    <button class="btn-primary" style="font-size:var(--text-caption);padding:0.35rem 0.9rem" onclick="soOpenStatus(${o.id},'${o.order_code}','${o.status}')">✓ Confirm</button>
                </div>
            </div>`).join('');
}

function renderSOTable() {
    const search  = document.getElementById('so-search')?.value.trim().toLowerCase() || '';
    const status  = document.getElementById('so-status-filter')?.value || '';
    const payment = document.getElementById('so-payment-filter')?.value || '';
    const sort    = document.getElementById('so-sort')?.value || 'newest';

    let list = SHOP_ORDERS.filter(o => {
        const ms = !search  || (o.order_code||'').toLowerCase().includes(search) || (o.trainee_name||'').toLowerCase().includes(search);
        const mst= !status  || o.status === status;
        const mp = !payment || o.payment_method === payment;
        return ms && mst && mp;
    });

    if (sort === 'oldest')     list = [...list].sort((a,b) => new Date(a.created_at) - new Date(b.created_at));
    else if (sort === 'total-desc') list = [...list].sort((a,b) => b.total - a.total);

    const tbody = document.getElementById('so-all-tbody');
    tbody.innerHTML = list.map(o => {
        const st  = SO_STATUS_LABELS[o.status] || { label: o.status, cls: 'badge-upcoming' };
        const date = new Date(o.created_at).toLocaleDateString('en-PH',{month:'short',day:'numeric',year:'numeric'});
        const payBadge = o.payment_method === 'gcash'
            ? '<span style="background:#e8f4ff;color:#1a73e8;border:1px solid #c5def8;border-radius:10px;padding:0.15rem 0.5rem;font-size:10px;font-weight:700">GCash</span>'
            : '<span style="background:#f0f7ec;color:#4B8423;border:1px solid #c3e0b0;border-radius:10px;padding:0.15rem 0.5rem;font-size:10px;font-weight:700">COD</span>';
        return `<tr>
            <td><code style="font-size:var(--text-caption);background:#f5f5f5;padding:0.15rem 0.4rem;border-radius:3px">${o.order_code}</code></td>
            <td class="light-txt">${date}</td>
            <td>${o.trainee_name || '—'}</td>
            <td>${o.items?.length || 0} item(s)</td>
            <td><strong>${fmtPeso(o.total)}</strong></td>
            <td>${payBadge}</td>
            <td><span class="badge ${st.cls}">${st.label}</span></td>
            <td>
                <div style="display:flex;gap:0.3rem;flex-wrap:wrap">
                    <button class="btn-xs" onclick="soViewOrder(${o.id})">👁 View</button>
                    ${o.status !== 'cancelled' && o.status !== 'delivered' ? `<button class="btn-xs green" onclick="soOpenStatus(${o.id},'${o.order_code}','${o.status}')">→ Advance</button>` : ''}
                    ${o.status !== 'cancelled' && o.status !== 'delivered' ? `<button class="btn-xs red" onclick="openCancelOrderModal(${o.id})">✕ Cancel</button>` : ''}
                </div>
            </td>
        </tr>`;
    }).join('') || `<tr><td colspan="8" style="text-align:center;padding:2rem;color:#aaa">No orders found.</td></tr>`;

    const countEl = document.getElementById('so-all-count');
    if (countEl) countEl.textContent = `Showing ${list.length} of ${SHOP_ORDERS.length} orders`;
}
function renderSOStatusList(status, containerId) {
    const el = document.getElementById(containerId);
    if (!el) return;
    const list = SHOP_ORDERS.filter(o => o.status === status);
    if (!list.length) {
        el.innerHTML = `<p class="light-txt" style="padding:2rem;text-align:center">No ${status.replace('_',' ')} orders.</p>`;
        return;
    }
    el.innerHTML = list.map(o => {
        const st   = SO_STATUS_LABELS[o.status] || { label: o.status, cls: 'badge-upcoming' };
        const date = new Date(o.created_at).toLocaleDateString('en-PH',{month:'short',day:'numeric',year:'numeric'});
        const addr = o.address ? [o.address.address_line, o.address.city_name].filter(Boolean).join(', ') : '—';
        return `<div class="inv-card" style="margin-bottom:0.75rem">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:0.5rem">
                <div>
                    <div style="font-weight:700;font-size:var(--text-body-sm)">${o.order_code}
                        <span style="font-weight:400;color:#666;margin-left:0.5rem">${o.trainee_name}</span>
                    </div>
                    <div style="font-size:var(--text-tiny);color:#888;margin-top:0.2rem">${date} · 📍 ${addr}</div>
                    ${o.cancel_reason ? `<div style="font-size:var(--text-tiny);color:#c0392b;margin-top:0.2rem;font-weight:600"> Reason: ${o.cancel_reason}</div>` : ''}
                    <div style="margin-top:0.5rem">
                        ${(o.items||[]).map(item => `<span style="font-size:var(--text-caption);background:#f5f5f5;padding:0.2rem 0.5rem;border-radius:4px;margin-right:0.3rem">${item.name} ×${item.quantity}</span>`).join('')}
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:0.75rem">
                    <div style="text-align:right">
                        <div style="font-weight:700">${fmtPeso(o.total)}</div>
                        <div style="font-size:var(--text-tiny);color:#888">${o.payment_method?.toUpperCase()}</div>
                    </div>
                    <span class="badge ${st.cls}">${st.label}</span>
                    <button class="btn-outline-sm" onclick="soViewOrder(${o.id})">👁 View</button>
                    ${o.status !== 'cancelled' && o.status !== 'delivered' ? `<button class="btn-primary" style="font-size:var(--text-caption);padding:0.35rem 0.9rem" onclick="soOpenStatus(${o.id},'${o.order_code}','${o.status}')">→ Advance</button>` : ''}
                    ${o.status !== 'cancelled' && o.status !== 'delivered' ? `<button class="btn-sm" style="font-size:var(--text-caption);padding:0.35rem 0.7rem;background:var(--color-danger);color:#fff;border:none;border-radius:var(--radius-sm);cursor:pointer" onclick="openCancelOrderModal(${o.id})">✕ Cancel</button>` : ''}
                </div>
            </div>
        </div>`;
    }).join('');
}

function soViewOrder(orderId) {
    const o = SHOP_ORDERS.find(x => x.id === orderId);
    if (!o) return;
    const st   = SO_STATUS_LABELS[o.status] || { label: o.status, cls: 'badge-upcoming' };
    const date = new Date(o.created_at).toLocaleDateString('en-PH',{month:'short',day:'numeric',year:'numeric'});
    const addr = o.address ? [o.address.address_line, o.address.barangay_name, o.address.city_name, o.address.province_name].filter(Boolean).join(', ') : '—';

    document.getElementById('so-od-title').textContent = o.order_code;
    document.getElementById('so-od-sub').textContent   = `${date} · ${o.trainee_name}`;
    document.getElementById('so-od-body').innerHTML = `
        <div style="display:flex;gap:0.5rem;margin-bottom:1rem;flex-wrap:wrap">
            <span class="badge ${st.cls}">${st.label}</span>
            <span style="background:#f5f5f5;padding:0.2rem 0.6rem;border-radius:10px;font-size:var(--text-caption);font-weight:600">${o.payment_method?.toUpperCase()}</span>
            ${o.gcash_ref ? `<span style="font-size:var(--text-caption);color:#1a73e8">Ref: ${o.gcash_ref}</span>` : ''}
        </div>
        ${o.gcash_screenshot ? `
        <div style="background:#e8f4ff;border:1.5px solid #c5def8;border-radius:var(--radius-sm);padding:0.85rem;margin-bottom:1rem">
            <div style="font-size:var(--text-caption);font-weight:700;color:#1a73e8;margin-bottom:0.5rem">📎 GCash Receipt</div>
            <a href="../../${o.gcash_screenshot}" target="_blank" style="display:inline-block">
                <img src="../../${o.gcash_screenshot}" alt="GCash Receipt"
                     style="max-width:100%;max-height:220px;border-radius:6px;border:1px solid #c5def8;object-fit:contain;cursor:zoom-in"
                     onerror="this.parentElement.innerHTML='<span style=color:#888;font-size:0.8rem>Receipt image not found</span>'">
            </a>
            <div style="font-size:var(--text-tiny);color:#666;margin-top:0.35rem">Click image to open full size</div>
        </div>` : ''}
        <div style="background:#f9fbf9;padding:0.75rem;border-radius:var(--radius-sm);margin-bottom:1rem">
            <div style="font-size:var(--text-caption);font-weight:700;margin-bottom:0.35rem">📍 Delivery Address</div>
            <div style="font-size:var(--text-body-sm)">${addr}</div>
            ${o.address?.contact_number ? `<div style="font-size:var(--text-caption);color:#666;margin-top:0.2rem">${o.address.contact_number}</div>` : ''}
        </div>
        <div style="margin-bottom:1rem">
            <div style="font-size:var(--text-caption);font-weight:700;margin-bottom:0.5rem">Items Ordered</div>
            ${(o.items||[]).map(item => `
                <div style="display:flex;justify-content:space-between;align-items:center;padding:0.5rem 0;border-bottom:1px solid var(--color-border)">
                    <div style="display:flex;align-items:center;gap:0.75rem">
                        <img src="${item.image ? '../../'+item.image : 'https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=60'}"
                             style="width:40px;height:40px;object-fit:cover;border-radius:6px;border:1px solid #eee"
                             onerror="this.src='https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=60'">
                        <div>
                            <div style="font-weight:600;font-size:var(--text-body-sm)">${item.name}</div>
                            <div style="font-size:var(--text-tiny);color:#888">${fmtPeso(item.unit_price)} × ${item.quantity}</div>
                        </div>
                    </div>
                    <strong>${fmtPeso(item.subtotal)}</strong>
                </div>`).join('')}
        </div>
        <div style="background:#f9fbf9;padding:0.75rem;border-radius:var(--radius-sm)">
            <div style="display:flex;justify-content:space-between;font-size:var(--text-body-sm);margin-bottom:0.3rem"><span>Subtotal</span><span>${fmtPeso(o.subtotal)}</span></div>
            <div style="display:flex;justify-content:space-between;font-size:var(--text-body-sm);margin-bottom:0.3rem;color:#666"><span>Shipping</span><span>${fmtPeso(o.shipping_fee)}</span></div>
            <div style="display:flex;justify-content:space-between;font-weight:800;font-size:1rem;padding-top:0.5rem;border-top:1px solid var(--color-border)"><span>Total</span><span>${fmtPeso(o.total)}</span></div>
        </div>
        ${o.notes ? `<div style="margin-top:0.75rem;background:#fffbea;border:1px solid #f7d98a;border-radius:var(--radius-sm);padding:0.6rem 0.75rem;font-size:var(--text-caption)">📝 ${o.notes}</div>` : ''}
        <div style="margin-top:1rem;display:flex;justify-content:flex-end;gap:0.5rem">
            ${o.status !== 'cancelled' && o.status !== 'delivered' ? `<button class="btn-outline" style="border-color:var(--color-danger);color:var(--color-danger)" onclick="closeModal('so-detail-modal');openCancelOrderModal(${o.id})">✕ Cancel Order</button>` : ''}
            ${o.status !== 'cancelled' && o.status !== 'delivered' ? `<button class="btn-primary" onclick="soOpenStatus(${o.id},'${o.order_code}','${o.status}');closeModal('so-detail-modal')">→ Advance Status</button>` : ''}
        </div>`;
    openModal('so-detail-modal');
}
function openCancelOrderModal(orderId) {
    const o = SHOP_ORDERS.find(x => x.id === orderId);
    if (!o) return;

    const existing = document.getElementById('cancel-order-modal');
    if (existing) existing.remove();

    const isGcash = o.payment_method === 'gcash';
    const overlay = document.createElement('div');
    overlay.id = 'cancel-order-modal';
    overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;display:flex;align-items:center;justify-content:center';
    overlay.innerHTML = `
        <div style="background:#fff;border-radius:14px;padding:2rem;max-width:460px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.2)">
            <h3 style="font-size:1.05rem;font-weight:700;margin-bottom:0.25rem">❌ Cancel Order</h3>
            <p style="font-size:0.83rem;color:#888;margin-bottom:1.25rem">${o.order_code} · ${o.trainee_name}</p>

            <div style="margin-bottom:1rem">
                <label style="font-size:0.85rem;font-weight:600;display:block;margin-bottom:0.4rem">Reason for Cancellation *</label>
                <select id="cancel-reason-select" style="width:100%;padding:0.6rem;border:1.5px solid #ddd;border-radius:8px;font-size:0.9rem;font-family:inherit;background:#fff" onchange="toggleCancelOther()">
                    <option value="">— Select a reason —</option>
                    <option value="Out of stock">Item(s) out of stock</option>
                    <option value="Duplicate order">Duplicate order</option>
                    <option value="Trainee requested cancellation">Trainee requested cancellation</option>
                    <option value="Incorrect order details">Incorrect order details</option>
                    <option value="Unable to deliver to address">Unable to deliver to address</option>
                    <option value="other">Other (specify below)</option>
                </select>
            </div>

            <div id="cancel-other-wrap" style="display:none;margin-bottom:1rem">
                <label style="font-size:0.85rem;font-weight:600;display:block;margin-bottom:0.4rem">Specify Reason *</label>
                <input type="text" id="cancel-reason-other" style="width:100%;padding:0.6rem;border:1.5px solid #ddd;border-radius:8px;font-size:0.9rem;font-family:inherit;box-sizing:border-box" placeholder="Describe the reason…">
            </div>

            ${isGcash ? `
            <div style="background:#e8f4ff;border:1px solid #c5def8;border-radius:8px;padding:0.85rem 1rem;margin-bottom:1rem">
                <div style="font-size:0.8rem;font-weight:700;color:#1a73e8;margin-bottom:0.3rem">💙 GCash Refund Required</div>
                <div style="font-size:0.82rem;color:#333">
                    This order was paid via GCash.<br>
                    <strong>Reference #: ${o.gcash_ref || '—'}</strong><br>
                    <span style="color:#666;font-size:0.78rem">Please process the refund manually using this reference number and inform the trainee.</span>
                </div>
            </div>` : ''}

            <div id="cancel-order-error" style="display:none;color:#e53e3e;font-size:0.82rem;margin-bottom:0.75rem"></div>

            <div style="display:flex;justify-content:flex-end;gap:0.75rem;margin-top:0.5rem">
                <button onclick="document.getElementById('cancel-order-modal').remove()" style="padding:0.6rem 1.2rem;border:1.5px solid #ddd;background:#fff;border-radius:8px;cursor:pointer;font-size:0.88rem">Keep Order</button>
                <button onclick="confirmCancelOrder(${orderId})" style="padding:0.6rem 1.2rem;background:#e53e3e;color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:0.88rem;font-weight:600">✓ Confirm Cancellation</button>
            </div>
        </div>`;
    document.body.appendChild(overlay);
    overlay.addEventListener('click', e => { if (e.target === overlay) overlay.remove(); });
}

function toggleCancelOther() {
    const sel = document.getElementById('cancel-reason-select').value;
    document.getElementById('cancel-other-wrap').style.display = sel === 'other' ? 'block' : 'none';
}

async function confirmCancelOrder(orderId) {
    const sel    = document.getElementById('cancel-reason-select').value;
    const other  = document.getElementById('cancel-reason-other')?.value.trim();
    const errEl  = document.getElementById('cancel-order-error');

    if (!sel) { errEl.textContent = 'Please select a reason.'; errEl.style.display = 'block'; return; }
    if (sel === 'other' && !other) { errEl.textContent = 'Please specify the reason.'; errEl.style.display = 'block'; return; }

    const reason = sel === 'other' ? other : sel;

    try {
        const r = await fetch('update_order_status.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_id: orderId, status: 'cancelled', note: reason }),
        });
        const d = await r.json();
        document.getElementById('cancel-order-modal')?.remove();
        if (d.success) {
            showToast('❌ Order cancelled — ' + reason);
            await loadShopOrders();
            renderSOOverview();
            renderSOTable();
            renderSOStatusList('pending',          'so-list-pending');
            renderSOStatusList('confirmed',        'so-list-confirmed');
            renderSOStatusList('preparing',        'so-list-preparing');
            renderSOStatusList('out_for_delivery', 'so-list-otd');
        renderSOStatusList('delivered',        'so-list-delivered');
        renderSOStatusList('cancelled',        'so-list-cancelled');
        updateSOBadges();
        } else {
            showToast('❌ ' + d.message);
        }
    } catch(e) { showToast('❌ Could not cancel order.'); }
}
function soOpenStatus(orderId, orderCode, currentStatus) {
    // Sequential flow map
    const nextStatus = {
        pending:          'confirmed',
        confirmed:        'preparing',
        preparing:        'out_for_delivery',
        out_for_delivery: 'delivered',
    };
    const nextLabel = {
        confirmed:        'Confirmed',
        preparing:        'Preparing',
        out_for_delivery: 'Out for Delivery',
        delivered:        'Delivered',
    };

    if (currentStatus === 'delivered' || currentStatus === 'cancelled') {
        showToast('This order is already ' + currentStatus + '.');
        return;
    }

    const next = nextStatus[currentStatus];
    document.getElementById('so-sm-id').value         = orderId;
    document.getElementById('so-sm-code').textContent = orderCode;
    document.getElementById('so-sm-status').value     = next;
    document.getElementById('so-sm-note').value       = '';
    document.getElementById('so-sm-error').style.display = 'none';

    // Update modal labels to show what's happening
    const nextEl = document.getElementById('so-sm-next-label');
    if (nextEl) nextEl.textContent = next ? (nextLabel[next] || next) : '—';

    const currentEl = document.getElementById('so-sm-current-label');
    if (currentEl) currentEl.textContent = SO_STATUS_LABELS[currentStatus]?.label || currentStatus;

    openModal('so-status-modal');
}

async function soConfirmStatus() {
    const id     = parseInt(document.getElementById('so-sm-id').value);
    const status = document.getElementById('so-sm-status').value;

    // If cancelling, open the cancel reason modal instead
    if (status === 'cancelled') {
        closeModal('so-status-modal');
        openCancelOrderModal(id);
        return;
    }
    const note   = document.getElementById('so-sm-note').value.trim();
    const errEl  = document.getElementById('so-sm-error');

    try {
        const r = await fetch('update_order_status.php', {
            method:  'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ order_id: id, status, note }),
        });
        const d = await r.json();
        if (d.success) {
            closeModal('so-status-modal');
            showToast('✅ Order status updated to ' + status);
            await loadShopOrders();
            renderSOOverview();
            renderSOTable();
            renderSOStatusList('pending',          'so-list-pending');
            renderSOStatusList('confirmed',        'so-list-confirmed');
            renderSOStatusList('preparing',        'so-list-preparing');
            renderSOStatusList('out_for_delivery', 'so-list-otd');
            renderSOStatusList('delivered',        'so-list-delivered');
            renderSOStatusList('cancelled',        'so-list-cancelled');
            updateSOBadges();
        } else {
            errEl.textContent = d.message; errEl.style.display = 'block';
        }
    } catch(e) { showToast('❌ Could not update status.'); }
}