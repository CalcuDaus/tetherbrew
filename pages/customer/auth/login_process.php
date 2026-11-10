<?php
require_once __DIR__ . '/../../../core/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/customer/login');
csrf_verify();

// Rate limiter check
$rateLimitKey = rate_limit_key('customer_login');
$maxAttempts = 5;
$decaySeconds = 300; // 5 menit

if (rate_limit_exceeded($rateLimitKey, $maxAttempts, $decaySeconds)) {
    $remaining = rate_limit_remaining_seconds($rateLimitKey);
    $_SESSION['validation_errors'] = [
        'phone' => ['Terlalu banyak percobaan login. Coba lagi dalam ' . rate_limit_format_time($remaining) . '.']
    ];
    $_SESSION['rate_limit_locked_until'] = time() + $remaining;
    store_old_input();
    redirect('/customer/login');
}

$phone = trim($_POST['phone'] ?? '');
$password = $_POST['password'] ?? '';

$errors = validate($_POST, ['phone' => 'required', 'password' => 'required']);
if (!empty($errors)) { redirect_back_with_errors($errors); }

$user = auth_attempt_phone($phone, $password);
if (!$user) {
    rate_limit_hit($rateLimitKey, $maxAttempts, $decaySeconds);
    
    if (rate_limit_exceeded($rateLimitKey, $maxAttempts, $decaySeconds)) {
        $remaining = rate_limit_remaining_seconds($rateLimitKey);
        $errorMsg = 'Terlalu banyak percobaan login. Coba lagi dalam ' . rate_limit_format_time($remaining) . '.';
        $_SESSION['rate_limit_locked_until'] = time() + $remaining;
    } else {
        $errorMsg = 'Nomor HP atau password salah.';
    }
    
    $_SESSION['validation_errors'] = ['phone' => [$errorMsg]];
    store_old_input();
    redirect('/customer/login');
}

// Login successful - clear rate limit
rate_limit_clear($rateLimitKey);

auth_login($user['id']);
redirect_intended('/customer/dashboard');
