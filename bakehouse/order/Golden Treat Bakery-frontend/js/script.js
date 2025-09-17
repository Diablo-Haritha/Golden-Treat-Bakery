// navbar
fetch("Navbar.html")
    .then(response => response.text())
    .then(data => {
      document.getElementById("navbar-placeholder").innerHTML = data;

      // Highlight active link
      const currentPage = window.location.pathname.split("/").pop();
      document.querySelectorAll("#navbar a").forEach(link => {
        if (link.getAttribute("href") === currentPage) {
          link.classList.add("active");
        }
      });
    });
// footer
fetch("footer.html")
    .then(response => response.text())
    .then(data => {
      document.getElementById("footer-placeholder").innerHTML = data;
    });

  // js/script.js
// All logic runs after the DOM is ready
document.addEventListener('DOMContentLoaded', function () {
  // ---------- SAMPLE ORDER DATA (replace with server-side rendering or fetch) ----------
  const SAMPLE_ORDER = [
    { id: 'p1', title: 'Beef Burger', meta: 'Large • Extra cheese', price: 850, qty: 1 },
    { id: 'p2', title: 'Crispy Fries', meta: 'Regular', price: 350, qty: 1 },
    { id: 'p3', title: 'Coke (330ml)', meta: 'Beverage', price: 200, qty: 1 }
  ];
  const DELIVERY_FEE = 150;

  // ---------- DOM refs ----------
  const orderItemsEl = document.getElementById('order-items');
  const subtotalEl = document.getElementById('subtotal');
  const deliveryFeeEl = document.getElementById('delivery-fee');
  const grandTotalEl = document.getElementById('grand-total');
  const buyNowBtn = document.getElementById('buy-now');
  const buyNowAmount = document.getElementById('buy-now-amount');
  const orderSummaryInput = document.getElementById('order-summary-input');
  const cardPanel = document.getElementById('card-panel');
  const paymentRadios = document.querySelectorAll('input[name="payment_method"]');

  // ---------- render order items and totals ----------
  function renderOrder() {
    orderItemsEl.innerHTML = '';
    let subtotal = 0;
    SAMPLE_ORDER.forEach(item => {
      const itemTotal = item.price * item.qty;
      subtotal += itemTotal;

      const li = document.createElement('li');
      li.className = 'list-group-item d-flex align-items-center';
      li.dataset.id = item.id;
      li.innerHTML = `
        <div class="flex-grow-1">
          <div class="fw-semibold">${escapeHtml(item.title)}</div>
          <div class="muted small">${escapeHtml(item.meta)}</div>
        </div>
        <div class="d-flex align-items-center ms-3">
          <input type="number" min="1" value="${item.qty}" class="form-control form-control-sm order-item-qty" data-price="${item.price}" aria-label="Quantity for ${escapeHtml(item.title)}" />
          <div class="ms-3 fw-semibold">LKR <span class="item-total">${formatNumber(itemTotal)}</span></div>
        </div>
      `;
      orderItemsEl.appendChild(li);
    });
    subtotalEl.textContent = formatNumber(subtotal);
    deliveryFeeEl.textContent = formatNumber(DELIVERY_FEE);
    const grand = subtotal + DELIVERY_FEE;
    grandTotalEl.textContent = formatNumber(grand);
    buyNowAmount.textContent = formatNumber(grand);
    orderSummaryInput.value = JSON.stringify({ items: SAMPLE_ORDER, subtotal, delivery: DELIVERY_FEE, total: grand });
    updateBuyButtonState();
    attachQtyListeners();
  }

  function attachQtyListeners(){
    orderItemsEl.querySelectorAll('.order-item-qty').forEach((input, idx) => {
      input.addEventListener('change', (e) => {
        const val = Math.max(1, parseInt(e.target.value) || 1);
        SAMPLE_ORDER[idx].qty = val;
        renderOrder(); // rerender
      });
    });
  }

  // ---------- helpers ----------
  function formatNumber(n) { return n.toLocaleString('en-LK'); }
  function escapeHtml(s){ return (s+'').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

  // ---------- payment method toggle ----------
  function onPaymentChange(){
    const method = document.querySelector('input[name="payment_method"]:checked').value;
    if(method === 'cod'){
      cardPanel.style.display = 'none';
      // make card inputs not required
      setCardRequired(false);
    } else {
      cardPanel.style.display = 'block';
      setCardRequired(true);
    }
    updateBuyButtonState();
  }
  function setCardRequired(flag){
    const els = ['card-number','exp-month','exp-year','cvv'];
    els.forEach(id => {
      const el = document.getElementById(id);
      if(!el) return;
      if(flag) el.setAttribute('required','required');
      else el.removeAttribute('required');
    });
  }
  paymentRadios.forEach(r => r.addEventListener('change', onPaymentChange));

  // ---------- expiry selects populate ----------
  (function populateExpiry(){
    const mEl = document.getElementById('exp-month');
    const yEl = document.getElementById('exp-year');
    if(!mEl || !yEl) return;
    for(let m=1;m<=12;m++){
      const mm = m.toString().padStart(2,'0');
      const opt = document.createElement('option'); opt.value = mm; opt.textContent = mm;
      mEl.appendChild(opt);
    }
    const now = new Date();
    const year = now.getFullYear();
    for(let i=0;i<12;i++){
      const y = (year + i).toString();
      const opt = document.createElement('option'); opt.value = y.slice(-2); opt.textContent = y;
      yEl.appendChild(opt);
    }
  })();

  // ---------- card input formatting ----------
  const cardNumberInput = document.getElementById('card-number');
  if(cardNumberInput){
    cardNumberInput.addEventListener('input', (e)=>{
      const digits = e.target.value.replace(/\D/g,'').slice(0,19);
      const parts = [];
      for(let i=0;i<digits.length;i+=4) parts.push(digits.slice(i,i+4));
      e.target.value = parts.join(' ');
      validateCardVisual();
      updateBuyButtonState();
    });
  }

  const cvvInput = document.getElementById('cvv');
  if(cvvInput){
    cvvInput.addEventListener('input', (e) => {
      e.target.value = e.target.value.replace(/\D/g,'').slice(0,4);
      updateBuyButtonState();
    });
  }

  // ---------- simple Luhn check ----------
  function luhnValid(number){
    const digits = (number || '').replace(/\D/g,'');
    if(digits.length < 12) return false;
    let sum = 0, alt = false;
    for(let i = digits.length - 1; i >= 0; i--){
      let d = parseInt(digits.charAt(i),10);
      if(alt){ d *= 2; if(d>9) d -= 9; }
      sum += d;
      alt = !alt;
    }
    return sum % 10 === 0;
  }
  function validateCardVisual(){
    if(!cardNumberInput) return;
    const num = cardNumberInput.value;
    if(num.trim() === '') { cardNumberInput.classList.remove('input-error'); return; }
    if(!luhnValid(num)) cardNumberInput.classList.add('input-error'); else cardNumberInput.classList.remove('input-error');
  }

  // ---------- form validation & submit handling ----------
  const checkoutForm = document.getElementById('checkout-form');
  if(checkoutForm){
    checkoutForm.addEventListener('submit', (e) => {
      if(!canSubmit()){
        e.preventDefault();
        e.stopPropagation();
        if(cardNumberInput && !luhnValid(cardNumberInput.value)) cardNumberInput.classList.add('input-error');
        return false;
      }
      // disable button and show loading state
      buyNowBtn.disabled = true;
      buyNowBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Processing...';
      // allow form to post to server (send-confirmation.php)
    });
  }

  function canSubmit(){
    // basic checks: order non-empty, delivery time filled, payment valid
    const subtotal = Number(subtotalEl.textContent.replace(/,/g,'')) || 0;
    if(subtotal <= 0) return false;
    const deliveryTimeEl = document.getElementById('delivery-time');
    if(!deliveryTimeEl || !deliveryTimeEl.value) return false;
    const method = document.querySelector('input[name="payment_method"]:checked').value;
    if(method === 'cod') return true;
    if(!cardNumberInput) return false;
    if(!luhnValid(cardNumberInput.value)) return false;
    const expM = document.getElementById('exp-month');
    const expY = document.getElementById('exp-year');
    if(!expM || !expM.value) return false;
    if(!expY || !expY.value) return false;
    if(!cvvInput || cvvInput.value.length < 3) return false;
    return true;
  }

  function updateBuyButtonState(){
    const grand = Number(grandTotalEl.textContent.replace(/,/g,'')) || 0;
    buyNowAmount.textContent = formatNumber(grand);
    if(buyNowBtn) buyNowBtn.disabled = !canSubmit();
  }

  // ---------- coupon (demo) ----------
  const applyCouponBtn = document.getElementById('apply-coupon');
  if(applyCouponBtn){
    applyCouponBtn.addEventListener('click', () => {
      const code = document.getElementById('coupon').value.trim();
      const msg = document.getElementById('coupon-msg');
      if(!code){ msg.textContent = 'Enter a coupon code.'; return; }
      if(code.toUpperCase() === 'DIS10'){
        const subtotal = Number(subtotalEl.textContent.replace(/,/g,'')) || 0;
        const discount = Math.round(subtotal * 0.10);
        const newSubtotal = subtotal - discount;
        subtotalEl.textContent = formatNumber(newSubtotal);
        const newGrand = newSubtotal + DELIVERY_FEE;
        grandTotalEl.textContent = formatNumber(newGrand);
        buyNowAmount.textContent = formatNumber(newGrand);
        orderSummaryInput.value = JSON.stringify({ items: SAMPLE_ORDER, subtotal: newSubtotal, delivery: DELIVERY_FEE, total: newGrand });
        msg.textContent = `Applied 10% off — you saved LKR ${formatNumber(discount)}.`;
        msg.classList.remove('text-danger'); msg.classList.add('text-success');
        updateBuyButtonState();
      } else {
        msg.textContent = 'Invalid coupon.';
        msg.classList.remove('text-success'); msg.classList.add('text-danger');
      }
    });
  }

  // ---------- address modal save ----------
  const addressForm = document.getElementById('address-form');
  if(addressForm){
    addressForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const rec = document.getElementById('modal-recipient').value.trim();
      const phone = document.getElementById('modal-phone').value.trim();
      const addr = document.getElementById('modal-address').value.trim();
      if(!rec || !phone || !addr) return;
      document.getElementById('recipient-name').textContent = rec;
      document.getElementById('address-phone').textContent = phone;
      document.getElementById('address-text').textContent = addr;
      // close modal
      const modalEl = document.getElementById('editAddressModal');
      const modalInstance = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
      modalInstance.hide();
    });
  }

  // ---------- initialization ----------
  (function init(){
    // render order
    renderOrder();

    // fill modal with current values
    const recEl = document.getElementById('recipient-name');
    const phoneEl = document.getElementById('address-phone');
    const addrEl = document.getElementById('address-text');
    if(recEl) document.getElementById('modal-recipient').value = recEl.textContent;
    if(phoneEl) document.getElementById('modal-phone').value = phoneEl.textContent;
    if(addrEl) document.getElementById('modal-address').value = addrEl.textContent;

    // initial payment toggle
    onPaymentChange();

    // update when delivery time or inputs change
    const deliveryTimeEl = document.getElementById('delivery-time');
    const deliveryInstructionsEl = document.getElementById('delivery-instructions');
    if(deliveryTimeEl) deliveryTimeEl.addEventListener('change', updateBuyButtonState);
    if(deliveryInstructionsEl) deliveryInstructionsEl.addEventListener('input', updateBuyButtonState);
  })();

}); // DOMContentLoaded
  