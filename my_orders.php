<?php
session_start();
include 'dbconnect.php';

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Database connection
try {
    $pdo = new PDO("mysql:host=localhost;dbname=pikkit", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'];

// Get filter parameters
$filterStatus = isset($_GET['status']) ? $_GET['status'] : 'all';
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query based on filters
try {
    $sql = "SELECT o.*, p.name as product_name, p.image as product_image, p.price as unit_price, u.name as seller_name 
            FROM orders o 
            JOIN products p ON o.product_id = p.id 
            JOIN users u ON p.seller_id = u.id 
            WHERE o.user_id = ?";
    
    $params = [$userId];
    
    // Add status filter
    if($filterStatus !== 'all') {
        $sql .= " AND o.status = ?";
        $params[] = $filterStatus;
    }
    
    // Add search filter
    if($searchQuery) {
        $sql .= " AND p.name LIKE ?";
        $params[] = "%$searchQuery%";
    }
    
    $sql .= " ORDER BY o.purchase_date DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get order statistics
    $statsStmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_orders,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
            SUM(total_price) as total_spent
        FROM orders 
        WHERE user_id = ?
    ");
    $statsStmt->execute([$userId]);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
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
        
        /* Header */
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
        
        /* Container */
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
        
        /* Stats Cards */
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
        
        .stat-icon {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 48px;
            opacity: 0.1;
        }
        
        /* Filters */
        .filters-section {
            background: white;
            padding: 24px;
            border-radius: 16px;
            box-shadow: var(--shadow-sm);
            border: 2px solid var(--border-color);
            margin-bottom: 32px;
        }
        
        .filters-row {
            display: flex;
            gap: 16px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        
        .filter-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--secondary-gray);
            margin-bottom: 8px;
            display: block;
        }
        
        .filter-select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            background: white;
            cursor: pointer;
            transition: var(--transition);
            font-family: inherit;
        }
        
        .filter-select:focus {
            outline: none;
            border-color: var(--primary-pink);
            box-shadow: 0 0 0 4px rgba(255, 182, 217, 0.15);
        }
        
        .search-input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 14px;
            transition: var(--transition);
            font-family: inherit;
        }
        
        .search-input:focus {
            outline: none;
            border-color: var(--primary-pink);
            box-shadow: 0 0 0 4px rgba(255, 182, 217, 0.15);
        }
        
        .filter-btn {
            padding: 12px 24px;
            background: linear-gradient(135deg, var(--primary-pink), var(--accent-pink));
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            font-family: inherit;
            margin-top: 24px;
        }
        
        .filter-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 107, 157, 0.4);
        }
        
        /* Orders List */
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
        
        .status-completed {
            background: rgba(40, 167, 69, 0.15);
            color: #1e7e34;
            border: 2px solid rgba(40, 167, 69, 0.3);
        }
        
        .status-cancelled {
            background: rgba(220, 53, 69, 0.15);
            color: #bd2130;
            border: 2px solid rgba(220, 53, 69, 0.3);
        }
        
        .order-content {
            display: flex;
            gap: 20px;
            align-items: center;
        }
        
        .order-product-image {
            width: 100px;
            height: 100px;
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
            margin-bottom: 8px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .order-meta {
            display: flex;
            gap: 24px;
            flex-wrap: wrap;
            margin-top: 12px;
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
        
        .seller-name {
            color: var(--accent-pink);
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
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
        }
        
        .empty-icon {
            font-size: 100px;
            margin-bottom: 24px;
            opacity: 0.3;
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
        
        /* Responsive */
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
            
            .filters-row {
                flex-direction: column;
            }
            
            .filter-group {
                width: 100%;
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
        <a href="index.php" class="back-link">← Back to Home</a>
    </header>
    
    <div class="container">
        <div class="page-header">
            <h1 class="page-title">My Orders</h1>
            <p class="page-subtitle">Track and manage your purchases</p>
        </div>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Orders</div>
                <div class="stat-value"><?php echo number_format($stats['total_orders']); ?></div>
                <div class="stat-icon"></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Pending</div>
                <div class="stat-value"><?php echo number_format($stats['pending_orders']); ?></div>
                <div class="stat-icon"></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Completed</div>
                <div class="stat-value"><?php echo number_format($stats['completed_orders']); ?></div>
                <div class="stat-icon"></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Spent</div>
                <div class="stat-value">Rs. <?php echo number_format($stats['total_spent']); ?></div>
                <div class="stat-icon"></div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filters-section">
            <form method="GET" action="my_orders.php">
                <div class="filters-row">
                    <div class="filter-group">
                        <label class="filter-label">Filter by Status</label>
                        <select name="status" class="filter-select" onchange="this.form.submit()">
                            <option value="all" <?php echo $filterStatus === 'all' ? 'selected' : ''; ?>>All Orders</option>
                            <option value="pending" <?php echo $filterStatus === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="completed" <?php echo $filterStatus === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $filterStatus === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Search Products</label>
                        <input 
                            type="text" 
                            name="search" 
                            class="search-input" 
                            placeholder="Search by product name..."
                            value="<?php echo htmlspecialchars($searchQuery); ?>"
                        >
                    </div>
                </div>
                <button type="submit" class="filter-btn"> Apply Filters</button>
            </form>
        </div>
        
        <!-- Orders List -->
        <div class="orders-section">
            <div class="orders-header">
                <h2 class="orders-title">Order History</h2>
                <span class="orders-count"><?php echo count($orders); ?> <?php echo count($orders) === 1 ? 'order' : 'orders'; ?></span>
            </div>
            
            <?php if(count($orders) > 0): ?>
                <div class="orders-list">
                    <?php foreach($orders as $order): ?>
                        <div class="order-card">
                            <div class="order-header-row">
                                <div class="order-info">
                                    <div class="order-id">Order #<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></div>
                                    <div class="order-date"><?php echo date('M d, Y g:i A', strtotime($order['purchase_date'])); ?></div>
                                </div>
                                <div class="order-status status-<?php echo $order['status']; ?>">
                                    <?php 
                                    $statusIcons = [
                                        'pending' => '',
                                        'completed' => '',
                                        'cancelled' => ''
                                    ];
                                    echo $statusIcons[$order['status']] . ' ' . ucfirst($order['status']); 
                                    ?>
                                </div>
                            </div>
                            
                            <div class="order-content">
                                <?php if(!empty($order['product_image'])): ?>
                                    <img src="uploads/products/<?php echo htmlspecialchars($order['product_image']); ?>" alt="Product" class="order-product-image">
                                <?php else: ?>
                                    <div class="order-product-image" style="display: flex; align-items: center; justify-content: center; color: #999; font-size: 36px;">📦</div>
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
                                            <span class="meta-value">Rs. <?php echo number_format($order['unit_price']); ?></span>
                                        </div>
                                        <div class="meta-item">
                                            <span class="meta-label">Seller</span>
                                            <span class="meta-value seller-name">👤 <?php echo htmlspecialchars($order['seller_name']); ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="order-price">
                                    <div class="price-label">Total Price</div>
                                    <div class="price-value">Rs. <?php echo number_format($order['total_price']); ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon"></div>
                    <h3 class="empty-title">No Orders Found</h3>
                    <p class="empty-text">
                        <?php if($searchQuery || $filterStatus !== 'all'): ?>
                            No orders match your current filters. Try adjusting your search criteria.
                        <?php else: ?>
                            You haven't placed any orders yet. Start shopping to see your orders here!
                        <?php endif; ?>
                    </p>
                    <?php if(!$searchQuery && $filterStatus === 'all'): ?>
                        <a href="index.php" class="empty-action">Start Shopping</a>
                    <?php else: ?>
                        <a href="my_orders.php" class="empty-action">Clear Filters</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>