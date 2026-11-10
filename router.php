<?php
/**
 * Router - URL routing untuk PHP Native
 * Mengarahkan URL ke file PHP yang sesuai
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = urldecode($uri);

$scriptName = $_SERVER['SCRIPT_NAME'];
$baseDir = str_replace('\\', '/', dirname($scriptName));

if ($baseDir !== '/' && strpos($uri, $baseDir) === 0) {
    $uri = substr($uri, strlen($baseDir));
}

$basename = basename($scriptName);
if (strpos($uri, '/' . $basename) === 0) {
    $uri = substr($uri, strlen('/' . $basename));
}

$uri = rtrim($uri, '/');
if ($uri === '') $uri = '/';
$method = $_SERVER['REQUEST_METHOD'];

// ==========================================
// Static route mapping
// ==========================================
$routes = [
    // Landing & Public
    '/' => 'index.php',
    
    // Auth
    '/login' => 'pages/auth/login.php',
    '/login/process' => 'pages/auth/login_process.php',
    '/register' => 'pages/auth/register.php',
    '/register/process' => 'pages/auth/register_process.php',
    '/logout' => 'pages/auth/logout.php',
    
    // Customer Auth
    '/customer/login' => 'pages/customer/auth/login.php',
    '/customer/login/process' => 'pages/customer/auth/login_process.php',
    '/customer/register' => 'pages/customer/auth/register.php',
    '/customer/register/process' => 'pages/customer/auth/register_process.php',
    '/customer/logout' => 'pages/auth/logout.php',
    '/customer/forgot-password' => 'pages/customer/auth/forgot_password.php',
    '/customer/forgot-password/process' => 'pages/customer/auth/forgot_password_process.php',
    '/customer/reset-password' => 'pages/customer/auth/reset_password.php',
    '/customer/reset-password/process' => 'pages/customer/auth/reset_password_process.php',
    
    // Customer App
    '/customer/dashboard' => 'pages/customer/dashboard.php',
    '/customer/menu' => 'pages/customer/menu.php',
    '/customer/chats' => 'pages/customer/chats.php',
    '/customer/chat_detail' => 'pages/customer/chat_detail.php',
    '/customer/me' => 'pages/customer/profile.php',
    
    // Dashboard
    '/dashboard' => 'pages/dashboard/index.php',
    '/owner/rider-performance' => 'pages/dashboard/owner_rider_detail.php',
    
    // Carts
    '/carts' => 'pages/carts/index.php',
    '/carts/create' => 'pages/carts/create.php',
    '/carts/store' => 'pages/carts/store.php',
    '/carts/edit' => 'pages/carts/edit.php',
    '/carts/update' => 'pages/carts/update.php',
    '/carts/delete' => 'pages/carts/delete.php',
    '/carts/delete-all' => 'pages/carts/delete_all.php',
    '/carts/toggle-status' => 'pages/carts/toggle_status.php',
    
    // Products
    '/products' => 'pages/products/index.php',
    '/products/create' => 'pages/products/create.php',
    '/products/store' => 'pages/products/store.php',
    '/products/edit' => 'pages/products/edit.php',
    '/products/update' => 'pages/products/update.php',
    '/products/delete' => 'pages/products/delete.php',
    '/products/delete-all' => 'pages/products/delete_all.php',
    '/admin/products' => 'pages/products/index.php',
    '/admin/products/create' => 'pages/products/create.php',
    '/admin/products/store' => 'pages/products/store.php',
    '/admin/products/edit' => 'pages/products/edit.php',
    '/admin/products/update' => 'pages/products/update.php',
    '/admin/products/delete' => 'pages/products/delete.php',
    '/admin/products/delete-all' => 'pages/products/delete_all.php',
    
    // Inventories
    '/inventories' => 'pages/inventories/index.php',
    '/inventories/update' => 'pages/inventories/update.php',
    
    // Transactions
    '/transactions' => 'pages/transactions/index.php',
    
    // Accounts (Owner only)
    '/accounts' => 'pages/accounts/index.php',
    '/accounts/create' => 'pages/accounts/create.php',
    '/accounts/store' => 'pages/accounts/store.php',
    '/accounts/edit' => 'pages/accounts/edit.php',
    '/accounts/update' => 'pages/accounts/update.php',
    '/accounts/delete' => 'pages/accounts/delete.php',
    '/accounts/delete-all' => 'pages/accounts/delete_all.php',
    
    // Branches (Owner only)
    '/branches' => 'pages/branches/index.php',
    '/branches/create' => 'pages/branches/create.php',
    '/branches/store' => 'pages/branches/store.php',
    '/branches/edit' => 'pages/branches/edit.php',
    '/branches/update' => 'pages/branches/update.php',
    '/branches/delete' => 'pages/branches/delete.php',
    '/branches/delete-all' => 'pages/branches/delete_all.php',
    '/branches/switch' => 'pages/branches/switch.php',
    
    // Riders
    '/riders' => 'pages/riders/index.php',
    '/riders/create' => 'pages/riders/create.php',
    '/riders/store' => 'pages/riders/store.php',
    '/riders/edit' => 'pages/riders/edit.php',
    '/riders/update' => 'pages/riders/update.php',
    '/riders/delete' => 'pages/riders/delete.php',
    '/riders/delete-all' => 'pages/riders/delete_all.php',
    '/riders/resign' => 'pages/riders/resign.php',
    '/riders/resign-process' => 'pages/riders/resign_process.php',
    
    // Bars
    '/bars' => 'pages/bars/index.php',
    '/bars/store' => 'pages/bars/store.php',
    '/bars/edit' => 'pages/bars/edit.php',
    '/bars/update' => 'pages/bars/update.php',
    '/bars/delete' => 'pages/bars/delete.php',
    '/bars/delete-all' => 'pages/bars/delete_all.php',
    
    // Rider POS & Features
    '/rider/pos' => 'pages/rider/pos.php',
    '/rider/pos/store' => 'pages/rider/pos_store.php',
    '/rider/transactions' => 'pages/rider/transactions.php',
    '/rider/location/update' => 'pages/rider/location_update.php',
    '/rider/location/live' => 'pages/rider/location_live.php',
    '/rider/stock/update' => 'pages/rider/stock_update.php',
    
    // Rider Chat
    '/rider/chat' => 'pages/rider/chat.php',
    '/rider/chat_detail' => 'pages/rider/chat_detail.php',
    '/rider/chat/show' => 'pages/rider/chat_detail.php',
    '/rider/chat/conversations-json' => 'pages/rider/chat/conversations_json.php',
    '/rider/chat/send' => 'pages/rider/chat/send.php',
    '/rider/chat/send-qris' => 'pages/rider/chat/send_qris.php',
    
    // Admin: Rider Sales
    '/rider-sales' => 'pages/rider_sales/index.php',
    '/rider-sales/create' => 'pages/rider_sales/create.php',
    '/rider-sales/store' => 'pages/rider_sales/store.php',
    '/rider-sales/edit' => 'pages/rider_sales/edit.php',
    '/rider-sales/update' => 'pages/rider_sales/update.php',
    '/rider-sales/delete' => 'pages/rider_sales/delete.php',
    '/rider-sales/delete-all' => 'pages/rider_sales/delete_all.php',
    '/rider-sales/confirm-journal' => 'pages/rider_sales/confirm_journal.php',
    '/rider-sales/rollback-journal' => 'pages/rider_sales/rollback_journal.php',
    '/rider-sales/available-stock' => 'pages/rider_sales/api_available_stock.php',
    
    // Admin: Rider Sales Report
    '/rider-sales-report' => 'pages/rider_sales_report/index.php',
    
    // Admin: Rider Finances
    '/rider-finances/kasbon' => 'pages/rider_finance/index.php',
    '/rider-finances/uang-makan' => 'pages/rider_finance/index.php',
    '/rider-finances/store' => 'pages/rider_finance/store.php',
    '/rider-finances/store-payment' => 'pages/rider_finance/store_payment.php',
    '/rider-finances/delete' => 'pages/rider_finance/delete.php',
    '/rider-finances/delete-all' => 'pages/rider_finance/delete_all.php',
    
    // Admin: Rider Minus
    '/rider-minus' => 'pages/rider_minus/index.php',
    
    // Admin: Office Kasbon
    '/admin/office-kasbon' => 'pages/office_kasbons/index.php',
    '/admin/office-kasbon/store' => 'pages/office_kasbons/store.php',
    '/admin/office-kasbon/delete' => 'pages/office_kasbons/delete.php',
    '/admin/office-kasbon/delete-all' => 'pages/office_kasbons/delete_all.php',
    '/admin/office-kasbon/payment' => 'pages/office_kasbons/store_payment.php',
    
    // Admin: Payroll
    '/payroll' => 'pages/payrolls/index.php',
    '/payroll/store' => 'pages/payrolls/store.php',
    '/payroll/show' => 'pages/payrolls/show.php',
    '/payroll/confirm' => 'pages/payrolls/confirm.php',
    '/payroll/rollback' => 'pages/payrolls/rollback.php',
    '/payroll/history' => 'pages/payrolls/history.php',
    
    // Admin: Journals
    '/journals' => 'pages/journals/index.php',
    '/journals/create' => 'pages/journals/create.php',
    '/journals/store' => 'pages/journals/store.php',
    '/journals/delete' => 'pages/journals/delete.php',
    '/journals/delete-all' => 'pages/journals/delete_all.php',
    '/journals/import' => 'pages/journals/import.php',
    '/journals/template' => 'pages/journals/template.php',
    
    // Admin: Journal Categories
    '/journal-categories' => 'pages/journal_categories/index.php',
    '/journal-categories/store' => 'pages/journal_categories/store.php',
    '/journal-categories/update' => 'pages/journal_categories/update.php',
    '/journal-categories/delete' => 'pages/journal_categories/delete.php',
    '/journal-categories/delete-all' => 'pages/journal_categories/delete_all.php',
    '/journal_categories' => 'pages/journal_categories/index.php',
    '/journal_categories/store' => 'pages/journal_categories/store.php',
    '/journal_categories/update' => 'pages/journal_categories/update.php',
    '/journal_categories/delete' => 'pages/journal_categories/delete.php',
    '/journal_categories/delete-all' => 'pages/journal_categories/delete_all.php',
    
    // Admin: Chat Monitoring
    '/admin/chats' => 'pages/chats/index.php',
    '/admin/chats/show' => 'pages/chats/show.php',
    
    // Admin: Settings
    '/admin/settings' => 'pages/settings/index.php',
    '/admin/settings/update' => 'pages/settings/update.php',
    '/settings' => 'pages/settings/index.php',
    '/settings/update' => 'pages/settings/update.php',
    
    // Admin: Productions
    '/admin/productions' => 'pages/productions/index.php',
    '/admin/productions/create' => 'pages/productions/create.php',
    '/admin/productions/store' => 'pages/productions/store.php',
    '/admin/productions/show' => 'pages/productions/show.php',
    '/admin/productions/edit' => 'pages/productions/edit.php',
    '/admin/productions/update' => 'pages/productions/update.php',
    '/admin/productions/delete' => 'pages/productions/delete.php',
    '/admin/productions/delete-all' => 'pages/productions/delete_all.php',
    '/admin/productions/add-stock' => 'pages/productions/add_stock.php',
    '/admin/productions/store-additional' => 'pages/productions/store_additional.php',
    
    // Admin: Spoiled Products
    '/admin/spoiled-products' => 'pages/spoiled_products/index.php',
    '/admin/spoiled-products/create' => 'pages/spoiled_products/create.php',
    '/admin/spoiled-products/store' => 'pages/spoiled_products/store.php',
    '/admin/spoiled-products/edit' => 'pages/spoiled_products/edit.php',
    '/admin/spoiled-products/update' => 'pages/spoiled_products/update.php',
    '/admin/spoiled-products/delete' => 'pages/spoiled_products/delete.php',
    '/admin/spoiled-products/delete-all' => 'pages/spoiled_products/delete_all.php',
    '/admin/spoiled_products' => 'pages/spoiled_products/index.php',
    '/admin/spoiled_products/create' => 'pages/spoiled_products/create.php',
    '/admin/spoiled_products/store' => 'pages/spoiled_products/store.php',
    '/admin/spoiled_products/edit' => 'pages/spoiled_products/edit.php',
    '/admin/spoiled_products/update' => 'pages/spoiled_products/update.php',
    '/admin/spoiled_products/delete' => 'pages/spoiled_products/delete.php',
    '/admin/spoiled_products/delete-all' => 'pages/spoiled_products/delete_all.php',
    
    // Admin: Available Stocks
    '/admin/available-stocks' => 'pages/available_stocks/index.php',
    '/admin/available_stocks' => 'pages/available_stocks/index.php',
    
    // Admin: Production Plan
    '/admin/production-plan' => 'pages/production_plan/index.php',
    '/admin/production_plan' => 'pages/production_plan/index.php',
    
    // Artikel (Public)
    '/artikel' => 'pages/artikel/index.php',
    '/artikel/show' => 'pages/artikel/show.php',
    
    // Artikel (Admin)
    '/admin/artikel' => 'pages/artikel/admin.php',
    '/admin/artikel/create' => 'pages/artikel/create.php',
    '/admin/artikel/store' => 'pages/artikel/store.php',
    '/admin/artikel/edit' => 'pages/artikel/edit.php',
    '/admin/artikel/update' => 'pages/artikel/update.php',
    '/admin/artikel/delete' => 'pages/artikel/delete.php',
    '/admin/artikel/delete-all' => 'pages/artikel/delete_all.php',
    '/artikel/admin' => 'pages/artikel/admin.php',
    '/artikel/create' => 'pages/artikel/create.php',
    '/artikel/store' => 'pages/artikel/store.php',
    '/artikel/edit' => 'pages/artikel/edit.php',
    '/artikel/update' => 'pages/artikel/update.php',
    '/artikel/delete' => 'pages/artikel/delete.php',
    '/artikel/delete-all' => 'pages/artikel/delete_all.php',
    
    // API Endpoints
    '/api/carts-map' => 'api/carts_map.php',
    '/api/rider-cups' => 'api/rider_cups.php',
    '/api/chat/start' => 'pages/api/chat/start.php',
    '/api/chat/conversations' => 'pages/api/chat/conversations.php',
    '/api/chat/messages' => 'pages/api/chat/messages.php',
    '/api/chat/send' => 'pages/api/chat/send.php',
    '/api/chat/send-qris' => 'pages/api/chat/send_qris.php',
    '/api/chat/read' => 'pages/api/chat/mark_read.php',
    
    // SEO
    '/sitemap.xml' => 'pages/sitemap.php',
    '/robots.txt' => 'pages/robots.php',
];

// ==========================================
// Route matching
// ==========================================
if (isset($routes[$uri])) {
    $file = __DIR__ . '/' . $routes[$uri];
    if (file_exists($file)) {
        require $file;
        exit;
    }
}

// ==========================================
// 404 - Not Found
// ==========================================
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Halaman Tidak Ditemukan</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; background: #0f172a; color: #e2e8f0; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .container { text-align: center; padding: 2rem; }
        h1 { font-size: 6rem; font-weight: 800; background: linear-gradient(135deg, #f59e0b, #d97706); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        p { font-size: 1.25rem; color: #94a3b8; margin: 1rem 0 2rem; }
        a { display: inline-block; padding: 0.75rem 2rem; background: linear-gradient(135deg, #f59e0b, #d97706); color: #0f172a; text-decoration: none; border-radius: 0.5rem; font-weight: 600; transition: transform 0.2s; }
        a:hover { transform: translateY(-2px); }
    </style>
</head>
<body>
    <div class="container">
        <h1>404</h1>
        <p>Halaman yang Anda cari tidak ditemukan.</p>
        <a href="/">Kembali ke Beranda</a>
    </div>
</body>
</html>
