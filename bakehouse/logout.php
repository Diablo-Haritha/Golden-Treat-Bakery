<?php
// logout.php (in C:\xampp\htdocs\hh\auth\)
require_once 'session_management.php'; // Adjust path

// Validate CSRF token
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    session_unset();
    session_destroy();
}
header("Location: login.php"); // Adjust path
exit();
?>