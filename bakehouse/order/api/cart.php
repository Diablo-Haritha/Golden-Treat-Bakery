<?php
// api/cart.php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../db.php'; // adapt if your db.php is in another folder

// determine cart user token (logged-in or temp)
$cart_user = null;
if (!empty($_SESSION['user_id'])) $cart_user = (string) $_SESSION['user_id'];
elseif (!empty($_SESSION['temp_user_id'])) $cart_user = (string) $_SESSION['temp_user_id'];
else {
    echo json_encode(['items'=>[]]);
    exit;
}

$stmt = $conn->prepare("
  SELECT c.product_id, c.quantity as qty, p.name, p.price, p.quantity as stock, p.image
  FROM cart c
  JOIN products p ON p.id = c.product_id
  WHERE c.user_id = ?
");
$stmt->bind_param('s', $cart_user);
$stmt->execute();
$res = $stmt->get_result();
$items = [];
while ($r = $res->fetch_assoc()) {
  $items[] = [
    'product_id' => (int)$r['product_id'],
    'name' => $r['name'],
    'price' => (float)$r['price'],
    'qty' => (int)$r['qty'],
    'stock' => (int)$r['stock'],
    'image' => $r['image']
  ];
}
$stmt->close();
echo json_encode(['items' => $items]);
