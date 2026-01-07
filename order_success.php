<?php
session_start();
include 'dbconnect.php';

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get order ID
$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if($orderId <= 0) {
    header("Location: index.php");
    exit();
}

// Database connection
try {
    $pdo = new PDO("mysql:host=localhost;dbname=pikkit", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Fetch order details
try {
    $stmt = $pdo->prepare("
        SELECT 
            o.id, o.order_number, o.order_status, o.total_amount, o.created_at,
            oi.product_name, oi.quantity, oi.product_price,
            b.buyer_name, b.buyer_email, b.buyer_phone, b.shipping_address, b.payment_method
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        LEFT JOIN buyers b ON o.user_id = b.user_id AND oi.product_id = b.product_id
        WHERE o.id = ? AND o.user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$orderId, $_SESSION['user_id']]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if(!$order) {
        header("Location: index.php");
        exit();
    }
    
    // Calculate estimated delivery date (3 days from order date)
    $orderDate = new DateTime($order['created_at']);
    $deliveryDate = clone $orderDate;
    $deliveryDate->modify('+3 days');
    
} catch(PDOException $e) {
    die("Error loading order: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmed - PikKiT</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary-pink: #FFB6D9;
            --primary-pink-dark: #FF8FB8;
            --accent-pink: #FF6B9D;
            --secondary-gray: #2C2C2C;
            --light-gray: #F8F9FA;
            --border-color: #E8E8E8;
            --white: #FFFFFF;
            --success-green: #28a745;
            --shadow-sm: 0 2px 8px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 16px rgba(0,0,0,0.1);
            --shadow-lg: 0 8px 32px rgba(0,0,0,0.15);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        body {
            font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--light-gray);
            min-height: 100vh;
            color: var(--secondary-gray);
        }
        
        .header {
            background: var(--white);
            padding: 16px 32px;
            box-shadow: var(--shadow-sm);
            border-bottom: 1px solid var(--border-color);
        }
        
        .logo {
            font-family: 'Outfit', sans-serif;
            font-size: 32px;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary-pink) 0%, var(--accent-pink) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-decoration: none;
            letter-spacing: -1.5px;
            max-width: 1200px;
            margin: 0 auto;
            display: block;
            width: fit-content;
        }
        
        .success-container {
            max-width: 800px;
            margin: 60px auto;
            padding: 0 32px;
        }
        
        .success-card {
            background: white;
            border-radius: 20px;
            padding: 50px 40px;
            box-shadow: var(--shadow-md);
            border: 2px solid var(--border-color);
            text-align: center;
        }
        
        .success-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, var(--success-green) 0%, #20c997 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            box-shadow: 0 8px 24px rgba(40, 167, 69, 0.3);
            animation: scaleIn 0.5s ease;
        }
        
        @keyframes scaleIn {
            from {
                transform: scale(0);
            }
            to {
                transform: scale(1);
            }
        }
        
        .checkmark {
            width: 50px;
            height: 50px;
            border: 4px solid white;
            border-radius: 50%;
            position: relative;
        }
        
        .checkmark::after {
            content: '';
            position: absolute;
            width: 12px;
            height: 24px;
            border: solid white;
            border-width: 0 4px 4px 0;
            top: 6px;
            left: 16px;
            transform: rotate(45deg);
        }
        
        .success-title {
            font-family: 'Outfit', sans-serif;
            font-size: 36px;
            font-weight: 800;
            color: var(--secondary-gray);
            margin-bottom: 12px;
            letter-spacing: -1.5px;
        }
        
        .success-subtitle {
            font-size: 16px;
            color: #666;
            margin-bottom: 40px;
            line-height: 1.6;
        }
        
        .order-details {
            background: var(--light-gray);
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 30px;
            text-align: left;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            color: #666;
            font-weight: 500;
        }
        
        .detail-value {
            color: var(--secondary-gray);
            font-weight: 600;
            text-align: right;
        }
        
        .order-number-highlight {
            color: var(--accent-pink);
            font-weight: 700;
            font-size: 18px;
        }
        
        .delivery-info-box {
            background: rgba(255, 182, 217, 0.1);
            border: 2px solid var(--primary-pink);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .delivery-title {
            font-size: 14px;
            color: #666;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .delivery-date {
            font-size: 20px;
            font-weight: 700;
            color: var(--accent-pink);
        }
        
        .action-buttons {
            display: flex;
            gap: 16px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 14px 32px;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 700;
            text-decoration: none;
            transition: var(--transition);
            cursor: pointer;
            border: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-pink) 0%, var(--accent-pink) 100%);
            color: white;
            box-shadow: 0 4px 16px rgba(255, 107, 157, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 107, 157, 0.4);
        }
        
        .btn-secondary {
            background: white;
            color: var(--secondary-gray);
            border: 2px solid var(--border-color);
        }
        
        .btn-secondary:hover {
            border-color: var(--primary-pink);
            background: rgba(255, 182, 217, 0.05);
        }
        
        .info-text {
            font-size: 14px;
            color: #666;
            margin-top: 30px;
            line-height: 1.6;
        }
        
        @media (max-width: 768px) {
            .success-container {
                margin: 40px auto;
                padding: 0 16px;
            }
            
            .success-card {
                padding: 40px 24px;
            }
            
            .success-title {
                font-size: 28px;
            }
            
            .order-details {
                padding: 20px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <a href="index.php" class="logo">PikKiT</a>
    </header>
    
    <div class="success-container">
        <div class="success-card">
            <div class="success-icon">
                <div class="checkmark"></div>
            </div>
            
            <h1 class="success-title">Order Confirmed!</h1>
            <p class="success-subtitle">
                Thank you for your order. We've received your order and will process it soon.
            </p>
            
            <div class="delivery-info-box">
                <div class="delivery-title">Estimated Delivery Date</div>
                <div class="delivery-date"><?php echo $deliveryDate->format('l, F d, Y'); ?></div>
            </div>
            
            <div class="order-details">
                <div class="detail-row">
                    <span class="detail-label">Order Number</span>
                    <span class="detail-value order-number-highlight">#<?php echo htmlspecialchars($order['order_number']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Product</span>
                    <span class="detail-value"><?php echo htmlspecialchars($order['product_name']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Quantity</span>
                    <span class="detail-value"><?php echo number_format($order['quantity']); ?> pcs</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Payment Method</span>
                    <span class="detail-value"><?php echo htmlspecialchars($order['payment_method']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Total Amount</span>
                    <span class="detail-value order-number-highlight">Rs. <?php echo number_format($order['total_amount'], 2); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Delivery Address</span>
                    <span class="detail-value"><?php echo htmlspecialchars($order['shipping_address']); ?></span>
                </div>
            </div>
            
            <div class="action-buttons">
                <a href="my_orders.php" class="btn btn-primary">View My Orders</a>
                <a href="index.php" class="btn btn-secondary">Continue Shopping</a>
            </div>
            
            <p class="info-text">
                You will receive an order confirmation email at <?php echo htmlspecialchars($order['buyer_email']); ?>
                shortly. You can track your order status in the "My Orders" section.
            </p>
        </div>
    </div>
</body>
</html>