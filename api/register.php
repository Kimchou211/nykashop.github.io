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

$name = $data['name'] ?? '';
$email = $data['email'] ?? '';
$password = $data['password'] ?? '';
$phone = $data['phone'] ?? '';
$address = $data['address'] ?? '';

// Validation
if (empty($name) || empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'សូមបំពេញព័ត៌មានចាំបាច់ទាំងអស់']);
    exit();
}

if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'message' => 'លេខសំងាត់ត្រូវតែយ៉ាងតិច ៦ តួអក្សរ']);
    exit();
}

$conn = getDBConnection();

// Check if email exists
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'អ៊ីមែលនេះមានរួចហើយ']);
    exit();
}
$stmt->close();

// Hash password
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// Insert user
$stmt = $conn->prepare("INSERT INTO users (name, email, password, phone, address) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("sssss", $name, $email, $hashedPassword, $phone, $address);

if ($stmt->execute()) {
    $userId = $stmt->insert_id;
    
    // Get user data
    $stmt2 = $conn->prepare("SELECT id, name, email, phone, address, created_at FROM users WHERE id = ?");
    $stmt2->bind_param("i", $userId);
    $stmt2->execute();
    $result = $stmt2->get_result();
    $user = $result->fetch_assoc();
    
    // Generate token
    $token = generateToken($userId, $email);
    
    echo json_encode([
        'success' => true,
        'message' => 'ចុះឈ្មោះជោគជ័យ',
        'user' => $user,
        'token' => $token
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'មានបញ្ហាក្នុងការចុះឈ្មោះ']);
}

$stmt->close();
$conn->close();
?>