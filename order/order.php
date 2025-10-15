<?php
// ---------- DB: orders CRUD (keep style unchanged) ----------
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
// Helper for bind_param dynamic refs (used if needed)
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
// Handle POST actions: add / edit / delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;

if ($action === 'add') {
    $order_date = !empty($_POST['order_date']) ? $_POST['order_date'] : date('Y-m-d');
    $customer = trim($_POST['customer_name'] ?? '');
    $product = trim($_POST['product'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 1);
    $price = (float)($_POST['price'] ?? 0.00);
    $status = $_POST['status'] ?? 'Order Received';  // Align with DB default

    if (empty($customer) || empty($product)) {
        $flash_error = "Customer name and product are required.";
    } else {
        $total_amount = $price * $quantity;
        // Generate order number
        $maxOrdRes = $conn->query("SELECT MAX(id) as max_id FROM orders");
        $maxId = ($maxOrdRes->fetch_assoc()['max_id'] ?? 0) + 1;
        $order_number = sprintf('ORD-%06d', $maxId);

        $stmt = $conn->prepare("INSERT INTO orders (order_number, order_date, customer_name, product, quantity, price, total_amount, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("ssssidds", $order_number, $order_date, $customer, $product, $quantity, $price, $total_amount, $status);
            $ok = $stmt->execute();
            $err = $stmt->error;
            $stmt->close();
            if (!$ok) $flash_error = "Insert failed: " . $err;
            else { header("Location: " . $_SERVER['PHP_SELF']); exit; }
        } else {
            $flash_error = "Insert failed: " . $conn->error;
        }
    }
}

   // require_once __DIR__ . '/sms_helpers.php';

 if ($action === 'edit') {
    $id = (int)($_POST['id'] ?? 0);

    // Fetch old row FIRST (added order_date, product for defaults)
    $sel = $conn->prepare("SELECT status, customer_name, order_date, product, deleted_at FROM orders WHERE id = ? LIMIT 1");
    $sel->bind_param("i", $id);
    $sel->execute();
    $resOld = $sel->get_result();
    $oldRow = $resOld->fetch_assoc();
    $sel->close();

    if (!$oldRow || !empty($oldRow['deleted_at'])) {
        $flash_error = "Order not found or has been deleted.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
    $old_status = $oldRow['status'] ?? null;

    // Collect fields from POST, with old defaults
    $order_date = !empty($_POST['order_date']) ? $_POST['order_date'] : $oldRow['order_date'];
    $customer = trim($_POST['customer_name'] ?? ($oldRow['customer_name'] ?? ''));
    $product = trim($_POST['product'] ?? ($oldRow['product'] ?? ''));
    $quantity = (int)($_POST['quantity'] ?? 1);
    $price = (float)($_POST['price'] ?? 0.00);
    $new_status = $_POST['status'] ?? 'Order Received';

    if (empty($customer) || empty($product)) {
        $flash_error = "Customer name and product are required.";
    } else {
        // Perform update
        $stmt = $conn->prepare("UPDATE orders SET order_date = ?, customer_name = ?, product = ?, quantity = ?, price = ?, status = ? WHERE id = ?");
        $stmt->bind_param("sssidsi", $order_date, $customer, $product, $quantity, $price, $new_status, $id);
        $ok = $stmt->execute();
        $err = $stmt->error;
        $stmt->close();

if (!$ok) $flash_error = "Update failed: " . $err;
        else {
            // If status changed, add history (unchanged)
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
                    if ($ins) {
                        $ins->bind_param("iisss", $maxHistId, $id, $old_status, $new_status, $note);
                        $ins->execute();
                        $ins->close();
                    } else {
                        $flash_error = "Failed to insert order status history: " . $conn->error;
                    }
                } else {
                    $ins = $conn->prepare(
                        "INSERT INTO order_status_history (id_new, id, order_id, old_status, new_status, changed_by, note, created_at)
                         VALUES (?, 0, ?, ?, ?, ?, ?, NOW())"
                    );
                    if ($ins) {
                        $ins->bind_param("iissis", $maxHistId, $id, $old_status, $new_status, $changed_by, $note);
                        $ins->execute();
                        $ins->close();
                    } else {
                        $flash_error = "Failed to insert order status history: " . $conn->error;
                    }
                }
            }
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }
}

// ----------  Delete handler ----------
if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        $flash_error = "Invalid order id.";
    } else {
        $deleted_by = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

        try {
            $conn->begin_transaction();

            // Lock & fetch the order (for update)
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

            // Check if orders table has deleted_by column
            $colCheck = $conn->query("SHOW COLUMNS FROM orders LIKE 'deleted_by'");
            $hasDeletedBy = ($colCheck && $colCheck->num_rows > 0);

            // Build & execute update depending on whether deleted_by exists
            if ($hasDeletedBy) {
                if ($deleted_by === null) {
                    // set deleted_by = NULL explicitly
                    $upd = $conn->prepare("UPDATE orders SET deleted_at = NOW(), deleted_by = NULL WHERE id = ? AND deleted_at IS NULL");
                    if (!$upd) throw new Exception("Prepare failed (update orders): " . $conn->error);
                    $upd->bind_param("i", $id);
                } else {
                    $upd = $conn->prepare("UPDATE orders SET deleted_at = NOW(), deleted_by = ? WHERE id = ? AND deleted_at IS NULL");
                    if (!$upd) throw new Exception("Prepare failed (update orders): " . $conn->error);
                    $upd->bind_param("ii", $deleted_by, $id);
                }
            } else {
                // fallback: only set deleted_at
                $upd = $conn->prepare("UPDATE orders SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
                if (!$upd) throw new Exception("Prepare failed (update orders): " . $conn->error);
                $upd->bind_param("i", $id);
            }

            $upd->execute();
            if ($upd->affected_rows <= 0) {
                // no row updated -> either id mismatch or already deleted
                $upd->close();
                throw new Exception("Update affected 0 rows (order may already be deleted).");
            }
            $upd->close();

            // insert history row
            $old_status = $orderRow['status'] ?? null;
            $new_status = 'Deleted';
            $note = "Order soft-deleted via admin UI";
            $maxHistRes = $conn->query("SELECT MAX(id_new) as max_id FROM order_status_history");
            $maxHistId = ($maxHistRes->fetch_assoc()['max_id'] ?? 0) + 1;

            if ($deleted_by === null) {
                // insert with changed_by = NULL
                $ins = $conn->prepare("INSERT INTO order_status_history (id_new, id, order_id, old_status, new_status, changed_by, note, created_at) VALUES (?, 0, ?, ?, ?, NULL, ?, NOW())");
                if (!$ins) throw new Exception("Prepare failed (insert history): " . $conn->error);
                $ins->bind_param("iisss", $maxHistId, $id, $old_status, $new_status, $note);
            } else {
                $ins = $conn->prepare("INSERT INTO order_status_history (id_new, id, order_id, old_status, new_status, changed_by, note, created_at) VALUES (?, 0, ?, ?, ?, ?, ?, NOW())");
                if (!$ins) throw new Exception("Prepare failed (insert history): " . $conn->error);
                $ins->bind_param("iissis", $maxHistId, $id, $old_status, $new_status, $deleted_by, $note);
            }
            $ins->execute();
            $ins->close();

            $conn->commit();
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            // Show the error for debugging; in production log this instead.
            $flash_error = "Delete failed: " . $e->getMessage();
        }
    }
}
// ---------- Return order handler (improved) ----------
if ($action === 'return') {
    $id = (int)($_POST['id'] ?? 0);
    $return_qty = (int)($_POST['return_quantity'] ?? 1);
    $reason = trim($_POST['return_reason'] ?? '');
    $refund_amount = isset($_POST['refund_amount']) ? (float)$_POST['refund_amount'] : null; // optional override
    $return_date = $_POST['return_date'] ?: date('Y-m-d');
    $processed_by = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

    if ($id <= 0) {
        $flash_error = "Invalid order id for return.";
    } elseif ($return_qty <= 0) {
        $flash_error = "Return quantity must be at least 1.";
    } else {
        try {
            $conn->begin_transaction();

            // Lock the order row
            $sel = $conn->prepare("SELECT id, quantity, price, total_amount, status, original_quantity, original_price, deleted_at FROM orders WHERE id = ? FOR UPDATE");
            if (!$sel) throw new Exception("Prepare failed (select order): " . $conn->error);
            $sel->bind_param("i", $id);
            $sel->execute();
            $res = $sel->get_result();
            $order = $res ? $res->fetch_assoc() : null;
            $sel->close();

            if (!$order) throw new Exception("Order not found (id: $id).");
            if (!empty($order['deleted_at'])) throw new Exception("Operation denied: this order has been deleted.");

            $order_qty = (int)$order['quantity'];
            $price_per_unit = (float)$order['price'];

            if ($return_qty > $order_qty) {
                throw new Exception("Return quantity ($return_qty) is greater than order quantity ($order_qty).");
            }

            // If refund not provided, compute it
            if ($refund_amount === null) {
                $refund_amount = $price_per_unit * $return_qty;
            } else {
                $refund_amount = (float)$refund_amount;
            }

            // Ensure original_quantity / original_price are preserved (set them if empty)
            $orig_qty = (int)($order['original_quantity'] ?? 0);
            $orig_price = (float)($order['original_price'] ?? 0.0);
            if ($orig_qty === 0 || $orig_price == 0.0) {
                $oq = $orig_qty === 0 ? $order_qty : $orig_qty;
                $op = ($orig_price == 0.0) ? $price_per_unit : $orig_price;
                $setOrig = $conn->prepare("UPDATE orders SET original_quantity = ?, original_price = ? WHERE id = ?");
                if (!$setOrig) throw new Exception("Prepare failed (set original): " . $conn->error);
                $setOrig->bind_param("idi", $oq, $op, $id);
                $setOrig->execute();
                $setOrig->close();
            }

            // Get next id_new for returns
            $maxRes = $conn->query("SELECT MAX(id_new) as max_id FROM returns");
            $maxId = ($maxRes->fetch_assoc()['max_id'] ?? 0) + 1;

            // Insert returns row
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

            // TODO: update product stock or order_items if you track them (not implemented here)
            // Note: Triggers will handle updating the orders table and inserting history

            $conn->commit();
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $flash_error = "Return failed: " . $e->getMessage();
        }
    }
}

if ($action === 'restore') {
    $id = (int)($_POST['id'] ?? 0);
    $restored_by = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

    // detect if deleted_by column exists
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
        // insert history
        $maxHistRes = $conn->query("SELECT MAX(id_new) as max_id FROM order_status_history");
        $maxHistId = ($maxHistRes->fetch_assoc()['max_id'] ?? 0) + 1;
        $note = "Order restored by admin";
        $old_status = 'Deleted';
        $new_status = 'Restored';
        if ($restored_by === null) {
            // bind with changed_by as NULL: use the 4-param form where column is provided as NULL in SQL
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
    header("Location: " . $_SERVER['PHP_SELF']); exit;
}
}

// Handle GET filters (move here if not already)
$order_id = (int)($_GET['order_id'] ?? 0);
$status = trim($_GET['status'] ?? '');

// Build dynamic WHERE clause
$where = "WHERE deleted_at IS NULL";
$params = [];
$types = "";
if ($order_id > 0) {
    $where .= " AND id = ?";
    $params[] = $order_id;
    $types .= "i";
}
if (!empty($status)) {
    $where .= " AND status = ?";
    $params[] = $status;
    $types .= "s";
}
//take from order and order_items
$sql = "
SELECT
  o.id,
  o.order_date,
  o.customer_name,
  -- aggregated items (format: 'Name xQ || Name2 xQ2')
  COALESCE(GROUP_CONCAT(CONCAT(oi.product_name, ' x', oi.quantity) SEPARATOR ' || '), o.product) AS product,
  -- total quantity (sum of order_items.quantity if present, else orders.quantity)
  COALESCE(SUM(oi.quantity), o.quantity) AS quantity,
  -- total amount: prefer sum(unit_price * qty), else orders.total_amount or orders.price
  COALESCE(ROUND(SUM(oi.unit_price * oi.quantity), 2), o.total_amount, o.price) AS total_amount,
  o.price AS price_per_unit,
  o.status
FROM orders o
LEFT JOIN order_items oi ON oi.order_id = o.id
$where
GROUP BY o.id
ORDER BY o.id DESC
";

// Prepare with dynamic params
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("SQL prepare failed: " . $conn->error);
}
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$orders = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Count total orders
$res2 = $conn->query("SELECT COUNT(*) AS total_orders FROM orders WHERE deleted_at IS NULL");
$row2 = $res2->fetch_assoc();
$totalOrders = (int)$row2['total_orders'];
$res3 = $conn->query("SELECT COUNT(*) AS total_returns FROM orders WHERE status = 'Returned' AND deleted_at IS NULL");
$row3 = $res3 ? $res3->fetch_assoc() : null; $totalReturns = (int)($row3['total_returns'] ?? 0);
// total distinct customers from orders table 
$res4 = $conn->query("SELECT COUNT(DISTINCT customer_name) AS total_customers FROM orders WHERE deleted_at IS NULL"); $row4 = $res4 ? $res4->fetch_assoc() : null; $totalCustomers = (int)($row4['total_customers'] ?? 0);
?>
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
      <div class="search-bar"><input id="globalSearch" type="text" placeholder="Search by customer, status or ID..." />
      </div>
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
            <form method="get"  class="filter-bar">
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
              <th>Price</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="orderTable">
            <?php if(empty($orders)): ?>
              <tr><td colspan="7" style="text-align:center;padding:18px">No orders found</td></tr>
            <?php else: foreach($orders as $o): ?>
              <tr>
                <td><?= htmlspecialchars($o['id']) ?></td>
                <td><?= htmlspecialchars($o['customer_name']) ?></td>
                <td><?= htmlspecialchars($o['product']) ?></td>
                <td><?= (int)$o['quantity'] ?></td>
                <td><?= number_format((float)$o['total_amount'], 2) ?></td>  
                <td><?= htmlspecialchars($o['status']) ?></td>
                <td>
                  <!-- View (data attributes) -->
                  <button class="btnview"
                    type="button"
                    data-id="<?= htmlspecialchars($o['id']) ?>"
                    data-order-date="<?= htmlspecialchars($o['order_date']) ?>"
                    data-customer="<?= htmlspecialchars($o['customer_name']) ?>"
                    data-product="<?= htmlspecialchars($o['product']) ?>"
                    data-quantity="<?= (int)$o['quantity'] ?>"
                    data-total="<?= htmlspecialchars(number_format((float)$o['total_amount'],2,'.','')) ?>"
                    data-status="<?= htmlspecialchars($o['status']) ?>">
                    <i class="fa-solid fa-eye"></i>
                  </button>

                  <!-- Update (data attributes) -->
                  <button class="btnupdate"
                    type="button"
                    data-id="<?= htmlspecialchars($o['id']) ?>"
                    data-order-date="<?= htmlspecialchars($o['order_date']) ?>"
                    data-customer="<?= htmlspecialchars($o['customer_name']) ?>"
                    data-product="<?= htmlspecialchars($o['product']) ?>"
                    data-quantity="<?= (int)$o['quantity'] ?>"
                    data-total="<?= htmlspecialchars(number_format((float)$o['total_amount'],2,'.','')) ?>"
                    data-status="<?= htmlspecialchars($o['status']) ?>">
                    <i class="fa-regular fa-pen-to-square"></i>
                  </button>

                 <!-- Return -->
                  <button class="btnreturn"
                    type="button"
                    data-id="<?= htmlspecialchars($o['id']) ?>"
                    data-quantity="<?= (int)$o['quantity'] ?>"
                    data-total="<?= htmlspecialchars(number_format((float)$o['total_amount'],2,'.','')) ?>"
                    title="Process return for order #<?= htmlspecialchars($o['id']) ?>">
                    <i class="fa-solid fa-rotate-left"></i>
                  </button>

                  <!-- Delete -->
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
                <option>Completed</option>
                <option>Pending</option>
                <option>Cancelled</option>
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

   
  <!-- Modal Add/Edit for ORDERS (uses your .modal-backdrop/.modal styles) -->
  <div class="modal-backdrop" id="orderModalBackdrop">
    <div class="modal">
      <h2 id="orderModalTitle">Add Order</h2>
      <form id="orderForm" method="post" style="margin-top:12px">
        <input type="hidden" name="action" id="order_form_action" value="add">
        <input type="hidden" name="id" id="order_form_id" value="0">

        <div class="form-grid">
          <div><label>Date</label><input id="order_form_date" name="order_date" type="date"></div>
          <div><label>Quantity</label><input id="order_form_quantity" name="quantity" type="number" min="1" value="1"></div>
          <div class="full"><label>Price</label><input id="order_form_price" name="price" type="number" step="0.01" min="0" value="0.00" required></div>
         <div class="full"><label>Customer</label><input id="order_form_customer" name="customer_name" type="text" required></div>
          <div class="full"><label>Product</label><input id="order_form_product" name="product" type="text" required></div>
          <div class="full">
            <label>Status</label>
            <select id="order_form_status" name="status">
                <option>Order Received</option>
                <option>Payment Confirmed</option>
                <option>Queued for Baking</option>
                <option>In Preparation</option>
                <option>Decorating</option>
                <option>Ready for Pickup</option>
                <option>Out for Delivery</option>
                <option>Completed</option>
                <option>Cancelled</option>
                <option>Refunded</option>
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
        <div><label>Return Date</label><input name="return_date" id="return_date" type="date" value="<?= date('Y-m-d') ?>"></div>
        <div><label>Quantity to return</label><input name="return_quantity" id="return_quantity" type="number" min="1" value="1"></div>
        <div class="full"><label>Refund Amount</label><input name="refund_amount" id="return_refund_amount" type="number" step="0.01" min="0" value="0.00"></div>
        <div class="full"><label>Reason</label><textarea name="return_reason" id="return_reason" rows="3"></textarea></div>
      </div>
      <div class="footer" style="margin-top:12px">
        <button type="button" class="btn light" onclick="closeReturnModal()">Cancel</button>
        <button type="submit" class="btn">Save Return</button>
      </div>
    </form>
  </div>
</div>


  <!-- =========== Your existing JS (unchanged) =========== -->
<script>
  document.addEventListener('DOMContentLoaded', () => {
  const orderModalBackdrop = document.getElementById('orderModalBackdrop');
  const orderForm = document.getElementById('orderForm');
  const inAction = (val) => document.getElementById('order_form_action').value = val;
  const inId = (val) => document.getElementById('order_form_id').value = val;
  const inDate = (val) => document.getElementById('order_form_date').value = val;
  const inCustomer = (val) => document.getElementById('order_form_customer').value = val;
  const inProduct = (val) => document.getElementById('order_form_product').value = val;
  const inQuantity = (val) => document.getElementById('order_form_quantity').value = val;
  const inPrice = (val) => {
    const el = document.getElementById('order_form_price');
    if (el) el.value = (val === undefined || val === null) ? '0.00' : Number(val).toFixed(2);
  };
  const inStatus = (val) => document.getElementById('order_form_status').value = val;
  const saveBtn = () => document.querySelector('#orderModalBackdrop .footer .btn:not(.light)');
  const today = () => new Date().toISOString().slice(0,10);
  // Also ensure that after server submit (page reload) modal is closed
  // ----- wire data-* buttons to modal functions (robust) -----
function parseAndCallOpen(btn, fn) {
  const ds = btn.dataset;
  // dataset properties: orderDate, customer, product, quantity, price, status, id
  const id = ds.id;
  const order_date = ds.orderDate || '';
  const customer = ds.customer || '';
  const product = ds.product || '';
  const quantity = ds.quantity || '1';
  const price = ds.price || '0.00';
  const status = ds.status || '';
  try {
    fn(id, order_date, customer, product, Number(quantity), price, status);
  } catch (err) {
    console.error('Failed to call modal function', err, { id, order_date, customer, product, quantity, price, status });
  }
}
// Tab wiring with alias fallback
(function(){
  // alias map: map sample data-page values to actual panel ids in your page
  const panelAlias = {
    'sales-dashboard': 'orders',        
    'sales-analysis': 'sales-analysis', 
    'sales-export': 'sales-export'      
  };

  function activatePanelByName(name) {
    // if direct match exists use it, otherwise try alias map
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

  // wire buttons
  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      const page = btn.dataset.page;
      activatePanelByName(page);
    });
  });

  // optional: activate initial panel from whichever has .active
  const initial = document.querySelector('.tab-btn.active')?.dataset.page || document.querySelector('.panel.active')?.id;
  if (initial) activatePanelByName(initial);
})();


// ---------- Return modal wiring (fixed) ----------
(function(){
  const returnModalBackdrop = document.getElementById('returnModalBackdrop');
  const returnForm = document.getElementById('returnForm');
  const inputReturnQty = document.getElementById('return_quantity');
  const inputRefund = document.getElementById('return_refund_amount');
  const inputReturnDate = document.getElementById('return_date');

  function openReturnModal(id, origQty, price){
    document.getElementById('returnModalTitle').textContent = 'Return Order #' + id;
    document.getElementById('return_order_id').value = String(id);

    // set min and max properly
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
    // focus quantity for convenience
    if (inputReturnQty) inputReturnQty.focus();
  }

  window.closeReturnModal = function(){ returnModalBackdrop.style.display = 'none'; };

  // Use event delegation in case buttons are dynamic
  document.addEventListener('click', function(e){
    const btn = e.target.closest && e.target.closest('.btnreturn');
    if (!btn) return;
    const ds = btn.dataset || {};
    const id = ds.id || btn.getAttribute('data-id');
    const qty = Number(ds.quantity || btn.getAttribute('data-quantity') || 1);
    const price = Number(ds.price || btn.getAttribute('data-price') || 0);
    openReturnModal(id, qty, price);
  });

  // client-side validation before submit
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
      // allow normal form POST to server
    });
  }

  // close when clicking backdrop (only when click the backdrop itself)
  if (returnModalBackdrop) {
    returnModalBackdrop.addEventListener('click', (ev) => { if (ev.target === returnModalBackdrop) closeReturnModal(); });
  }
})();

// ---------- Export utilities (works on the orders table DOM) ----------
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
    setTimeout(()=>URL.revokeObjectURL(a.href), 1000);
  }

  // wire export buttons (IDs in your export panel: expCsv, expXlsx, expPdf)
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


  // Open Add
  window.openOrderAdd = function(){
    document.getElementById('orderModalTitle').textContent = 'Add Order';
    inAction('add');
    inId('0');
    inDate(today());
    inCustomer('');
    inProduct('');
    inQuantity('1');
    inPrice('0.00');
    inStatus('Pending');
    // ensure form enabled
    enableFormFields();
    if (saveBtn()) saveBtn().style.display = '';
    orderModalBackdrop.style.display = 'flex';
  };

  // Open Edit - called by onclick attributes in PHP rows
  // signature now includes price
  window.openOrderEdit = function(id, order_date, customer, product, quantity, price, status){
    console.log('openOrderEdit called with', id, order_date, customer, product, quantity, price, status);
    document.getElementById('orderModalTitle').textContent = 'Edit Order #' + id;
    inAction('edit');
    inId(String(id));
    inDate(order_date || today());
    inCustomer(customer || '');
    inProduct(product || '');
    inQuantity(String(quantity ?? 1));
    inPrice(price ?? '0.00');
    inStatus(status || 'Pending');
    enableFormFields();
    if (saveBtn()) saveBtn().style.display = '';
    orderModalBackdrop.style.display = 'flex';
  };

  // Open View (read-only) — accepts price param too
  window.openOrderView = function(id, order_date, customer, product, quantity, price, status){
    console.log('openOrderView called with', id, order_date, customer, product, quantity, price, status);
    document.getElementById('orderModalTitle').textContent = 'View Order #' + id;
    inAction('view');
    inId(String(id));
    inDate(order_date || today());
    inCustomer(customer || '');
    inProduct(product || '');
    inQuantity(String(quantity ?? 1));
    inPrice(price ?? '0.00');
    inStatus(status || '');
    // disable inputs & hide Save
    disableFormFields();
    const s = saveBtn();
    if (s) s.style.display = 'none';
    orderModalBackdrop.style.display = 'flex';
  };

  function disableFormFields(){
    ['order_form_date','order_form_customer','order_form_product','order_form_quantity','order_form_price','order_form_status'].forEach(n=>{
      const el = document.getElementById(n);
      if (el) el.setAttribute('disabled','disabled');
    });
  }
  function enableFormFields(){
    ['order_form_date','order_form_customer','order_form_product','order_form_quantity','order_form_price','order_form_status'].forEach(n=>{
      const el = document.getElementById(n);
      if (el) el.removeAttribute('disabled');
    });
  }

  // Close modal
  window.closeOrderModal = function(){
    enableFormFields();
    const s = saveBtn();
    if (s) s.style.display = '';
    orderModalBackdrop.style.display = 'none';
  };

  // Prevent submission when in view mode; otherwise allow normal POST to server
  if (orderForm) {
    orderForm.addEventListener('submit', function(e){
      const act = document.getElementById('order_form_action').value;
      if (act === 'view') {
        e.preventDefault();
        return false;
      }
      // otherwise let the form submit (POST to same page)
    });
  }

  // Close when clicking backdrop
  if (orderModalBackdrop) {
    orderModalBackdrop.addEventListener('click', (ev) => {
      if (ev.target === orderModalBackdrop) closeOrderModal();
    });
  }

  // Close on ESC
  document.addEventListener('keydown', (ev) => {
    if (ev.key === 'Escape' && orderModalBackdrop.style.display === 'flex') {
      closeOrderModal();
    }
  });

  // GLOBAL SEARCH: filter server-rendered rows (search across all visible columns)
  const globalSearch = document.getElementById('globalSearch');
  const tbody = document.getElementById('orderTable');

  function filterTable() {
    const q = (globalSearch?.value || '').trim().toLowerCase();
    if (!tbody) return;
    const rows = Array.from(tbody.querySelectorAll('tr'));
    if (!q) {
      rows.forEach(r => r.style.display = '');
      return;
    }
    rows.forEach(r => {
      const tds = Array.from(r.querySelectorAll('td'));
      if (!tds.length) { r.style.display = ''; return; }
      // combine all cell text for robust matching (includes price if present)
      const rowText = tds.map(td => (td.textContent || '').toLowerCase()).join(' ');
      const match = rowText.includes(q);
      r.style.display = match ? '' : 'none';
    });
  }

  if (globalSearch) {
    globalSearch.addEventListener('input', filterTable);
  }

  // If the page has "Add" UI element with id btnAdd, wire it
  const btnAdd = document.getElementById('btnAdd');
  if (btnAdd) btnAdd.addEventListener('click', openOrderAdd);

  // Also ensure that after server submit (page reload) modal is closed
  closeOrderModal();
});
</script>
</body>

</html>
