<?php
// api/update_order_status.php
// POST JSON: { "order_id": 123, "new_status": "Out for Delivery", "changed_by": 1, "note": "..." }
// Auth: send header X-API-KEY: your_api_key_here

// CONFIG - set these
define('API_KEY', 'REPLACE_WITH_YOUR_API_KEY');
define('TWILIO_SID', 'REPLACE_TWILIO_SID');        // if using Twilio
define('TWILIO_TOKEN', 'REPLACE_TWILIO_AUTH_TOKEN');
define('TWILIO_FROM', '+1234567890');             // your Twilio number
// OR configure a generic gateway URL below
define('GENERIC_SMS_GATEWAY_URL', '');            // e.g. https://sms-gateway.example/send

header('Content-Type: application/json; charset=utf-8');

// simple API key check
$clientKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($clientKey !== API_KEY) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}
$order_id = isset($data['order_id']) ? (int)$data['order_id'] : 0;
$new_status = trim($data['new_status'] ?? '');
$changed_by = isset($data['changed_by']) ? (int)$data['changed_by'] : null;
$note = trim($data['note'] ?? '');

if ($order_id <= 0 || $new_status === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'order_id and new_status required']);
    exit;
}

// DB connect - adjust to your credentials
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'Order';
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DB connection failed']);
    exit;
}
$conn->set_charset('utf8mb4');

require_once __DIR__ . '/../sms_helpers.php'; // helper functions (below) - adjust path if needed

// get previous status
$stmt = $conn->prepare("SELECT status, customer FROM orders WHERE id = ?");
$stmt->bind_param('i', $order_id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();
if (!$row) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Order not found']);
    exit;
}
$old_status = $row['status'];
$customerName = $row['customer'];

// if no change -> still record maybe, but here return early
if ($old_status === $new_status) {
    echo json_encode(['ok' => true, 'changed' => false, 'message' => 'No status change']);
    exit;
}

// transaction: update orders + insert history
$conn->begin_transaction();
$ok = true;
try {
    $upd = $conn->prepare("UPDATE orders SET status = ?, updated_at = CURRENT_TIMESTAMP() WHERE id = ?");
    $upd->bind_param('si', $new_status, $order_id);
    if (!$upd->execute()) throw new Exception('Update failed: ' . $upd->error);
    $upd->close();

    $ins = $conn->prepare("INSERT INTO order_status_history (order_id, old_status, new_status, changed_by, note, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $ins->bind_param('issis', $order_id, $old_status, $new_status, $changed_by, $note);
    if (!$ins->execute()) throw new Exception('History insert failed: ' . $ins->error);
    $ins->close();

    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    exit;
}

// find customer mobile (tries users.full_name match, then orders.mobile if present)
$mobile = sms_get_mobile_for_order($conn, $order_id, $customerName);

// send SMS if we have a number
$sms_sent = false;
$sms_result = null;
$message = "Order #{$order_id} status updated: {$old_status} → {$new_status}.";

if ($mobile) {
    // prefer Twilio if configured
    if (TWILIO_SID && TWILIO_TOKEN && TWILIO_FROM) {
        $sms_result = sms_send_twilio($mobile, $message);
        $sms_sent = ($sms_result['ok'] ?? false);
    } elseif (GENERIC_SMS_GATEWAY_URL) {
        $sms_result = sms_send_generic($mobile, $message);
        $sms_sent = ($sms_result['ok'] ?? false);
    } else {
        // no gateway configured
        $sms_result = ['ok' => false, 'error' => 'No SMS gateway configured on server'];
    }
    // record log
    sms_log($conn, $order_id, $mobile, $message, $sms_sent ? 'sent' : 'failed', json_encode($sms_result));
}

echo json_encode([
    'ok' => true,
    'changed' => true,
    'order_id' => $order_id,
    'old_status' => $old_status,
    'new_status' => $new_status,
    'sms_sent' => $sms_sent,
    'sms_result' => $sms_result
]);
