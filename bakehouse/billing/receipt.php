<?php
// billing/receipt.php
require_once 'db.php';

if (!isset($_GET['bill_id'])) {
    die('Bill ID required');
}

$bill_id = (int)$_GET['bill_id'];

// Fetch bill header
$bill = $pdo->prepare("SELECT * FROM bills WHERE id = ?");
$bill->execute([$bill_id]);
$billData = $bill->fetch();

if (!$billData) die('Bill not found');

// Fetch items
$items = $pdo->prepare("SELECT * FROM bill_items WHERE bill_id = ?");
$items->execute([$bill_id]);
$billItems = $items->fetchAll();

// Fetch settings
$settings = $pdo->query("SELECT * FROM settings WHERE id = 1")->fetch();
$vatPercent = $settings['vat_percent'] ?? 0;
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Receipt #<?= $bill_id ?></title>
  <style>
    body {
      font-family: 'Courier New', monospace;
      max-width: 400px;
      margin: 0 auto;
      padding: 10px;
      font-size: 14px;
    }
    .center { text-align: center; }
    .bold { font-weight: bold; }
    .divider { border-bottom: 1px dashed #000; margin: 8px 0; }
    .item { display: flex; justify-content: space-between; }
    .total { margin-top: 10px; }
  </style>
</head>
<body onload="window.print()">
  <div class="center bold"><?= htmlspecialchars($settings['shop_name']) ?></div>
  <div class="center"><?= htmlspecialchars($settings['shop_address']) ?></div>
  <div class="center">Tel: <?= htmlspecialchars($settings['shop_tel']) ?></div>
  <div class="center">Date: <?= date('Y-m-d H:i', strtotime($billData['created_at'])) ?></div>
  
  <div class="divider"></div>
  
  <div class="item bold">
    <span>Item</span>
    <span>Qty × Price = Total</span>
  </div>
  
  <div class="divider"></div>
  
  <?php foreach ($billItems as $item): ?>
    <div class="item">
      <span><?= htmlspecialchars(substr($item['item_name'], 0, 20)) ?></span>
      <span><?= $item['qty'] ?> × <?= number_format($item['price'], 2) ?> = <?= number_format($item['price'] * $item['qty'], 2) ?></span>
    </div>
  <?php endforeach; ?>
  
  <?php
    $subtotal = array_sum(array_map(fn($i) => $i['price'] * $i['qty'], $billItems));
    $vat = ($subtotal * $vatPercent) / 100;
    $total = $subtotal + $vat;
  ?>
  
  <div class="divider"></div>
  <div class="item total">
    <span>Subtotal:</span>
    <span>LKR <?= number_format($subtotal, 2) ?></span>
  </div>
  <div class="item total">
    <span>VAT (<?= $vatPercent ?>%):</span>
    <span>LKR <?= number_format($vat, 2) ?></span>
  </div>
  <div class="item total bold">
    <span>TOTAL:</span>
    <span>LKR <?= number_format($total, 2) ?></span>
  </div>
  
  <div class="divider"></div>
  <div class="center"><?= htmlspecialchars($settings['thank_note']) ?></div>
  <div class="center">Thank you! Visit again!</div>
</body>
</html>