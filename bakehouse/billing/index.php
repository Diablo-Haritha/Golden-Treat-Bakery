<?php
// ============================================================
// Golden Treat Bakery - Professional Billing System
// With Sidebar + Navbar | Print Ready | Debit Support
// ============================================================

// Database connection
$host = 'localhost';
$db   = 'golden_treat';
$user = 'root';          // ← CHANGE TO YOUR DB USER
$pass = '';              // ← CHANGE TO YOUR DB PASSWORD
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$opt = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
$pdo = new PDO($dsn, $user, $pass, $opt);

// Handle API requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    
    try {
        if ($_GET['action'] === 'save_bill') {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("
                INSERT INTO bills (
                    customer_name, discount, loyalty_redeemed, notes, 
                    payment_method, total_amount, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $input['customer_name'] ?? 'Walk-in',
                $input['discount'] ?? 0,
                $input['loyalty_redeemed'] ?? 0,
                $input['notes'] ?? '',
                $input['payment_method'] ?? 'cash',
                $input['total_amount'] ?? 0
            ]);
            $bill_id = $pdo->lastInsertId();
            
            $itemStmt = $pdo->prepare("INSERT INTO bill_items (bill_id, item_name, price, qty) VALUES (?, ?, ?, ?)");
            $stockStmt = $pdo->prepare("UPDATE products SET stock_quantity = GREATEST(0, stock_quantity - ?) WHERE id = ?");
            
            foreach ($input['items'] as $item) {
                $itemStmt->execute([$bill_id, $item['name'], $item['price'], $item['qty']]);
                if (isset($item['id'])) $stockStmt->execute([$item['qty'], $item['id']]);
            }
            
            if ($input['payment_method'] === 'debit' && !empty($input['customer_id'])) {
                $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?")
                    ->execute([$input['total_amount'], $input['customer_id']]);
            }
            
            $pdo->commit();
            echo json_encode(['success' => true, 'bill_id' => $bill_id]);
            exit;
        }
        
        if ($_GET['action'] === 'get_customers') {
            $term = $input['term'] ?? '';
            $stmt = $pdo->prepare("
                SELECT id, full_name, mobile, balance 
                FROM users 
                WHERE role = 'customer' 
                AND (full_name LIKE ? OR mobile LIKE ?)
                LIMIT 10
            ");
            $stmt->execute(["%$term%", "%$term%"]);
            echo json_encode($stmt->fetchAll());
            exit;
        }
        
        if ($_GET['action'] === 'get_settings') {
            $settings = $pdo->query("SELECT * FROM settings WHERE id = 1")->fetch();
            echo json_encode($settings ?: []);
            exit;
        }
        
        if ($_GET['action'] === 'update_settings') {
            $pdo->prepare("
                UPDATE settings 
                SET shop_name = ?, shop_slogan = ?, vat_percent = ?, thank_note = ?
                WHERE id = 1
            ")->execute([
                $input['shop_name'],
                $input['shop_slogan'],
                $input['vat_percent'],
                $input['thank_note']
            ]);
            echo json_encode(['success' => true]);
            exit;
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Fetch data
$settings = $pdo->query("SELECT * FROM settings WHERE id = 1")->fetch();
$vatPercent = $settings ? (float)$settings['vat_percent'] : 8.0;
$products = $pdo->query("SELECT id, name, price, stock_quantity FROM products WHERE visibility = 1 ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Billing - Golden Treat Bakery</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
    :root {
      --primary: #d4a574;
      --primary-dark: #b88a5f;
      --text: #3a2a1f;
      --light: #fdf9f3;
      --card: #ffffff;
      --border: #f0e0d0;
      --sidebar-bg: #2c2c2c;
      --sidebar-text: #e0e0e0;
      --sidebar-hover: #3a3a3a;
    }
    body {
      background: var(--light);
      color: var(--text);
      display: flex;
      min-height: 100vh;
    }

    /* Sidebar */
    .sidebar {
      width: 250px;
      background: var(--sidebar-bg);
      color: var(--sidebar-text);
      padding: 20px 0;
      transition: all 0.3s;
      height: 100vh;
      position: fixed;
      overflow-y: auto;
    }
    .logo {
      padding: 0 20px 20px;
      border-bottom: 1px solid #444;
    }
    .logo h1 {
      font-size: 20px;
      font-weight: 600;
      color: var(--primary);
    }
    .nav-item {
      padding: 12px 20px;
      display: flex;
      align-items: center;
      gap: 12px;
      cursor: pointer;
      transition: all 0.2s;
    }
    .nav-item:hover, .nav-item.active {
      background: var(--sidebar-hover);
      color: white;
    }
    .nav-item i { width: 24px; text-align: center; }

    /* Main Content */
    .main {
      flex: 1;
      margin-left: 250px;
      display: flex;
      flex-direction: column;
    }
    @media (max-width: 900px) {
      .sidebar { width: 70px; }
      .sidebar .logo h1, .sidebar .nav-text { display: none; }
      .sidebar .nav-item { justify-content: center; padding: 15px 0; }
      .main { margin-left: 70px; }
    }

    /* Top Navbar */
    .navbar {
      background: white;
      padding: 0 30px;
      height: 60px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    .page-title { font-size: 20px; font-weight: 600; color: var(--primary); }
    .navbar-actions { display: flex; gap: 15px; }
    .btn {
      padding: 8px 20px;
      border: none;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 15px;
    }
    .btn-primary { background: var(--primary); color: white; }
    .btn-outline { background: transparent; border: 2px solid var(--primary); color: var(--primary); }

    /* Content */
    .content {
      padding: 30px;
      flex: 1;
    }
    .billing-container {
      display: grid;
      grid-template-columns: 1fr 400px;
      gap: 24px;
      max-width: 1600px;
    }
    @media (max-width: 1200px) {
      .billing-container { grid-template-columns: 1fr; }
      .bill-panel { order: -1; }
    }

    /* Products */
    .products-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
      gap: 20px;
    }
    .product-card {
      background: var(--card);
      border-radius: 16px;
      padding: 20px 10px;
      text-align: center;
      cursor: pointer;
      transition: all 0.2s;
      box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }
    .product-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 6px 16px rgba(212, 165, 116, 0.2);
      border: 2px solid var(--primary);
    }
    .product-card h3 {
      font-size: 16px;
      margin: 10px 0 6px;
      font-weight: 600;
    }
    .product-card .price {
      font-weight: 700;
      color: var(--primary);
      font-size: 18px;
    }
    .stock-low { color: #e74c3c; font-size: 12px; margin-top: 4px; }

    /* Bill Panel */
    .bill-panel {
      background: var(--card);
      border-radius: 20px;
      padding: 24px;
      box-shadow: 0 8px 30px rgba(0,0,0,0.08);
      height: fit-content;
      position: sticky;
      top: 90px;
    }
    .panel-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
    }
    .panel-title {
      font-size: 22px;
      font-weight: 700;
      color: var(--primary);
    }
    .summary-box {
      background: #fdf6ee;
      border-radius: 16px;
      padding: 20px;
      margin-bottom: 24px;
    }
    .summary-row {
      display: flex;
      justify-content: space-between;
      padding: 8px 0;
      font-size: 16px;
    }
    .summary-row.total {
      font-weight: 700;
      color: #e74c3c;
      font-size: 20px;
      margin-top: 12px;
      padding-top: 12px;
      border-top: 2px dashed var(--primary);
    }
    input, select, textarea {
      width: 100%;
      padding: 12px;
      border: 2px solid var(--border);
      border-radius: 12px;
      font-size: 16px;
      margin-bottom: 16px;
    }
    textarea { min-height: 80px; resize: vertical; }
    .bill-items {
      max-height: 200px;
      overflow-y: auto;
      margin-bottom: 20px;
      padding-right: 8px;
    }
    .bill-item {
      display: flex;
      justify-content: space-between;
      padding: 12px 0;
      border-bottom: 1px solid #f5f0eb;
    }
    .bill-item:last-child { border-bottom: none; }
    .remove-btn {
      background: none;
      border: none;
      color: #e74c3c;
      font-size: 20px;
      cursor: pointer;
      width: 30px;
      height: 30px;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    /* Modals */
    .modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0; top: 0;
      width: 100%; height: 100%;
      background: rgba(0,0,0,0.7);
      align-items: center;
      justify-content: center;
    }
    .modal-content {
      background: white;
      border-radius: 20px;
      width: 90%;
      max-width: 600px;
      max-height: 90vh;
      overflow-y: auto;
      box-shadow: 0 20px 50px rgba(0,0,0,0.3);
    }
    .modal-header {
      padding: 24px;
      border-bottom: 2px solid var(--border);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .modal-title {
      font-size: 24px;
      font-weight: 700;
      color: var(--primary);
    }
    .close-modal {
      background: none;
      border: none;
      font-size: 28px;
      cursor: pointer;
      color: #999;
    }
    .modal-body {
      padding: 24px;
    }
    .form-group {
      margin-bottom: 20px;
    }
    .form-group label {
      display: block;
      margin-bottom: 8px;
      font-weight: 600;
      color: var(--text);
    }

    /* Print */
    @media print {
      .sidebar, .navbar, .no-print { display: none !important; }
      body { margin: 0; background: white; }
      .main { margin-left: 0; }
      .bill-panel {
        position: static !important;
        max-width: 400px;
        margin: 0 auto;
        box-shadow: none;
        border-radius: 0;
      }
    }
  </style>
</head>
<body>

  <!-- Sidebar -->
  <div class="sidebar">
    <div class="logo">
      <h1>Golden Treat</h1>
    </div>
    <div class="nav-item active">
      <i class="fas fa-cash-register"></i>
      <span class="nav-text">Billing</span>
    </div>
    <div class="nav-item" onclick="window.location='stock.php'">
      <i class="fas fa-boxes"></i>
      <span class="nav-text">Stock</span>
    </div>
    <div class="nav-item" onclick="window.location='orders.php'">
      <i class="fas fa-shopping-cart"></i>
      <span class="nav-text">Orders</span>
    </div>
    <div class="nav-item" onclick="window.location='bookings.php'">
      <i class="fas fa-calendar-check"></i>
      <span class="nav-text">Bookings</span>
    </div>
    <div class="nav-item" onclick="window.location='reports.php'">
      <i class="fas fa-chart-bar"></i>
      <span class="nav-text">Reports</span>
    </div>
    <div class="nav-item" onclick="openSettingsModal()">
      <i class="fas fa-cog"></i>
      <span class="nav-text">Settings</span>
    </div>
  </div>

  <!-- Main Content -->
  <div class="main">
    <!-- Top Navbar -->
    <div class="navbar">
      <div class="page-title">Billing System</div>
      <div class="navbar-actions">
        <button class="btn btn-outline no-print" onclick="clearBill()">
          <i class="fas fa-trash"></i> New Bill
        </button>
        <button class="btn btn-primary no-print" onclick="saveAndPrint()">
          <i class="fas fa-print"></i> Save & Print
        </button>
      </div>
    </div>

    <!-- Content -->
    <div class="content">
      <div class="billing-container">
        <!-- Products -->
        <div>
          <input type="text" id="search-products" placeholder="Search products..." 
                 oninput="filterProducts()" 
                 style="width:100%; padding:14px; font-size:16px; border-radius:12px; border:2px solid var(--border); margin-bottom:20px;">
          <div class="products-grid" id="products-grid">
            <?php foreach ($products as $p): ?>
              <div class="product-card" data-id="<?= $p['id'] ?>" data-name="<?= htmlspecialchars($p['name']) ?>" 
                   onclick='addItem(<?= json_encode($p) ?>)'>
                <i class="fas fa-cupcake" style="font-size:28px; color:var(--primary);"></i>
                <h3><?= htmlspecialchars($p['name']) ?></h3>
                <div class="price">Rs. <?= number_format($p['price'], 2) ?></div>
                <?php if ($p['stock_quantity'] < 5): ?>
                  <div class="stock-low">Low Stock!</div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Bill Panel -->
        <div class="bill-panel">
          <div class="panel-header">
            <div class="panel-title">Current Bill</div>
          </div>

          <div class="form-group">
            <label>Customer</label>
            <div style="display:flex; gap:10px;">
              <input type="text" id="customer-name" placeholder="Walk-in Customer" onfocus="openDebitModal()">
              <select id="payment-method" style="flex:1;" onchange="handlePaymentChange()">
                <option value="cash">Cash</option>
                <option value="card">Card</option>
                <option value="mobile">Mobile</option>
                <option value="debit">Debit Account</option>
              </select>
            </div>
            <input type="hidden" id="customer-id">
          </div>

          <div class="summary-box">
            <div class="summary-row">
              <span>Subtotal:</span>
              <span id="subtotal">Rs. 0.00</span>
            </div>
            <div class="summary-row">
              <span>VAT (<?= $vatPercent ?>%):</span>
              <span id="vat">Rs. 0.00</span>
            </div>
            <div class="summary-row">
              <span>Discount:</span>
              <input type="text" id="discount" placeholder="0%" style="width:90px; text-align:right; padding:6px;" onblur="calculate()">
            </div>
            <div class="summary-row total">
              <span>GRAND TOTAL:</span>
              <span id="total">Rs. 0.00</span>
            </div>
          </div>

          <div class="form-group">
            <label>Bill Items</label>
            <div class="bill-items" id="bill-items">
              <div style="text-align:center; color:#999; padding:20px;">No items added</div>
            </div>
          </div>

          <div class="form-group">
            <label>Notes</label>
            <textarea id="notes" placeholder="Special instructions..."></textarea>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Debit Modal -->
  <div id="debitModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-title">Select Customer (Debit)</div>
        <button class="close-modal" onclick="closeDebitModal()">&times;</button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label>Search Customer</label>
          <input type="text" id="customer-search" placeholder="Name or phone..." oninput="searchCustomers()">
        </div>
        <div id="customers-list" style="max-height:300px; overflow-y:auto;">
          <div style="padding:20px; text-align:center;">Start typing to search...</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Settings Modal -->
  <div id="settingsModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-title">Bill Structure Settings</div>
        <button class="close-modal" onclick="closeSettingsModal()">&times;</button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label>Shop Name</label>
          <input type="text" id="setting-shop-name" value="<?= htmlspecialchars($settings['shop_name'] ?? 'Golden Treat Bakery') ?>">
        </div>
        <div class="form-group">
          <label>Slogan</label>
          <input type="text" id="setting-slogan" value="<?= htmlspecialchars($settings['shop_slogan'] ?? 'Fresh & Tasty Every Day') ?>">
        </div>
        <div class="form-group">
          <label>VAT (%)</label>
          <input type="number" id="setting-vat" value="<?= $vatPercent ?>" step="0.01">
        </div>
        <div class="form-group">
          <label>Thank You Note</label>
          <textarea id="setting-thank-note"><?= htmlspecialchars($settings['thank_note'] ?? 'Thank you for visiting!') ?></textarea>
        </div>
        <button class="btn btn-primary" onclick="saveSettings()" style="width:100%;">Save Settings</button>
      </div>
    </div>
  </div>

  <!-- Success Modal -->
  <div id="successModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-title">✅ Bill Saved!</div>
        <button class="close-modal" onclick="closeSuccessModal()">&times;</button>
      </div>
      <div class="modal-body" style="text-align:center; padding:30px;">
        <p style="font-size:18px;">Bill ID: <strong id="saved-bill-id"></strong></p>
        <p style="font-size:22px; color:var(--primary); margin:15px 0;">Total: Rs. <span id="saved-total"></span></p>
        <button class="btn btn-primary" onclick="window.print()" style="margin-top:20px; width:100%;">
          <i class="fas fa-print"></i> Print Receipt
        </button>
      </div>
    </div>
  </div>

  <script>
    let cart = [];
    let vatPercent = <?= $vatPercent ?>;
    let discount = 0;
    let selectedCustomer = null;

    function addItem(product) {
      const existing = cart.find(item => item.id === product.id);
      if (existing) {
        existing.qty++;
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

    function calculate() {
      const discountInput = document.getElementById('discount').value.trim();
      if (discountInput.endsWith('%')) {
        discount = parseFloat(discountInput) || 0;
      } else if (discountInput) {
        discount = -Math.abs(parseFloat(discountInput));
      } else {
        discount = 0;
      }

      let subtotal = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
      let discountAmount = 0;
      
      if (discount > 0) {
        discountAmount = (subtotal * discount) / 100;
      } else if (discount < 0) {
        discountAmount = -discount;
      }
      
      subtotal = Math.max(0, subtotal - discountAmount);
      const vat = (subtotal * vatPercent) / 100;
      const total = subtotal + vat;
      
      document.getElementById('subtotal').textContent = `Rs. ${subtotal.toFixed(2)}`;
      document.getElementById('vat').textContent = `Rs. ${vat.toFixed(2)}`;
      document.getElementById('total').textContent = `Rs. ${total.toFixed(2)}`;
    }

    function renderBill() {
      calculate();
      const container = document.getElementById('bill-items');
      if (cart.length === 0) {
        container.innerHTML = '<div style="text-align:center; color:#999; padding:20px;">No items added</div>';
        return;
      }
      container.innerHTML = cart.map((item, index) => `
        <div class="bill-item">
          <div>
            <div><strong>${item.name}</strong></div>
            <div>Rs. ${item.price.toFixed(2)} × ${item.qty}</div>
          </div>
          <button class="remove-btn" onclick="removeItem(${index})">×</button>
        </div>
      `).join('');
    }

    function clearBill() {
      if (cart.length === 0 || confirm('Start a new bill? Current items will be lost.')) {
        cart = [];
        document.getElementById('customer-name').value = '';
        document.getElementById('customer-id').value = '';
        document.getElementById('notes').value = '';
        document.getElementById('discount').value = '';
        document.getElementById('payment-method').value = 'cash';
        selectedCustomer = null;
        renderBill();
      }
    }

    function handlePaymentChange() {
      if (document.getElementById('payment-method').value === 'debit') {
        openDebitModal();
      }
    }

    function openDebitModal() {
      document.getElementById('debitModal').style.display = 'flex';
      document.getElementById('customer-search').value = '';
      document.getElementById('customers-list').innerHTML = '<div style="padding:20px; text-align:center;">Start typing to search...</div>';
    }

    function closeDebitModal() {
      document.getElementById('debitModal').style.display = 'none';
    }

    function searchCustomers() {
      const term = document.getElementById('customer-search').value.trim();
      if (term.length < 2) {
        document.getElementById('customers-list').innerHTML = '<div style="padding:20px; text-align:center;">Enter 2+ characters...</div>';
        return;
      }
      
      fetch('?action=get_customers', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ term })
      })
      .then(res => res.json())
      .then(customers => {
        if (customers.length === 0) {
          document.getElementById('customers-list').innerHTML = '<div style="padding:20px; text-align:center;">No customers found</div>';
          return;
        }
        document.getElementById('customers-list').innerHTML = customers.map(c => `
          <div style="padding:15px; border-bottom:1px solid #eee; cursor:pointer;" 
               onclick="selectCustomer(${c.id}, '${c.full_name.replace(/'/g, "\\'")}', ${c.balance})">
            <div style="font-weight:600;">${c.full_name}</div>
            <div>Phone: ${c.mobile} | Balance: Rs. ${c.balance.toFixed(2)}</div>
          </div>
        `).join('');
      });
    }

    function selectCustomer(id, name, balance) {
      document.getElementById('customer-name').value = name;
      document.getElementById('customer-id').value = id;
      selectedCustomer = { id, name, balance };
      closeDebitModal();
    }

    function openSettingsModal() {
      fetch('?action=get_settings')
        .then(res => res.json())
        .then(settings => {
          if (Object.keys(settings).length > 0) {
            document.getElementById('setting-shop-name').value = settings.shop_name || '';
            document.getElementById('setting-slogan').value = settings.shop_slogan || '';
            document.getElementById('setting-vat').value = settings.vat_percent || 0;
            document.getElementById('setting-thank-note').value = settings.thank_note || '';
          }
        });
      document.getElementById('settingsModal').style.display = 'flex';
    }

    function closeSettingsModal() {
      document.getElementById('settingsModal').style.display = 'none';
    }

    function saveSettings() {
      const data = {
        shop_name: document.getElementById('setting-shop-name').value,
        shop_slogan: document.getElementById('setting-slogan').value,
        vat_percent: document.getElementById('setting-vat').value,
        thank_note: document.getElementById('setting-thank-note').value
      };
      
      fetch('?action=update_settings', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
      })
      .then(res => res.json())
      .then(result => {
        if (result.success) {
          vatPercent = parseFloat(data.vat_percent);
          calculate();
          closeSettingsModal();
          alert('Settings saved successfully!');
        } else {
          alert('Failed to save settings');
        }
      });
    }

    function saveAndPrint() {
      if (cart.length === 0) {
        alert('Please add items to the bill!');
        return;
      }
      
      const paymentMethod = document.getElementById('payment-method').value;
      if (paymentMethod === 'debit' && !selectedCustomer) {
        alert('Please select a customer for debit payment');
        return;
      }
      
      let subtotal = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
      let discountAmount = 0;
      if (discount > 0) {
        discountAmount = (subtotal * discount) / 100;
      } else if (discount < 0) {
        discountAmount = -discount;
      }
      subtotal = Math.max(0, subtotal - discountAmount);
      const vat = (subtotal * vatPercent) / 100;
      const total = subtotal + vat;
      
      const billData = {
        customer_name: document.getElementById('customer-name').value || 'Walk-in',
        customer_id: selectedCustomer ? selectedCustomer.id : null,
        items: cart,
        discount: discountAmount,
        loyalty_redeemed: 0,
        notes: document.getElementById('notes').value,
        payment_method: paymentMethod,
        total_amount: total
      };
      
      fetch('?action=save_bill', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(billData)
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          document.getElementById('saved-bill-id').textContent = data.bill_id;
          document.getElementById('saved-total').textContent = total.toFixed(2);
          document.getElementById('successModal').style.display = 'flex';
          clearBill();
        } else {
          alert('Error: ' + (data.message || 'Failed to save bill'));
        }
      });
    }

    function closeSuccessModal() {
      document.getElementById('successModal').style.display = 'none';
    }

    function filterProducts() {
      const term = document.getElementById('search-products').value.toLowerCase();
      document.querySelectorAll('.product-card').forEach(card => {
        const name = card.dataset.name.toLowerCase();
        card.style.display = name.includes(term) ? 'block' : 'none';
      });
    }

    window.onclick = function(event) {
      if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
      }
    }

    // Initialize
    renderBill();
  </script>
</body>
</html>