<?php
require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);
try {
    $mail->SMTPDebug = SMTP::DEBUG_SERVER;
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'didulanr@gmail.com.com'; // Your Gmail
    $mail->Password = 'sbtjlqesgzfwlslh';   // Your App Password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->setFrom('didulanr@gmail.com', 'Test');
    $mail->addAddress('didulanr@gmail.com');
    $mail->isHTML(true);
    $mail->Subject = 'Test Email';
    $mail->Body = 'This is a test!';
    $mail->send();
    echo 'Email sent!';
} catch (Exception $e) {
    echo 'Error: ' . $mail->ErrorInfo;
}
?>