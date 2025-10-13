<?php
// ---------- DB CONNECTION ----------
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "golden_treat";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ---------- Ensure status column exists (run once if needed) ----------
// Uncomment the next line if status column doesn't exist yet
// $conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS status ENUM('pending', 'confirmed', 'shipped', 'delivered', 'cancelled') NOT NULL DEFAULT 'pending' AFTER total_amount");

// ---------- Initialize message ----------
$message = "";

// ---------- Handle form submissions ----------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        // ADD ORDER
        if ($_POST['action'] == 'add') {
            $customerName = $_POST['fCustomerName'] ?? '';
            $customerEmail = $_POST['fCustomerEmail'] ?? '';
            $customerPhone = $_POST['fCustomerPhone'] ?? '';
            $totalAmount = (float)($_POST['fTotalAmount'] ?? 0);
            $userId = !empty($_POST['fUserId']) ? (int)$_POST['fUserId'] : null;
            $sessionId = $_POST['fSessionId'] ?? '';
            $status = $_POST['fStatus'] ?? 'pending';

            // Generate order_number if empty
            $orderNumber = $_POST['fOrderNumber'] ?? '';
            if (empty($orderNumber)) {
                $orderNumber = 'GT-' . date('Ymd') . '-' . str_pad(rand(1,999), 3, '0', STR_PAD_LEFT);
            }

            if (empty($customerName) || empty($customerEmail) || $totalAmount <= 0) {
                $message = "⚠ Please fill required fields (Name, Email, Amount).";
            } elseif (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
                $message = "⚠ Please enter a valid email.";
            } else {
                // Check if order_number exists
                $sql = "SELECT id FROM orders WHERE order_number = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("s", $orderNumber);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $message = "⚠ Order number already exists.";
                } else {
                    $userIdParam = $userId ? $userId : null;
                    $sql = "INSERT INTO orders (order_number, customer_name, customer_email, customer_phone, total_amount, user_id, session_id, status) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    if ($stmt === false) {
                        $message = "❌ Prepare failed: " . $conn->error;
                    } else {
                        $stmt->bind_param("sssidsis", $orderNumber, $customerName, $customerEmail, $customerPhone, $totalAmount, $userIdParam, $sessionId, $status);
                        try {
                            $stmt->execute();
                            $message = "✅ Order added successfully.";
                        } catch (mysqli_sql_exception $e) {
                            $message = "❌ Insert failed: " . $e->getMessage();
                        }
                        $stmt->close();
                    }
                }
            }

        // UPDATE ORDER
        } elseif ($_POST['action'] == 'update') {
            $id = (int)$_POST['fId'];
            $orderNumber = mysqli_real_escape_string($conn, $_POST['fOrderNumber']);
            $customerName = mysqli_real_escape_string($conn, $_POST['fCustomerName']);
            $customerEmail = mysqli_real_escape_string($conn, $_POST['fCustomerEmail']);
            $customerPhone = mysqli_real_escape_string($conn, $_POST['fCustomerPhone']);
            $totalAmount = (float)$_POST['fTotalAmount'];
            $userId = !empty($_POST['fUserId']) ? (int)$_POST['fUserId'] : null;
            $sessionId = mysqli_real_escape_string($conn, $_POST['fSessionId']);
            $status = mysqli_real_escape_string($conn, $_POST['fStatus']);

            if (empty($customerName) || empty($customerEmail) || $totalAmount <= 0) {
                $message = "⚠ Please fill required fields (Name, Email, Amount).";
            } elseif (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
                $message = "⚠ Please enter a valid email.";
            } else {
                $userIdParam = $userId ? $userId : null;
                $sql = "UPDATE orders SET 
                        order_number='$orderNumber', 
                        customer_name='$customerName', 
                        customer_email='$customerEmail', 
                        customer_phone='$customerPhone', 
                        total_amount=$totalAmount, 
                        user_id=" . ($userId ? "'$userId'" : "NULL") . ", 
                        session_id='$sessionId', 
                        status='$status'
                        WHERE id=$id";
                if ($conn->query($sql) === TRUE) {
                    $message = "✅ Order updated successfully.";
                } else {
                    $message = "❌ Error: " . $conn->error;
                }
            }

        // DELETE ORDER
        } elseif ($_POST['action'] == 'delete') {
            $id = (int)$_POST['id'];
            if ($id > 0) {
                $conn->begin_transaction();
                try {
                    // Disable foreign key checks if any related tables
                    $conn->query("SET FOREIGN_KEY_CHECKS = 0");

                    // Delete the order
                    $sql = "DELETE FROM orders WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    if ($stmt === false) {
                        throw new Exception("Prepare failed: " . $conn->error);
                    }
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $stmt->close();

                    // Re-enable foreign key checks
                    $conn->query("SET FOREIGN_KEY_CHECKS = 1");

                    $conn->commit();
                    $message = "🗑 Order deleted successfully.";
                } catch (Exception $e) {
                    $conn->rollback();
                    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
                    $message = "❌ Error: " . $e->getMessage();
                }
            } else {
                $message = "❌ Invalid order ID.";
            }

        // EXPORT CSV
        } elseif ($_POST['action'] == 'export_csv') {
            $from = mysqli_real_escape_string($conn, $_POST['expFrom']);
            $to = mysqli_real_escape_string($conn, $_POST['expTo']);
            $status = mysqli_real_escape_string($conn, $_POST['expStatus']);
            $searchTerm = mysqli_real_escape_string($conn, $_POST['expSearch']);

            $conditions = [];
            if ($from) $conditions[] = "DATE(created_at) >= '$from'";
            if ($to) $conditions[] = "DATE(created_at) <= '$to'";
            if ($status) $conditions[] = "status = '$status'";
            if ($searchTerm) $conditions[] = "(order_number LIKE '%$searchTerm%' OR customer_name LIKE '%$searchTerm%' OR customer_email LIKE '%$searchTerm%')";
            $where = $conditions ? "WHERE " . implode(" AND ", $conditions) : "";

            $sql = "SELECT id, order_number, customer_name, customer_email, customer_phone, total_amount, status, created_at 
                    FROM orders $where";
            $result = $conn->query($sql);

            header('Content-Type: text/csv');
            header('Content-Disposition: attachment;filename=orders_export.csv');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Order ID', 'Order Number', 'Customer Name', 'Email', 'Phone', 'Total Amount', 'Status', 'Created At']);
            while ($row = $result->fetch_assoc()) {
                fputcsv($output, $row);
            }
            fclose($output);
            exit();
        }
    }
}

// ---------- Handle filters and sorting ----------
$fromDate = isset($_GET['filterFrom']) ? mysqli_real_escape_string($conn, $_GET['filterFrom']) : '';
$toDate = isset($_GET['filterTo']) ? mysqli_real_escape_string($conn, $_GET['filterTo']) : '';
$statusFilter = isset($_GET['filterStatus']) ? mysqli_real_escape_string($conn, $_GET['filterStatus']) : '';
$search = isset($_GET['globalSearch']) ? mysqli_real_escape_string($conn, $_GET['globalSearch']) : '';
$sortKey = isset($_GET['sort']) ? mysqli_real_escape_string($conn, $_GET['sort']) : 'created_at';
$sortDir = isset($_GET['dir']) && $_GET['dir'] === 'asc' ? 'ASC' : 'DESC';

$conditions = [];
if ($fromDate) $conditions[] = "DATE(created_at) >= '$fromDate'";
if ($toDate) $conditions[] = "DATE(created_at) <= '$toDate'";
if ($statusFilter) $conditions[] = "status = '$statusFilter'";
if ($search) {
    $conditions[] = "(id LIKE '%$search%' 
                   OR order_number LIKE '%$search%' 
                   OR customer_name LIKE '%$search%' 
                   OR customer_email LIKE '%$search%' 
                   OR customer_phone LIKE '%$search%')";
}
$where = $conditions ? "WHERE " . implode(" AND ", $conditions) : "";

$sql = "SELECT id, order_number, customer_name, customer_email, customer_phone, total_amount, status, created_at 
        FROM orders $where ORDER BY $sortKey $sortDir";
$result = $conn->query($sql);

$orders = [];
while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}

// ---------- Calculate card metrics ----------
$totalOrders = count($orders);
$pendingOrders = count(array_filter($orders, fn($r) => $r['status'] === 'pending'));
$totalRevenue = array_sum(array_map(fn($r) => (float)$r['total_amount'], $orders));
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Order Management - Golden Treat</title>
  <link rel="stylesheet" href="style1.css">
   <style>
    .addform { display: none; position: fixed; top:0; left:0; width:100%; height:100%; background: rgba(0,0,0,0.5); z-index:1000; align-items: center; justify-content: center; }
    .addform-content { background: #fff; padding:20px; border-radius:8px; width:90%; max-width:500px; max-height:90vh; overflow-y:auto; position:relative; }
    .close { position: absolute; top:10px; right:15px; font-size:24px; cursor:pointer; }
    #suggestions { position: absolute; background: #fff; border:1px solid #ccc; z-index:2000; max-height:150px; overflow-y:auto; width: calc(100% - 40px); }
    #suggestions div { padding:8px; cursor:pointer; }
    #suggestions div:hover { background:#f0f8ff; }
    .error-alert { position: fixed; top:20px; right:20px; background:#ffdddd; padding:10px; border:1px solid red; border-radius:4px; }
    .badge { padding:4px 8px; border-radius:4px; color:#fff; }
    .Paid { background: #4CAF50; }
    .Pending { background: #ff9800; }
    .Cancelled { background: #f44336; }
    .Returned { background: #607D8B; }
    :root {
  --brand: #30b6a2;
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
  overflow-x: hidden; /* Prevent horizontal scroll */
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

  z-index: 1000; /* High z-index to stay on top */
  height: 70px; /* Fixed height for consistency */
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
  top: 70px; /* Below header height */
  left: 0;
  width: 260px;
  height: calc(100vh - 70px); /* Full height minus header */
  background: #fff;
  padding: 18px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  border-right: 1px solid #e5e7eb;
  overflow-y: auto; /* Allow scroll inside sidebar if needed, but header/sidebar fixed */
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
  background: #30b6a2;
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

.salebtn .tab-btn + .tab-btn {
  margin-top: 8px;
}

.salebtn .tab-btn.active {
  outline: 3px solid rgba(48, 182, 162, .35);
  background: #fff;
  color: var(--brand);
}

/* Main Content Area - Adjusted for Fixed Elements */
.layout {
  margin-top: 70px; /* Space for header */
  margin-left: 260px; /* Space for sidebar */
  flex: 1;
  min-height: calc(100vh - 70px);
  display: flex;
  flex-direction: column;
}

.free-area {
  flex: 1;
  background: #f3f4f6;
  padding: 24px;
  overflow: auto; /* Main content scrolls */
  min-height: 100%;
}

/* Rest of the CSS remains the same */
.panel {
  display: none;
}

.panel.active {
  display: block;
}

.content {
  background: #30b6a2;
  border-radius: 14px;
  padding: 18px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, .08);
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.contents {
  background: #e7ebea;
  border-radius: 14px;
  padding: 18px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, .08);
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.cards {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
  
}


.card {
  background: #fff;
  border-radius: 12px;
  padding: 16px;
  box-shadow: 0 1px 6px rgba(0, 0, 0, .06);
  text-align: center;
}

.card h3 {
  font-size: 14px;
  color: #374151;
}

.card p {
  font-size: 22px;
  font-weight: 800;
  margin-top: 6px;
  color: #0f172a;
}

.toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  align-items: center;
}

.filter-bar {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.filter-bar input,
.filter-bar select,
.filter-bar button {
  padding: 8px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  background: #fff;
}

.btn {
  padding: 9px 12px;
  border: none;
  border-radius: 10px;
  cursor: pointer;
  color: #fff;
  background: var(--primary);
}

.btn.secondary {
  background: #000000;
}

.btn.warn {
  background: var(--warn);
  color: #000;
}

.btn.danger {
  background: var(--danger);
}

.btn.light {
  background: #e5e7eb;
  color: #111827;
  border: 1px solid #d1d5db;
}

.table-wrap {
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 1px 6px rgba(0, 0, 0, .06);
  overflow: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
}

th,
td {
  padding: 12px;
  border-bottom: 1px solid #e5e7eb;
  text-align: left;
  white-space: nowrap;
  color: #000000ff;
}

th {
  background: #f9fafb;
  font-size: 13px;
  color: #000000ff;
  cursor: pointer;
  position: sticky;
  top: 0;
}

tr:hover td {
  background: #30b6a283;
}

.badge {
  padding: 4px 8px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
}

.Completed {
  background: #d1fae5;
  color: #065f46;
}

.Pending {
  background: #fef3c7;
  color: #92400e;
}

.Cancelled {
  background: #fee2e2;
  color: #991b1b;
}

.row-actions button {
  padding: 6px 10px;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  gap:30px;
}

.row-actions .edit {
  background: var(--warn);
}

.row-actions .del {
  background: var(--danger);
  color: #fff;
}

/* Modal */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, .35);
  display: none;
  align-items: center;
  justify-content: center;
  z-index: 50;
}

.modal {
  width: 100%;
  max-width: 520px;
  background: #fff;
  border-radius: 14px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, .25);
  padding: 18px;
}

.modal h2 {
  margin-bottom: 10px;
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.form-grid .full {
  grid-column: 1/-1;
}

.modal input,
.modal select {
  width: 100%;
  padding: 10px;
  border: 1px solid #d1d5db;
  border-radius: 10px;
}

.modal .footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 12px;
}

/* Analysis */
.chart-card {
  background: #fff;
  border-radius: 14px;
  padding: 16px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, .08);
}

.analysis-controls {
  background: #fff;
  border-radius: 14px;
  padding: 12px;
  box-shadow: 0 1px 6px rgba(0, 0, 0, .06);
  margin-bottom: 12px;
}

.analysis-controls .filter-row {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  align-items: center;
}

.analysis-controls label {
  font-size: 12px;
  color: #374151;
}

.mini-cards {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
  margin: 12px 0;
}

.mini-card {
  background: #fff;
  border-radius: 12px;
  padding: 14px;
  text-align: center;
  box-shadow: 0 1px 6px rgba(0, 0, 0, .06);
}

.mini-card h4 {
  margin-bottom: 6px;
  font-size: 12px;
  color: #374151;
}

.mini-card p {
  font-size: 20px;
  font-weight: 800;
  color: #0f172a;
}

.charts-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.chart-wrap {
  position: relative;
  height: 320px;
}

/* Export panel */
.export-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 12px;
}

.export-grid .row {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.muted {
  color: var(--muted);
  font-size: 13px;
}

@media (max-width: 1000px) {
  .cards {
    grid-template-columns: 1fr;
  }

  .charts-grid {
    grid-template-columns: 1fr;
  }

  .sidebar {
    width: 220px;
  }

  .layout {
    margin-left: 220px; /* Adjust for smaller sidebar */
  }
}

/* Additional styles from provided CSS */
.card {
  background: rgba(0, 0, 0, 0.452);
  backdrop-filter: blur(12px);
  border-radius: 20px;
  padding: 25px;
  text-align: center;
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
  transition: transform 0.3s, box-shadow 0.3s;
}

.card:hover {
  transform: translateY(-5px);
  box-shadow: 0 12px 30px rgba(0, 0, 0, 0.4);
}

.card h3 {
  font-size: 1.5rem;
  margin-bottom: 10px;
  color: #ffcc00;
}

.card p {
  font-size: 2.1rem;
  font-weight: bold;
  margin: 10px 0;
}

.info-btn {
  margin-top: 10px;
  padding: 10px 15px;
  border: none;
  border-radius: 12px;
  background: #ffdd57;
  color: #333;
  font-weight: bold;
  cursor: pointer;
  transition: all 0.3s;
}

.info-btn:hover {
  background: #ffd633;
  transform: scale(1.05);
}

/* POPUP STYLES */
.popup {
  display: none;
  position: fixed;
  z-index: 1000;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(8px);
  align-items: center;
  justify-content: center;
}

.popup-content {
  background: rgba(255, 255, 255, 0.15);
  backdrop-filter: blur(20px);
  padding: 30px;
  border-radius: 20px;
  width: 400px;
  max-width: 90%;
  color: #fff;
  text-align: left;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
  animation: fadeIn 0.3s ease-in-out;
}

.popup-content h2 {
  margin-top: 0;
  font-size: 1.6rem;
  color: #ffdd57;
}

.popup-content ul {
  margin: 15px 0;
  padding-left: 20px;
}

.popup-content li {
  margin: 8px 0;
}

.close {
  float: right;
  font-size: 1.5rem;
  cursor: pointer;
  color: #fff;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: scale(0.9);
  }
  to {
    opacity: 1;
    transform: scale(1);
  }
}

.addform {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.addform-content {
  background: #fff;
  padding: 20px;
  align-items: center;
  justify-content: center;
  border: 3px solid #30b6a2;
  border-radius: 15px;
  width: 350px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
  position: relative;
  animation: fadeIn 0.3s ease-in-out;
}

.close {
  position: absolute;
  top: 10px;
  right: 15px;
  font-size: 20px;
  color: red;
  cursor: pointer;
}

.addform-content form label {
  display: block;
  margin: 10px 0 5px;
  font-weight: bold;
}

.addform-content form input,
.addform-content form select,
.addform-content form button {
  width: 100%;
  padding: 8px;
  margin-bottom: 12px;
  border: 1px solid #ccc;
  border-radius: 6px;
}

.addform-content form button {
  background: #30b6a2;
  color: white;
  font-weight: bold;
  cursor: pointer;
}

.addform-content form button:hover {
  background: #30b6a2;
}

.tab-btn {
  cursor: pointer;
  padding: 10px;
  margin: 5px 0;
  width: 100%;
  text-align: left;
  border: none;
  background: #f0f0f0;
}

.tab-btn.active {
  background: #2563eb;
  color: white;
}

.chart-wrap {
  width: 100%;
  height: 300px;
}
  </style>
</head>
<body>
  <?php if ($message): ?>
    <div class="message <?php echo strpos($message, 'Error') !== false ? 'error' : ''; ?>">
      <?php echo htmlspecialchars($message); ?>
    </div>
  <?php endif; ?>

  <!-- Header -->
  <div class="header">
    <div class="header-left"><img src="logo.jpg" alt="Logo" /></div>
    <div class="header-middle">
      <div class="header-middle-title">Order Management</div>
      <div class="search-bar"><input id="globalSearch" type="text" placeholder="Search by order number, customer or email..." />
      </div>
    </div>
    <div class="header-right">
      <button class="role-btn" onclick="window.location.href='../index.html'">Dashboard</button>
      <div class="user-icon"></div>
    </div>
  </div>

  <div class="layout">
    <!-- Sidebar -->
    <aside class="sidebar">
      <h1>Order Dashboard</h1>
      <nav>
        <button class="salesbtn" onclick="window.location.href='index.php'">User</button>
        <div class="otherbtn">
          <button class="Sbtn" onclick="window.location.href='../sales/index.php'">Sales</button>
          <button class="Ubtn" onclick="window.location.href='order_management.php'">Order</button>
          <button class="Bbtn" onclick="window.location.href='../booking/index.html'">Booking</button>
        </div>
        <hr />
        <p>Order Management</p>
        <div class="salebtn">
          <button class="tab-btn active" onclick="window.location.href='order_management.php'">Order Dashboard</button>
          <button class="tab-btn" onclick="window.location.href='../orders_log.php'">Order SUM</button>
          <button class="tab-btn" onclick="window.location.href='order_analysis.php'">Order Analysis</button>
        </div>
      </nav>
    </aside>

    <main class="free-area">
      <section id="order-dashboard" class="panel active">
        <div class="content">
          <h1>Order Management</h1>
          <div class="cards">
            <div class="card">
              <h3>Total Orders</h3>
              <p><?php echo $totalOrders; ?></p>
            </div>
            <div class="card">
              <h3>Pending Orders</h3>
              <p><?php echo $pendingOrders; ?></p>
            </div>
            <div class="card">
              <h3>Total Revenue</h3>
              <p>Rs. <?php echo number_format($totalRevenue, 2); ?></p>
            </div>
          </div>
          <div class="toolbar">
            <form class="filter-bar" method="GET" action="">
              <input type="date" id="filterFrom" name="filterFrom" value="<?php echo htmlspecialchars($fromDate); ?>" />
              <input type="date" id="filterTo" name="filterTo" value="<?php echo htmlspecialchars($toDate); ?>" />
              <select id="filterStatus" name="filterStatus">
                <option value="">All Status</option>
                <option <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option <?php echo $statusFilter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                <option <?php echo $statusFilter === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                <option <?php echo $statusFilter === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                <option <?php echo $statusFilter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
              </select>
              <button class="btn light" type="submit">Filter</button>
              <button class="btn light" type="button" onclick="window.location.href='?panel=order-dashboard'">Reset</button>
              <input type="hidden" name="panel" value="order-dashboard">
            </form>
            <div style="flex:1"></div>
            <button class="btn secondary" onclick="window.location.href='?panel=order-dashboard&modal=add'">➕ Add Order</button>
            <form method="POST" action="">
              <input type="hidden" name="action" value="export_csv">
              <input type="date" name="expFrom" />
              <input type="date" name="expTo" />
              <select name="expStatus"><option value="">All</option><option>Pending</option><option>Confirmed</option><option>Shipped</option><option>Delivered</option><option>Cancelled</option></select>
              <input type="text" name="expSearch" placeholder="Search" />
              <button class="btn" type="submit">⬇ CSV</button>
            </form>
          </div>
          <div class="table-wrap">
            <table id="orderTable">
              <thead>
                <tr>
                  <th><a href="?sort=id&dir=<?php echo $sortKey === 'id' && $sortDir === 'ASC' ? 'desc' : 'asc'; ?>&panel=order-dashboard">Order ID ▲▼</a></th>
                  <th><a href="?sort=order_number&dir=<?php echo $sortKey === 'order_number' && $sortDir === 'ASC' ? 'desc' : 'asc'; ?>&panel=order-dashboard">Order Number ▲▼</a></th>
                  <th><a href="?sort=customer_name&dir=<?php echo $sortKey === 'customer_name' && $sortDir === 'ASC' ? 'desc' : 'asc'; ?>&panel=order-dashboard">Customer Name ▲▼</a></th>
                  <th><a href="?sort=customer_email&dir=<?php echo $sortKey === 'customer_email' && $sortDir === 'ASC' ? 'desc' : 'asc'; ?>&panel=order-dashboard">Email ▲▼</a></th>
                  <th><a href="?sort=customer_phone&dir=<?php echo $sortKey === 'customer_phone' && $sortDir === 'ASC' ? 'desc' : 'asc'; ?>&panel=order-dashboard">Phone ▲▼</a></th>
                  <th><a href="?sort=total_amount&dir=<?php echo $sortKey === 'total_amount' && $sortDir === 'ASC' ? 'desc' : 'asc'; ?>&panel=order-dashboard">Total Amount ▲▼</a></th>
                  <th><a href="?sort=status&dir=<?php echo $sortKey === 'status' && $sortDir === 'ASC' ? 'desc' : 'asc'; ?>&panel=order-dashboard">Status ▲▼</a></th>
                  <th><a href="?sort=created_at&dir=<?php echo $sortKey === 'created_at' && $sortDir === 'ASC' ? 'desc' : 'asc'; ?>&panel=order-dashboard">Created At ▲▼</a></th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($orders as $order): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($order['id']); ?></td>
                    <td><?php echo htmlspecialchars($order['order_number']); ?></td>
                    <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                    <td><?php echo htmlspecialchars($order['customer_email']); ?></td>
                    <td><?php echo htmlspecialchars($order['customer_phone']); ?></td>
                    <td>Rs. <?php echo number_format($order['total_amount'], 2); ?></td>
                    <td><span class="badge <?php echo htmlspecialchars($order['status']); ?>"><?php echo htmlspecialchars($order['status']); ?></span></td>
                    <td><?php echo date('Y-m-d H:i:s', strtotime($order['created_at'])); ?></td>
                    <td class="row-actions">
                      <button class="edit" onclick="window.location.href='?panel=order-dashboard&modal=edit&id=<?php echo $order['id']; ?>'">✏ Edit</button>
                      <form method="POST" action="" onsubmit="return confirm('Delete order #<?php echo $order['id']; ?>?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo $order['id']; ?>">
                        <button class="del" type="submit">🗑 Delete</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <section id="order-export" class="panel <?php echo isset($_GET['panel']) && $_GET['panel'] === 'order-export' ? 'active' : ''; ?>">
        <div class="content">
          <h2>Export Order Reports</h2>
          <form method="POST" action="" class="export-grid">
            <div class="row">
              <input type="date" id="expFrom" name="expFrom" />
              <input type="date" id="expTo" name="expTo" />
              <select id="expStatus" name="expStatus">
                <option value="">All Status</option>
                <option>Pending</option>
                <option>Confirmed</option>
                <option>Shipped</option>
                <option>Delivered</option>
                <option>Cancelled</option>
              </select>
              <input type="text" id="expSearch" name="expSearch" placeholder="Search Term" />
            </div>
            <div class="row">
              <input type="hidden" name="action" value="export_csv">
              <button class="btn" type="submit">Export CSV</button>
              <span class="muted">Exports use live, filtered data.</span>
            </div>
          </form>
        </div>
      </section>
    </main>
  </div>

  <?php
  // Handle modal display
  $modal = isset($_GET['modal']) ? $_GET['modal'] : '';
  $editOrder = null;
  if ($modal === 'edit' && isset($_GET['id'])) {
      $id = (int)$_GET['id'];
      $sql = "SELECT * FROM orders WHERE id = $id";
      $result = $conn->query($sql);
      if ($result->num_rows > 0) {
          $editOrder = $result->fetch_assoc();
      }
  }
  ?>
  <div class="modal-backdrop" style="display: <?php echo $modal ? 'flex' : 'none'; ?>;">
    <div class="modal">
      <h2><?php echo $modal === 'edit' ? 'Edit Order' : 'Add Order'; ?></h2>
      <form method="POST" action="">
        <div class="form-grid">
          <div><label>Order ID</label><input id="fId" name="fId" type="text" value="<?php echo $editOrder ? htmlspecialchars($editOrder['id']) : ''; ?>" readonly></div>
          <div><label>Order Number</label><input id="fOrderNumber" name="fOrderNumber" type="text" placeholder="Auto-generated if empty" value="<?php echo $editOrder ? htmlspecialchars($editOrder['order_number']) : ''; ?>"></div>
          <div class="full"><label>Customer Name</label><input id="fCustomerName" name="fCustomerName" type="text" placeholder="Customer Name" value="<?php echo $editOrder ? htmlspecialchars($editOrder['customer_name']) : ''; ?>" required></div>
          <div class="full"><label>Customer Email</label><input id="fCustomerEmail" name="fCustomerEmail" type="email" placeholder="customer@example.com" value="<?php echo $editOrder ? htmlspecialchars($editOrder['customer_email']) : ''; ?>" required></div>
          <div><label>Customer Phone</label><input id="fCustomerPhone" name="fCustomerPhone" type="tel" placeholder="Phone Number" value="<?php echo $editOrder ? htmlspecialchars($editOrder['customer_phone']) : ''; ?>"></div>
          <div><label>Total Amount</label><input id="fTotalAmount" name="fTotalAmount" type="number" step="0.01" placeholder="0.00" value="<?php echo $editOrder ? htmlspecialchars($editOrder['total_amount']) : ''; ?>" required></div>
          <div><label>User ID (Optional)</label><input id="fUserId" name="fUserId" type="number" placeholder="User ID" value="<?php echo $editOrder ? htmlspecialchars($editOrder['user_id']) : ''; ?>"></div>
          <div class="full"><label>Session ID (Optional)</label><input id="fSessionId" name="fSessionId" type="text" placeholder="Session ID" value="<?php echo $editOrder ? htmlspecialchars($editOrder['session_id']) : ''; ?>"></div>
          <div>
            <label>Status</label>
            <select id="fStatus" name="fStatus" required>
              <option value="pending" <?php echo $editOrder && $editOrder['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
              <option value="confirmed" <?php echo $editOrder && $editOrder['status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
              <option value="shipped" <?php echo $editOrder && $editOrder['status'] === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
              <option value="delivered" <?php echo $editOrder && $editOrder['status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
              <option value="cancelled" <?php echo $editOrder && $editOrder['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
            </select>
          </div>
        </div>
        <div class="footer">
          <button class="btn light" type="button" onclick="window.location.href='?panel=order-dashboard'">Cancel</button>
          <input type="hidden" name="action" value="<?php echo $modal === 'edit' ? 'update' : 'add'; ?>">
          <button class="btn" type="submit">Save</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    // Global search (adapt for orders)
    document.getElementById('globalSearch').addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        const rows = document.querySelectorAll('#orderTable tbody tr');
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchTerm) ? '' : 'none';
        });
    });
  </script>

</body>
</html>
<?php $conn->close(); ?>