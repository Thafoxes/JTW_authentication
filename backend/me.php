<?php
// backend/me.php

require_once __DIR__ . '/helpers/cors.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/helpers/jwt.php';

header('Content-Type: application/json; charset=utf-8');

// Get all HTTP headers
$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';

if (empty($authHeader) && isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
}

if (empty($authHeader)) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Authorization header missing."]);
    exit();
}

// Extract the token from Bearer <token>
$token = null;
if (preg_match('/Bearer\s(\S+)/i', $authHeader, $matches)) {
    $token = $matches[1];
}

if (!$token) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Malformed authorization header. Expected Bearer <token>."]);
    exit();
}

try {
    // Decode and validate token
    $payload = JWT::decode($token, JWT_SECRET);
    
    // Fetch fresh database record to ensure user still exists and role hasn't changed
    $db = Database::getConnection();
    $stmt = $db->prepare("SELECT user_id, username, email, ROLE, member_valid, date_joined, gym_status FROM users WHERE user_id = ? LIMIT 1");
    $stmt->execute([$payload['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        http_response_code(401);
        echo json_encode(["success" => false, "message" => "User account no longer exists."]);
        exit();
    }
    
    if ($user['ROLE'] === 'blacklisted') {
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "Your account has been blacklisted."]);
        exit();
    }

    if ((int)$user['member_valid'] !== 1) {
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "Your membership is not currently active."]);
        exit();
    }
    
    echo json_encode([
        "success" => true,
        "user" => [
            "user_id" => (int) $user['user_id'],
            "username" => $user['username'],
            "email" => $user['email'],
            "role" => $user['ROLE'],
            "date_joined" => $user['date_joined'],
            "member_valid" => (int) $user['member_valid'],
            "gym_status" => $user['gym_status']
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Unauthorized: " . $e->getMessage()
    ]);
}
