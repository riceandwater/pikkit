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
        SELECT b.*, p.name as product_name, p.image as product_image, p.price as unit_price
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
    
    // Update order status to processing if it was pending (for COD)
    if($order['status'] === 'pending') {
        $stmt = $pdo->prepare("UPDATE buyers SET status = 'processing' WHERE id = ?");
        $stmt->execute([$orderId]);
        $order['status'] = 'processing';
    }
    
} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Successful - PikKiT</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary-pink: #FFB6D9;
            --accent-pink: #FF6B9D;
            --success-green: #28a745;
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
        
        .success-container {
            background: white;
            border-radius: 20px;
            padding: 50px 40px;
            max-width: 600px;
            width: 100%;
            box-shadow: var(--shadow-md);
            border: 2px solid var(--border-color);
            text-align: center;
        }
        
        .success-icon {
            width: 100px;
            height: 100px;
            background: var(--success-green);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            animation: scaleIn 0.5s ease;
        }
        
        @keyframes scaleIn {
            from {
                transform: scale(0);
                opacity: 0;
            }
            to {
                transform: scale(1);
                opacity: 1;
            }
        }
        
        .checkmark {
            width: 50px;
            height: 50px;
            border: 5px solid white;
            border-radius: 50%;
            position: relative;
        }
        
        .checkmark::after {
            content: '';
            position: absolute;
            top: 8px;
            left: 14px;
            width: 12px;
            height: 22px;
            border: solid white;
            border-width: 0 5px 5px 0;
            transform: rotate(45deg);
        }
        
        .success-title {
            font-family: 'Outfit', sans-serif;
            font-size: 32px;
            font-weight: 700;
            color: var(--secondary-gray);
            margin-bottom: 12px;
        }
        
        .success-message {
            font-size: 16px;
            color: #666;
            margin-bottom: 35px;
            line-height: 1.6;
        }
        
        .order-details {
            background: var(--light-gray);
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            text-align: left;
        }
        
        .order-header {
            font-weight: 700;
            font-size: 18px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 14px;
        }
        
        .detail-label {
            color: #666;
            font-weight: 500;
        }
        
        .detail-value {
            font-weight: 600;
            color: var(--secondary-gray);
        }
        
        .product-info {
            display: flex;
            gap: 15px;
            padding: 15px;
            background: white;
            border-radius: 10px;
            margin-top: 15px;
            border: 2px solid var(--border-color);
        }
        
        .product-image {
            width: 70px;
            height: 70px;
            border-radius: 8px;
            overflow: hidden;
            flex-shrink: 0;
            border: 2px solid var(--border-color);
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .product-details {
            flex: 1;
            text-align: left;
        }
        
        .product-name {
            font-weight: 600;
            font-size: 15px;
            margin-bottom: 6px;
        }
        
        .product-meta {
            font-size: 13px;
            color: #666;
        }
        
        .total-amount {
            display: flex;
            justify-content: space-between;
            padding-top: 15px;
            margin-top: 15px;
            border-top: 2px solid var(--border-color);
            font-size: 18px;
            font-weight: 700;
        }
        
        .total-amount .amount {
            color: var(--accent-pink);
        }
        
        .delivery-info {
            background: #e7f3ff;
            border: 2px solid #0066cc;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 30px;
            font-size: 14px;
            color: #004085;
        }
        
        .delivery-info strong {
            display: block;
            margin-bottom: 8px;
            font-size: 15px;
        }
        
        .action-buttons {
            display: flex;
            gap: 12px;
        }
        
        .btn {
            flex: 1;
            padding: 16px;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-block;
            font-family: inherit;
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
            background: transparent;
            color: var(--secondary-gray);
            border: 2px solid var(--border-color);
        }
        
        .btn-secondary:hover {
            border-color: var(--primary-pink);
            background: rgba(255, 182, 217, 0.05);
        }
        
        .payment-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .badge-cod {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-esewa {
            background: #d4edda;
            color: #155724;
        }
        
        @media (max-width: 600px) {
            .success-container {
                padding: 35px 25px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .product-info {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
            
            .product-details {
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-icon">
            <div class="checkmark"></div>
        </div>
        
        <h1 class="success-title">Order Placed Successfully!</h1>
        <p class="success-message">
            Thank you for your purchase. Your order has been received and is being processed.
        </p>
        
        <div class="order-details">
            <div class="order-header">Order Details</div>
            
            <div class="detail-row">
                <span class="detail-label">Order ID:</span>
                <span class="detail-value">#<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Order Date:</span>
                <span class="detail-value"><?php echo date('M d, Y', strtotime($order['purchase_date'])); ?></span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Payment Method:</span>
                <span class="detail-value">
                    <span class="payment-badge <?php echo $order['payment_method'] === 'COD' ? 'badge-cod' : 'badge-esewa'; ?>">
                        <?php echo $order['payment_method'] === 'COD' ? 'Cash on Delivery' : 'eSewa'; ?>
                    </span>
                </span>
            </div>
            
            <?php if(!empty($order['transaction_id'])): ?>
            <div class="detail-row">
                <span class="detail-label">Transaction ID:</span>
                <span class="detail-value"><?php echo htmlspecialchars($order['transaction_id']); ?></span>
            </div>
            <?php endif; ?>
            
            <div class="detail-row">
                <span class="detail-label">Delivery Address:</span>
                <span class="detail-value"><?php echo htmlspecialchars($order['shipping_address']); ?></span>
            </div>
            
            <div class="product-info">
                <div class="product-image">
                    <?php if(!empty($order['product_image'])): ?>
                        <img src="uploads/products/<?php echo htmlspecialchars($order['product_image']); ?>" alt="<?php echo htmlspecialchars($order['product_name']); ?>">
                    <?php else: ?>
                        <div style="width:100%;height:100%;background:#f0f0f0;"></div>
                    <?php endif; ?>
                </div>
                <div class="product-details">
                    <div class="product-name"><?php echo htmlspecialchars($order['product_name']); ?></div>
                    <div class="product-meta">
                        Quantity: <?php echo $order['quantity']; ?> × Rs. <?php echo number_format($order['unit_price']); ?>
                    </div>
                </div>
            </div>
            
            <div class="total-amount">
                <span>Total Paid:</span>
                <span class="amount">Rs. <?php echo number_format($order['total_price']); ?></span>
            </div>
        </div>
        
        <?php if($order['payment_method'] === 'COD'): ?>
        <div class="delivery-info">
            <strong> Delivery Information</strong>
            Please keep Rs. <?php echo number_format($order['total_price']); ?> ready in cash. 
            Our delivery partner will contact you at <?php echo htmlspecialchars($order['buyer_phone']); ?> 
            for delivery confirmation.
        </div>
        <?php else: ?>
        <div class="delivery-info">
            <strong>Payment Confirmed</strong>
            Your payment has been processed successfully via eSewa. 
            We'll send order updates to <?php echo htmlspecialchars($order['buyer_email']); ?>.
        </div>
        <?php endif; ?>
        
        <div class="action-buttons">
            <a href="index.php" class="btn btn-primary">Continue Shopping</a>
        </div>
    </div>
</body>
</html>