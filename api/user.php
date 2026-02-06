<?php
require_once 'config.php';

$token = getBearerToken();

if (!$token) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'អត់មាន token']);
    exit();
}

$payload = verifyToken($token);

if (!$payload) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token មិនត្រឹមត្រូវ']);
    exit();
}

$userId = $payload['user_id'];

$conn = getDBConnection();

$stmt = $conn->prepare("SELECT id, name, email, phone, address, created_at FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'អ្នកប្រើប្រាស់មិនត្រូវបានរកឃើញ']);
    exit();
}

$user = $result->fetch_assoc();

echo json_encode([
    'success' => true,
    'user' => $user
]);

$stmt->close();
$conn->close();
?>