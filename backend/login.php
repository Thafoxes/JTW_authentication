<?php
// backend/login.php

require_once __DIR__ . '/helpers/cors.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/helpers/jwt.php';

header('Content-Type: application/json; charset=utf-8');

// NOTE: CSRF protection is not implemented in this project because we initially want to
// display how this project works by looking at how the token flow behaves.
// Additionally, since this API uses JWTs stored in localStorage and passed via custom
// Authorization headers (rather than cookies), standard browser-based CSRF is mitigated.

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method Not Allowed. Only POST is supported."]);
    exit();
}

// Get raw post data
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

$email = isset($input['email']) ? trim($input['email']) : '';
$password = isset($input['password']) ? $input['password'] : '';

// Validation
if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Please provide both email and password."]);
    exit();
}

try {
    $db = Database::getConnection();
    
    // Fetch user by email
    $stmt = $db->prepare("SELECT user_id, username, email, password_hash, ROLE, member_valid FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        http_response_code(401); // Unauthorized
        echo json_encode(["success" => false, "message" => "Invalid email or password."]);
        exit();
    }
    
    // Verify password
    if (!password_verify($password, $user['password_hash'])) {
        http_response_code(401); // Unauthorized
        echo json_encode(["success" => false, "message" => "Invalid email or password."]);
        exit();
    }
    
    // Check if member status is valid / not blacklisted
    if ($user['ROLE'] === 'blacklisted') {
        http_response_code(403); // Forbidden
        echo json_encode(["success" => false, "message" => "Your account has been blacklisted."]);
        exit();
    }

    if ((int)$user['member_valid'] !== 1) {
        http_response_code(403); // Forbidden
        echo json_encode(["success" => false, "message" => "Your membership is not currently active."]);
        exit();
    }
    
    // Generate JWT payload
    $issuedAt = time();
    $expirationTime = $issuedAt + (60 * 120); // Valid for 2 hours
    $payload = [
        "user_id" => (int) $user['user_id'],
        "username" => $user['username'],
        "email" => $user['email'],
        "role" => $user['ROLE'],
        "member_valid" => (int) $user['member_valid'],
        "iat" => $issuedAt,
        "exp" => $expirationTime
    ];
    
    // Encode JWT
    $jwtToken = JWT::encode($payload, JWT_SECRET);
    
    echo json_encode([
        "success" => true,
        "message" => "Login successful!",
        "token" => $jwtToken,
        "user" => [
            "user_id" => (int) $user['user_id'],
            "username" => $user['username'],
            "email" => $user['email'],
            "role" => $user['ROLE']
        ]
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Database error during login. Details: " . $e->getMessage()
    ]);
}
