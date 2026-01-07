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

// Initialize arrays
$orders = [];
$stats = [
    'total_orders' => 0,
    'pending_orders' => 0,
    'processing_orders' => 0,
    'delivered_orders' => 0,
    'total_spent' => 0
];

try {
    // Get orders from orders table only
    $sql = "SELECT 
                id,
                order_number,
                order_status,
                created_at,
                updated_at,
                total_amount
            FROM orders
            WHERE user_id = ?
            ORDER BY created_at DESC";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Get statistics
    $statsQuery = "SELECT 
            COUNT(*) as total_orders,
            SUM(CASE WHEN order_status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
            SUM(CASE WHEN order_status = 'processing' THEN 1 ELSE 0 END) as processing_orders,
            SUM(CASE WHEN order_status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders,
            COALESCE(SUM(total_amount), 0) as total_spent
        FROM orders
        WHERE user_id = ?";
    
    $statsStmt = $conn->prepare($statsQuery);
    if (!$statsStmt) {
        throw new Exception("Stats prepare failed: " . $conn->error);
    }
    
    $statsStmt->bind_param("i", $userId);
    $statsStmt->execute();
    $statsResult = $statsStmt->get_result();
    $fetchedStats = $statsResult->fetch_assoc();
    $statsStmt->close();
    
    // Update stats if data exists
    if($fetchedStats) {
        $stats = $fetchedStats;
    }
    
} catch(Exception $e) {
    error_log("Error fetching orders: " . $e->getMessage());
    $orders = [];
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
            --accent-pink: #FF6B9D;
            --secondary-gray: #2C2C2C;
            --light-gray: #F8F9FA;
            --border-color: #E8E8E8;
            --white: #FFFFFF;
            --shadow-sm: 0 2px 8px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 16px rgba(0,0,0,0.1);
            --transition: all 0.3s ease;
        }
        
        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--light-gray);
            min-height: 100vh;
            color: var(--secondary-gray);
        }
        
        .header {
            background: var(--white);
            padding: 16px 32px;
            box-shadow: var(--shadow-sm);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .logo {
            font-family: 'Outfit', sans-serif;
            font-size: 32px;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary-pink), var(--accent-pink));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-decoration: none;
            letter-spacing: -1.5px;
        }
        
        .back-link {
            padding: 10px 20px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            text-decoration: none;
            color: var(--secondary-gray);
            font-weight: 600;
            transition: var(--transition);
        }
        
        .back-link:hover {
            border-color: var(--primary-pink);
            color: var(--accent-pink);
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
            margin-bottom: 8px;
            letter-spacing: -2px;
        }
        
        .page-subtitle {
            color: #666;
            font-size: 16px;
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
            position: relative;
            overflow: hidden;
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
            margin-bottom: 8px;
        }
        
        .stat-value {
            font-family: 'Outfit', sans-serif;
            font-size: 32px;
            font-weight: 800;
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
            padding: 28px;
            margin-bottom: 16px;
            border: 2px solid transparent;
            transition: var(--transition);
        }
        
        .order-card:hover {
            border-color: var(--primary-pink);
            box-shadow: var(--shadow-sm);
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
        }
        
        .order-info {
            flex: 1;
        }
        
        .order-number {
            font-family: 'Outfit', sans-serif;
            font-size: 20px;
            font-weight: 800;
            margin-bottom: 8px;
            color: var(--secondary-gray);
        }
        
        .order-date {
            font-size: 14px;
            color: #666;
            margin-bottom: 4px;
        }
        
        .delivery-info {
            font-size: 13px;
            color: #666;
            margin-top: 8px;
            padding: 8px 12px;
            background: white;
            border-radius: 6px;
            display: inline-block;
        }
        
        .order-status {
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-pending {
            background: rgba(255, 193, 7, 0.15);
            color: #d39e00;
            border: 2px solid rgba(255, 193, 7, 0.3);
        }
        
        .status-confirmed, .status-delivered {
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
        
        .status-failed, .status-cancelled {
            background: rgba(220, 53, 69, 0.15);
            color: #bd2130;
            border: 2px solid rgba(220, 53, 69, 0.3);
        }
        
        .order-content {
            background: white;
            padding: 24px;
            border-radius: 12px;
            border: 2px solid var(--border-color);
        }
        
        .order-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        
        .detail-label {
            font-size: 11px;
            color: #999;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .detail-value {
            font-size: 16px;
            font-weight: 700;
            color: var(--secondary-gray);
        }
        
        .order-amount {
            border-top: 2px solid var(--border-color);
            padding-top: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .amount-label {
            font-size: 16px;
            color: #666;
            font-weight: 600;
        }
        
        .amount-value {
            font-family: 'Outfit', sans-serif;
            font-size: 32px;
            font-weight: 800;
            color: var(--accent-pink);
            letter-spacing: -1px;
        }
        
        .view-details-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: linear-gradient(135deg, var(--primary-pink), var(--accent-pink));
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            transition: var(--transition);
            margin-top: 16px;
        }
        
        .view-details-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 107, 157, 0.4);
        }
        
        .empty-state {
            text-align: center;
            padding: 80px 20px;
        }
        
        .empty-title {
            font-family: 'Outfit', sans-serif;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 12px;
        }
        
        .empty-text {
            font-size: 16px;
            color: #999;
            margin-bottom: 32px;
        }
        
        .empty-action {
            display: inline-flex;
            padding: 14px 32px;
            background: linear-gradient(135deg, var(--primary-pink), var(--accent-pink));
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 700;
            transition: var(--transition);
        }
        
        .empty-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 107, 157, 0.4);
        }
        
        @media (max-width: 768px) {
            .header {
                padding: 16px 20px;
            }
            
            .logo {
                font-size: 24px;
            }
            
            .container {
                padding: 24px 16px;
            }
            
            .page-title {
                font-size: 32px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .order-header {
                flex-direction: column;
            }
            
            .order-details-grid {
                grid-template-columns: 1fr;
            }
            
            .order-amount {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
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
                <div class="stat-value"><?php echo $stats['total_orders']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Pending</div>
                <div class="stat-value"><?php echo $stats['pending_orders']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Processing</div>
                <div class="stat-value"><?php echo $stats['processing_orders']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Spent</div>
                <div class="stat-value">Rs. <?php echo number_format($stats['total_spent'], 2); ?></div>
            </div>
        </div>
        
        <div class="orders-section">
            <div class="orders-header">
                <h2 class="orders-title">Order History</h2>
                <span class="orders-count"><?php echo count($orders); ?> <?php echo count($orders) === 1 ? 'order' : 'orders'; ?></span>
            </div>
            
            <?php if(count($orders) > 0): ?>
                <div class="orders-list">
                    <?php foreach($orders as $order): ?>
                        <?php
                        // Calculate delivery date (3 days from order)
                        $orderDate = new DateTime($order['created_at']);
                        $deliveryDate = clone $orderDate;
                        $deliveryDate->modify('+3 days');
                        ?>
                        <div class="order-card">
                            <div class="order-header">
                                <div class="order-info">
                                    <div class="order-number">#<?php echo htmlspecialchars($order['order_number']); ?></div>
                                    <div class="order-date">Placed on <?php echo date('F d, Y \a\t g:i A', strtotime($order['created_at'])); ?></div>
                                    <?php if($order['order_status'] != 'delivered'): ?>
                                        <div class="delivery-info">
                                             Estimated Delivery: <?php echo $deliveryDate->format('M d, Y'); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="order-status status-<?php echo strtolower($order['order_status']); ?>">
                                    <?php echo ucfirst($order['order_status']); ?>
                                </div>
                            </div>
                            
                            <div class="order-content">
                                <div class="order-details-grid">
                                    <div class="detail-item">
                                        <span class="detail-label">Order ID</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($order['order_number']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Order Date</span>
                                        <span class="detail-value"><?php echo date('M d, Y', strtotime($order['created_at'])); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Status</span>
                                        <span class="detail-value"><?php echo ucfirst($order['order_status']); ?></span>
                                    </div>
                                    <?php if(!empty($order['updated_at'])): ?>
                                    <div class="detail-item">
                                        <span class="detail-label">Last Updated</span>
                                        <span class="detail-value"><?php echo date('M d, Y', strtotime($order['updated_at'])); ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="order-amount">
                                    <span class="amount-label">Total Amount:</span>
                                    <span class="amount-value">Rs. <?php echo number_format($order['total_amount'], 2); ?></span>
                                </div>
                                
                                <!-- Optional: Add a link to view full order details if you have an order details page -->
                                <!-- <a href="order_details.php?id=<?php echo $order['id']; ?>" class="view-details-btn">
                                    View Order Details →
                                </a> -->
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
