<?php
/**
 * Guard / Role-based access control
 * Pengganti Laravel middleware('role:...')
 */

/**
 * Require user to be authenticated, redirect to login if not
 */
function require_auth() {
    if (!auth_check()) {
        $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'];
        redirect('/login');
    }
}

/**
 * Require user to have one of the specified roles
 */
function require_role(...$roles) {
    require_auth();
    $user = auth_user();
    if (!in_array($user['role'], $roles)) {
        http_response_code(403);
        echo '<h1>403 - Akses Ditolak</h1>';
        echo '<p>Anda tidak memiliki izin untuk mengakses halaman ini.</p>';
        echo '<a href="' . url('/dashboard') . '">Kembali ke Dashboard</a>';
        exit;
    }
}

/**
 * Shortcut: require owner role
 */
function require_owner() {
    require_role('owner');
}

/**
 * Shortcut: require admin or owner role
 */
function require_admin_or_owner() {
    require_role('owner', 'admin');
}

/**
 * Shortcut: require bar, admin, or owner role
 */
function require_bar_admin_owner() {
    require_role('owner', 'admin', 'bar');
}

/**
 * Shortcut: require rider role
 */
function require_rider() {
    require_role('rider');
}

/**
 * Shortcut: require customer role
 */
function require_customer() {
    require_role('customer');
}

/**
 * Require guest (not authenticated) — for login/register pages
 */
function require_guest() {
    if (auth_check()) {
        $user = auth_user();
        if ($user['role'] === 'customer') {
            redirect('/customer/dashboard');
        } else {
            redirect('/dashboard');
        }
    }
}
