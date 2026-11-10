<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_admin_or_owner();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/artikel/admin'); csrf_verify();
$id = $_POST['id'];

$stmt = $pdo->prepare("SELECT cover_image FROM artikels WHERE id=?"); $stmt->execute([$id]); $a = $stmt->fetch();
if($a && $a['cover_image'] && file_exists(__DIR__ . '/../../public' . $a['cover_image'])) {
    unlink(__DIR__ . '/../../public' . $a['cover_image']);
}
$pdo->prepare("DELETE FROM artikels WHERE id=?")->execute([$id]);
redirect_with('/artikel/admin', 'success', 'Artikel berhasil dihapus.');
