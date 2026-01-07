<?php
// Start output buffering FIRST to prevent header errors
ob_start();
session_start();

// DEBUG MODE - Add this at the top temporarily
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Handle session clearing BEFORE any output
if(isset($_GET['clear_reset'])) {
    unset($_SESSION['forgot_email']);
    unset($_SESSION['otp_verified']);
    unset($_SESSION['reset_step']);
    header("Location: login.php");
    ob_end_flush();
    exit();
}

include 'dbconnect.php';

// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

// Database connection
try {
    $pdo = new PDO("mysql:host=localhost;dbname=pikkit", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

$error = '';
$success = '';
$showOtpForm = false;
$showResetForm = false;

// DEBUG: Show session state at top of page
$debug_info = "DEBUG INFO:<br>";
$debug_info .= "Session Email: " . ($_SESSION['forgot_email'] ?? 'NOT SET') . "<br>";
$debug_info .= "OTP Verified: " . (isset($_SESSION['otp_verified']) ? ($_SESSION['otp_verified'] ? 'TRUE' : 'FALSE') : 'NOT SET') . "<br>";
$debug_info .= "Reset Step: " . ($_SESSION['reset_step'] ?? 'NOT SET') . "<br>";
$debug_info .= "GET Step: " . ($_GET['step'] ?? 'NOT SET') . "<br>";
$debug_info .= "Show OTP Form: " . ($showOtpForm ? 'TRUE' : 'FALSE') . "<br>";
$debug_info .= "Show Reset Form: " . ($showResetForm ? 'TRUE' : 'FALSE') . "<br>";

// Function to validate Gmail address
function isValidGmail($email) {
    $email = strtolower(trim($email));
    return preg_match('/^[a-z0-9._%+-]+@gmail\.com$/i', $email);
}

// Function to send OTP via Gmail SMTP
function sendOtpEmail($to_email, $otp) {
    $mail = new PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'pikkitg1@gmail.com';
        $mail->Password   = 'mpnp zomu oucy cgpz';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        $mail->setFrom('pikkitg1@gmail.com', 'Pikkit');
        $mail->addAddress($to_email);
        
        $mail->isHTML(true);
        $mail->Subject = 'Pikkit - Password Reset OTP';
        $mail->Body    = '
            <html>
            <body style="font-family: Arial, sans-serif; padding: 20px; background-color: #f5f5f5;">
                <div style="max-width: 600px; margin: 0 auto; background-color: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                    <div style="text-align: center; margin-bottom: 20px;">
                        <h1 style="color: #333; margin: 0;">Pikkit</h1>
                    </div>
                    <h2 style="color: #333; text-align: center;">Password Reset Request</h2>
                    <p style="color: #666; font-size: 16px;">Hello,</p>
                    <p style="color: #666; font-size: 16px;">You requested to reset your password. Please use the following OTP to complete the process:</p>
                    <div style="background-color: #f0f0f0; padding: 20px; text-align: center; border-radius: 5px; margin: 20px 0;">
                        <h1 style="color: #333; letter-spacing: 5px; margin: 0; font-size: 32px;">' . $otp . '</h1>
                    </div>
                    <p style="color: #666; font-size: 14px;"><strong>This OTP will expire in 15 minutes.</strong></p>
                    <p style="color: #666; font-size: 14px;">If you did not request a password reset, please ignore this email and your password will remain unchanged.</p>
                    <hr style="border: none; border-top: 1px solid #e0e0e0; margin: 20px 0;">
                    <p style="color: #999; font-size: 12px; text-align: center;">This is an automated message from Pikkit. Please do not reply to this email.</p>
                </div>
            </body>
            </html>
        ';
        $mail->AltBody = "Your OTP for password reset is: $otp\n\nThis OTP will expire in 15 minutes.\n\nIf you did not request this, please ignore this email.";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

// Handle manual login
if(isset($_POST['manual_login'])) {
    $username = trim($_POST['username']);
    $email = strtolower(trim($_POST['email']));
    $pass = $_POST['password'];
    
    if(empty($username) || empty($email) || empty($pass)) {
        $error = "Please fill in all fields";
    } elseif(!isValidGmail($email)) {
        $error = "Please use a valid Gmail address (@gmail.com)";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if(!$user) {
            $error = "This email is not registered. Please <a href='registration.php' style='color: #333; font-weight: bold;'>create an account</a> first.";
        } else {
            if($user['name'] !== $username) {
                $error = "Invalid username or password";
            } elseif(!password_verify($pass, $user['password'])) {
                $error = "Invalid username or password";
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                header("Location: index.php");
                ob_end_flush();
                exit();
            }
        }
    }
}

// STEP 1: Send OTP
if(isset($_POST['send_otp'])) {
    $email = strtolower(trim($_POST['forgot_email']));
    
    if(!isValidGmail($email)) {
        $error = "Please use a valid Gmail address (@gmail.com)";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($user) {
            $otp = (string)rand(100000, 999999);
            $expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
            
            $stmt = $pdo->prepare("DELETE FROM otp_codes WHERE email = ?");
            $stmt->execute([$email]);
            
            $stmt = $pdo->prepare("INSERT INTO otp_codes (email, otp, expiry) VALUES (?, ?, ?)");
            $stmt->execute([$email, $otp, $expiry]);
            
            if(sendOtpEmail($email, $otp)) {
                $_SESSION['forgot_email'] = $email;
                $_SESSION['reset_step'] = 'otp';
                unset($_SESSION['otp_verified']);
                
                // DEBUG: Check what we set
                error_log("OTP SENT - Email: $email, Step: otp");
                
                header("Location: login.php?step=otp&success=1");
                ob_end_flush();
                exit();
            } else {
                $error = "Failed to send OTP. Please try again.";
            }
        } else {
            $error = "Email not found in our system";
        }
    }
}

// STEP 2: Verify OTP - THIS IS THE CRITICAL PART
if(isset($_POST['verify_otp'])) {
    $email = $_SESSION['forgot_email'] ?? '';
    $entered_otp = preg_replace('/\s+/', '', trim($_POST['otp']));
    
    error_log("VERIFY OTP STARTED - Email from session: $email, Entered OTP: $entered_otp");
    
    if(empty($email)) {
        $error = "Session expired. Please start over.";
        error_log("ERROR: No email in session");
        unset($_SESSION['forgot_email']);
        unset($_SESSION['reset_step']);
    } elseif(empty($entered_otp) || strlen($entered_otp) != 6) {
        $error = "Please enter a valid 6-digit OTP.";
        error_log("ERROR: Invalid OTP format");
        $_SESSION['reset_step'] = 'otp';
        $showOtpForm = true;
    } else {
        // Check OTP in database
        $stmt = $pdo->prepare("SELECT * FROM otp_codes WHERE email = ? AND otp = ? AND expiry > NOW()");
        $stmt->execute([$email, $entered_otp]);
        $otp_record = $stmt->fetch(PDO::FETCH_ASSOC);
        
        error_log("OTP Query Result: " . ($otp_record ? "FOUND" : "NOT FOUND"));
        
        if($otp_record) {
            // SUCCESS - Set session variables
            $_SESSION['otp_verified'] = true;
            $_SESSION['reset_step'] = 'password';
            
            error_log("OTP VERIFIED - Setting otp_verified=true, reset_step=password");
            
            // Force write session
            session_write_close();
            
            // Restart session
            session_start();
            
            // Verify it was saved
            error_log("After session restart - otp_verified: " . ($_SESSION['otp_verified'] ? 'true' : 'false'));
            
            // REDIRECT
            header("Location: login.php?step=reset&success=1");
            ob_end_flush();
            exit();
        } else {
            // Check if expired
            $stmt = $pdo->prepare("SELECT * FROM otp_codes WHERE email = ? AND otp = ?");
            $stmt->execute([$email, $entered_otp]);
            $expired_record = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if($expired_record) {
                $error = "OTP has expired. Please request a new one.";
                error_log("ERROR: OTP expired");
            } else {
                $error = "Invalid OTP. Please check and try again.";
                error_log("ERROR: OTP not found in database");
            }
            $_SESSION['reset_step'] = 'otp';
            $showOtpForm = true;
        }
    }
}

// STEP 3: Reset password
if(isset($_POST['reset_password'])) {
    $email = $_SESSION['forgot_email'] ?? '';
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    error_log("RESET PASSWORD - Email: $email, OTP Verified: " . (isset($_SESSION['otp_verified']) ? 'true' : 'false'));
    
    if(!isset($_SESSION['otp_verified']) || !$_SESSION['otp_verified']) {
        $error = "Please verify OTP first";
        error_log("ERROR: OTP not verified");
        $_SESSION['reset_step'] = 'otp';
        $showOtpForm = true;
    } elseif(empty($email)) {
        $error = "Session expired. Please start over.";
        unset($_SESSION['forgot_email']);
        unset($_SESSION['reset_step']);
        unset($_SESSION['otp_verified']);
    } elseif(empty($new_password) || empty($confirm_password)) {
        $error = "Please fill in all fields";
        $showResetForm = true;
    } elseif(strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters";
        $showResetForm = true;
    } elseif($new_password !== $confirm_password) {
        $error = "Passwords do not match";
        $showResetForm = true;
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
        
        if($stmt->execute([$hashed_password, $email])) {
            $stmt = $pdo->prepare("DELETE FROM otp_codes WHERE email = ?");
            $stmt->execute([$email]);
            
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if($user) {
                unset($_SESSION['forgot_email']);
                unset($_SESSION['otp_verified']);
                unset($_SESSION['reset_step']);
                
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                
                error_log("PASSWORD RESET SUCCESS - Redirecting to index.php");
                
                header("Location: index.php");
                ob_end_flush();
                exit();
            }
        } else {
            $error = "Failed to update password. Please try again.";
            $showResetForm = true;
        }
    }
}

// Handle URL parameters - THIS DETERMINES WHICH FORM TO SHOW
if(isset($_GET['step'])) {
    error_log("GET STEP: " . $_GET['step']);
    
    if($_GET['step'] === 'otp') {
        if(isset($_SESSION['forgot_email']) && $_SESSION['reset_step'] === 'otp') {
            $showOtpForm = true;
            error_log("SHOWING OTP FORM");
            if(isset($_GET['success'])) {
                $success = "OTP sent successfully! Please check your Gmail inbox.";
            }
        } else {
            error_log("ERROR: Cannot show OTP form - session check failed");
        }
    } elseif($_GET['step'] === 'reset') {
        error_log("Checking for reset form - otp_verified: " . (isset($_SESSION['otp_verified']) ? ($_SESSION['otp_verified'] ? 'true' : 'false') : 'not set'));
        error_log("forgot_email: " . (isset($_SESSION['forgot_email']) ? $_SESSION['forgot_email'] : 'not set'));
        
        if(isset($_SESSION['otp_verified']) && $_SESSION['otp_verified'] === true && isset($_SESSION['forgot_email'])) {
            $showResetForm = true;
            error_log("SHOWING RESET FORM");
            if(isset($_GET['success'])) {
                $success = "OTP verified! Please create your new password.";
            }
        } else {
            error_log("ERROR: Cannot show reset form - Session expired or tampered");
            unset($_SESSION['forgot_email']);
            unset($_SESSION['otp_verified']);
            unset($_SESSION['reset_step']);
            header("Location: login.php");
            ob_end_flush();
            exit();
        }
    }
}

// Fallback: Determine form based on session (no URL params)
if(!isset($_GET['step']) && isset($_SESSION['forgot_email']) && !$showOtpForm && !$showResetForm) {
    $step = $_SESSION['reset_step'] ?? 'email';
    error_log("FALLBACK - Session step: $step");
    
    if($step === 'password' && isset($_SESSION['otp_verified']) && $_SESSION['otp_verified']) {
        $showResetForm = true;
        error_log("FALLBACK - Showing reset form");
    } elseif($step === 'otp') {
        $showOtpForm = true;
        error_log("FALLBACK - Showing OTP form");
    }
}

// Update debug info after processing
$debug_info = "DEBUG INFO (After Processing):<br>";
$debug_info .= "Session Email: " . ($_SESSION['forgot_email'] ?? 'NOT SET') . "<br>";
$debug_info .= "OTP Verified: " . (isset($_SESSION['otp_verified']) ? ($_SESSION['otp_verified'] ? 'TRUE' : 'FALSE') : 'NOT SET') . "<br>";
$debug_info .= "Reset Step: " . ($_SESSION['reset_step'] ?? 'NOT SET') . "<br>";
$debug_info .= "GET Step: " . ($_GET['step'] ?? 'NOT SET') . "<br>";
$debug_info .= "Show OTP Form: " . ($showOtpForm ? 'TRUE' : 'FALSE') . "<br>";
$debug_info .= "Show Reset Form: " . ($showResetForm ? 'TRUE' : 'FALSE') . "<br>";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Pikkit (DEBUG)</title>
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .debug-panel {
            position: fixed;
            top: 10px;
            right: 10px;
            background: #fff;
            border: 2px solid #f00;
            padding: 10px;
            font-size: 11px;
            max-width: 300px;
            z-index: 9999;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        
        .login-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 450px;
            padding: 25px;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 15px;
        }
        
        .logo img {
            max-width: 300px;
            width: 100%;
            height: auto;
        }
        
        .logo p {
            color: #666;
            margin-top: 8px;
            font-size: 13px;
        }
        
        .alert {
            padding: 10px 12px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-size: 13px;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert-error {
            background-color: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }

        .alert-error a {
            color: #c33;
            text-decoration: underline;
        }
        
        .alert-success {
            background-color: #efe;
            color: #3c3;
            border: 1px solid #cfc;
        }
        
        .form-group {
            margin-bottom: 12px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 500;
            font-size: 13px;
        }
        
        .form-group input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #333;
        }
        
        .gmail-hint {
            font-size: 11px;
            color: #666;
            margin-top: 4px;
        }
        
        .btn {
            width: 100%;
            padding: 11px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #333;
            color: white;
        }
        
        .btn-primary:hover {
            background: #000;
        }
        
        .divider {
            text-align: center;
            margin: 15px 0;
            position: relative;
        }
        
        .divider::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            width: 100%;
            height: 1px;
            background: #e0e0e0;
        }
        
        .divider span {
            background: white;
            padding: 0 15px;
            position: relative;
            color: #666;
            font-size: 13px;
        }
        
        .google-btn {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
        }
        
        .forgot-password {
            text-align: right;
            margin-top: -5px;
            margin-bottom: 12px;
        }
        
        .forgot-password a {
            color: #333;
            text-decoration: none;
            font-size: 12px;
        }
        
        .register-link {
            text-align: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
            color: #666;
            font-size: 13px;
        }
        
        .register-link a {
            color: #333;
            text-decoration: none;
            font-weight: 600;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            max-width: 400px;
            width: 90%;
        }
        
        .modal-header {
            margin-bottom: 20px;
        }
        
        .modal-header h2 {
            color: #333;
            font-size: 24px;
        }
        
        .close-modal {
            background: #f0f0f0;
            color: #666;
            margin-top: 10px;
        }
        
        .otp-input {
            text-align: center;
            font-size: 24px;
            letter-spacing: 10px;
            font-weight: bold;
        }

        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 20px;
            gap: 10px;
        }

        .step {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
            color: #999;
        }

        .step.active {
            background: #333;
            color: white;
        }

        .step.completed {
            background: #3c3;
            color: white;
        }
    </style>
</head>
<body>
    <!-- DEBUG PANEL -->
    <div class="debug-panel">
        <strong>DEBUG MODE</strong><br>
        <?php echo $debug_info; ?>
    </div>

    <div class="login-container">
        <div class="logo">
            <img src="images/pikkit logo.png" alt="Pikkit Logo">
            <p>Welcome back! Please login to your account</p>
        </div>
        
        <?php if($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <div id="g_id_onload"
             data-client_id="863511518630-t4oj6ktd9net7g1pj9a8etrot6ict9md.apps.googleusercontent.com"
             data-callback="handleCredentialResponse"
             data-auto_prompt="false">
        </div>
        
        <div class="google-btn" id="googleSignInDiv"></div>
        
        <div class="divider">
            <span>OR</span>
        </div>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>
            
            <div class="form-group">
                <label for="email">Gmail Address</label>
                <input type="email" id="email" name="email" placeholder="yourname@gmail.com" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <div class="forgot-password">
                <a href="#" onclick="openForgotModal(); return false;">Forgot Password?</a>
            </div>
            
            <button type="submit" name="manual_login" class="btn btn-primary">Login</button>
        </form>
        
        <div class="register-link">
            Don't have an account? <a href="registration.php">Create Account</a>
        </div>
    </div>
    
    <div id="forgotModal" class="modal <?php echo ($showOtpForm || $showResetForm) ? 'active' : ''; ?>">
        <div class="modal-content">
            <div class="step-indicator">
                <div class="step <?php echo (!$showOtpForm && !$showResetForm) ? 'active' : 'completed'; ?>">1</div>
                <div class="step <?php echo $showOtpForm ? 'active' : ($showResetForm ? 'completed' : ''); ?>">2</div>
                <div class="step <?php echo $showResetForm ? 'active' : ''; ?>">3</div>
            </div>

            <div class="modal-header">
                <h2>
                    <?php 
                        if($showResetForm) {
                            echo 'Create New Password';
                        } elseif($showOtpForm) {
                            echo 'Enter OTP';
                        } else {
                            echo 'Forgot Password';
                        }
                    ?>
                </h2>
            </div>
            
            <?php if($showResetForm): ?>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" minlength="6" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" minlength="6" required>
                    </div>
                    <button type="submit" name="reset_password" class="btn btn-primary">Reset Password & Login</button>
                    <button type="button" class="btn close-modal" onclick="closeForgotModal()">Cancel</button>
                </form>
            <?php elseif($showOtpForm): ?>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="otp">Enter 6-digit OTP</label>
                        <input type="text" id="otp" name="otp" class="otp-input" maxlength="6" pattern="[0-9]{6}" placeholder="000000" required>
                    </div>
                    <button type="submit" name="verify_otp" class="btn btn-primary">Verify OTP</button>
                    <button type="button" class="btn close-modal" onclick="closeForgotModal()">Cancel</button>
                </form>
            <?php else: ?>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="forgot_email">Enter your Gmail address</label>
                        <input type="email" id="forgot_email" name="forgot_email" placeholder="yourname@gmail.com" required>
                    </div>
                    <button type="submit" name="send_otp" class="btn btn-primary">Send OTP</button>
                    <button type="button" class="btn close-modal" onclick="closeForgotModal()">Cancel</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function openForgotModal() {
            document.getElementById('forgotModal').classList.add('active');
        }
        
        function closeForgotModal() {
            window.location.href = 'login.php?clear_reset=1';
        }
        
        function handleCredentialResponse(response) {
            fetch('google_auth.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ credential: response.credential })
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    window.location.href = 'index.php';
                } else {
                    if(data.redirect === 'registration.php') {
                        alert('WARNING: ' + data.message);
                        window.location.href = 'registration.php';
                    } else {
                        alert('WARNING: ' + (data.message || 'Google login failed.'));
                    }
                }
            });
        }
        
        window.onload = function() {
            const initGoogle = () => {
                if (typeof google !== 'undefined' && google.accounts) {
                    google.accounts.id.initialize({
                        client_id: '863511518630-t4oj6ktd9net7g1pj9a8etrot6ict9md.apps.googleusercontent.com',
                        callback: handleCredentialResponse
                    });
                    
                    google.accounts.id.renderButton(
                        document.getElementById('googleSignInDiv'),
                        { theme: 'outline', size: 'large', width: 400, text: 'continue_with' }
                    );
                } else {
                    setTimeout(initGoogle, 100);
                }
            };
            initGoogle();
        };

        document.getElementById('forgotModal').addEventListener('click', function(e) {
            if(e.target === this) closeForgotModal();
        });
        
        if(document.getElementById('otp')) {
            const otpInput = document.getElementById('otp');
            otpInput.addEventListener('input', function(e) {
                this.value = this.value.replace(/[^0-9]/g, '');
                if(this.value.length > 6) {
                    this.value = this.value.slice(0, 6);
                }
            });
        }

        if(document.getElementById('confirm_password')) {
            document.getElementById('confirm_password').addEventListener('input', function() {
                const newPass = document.getElementById('new_password').value;
                const confirmPass = this.value;
                if(confirmPass && newPass !== confirmPass) {
                    this.setCustomValidity('Passwords do not match');
                } else {
                    this.setCustomValidity('');
                }
            });
        }
    </script>
</body>
</html>
<?php ob_end_flush(); ?>