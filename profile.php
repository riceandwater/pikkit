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
$successMsg = '';
$errorMsg = '';

// Fetch current user data
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if(!$user) {
        header("Location: login.php");
        exit();
    }
} catch(PDOException $e) {
    die("Error fetching user data: " . $e->getMessage());
}

// Handle profile update
if(isset($_POST['update_profile'])) {
    $newName = trim($_POST['name']);
    $newEmail = trim($_POST['email']);
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    $errors = [];
    
    // Validate inputs
    if(empty($newName)) {
        $errors[] = "Name cannot be empty";
    }
    
    if(empty($newEmail)) {
        $errors[] = "Email cannot be empty";
    } elseif(!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    // Check if email already exists (excluding current user)
    if(empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$newEmail, $userId]);
        if($stmt->fetch()) {
            $errors[] = "Email already in use";
        }
    }
    
    // Password change logic
    $passwordUpdate = false;
    if(!empty($newPassword) || !empty($confirmPassword)) {
        if(empty($currentPassword)) {
            $errors[] = "Current password is required to change password";
        } else {
            // Verify current password
            if(!password_verify($currentPassword, $user['password'])) {
                $errors[] = "Current password is incorrect";
            } else {
                if($newPassword !== $confirmPassword) {
                    $errors[] = "New passwords do not match";
                } elseif(strlen($newPassword) < 6) {
                    $errors[] = "New password must be at least 6 characters";
                } else {
                    $passwordUpdate = true;
                }
            }
        }
    }
    
    // Update profile if no errors
    if(empty($errors)) {
        try {
            if($passwordUpdate) {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, password = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$newName, $newEmail, $hashedPassword, $userId]);
                $successMsg = "Profile and password updated successfully!";
            } else {
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$newName, $newEmail, $userId]);
                $successMsg = "Profile updated successfully!";
            }
            
            // Update session
            $_SESSION['user_name'] = $newName;
            $_SESSION['user_email'] = $newEmail;
            
            // Refresh user data
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch(PDOException $e) {
            $errorMsg = "Failed to update profile: " . $e->getMessage();
        }
    } else {
        $errorMsg = implode("<br>", $errors);
    }
}

// Handle profile picture upload
if(isset($_POST['upload_picture'])) {
    if(isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $filename = $_FILES['profile_picture']['name'];
        $filetype = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $filesize = $_FILES['profile_picture']['size'];
        
        if(!in_array($filetype, $allowed)) {
            $errorMsg = "Only JPG, PNG, GIF, and WEBP files are allowed";
        } elseif($filesize > 5242880) { // 5MB
            $errorMsg = "File size must be less than 5MB";
        } else {
            $uploadDir = 'uploads/profiles/';
            if(!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            // Generate unique filename
            $newFilename = uniqid() . '_' . time() . '.' . $filetype;
            $uploadPath = $uploadDir . $newFilename;
            
            if(move_uploaded_file($_FILES['profile_picture']['tmp_name'], $uploadPath)) {
                // Delete old profile picture if exists
                if(!empty($user['profile_picture']) && file_exists('uploads/profiles/' . $user['profile_picture'])) {
                    unlink('uploads/profiles/' . $user['profile_picture']);
                }
                
                // Update database
                try {
                    $stmt = $pdo->prepare("UPDATE users SET profile_picture = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$newFilename, $userId]);
                    
                    $successMsg = "Profile picture updated successfully!";
                    
                    // Refresh user data
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$userId]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                } catch(PDOException $e) {
                    $errorMsg = "Failed to update profile picture in database";
                }
            } else {
                $errorMsg = "Failed to upload file";
            }
        }
    } else {
        $errorMsg = "Please select a file to upload";
    }
}

// Handle profile picture removal
if(isset($_POST['remove_picture'])) {
    if(!empty($user['profile_picture'])) {
        if(file_exists('uploads/profiles/' . $user['profile_picture'])) {
            unlink('uploads/profiles/' . $user['profile_picture']);
        }
        
        try {
            $stmt = $pdo->prepare("UPDATE users SET profile_picture = NULL, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$userId]);
            
            $successMsg = "Profile picture removed successfully!";
            
            // Refresh user data
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            $errorMsg = "Failed to remove profile picture";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - PikKiT</title>
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
            padding: 16px 32px;
            box-shadow: var(--shadow-sm);
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
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
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--secondary-gray);
            text-decoration: none;
            font-weight: 600;
            padding: 10px 20px;
            border-radius: 10px;
            border: 2px solid var(--border-color);
            transition: var(--transition);
        }
        
        .back-link:hover {
            border-color: var(--primary-pink);
            color: var(--accent-pink);
            background: rgba(255, 182, 217, 0.05);
            transform: translateX(-4px);
        }
        
        /* Main Container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 32px;
        }
        
        .page-title {
            font-family: 'Outfit', sans-serif;
            font-size: 42px;
            font-weight: 800;
            color: var(--secondary-gray);
            margin-bottom: 12px;
            letter-spacing: -2px;
        }
        
        .page-subtitle {
            color: #666;
            font-size: 16px;
            margin-bottom: 40px;
            font-weight: 500;
        }
        
        /* Alert Messages */
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 14px;
            font-weight: 600;
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
        
        /* Profile Grid */
        .profile-grid {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 32px;
        }
        
        /* Profile Picture Section */
        .profile-picture-card {
            background: white;
            border-radius: 20px;
            padding: 32px;
            box-shadow: var(--shadow-sm);
            border: 2px solid var(--border-color);
            height: fit-content;
            position: sticky;
            top: 100px;
        }
        
        .profile-picture-wrapper {
            text-align: center;
            margin-bottom: 24px;
        }
        
        .profile-picture-display {
            width: 200px;
            height: 200px;
            border-radius: 50%;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, var(--primary-pink), var(--accent-pink));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 72px;
            color: white;
            font-weight: 700;
            box-shadow: var(--shadow-md);
            overflow: hidden;
            border: 5px solid white;
        }
        
        .profile-picture-display img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .profile-name {
            font-family: 'Outfit', sans-serif;
            font-size: 24px;
            font-weight: 700;
            color: var(--secondary-gray);
            margin-bottom: 6px;
        }
        
        .profile-email {
            color: #666;
            font-size: 14px;
            font-weight: 500;
        }
        
        .picture-upload-form {
            margin-top: 24px;
        }
        
        .file-input-wrapper {
            margin-bottom: 16px;
        }
        
        .file-input-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--secondary-gray);
            margin-bottom: 8px;
        }
        
        .file-input {
            width: 100%;
            padding: 12px;
            border: 2px dashed var(--border-color);
            border-radius: 10px;
            font-size: 14px;
            transition: var(--transition);
            cursor: pointer;
            background: var(--light-gray);
        }
        
        .file-input:hover {
            border-color: var(--primary-pink);
            background: rgba(255, 182, 217, 0.05);
        }
        
        .picture-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            font-family: inherit;
            flex: 1;
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
        
        .btn-secondary {
            background: var(--light-gray);
            color: var(--secondary-gray);
            border: 2px solid var(--border-color);
        }
        
        .btn-secondary:hover {
            background: #e9ecef;
            border-color: var(--primary-pink);
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        
        .member-since {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
            text-align: center;
            color: #999;
            font-size: 13px;
        }
        
        /* Profile Form Section */
        .profile-form-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: var(--shadow-sm);
            border: 2px solid var(--border-color);
        }
        
        .form-section {
            margin-bottom: 40px;
        }
        
        .form-section:last-child {
            margin-bottom: 0;
        }
        
        .section-title {
            font-family: 'Outfit', sans-serif;
            font-size: 22px;
            font-weight: 700;
            color: var(--secondary-gray);
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .form-group {
            margin-bottom: 24px;
        }
        
        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: var(--secondary-gray);
            margin-bottom: 8px;
        }
        
        .form-label .required {
            color: var(--accent-pink);
        }
        
        .form-input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 15px;
            transition: var(--transition);
            font-family: inherit;
            background: white;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--primary-pink);
            box-shadow: 0 0 0 4px rgba(255, 182, 217, 0.15);
        }
        
        .form-help {
            font-size: 12px;
            color: #999;
            margin-top: 6px;
        }
        
        .password-section-note {
            background: rgba(255, 182, 217, 0.1);
            padding: 16px;
            border-radius: 10px;
            border: 2px solid rgba(255, 182, 217, 0.3);
            margin-bottom: 24px;
            font-size: 14px;
            color: #666;
            line-height: 1.6;
        }
        
        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            padding-top: 24px;
            border-top: 2px solid var(--border-color);
        }
        
        .btn-large {
            padding: 14px 36px;
            font-size: 15px;
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .profile-grid {
                grid-template-columns: 1fr;
            }
            
            .profile-picture-card {
                position: static;
            }
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 24px 16px;
            }
            
            .page-title {
                font-size: 32px;
            }
            
            .profile-form-card {
                padding: 24px;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn-large {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <a href="index.php" class="logo">PikKiT</a>
        <a href="index.php" class="back-link">← Back to Home</a>
    </header>
    
    <div class="container">
        <h1 class="page-title">My Profile</h1>
        <p class="page-subtitle">Manage your account settings and preferences</p>
        
        <?php if($successMsg): ?>
            <div class="alert alert-success">✓ <?php echo htmlspecialchars($successMsg); ?></div>
        <?php endif; ?>
        
        <?php if($errorMsg): ?>
            <div class="alert alert-error">✕ <?php echo $errorMsg; ?></div>
        <?php endif; ?>
        
        <div class="profile-grid">
            <!-- Profile Picture Section -->
            <div class="profile-picture-card">
                <div class="profile-picture-wrapper">
                    <div class="profile-picture-display">
                        <?php if(!empty($user['profile_picture'])): ?>
                            <img src="uploads/profiles/<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Profile Picture">
                        <?php else: ?>
                            <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="profile-name"><?php echo htmlspecialchars($user['name']); ?></div>
                    <div class="profile-email"><?php echo htmlspecialchars($user['email']); ?></div>
                </div>
                
                <form method="POST" enctype="multipart/form-data" class="picture-upload-form">
                    <div class="file-input-wrapper">
                        <label class="file-input-label">Change Profile Picture</label>
                        <input type="file" name="profile_picture" class="file-input" accept="image/*">
                    </div>
                    <div class="picture-actions">
                        <button type="submit" name="upload_picture" class="btn btn-primary">
                             Upload
                        </button>
                        <?php if(!empty($user['profile_picture'])): ?>
                            <button type="submit" name="remove_picture" class="btn btn-danger" onclick="return confirm('Remove profile picture?')">
                                Delete
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
                
                <div class="member-since">
                    Member since <?php echo date('M Y', strtotime($user['created_at'])); ?>
                </div>
            </div>
            
            <!-- Profile Form Section -->
            <div class="profile-form-card">
                <form method="POST" action="">
                    <!-- Basic Information -->
                    <div class="form-section">
                        <h2 class="section-title">Basic Information</h2>
                        
                        <div class="form-group">
                            <label class="form-label">
                                Name <span class="required">*</span>
                            </label>
                            <input 
                                type="text" 
                                name="name" 
                                class="form-input" 
                                value="<?php echo htmlspecialchars($user['name']); ?>"
                                required
                                maxlength="100"
                            >
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                Email <span class="required">*</span>
                            </label>
                            <input 
                                type="email" 
                                name="email" 
                                class="form-input" 
                                value="<?php echo htmlspecialchars($user['email']); ?>"
                                required
                                maxlength="100"
                            >
                        </div>
                    </div>
                    
                    <!-- Password Change -->
                    <div class="form-section">
                        <h2 class="section-title">Change Password</h2>
                        
                        <div class="password-section-note">
                             Leave password fields empty if you don't want to change your password.
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Current Password</label>
                            <input 
                                type="password" 
                                name="current_password" 
                                class="form-input"
                                placeholder="Enter your current password"
                            >
                            <div class="form-help">Required to change password</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">New Password</label>
                            <input 
                                type="password" 
                                name="new_password" 
                                class="form-input"
                                placeholder="Enter new password"
                                minlength="6"
                            >
                            <div class="form-help">Minimum 6 characters</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Confirm New Password</label>
                            <input 
                                type="password" 
                                name="confirm_password" 
                                class="form-input"
                                placeholder="Confirm new password"
                            >
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <a href="index.php" class="btn btn-secondary btn-large">Cancel</a>
                        <button type="submit" name="update_profile" class="btn btn-primary btn-large">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>