<?php
session_start();
header('Content-Type: application/json');

// Database connection
try {
    $pdo = new PDO("mysql:host=localhost;dbname=pikkit", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Get the JSON data from the request
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if(!isset($data['credential'])) {
    echo json_encode(['success' => false, 'message' => 'No credential provided']);
    exit();
}

$id_token = $data['credential'];

// Your Google Client ID
$client_id = '863511518630-t4oj6ktd9net7g1pj9a8etrot6ict9md.apps.googleusercontent.com';

try {
    // Verify the Google ID token using Google's tokeninfo endpoint
    $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($id_token);
    
    // Make the request
    $response = file_get_contents($url);
    
    if($response === false) {
        throw new Exception('Failed to verify token with Google');
    }
    
    $payload = json_decode($response, true);
    
    // Check if token is valid
    if(!isset($payload['email']) || $payload['aud'] !== $client_id) {
        throw new Exception('Invalid ID token');
    }
    
    // Extract user information
    $google_id = $payload['sub'];
    $email = $payload['email'];
    $name = $payload['name'] ?? 'User';
    $picture = $payload['picture'] ?? null;
    $email_verified = $payload['email_verified'] ?? false;
    
    // For security, only accept verified Gmail addresses
    if(!$email_verified) {
        throw new Exception('Email not verified');
    }
    
    // Check if it's a Gmail address
    if(!preg_match('/@gmail\.com$/i', $email)) {
        throw new Exception('Only Gmail addresses are allowed');
    }
    
    // Check if user exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($user) {
        // User exists, update google_id and picture if needed
        if(empty($user['google_id']) || $user['profile_picture'] !== $picture) {
            $stmt = $pdo->prepare("UPDATE users SET google_id = ?, profile_picture = ? WHERE id = ?");
            $stmt->execute([$google_id, $picture, $user['id']]);
        }
        
        // Log the user in
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        
        echo json_encode(['success' => true, 'message' => 'Login successful']);
    } else {
        // Create new user
        // Generate a random password for Google sign-in users
        $random_password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, google_id, profile_picture, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$name, $email, $random_password, $google_id, $picture]);
        
        $user_id = $pdo->lastInsertId();
        
        // Log the user in
        $_SESSION['user_id'] = $user_id;
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;
        
        echo json_encode(['success' => true, 'message' => 'Account created and logged in']);
    }
    
} catch(Exception $e) {
    error_log('Google Auth Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Authentication failed: ' . $e->getMessage()]);
}
?>