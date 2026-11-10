<?php
require_once __DIR__ . '/../../../core/bootstrap.php'; require_auth();
csrf_verify();
header('Content-Type: application/json');

$riderId = $_POST['rider_id'] ?? null;
$cartId = $_POST['cart_id'] ?? null;

if(!$riderId) { echo json_encode(['error' => 'rider_id required']); exit; }

// Check existing conversation
$stmt = $pdo->prepare("SELECT * FROM conversations WHERE customer_id=? AND rider_id=? AND (cart_id=? OR cart_id IS NULL) LIMIT 1");
$stmt->execute([auth_id(), $riderId, $cartId]);
$conv = $stmt->fetch();

if(!$conv) {
    $ins = $pdo->prepare("INSERT INTO conversations (customer_id, rider_id, cart_id, status, created_at, updated_at) VALUES (?,?,?, 'active', NOW(), NOW())");
    $ins->execute([auth_id(), $riderId, $cartId]);
    $convId = $pdo->lastInsertId();
    $stmt = $pdo->prepare("SELECT * FROM conversations WHERE id=?"); $stmt->execute([$convId]); $conv = $stmt->fetch();
}

echo json_encode(['conversation' => $conv]);
