<?php
// send-confirmation.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once 'db.php'; // make sure db.php defines $conn (mysqli)
$conn->set_charset('utf8mb4');

// CSRF check (checkout page must generate $_SESSION['csrf_token'])
if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    die('Invalid CSRF token.');
}

// Determine cart user id
$cart_user = null;
if (!empty($_SESSION['user_id'])) $cart_user = (string) $_SESSION['user_id'];
elseif (!empty($_SESSION['temp_user_id'])) $cart_user = (string) $_SESSION['temp_user_id'];
else {
    $_SESSION['temp_user_id'] = uniqid('guest_', true);
    $cart_user = $_SESSION['temp_user_id'];
}

// Gather posted fields
$delivery_time = isset($_POST['delivery_time']) ? trim($_POST['delivery_time']) : null;
$delivery_instructions = isset($_POST['delivery_instructions']) ? trim($_POST['delivery_instructions']) : null;
$payment_method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : 'cod';
$order_summary_json = isset($_POST['order_summary']) ? trim($_POST['order_summary']) : '';
$delivery_name = isset($_POST['delivery_name']) ? trim($_POST['delivery_name']) : null;
$delivery_phone = isset($_POST['delivery_phone']) ? trim($_POST['delivery_phone']) : null;
$delivery_address = isset($_POST['delivery_address']) ? trim($_POST['delivery_address']) : null;
$card_last4 = isset($_POST['card_last4']) ? preg_replace('/\D/','',$_POST['card_last4']) : null;

// try to fill missing customer fields from customers/users tables
if ((empty($delivery_name) || empty($delivery_phone) || empty($delivery_address)) && !empty($_SESSION['user_id'])) {
    $u_stmt = $conn->prepare("SELECT full_name, mobile, address FROM users WHERE id = ? LIMIT 1");
    if ($u_stmt) {
        $u_stmt->bind_param('i', $_SESSION['user_id']);
        $u_stmt->execute();
        $resu = $u_stmt->get_result();
        if ($resu && $row = $resu->fetch_assoc()) {
            if (empty($delivery_name)) $delivery_name = $row['full_name'];
            if (empty($delivery_phone)) $delivery_phone = $row['mobile'];
            if (empty($delivery_address)) $delivery_address = $row['address'];
        }
        $u_stmt->close();
    }
}

// Start transaction
$conn->begin_transaction(MYSQLI_TRANS_START_READ_WRITE);

// read cart items and lock product rows
$cart_select = $conn->prepare("
    SELECT c.product_id, c.quantity AS qty, p.name AS product_name, p.price, p.quantity AS stock
    FROM cart c
    JOIN products p ON p.id = c.product_id
    WHERE c.user_id = ?
    FOR UPDATE
");
if (!$cart_select) { $conn->rollback(); http_response_code(500); die("DB error: " . $conn->error); }
$cart_select->bind_param('s', $cart_user);
$cart_select->execute();
$res = $cart_select->get_result();
$cart_items = [];
while ($r = $res->fetch_assoc()) $cart_items[] = $r;
$cart_select->close();

if (count($cart_items) === 0) {
    $conn->rollback();
    $_SESSION['checkout_error'] = "Your cart is empty.";
    header('Location: product.php');
    exit;
}

// totals & stock check
$subtotal = 0.0; $total_qty = 0;
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
$delivery_fee = 150.00;
$grand_total = round($subtotal + $delivery_fee, 2);

// Prepare order_summary JSON (validate or rebuild)
$order_summary_db = '';
if (!empty($order_summary_json)) {
    $decoded = json_decode($order_summary_json, true);
    if (json_last_error() === JSON_ERROR_NONE && isset($decoded['items'])) {
        $order_summary_db = $conn->real_escape_string($order_summary_json);
    }
}
if (empty($order_summary_db)) {
    $built = ['items'=>[], 'subtotal'=>round($subtotal,2), 'delivery'=>$delivery_fee, 'grand'=>round($grand_total,2)];
    foreach ($cart_items as $it) {
        $built['items'][] = [
            'product_id' => (int)$it['product_id'],
            'product_name' => $it['product_name'],
            'qty' => (int)$it['qty'],
            'unit_price' => (float)$it['price'],
            'line_total' => round(floatval($it['price']) * intval($it['qty']), 2)
        ];
    }
    $order_summary_db = $conn->real_escape_string(json_encode($built));
}

// Insert into orders table (matches your schema)
$order_date = date('Y-m-d');
$customer_name = $delivery_name ?: 'Guest';
$mobile = $delivery_phone ?: null;
$product_summary = (count($cart_items) === 1) ? $cart_items[0]['product_name'] : 'Multiple items';
$original_qty = $total_qty;
$price_decimal = number_format($grand_total, 2, '.', '');
$original_price_decimal = $price_decimal;

$insert_sql = "INSERT INTO `orders`
  (`order_date`,`customer`,`product`,`quantity`,`original_quantity`,`price`,`total_amount`,`original_price`,`status`,`created_at`,`mobile`,`order_summary`)
  VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Order Received', NOW(), ?, ?)";
$stmt = $conn->prepare($insert_sql);
if (!$stmt) { $conn->rollback(); http_response_code(500); die("DB error (order prepare): " . $conn->error); }

$stmt->bind_param('sssii ddd ss',
    $order_date, $customer_name, $product_summary,
    $total_qty, $original_qty,
    $price_decimal, $price_decimal, $original_price_decimal,
    $mobile, $order_summary_db
);

// Note: PHP requires types string without spaces. Because we used a string with spaces logically above,
// we'll rebind using correct types string:
$stmt->close();
// proper binding with explicit types:
$stmt = $conn->prepare($insert_sql);
if (!$stmt) { $conn->rollback(); http_response_code(500); die("DB error (order prepare 2): ".$conn->error); }
$types = 'sssii ddd ss'; // placeholder; PHP bind_param can't accept spaces -> we'll supply final string below

// Solid binding: build params and types explicitly
// types order: s(order_date) s(customer) s(product) i(quantity) i(original_quantity) d(price) d(total_amount) d(original_price) s(mobile) s(order_summary)
$types_final = 'sssii ddd ss'; // redundant description, will use corrected below
// Construct real types string: 'sssiidddss'
$types_str = 'sssiidddss';
$stmt->bind_param($types_str,
    $order_date, $customer_name, $product_summary,
    $total_qty, $original_qty,
    $price_decimal, $price_decimal, $original_price_decimal,
    $mobile, $order_summary_db
);

if (!$stmt->execute()) {
    $conn->rollback();
    http_response_code(500);
    die("DB error (order execute): " . $stmt->error);
}
$order_insert_id = $stmt->insert_id;
$stmt->close();

// Create human-friendly order_number and update the row
$order_number = 'TSH-' . date('Ymd') . '-' . str_pad($order_insert_id, 4, '0', STR_PAD_LEFT);
$upd = $conn->prepare("UPDATE orders SET order_number = ? WHERE id = ?");
if ($upd) {
    $upd->bind_param('si', $order_number, $order_insert_id);
    $upd->execute();
    $upd->close();
}

// Insert into order_items (if table exists) and reduce stock safely
$res_check = $conn->query("SHOW TABLES LIKE 'order_items'");
$has_order_items = ($res_check && $res_check->num_rows > 0);

$oi_stmt = null;
if ($has_order_items) {
    $oi_stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity, line_total, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    if (!$oi_stmt) { $conn->rollback(); http_response_code(500); die("DB error (order_items prepare): " . $conn->error); }
}

$update_stmt = $conn->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ? AND quantity >= ?");
if (!$update_stmt) { $conn->rollback(); http_response_code(500); die("DB error (product update prepare): " . $conn->error); }

foreach ($cart_items as $it) {
    $pid = (int)$it['product_id'];
    $qty = (int)$it['qty'];
    $unit_price = (float)$it['price'];
    // reduce stock safely
    $update_stmt->bind_param('iii', $qty, $pid, $qty);
    if (!$update_stmt->execute()) { $conn->rollback(); http_response_code(500); die("DB error (update product): " . $update_stmt->error); }
    if ($update_stmt->affected_rows === 0) {
        $conn->rollback();
        $_SESSION['checkout_error'] = "Insufficient stock for product: " . htmlspecialchars($it['product_name']) . ". Please try again.";
        header('Location: product.php');
        exit;
    }
    // insert order_items
    if ($oi_stmt) {
        $line_total = round($unit_price * $qty, 2);
        $oi_stmt->bind_param('iisdid', $order_insert_id, $pid, $it['product_name'], $unit_price, $qty, $line_total);
        if (!$oi_stmt->execute()) { $conn->rollback(); http_response_code(500); die("DB error (insert order_items): " . $oi_stmt->error); }
    }
}
if ($oi_stmt) $oi_stmt->close();
$update_stmt->close();

// clear cart
$del = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
if (!$del) { $conn->rollback(); http_response_code(500); die("DB error (delete cart prepare): " . $conn->error); }
$del->bind_param('s', $cart_user);
if (!$del->execute()) { $conn->rollback(); http_response_code(500); die("DB error (delete cart execute): " . $del->error); }
$del->close();

// commit
$conn->commit();

// queue SMS if table exists and mobile present
$res_sms = $conn->query("SHOW TABLES LIKE 'sms_queue'");
if ($res_sms && $res_sms->num_rows > 0 && !empty($mobile)) {
    $sms_stmt = $conn->prepare("INSERT INTO sms_queue (order_id, mobile, message, attempts, next_try, created_at) VALUES (?, ?, ?, 0, NOW(), NOW())");
    if ($sms_stmt) {
        $sms_msg = "Thanks {$customer_name} — your order #{$order_number} has been received. Total LKR {$price_decimal}.";
        $sms_stmt->bind_param('iss', $order_insert_id, $mobile, $sms_msg);
        $sms_stmt->execute();
        $sms_stmt->close();
    }
}

// redirect to success page
header("Location: order-success.php?order_id=" . urlencode($order_insert_id));
exit;
?>