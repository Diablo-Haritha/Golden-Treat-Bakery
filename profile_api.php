<?php
session_start();
header('Content-Type: application/json');

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$servername = "localhost";
$username = "root"; // Change to your DB username
$password = ""; // Change to your DB password
$dbname = "golden_treat";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log("User not logged in: Session user_id not set");
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle GET request (Read user data and orders)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Fetch user data
    $user_query = "SELECT full_name, email, mobile, address, district, date_joined, last_login, profile_picture 
                   FROM users WHERE id = ?";
    $stmt = $conn->prepare($user_query);
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        echo json_encode(['status' => 'error', 'message' => 'Query preparation failed']);
        exit();
    }
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $user_result = $stmt->get_result();
    $user = $user_result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        error_log("No user found for user_id: $user_id");
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
        exit();
    }

    // Fetch orders
    $order_query = "SELECT order_id, product_name, order_date, status 
                    FROM orders WHERE user_id = ? ORDER BY order_date DESC";
    $stmt = $conn->prepare($order_query);
    if (!$stmt) {
        error_log("Prepare failed for orders: " . $conn->error);
        echo json_encode(['status' => 'error', 'message' => 'Order query preparation failed']);
        exit();
    }
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $order_result = $stmt->get_result();
    $orders = [];
    while ($row = $order_result->fetch_assoc()) {
        $orders[] = $row;
    }
    $stmt->close();

    // Calculate total orders and total spent
    $total_orders = count($orders);
    $total_spent_query = "SELECT SUM(total_amount) as total_spent FROM orders WHERE user_id = ?";
    $stmt = $conn->prepare($total_spent_query);
    if (!$stmt) {
        error_log("Prepare failed for total spent: " . $conn->error);
        echo json_encode(['status' => 'error', 'message' => 'Total spent query preparation failed']);
        exit();
    }
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $total_spent_result = $stmt->get_result();
    $total_spent = $total_spent_result->fetch_assoc()['total_spent'] ?? 0;
    $stmt->close();

    // Simple recommendation logic
    $recommended_product = !empty($orders) ? $orders[0]['product_name'] : 'Red Velvet Cake';

    echo json_encode([
        'status' => 'success',
        'user' => $user,
        'orders' => $orders,
        'total_orders' => $total_orders,
        'total_spent' => $total_spent,
        'recommended_product' => $recommended_product
    ]);
}

// Handle POST requests (Update, Logout, Delete, Upload Picture)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : (json_decode(file_get_contents('php://input'), true)['action'] ?? '');

    if ($action === 'update') {
        $input = json_decode(file_get_contents('php://input'), true);
        $fullName = $conn->real_escape_string($input['fullName'] ?? '');
        $email = $conn->real_escape_string($input['email'] ?? '');
        $mobile = $conn->real_escape_string($input['mobile'] ?? '');
        $address = $conn->real_escape_string($input['address'] ?? '');
        $district = $conn->real_escape_string($input['district'] ?? '');

        // Check if email is unique (excluding current user)
        $email_check_query = "SELECT id FROM users WHERE email = ? AND id != ?";
        $stmt = $conn->prepare($email_check_query);
        if (!$stmt) {
            error_log("Prepare failed for email check: " . $conn->error);
            echo json_encode(['status' => 'error', 'message' => 'Email check query preparation failed']);
            exit();
        }
        $stmt->bind_param('si', $email, $user_id);
        $stmt->execute();
        $email_result = $stmt->get_result();
        if ($email_result->num_rows > 0) {
            echo json_encode(['status' => 'error', 'message' => 'Email already in use']);
            $stmt->close();
            $conn->close();
            exit();
        }
        $stmt->close();

        $update_query = "UPDATE users SET full_name = ?, email = ?, mobile = ?, address = ?, district = ? WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        if (!$stmt) {
            error_log("Prepare failed for update: " . $conn->error);
            echo json_encode(['status' => 'error', 'message' => 'Update query preparation failed']);
            exit();
        }
        $stmt->bind_param('sssssi', $fullName, $email, $mobile, $address, $district, $user_id);
        if ($stmt->execute()) {
            $_SESSION['full_name'] = $fullName;
            echo json_encode(['status' => 'success', 'message' => 'Profile updated']);
        } else {
            error_log("Update failed: " . $conn->error);
            echo json_encode(['status' => 'error', 'message' => 'Error updating profile']);
        }
        $stmt->close();
    } elseif ($action === 'upload_picture') {
        if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
            error_log("Profile picture upload failed: No file or upload error");
            echo json_encode(['status' => 'error', 'message' => 'No file uploaded or upload error']);
            exit();
        }

        $file = $_FILES['profile_picture'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 2 * 1024 * 1024; // 2MB

        if (!in_array($file['type'], $allowed_types)) {
            error_log("Invalid file type: " . $file['type']);
            echo json_encode(['status' => 'error', 'message' => 'Invalid file type. Only JPG, PNG, and GIF allowed.']);
            exit();
        }

        if ($file['size'] > $max_size) {
            error_log("File too large: " . $file['size']);
            echo json_encode(['status' => 'error', 'message' => 'File size exceeds 2MB limit']);
            exit();
        }

        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_name = $user_id . '_' . time() . '_' . basename($file['name']);
        $file_path = $upload_dir . $file_name;

        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            // Update database with new profile picture path
            $update_query = "UPDATE users SET profile_picture = ? WHERE id = ?";
            $stmt = $conn->prepare($update_query);
            if (!$stmt) {
                error_log("Prepare failed for profile picture update: " . $conn->error);
                echo json_encode(['status' => 'error', 'message' => 'Profile picture update query preparation failed']);
                exit();
            }
            $stmt->bind_param('si', $file_path, $user_id);
            if ($stmt->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'Profile picture updated', 'profile_picture' => $file_path]);
            } else {
                error_log("Profile picture update failed: " . $conn->error);
                echo json_encode(['status' => 'error', 'message' => 'Error updating profile picture']);
            }
            $stmt->close();
        } else {
            error_log("File move failed for: " . $file_path);
            echo json_encode(['status' => 'error', 'message' => 'Error saving profile picture']);
        }
    } elseif ($action === 'logout') {
        session_destroy();
        echo json_encode(['status' => 'success', 'message' => 'Logged out']);
    } elseif ($action === 'delete') {
        // Delete orders first due to foreign key constraint
        $delete_orders_query = "DELETE FROM orders WHERE user_id = ?";
        $stmt = $conn->prepare($delete_orders_query);
        if (!$stmt) {
            error_log("Prepare failed for delete orders: " . $conn->error);
            echo json_encode(['status' => 'error', 'message' => 'Delete orders query preparation failed']);
            exit();
        }
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->close();

        // Delete user
        $delete_user_query = "DELETE FROM users WHERE id = ?";
        $stmt = $conn->prepare($delete_user_query);
        if (!$stmt) {
            error_log("Prepare failed for delete user: " . $conn->error);
            echo json_encode(['status' => 'error', 'message' => 'Delete user query preparation failed']);
            exit();
        }
        $stmt->bind_param('i', $user_id);
        if ($stmt->execute()) {
            session_destroy();
            echo json_encode(['status' => 'success', 'message' => 'Account deleted']);
        } else {
            error_log("Delete user failed: " . $conn->error);
            echo json_encode(['status' => 'error', 'message' => 'Error deleting account']);
        }
        $stmt->close();
    } else {
        error_log("Invalid action: $action");
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }
}

$conn->close();
?>