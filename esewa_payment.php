<?php
session_start();
include 'dbconnect.php';

try {
    $pdo = new PDO("mysql:host=localhost;dbname=pikkit", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if($orderId <= 0) {
    header("Location: index.php");
    exit();
}

// Fetch order details
try {
    $stmt = $pdo->prepare("
        SELECT b.*, p.name as product_name, p.image as product_image 
        FROM buyers b 
        JOIN products p ON b.product_id = p.id 
        WHERE b.id = ? AND b.user_id = ?
    ");
    $stmt->execute([$orderId, $_SESSION['user_id']]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if(!$order) {
        header("Location: index.php");
        exit();
    }
} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Handle payment confirmation
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment'])) {
    $esewaId = trim($_POST['esewa_id']);
    $esewaPin = $_POST['esewa_pin'];
    
    if(!empty($esewaId) && !empty($esewaPin)) {
        // Simulate payment processing
        try {
            $stmt = $pdo->prepare("UPDATE buyers SET status = 'completed' WHERE id = ?");
            $stmt->execute([$orderId]);
            
            header("Location: order_success.php?order_id=" . $orderId);
            exit();
        } catch(PDOException $e) {
            $error = "Payment processing failed";
        }
    } else {
        $error = "Please enter eSewa ID and PIN";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>eSewa Payment - PikKiT</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --esewa-green: #60BB46;
            --esewa-dark: #2B7A0B;
            --accent-pink: #FF6B9D;
            --secondary-gray: #2C2C2C;
            --light-gray: #F8F9FA;
            --border-color: #E8E8E8;
            --white: #FFFFFF;
            --shadow-md: 0 4px 16px rgba(0,0,0,0.1);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        body {
            font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--light-gray);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .payment-container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 500px;
            width: 100%;
            box-shadow: var(--shadow-md);
            border: 2px solid var(--border-color);
        }
        
        .esewa-logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .esewa-logo h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 42px;
            font-weight: 800;
            color: var(--esewa-green);
            letter-spacing: -1px;
        }
        
        .demo-badge {
            display: inline-block;
            background: #ffc107;
            color: #000;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            margin-top: 10px;
            text-transform: uppercase;
        }
        
        .order-info {
            background: var(--light-gray);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 14px;
        }
        
        .info-label {
            color: #666;
        }
        
        .info-value {
            font-weight: 600;
        }
        
        .total-amount {
            font-size: 32px;
            font-weight: 800;
            color: var(--esewa-green);
            text-align: center;
            margin: 20px 0;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: var(--secondary-gray);
            margin-bottom: 8px;
        }
        
        .form-input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 15px;
            font-family: inherit;
            transition: var(--transition);
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--esewa-green);
            box-shadow: 0 0 0 4px rgba(96, 187, 70, 0.15);
        }
        
        .demo-note {
            background: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #856404;
        }
        
        .demo-note strong {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .demo-credentials {
            background: white;
            padding: 10px;
            border-radius: 6px;
            margin-top: 10px;
            font-family: monospace;
            font-size: 12px;
        }
        
        .btn-container {
            display: flex;
            gap: 12px;
            margin-top: 25px;
        }
        
        .btn {
            flex: 1;
            padding: 16px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            font-family: inherit;
        }
        
        .btn-pay {
            background: var(--esewa-green);
            color: white;
            box-shadow: 0 4px 16px rgba(96, 187, 70, 0.3);
        }
        
        .btn-pay:hover {
            background: var(--esewa-dark);
            transform: translateY(-2px);
        }
        
        .btn-cancel {
            background: transparent;
            color: var(--secondary-gray);
            border: 2px solid var(--border-color);
        }
        
        .btn-cancel:hover {
            border-color: #dc3545;
            color: #dc3545;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 2px solid #f5c6cb;
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .security-note {
            text-align: center;
            font-size: 12px;
            color: #999;
            margin-top: 20px;
        }
        
        @media (max-width: 600px) {
            .payment-container {
                padding: 25px;
            }
            
            .btn-container {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <div class="esewa-logo">
            <h1>eSewa</h1>
            <span class="demo-badge">Demo Mode</span>
        </div>
        
        <?php if(isset($error)): ?>
            <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="order-info">
            <div class="info-row">
                <span class="info-label">Order ID:</span>
                <span class="info-value">#<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Product:</span>
                <span class="info-value"><?php echo htmlspecialchars($order['product_name']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Quantity:</span>
                <span class="info-value"><?php echo $order['quantity']; ?></span>
            </div>
        </div>
        
        <div class="total-amount">
            Rs. <?php echo number_format($order['total_price']); ?>
        </div>
        
        <div class="demo-note">
            <strong>🎭 DEMO PAYMENT GATEWAY</strong>
            This is a test environment. Use any credentials to complete the payment.
            <div class="demo-credentials">
                eSewa ID: demo@esewa.com.np<br>
                PIN: 1234 (any 4 digits)
            </div>
        </div>
        
        <form method="POST">
            <div class="form-group">
                <label class="form-label">eSewa ID / Mobile Number</label>
                <input type="text" name="esewa_id" class="form-input" 
                       placeholder="demo@esewa.com.np or 98XXXXXXXX" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">MPIN / Password</label>
                <input type="password" name="esewa_pin" class="form-input" 
                       placeholder="Enter 4-digit PIN" maxlength="4" required>
            </div>
            
            <div class="btn-container">
                <button type="button" class="btn btn-cancel" onclick="window.location.href='buy.php?id=<?php echo $order['product_id']; ?>'">
                    Cancel
                </button>
                <button type="submit" name="confirm_payment" class="btn btn-pay">
                    Pay Now
                </button>
            </div>
        </form>
        
        <div class="security-note">
            🔒 Secured by eSewa Demo Gateway
        </div>
    </div>
</body>
</html>