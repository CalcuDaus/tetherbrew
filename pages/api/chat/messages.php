<?php
require_once __DIR__ . '/../../../core/bootstrap.php'; require_auth();
header('Content-Type: application/json');

$convId = $_GET['conversation_id'] ?? null;
if(!$convId) { echo json_encode(['error' => 'conversation_id required']); exit; }

$stmt = $pdo->prepare("SELECT * FROM conversations WHERE id=?"); $stmt->execute([$convId]); $conv = $stmt->fetch();
if(!$conv) { echo json_encode(['error' => 'conversation not found']); exit; }

if($conv['customer_id'] != auth_id() && $conv['rider_id'] != auth_id() && !in_array(auth_role(), ['admin','owner'])) {
    echo json_encode(['error' => 'unauthorized']); exit;
}

$page = $_GET['page'] ?? 1;
$limit = 50;
$offset = ($page - 1) * $limit;

$msgStmt = $pdo->prepare("SELECT m.*, u.name as sender_name, u.role as sender_role FROM messages m JOIN users u ON m.sender_id=u.id WHERE m.conversation_id=? ORDER BY m.created_at DESC LIMIT ? OFFSET ?");
$msgStmt->bindValue(1, $convId, PDO::PARAM_INT);
$msgStmt->bindValue(2, $limit, PDO::PARAM_INT);
$msgStmt->bindValue(3, $offset, PDO::PARAM_INT);
$msgStmt->execute();
$messages = $msgStmt->fetchAll();

echo json_encode(['data' => $messages]);
