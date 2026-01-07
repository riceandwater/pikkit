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
    $email = trim($_POST['seller_email']);
    $color = trim($_POST['product_color']);
    
    // Validate inputs
    if(empty($name) || empty($description) || $price <= 0 || empty($email)) {
        $error = "Please fill in all required fields with valid values";
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
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
                    INSERT INTO products (seller_id, name, description, price, category, stock, image, seller_email, color) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$userId, $name, $description, $price, $category, $stock, $imageName, $email, $color]);
                
                // Update or create seller record
                $stmt = $pdo->prepare("
                    INSERT INTO sellers (user_id, total_products, email) 
                    VALUES (?, 1, ?) 
                    ON DUPLICATE KEY UPDATE total_products = total_products + 1, email = ?
                ");
                $stmt->execute([$userId, $email, $email]);
                
                $success = "Product added successfully! Redirecting...";
                header("refresh:2;url=index.php");
            } catch(PDOException $e) {
                $error = "Failed to add product. Please try again.";
                error_log("Product insert error: " . $e->getMessage());
            }
        }
    }
}

// Fetch user's email if exists
$userEmail = '';
try {
    $stmt = $pdo->prepare("SELECT email FROM sellers WHERE user_id = ?");
    $stmt->execute([$userId]);
    $seller = $stmt->fetch(PDO::FETCH_ASSOC);
    if($seller) {
        $userEmail = $seller['email'];
    }
} catch(PDOException $e) {
    // If sellers table doesn't have email yet, try users table
    try {
        $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if($user) {
            $userEmail = $user['email'];
        }
    } catch(PDOException $e) {
        // Ignore error
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
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #fafafa;
            min-height: 100vh;
            color: #1a1a1a;
        }
        
        .header {
            background: linear-gradient(135deg, #FFB6C1 0%, #FFA0B4 100%);
            padding: 16px 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
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
        
        .back-btn {
            background: white;
            color: #333;
            padding: 10px 24px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .back-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 24px;
        }
        
        .page-title {
            font-size: 32px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }
        
        .page-subtitle {
            color: #666;
            font-size: 15px;
            margin-bottom: 32px;
        }
        
        .form-layout {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 32px;
            align-items: start;
        }
        
        .form-card {
            background: white;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            border: 1px solid #f0f0f0;
        }
        
        .preview-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            border: 1px solid #f0f0f0;
            position: sticky;
            top: 100px;
        }
        
        .preview-title {
            font-size: 18px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .preview-content {
            border: 2px dashed #e0e0e0;
            border-radius: 12px;
            padding: 20px;
            min-height: 400px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        
        .preview-image-container {
            width: 100%;
            height: 280px;
            background: linear-gradient(135deg, #f5f5f5, #e8e8e8);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }
        
        .preview-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: none;
        }
        
        .preview-placeholder {
            color: #999;
            font-size: 48px;
        }
        
        .preview-info {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .preview-name {
            font-size: 18px;
            font-weight: 600;
            color: #1a1a1a;
            word-break: break-word;
        }
        
        .preview-price {
            font-size: 24px;
            font-weight: 700;
            color: #FF6347;
        }
        
        .preview-description {
            font-size: 14px;
            color: #666;
            line-height: 1.6;
            word-break: break-word;
        }
        
        .preview-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 8px;
        }
        
        .preview-badge {
            padding: 6px 12px;
            background: #f5f5f5;
            border-radius: 6px;
            font-size: 12px;
            color: #666;
            font-weight: 600;
        }
        
        .preview-color {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .color-dot {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 2px solid #ddd;
        }
        
        .alert {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 14px;
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
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }
        
        .form-group {
            margin-bottom: 24px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 10px;
            color: #1a1a1a;
            font-weight: 600;
            font-size: 14px;
        }
        
        .required {
            color: #FF6347;
            margin-left: 2px;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            font-family: inherit;
            transition: all 0.2s;
            background: white;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #FFB6C1;
            box-shadow: 0 0 0 3px rgba(255, 182, 193, 0.1);
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 120px;
            line-height: 1.6;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .color-input-wrapper {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        
        .color-input-wrapper input[type="color"] {
            width: 60px;
            height: 45px;
            padding: 4px;
            cursor: pointer;
            border: 2px solid #e0e0e0;
        }
        
        .color-input-wrapper input[type="text"] {
            flex: 1;
        }
        
        .file-upload-area {
            border: 2px dashed #e0e0e0;
            border-radius: 12px;
            padding: 32px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: #fafafa;
        }
        
        .file-upload-area:hover {
            border-color: #FFB6C1;
            background: #fff;
        }
        
        .file-upload-area.dragover {
            border-color: #FFB6C1;
            background: rgba(255, 182, 193, 0.05);
        }
        
        .file-upload-icon {
            font-size: 48px;
            margin-bottom: 16px;
        }
        
        .file-upload-text {
            font-size: 15px;
            color: #666;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .file-upload-hint {
            font-size: 13px;
            color: #999;
        }
        
        .file-input {
            display: none;
        }
        
        .uploaded-file-info {
            display: none;
            align-items: center;
            gap: 12px;
            padding: 16px;
            background: #f5f5f5;
            border-radius: 10px;
            margin-top: 16px;
        }
        
        .uploaded-file-info.show {
            display: flex;
        }
        
        .file-icon {
            font-size: 32px;
        }
        
        .file-details {
            flex: 1;
        }
        
        .file-name {
            font-size: 14px;
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
        }
        
        .file-size {
            font-size: 12px;
            color: #666;
        }
        
        .remove-file {
            background: #ff4444;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s;
        }
        
        .remove-file:hover {
            background: #cc0000;
        }
        
        .btn {
            padding: 14px 32px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #333, #000);
            color: white;
            width: 100%;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.25);
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }
        
        .hint {
            font-size: 13px;
            color: #999;
            margin-top: 8px;
            line-height: 1.5;
        }
        
        .email-icon {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }
        
        .input-with-icon {
            position: relative;
        }
        
        .input-with-icon input {
            padding-right: 45px;
        }
        
        @media (max-width: 1024px) {
            .form-layout {
                grid-template-columns: 1fr;
            }
            
            .preview-card {
                position: relative;
                top: 0;
                order: -1;
            }
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 0 16px;
            }
            
            .form-card, .preview-card {
                padding: 20px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .page-title {
                font-size: 28px;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <a href="index.php" class="logo">PikKiT</a>
            <a href="index.php" class="back-btn">
                <span>←</span>
                <span>Back to Shop</span>
            </a>
        </div>
    </header>
    
    <div class="container">
        <h1 class="page-title">Sell Your Product</h1>
        <p class="page-subtitle">Fill in the details below to list your product on Pikkit</p>
        
        <?php if($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <div class="form-layout">
            <div class="form-card">
                <form method="POST" enctype="multipart/form-data" id="productForm">
                    <div class="form-group">
                        <label for="product_name">Product Name <span class="required">*</span></label>
                        <input 
                            type="text" 
                            id="product_name" 
                            name="product_name" 
                            placeholder="Enter product name"
                            required
                            oninput="updatePreview()"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="seller_email">Contact Email <span class="required">*</span></label>
                        <div class="input-with-icon">
                            <input 
                                type="email" 
                                id="seller_email" 
                                name="seller_email" 
                                placeholder="your.email@example.com"
                                value="<?php echo htmlspecialchars($userEmail); ?>"
                                required
                                oninput="updatePreview()"
                            >
                            <span class="email-icon">✉</span>
                        </div>
                        <div class="hint">This email will be used for buyer inquiries and order notifications.</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description <span class="required">*</span></label>
                        <textarea 
                            id="description" 
                            name="description" 
                            placeholder="Describe your product in detail..."
                            required
                            oninput="updatePreview()"
                        ></textarea>
                        <div class="hint">Provide detailed information about your product including features, condition, and specifications.</div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="price">Price (Rs.) <span class="required">*</span></label>
                            <input 
                                type="number" 
                                id="price" 
                                name="price" 
                                min="1" 
                                step="0.01" 
                                placeholder="0.00"
                                required
                                oninput="updatePreview()"
                            >
                        </div>
                        
                        <div class="form-group">
                            <label for="stock">Stock Quantity <span class="required">*</span></label>
                            <input 
                                type="number" 
                                id="stock" 
                                name="stock" 
                                min="1" 
                                value="1" 
                                required
                                oninput="updatePreview()"
                            >
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="category">Category</label>
                            <select id="category" name="category" onchange="updatePreview()">
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
                        
                        
                    </div>
                    
                    <div class="form-group">
                        <label>Product Image</label>
                        <div class="file-upload-area" id="fileUploadArea" onclick="document.getElementById('product_image').click()">
                            <div class="file-upload-icon"></div>
                            <div class="file-upload-text">Click to upload or drag and drop</div>
                            <div class="file-upload-hint">JPG, JPEG, PNG, GIF (Max 5MB)</div>
                        </div>
                        <input 
                            type="file" 
                            id="product_image" 
                            name="product_image" 
                            accept="image/*" 
                            class="file-input"
                            onchange="handleFileSelect(this.files)"
                        >
                        <div class="uploaded-file-info" id="uploadedFileInfo">
                            <span class="file-icon"></span>
                            <div class="file-details">
                                <div class="file-name" id="fileName"></div>
                                <div class="file-size" id="fileSize"></div>
                            </div>
                            <button type="button" class="remove-file" onclick="removeFile()">Remove</button>
                        </div>
                    </div>
                    
                    <button type="submit" name="add_product" class="btn btn-primary">
                        <span></span>
                        <span>List Product</span>
                    </button>
                </form>
            </div>
            
            <div class="preview-card">
                <div class="preview-title">
                    <span></span>
                    <span>Live Preview</span>
                </div>
                <div class="preview-content">
                    <div class="preview-image-container">
                        <img id="previewImage" class="preview-image" alt="Product preview">
                        <span class="preview-placeholder" id="previewPlaceholder">📷</span>
                    </div>
                    <div class="preview-info">
                        <div class="preview-name" id="previewName">Product Name</div>
                        <div class="preview-price" id="previewPrice">Rs. 0</div>
                        <div class="preview-description" id="previewDescription">Product description will appear here...</div>
                        <div class="preview-meta">
                            <span class="preview-badge" id="previewCategory">Uncategorized</span>
                            <span class="preview-badge" id="previewStock">Stock: 1</span>
                            <span class="preview-badge preview-color" id="previewColor">
                                <span class="color-dot" id="previewColorDot"></span>
                                <span id="previewColorText">No color</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Color picker sync
       
        
        // Drag and drop functionality
        const fileUploadArea = document.getElementById('fileUploadArea');
        const fileInput = document.getElementById('product_image');
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            fileUploadArea.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            fileUploadArea.addEventListener(eventName, () => {
                fileUploadArea.classList.add('dragover');
            }, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            fileUploadArea.addEventListener(eventName, () => {
                fileUploadArea.classList.remove('dragover');
            }, false);
        });
        
        fileUploadArea.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            const files = dt.files;
            handleFileSelect(files);
        }, false);
        
        // File selection handler
        function handleFileSelect(files) {
            if (files.length > 0) {
                const file = files[0];
                
                // Validate file type
                if (!file.type.match('image.*')) {
                    alert('Please select an image file');
                    return;
                }
                
                // Validate file size (5MB)
                if (file.size > 5 * 1024 * 1024) {
                    alert('File size must be less than 5MB');
                    return;
                }
                
                // Display file info
                document.getElementById('fileName').textContent = file.name;
                document.getElementById('fileSize').textContent = formatFileSize(file.size);
                document.getElementById('uploadedFileInfo').classList.add('show');
                
                // Preview image
                const reader = new FileReader();
                reader.onload = (e) => {
                    const previewImage = document.getElementById('previewImage');
                    const placeholder = document.getElementById('previewPlaceholder');
                    previewImage.src = e.target.result;
                    previewImage.style.display = 'block';
                    placeholder.style.display = 'none';
                };
                reader.readAsDataURL(file);
            }
        }
        
        function removeFile() {
            document.getElementById('product_image').value = '';
            document.getElementById('uploadedFileInfo').classList.remove('show');
            const previewImage = document.getElementById('previewImage');
            const placeholder = document.getElementById('previewPlaceholder');
            previewImage.style.display = 'none';
            previewImage.src = '';
            placeholder.style.display = 'block';
        }
        
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }
        
          // Live preview updates
        function updatePreview() {
            const name = document.getElementById('product_name').value || 'Product Name';
            const price = document.getElementById('price').value || '0';
            const description = document.getElementById('description').value || 'Product description will appear here...';
            const category = document.getElementById('category').value || 'Uncategorized';
            const stock = document.getElementById('stock').value || '1';
            
            document.getElementById('previewName').textContent = name;
            document.getElementById('previewPrice').textContent = 'Rs. ' + (parseFloat(price) > 0 ? parseFloat(price).toLocaleString() : '0');
            document.getElementById('previewDescription').textContent = description;
            document.getElementById('previewCategory').textContent = category;
            document.getElementById('previewStock').textContent = 'Stock: ' + stock;
        }
        
        // Initialize preview
        updatePreview();
    </script>
</body>
</html>