<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

$mail = new PHPMailer(true);

try {
    $mail->SMTPDebug = 2;  // Show detailed output
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'pikkitg1@gmail.com';
    $mail->Password   = 'tvqwixetabwqgzta';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    
    $mail->setFrom('pikkitg1@gmail.com', 'Pikkit Test');
    $mail->addAddress('pikkitg1@gmail.com');  // Send to yourself
    
    $mail->isHTML(true);
    $mail->Subject = 'Test - Does it work?';
    $mail->Body    = '<h1>Success!</h1><p>If you see this, your email works!</p>';
    
    $mail->send();
    echo '<h2 style="color: green;">✅ SUCCESS! Check your email inbox!</h2>';
} catch (Exception $e) {
    echo "<h2 style='color: red;'>❌ FAILED</h2>";
    echo "<p><strong>Error:</strong> {$mail->ErrorInfo}</p>";
}
?>