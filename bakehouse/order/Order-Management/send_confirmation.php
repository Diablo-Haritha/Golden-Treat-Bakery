<?php
// send-confirmation.php
// Handle checkout form post: create order, order_items, reduce stock, clear cart.
// IMPORTANT: Back up your DB before running on production.

ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

// DB config - edit if needed
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'golden_treat';

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_error) {
    http_response_code(500);
    die('DB connection failed: ' . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

// Determine cart user id (guest or logged-in)
$cart_user = null;
if (!empty($_SESSION['user_id'])) {
    // logged in user - store as string for cart.user_id compatibility
    $cart_user = (string) $_SESSION['user_id'];
} elseif (!empty($_SESSION['temp_user_id'])) {
    $cart_user = (string) $_SESSION['temp_user_id'];
} else {
    // create temp id if somehow missing
    $_SESSION['temp_user_id'] = uniqid('guest_', true);
    $cart_user = $_SESSION['temp_user_id'];
}

// Gather posted fields (basic validation)
$delivery_time = isset($_POST['delivery_time']) ? trim($_POST['delivery_time']) : null;
$delivery_instructions = isset($_POST['delivery_instructions']) ? trim($_POST['delivery_instructions']) : null;
$payment_method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : 'cod';
$card_number = isset($_POST['card_number']) ? preg_replace('/\s+/', '', $_POST['card_number']) : '';
$exp_month = isset($_POST['exp_month']) ? trim($_POST['exp_month']) : '';
$exp_year = isset($_POST['exp_year']) ? trim($_POST['exp_year']) : '';
$cvv = isset($_POST['cvv']) ? trim($_POST['cvv']) : '';
$order_summary_json = isset($_POST['order_summary']) ? trim($_POST['order_summary']) : '';

// Optional customer fields (if your frontend supplies them)
$delivery_name = isset($_POST['delivery_name']) ? trim($_POST['delivery_name']) : null;
$delivery_phone = isset($_POST['delivery_phone']) ? trim($_POST['delivery_phone']) : null;
$delivery_address = isset($_POST['delivery_address']) ? trim($_POST['delivery_address']) : null;

// If delivery fields not posted, try to get them from users table for logged in user
if (empty($delivery_name) || empty($delivery_phone) || empty($delivery_address)) {
    if (!empty($_SESSION['user_id'])) {
        $stmt = $conn->prepare("SELECT full_name, mobile, address FROM users WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $_SESSION['user_id']);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && $row = $res->fetch_assoc()) {
                if (empty($delivery_name)) $delivery_name = $row['full_name'];
                if (empty($delivery_phone)) $delivery_phone = $row['mobile'];
                if (empty($delivery_address)) $delivery_address = $row['address'];
            }
            $stmt->close();
        }
    }
}

// Basic validation: ensure cart has items
$stmt = $conn->prepare("
    SELECT c.product_id, c.quantity AS qty, p.name AS product_name, p.price, p.quantity AS stock
    FROM cart c
    JOIN products p ON p.id = c.product_id
    WHERE c.user_id = ?
    FOR UPDATE
");
if (!$stmt) {
    http_response_code(500);
    die("DB error: " . $conn->error);
}
$conn->begin_transaction(MYSQLI_TRANS_START_READ_WRITE);
$stmt->bind_param('s', $cart_user);
$stmt->execute();
$res = $stmt->get_result();
$cart_items = [];
while ($r = $res->fetch_assoc()) {
    $cart_items[] = $r;
}
$stmt->close();

if (count($cart_items) === 0) {
    $conn->rollback();
    // redirect back with message or show error
    $_SESSION['checkout_error'] = "Your cart is empty.";
    header('Location: product.php');
    exit;
}

// compute totals and check stock
$subtotal = 0.0;
$total_qty = 0;
foreach ($cart_items as $it) {
    $line_total = floatval($it['price']) * intval($it['qty']);
    $subtotal += $line_total;
    $total_qty += intval($it['qty']);
    if (intval($it['qty']) > intval($it['stock'])) {
        $conn->rollback();
        $_SESSION['checkout_error'] = "Insufficient stock for product: " . htmlspecialchars($it['product_name']);
        header('Location: product.php');
        exit;
    }
}

$delivery_fee = 150.00; // you can make this dynamic
$grand_total = round($subtotal + $delivery_fee, 2);

// Prepare order insert
$order_date = date('Y-m-d');
$customer_name = $delivery_name ?: 'Guest';
$mobile = $delivery_phone ?: null;
$product_summary = (count($cart_items) === 1) ? $cart_items[0]['product_name'] : 'Multiple items';

// Insert into orders table (fields chosen to match your existing dump's detailed orders table)
$insert_order_sql = "INSERT INTO `orders` 
    (`order_date`, `customer`, `product`, `quantity`, `price`, `original_price`, `status`, `created_at`, `mobile`)
    VALUES (?, ?, ?, ?, ?, ?, 'Order Received', NOW(), ?)";
$stmt = $conn->prepare($insert_order_sql);
if (!$stmt) {
    $conn->rollback();
    http_response_code(500);
    die("DB error (order prepare): " . $conn->error);
}
$price_decimal = number_format($grand_total, 2, '.', '');
$original_price_decimal = $price_decimal;
$stmt->bind_param('sssidds', $order_date, $customer_name, $product_summary, $total_qty, $price_decimal, $original_price_decimal, $mobile);
if (!$stmt->execute()) {
    $conn->rollback();
    http_response_code(500);
    die("DB error (order execute): " . $stmt->error);
}
$order_insert_id = $stmt->insert_id;
$stmt->close();

// Insert order_items if table exists
$has_order_items = false;
$res = $conn->query("SHOW TABLES LIKE 'order_items'");
if ($res && $res->num_rows > 0) {
    $has_order_items = true;
}

if ($has_order_items) {
    $oi_stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity, line_total, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    if (!$oi_stmt) {
        $conn->rollback();
        http_response_code(500);
        die("DB error (order_items prepare): " . $conn->error);
    }
} else {
    $oi_stmt = null;
}

// For each cart item: reduce product stock and optionally insert order_items
$update_product_stmt = $conn->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ?");
if (!$update_product_stmt) {
    $conn->rollback();
    http_response_code(500);
    die("DB error (product update prepare): " . $conn->error);
}

foreach ($cart_items as $it) {
    $pid = intval($it['product_id']);
    $qty = intval($it['qty']);
    $price = floatval($it['price']);
    // reduce stock (we did FOR UPDATE earlier to lock rows)
    $update_product_stmt->bind_param('ii', $qty, $pid);
    if (!$update_product_stmt->execute()) {
        $conn->rollback();
        http_response_code(500);
        die("DB error (update product): " . $update_product_stmt->error);
    }
    // optional order_items insert
    if ($oi_stmt) {
        $line_total = round($price * $qty, 2);
        $oi_stmt->bind_param('iisdid', $order_insert_id, $pid, $it['product_name'], $price, $qty, $line_total);
        if (!$oi_stmt->execute()) {
            $conn->rollback();
            http_response_code(500);
            die("DB error (insert order_items): " . $oi_stmt->error);
        }
    }
}
if ($oi_stmt) $oi_stmt->close();
$update_product_stmt->close();

// Optionally record order status history if table exists
$res = $conn->query("SHOW TABLES LIKE 'order_status_history'");
if ($res && $res->num_rows > 0) {
    $osh_stmt = $conn->prepare("INSERT INTO order_status_history (order_id, old_status, new_status, changed_by, note, created_at) VALUES (?, ?, ?, NULL, ?, NOW())");
    if ($osh_stmt) {
        $osh_stmt->bind_param('isss', $order_insert_id, $null_old='','Order Received', $note = 'Order created via web checkout');
        $osh_stmt->execute();
        $osh_stmt->close();
    }
}

// Clear cart for this user
$del_stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
if (!$del_stmt) {
    $conn->rollback();
    http_response_code(500);
    die("DB error (delete cart prepare): " . $conn->error);
}
$del_stmt->bind_param('s', $cart_user);
if (!$del_stmt->execute()) {
    $conn->rollback();
    http_response_code(500);
    die("DB error (delete cart execute): " . $del_stmt->error);
}
$del_stmt->close();

// Commit all changes
$conn->commit();

// Optionally: queue SMS by inserting into sms_queue (if exists)
$res = $conn->query("SHOW TABLES LIKE 'sms_queue'");
if ($res && $res->num_rows > 0) {
    $sms_stmt = $conn->prepare("INSERT INTO sms_queue (order_id, mobile, message, attempts, next_try, created_at) VALUES (?, ?, ?, 0, NOW(), NOW())");
    if ($sms_stmt) {
        $sms_msg = "Thanks $customer_name — your order #$order_insert_id has been placed. Total LKR $price_decimal.";
        $mobile_for_sms = $mobile ?: '';
        $sms_stmt->bind_param('iss', $order_insert_id, $mobile_for_sms, $sms_msg);
        $sms_stmt->execute();
        $sms_stmt->close();
    }
}

// Redirect to a success/thank-you page (create order-success.php to display details)
header("Location: order-success.php?order_id=" . urlencode($order_insert_id));
exit;
?>