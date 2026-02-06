<?php
require_once 'config.php';

$token = getBearerToken();

if (!$token) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'តម្រូវឲ្យចូលគណនី']);
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Create new order
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        $data = $_POST;
    }
    
    $items = $data['items'] ?? [];
    $total = $data['total'] ?? 0;
    $shippingAddress = $data['shippingAddress'] ?? '';
    $paymentMethod = $data['paymentMethod'] ?? 'cash';
    
    if (empty($items) || $total <= 0) {
        echo json_encode(['success' => false, 'message' => 'ទិន្នន័យការបញ្ជាទិញមិនត្រឹមត្រូវ']);
        exit();
    }
    
    // Generate order number
    $orderNumber = 'KROMA-' . date('Ymd') . '-' . strtoupper(uniqid());
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Insert order
        $stmt = $conn->prepare("INSERT INTO orders (user_id, order_number, total_amount, shipping_address, payment_method) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $userId, $orderNumber, $total, $shippingAddress, $paymentMethod);
        
        if (!$stmt->execute()) {
            throw new Exception('មិនអាចបង្កើតការបញ្ជាទិញបានទេ');
        }
        
        $orderId = $stmt->insert_id;
        $stmt->close();
        
        // Insert order items
        $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_name, product_price, quantity, subtotal) VALUES (?, ?, ?, ?, ?)");
        
        foreach ($items as $item) {
            $subtotal = $item['price'] * $item['quantity'];
            $stmt->bind_param("isdid", $orderId, $item['name'], $item['price'], $item['quantity'], $subtotal);
            $stmt->execute();
        }
        
        $stmt->close();
        
        // Commit transaction
        $conn->commit();
        
        // Get complete order
        $stmt = $conn->prepare("
            SELECT o.*, 
                   GROUP_CONCAT(
                       CONCAT(oi.product_name, '|', oi.product_price, '|', oi.quantity, '|', oi.subtotal)
                       ORDER BY oi.id SEPARATOR ';;'
                   ) as items_string
            FROM orders o
            LEFT JOIN order_items oi ON o.id = oi.order_id
            WHERE o.id = ?
            GROUP BY o.id
        ");
        $stmt->bind_param("i", $orderId);
        $stmt->execute();
        $result = $stmt->get_result();
        $order = $result->fetch_assoc();
        
        // Parse items
        $itemsArray = [];
        if (!empty($order['items_string'])) {
            $itemsList = explode(';;', $order['items_string']);
            foreach ($itemsList as $itemStr) {
                list($name, $price, $quantity, $subtotal) = explode('|', $itemStr);
                $itemsArray[] = [
                    'name' => $name,
                    'price' => (float)$price,
                    'quantity' => (int)$quantity,
                    'subtotal' => (float)$subtotal
                ];
            }
        }
        
        unset($order['items_string']);
        $order['items'] = $itemsArray;
        
        // Send to Telegram (your existing function)
        // You can call your Telegram sending function here
        
        echo json_encode([
            'success' => true,
            'message' => 'ការបញ្ជាទិញជោគជ័យ',
            'order' => $order
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get user orders
    $stmt = $conn->prepare("
        SELECT o.*, 
               GROUP_CONCAT(
                   CONCAT(oi.product_name, '|', oi.product_price, '|', oi.quantity, '|', oi.subtotal)
                   ORDER BY oi.id SEPARATOR ';;'
               ) as items_string
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE o.user_id = ?
        GROUP BY o.id
        ORDER BY o.created_at DESC
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $orders = [];
    while ($row = $result->fetch_assoc()) {
        // Parse items
        $itemsArray = [];
        if (!empty($row['items_string'])) {
            $itemsList = explode(';;', $row['items_string']);
            foreach ($itemsList as $itemStr) {
                list($name, $price, $quantity, $subtotal) = explode('|', $itemStr);
                $itemsArray[] = [
                    'name' => $name,
                    'price' => (float)$price,
                    'quantity' => (int)$quantity,
                    'subtotal' => (float)$subtotal
                ];
            }
        }
        
        unset($row['items_string']);
        $row['items'] = $itemsArray;
        $orders[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'orders' => $orders
    ]);
    
    $stmt->close();
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'មិនអនុញ្ញាតិ method នេះទេ']);
}

$conn->close();
?>