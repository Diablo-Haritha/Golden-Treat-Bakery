<?php
// billing/save_bill.php
header('Content-Type: application/json');
require_once 'db.php';

$input = json_decode(file_get_contents('php://input'), true);
$customer = $input['customer_name'] ?? 'Walk-in Customer';
$items = $input['items'] ?? [];

if (empty($items)) {
    echo json_encode(['success' => false, 'message' => 'No items in bill']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Insert bill
    $stmt = $pdo->prepare("INSERT INTO bills (customer_name) VALUES (?)");
    $stmt->execute([$customer]);
    $bill_id = $pdo->lastInsertId();

    // Insert items + deduct stock
    $itemStmt = $pdo->prepare("INSERT INTO bill_items (bill_id, item_name, price, qty) VALUES (?, ?, ?, ?)");
    $stockStmt = $pdo->prepare("UPDATE products SET stock_quantity = GREATEST(0, stock_quantity - ?) WHERE id = ?");

    foreach ($items as $item) {
        $itemStmt->execute([$bill_id, $item['name'], $item['price'], $item['qty']]);
        if (isset($item['id'])) {
            $stockStmt->execute([$item['qty'], $item['id']]);
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'bill_id' => $bill_id]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Failed to save bill']);
}
?>