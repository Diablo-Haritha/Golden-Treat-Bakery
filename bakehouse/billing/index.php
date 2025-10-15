<?php
/*
Separate SQL file (save as billing_schema.sql) - run this against your `golden_treat` database before using the POS.

-- ============================================================
-- BILLING / POS ADD-ON TABLES (for saving bills and items)
-- ============================================================
CREATE TABLE IF NOT EXISTS sales_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sale_id INT NOT NULL,
  product_id INT NULL,
  product_name VARCHAR(255) NOT NULL,
  quantity INT NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  line_total DECIMAL(10,2) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Optional: lightweight bills table (if you want to track bills separately)
CREATE TABLE IF NOT EXISTS bills (
  id INT AUTO_INCREMENT PRIMARY KEY,
  bill_number VARCHAR(50) UNIQUE NOT NULL,
  bill_date DATETIME DEFAULT CURRENT_TIMESTAMP,
  customer_name VARCHAR(255),
  user_id INT NULL,
  subtotal DECIMAL(12,2) NOT NULL,
  discount_amount DECIMAL(12,2) DEFAULT 0.00,
  vat_amount DECIMAL(12,2) DEFAULT 0.00,
  total DECIMAL(12,2) NOT NULL,
  status ENUM('Pending','Paid','Cancelled') DEFAULT 'Paid'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Index to help queries for recommendations
CREATE INDEX idx_sales_customer ON sales(customer);
CREATE INDEX idx_sales_created_at ON sales(created_at);

-- End of SQL
*/

// ------------------ CONFIG ------------------
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'golden_treat';

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_errno) {
    http_response_code(500);
    echo json_encode(['error' => 'DB connection failed: ' . $conn->connect_error]);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// Simple router for AJAX actions
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
if ($action === 'list_products') {
    // return products for grid
    $q = $conn->prepare("SELECT id, name, price, stock_quantity, image FROM products WHERE visibility=1 ORDER BY name ASC");
    $q->execute();
    $res = $q->get_result()->fetch_all(MYSQLI_ASSOC);
    echo json_encode($res);
    exit;
}

if ($action === 'search_customers') {
    $term = '%'.($_GET['q'] ?? '').'%';
    $q = $conn->prepare("SELECT id, full_name, mobile, email FROM users WHERE role='customer' AND (full_name LIKE ? OR mobile LIKE ?) LIMIT 10");
    $q->bind_param('ss', $term, $term);
    $q->execute();
    echo json_encode($q->get_result()->fetch_all(MYSQLI_ASSOC));
    exit;
}

if ($action === 'top_products') {
    // Top 6 selling products by sum(quantity) from sales_items
    $sql = "SELECT si.product_id, si.product_name, SUM(si.quantity) as sold
            FROM sales_items si
            GROUP BY si.product_id, si.product_name
            ORDER BY sold DESC
            LIMIT 6";
    $res = $conn->query($sql);
    echo json_encode($res->fetch_all(MYSQLI_ASSOC));
    exit;
}

if ($action === 'save_bill') {
    // Expected POST: customer_name, user_id (nullable), items(json array of {product_id, name, qty, unit_price}), discount (flat or percent flag?), discount_type, discount_value, vat_percent
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['items']) || !is_array($data['items'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid payload']);
        exit;
    }

    $customer_name = $conn->real_escape_string($data['customer_name'] ?? 'Walk-in Customer');
    $user_id = isset($data['user_id']) && $data['user_id'] ? intval($data['user_id']) : null;
    $items = $data['items'];
    $discount_type = $data['discount_type'] ?? 'flat'; // 'flat' or 'percent'
    $discount_value = floatval($data['discount_value'] ?? 0);
    $vat_percent = floatval($data['vat_percent'] ?? 0);

    // calculate totals
    $subtotal = 0.0;
    foreach ($items as $it) {
        $qty = max(0, intval($it['qty']));
        $price = floatval($it['unit_price']);
        $subtotal += ($qty * $price);
    }

    if ($discount_type === 'percent') {
        $discount_amount = round($subtotal * ($discount_value/100.0), 2);
    } else {
        $discount_amount = round($discount_value, 2);
    }

    $taxable = max(0, $subtotal - $discount_amount);
    $vat_amount = round($taxable * ($vat_percent/100.0), 2);
    $total = round($taxable + $vat_amount, 2);

    // Start transaction
    $conn->begin_transaction();
    try {
        // create sale (sales table exists in your DB)
        $stmt = $conn->prepare("INSERT INTO sales (date, customer, user_id, quantity, total, status, staff, created_at) VALUES (CURDATE(), ?, ?, ?, ?, 'Paid', 'POS', NOW())");
        $total_qty = 0;
        foreach ($items as $it) $total_qty += intval($it['qty']);
        $stmt->bind_param('siid', $customer_name, $user_id, $total_qty, $total);
        $stmt->execute();
        $sale_id = $stmt->insert_id;

        // save into sales_items
        $stmtItem = $conn->prepare("INSERT INTO sales_items (sale_id, product_id, product_name, quantity, unit_price, line_total) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($items as $it) {
            $pid = isset($it['product_id']) && $it['product_id'] ? intval($it['product_id']) : null;
            $name = $it['name'];
            $qty = intval($it['qty']);
            $unit = floatval($it['unit_price']);
            $line = round($qty * $unit, 2);
            $stmtItem->bind_param('iisiid', $sale_id, $pid, $name, $qty, $unit, $line);
            $stmtItem->execute();

            // optionally reduce product stock_quantity if product_id provided
            if ($pid) {
                $u = $conn->prepare("UPDATE products SET stock_quantity = GREATEST(stock_quantity - ?, 0) WHERE id = ?");
                $u->bind_param('ii', $qty, $pid);
                $u->execute();
            }
        }

        // optionally insert into bills table for quick listing
        $billno = 'BILL-'.date('Ymd').'-'.str_pad($sale_id, 5, '0', STR_PAD_LEFT);
        $insB = $conn->prepare("INSERT INTO bills (bill_number, customer_name, user_id, subtotal, discount_amount, vat_amount, total) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $insB->bind_param('siidddd', $billno, $customer_name, $user_id, $subtotal, $discount_amount, $vat_amount, $total);
        $insB->execute();

        $conn->commit();

        echo json_encode(['success' => true, 'sale_id' => $sale_id, 'bill_number' => $billno]);
    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(['error' => 'Save failed: '.$e->getMessage()]);
    }
    exit;
}

// If no action -> serve the POS HTML UI
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Golden Treat — POS Billing</title>
<style>
:root{--accent:#c65b2e}
body{font-family:Inter,system-ui,Arial;background:#f5f7fa;margin:0;padding:18px}
.container{max-width:1200px;margin:0 auto;background:#fff;padding:18px;border-radius:10px;box-shadow:0 6px 20px rgba(0,0,0,.06)}
.header{display:flex;justify-content:space-between;align-items:center}
.grid{display:grid;grid-template-columns:1fr 420px;gap:18px;margin-top:18px}
.products{border:1px solid #eee;padding:12px;border-radius:8px;height:620px;overflow:auto}
.product{display:flex;align-items:center;gap:12px;padding:8px;border-radius:8px;border-bottom:1px solid #f0f0f0}
.product img{width:56px;height:56px;object-fit:cover;border-radius:8px}
.product button{margin-left:auto;padding:8px 10px;border-radius:6px;border:0;background:var(--accent);color:#fff;cursor:pointer}
.cart{border:1px solid #eee;padding:12px;border-radius:8px;height:620px;display:flex;flex-direction:column}
.cart table{width:100%;border-collapse:collapse}
.cart th,.cart td{padding:6px;text-align:left}
.controls{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px}
.small{font-size:13px;padding:6px;border-radius:6px;border:1px solid #ddd}
.total{margin-top:auto;padding-top:12px}
.recommend{margin-top:12px}
</style>
</head>
<body>
<div class="container">
  <div class="header">
    <h2>Golden Treat — POS Billing</h2>
    <div>
      <button onclick="window.open('all_bill.php','_blank')">Open all_bill.php</button>
    </div>
  </div>

  <div class="grid">
    <div>
      <div style="display:flex;gap:8px;margin-bottom:8px;align-items:center">
        <input id="customer" class="small" placeholder="Customer name (type to search)" style="flex:1">
        <button id="tempCustomer" class="small">Temp</button>
        <input id="vat" class="small" placeholder="VAT %" value="8" style="width:80px">
        <select id="discount_type" class="small"><option value="flat">Discount (LKR)</option><option value="percent">Discount (%)</option></select>
        <input id="discount_value" class="small" value="0" style="width:90px">
      </div>

      <div class="products" id="products"></div>

      <div class="recommend">
        <strong>Top selling / Recommendations</strong>
        <div id="recommend_area"></div>
      </div>
    </div>

    <div class="cart">
      <div style="display:flex;gap:8px;margin-bottom:8px;align-items:center">
        <input id="manual_name" class="small" placeholder="Manual product name">
        <input id="manual_price" class="small" placeholder="Price">
        <input id="manual_qty" class="small" placeholder="Qty" value="1" style="width:70px">
        <button id="add_manual" class="small">Add</button>
      </div>

      <table id="cart_table">
        <thead><tr><th>Item</th><th>Qty</th><th>Unit</th><th>Line</th><th></th></tr></thead>
        <tbody></tbody>
      </table>

      <div class="total">
        <div>Subtotal: <span id="subtotal">0.00</span></div>
        <div>Discount: <span id="discount">0.00</span></div>
        <div>VAT: <span id="vat_amt">0.00</span></div>
        <h3>Total: <span id="total">0.00</span></h3>
        <div style="margin-top:8px;display:flex;gap:8px">
          <button id="save" style="padding:10px 14px;border-radius:8px;border:0;background:var(--accent);color:#fff">Save Bill</button>
          <button id="clear" style="padding:10px 14px;border-radius:8px;border:1px solid #ddd">Clear</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
let products = [];
let cart = [];

function fetchProducts(){
  fetch('?action=list_products').then(r=>r.json()).then(data=>{
    products = data;
    renderProducts();
  });
}

function fetchRec(){
  fetch('?action=top_products').then(r=>r.json()).then(data=>{
    const area = document.getElementById('recommend_area');
    area.innerHTML = '';
    data.forEach(p=>{
      const d = document.createElement('div');
      d.style.padding='6px';
      d.style.border='1px solid #eee';
      d.style.display='inline-block';
      d.style.margin='6px';
      d.style.borderRadius='6px';
      d.innerText = p.product_name + ' ('+p.sold+')';
      d.onclick = ()=>{ addToCart({product_id:p.product_id,name:p.product_name,qty:1,unit_price:0}); };
      area.appendChild(d);
    });
  });
}

function renderProducts(){
  const wrap = document.getElementById('products');
  wrap.innerHTML='';
  products.forEach(p=>{
    const el = document.createElement('div');
    el.className='product';
    el.innerHTML = `<img src="${p.image || 'default.jpg'}" onerror="this.src='default.jpg'">` +
                   `<div><strong>${p.name}</strong><div>Price: ${parseFloat(p.price).toFixed(2)} | Stock: ${p.stock_quantity}</div></div>`;
    const btn = document.createElement('button'); btn.innerText='Add';
    btn.onclick=()=> addToCart({product_id:p.id,name:p.name,qty:1,unit_price:p.price});
    el.appendChild(btn);
    wrap.appendChild(el);
  });
}

function addToCart(item){
  // merge if same product and price
  const found = cart.find(c=> c.product_id && item.product_id && c.product_id==item.product_id && c.unit_price==item.unit_price);
  if(found){ found.qty += item.qty; }
  else cart.push(Object.assign({},item));
  renderCart();
}

function renderCart(){
  const tbody = document.querySelector('#cart_table tbody');
  tbody.innerHTML = '';
  let subtotal = 0;
  cart.forEach((c, idx)=>{
    const line = (c.qty * c.unit_price);
    subtotal += line;
    const tr = document.createElement('tr');
    tr.innerHTML = `<td>${c.name}</td><td><input type="number" value="${c.qty}" min="1" style="width:60px" data-idx="${idx}"></td><td>${parseFloat(c.unit_price).toFixed(2)}</td><td>${line.toFixed(2)}</td><td><button data-rem="${idx}">x</button></td>`;
    tbody.appendChild(tr);
  });
  document.querySelectorAll('#cart_table input[type=number]').forEach(inp=>{
    inp.onchange = (e)=>{ const i=parseInt(e.target.dataset.idx); cart[i].qty = parseInt(e.target.value); renderCart(); };
  });
  document.querySelectorAll('#cart_table button[data-rem]').forEach(b=>{ b.onclick=()=>{ const i=parseInt(b.getAttribute('data-rem')); cart.splice(i,1); renderCart(); }; });

  const discount_type = document.getElementById('discount_type').value;
  const discount_value = parseFloat(document.getElementById('discount_value').value||0);
  const vat_percent = parseFloat(document.getElementById('vat').value||0);

  let discount = 0;
  if(discount_type==='percent') discount = subtotal * (discount_value/100.0);
  else discount = discount_value;
  discount = Math.max(0, discount);
  const taxable = Math.max(0, subtotal - discount);
  const vat_amt = taxable * (vat_percent/100.0);
  const total = taxable + vat_amt;

  document.getElementById('subtotal').innerText = subtotal.toFixed(2);
  document.getElementById('discount').innerText = discount.toFixed(2);
  document.getElementById('vat_amt').innerText = vat_amt.toFixed(2);
  document.getElementById('total').innerText = total.toFixed(2);
}

// manual add
document.getElementById('add_manual').onclick = ()=>{
  const name = document.getElementById('manual_name').value.trim();
  const price = parseFloat(document.getElementById('manual_price').value||0);
  const qty = parseInt(document.getElementById('manual_qty').value||1);
  if(!name || price<=0) return alert('Enter name and price');
  addToCart({product_id:null,name,qty,unit_price:price});
};

// temp customer
document.getElementById('tempCustomer').onclick = ()=>{ document.getElementById('customer').value = 'Walk-in Customer'; };

// typeahead customers
let custTimer=null;
const custInput = document.getElementById('customer');
custInput.addEventListener('input', ()=>{
  const v = custInput.value.trim();
  if(custTimer) clearTimeout(custTimer);
  if(v.length<2) return;
  custTimer = setTimeout(()=>{
    fetch('?action=search_customers&q='+encodeURIComponent(v)).then(r=>r.json()).then(data=>{
      // simple dropdown
      let list = document.getElementById('cust_list');
      if(!list){ list = document.createElement('div'); list.id='cust_list'; list.style.position='absolute'; list.style.background='#fff'; list.style.border='1px solid #ddd'; list.style.padding='6px'; document.body.appendChild(list);} 
      list.innerHTML='';
      const rect = custInput.getBoundingClientRect();
      list.style.left = rect.left + 'px'; list.style.top = (rect.bottom+4)+'px'; list.style.width = rect.width+'px';
      data.forEach(c=>{ const d=document.createElement('div'); d.style.padding='6px'; d.style.cursor='pointer'; d.innerText = c.full_name + (c.mobile?(' — '+c.mobile):''); d.onclick = ()=>{ custInput.value = c.full_name; list.remove(); }; list.appendChild(d); });
    });
  },250);
});

// save bill
document.getElementById('save').onclick = ()=>{
  if(cart.length===0) return alert('Cart empty');
  const items = cart.map(c=>({product_id: c.product_id, name: c.name, qty: c.qty, unit_price: c.unit_price}));
  const payload = {
    customer_name: document.getElementById('customer').value || 'Walk-in Customer',
    user_id: null,
    items: items,
    discount_type: document.getElementById('discount_type').value,
    discount_value: parseFloat(document.getElementById('discount_value').value||0),
    vat_percent: parseFloat(document.getElementById('vat').value||0)
  };
  fetch('?action=save_bill', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload)}).then(r=>r.json()).then(resp=>{
    if(resp.success){ alert('Saved bill: '+resp.bill_number); window.location.href='all_bill.php'; }
    else alert('Save failed: '+(resp.error||'Unknown'));
  });
};

// clear
document.getElementById('clear').onclick = ()=>{ cart=[]; renderCart(); };

// initial
fetchProducts(); fetchRec(); renderCart();
</script>

</body>
</html>
