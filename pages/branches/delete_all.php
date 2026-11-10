<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_auth(); 
require_owner();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/branches');
}

csrf_verify();

try {
    $pdo->prepare("DELETE FROM branches")->execute();
    
    // Clear branch session if no branches left
    unset($_SESSION['active_branch_id']);
    
    redirect_with('/admin/branches', 'success', 'Semua cabang berhasil dihapus!');
} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        redirect_with('/admin/branches', 'error', 'Gagal menghapus cabang karena masih ada data yang tertaut pada cabang tersebut.');
    }
    redirect_with('/admin/branches', 'error', 'Gagal menghapus data: ' . $e->getMessage());
}
