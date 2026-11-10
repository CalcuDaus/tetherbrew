<?php
require_once __DIR__ . '/../../../core/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/customer/login');
}

csrf_verify();

$token = $_POST['token'] ?? '';
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

$errors = validate($_POST, [
    'password' => 'required|min:6|confirmed'
]);

if (!empty($errors)) {
    redirect_back_with_errors($errors);
}

// Validasi Token lagi
$stmt = $pdo->prepare("SELECT created_at FROM password_reset_tokens WHERE email = ? AND token = ?");
$stmt->execute([$email, $token]);
$tokenData = $stmt->fetch();

if (!$tokenData) {
    redirect_with('/customer/forgot-password', 'error', 'Token reset password tidak valid atau sudah digunakan.');
}

// Cek kadaluarsa (misal 1 jam / 3600 detik)
$createdAt = strtotime($tokenData['created_at']);
if (time() - $createdAt > 3600) {
    $pdo->prepare("DELETE FROM password_reset_tokens WHERE email = ?")->execute([$email]);
    redirect_with('/customer/forgot-password', 'error', 'Link reset password sudah kadaluarsa. Silakan request kembali.');
}

// Update password
$hashedPassword = password_hash($password, PASSWORD_BCRYPT);
$pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE email = ? AND role = 'customer'")
    ->execute([$hashedPassword, $email]);

// Hapus token
$pdo->prepare("DELETE FROM password_reset_tokens WHERE email = ?")->execute([$email]);

redirect_with('/customer/login', 'success', 'Password berhasil diubah! Silakan login dengan password baru Anda.');
