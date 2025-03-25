<?php
require_once 'config.php';

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, null, 'Invalid request method');
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$requiredFields = ['bloodType', 'units', 'urgency', 'latitude', 'longitude', 'receiverEmail'];
validateRequiredFields($requiredFields, $data);

try {
    $conn = getDBConnection();

    // Get receiver ID
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND user_type = 'receiver'");
    $stmt->execute([sanitizeInput($data['receiverEmail'])]);
    $receiver = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$receiver) {
        sendResponse(false, null, 'Invalid receiver email');
    }

    // Insert blood request
    $stmt = $conn->prepare("
        INSERT INTO blood_requests (
            receiver_id, blood_type, units, urgency, 
            latitude, longitude, status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");
    $stmt->execute([
        $receiver['id'],
        sanitizeInput($data['bloodType']),
        $data['units'],
        sanitizeInput($data['urgency']),
        $data['latitude'],
        $data['longitude']
    ]);
    $requestId = $conn->lastInsertId();

    // Get nearby donors
    $stmt = $conn->prepare("
        SELECT u.*, d.blood_type, d.last_donation,
               calculate_distance(u.latitude, u.longitude, ?, ?) as distance
        FROM users u
        JOIN donors d ON u.id = d.user_id
        WHERE d.blood_type = ?
        AND u.id != ?
        HAVING distance <= 50
        ORDER BY distance ASC
        LIMIT 10
    ");
    $stmt->execute([
        $data['latitude'],
        $data['longitude'],
        sanitizeInput($data['bloodType']),
        $receiver['id']
    ]);
    $nearbyDonors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Notify nearby donors (simulated)
    foreach ($nearbyDonors as $donor) {
        // In a real application, you would send push notifications or emails here
        $stmt = $conn->prepare("
            INSERT INTO notifications (
                user_id, request_id, message, created_at
            ) VALUES (?, ?, ?, NOW())
        ");
        $message = "New blood request for {$data['bloodType']} near your location";
        $stmt->execute([$donor['id'], $requestId, $message]);
    }

    sendResponse(true, [
        'requestId' => $requestId,
        'nearbyDonors' => count($nearbyDonors)
    ], 'Blood request submitted successfully');

} catch (Exception $e) {
    sendResponse(false, null, 'Failed to submit blood request: ' . $e->getMessage());
}
?> 