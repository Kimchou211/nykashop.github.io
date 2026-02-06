<?php
// config.php - WITH DEBUGGING
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database configuration
define('DB_HOST', 'sql206.infinityfree.com');
define('DB_USER', 'if0_39807874');
define('DB_PASS', 'wjNzYr3dcqk8'); // កែជាលេខសំងាត់ MySQL របស់អ្នក
define('DB_NAME', 'if0_39807874_kroma_tech');
define('JWT_SECRET', 'kroma_tech_secret_key_2024');

// Create database connection
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        // Return error as JSON instead of die()
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'មិនអាចតភ្ជាប់ទៅទិន្នន័យបានទេ',
            'error' => $conn->connect_error,
            'host' => DB_HOST,
            'user' => DB_USER,
            'db' => DB_NAME
        ]);
        exit();
    }
    
    $conn->set_charset("utf8");
    return $conn;
}

// Generate JWT token (same as before)
function generateToken($userId, $email) {
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $payload = json_encode([
        'user_id' => $userId,
        'email' => $email,
        'exp' => time() + (7 * 24 * 60 * 60) // 7 days
    ]);
    
    $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
    
    $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, JWT_SECRET, true);
    $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    
    return $base64Header . "." . $base64Payload . "." . $base64Signature;
}

// Verify JWT token (same as before)
function verifyToken($token) {
    try {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }
        
        $signature = hash_hmac('sha256', $parts[0] . "." . $parts[1], JWT_SECRET, true);
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        if ($base64Signature !== $parts[2]) {
            return false;
        }
        
        $payload = json_decode(base64_decode($parts[1]), true);
        
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return false;
        }
        
        return $payload;
    } catch (Exception $e) {
        return false;
    }
}

// Get authorization header (same as before)
function getAuthorizationHeader() {
    $headers = null;
    
    if (isset($_SERVER['Authorization'])) {
        $headers = trim($_SERVER['Authorization']);
    } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers = trim($_SERVER['HTTP_AUTHORIZATION']);
    } elseif (function_exists('apache_request_headers')) {
        $requestHeaders = apache_request_headers();
        $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
        
        if (isset($requestHeaders['Authorization'])) {
            $headers = trim($requestHeaders['Authorization']);
        }
    }
    
    return $headers;
}

// Get bearer token (same as before)
function getBearerToken() {
    $headers = getAuthorizationHeader();
    
    if (!empty($headers)) {
        if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
            return $matches[1];
        }
    }
    
    return null;
}
?>