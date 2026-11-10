<?php
/**
 * Layout Customer App
 * Pengganti resources/views/layouts/customer.blade.php
 * Mobile-first dengan bottom navigation bar
 */
$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
function is_active_nav($path, $currentPath) {
    $path = rtrim($path, '/');
    $currentPath = rtrim($currentPath, '/');
    $length = strlen($path);
    if ($length == 0) return false;
    return (substr($currentPath, -$length) === $path);
}
?>
<!DOCTYPE html>
<html lang="id" class="light-theme">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <script>
        (function() {
            const origFetch = window.fetch;
            window.fetch = function(input, init) {
                init = init || {};
                const method = (init.method || 'GET').toUpperCase();
                const urlStr = typeof input === 'string' ? input : (input && input.url ? input.url : '');
                const isSameOrigin = !urlStr.startsWith('http') || urlStr.startsWith(window.location.origin);
                if (isSameOrigin && method !== 'GET' && method !== 'HEAD') {
                    const meta = document.querySelector('meta[name="csrf-token"]');
                    if (meta) {
                        init.headers = new Headers(init.headers || {});
                        if (!init.headers.has('X-CSRF-TOKEN')) {
                            init.headers.set('X-CSRF-TOKEN', meta.getAttribute('content'));
                        }
                    }
                }
                return origFetch.call(this, input, init);
            };
        })();
    </script>
    <title><?= e($pageTitle ?? 'Tether Brew App') ?></title>
    <link rel="manifest" href="<?= base_url('manifest.webmanifest') ?>">
    <meta name="theme-color" content="#16a34a">
    <link rel="icon" type="image/webp" href="<?= base_url('assets/images/tether-icon-head.webp') ?>">
    <link rel="apple-touch-icon" href="<?= base_url('assets/images/pwa-192.png') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>?v=<?= filemtime(__DIR__ . '/../assets/css/app.css') ?>">
    <style>
        body { background-color: #f1f5f9; font-family: 'Poppins', sans-serif; margin: 0; padding: 0; display: flex; justify-content: center; }
        .app-wrapper { background-color: #ffffff; width: 100%; max-width: 480px; min-height: 100vh; position: relative; box-shadow: 0 0 20px rgba(0,0,0,0.05); display: flex; flex-direction: column; overflow-x: hidden; }
        .app-content { flex: 1; padding-bottom: 90px; overflow-y: auto; }
        .bottom-nav { position: fixed; bottom: 0; left: 50%; transform: translateX(-50%); width: 100%; max-width: 480px; background: #ffffff; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-around; align-items: center; z-index: 50; padding: 12px 0 calc(22px + env(safe-area-inset-bottom)) 0; }
        .nav-item { position: relative; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #94a3b8; text-decoration: none; font-size: 0.75rem; font-weight: 500; gap: 4px; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); width: 25%; }
        .nav-item svg { width: 24px; height: 24px; stroke-width: 2; transition: all 0.3s ease; }
        .nav-item::before { content: ''; position: absolute; top: -12px; left: 50%; transform: translateX(-50%); width: 0; height: 3px; background: #8b5c2a; border-radius: 0 0 4px 4px; transition: width 0.3s ease; }
        
        .nav-item.active { color: #8b5c2a; transform: translateY(-2px); }
        .nav-item.active svg { fill: rgba(139, 92, 42, 0.15); stroke: #8b5c2a; filter: drop-shadow(0 4px 6px rgba(139, 92, 42, 0.2)); transform: scale(1.1); }
        .nav-item.active span { font-weight: 700; }
        .nav-item.active::before { width: 24px; }
        
        .app-header { padding: 20px; background: #ffffff; position: sticky; top: 0; z-index: 40; }
    </style>
    <?= $extraHead ?? '' ?>
</head>
<body>
    <div class="app-wrapper">
        <div class="app-content">
            <?= $content ?>
        </div>

        <!-- Bottom Navigation -->
        <nav class="bottom-nav">
            <a href="<?= url('/customer/dashboard') ?>" class="nav-item <?= is_active_nav('/customer/dashboard', $currentPath) ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                <span>Beranda</span>
            </a>
            <a href="<?= url('/customer/menu') ?>" class="nav-item <?= is_active_nav('/customer/menu', $currentPath) ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="9" y1="3" x2="9" y2="21"></line></svg>
                <span>Menu</span>
            </a>
            <a href="<?= url('/customer/chats') ?>" class="nav-item <?= is_active_nav('/customer/chats', $currentPath) ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                <span>Chats</span>
            </a>
            <a href="<?= url('/customer/me') ?>" class="nav-item <?= is_active_nav('/customer/me', $currentPath) ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                <span>Me</span>
            </a>
        </nav>

        <!-- ===== PWA INSTALL BANNER (Customer App) ===== -->
        <div id="pwa-install-banner" class="pwa-install-banner" style="display:none;">
            <div class="pwa-banner-card">
                <div class="pwa-banner-left">
                    <img src="<?= base_url('assets/images/pwa-192.png') ?>" alt="Tether Brew" class="pwa-banner-logo" width="40" height="40">
                    <div class="pwa-banner-info">
                        <strong>Install Tether Brew</strong>
                        <span>Buka langsung dari layar utama HP</span>
                    </div>
                </div>
                <div class="pwa-banner-right">
                    <button type="button" id="pwa-banner-install" class="btn-pwa-install">Install</button>
                    <button type="button" id="pwa-banner-close" class="btn-pwa-close" aria-label="Tutup Banner">✕</button>
                </div>
            </div>
        </div>
    </div>

    <?= $extraScripts ?? '' ?>
    <script src="<?= base_url('assets/js/pwa-install.js') ?>?v=<?= filemtime(__DIR__ . '/../assets/js/pwa-install.js') ?>" defer></script>
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('<?= base_url('sw.js') ?>').catch(function() {});
            });
        }
    </script>
</body>
</html>
