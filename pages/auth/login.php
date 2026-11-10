<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_guest();

$errors = validation_errors();
$pageTitle = 'Login - Tether Brew';

// Check if currently rate limited
$rateLimitKey = rate_limit_key('admin_login');
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
    <h2 class="auth-title">Masuk ke Akun</h2>

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

    <form method="POST" action="<?= base_url('login/process') ?>" class="auth-form" id="login-form">
        <?= csrf_field() ?>

        <div class="form-group">
            <label class="form-label" for="email">Email</label>
            <input type="email" id="email" name="email" class="form-input" value="<?= e(old('email')) ?>" placeholder="nama@email.com" required autofocus <?= $isLocked ? 'disabled' : '' ?>>
        </div>

        <div class="form-group">
            <label class="form-label" for="password">Password</label>
            <div style="position: relative;">
                <input type="password" id="password" name="password" class="form-input" placeholder="••••••••" required style="padding-right: 2.5rem;" <?= $isLocked ? 'disabled' : '' ?>>
                <button type="button" onclick="togglePassword('password', 'eye-icon-password')" style="position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); background: transparent; border: none; cursor: pointer; color: #64748b; padding: 0; display: flex; align-items: center; justify-content: center;">
                    <svg id="eye-icon-password" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                </button>
            </div>
        </div>

        <div class="checkbox-group">
            <input type="checkbox" id="remember" name="remember" <?= $isLocked ? 'disabled' : '' ?>>
            <label for="remember">Ingat saya</label>
        </div>

        <button type="submit" class="btn-auth" id="login-btn" style="width: 100%; justify-content: center; margin-top: 8px;" <?= $isLocked ? 'disabled style="width: 100%; justify-content: center; margin-top: 8px; opacity: 0.5; cursor: not-allowed;"' : '' ?>>
            Masuk
        </button>
    </form>

</div>

<script>
    function togglePassword(inputId, iconId) {
        const input = document.getElementById(inputId);
        const icon = document.getElementById(iconId);
        if (input.type === 'password') {
            input.type = 'text';
            icon.innerHTML = '<path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"></path><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"></path><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"></path><line x1="2" y1="2" x2="22" y2="22"></line>';
        } else {
            input.type = 'password';
            icon.innerHTML = '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path><circle cx="12" cy="12" r="3"></circle>';
        }
    }

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
require __DIR__ . '/../../layouts/guest.php';

