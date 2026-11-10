<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_role('owner');
$pageTitle = 'Pengaturan Aplikasi';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $keys = ['bonus_per_cup', 'uang_makan_target_cup', 'uang_makan_base_amount'];
    try {
        $pdo->beginTransaction();
        foreach($keys as $key) {
            if(isset($_POST[$key])) {
                $val = $_POST[$key];
                $pdo->prepare("INSERT INTO app_settings (`key`, `value`, created_at, updated_at) VALUES (?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE `value` = ?, updated_at = NOW()")->execute([$key, $val, $val]);
            }
        }
        $pdo->commit();
        set_flash('success', 'Pengaturan berhasil diperbarui!');
        redirect('/admin/settings');
    } catch (Exception $e) {
        $pdo->rollBack();
        set_flash('error', 'Gagal memperbarui pengaturan: ' . $e->getMessage());
    }
}

$stmt = $pdo->query("SELECT `key`, `value` FROM app_settings");
$settings = []; while($row = $stmt->fetch()) { $settings[$row['key']] = $row['value']; }

ob_start();
?>
<div class="card" style="max-width:600px; margin:0 auto;"><div class="card-header"><div class="card-title">Pengaturan Gaji & Bonus Rider</div></div><div class="card-body">
    <form method="POST" action="<?= url('/admin/settings') ?>">
        <?= csrf_field() ?>
        <div class="form-group"><label class="form-label">Bonus Per Cup (Rp)</label><input type="number" name="bonus_per_cup" class="form-input" value="<?= e($settings['bonus_per_cup'] ?? 2000) ?>" required></div>
        <div class="form-group"><label class="form-label">Target Penjualan untuk Uang Makan (Cup/Bulan)</label><input type="number" name="uang_makan_target_cup" class="form-input" value="<?= e($settings['uang_makan_target_cup'] ?? 1040) ?>" required></div>
        <div class="form-group"><label class="form-label">Uang Makan Maksimal (Rp)</label><input type="number" name="uang_makan_base_amount" class="form-input" value="<?= e($settings['uang_makan_base_amount'] ?? 650000) ?>" required></div>
        <div style="text-align:right;"><button type="submit" class="btn btn-primary">Simpan Pengaturan</button></div>
    </form>
</div></div>
<?php $content = ob_get_clean(); require __DIR__ . '/../../layouts/app.php';
