<?php
session_start();
require 'db.php';
$order_id = intval($_GET['order_id'] ?? 0);
if (!$order_id) { echo "Invalid order."; exit; }

$stmt = $conn->prepare("SELECT order_number, created_at, price, total_amount, order_summary FROM orders WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $order_id);
$stmt->execute();
$res = $stmt->get_result();
$order = $res->fetch_assoc();
$stmt->close();
if (!$order) { echo "Order not found."; exit; }

$summary = json_decode($order['order_summary'] ?? '{}', true);
?>
<!doctype html><html><head><meta charset="utf-8"><title>Order Received</title>
<link rel="stylesheet" href="css/style.css"></head><body>
  <main class="container" style="padding:24px;">
    <h1>Thanks — your order has been placed</h1>
    <p>Order number: <strong><?php echo htmlspecialchars($order['order_number'] ?? $order_id); ?></strong></p>
    <p>Placed: <?php echo htmlspecialchars($order['created_at']); ?></p>
    <p>Total: LKR <?php echo number_format($order['price'] ?: $order['total_amount'], 2); ?></p>

    <h3>Items</h3>
    <?php if (!empty($summary['items'])): ?>
      <ul>
        <?php foreach ($summary['items'] as $it): ?>
          <li><?php echo htmlspecialchars($it['product_name'] ?? $it['name']); ?> — <?php echo (int)$it['qty']; ?> × LKR <?php echo number_format($it['unit_price'] ?? $it['price'], 2); ?> = LKR <?php echo number_format($it['line_total'] ?? (($it['qty'] * ($it['unit_price'] ?? $it['price']))), 2); ?></li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p>No item summary available.</p>
    <?php endif; ?>

    <p><a href="index.php">Continue shopping</a></p>
  </main>
</body></html>
