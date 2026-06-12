<?php
// backend/helpers/auth.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/jwt.php';

function requireAuth(): array {
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
        return JWT::decode($token, JWT_SECRET);
    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode(["success" => false, "message" => "Unauthorized: " . $e->getMessage()]);
        exit();
    }
}
