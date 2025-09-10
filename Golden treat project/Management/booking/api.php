<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require 'db.php';

$method = $_SERVER['REQUEST_METHOD'];
$id = $_GET['id'] ?? null;
$input = json_decode(file_get_contents('php://input'), true) ?? [];

switch ($method) {
    case 'GET':
        $stmt = $pdo->query("SELECT * FROM bookings");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'POST':
        $stmt = $pdo->prepare("INSERT INTO bookings (bookingId, customerName, date, time, tableNumber, status) VALUES (:bookingId, :customerName, :date, :time, :tableNumber, :status)");
        $stmt->execute([
            ':bookingId' => $input['bookingId'] ?? 'BID' . time(),
            ':customerName' => $input['customerName'] ?? 'Unknown',
            ':date' => $input['date'],
            ':time' => $input['time'],
            ':tableNumber' => $input['tableNumber'],
            ':status' => $input['status'] ?? 'Pending'
        ]);
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        break;

    case 'PUT':
        if (!$id) { http_response_code(400); echo json_encode(['error'=>'Booking ID required']); exit; }
        $stmt = $pdo->prepare("UPDATE bookings SET bookingId=:bookingId, customerName=:customerName, date=:date, time=:time, tableNumber=:tableNumber, status=:status WHERE id=:id");
        $stmt->execute([
            ':bookingId' => $input['bookingId'],
            ':customerName' => $input['customerName'],
            ':date' => $input['date'],
            ':time' => $input['time'],
            ':tableNumber' => $input['tableNumber'],
            ':status' => $input['status'],
            ':id' => $id
        ]);
        echo json_encode(['success'=>true]);
        break;

    case 'DELETE':
        if (!$id) { http_response_code(400); echo json_encode(['error'=>'Booking ID required']); exit; }
        $stmt = $pdo->prepare("DELETE FROM bookings WHERE id=:id");
        $stmt->execute([':id'=>$id]);
        echo json_encode(['success'=>true]);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error'=>'Method not allowed']);
        break;
}
?>
