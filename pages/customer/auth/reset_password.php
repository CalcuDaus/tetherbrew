<?php
require_once __DIR__ . '/../../../core/bootstrap.php';
require_guest();

$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? '';

if (empty($token) || empty($email)) {
    redirect_with('/customer/forgot-password', 'error', 'Link reset password tidak valid atau tidak lengkap.');
}

// Validasi Token
$stmt = $pdo->prepare("SELECT created_at FROM password_reset_tokens WHERE email = ? AND token = ?");
$stmt->execute([$email, $token]);
$tokenData = $stmt->fetch();

if (!$tokenData) {
    redirect_with('/customer/forgot-password', 'error', 'Token reset password tidak valid atau sudah digunakan.');
}

// Cek kadaluarsa (misal 1 jam / 3600 detik)
$createdAt = strtotime($tokenData['created_at']);
if (time() - $createdAt > 3600) {
    // Hapus token kadaluarsa
    $pdo->prepare("DELETE FROM password_reset_tokens WHERE email = ?")->execute([$email]);
    redirect_with('/customer/forgot-password', 'error', 'Link reset password sudah kadaluarsa. Silakan request kembali.');
}

$errors = validation_errors();
$pageTitle = 'Buat Password Baru';

ob_start();
?>
<div class="auth-card">
    <h2 class="auth-title">Password Baru</h2>
    <p class="auth-subtitle">Masukkan password baru untuk akun Anda</p>

    <?= render_errors($errors) ?>

    <form method="POST" action="<?= url('/customer/reset-password/process') ?>" class="auth-form">
        <?= csrf_field() ?>
        
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <input type="hidden" name="email" value="<?= e($email) ?>">

        <div class="form-group">
            <label for="password" class="form-label">Password Baru</label>
            <input type="password" id="password" name="password" class="form-input" required autofocus placeholder="Minimal 6 karakter">
        </div>

        <div class="form-group">
            <label for="password_confirmation" class="form-label">Konfirmasi Password Baru</label>
            <input type="password" id="password_confirmation" name="password_confirmation" class="form-input" required placeholder="Ulangi password baru">
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; margin-top: 8px;">Simpan Password Baru</button>
    </form>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../../layouts/guest.php';
