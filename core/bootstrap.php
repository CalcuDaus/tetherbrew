<?php
// Security headers dasar
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// Hardening konfigurasi PHP Session sebelum session_start()
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? null) == 443);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Flash old input logic (survives exactly one request)
if (isset($_SESSION['_flashed_old_input'])) {
    $_SESSION['old_input'] = $_SESSION['_flashed_old_input'];
    unset($_SESSION['_flashed_old_input']);
} else {
    unset($_SESSION['old_input']);
}
// Load configs
$dbConfig  = require __DIR__ . '/../config/database.php';
$appConfig = require __DIR__ . '/../config/app.php';

// PDO connection (global $pdo)
try {
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
        $dbConfig['username'],
        $dbConfig['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// Load semua helper
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/guard.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/flash.php';
require_once __DIR__ . '/pagination.php';
require_once __DIR__ . '/redirect.php';
require_once __DIR__ . '/validator.php';
require_once __DIR__ . '/rate_limiter.php';
