<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_auth(); 
require_admin_or_owner();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/artikel');
}

csrf_verify();

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("DELETE FROM artikels");
    $stmt->execute();
    
    $pdo->commit();
    redirect_with('/admin/artikel', 'success', 'Semua artikel berhasil dihapus!');
} catch (Exception $e) {
    $pdo->rollBack();
    redirect_with('/admin/artikel', 'error', 'Gagal menghapus data: ' . $e->getMessage());
}
