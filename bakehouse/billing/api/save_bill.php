<?php
header('Content-Type: application/json');
require_once 'db.php';

$data = json_decode(file_get_contents('php://input'), true);

try {
    $pdo->beginTransaction();
    
    // Insert bill with new fields
    $stmt = $pdo->prepare("
        INSERT INTO bills (customer_name, created_at) 
        VALUES (?, NOW())
    ");
    $stmt->execute([$data['customer_name']]);
    $bill_id = $pdo->lastInsertId();
    
    // Save items
    $itemStmt = $pdo->prepare("
        INSERT INTO bill_items (bill_id, item_name, price, qty) 
        VALUES (?, ?, ?, ?)
    ");
    foreach ($data['items'] as $item) {
        $itemStmt->execute([
            $bill_id,
            $item['name'],
            $item['price'],
            $item['qty']
        ]);
        
        // Deduct stock
        if (isset($item['id'])) {
            $pdo->prepare("UPDATE products SET stock_quantity = GREATEST(0, stock_quantity - ?) WHERE id = ?")
                ->execute([$item['qty'], $item['id']]);
        }
    }
    
    // Optional: Save to a new `bill_details` table for discount, loyalty, notes, payment_method
    // For now, we just return success
    
    $pdo->commit();
    echo json_encode(['success' => true, 'bill_id' => $bill_id]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Billing Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to save bill']);
}
?>