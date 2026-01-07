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

// Get product ID
$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if($productId <= 0) {
    header("Location: index.php");
    exit();
}

// Fetch product details
try {
    $stmt = $pdo->prepare("
        SELECT p.*, u.name as seller_name 
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

// Fetch user details for pre-filling
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $user = null;
}

// Handle form submission
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $buyerName = trim($_POST['buyer_name']);
    $buyerEmail = trim($_POST['buyer_email']);
    $buyerPhone = trim($_POST['buyer_phone']);
    $shippingAddress = trim($_POST['shipping_address']);
    $quantity = (int)$_POST['quantity'];
    $paymentMethod = $_POST['payment_method'];
    
    $errors = [];
    
    // Validation
    if(empty($buyerName)) {
        $errors[] = "Name is required";
    }
    if(empty($buyerEmail) || !filter_var($buyerEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required";
    }
    if(empty($buyerPhone)) {
        $errors[] = "Phone number is required";
    }
    if(empty($shippingAddress)) {
        $errors[] = "Delivery address is required";
    }
    if($quantity <= 0) {
        $errors[] = "Quantity must be at least 1";
    }
    if($quantity > $product['stock']) {
        $errors[] = "Only " . $product['stock'] . " items available in stock";
    }
    if(!in_array($paymentMethod, ['COD', 'Esewa'])) {
        $errors[] = "Invalid payment method";
    }
    
    if(empty($errors)) {
        try {
            $totalPrice = $product['price'] * $quantity;
            
            // Insert order - matching your database column names
            $stmt = $pdo->prepare("
                INSERT INTO buyers (
                    user_id, product_id, quantity, total_price, 
                    buyer_name, buyer_email, buyer_phone, shipping_address,
                    payment_method, status, purchase_date
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([
                $userId, $productId, $quantity, $totalPrice,
                $buyerName, $buyerEmail, $buyerPhone, $shippingAddress,
                $paymentMethod
            ]);
            
            $orderId = $pdo->lastInsertId();
            
            // Update product stock
            $stmt = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
            $stmt->execute([$quantity, $productId]);
            
            // Update seller's total sales
            $stmt = $pdo->prepare("
                INSERT INTO sellers (user_id, total_products, total_sales) 
                VALUES (?, 1, ?) 
                ON DUPLICATE KEY UPDATE total_sales = total_sales + ?
            ");
            $stmt->execute([$product['seller_id'], $totalPrice, $totalPrice]);
            
            // Redirect based on payment method
            if($paymentMethod === 'Esewa') {
                header("Location: esewa_payment.php?order_id=" . $orderId);
                exit();
            } else {
                header("Location: order_success.php?order_id=" . $orderId);
                exit();
            }
            
        } catch(PDOException $e) {
            $errors[] = "Order failed. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - <?php echo htmlspecialchars($product['name']); ?></title>
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
        
        .header {
            background: var(--white);
            padding: 16px 32px;
            box-shadow: var(--shadow-sm);
            border-bottom: 1px solid var(--border-color);
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            gap: 24px;
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
        }
        
        .back-btn {
            margin-left: auto;
            padding: 10px 20px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            text-decoration: none;
            color: var(--secondary-gray);
            font-weight: 600;
            transition: var(--transition);
        }
        
        .back-btn:hover {
            border-color: var(--primary-pink);
            color: var(--accent-pink);
        }
        
        .checkout-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 32px;
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 30px;
        }
        
        .checkout-card {
            background: white;
            border-radius: 20px;
            padding: 35px;
            box-shadow: var(--shadow-md);
            border: 2px solid var(--border-color);
        }
        
        .card-title {
            font-family: 'Outfit', sans-serif;
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 25px;
            color: var(--secondary-gray);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: var(--secondary-gray);
            margin-bottom: 8px;
        }
        
        .form-input, .form-textarea {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 15px;
            font-family: inherit;
            transition: var(--transition);
        }
        
        .form-textarea {
            min-height: 80px;
            resize: vertical;
        }
        
        .form-input:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--primary-pink);
            box-shadow: 0 0 0 4px rgba(255, 182, 217, 0.15);
        }
        
        .quantity-control {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .quantity-btn {
            width: 40px;
            height: 40px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            background: white;
            font-size: 20px;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            color: var(--secondary-gray);
        }
        
        .quantity-btn:hover {
            border-color: var(--primary-pink);
            background: var(--light-gray);
        }
        
        .quantity-input {
            width: 80px;
            text-align: center;
            font-weight: 600;
        }
        
        .payment-options {
            display: grid;
            gap: 15px;
        }
        
        .payment-option {
            position: relative;
        }
        
        .payment-radio {
            position: absolute;
            opacity: 0;
        }
        
        .payment-label {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 16px 20px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            cursor: pointer;
            transition: var(--transition);
            background: white;
        }
        
        .payment-radio:checked + .payment-label {
            border-color: var(--accent-pink);
            background: rgba(255, 182, 217, 0.1);
        }
        
        .payment-icon {
            width: 24px;
            height: 24px;
            border: 2px solid var(--border-color);
            border-radius: 50%;
            position: relative;
            transition: var(--transition);
        }
        
        .payment-radio:checked + .payment-label .payment-icon {
            border-color: var(--accent-pink);
            background: var(--accent-pink);
        }
        
        .payment-radio:checked + .payment-label .payment-icon::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 8px;
            height: 8px;
            background: white;
            border-radius: 50%;
        }
        
        .payment-details {
            flex: 1;
        }
        
        .payment-name {
            font-weight: 600;
            font-size: 16px;
            margin-bottom: 4px;
        }
        
        .payment-desc {
            font-size: 13px;
            color: #666;
        }
        
        .order-summary {
            position: sticky;
            top: 100px;
        }
        
        .product-summary {
            display: flex;
            gap: 15px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--border-color);
            margin-bottom: 20px;
        }
        
        .product-image {
            width: 80px;
            height: 80px;
            border-radius: 10px;
            border: 2px solid var(--border-color);
            overflow: hidden;
            flex-shrink: 0;
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .product-info {
            flex: 1;
        }
        
        .product-name {
            font-weight: 600;
            font-size: 16px;
            margin-bottom: 6px;
        }
        
        .product-price {
            color: var(--accent-pink);
            font-weight: 700;
            font-size: 18px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 15px;
        }
        
        .summary-label {
            color: #666;
        }
        
        .summary-value {
            font-weight: 600;
        }
        
        .summary-total {
            display: flex;
            justify-content: space-between;
            padding-top: 15px;
            margin-top: 15px;
            border-top: 2px solid var(--border-color);
            font-size: 20px;
            font-weight: 700;
        }
        
        .summary-total .summary-value {
            color: var(--accent-pink);
        }
        
        .submit-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--primary-pink) 0%, var(--accent-pink) 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            margin-top: 20px;
            box-shadow: 0 4px 16px rgba(255, 107, 157, 0.3);
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 107, 157, 0.4);
        }
        
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 14px;
            font-weight: 600;
            border: 2px solid;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }
        
        .stock-warning {
            background: #fff3cd;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 13px;
            color: #856404;
            margin-top: 8px;
        }
        
        @media (max-width: 968px) {
            .checkout-container {
                grid-template-columns: 1fr;
            }
            
            .order-summary {
                position: static;
                order: -1;
            }
            
            .checkout-container {
                padding: 0 16px;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <a href="index.php" class="logo">PikKiT</a>
            <a href="index.php" class="back-btn">Back to shop</a>
        </div>
    </header>
    
    <div class="checkout-container">
        <!-- Checkout Form -->
        <div class="checkout-card">
            <h2 class="card-title">Checkout</h2>
            
            <?php if(!empty($errors)): ?>
                <div class="alert alert-error">
                    <?php foreach($errors as $error): ?>
                        • <?php echo htmlspecialchars($error); ?><br>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="checkoutForm">
                <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="buyer_name" class="form-input" 
                           value="<?php echo isset($_POST['buyer_name']) ? htmlspecialchars($_POST['buyer_name']) : ($user ? htmlspecialchars($user['name']) : ''); ?>" 
                           required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Email Address *</label>
                    <input type="email" name="buyer_email" class="form-input" 
                           value="<?php echo isset($_POST['buyer_email']) ? htmlspecialchars($_POST['buyer_email']) : ($user ? htmlspecialchars($user['email']) : ''); ?>" 
                           required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Phone Number *</label>
                    <input type="tel" name="buyer_phone" class="form-input" 
                           value="<?php echo isset($_POST['buyer_phone']) ? htmlspecialchars($_POST['buyer_phone']) : ''; ?>" 
                           placeholder="98XXXXXXXX" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Delivery Address *</label>
                    <textarea name="shipping_address" class="form-textarea" 
                              placeholder="Enter your complete delivery address (City, Area, Street, Landmark)" 
                              required><?php echo isset($_POST['shipping_address']) ? htmlspecialchars($_POST['shipping_address']) : ''; ?></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Quantity *</label>
                    <div class="quantity-control">
                        <button type="button" class="quantity-btn" onclick="decreaseQuantity()">-</button>
                        <input type="number" name="quantity" id="quantityInput" class="form-input quantity-input" 
                               value="1" min="1" max="<?php echo $product['stock']; ?>" required>
                        <button type="button" class="quantity-btn" onclick="increaseQuantity()">+</button>
                    </div>
                    <?php if($product['stock'] < 10): ?>
                        <div class="stock-warning">Only <?php echo $product['stock']; ?> items left in stock!</div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Payment Method *</label>
                    <div class="payment-options">
                        <div class="payment-option">
                            <input type="radio" name="payment_method" value="COD" id="cod" class="payment-radio" checked>
                            <label for="cod" class="payment-label">
                                <div class="payment-icon"></div>
                                <div class="payment-details">
                                    <div class="payment-name">Cash on Delivery</div>
                                    <div class="payment-desc">Pay when you receive the product</div>
                                </div>
                            </label>
                        </div>
                        
                        <div class="payment-option">
                            <input type="radio" name="payment_method" value="Esewa" id="esewa" class="payment-radio">
                            <label for="esewa" class="payment-label">
                                <div class="payment-icon"></div>
                                <div class="payment-details">
                                    <div class="payment-name">eSewa</div>
                                    <div class="payment-desc">Pay securely with eSewa wallet</div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
                
                <button type="submit" name="place_order" class="submit-btn">
                    PLACE ORDER
                </button>
            </form>
        </div>
        
        <!-- Order Summary -->
        <div class="checkout-card order-summary">
            <h2 class="card-title">Order Summary</h2>
            
            <div class="product-summary">
                <div class="product-image">
                    <?php if(!empty($product['image'])): ?>
                        <img src="uploads/products/<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                    <?php else: ?>
                        <div style="width:100%;height:100%;background:#f0f0f0;display:flex;align-items:center;justify-content:center;font-size:10px;color:#999;">No Image</div>
                    <?php endif; ?>
                </div>
                <div class="product-info">
                    <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                    <div class="product-price">Rs. <?php echo number_format($product['price']); ?></div>
                </div>
            </div>
            
            <div class="summary-row">
                <span class="summary-label">Price per item:</span>
                <span class="summary-value" id="pricePerItem">Rs. <?php echo number_format($product['price']); ?></span>
            </div>
            
            <div class="summary-row">
                <span class="summary-label">Quantity:</span>
                <span class="summary-value" id="summaryQuantity">1</span>
            </div>
            
            <div class="summary-row">
                <span class="summary-label">Subtotal:</span>
                <span class="summary-value" id="subtotal">Rs. <?php echo number_format($product['price']); ?></span>
            </div>
            
            <div class="summary-row">
                <span class="summary-label">Delivery:</span>
                <span class="summary-value">Free</span>
            </div>
            
            <div class="summary-total">
                <span class="summary-label">Total:</span>
                <span class="summary-value" id="totalPrice">Rs. <?php echo number_format($product['price']); ?></span>
            </div>
        </div>
    </div>
    
    <script>
        const pricePerItem = <?php echo $product['price']; ?>;
        const maxStock = <?php echo $product['stock']; ?>;
        
        function updateSummary() {
            const quantity = parseInt(document.getElementById('quantityInput').value) || 1;
            const total = pricePerItem * quantity;
            
            document.getElementById('summaryQuantity').textContent = quantity;
            document.getElementById('subtotal').textContent = 'Rs. ' + total.toLocaleString();
            document.getElementById('totalPrice').textContent = 'Rs. ' + total.toLocaleString();
        }
        
        function increaseQuantity() {
            const input = document.getElementById('quantityInput');
            const currentValue = parseInt(input.value) || 1;
            if(currentValue < maxStock) {
                input.value = currentValue + 1;
                updateSummary();
            }
        }
        
        function decreaseQuantity() {
            const input = document.getElementById('quantityInput');
            const currentValue = parseInt(input.value) || 1;
            if(currentValue > 1) {
                input.value = currentValue - 1;
                updateSummary();
            }
        }
        
        document.getElementById('quantityInput').addEventListener('input', updateSummary);
        document.getElementById('quantityInput').addEventListener('change', function() {
            let value = parseInt(this.value) || 1;
            if(value < 1) value = 1;
            if(value > maxStock) value = maxStock;
            this.value = value;
            updateSummary();
        });
    </script>
</body>
</html>