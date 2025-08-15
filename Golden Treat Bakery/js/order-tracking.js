// js/order-tracking.js
document.addEventListener('DOMContentLoaded', () => {
  const trackForm = document.getElementById('track-form');
  const orderQuery = document.getElementById('order-query');
  const trackMsg = document.getElementById('track-msg');
  const statusPanel = document.getElementById('status-panel');
  const statusOrderId = document.getElementById('status-order-id');
  const statusHeadline = document.getElementById('status-headline');
  const statusDetail = document.getElementById('status-detail');
  const statusLastUpdate = document.getElementById('status-last-update');
  const statusEta = document.getElementById('status-eta');
  const statusCourier = document.getElementById('status-courier');
  const progressBar = document.getElementById('progress-bar');
  const stepDots = document.querySelectorAll('.step-dot');
  const trackingHistoryEl = document.getElementById('tracking-history');
  const orderItemsEl = document.getElementById('track-order-items');
  const copyBtn = document.getElementById('copy-order-id');
  const notifyBtn = document.getElementById('notify-btn');
  const notifyMsg = document.getElementById('notify-msg');

  // API endpoint (change to your real endpoint)
  const STATUS_API = '/api/order-status'; // e.g. /api/order-status?order_id=TSH-...
  const NOTIFY_API = '/send-tracking-notify.php'; // endpoint to subscribe for SMS/email notifications

  // order stages (map stage index to progress percent)
  const STAGES = ['processing', 'packed', 'shipped', 'out_for_delivery', 'delivered'];
  const STAGE_PERCENT = { 'processing': 10, 'packed': 35, 'shipped': 65, 'out_for_delivery': 85, 'delivered': 100 };

  // usage: trackForm submission
  trackForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const q = orderQuery.value.trim();
    if (!q) {
      showMessage('Please enter an order ID, email or phone.', 'danger');
      return;
    }
    showMessage('Searching order...', 'info');
    try {
      const statusData = await fetchStatus(q);
      populateStatus(statusData);
      showMessage('', ''); // clear
    } catch (err) {
      console.error(err);
      showMessage('Unable to fetch order status (showing demo response).', 'warning');
      // fallback demo
      const demo = demoStatus(q);
      populateStatus(demo);
    }
  });

  // copy order id
  if (copyBtn) copyBtn.addEventListener('click', () => {
    const id = statusOrderId.textContent || '';
    if (!id) return;
    navigator.clipboard?.writeText(id).then(() => {
      showMessage('Order ID copied to clipboard.', 'success');
    }).catch(() => showMessage('Unable to copy.', 'danger'));
  });

  // notify subscription
  notifyBtn.addEventListener('click', () => {
    const email = document.getElementById('notify-email').value.trim();
    const phone = document.getElementById('notify-phone').value.trim();
    const orderId = statusOrderId.textContent || orderQuery.value.trim();

    if (!orderId) { notifyMsg.innerText = 'Track an order first, or provide an Order ID.'; return; }
    if (!email && !phone) { notifyMsg.innerText = 'Enter email or phone to subscribe.'; return; }

    notifyMsg.innerText = 'Subscribing...';

    // POST to your server (you must implement NOTIFY_API to accept order_id,email,phone)
    fetch(NOTIFY_API, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ order_id: orderId, email, phone })
    }).then(r => r.json()).then(resp => {
      if (resp && resp.success) {
        notifyMsg.innerText = 'Subscribed — we will notify you of status changes.';
      } else {
        notifyMsg.innerText = resp.message || 'Subscription failed. Server error.';
      }
    }).catch(err => {
      console.error(err);
      notifyMsg.innerText = 'Unable to subscribe (demo fallback).';
    });
  });

  // fetch status from API — try real API then fallback; expects JSON like:
  // { order_id, status, last_update, eta, courier:{name,phone,tracking_url}, items:[{title,qty,price}], history:[{status,ts,detail}] }
  async function fetchStatus(query) {
    // try GET by order_id param
    const url = `${STATUS_API}?q=${encodeURIComponent(query)}`;
    const resp = await fetch(url, {credentials: 'same-origin'});
    if (!resp.ok) throw new Error('Network error');
    return await resp.json();
  }

  // render status data into UI
  function populateStatus(data) {
    // If backend returns an error, show message
    if (!data || data.error) {
      showMessage(data?.message || 'Order not found', 'danger');
      return;
    }

    statusPanel.style.display = 'block';
    statusOrderId.textContent = data.order_id || '—';
    statusHeadline.textContent = `Status: ${humanizeStatus(data.status)}`;
    statusDetail.textContent = data.detail || data.status_note || 'No additional details.';
    statusLastUpdate.textContent = `Last update: ${formatDate(data.last_update)}`;
    statusEta.textContent = data.eta || '—';
    statusCourier.textContent = (data.courier && data.courier.name) ? `${data.courier.name} • ${data.courier.phone || ''}` : '—';

    // progress
    const pct = STAGE_PERCENT[data.status] || 0;
    progressBar.style.width = `${pct}%`;
    stepDots.forEach(dot => {
      const stepIdx = parseInt(dot.dataset.step, 10) - 1;
      const stageName = STAGES[stepIdx];
      dot.classList.toggle('active', STAGE_PERCENT[stageName] <= pct);
    });

    // history
    renderHistory(data.history || []);

    // items
    renderItems(data.items || []);

    // scroll to status
    statusPanel.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  // render history list
  function renderHistory(history = []) {
    trackingHistoryEl.innerHTML = '';
    if (history.length === 0) {
      trackingHistoryEl.innerHTML = `<li class="list-group-item">No history available.</li>`;
      return;
    }
    history.forEach(h => {
      const li = document.createElement('li');
      li.className = 'list-group-item';
      li.innerHTML = `<div class="fw-semibold">${humanizeStatus(h.status)}</div>
                      <div class="small-muted">${formatDate(h.ts)} — ${h.detail || ''}</div>`;
      trackingHistoryEl.appendChild(li);
    });
  }

  // render items
  function renderItems(items = []) {
    orderItemsEl.innerHTML = '';
    if (items.length === 0) {
      orderItemsEl.innerHTML = `<li class="list-group-item">No items available.</li>`;
      return;
    }
    items.forEach(it => {
      const li = document.createElement('li');
      li.className = 'list-group-item d-flex justify-content-between align-items-center';
      li.innerHTML = `<div>
                        <div class="fw-semibold">${escapeHtml(it.title)}</div>
                        <div class="small-muted">${(it.meta) ? escapeHtml(it.meta) : ''}</div>
                      </div>
                      <div class="text-end small-muted">x${it.qty} • LKR ${numberFormat(it.price * it.qty)}</div>`;
      orderItemsEl.appendChild(li);
    });
  }

  // Helpers
  function showMessage(text, type) {
    trackMsg.className = '';
    trackMsg.textContent = text || '';
    if (!type) return;
    const cls = (type === 'danger') ? 'text-danger' : (type === 'warning') ? 'text-warning' : (type === 'success') ? 'text-success' : 'text-info';
    trackMsg.classList.add(cls);
  }
  function humanizeStatus(s) {
    if (!s) return 'Unknown';
    return s.replace(/_/g, ' ').replace(/\b\w/g, ch => ch.toUpperCase());
  }
  function formatDate(ts) {
    if (!ts) return '—';
    const d = new Date(ts);
    if (isNaN(d)) return ts;
    return d.toLocaleString();
  }
  function numberFormat(n) { return (n || 0).toLocaleString('en-LK'); }
  function escapeHtml(s) { return (s+'').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

  // demo fallback data (used when no backend available)
  function demoStatus(query) {
    const now = new Date();
    const orderId = query.match(/TSH/i) ? query : `TSH-20250815-00${Math.floor(Math.random()*90)+1}`;
    const status = 'out_for_delivery';
    return {
      order_id: orderId,
      status,
      last_update: now.toISOString(),
      eta: new Date(now.getTime() + 30*60000).toLocaleTimeString(),
      courier: { name: 'FastRunner Couriers', phone: '+94 77 222 3333' },
      items: [
        { title: 'Beef Burger', qty: 1, price: 850 },
        { title: 'Crispy Fries', qty: 1, price: 350 }
      ],
      history: [
        { status: 'processing', ts: new Date(now.getTime() - 60*60000).toISOString(), detail: 'Order confirmed & being prepared' },
        { status: 'packed', ts: new Date(now.getTime() - 45*60000).toISOString(), detail: 'Packed & ready for pickup' },
        { status: 'shipped', ts: new Date(now.getTime() - 25*60000).toISOString(), detail: 'With courier' },
        { status: 'out_for_delivery', ts: new Date(now.getTime() - 5*60000).toISOString(), detail: 'Courier is nearby' }
      ]
    };
  }

  // Escape HTML utility for display
  // (already provided above)

  // End of DOMContentLoaded
});
