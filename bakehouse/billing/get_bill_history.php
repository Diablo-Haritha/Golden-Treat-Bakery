<!-- Simple history page -->
<?php
$bills = $pdo->query("
  SELECT b.id, b.customer_name, b.created_at, 
         SUM(bi.price * bi.qty) as total
  FROM bills b
  JOIN bill_items bi ON b.id = bi.bill_id
  GROUP BY b.id
  ORDER BY b.created_at DESC
  LIMIT 50
")->fetchAll();
?>

<table border="1" style="width:100%; border-collapse: collapse;">
  <tr><th>ID</th><th>Customer</th><th>Date</th><th>Total</th></tr>
  <?php foreach ($bills as $b): ?>
  <tr>
    <td><?= $b['id'] ?></td>
    <td><?= htmlspecialchars($b['customer_name']) ?></td>
    <td><?= $b['created_at'] ?></td>
    <td>LKR <?= number_format($b['total'], 2) ?></td>
  </tr>
  <?php endforeach; ?>
</table>