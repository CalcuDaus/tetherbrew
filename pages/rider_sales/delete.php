<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_bar_admin_owner();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/rider-sales'); csrf_verify();
$id = $_POST['id']; $branchId = activeBranchId();
$user = auth_user();

$check = $pdo->prepare("SELECT * FROM rider_daily_sales WHERE id = ? AND branch_id = ?"); $check->execute([$id, $branchId]); $sale = $check->fetch();
if(!$sale) redirect_with('/rider-sales', 'error', 'Data penjualan tidak ditemukan.');

// 1. Batas waktu maksimal 2 hari
$createdAt = new DateTime($sale['created_at']);
$now = new DateTime();
$diffDays = $now->diff($createdAt)->days;
if ($diffDays > 2) {
    redirect_with('/rider-sales', 'error', 'Penjualan yang sudah lebih dari 2 hari sejak diinput tidak dapat dihapus.');
}

// 2. Cek apakah Jurnal Umum hari tersebut sudah terkonfirmasi
$confCheck = $pdo->prepare("SELECT id FROM rider_sales_journal_confirmations WHERE branch_id=? AND date=?"); 
$confCheck->execute([$branchId, $sale['date']]);
if($confCheck->fetch()) {
    redirect_with('/rider-sales', 'error', 'Penghapusan ditolak karena data ini sudah terekap ke Jurnal Umum. Silakan minta Admin untuk membatalkan (rollback) konfirmasi Jurnal Umum di tanggal tersebut terlebih dahulu.');
}

// 3. Cek apakah minus sudah terbayar
if (($sale['minus_paid'] ?? 0) > 0) {
    redirect_with('/rider-sales', 'error', 'Penghapusan ditolak karena sisa minus sudah terpotong di Slip Gaji. Silakan minta Admin membatalkan Slip Gaji terkait terlebih dahulu.');
}

// 4. Role validation untuk Bar
if ($user['role'] === 'bar') {
    // Check if data is verified by admin/owner
    if ($sale['admin_id']) {
        $adminCheck = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $adminCheck->execute([$sale['admin_id']]);
        $admin = $adminCheck->fetch();
        if ($admin && in_array($admin['role'], ['admin', 'owner'])) {
            redirect_with('/rider-sales', 'error', 'Data penjualan ini sudah diverifikasi oleh Admin. Hanya Admin yang dapat memanipulasi data yang sudah diverifikasi.');
        }
    }
}

// Lolos validasi, hapus data
try {
    $pdo->beginTransaction();
    $pdo->prepare("DELETE FROM rider_daily_sale_items WHERE rider_daily_sale_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM rider_daily_sales WHERE id = ?")->execute([$id]);
    $pdo->commit();
    redirect_with('/rider-sales', 'success', 'Data penjualan berhasil dihapus!');
} catch (Exception $e) {
    $pdo->rollBack(); redirect_with('/rider-sales', 'error', 'Gagal: '.$e->getMessage());
}
