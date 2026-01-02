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

// Handle delete pocket
if(isset($_POST['delete_pocket'])) {
    $pocketId = (int)$_POST['pocket_id'];
    try {
        // Delete all items in the pocket first
        $stmt = $pdo->prepare("DELETE FROM pocket WHERE user_id = ? AND pocket_id = ?");
        $stmt->execute([$userId, $pocketId]);
        
        // Delete the pocket
        $stmt = $pdo->prepare("DELETE FROM pockets WHERE id = ? AND user_id = ?");
        $stmt->execute([$pocketId, $userId]);
        
        $successMsg = "Pocket deleted successfully!";
    } catch(PDOException $e) {
        $errorMsg = "Failed to delete pocket.";
    }
}

// Handle remove item from pocket
if(isset($_POST['remove_item'])) {
    $pocketItemId = (int)$_POST['pocket_item_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM pocket WHERE id = ? AND user_id = ?");
        $stmt->execute([$pocketItemId, $userId]);
        $successMsg = "Item removed from pocket!";
    } catch(PDOException $e) {
        $errorMsg = "Failed to remove item.";
    }
}

// Handle update quantity
if(isset($_POST['update_quantity'])) {
    $pocketItemId = (int)$_POST['pocket_item_id'];
    $newQuantity = (int)$_POST['quantity'];
    
    if($newQuantity > 0) {
        try {
            $stmt = $pdo->prepare("UPDATE pocket SET quantity = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$newQuantity, $pocketItemId, $userId]);
            $successMsg = "Quantity updated!";
        } catch(PDOException $e) {
            $errorMsg = "Failed to update quantity.";
        }
    }
}

// Fetch all user's pockets
try {
    $stmt = $pdo->prepare("SELECT * FROM pockets WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    $userPockets = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $userPockets = [];
}

// Fetch items for each pocket
$pocketsWithItems = [];
foreach($userPockets as $pocket) {
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, pr.name as product_name, pr.price, pr.image, pr.stock
            FROM pocket p
            JOIN products pr ON p.product_id = pr.id
            WHERE p.user_id = ? AND p.pocket_id = ?
            ORDER BY p.added_at DESC
        ");
        $stmt->execute([$userId, $pocket['id']]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $pocketsWithItems[] = [
            'pocket' => $pocket,
            'items' => $items
        ];
    } catch(PDOException $e) {
        $pocketsWithItems[] = [
            'pocket' => $pocket,
            'items' => []
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Pockets - PikKiT</title>
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
            justify-content: space-between;
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
        
        .btn-back {
            background: transparent;
            color: var(--secondary-gray);
            border-color: var(--border-color);
        }
        
        .btn-back:hover {
            border-color: var(--primary-pink);
            color: var(--accent-pink);
            background: rgba(255, 182, 217, 0.05);
        }
        
        /* Main Container */
        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 32px;
        }
        
        .page-header {
            margin-bottom: 40px;
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
        }
        
        /* Alert Messages */
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
        
        /* Pockets Grid */
        .pockets-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(450px, 1fr));
            gap: 32px;
        }
        
        .pocket-card {
            background: var(--white);
            border-radius: 20px;
            padding: 28px;
            box-shadow: var(--shadow-sm);
            border: 2px solid var(--border-color);
            transition: var(--transition);
        }
        
        .pocket-card:hover {
            box-shadow: var(--shadow-md);
            border-color: var(--primary-pink);
        }
        
        .pocket-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .pocket-title {
            font-family: 'Outfit', sans-serif;
            font-size: 24px;
            font-weight: 700;
            color: var(--secondary-gray);
        }
        
        .pocket-delete {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .pocket-delete:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        
        .pocket-items {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        
        .pocket-item {
            display: flex;
            gap: 16px;
            padding: 16px;
            background: var(--light-gray);
            border-radius: 12px;
            transition: var(--transition);
        }
        
        .pocket-item:hover {
            background: #e9ecef;
        }
        
        .item-image {
            width: 80px;
            height: 80px;
            border-radius: 10px;
            overflow: hidden;
            flex-shrink: 0;
            background: white;
        }
        
        .item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .item-details {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .item-name {
            font-weight: 600;
            font-size: 15px;
            color: var(--secondary-gray);
        }
        
        .item-price {
            color: var(--accent-pink);
            font-weight: 700;
            font-size: 16px;
        }
        
        .item-quantity {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 4px;
        }
        
        .quantity-label {
            font-size: 13px;
            color: #666;
            font-weight: 500;
        }
        
        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .quantity-input {
            width: 60px;
            padding: 6px 10px;
            border: 2px solid var(--border-color);
            border-radius: 6px;
            text-align: center;
            font-weight: 600;
        }
        
        .quantity-btn {
            background: var(--primary-pink);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .quantity-btn:hover {
            background: var(--accent-pink);
        }
        
        .item-actions {
            display: flex;
            flex-direction: column;
            gap: 8px;
            justify-content: center;
        }
        
        .item-remove {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            white-space: nowrap;
        }
        
        .item-remove:hover {
            background: #c82333;
        }
        
        .empty-pocket {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }
        
        .empty-pocket-text {
            font-size: 14px;
            margin-top: 10px;
        }
        
        .no-pockets {
            text-align: center;
            padding: 80px 20px;
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow-sm);
        }
        
        .no-pockets h3 {
            font-size: 24px;
            color: var(--secondary-gray);
            margin-bottom: 12px;
            font-weight: 700;
        }
        
        .no-pockets p {
            color: #666;
            font-size: 15px;
            margin-bottom: 24px;
        }
        
        .btn-create {
            background: linear-gradient(135deg, var(--primary-pink) 0%, var(--accent-pink) 100%);
            color: white;
            padding: 14px 32px;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-create:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 107, 157, 0.4);
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .pockets-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .main-container {
                padding: 24px 16px;
            }
            
            .page-header h1 {
                font-size: 32px;
            }
            
            .pocket-card {
                padding: 20px;
            }
            
            .pocket-item {
                flex-direction: column;
            }
            
            .item-actions {
                flex-direction: row;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <a href="index.php" class="logo">PikKiT</a>
            
            <div class="header-actions">
                <a href="index.php" class="btn btn-back">Back to Home</a>
            </div>
        </div>
    </header>
    
    <!-- Main Container -->
    <div class="main-container">
        <div class="page-header">
            <h1>My Pockets</h1>
            <p class="page-subtitle">Manage your saved items organized in pockets</p>
        </div>
        
        <?php if(isset($successMsg)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($successMsg); ?></div>
        <?php endif; ?>
        
        <?php if(isset($errorMsg)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($errorMsg); ?></div>
        <?php endif; ?>
        
        <?php if(count($pocketsWithItems) > 0): ?>
            <div class="pockets-grid">
                <?php foreach($pocketsWithItems as $pocketData): ?>
                    <div class="pocket-card">
                        <div class="pocket-header">
                            <h2 class="pocket-title"><?php echo htmlspecialchars($pocketData['pocket']['name']); ?></h2>
                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this pocket and all its items?');">
                                <input type="hidden" name="pocket_id" value="<?php echo $pocketData['pocket']['id']; ?>">
                                <button type="submit" name="delete_pocket" class="pocket-delete">Delete Pocket</button>
                            </form>
                        </div>
                        
                        <div class="pocket-items">
                            <?php if(count($pocketData['items']) > 0): ?>
                                <?php foreach($pocketData['items'] as $item): ?>
                                    <div class="pocket-item">
                                        <div class="item-image">
                                            <?php if(!empty($item['image'])): ?>
                                                <img src="uploads/products/<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                                            <?php else: ?>
                                                <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:#999;">No Image</div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="item-details">
                                            <div class="item-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                            <div class="item-price">Rs. <?php echo number_format($item['price']); ?></div>
                                            
                                            <div class="item-quantity">
                                                <span class="quantity-label">Quantity:</span>
                                                <form method="POST" class="quantity-controls">
                                                    <input type="hidden" name="pocket_item_id" value="<?php echo $item['id']; ?>">
                                                    <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" min="1" max="<?php echo $item['stock']; ?>" class="quantity-input">
                                                    <button type="submit" name="update_quantity" class="quantity-btn">Update</button>
                                                </form>
                                            </div>
                                        </div>
                                        
                                        <div class="item-actions">
                                            <form method="POST" onsubmit="return confirm('Remove this item from pocket?');">
                                                <input type="hidden" name="pocket_item_id" value="<?php echo $item['id']; ?>">
                                                <button type="submit" name="remove_item" class="item-remove">Remove</button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-pocket">
                                    <div class="empty-pocket-text">This pocket is empty. Start adding products!</div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-pockets">
                <h3>No Pockets Yet</h3>
                <p>Create your first pocket to start organizing your favorite products</p>
                <a href="index.php" class="btn-create">Go to Home & Create Pocket</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
