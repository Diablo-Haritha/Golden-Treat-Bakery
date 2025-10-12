<?php
// profile_api.php - Updated version to fix profile picture display issue
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Enable error reporting for debugging (disable in production)
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

$user_id = (int)$_SESSION['user_id'];

// Validate user exists
$user_check_query = "SELECT id FROM users WHERE id = ?";
$stmt = $conn->prepare($user_check_query);
if (!$stmt) {
    error_log("Prepare failed for user check: " . $conn->error);
    echo json_encode(['status' => 'error', 'message' => 'User validation failed']);
    exit();
}
$stmt->bind_param('i', $user_id);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) {
    error_log("User not found for user_id: $user_id");
    session_destroy();
    echo json_encode(['status' => 'error', 'message' => 'User not found']);
    exit();
}
$stmt->close();

// Auto-add missing columns if needed
$check_col = "SHOW COLUMNS FROM users LIKE 'last_login'";
$col_result = $conn->query($check_col);
if ($col_result->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN last_login TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP");
}

$check_col2 = "SHOW COLUMNS FROM users LIKE 'profile_picture'";
$col_result2 = $conn->query($check_col2);
if ($col_result2->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) DEFAULT NULL");
}

// Update last_login on fetch
$update_login_query = "UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?";
$stmt = $conn->prepare($update_login_query);
if ($stmt) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->close();
}

// Base URL for your project (adjust if needed)
$base_url = ''; // Relative path - empty for same directory
$server_base_url = 'http://localhost/bakery/bk/'; // Absolute URL for responses - adjust to your path

// Handle GET request (Read user data)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Fetch user data
    $user_query = "SELECT full_name, email, mobile, address, district, date_joined, last_login, profile_picture 
                   FROM users WHERE id = ?";
    $stmt = $conn->prepare($user_query);
    if (!$stmt) {
        error_log("Prepare failed for user query: " . $conn->error);
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

    // Ensure profile_picture is a valid URL with cache-busting
    if ($user['profile_picture'] && file_exists($_SERVER['DOCUMENT_ROOT'] . '/bakery/bk/' . $user['profile_picture'])) {
        $user['profile_picture'] = $server_base_url . $user['profile_picture'] . '?t=' . time();
        error_log("Profile picture exists at: " . $_SERVER['DOCUMENT_ROOT'] . '/bakery/bk/' . $user['profile_picture']);
    } else {
        $user['profile_picture'] = 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=140&q=80';
        error_log("Profile picture not found, using default: " . $user['profile_picture']);
    }

    // Initialize session full_name if not set
    $_SESSION['full_name'] = $_SESSION['full_name'] ?? $user['full_name'];

    echo json_encode([
        'status' => 'success',
        'user' => $user
    ]);
}

// Handle POST requests (Update, Logout, Delete, Upload Picture)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : (json_decode(file_get_contents('php://input'), true)['action'] ?? '');

    if ($action === 'update') {
        $input = json_decode(file_get_contents('php://input'), true);
        $fullName = trim($input['fullName'] ?? '');
        $email = trim($input['email'] ?? '');
        $mobile = trim($input['mobile'] ?? '');
        $address = trim($input['address'] ?? '');
        $district = trim($input['district'] ?? '');

        // Input validation
        if (empty($fullName) || empty($email) || empty($mobile) || empty($address) || empty($district)) {
            echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
            exit();
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid email format']);
            exit();
        }
        if (!preg_match('/^\+?\d{10,15}$/', $mobile)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid mobile number']);
            exit();
        }
        if (strlen($fullName) > 255 || strlen($email) > 255 || strlen($mobile) > 20 || strlen($district) > 100) {
            echo json_encode(['status' => 'error', 'message' => 'Input length exceeds limits']);
            exit();
        }

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
            echo json_encode(['status' => 'error', 'message' => 'Error updating profile: ' . $conn->error]);
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

        // Additional image validation
        $image_info = getimagesize($file['tmp_name']);
        if ($image_info === false) {
            error_log("Invalid image file: " . $file['name']);
            echo json_encode(['status' => 'error', 'message' => 'Invalid image file']);
            exit();
        }

        $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/bakery/bk/Uploads/';
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                error_log("Failed to create directory: $upload_dir");
                echo json_encode(['status' => 'error', 'message' => 'Failed to create upload directory']);
                exit();
            }
        }

        // Delete old profile picture if exists
        $old_picture_query = "SELECT profile_picture FROM users WHERE id = ?";
        $stmt = $conn->prepare($old_picture_query);
        if ($stmt) {
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $old_picture = $stmt->get_result()->fetch_assoc()['profile_picture'];
            if ($old_picture && file_exists($_SERVER['DOCUMENT_ROOT'] . '/bakery/bk/' . $old_picture)) {
                if (!unlink($_SERVER['DOCUMENT_ROOT'] . '/bakery/bk/' . $old_picture)) {
                    error_log("Failed to delete old profile picture: $old_picture");
                }
            }
            $stmt->close();
        }

        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $file_name = $user_id . '_' . time() . '.' . $file_extension;
        $file_path = 'Uploads/' . $file_name; // Relative path for DB storage
        $full_file_path = $upload_dir . $file_name; // Absolute path for file operations

        if (move_uploaded_file($file['tmp_name'], $full_file_path)) {
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
                $full_url = $server_base_url . $file_path . '?t=' . time();
                error_log("Profile picture uploaded: $full_url");
                echo json_encode(['status' => 'success', 'message' => 'Profile picture updated', 'profile_picture' => $full_url]);
            } else {
                error_log("Profile picture update failed: " . $conn->error);
                echo json_encode(['status' => 'error', 'message' => 'Error updating profile picture']);
            }
            $stmt->close();
        } else {
            error_log("File move failed for: $full_file_path");
            echo json_encode(['status' => 'error', 'message' => 'Error saving profile picture']);
        }
    } elseif ($action === 'logout') {
        session_destroy();
        echo json_encode(['status' => 'success', 'message' => 'Logged out']);
    } elseif ($action === 'delete') {
        // Delete profile picture if exists
        $old_picture_query = "SELECT profile_picture FROM users WHERE id = ?";
        $stmt = $conn->prepare($old_picture_query);
        if ($stmt) {
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $old_picture = $stmt->get_result()->fetch_assoc()['profile_picture'];
            if ($old_picture && file_exists($_SERVER['DOCUMENT_ROOT'] . '/bakery/bk/' . $old_picture)) {
                if (!unlink($_SERVER['DOCUMENT_ROOT'] . '/bakery/bk/' . $old_picture)) {
                    error_log("Failed to delete profile picture: $old_picture");
                }
            }
            $stmt->close();
        }

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
            echo json_encode(['status' => 'error', 'message' => 'Error deleting account: ' . $conn->error]);
        }
        $stmt->close();
    } else {
        error_log("Invalid action: $action");
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }
}

$conn->close();
?>