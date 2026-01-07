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

// Handle status update
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $orderId = (int)$_POST['order_id'];
    $newStatus = $_POST['new_status'];
    
    $validStatuses = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'failed'];
    
    if(in_array($newStatus, $validStatuses)) {
        try {
            // Verify the seller owns this order item
            $checkStmt = $conn->prepare("
                SELECT oi.id 
                FROM order_items oi 
                WHERE oi.order_id = ? AND oi.seller_id = ?
            ");
            $checkStmt->bind_param("ii", $orderId, $userId);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            
            if($result->num_rows > 0) {
                // Update order status
                $updateStmt = $conn->prepare("UPDATE orders SET order_status = ? WHERE id = ?");
                $updateStmt->bind_param("si", $newStatus, $orderId);
                $updateStmt->execute();
                $updateStmt->close();
                
                $successMessage = "Order status updated successfully!";
            } else {
                $errorMessage = "You don't have permission to update this order.";
            }
            $checkStmt->close();
            
        } catch(Exception $e) {
            $errorMessage = "Failed to update order status.";
            error_log("Status update error: " . $e->getMessage());
        }
    }
}

// Fetch orders where user is the seller
try {
    $sql = "SELECT 
                o.id as order_id,
                o.order_number,
                o.order_status,
                o.created_at,
                o.total_amount,
                oi.product_id,
                oi.product_name,
                oi.product_price,
                oi.quantity,
                oi.subtotal,
                p.image as product_image,
                u.name as buyer_name,
                u.email as buyer_email,
                u.phone as buyer_phone,
                b.shipping_address
            FROM orders o
            JOIN order_items oi ON o.id = oi.order_id
            LEFT JOIN products p ON oi.product_id = p.id
            LEFT JOIN users u ON o.user_id = u.id
            LEFT JOIN buyers b ON o.user_id = b.user_id AND oi.product_id = b.product_id
            WHERE oi.seller_id = ?
            ORDER BY o.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Get seller statistics
    $statsQuery = "
        SELECT 
            COUNT(DISTINCT o.id) as total_orders,
            SUM(CASE WHEN o.order_status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
            SUM(CASE WHEN o.order_status = 'processing' THEN 1 ELSE 0 END) as processing_orders,
            SUM(CASE WHEN o.order_status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders,
            COALESCE(SUM(oi.subtotal), 0) as total_revenue
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        WHERE oi.seller_id = ?
    ";
    
    $statsStmt = $conn->prepare($statsQuery);
    $statsStmt->bind_param("i", $userId);
    $statsStmt->execute();
    $statsResult = $statsStmt->get_result();
    $stats = $statsResult->fetch_assoc();
    $statsStmt->close();
    
    if(!$stats || $stats['total_orders'] == 0) {
        $stats = [
            'total_orders' => 0,
            'pending_orders' => 0,
            'processing_orders' => 0,
            'delivered_orders' => 0,
            'total_revenue' => 0
        ];
    }
    
} catch(Exception $e) {
    $orders = [];
    $stats = [
        'total_orders' => 0,
        'pending_orders' => 0,
        'processing_orders' => 0,
        'delivered_orders' => 0,
        'total_revenue' => 0
    ];
    error_log("Error fetching seller orders: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Sales - PikKiT</title>
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
        
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 14px;
            font-weight: 600;
            border: 2px solid;
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
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
        
        .status-update-form {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .status-select {
            padding: 8px 12px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            font-family: inherit;
        }
        
        .status-select:focus {
            outline: none;
            border-color: var(--primary-pink);
        }
        
        .update-btn {
            padding: 8px 16px;
            background: linear-gradient(135deg, var(--primary-pink), var(--accent-pink));
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .update-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 107, 157, 0.4);
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
        
        .buyer-info {
            background: white;
            padding: 16px;
            border-radius: 10px;
            border: 2px solid var(--border-color);
            margin-top: 12px;
        }
        
        .buyer-header {
            font-size: 13px;
            color: #999;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }
        
        .buyer-details {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .buyer-detail-row {
            display: flex;
            gap: 8px;
            font-size: 14px;
        }
        
        .buyer-detail-label {
            color: #666;
            min-width: 80px;
        }
        
        .buyer-detail-value {
            color: var(--secondary-gray);
            font-weight: 600;
            flex: 1;
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
            }
            
            .order-product-image {
                width: 100%;
                height: 180px;
            }
            
            .order-header-row {
                flex-direction: column;
            }
            
            .status-update-form {
                width: 100%;
            }
            
            .status-select {
                flex: 1;
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
            <h1 class="page-title">My Sales</h1>
            <p class="page-subtitle">Manage your orders and update delivery status</p>
        </div>
        
        <?php if(isset($successMessage)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($successMessage); ?></div>
        <?php endif; ?>
        
        <?php if(isset($errorMessage)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($errorMessage); ?></div>
        <?php endif; ?>
        
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
                <div class="stat-label">Processing</div>
                <div class="stat-value"><?php echo number_format($stats['processing_orders']); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Revenue</div>
                <div class="stat-value">Rs. <?php echo number_format($stats['total_revenue'], 2); ?></div>
            </div>
        </div>
        
        <div class="orders-section">
            <div class="orders-header">
                <h2 class="orders-title">Orders Received</h2>
                <span class="orders-count"><?php echo count($orders); ?> <?php echo count($orders) === 1 ? 'order' : 'orders'; ?></span>
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
                                <form method="POST" class="status-update-form">
                                    <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                    <select name="new_status" class="status-select">
                                        <option value="pending" <?php echo $order['order_status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="confirmed" <?php echo $order['order_status'] == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                        <option value="processing" <?php echo $order['order_status'] == 'processing' ? 'selected' : ''; ?>>Processing</option>
                                        <option value="shipped" <?php echo $order['order_status'] == 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                        <option value="delivered" <?php echo $order['order_status'] == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                        <option value="failed" <?php echo $order['order_status'] == 'failed' ? 'selected' : ''; ?>>Failed</option>
                                    </select>
                                    <button type="submit" name="update_status" class="update-btn">Update</button>
                                </form>
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
                                    
                                    <?php if(!empty($order['buyer_name'])): ?>
                                    <div class="buyer-info">
                                        <div class="buyer-header">Buyer Information</div>
                                        <div class="buyer-details">
                                            <div class="buyer-detail-row">
                                                <span class="buyer-detail-label">Name:</span>
                                                <span class="buyer-detail-value"><?php echo htmlspecialchars($order['buyer_name']); ?></span>
                                            </div>
                                            <?php if(!empty($order['buyer_email'])): ?>
                                            <div class="buyer-detail-row">
                                                <span class="buyer-detail-label">Email:</span>
                                                <span class="buyer-detail-value"><?php echo htmlspecialchars($order['buyer_email']); ?></span>
                                            </div>
                                            <?php endif; ?>
                                            <?php if(!empty($order['buyer_phone'])): ?>
                                            <div class="buyer-detail-row">
                                                <span class="buyer-detail-label">Phone:</span>
                                                <span class="buyer-detail-value"><?php echo htmlspecialchars($order['buyer_phone']); ?></span>
                                            </div>
                                            <?php endif; ?>
                                            <?php if(!empty($order['shipping_address'])): ?>
                                            <div class="buyer-detail-row">
                                                <span class="buyer-detail-label">Address:</span>
                                                <span class="buyer-detail-value"><?php echo htmlspecialchars($order['shipping_address']); ?></span>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="order-price">
                                    <div class="price-label">Total</div>
                                    <div class="price-value">Rs. <?php echo number_format($order['subtotal'], 2); ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <h3 class="empty-title">No Orders Yet</h3>
                    <p class="empty-text">
                        You haven't received any orders yet. Start selling to see your orders here!
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>