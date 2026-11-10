<?php
require_once __DIR__ . '/../../../core/bootstrap.php';
require_auth();
csrf_verify();
header('Content-Type: application/json');

$convId = $_POST['conversation_id'] ?? null;
if (!$convId) { echo json_encode(['error' => 'conversation_id required']); exit; }

$stmt = $pdo->prepare("SELECT customer_id, rider_id FROM conversations WHERE id = ?");
$stmt->execute([$convId]);
$conv = $stmt->fetch();
$role = auth_user()['role'] ?? '';
if (!$conv || ($conv['customer_id'] != auth_id() && $conv['rider_id'] != auth_id() && !in_array($role, ['admin', 'owner']))) {
    http_response_code(403);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$pdo->prepare("UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND sender_id != ? AND is_read = 0")
    ->execute([$convId, auth_id()]);

echo json_encode(['success' => true]);
