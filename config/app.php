<?php
if (file_exists(__DIR__ . '/app.local.php')) {
    return require __DIR__ . '/app.local.php';
}

return [
    'name' => 'Tether Brew',
    'url'  => 'https://tetherbrew.com',
    'upload_path' => __DIR__ . '/../uploads/',
    'upload_url'  => '/uploads/',
];
