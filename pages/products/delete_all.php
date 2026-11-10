<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_auth(); 
require_admin_or_owner();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/products');
}

csrf_verify();

try {
    $pdo->prepare("DELETE FROM products")->execute();
    redirect_with('/products', 'success', 'Semua produk berhasil dihapus!');
} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        redirect_with('/products', 'error', 'Gagal menghapus semua produk karena masih ada riwayat transaksi yang terhubung. Hapus transaksi terkait terlebih dahulu.');
    }
    redirect_with('/products', 'error', 'Gagal menghapus data: ' . $e->getMessage());
}
