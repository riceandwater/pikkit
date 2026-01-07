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

// Get user's pockets if logged in
$userPockets = [];
if($isLoggedIn) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM pockets WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$userId]);
        $userPockets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        $userPockets = [];
    }
}

// Handle Add to Pocket
if(isset($_POST['add_to_pocket']) && $isLoggedIn) {
    $selectedPocketId = isset($_POST['pocket_id']) ? (int)$_POST['pocket_id'] : 0;
    
    if($selectedPocketId > 0) {
        try {
            // Verify pocket belongs to user
            $stmt = $pdo->prepare("SELECT * FROM pockets WHERE id = ? AND user_id = ?");
            $stmt->execute([$selectedPocketId, $userId]);
            $pocket = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if($pocket) {
                // Check if already in pocket
                $stmt = $pdo->prepare("SELECT * FROM pocket WHERE user_id = ? AND product_id = ? AND pocket_id = ?");
                $stmt->execute([$userId, $productId, $selectedPocketId]);
                
                if($stmt->fetch()) {
                    // Update quantity
                    $stmt = $pdo->prepare("UPDATE pocket SET quantity = quantity + 1 WHERE user_id = ? AND product_id = ? AND pocket_id = ?");
                    $stmt->execute([$userId, $productId, $selectedPocketId]);
                    $message = "Product quantity updated in pocket!";
                } else {
                    // Add new
                    $stmt = $pdo->prepare("INSERT INTO pocket (user_id, product_id, pocket_id, quantity, added_at) VALUES (?, ?, ?, 1, NOW())");
                    $stmt->execute([$userId, $productId, $selectedPocketId]);
                    $message = "Product added to pocket successfully!";
                }
            } else {
                $error = "Invalid pocket selected";
            }
        } catch(PDOException $e) {
            $error = "Failed to add to pocket: " . $e->getMessage();
        }
    } else {
        $error = "Please select a pocket";
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
            $stmt = $pdo->prepare("INSERT INTO buyers (user_id, product_id, quantity, total_price, status, purchase_date) VALUES (?, ?, 1, ?, 'completed', NOW())");
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
            padding: 0;
            box-shadow: var(--shadow-sm);
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid var(--border-color);
        }
        
        .header-content {
            max-width: 1600px;
            margin: 0 auto;
            padding: 16px 32px;
            display: flex;
            align-items: center;
            gap: 32px;
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
        }
        
        .search-container {
            flex: 1;
            max-width: 600px;
        }
        
        .search-input {
            width: 100%;
            padding: 14px 20px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            font-size: 15px;
            outline: none;
            background: var(--white);
            transition: var(--transition);
            cursor: pointer;
        }
        
        .search-input:hover {
            border-color: var(--primary-pink);
        }
        
        .back-btn {
            background: transparent;
            color: var(--secondary-gray);
            padding: 11px 24px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .back-btn:hover {
            border-color: var(--primary-pink);
            color: var(--accent-pink);
            background: rgba(255, 182, 217, 0.05);
        }
        
        /* Product Detail Container */
        .product-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 32px;
        }
        
        .product-detail {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: var(--shadow-md);
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            gap: 50px;
            border: 2px solid var(--border-color);
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
            border: 2px solid var(--border-color);
            border-radius: 16px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--light-gray);
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
            font-family: 'Outfit', sans-serif;
            font-size: 32px;
            font-weight: 700;
            color: var(--secondary-gray);
            letter-spacing: -0.5px;
        }
        
        .product-price {
            font-size: 38px;
            font-weight: 800;
            color: var(--accent-pink);
            letter-spacing: -1px;
        }
        
        .product-description-section {
            margin-top: 10px;
        }
        
        .description-label {
            font-size: 16px;
            font-weight: 700;
            color: var(--secondary-gray);
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .product-description {
            font-size: 15px;
            color: #666;
            line-height: 1.7;
        }
        
        .product-meta {
            display: flex;
            flex-direction: column;
            gap: 12px;
            padding: 20px 0;
            border-top: 2px solid var(--border-color);
            border-bottom: 2px solid var(--border-color);
        }
        
        .meta-item {
            display: flex;
            gap: 12px;
            font-size: 14px;
        }
        
        .meta-label {
            font-weight: 700;
            color: #666;
            min-width: 80px;
        }
        
        .meta-value {
            color: var(--secondary-gray);
            font-weight: 500;
        }
        
        .contact-seller-section {
            margin: 20px 0;
        }
        
        .contact-seller-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 14px 24px;
            background: #FF8FB8;
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 15px;
            transition: var(--transition);
            box-shadow: 0 4px 12px rgba(66, 133, 244, 0.3);
        }
        
        .contact-seller-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(66, 133, 244, 0.4);
        }
        
        .contact-seller-btn svg {
            width: 20px;
            height: 20px;
        }
        
        .action-section {
            margin-top: 20px;
        }
        
        .pocket-selector {
            margin-bottom: 20px;
        }
        
        .pocket-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: var(--secondary-gray);
            margin-bottom: 10px;
        }
        
        .pocket-select {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 15px;
            font-weight: 500;
            color: var(--secondary-gray);
            background: white;
            cursor: pointer;
            transition: var(--transition);
            font-family: inherit;
        }
        
        .pocket-select:focus {
            outline: none;
            border-color: var(--primary-pink);
            box-shadow: 0 0 0 4px rgba(255, 182, 217, 0.15);
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
        }
        
        .btn {
            flex: 1;
            padding: 16px 32px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            font-family: inherit;
        }
        
        .btn-buy {
            background: linear-gradient(135deg, var(--primary-pink) 0%, var(--accent-pink) 100%);
            color: white;
            box-shadow: 0 4px 16px rgba(255, 107, 157, 0.3);
        }
        
        .btn-buy:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 107, 157, 0.4);
        }
        
        .btn-pocket {
            background: var(--secondary-gray);
            color: white;
        }
        
        .btn-pocket:hover {
            background: #000;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .btn-pocket:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }
        
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 14px;
            font-weight: 600;
            border: 2px solid;
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
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border-color: #bee5eb;
        }
        
        .login-prompt {
            background: #fff3cd;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            margin-top: 20px;
            border: 2px solid #ffc107;
        }
        
        .login-prompt a {
            color: var(--accent-pink);
            font-weight: 700;
            text-decoration: none;
            border-bottom: 2px solid var(--accent-pink);
        }
        
        .login-prompt a:hover {
            opacity: 0.8;
        }
        
        .no-pockets-notice {
            background: var(--light-gray);
            padding: 16px;
            border-radius: 10px;
            font-size: 14px;
            color: #666;
            margin-bottom: 16px;
            border: 2px dashed var(--border-color);
        }
        
        .no-pockets-notice a {
            color: var(--accent-pink);
            font-weight: 600;
            text-decoration: none;
        }
        
        @media (max-width: 768px) {
            .product-detail {
                grid-template-columns: 1fr;
                gap: 30px;
                padding: 24px;
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
            
            .product-container {
                padding: 0 16px;
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
            
            <a href="index.php" class="back-btn">Back to Shop</a>
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
                    <div class="description-label">Description</div>
                    <p class="product-description"><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                </div>
                
                <div class="product-meta">
                    <div class="meta-item">
                        <span class="meta-label">Seller:</span>
                        <span class="meta-value"><?php echo htmlspecialchars($product['seller_name']); ?></span>
                    </div>
                    <?php if(!empty($product['seller_email'])): ?>
                        <div class="meta-item">
                            <span class="meta-label">Email:</span>
                            <span class="meta-value"><?php echo htmlspecialchars($product['seller_email']); ?></span>
                        </div>
                    <?php endif; ?>
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
                
                <?php if(!empty($product['seller_email'])): ?>
                    <div class="contact-seller-section">
                        <a href="https://mail.google.com/mail/?view=cm&fs=1&to=<?php echo urlencode($product['seller_email']); ?>&su=<?php echo urlencode('Inquiry about: ' . $product['name']); ?>&body=<?php echo urlencode('Hi ' . $product['seller_name'] . ',

I am interested in your product "' . $product['name'] . '" listed on Pikkit for Rs. ' . number_format($product['price']) . '.

Could you please provide more information?

Thank you!'); ?>" 
                           target="_blank" 
                           class="contact-seller-btn">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                <polyline points="22,6 12,13 2,6"></polyline>
                            </svg>
                            Contact Seller via Gmail
                        </a>
                    </div>
                <?php endif; ?>
                
                <?php if($isLoggedIn): ?>
                    <?php if($product['stock'] > 0): ?>
                        <div class="action-section">
                            <!-- Pocket Selection -->
                            <form method="POST" id="addToPocketForm">
                                <?php if(count($userPockets) > 0): ?>
                                    <div class="pocket-selector">
                                        <label class="pocket-label">Select Pocket:</label>
                                        <select name="pocket_id" class="pocket-select" required>
                                            <option value="">Choose a pocket...</option>
                                            <?php foreach($userPockets as $pocket): ?>
                                                <option value="<?php echo $pocket['id']; ?>"><?php echo htmlspecialchars($pocket['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php else: ?>
                                    <div class="no-pockets-notice">
                                        You don't have any pockets yet. <a href="index.php">Create one from the sidebar</a> to save items!
                                    </div>
                                <?php endif; ?>
                                
                              <div class="action-buttons">
                           <button type="button" class="btn btn-buy" onclick="window.location.href='buy.php?id=<?php echo $productId; ?>'">
                                 BUY NOW
                             </button>
                             <button type="submit" name="add_to_pocket" class="btn btn-pocket" <?php echo count($userPockets) == 0 ? 'disabled' : ''; ?>>
                              ADD TO POCKET
                               </button>
                               </div>
                            </form>
                        </div>
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
    
    <script>
        function buyNow() {
            if(confirm('Confirm purchase of this product?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="buy_now" value="1">';
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
