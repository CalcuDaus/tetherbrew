<?php
require_once __DIR__ . '/../../../core/bootstrap.php'; require_auth(); require_role('rider');
csrf_verify();
header('Content-Type: application/json');

$convId = $_POST['conversation_id'] ?? null;
if(!$convId) { echo json_encode(['error' => 'conversation_id required']); exit; }

$stmt = $pdo->prepare("SELECT * FROM conversations WHERE id=?"); $stmt->execute([$convId]); $conv = $stmt->fetch();
if(!$conv || $conv['rider_id'] != auth_id()) { echo json_encode(['error' => 'unauthorized']); exit; }

// Use a static QRIS image path or settings
$qrisPath = '/assets/images/qris.jpg'; // Assuming an asset

$pdo->prepare("INSERT INTO messages (conversation_id, sender_id, body, attachment_path, attachment_type, is_read, created_at, updated_at) VALUES (?,?,'QRIS Pembayaran Tether Brew',?,'image',0,NOW(),NOW())")
    ->execute([$convId, auth_id(), $qrisPath]);
$msgId = $pdo->lastInsertId();

$pdo->prepare("UPDATE conversations SET last_message_at=NOW(), updated_at=NOW() WHERE id=?")->execute([$convId]);

$msgStmt = $pdo->prepare("SELECT m.*, u.name as sender_name, u.role as sender_role FROM messages m JOIN users u ON m.sender_id=u.id WHERE m.id=?");
$msgStmt->execute([$msgId]); $message = $msgStmt->fetch();

echo json_encode(['message' => $message]);
