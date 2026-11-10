<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_auth(); require_admin_or_owner();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/products');
csrf_verify();
try {
    $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$_POST['id']]);
    clear_carts_map_cache();
    redirect_with('/products', 'success', 'Produk berhasil dihapus!');
} catch (PDOException $e) {
    if ($e->getCode() == 23000) redirect_with('/products', 'error', 'Produk tidak dapat dihapus karena sudah memiliki riwayat transaksi. Nonaktifkan saja produk ini.');
    throw $e;
}
