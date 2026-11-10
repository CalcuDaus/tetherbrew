<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_role('customer');

$stmt = $pdo->prepare("SELECT t.*, c.name as cart_name FROM transactions t LEFT JOIN carts c ON t.cart_id = c.id WHERE t.user_id = ? ORDER BY t.created_at DESC LIMIT 50");
$stmt->execute([auth_id()]); $transactions = $stmt->fetchAll();

$pageTitle = 'Riwayat Transaksi';
ob_start();
?>
<div class="card" style="max-width:800px; margin:0 auto;"><div class="card-header"><div class="card-title">Riwayat Belanja Saya</div></div><div class="card-body" style="padding:0;">
    <?php if(empty($transactions)): ?>
        <div style="text-align:center; padding:40px; color:var(--text-muted);">Belum ada riwayat belanja.</div>
    <?php else: ?>
        <div class="table-container"><table><thead><tr><th>Waktu</th><th>Lokasi (Cart)</th><th>Total Transaksi</th><th>Pembayaran</th></tr></thead><tbody>
        <?php foreach($transactions as $t): ?>
            <tr>
                <td style="font-size:13px;"><?= date('d M Y, H:i', strtotime($t['created_at'])) ?></td>
                <td><span class="badge badge-info"><?= e($t['cart_name'] ?? 'Pusat') ?></span></td>
                <td style="font-weight:bold; color:var(--accent-green);"><?= formatRupiah($t['total_amount']) ?></td>
                <td><?= strtoupper($t['payment_method']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</div></div>
<?php $content = ob_get_clean(); require __DIR__ . '/../../layouts/app.php';
