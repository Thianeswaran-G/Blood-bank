<?php
require_once 'config.php';

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, null, 'Invalid request method');
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$requiredFields = ['name', 'email', 'password', 'phone', 'latitude', 'longitude', 'userType'];
validateRequiredFields($requiredFields, $data);

try {
    $conn = getDBConnection();

    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$data['email']]);
    if ($stmt->rowCount() > 0) {
        sendResponse(false, null, 'Email already registered');
    }

    // Hash password
    $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);

    // Begin transaction
    $conn->beginTransaction();

    // Insert into users table
    $stmt = $conn->prepare("
        INSERT INTO users (name, email, password, phone, latitude, longitude, user_type)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        sanitizeInput($data['name']),
        sanitizeInput($data['email']),
        $hashedPassword,
        sanitizeInput($data['phone']),
        $data['latitude'],
        $data['longitude'],
        $data['userType']
    ]);
    $userId = $conn->lastInsertId();

    // Insert additional data based on user type
    if ($data['userType'] === 'donor') {
        $stmt = $conn->prepare("
            INSERT INTO donors (user_id, blood_type, last_donation, health_conditions)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            sanitizeInput($data['bloodType']),
            $data['lastDonation'] ?? null,
            sanitizeInput($data['healthConditions'] ?? '')
        ]);
    } else {
        $stmt = $conn->prepare("
            INSERT INTO receivers (user_id, emergency_contact, emergency_phone, medical_history)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            sanitizeInput($data['emergencyContact']),
            sanitizeInput($data['emergencyPhone']),
            sanitizeInput($data['medicalHistory'])
        ]);
    }

    // Commit transaction
    $conn->commit();

    sendResponse(true, [
        'userId' => $userId,
        'userType' => $data['userType']
    ], 'Registration successful');

} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    sendResponse(false, null, 'Registration failed: ' . $e->getMessage());
}
?> 