<?php
// session_management.php
// Place in a common directory, e.g., C:\xampp\htdocs\hh\includes\

// Set timezone to ensure consistent MySQL NOW() behavior
date_default_timezone_set('Asia/Kolkata');

// Configure session cookie parameters only if no session is active
$is_production = false; // Set to true in production with HTTPS
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0, // Session cookie expires when browser closes
        'path' => '/',
        'secure' => $is_production, // Enforce HTTPS in production
        'httponly' => true, // Prevent JavaScript access
        'samesite' => 'Strict' // Prevent CSRF
    ]);
    session_start();
}

// Generate CSRF token if not set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Session timeout (30 minutes)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    session_unset();
    session_destroy();
    header("Location: ../bakehouse/login.php"); // Adjust path to login.php
    exit();
}
$_SESSION['last_activity'] = time(); // Update last activity time

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../bakehouse/login.php"); // Adjust path to login.php
    exit();
}

// Optional: Role-based access control
function requireRole($allowedRoles = []) {
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowedRoles)) {
        header("Location: ../bakehouse/login.php"); // Adjust path
        exit();
    }
}
?>