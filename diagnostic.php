<?php
session_start();

// Connect to database
$pdo = new PDO("mysql:host=localhost;dbname=pikkit", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "<h2>DIAGNOSTIC CHECK</h2>";

// Check otp_codes table structure
echo "<h3>1. OTP Table Structure:</h3>";
$stmt = $pdo->query("DESCRIBE otp_codes");
echo "<table border='1'>";
echo "<tr><th>Field</th><th>Type</th></tr>";
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td></tr>";
}
echo "</table>";

// Check if there are any OTPs
echo "<h3>2. Current OTPs in Database:</h3>";
$stmt = $pdo->query("SELECT * FROM otp_codes ORDER BY id DESC LIMIT 5");
$otps = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<table border='1'>";
echo "<tr><th>Email</th><th>OTP</th><th>Expiry</th><th>Expired?</th></tr>";
foreach($otps as $otp) {
    $expired = (strtotime($otp['expiry']) < time()) ? 'YES' : 'NO';
    echo "<tr><td>{$otp['email']}</td><td>{$otp['otp']}</td><td>{$otp['expiry']}</td><td>{$expired}</td></tr>";
}
echo "</table>";

// Check session
echo "<h3>3. Current Session Variables:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Test OTP verification
if(isset($_POST['test_otp'])) {
    $email = $_POST['test_email'];
    $otp = $_POST['test_otp_code'];
    
    echo "<h3>4. Testing OTP Verification:</h3>";
    echo "Email: $email<br>";
    echo "OTP: $otp<br>";
    
    $stmt = $pdo->prepare("SELECT * FROM otp_codes WHERE email = ? AND otp = ? AND expiry > NOW()");
    $stmt->execute([$email, $otp]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($result) {
        echo "<p style='color: green;'>✓ OTP IS VALID!</p>";
        echo "<pre>";
        print_r($result);
        echo "</pre>";
    } else {
        echo "<p style='color: red;'>✗ OTP NOT FOUND OR EXPIRED</p>";
        
        // Check without expiry
        $stmt = $pdo->prepare("SELECT * FROM otp_codes WHERE email = ? AND otp = ?");
        $stmt->execute([$email, $otp]);
        $result2 = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($result2) {
            echo "<p style='color: orange;'>OTP exists but is EXPIRED</p>";
            echo "<pre>";
            print_r($result2);
            echo "</pre>";
        } else {
            echo "<p style='color: red;'>OTP does not exist in database</p>";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>OTP Diagnostic</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        table { border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px; text-align: left; }
        th { background: #333; color: white; }
        form { background: #f0f0f0; padding: 15px; margin: 20px 0; }
        input { padding: 8px; margin: 5px; }
        button { padding: 10px 20px; background: #333; color: white; border: none; cursor: pointer; }
    </style>
</head>
<body>
    <h3>Test OTP Verification:</h3>
    <form method="POST">
        <input type="email" name="test_email" placeholder="Enter email" required>
        <input type="text" name="test_otp_code" placeholder="Enter OTP" maxlength="6" required>
        <button type="submit" name="test_otp">Test Verification</button>
    </form>
    
    <hr>
    <p><a href="login.php">Back to Login</a></p>
</body>
</html>