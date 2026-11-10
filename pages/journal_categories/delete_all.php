<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_auth(); 
require_admin_or_owner();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/journal-categories');
}

csrf_verify();

try {
    $pdo->prepare("DELETE FROM journal_categories")->execute();
    redirect_with('/admin/journal-categories', 'success', 'Semua kategori jurnal berhasil dihapus!');
} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        redirect_with('/admin/journal-categories', 'error', 'Gagal menghapus kategori karena masih ada data jurnal yang menggunakannya.');
    }
    redirect_with('/admin/journal-categories', 'error', 'Gagal menghapus data: ' . $e->getMessage());
}
