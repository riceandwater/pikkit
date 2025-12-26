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
$userId = $isLoggedIn ? $_SESSION['user_id'] : null;

// Get product ID
$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if($productId <= 0) {
    header("Location: index.php");
    exit();
}

// Handle Add to Pocket
if(isset($_POST['add_to_pocket']) && $isLoggedIn) {
    try {
        // Check if already in pocket
        $stmt = $pdo->prepare("SELECT * FROM pocket WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$userId, $productId]);
        
        if($stmt->fetch()) {
            // Update quantity
            $stmt = $pdo->prepare("UPDATE pocket SET quantity = quantity + 1 WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$userId, $productId]);
            $message = "Product quantity updated in pocket!";
        } else {
            // Add new
            $stmt = $pdo->prepare("INSERT INTO pocket (user_id, product_id, quantity) VALUES (?, ?, 1)");
            $stmt->execute([$userId, $productId]);
            $message = "Product added to pocket!";
        }
    } catch(PDOException $e) {
        $error = "Failed to add to pocket";
    }
}

// Handle Buy Now
if(isset($_POST['buy_now']) && $isLoggedIn) {
    try {
        // Fetch product details
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($product) {
            // Create buyer record
            $stmt = $pdo->prepare("INSERT INTO buyers (user_id, product_id, quantity, total_price, status) VALUES (?, ?, 1, ?, 'completed')");
            $stmt->execute([$userId, $productId, $product['price']]);
            
            // Update seller's total sales
            $stmt = $pdo->prepare("
                INSERT INTO sellers (user_id, total_products, total_sales) 
                VALUES (?, 1, ?) 
                ON DUPLICATE KEY UPDATE total_sales = total_sales + ?
            ");
            $stmt->execute([$product['seller_id'], $product['price'], $product['price']]);
            
            // Update product stock
            $stmt = $pdo->prepare("UPDATE products SET stock = stock - 1 WHERE id = ? AND stock > 0");
            $stmt->execute([$productId]);
            
            $success = "Purchase successful! Thank you for your order.";
        }
    } catch(PDOException $e) {
        $error = "Purchase failed. Please try again.";
    }
}

// Fetch product details
try {
    $stmt = $pdo->prepare("
        SELECT p.*, u.name as seller_name, u.email as seller_email 
        FROM products p 
        JOIN users u ON p.seller_id = u.id 
        WHERE p.id = ?
    ");
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if(!$product) {
        header("Location: index.php");
        exit();
    }
} catch(PDOException $e) {
    die("Error loading product: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']); ?> - Pikkit</title>
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
        
        /* Header */
        .header {
            background: #FFB6C1;
            padding: 20px 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
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
        
        .search-input {
            width: 100%;
            padding: 12px 20px;
            border: none;
            border-radius: 25px;
            font-size: 15px;
            outline: none;
        }
        
        .back-btn {
            background: white;
            color: #333;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
        }
        
        .back-btn:hover {
            background: #f0f0f0;
        }
        
        /* Product Detail Container */
        .product-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .product-detail {
            background: white;
            border-radius: 10px;
            padding: 40px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            gap: 50px;
        }
        
        .product-image-section {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .product-image {
            width: 100%;
            max-width: 400px;
            height: 400px;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
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
        
        .product-info-section {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .product-title {
            font-size: 28px;
            font-weight: 600;
            color: #333;
        }
        
        .product-price {
            font-size: 36px;
            font-weight: bold;
            color: #FF6347;
        }
        
        .product-description-section {
            margin-top: 10px;
        }
        
        .description-label {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }
        
        .product-description {
            font-size: 16px;
            color: #666;
            line-height: 1.6;
        }
        
        .product-meta {
            display: flex;
            flex-direction: column;
            gap: 10px;
            padding: 15px 0;
            border-top: 1px solid #e0e0e0;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .meta-item {
            display: flex;
            gap: 10px;
            font-size: 14px;
        }
        
        .meta-label {
            font-weight: 600;
            color: #666;
        }
        
        .meta-value {
            color: #333;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        
        .btn {
            flex: 1;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-buy {
            background: #FFB6C1;
            color: #333;
        }
        
        .btn-buy:hover {
            background: #FF9BAD;
        }
        
        .btn-pocket {
            background: #FFB6C1;
            color: #333;
        }
        
        .btn-pocket:hover {
            background: #FF9BAD;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .login-prompt {
            background: #fff3cd;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            margin-top: 20px;
        }
        
        .login-prompt a {
            color: #333;
            font-weight: 600;
        }
        
        @media (max-width: 768px) {
            .product-detail {
                grid-template-columns: 1fr;
                gap: 30px;
                padding: 20px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .header-content {
                flex-wrap: wrap;
            }
            
            .search-container {
                order: 3;
                flex-basis: 100%;
                margin-top: 15px;
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
                <input type="text" class="search-input" placeholder="Search for anything" readonly onclick="window.location.href='index.php'">
            </div>
            
            <a href="index.php" class="back-btn">← Back to Shop</a>
        </div>
    </header>
    
    <!-- Product Detail -->
    <div class="product-container">
        <?php if(isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if(isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if(isset($message)): ?>
            <div class="alert alert-info"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <div class="product-detail">
            <!-- Product Image -->
            <div class="product-image-section">
                <div class="product-image">
                    <?php if(!empty($product['image'])): ?>
                        <img src="uploads/products/<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                    <?php else: ?>
                        <span style="color: #999; font-size: 18px;">No Image Available</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Product Info -->
            <div class="product-info-section">
                <h1 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h1>
                
                <div class="product-price">Rs. <?php echo number_format($product['price']); ?></div>
                
                <div class="product-description-section">
                    <div class="description-label">DESCRIPTION:</div>
                    <p class="product-description"><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                </div>
                
                <div class="product-meta">
                    <div class="meta-item">
                        <span class="meta-label">Seller:</span>
                        <span class="meta-value"><?php echo htmlspecialchars($product['seller_name']); ?></span>
                    </div>
                    <?php if(!empty($product['category'])): ?>
                        <div class="meta-item">
                            <span class="meta-label">Category:</span>
                            <span class="meta-value"><?php echo htmlspecialchars($product['category']); ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="meta-item">
                        <span class="meta-label">Stock:</span>
                        <span class="meta-value"><?php echo $product['stock'] > 0 ? $product['stock'] . ' available' : 'Out of stock'; ?></span>
                    </div>
                </div>
                
                <?php if($isLoggedIn): ?>
                    <?php if($product['stock'] > 0): ?>
                        <form method="POST" class="action-buttons">
                            <button type="submit" name="buy_now" class="btn btn-buy">BUY NOW</button>
                            <button type="submit" name="add_to_pocket" class="btn btn-pocket">ADD TO POCKET</button>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-error">This product is currently out of stock</div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="login-prompt">
                        Please <a href="login.php">login</a> or <a href="registration.php">register</a> to purchase this product
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>