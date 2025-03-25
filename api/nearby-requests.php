<?php
require_once 'config.php';

// Handle GET request
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, null, 'Invalid request method');
}

// Get query parameters
$donorEmail = $_GET['donorEmail'] ?? null;
$bloodType = $_GET['bloodType'] ?? null;

if (!$donorEmail) {
    sendResponse(false, null, 'Donor email is required');
}

try {
    $conn = getDBConnection();

    // Get donor's location and blood type
    $stmt = $conn->prepare("
        SELECT u.*, d.blood_type
        FROM users u
        JOIN donors d ON u.id = d.user_id
        WHERE u.email = ?
    ");
    $stmt->execute([sanitizeInput($donorEmail)]);
    $donor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$donor) {
        sendResponse(false, null, 'Donor not found');
    }

    // Build query for nearby requests
    $query = "
        SELECT br.*, 
               u.name as receiver_name,
               u.phone as receiver_phone,
               calculate_distance(u.latitude, u.longitude, ?, ?) as distance
        FROM blood_requests br
        JOIN users u ON br.receiver_id = u.id
        WHERE br.status = 'pending'
        AND br.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ";

    $params = [$donor['latitude'], $donor['longitude']];

    // Filter by blood type if specified
    if ($bloodType) {
        $query .= " AND br.blood_type = ?";
        $params[] = sanitizeInput($bloodType);
    }

    $query .= " HAVING distance <= 50 ORDER BY br.urgency DESC, distance ASC LIMIT 20";

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format response data
    $formattedRequests = array_map(function($request) {
        return [
            'id' => $request['id'],
            'bloodType' => $request['blood_type'],
            'units' => $request['units'],
            'urgency' => $request['urgency'],
            'distance' => round($request['distance'], 2),
            'location' => [
                'latitude' => $request['latitude'],
                'longitude' => $request['longitude']
            ],
            'receiver' => [
                'name' => $request['receiver_name'],
                'phone' => $request['receiver_phone']
            ],
            'timestamp' => $request['created_at']
        ];
    }, $requests);

    sendResponse(true, $formattedRequests);

} catch (Exception $e) {
    sendResponse(false, null, 'Failed to fetch nearby requests: ' . $e->getMessage());
}
?> 