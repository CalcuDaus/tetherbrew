<?php
require_once __DIR__ . '/../../../core/bootstrap.php';
require_guest();

$errors = validation_errors();
$pageTitle = 'Daftar Customer';

ob_start();
?>
<div class="auth-card">
    <h2 class="auth-title">Daftar Akun</h2>

    <?= render_errors($errors) ?>

    <form method="POST" action="<?= url('/customer/register/process') ?>" class="auth-form">
        <?= csrf_field() ?>
        
        <div class="form-group">
            <label for="name" class="form-label">Nama Lengkap</label>
            <input type="text" id="name" name="name" class="form-input" value="<?= e(old('name')) ?>" required placeholder="Nama Anda">
        </div>

        <div class="form-group">
            <label for="email" class="form-label">Email</label>
            <input type="email" id="email" name="email" class="form-input" value="<?= e(old('email')) ?>" required placeholder="email@contoh.com">
        </div>

        <div class="form-group">
            <label for="phone" class="form-label">Nomor HP</label>
            <input type="tel" id="phone" name="phone" class="form-input" value="<?= e(old('phone')) ?>" required placeholder="08xxxxxxxxxx">
        </div>

        <div class="form-group">
            <label for="password" class="form-label">Password</label>
            <input type="password" id="password" name="password" class="form-input" required placeholder="Minimal 6 karakter">
        </div>

        <div class="form-group">
            <label for="password_confirmation" class="form-label">Konfirmasi Password</label>
            <input type="password" id="password_confirmation" name="password_confirmation" class="form-input" required placeholder="Ulangi password">
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; margin-top: 8px;">Daftar</button>

        <p style="text-align: center; margin-top: 16px; font-size: 14px; color: var(--text-muted);">
            Sudah punya akun? <a href="<?= url('/customer/login') ?>" style="color: var(--accent-green);">Login</a>
        </p>
    </form>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../../layouts/guest.php';
