<?php
session_start();
header('Content-Type: application/json');

// Database connection
try {
    $pdo = new PDO("mysql:host=localhost;dbname=pikkit", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
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

/**
 * Verify Google ID Token using cURL with better error handling
 */
function verifyGoogleToken($id_token, $client_id) {
    $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($id_token);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_FOLLOWLOCATION => false
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if($response === false) {
        throw new Exception('Network error: ' . $curl_error);
    }
    
    if($http_code !== 200) {
        throw new Exception('Token verification failed with HTTP code: ' . $http_code);
    }
    
    $payload = json_decode($response, true);
    
    if(json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON response from Google');
    }
    
    // Verify the token belongs to our app
    if(!isset($payload['aud']) || $payload['aud'] !== $client_id) {
        throw new Exception('Token audience mismatch');
    }
    
    // Verify email exists
    if(!isset($payload['email'])) {
        throw new Exception('Email not found in token');
    }
    
    // Verify email is verified by Google
    if(empty($payload['email_verified'])) {
        throw new Exception('Email not verified by Google');
    }
    
    return $payload;
}

try {
    // Verify the Google ID token
    $payload = verifyGoogleToken($id_token, $client_id);
    
    // Extract user information
    $google_id = $payload['sub'];
    $email = strtolower(trim($payload['email']));
    $name = $payload['name'] ?? 'User';
    $picture = $payload['picture'] ?? null;
    
    // Validate Gmail address
    if(!preg_match('/^[a-z0-9._%+-]+@gmail\.com$/i', $email)) {
        throw new Exception('Only Gmail addresses are allowed');
    }
    
    // Check if user already exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($existing_user) {
        // User already exists - redirect to login
        echo json_encode([
            'success' => false, 
            'message' => 'This email is already registered. Please login instead.',
            'redirect' => 'login.php'
        ]);
    } else {
        // Create new user
        $stmt = $pdo->prepare("INSERT INTO users (name, email, google_id, profile_picture, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
        
        if($stmt->execute([$name, $email, $google_id, $picture])) {
            $user_id = $pdo->lastInsertId();
            
            // Regenerate session ID to prevent session fixation
            session_regenerate_id(true);
            
            // Log the user in
            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email;
            
            echo json_encode([
                'success' => true, 
                'message' => 'Registration successful',
                'user' => [
                    'name' => $name,
                    'email' => $email
                ]
            ]);
        } else {
            throw new Exception('Failed to create user account');
        }
    }
    
} catch(PDOException $e) {
    error_log('Database Error in Google Registration: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'A database error occurred. Please try again.'
    ]);
} catch(Exception $e) {
    error_log('Google Registration Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Registration failed. Please try again.'
    ]);
}
?>