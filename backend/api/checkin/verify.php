<?php
// backend/api/checkin/verify.php

require_once __DIR__ . '/../../helpers/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/jwt.php';

header('Content-Type: application/json; charset=utf-8');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method Not Allowed. Only POST is supported."]);
    exit();
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
$token = isset($input['token']) ? trim($input['token']) : '';

if (empty($token)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Check-in token is required."]);
    exit();
}

try {
    // Decode and verify the JWT signature
    $payload = JWT::decode($token, JWT_SECRET);
    $userId = (int)$payload['sub'];
    $jti = $payload['jti'];
    
    $db = Database::getConnection();
    
    // Fetch the user from the database
    $stmt = $db->prepare("SELECT user_id, gym_status, active_jti FROM users WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "User not found."]);
        exit();
    }
    
    // Validation Rules
    if ($user['gym_status'] === 'INSIDE') {
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "Impersonation blocked: User already inside gym."]);
        exit();
    }
    
    if (empty($user['active_jti']) || $user['active_jti'] !== $jti) {
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "Invalid or already redeemed pass."]);
        exit();
    }
    
    // Start transaction to update status and log check-in atomically
    $db->beginTransaction();
    
    // Update user status and invalidate the JTI
    $updateStmt = $db->prepare("UPDATE users SET gym_status = 'INSIDE', active_jti = NULL WHERE user_id = ?");
    $updateStmt->execute([$userId]);
    
    // Log the check-in
    $logStmt = $db->prepare("INSERT INTO check_ins (user_id, check_in_time) VALUES (?, NOW())");
    $logStmt->execute([$userId]);
    
    $db->commit();
    
    echo json_encode([
        "success" => true,
        "message" => "Check-in successful. Welcome to the gym!"
    ]);
    
} catch (Exception $e) {
    // If JWT decoding failed (signature error, expired, etc.)
    http_response_code(403);
    echo json_encode([
        "success" => false,
        "message" => "Invalid or expired check-in pass. Details: " . $e->getMessage()
    ]);
}
