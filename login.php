<?php
session_start();
include 'dbconnect.php';

// Convert to PDO for consistency
try {
    $pdo = new PDO("mysql:host=localhost;dbname=pikkit", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

$error = '';
$success = '';
$showOtpForm = false;

// Handle manual login
if(isset($_POST['manual_login'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $pass = $_POST['password'];
    
    if(empty($username) || empty($email) || empty($pass)) {
        $error = "Please fill in all fields";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND name = ?");
        $stmt->execute([$email, $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($user && password_verify($pass, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            header("Location: index.php");
            exit();
        } else {
            $error = "Invalid username, email or password";
        }
    }
}

// Handle forgot password - send OTP
if(isset($_POST['send_otp'])) {
    $email = trim($_POST['forgot_email']);
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($user) {
        // Generate 6-digit OTP
        $otp = rand(100000, 999999);
        $expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        
        // Store OTP in database
        $stmt = $pdo->prepare("INSERT INTO otp_codes (email, otp, expiry) VALUES (?, ?, ?) 
                              ON DUPLICATE KEY UPDATE otp = ?, expiry = ?");
        $stmt->execute([$email, $otp, $expiry, $otp, $expiry]);
        
        // Send email (using PHP mail function)
        $subject = "Pikkit - Password Reset OTP";
        $message = "Your OTP for password reset is: $otp\n\nThis OTP will expire in 15 minutes.";
        $headers = "From: noreply@pikkit.com";
        
        if(mail($email, $subject, $message, $headers)) {
            $_SESSION['forgot_email'] = $email;
            $showOtpForm = true;
            $success = "OTP sent to your email!";
        } else {
            $error = "Failed to send OTP. Please try again.";
        }
    } else {
        $error = "Email not found";
    }
}

// Verify OTP and login
if(isset($_POST['verify_otp'])) {
    $email = $_SESSION['forgot_email'] ?? '';
    $entered_otp = trim($_POST['otp']);
    
    if(!empty($email)) {
        $stmt = $pdo->prepare("SELECT * FROM otp_codes WHERE email = ? AND otp = ? AND expiry > NOW()");
        $stmt->execute([$email, $entered_otp]);
        $otp_record = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($otp_record) {
            // Get user details
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Delete used OTP
            $stmt = $pdo->prepare("DELETE FROM otp_codes WHERE email = ?");
            $stmt->execute([$email]);
            
            // Log user in
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            unset($_SESSION['forgot_email']);
            
            header("Location: index.php");
            exit();
        } else {
            $error = "Invalid or expired OTP";
            $showOtpForm = true;
        }
    }
}

// Check if showing OTP form from session
if(isset($_SESSION['forgot_email']) && !$showOtpForm) {
    $showOtpForm = true;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Pikkit</title>
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
            padding: 8px;
            border-radius: 5px;
            margin-bottom: 12px;
            font-size: 13px;
        }
        
        .alert-error {
            background-color: #fee;
            color: #c33;
            border: 1px solid #fcc;
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
            padding: 9px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #333;
        }
        
        .btn {
            width: 100%;
            padding: 10px;
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
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .google-btn:hover {
            border-color: #333;
            background: #f9f9f9;
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
        
        .forgot-password a:hover {
            text-decoration: underline;
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
        
        .register-link a:hover {
            text-decoration: underline;
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
            margin-top: 15px;
        }
        
        .close-modal:hover {
            background: #e0e0e0;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <img src="images/pikkit logo.png" alt="Pikkit Logo">
            <p>Welcome back! Please login to your account</p>
        </div>
        
        <?php if($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <!-- Google Sign In -->
        <div id="g_id_onload"
             data-client_id="863511518630-t4oj6ktd9net7g1pj9a8etrot6ict9md.apps.googleusercontent.com"
             data-callback="handleCredentialResponse">
        </div>
        
        <button class="google-btn" onclick="googleSignIn()">
            <svg width="20" height="20" viewBox="0 0 24 24">
                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
            </svg>
            Continue with Google
        </button>
        
        <div class="divider">
            <span>OR</span>
        </div>
        
        <!-- Manual Login Form -->
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required>
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
    
    <!-- Forgot Password Modal -->
    <div id="forgotModal" class="modal <?php echo $showOtpForm ? 'active' : ''; ?>">
        <div class="modal-content">
            <div class="modal-header">
                <h2><?php echo $showOtpForm ? 'Enter OTP' : 'Forgot Password'; ?></h2>
            </div>
            
            <?php if(!$showOtpForm): ?>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="forgot_email">Enter your email address</label>
                        <input type="email" id="forgot_email" name="forgot_email" required>
                    </div>
                    <button type="submit" name="send_otp" class="btn btn-primary">Send OTP</button>
                    <button type="button" class="btn close-modal" onclick="closeForgotModal()">Cancel</button>
                </form>
            <?php else: ?>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="otp">Enter 6-digit OTP</label>
                        <input type="text" id="otp" name="otp" maxlength="6" pattern="[0-9]{6}" required>
                    </div>
                    <button type="submit" name="verify_otp" class="btn btn-primary">Verify OTP</button>
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
            document.getElementById('forgotModal').classList.remove('active');
            window.location.href = 'login.php';
        }
        
        function handleCredentialResponse(response) {
            // Send the ID token to your server
            fetch('google_auth.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    credential: response.credential
                })
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    window.location.href = 'index.php';
                } else {
                    alert('Google login failed. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        }
        
        function googleSignIn() {
            google.accounts.id.prompt();
        }

        // Close modal when clicking outside
        document.getElementById('forgotModal').addEventListener('click', function(e) {
            if(e.target === this) {
                closeForgotModal();
            }
        });
    </script>
</body>
</html>