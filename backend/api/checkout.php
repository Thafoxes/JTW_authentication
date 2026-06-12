<?php
// backend/api/checkout.php

require_once __DIR__ . '/../helpers/cors.php';
require_once __DIR__ . '/../helpers/auth.php';

header('Content-Type: application/json; charset=utf-8');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method Not Allowed. Only POST is supported."]);
    exit();
}

// Authenticate session
$payload = requireAuth();
$userId = (int)$payload['user_id'];

try {
    $db = Database::getConnection();
    
    // Find if there is an active check-in entry for this user
    $stmt = $db->prepare("SELECT id FROM check_ins WHERE user_id = ? AND check_out_time IS NULL ORDER BY check_in_time DESC LIMIT 1");
    $stmt->execute([$userId]);
    $checkIn = $stmt->fetch();
    
    $db->beginTransaction();
    
    if ($checkIn) {
        // Update check_out_time to NOW()
        $updateLogStmt = $db->prepare("UPDATE check_ins SET check_out_time = NOW() WHERE id = ?");
        $updateLogStmt->execute([$checkIn['id']]);
    }
    
    // Update users table status
    $updateUserStmt = $db->prepare("UPDATE users SET gym_status = 'OUTSIDE', active_jti = NULL WHERE user_id = ?");
    $updateUserStmt->execute([$userId]);
    
    $db->commit();
    
    echo json_encode([
        "success" => true,
        "message" => "Check-out successful. Goodbye!"
    ]);
    
} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database error during check-out: " . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Server error: " . $e->getMessage()]);
}
