<?php
/**
 * CSRF Protection
 */

/**
 * Generate and return CSRF token
 */
function csrf_token() {
    if (!isset($_SESSION['_token'])) {
        $_SESSION['_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_token'];
}

/**
 * Return hidden input field with CSRF token
 */
function csrf_field() {
    return '<input type="hidden" name="_token" value="' . csrf_token() . '">';
}

/**
 * Verify CSRF token from POST request
 */
function csrf_verify() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

    $token = $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

    if (empty($_SESSION['_token']) || !hash_equals($_SESSION['_token'], (string)$token)) {
        http_response_code(419);
        $isJson = !empty($_SERVER['HTTP_X_CSRF_TOKEN'])
            || strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false
            || strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false;

        if ($isJson) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Sesi kedaluwarsa (CSRF invalid). Silakan muat ulang halaman.']);
        } else {
            echo '<h1>419 - Sesi Kedaluwarsa</h1>';
            echo '<p>Token CSRF tidak valid. Silakan muat ulang halaman.</p>';
            echo '<a href="javascript:history.back()">Kembali</a>';
        }
        exit;
    }
}
