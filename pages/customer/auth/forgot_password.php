<?php
require_once __DIR__ . '/../../../core/bootstrap.php';
require_guest();

$errors = validation_errors();
$pageTitle = 'Lupa Password Customer';

ob_start();
?>
<div class="auth-card">
    <h2 class="auth-title">Lupa Password</h2>
    <p class="auth-subtitle">Masukkan email Anda untuk menerima link reset password</p>

    <?= render_errors($errors) ?>

    <form method="POST" action="<?= url('/customer/forgot-password/process') ?>" class="auth-form">
        <?= csrf_field() ?>
        
        <div class="form-group">
            <label for="email" class="form-label">Alamat Email</label>
            <input type="email" id="email" name="email" class="form-input" required autofocus placeholder="email@contoh.com">
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; margin-top: 8px;">Kirim Link Reset</button>

        <p style="text-align: center; margin-top: 16px; font-size: 14px; color: var(--text-muted);">
            Ingat password Anda? <a href="<?= url('/customer/login') ?>" style="color: var(--accent-green);">Kembali ke Login</a>
        </p>
    </form>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../../layouts/guest.php';
