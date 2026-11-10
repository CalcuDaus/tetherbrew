<?php
require_once __DIR__ . '/../../../core/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/customer/forgot-password');
}

csrf_verify();

$rateKey = rate_limit_key('customer_forgot_pw');
if (rate_limit_exceeded($rateKey, 3, 600)) {
    $rem = rate_limit_remaining_seconds($rateKey);
    redirect_with('/customer/forgot-password', 'error', 'Terlalu banyak permintaan. Coba lagi dalam ' . rate_limit_format_time($rem) . '.');
}
rate_limit_hit($rateKey, 3, 600);

$email = trim($_POST['email']);

$errors = validate(['email' => $email], [
    'email' => 'required|email'
]);

if (!empty($errors)) {
    redirect_back_with_errors($errors);
}

// Cek apakah email terdaftar sebagai customer
$stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ? AND role = 'customer'");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    // Untuk alasan keamanan, jangan beri tahu jika email tidak ada, tetap tampilkan pesan sukses
    redirect_with('/customer/forgot-password', 'success', 'Jika email Anda terdaftar, Anda akan menerima link reset password sesaat lagi.');
}

// Hapus token lama jika ada
$pdo->prepare("DELETE FROM password_reset_tokens WHERE email = ?")->execute([$email]);

// Buat token baru
$token = bin2hex(random_bytes(32));
$pdo->prepare("INSERT INTO password_reset_tokens (email, token, created_at) VALUES (?, ?, NOW())")
    ->execute([$email, $token]);

$resetLink = url("/customer/reset-password?token=" . $token . "&email=" . urlencode($email));

// PENGIRIMAN EMAIL MENGGUNAKAN FUNGSI NATIVE mail()
$to = $email;
$subject = "Reset Password - Tether Brew";
$message = "
<html>
<head>
  <title>Reset Password</title>
</head>
<body>
  <h2>Reset Password Akun Anda</h2>
  <p>Halo,</p>
  <p>Kami menerima permintaan untuk mereset password akun Anda di Tether Brew.</p>
  <p>Klik tombol di bawah ini untuk mereset password Anda:</p>
  <p><a href='{$resetLink}' style='display:inline-block; padding:10px 20px; background-color:#22c55e; color:white; text-decoration:none; border-radius:5px;'>Reset Password</a></p>
  <p>Jika tombol di atas tidak berfungsi, copy-paste link berikut ke browser Anda:</p>
  <p>{$resetLink}</p>
  <br>
  <p>Jika Anda tidak merasa meminta reset password, abaikan email ini.</p>
  <p>Terima kasih,<br>Tim Tether Brew</p>
</body>
</html>
";

// Headers untuk email HTML
$headers = "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";

// Penting: Ganti email 'from' dengan email domain yang valid di Hostinger!
// Contoh: no-reply@domainanda.com
$domain = $_SERVER['HTTP_HOST'] ?? 'tetherbrew.com';
$fromEmail = 'no-reply@' . $domain;

$headers .= "From: Tether Brew <{$fromEmail}>" . "\r\n";
$headers .= "Reply-To: {$fromEmail}" . "\r\n";

$mailSent = @mail($to, $subject, $message, $headers);

if ($mailSent || $_SERVER['SERVER_NAME'] === 'localhost') {
    // Pada localhost, fungsi mail() biasanya akan gagal (false) jika tidak ada SMTP,
    // Kita tambahkan pengecekan localhost untuk kebutuhan simulasi development
    $devMessage = "";
    if ($_SERVER['SERVER_NAME'] === 'localhost') {
        $devMessage = "<br><br><small style='color:#64748b;'><em>(Simulasi Localhost): <a href='{$resetLink}' style='color:blue; text-decoration:underline;'>Klik Link Reset Ini</a></em></small>";
    }
    
    redirect_with('/customer/forgot-password', 'success', 'Link reset password telah dikirim ke email Anda. Silakan cek Inbox atau folder Spam.' . $devMessage);
} else {
    redirect_with('/customer/forgot-password', 'error', 'Gagal mengirim email reset password. Pastikan konfigurasi email server valid.');
}

