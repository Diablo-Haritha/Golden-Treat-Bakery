// billing/js/billing.js
let cart = [];
let vatPercent = 8;
let discount = 0; // % or fixed
let loyaltyPoints = 0;

// Fetch VAT from DOM
const vatMatch = document.querySelector('.summary-row:nth-child(3) span:first-child').textContent.match(/\d+/);
if (vatMatch) vatPercent = parseFloat(vatMatch[0]);

// Load products
fetch('api/get_products.php')
  .then(res => res.json())
  .then(products => {
    const grid = document.getElementById('products-grid');
    products.forEach(p => {
      const isLow = p.stock_quantity < 5;
      const card = document.createElement('div');
      card.className = 'product-card';
      if (isLow) card.classList.add('stock-low');
      card.innerHTML = `
        <h4>${p.name}</h4>
        <div class="price">Rs. ${parseFloat(p.price).toFixed(2)}</div>
        ${isLow ? '<div class="stock-low">Low Stock!</div>' : ''}
      `;
      card.dataset.id = p.id;
      card.dataset.price = p.price;
      card.dataset.name = p.name;
      card.onclick = () => addItemToBill(p);
      grid.appendChild(card);
    });
  });

function addItemToBill(product) {
  const existing = cart.find(item => item.id === product.id);
  if (existing) {
    existing.qty += 1;
  } else {
    cart.push({
      id: product.id,
      name: product.name,
      price: parseFloat(product.price),
      qty: 1
    });
  }
  renderBill();
}

function removeItem(index) {
  cart.splice(index, 1);
  renderBill();
}

function updateDiscount() {
  const input = document.getElementById('discount-input').value.trim();
  if (input.endsWith('%')) {
    discount = parseFloat(input) || 0;
  } else if (input) {
    discount = -parseFloat(input); // negative = fixed amount
  } else {
    discount = 0;
  }
  renderBill();
}

function updateLoyalty() {
  loyaltyPoints = parseInt(document.getElementById('loyalty-input').value) || 0;
  renderBill();
}

function calculateTotals() {
  let subtotal = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
  
  // Apply discount
  let discountAmount = 0;
  if (discount > 0) {
    discountAmount = (subtotal * discount) / 100;
  } else if (discount < 0) {
    discountAmount = -discount; // fixed amount
  }
  subtotal = Math.max(0, subtotal - discountAmount);
  
  // Apply loyalty (1 point = Rs. 1)
  const loyaltyRedeem = Math.min(loyaltyPoints, subtotal);
  subtotal = Math.max(0, subtotal - loyaltyRedeem);
  
  const vat = (subtotal * vatPercent) / 100;
  const grandTotal = subtotal + vat;
  
  return { subtotal, vat, grandTotal, discountAmount, loyaltyRedeem };
}

function renderBill() {
  const { subtotal, vat, grandTotal } = calculateTotals();
  
  // Update summary
  document.getElementById('subtotal').textContent = `Rs. ${subtotal.toFixed(2)}`;
  document.getElementById('vat').textContent = `Rs. ${vat.toFixed(2)}`;
  document.getElementById('grand-total').textContent = `Rs. ${grandTotal.toFixed(2)}`;
  
  // Update items list
  const list = document.getElementById('bill-items-list');
  list.innerHTML = '';
  
  if (cart.length === 0) {
    list.innerHTML = '<div style="padding:20px;text-align:center;color:#999;">No items added</div>';
    return;
  }
  
  cart.forEach((item, index) => {
    const lineTotal = item.price * item.qty;
    const div = document.createElement('div');
    div.className = 'bill-item';
    div.innerHTML = `
      <div class="bill-item-info">
        <div><strong>${item.name}</strong></div>
        <div>Rs. ${item.price.toFixed(2)} × ${item.qty}</div>
      </div>
      <div class="bill-item-actions">
        <button class="remove" onclick="removeItem(${index})">×</button>
      </div>
    `;
    list.appendChild(div);
  });
}

// Event Listeners
document.getElementById('discount-input').addEventListener('change', updateDiscount);
document.getElementById('loyalty-input').addEventListener('change', updateLoyalty);

// Save Bill
document.getElementById('save-bill-btn').onclick = function() {
  if (cart.length === 0) {
    alert("Please add items to the bill!");
    return;
  }
  
  const customer = document.getElementById('customer-name').value || 'Walk-in Customer';
  const notes = document.getElementById('bill-notes').value;
  const paymentMethod = document.getElementById('payment-method').value;
  
  const { subtotal, vat, grandTotal, discountAmount, loyaltyRedeem } = calculateTotals();
  
  fetch('api/save_bill.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      customer_name: customer,
      items: cart,
      discount: discountAmount,
      loyalty_redeemed: loyaltyRedeem,
      notes: notes,
      payment_method: paymentMethod,
      total: grandTotal
    })
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      alert(`✅ Bill saved! ID: ${data.bill_id}\nTotal: Rs. ${grandTotal.toFixed(2)}`);
      // Reset for new bill
      cart = [];
      document.getElementById('customer-name').value = '';
      document.getElementById('discount-input').value = '';
      document.getElementById('loyalty-input').value = '';
      document.getElementById('bill-notes').value = '';
      renderBill();
    } else {
      alert('❌ Error: ' + data.message);
    }
  });
};