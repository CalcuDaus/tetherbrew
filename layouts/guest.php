<?php
/**
 * Layout Guest (Login/Register)
 * Pengganti resources/views/layouts/guest.blade.php
 */
?>
<!DOCTYPE html>
<html lang="id" class="light-theme">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Tether Brew') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
    <script>
        (function() {
            const theme = localStorage.getItem('theme') || 'light';
            if (theme === 'dark') { document.documentElement.classList.remove('light-theme'); }
        })();
    </script>
</head>
<body>
    <div class="auth-container">
        <div class="auth-brand">
            <div class="auth-brand-icon">
                <img src="<?= base_url('assets/images/tether-icon-head.webp') ?>" alt="Logo" style="width: 100%; height: 100%; object-fit: contain;">
            </div>
            <div class="auth-brand-title">Tether Brew</div>
            <div class="auth-brand-sub">Management System</div>
        </div>

        <?= $content ?>
    </div>
</body>
</html>
