<?php
// backend/api/checkin/generate.php

require_once __DIR__ . '/../../helpers/cors.php';
require_once __DIR__ . '/../../helpers/auth.php';

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
    
    // Check user's current gym status
    $stmt = $db->prepare("SELECT gym_status FROM users WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "User not found."]);
        exit();
    }
    
    if ($user['gym_status'] === 'INSIDE') {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "User is already inside the gym."]);
        exit();
    }
    
    // Generate secure JTI
    $jti = bin2hex(random_bytes(16));
    
    // Generate JWT for check-in
    $issuedAt = time();
    $expirationTime = $issuedAt + 300; // Pass is valid for 5 minutes
    
    $checkinPayload = [
        "sub" => $userId,
        "jti" => $jti,
        "iat" => $issuedAt,
        "exp" => $expirationTime
    ];
    
    $token = JWT::encode($checkinPayload, JWT_SECRET);
    
    // Update user's active_jti in database
    $updateStmt = $db->prepare("UPDATE users SET active_jti = ? WHERE user_id = ?");
    $updateStmt->execute([$jti, $userId]);
    
    echo json_encode([
        "success" => true,
        "token" => $token
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Server error: " . $e->getMessage()]);
}
