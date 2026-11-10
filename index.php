<?php
/**
 * Landing Page / Homepage
 * Full conversion from welcome.blade.php (Laravel lama)
 */
require_once __DIR__ . '/core/bootstrap.php';

// Get latest articles
$artikels = $pdo->query("SELECT * FROM artikels WHERE is_published = 1 ORDER BY published_at DESC LIMIT 3")->fetchAll();

// Get active products
$products = $pdo->query("SELECT * FROM products WHERE is_active = 1 ORDER BY sort_order, name")->fetchAll();

$isLoggedIn = auth_check();
$currentUser = $isLoggedIn ? auth_user() : null;
$isCustomer = $isLoggedIn && isset($currentUser['role']) && $currentUser['role'] === 'customer';
?>
<!DOCTYPE html>
<html lang="id" class="light-theme">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- ===== SEO META TAGS & AIO/GEO (AI Generative Engine Optimization) ===== -->
    <title>Tether Brew Kopi Keliling Medan Sekitar | Kopi Segar Mulai Rp 8.000</title>
    <meta name="description" content="Cari kopi keliling Medan sekitar? Tether Brew adalah pelopor kopi keliling premium di Medan dengan harga murah mulai Rp 8.000. Temukan gerobak terdekat secara realtime sekarang!">
    <meta name="keywords" content="kopi keliling, kopi keliling medan, kopi keliling medan sekitar, kopi keliling terdekat medan, kopi medan, kopi segar medan, gerobak kopi medan, tether brew, es kopi medan">
    <meta name="author" content="Tether Brew">
    
    <!-- AI & Search Engine Crawlers -->
    <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">
    <!-- Explicitly allow major AI crawlers -->
    <meta name="googlebot" content="index, follow">
    <meta name="bingbot" content="index, follow">
    <meta name="OAI-SearchBot" content="index, follow"> <!-- ChatGPT Search -->
    <meta name="ChatGPT-User" content="index, follow"> <!-- ChatGPT Plugins/Web -->
    <meta name="anthropic-ai" content="index, follow"> <!-- Claude -->
    <meta name="CCBot" content="index, follow"> <!-- Common Crawl (used by many LLMs) -->
    <meta name="PerplexityBot" content="index, follow"> <!-- Perplexity AI -->
    
    <link rel="canonical" href="<?= base_url() ?>">

    <!-- ===== GEO META (Medan, Sumatera Utara) ===== -->
    <meta name="geo.region" content="ID-SU">
    <meta name="geo.placename" content="Medan">
    <meta name="geo.position" content="3.5952;98.6722">
    <meta name="ICBM" content="3.5952, 98.6722">

    <!-- ===== OPEN GRAPH ===== -->
    <meta property="og:type" content="website">
    <meta property="og:locale" content="id_ID">
    <meta property="og:site_name" content="Tether Brew">
    <meta property="og:title" content="Tether Brew – Kopi Keliling Medan Sekitar">
    <meta property="og:description" content="Cari kopi keliling Medan sekitar? Tether Brew adalah pilihan kopi keliling premium mulai Rp 8.000! Cek lokasi gerobak terdekat di peta.">
    <meta property="og:url" content="<?= base_url() ?>">
    <meta property="og:image" content="<?= base_url('assets/images/favicon.webp') ?>">
    <meta property="og:image:width" content="512">
    <meta property="og:image:height" content="512">
    <meta property="og:image:alt" content="Tether Brew - Kopi Keliling Medan">

    <!-- ===== TWITTER CARD ===== -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Tether Brew – Kopi Keliling Medan Sekitar">
    <meta name="twitter:description" content="Cari kopi keliling Medan sekitar? Temukan gerobak kopi keliling Tether Brew terdekat via peta realtime! Harga mulai 8rb.">
    <meta name="twitter:image" content="<?= base_url('assets/images/favicon.webp') ?>">

    <!-- ===== JSON-LD STRUCTURED DATA (Enhanced for AI / GEO) ===== -->
    <script type="application/ld+json">
    <?= json_encode([
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'FoodEstablishment',
                '@id' => base_url() . '#foodestablishment',
                'name' => 'Tether Brew',
                'alternateName' => 'Kopi Keliling Medan',
                'description' => 'Tether Brew adalah pelopor kopi keliling di Medan sekitarnya. Menyajikan minuman premium secara mobile melalui gerobak kopi keliling terdekat, dengan harga murah mulai Rp 8.000. Kami membawa pengalaman kafe langsung ke lokasi Anda.',
                'url' => base_url(),
                'logo' => base_url('assets/images/favicon.webp'),
                'image' => base_url('assets/images/favicon.webp'),
                'telephone' => '+6282379742368',
                'servesCuisine' => ['Kopi', 'Minuman', 'Coffee', 'Non-Coffee', 'Cold Brew'],
                'priceRange' => 'Rp 8.000 - Rp 15.000',
                'currenciesAccepted' => 'IDR',
                'paymentAccepted' => 'Cash, Transfer, QRIS',
                'address' => [
                    '@type' => 'PostalAddress',
                    'addressLocality' => 'Medan',
                    'addressRegion' => 'Sumatera Utara',
                    'addressCountry' => 'ID',
                ],
                'geo' => [
                    '@type' => 'GeoCoordinates',
                    'latitude' => 3.5952,
                    'longitude' => 98.6722,
                ],
                'areaServed' => [
                    '@type' => 'City',
                    'name' => 'Medan',
                ],
                'hasMenu' => [
                    '@type' => 'Menu',
                    'hasMenuSection' => [
                        [
                            '@type' => 'MenuSection',
                            'name' => 'Kopi',
                            'hasMenuItem' => [
                                ['@type' => 'MenuItem', 'name' => 'Cold Brew', 'offers' => ['@type' => 'Offer', 'price' => '15000', 'priceCurrency' => 'IDR']],
                                ['@type' => 'MenuItem', 'name' => 'Honey Brew', 'offers' => ['@type' => 'Offer', 'price' => '15000', 'priceCurrency' => 'IDR']],
                                ['@type' => 'MenuItem', 'name' => 'Americano', 'offers' => ['@type' => 'Offer', 'price' => '8000', 'priceCurrency' => 'IDR']],
                                ['@type' => 'MenuItem', 'name' => 'Caramel Brew', 'offers' => ['@type' => 'Offer', 'price' => '12000', 'priceCurrency' => 'IDR']],
                                ['@type' => 'MenuItem', 'name' => 'Vanilla Brew', 'offers' => ['@type' => 'Offer', 'price' => '12000', 'priceCurrency' => 'IDR']],
                            ],
                        ],
                        [
                            '@type' => 'MenuSection',
                            'name' => 'Non-Kopi',
                            'hasMenuItem' => [
                                ['@type' => 'MenuItem', 'name' => 'Matcha Brew', 'offers' => ['@type' => 'Offer', 'price' => '12000', 'priceCurrency' => 'IDR']],
                                ['@type' => 'MenuItem', 'name' => 'Cokelat Brew', 'offers' => ['@type' => 'Offer', 'price' => '12000', 'priceCurrency' => 'IDR']],
                                ['@type' => 'MenuItem', 'name' => 'Taro Brew', 'offers' => ['@type' => 'Offer', 'price' => '12000', 'priceCurrency' => 'IDR']],
                            ],
                        ],
                    ],
                ],
                'sameAs' => ['https://www.instagram.com/tetherbrew/']
            ],
            [
                '@type' => 'FAQPage',
                '@id' => base_url() . '#faq',
                'mainEntity' => [
                    [
                        '@type' => 'Question',
                        'name' => 'Apa itu Tether Brew?',
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text' => 'Tether Brew adalah layanan kopi keliling premium di Medan sekitarnya yang menyajikan kopi segar dan berkualitas langsung dari gerobak dengan harga mulai dari Rp 8.000.'
                        ]
                    ],
                    [
                        '@type' => 'Question',
                        'name' => 'Dimana lokasi Tether Brew terdekat di Medan?',
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text' => 'Gerobak kopi keliling Tether Brew tersebar di berbagai titik di kota Medan. Anda dapat melacak lokasi gerobak terdekat secara realtime melalui website resmi kami.'
                        ]
                    ],
                    [
                        '@type' => 'Question',
                        'name' => 'Berapa harga kopi di Tether Brew?',
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text' => 'Harga kopi di Tether Brew sangat terjangkau, mulai dari Rp 8.000 untuk Americano hingga Rp 15.000 untuk Cold Brew.'
                        ]
                    ]
                ]
            ],
            [
                '@type' => 'WebSite',
                '@id' => base_url() . '#website',
                'url' => base_url(),
                'name' => 'Tether Brew Kopi Keliling',
                'description' => 'Kopi keliling premium Medan sekitar',
                'publisher' => [
                    '@id' => base_url() . '#foodestablishment'
                ]
            ]
        ]
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?>
    </script>

    <!-- ===== FAVICON & PWA ===== -->
    <link rel="manifest" href="<?= base_url('manifest.webmanifest') ?>">
    <link rel="icon" type="image/webp" href="<?= base_url('assets/images/tether-icon-head.webp') ?>">
    <link rel="apple-touch-icon" href="<?= base_url('assets/images/pwa-192.png') ?>">
    <meta name="theme-color" content="#16a34a">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Tether Brew">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://unpkg.com" crossorigin>
    <link rel="preload" as="image" href="<?= base_url('uploads/image/kopi-tether-new.webp') ?>" fetchpriority="high">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" media="print" onload="this.media='all'" />
    <noscript><link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"></noscript>
    <link rel="stylesheet" href="<?= base_url('assets/css/landing.css') ?>?v=<?= filemtime(__DIR__ . '/assets/css/landing.css') ?>">
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
                const newTheme = document.documentElement.classList.contains('light-theme') ? 'light' : 'dark';
                localStorage.setItem('theme', newTheme);
                window.dispatchEvent(new CustomEvent('theme-changed', { detail: { theme: newTheme } }));
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
<body data-customer-id="<?= $isCustomer ? $currentUser['id'] : '' ?>" data-customer-name="<?= $isCustomer ? e($currentUser['name']) : '' ?>">

    <!-- ===== NAVBAR ===== -->
    <nav class="navbar" id="navbar">
        <div class="navbar-menu" id="navbar-menu">
            <a href="#home">Beranda</a>
            <a href="#about">Tentang</a>
            <a href="#menu">Menu</a>
            <a href="#maps">Lokasi</a>
            <a href="#contact">Kontak</a>
            <?php if ($isCustomer): ?>
                <a href="<?= url('customer/dashboard') ?>">Dashboard App</a>
            <?php endif; ?>
            <button type="button" id="pwa-install-btn-nav" class="btn-pwa-nav pwa-install-trigger" style="display:none;" title="Install Aplikasi Tether Brew">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                <span>Install App</span>
            </button>
            <button onclick="window.switchTheme(event, () => { const el = document.getElementById('theme-icon-dark'); const el2 = document.getElementById('theme-icon-light'); if(el) el.style.display = el.style.display === 'none' ? 'block' : 'none'; if(el2) el2.style.display = el2.style.display === 'none' ? 'block' : 'none'; })" class="theme-toggle-btn-landing" id="theme-toggle-desktop">
                <svg id="theme-icon-dark-desktop" width="1.4em" height="1.4em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
                    <circle cx="12" cy="12" r="5"/><line x1="12" x2="12" y1="1" y2="3"/><line x1="12" x2="12" y1="21" y2="23"/><line x1="4.22" x2="5.64" y1="4.22" y2="5.64"/><line x1="18.36" x2="19.78" y1="18.36" y2="19.78"/><line x1="1" x2="3" y1="12" y2="12"/><line x1="21" x2="23" y1="12" y2="12"/><line x1="4.22" x2="5.64" y1="19.78" y2="18.36"/><line x1="18.36" x2="19.78" y1="5.64" y2="4.22"/>
                </svg>
                <svg id="theme-icon-light-desktop" width="1.4em" height="1.4em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                </svg>
            </button>
        </div>
        <button class="mobile-toggle" id="mobile-toggle">☰</button>
    </nav>

    <!-- ===== MOBILE THEME TOGGLE ===== -->
    <div class="mobile-theme-toggle-wrapper">
        <button onclick="window.switchTheme(event)" class="theme-toggle-btn-landing" id="theme-toggle-mobile">
            <svg class="theme-icon-sun" width="1.4em" height="1.4em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
                <circle cx="12" cy="12" r="5"/><line x1="12" x2="12" y1="1" y2="3"/><line x1="12" x2="12" y1="21" y2="23"/><line x1="4.22" x2="5.64" y1="4.22" y2="5.64"/><line x1="18.36" x2="19.78" y1="18.36" y2="19.78"/><line x1="1" x2="3" y1="12" y2="12"/><line x1="21" x2="23" y1="12" y2="12"/><line x1="4.22" x2="5.64" y1="19.78" y2="18.36"/><line x1="18.36" x2="19.78" y1="5.64" y2="4.22"/>
            </svg>
            <svg class="theme-icon-moon" width="1.4em" height="1.4em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
            </svg>
        </button>
    </div>

    <!-- ===== HOME / HERO ===== -->
    <section class="hero" id="home">
        <div class="hero-bg">
            <img src="<?= base_url('uploads/image/kopi-tether-new.webp') ?>" alt="Tether Brew Coffee Background" fetchpriority="high" decoding="async">
        </div>
        <div class="hero-content">
            <div class="hero-badge">
                <span class="badge-dot"></span>
                Tether Brew · Kopi Keliling Medan
            </div>
            <h1>Kopi Premium, Segar & Murah di<br><span class="highlight">Medan</span> dari Tether Brew</h1>
            <p>Mencari kopi keliling Medan sekitar? Temukan gerobak kopi keliling premium kami yang tersebar di kota. Segar, berkualitas, dan terjangkau mulai Rp 8.000.</p>
            <div class="hero-cta">
                <div class="hero-cta-row">
                    <a href="#maps" class="btn-hero btn-hero-primary">
                        <svg class="icon-two-tone" width="1.4em" height="1.4em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                            <circle cx="12" cy="10" r="3" />
                        </svg>
                        Lihat Peta
                    </a>
                    <?php if ($isCustomer): ?>
                        <a href="#menu" class="btn-hero btn-hero-secondary">
                            <svg class="icon-two-tone" width="1.4em" height="1.4em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 8h1a4 4 0 0 1 0 8h-1" />
                                <path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z" />
                                <line x1="6" x2="6" y1="1" y2="4" />
                                <line x1="10" x2="10" y1="1" y2="4" />
                                <line x1="14" x2="14" y1="1" y2="4" />
                            </svg>
                            Lihat Menu
                        </a>
                    <?php elseif ($isLoggedIn): ?>
                        <a href="#menu" class="btn-hero btn-hero-secondary">
                            <svg class="icon-two-tone" width="1.4em" height="1.4em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 8h1a4 4 0 0 1 0 8h-1" />
                                <path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z" />
                                <line x1="6" x2="6" y1="1" y2="4" />
                                <line x1="10" x2="10" y1="1" y2="4" />
                                <line x1="14" x2="14" y1="1" y2="4" />
                            </svg>
                            Lihat Menu
                        </a>
                    <?php else: ?>
                        <a href="<?= url('customer/login') ?>" class="btn-hero btn-hero-secondary">
                            <svg class="icon-two-tone" width="1.4em" height="1.4em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
                                <polyline points="10 17 15 12 10 7" />
                                <line x1="15" x2="3" y1="12" y2="12" />
                            </svg>
                            Login
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="hero-stats">
                <div>
                    <div class="hero-stat-value" id="stat-carts">-</div>
                    <div class="hero-stat-label">Gerobak Aktif</div>
                </div>
                <div>
                    <div class="hero-stat-value" id="stat-menus">-</div>
                    <div class="hero-stat-label">Varian Menu</div>
                </div>
                <div>
                    <div class="hero-stat-value">8K</div>
                    <div class="hero-stat-label">Mulai Dari</div>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== MAPS ===== -->
    <section class="map-section section-relative" id="maps">
        <div class="deco-bg-wrapper">
            <img src="<?= base_url('uploads/image/kopi-tether-new.webp') ?>" class="deco-cup deco-cup-1" alt="" loading="lazy">
        </div>
        <div class="section-header">
            <div class="section-tag">LOKASI</div>
            <h2 class="section-title">Temukan Gerobak Terdekat</h2>
            <p class="section-subtitle">Klik marker hijau di peta untuk melihat detail menu dan stok yang tersedia</p>
        </div>
        <div class="map-wrapper">
            <div class="map-overlay">
                <div class="dot" id="map-live-dot"></div>
                <span id="cart-count-label">Memuat data...</span>
            </div>
            <button type="button" id="locate-me-btn" class="map-locate-btn" onclick="locateNearestCart()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M12 2v3m0 14v3M2 12h3m14 0h3"/></svg>
                <span id="locate-me-label">Cari Terdekat</span>
            </button>
            <div id="map"></div>
        </div>
    </section>

    <!-- ===== MENU ===== -->
    <section class="menu-section section-relative" id="menu">
        <div class="deco-bg-wrapper">
            <img src="<?= base_url('uploads/image/kopi-tether-new.webp') ?>" class="deco-cup deco-cup-2" alt="" loading="lazy">
        </div>
        <div class="section-header">
            <div class="section-tag">MENU</div>
            <h2 class="section-title">Menu Tether Brew</h2>
            <p class="section-subtitle">Nikmati berbagai varian kopi dan minuman segar pilihan kami</p>
        </div>

        <div class="menu-tabs">
            <button class="menu-tab active" data-category="semua">Semua</button>
            <button class="menu-tab" data-category="kopi"><svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:-0.25em; margin-right:4px;"><path d="M18 8h1a4 4 0 0 1 0 8h-1"/><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"/><line x1="6" y1="1" x2="6" y2="4"/><line x1="10" y1="1" x2="10" y2="4"/><line x1="14" y1="1" x2="14" y2="4"/></svg> Coffee</button>
            <button class="menu-tab" data-category="non-kopi"><svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:-0.25em; margin-right:4px;"><path d="M6 9l1.5 11.5A2 2 0 0 0 9.5 22h5a2 2 0 0 0 2-1.5L18 9" /><line x1="4" y1="9" x2="20" y2="9" /><path d="M5 9 A 7 5 0 0 1 19 9" /><line x1="12" y1="4" x2="14" y2="0" /></svg> Non-Coffee</button>
        </div>

        <div class="menu-grid" id="menu-grid">
            <!-- Filled by JS -->
        </div>
    </section>

    <!-- ===== GEROBAK LIST ===== -->
    <section class="gerobak-section" id="gerobak">
        <div class="section-header">
            <div class="section-tag">GEROBAK</div>
            <h2 class="section-title">Gerobak Aktif</h2>
            <p class="section-subtitle">Klik kartu untuk zoom ke lokasi gerobak di peta</p>
        </div>
        <div class="gerobak-grid" id="cart-grid">
            <div class="empty-state-text">
                Memuat data gerobak...
            </div>
        </div>
        <div class="gerobak-pagination" id="cart-pagination"></div>
    </section>

    <!-- ===== ARTIKEL ===== -->
    <?php if (!empty($artikels)): ?>
    <section class="artikel-section section-relative" id="artikel">
        <div class="deco-bg-wrapper">
            <img src="<?= base_url('uploads/image/kopi-tether-new.webp') ?>" class="deco-cup deco-cup-4" alt="" loading="lazy">
        </div>
        <div class="section-header">
            <div class="section-tag">ARTIKEL</div>
            <h2 class="section-title">Cerita & Tips Kopi</h2>
            <p class="section-subtitle">Temukan inspirasi, tips menyeduh, dan cerita di balik setiap cangkir kopi Tether Brew</p>
        </div>
        <div class="artikel-grid">
            <?php foreach ($artikels as $item): ?>
            <article class="artikel-card">
                <div class="artikel-card-img">
                    <img src="<?= $item['cover_image'] ? base_url('uploads/' . $item['cover_image']) : base_url('uploads/image/kopi-tether-new.webp') ?>" alt="<?= e($item['title']) ?>" loading="lazy">
                    <span class="artikel-card-category"><?= e($item['category']) ?></span>
                </div>
                <div class="artikel-card-body">
                    <div class="artikel-card-meta">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        <span><?= $item['published_at'] ? date('d F Y', strtotime($item['published_at'])) : date('d F Y', strtotime($item['created_at'])) ?></span>
                    </div>
                    <h3 class="artikel-card-title"><?= e($item['title']) ?></h3>
                    <p class="artikel-card-excerpt"><?= e($item['excerpt']) ?></p>
                    <a href="<?= url('artikel/show?slug=' . e($item['slug'])) ?>" class="artikel-card-link">
                        Baca Selengkapnya
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    </a>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <div class="artikel-cta">
            <a href="<?= url('artikel') ?>" class="btn-hero btn-hero-secondary">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                Lihat Semua Artikel
            </a>
        </div>
    </section>
    <?php endif; ?>

    <!-- ===== ABOUT ===== -->
    <section class="about-section section-relative" id="about">
        <div class="deco-bg-wrapper">
            <img src="<?= base_url('uploads/image/kopi-tether-new.webp') ?>" class="deco-cup deco-cup-3" alt="" loading="lazy">
        </div>
        <div class="section-header">
            <div class="section-tag">TENTANG</div>
            <h2 class="section-title">Kenapa Pilih Tether Brew?</h2>
            <p class="section-subtitle">Pelopor kopi keliling Medan sekitar! Sensasi kopi premium yang langsung meluncur ke lokasimu</p>
        </div>
        <div class="about-grid">
            <div class="about-card">
                <div class="about-icon">
                    <svg class="icon-two-tone" width="1.5em" height="1.5em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 8h1a4 4 0 0 1 0 8h-1"/><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"/><line x1="6" x2="6" y1="1" y2="4"/><line x1="10" x2="10" y1="1" y2="4"/><line x1="14" x2="14" y1="1" y2="4"/>
                    </svg>
                </div>
                <h3>Kopi Segar Berkualitas</h3>
                <p>Biji kopi pilihan dari petani lokal Sumatera Utara, diolah fresh setiap hari untuk menghasilkan kopi terbaik di Medan.</p>
            </div>
            <div class="about-card">
                <div class="about-icon">
                    <svg class="icon-two-tone" width="1.5em" height="1.5em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 12V8a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V15"/><rect x="16" y="12" width="6" height="3" rx="1"/>
                    </svg>
                </div>
                <h3>Harga Murah Meriah</h3>
                <p>Mulai dari Rp 8.000 saja! Kopi premium khas Medan yang bisa dinikmati semua kalangan tanpa menguras dompet.</p>
            </div>
            <div class="about-card">
                <div class="about-icon">
                    <svg class="icon-two-tone" width="1.5em" height="1.5em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>
                    </svg>
                </div>
                <h3>Keliling & Selalu Dekat</h3>
                <p>Gerobak kopi kami keliling di berbagai lokasi strategis di Medan. Cek peta realtime untuk menemukan yang terdekat!</p>
            </div>
        </div>
    </section>

    <!-- ===== CONTACT ===== -->
    <section class="contact-section" id="contact">
        <div class="contact-inner">
            <h3>Hubungi Kami</h3>
            <p>Mau jadi mitra Tether Brew? Atau ada pertanyaan? Jangan ragu untuk menghubungi kami.</p>
            <div class="contact-links">
                <a target="_blank" href="https://wa.me/6282379742368" class="contact-link"><svg width="1.2em" height="1.2em"
                        viewBox="0 0 24 24" fill="currentColor" style="display:inline-block; vertical-align:-0.2em; margin-right:4px;">
                        <path
                            d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z" />
                    </svg> WhatsApp</a>
                <a href="mailto:tethebrew@gmail.com" class="contact-link"><svg width="1.2em" height="1.2em" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        style="display:inline-block; vertical-align:-0.2em; margin-right:4px;">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                        <polyline points="22,6 12,13 2,6"></polyline>
                    </svg> Email</a>
                <a target="_blank" href="https://www.instagram.com/tetherbrew/" class="contact-link"><svg width="1.2em" height="1.2em"
                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round" style="display:inline-block; vertical-align:-0.2em; margin-right:4px;">
                        <rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect>
                        <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path>
                        <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line>
                    </svg> Instagram</a>
            </div>
        </div>
    </section>

    <!-- ===== BOTTOM NAV MOBILE ===== -->
    <nav class="bottom-nav">
        <div class="bottom-nav-inner">
            <a href="#home" class="bottom-nav-item active" data-section="home">
                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline>
                </svg>
                <span>Beranda</span>
            </a>
            <a href="#maps" class="bottom-nav-item" data-section="maps">
                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle>
                </svg>
                <span>Lokasi</span>
            </a>
            <a href="#menu" class="bottom-nav-item" data-section="menu">
                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 8h1a4 4 0 0 1 0 8h-1"></path><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"></path><line x1="6" y1="1" x2="6" y2="4"></line><line x1="10" y1="1" x2="10" y2="4"></line><line x1="14" y1="1" x2="14" y2="4"></line>
                </svg>
                <span>Menu</span>
            </a>
            <a href="#about" class="bottom-nav-item" data-section="about">
                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line>
                </svg>
                <span>Tentang</span>
            </a>
            <?php if ($isCustomer): ?>
                <a href="<?= url('customer/dashboard') ?>" class="bottom-nav-item">
                    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    <span>Dashboard</span>
                </a>
            <?php else: ?>
                <a href="#contact" class="bottom-nav-item" data-section="contact">
                    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                    </svg>
                    <span>Kontak</span>
                </a>
            <?php endif; ?>
        </div>
    </nav>

    <!-- ===== PWA INSTALL BANNER (Mobile / Popup) ===== -->
    <div id="pwa-install-banner" class="pwa-install-banner" style="display:none;">
        <div class="pwa-banner-card">
            <div class="pwa-banner-left">
                <img src="<?= base_url('assets/images/pwa-192.png') ?>" alt="Tether Brew" class="pwa-banner-logo" width="40" height="40">
                <div class="pwa-banner-info">
                    <strong>Install Tether Brew</strong>
                    <span>Pesan kopi lebih cepat & praktis</span>
                </div>
            </div>
            <div class="pwa-banner-right">
                <button type="button" id="pwa-banner-install" class="btn-pwa-install">Install</button>
                <button type="button" id="pwa-banner-close" class="btn-pwa-close" aria-label="Tutup Banner">✕</button>
            </div>
        </div>
    </div>

    <!-- ===== ORDER PANEL (Slide-up) ===== -->
    <div id="order-panel" class="order-panel">
        <div class="order-panel-backdrop" id="order-backdrop"></div>
        <div class="order-panel-sheet" id="order-sheet">
            <div class="order-panel-handle"><div class="handle-bar"></div></div>
            <div class="order-panel-header">
                <div>
                    <h3 id="order-cart-name">Nama Gerobak</h3>
                    <p id="order-rider-name" class="order-rider">Rider: -</p>
                    <div id="order-distance" class="order-distance"></div>
                </div>
                <button id="order-close-btn" class="order-close-btn">✕</button>
            </div>
            <div id="order-no-rider-alert" class="order-no-rider-alert" style="display:none;"></div>
            <div class="order-panel-body" id="order-menu-list">
                <!-- Menu items rendered by JS -->
            </div>
            <div class="order-notes-wrapper">
                <label for="order-notes">Catatan Pesanan</label>
                <textarea id="order-notes" class="order-notes" placeholder="Cth: Less ice.." rows="2"></textarea>
            </div>
            <div class="order-panel-footer">
                <div class="order-summary">
                    <div class="order-total-row">
                        <span>Total</span>
                        <span id="order-total-price" class="order-total-price">Rp 0</span>
                    </div>
                    <div id="order-item-count" class="order-item-count">0 item dipilih</div>
                </div>
                <div class="order-action-buttons">
                    <a id="order-nav-btn" href="#" target="_blank" class="order-nav-btn">
                        <svg class="icon-two-tone" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"
                            fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round">
                            <polygon points="3 11 22 2 13 21 11 13 3 11"></polygon>
                        </svg>
                        Arahkan
                    </a>
                    <button id="order-loc-btn" type="button" class="order-loc-btn">
                        <svg class="icon-two-tone" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"
                            fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                            <circle cx="12" cy="10" r="3"></circle>
                        </svg>
                        <span id="order-loc-btn-text">Kirim Lokasi</span>
                    </button>
                </div>
                <div class="order-action-buttons" style="margin-top: 8px;">
                    <a id="order-wa-btn" href="#" target="_blank" class="order-wa-btn disabled">
                        <svg class="icon-two-tone" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"
                            fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round">
                            <path
                                d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z" />
                        </svg>
                        <span id="order-wa-btn-text">Pesan via WhatsApp</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== FOOTER ===== -->
    <footer class="footer">
        <p>&copy; <?= date('Y') ?> Tether Brew.</p>
    </footer>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" defer></script>
    <script>
        window.__catalogMenus = <?= json_encode(array_map(fn($p) => [
            'name'        => $p['name'],
            'description' => $p['description'],
            'price'       => (float)$p['price'],
            'category'    => $p['category'],
            'image'       => $p['image'],
        ], $products), JSON_UNESCAPED_UNICODE) ?>;
    </script>
    <script src="<?= base_url('assets/js/landing.js') ?>?v=<?= filemtime(__DIR__ . '/assets/js/landing.js') ?>" defer></script>
    <script src="<?= base_url('assets/js/pwa-install.js') ?>?v=<?= filemtime(__DIR__ . '/assets/js/pwa-install.js') ?>" defer></script>
<style>
@keyframes slideUp {
    from { opacity: 0; transform: translateX(-50%) translateY(20px); }
    to { opacity: 1; transform: translateX(-50%) translateY(0); }
}
</style>

<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function() {
            navigator.serviceWorker.register('<?= base_url('sw.js') ?>').catch(function() {});
        });
    }
</script>
</body>
</html>
