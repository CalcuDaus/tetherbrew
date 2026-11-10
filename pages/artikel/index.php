<?php
require_once __DIR__ . '/../../core/bootstrap.php';
$query = "SELECT a.*, u.name as author_name FROM artikels a JOIN users u ON a.user_id = u.id WHERE a.is_published = 1 ORDER BY a.published_at DESC";
$pagination = paginate($pdo, $query, [], 9);
$artikels = $pagination['data'];
?>
<!DOCTYPE html>
<html lang="id" class="light-theme">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Artikel – Tether Brew | Cerita & Tips Kopi Keliling Medan</title>
    <meta name="description" content="Baca artikel terbaru dari Tether Brew tentang dunia kopi, tips menyeduh, dan cerita di balik kopi keliling Medan.">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?= url('/artikel') ?>">
    <link rel="icon" type="image/webp" href="<?= base_url('assets/images/tether-icon-head.webp') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="<?= base_url('assets/css/landing.css') ?>">
    <script>
        (function() {
            const theme = localStorage.getItem('theme') || 'light';
            if (theme === 'dark') {
                document.documentElement.classList.remove('light-theme');
            }
        })();

        window.switchTheme = async (event, callback) => {
            const x = event.clientX;
            const y = event.clientY;
            const endRadius = Math.hypot(
                Math.max(x, innerWidth - x),
                Math.max(y, innerHeight - y)
            );

            if (!document.startViewTransition || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                if (callback) callback();
                document.documentElement.classList.toggle('light-theme');
                localStorage.setItem('theme', document.documentElement.classList.contains('light-theme') ? 'light' : 'dark');
                return;
            }

            const transition = document.startViewTransition(() => {
                if (callback) callback();
                document.documentElement.classList.toggle('light-theme');
                localStorage.setItem('theme', document.documentElement.classList.contains('light-theme') ? 'light' : 'dark');
            });

            await transition.ready;

            document.documentElement.animate(
                {
                    clipPath: [
                        `circle(0px at ${x}px ${y}px)`,
                        `circle(${endRadius}px at ${x}px ${y}px)`
                    ]
                },
                {
                    duration: 500,
                    easing: 'ease-in-out',
                    pseudoElement: '::view-transition-new(root)'
                }
            );
        };
    </script>
</head>
<body x-data="{ darkMode: (localStorage.getItem('theme') || 'light') !== 'light' }">

    <!-- ===== SIMPLE TOP BAR ===== -->
    <div class="artikel-page-topbar">
        <a href="<?= base_url() ?>" class="artikel-back-btn">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
            Kembali
        </a>
        <button @click="window.switchTheme($event, () => darkMode = !darkMode)" class="theme-toggle-btn-landing">
            <template x-if="darkMode">
                <svg width="1.4em" height="1.4em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="5"/><line x1="12" x2="12" y1="1" y2="3"/><line x1="12" x2="12" y1="21" y2="23"/><line x1="4.22" x2="5.64" y1="4.22" y2="5.64"/><line x1="18.36" x2="19.78" y1="18.36" y2="19.78"/><line x1="1" x2="3" y1="12" y2="12"/><line x1="21" x2="23" y1="12" y2="12"/><line x1="4.22" x2="5.64" y1="19.78" y2="18.36"/><line x1="18.36" x2="19.78" y1="5.64" y2="4.22"/>
                </svg>
            </template>
            <template x-if="!darkMode">
                <svg width="1.4em" height="1.4em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                </svg>
            </template>
        </button>
    </div>

    <!-- ===== ARTIKEL PAGE HERO ===== -->
    <section class="artikel-page-hero">
        <div class="section-header">
            <div class="section-tag">ARTIKEL</div>
            <h1 class="section-title" style="font-size: clamp(28px, 4vw, 44px);">Semua Artikel</h1>
            <p class="section-subtitle">Jelajahi cerita, tips, dan insight seputar dunia kopi dari Tether Brew</p>
        </div>
    </section>

    <!-- ===== ALL ARTIKEL ===== -->
    <section class="artikel-page-content">
        <?php if(!empty($artikels)): ?>
        <div class="artikel-page-grid">
            <?php foreach($artikels as $item): ?>
            <article class="artikel-card">
                <div class="artikel-card-img">
                    <img src="<?= e(artikel_cover_url($item['cover_image'])) ?>" alt="<?= e($item['title']) ?>" loading="lazy">
                    <span class="artikel-card-category"><?= e($item['category']) ?></span>
                </div>
                <div class="artikel-card-body">
                    <div class="artikel-card-meta">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        <span><?= $item['published_at'] ? date('d F Y', strtotime($item['published_at'])) : date('d F Y', strtotime($item['created_at'])) ?></span>
                    </div>
                    <h3 class="artikel-card-title"><?= e($item['title']) ?></h3>
                    <p class="artikel-card-excerpt"><?= e($item['excerpt']) ?></p>
                    <a href="<?= url('/artikel/show?slug=' . e($item['slug'])) ?>" class="artikel-card-link">
                        Baca Selengkapnya
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    </a>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div style="text-align: center; padding: 80px 20px; color: var(--text-muted);">
            <p style="font-size: 18px; font-weight: 600;">Belum ada artikel</p>
            <p style="margin-top: 8px;">Artikel akan segera hadir. Nantikan!</p>
        </div>
        <?php endif; ?>
    </section>

    <!-- ===== FOOTER ===== -->
    <footer class="footer">
        <p>© <?= date('Y') ?> Tether Brew.</p>
    </footer>

</body>
</html>
