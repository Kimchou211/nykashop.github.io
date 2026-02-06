<?php
require_once 'config.php';

echo json_encode([
    'success' => true,
    'message' => '✅ Server is running!',
    'timestamp' => date('Y-m-d H:i:s'),
    'data' => [
        'server' => 'KROMA Tech API',
        'version' => '1.0',
        'status' => 'online'
    ]
]);
?>