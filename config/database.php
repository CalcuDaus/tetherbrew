<?php
// Gunakan konfigurasi lokal jika tersedia
if (file_exists(__DIR__ . '/database.local.php')) {
    return require __DIR__ . '/database.local.php';
}

// Konfigurasi database (Production)
return [
    'host'     => 'localhost',
    'dbname'   => 'u665669172_tetherbrew',
    'username' => 'u665669172_tetherbrew',
    'password' => 'bWb?a@G~6m',
    'charset'  => 'utf8mb4',
];
