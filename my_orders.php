<?php
session_start();
include 'dbconnect.php';

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$userName = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '';

// Build query to get orders with seller information
try {
    $sql = "SELECT 
                o.id as order_id,
                o.order_number,
                o.order_status,
                o.created_at,
                o.total_amount as order_total,
                oi.id as item_id,
                oi.product_id,
                oi.product_name,
                oi.product_price,
                oi.quantity,
                oi.subtotal,
                p.image as product_image,
                u.name as seller_name,
                u.email as seller_email,
                u.phone as seller_phone
            FROM orders o
            JOIN order_items oi ON o.id = oi.order_id
            LEFT JOIN products p ON oi.product_id = p.id
            LEFT JOIN users u ON oi.seller_id = u.id
            WHERE o.user_id = ?
            ORDER BY o.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Get order statistics
    $statsQuery = "
        SELECT 
            COUNT(DISTINCT o.id) as total_orders,
            SUM(CASE WHEN o.order_status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
            SUM(CASE WHEN o.order_status = 'confirmed' THEN 1 ELSE 0 END) as completed_orders,
            SUM(CASE WHEN o.order_status = 'failed' THEN 1 ELSE 0 END) as cancelled_orders,
            COALESCE(SUM(o.total_amount), 0) as total_spent
        FROM orders o
        WHERE o.user_id = ?
    ";
    
    $statsStmt = $conn->prepare($statsQuery);
    $statsStmt->bind_param("i", $userId);
    $statsStmt->execute();
    $statsResult = $statsStmt->get_result();
    $stats = $statsResult->fetch_assoc();
    $statsStmt->close();
    
    // Ensure stats have default values if no orders exist
    if(!$stats || $stats['total_orders'] == 0) {
        $stats = [
            'total_orders' => 0, 
            'pending_orders' => 0, 
            'completed_orders' => 0, 
            'cancelled_orders' => 0, 
            'total_spent' => 0
        ];
    }
    
} catch(Exception $e) {
    $orders = [];
    $stats = ['total_orders' => 0, 'pending_orders' => 0, 'completed_orders' => 0, 'cancelled_orders' => 0, 'total_spent' => 0];
    error_log("Error fetching orders: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - PikKiT</title>
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
            --warning-orange: #ffc107;
            --danger-red: #dc3545;
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
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
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
            transition: var(--transition);
        }
        
        .logo:hover {
            transform: scale(1.05);
            filter: brightness(1.1);
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--secondary-gray);
            text-decoration: none;
            font-weight: 600;
            padding: 10px 20px;
            border-radius: 10px;
            border: 2px solid var(--border-color);
            transition: var(--transition);
        }
        
        .back-link:hover {
            border-color: var(--primary-pink);
            color: var(--accent-pink);
            background: rgba(255, 182, 217, 0.05);
            transform: translateX(-4px);
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 32px;
        }
        
        .page-header {
            margin-bottom: 32px;
        }
        
        .page-title {
            font-family: 'Outfit', sans-serif;
            font-size: 42px;
            font-weight: 800;
            color: var(--secondary-gray);
            margin-bottom: 8px;
            letter-spacing: -2px;
        }
        
        .page-subtitle {
            color: #666;
            font-size: 16px;
            font-weight: 500;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }
        
        .stat-card {
            background: white;
            padding: 24px;
            border-radius: 16px;
            box-shadow: var(--shadow-sm);
            border: 2px solid var(--border-color);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
            border-color: var(--primary-pink);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, var(--primary-pink), var(--accent-pink));
        }
        
        .stat-label {
            font-size: 13px;
            color: #999;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        
        .stat-value {
            font-family: 'Outfit', sans-serif;
            font-size: 32px;
            font-weight: 800;
            color: var(--secondary-gray);
            letter-spacing: -1px;
        }
        
        .orders-section {
            background: white;
            border-radius: 16px;
            box-shadow: var(--shadow-sm);
            border: 2px solid var(--border-color);
            overflow: hidden;
        }
        
        .orders-header {
            padding: 24px 28px;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .orders-title {
            font-family: 'Outfit', sans-serif;
            font-size: 24px;
            font-weight: 700;
            color: var(--secondary-gray);
        }
        
        .orders-count {
            font-size: 14px;
            color: #999;
            font-weight: 600;
        }
        
        .orders-list {
            padding: 12px;
        }
        
        .order-card {
            background: var(--light-gray);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 16px;
            border: 2px solid transparent;
            transition: var(--transition);
        }
        
        .order-card:last-child {
            margin-bottom: 0;
        }
        
        .order-card:hover {
            border-color: var(--primary-pink);
            box-shadow: var(--shadow-sm);
            transform: translateX(4px);
        }
        
        .order-header-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            gap: 16px;
        }
        
        .order-info {
            flex: 1;
        }
        
        .order-id {
            font-size: 12px;
            color: #999;
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        .order-date {
            font-size: 14px;
            color: #666;
            font-weight: 500;
        }
        
        .order-status {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-pending {
            background: rgba(255, 193, 7, 0.15);
            color: #d39e00;
            border: 2px solid rgba(255, 193, 7, 0.3);
        }
        
        .status-confirmed {
            background: rgba(40, 167, 69, 0.15);
            color: #1e7e34;
            border: 2px solid rgba(40, 167, 69, 0.3);
        }
        
        .status-processing {
            background: rgba(23, 162, 184, 0.15);
            color: #117a8b;
            border: 2px solid rgba(23, 162, 184, 0.3);
        }
        
        .status-shipped {
            background: rgba(111, 66, 193, 0.15);
            color: #5a3296;
            border: 2px solid rgba(111, 66, 193, 0.3);
        }
        
        .status-failed {
            background: rgba(220, 53, 69, 0.15);
            color: #bd2130;
            border: 2px solid rgba(220, 53, 69, 0.3);
        }
        
        .order-content {
            display: flex;
            gap: 20px;
            align-items: flex-start;
        }
        
        .order-product-image {
            width: 120px;
            height: 120px;
            border-radius: 12px;
            object-fit: cover;
            background: white;
            border: 2px solid var(--border-color);
            flex-shrink: 0;
        }
        
        .order-details {
            flex: 1;
        }
        
        .product-name {
            font-size: 18px;
            font-weight: 700;
            color: var(--secondary-gray);
            margin-bottom: 12px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .order-meta {
            display: flex;
            gap: 24px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }
        
        .meta-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .meta-label {
            font-size: 11px;
            color: #999;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .meta-value {
            font-size: 15px;
            font-weight: 700;
            color: var(--secondary-gray);
        }
        
        .seller-info {
            background: white;
            padding: 16px;
            border-radius: 10px;
            border: 2px solid var(--border-color);
            margin-top: 12px;
        }
        
        .seller-header {
            font-size: 13px;
            color: #999;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }
        
        .seller-details {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .seller-detail-row {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }
        
        .seller-detail-label {
            color: #666;
            min-width: 60px;
        }
        
        .seller-detail-value {
            color: var(--secondary-gray);
            font-weight: 600;
        }
        
        .contact-seller-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            background: linear-gradient(135deg, var(--primary-pink), var(--accent-pink));
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            transition: var(--transition);
            margin-top: 12px;
            border: none;
            cursor: pointer;
        }
        
        .contact-seller-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 107, 157, 0.4);
        }
        
        .order-price {
            text-align: right;
        }
        
        .price-label {
            font-size: 12px;
            color: #999;
            margin-bottom: 6px;
        }
        
        .price-value {
            font-family: 'Outfit', sans-serif;
            font-size: 28px;
            font-weight: 800;
            color: var(--accent-pink);
            letter-spacing: -1px;
        }
        
        .empty-state {
            text-align: center;
            padding: 80px 20px;
        }
        
        .empty-title {
            font-family: 'Outfit', sans-serif;
            font-size: 28px;
            font-weight: 700;
            color: var(--secondary-gray);
            margin-bottom: 12px;
        }
        
        .empty-text {
            font-size: 16px;
            color: #999;
            margin-bottom: 32px;
        }
        
        .empty-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 14px 32px;
            background: linear-gradient(135deg, var(--primary-pink), var(--accent-pink));
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 700;
            transition: var(--transition);
            box-shadow: 0 4px 16px rgba(255, 107, 157, 0.3);
        }
        
        .empty-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 107, 157, 0.4);
        }
        
        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 24px 16px;
            }
            
            .page-title {
                font-size: 32px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .order-content {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .order-product-image {
                width: 100%;
                height: 180px;
            }
            
            .order-header-row {
                flex-direction: column;
            }
            
            .order-price {
                text-align: left;
            }
            
            .order-meta {
                gap: 16px;
            }
        }
        
        @media (max-width: 480px) {
            .order-card {
                padding: 20px;
            }
            
            .product-name {
                font-size: 16px;
            }
            
            .price-value {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <a href="index.php" class="logo">PikKiT</a>
        <a href="index.php" class="back-link">Back to Home</a>
    </header>
    
    <div class="container">
        <div class="page-header">
            <h1 class="page-title">My Orders</h1>
            <p class="page-subtitle">Track and manage your purchases</p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Orders</div>
                <div class="stat-value"><?php echo number_format($stats['total_orders']); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Pending</div>
                <div class="stat-value"><?php echo number_format($stats['pending_orders']); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Completed</div>
                <div class="stat-value"><?php echo number_format($stats['completed_orders']); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Spent</div>
                <div class="stat-value">Rs. <?php echo number_format($stats['total_spent'], 2); ?></div>
            </div>
        </div>
        
        <div class="orders-section">
            <div class="orders-header">
                <h2 class="orders-title">Order History</h2>
                <span class="orders-count"><?php echo count($orders); ?> <?php echo count($orders) === 1 ? 'item' : 'items'; ?></span>
            </div>
            
            <?php if(count($orders) > 0): ?>
                <div class="orders-list">
                    <?php foreach($orders as $order): ?>
                        <div class="order-card">
                            <div class="order-header-row">
                                <div class="order-info">
                                    <div class="order-id">Order #<?php echo htmlspecialchars($order['order_number']); ?></div>
                                    <div class="order-date"><?php echo date('M d, Y g:i A', strtotime($order['created_at'])); ?></div>
                                </div>
                                <div class="order-status status-<?php echo htmlspecialchars($order['order_status']); ?>">
                                    <?php echo ucfirst(htmlspecialchars($order['order_status'])); ?>
                                </div>
                            </div>
                            
                            <div class="order-content">
                                <?php if(!empty($order['product_image'])): ?>
                                    <img src="uploads/products/<?php echo htmlspecialchars($order['product_image']); ?>" alt="Product" class="order-product-image">
                                <?php else: ?>
                                    <div class="order-product-image" style="display: flex; align-items: center; justify-content: center; color: #999; font-size: 18px; font-weight: 600;">No Image</div>
                                <?php endif; ?>
                                
                                <div class="order-details">
                                    <div class="product-name"><?php echo htmlspecialchars($order['product_name']); ?></div>
                                    
                                    <div class="order-meta">
                                        <div class="meta-item">
                                            <span class="meta-label">Quantity</span>
                                            <span class="meta-value"><?php echo number_format($order['quantity']); ?> pcs</span>
                                        </div>
                                        <div class="meta-item">
                                            <span class="meta-label">Unit Price</span>
                                            <span class="meta-value">Rs. <?php echo number_format($order['product_price'], 2); ?></span>
                                        </div>
                                    </div>
                                    
                                    <?php if(!empty($order['seller_name'])): ?>
                                    <div class="seller-info">
                                        <div class="seller-header">Seller Information</div>
                                        <div class="seller-details">
                                            <div class="seller-detail-row">
                                                <span class="seller-detail-label">Name:</span>
                                                <span class="seller-detail-value"><?php echo htmlspecialchars($order['seller_name']); ?></span>
                                            </div>
                                            <?php if(!empty($order['seller_email'])): ?>
                                            <div class="seller-detail-row">
                                                <span class="seller-detail-label">Email:</span>
                                                <span class="seller-detail-value"><?php echo htmlspecialchars($order['seller_email']); ?></span>
                                            </div>
                                            <?php endif; ?>
                                            <?php if(!empty($order['seller_phone'])): ?>
                                            <div class="seller-detail-row">
                                                <span class="seller-detail-label">Phone:</span>
                                                <span class="seller-detail-value"><?php echo htmlspecialchars($order['seller_phone']); ?></span>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <?php if(!empty($order['seller_email'])): ?>
                                        <a href="https://mail.google.com/mail/?view=cm&fs=1&to=<?php echo urlencode($order['seller_email']); ?>&su=<?php echo urlencode('Inquiry about Order #' . $order['order_number']); ?>&body=<?php echo urlencode('Hello ' . $order['seller_name'] . ',

I have a question about my order:

Order Number: #' . $order['order_number'] . '
Product: ' . $order['product_name'] . '
Order Date: ' . date('M d, Y', strtotime($order['created_at'])) . '

'); ?>" target="_blank" class="contact-seller-btn">
                                            Contact Seller via Gmail
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="order-price">
                                    <div class="price-label">Subtotal</div>
                                    <div class="price-value">Rs. <?php echo number_format($order['subtotal'], 2); ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <h3 class="empty-title">No Orders Found</h3>
                    <p class="empty-text">
                        You haven't placed any orders yet. Start shopping to see your orders here!
                    </p>
                    <a href="index.php" class="empty-action">Start Shopping</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>