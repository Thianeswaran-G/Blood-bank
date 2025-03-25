<?php
require_once 'config.php';

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, null, 'Invalid request method');
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$requiredFields = ['requestId', 'donorEmail'];
validateRequiredFields($requiredFields, $data);

try {
    $conn = getDBConnection();

    // Begin transaction
    $conn->beginTransaction();

    // Get donor ID
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND user_type = 'donor'");
    $stmt->execute([sanitizeInput($data['donorEmail'])]);
    $donor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$donor) {
        throw new Exception('Invalid donor email');
    }

    // Check if request exists and is pending
    $stmt = $conn->prepare("
        SELECT * FROM blood_requests 
        WHERE id = ? AND status = 'pending'
    ");
    $stmt->execute([$data['requestId']]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        throw new Exception('Request not found or already processed');
    }

    // Check if donor has already accepted this request
    $stmt = $conn->prepare("
        SELECT id FROM request_acceptances 
        WHERE request_id = ? AND donor_id = ?
    ");
    $stmt->execute([$data['requestId'], $donor['id']]);
    if ($stmt->rowCount() > 0) {
        throw new Exception('You have already accepted this request');
    }

    // Update request status
    $stmt = $conn->prepare("
        UPDATE blood_requests 
        SET status = 'accepted' 
        WHERE id = ?
    ");
    $stmt->execute([$data['requestId']]);

    // Create request acceptance record
    $stmt = $conn->prepare("
        INSERT INTO request_acceptances (
            request_id, donor_id, created_at
        ) VALUES (?, ?, NOW())
    ");
    $stmt->execute([$data['requestId'], $donor['id']]);

    // Create donation record
    $stmt = $conn->prepare("
        INSERT INTO donations (
            request_id, status, created_at
        ) VALUES (?, 'scheduled', NOW())
    ");
    $stmt->execute([$data['requestId']]);

    // Get receiver information
    $stmt = $conn->prepare("
        SELECT u.name, u.phone, u.email
        FROM blood_requests br
        JOIN users u ON br.receiver_id = u.id
        WHERE br.id = ?
    ");
    $stmt->execute([$data['requestId']]);
    $receiver = $stmt->fetch(PDO::FETCH_ASSOC);

    // Commit transaction
    $conn->commit();

    sendResponse(true, [
        'receiver' => $receiver,
        'request' => [
            'bloodType' => $request['blood_type'],
            'units' => $request['units'],
            'urgency' => $request['urgency']
        ]
    ], 'Request accepted successfully');

} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    sendResponse(false, null, 'Failed to accept request: ' . $e->getMessage());
}
?> 