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

// Handle logout
if(isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
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
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
        }
        
        /* Header Styles */
        .header {
            background: #FFB6C1;
            padding: 20px 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            align-items: center;
            gap: 30px;
        }
        
        .logo {
            font-size: 32px;
            font-weight: bold;
            color: #fff;
            text-decoration: none;
            letter-spacing: 2px;
        }
        
        .search-container {
            flex: 1;
            max-width: 600px;
        }
        
        .search-form {
            position: relative;
        }
        
        .search-input {
            width: 100%;
            padding: 12px 45px 12px 20px;
            border: none;
            border-radius: 25px;
            font-size: 15px;
            outline: none;
        }
        
        .search-btn {
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            background: #333;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .search-btn:hover {
            background: #000;
        }
        
        .header-actions {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .btn {
            padding: 10px 25px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-block;
        }
        
        .btn-login {
            background: white;
            color: #333;
        }
        
        .btn-login:hover {
            background: #f0f0f0;
        }
        
        .btn-signup {
            background: #333;
            color: white;
        }
        
        .btn-signup:hover {
            background: #000;
        }
        
        .user-menu {
            position: relative;
        }
        
        .user-btn {
            background: white;
            color: #333;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .user-dropdown {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-top: 10px;
            min-width: 200px;
        }
        
        .user-menu:hover .user-dropdown {
            display: block;
        }
        
        .user-dropdown a {
            display: block;
            padding: 12px 20px;
            color: #333;
            text-decoration: none;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .user-dropdown a:hover {
            background: #f5f5f5;
        }
        
        .user-dropdown a:last-child {
            border-bottom: none;
        }
        
        /* Main Container */
        .main-container {
            display: flex;
            max-width: 1400px;
            margin: 0 auto;
            min-height: calc(100vh - 80px);
        }
        
        /* Sidebar */
        .sidebar {
            width: 260px;
            background: #FFB6C1;
            padding: 30px 0;
            height: calc(100vh - 80px);
            position: sticky;
            top: 80px;
        }
        
        .sell-btn {
            margin: 0 20px 30px;
            background: #000;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: calc(100% - 40px);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .sell-btn:hover {
            background: #333;
        }
        
        /* Products Grid */
        .products-section {
            flex: 1;
            padding: 30px;
        }
        
        .products-header {
            margin-bottom: 25px;
        }
        
        .products-header h2 {
            color: #333;
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .results-info {
            color: #666;
            font-size: 14px;
        }
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 25px;
        }
        
        .product-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.3s;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .product-info {
            padding: 15px;
        }
        
        .product-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 16px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .product-price {
            color: #FF6347;
            font-size: 18px;
            font-weight: bold;
        }
        
        .no-products {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .no-products h3 {
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar {
                display: none;
            }
            
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }
        }
        
        @media (max-width: 768px) {
            .header-content {
                flex-wrap: wrap;
            }
            
            .search-container {
                order: 3;
                flex-basis: 100%;
                margin-top: 15px;
            }
            
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <a href="index.php" class="logo">PikKiT</a>
            
            <div class="search-container">
                <form method="GET" action="index.php" class="search-form">
                    <input 
                        type="text" 
                        name="search" 
                        class="search-input" 
                        placeholder="Search for anything"
                        value="<?php echo htmlspecialchars($searchQuery); ?>"
                    >
                    <button type="submit" class="search-btn">Search</button>
                </form>
            </div>
            
            <div class="header-actions">
                <?php if($isLoggedIn): ?>
                    <div class="user-menu">
                        <div class="user-btn">
                            <span>👤</span>
                            <span><?php echo htmlspecialchars($userName); ?></span>
                        </div>
                        <div class="user-dropdown">
                            <a href="profile.php">My Profile</a>
                            <a href="my_orders.php">My Orders</a>
                            <a href="my_products.php">My Products</a>
                            <a href="pocket.php">My Pocket</a>
                            <a href="index.php?logout=1">Logout</a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="login.php" class="btn btn-login">Login</a>
                    <a href="registration.php" class="btn btn-signup">Sign Up</a>
                <?php endif; ?>
            </div>
        </div>
    </header>
    
    <!-- Main Container -->
    <div class="main-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <?php if($isLoggedIn): ?>
                <button class="sell-btn" onclick="location.href='sell_product.php'">
                    <span>➕</span>
                    Sell
                </button>
            <?php else: ?>
                <button class="sell-btn" onclick="alert('Please login to sell products'); location.href='login.php'">
                    <span>➕</span>
                    Sell
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
                        echo "$count " . ($count == 1 ? 'product' : 'products') . " found for \"" . htmlspecialchars($searchQuery) . "\"";
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
                                    <span style="color: #999;">No Image</span>
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
                    <h3>No products found</h3>
                    <p><?php echo $searchQuery ? 'Try searching with different keywords' : 'Be the first to add products!'; ?></p>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>