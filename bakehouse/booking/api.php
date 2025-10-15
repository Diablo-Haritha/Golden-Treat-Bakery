<?php
// Suppress errors and clean output buffer
ini_set('display_errors', '0');
ob_clean();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle CORS preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require 'db.php';

$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? $_GET['id'] : null;
$input = json_decode(file_get_contents('php://input'), true) ?? [];

switch ($method) {
    case 'GET':
        try {
            $stmt = $pdo->query("SELECT * FROM bookings");
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'POST':
        // Validate required fields
        if (!isset($input['customerName']) || !trim($input['customerName'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Customer name is required']);
            exit;
        }
        // Email and phone optional for admin, required for customer
        $isAdmin = isset($input['source']) && $input['source'] === 'admin';
        if (!$isAdmin) {
            if (!isset($input['email']) || !preg_match("/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/", $input['email'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Valid email is required']);
                exit;
            }
            if (!isset($input['phone']) || !preg_match("/^\d{10}$/", $input['phone'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Phone number must be exactly 10 digits']);
                exit;
            }
        }
        if (!isset($input['date']) || empty($input['date'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Date is required']);
            exit;
        }
        // Prevent past dates
        $today = date("Y-m-d");
        if ($input['date'] < $today) {
            http_response_code(400);
            echo json_encode(['error' => 'Booking date cannot be in the past']);
            exit;
        }
        if (!isset($input['time']) || empty($input['time'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Time is required']);
            exit;
        }
        if (!isset($input['tableNumber']) || !is_numeric($input['tableNumber']) || $input['tableNumber'] <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Valid number of guests is required']);
            exit;
        }

        // Generate bookingId
        try {
            $stmt = $pdo->query("SELECT bookingId FROM bookings ORDER BY id DESC LIMIT 1");
            $last = $stmt->fetch(PDO::FETCH_ASSOC);
            $bookingId = isset($input['bookingId']) && !empty($input['bookingId']) ? $input['bookingId'] : ($last ? "BID" . str_pad((int)substr($last['bookingId'], 3) + 1, 4, "0", STR_PAD_LEFT) : "BID1001");

            // Insert into bookings
            $stmt = $pdo->prepare(
                "INSERT INTO bookings (bookingId, customerName, email, phone, date, time, tableNumber, status) 
                 VALUES (:bookingId, :customerName, :email, :phone, :date, :time, :tableNumber, :status)"
            );
            $result = $stmt->execute([
                ':bookingId' => $bookingId,
                ':customerName' => trim($input['customerName']),
                ':email' => isset($input['email']) ? trim($input['email']) : '',
                ':phone' => isset($input['phone']) ? trim($input['phone']) : '',
                ':date' => $input['date'],
                ':time' => $input['time'],
                ':tableNumber' => (int)$input['tableNumber'],
                ':status' => isset($input['status']) ? $input['status'] : 'Pending'
            ]);

            if ($result) {
                echo json_encode(['success' => true, 'id' => $pdo->lastInsertId(), 'bookingId' => $bookingId, 'msg' => 'Booking successful!']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to create booking']);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'PUT':
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Booking ID required']);
            exit;
        }
        // Validate fields for update
        if (!isset($input['customerName']) || !trim($input['customerName'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Customer name is required']);
            exit;
        }
        // Email and phone optional for admin
        $isAdmin = isset($input['source']) && $input['source'] === 'admin';
        if (!$isAdmin) {
            if (!isset($input['email']) || !preg_match("/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/", $input['email'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Valid email is required']);
                exit;
            }
            if (!isset($input['phone']) || !preg_match("/^\d{10}$/", $input['phone'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Phone number must be exactly 10 digits']);
                exit;
            }
        }
        if (!isset($input['date']) || empty($input['date'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Date is required']);
            exit;
        }
        if ($input['date'] < $today) {
            http_response_code(400);
            echo json_encode(['error' => 'Booking date cannot be in the past']);
            exit;
        }
        if (!isset($input['time']) || empty($input['time'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Time is required']);
            exit;
        }
        if (!isset($input['tableNumber']) || !is_numeric($input['tableNumber']) || $input['tableNumber'] <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Valid number of guests is required']);
            exit;
        }

        try {
            $stmt = $pdo->prepare(
                "UPDATE bookings SET bookingId=:bookingId, customerName=:customerName, email=:email, phone=:phone, date=:date, time=:time, tableNumber=:tableNumber, status=:status WHERE id=:id"
            );
            $result = $stmt->execute([
                ':bookingId' => isset($input['bookingId']) ? $input['bookingId'] : 'BID' . time(),
                ':customerName' => trim($input['customerName']),
                ':email' => isset($input['email']) ? trim($input['email']) : '',
                ':phone' => isset($input['phone']) ? trim($input['phone']) : '',
                ':date' => $input['date'],
                ':time' => $input['time'],
                ':tableNumber' => (int)$input['tableNumber'],
                ':status' => isset($input['status']) ? $input['status'] : 'Pending',
                ':id' => $id
            ]);
            echo json_encode(['success' => $result]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'DELETE':
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Booking ID required']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("DELETE FROM bookings WHERE id=:id");
            $result = $stmt->execute([':id' => $id]);
            echo json_encode(['success' => $result]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}
exit;
?>