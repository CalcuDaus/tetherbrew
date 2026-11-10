<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_auth();
require_role('rider');

$cartStmt = $pdo->prepare("SELECT id FROM carts WHERE user_id = ?");
$cartStmt->execute([auth_id()]);
$cart = $cartStmt->fetch();

$transactions = [];
$itemsByTx = [];
$pagination = null;

if ($cart) {
    $pagination = paginate($pdo, "SELECT * FROM transactions WHERE cart_id = ? ORDER BY id DESC", [$cart['id']], 15);
    $transactions = $pagination['data'];

    if (!empty($transactions)) {
        $txIds = array_map(fn($t) => (int)$t['id'], $transactions);
        $placeholders = implode(',', array_fill(0, count($txIds), '?'));
        $itemsStmt = $pdo->prepare("
            SELECT ti.transaction_id, ti.qty, p.name
            FROM transaction_items ti
            INNER JOIN products p ON ti.product_id = p.id
            WHERE ti.transaction_id IN ({$placeholders})
        ");
        $itemsStmt->execute($txIds);
        foreach ($itemsStmt->fetchAll() as $row) {
            $itemsByTx[(int)$row['transaction_id']][] = e($row['name']) . ' (' . (int)$row['qty'] . ')';
        }
    }
}

$pageTitle = 'Riwayat Transaksi Saya';
ob_start();
?>
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
    <h3 style="margin: 0; font-size: 1.15rem; font-weight: 700; color: var(--text-primary);">Riwayat Transaksi Saya</h3>
    <a href="<?= url('/rider/pos') ?>" class="btn btn-primary btn-sm" style="height: 36px; display: inline-flex; align-items: center; gap: 6px; padding: 0 14px; border-radius: 8px; text-decoration: none;">
        ← Kembali ke POS
    </a>
</div>

<div class="card">
    <div class="card-body" style="padding:0;">
        <?php if (empty($transactions)): ?>
            <div style="text-align:center; padding:48px 20px; color:var(--text-muted);">
                <h3>Belum ada transaksi</h3>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Waktu</th>
                            <th>Total</th>
                            <th>Pembayaran</th>
                            <th>Detail Item</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $t):
                            $tid = (int)$t['id'];
                            $itemSummary = implode(', ', $itemsByTx[$tid] ?? []);
                        ?>
                            <tr>
                                <td style="font-weight:700;">#<?= $tid ?></td>
                                <td style="font-size:12px; white-space:nowrap;"><?= date('d M Y H:i', strtotime($t['created_at'])) ?></td>
                                <td style="font-weight:700; color:var(--accent-green); white-space:nowrap;">
                                    <?= formatRupiah($t['total_price']) ?>
                                    <?php if ($t['payment_method'] === 'cash' && $t['change_amount'] !== null && (float)$t['change_amount'] > 0): ?>
                                        <div style="font-size:11px; font-weight:500; color:var(--text-muted);">Kembali: <?= formatRupiah($t['change_amount']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?= $t['payment_method'] === 'cash' ? 'success' : 'info' ?>">
                                        <?= strtoupper(e($t['payment_method'])) ?>
                                    </span>
                                </td>
                                <td style="font-size:13px; color:var(--text-secondary);"><?= $itemSummary ?: '-' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div style="padding:16px;"><?= render_pagination($pagination) ?></div>
        <?php endif; ?>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';
