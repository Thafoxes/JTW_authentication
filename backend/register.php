<?php
// backend/register.php

require_once __DIR__ . '/helpers/cors.php';
require_once __DIR__ . '/config/database.php';

header('Content-Type: application/json; charset=utf-8');

// only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method Not Allowed. Only POST is supported."]);
    exit();
}

// Get raw post data
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

$username = isset($input['username']) ? trim($input['username']) : '';
$email = isset($input['email']) ? trim($input['email']) : '';
$password = isset($input['password']) ? $input['password'] : '';

// validation
if (empty($username) || empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Please fill in all fields (username, email, password)."]);
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Invalid email format."]);
    exit();
}

if (strlen($username) < 3) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Username must be more than 3 characters."]);
    exit();
}


try {
    $db = Database::getConnection();
    
    // check if email already exists
    $stmt = $db->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        http_response_code(409); // Conflict
        echo json_encode(["success" => false, "message" => "Email is already registered."]);
        exit();
    }

    // check if username already exists
    $stmt = $db->prepare("SELECT user_id FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        http_response_code(409); // conflict
        echo json_encode(["success" => false, "message" => "Username is already taken."]);
        exit();
    }

    // hash the password
    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
    
    // insert new user
    $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, role, member_valid) VALUES (?, ?, ?, 'member', 1)");
    $stmt->execute([$username, $email, $passwordHash]);
    
    http_response_code(201); // created
    echo json_encode([
        "success" => true,
        "message" => "Account created successfully! You can now log in."
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Database error: " . $e->getMessage()
    ]);
}
