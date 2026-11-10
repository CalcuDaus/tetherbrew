<?php
/**
 * Layout Utama (Admin/Rider/Owner/Bar)
 * Pengganti resources/views/layouts/app.blade.php
 * 
 * Variables expected:
 * - $pageTitle (string) - Title halaman
 * - $content (string) - HTML content halaman
 */
$currentUser = auth_user();
$currentRole = $currentUser['role'] ?? '';
$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
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
    <title><?= e($pageTitle ?? 'Dashboard') ?> - Tether Brew</title>
    <link rel="manifest" href="<?= base_url('manifest.webmanifest') ?>">
    <meta name="theme-color" content="#16a34a">
    <link rel="icon" type="image/webp" href="<?= base_url('assets/images/tether-icon-head.webp') ?>">
    <link rel="apple-touch-icon" href="<?= base_url('assets/images/pwa-192.png') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <?php if (in_array($currentRole, ['owner', 'admin'])): ?>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <?php endif; ?>
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>?v=<?= filemtime(__DIR__ . '/../assets/css/app.css') ?>">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .swal2-popup { border-radius: 12px !important; font-family: 'Inter', sans-serif !important; background: var(--bg-card) !important; color: var(--text-primary) !important; border: 1px solid var(--border-color) !important; }
        .swal2-html-container { color: var(--text-secondary) !important; }
        .swal2-title { font-size: 1.25rem !important; }
        .swal2-styled.swal2-confirm { background-color: var(--accent-green) !important; border-radius: 8px !important; }
        .swal2-styled.swal2-cancel { background-color: rgba(239, 68, 68, 0.1) !important; color: #ef4444 !important; border-radius: 8px !important; }
        .select2-container { max-width: 100%; }
        .select2-container--default .select2-selection--single { background-color: var(--bg-card, #fff); border: 1px solid var(--border-color, #e2e8f0); border-radius: 8px; height: 42px; display: flex; align-items: center; position: relative; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { color: var(--text-primary, #1e293b); line-height: normal; padding-left: 12px; padding-right: 30px; width: 100%; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 40px; right: 8px; display: flex; align-items: center; justify-content: center; }
        .select2-dropdown { background-color: var(--bg-card, #fff); border: 1px solid var(--border-color, #e2e8f0); border-radius: 8px; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); z-index: 9999; }
        .select2-search--dropdown .select2-search__field { background-color: var(--bg-main, #f8fafc); border: 1px solid var(--border-color, #e2e8f0); border-radius: 6px; color: var(--text-primary, #1e293b); }
        .select2-results__option { color: var(--text-primary, #1e293b); }
        .select2-container--default .select2-results__option--selected { background-color: var(--bg-secondary, #f1f5f9); }
        .select2-container--default .select2-results__option--highlighted.select2-results__option--selectable { background-color: var(--accent-green, #22c55e); color: #ffffff; }
    </style>
    <script>
        // Theme initialization
        (function() {
            const theme = localStorage.getItem('theme') || 'light';
            if (theme === 'dark') { document.documentElement.classList.remove('light-theme'); }
        })();

        // Sidebar state initialization to prevent layout flash on desktop
        (function() {
            if (window.innerWidth > 768 && localStorage.getItem('sidebar-collapsed') === 'true') {
                document.documentElement.classList.add('sidebar-collapsed');
            }
        })();

        // Confirm dialog for forms with data-confirm
        document.addEventListener('submit', function(e) {
            const form = e.target;
            if (form.hasAttribute('data-confirm')) {
                e.preventDefault();
                const message = form.getAttribute('data-confirm');
                Swal.fire({
                    title: 'Konfirmasi', text: message, icon: 'warning',
                    showCancelButton: true, confirmButtonText: 'Ya, Lanjutkan', cancelButtonText: 'Batal', reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.removeAttribute('data-confirm');
                        if (typeof form.requestSubmit === 'function') {
                            form.requestSubmit();
                        } else {
                            form.submit();
                        }
                    }
                });
            }
        });

        // Theme switcher
        window.switchTheme = function(event) {
            document.documentElement.classList.toggle('light-theme');
            const newTheme = document.documentElement.classList.contains('light-theme') ? 'light' : 'dark';
            localStorage.setItem('theme', newTheme);
            window.dispatchEvent(new CustomEvent('theme-changed', { detail: { theme: newTheme } }));
        };

        // Confirm Delete All
        window.confirmDeleteAll = function(formId) {
            Swal.fire({
                title: 'HAPUS SEMUA DATA?',
                html: 'Tindakan ini sangat berbahaya dan <b>tidak dapat dibatalkan</b>.<br>Ketik kalimat berikut untuk mengonfirmasi:<br><br><b style="color:var(--accent-red, red);">Saya yakin untuk menghapus data ini dan menerima konsekuensinya</b>',
                icon: 'warning',
                input: 'text',
                inputPlaceholder: 'Ketik di sini...',
                showCancelButton: true,
                confirmButtonText: 'HAPUS PERMANEN',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#ef4444',
                preConfirm: (inputValue) => {
                    if (inputValue !== 'Saya yakin untuk menghapus data ini dan menerima konsekuensinya') {
                        Swal.showValidationMessage('Kalimat tidak cocok!');
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById(formId).submit();
                }
            });
        };
    </script>
    <?= $extraHead ?? '' ?>
</head>
<body class="<?= $currentRole === 'rider' ? 'is-rider' : '' ?>">

    <!-- Sidebar Backdrop for Mobile -->
    <div class="sidebar-backdrop" id="sidebar-backdrop"></div>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <div class="sidebar-brand-icon">
                <img src="<?= base_url('assets/images/tether-icon-head.webp') ?>" alt="Logo" style="width: 100%; height: 100%; object-fit: contain;">
            </div>
            <div style="flex: 1; min-width: 0;">
                <div class="sidebar-brand-text">Tether Brew</div>
                <div class="sidebar-brand-sub">Management System</div>
            </div>
            <button type="button" class="sidebar-close-mobile" id="sidebar-close-mobile" aria-label="Tutup Menu" title="Tutup Menu">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>

        <nav class="sidebar-nav">
            <?php if ($currentRole === 'owner'): ?>
                <!-- ====== OWNER SIDEBAR ====== -->
                <a href="<?= url('/dashboard') ?>" class="nav-link <?= is_active('/dashboard') ? 'active' : '' ?>">
                    <span class="nav-link-icon">
                        <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/>
                        </svg>
                    </span> Dashboard
                </a>

                <?php $isLaporanActive = is_active('/journals') || is_active('/rider-sales-report') || is_active('/rider-minus') || is_active('/payroll') || is_active('/rider-finances/kasbon') || is_active('/admin/office-kasbon') || is_active('/admin/productions'); ?>
                <div class="nav-section">
                    <div class="nav-link dropdown-toggle <?= $isLaporanActive ? 'active' : '' ?>">
                        <span class="nav-link-icon">
                            <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/>
                            </svg>
                        </span>
                        <span>Laporan</span>
                        <svg class="dropdown-arrow <?= $isLaporanActive ? 'rotated' : '' ?>" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                    </div>
                    <div class="nav-submenu <?= $isLaporanActive ? '' : 'collapsed' ?>" <?= $isLaporanActive ? '' : 'style="display: none;"' ?>>
                        <a href="<?= url('/journals') ?>" class="nav-submenu-item <?= is_active('/journals') ? 'active' : '' ?>">
                            <span class="nav-submenu-icon"><svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/><path d="M8 7h6"/><path d="M8 11h8"/></svg></span>
                            Jurnal Umum
                        </a>
                        <a href="<?= url('/rider-finances/kasbon') ?>" class="nav-submenu-item <?= is_active('/rider-finances/kasbon') ? 'active' : '' ?>">
                            <span class="nav-submenu-icon">
                                <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                            </span>
                            Kasbon Rider
                        </a>
                        <a href="<?= url('/admin/office-kasbon') ?>" class="nav-submenu-item <?= is_active('/admin/office-kasbon') ? 'active' : '' ?>">
                            <span class="nav-submenu-icon">
                                <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                            </span>
                            Kasbon Office
                        </a>
                        <a href="<?= url('/rider-sales-report') ?>" class="nav-submenu-item <?= is_active('/rider-sales-report') ? 'active' : '' ?>">
                            <span class="nav-submenu-icon"><svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/></svg></span>
                            Laporan Penjualan
                        </a>
                        <a href="<?= url('/rider-minus') ?>" class="nav-submenu-item <?= is_active('/rider-minus') ? 'active' : '' ?>">
                            <span class="nav-submenu-icon"><svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M8 12h8"/></svg></span>
                            Laporan Minus
                        </a>
                        <a href="<?= url('/payroll') ?>" class="nav-submenu-item <?= (is_active('/payroll') && !is_active('/payroll/history')) ? 'active' : '' ?>">
                            <span class="nav-submenu-icon"><svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/></svg></span>
                            Slip Gaji Rider
                        </a>
                        <a href="<?= url('/payroll/history') ?>" class="nav-submenu-item <?= is_active('/payroll/history') ? 'active' : '' ?>">
                            <span class="nav-submenu-icon"><svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><polyline points="14 2 14 8 20 8"/><line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/><polyline points="10 9 9 9 8 9"/></svg></span>
                            Riwayat Slip Gaji
                        </a>
                        <a href="<?= url('/admin/productions') ?>" class="nav-submenu-item <?= is_active('/admin/productions') ? 'active' : '' ?>">
                            <span class="nav-submenu-icon"><svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg></span>
                            Riwayat Produksi
                        </a>
                    </div>
                </div>

                <a href="<?= url('/branches') ?>" class="nav-link <?= is_active('/branches') ? 'active' : '' ?>">
                    <span class="nav-link-icon">
                        <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m2 7 4.38-2.92a2 2 0 0 1 2.22 0L13 7"/><path d="M2 7v14"/><path d="M22 7v14"/><path d="M22 21H2"/><path d="M13 7v14"/><path d="M7 21v-5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v5"/>
                        </svg>
                    </span> Cabang
                </a>

                <a href="<?= url('/admin/chats') ?>" class="nav-link <?= is_active('/admin/chats') ? 'active' : '' ?>">
                    <span class="nav-link-icon">
                        <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                        </svg>
                    </span> Monitoring Chat
                </a>

                <a href="<?= url('/admin/settings') ?>" class="nav-link <?= is_active('/admin/settings') ? 'active' : '' ?>">
                    <span class="nav-link-icon">
                        <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"></path><circle cx="12" cy="12" r="3"></circle>
                        </svg>
                    </span> Pengaturan
                </a>

                <a href="<?= url('/accounts') ?>" class="nav-link <?= is_active('/accounts') ? 'active' : '' ?>">
                    <span class="nav-link-icon">
                        <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                    </span> Kelola Akun
                </a>

            <?php elseif ($currentRole === 'admin'): ?>
                <!-- ====== ADMIN SIDEBAR ====== -->
                <?php
                    $isOperasionalActive = is_active('/admin/productions') || is_active('/admin/available-stocks') || is_active('/admin/production-plan') || is_active('/admin/spoiled-products') || is_active('/rider-sales') || is_active('/journals') || is_active('/rider-finances') || is_active('/admin/office-kasbon') || is_active('/rider-minus') || is_active('/payroll') || is_active('/rider-sales-report') || is_active('/admin/chats');
                    $isManajemenActive = is_active('/carts') || is_active('/riders') || is_active('/bars') || is_active('/products') || is_active('/inventories') || is_active('/transactions') || is_active('/admin/artikel') || is_active('/journal-categories');
                ?>
                <a href="<?= url('/dashboard') ?>" class="nav-link <?= is_active('/dashboard') ? 'active' : '' ?>">
                    <span class="nav-link-icon">
                        <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/>
                        </svg>
                    </span> Dashboard
                </a>

                <div class="nav-section">
                    <div class="nav-link dropdown-toggle <?= $isOperasionalActive ? 'active' : '' ?>">
                        <span class="nav-link-icon">
                            <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 12h-4l-3 9L9 3l-3 9H2"></path>
                            </svg>
                        </span>
                        <span>Operasional</span>
                        <svg class="dropdown-arrow <?= $isOperasionalActive ? 'rotated' : '' ?>" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                    </div>
                    <div class="nav-submenu <?= $isOperasionalActive ? '' : 'collapsed' ?>" <?= $isOperasionalActive ? '' : 'style="display: none;"' ?>>
                        <a href="<?= url('/admin/productions') ?>" class="nav-submenu-item <?= is_active('/admin/productions') ? 'active' : '' ?>">
                            <span class="nav-submenu-icon">
                                <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                            </span> Riwayat Produksi
                        </a>
                        <a href="<?= url('/admin/available-stocks') ?>" class="nav-submenu-item <?= is_active('/admin/available-stocks') ? 'active' : '' ?>">
                            <span class="nav-submenu-icon">
                                <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                            </span> Sisa Stok
                        </a>
                        <a href="<?= url('/admin/production-plan') ?>" class="nav-submenu-item <?= is_active('/admin/production-plan') ? 'active' : '' ?>">
                            <span class="nav-submenu-icon">
                                <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                            </span> Plan Produksi
                        </a>
                        <a href="<?= url('/admin/spoiled-products') ?>" class="nav-submenu-item <?= is_active('/admin/spoiled-products') ? 'active' : '' ?>">
                            <span class="nav-submenu-icon">
                                <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                            </span> Produk Basi
                        </a>
                        <a href="<?= url('/rider-sales') ?>" class="nav-submenu-item <?= is_active('/rider-sales') ? 'active' : '' ?>">
                            <span class="nav-submenu-icon">
                                <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </span> Input Penjualan
                        </a>
                        <a href="<?= url('/journals') ?>" class="nav-submenu-item <?= is_active('/journals') ? 'active' : '' ?>">
                            <span class="nav-submenu-icon">
                                <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/><path d="M8 7h6"/><path d="M8 11h8"/></svg>
                            </span> Jurnal Umum
                        </a>
                        <a href="<?= url('/rider-finances/kasbon') ?>" class="nav-submenu-item <?= is_active('/rider-finances/kasbon') ? 'active' : '' ?>">
                            <span class="nav-submenu-icon">
                                <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                            </span> Kasbon Rider
                        </a>
                        <a href="<?= url('/admin/office-kasbon') ?>" class="nav-submenu-item <?= is_active('/admin/office-kasbon') ? 'active' : '' ?>">
                            <span class="nav-submenu-icon">
                                <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                            </span> Kasbon Office
                        </a>
                        <a href="<?= url('/rider-finances/uang-makan') ?>" class="nav-submenu-item <?= is_active('/rider-finances/uang-makan') ? 'active' : '' ?>">
                            <span class="nav-submenu-icon">
                                <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                            </span> Uang Makan
                        </a>
                        <a href="<?= url('/rider-minus') ?>" class="nav-submenu-item <?= is_active('/rider-minus') ? 'active' : '' ?>">
                            <span class="nav-submenu-icon">
                                <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M8 12h8"/></svg>
                            </span> Laporan Minus
                        </a>
                        <a href="<?= url('/payroll') ?>" class="nav-submenu-item <?= (is_active('/payroll') && !is_active('/payroll/history')) ? 'active' : '' ?>">
                            <span class="nav-submenu-icon">
                                <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/><line x1="7" x2="7" y1="15" y2="15"/><line x1="12" x2="12" y1="15" y2="15"/></svg>
                            </span> Slip Gaji Rider
                        </a>
                        <a href="<?= url('/payroll/history') ?>" class="nav-submenu-item <?= is_active('/payroll/history') ? 'active' : '' ?>">
                            <span class="nav-submenu-icon">
                                <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><polyline points="14 2 14 8 20 8"/><line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                            </span> Riwayat Slip Gaji
                        </a>
                        <a href="<?= url('/rider-sales-report') ?>" class="nav-submenu-item <?= is_active('/rider-sales-report') ? 'active' : '' ?>">
                            <span class="nav-submenu-icon">
                                <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/></svg>
                            </span> Laporan Penjualan
                        </a>
                        <a href="<?= url('/admin/chats') ?>" class="nav-submenu-item <?= is_active('/admin/chats') ? 'active' : '' ?>">
                            <span class="nav-submenu-icon">
                                <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                            </span> Monitoring Chat
                        </a>
                    </div>
                </div>

                <div class="nav-section">
                    <div class="nav-link dropdown-toggle <?= $isManajemenActive ? 'active' : '' ?>">
                        <span class="nav-link-icon">
                            <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path>
                            </svg>
                        </span>
                        <span>Manajemen Data</span>
                        <svg class="dropdown-arrow <?= $isManajemenActive ? 'rotated' : '' ?>" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                    </div>

                    <div class="nav-submenu <?= $isManajemenActive ? '' : 'collapsed' ?>" <?= $isManajemenActive ? '' : 'style="display: none;"' ?>>
                        <a href="<?= url('/carts') ?>" class="nav-submenu-item <?= is_active('/carts') ? 'active' : '' ?>">
                            <span class="nav-submenu-icon">
                                <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>
                            </span> Gerobak
                        </a>
                        <a href="<?= url('/riders') ?>" class="nav-submenu-item <?= is_active('/riders') ? 'active' : '' ?>">
                            <span class="nav-submenu-icon">
                                <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 17a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V9.5C2 7 4 5 6.5 5H18c2.2 0 4 1.8 4 4v8Z"/><polyline points="15,9 18,9 18,11"/><path d="M22 9c-4.2-3-8.8-3-13 0"/></svg>
                            </span> Rider
                        </a>
                        <a href="<?= url('/bars') ?>" class="nav-submenu-item <?= is_active('/bars') ? 'active' : '' ?>">
                            <span class="nav-submenu-icon">
                                <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2v0a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"/></svg>
                            </span> Bar
                        </a>
                        <a href="<?= url('/products') ?>" class="nav-submenu-item <?= is_active('/products') ? 'active' : '' ?>">
                            <span class="nav-submenu-icon">
                                <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 8h1a4 4 0 1 1 0 8h-1"/><path d="M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4Z"/><line x1="6" x2="6" y1="2" y2="4"/><line x1="10" x2="10" y1="2" y2="4"/><line x1="14" x2="14" y1="2" y2="4"/></svg>
                            </span> Produk
                        </a>
                        <a href="<?= url('/inventories') ?>" class="nav-submenu-item <?= is_active('/inventories') ? 'active' : '' ?>">
                            <span class="nav-submenu-icon">
                                <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                            </span> Stok
                        </a>
                        <a href="<?= url('/transactions') ?>" class="nav-submenu-item <?= is_active('/transactions') ? 'active' : '' ?>">
                            <span class="nav-submenu-icon">
                                <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            </span> Transaksi
                        </a>
                        <a href="<?= url('/admin/artikel') ?>" class="nav-submenu-item <?= is_active('/admin/artikel') ? 'active' : '' ?>">
                            <span class="nav-submenu-icon">
                                <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2Zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2"/><path d="M18 14h-8"/><path d="M15 18h-5"/><path d="M10 6h8v4h-8V6Z"/></svg>
                            </span> Artikel
                        </a>
                        <a href="<?= url('/journal-categories') ?>" class="nav-submenu-item <?= is_active('/journal-categories') ? 'active' : '' ?>">
                            <span class="nav-submenu-icon">
                                <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2H2v10l9.29 9.29c.94.94 2.48.94 3.42 0l8.29-8.29c.94-.94.94-2.48 0-3.42L13.71 4 12 2Z"/><circle cx="7" cy="7" r="2"/></svg>
                            </span> Kategori Jurnal
                        </a>
                    </div>
                </div>

            <?php elseif ($currentRole === 'bar'): ?>
                <!-- ====== BAR SIDEBAR ====== -->
                <?php $isBarOperasionalActive = is_active('/admin/productions') || is_active('/admin/available-stocks') || is_active('/admin/production-plan') || is_active('/admin/spoiled-products') || is_active('/rider-sales'); ?>
                <div class="nav-section">
                    <div class="nav-link dropdown-toggle <?= $isBarOperasionalActive ? 'active' : '' ?>">
                        <span class="nav-link-icon">
                            <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 12h-4l-3 9L9 3l-3 9H2"></path>
                            </svg>
                        </span>
                        <span>Operasional</span>
                        <svg class="dropdown-arrow <?= $isBarOperasionalActive ? 'rotated' : '' ?>" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </div>
                    
                    <div class="nav-submenu <?= $isBarOperasionalActive ? '' : 'collapsed' ?>" <?= $isBarOperasionalActive ? '' : 'style="display: none;"' ?>>
                        <a href="<?= url('/admin/productions') ?>" class="nav-submenu-item <?= is_active('/admin/productions') ? 'active' : '' ?>">
                            <span class="nav-submenu-icon">
                                <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                            </span>
                            Riwayat Produksi
                        </a>
                        <a href="<?= url('/admin/available-stocks') ?>" class="nav-submenu-item <?= is_active('/admin/available-stocks') ? 'active' : '' ?>">
                            <span class="nav-submenu-icon">
                                <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                            </span>
                            Sisa Stok
                        </a>
                        <a href="<?= url('/admin/production-plan') ?>" class="nav-submenu-item <?= is_active('/admin/production-plan') ? 'active' : '' ?>">
                            <span class="nav-submenu-icon">
                                <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                            </span>
                            Plan Produksi
                        </a>
                        <a href="<?= url('/admin/spoiled-products') ?>" class="nav-submenu-item <?= is_active('/admin/spoiled-products') ? 'active' : '' ?>">
                            <span class="nav-submenu-icon">
                                <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                            </span>
                            Produk Basi
                        </a>
                        <a href="<?= url('/rider-sales') ?>" class="nav-submenu-item <?= is_active('/rider-sales') ? 'active' : '' ?>">
                            <span class="nav-submenu-icon">
                                <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </span>
                            Input Penjualan
                        </a>
                    </div>
                </div>

            <?php elseif ($currentRole === 'rider'): ?>
                <!-- ====== RIDER SIDEBAR ====== -->
                <a href="<?= url('/dashboard') ?>" class="nav-link <?= is_active('/dashboard') ? 'active' : '' ?>">
                    <span class="nav-link-icon">
                        <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/>
                        </svg>
                    </span> Dashboard
                </a>
                <a href="<?= url('/rider/transactions') ?>" class="nav-link <?= is_active('/rider/transactions') ? 'active' : '' ?>">
                    <span class="nav-link-icon">
                        <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/></svg>
                    </span> Riwayat Transaksi
                </a>
                <a href="<?= url('/rider/chat') ?>" class="nav-link <?= is_active('/rider/chat') ? 'active' : '' ?>">
                    <span class="nav-link-icon">
                        <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                    </span> Chat Grup
                </a>
            <?php endif; ?>

            <a href="<?= url('/') ?>" class="nav-link" style="margin-top: 15px;">
                <span class="nav-link-icon">
                    <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                </span> Landing Page
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="user-card">
                <div class="user-avatar"><?= strtoupper(substr($currentUser['name'], 0, 1)) ?></div>
                <div class="user-info">
                    <div class="user-name"><?= e($currentUser['name']) ?></div>
                    <div class="user-role"><?= e($currentUser['role']) ?></div>
                </div>
            </div>
            <form method="POST" action="<?= url('/logout') ?>" style="margin-top: 8px;">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-secondary btn-sm" style="width: 100%; justify-content: center; gap: 8px;">
                    <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg> Logout
                </button>
            </form>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="main-content">
        <div class="topbar">
            <button type="button" class="sidebar-toggle" id="sidebar-toggle" aria-label="Toggle Sidebar" title="Sembunyikan / Tampilkan Sidebar">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="3" y1="12" x2="21" y2="12"></line>
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <line x1="3" y1="18" x2="21" y2="18"></line>
                </svg>
            </button>
            <div class="topbar-title"><?= e($pageTitle ?? 'Dashboard') ?></div>
            <div class="topbar-actions">
                <?php if (activeBranch()): ?>
                    <?php if ($currentUser['role'] === 'owner'): ?>
                        <div id="branch-switcher-wrap" style="position: relative;">
                            <button type="button" id="branch-switcher-btn" onclick="var m=document.getElementById('branch-switcher-menu'),i=document.getElementById('branch-switcher-icon'); var isOpen=m.style.display==='block'; m.style.display=isOpen?'none':'block'; i.style.transform=isOpen?'':'rotate(180deg)';" class="btn btn-secondary btn-sm" style="height: 32px; padding: 0 12px; border-radius: 8px; font-weight: 600; display: flex; align-items: center; gap: 6px;">
                                <svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m2 7 4.38-2.92a2 2 0 0 1 2.22 0L13 7"/><path d="M2 7v14"/><path d="M22 7v14"/><path d="M22 21H2"/><path d="M13 7v14"/><path d="M7 21v-5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v5"/></svg>
                                <?= e(activeBranch()['name']) ?>
                                <svg id="branch-switcher-icon" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="transition: transform 0.2s;"><polyline points="6 9 12 15 18 9"></polyline></svg>
                            </button>
                            <div id="branch-switcher-menu" style="position: absolute; right: 0; top: calc(100% + 8px); background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 8px; min-width: 200px; z-index: 50; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); overflow: hidden; display: none;">
                                <?php 
                                    global $pdo;
                                    $branches = $pdo->query("SELECT * FROM branches WHERE is_active = 1")->fetchAll();
                                    foreach($branches as $b): 
                                ?>
                                    <form action="<?= url('/branches/switch') ?>" method="POST" style="margin: 0;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= $b['id'] ?>">
                                        <button type="submit" style="width: 100%; border: none; background: transparent; display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; color: var(--text-primary); text-decoration: none; border-bottom: 1px solid var(--border-color); font-size: 14px; cursor: pointer; text-align: left; <?= activeBranchId() == $b['id'] ? 'background: rgba(59, 130, 246, 0.1); font-weight: 600;' : '' ?>">
                                            <?= e($b['name']) ?>
                                            <?php if(activeBranchId() == $b['id']): ?>
                                                <svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                            <?php endif; ?>
                                        </button>
                                    </form>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <span class="badge badge-info" style="font-size:12px; padding:6px 12px; background: rgba(34,197,94,0.1); color: var(--accent-green); border-radius: 8px; display:flex; align-items:center;">
                            <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 4px;"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                            <?= e(activeBranch()['name']) ?>
                        </span>
                    <?php endif; ?>
                <?php endif; ?>
                <button type="button" id="pwa-install-btn-topbar" class="btn btn-secondary btn-sm pwa-install-trigger" style="display:none; height: 32px; padding: 0 10px; border-radius: 8px; font-weight: 600; align-items: center; gap: 6px; color: var(--accent-green); border-color: rgba(34,197,94,0.3);" title="Install Aplikasi Tether Brew">
                    <svg width="1.1em" height="1.1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="7 10 12 15 17 10"></polyline>
                        <line x1="12" y1="15" x2="12" y2="3"></line>
                    </svg>
                    <span>Install App</span>
                </button>
                <button class="btn btn-secondary btn-sm" onclick="switchTheme(event)" title="Toggle Theme" style="display:flex; align-items:center; justify-content:center; padding: 0 8px; height: 32px;">
                    <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"></path></svg>
                </button>
            </div>
        </div>

        <div class="page-content">
            <?= render_flash() ?>
            <?= $content ?>
        </div>
    </div>

    <script src="<?= base_url('assets/js/app.js') ?>?v=<?= filemtime(__DIR__ . '/../assets/js/app.js') ?>"></script>
    <script src="<?= base_url('assets/js/pwa-install.js') ?>?v=<?= filemtime(__DIR__ . '/../assets/js/pwa-install.js') ?>" defer></script>
    <?= $extraScripts ?? '' ?>
    <script>
        // Auto-hide flash messages
        setTimeout(function() {
            var flash = document.getElementById('flash-message');
            if (flash) { flash.style.opacity = '0'; flash.style.transform = 'translateY(-10px)'; setTimeout(function() { flash.remove(); }, 300); }
        }, 5000);

        // Branch switcher click outside
        document.addEventListener('click', function(e) {
            var wrap = document.getElementById('branch-switcher-wrap');
            var menu = document.getElementById('branch-switcher-menu');
            var icon = document.getElementById('branch-switcher-icon');
            if (wrap && menu && !wrap.contains(e.target)) {
                menu.style.display = 'none';
                if (icon) icon.style.transform = '';
            }
        });

        // PWA Service Worker Registration
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('<?= base_url('sw.js') ?>').catch(function() {});
            });
        }
    </script>
</body>
</html>
