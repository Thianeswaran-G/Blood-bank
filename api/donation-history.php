<?php
require_once 'config.php';

// Handle GET request
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, null, 'Invalid request method');
}

// Get query parameters
$donorEmail = $_GET['donorEmail'] ?? null;

if (!$donorEmail) {
    sendResponse(false, null, 'Donor email is required');
}

try {
    $conn = getDBConnection();

    // Get donor's donation history
    $stmt = $conn->prepare("
        SELECT d.*, 
               br.blood_type,
               br.units,
               br.urgency,
               u.name as receiver_name,
               ra.created_at as donation_date
        FROM donations d
        JOIN request_acceptances ra ON d.request_id = ra.request_id
        JOIN blood_requests br ON ra.request_id = br.id
        JOIN users u ON br.receiver_id = u.id
        WHERE ra.donor_id = (
            SELECT id FROM users WHERE email = ?
        )
        ORDER BY ra.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([sanitizeInput($donorEmail)]);
    $donations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format response data
    $formattedDonations = array_map(function($donation) {
        return [
            'id' => $donation['id'],
            'bloodType' => $donation['blood_type'],
            'units' => $donation['units'],
            'urgency' => $donation['urgency'],
            'receiver' => [
                'name' => $donation['receiver_name']
            ],
            'date' => $donation['donation_date'],
            'status' => $donation['status']
        ];
    }, $donations);

    sendResponse(true, $formattedDonations);

} catch (Exception $e) {
    sendResponse(false, null, 'Failed to fetch donation history: ' . $e->getMessage());
}
?> 