<?php
// sms_helpers.php
// Enhanced SMS helpers following the admin guide (customer notifications).
// - Uses Twilio when TWILIO_* env vars are set
// - Falls back to a generic HTTP provider stub (sms_send_generic) if needed
// - Provides lookup of mobile numbers (orders -> customers -> users)
// - Logs attempts to sms_logs table
//
// See AI guide: "Communication: send automated notifications to customers". :contentReference[oaicite:1]{index=1}

/**
 * Find a mobile number for an order.
 * Priority:
 *  1. orders.mobile (if present)
 *  2. customers.mobile (if you have a customers table)
 *  3. users.mobile (if processed_by or similar maps)
 *
 * Returns normalized phone string (as stored) or null.
 */
function sms_get_mobile_for_order($conn, $order_id, $customerName = '') {
    // 1) try orders table
    $stmt = $conn->prepare("SELECT mobile, customer FROM orders WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!empty($r['mobile'])) return trim($r['mobile']);
        // keep customer name if not provided
        if ($customerName === '' && !empty($r['customer'])) $customerName = $r['customer'];
    }

    // 2) try customers table (if exists) - lookup by name (best-effort)
    if ($customerName !== '') {
        $stmt = @$conn->prepare("SELECT mobile FROM customers WHERE name = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $customerName);
            $stmt->execute();
            $r = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!empty($r['mobile'])) return trim($r['mobile']);
        }
    }

    // 3) optional: try users table (if orders.processed_by links to users)
    $stmt = @$conn->prepare("SELECT u.mobile FROM users u JOIN orders o ON o.id = ? WHERE o.processed_by = u.id LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!empty($r['mobile'])) return trim($r['mobile']);
    }

    // not found
    return null;
}

/**
 * Send SMS via Twilio REST API.
 * Expects TWILIO_SID, TWILIO_TOKEN, TWILIO_FROM in environment.
 * Returns structured array: ['ok' => bool, 'http_code' => int, 'response' => mixed, 'error' => '...']
 */
function sms_send_twilio($to, $message) {
    $sid   = getenv('TWILIO_SID') ?: null;
    $token = getenv('TWILIO_TOKEN') ?: null;
    $from  = getenv('TWILIO_FROM') ?: null;

    if (!$sid || !$token || !$from) {
        return ['ok' => false, 'error' => 'twilio_not_configured'];
    }

    $url = "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json";

    $data = http_build_query([
        'To' => $to,
        'From' => $from,
        'Body' => $message
    ]);

    // Basic auth with SID:TOKEN
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, $sid . ':' . $token);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    // minimal timeouts for dev; change if needed
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $resp = curl_exec($ch);
    $err  = null;
    if ($resp === false) {
        $err = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 0;
        curl_close($ch);
        return ['ok' => false, 'http_code' => $httpCode, 'error' => 'curl_error', 'curl_error' => $err];
    }
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Twilio returns JSON
    $json = json_decode($resp, true);
    $ok = ($httpCode >= 200 && $httpCode < 300);
    return ['ok' => $ok, 'http_code' => $httpCode, 'response' => $json];
}

/**
 * Generic HTTP provider template (adapt for other SMS providers).
 * $cfg expects keys: url, method ('POST'|'GET'), field_map (assoc TO/FROM/BODY -> provider field names), headers (array)
 *
 * Example usage:
 * sms_send_generic('to','body', ['url'=>'https://api.example.com/send','method'=>'POST','field_map'=>['to'=>'phone','body'=>'text']])
 */
function sms_send_generic($to, $message, $cfg = []) {
    if (empty($cfg['url'])) return ['ok' => false, 'error' => 'no_provider_config'];

    $method = strtoupper($cfg['method'] ?? 'POST');
    $field_map = $cfg['field_map'] ?? ['to' => 'to', 'body' => 'body', 'from' => 'from'];
    $payload = [];

    foreach ($field_map as $k => $fieldName) {
        if ($k === 'to') $payload[$fieldName] = $to;
        elseif ($k === 'body') $payload[$fieldName] = $message;
        elseif ($k === 'from' && isset($cfg['from'])) $payload[$fieldName] = $cfg['from'];
    }

    $ch = curl_init($cfg['url']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if (!empty($cfg['headers']) && in_array('application/json', $cfg['headers'])) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $cfg['headers']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
        }
    }
    // optional headers
    if (!empty($cfg['headers'])) curl_setopt($ch, CURLOPT_HTTPHEADER, $cfg['headers']);

    $resp = curl_exec($ch);
    if ($resp === false) {
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 0;
        curl_close($ch);
        return ['ok' => false, 'http_code' => $code, 'error' => 'curl_error', 'curl_error' => $err];
    }
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // try json decode, otherwise return raw body
    $body = json_decode($resp, true) ?? $resp;
    return ['ok' => ($code >= 200 && $code < 300), 'http_code' => $code, 'response' => $body];
}

/**
 * Log SMS attempt into sms_logs table.
 * Table suggestion:
 * CREATE TABLE sms_logs (
 *   id INT AUTO_INCREMENT PRIMARY KEY,
 *   order_id INT DEFAULT NULL,
 *   mobile VARCHAR(50),
 *   message TEXT,
 *   status VARCHAR(32),
 *   meta JSON DEFAULT NULL,
 *   created_at DATETIME DEFAULT CURRENT_TIMESTAMP
 * );
 *
 * This function will attempt to insert; it will silently fail if the table doesn't exist.
 */
function sms_log($conn, $order_id, $to, $message, $status = 'unknown', $meta = '') {
    $stmt = @$conn->prepare("INSERT INTO sms_logs (order_id, mobile, message, status, meta, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    if (!$stmt) {
        // Table might not exist — for development, silently skip or log to error_log
        error_log("sms_log prepare failed: " . $conn->error);
        return false;
    }
    $stmt->bind_param("issss", $order_id, $to, $message, $status, $meta);
    $ok = $stmt->execute();
    if (!$ok) {
        error_log("sms_log execute failed: " . $stmt->error);
    }
    $stmt->close();
    return $ok;
}

/**
 * High-level helper: find mobile, send (Twilio preferred), and log result.
 * Returns structure: ['ok'=>bool, 'provider'=>'twilio'|'generic'|'none', 'result'=>...]
 */
function sms_send_and_log($conn, $order_id, $message, $opts = []) {
    $mobile = sms_get_mobile_for_order($conn, $order_id, $opts['customerName'] ?? '');
    if (!$mobile) {
        // nothing to send, but log as failed (no mobile)
        sms_log($conn, $order_id, '', $message, 'failed_no_mobile', json_encode(['note'=>'no mobile found']));
        return ['ok' => false, 'provider' => 'none', 'error' => 'no_mobile'];
    }

    // prefer Twilio if configured
    $sid   = getenv('TWILIO_SID') ?: null;
    $token = getenv('TWILIO_TOKEN') ?: null;
    $from  = getenv('TWILIO_FROM') ?: null;

    if ($sid && $token && $from) {
        $res = sms_send_twilio($mobile, $message);
        $status = ($res['ok'] ?? false) ? 'sent' : 'failed';
        sms_log($conn, $order_id, $mobile, $message, $status, json_encode($res));
        return ['ok' => ($res['ok'] ?? false), 'provider' => 'twilio', 'result' => $res];
    }

    // fallback: generic provider config passed in opts
    if (!empty($opts['provider_cfg'])) {
        $res = sms_send_generic($mobile, $message, $opts['provider_cfg']);
        $status = ($res['ok'] ?? false) ? 'sent' : 'failed';
        sms_log($conn, $order_id, $mobile, $message, $status, json_encode($res));
        return ['ok' => ($res['ok'] ?? false), 'provider' => 'generic', 'result' => $res];
    }

    // nothing configured
    sms_log($conn, $order_id, $mobile, $message, 'not_sent_no_provider', json_encode(['note' => 'no provider configured']));
    return ['ok' => false, 'provider' => 'none', 'error' => 'no_provider_configured'];
}
