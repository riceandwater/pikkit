<?php
session_start();
include 'dbconnect.php';

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if($orderId <= 0) {
    header("Location: index.php");
    exit();
}

// Fetch order details from the orders and order_items tables
try {
    // Get order header information
    $orderQuery = "
        SELECT 
            o.id,
            o.order_number,
            o.user_id,
            o.buyer_name,
            o.buyer_email,
            o.buyer_phone,
            o.shipping_address,
            o.payment_method,
            o.payment_status,
            o.order_status,
            o.total_amount,
            o.transaction_id,
            o.created_at
        FROM orders o
        WHERE o.id = ? AND o.user_id = ?
    ";
    
    $orderStmt = $conn->prepare($orderQuery);
    $orderStmt->bind_param("ii", $orderId, $_SESSION['user_id']);
    $orderStmt->execute();
    $orderResult = $orderStmt->get_result();
    $order = $orderResult->fetch_assoc();
    $orderStmt->close();
    
    if(!$order) {
        header("Location: index.php");
        exit();
    }
    
    // Get order items with seller information
    $itemsQuery = "
        SELECT 
            oi.id,
            oi.product_id,
            oi.product_name,
            oi.product_price,
            oi.quantity,
            oi.subtotal,
            p.image as product_image,
            u.name as seller_name,
            u.email as seller_email,
            u.phone as seller_phone
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        LEFT JOIN users u ON oi.seller_id = u.id
        WHERE oi.order_id = ?
    ";
    
    $itemsStmt = $conn->prepare($itemsQuery);
    $itemsStmt->bind_param("i", $orderId);
    $itemsStmt->execute();
    $itemsResult = $itemsStmt->get_result();
    $orderItems = $itemsResult->fetch_all(MYSQLI_ASSOC);
    $itemsStmt->close();
    
    // Update order status from 'pending' to 'processing' after successful purchase
    if($order['order_status'] === 'pending') {
        $updateQuery = "UPDATE orders SET order_status = 'processing' WHERE id = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param("i", $orderId);
        $updateStmt->execute();
        $updateStmt->close();
        $order['order_status'] = 'processing';
    }
    
} catch(Exception $e) {
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
            max-width: 800px;
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
        
        .products-list {
            margin-top: 20px;
        }
        
        .product-info {
            display: flex;
            gap: 15px;
            padding: 15px;
            background: white;
            border-radius: 10px;
            margin-bottom: 12px;
            border: 2px solid var(--border-color);
            align-items: flex-start;
        }
        
        .product-info:last-child {
            margin-bottom: 0;
        }
        
        .product-image {
            width: 90px;
            height: 90px;
            border-radius: 8px;
            overflow: hidden;
            flex-shrink: 0;
            border: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f5f5f5;
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
            margin-bottom: 8px;
            line-height: 1.4;
        }
        
        .product-meta {
            font-size: 13px;
            color: #666;
            margin-bottom: 10px;
        }
        
        .seller-info-inline {
            background: #f0f8ff;
            padding: 10px 12px;
            border-radius: 6px;
            margin-top: 8px;
            font-size: 12px;
        }
        
        .seller-info-inline strong {
            color: var(--accent-pink);
            display: block;
            margin-bottom: 4px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .seller-contact {
            color: #555;
            line-height: 1.5;
        }
        
        .contact-seller-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: linear-gradient(135deg, var(--primary-pink), var(--accent-pink));
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            transition: var(--transition);
            margin-top: 8px;
            border: none;
            cursor: pointer;
        }
        
        .contact-seller-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(255, 107, 157, 0.4);
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
            
            .product-image {
                width: 120px;
                height: 120px;
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
                <span class="detail-label">Order Number:</span>
                <span class="detail-value">#<?php echo htmlspecialchars($order['order_number']); ?></span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Order Date:</span>
                <span class="detail-value"><?php echo date('M d, Y g:i A', strtotime($order['created_at'])); ?></span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Order Status:</span>
                <span class="detail-value" style="color: #117a8b; font-weight: 700;">Processing</span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Payment Method:</span>
                <span class="detail-value">
                    <span class="payment-badge <?php echo strtolower($order['payment_method']) === 'cod' ? 'badge-cod' : 'badge-esewa'; ?>">
                        <?php echo strtolower($order['payment_method']) === 'cod' ? 'Cash on Delivery' : 'eSewa'; ?>
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
            
            <div class="products-list">
                <?php foreach($orderItems as $item): ?>
                <div class="product-info">
                    <div class="product-image">
                        <?php if(!empty($item['product_image'])): ?>
                            <img src="uploads/products/<?php echo htmlspecialchars($item['product_image']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                        <?php else: ?>
                            <span style="color: #999; font-size: 12px;">No Image</span>
                        <?php endif; ?>
                    </div>
                    <div class="product-details">
                        <div class="product-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                        <div class="product-meta">
                            Quantity: <?php echo $item['quantity']; ?> × Rs. <?php echo number_format($item['product_price'], 2); ?> = Rs. <?php echo number_format($item['subtotal'], 2); ?>
                        </div>
                        
                        <?php if(!empty($item['seller_name'])): ?>
                        <div class="seller-info-inline">
                            <strong>Seller Information</strong>
                            <div class="seller-contact">
                                <?php echo htmlspecialchars($item['seller_name']); ?>
                                <?php if(!empty($item['seller_email'])): ?>
                                <br>Email: <?php echo htmlspecialchars($item['seller_email']); ?>
                                <?php endif; ?>
                                <?php if(!empty($item['seller_phone'])): ?>
                                <br>Phone: <?php echo htmlspecialchars($item['seller_phone']); ?>
                                <?php endif; ?>
                            </div>
                            <?php if(!empty($item['seller_email'])): ?>
                            <a href="https://mail.google.com/mail/?view=cm&fs=1&to=<?php echo urlencode($item['seller_email']); ?>&su=<?php echo urlencode('Question about Order #' . $order['order_number']); ?>&body=<?php echo urlencode('Hello ' . $item['seller_name'] . ',

I have a question about my order:

Order Number: #' . $order['order_number'] . '
Product: ' . $item['product_name'] . '
Order Date: ' . date('M d, Y', strtotime($order['created_at'])) . '

'); ?>" target="_blank" class="contact-seller-btn">
                                Contact via Gmail
                            </a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="total-amount">
                <span>Total Paid:</span>
                <span class="amount">Rs. <?php echo number_format($order['total_amount'], 2); ?></span>
            </div>
        </div>
        
        <?php if(strtolower($order['payment_method']) === 'cod'): ?>
        <div class="delivery-info">
            <strong>Delivery Information</strong>
            Please keep Rs. <?php echo number_format($order['total_amount'], 2); ?> ready in cash. 
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
            <a href="my_orders.php" class="btn btn-secondary">View My Orders</a>
            <a href="index.php" class="btn btn-primary">Continue Shopping</a>
        </div>
    </div>
</body>
</html>