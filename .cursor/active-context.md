> **BrainSync Context Pumper** 🧠
> Dynamically loaded for active file: `pages\transactions\index.php` (Domain: **Generic Logic**)

### 📐 Generic Logic Conventions & Fixes
- **[what-changed] 🟢 Edited pages/transactions/index.php (46 changes, 168min)**: Active editing session on pages/transactions/index.php.
46 content changes over 168 minutes.
- **[what-changed] what-changed in index.php**: File updated (external): index.php

Content summary (644 lines):
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

