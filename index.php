<?php
session_start();
include 'dbconnect.php';

// Database connection
try {
    $pdo = new PDO("mysql:host=localhost;dbname=pikkit", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userName = $isLoggedIn ? $_SESSION['user_name'] : '';
$userEmail = $isLoggedIn ? $_SESSION['user_email'] : '';
$userId = $isLoggedIn ? $_SESSION['user_id'] : null;

// Handle logout
if(isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}

// Handle create pocket
if(isset($_POST['create_pocket']) && $isLoggedIn) {
    $pocketName = trim($_POST['pocket_name']);
    if(!empty($pocketName)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO pockets (user_id, name, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([$userId, $pocketName]);
            $successMsg = "Pocket '{$pocketName}' created successfully!";
        } catch(PDOException $e) {
            $errorMsg = "Failed to create pocket. It may already exist.";
        }
    }
}

// Get user's pockets
$userPockets = [];
if($isLoggedIn) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM pockets WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$userId]);
        $userPockets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        // Table might not exist, will be handled gracefully
    }
}

// Get search query
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';

// Fetch products from database
try {
    if($searchQuery) {
        $stmt = $pdo->prepare("
            SELECT p.*, u.name as seller_name 
            FROM products p 
            JOIN users u ON p.seller_id = u.id 
            WHERE p.name LIKE ? OR p.description LIKE ?
            ORDER BY p.created_at DESC
        ");
        $searchTerm = "%$searchQuery%";
        $stmt->execute([$searchTerm, $searchTerm]);
    } else {
        $stmt = $pdo->prepare("
            SELECT p.*, u.name as seller_name 
            FROM products p 
            JOIN users u ON p.seller_id = u.id 
            ORDER BY p.created_at DESC
        ");
        $stmt->execute();
    }
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $products = [];
    error_log("Error fetching products: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pikkit - Shop Everything You Need</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #fafafa;
            min-height: 100vh;
            color: #1a1a1a;
        }
        
        /* Header Styles */
        .header {
            background: linear-gradient(135deg, #FFB6C1 0%, #FFA0B4 100%);
            padding: 16px 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            position: sticky;
            top: 0;
            z-index: 100;
            backdrop-filter: blur(10px);
        }
        
        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 24px;
            display: flex;
            align-items: center;
            gap: 24px;
        }
        
        .logo {
            font-size: 28px;
            font-weight: 700;
            color: #fff;
            text-decoration: none;
            letter-spacing: -0.5px;
            transition: transform 0.2s;
        }
        
        .logo:hover {
            transform: scale(1.05);
        }
        
        .menu-toggle {
            display: none;
            background: white;
            color: #333;
            border: none;
            padding: 10px 14px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: all 0.2s;
        }
        
        .menu-toggle:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .search-container {
            flex: 1;
            max-width: 600px;
            position: relative;
        }
        
        .search-form {
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .search-input {
            width: 100%;
            padding: 12px 120px 12px 48px;
            border: none;
            border-radius: 24px;
            font-size: 15px;
            outline: none;
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        
        .search-input:focus {
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
            transform: translateY(-1px);
        }
        
        .search-icon {
            position: absolute;
            left: 16px;
            color: #999;
            font-size: 18px;
            pointer-events: none;
        }
        
        .search-btn {
            position: absolute;
            right: 6px;
            background: #333;
            color: white;
            border: none;
            padding: 8px 24px;
            border-radius: 18px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.2s;
        }
        
        .search-btn:hover {
            background: #000;
            transform: scale(1.02);
        }
        
        .clear-search {
            position: absolute;
            right: 110px;
            background: transparent;
            border: none;
            color: #999;
            cursor: pointer;
            font-size: 18px;
            padding: 4px 8px;
            display: none;
        }
        
        .clear-search.show {
            display: block;
        }
        
        .header-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        
        .btn {
            padding: 10px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-login {
            background: white;
            color: #333;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .btn-login:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .btn-signup {
            background: #333;
            color: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .btn-signup:hover {
            background: #000;
            transform: translateY(-1px);
        }
        
        .user-menu {
            position: relative;
        }
        
        .user-btn {
            background: white;
            color: #333;
            padding: 10px 18px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: all 0.2s;
        }
        
        .user-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #FFB6C1, #FFA0B4);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }
        
        .user-dropdown {
            display: none;
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
            min-width: 220px;
            overflow: hidden;
            animation: slideDown 0.2s ease;
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
        
        .user-menu:hover .user-dropdown {
            display: block;
        }
        
        .user-dropdown a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 20px;
            color: #333;
            text-decoration: none;
            border-bottom: 1px solid #f0f0f0;
            transition: all 0.2s;
        }
        
        .user-dropdown a:hover {
            background: #fafafa;
            padding-left: 24px;
        }
        
        .user-dropdown a:last-child {
            border-bottom: none;
            color: #dc3545;
        }
        
        /* Main Container */
        .main-container {
            display: flex;
            max-width: 1400px;
            margin: 0 auto;
            min-height: calc(100vh - 80px);
            position: relative;
        }
        
        /* Sidebar */
        .sidebar {
            width: 280px;
            background: white;
            padding: 24px;
            height: calc(100vh - 80px);
            position: sticky;
            top: 80px;
            overflow-y: auto;
            box-shadow: 2px 0 12px rgba(0,0,0,0.04);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border-right: 1px solid #f0f0f0;
        }
        
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }
        
        .sidebar::-webkit-scrollbar-track {
            background: transparent;
        }
        
        .sidebar::-webkit-scrollbar-thumb {
            background: #ddd;
            border-radius: 3px;
        }
        
        .sidebar::-webkit-scrollbar-thumb:hover {
            background: #bbb;
        }
        
        .sidebar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid #f5f5f5;
        }
        
        .sidebar-title {
            font-size: 20px;
            font-weight: 700;
            color: #1a1a1a;
            letter-spacing: -0.3px;
        }
        
        .close-sidebar {
            display: none;
            background: #f5f5f5;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            transition: all 0.2s;
        }
        
        .close-sidebar:hover {
            background: #e8e8e8;
            transform: rotate(90deg);
        }
        
        .sell-btn {
            background: linear-gradient(135deg, #FFB6C1, #FFA0B4);
            color: white;
            padding: 14px 20px;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-bottom: 24px;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(255, 182, 193, 0.3);
        }
        
        .sell-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 182, 193, 0.4);
        }
        
        .pockets-section {
            margin-top: 20px;
        }
        
        .section-title {
            font-size: 15px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            letter-spacing: -0.2px;
        }
        
        .create-pocket-form {
            margin-bottom: 20px;
            padding: 16px;
            background: #fafafa;
            border-radius: 12px;
            border: 1px solid #f0f0f0;
        }
        
        .pocket-input {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 12px;
            transition: all 0.2s;
        }
        
        .pocket-input:focus {
            outline: none;
            border-color: #FFB6C1;
            background: white;
            box-shadow: 0 0 0 3px rgba(255, 182, 193, 0.1);
        }
        
        .create-btn {
            width: 100%;
            padding: 11px;
            background: #333;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .create-btn:hover {
            background: #000;
            transform: translateY(-1px);
        }
        
        .pockets-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .pocket-item {
            padding: 12px 14px;
            background: #fafafa;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            color: #333;
            border: 1px solid transparent;
        }
        
        .pocket-item:hover {
            background: linear-gradient(135deg, #FFB6C1, #FFA0B4);
            color: white;
            transform: translateX(4px);
            border-color: rgba(255, 182, 193, 0.3);
            box-shadow: 0 2px 8px rgba(255, 182, 193, 0.3);
        }
        
        .pocket-icon {
            font-size: 18px;
        }
        
        .pocket-name {
            flex: 1;
            font-size: 14px;
            font-weight: 500;
        }
        
        .empty-pockets {
            text-align: center;
            padding: 32px 16px;
            color: #999;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 16px;
            font-size: 13px;
            font-weight: 500;
            border: 1px solid;
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
        
        .login-prompt {
            padding: 16px;
            background: linear-gradient(135deg, #fff3cd, #ffe8a1);
            border-radius: 12px;
            text-align: center;
            font-size: 14px;
            color: #856404;
            margin-bottom: 20px;
            border: 1px solid #ffeaa7;
        }
        
        .login-prompt a {
            color: #333;
            font-weight: 700;
            text-decoration: none;
            border-bottom: 2px solid #333;
        }
        
        /* Products Section */
        .products-section {
            flex: 1;
            padding: 32px;
            background: #fafafa;
        }
        
        .products-header {
            margin-bottom: 28px;
        }
        
        .products-header h2 {
            color: #1a1a1a;
            font-size: 28px;
            margin-bottom: 8px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        
        .results-info {
            color: #666;
            font-size: 14px;
            font-weight: 500;
        }
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 24px;
        }
        
        .product-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
            border: 1px solid #f0f0f0;
        }
        
        .product-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 32px rgba(0,0,0,0.12);
            border-color: #FFB6C1;
        }
        
        .product-image {
            width: 100%;
            height: 220px;
            object-fit: cover;
            background: linear-gradient(135deg, #f5f5f5, #e8e8e8);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }
        
        .product-card:hover .product-image img {
            transform: scale(1.05);
        }
        
        .product-info {
            padding: 18px;
        }
        
        .product-name {
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 10px;
            font-size: 15px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.4;
            min-height: 42px;
        }
        
        .product-price {
            color: #FF6347;
            font-size: 20px;
            font-weight: 700;
            letter-spacing: -0.3px;
        }
        
        .no-products {
            text-align: center;
            padding: 80px 20px;
            color: #666;
        }
        
        .no-products-icon {
            font-size: 64px;
            margin-bottom: 16px;
            opacity: 0.5;
        }
        
        .no-products h3 {
            font-size: 24px;
            margin-bottom: 12px;
            color: #333;
            font-weight: 700;
        }
        
        .no-products p {
            font-size: 15px;
            color: #999;
        }
        
        /* Overlay for mobile */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 98;
            backdrop-filter: blur(4px);
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar {
                position: fixed;
                left: 0;
                top: 0;
                height: 100vh;
                z-index: 99;
                transform: translateX(-100%);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .sidebar-overlay.active {
                display: block;
            }
            
            .menu-toggle {
                display: block;
            }
            
            .close-sidebar {
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
                gap: 20px;
            }
        }
        
        @media (max-width: 768px) {
            .header-content {
                flex-wrap: wrap;
                gap: 16px;
            }
            
            .search-container {
                order: 3;
                flex-basis: 100%;
                margin-top: 8px;
            }
            
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
                gap: 16px;
            }
            
            .products-section {
                padding: 20px 16px;
            }
            
            .product-image {
                height: 180px;
            }
            
            .product-info {
                padding: 14px;
            }
            
            .product-name {
                font-size: 14px;
                min-height: 40px;
            }
            
            .product-price {
                font-size: 18px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <button class="menu-toggle" onclick="toggleSidebar()">☰</button>
            <a href="index.php" class="logo">PikKiT</a>
            
            <div class="search-container">
                <form method="GET" action="index.php" class="search-form" id="searchForm">
                    <span class="search-icon"></span>
                    <input 
                        type="text" 
                        name="search" 
                        class="search-input" 
                        id="searchInput"
                        placeholder="Search for anything..."
                        value="<?php echo htmlspecialchars($searchQuery); ?>"
                        autocomplete="off"
                    >
                    <button type="button" class="clear-search" id="clearSearch" onclick="clearSearch()">✕</button>
                    <button type="submit" class="search-btn">Search</button>
                </form>
            </div>
            
            <div class="header-actions">
                <?php if($isLoggedIn): ?>
                    <div class="user-menu">
                        <div class="user-btn">
                            <div class="user-avatar">👤</div>
                            <span><?php echo htmlspecialchars($userName); ?></span>
                        </div>
                        <div class="user-dropdown">
                            <a href="profile.php">👤 My Profile</a>
                            <a href="my_orders.php">📦 My Orders</a>
                            <a href="my_products.php">🏷️ My Products</a>
                            <a href="pocket.php">🛒 My Pocket</a>
                            <a href="index.php?logout=1">🚪 Logout</a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="login.php" class="btn btn-login">Login</a>
                    <a href="registration.php" class="btn btn-signup">Sign Up</a>
                <?php endif; ?>
            </div>
        </div>
    </header>
    
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
    
    <!-- Main Container -->
    <div class="main-container">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <span class="sidebar-title">Menu</span>
                <button class="close-sidebar" onclick="toggleSidebar()">✕</button>
            </div>
            
            <?php if($isLoggedIn): ?>
                <button class="sell-btn" onclick="location.href='sell_product.php'">
                    <span></span>
                    Sell Product
                </button>
                
                <div class="pockets-section">
                    <div class="section-title">
                        <span></span>
                        <span>My Pockets</span>
                    </div>
                    
                    <?php if(isset($successMsg)): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($successMsg); ?></div>
                    <?php endif; ?>
                    
                    <?php if(isset($errorMsg)): ?>
                        <div class="alert alert-error"><?php echo htmlspecialchars($errorMsg); ?></div>
                    <?php endif; ?>
                    
                    <div class="create-pocket-form">
                        <form method="POST" action="">
                            <input 
                                type="text" 
                                name="pocket_name" 
                                class="pocket-input" 
                                placeholder="Create new pocket..."
                                required
                                maxlength="50"
                            >
                            <button type="submit" name="create_pocket" class="create-btn">
                                Create Pocket
                            </button>
                        </form>
                    </div>
                    
                    <div class="pockets-list">
                        <?php if(count($userPockets) > 0): ?>
                            <?php foreach($userPockets as $pocket): ?>
                                <a href="pocket_view.php?pocket_id=<?php echo $pocket['id']; ?>" class="pocket-item">
                                    <span class="pocket-icon"></span>
                                    <span class="pocket-name"><?php echo htmlspecialchars($pocket['name']); ?></span>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-pockets">
                                No pockets yet.<br>
                                Create one to organize products!
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="login-prompt">
                    <p><a href="login.php">Login</a> to sell and create pockets</p>
                </div>
                <button class="sell-btn" onclick="alert('Please login to sell products'); location.href='login.php'">
                    <span>➕</span>
                    Sell Product
                </button>
            <?php endif; ?>
        </aside>
        
        <!-- Products Section -->
        <main class="products-section">
            <div class="products-header">
                <h2><?php echo $searchQuery ? 'Search Results' : 'All Products'; ?></h2>
                <p class="results-info">
                    <?php 
                    $count = count($products);
                    if($searchQuery) {
                        echo "$count " . ($count == 1 ? 'result' : 'results') . " for \"" . htmlspecialchars($searchQuery) . "\"";
                    } else {
                        echo "$count " . ($count == 1 ? 'product' : 'products') . " available";
                    }
                    ?>
                </p>
            </div>
            
            <?php if(count($products) > 0): ?>
                <div class="products-grid">
                    <?php foreach($products as $product): ?>
                        <a href="product_detail.php?id=<?php echo $product['id']; ?>" class="product-card">
                            <div class="product-image">
                                <?php if(!empty($product['image'])): ?>
                                    <img src="uploads/products/<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                <?php else: ?>
                                    <span style="color: #999; font-size: 48px;"></span>
                                <?php endif; ?>
                            </div>
                            <div class="product-info">
                                <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                                <div class="product-price">Rs. <?php echo number_format($product['price']); ?></div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-products">
                    <div class="no-products-icon"></div>
                    <h3>No products found</h3>
                    <p><?php echo $searchQuery ? 'Try searching with different keywords' : 'Be the first to add products!'; ?></p>
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        }
        
        // Search functionality
        const searchInput = document.getElementById('searchInput');
        const clearBtn = document.getElementById('clearSearch');
        
        function updateClearButton() {
            if(searchInput.value.length > 0) {
                clearBtn.classList.add('show');
            } else {
                clearBtn.classList.remove('show');
            }
        }
        
        function clearSearch() {
            searchInput.value = '';
            updateClearButton();
            searchInput.focus();
            if(window.location.search.includes('search=')) {
                window.location.href = 'index.php';
            }
        }
        
        searchInput.addEventListener('input', updateClearButton);
        updateClearButton();
        
        // Auto-hide sidebar on product click (mobile)
        if(window.innerWidth <= 1024) {
            document.querySelectorAll('.product-card').forEach(card => {
                card.addEventListener('click', () => {
                    const sidebar = document.getElementById('sidebar');
                    const overlay = document.getElementById('sidebarOverlay');
                    if(sidebar.classList.contains('active')) {
                        sidebar.classList.remove('active');
                        overlay.classList.remove('active');
                    }
                });
            });
        }
    </script>
</body>
</html>