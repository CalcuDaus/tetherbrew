<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_role('customer');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    csrf_verify();
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];

    if (!empty($password)) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET name=?, phone=?, password=? WHERE id=?");
        $stmt->execute([$name, $phone, $hashed, auth_id()]);
    } else {
        $stmt = $pdo->prepare("UPDATE users SET name=?, phone=? WHERE id=?");
        $stmt->execute([$name, $phone, auth_id()]);
    }
    
    // Update session so UI reflects immediately
    auth_refresh();
    
    redirect_with('/customer/me', 'success', 'Profil Anda berhasil diperbarui!');
}

$pageTitle = 'Me - Tether Brew';

$stmt = $pdo->prepare("SELECT t.*, c.name as cart_name FROM transactions t LEFT JOIN carts c ON t.cart_id = c.id WHERE t.user_id = ? ORDER BY t.created_at DESC LIMIT 50");
$stmt->execute([auth_id()]); 
$transactions = $stmt->fetchAll();

ob_start();
?>
<style>
    .me-header {
        padding: 30px 20px;
        background: linear-gradient(135deg, #8b5c2a, #6b4420);
        color: white;
        text-align: center;
    }
    .profile-avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: white;
        color: #8b5c2a;
        font-size: 2rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 15px auto;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }
    .profile-name {
        font-size: 1.25rem;
        font-weight: 700;
        margin: 0 0 5px 0;
    }
    .profile-phone {
        font-size: 0.9rem;
        opacity: 0.9;
        margin: 0;
    }

    .section-block {
        padding: 20px;
        background: white;
        margin-top: 10px;
    }
    .section-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: #1e293b;
        margin: 0 0 15px 0;
    }

    .history-card {
        padding: 15px;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        margin-bottom: 10px;
    }
    .history-header {
        display: flex;
        justify-content: space-between;
        margin-bottom: 8px;
    }
    .history-title {
        font-weight: 600;
        color: #1e293b;
    }
    .history-date {
        font-size: 0.8rem;
        color: #64748b;
    }
    .history-body {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .history-price {
        font-weight: 700;
        color: #8b5c2a;
    }
    .history-status {
        font-size: 0.75rem;
        padding: 4px 8px;
        border-radius: 8px;
        background: #dcfce7;
        color: #16a34a;
        font-weight: 600;
    }

    .logout-btn {
        display: block;
        width: 100%;
        padding: 15px;
        background: white;
        color: #ef4444;
        border: none;
        border-top: 1px solid #e2e8f0;
        border-bottom: 1px solid #e2e8f0;
        text-align: center;
        font-weight: 600;
        font-size: 1rem;
        margin-top: 20px;
        cursor: pointer;
    }
</style>

<div class="me-header">
    <div class="profile-avatar">
        <?= strtoupper(substr(auth_user()['name'], 0, 1)) ?>
    </div>
    <h1 class="profile-name"><?= e(auth_user()['name']) ?></h1>
    <p class="profile-phone"><?= e(auth_user()['phone'] ?? auth_user()['email']) ?></p>
</div>

<!-- Sub-menu Pengaturan Akun -->
<div class="section-block" style="padding: 0;">
    <div onclick="document.getElementById('settings-form-wrapper').style.display = document.getElementById('settings-form-wrapper').style.display === 'none' ? 'block' : 'none'" style="padding: 20px; display: flex; justify-content: space-between; align-items: center; cursor: pointer;">
        <h2 class="section-title" style="margin: 0;">⚙️ Pengaturan Akun</h2>
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
    </div>
    
    <div id="settings-form-wrapper" style="display: none; padding: 0 20px 20px 20px; border-top: 1px solid #e2e8f0;">
        <form method="POST" action="<?= url('/customer/me') ?>" style="margin-top: 15px;">
            <?= csrf_field() ?>
            <input type="hidden" name="update_profile" value="1">
            
            <div style="margin-bottom:15px;">
                <label style="display:block; margin-bottom:5px; font-size:0.9rem; color:#64748b; font-weight:500;">Nama Lengkap</label>
                <input type="text" name="name" value="<?= e(auth_user()['name']) ?>" required style="width:100%; padding:10px 15px; border:1px solid #cbd5e1; border-radius:10px; outline:none; box-sizing:border-box; font-family:inherit;">
            </div>
            
            <div style="margin-bottom:15px;">
                <label style="display:block; margin-bottom:5px; font-size:0.9rem; color:#64748b; font-weight:500;">No. Telepon / WhatsApp</label>
                <input type="text" name="phone" value="<?= e(auth_user()['phone'] ?? '') ?>" required style="width:100%; padding:10px 15px; border:1px solid #cbd5e1; border-radius:10px; outline:none; box-sizing:border-box; font-family:inherit;">
            </div>
            
            <div style="margin-bottom:20px;">
                <label style="display:block; margin-bottom:5px; font-size:0.9rem; color:#64748b; font-weight:500;">Kata Sandi Baru</label>
                <input type="password" name="password" placeholder="Kosongkan jika tidak ingin diubah" style="width:100%; padding:10px 15px; border:1px solid #cbd5e1; border-radius:10px; outline:none; box-sizing:border-box; font-family:inherit;">
            </div>
            
            <button type="submit" style="width:100%; padding:12px; background:#8b5c2a; color:white; border:none; border-radius:10px; font-weight:600; cursor:pointer; font-family:inherit;">
                Simpan Perubahan
            </button>
        </form>
    </div>
</div>

<div class="section-block">
    <h2 class="section-title">Riwayat Pemesanan</h2>
    <?php if(empty($transactions)): ?>
        <div style="text-align: center; color: #64748b; padding: 20px;">
            Belum ada riwayat pemesanan.
        </div>
    <?php else: ?>
        <?php foreach($transactions as $transaction): ?>
            <div class="history-card">
                <div class="history-header">
                    <span class="history-title"><?= e($transaction['cart_name'] ?? 'Gerobak') ?></span>
                    <span class="history-date"><?= date('d M Y, H:i', strtotime($transaction['created_at'])) ?> WIB</span>
                </div>
                <div class="history-body">
                    <span class="history-price"><?= formatRupiah($transaction['total_price']) ?></span>
                    <span class="history-status">Selesai</span>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<form method="POST" action="<?= url('/logout') ?>">
    <?= csrf_field() ?>
    <button type="submit" class="logout-btn">
        Keluar (Logout)
    </button>
</form>

<?php 
$content = ob_get_clean(); 
require __DIR__ . '/../../layouts/customer.php';
?>
