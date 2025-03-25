<?php
require_once 'config.php';

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, null, 'Invalid request method');
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$requiredFields = ['email', 'password'];
validateRequiredFields($requiredFields, $data);

try {
    $conn = getDBConnection();

    // Get user data
    $stmt = $conn->prepare("
        SELECT u.*, 
               CASE 
                   WHEN u.user_type = 'donor' THEN d.blood_type
                   ELSE NULL 
               END as blood_type
        FROM users u
        LEFT JOIN donors d ON u.id = d.user_id
        WHERE u.email = ?
    ");
    $stmt->execute([sanitizeInput($data['email'])]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($data['password'], $user['password'])) {
        sendResponse(false, null, 'Invalid email or password');
    }

    // Generate session token
    $sessionToken = bin2hex(random_bytes(32));

    // Store session token
    $stmt = $conn->prepare("
        INSERT INTO sessions (user_id, token, created_at)
        VALUES (?, ?, NOW())
    ");
    $stmt->execute([$user['id'], $sessionToken]);

    // Remove sensitive data
    unset($user['password']);

    sendResponse(true, [
        'user' => $user,
        'sessionToken' => $sessionToken
    ], 'Login successful');

} catch (Exception $e) {
    sendResponse(false, null, 'Login failed: ' . $e->getMessage());
}
?> 