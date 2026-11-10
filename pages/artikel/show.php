<?php
require_once __DIR__ . '/../../core/bootstrap.php';
$slug = $_GET['slug'] ?? null; if(!$slug) redirect('/artikel');

$stmt = $pdo->prepare("SELECT a.*, u.name as author_name FROM artikels a JOIN users u ON a.user_id = u.id WHERE a.slug=? AND a.is_published=1");
$stmt->execute([$slug]); $artikel = $stmt->fetch();
if(!$artikel) redirect('/artikel');

$read_time = $artikel['read_time'] ?? estimateReadTime($artikel['content']);
?>
<!DOCTYPE html>
<html lang="id" class="light-theme">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($artikel['title']) ?> – Tether Brew</title>
    <meta name="description" content="<?= e($artikel['excerpt']) ?>">
    <meta name="keywords" content="artikel kopi, <?= e($artikel['category']) ?>, kopi keliling medan, tether brew">
    <meta name="author" content="<?= e($artikel['author_name']) ?>">
    <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">
    <link rel="canonical" href="<?= url('/artikel/show?slug=' . e($artikel['slug'])) ?>">

    <!-- Open Graph -->
    <meta property="og:type" content="article">
    <meta property="og:title" content="<?= e($artikel['title']) ?> – Tether Brew">
    <meta property="og:description" content="<?= e($artikel['excerpt']) ?>">
    <meta property="og:url" content="<?= url('/artikel/show?slug=' . e($artikel['slug'])) ?>">
    <meta property="og:image" content="<?= e(artikel_cover_url($artikel['cover_image'])) ?>">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($artikel['title']) ?> – Tether Brew">
    <meta name="twitter:description" content="<?= e($artikel['excerpt']) ?>">
    <meta name="twitter:image" content="<?= e(artikel_cover_url($artikel['cover_image'])) ?>">

    <!-- JSON-LD STRUCTURED DATA -->
    <script type="application/ld+json">
    <?= json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $artikel['title'],
        'image' => artikel_cover_url($artikel['cover_image']),
        'author' => [
            '@type' => 'Person',
            'name' => $artikel['author_name']
        ],
        'publisher' => [
            '@type' => 'Organization',
            'name' => 'Tether Brew',
            'logo' => [
                '@type' => 'ImageObject',
                'url' => base_url('assets/images/favicon.webp')
            ]
        ],
        'datePublished' => $artikel['published_at'] ?? $artikel['created_at'],
        'description' => $artikel['excerpt']
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?>
    </script>

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
        <a href="<?= url('/artikel') ?>" class="artikel-back-btn">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
            Semua Artikel
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

    <!-- ===== ARTICLE DETAIL ===== -->
    <article class="artikel-detail">
        <!-- Hero Image -->
        <div class="artikel-detail-hero">
            <img src="<?= e(artikel_cover_url($artikel['cover_image'])) ?>" alt="<?= e($artikel['title']) ?>" loading="lazy">
            <div class="artikel-detail-hero-overlay"></div>
            <div class="artikel-detail-hero-content">
                <span class="artikel-card-category"><?= e($artikel['category']) ?></span>
                <h1 class="artikel-detail-title"><?= e($artikel['title']) ?></h1>
                <div class="artikel-detail-meta">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <span><?= $artikel['published_at'] ? date('d F Y', strtotime($artikel['published_at'])) : date('d F Y', strtotime($artikel['created_at'])) ?></span>
                    <span class="artikel-detail-meta-dot">·</span>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <span><?= $read_time ?> menit baca</span>
                </div>
            </div>
        </div>

        <!-- Article Body -->
        <div class="artikel-detail-body">
            <div class="artikel-detail-content">
                <?= $artikel['content'] ?>
            </div>

            <!-- Share & Back -->
            <div class="artikel-detail-footer">
                <a href="<?= url('/artikel') ?>" class="btn-hero btn-hero-secondary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                    Kembali ke Daftar Artikel
                </a>
                <a href="<?= base_url() ?>" class="btn-hero btn-hero-primary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                    Beranda
                </a>
            </div>
        </div>
    </article>

    <!-- ===== FOOTER ===== -->
    <footer class="footer">
        <p>© <?= date('Y') ?> Tether Brew.</p>
    </footer>

</body>
</html>
