<?php
require_once __DIR__ . '/../../../core/bootstrap.php'; require_auth();
csrf_verify();
header('Content-Type: application/json');

$convId = $_POST['conversation_id'] ?? null;
$body = trim($_POST['body'] ?? '');
$attachmentPath = null; $attachmentType = null;

if(!$convId) { echo json_encode(['error' => 'conversation_id required']); exit; }

$stmt = $pdo->prepare("SELECT * FROM conversations WHERE id=?"); $stmt->execute([$convId]); $conv = $stmt->fetch();
if(!$conv) { echo json_encode(['error' => 'conversation not found']); exit; }

if($conv['customer_id'] != auth_id() && $conv['rider_id'] != auth_id() && !in_array(auth_user()['role'] ?? '', ['admin','owner'])) {
    echo json_encode(['error' => 'unauthorized']); exit;
}

if(isset($_FILES['attachment']) && $_FILES['attachment']['error'] == UPLOAD_ERR_OK) {
    $file = $_FILES['attachment'];
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if(in_array($mime, $allowedMimes) && $file['size'] <= 5242880) {
        $attachmentType = ($mime === 'application/pdf') ? 'pdf' : 'image';
        $filename = uniqid() . '_' . time() . '.' . $ext;
        $uploadDir = __DIR__ . '/../../../uploads/chat-attachments/';
        if(!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        if(move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
            $attachmentPath = '/uploads/chat-attachments/' . $filename;
        }
    }
}

if(!$body && !$attachmentPath) { echo json_encode(['error' => 'body or attachment required']); exit; }

$pdo->prepare("INSERT INTO messages (conversation_id, sender_id, body, attachment_path, attachment_type, is_read, created_at, updated_at) VALUES (?,?,?,?,?,0,NOW(),NOW())")
    ->execute([$convId, auth_id(), $body, $attachmentPath, $attachmentType]);
$msgId = $pdo->lastInsertId();

$pdo->prepare("UPDATE conversations SET last_message_at=NOW(), updated_at=NOW() WHERE id=?")->execute([$convId]);

$msgStmt = $pdo->prepare("SELECT m.*, u.name as sender_name, u.role as sender_role FROM messages m JOIN users u ON m.sender_id=u.id WHERE m.id=?");
$msgStmt->execute([$msgId]); $message = $msgStmt->fetch();

echo json_encode(['message' => $message]);
