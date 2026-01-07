<?php
// Test Email Script
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

$mail = new PHPMailer(true);

try {
    // Enable verbose debug output
    $mail->SMTPDebug = 2; // Shows detailed debug information
    $mail->Debugoutput = 'html';
    
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'pikkitg1@gmail.com'; // Your Gmail
    $mail->Password   = 'mpnp zomu oucy cgpz'; // Your App Password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    
    $mail->setFrom('pikkitg1@gmail.com', 'Pikkit Test');
    $mail->addAddress('pikkitg1@gmail.com'); // Send to yourself for testing
    
    $mail->isHTML(true);
    $mail->Subject = 'Test Email from Pikkit';
    $mail->Body    = '<h1>Success!</h1><p>If you receive this, your email configuration is working correctly.</p>';
    
    $mail->send();
    echo '<div style="background: #efe; padding: 20px; border: 2px solid #3c3; margin: 20px;">
            <h2 style="color: #3c3;">✅ EMAIL SENT SUCCESSFULLY!</h2>
            <p>Check your inbox at pikkitg1@gmail.com</p>
          </div>';
    
} catch (Exception $e) {
    echo '<div style="background: #fee; padding: 20px; border: 2px solid #c33; margin: 20px;">
            <h2 style="color: #c33;">❌ EMAIL FAILED!</h2>
            <p><strong>Error:</strong> ' . $mail->ErrorInfo . '</p>
          </div>';
}
?>