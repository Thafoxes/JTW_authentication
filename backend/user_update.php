<?php


require_once __DIR__ . '/helpers/cors.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/helpers/jwt.php';
require_once __DIR__ . '/global_var.php';

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
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Unauthorized: " . $e->getMessage()]);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method Not Allowed. Only POST is supported."]);
    exit();
}

// Get raw post data
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

$userId = isset($input['userId']) ? trim($input['userId']) : '';
$username = isset($input['username']) ? trim($input['username']) : '';
$email = isset($input['email']) ? trim($input['email']) : '';
$isMemberValid = isset($input['isMemberValid']) ? $input['isMemberValid'] : '';
$role = isset($input['role']) ? $input['role'] : '';

// Validation
if (empty($userId) || empty($username) || empty($email) || $isMemberValid === '' || empty($role)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Please fill in all fields."]);
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

// Validate Role Enum
if (Membership::tryFrom($role) === null) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Invalid role specified."]);
    exit();
}

// Authorization check: User can only update themselves, unless they are an admin
$authenticatedUserId = (int)$payload['user_id'];
$authenticatedUserRole = $payload['role'];

if ($authenticatedUserId !== (int)$userId && $authenticatedUserRole !== 'admin') {
    http_response_code(403); // Forbidden
    echo json_encode(["success" => false, "message" => "You are not authorized to update this user account."]);
    exit();
}

try {
    $db = Database::getConnection();

    // fetch user current details to prevent privilege escalation
    $checkStmt = $db->prepare("SELECT ROLE, member_valid FROM users WHERE user_id = ? LIMIT 1");
    $checkStmt->execute([$userId]);
    $currentUser = $checkStmt->fetch();
    
    if (!$currentUser) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "User to update does not exist."]);
        exit();
    }
    
    // non-admins cannot elevate their own role or validation status
    if ($authenticatedUserRole !== 'admin') {
        $role = $currentUser['ROLE'];
        $isMemberValid = $currentUser['member_valid'];
    }

    $stmt = $db->prepare("CALL update_user(?,?,?,?,?)");
    $stmt->execute([$username, $email, $isMemberValid, $role, $userId]);

    http_response_code(200); // OK
    echo json_encode([
        "success" => true,
        "message" => "Account updated successfully!"
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
    exit();
}


?>