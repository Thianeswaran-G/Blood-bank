<?php
require_once 'config.php';

// Handle GET request
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, null, 'Invalid request method');
}

// Get query parameters
$receiverEmail = $_GET['receiverEmail'] ?? null;

if (!$receiverEmail) {
    sendResponse(false, null, 'Receiver email is required');
}

try {
    $conn = getDBConnection();

    // Get receiver's requests with donor information
    $stmt = $conn->prepare("
        SELECT br.*, 
               u.name as receiver_name,
               d.name as donor_name,
               d.blood_type as donor_blood_type,
               d.phone as donor_phone,
               d.email as donor_email
        FROM blood_requests br
        JOIN users u ON br.receiver_id = u.id
        LEFT JOIN request_acceptances ra ON br.id = ra.request_id
        LEFT JOIN users d ON ra.donor_id = d.id
        WHERE u.email = ?
        ORDER BY br.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([sanitizeInput($receiverEmail)]);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format response data
    $formattedRequests = array_map(function($request) {
        $response = [
            'id' => $request['id'],
            'bloodType' => $request['blood_type'],
            'units' => $request['units'],
            'urgency' => $request['urgency'],
            'status' => $request['status'],
            'timestamp' => $request['created_at']
        ];

        // Add donor information if request is accepted
        if ($request['status'] === 'accepted' && $request['donor_name']) {
            $response['donor'] = [
                'name' => $request['donor_name'],
                'bloodType' => $request['donor_blood_type'],
                'phone' => $request['donor_phone'],
                'email' => $request['donor_email']
            ];
        }

        return $response;
    }, $requests);

    sendResponse(true, $formattedRequests);

} catch (Exception $e) {
    sendResponse(false, null, 'Failed to fetch request history: ' . $e->getMessage());
}
?> 