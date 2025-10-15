<?php
// index.php - Enhanced Create New Bill Page with Improved UX
// Database Connection
$host = 'localhost';
$dbname = 'golden_treat';
$username = 'root';
$password = '';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Handle Form Submission (Save Bill)
$successMessage = '';
$errorMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerName = trim($_POST['customer_name'] ?? '');
    $customerId = !empty($_POST['customer_id']) ? intval($_POST['customer_id']) : null;
    $paymentMethod = $_POST['payment_method'] ?? '';
    $discount = floatval($_POST['discount'] ?? 0);
    $vatPercent = floatval($_POST['vat_percent'] ?? 8);
    $billItemsData = json_decode($_POST['bill_items'] ?? '[]', true);
    if (empty($customerName) || empty($billItemsData) || empty($paymentMethod)) {
        $errorMessage = 'Invalid data provided.';
    } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO bills (customer_name, user_id, payment_method, discount, vat_percent) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$customerName, $customerId, $paymentMethod, $discount, $vatPercent]);
            $billId = $pdo->lastInsertId();
            $stmt = $pdo->prepare("INSERT INTO bill_items (bill_id, item_name, price, qty) VALUES (?, ?, ?, ?)");
            foreach ($billItemsData as $item) {
                if (isset($item['name'], $item['price'], $item['qty']) && $item['qty'] > 0) {
                    $stmt->execute([$billId, $item['name'], $item['price'], $item['qty']]);
                }
            }
            $stmt = $pdo->prepare("SELECT SUM(price * qty) as subtotal FROM bill_items WHERE bill_id = ?");
            $stmt->execute([$billId]);
            $subtotal = floatval($stmt->fetchColumn() ?: 0);
            $vatAmount = ($subtotal - $discount) * ($vatPercent / 100);
            $grandTotal = $subtotal - $discount + $vatAmount;
            $stmt = $pdo->prepare("UPDATE bills SET grand_total = ? WHERE id = ?");
            $stmt->execute([$grandTotal, $billId]);
            $pdo->commit();
            $successMessage = "Bill saved successfully! ID: $billId";
        } catch (Exception $e) {
            $pdo->rollback();
            $errorMessage = 'Error saving bill: ' . $e->getMessage();
        }
    }
}

// Handle AJAX Product Search
if (isset($_GET['action']) && $_GET['action'] === 'search_products' && isset($_GET['q'])) {
    header('Content-Type: application/json');
    $query = '%' . $_GET['q'] . '%';
    $stmt = $pdo->prepare("SELECT id, name, price, category FROM products WHERE visibility = 1 AND name LIKE ? ORDER BY name LIMIT 8");
    $stmt->execute([$query]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// Fetch Products
$stmt = $pdo->query("SELECT id, name, price, image, category FROM products WHERE visibility = 1 ORDER BY name");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Categories
$stmt = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND visibility = 1 ORDER BY category");
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create New Bill - Golden Treat</title>
  <link rel="stylesheet" href="style.css">
  <style>
:root {
  --brand: #e10000;
  --ink: #111827;
  --paper: #fff;
  --muted: #6b7280;
  --soft: #e5e7eb;
  --warn: #ffc107;
  --danger: #dc3545;
  --primary: #007bff;
}

* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: Arial, Helvetica, sans-serif;
  background: #f4f6f9;
  color: #0f172a;
  min-height: 100vh;
  overflow-x: hidden;
  /* Prevent horizontal scroll */
}

/* Fixed Header */
.header {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: #fff;
  padding: 12px 16px;

  z-index: 1000;
  /* High z-index to stay on top */
  height: 70px;
  /* Fixed height for consistency */
  box-sizing: border-box;
}

.header-left img {
  width: 56px;
  height: auto;
  border-radius: 8px;
  display: block;
  box-shadow: 2px 2px 5px rgba(0, 0, 0, .15);
}

.header-middle {
  display: flex;
  align-items: center;
  gap: 12px;
  flex: 1;
  margin: 0 16px;
  max-width: 720px;
}

.header-middle-title {
  font-weight: 800;
  font-size: 26px;
  color: var(--brand);
  white-space: nowrap;
}

.search-bar {
  flex: 1;
  display: flex;
}

.search-bar input {
  width: 100%;
  padding: 8px 10px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
}

.header-right {
  display: flex;
  align-items: center;
  gap: 12px;
}

.role-btn {
  background: #111827;
  color: #fff;
  padding: 8px 14px;
  border: none;
  border-radius: 8px;
  cursor: pointer;
}

.role-btn:hover {
  opacity: .9;
}

.user-icon {
  width: 28px;
  height: 28px;
  background: linear-gradient(135deg, #bbb, #888);
  border-radius: 50%;
}

/* Fixed Sidebar */
.sidebar {
  position: fixed;
  top: 70px;
  /* Below header height */
  left: 0;
  width: 260px;
  height: calc(100vh - 70px);
  /* Full height minus header */
  background: #fff;
  padding: 18px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  border-right: 1px solid #e5e7eb;
  overflow-y: auto;
  /* Allow scroll inside sidebar if needed, but header/sidebar fixed */
  z-index: 999;
}

.sidebar h1 {
  text-align: center;
  font-size: 20px;
  margin-bottom: 8px;
  color: #0f172a;
}

.sidebar nav {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

/* Sidebar groups */
.salesbtn {
  background: var(--brand);
  border: none;
  border-radius: 10px;
  color: #fff;
  font-weight: 800;
  font-size: 22px;
  padding: 12px;
  text-align: center;
}

.otherbtn button {
  border: none;
  border-radius: 10px;
  color: #fff;
  cursor: pointer;
  padding: 10px 12px;
  font-weight: 700;
  gap: 10px;
}

.salebtn button {
  background: #e10000;
  border: none;
  text-align: left;
  padding: 10px;
  margin: 10px;
  border-radius: 6px;
  cursor: pointer;
  font-size: 14px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  color: #fff;
}

.Sbtn {
  background: #e37200;
  margin-left: 10px;
}

.Ubtn {
  background: #9c0dc7;
}

.Bbtn {
  background: #edcd00;
}

.otherbtn button:hover {
  filter: brightness(1.1);
}

.sidebar hr {
  margin: 8px 0;
}

.sidebar p {
  font-size: 12px;
  color: #6b7280;
  font-weight: 700;
}

.salebtn button {
  background: var(--brand);
  text-align: left;
}

.salebtn button.active {
  outline: 3px solid rgba(48, 182, 162, .35);
}

.sidebar hr {
  margin: 8px 0;
}

.sidebar p {
  font-size: 12px;
  color: #6b7280;
  font-weight: 700;
}

/* Sales Management sub-tabs */
.salebtn {
  display: flex;
  flex-direction: column;
}

.salebtn .tab-btn {
  background: var(--brand);
  border: none;
  border-radius: 10px;
  color: #fff;
  cursor: pointer;
  padding: 10px 12px;
  font-weight: 700;
  text-align: left;
}

.salebtn .tab-btn+.tab-btn {
  margin-top: 8px;
}

.salebtn .tab-btn.active {
  outline: 3px solid #e10000;
  background: #fff;
  color: var(--brand);
}

    /* Main Layout */
    .layout {
      margin-left: 260px;
      margin-top: 70px;
      min-height: calc(100vh - 70px);
      padding: 20px;
      background: #f8f9fa;
    }

    .container {
      display: grid;
      grid-template-columns: 1fr 450px;
      gap: 20px;
      max-width: 1600px;
      margin: 0 auto;
    }

    .products-section {
      background: #fff;
      border-radius: 12px;
      padding: 24px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
      height: calc(100vh - 130px);
      display: flex;
      flex-direction: column;
    }

    .bill-section {
      background: #fff;
      border-radius: 12px;
      padding: 24px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
      height: calc(100vh - 130px);
      overflow-y: auto;
      position: sticky;
      top: 90px;
    }

    /* Enhanced Product Filters with Tabs */
    .product-filters {
      display: flex;
      flex-direction: column;
      gap: 12px;
      margin-bottom: 20px;
    }

    .category-tabs {
      display: flex;
      gap: 8px;
      overflow-x: auto;
      padding-bottom: 8px;
      border-bottom: 1px solid #e5e7eb;
      scrollbar-width: thin;
      scrollbar-color: #d1d5db #f3f4f6;
    }

    .category-tabs::-webkit-scrollbar {
      height: 4px;
    }

    .category-tabs::-webkit-scrollbar-track {
      background: #f3f4f6;
      border-radius: 2px;
    }

    .category-tabs::-webkit-scrollbar-thumb {
      background: #d1d5db;
      border-radius: 2px;
    }

    .category-tabs .tab-btn {
      flex-shrink: 0;
      padding: 8px 16px;
      border: 2px solid #e5e7eb;
      background: white;
      color: #374151;
      cursor: pointer;
      border-radius: 20px;
      font-size: 14px;
      font-weight: 600;
      transition: all 0.2s ease;
      white-space: nowrap;
    }

    .category-tabs .tab-btn:hover {
      border-color: var(--brand);
      background: #fef2f2;
    }

    .category-tabs .tab-btn.active {
      background: var(--brand);
      color: white;
      border-color: var(--brand);
      box-shadow: 0 2px 8px rgba(225, 0, 0, 0.2);
    }

    .search-wrapper {
      position: relative;
      min-width: 300px;
    }

    .search-products {
      width: 100%;
      padding: 12px 40px 12px 16px;
      border: 2px solid #e5e7eb;
      border-radius: 8px;
      font-size: 14px;
      transition: all 0.2s;
    }

    .search-products:focus {
      outline: none;
      border-color: var(--brand);
      box-shadow: 0 0 0 3px rgba(225, 0, 0, 0.1);
    }

    .search-icon {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      color: #9ca3af;
      pointer-events: none;
    }

    .clear-filters {
      padding: 10px 16px;
      background: #f3f4f6;
      border: 2px solid #e5e7eb;
      border-radius: 8px;
      cursor: pointer;
      font-size: 14px;
      font-weight: 600;
      color: #374151;
      transition: all 0.2s;
      white-space: nowrap;
      align-self: flex-start;
    }

    .clear-filters:hover {
      background: #e5e7eb;
      border-color: #d1d5db;
    }

    /* Product Suggestions Dropdown */
    .product-suggestions-div {
      position: absolute;
      top: 100%;
      left: 0;
      right: 0;
      background: white;
      border: 2px solid #e5e7eb;
      border-radius: 8px;
      margin-top: 4px;
      max-height: 300px;
      overflow-y: auto;
      z-index: 1000;
      display: none;
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }

    .suggestion-item {
      padding: 12px 16px;
      cursor: pointer;
      border-bottom: 1px solid #f3f4f6;
      transition: background 0.15s;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .suggestion-item:last-child {
      border-bottom: none;
    }

    .suggestion-item:hover {
      background: #fef2f2;
    }

    .suggestion-name {
      font-weight: 600;
      color: #111827;
    }

    .suggestion-price {
      color: var(--brand);
      font-weight: bold;
    }

    /* Product Grid with Scroll */
    .product-grid-wrapper {
      flex: 1;
      overflow-y: auto;
      padding-right: 4px;
    }

    .product-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
      gap: 16px;
      opacity: 1;
      transition: opacity 0.3s ease;
    }

    .product-grid.loading {
      opacity: 0.6;
    }

    .product-card {
      border: 2px solid #e5e7eb;
      padding: 12px;
      border-radius: 12px;
      text-align: center;
      cursor: pointer;
      background: #fff;
      transition: all 0.2s ease;
      position: relative;
      animation: fadeInUp 0.3s ease forwards;
      opacity: 0;
      transform: translateY(20px);
    }

    .product-card:nth-child(1) { animation-delay: 0.1s; }
    .product-card:nth-child(2) { animation-delay: 0.2s; }
    .product-card:nth-child(3) { animation-delay: 0.3s; }
    .product-card:nth-child(4) { animation-delay: 0.4s; }

    @keyframes fadeInUp {
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .product-card:hover {
      box-shadow: 0 6px 16px rgba(225, 0, 0, 0.2);
      transform: translateY(-4px);
      border-color: var(--brand);
    }

    .product-card:active {
      transform: translateY(-2px);
    }

    .product-card img {
      width: 100%;
      height: 120px;
      object-fit: cover;
      border-radius: 8px;
      margin-bottom: 10px;
      transition: transform 0.2s ease;
    }

    .product-card:hover img {
      transform: scale(1.05);
    }

    .product-name {
      font-weight: 600;
      margin: 8px 0 4px 0;
      font-size: 14px;
      color: #374151;
      line-height: 1.3;
      min-height: 36px;
    }

    .product-price {
      color: var(--brand);
      font-size: 16px;
      font-weight: bold;
      margin-top: 4px;
    }

    .product-category {
      font-size: 11px;
      color: #6b7280;
      background: #f3f4f6;
      padding: 2px 8px;
      border-radius: 4px;
      display: inline-block;
      margin-bottom: 6px;
    }

    /* Bill Section Improvements */
    .bill-header {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 20px;
      padding-bottom: 16px;
      border-bottom: 2px solid #f3f4f6;
    }

    .bill-header h1 {
      color: var(--brand);
      font-size: 24px;
      flex: 1;
    }

    .bill-count {
      background: var(--brand);
      color: white;
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 14px;
      font-weight: bold;
      transition: transform 0.2s ease;
    }

    .bill-count:hover {
      transform: scale(1.05);
    }

    .customer-section, .payment-method, .discount-vat-section {
      margin-bottom: 20px;
    }

    .input-group {
      margin-bottom: 16px;
    }

    .input-group label {
      display: block;
      margin-bottom: 6px;
      font-weight: 600;
      color: #374151;
      font-size: 14px;
    }

    .customer-input, .payment-method select {
      width: 100%;
      padding: 12px 16px;
      font-size: 15px;
      border: 2px solid #e5e7eb;
      border-radius: 8px;
      transition: all 0.2s;
      background: white;
    }

    .customer-input:focus, .payment-method select:focus {
      border-color: var(--brand);
      outline: none;
      box-shadow: 0 0 0 3px rgba(225, 0, 0, 0.1);
    }

    .temp-customer {
      font-size: 12px;
      color: #6b7280;
      margin-top: 6px;
      display: block;
      line-height: 1.4;
    }

    /* Discount and VAT Section */
    .discount-vat-section {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
    }

    .discount-vat-section .input-group {
      margin-bottom: 0;
    }

    .discount-vat-section input {
      width: 100%;
      padding: 12px 16px;
      border: 2px solid #e5e7eb;
      border-radius: 8px;
      text-align: center;
      font-size: 15px;
      font-weight: 600;
      transition: all 0.2s;
    }

    .discount-vat-section input:focus {
      border-color: var(--brand);
      outline: none;
      box-shadow: 0 0 0 3px rgba(225, 0, 0, 0.1);
    }

    /* Bill Items Section */
    .bill-items-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin: 24px 0 12px 0;
    }

    .bill-items-header h3 {
      color: #111827;
      font-size: 16px;
      margin: 0;
    }

    .items-count {
      color: #6b7280;
      font-size: 14px;
      font-weight: 600;
    }

   
    #itemsTable {
      width: 100%;
      border-collapse: separate;
      border-spacing: 0;
      margin-bottom: 20px;
      font-size: 13px;
      border: 2px solid #e5e7eb;
      border-radius: 8px;
      overflow: hidden;
    }

    #itemsTable th {
      background: var(--brand);
      color: white;
      font-weight: 600;
      padding: 12px 8px;
      text-align: left;
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    #itemsTable td {
      padding: 12px 8px;
      border-bottom: 1px solid #f3f4f6;
    }

    #itemsTable tr:last-child td {
      border-bottom: none;
    }

    #itemsTable tbody tr {
      transition: background 0.15s;
    }

    #itemsTable tbody tr:hover {
      background: #fef2f2;
    }

    .qty-input {
      width: 100%;
      padding: 4px 8px;
      border: 2px solid #e5e7eb;
      border-radius: 6px;
      text-align: center;
      font-weight: 600;
      transition: all 0.2s;
      box-sizing: border-box;
    }

    .qty-input:focus {
      border-color: var(--brand);
      outline: none;
    }

    .item-total {
      font-weight: bold;
      color: var(--brand);
      text-align: right;
    }

    .remove-item {
      color: #ef4444;
      cursor: pointer;
      font-weight: 600;
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      transition: color 0.2s;
      display: block;
      width: 100%;
    }

    .remove-item:hover {
      color: #dc2626;
      text-decoration: underline;
    }

    .empty-bill {
      text-align: center;
      padding: 40px 20px;
      color: #9ca3af;
    }

    .empty-bill-icon {
      font-size: 48px;
      margin-bottom: 12px;
      opacity: 0.5;
    }

    /* Totals Box */
    .totals-box {
      background: linear-gradient(135deg, #fef2f2, #fee2e2);
      padding: 20px;
      border-radius: 10px;
      margin: 20px 0;
      border: 2px solid #fecaca;
      transition: box-shadow 0.2s ease;
    }

    .totals-box:hover {
      box-shadow: 0 4px 12px rgba(225, 0, 0, 0.1);
    }

    .totals-box .total-row {
      display: flex;
      justify-content: space-between;
      margin: 10px 0;
      font-size: 15px;
      color: #374151;
    }

    .totals-box .total-row.grand {
      font-size: 20px;
      font-weight: bold;
      color: var(--brand);
      border-top: 2px solid #fecaca;
      padding-top: 12px;
      margin-top: 12px;
    }

    .totals-box .total-label {
      font-weight: 600;
    }

    .totals-box .total-value {
      font-weight: bold;
    }

    /* Action Buttons */
    .bill-actions {
      display: flex;
      gap: 12px;
      margin-top: 24px;
    }

    .btn {
      flex: 1;
      padding: 14px 20px;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      font-size: 15px;
      font-weight: 700;
      transition: all 0.2s;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }

    .btn-clear {
      background: #f3f4f6;
      color: #374151;
      border: 2px solid #e5e7eb;
    }

    .btn-clear:hover {
      background: #e5e7eb;
      border-color: #d1d5db;
    }

    .btn-save {
      background: var(--brand);
      color: white;
    }

    .btn-save:hover {
      background: #c90000;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(225, 0, 0, 0.3);
    }

    .btn-save:active {
      transform: translateY(0);
    }

    .btn:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }

    /* Messages */
    .message {
      padding: 14px 16px;
      margin-bottom: 20px;
      border-radius: 8px;
      font-weight: 600;
      animation: slideIn 0.3s ease;
    }

    @keyframes slideIn {
      from {
        opacity: 0;
        transform: translateY(-10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .success {
      background: #d1fae5;
      color: #065f46;
      border: 2px solid #6ee7b7;
    }

    .error {
      background: #fee2e2;
      color: #991b1b;
      border: 2px solid #fecaca;
    }

    /* Loading State */
    .loading {
      text-align: center;
      padding: 40px;
      color: #9ca3af;
    }

    .spinner {
      border: 3px solid #f3f4f6;
      border-top: 3px solid var(--brand);
      border-radius: 50%;
      width: 40px;
      height: 40px;
      animation: spin 1s linear infinite;
      margin: 0 auto 16px;
    }

    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }

    /* Scrollbar Styling */
    .product-grid-wrapper::-webkit-scrollbar,
    .bill-section::-webkit-scrollbar {
      width: 8px;
    }

    .product-grid-wrapper::-webkit-scrollbar-track,
    .bill-section::-webkit-scrollbar-track {
      background: #f3f4f6;
      border-radius: 4px;
    }

    .product-grid-wrapper::-webkit-scrollbar-thumb,
    .bill-section::-webkit-scrollbar-thumb {
      background: #d1d5db;
      border-radius: 4px;
    }

    .product-grid-wrapper::-webkit-scrollbar-thumb:hover,
    .bill-section::-webkit-scrollbar-thumb:hover {
      background: #9ca3af;
    }

    /* Responsive Design */
    @media (max-width: 1400px) {
      .container {
        grid-template-columns: 1fr 400px;
      }
    }

    @media (max-width: 1024px) {
      .container {
        grid-template-columns: 1fr;
        gap: 16px;
      }
      
      .bill-section {
        position: relative;
        top: 0;
        height: auto;
        order: -1;
      }
      
      .products-section {
        height: auto;
      }

      .category-tabs {
        flex-wrap: wrap;
        gap: 4px;
      }

      .category-tabs .tab-btn {
        padding: 6px 12px;
        font-size: 13px;
      }
    }

    @media (max-width: 768px) {
      .sidebar {
        width: 100%;
        height: auto;
        position: relative;
        top: auto;
        left: auto;
      }
      
      .layout {
        margin-left: 0;
        margin-top: 0;
        padding: 12px;
      }
      
      .header {
        position: relative;
        height: auto;
        flex-wrap: wrap;
      }
      
      .header-middle {
        order: 3;
        width: 100%;
        margin: 12px 0 0 0;
      }
      
      .search-wrapper {
        min-width: 100%;
      }
      
      .product-grid {
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        gap: 12px;
      }
      
      .discount-vat-section {
        grid-template-columns: 1fr;
      }
      
      .bill-actions {
        flex-direction: column;
      }

      .category-tabs {
        justify-content: flex-start;
      }
    }
    /* ===== Input Group Styling ===== */
.input-group {
  display: flex;
  flex-direction: column;
  margin-bottom: 16px;
  font-family: "Poppins", sans-serif;
}

/* ===== Label ===== */
.input-group label {
  margin-bottom: 6px;
  font-weight: 600;
  color: #333;
  font-size: 14px;
}

/* ===== Select Box ===== */
.input-group select {
  appearance: none;
  -webkit-appearance: none;
  -moz-appearance: none;
  background: #f9f9f9;
  border: 1px solid #ccc;
  border-radius: 8px;
  padding: 10px 14px;
  font-size: 15px;
  color: #333;
  cursor: pointer;
  transition: all 0.2s ease;
  background-image: url("data:image/svg+xml;utf8,<svg fill='gray' height='20' viewBox='0 0 24 24' width='20' xmlns='http://www.w3.org/2000/svg'><path d='M7 10l5 5 5-5z'/></svg>");
  background-repeat: no-repeat;
  background-position: right 12px center;
  background-size: 18px;
}

/* ===== Hover & Focus ===== */
.input-group select:hover {
  border-color: #007bff;
  background-color: #f1f7ff;
}

.input-group select:focus {
  border-color: #007bff;
  outline: none;
  box-shadow: 0 0 4px rgba(0, 123, 255, 0.3);
}

/* ===== Required Field Asterisk ===== */
.input-group label::after {
  content: " *";
  color: #e63946;
}

/* ===== Dark Mode (Optional) ===== */
@media (prefers-color-scheme: dark) {
  .input-group label {
    color: #eee;
  }

  .input-group select {
    background: #2b2b2b;
    border-color: #555;
    color: #f1f1f1;
  }

  .input-group select:hover {
    background-color: #333;
    border-color: #007bff;
  }
}

  </style>
</head>
<body>
  <!-- Header - UNCHANGED -->
  <div class="header">
    <div class="header-left"><img src="logo.jpg" alt="Logo" /></div>
    <div class="header-middle">
      <div class="header-middle-title">Payment Management</div>
      <div class="search-bar"><input id="globalSearch" type="text" placeholder="Search by customer, status or ID..." /></div>
    </div>
    <div class="header-right">
      <button class="role-btn" onclick="window.location.href='../index.html'">Dashboard</button>
      <div class="user-icon"></div>
    </div>
  </div>

  <div class="layout">
    <!-- Sidebar - UNCHANGED -->
    <aside class="sidebar">
      <h1>Payment Dashboard</h1>
      <nav>
        <button class="salesbtn" onclick="window.location.href='index.php'">Payment</button>
        <div class="otherbtn">
         <button class="Sbtn" onclick="window.location.href='../stoke/stock.php'">Stock</button>
         <button class="Ubtn" onclick="window.location.href='../order/order.php'">Order</button>
         <button class="Bbtn" onclick="window.location.href='../booking/index.html'">Booking</button>
        </div>
        <hr />
        <p>Sales Management</p>
        <div class="salebtn">
         <button class="tab-btn active" onclick="window.location.href='index.php'">Bill🧾</button>
         <button class="tab-btn" onclick="window.location.href='save_bill.php'">All Bills</button>
         <button class="tab-btn " onclick="window.location.href='setting.php'">⚙️Setting</button>
        </div>
      </nav>
    </aside>

    <div class="container">
      <!-- Left: Products Section -->
      <div class="products-section">
        <!-- Enhanced Product Filters with Tabs -->
        <div class="product-filters">
          <div class="category-tabs">
            <button class="tab-btn active" data-category="">All Categories</button>
            <?php foreach ($categories as $cat): ?>
              <button class="tab-btn" data-category="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></button>
            <?php endforeach; ?>
          </div>
          
          <div class="search-wrapper">
            <input type="text" id="productSearch" class="search-products" placeholder="🔍 Search products by name or ID..." oninput="searchProducts(this.value)" autocomplete="off">
            <div id="productSuggestions" class="product-suggestions-div"></div>
          </div>
          
          <button class="clear-filters" onclick="clearAllFilters()">Clear Filters</button>
        </div>

        <!-- Product Grid with Scroll -->
        <div class="product-grid-wrapper">
          <div id="productGrid" class="product-grid">
            <?php foreach ($products as $product): ?>
              <?php $img = $product['image'] ? $product['image'] : 'default.jpg'; ?>
              <div class="product-card" data-id="<?php echo $product['id']; ?>" data-category="<?php echo htmlspecialchars($product['category'] ?? ''); ?>" onclick="addToBill(<?php echo $product['id']; ?>, '<?php echo addslashes($product['name']); ?>', <?php echo $product['price']; ?>)">
               
                <?php if ($product['category']): ?>
                  <div class="product-category"><?php echo htmlspecialchars($product['category']); ?></div>
                <?php endif; ?>
                <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                <div class="product-price">Rs. <?php echo number_format($product['price'], 2); ?></div>
              </div>
            <?php endforeach; ?>
          </div>
          <?php if (empty($products)): ?>
            <div class="loading">
              <div style="font-size: 48px; margin-bottom: 16px;">📦</div>
              <p style="font-size: 16px; color: #6b7280;">No products available. Add products to get started.</p>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Right: Bill Section -->
      <div class="bill-section">
        <div class="bill-header">
          <h1>🧾 New Bill</h1>
          <span class="bill-count" id="billCount">0 items</span>
        </div>
        
        <?php if ($successMessage): ?>
          <div class="message success">✅ <?php echo htmlspecialchars($successMessage); ?></div>
        <?php endif; ?>
        <?php if ($errorMessage): ?>
          <div class="message error">❌ <?php echo htmlspecialchars($errorMessage); ?></div>
        <?php endif; ?>

        <form method="POST" action="index.php" id="billForm">
          <!-- Customer Section -->
          <div class="input-group">
            <label for="customerInput">Customer Name *</label>
            <input type="text" id="customerInput" name="customer_name" class="customer-input" placeholder="Enter customer name..." required>
            <small class="temp-customer">💡 Enter customer name. New customers will be saved automatically.</small>
            <input type="hidden" id="selectedCustomerId" name="customer_id">
          </div>

          <!-- Payment Method -->
          <div class="input-group">
            <label for="paymentMethod">Payment Method *</label>
            <select name="payment_method" id="paymentMethod" required>
              <option value="">Select Payment Method</option>
              <option value="Cash">💵 Cash</option>
              <option value="Card">💳 Card</option>
              <option value="Online">🌐 Online Transfer</option>
              <option value="UPI">📱 UPI</option>
            </select>
          </div>

          <!-- Discount and VAT Section -->
          <div class="discount-vat-section">
            <div class="input-group">
              <label for="discount">Discount (Rs.)</label>
              <input type="number" id="discount" name="discount" value="0" step="0.01" min="0" onchange="calculateTotals()">
            </div>
            <div class="input-group">
              <label for="vatPercent">VAT (%)</label>
              <input type="number" id="vatPercent" name="vat_percent" value="8" step="0.01" min="0" max="100" onchange="calculateTotals()">
            </div>
          </div>

          <!-- Bill Items Section -->
          <div class="bill-items-header">
            <h3>Bill Items</h3>
            <span class="items-count" id="itemsCount">0 items</span>
          </div>

          <table id="itemsTable">
            <thead>
              <tr>
                <th>Item</th>
                <th style="text-align: center;">Price</th>
                <th style="text-align: center;">Qty</th>
                <th style="text-align: right;">Total</th>
                <th style="text-align: center;">Action</th>
              </tr>
            </thead>
            <tbody id="itemsTableBody">
              <tr>
                <td colspan="5" class="empty-bill">
                  <div class="empty-bill-icon">🛒</div>
                  <div>No items added yet</div>
                  <div style="font-size: 12px; margin-top: 8px;">Click a product to add it to the bill</div>
                </td>
              </tr>
            </tbody>
          </table>

          <!-- Totals Box -->
          <div class="totals-box">
            <div class="total-row">
              <span class="total-label">Subtotal:</span>
              <span class="total-value">Rs. <span id="subtotal">0.00</span></span>
            </div>
            <div class="total-row">
              <span class="total-label">Discount:</span>
              <span class="total-value">- Rs. <span id="discountAmount">0.00</span></span>
            </div>
            <div class="total-row">
              <span class="total-label">VAT (<span id="vatPercentDisplay">8</span>%):</span>
              <span class="total-value">+ Rs. <span id="vat">0.00</span></span>
            </div>
            <div class="total-row grand">
              <span class="total-label">Grand Total:</span>
              <span class="total-value">Rs. <span id="grandtotal">0.00</span></span>
            </div>
          </div>

          <input type="hidden" name="bill_items" id="billItemsData">
          
          <div class="bill-actions">
            <button type="button" class="btn btn-clear" onclick="clearBill()">
              🗑️ Clear Bill
            </button>
            <button type="submit" class="btn btn-save" id="saveBillBtn">
              💾 Save Bill
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    let billItems = [];
    let searchTimeout = null;
    let currentCategory = '';

    // Tab functionality
    document.addEventListener('DOMContentLoaded', () => {
      document.querySelectorAll('.category-tabs .tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
          document.querySelectorAll('.category-tabs .tab-btn').forEach(b => b.classList.remove('active'));
          btn.classList.add('active');
          currentCategory = btn.dataset.category || '';
          applyFilters();
          // Reset search if switching tabs
          const searchInput = document.getElementById('productSearch');
          if (searchInput.value) {
            searchInput.value = '';
            document.getElementById('productSuggestions').style.display = 'none';
          }
        });
      });
    });

    // Add to Bill with Animation Feedback
    function addToBill(id, name, price) {
      const existing = billItems.find(item => item.id === id);
      if (existing) {
        existing.qty += 1;
        showNotification(`Added another ${name}`, 'success');
      } else {
        billItems.push({id, name, price, qty: 1});
        showNotification(`${name} added to bill`, 'success');
      }
      renderBillTable();
      calculateTotals();
      updateBillCount();
      
      // Visual feedback on product card
      const card = document.querySelector(`.product-card[data-id="${id}"]`);
      if (card) {
        card.style.transform = 'scale(0.95)';
        setTimeout(() => {
          card.style.transform = '';
        }, 150);
      }
    }

    // Render Bill Table with Enhanced UI
    function renderBillTable() {
      const tbody = document.getElementById('itemsTableBody');
      
      if (billItems.length === 0) {
        tbody.innerHTML = `
          <tr>
            <td colspan="5" class="empty-bill">
              <div class="empty-bill-icon">🛒</div>
              <div>No items added yet</div>
              <div style="font-size: 12px; margin-top: 8px;">Click a product to add it to the bill</div>
            </td>
          </tr>
        `;
        return;
      }

      let rows = '';
      billItems.forEach((item, index) => {
        const total = (item.price * item.qty).toFixed(2);
        rows += `
          <tr>
            <td style="word-break: break-word;"><strong>${item.name}</strong></td>
            <td style="text-align: center;">Rs. ${item.price.toFixed(2)}</td>
            <td style="text-align: center;">
              <input type="number" class="qty-input" value="${item.qty}" min="1" max="9999" onchange="updateQty(${index}, this.value)">
            </td>
            <td class="item-total">Rs. ${total}</td>
            <td style="text-align: center;">
              <span class="remove-item" onclick="removeItem(${index}, '${item.name.replace(/'/g, "\\'")}')">Remove</span>
            </td>
          </tr>
        `;
      });
      tbody.innerHTML = rows;
    }

    function updateQty(index, qty) {
      const newQty = parseInt(qty) || 1;
      if (newQty < 1) {
        showNotification('Quantity must be at least 1', 'error');
        billItems[index].qty = 1;
      } else if (newQty > 9999) {
        showNotification('Quantity cannot exceed 9999', 'error');
        billItems[index].qty = 9999;
      } else {
        billItems[index].qty = newQty;
      }
      renderBillTable();
      calculateTotals();
      updateBillCount();
    }

    function removeItem(index, name) {
      if (confirm(`Remove ${name} from bill?`)) {
        billItems.splice(index, 1);
        renderBillTable();
        calculateTotals();
        updateBillCount();
        showNotification(`${name} removed from bill`, 'info');
      }
    }

    // Calculate Totals
    function calculateTotals() {
      const subtotal = billItems.reduce((sum, item) => sum + (item.price * item.qty), 0);
      const discount = parseFloat(document.getElementById('discount').value) || 0;
      const vatPercent = parseFloat(document.getElementById('vatPercent').value) || 0;
      const vatAmount = ((subtotal - discount) * (vatPercent / 100));
      const grandTotal = (subtotal - discount + vatAmount);

      document.getElementById('subtotal').textContent = subtotal.toFixed(2);
      document.getElementById('discountAmount').textContent = discount.toFixed(2);
      document.getElementById('vat').textContent = vatAmount.toFixed(2);
      document.getElementById('grandtotal').textContent = grandTotal.toFixed(2);
      document.getElementById('vatPercentDisplay').textContent = vatPercent.toFixed(1);

      document.getElementById('billItemsData').value = JSON.stringify(billItems);
      
      // Enable/disable save button
      const saveBtn = document.getElementById('saveBillBtn');
      if (billItems.length === 0) {
        saveBtn.disabled = true;
        saveBtn.style.opacity = '0.5';
      } else {
        saveBtn.disabled = false;
        saveBtn.style.opacity = '1';
      }
    }

    function updateBillCount() {
      const totalItems = billItems.reduce((sum, item) => sum + item.qty, 0);
      document.getElementById('billCount').textContent = `${totalItems} item${totalItems !== 1 ? 's' : ''}`;
      document.getElementById('itemsCount').textContent = `${billItems.length} item${billItems.length !== 1 ? 's' : ''}`;
    }

    function clearBill() {
      if (billItems.length === 0) {
        showNotification('Bill is already empty', 'info');
        return;
      }
      
      if (confirm('Are you sure you want to clear the entire bill?')) {
        billItems = [];
        renderBillTable();
        calculateTotals();
        updateBillCount();
        document.getElementById('customerInput').value = '';
        document.getElementById('selectedCustomerId').value = '';
        document.getElementById('paymentMethod').value = '';
        document.getElementById('discount').value = 0;
        document.getElementById('vatPercent').value = 8;
        showNotification('Bill cleared successfully', 'success');
      }
    }

    // Product Search with AJAX Suggestions
    function searchProducts(query) {
      const suggestionsDiv = document.getElementById('productSuggestions');
      
      if (query.length < 2) {
        suggestionsDiv.innerHTML = '';
        suggestionsDiv.style.display = 'none';
        applyFilters();
        return;
      }

      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => {
        fetch(`index.php?action=search_products&q=${encodeURIComponent(query)}`)
          .then(response => response.json())
          .then(data => {
            suggestionsDiv.innerHTML = '';
            if (data.length > 0) {
              data.forEach(product => {
                const div = document.createElement('div');
                div.className = 'suggestion-item';
                div.innerHTML = `
                  <span class="suggestion-name">${product.name}</span>
                  <span class="suggestion-price">Rs. ${parseFloat(product.price).toFixed(2)}</span>
                `;
                div.onclick = () => {
                  addToBill(product.id, product.name, product.price);
                  suggestionsDiv.style.display = 'none';
                  document.getElementById('productSearch').value = '';
                  applyFilters();
                };
                suggestionsDiv.appendChild(div);
              });
              suggestionsDiv.style.display = 'block';
            } else {
              suggestionsDiv.innerHTML = '<div class="suggestion-item" style="color: #9ca3af; cursor: default;">No products found</div>';
              suggestionsDiv.style.display = 'block';
            }
            applyFilters();
          })
          .catch(() => {
            suggestionsDiv.style.display = 'none';
            applyFilters();
          });
      }, 300);
    }

    // Apply Filters (Category + Search)
    function applyFilters() {
      const category = currentCategory.toLowerCase();
      const searchQuery = document.getElementById('productSearch').value.toLowerCase();
      const cards = document.querySelectorAll('.product-card');
      const grid = document.getElementById('productGrid');
      
      // Show loading state briefly
      grid.classList.add('loading');
      
      setTimeout(() => {
        let visibleCount = 0;
        cards.forEach((card, index) => {
          const cardCategory = (card.dataset.category || '').toLowerCase();
          const cardName = card.querySelector('.product-name').textContent.toLowerCase();
          const cardId = card.dataset.id;
          
          const categoryMatch = !category || cardCategory === category;
          const searchMatch = !searchQuery || cardName.includes(searchQuery) || cardId.includes(searchQuery);
          
          if (categoryMatch && searchMatch) {
            card.style.display = 'block';
            visibleCount++;
            // Reset animation for visible cards
            card.style.animation = 'none';
            setTimeout(() => {
              card.style.animation = `fadeInUp 0.3s ease forwards`;
              card.style.animationDelay = `${index * 0.05}s`;
            }, 10);
          } else {
            card.style.display = 'none';
          }
        });
        
        grid.classList.remove('loading');
        
        // Show no results message if needed
        let noResultsMsg = document.getElementById('noResultsMsg');
        
        if (visibleCount === 0 && cards.length > 0) {
          if (!noResultsMsg) {
            noResultsMsg = document.createElement('div');
            noResultsMsg.id = 'noResultsMsg';
            noResultsMsg.className = 'loading';
            noResultsMsg.innerHTML = `
              <div style="font-size: 48px; margin-bottom: 16px;">🔍</div>
              <p style="font-size: 16px; color: #6b7280;">No products match your filters</p>
              <p style="font-size: 14px; color: #9ca3af; margin-top: 8px;">Try adjusting your search or filters</p>
            `;
            grid.appendChild(noResultsMsg);
          }
        } else if (noResultsMsg) {
          noResultsMsg.remove();
        }
      }, 150);
    }

    function clearAllFilters() {
      currentCategory = '';
      document.querySelector('.category-tabs .tab-btn[data-category=""]').classList.add('active');
      document.querySelectorAll('.category-tabs .tab-btn').forEach(b => {
        if (b.dataset.category !== '') b.classList.remove('active');
      });
      document.getElementById('productSearch').value = '';
      document.getElementById('productSuggestions').style.display = 'none';
      applyFilters();
      showNotification('Filters cleared', 'info');
    }

    // Notification System
    function showNotification(message, type = 'info') {
      const notification = document.createElement('div');
      notification.style.cssText = `
        position: fixed;
        top: 90px;
        right: 20px;
        background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
        color: white;
        padding: 12px 20px;
        border-radius: 8px;
        font-weight: 600;
        z-index: 10000;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        animation: slideInRight 0.3s ease;
      `;
      notification.textContent = message;
      document.body.appendChild(notification);
      
      setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => notification.remove(), 300);
      }, 3000);
    }

    // Add CSS for animations
    const style = document.createElement('style');
    style.textContent = `
      @keyframes slideInRight {
        from {
          opacity: 0;
          transform: translateX(100px);
        }
        to {
          opacity: 1;
          transform: translateX(0);
        }
      }
      @keyframes slideOutRight {
        from {
          opacity: 1;
          transform: translateX(0);
        }
        to {
          opacity: 0;
          transform: translateX(100px);
        }
      }
    `;
    document.head.appendChild(style);

    // Close suggestions when clicking outside
    document.addEventListener('click', (e) => {
      const suggestionsDiv = document.getElementById('productSuggestions');
      const searchInput = document.getElementById('productSearch');
      if (e.target !== searchInput && !suggestionsDiv.contains(e.target)) {
        suggestionsDiv.style.display = 'none';
      }
    });

    // Form submission validation
    document.getElementById('billForm').addEventListener('submit', (e) => {
      if (billItems.length === 0) {
        e.preventDefault();
        showNotification('Please add at least one item to the bill', 'error');
        return false;
      }
      
      const customerName = document.getElementById('customerInput').value.trim();
      const paymentMethod = document.getElementById('paymentMethod').value;
      
      if (!customerName) {
        e.preventDefault();
        showNotification('Please enter customer name', 'error');
        return false;
      }
      
      if (!paymentMethod) {
        e.preventDefault();
        showNotification('Please select a payment method', 'error');
        return false;
      }
      
      // Show loading state
      const saveBtn = document.getElementById('saveBillBtn');
      saveBtn.innerHTML = '<div class="spinner" style="width: 20px; height: 20px; margin: 0 auto;"></div> Saving...';
      saveBtn.disabled = true;
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', (e) => {
      // Ctrl/Cmd + K to focus search
      if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        document.getElementById('productSearch').focus();
      }
      
      // Escape to clear search/suggestions
      if (e.key === 'Escape') {
        document.getElementById('productSearch').value = '';
        document.getElementById('productSuggestions').style.display = 'none';
        applyFilters();
      }
    });

    // Initialize
    renderBillTable();
    calculateTotals();
    updateBillCount();
    
    // Add tooltip to search
    document.getElementById('productSearch').title = 'Press Ctrl+K to focus search';
  </script>
</body>
</html>