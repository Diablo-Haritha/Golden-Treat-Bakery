<?php
ob_start();
session_start();
include 'db_connect.php';

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("DELETE FROM orders WHERE user_id = ?");
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error, 3, "C:/xampp/htdocs/bakery/bk/error.log");
        die("Database error. Check error.log.");
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error, 3, "C:/xampp/htdocs/bakery/bk/error.log");
        die("Database error. Check error.log.");
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    session_destroy();
    echo "<script>alert('Account deleted successfully!'); window.location.href='bk/home.php';</script>";
} else {
    header("Location: bk/login.php");
    exit();
}
ob_end_flush();
?>