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
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'];
$userEmail = $_SESSION['user_email'];

// Handle product deletion
if(isset($_POST['delete_product']) && isset($_POST['product_id'])) {
    $productId = $_POST['product_id'];
    
    try {
        // First, get the product details to check ownership and get image name
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND seller_id = ?");
        $stmt->execute([$productId, $userId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($product) {
            // Delete the product from database
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ? AND seller_id = ?");
            $stmt->execute([$productId, $userId]);
            
            // Delete the image file if it exists
            if(!empty($product['image'])) {
                $imagePath = "uploads/products/" . $product['image'];
                if(file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }
            
            $successMsg = "Product deleted successfully!";
        } else {
            $errorMsg = "Product not found or you don't have permission to delete it.";
        }
    } catch(PDOException $e) {
        $errorMsg = "Failed to delete product: " . $e->getMessage();
    }
}

// Handle inline product update
if(isset($_POST['update_product']) && isset($_POST['product_id'])) {
    $productId = $_POST['product_id'];
    $productName = trim($_POST['product_name']);
    $productPrice = trim($_POST['product_price']);
    $productDescription = trim($_POST['product_description']);
    $productCategory = trim($_POST['product_category']);
    $productStock = trim($_POST['product_stock']);
    
    // Validation
    $errors = [];
    
    if(empty($productName)) {
        $errors[] = "Product name is required";
    }
    
    if(empty($productPrice) || !is_numeric($productPrice) || $productPrice <= 0) {
        $errors[] = "Valid product price is required";
    }
    
    if(empty($productDescription)) {
        $errors[] = "Product description is required";
    }
    
    if(empty($productStock) || !is_numeric($productStock) || $productStock < 0) {
        $productStock = 1; // Default stock
    }
    
    // Get current product data
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND seller_id = ?");
    $stmt->execute([$productId, $userId]);
    $currentProduct = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if(!$currentProduct) {
        $errors[] = "Product not found or you don't have permission to edit it";
    }
    
    $newImage = $currentProduct['image'];
    
    // Handle image upload if new image is provided
    if(isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        if(!in_array($_FILES['product_image']['type'], $allowedTypes)) {
            $errors[] = "Invalid image type. Allowed: JPG, PNG, GIF, WEBP";
        } elseif($_FILES['product_image']['size'] > $maxSize) {
            $errors[] = "Image size must be less than 5MB";
        } else {
            $imageExtension = pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION);
            $newImageName = 'product_' . time() . '_' . rand(1000, 9999) . '.' . $imageExtension;
            $uploadPath = 'uploads/products/' . $newImageName;
            
            // Create directory if it doesn't exist
            if(!file_exists('uploads/products/')) {
                mkdir('uploads/products/', 0777, true);
            }
            
            if(move_uploaded_file($_FILES['product_image']['tmp_name'], $uploadPath)) {
                // Delete old image if it exists
                if(!empty($currentProduct['image']) && file_exists('uploads/products/' . $currentProduct['image'])) {
                    unlink('uploads/products/' . $currentProduct['image']);
                }
                $newImage = $newImageName;
            } else {
                $errors[] = "Failed to upload image";
            }
        }
    }
    
    // Update product if no errors
    if(empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE products 
                SET name = ?, price = ?, description = ?, category = ?, stock = ?, image = ?, updated_at = NOW()
                WHERE id = ? AND seller_id = ?
            ");
            $stmt->execute([
                $productName, 
                $productPrice, 
                $productDescription, 
                $productCategory, 
                $productStock, 
                $newImage, 
                $productId, 
                $userId
            ]);
            
            $successMsg = "Product updated successfully!";
        } catch(PDOException $e) {
            $errorMsg = "Failed to update product: " . $e->getMessage();
        }
    } else {
        $errorMsg = implode(", ", $errors);
    }
}

// Get seller's products
$products = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM products 
        WHERE seller_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$userId]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching products: " . $e->getMessage());
}

// Handle logout
if(isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Products - PikKiT</title>
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
        
        /* Header Styles */
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
            filter: brightness(1.1);
        }
        
        .header-spacer {
            flex: 1;
        }
        
        .header-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        
        .btn {
            padding: 11px 24px;
            border: 2px solid transparent;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-family: inherit;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-pink) 0%, var(--accent-pink) 100%);
            color: white;
            box-shadow: 0 2px 8px rgba(255, 107, 157, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 107, 157, 0.4);
        }
        
        .user-menu {
            position: relative;
        }
        
        .user-btn {
            background: var(--white);
            color: var(--secondary-gray);
            padding: 10px 18px;
            border-radius: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            transition: var(--transition);
            border: 2px solid var(--border-color);
        }
        
        .user-btn:hover {
            border-color: var(--primary-pink);
            background: rgba(255, 182, 217, 0.05);
        }
        
        .user-avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-pink), var(--accent-pink));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            box-shadow: 0 2px 8px rgba(255, 107, 157, 0.3);
            color: white;
            font-weight: 700;
        }
        
        .user-dropdown {
            display: none;
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            background: white;
            border-radius: 16px;
            box-shadow: var(--shadow-lg);
            min-width: 240px;
            overflow: hidden;
            animation: slideDown 0.3s ease;
            border: 1px solid var(--border-color);
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
            color: var(--secondary-gray);
            text-decoration: none;
            border-bottom: 1px solid var(--border-color);
            transition: var(--transition);
            font-weight: 500;
        }
        
        .user-dropdown a:hover {
            background: var(--light-gray);
            padding-left: 26px;
        }
        
        .user-dropdown a:last-child {
            border-bottom: none;
            color: #dc3545;
        }
        
        /* Main Content */
        .main-content {
            max-width: 1600px;
            margin: 0 auto;
            padding: 40px 32px;
        }
        
        .page-header {
            margin-bottom: 32px;
        }
        
        .page-header h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 42px;
            font-weight: 800;
            color: var(--secondary-gray);
            margin-bottom: 10px;
            letter-spacing: -1.5px;
        }
        
        .page-subtitle {
            color: #666;
            font-size: 16px;
            font-weight: 500;
        }
        
        /* Alert Messages */
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
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
            border: 2px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 2px solid #f5c6cb;
        }
        
        /* Stats Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
            border-color: var(--primary-pink);
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: 800;
            color: var(--accent-pink);
            font-family: 'Outfit', sans-serif;
        }
        
        /* Products Grid */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 24px;
        }
        
        .product-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            border: 2px solid transparent;
        }
        
        .product-card:hover {
            box-shadow: var(--shadow-md);
            border-color: var(--primary-pink);
        }
        
        .product-card.editing {
            border-color: var(--accent-pink);
            box-shadow: var(--shadow-lg);
        }
        
        .product-image-container {
            position: relative;
            width: 100%;
            height: 250px;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        
        .product-image-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .product-image-placeholder {
            font-size: 64px;
            color: #ccc;
        }
        
        .image-upload-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            display: none;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 12px;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .editing .image-upload-overlay {
            display: flex;
        }
        
        .image-upload-overlay:hover {
            background: rgba(0, 0, 0, 0.8);
        }
        
        .upload-icon {
            font-size: 48px;
            color: white;
        }
        
        .upload-text {
            color: white;
            font-weight: 600;
            font-size: 14px;
        }
        
        .product-info {
            padding: 20px;
        }
        
        /* View Mode Styles */
        .view-mode {
            display: block;
        }
        
        .edit-mode {
            display: none;
        }
        
        .editing .view-mode {
            display: none;
        }
        
        .editing .edit-mode {
            display: block;
        }
        
        .product-name {
            font-weight: 700;
            color: var(--secondary-gray);
            margin-bottom: 8px;
            font-size: 18px;
            line-height: 1.4;
        }
        
        .product-description {
            color: #666;
            font-size: 14px;
            margin-bottom: 12px;
            line-height: 1.6;
        }
        
        .product-price {
            color: var(--accent-pink);
            font-size: 24px;
            font-weight: 800;
            margin-bottom: 12px;
            letter-spacing: -0.5px;
        }
        
        .product-meta {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            padding: 12px 0;
            border-top: 1px solid var(--border-color);
            margin-bottom: 16px;
        }
        
        .meta-item {
            font-size: 13px;
            color: #666;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .meta-label {
            font-weight: 600;
            color: var(--secondary-gray);
        }
        
        /* Edit Mode Form Styles */
        .edit-form {
            width: 100%;
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
            color: var(--secondary-gray);
            font-size: 13px;
        }
        
        .form-input,
        .form-textarea {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: var(--transition);
        }
        
        .form-input:focus,
        .form-textarea:focus {
            outline: none;
            border-color: var(--primary-pink);
            box-shadow: 0 0 0 3px rgba(255, 182, 217, 0.15);
        }
        
        .form-textarea {
            min-height: 80px;
            resize: vertical;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        
        /* Action Buttons */
        .product-actions {
            display: flex;
            gap: 10px;
        }
        
        .action-btn {
            flex: 1;
            padding: 12px 20px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            font-family: inherit;
            text-decoration: none;
            text-align: center;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        
        .btn-edit {
            background: var(--light-gray);
            color: var(--secondary-gray);
            border: 2px solid var(--border-color);
        }
        
        .btn-edit:hover {
            background: var(--secondary-gray);
            color: white;
            border-color: var(--secondary-gray);
        }
        
        .btn-delete {
            background: #fff5f5;
            color: #dc3545;
            border: 2px solid #ffdddd;
        }
        
        .btn-delete:hover {
            background: #dc3545;
            color: white;
            border-color: #dc3545;
        }
        
        .btn-save {
            background: linear-gradient(135deg, var(--primary-pink) 0%, var(--accent-pink) 100%);
            color: white;
            border: 2px solid transparent;
        }
        
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 107, 157, 0.4);
        }
        
        .btn-cancel {
            background: var(--light-gray);
            color: var(--secondary-gray);
            border: 2px solid var(--border-color);
        }
        
        .btn-cancel:hover {
            background: var(--border-color);
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow-sm);
        }
        
        .empty-icon {
            font-size: 80px;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        .empty-state h3 {
            font-size: 28px;
            font-weight: 700;
            color: var(--secondary-gray);
            margin-bottom: 12px;
        }
        
        .empty-state p {
            color: #666;
            font-size: 16px;
            margin-bottom: 24px;
        }
        
        /* Delete Confirmation Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            z-index: 2000;
            backdrop-filter: blur(4px);
            animation: fadeIn 0.3s ease;
        }
        
        .modal-overlay.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal {
            background: white;
            border-radius: 20px;
            padding: 32px;
            max-width: 480px;
            width: 90%;
            box-shadow: var(--shadow-lg);
            animation: scaleIn 0.3s ease;
        }
        
        @keyframes scaleIn {
            from {
                opacity: 0;
                transform: scale(0.9);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        .modal-header {
            text-align: center;
            margin-bottom: 24px;
        }
        
        .modal-icon {
            font-size: 64px;
            margin-bottom: 16px;
        }
        
        .modal h3 {
            font-size: 24px;
            font-weight: 700;
            color: var(--secondary-gray);
            margin-bottom: 8px;
        }
        
        .modal p {
            color: #666;
            font-size: 15px;
            line-height: 1.6;
        }
        
        .modal-actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }
        
        .modal-btn {
            flex: 1;
            padding: 14px 24px;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            font-family: inherit;
        }
        
        .modal-btn-cancel {
            background: var(--light-gray);
            color: var(--secondary-gray);
            border: 2px solid var(--border-color);
        }
        
        .modal-btn-cancel:hover {
            background: var(--border-color);
        }
        
        .modal-btn-confirm {
            background: #dc3545;
            color: white;
        }
        
        .modal-btn-confirm:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .header-content {
                padding: 16px 20px;
            }
            
            .logo {
                font-size: 26px;
            }
            
            .main-content {
                padding: 24px 16px;
            }
            
            .page-header h1 {
                font-size: 32px;
            }
            
            .products-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .modal {
                padding: 24px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <a href="index.php" class="logo">PikKiT</a>
            
            <div class="header-spacer"></div>
            
            <div class="header-actions">
                <a href="sell_product.php" class="btn btn-primary">Sell New Product</a>
                
                <div class="user-menu">
                    <div class="user-btn">
                        <div class="user-avatar"><?php echo strtoupper(substr($userName, 0, 1)); ?></div>
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
            </div>
        </div>
    </header>
    
    <!-- Main Content -->
    <main class="main-content">
        <div class="page-header">
            <h1>My Products</h1>
            <p class="page-subtitle">Manage all your listed products</p>
        </div>
        
        <?php if(isset($successMsg)): ?>
            <div class="alert alert-success">
                <span>✓</span>
                <?php echo htmlspecialchars($successMsg); ?>
            </div>
        <?php endif; ?>
        
        <?php if(isset($errorMsg)): ?>
            <div class="alert alert-error">
                <span>✕</span>
                <?php echo htmlspecialchars($errorMsg); ?>
            </div>
        <?php endif; ?>
        
        <!-- Stats -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-label">Total Products</div>
                <div class="stat-value"><?php echo count($products); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Value</div>
                <div class="stat-value">
                    Rs. <?php echo number_format(array_sum(array_column($products, 'price'))); ?>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Stock</div>
                <div class="stat-value"><?php echo array_sum(array_column($products, 'stock')); ?></div>
            </div>
        </div>
        
        <?php if(count($products) > 0): ?>
            <!-- Products Grid -->
            <div class="products-grid">
                <?php foreach($products as $product): ?>
                    <div class="product-card" id="product-<?php echo $product['id']; ?>">
                        <form method="POST" enctype="multipart/form-data" class="product-form">
                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                            
                            <!-- Product Image -->
                            <div class="product-image-container">
                                <?php if(!empty($product['image'])): ?>
                                    <img src="uploads/products/<?php echo htmlspecialchars($product['image']); ?>" 
                                         alt="<?php echo htmlspecialchars($product['name']); ?>"
                                         id="preview-<?php echo $product['id']; ?>">
                                <?php else: ?>
                                    <div class="product-image-placeholder" id="preview-<?php echo $product['id']; ?>">📦</div>
                                <?php endif; ?>
                                
                                <label for="image-<?php echo $product['id']; ?>" class="image-upload-overlay">
                                    <div class="upload-icon">📷</div>
                                    <div class="upload-text">Click to change image</div>
                                </label>
                                <input 
                                    type="file" 
                                    name="product_image" 
                                    id="image-<?php echo $product['id']; ?>" 
                                    accept="image/*"
                                    style="display: none;"
                                    onchange="previewImage(this, <?php echo $product['id']; ?>)"
                                >
                            </div>
                            
                            <div class="product-info">
                                <!-- View Mode -->
                                <div class="view-mode">
                                    <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                                    <div class="product-description">
                                        <?php echo htmlspecialchars($product['description']); ?>
                                    </div>
                                    <div class="product-price">Rs. <?php echo number_format($product['price']); ?></div>
                                    <div class="product-meta">
                                        <div class="meta-item">
                                            <span class="meta-label">Category:</span>
                                            <span><?php echo !empty($product['category']) ? htmlspecialchars($product['category']) : 'Uncategorized'; ?></span>
                                        </div>
                                        <div class="meta-item">
                                            <span class="meta-label">Stock:</span>
                                            <span><?php echo htmlspecialchars($product['stock']); ?></span>
                                        </div>
                                    </div>
                                    <div class="product-actions">
                                        <button type="button" class="action-btn btn-edit" onclick="enableEdit(<?php echo $product['id']; ?>)">
                                            Edit
                                        </button>
                                        <button 
                                            type="button"
                                            class="action-btn btn-delete" 
                                            onclick="confirmDelete(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars(addslashes($product['name'])); ?>')">
                                            Delete
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Edit Mode -->
                                <div class="edit-mode">
                                    <div class="form-group">
                                        <label class="form-label">Product Name *</label>
                                        <input 
                                            type="text" 
                                            name="product_name" 
                                            class="form-input"
                                            value="<?php echo htmlspecialchars($product['name']); ?>"
                                            required
                                        >
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">Price (Rs.) *</label>
                                        <input 
                                            type="number" 
                                            name="product_price" 
                                            class="form-input"
                                            value="<?php echo htmlspecialchars($product['price']); ?>"
                                            required
                                            min="1"
                                            step="0.01"
                                        >
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">Description *</label>
                                        <textarea 
                                            name="product_description" 
                                            class="form-textarea"
                                            required
                                        ><?php echo htmlspecialchars($product['description']); ?></textarea>
                                    </div>
                                    
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label class="form-label">Category</label>
                                            <input 
                                                type="text" 
                                                name="product_category" 
                                                class="form-input"
                                                value="<?php echo htmlspecialchars($product['category']); ?>"
                                                placeholder="e.g., Electronics"
                                            >
                                        </div>
                                        
                                        <div class="form-group">
                                            <label class="form-label">Stock</label>
                                            <input 
                                                type="number" 
                                                name="product_stock" 
                                                class="form-input"
                                                value="<?php echo htmlspecialchars($product['stock']); ?>"
                                                min="0"
                                            >
                                        </div>
                                    </div>
                                    
                                    <div class="product-actions">
                                        <button type="button" class="action-btn btn-cancel" onclick="cancelEdit(<?php echo $product['id']; ?>)">
                                            Cancel
                                        </button>
                                        <button type="submit" name="update_product" class="action-btn btn-save">
                                            Save Changes
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <!-- Empty State -->
            <div class="empty-state">
                <div class="empty-icon">📦</div>
                <h3>No Products Yet</h3>
                <p>You haven't listed any products. Start selling today!</p>
                <a href="sell_product.php" class="btn btn-primary">List Your First Product</a>
            </div>
        <?php endif; ?>
    </main>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal">
            <div class="modal-header">
                <div class="modal-icon">⚠️</div>
                <h3>Delete Product?</h3>
                <p>Are you sure you want to delete "<strong id="productNameToDelete"></strong>"? This action cannot be undone and the product image will also be permanently deleted.</p>
            </div>
            <div class="modal-actions">
                <button class="modal-btn modal-btn-cancel" onclick="closeDeleteModal()">Cancel</button>
                <form method="POST" id="deleteForm" style="flex: 1; margin: 0;">
                    <input type="hidden" name="product_id" id="productIdToDelete">
                    <button type="submit" name="delete_product" class="modal-btn modal-btn-confirm" style="width: 100%;">
                        Delete Product
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Enable edit mode for a product card
        function enableEdit(productId) {
            const card = document.getElementById('product-' + productId);
            card.classList.add('editing');
        }
        
        // Cancel edit mode
        function cancelEdit(productId) {
            const card = document.getElementById('product-' + productId);
            card.classList.remove('editing');
            
            // Reset form
            const form = card.querySelector('.product-form');
            form.reset();
        }
        
        // Preview image before upload
        function previewImage(input, productId) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const preview = document.getElementById('preview-' + productId);
                    
                    // If preview is an img element
                    if(preview.tagName === 'IMG') {
                        preview.src = e.target.result;
                    } else {
                        // If preview is a placeholder div, replace it with img
                        const img = document.createElement('img');
                        img.id = 'preview-' + productId;
                        img.src = e.target.result;
                        img.alt = 'Product preview';
                        preview.parentNode.replaceChild(img, preview);
                    }
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        // Delete confirmation
        let productIdToDelete = null;
        
        function confirmDelete(productId, productName) {
            productIdToDelete = productId;
            document.getElementById('productIdToDelete').value = productId;
            document.getElementById('productNameToDelete').textContent = productName;
            document.getElementById('deleteModal').classList.add('active');
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
            productIdToDelete = null;
        }
        
        // Close modal when clicking outside
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if(e.target === this) {
                closeDeleteModal();
            }
        });
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if(e.key === 'Escape') {
                closeDeleteModal();
                
                // Also cancel any active edits
                document.querySelectorAll('.product-card.editing').forEach(card => {
                    const productId = card.id.replace('product-', '');
                    cancelEdit(productId);
                });
            }
        });
        
        // Auto-hide success/error messages after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);
    </script>
</body>
</html>