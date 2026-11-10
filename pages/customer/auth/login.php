<?php
require_once __DIR__ . '/../../../core/bootstrap.php';
require_guest();

$errors = validation_errors();
$pageTitle = 'Login Customer';

// Check if currently rate limited
$rateLimitKey = rate_limit_key('customer_login');
$isLocked = rate_limit_exceeded($rateLimitKey, 5, 300);
$lockSeconds = rate_limit_remaining_seconds($rateLimitKey);

// Also check from session (set by login_process)
if (isset($_SESSION['rate_limit_locked_until'])) {
    $sessionLock = $_SESSION['rate_limit_locked_until'] - time();
    if ($sessionLock > 0) {
        $isLocked = true;
        $lockSeconds = max($lockSeconds, $sessionLock);
    }
    unset($_SESSION['rate_limit_locked_until']);
}

ob_start();
?>
<div class="auth-card">
    <h2 class="auth-title">Login Customer</h2>

    <?php if ($isLocked && $lockSeconds > 0): ?>
    <div id="rate-limit-banner" style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; text-align: center;">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 6px;">
            <circle cx="12" cy="12" r="10"></circle>
            <polyline points="12 6 12 12 16 14"></polyline>
        </svg>
        <span style="color: #dc2626; font-size: 14px; font-weight: 500;">
            Terlalu banyak percobaan. Coba lagi dalam <span id="countdown-timer" style="font-weight: 700;"><?= rate_limit_format_time($lockSeconds) ?></span>
        </span>
    </div>
    <?php endif; ?>

    <?= render_errors($errors) ?>

    <form method="POST" action="<?= url('/customer/login/process') ?>" class="auth-form" id="login-form">
        <?= csrf_field() ?>
        
        <div class="form-group">
            <label for="phone" class="form-label">Nomor HP</label>
            <input type="tel" id="phone" name="phone" class="form-input" value="<?= e(old('phone')) ?>" required autofocus placeholder="08xxxxxxxxxx" <?= $isLocked ? 'disabled' : '' ?>>
        </div>

        <div class="form-group">
            <label for="password" class="form-label" style="margin-bottom: 10px;">Password</label>
            <input style="margin-bottom:10px" type="password" id="password" name="password" class="form-input" required placeholder="••••••••" <?= $isLocked ? 'disabled' : '' ?>>
            <a href="<?= url('/customer/forgot-password') ?>" style="font-size: 13px; color: var(--accent-green); text-decoration: none;margin-top: 10px;">Lupa Password?</a>
        </div>

        <button type="submit" class="btn btn-primary" id="login-btn" style="width: 100%; justify-content: center; margin-top: 8px;" <?= $isLocked ? 'disabled style="width: 100%; justify-content: center; margin-top: 8px; opacity: 0.5; cursor: not-allowed;"' : '' ?>>Masuk</button>

        <p style="text-align: center; margin-top: 16px; font-size: 14px; color: var(--text-muted);">
            Belum punya akun? <a href="<?= url('/customer/register') ?>" style="color: var(--accent-green);">Daftar</a>
        </p>
    </form>
</div>

<script>
<?php if ($isLocked && $lockSeconds > 0): ?>
(function() {
    let remaining = <?= (int)$lockSeconds ?>;
    const timerEl = document.getElementById('countdown-timer');
    const banner = document.getElementById('rate-limit-banner');
    const form = document.getElementById('login-form');
    const btn = document.getElementById('login-btn');

    function formatTime(secs) {
        const m = Math.floor(secs / 60);
        const s = secs % 60;
        if (m > 0 && s > 0) return m + ' menit ' + s + ' detik';
        if (m > 0) return m + ' menit';
        return s + ' detik';
    }

    const interval = setInterval(function() {
        remaining--;
        if (remaining <= 0) {
            clearInterval(interval);
            banner.style.display = 'none';
            // Re-enable form
            form.querySelectorAll('input, button[type="submit"]').forEach(function(el) {
                el.disabled = false;
                el.style.opacity = '';
                el.style.cursor = '';
            });
            btn.style.opacity = '1';
            btn.style.cursor = 'pointer';
        } else {
            timerEl.textContent = formatTime(remaining);
        }
    }, 1000);
})();
<?php endif; ?>
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../../layouts/guest.php';
