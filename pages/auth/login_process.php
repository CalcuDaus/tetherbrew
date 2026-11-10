<?php
require_once __DIR__ . '/../../core/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/login');
}

csrf_verify();

// Rate limiter check
$rateLimitKey = rate_limit_key('admin_login');
$maxAttempts = 5;
$decaySeconds = 300; // 5 menit

if (rate_limit_exceeded($rateLimitKey, $maxAttempts, $decaySeconds)) {
    $remaining = rate_limit_remaining_seconds($rateLimitKey);
    $_SESSION['validation_errors'] = [
        'email' => ['Terlalu banyak percobaan login. Coba lagi dalam ' . rate_limit_format_time($remaining) . '.']
    ];
    $_SESSION['rate_limit_locked_until'] = time() + $remaining;
    store_old_input();
    redirect('/login');
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// Validate
$errors = validate($_POST, [
    'email' => 'required|email',
    'password' => 'required',
]);

if (!empty($errors)) {
    redirect_back_with_errors($errors);
}

// Attempt login
$user = auth_attempt($email, $password);

if (!$user) {
    rate_limit_hit($rateLimitKey, $maxAttempts, $decaySeconds);
    
    if (rate_limit_exceeded($rateLimitKey, $maxAttempts, $decaySeconds)) {
        $remaining = rate_limit_remaining_seconds($rateLimitKey);
        $errorMsg = 'Terlalu banyak percobaan login. Coba lagi dalam ' . rate_limit_format_time($remaining) . '.';
        $_SESSION['rate_limit_locked_until'] = time() + $remaining;
    } else {
        $errorMsg = 'Email atau password salah.';
    }
    
    $_SESSION['validation_errors'] = ['email' => [$errorMsg]];
    store_old_input();
    redirect('/login');
}

// Check role - customer should use customer login
if ($user['role'] === 'customer') {
    $_SESSION['validation_errors'] = ['email' => ['Gunakan halaman login customer.']];
    store_old_input();
    redirect('/login');
}

// Login successful - clear rate limit
rate_limit_clear($rateLimitKey);

// Redirect to intended URL or dashboard
auth_login($user['id']);
redirect_intended('/dashboard');
