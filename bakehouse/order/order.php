<?php
// ---------- DB: orders CRUD ----------
$host = "localhost";
$user = "root";
$pass = "";
$db   = "golden_treat";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("DB Connection failed: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

if (session_status() === PHP_SESSION_NONE) session_start();
$action = $_POST['action'] ?? $_GET['action'] ?? null;

// Helper for bind_param dynamic refs
function refValues($arr){
    $refs = [];
    foreach ($arr as $k => $v) $refs[$k] = &$arr[$k];
    return $refs;
}

// Get unique statuses for filter dropdown
$enumRes = $conn->query("SELECT DISTINCT status FROM orders WHERE deleted_at IS NULL ORDER BY status");
$enumList = [];
while ($row = $enumRes->fetch_assoc()) {
    $enumList[] = $row['status'];
}

// Initialize flash messages
$flash_error = $_SESSION['flash_error'] ?? null;
$flash_success = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_error'], $_SESSION['flash_success']);

// Handle POST actions: add / edit / delete / return
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;

    if ($action === 'add') {
        $order_date = !empty($_POST['order_date']) ? $_POST['order_date'] : date('Y-m-d');
        $customer = trim($_POST['customer_name'] ?? '');
        $product = trim($_POST['product'] ?? '');
        $quantity = (int)($_POST['quantity'] ?? 1);
        $price = (float)($_POST['price'] ?? 0.00);
        $status = in_array($_POST['status'] ?? 'pending', $enumList) ? $_POST['status'] : 'pending';

        if (empty($customer) || empty($product)) {
            $_SESSION['flash_error'] = "Customer name and product are required.";
        } else {
            $total_amount = $price * $quantity;
            // Generate order number
            $maxOrdRes = $conn->query("SELECT MAX(id) as max_id FROM orders");
            $maxId = ($maxOrdRes->fetch_assoc()['max_id'] ?? 0) + 1;
            $order_number = sprintf('ORD-%06d', $maxId);

            // Start transaction for orders and order_items
            $conn->begin_transaction();
            try {
                // Insert into orders (UPDATED: Now sets product, quantity, price, original_quantity, original_price for trigger consistency)
                $stmt = $conn->prepare("INSERT INTO orders (order_number, order_date, customer_name, product, quantity, original_quantity, price, original_price, total_amount, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param("ssssiiddds", $order_number, $order_date, $customer, $product, $quantity, $quantity, $price, $price, $total_amount, $status);
                    $ok = $stmt->execute();
                    $err = $stmt->error;
                    $order_id = $conn->insert_id;
                    $stmt->close();
                    if (!$ok) throw new Exception("Insert into orders failed: " . $err);

                    // Insert into order_items (unchanged)
                    $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_name, quantity, unit_price) VALUES (?, ?, ?, ?)");
                    if ($stmt) {
                        $stmt->bind_param("isid", $order_id, $product, $quantity, $price);
                        $ok = $stmt->execute();
                        $err = $stmt->error;
                        $stmt->close();
                        if (!$ok) throw new Exception("Insert into order_items failed: " . $err);
                    } else {
                        throw new Exception("Prepare failed for order_items: " . $conn->error);
                    }

                    $conn->commit();
                    $_SESSION['flash_success'] = "Order added successfully.";
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit;
                } else {
                    throw new Exception("Prepare failed for orders: " . $conn->error);
                }
            } catch (Exception $e) {
                $conn->rollback();
                $_SESSION['flash_error'] = "Insert failed: " . $e->getMessage();
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            }
        }
    }

    if ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        // Debug: Log POST data
        error_log("Edit POST data: " . print_r($_POST, true));

        // Fetch old row from orders and order_items (UPDATED: Use COALESCE to handle legacy data without order_items)
        $sel = $conn->prepare("SELECT o.status, o.customer_name, o.order_date, COALESCE(oi.product_name, o.product) AS product_name, COALESCE(oi.quantity, o.quantity) AS quantity, COALESCE(oi.unit_price, o.price) AS unit_price, o.deleted_at 
                               FROM orders o 
                               LEFT JOIN order_items oi ON o.id = oi.order_id 
                               WHERE o.id = ? LIMIT 1");
        if (!$sel) {
            $_SESSION['flash_error'] = "Prepare failed: " . $conn->error;
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
        $sel->bind_param("i", $id);
        $sel->execute();
        $resOld = $sel->get_result();
        $oldRow = $resOld->fetch_assoc();
        $sel->close();

        if (!$oldRow || !empty($oldRow['deleted_at'])) {
            $_SESSION['flash_error'] = "Order not found or has been deleted.";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
        $old_status = $oldRow['status'] ?? null;

        // Collect fields from POST, with old defaults
        $order_date = !empty($_POST['order_date']) ? $_POST['order_date'] : $oldRow['order_date'];
        $customer = trim($_POST['customer_name'] ?? ($oldRow['customer_name'] ?? ''));
        $product = trim($_POST['product'] ?? ($oldRow['product_name'] ?? ''));
        $quantity = (int)($_POST['quantity'] ?? ($oldRow['quantity'] ?? 1));
        $price = (float)($_POST['price'] ?? ($oldRow['unit_price'] ?? 0.00));
        $new_status = in_array($_POST['status'] ?? $old_status, $enumList) ? $_POST['status'] : $old_status;

        if (empty($customer) || empty($product)) {
            $_SESSION['flash_error'] = "Customer name and product are required.";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }

        $total_amount = $price * $quantity;
        $conn->begin_transaction();
        try {
            // Update orders (UPDATED: Now sets product, quantity, price for trigger consistency; originals unchanged to preserve history)
            $stmt = $conn->prepare("UPDATE orders SET order_date = ?, customer_name = ?, product = ?, quantity = ?, price = ?, total_amount = ?, status = ? WHERE id = ?");
            if (!$stmt) throw new Exception("Prepare failed for orders update: " . $conn->error);
            $stmt->bind_param("sssiddsi", $order_date, $customer, $product, $quantity, $price, $total_amount, $new_status, $id);
            $ok = $stmt->execute();
            $err = $stmt->error;
            $stmt->close();
            if (!$ok) throw new Exception("Update orders failed: " . $err);

            // Update or insert order_items
            $checkItems = $conn->prepare("SELECT COUNT(*) as cnt FROM order_items WHERE order_id = ?");
            $checkItems->bind_param("i", $id);
            $checkItems->execute();
            $itemCount = $checkItems->get_result()->fetch_assoc()['cnt'];
            $checkItems->close();

            if ($itemCount > 0) {
                $stmt = $conn->prepare("UPDATE order_items SET product_name = ?, quantity = ?, unit_price = ? WHERE order_id = ?");
                if (!$stmt) throw new Exception("Prepare failed for order_items update: " . $conn->error);
                $stmt->bind_param("sidi", $product, $quantity, $price, $id);
                $ok = $stmt->execute();
                $err = $stmt->error;
                $stmt->close();
                if (!$ok) throw new Exception("Update order_items failed: " . $err);
            } else {
                $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_name, quantity, unit_price) VALUES (?, ?, ?, ?)");
                if (!$stmt) throw new Exception("Prepare failed for order_items insert: " . $conn->error);
                $stmt->bind_param("isid", $id, $product, $quantity, $price);
                $ok = $stmt->execute();
                $err = $stmt->error;
                $stmt->close();
                if (!$ok) throw new Exception("Insert order_items failed: " . $err);
            }

            // If status changed, add history
            if ($old_status !== null && $old_status !== $new_status) {
                $changed_by = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
                $note = "Updated through admin UI";
                $maxHistRes = $conn->query("SELECT MAX(id_new) as max_id FROM order_status_history");
                $maxHistId = ($maxHistRes->fetch_assoc()['max_id'] ?? 0) + 1;
                if ($changed_by === null) {
                    $ins = $conn->prepare(
                        "INSERT INTO order_status_history (id_new, id, order_id, old_status, new_status, changed_by, note, created_at)
                         VALUES (?, 0, ?, ?, ?, NULL, ?, NOW())"
                    );
                    if (!$ins) throw new Exception("Prepare failed for status history: " . $conn->error);
                    $ins->bind_param("iisss", $maxHistId, $id, $old_status, $new_status, $note);
                } else {
                    $ins = $conn->prepare(
                        "INSERT INTO order_status_history (id_new, id, order_id, old_status, new_status, changed_by, note, created_at)
                         VALUES (?, 0, ?, ?, ?, ?, ?, NOW())"
                    );
                    if (!$ins) throw new Exception("Prepare failed for status history: " . $conn->error);
                    $ins->bind_param("iissis", $maxHistId, $id, $old_status, $new_status, $changed_by, $note);
                }
                if (!$ins->execute()) {
                    $err = $ins->error;
                    $ins->close();
                    throw new Exception("Failed to insert order status history: " . $err);
                }
                $ins->close();
            }

            $conn->commit();
            $_SESSION['flash_success'] = "Order updated successfully.";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['flash_error'] = "Update failed: " . $e->getMessage();
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['flash_error'] = "Invalid order id.";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
        $deleted_by = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

        try {
            $conn->begin_transaction();

            $s2 = $conn->prepare("SELECT status, deleted_at FROM orders WHERE id = ? FOR UPDATE");
            if (!$s2) throw new Exception("Prepare failed (select order): " . $conn->error);
            $s2->bind_param("i", $id);
            $s2->execute();
            $res2 = $s2->get_result();
            $orderRow = $res2 ? $res2->fetch_assoc() : null;
            $s2->close();

            if (!$orderRow) {
                throw new Exception("Order not found (id: $id).");
            }
            if (!empty($orderRow['deleted_at'])) {
                throw new Exception("Order already deleted.");
            }

            $colCheck = $conn->query("SHOW COLUMNS FROM orders LIKE 'deleted_by'");
            $hasDeletedBy = ($colCheck && $colCheck->num_rows > 0);

            if ($hasDeletedBy) {
                if ($deleted_by === null) {
                    $upd = $conn->prepare("UPDATE orders SET deleted_at = NOW(), deleted_by = NULL WHERE id = ? AND deleted_at IS NULL");
                    $upd->bind_param("i", $id);
                } else {
                    $upd = $conn->prepare("UPDATE orders SET deleted_at = NOW(), deleted_by = ? WHERE id = ? AND deleted_at IS NULL");
                    $upd->bind_param("ii", $deleted_by, $id);
                }
            } else {
                $upd = $conn->prepare("UPDATE orders SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
                $upd->bind_param("i", $id);
            }
            $upd->execute();
            if ($upd->affected_rows <= 0) {
                $upd->close();
                throw new Exception("Update affected 0 rows (order may already be deleted).");
            }
            $upd->close();

            $old_status = $orderRow['status'] ?? null;
            $new_status = 'Deleted';
            $note = "Order soft-deleted via admin UI";
            $maxHistRes = $conn->query("SELECT MAX(id_new) as max_id FROM order_status_history");
            $maxHistId = ($maxHistRes->fetch_assoc()['max_id'] ?? 0) + 1;

            if ($deleted_by === null) {
                $ins = $conn->prepare("INSERT INTO order_status_history (id_new, id, order_id, old_status, new_status, changed_by, note, created_at) VALUES (?, 0, ?, ?, ?, NULL, ?, NOW())");
                $ins->bind_param("iisss", $maxHistId, $id, $old_status, $new_status, $note);
            } else {
                $ins = $conn->prepare("INSERT INTO order_status_history (id_new, id, order_id, old_status, new_status, changed_by, note, created_at) VALUES (?, 0, ?, ?, ?, ?, ?, NOW())");
                $ins->bind_param("iissis", $maxHistId, $id, $old_status, $new_status, $deleted_by, $note);
            }
            $ins->execute();
            $ins->close();

            $conn->commit();
            $_SESSION['flash_success'] = "Order deleted successfully.";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['flash_error'] = "Delete failed: " . $e->getMessage();
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }

    if ($action === 'return') {
        $id = (int)($_POST['id'] ?? 0);
        $return_qty = (int)($_POST['return_quantity'] ?? 1);
        $reason = trim($_POST['return_reason'] ?? '');
        $refund_amount = isset($_POST['refund_amount']) ? (float)$_POST['refund_amount'] : null;
        $return_date = $_POST['return_date'] ?: date('Y-m-d');
        $processed_by = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

        if ($id <= 0) {
            $_SESSION['flash_error'] = "Invalid order id for return.";
        } elseif ($return_qty <= 0) {
            $_SESSION['flash_error'] = "Return quantity must be at least 1.";
        } else {
            try {
                $conn->begin_transaction();

                // Fetch order details to validate (UPDATED: Use COALESCE to handle legacy data without order_items)
                $sel = $conn->prepare("SELECT o.id, COALESCE(oi.quantity, o.quantity) AS quantity, COALESCE(oi.unit_price, o.price) AS unit_price, o.total_amount, o.status, o.deleted_at 
                                      FROM orders o 
                                      LEFT JOIN order_items oi ON o.id = oi.order_id 
                                      WHERE o.id = ? FOR UPDATE");
                if (!$sel) throw new Exception("Prepare failed (select order): " . $conn->error);
                $sel->bind_param("i", $id);
                $sel->execute();
                $res = $sel->get_result();
                $order = $res ? $res->fetch_assoc() : null;
                $sel->close();

                if (!$order) throw new Exception("Order not found (id: $id).");
                if (!empty($order['deleted_at'])) throw new Exception("Operation denied: this order has been deleted.");

                $order_qty = (int)$order['quantity'];
                $price_per_unit = (float)$order['unit_price'];

                if ($return_qty > $order_qty) {
                    throw new Exception("Return quantity ($return_qty) is greater than order quantity ($order_qty).");
                }

                if ($refund_amount === null) {
                    $refund_amount = $price_per_unit * $return_qty;
                } else {
                    $refund_amount = (float)$refund_amount;
                }

                // Generate unique id_new for returns
                $maxRes = $conn->query("SELECT MAX(id_new) as max_id FROM returns");
                $maxId = ($maxRes->fetch_assoc()['max_id'] ?? 0) + 1;

                // Insert into returns table
                $ins = $conn->prepare("INSERT INTO returns (id_new, id, order_id, return_date, quantity, reason, refund_amount, processed_by, created_at) VALUES (?, 0, ?, ?, ?, ?, ?, ?, NOW())");
                if (!$ins) throw new Exception("Prepare failed (insert return): " . $conn->error);
                $pb = $processed_by !== null ? (int)$processed_by : null;
                $ins->bind_param("iisisdi", $maxId, $id, $return_date, $return_qty, $reason, $refund_amount, $pb);
                if (!$ins->execute()) {
                    $err = $ins->error;
                    $ins->close();
                    throw new Exception("Insert into returns failed: " . $err);
                }
                $ins->close();

                $conn->commit();
                $_SESSION['flash_success'] = "Return processed successfully for order #$id.";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            } catch (Exception $e) {
                $conn->rollback();
                $_SESSION['flash_error'] = "Return failed: " . $e->getMessage();
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            }
        }
    }

    if ($action === 'restore') {
        $id = (int)($_POST['id'] ?? 0);
        $restored_by = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

        $colCheck = $conn->query("SHOW COLUMNS FROM orders LIKE 'deleted_by'");
        $hasDeletedBy = ($colCheck && $colCheck->num_rows > 0);

        if ($hasDeletedBy) {
            if ($restored_by === null) {
                $stmt = $conn->prepare("UPDATE orders SET deleted_at = NULL, deleted_by = NULL WHERE id = ? AND deleted_at IS NOT NULL");
                $stmt->bind_param("i", $id);
            } else {
                $stmt = $conn->prepare("UPDATE orders SET deleted_at = NULL, deleted_by = ? WHERE id = ? AND deleted_at IS NOT NULL");
                $stmt->bind_param("ii", $restored_by, $id);
            }
        } else {
            $stmt = $conn->prepare("UPDATE orders SET deleted_at = NULL WHERE id = ? AND deleted_at IS NOT NULL");
            $stmt->bind_param("i", $id);
        }

        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            $maxHistRes = $conn->query("SELECT MAX(id_new) as max_id FROM order_status_history");
            $maxHistId = ($maxHistRes->fetch_assoc()['max_id'] ?? 0) + 1;
            $note = "Order restored by admin";
            $old_status = 'Deleted';
            $new_status = 'Restored';
            if ($restored_by === null) {
                $ins = $conn->prepare("INSERT INTO order_status_history (id_new, id, order_id, old_status, new_status, changed_by, note, created_at) VALUES (?, 0, ?, ?, ?, NULL, ?, NOW())");
                $ins->bind_param("iisss", $maxHistId, $id, $old_status, $new_status, $note);
            } else {
                $ins = $conn->prepare("INSERT INTO order_status_history (id_new, id, order_id, old_status, new_status, changed_by, note, created_at) VALUES (?, 0, ?, ?, ?, ?, ?, NOW())");
                $ins->bind_param("iissis", $maxHistId, $id, $old_status, $new_status, $restored_by, $note);
            }
            $ins->execute();
            $ins->close();
        }
        $stmt->close();
        $_SESSION['flash_success'] = "Order restored successfully.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Handle GET filters and pagination
$order_id = (int)($_GET['order_id'] ?? 0);
$status = trim($_GET['status'] ?? '');
$current_page = (int)($_GET['page'] ?? 1);
if ($current_page < 1) $current_page = 1;

// Define records per page
$records_per_page = 10;
$offset = ($current_page - 1) * $records_per_page;

// Build dynamic WHERE clause
$where = "WHERE o.deleted_at IS NULL";
$params = [];
$types = "";
if ($order_id > 0) {
    $where .= " AND o.id = ?";
    $params[] = $order_id;
    $types .= "i";
}
if (!empty($status)) {
    $where .= " AND o.status = ?";
    $params[] = $status;
    $types .= "s";
}

// Count total orders for pagination
$count_sql = "SELECT COUNT(*) as total FROM orders o $where";
$count_stmt = $conn->prepare($count_sql);
if ($params) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_orders = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

// Calculate total pages
$total_pages = ceil($total_orders / $records_per_page);

// Fetch orders with order_items (UPDATED: Use COALESCE to fall back to orders fields for legacy data)
$sql = "SELECT 
          o.id,
          o.order_date,
          o.customer_name,
          COALESCE(oi.product_name, o.product) AS product_name,
          COALESCE(oi.quantity, o.quantity) AS quantity,
          COALESCE(oi.unit_price, o.price) AS unit_price,
          o.total_amount,
          o.status
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        $where ORDER BY o.id DESC LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("SQL prepare failed: " . $conn->error);
}
$params[] = $records_per_page;
$params[] = $offset;
$types .= "ii";
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$orders = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Count total orders, returns, and customers
$res2 = $conn->query("SELECT COUNT(*) AS total_orders FROM orders WHERE deleted_at IS NULL");
$row2 = $res2->fetch_assoc();
$totalOrders = (int)$row2['total_orders'];

$res3 = $conn->query("SELECT COUNT(*) AS total_returns FROM returns");
$row3 = $res3 ? $res3->fetch_assoc() : null;
$totalReturns = (int)($row3['total_returns'] ?? 0);

$res4 = $conn->query("SELECT COUNT(DISTINCT customer_name) AS total_customers FROM orders WHERE deleted_at IS NULL");
$row4 = $res4 ? $res4->fetch_assoc() : null;
$totalCustomers = (int)($row4['total_customers'] ?? 0);
?>

<!-- The HTML remains unchanged; no fixes needed there -->
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Order Management Admin UI</title>
  <link rel="stylesheet" href="css/order_style.css">
  <!-- Charts & export -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"/>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.4/jspdf.plugin.autotable.min.js"></script>
</head>
<body>
  <!-- Header -->
  <div class="header">
    <div class="header-left"><img src="logo.jpg" alt="Logo" /></div>
    <div class="header-middle">
      <div class="header-middle-title">Order Management</div>
      <div class="search-bar"><input id="globalSearch" type="text" placeholder="Search by customer, status or ID..." /></div>
    </div>
    <div class="header-right">
      <button class="role-btn" onclick="window.location.href='index.html'">Dashboard</button>
      <div class="user-icon"></div>
    </div>
  </div>

  <div class="layout">
    <!-- Sidebar -->
    <aside class="sidebar">
      <h1>Purchase Dashboard</h1>
      <nav>
        <button class="salesbtn" disabled>Order</button>
        <div class="otherbtn">
          <button class="Sbtn" onclick="window.location.href='../stoke/stock.php'">Stock</button>
          <button class="Ubtn" onclick="window.location.href='../sales/index.php'">Sales</button>
          <button class="Bbtn" onclick="window.location.href='../booking/index.html'">Booking</button>
        </div>
        <hr />
        <p>Order Management</p>
        <div class="salebtn">
          <button class="tab-btn active" data-page="sales-dashboard">Purchase Dashboard</button>
          <button class="tab-btn" data-page="sales-export">Export Report</button>
          <button type="button" class="orderhistory" onclick="window.location.href='purchase_returns.php'">Purchase Returns</button>
          <button type="button" class="orderhistory" onclick="window.location.href='purchase_history.php'">Purchase History</button>
        </div>
      </nav>
    </aside>

    <main class="free-area">
      <!-- Orders Panel -->
      <section id="orders" class="panel active">
        <div class="content">
          <h2>Order Management</h2>
          <?php if (isset($flash_error)): ?>
            <div class="alert error"><?= htmlspecialchars($flash_error) ?></div>
          <?php endif; ?>
          <?php if (isset($flash_success)): ?>
            <div class="alert success"><?= htmlspecialchars($flash_success) ?></div>
          <?php endif; ?>
          <div class="cards">
            <div class="card">
              <h3>Total Returns</h3>
              <p id="cardTotal"><?= $totalReturns ?></p>
            </div>
            <div class="card">
              <h3>Total Orders</h3>
              <p id="cardOrders"><?= $totalOrders ?></p>
            </div>
            <div class="card">
              <h3>Total Customers</h3>
              <p id="cardCustomers"><?= $totalCustomers ?></p>
            </div>
          </div>

          <div class="toolbar">
            <form method="get" class="filter-bar">
              <input type="number" name="order_id" placeholder="Order ID" value="<?= ($order_id ? (int)$order_id : '') ?>" />
              <select name="status" onchange="this.form.submit();">
                <option value="">All status</option>
                <?php foreach ($enumList as $st): $sel = ($st === $status) ? 'selected' : ''; ?>
                  <option value="<?= htmlspecialchars($st) ?>" <?= $sel ?>><?= htmlspecialchars($st) ?></option>
                <?php endforeach; ?>
              </select>
              <button class="btn secondary" id="btnAdd" type="button" onclick="openOrderAdd()"><i class="fa-solid fa-circle-plus"></i><b> Add Order</b></button>
            </form>
          </div>

          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Order ID</th>
                  <th>Customer</th>
                  <th>Product</th>
                  <th>Quantity</th>
                  <th>Total</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="orderTable">
                <?php if (empty($orders)): ?>
                  <tr><td colspan="7" style="text-align:center;padding:18px">No orders found</td></tr>
                <?php else: foreach ($orders as $o): ?>
                  <tr>
                    <td><?= htmlspecialchars($o['id']) ?></td>
                    <td><?= htmlspecialchars($o['customer_name']) ?></td>
                    <td><?= htmlspecialchars($o['product_name'] ?? '') ?></td>
                    <td><?= (int)$o['quantity'] ?></td>
                    <td>
  <?= number_format((float)($o['total_amount'] ?? ((float)$o['unit_price'] * (int)$o['quantity'])), 2) ?>
</td>
                    <td><?= htmlspecialchars($o['status']) ?></td>
                    <td>
                      <button class="btnview"
                        type="button"
                        data-id="<?= htmlspecialchars($o['id']) ?>"
                        data-order-date="<?= htmlspecialchars($o['order_date']) ?>"
                        data-customer="<?= htmlspecialchars($o['customer_name']) ?>"
                        data-product="<?= htmlspecialchars($o['product_name'] ?? '') ?>"
                        data-quantity="<?= (int)$o['quantity'] ?>"
                        data-price="<?= htmlspecialchars(number_format((float)$o['unit_price'], 2, '.', '')) ?>"
                        data-total="<?= htmlspecialchars(number_format((float)($o['total_amount'] ?? ((float)$o['unit_price'] * (int)$o['quantity'])), 2, '.', '')) ?>"
                        data-status="<?= htmlspecialchars($o['status']) ?>">
                        <i class="fa-solid fa-eye"></i>
                      </button>
                      <button class="btnupdate"
                        type="button"
                        data-id="<?= htmlspecialchars($o['id']) ?>"
                        data-order-date="<?= htmlspecialchars($o['order_date']) ?>"
                        data-customer="<?= htmlspecialchars($o['customer_name']) ?>"
                        data-product="<?= htmlspecialchars($o['product_name'] ?? '') ?>"
                        data-quantity="<?= (int)$o['quantity'] ?>"
                        data-price="<?= htmlspecialchars(number_format((float)$o['unit_price'], 2, '.', '')) ?>"
                        data-total="<?= htmlspecialchars(number_format((float)($o['total_amount'] ?? ((float)$o['unit_price'] * (int)$o['quantity'])), 2, '.', '')) ?>"
                        data-status="<?= htmlspecialchars($o['status']) ?>">
                        <i class="fa-regular fa-pen-to-square"></i>
                      </button>
                      <button class="btnreturn"
                        type="button"
                        data-id="<?= htmlspecialchars($o['id']) ?>"
                        data-quantity="<?= (int)$o['quantity'] ?>"
                        data-price="<?= htmlspecialchars(number_format((float)$o['unit_price'], 2, '.', '')) ?>"
                        title="Process return for order #<?= htmlspecialchars($o['id']) ?>">
                        <i class="fa-solid fa-rotate-left"></i>
                      </button>
                      <form method="post" style="display:inline" onsubmit="return confirm('Delete order #<?= $o['id'] ?>?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $o['id'] ?>">
                        <button class="btndelete" type="submit"><i class="fa-solid fa-trash-can"></i></button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
            <!-- Pagination Controls -->
            <div class="pagination">
              <form method="get" class="pagination-form">
                <!-- Preserve existing filters -->
                <?php if ($order_id): ?>
                  <input type="hidden" name="order_id" value="<?= htmlspecialchars($order_id) ?>">
                <?php endif; ?>
                <?php if ($status): ?>
                  <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
                <?php endif; ?>
                
                <!-- Previous Button -->
                <button type="submit" name="page" value="<?= max(1, $current_page - 1) ?>" <?= $current_page <= 1 ? 'disabled' : '' ?>>Previous</button>
                
                <!-- Page Numbers -->
                <?php
                $range = 2; // Number of pages to show before and after current page
                $start = max(1, $current_page - $range);
                $end = min($total_pages, $current_page + $range);

                // Show first page and ellipsis if needed
                if ($start > 1): ?>
                  <button type="submit" name="page" value="1">1</button>
                  <?php if ($start > 2): ?>
                    <span>...</span>
                  <?php endif; ?>
                <?php endif; ?>

                <!-- Page range -->
                <?php for ($i = $start; $i <= $end; $i++): ?>
                  <button type="submit" name="page" value="<?= $i ?>" <?= $i == $current_page ? 'class="active"' : '' ?>><?= $i ?></button>
                <?php endfor; ?>

                <!-- Show last page and ellipsis if needed -->
                <?php if ($end < $total_pages): ?>
                  <?php if ($end < $total_pages - 1): ?>
                    <span>...</span>
                  <?php endif; ?>
                  <button type="submit" name="page" value="<?= $total_pages ?>"><?= $total_pages ?></button>
                <?php endif; ?>

                <!-- Next Button -->
                <button type="submit" name="page" value="<?= min($total_pages, $current_page + 1) ?>" <?= $current_page >= $total_pages ? 'disabled' : '' ?>>Next</button>
              </form>
            </div>
          </div>
        </div>
      </section>
      
      <!-- Export -->
      <section id="sales-export" class="panel">
        <div class="content">
          <h2>Export Reports</h2>
          <div class="export-grid">
            <div class="row">
              <input type="date" id="expFrom" />
              <input type="date" id="expTo" />
              <select id="expStatus">
                <option value="">All Status</option>
                <?php foreach ($enumList as $st): ?>
                  <option value="<?= htmlspecialchars($st) ?>"><?= htmlspecialchars($st) ?></option>
                <?php endforeach; ?>
              </select>
              <input type="text" id="expCustomer" placeholder="Customer" />
            </div>
            <div class="row">
              <button class="btn" id="expCsv">Export CSV</button>
              <button class="btn secondary" id="expXlsx">Export Excel</button>
              <button class="btn warn" id="expPdf">Export PDF</button>
              <span class="muted">Exports use live, filtered data.</span>
            </div>
          </div>
        </div>
      </section>
    </main>
  </div>

  <!-- Modal Add/Edit for ORDERS -->
  <div class="modal-backdrop" id="orderModalBackdrop">
    <div class="modal">
      <h2 id="orderModalTitle">Add Order</h2>
      <form id="orderForm" method="post" style="margin-top:12px">
        <input type="hidden" name="action" id="order_form_action" value="add">
        <input type="hidden" name="id" id="order_form_id" value="0">
        <div class="form-grid">
          <div><label>Date</label><input id="order_form_date" name="order_date" type="date" required></div>
          <div><label>Quantity</label><input id="order_form_quantity" name="quantity" type="number" min="1" value="1" required></div>
          <div class="full"><label>Price</label><input id="order_form_price" name="price" type="number" step="0.01" min="0" value="0.00" required></div>
          <div class="full"><label>Customer</label><input id="order_form_customer" name="customer_name" type="text" required></div>
          <div class="full"><label>Product</label><input id="order_form_product" name="product" type="text" required></div>
          <div class="full">
            <label>Status</label>
            <select id="order_form_status" name="status">
              <?php foreach ($enumList as $st): ?>
                <option value="<?= htmlspecialchars($st) ?>"><?= htmlspecialchars($st) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="footer" style="margin-top:12px">
          <button type="button" class="btn light" onclick="closeOrderModal()">Cancel</button>
          <button type="submit" class="btn">Save</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Return Modal -->
  <div class="modal-backdrop" id="returnModalBackdrop" style="display:none">
    <div class="modal">
      <h2 id="returnModalTitle">Return Order</h2>
      <form id="returnForm" method="post" style="margin-top:12px">
        <input type="hidden" name="action" value="return">
        <input type="hidden" name="id" id="return_order_id" value="0">
        <div class="form-grid">
          <div><label>Return Date</label><input name="return_date" id="return_date" type="date" value="<?= date('Y-m-d') ?>" required></div>
          <div><label>Quantity to return</label><input name="return_quantity" id="return_quantity" type="number" min="1" value="1" required></div>
          <div class="full"><label>Refund Amount</label><input name="refund_amount" id="return_refund_amount" type="number" step="0.01" min="0" value="0.00" required></div>
          <div class="full"><label>Reason</label><textarea name="return_reason" id="return_reason" rows="3"></textarea></div>
        </div>
        <div class="footer" style="margin-top:12px">
          <button type="button" class="btn light" onclick="closeReturnModal()">Cancel</button>
          <button type="submit" class="btn">Save Return</button>
        </div>
      </form>
    </div>
  </div>

  <!-- JavaScript -->
  <script>
  document.addEventListener('DOMContentLoaded', () => {
    const orderModalBackdrop = document.getElementById('orderModalBackdrop');
    const orderForm = document.getElementById('orderForm');
    const inAction = (val) => document.getElementById('order_form_action').value = val;
    const inId = (val) => document.getElementById('order_form_id').value = val;
    const inDate = (val) => document.getElementById('order_form_date').value = val;
    const inCustomer = (val) => document.getElementById('order_form_customer').value = val || '';
    const inProduct = (val) => document.getElementById('order_form_product').value = val || '';
    const inQuantity = (val) => document.getElementById('order_form_quantity').value = val || '1';
    const inPrice = (val) => {
      const el = document.getElementById('order_form_price');
      if (el) el.value = (val === undefined || val === null) ? '0.00' : Number(val).toFixed(2);
    };
    const inStatus = (val) => {
      const el = document.getElementById('order_form_status');
      if (el) {
        const option = Array.from(el.options).find(opt => opt.value.toLowerCase() === (val || '').toLowerCase());
        if (option) el.value = option.value;
        else el.value = el.options[0]?.value || '';
      }
    };
    const saveBtn = () => document.querySelector('#orderModalBackdrop .footer .btn:not(.light)');
    const today = () => new Date().toISOString().slice(0,10);

    // Reset form to prevent data leakage
    function resetOrderForm() {
      inAction('add');
      inId('0');
      inDate('');
      inCustomer('');
      inProduct('');
      inQuantity('1');
      inPrice('0.00');
      inStatus('');
      orderForm.reset();
    }

    function parseAndCallOpen(btn, fn) {
      const ds = btn.dataset;
      const id = ds.id || '';
      const order_date = ds.orderDate || today();
      const customer = ds.customer || '';
      const product = ds.product || '';
      const quantity = ds.quantity || '1';
      const price = ds.price || '0.00';
      const status = ds.status || '';
      try {
        fn(id, order_date, customer, product, Number(quantity), Number(price), status);
      } catch (err) {
        console.error('Failed to call modal function', err, { id, order_date, customer, product, quantity, price, status });
      }
    }

    // Tab wiring
    (function(){
      const panelAlias = {
        'sales-dashboard': 'orders',
        'sales-export': 'sales-export'
      };

      function activatePanelByName(name) {
        let id = name;
        if (!document.getElementById(id) && panelAlias[name]) id = panelAlias[name];
        const panel = document.getElementById(id);
        if (!panel) {
          console.warn('activatePanel: no panel found for', name, '->', id);
          return;
        }
        document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
        panel.classList.add('active');
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.toggle('active', b.dataset.page === name));
      }

      document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
          const page = btn.dataset.page;
          activatePanelByName(page);
        });
      });

      const initial = document.querySelector('.tab-btn.active')?.dataset.page || document.querySelector('.panel.active')?.id;
      if (initial) activatePanelByName(initial);
    })();

    // Return modal wiring
    (function(){
      const returnModalBackdrop = document.getElementById('returnModalBackdrop');
      const returnForm = document.getElementById('returnForm');
      const inputReturnQty = document.getElementById('return_quantity');
      const inputRefund = document.getElementById('return_refund_amount');
      const inputReturnDate = document.getElementById('return_date');

      function openReturnModal(id, origQty, price){
        document.getElementById('returnModalTitle').textContent = 'Return Order #' + id;
        document.getElementById('return_order_id').value = String(id);
        const maxQty = Math.max(1, Number(origQty) || 1);
        if (inputReturnQty) {
          inputReturnQty.setAttribute('min', '1');
          inputReturnQty.setAttribute('max', String(maxQty));
          inputReturnQty.value = '1';
        }
        if (inputRefund) inputRefund.value = (Number(price) || 0).toFixed(2);
        if (inputReturnDate) inputReturnDate.value = inputReturnDate.value || new Date().toISOString().slice(0,10);
        document.getElementById('return_reason').value = '';
        returnModalBackdrop.style.display = 'flex';
        if (inputReturnQty) inputReturnQty.focus();
      }

      window.closeReturnModal = function(){ returnModalBackdrop.style.display = 'none'; };

      document.addEventListener('click', function(e){
        const btn = e.target.closest('.btnreturn');
        if (!btn) return;
        const ds = btn.dataset || {};
        const id = ds.id || btn.getAttribute('data-id');
        const qty = Number(ds.quantity || btn.getAttribute('data-quantity') || 1);
        const price = Number(ds.price || btn.getAttribute('data-price') || 0);
        openReturnModal(id, qty, price);
      });

      if (returnForm) {
        returnForm.addEventListener('submit', (e) => {
          const qEl = document.getElementById('return_quantity');
          const q = Number(qEl?.value || 0);
          const max = Number(qEl?.getAttribute('max') || Infinity);
          if (!q || q < 1) {
            e.preventDefault();
            alert('Return quantity must be at least 1.');
            return;
          }
          if (q > max) {
            e.preventDefault();
            alert('Return quantity cannot exceed original quantity (' + max + ').');
            return;
          }
        });
      }

      if (returnModalBackdrop) {
        returnModalBackdrop.addEventListener('click', (ev) => { if (ev.target === returnModalBackdrop) closeReturnModal(); });
      }
    })();

    // Export utilities
    (function(){
      function gatherVisibleOrders() {
        const rows = Array.from(document.querySelectorAll('#orderTable tr')).filter(r => r.style.display !== 'none');
        const out = rows.map(r => {
          const tds = r.querySelectorAll('td');
          if (!tds.length) return null;
          return {
            id: tds[0].textContent.trim(),
            customer: tds[1].textContent.trim(),
            product: tds[2].textContent.trim(),
            quantity: tds[3].textContent.trim(),
            price: tds[4].textContent.trim(),
            status: tds[5].textContent.trim()
          };
        }).filter(Boolean);
        return out;
      }

      function quoteCSV(s) {
        const str = String(s ?? '');
        return /[",\n]/.test(str) ? `"${str.replace(/"/g, '""')}"` : str;
      }

      function toCSV(rows) {
        const header = ['Order ID','Customer','Product','Quantity','Price','Status'];
        const lines = [header.join(',')];
        rows.forEach(r => lines.push([quoteCSV(r.id), quoteCSV(r.customer), quoteCSV(r.product), r.quantity, r.price, quoteCSV(r.status)].join(',')));
        return lines.join('\n');
      }

      function download(name, blob) {
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = name;
        a.click();
        setTimeout(() => URL.revokeObjectURL(a.href), 1000);
      }

      const btnCsv = document.getElementById('expCsv');
      const btnXlsx = document.getElementById('expXlsx');
      const btnPdf = document.getElementById('expPdf');

      if (btnCsv) btnCsv.addEventListener('click', () => {
        const rows = gatherVisibleOrders();
        const csv = toCSV(rows);
        download('orders_export.csv', new Blob([csv], { type: 'text/csv' }));
      });

      if (btnXlsx) btnXlsx.addEventListener('click', () => {
        const rows = gatherVisibleOrders().map(r => ({ 'Order ID': r.id, 'Customer': r.customer, 'Product': r.product, 'Quantity': +r.quantity, 'Price': r.price, 'Status': r.status }));
        const ws = XLSX.utils.json_to_sheet(rows);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Orders');
        XLSX.writeFile(wb, 'orders_export.xlsx');
      });

      if (btnPdf) btnPdf.addEventListener('click', () => {
        const rows = gatherVisibleOrders();
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        doc.setFontSize(12);
        doc.text('Orders Report', 14, 16);
        const body = rows.map(r => [r.id, r.customer, r.product, r.quantity, r.price, r.status]);
        doc.autoTable({ startY: 22, head: [['ID','Customer','Product','Qty','Price','Status']], body });
        doc.save('orders_export.pdf');
      });
    })();

    document.querySelectorAll('.btnupdate').forEach(btn => {
      btn.addEventListener('click', (e) => {
        parseAndCallOpen(btn, window.openOrderEdit);
      });
    });

    document.querySelectorAll('.btnview').forEach(btn => {
      btn.addEventListener('click', (e) => {
        parseAndCallOpen(btn, window.openOrderView);
      });
    });

    window.openOrderAdd = function(){
      resetOrderForm();
      document.getElementById('orderModalTitle').textContent = 'Add Order';
      inAction('add');
      inId('0');
      inDate(today());
      inCustomer('');
      inProduct('');
      inQuantity('1');
      inPrice('0.00');
      inStatus('<?php echo htmlspecialchars($enumList[0] ?? 'pending'); ?>');
      enableFormFields();
      if (saveBtn()) saveBtn().style.display = '';
      orderModalBackdrop.style.display = 'flex';
      document.getElementById('order_form_date').focus();
    };

    window.openOrderEdit = function(id, order_date, customer, product, quantity, price, status){
      resetOrderForm();
      document.getElementById('orderModalTitle').textContent = 'Edit Order #' + id;
      inAction('edit');
      inId(String(id));
      inDate(order_date || today());
      inCustomer(customer || '');
      inProduct(product || '');
      inQuantity(String(quantity || 1));
      inPrice(price || '0.00');
      inStatus(status || '<?php echo htmlspecialchars($enumList[0] ?? 'pending'); ?>');
      enableFormFields();
      if (saveBtn()) saveBtn().style.display = '';
      orderModalBackdrop.style.display = 'flex';
      document.getElementById('order_form_customer').focus();
    };

    window.openOrderView = function(id, order_date, customer, product, quantity, price, status){
      resetOrderForm();
      document.getElementById('orderModalTitle').textContent = 'View Order #' + id;
      inAction('view');
      inId(String(id));
      inDate(order_date || today());
      inCustomer(customer || '');
      inProduct(product || '');
      inQuantity(String(quantity || 1));
      inPrice(price || '0.00');
      inStatus(status || '<?php echo htmlspecialchars($enumList[0] ?? 'pending'); ?>');
      disableFormFields();
      const s = saveBtn();
      if (s) s.style.display = 'none';
      orderModalBackdrop.style.display = 'flex';
    };

    function disableFormFields(){
      ['order_form_date','order_form_customer','order_form_product','order_form_quantity','order_form_price','order_form_status'].forEach(n => {
        const el = document.getElementById(n);
        if (el) el.setAttribute('disabled', 'disabled');
      });
    }

    function enableFormFields(){
      ['order_form_date','order_form_customer','order_form_product','order_form_quantity','order_form_price','order_form_status'].forEach(n => {
        const el = document.getElementById(n);
        if (el) el.removeAttribute('disabled');
      });
    }

    window.closeOrderModal = function(){
      enableFormFields();
      const s = saveBtn();
      if (s) s.style.display = '';
      orderModalBackdrop.style.display = 'none';
      resetOrderForm();
    };

    if (orderForm) {
      orderForm.addEventListener('submit', function(e){
        const act = document.getElementById('order_form_action').value;
        if (act === 'view') {
          e.preventDefault();
          return false;
        }
        // Client-side validation
        const customer = document.getElementById('order_form_customer').value.trim();
        const product = document.getElementById('order_form_product').value.trim();
        const price = parseFloat(document.getElementById('order_form_price').value);
        const quantity = parseInt(document.getElementById('order_form_quantity').value);
        if (!customer) {
          e.preventDefault();
          alert('Customer name is required.');
          document.getElementById('order_form_customer').focus();
          return;
        }
        if (!product) {
          e.preventDefault();
          alert('Product name is required.');
          document.getElementById('order_form_product').focus();
          return;
        }
        if (isNaN(price) || price < 0) {
          e.preventDefault();
          alert('Price must be a valid number >= 0.');
          document.getElementById('order_form_price').focus();
          return;
        }
        if (isNaN(quantity) || quantity < 1) {
          e.preventDefault();
          alert('Quantity must be at least 1.');
          document.getElementById('order_form_quantity').focus();
          return;
        }
      });
    }

    if (orderModalBackdrop) {
      orderModalBackdrop.addEventListener('click', (ev) => {
        if (ev.target === orderModalBackdrop) closeOrderModal();
      });
    }

    document.addEventListener('keydown', (ev) => {
      if (ev.key === 'Escape' && orderModalBackdrop.style.display === 'flex') {
        closeOrderModal();
      }
    });

    const globalSearch = document.getElementById('globalSearch');
    const tbody = document.getElementById('orderTable');

    function filterTable() {
      const q = (globalSearch?.value || '').trim().toLowerCase();
      if (!tbody) return;
      const rows = Array.from(tbody.querySelectorAll('tr'));
      let visibleCount = 0;

      if (!q) {
        rows.forEach(r => {
          r.style.display = '';
          visibleCount++;
        });
      } else {
        rows.forEach(r => {
          const tds = Array.from(r.querySelectorAll('td'));
          if (!tds.length) {
            r.style.display = '';
            visibleCount++;
            return;
          }
          const rowText = tds.map(td => (td.textContent || '').toLowerCase()).join(' ');
          const match = rowText.includes(q);
          r.style.display = match ? '' : 'none';
          if (match) visibleCount++;
        });
      }

      // Show a message if no rows are visible
      if (visibleCount === 0 && rows.length > 0) {
        const noResultRow = document.createElement('tr');
        noResultRow.innerHTML = '<td colspan="7" style="text-align:center;padding:18px">No matching orders found on this page</td>';
        tbody.innerHTML = '';
        tbody.appendChild(noResultRow);
      } else if (visibleCount === 0 && rows.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:18px">No orders found</td></tr>';
      }
    }

    if (globalSearch) {
      globalSearch.addEventListener('input', filterTable);
    }

    // Reset to page 1 when filters change
    document.querySelectorAll('.filter-bar select, .filter-bar input[name="order_id"]').forEach(elem => {
      elem.addEventListener('change', () => {
        const form = elem.closest('form');
        const pageInput = document.createElement('input');
        pageInput.type = 'hidden';
        pageInput.name = 'page';
        pageInput.value = '1';
        form.appendChild(pageInput);
        form.submit();
      });
    });

    const btnAdd = document.getElementById('btnAdd');
    if (btnAdd) btnAdd.addEventListener('click', openOrderAdd);

    closeOrderModal();
  });
  </script>
</body>
</html>