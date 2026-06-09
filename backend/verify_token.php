<?php
// backend/verify_token.php

require_once __DIR__ . '/helpers/cors.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/helpers/jwt.php';

header('Content-Type: application/json; charset=utf-8');

// Parse JSON input
$input = json_decode(file_get_contents('php://input'), true);

$token = isset($input['token']) ? trim($input['token']) : '';
$customSecret = isset($input['secret']) ? $input['secret'] : null;

// Determine secret to use
$secretToUse = ($customSecret !== null && $customSecret !== '') ? $customSecret : JWT_SECRET;

if (empty($token)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Token parameter is missing."]);
    exit();
}

$parts = explode('.', $token);
$header = null;
$payload = null;
$isValid = false;
$errorReason = null;

// Helper to base64url decode
function base64UrlDecode(string $data): string {
    $remainder = strlen($data) % 4;
    if ($remainder) {
        $padlen = 4 - $remainder;
        $data .= str_repeat('=', $padlen);
    }
    $decoded = base64_decode(str_replace(['-', '_'], ['+', '/'], $data));
    return $decoded === false ? '' : $decoded;
}

if (count($parts) !== 3) {
    $errorReason = "Invalid token format. A JWT must consist of three parts separated by dots (header.payload.signature).";
} else {
    list($base64UrlHeader, $base64UrlPayload, $base64UrlSignature) = $parts;
    
    // Try decoding header and payload regardless of signature validity
    $headerJson = base64UrlDecode($base64UrlHeader);
    $payloadJson = base64UrlDecode($base64UrlPayload);
    
    $header = json_decode($headerJson, true);
    $payload = json_decode($payloadJson, true);
    
    if ($headerJson === '' || !$header) {
        $errorReason = "Invalid Base64 or JSON structure in the header block.";
    } elseif ($payloadJson === '' || !$payload) {
        $errorReason = "Invalid Base64 or JSON structure in the payload block.";
    } else {
        // Validate signature and expiration
        try {
            // Using standard JWT decode to perform complete verification
            $decodedPayload = JWT::decode($token, $secretToUse);
            $isValid = true;
        } catch (Exception $e) {
            $isValid = false;
            $errorReason = $e->getMessage();
        }
    }
}

echo json_encode([
    "success" => true,
    "valid" => $isValid,
    "reason" => $errorReason,
    "header" => $header,
    "payload" => $payload,
    "verified_with_secret" => ($customSecret !== null && $customSecret !== '') ? "CUSTOM_SECRET" : "DEFAULT_SERVER_SECRET"
]);
