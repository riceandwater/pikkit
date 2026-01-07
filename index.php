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

// Fetch user profile picture if logged in
$userProfilePicture = null;
if($isLoggedIn) {
    try {
        $stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $userRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $userProfilePicture = $userRow['profile_picture'] ?? null;
    } catch(PDOException $e) {
        // Continue without profile picture
    }
}

// Get error message from session
$errorMessage = '';
if(isset($_SESSION['error_message'])) {
    $errorMessage = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

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
    <title>PikKiT - Shop Everything You Need</title>
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
            --shadow-panel: 4px 0 24px rgba(0,0,0,0.08);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-bounce: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }
        
        body {
            font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--light-gray);
            min-height: 100vh;
            color: var(--secondary-gray);
        }
        
        /* ===== HEADER STYLES ===== */
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
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .logo:hover {
            transform: scale(1.05);
            filter: brightness(1.1);
        }
        
        .search-container {
            flex: 1;
            max-width: 600px;
        }
        
        .search-form {
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .search-input {
            width: 100%;
            padding: 14px 140px 14px 20px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            font-size: 15px;
            outline: none;
            background: var(--white);
            transition: var(--transition);
            font-family: inherit;
        }
        
        .search-input:focus {
            border-color: var(--primary-pink);
            box-shadow: 0 0 0 4px rgba(255, 182, 217, 0.15);
        }
        
        .search-input::placeholder {
            color: #999;
        }
        
        .clear-search {
            position: absolute;
            right: 130px;
            background: transparent;
            border: none;
            color: #999;
            cursor: pointer;
            font-size: 18px;
            padding: 4px 10px;
            display: none;
            transition: var(--transition);
            border-radius: 6px;
        }
        
        .clear-search:hover {
            color: var(--secondary-gray);
            background: var(--light-gray);
        }
        
        .clear-search.show {
            display: block;
        }
        
        .search-btn {
            position: absolute;
            right: 6px;
            background: linear-gradient(135deg, var(--primary-pink) 0%, var(--accent-pink) 100%);
            color: white;
            border: none;
            padding: 10px 28px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: var(--transition);
            font-family: inherit;
            box-shadow: 0 2px 8px rgba(255, 107, 157, 0.3);
        }
        
        .search-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(255, 107, 157, 0.4);
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
        
        .btn-login {
            background: transparent;
            color: var(--secondary-gray);
            border-color: var(--border-color);
        }
        
        .btn-login:hover {
            border-color: var(--primary-pink);
            color: var(--accent-pink);
            background: rgba(255, 182, 217, 0.05);
        }
        
        .btn-signup {
            background: var(--secondary-gray);
            color: white;
        }
        
        .btn-signup:hover {
            background: #000;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
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
            overflow: hidden;
            flex-shrink: 0;
        }
        
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
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
            border: 1px solid var(--border-color);
        }
        
        .user-dropdown.show {
            display: block;
            animation: slideDown 0.3s ease;
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
        
        /* ===== MAIN LAYOUT ===== */
        .main-container {
            display: flex;
            max-width: 1600px;
            margin: 0 auto;
            position: relative;
        }
        
        /* ===== SIDE PANEL STYLES ===== */
        .side-panel {
            position: fixed;
            left: 0;
            top: 0;
            width: 280px;
            height: 100vh;
            background: linear-gradient(180deg, #FFB6D9 0%, #FF8FB8 100%);
            z-index: 1001;
            transform: translateX(-100%);
            transition: transform 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            box-shadow: var(--shadow-panel);
            display: flex;
            flex-direction: column;
        }
        
        .side-panel.active {
            transform: translateX(0);
        }
        
        .panel-header {
            padding: 24px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .panel-logo {
            font-family: 'Outfit', sans-serif;
            font-size: 36px;
            font-weight: 900;
            color: white;
            letter-spacing: -2px;
            text-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .panel-close {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
            backdrop-filter: blur(10px);
        }
        
        .panel-close:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg);
        }
        
        .panel-content {
            flex: 1;
            padding: 32px 28px;
            overflow-y: auto;
        }
        
        .panel-content::-webkit-scrollbar {
            width: 6px;
        }
        
        .panel-content::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
        }
        
        .panel-content::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 10px;
        }
        
        .panel-content::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.5);
        }
        
        .panel-actions {
            display: flex;
            flex-direction: column;
            gap: 14px;
            margin-bottom: 32px;
        }
        
        .panel-btn {
            background: rgba(255, 255, 255, 0.95);
            color: var(--secondary-gray);
            padding: 16px 24px;
            border: none;
            border-radius: 14px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: var(--transition-bounce);
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
            font-family: inherit;
            text-decoration: none;
            justify-content: flex-start;
            backdrop-filter: blur(10px);
        }
        
        .panel-btn:hover {
            transform: translateX(8px) scale(1.02);
            box-shadow: 0 6px 24px rgba(0,0,0,0.15);
            background: white;
        }
        
        .panel-btn-icon {
            width: 28px;
            height: 28px;
            background: linear-gradient(135deg, var(--primary-pink), var(--accent-pink));
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }
        
        .panel-section {
            margin-top: 24px;
        }
        
        .panel-section-title {
            color: white;
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 16px;
            opacity: 0.9;
        }
        
        .create-pocket-form {
            margin-bottom: 20px;
            padding: 18px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 14px;
            border: 2px dashed rgba(255, 255, 255, 0.3);
            transition: var(--transition);
            backdrop-filter: blur(10px);
        }
        
        .create-pocket-form:hover {
            border-color: rgba(255, 255, 255, 0.5);
            background: rgba(255, 255, 255, 0.2);
        }
        
        .pocket-input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 10px;
            font-size: 14px;
            margin-bottom: 12px;
            transition: var(--transition);
            font-family: inherit;
            background: rgba(255, 255, 255, 0.9);
            color: var(--secondary-gray);
        }
        
        .pocket-input:focus {
            outline: none;
            border-color: white;
            background: white;
            box-shadow: 0 0 0 4px rgba(255, 255, 255, 0.2);
        }
        
        .pocket-input::placeholder {
            color: #999;
        }
        
        .create-btn {
            width: 100%;
            padding: 13px;
            background: rgba(255, 255, 255, 0.95);
            color: var(--secondary-gray);
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            font-family: inherit;
        }
        
        .create-btn:hover {
            background: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .pockets-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .pocket-item {
            padding: 14px 16px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            color: white;
            border: 2px solid transparent;
            font-weight: 600;
            backdrop-filter: blur(10px);
        }
        
        .pocket-item:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateX(8px);
            border-color: rgba(255, 255, 255, 0.3);
        }
        
        .pocket-icon {
            font-size: 20px;
        }
        
        .pocket-name {
            flex: 1;
            font-size: 14px;
        }
        
        .empty-pockets {
            text-align: center;
            padding: 32px 20px;
            color: white;
            font-size: 14px;
            line-height: 1.8;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            backdrop-filter: blur(10px);
        }
        
        .empty-pockets-icon {
            font-size: 48px;
            margin-bottom: 12px;
            opacity: 0.7;
        }
        
        .alert {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 18px;
            font-size: 13px;
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
            background: rgba(255, 255, 255, 0.25);
            color: white;
            border-color: rgba(255, 255, 255, 0.4);
            backdrop-filter: blur(10px);
        }
        
        .alert-error {
            background: rgba(220, 53, 69, 0.15);
            color: white;
            border-color: rgba(220, 53, 69, 0.3);
            backdrop-filter: blur(10px);
        }
        
        .login-prompt {
            padding: 20px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 14px;
            text-align: center;
            font-size: 14px;
            color: white;
            margin-bottom: 24px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            line-height: 1.6;
            backdrop-filter: blur(10px);
            font-weight: 600;
        }
        
        .login-prompt a {
            color: white;
            font-weight: 800;
            text-decoration: none;
            border-bottom: 2px solid white;
            transition: var(--transition);
            padding-bottom: 2px;
        }
        
        .login-prompt a:hover {
            opacity: 0.8;
            letter-spacing: 0.5px;
        }
        
        /* Panel Toggle Button */
        .panel-toggle {
            position: fixed;
            left: 24px;
            top: 50%;
            transform: translateY(-50%);
            background: linear-gradient(135deg, var(--primary-pink) 0%, var(--accent-pink) 100%);
            color: white;
            border: none;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 999;
            box-shadow: 0 4px 20px rgba(255, 107, 157, 0.4);
            transition: var(--transition-bounce);
        }
        
        .panel-toggle:hover {
            transform: translateY(-50%) scale(1.1);
            box-shadow: 0 6px 28px rgba(255, 107, 157, 0.5);
        }
        
        .panel-toggle.active {
            left: 304px;
        }
        
        /* Panel Overlay */
        .panel-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            backdrop-filter: blur(4px);
            animation: fadeIn 0.3s ease;
        }
        
        .panel-overlay.active {
            display: block;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        /* ===== PRODUCTS SECTION ===== */
        .products-section {
            flex: 1;
            padding: 40px 32px;
            background: var(--light-gray);
            transition: var(--transition);
        }
        
        .products-section.shifted {
            margin-left: 280px;
        }
        
        .error-message-banner {
            background: #f8d7da;
            color: #721c24;
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 15px;
            font-weight: 600;
            border: 2px solid #f5c6cb;
            animation: slideIn 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .close-error {
            background: none;
            border: none;
            color: #721c24;
            font-size: 20px;
            cursor: pointer;
            padding: 0 8px;
            transition: var(--transition);
        }
        
        .close-error:hover {
            transform: scale(1.2);
        }
        
        .products-header {
            margin-bottom: 32px;
        }
        
        .products-header h2 {
            color: var(--secondary-gray);
            font-family: 'Outfit', sans-serif;
            font-size: 36px;
            margin-bottom: 10px;
            font-weight: 800;
            letter-spacing: -1.5px;
        }
        
        .results-info {
            color: #666;
            font-size: 15px;
            font-weight: 500;
        }
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 28px;
        }
        
        .product-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
            border: 2px solid transparent;
        }
        
        .product-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-pink);
        }
        
        .product-image {
            width: 100%;
            height: 280px;
            object-fit: cover;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
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
            transition: transform 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .product-card:hover .product-image img {
            transform: scale(1.1);
        }
        
        .product-info {
            padding: 20px;
        }
        
        .product-name {
            font-weight: 600;
            color: var(--secondary-gray);
            margin-bottom: 12px;
            font-size: 16px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.5;
            min-height: 48px;
        }
        
        .product-price {
            color: var(--accent-pink);
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -0.5px;
        }
        
        .no-products {
            text-align: center;
            padding: 100px 20px;
            color: #666;
        }
        
        .no-products-icon {
            font-size: 80px;
            margin-bottom: 20px;
            opacity: 0.4;
        }
        
        .no-products h3 {
            font-size: 28px;
            margin-bottom: 14px;
            color: var(--secondary-gray);
            font-weight: 700;
        }
        
        .no-products p {
            font-size: 16px;
            color: #999;
        }
        
        /* ===== RESPONSIVE DESIGN ===== */
        @media (max-width: 1200px) {
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
                gap: 24px;
            }
        }
        
        @media (max-width: 1024px) {
            .products-section.shifted {
                margin-left: 0;
            }
            
            .panel-toggle.active {
                left: 24px;
            }
        }
        
        @media (max-width: 768px) {
            .header-content {
                flex-wrap: wrap;
                gap: 16px;
                padding: 16px 20px;
            }
            
            .logo {
                font-size: 26px;
            }
            
            .search-container {
                order: 3;
                flex-basis: 100%;
                margin-top: 8px;
            }
            
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
                gap: 18px;
            }
            
            .products-section {
                padding: 24px 16px;
            }
            
            .product-image {
                height: 220px;
            }
            
            .product-info {
                padding: 16px;
            }
            
            .product-name {
                font-size: 14px;
                min-height: 42px;
            }
            
            .product-price {
                font-size: 19px;
            }
            
            .side-panel {
                width: 85%;
                max-width: 320px;
            }
            
            .panel-toggle {
                width: 52px;
                height: 52px;
                font-size: 22px;
            }
        }
        
        @media (max-width: 480px) {
            .logo {
                font-size: 24px;
            }
            
            .products-header h2 {
                font-size: 28px;
            }
            
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
                gap: 14px;
            }
        }
    </style>
</head>
<body>
    <!-- Panel Toggle Button -->
    <button class="panel-toggle" id="panelToggle" onclick="togglePanel()">
        <span id="toggleIcon">></span>
    </button>
    
    <!-- Panel Overlay -->
    <div class="panel-overlay" id="panelOverlay" onclick="togglePanel()"></div>
    
    <!-- Side Panel -->
    <aside class="side-panel" id="sidePanel">
        <div class="panel-header">
            <div class="panel-logo">PikKiT</div>
            <button class="panel-close" onclick="togglePanel()">✕</button>
        </div>
        
        <div class="panel-content">
            <?php if($isLoggedIn): ?>
                <!-- Quick Actions -->
                <div class="panel-actions">
                    <a href="sell_product.php" class="panel-btn">
                        <div class="panel-btn-icon"></div>
                        <span>Sell</span>
                    </a>
                </div>
                
                <div class="panel-section">
                    <div class="panel-section-title">My Pockets</div>
                    
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
                                ➕ Create Pocket
                            </button>
                        </form>
                    </div>
                    
                    <div class="pockets-list">
                        <?php if(count($userPockets) > 0): ?>
                            <?php foreach($userPockets as $pocket): ?>
                                <a href="pocket.php?id=<?php echo $pocket['id']; ?>" class="pocket-item">
                                    <span class="pocket-icon"></span>
                                    <span class="pocket-name"><?php echo htmlspecialchars($pocket['name']); ?></span>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-pockets">
                                <div class="empty-pockets-icon"></div>
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
                <div class="panel-actions">
                    <a href="login.php" class="panel-btn" onclick="alert('Please login to sell products'); return false;">
                        <div class="panel-btn-icon">🏷️</div>
                        <span>Sell</span>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </aside>
    
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <a href="index.php" class="logo">PikKiT</a>
            
            <div class="search-container">
                <form method="GET" action="index.php" class="search-form" id="searchForm">
                    <input 
                        type="text" 
                        name="search" 
                        class="search-input" 
                        id="searchInput"
                        placeholder="Search for anything"
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
                        <div class="user-btn" onclick="toggleUserDropdown(event)">
                            <div class="user-avatar">
                                <?php if(!empty($userProfilePicture)): ?>
                                    <img src="uploads/profiles/<?php echo htmlspecialchars($userProfilePicture); ?>" alt="<?php echo htmlspecialchars($userName); ?>">
                                <?php else: ?>
                                    <?php echo strtoupper(substr($userName, 0, 1)); ?>
                                <?php endif; ?>
                            </div>
                            <span><?php echo htmlspecialchars($userName); ?></span>
                        </div>
                        <div class="user-dropdown" id="userDropdown">
                            <a href="profile.php"> My Profile</a>
                            <a href="my_orders.php"> My Orders</a>
                            <a href="my_products.php"> My Products</a>
                            <a href="pocket.php"> My Pocket</a>
                            <a href="index.php?logout=1"> Logout</a>
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
        <!-- Products Section -->
        <main class="products-section" id="productsSection">
            <?php if(!empty($errorMessage)): ?>
                <div class="error-message-banner" id="errorBanner">
                    <span><?php echo htmlspecialchars($errorMessage); ?></span>
                    <button class="close-error" onclick="document.getElementById('errorBanner').remove()">✕</button>
                </div>
            <?php endif; ?>
            
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
        // Panel Toggle
        function togglePanel() {
            const panel = document.getElementById('sidePanel');
            const overlay = document.getElementById('panelOverlay');
            const toggle = document.getElementById('panelToggle');
            const toggleIcon = document.getElementById('toggleIcon');
            const productsSection = document.getElementById('productsSection');
            
            panel.classList.toggle('active');
            overlay.classList.toggle('active');
            toggle.classList.toggle('active');
            
            if(panel.classList.contains('active')) {
                toggleIcon.textContent = '✕';
                if(window.innerWidth > 1024) {
                    productsSection.classList.add('shifted');
                }
            } else {
                toggleIcon.textContent = '>';
                productsSection.classList.remove('shifted');
            }
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
        
        // User Dropdown Toggle - Updated function
        function toggleUserDropdown(event) {
            event.stopPropagation();
            const dropdown = document.getElementById('userDropdown');
            dropdown.classList.toggle('show');
        }
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            const dropdown = document.getElementById('userDropdown');
            const userBtn = document.querySelector('.user-btn');
            
            if(dropdown && userBtn) {
                if(!userBtn.contains(e.target) && !dropdown.contains(e.target)) {
                    dropdown.classList.remove('show');
                }
            }
        });
        
        // Prevent dropdown from closing when clicking inside it
        const userDropdown = document.getElementById('userDropdown');
        if(userDropdown) {
            userDropdown.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        }
        
        // Auto-hide error banner after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const errorBanner = document.getElementById('errorBanner');
            if(errorBanner) {
                setTimeout(function() {
                    errorBanner.style.transition = 'opacity 0.5s ease';
                    errorBanner.style.opacity = '0';
                    setTimeout(function() {
                        errorBanner.remove();
                    }, 500);
                }, 5000);
            }
        });
        
        // Handle window resize
        window.addEventListener('resize', function() {
            const panel = document.getElementById('sidePanel');
            const productsSection = document.getElementById('productsSection');
            
            if(window.innerWidth <= 1024) {
                productsSection.classList.remove('shifted');
            } else if(panel.classList.contains('active')) {
                productsSection.classList.add('shifted');
            }
        });
        
        // Close panel when clicking on a link (mobile only)
        if(window.innerWidth <= 1024) {
            document.querySelectorAll('.side-panel a, .panel-btn').forEach(link => {
                link.addEventListener('click', function() {
                    const panel = document.getElementById('sidePanel');
                    const overlay = document.getElementById('panelOverlay');
                    const toggle = document.getElementById('panelToggle');
                    const toggleIcon = document.getElementById('toggleIcon');
                    
                    if(panel.classList.contains('active')) {
                        panel.classList.remove('active');
                        overlay.classList.remove('active');
                        toggle.classList.remove('active');
                        toggleIcon.textContent = '>';
                    }
                });
            });
        }
    </script>
</body>
</html>