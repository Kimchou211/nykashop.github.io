<?php
// debug.php - Check database connection and data
header('Content-Type: application/json; charset=utf-8');

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'kroma_tech');

// Test connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

$response = [
    'timestamp' => date('Y-m-d H:i:s'),
    'database' => [
        'host' => DB_HOST,
        'user' => DB_USER,
        'database' => DB_NAME,
        'connected' => !$conn->connect_error
    ]
];

if ($conn->connect_error) {
    $response['database']['error'] = $conn->connect_error;
} else {
    // Check if users table exists
    $result = $conn->query("SHOW TABLES LIKE 'users'");
    $response['tables'] = [
        'users_exists' => $result->num_rows > 0
    ];
    
    // Get all tables
    $tables = $conn->query("SHOW TABLES");
    $tableList = [];
    while ($row = $tables->fetch_array()) {
        $tableList[] = $row[0];
    }
    $response['tables']['all_tables'] = $tableList;
    
    // Get users count
    if ($response['tables']['users_exists']) {
        $usersCount = $conn->query("SELECT COUNT(*) as count FROM users");
        $count = $usersCount->fetch_assoc();
        $response['users'] = [
            'count' => $count['count']
        ];
        
        // Get first 10 users
        $users = $conn->query("SELECT id, name, email, created_at FROM users LIMIT 10");
        $userList = [];
        while ($user = $users->fetch_assoc()) {
            $userList[] = $user;
        }
        $response['users']['list'] = $userList;
    }
    
    $conn->close();
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>