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
$error = '';
$success = '';

// Handle product submission
if(isset($_POST['add_product'])) {
    $name = trim($_POST['product_name']);
    $description = trim($_POST['description']);
    $price = floatval($_POST['price']);
    $category = trim($_POST['category']);
    $stock = intval($_POST['stock']);
    
    // Validate inputs
    if(empty($name) || empty($description) || $price <= 0) {
        $error = "Please fill in all required fields with valid values";
    } else {
        // Handle image upload
        $imageName = null;
        if(isset($_FILES['product_image']) && $_FILES['product_image']['error'] === 0) {
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            $fileType = $_FILES['product_image']['type'];
            
            if(in_array($fileType, $allowedTypes)) {
                $uploadDir = 'uploads/products/';
                
                // Create directory if it doesn't exist
                if(!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $extension = pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION);
                $imageName = uniqid() . '_' . time() . '.' . $extension;
                $uploadPath = $uploadDir . $imageName;
                
                if(!move_uploaded_file($_FILES['product_image']['tmp_name'], $uploadPath)) {
                    $error = "Failed to upload image";
                    $imageName = null;
                }
            } else {
                $error = "Invalid image format. Please upload JPG, JPEG, PNG, or GIF";
            }
        }
        
        if(empty($error)) {
            try {
                // Insert product
                $stmt = $pdo->prepare("
                    INSERT INTO products (seller_id, name, description, price, category, stock, image) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$userId, $name, $description, $price, $category, $stock, $imageName]);
                
                // Update or create seller record
                $stmt = $pdo->prepare("
                    INSERT INTO sellers (user_id, total_products) 
                    VALUES (?, 1) 
                    ON DUPLICATE KEY UPDATE total_products = total_products + 1
                ");
                $stmt->execute([$userId]);
                
                $success = "Product added successfully! Redirecting...";
                header("refresh:2;url=index.php");
            } catch(PDOException $e) {
                $error = "Failed to add product. Please try again.";
                error_log("Product insert error: " . $e->getMessage());
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sell Product - Pikkit</title>
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
            justify-content: space-between;
        }
        
        .logo {
            font-size: 32px;
            font-weight: bold;
            color: #fff;
            text-decoration: none;
            letter-spacing: 2px;
        }
        
        .back-btn {
            background: white;
            color: #333;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 600;
        }
        
        .back-btn:hover {
            background: #f0f0f0;
        }
        
        .container {
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .form-card {
            background: white;
            border-radius: 10px;
            padding: 40px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .form-header {
            margin-bottom: 30px;
        }
        
        .form-header h1 {
            font-size: 28px;
            color: #333;
            margin-bottom: 10px;
        }
        
        .form-header p {
            color: #666;
            font-size: 14px;
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }
        
        .required {
            color: #FF6347;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #FFB6C1;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }
        
        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }
        
        .file-input-wrapper input[type=file] {
            position: absolute;
            left: -9999px;
        }
        
        .file-input-label {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 12px 15px;
            background: #f5f5f5;
            border: 2px dashed #ddd;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .file-input-label:hover {
            background: #e8e8e8;
            border-color: #FFB6C1;
        }
        
        .file-name {
            margin-top: 8px;
            font-size: 12px;
            color: #666;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .btn {
            padding: 14px 30px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #333;
            color: white;
            width: 100%;
        }
        
        .btn-primary:hover {
            background: #000;
        }
        
        .hint {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        @media (max-width: 768px) {
            .form-card {
                padding: 25px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <a href="index.php" class="logo">PikKiT</a>
            <a href="index.php" class="back-btn">← Back to Shop</a>
        </div>
    </header>
    
    <div class="container">
        <div class="form-card">
            <div class="form-header">
                <h1>Sell Your Product</h1>
                <p>Fill in the details below to list your product on Pikkit</p>
            </div>
            
            <?php if($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="product_name">Product Name <span class="required">*</span></label>
                    <input type="text" id="product_name" name="product_name" required>
                </div>
                
                <div class="form-group">
                    <label for="description">Description <span class="required">*</span></label>
                    <textarea id="description" name="description" required placeholder="Describe your product in detail..."></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="price">Price (Rs.) <span class="required">*</span></label>
                        <input type="number" id="price" name="price" min="1" step="0.01" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="stock">Stock Quantity <span class="required">*</span></label>
                        <input type="number" id="stock" name="stock" min="1" value="1" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="category">Category</label>
                    <select id="category" name="category">
                        <option value="">Select Category</option>
                        <option value="Electronics">Electronics</option>
                        <option value="Clothing">Clothing</option>
                        <option value="Sports">Sports & Outdoors</option>
                        <option value="Toys">Toys & Games</option>
                        <option value="Home">Home & Garden</option>
                        <option value="Books">Books</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Product Image</label>
                    <div class="file-input-wrapper">
                        <input type="file" id="product_image" name="product_image" accept="image/*" onchange="displayFileName(this)">
                        <label for="product_image" class="file-input-label">
                            📷 Click to upload image
                        </label>
                    </div>
                    <div id="file-name" class="file-name"></div>
                    <div class="hint">Accepted formats: JPG, JPEG, PNG, GIF (Max 5MB)</div>
                </div>
                
                <button type="submit" name="add_product" class="btn btn-primary">List Product</button>
            </form>
        </div>
    </div>
    
    <script>
        function displayFileName(input) {
            const fileName = input.files[0]?.name || '';
            const fileNameDiv = document.getElementById('file-name');
            if(fileName) {
                fileNameDiv.textContent = '📎 ' + fileName;
                fileNameDiv.style.color = '#333';
            } else {
                fileNameDiv.textContent = '';
            }
        }
    </script>
</body>
</html>