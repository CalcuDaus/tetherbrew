<?php
require_once __DIR__ . '/../../../core/bootstrap.php'; require_auth();
header('Content-Type: application/json');

$role = auth_role();
$userId = auth_id();

if($role === 'rider') {
    $stmt = $pdo->prepare("SELECT c.*, u.name as customer_name, cart.name as cart_name,
        (SELECT COUNT(*) FROM messages m WHERE m.conversation_id=c.id AND m.sender_id!=? AND m.is_read=0) as unread_count,
        (SELECT body FROM messages m WHERE m.conversation_id=c.id ORDER BY created_at DESC LIMIT 1) as last_message
        FROM conversations c 
        JOIN users u ON c.customer_id = u.id 
        LEFT JOIN carts cart ON c.cart_id = cart.id 
        WHERE c.rider_id=? ORDER BY c.last_message_at DESC");
    $stmt->execute([$userId, $userId]);
    $convs = $stmt->fetchAll();
} elseif($role === 'customer') {
    $stmt = $pdo->prepare("SELECT c.*, u.name as rider_name, cart.name as cart_name,
        (SELECT COUNT(*) FROM messages m WHERE m.conversation_id=c.id AND m.sender_id!=? AND m.is_read=0) as unread_count,
        (SELECT body FROM messages m WHERE m.conversation_id=c.id ORDER BY created_at DESC LIMIT 1) as last_message
        FROM conversations c 
        JOIN users u ON c.rider_id = u.id 
        LEFT JOIN carts cart ON c.cart_id = cart.id 
        WHERE c.customer_id=? ORDER BY c.last_message_at DESC");
    $stmt->execute([$userId, $userId]);
    $convs = $stmt->fetchAll();
} else {
    // Admin/Owner - see branch conversations or all conversations
    $branchId = activeBranchId();
    if ($branchId && $role === 'admin') {
        $stmt = $pdo->prepare("SELECT c.*, cust.name as customer_name, rid.name as rider_name, cart.name as cart_name,
            (SELECT COUNT(*) FROM messages m WHERE m.conversation_id=c.id AND m.is_read=0) as unread_count,
            (SELECT body FROM messages m WHERE m.conversation_id=c.id ORDER BY created_at DESC LIMIT 1) as last_message
            FROM conversations c 
            JOIN users cust ON c.customer_id = cust.id 
            JOIN users rid ON c.rider_id = rid.id
            LEFT JOIN carts cart ON c.cart_id = cart.id 
            WHERE rid.branch_id=? ORDER BY c.last_message_at DESC");
        $stmt->execute([$branchId]);
    } else {
        $stmt = $pdo->query("SELECT c.*, cust.name as customer_name, rid.name as rider_name, cart.name as cart_name,
            (SELECT COUNT(*) FROM messages m WHERE m.conversation_id=c.id AND m.is_read=0) as unread_count,
            (SELECT body FROM messages m WHERE m.conversation_id=c.id ORDER BY created_at DESC LIMIT 1) as last_message
            FROM conversations c 
            JOIN users cust ON c.customer_id = cust.id 
            JOIN users rid ON c.rider_id = rid.id
            LEFT JOIN carts cart ON c.cart_id = cart.id 
            ORDER BY c.last_message_at DESC");
    }
    $convs = $stmt->fetchAll();
}

echo json_encode(['conversations' => $convs]);
