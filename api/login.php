<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'មិនអនុញ្ញាតិ method នេះទេ']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    $data = $_POST;
}

$email = $data['email'] ?? '';
$password = $data['password'] ?? '';

// Validation
if (empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'សូមបំពេញអ៊ីមែលនិងលេខសំងាត់']);
    exit();
}

$conn = getDBConnection();

// Find user
$stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'អ៊ីមែលឬលេខសំងាត់មិនត្រឹមត្រូវ']);
    exit();
}

$user = $result->fetch_assoc();

// Verify password
if (!password_verify($password, $user['password'])) {
    echo json_encode(['success' => false, 'message' => 'អ៊ីមែលឬលេខសំងាត់មិនត្រឹមត្រូវ']);
    exit();
}

// Remove password from response
unset($user['password']);

// Generate token
$token = generateToken($user['id'], $user['email']);

echo json_encode([
    'success' => true,
    'message' => 'ចូលគណនីជោគជ័យ',
    'user' => $user,
    'token' => $token
]);

$stmt->close();
$conn->close();
?>