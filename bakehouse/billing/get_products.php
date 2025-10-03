<?php
// billing/get_products.php
header('Content-Type: application/json');
require_once 'db.php'; // Adjust path to your DB connection

$stmt = $pdo->query("SELECT id, name, price FROM products WHERE visibility = 1 ORDER BY name");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($products);
?>