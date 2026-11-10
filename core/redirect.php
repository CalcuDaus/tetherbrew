<?php
/**
 * Redirect helpers
 */

/**
 * Redirect to a URL
 */
function redirect($url) {
    if (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0) {
        header('Location: ' . $url);
    } else {
        header('Location: ' . url($url));
    }
    exit;
}

/**
 * Redirect back to the previous page
 * @param string|null $flashType Flash type (success/error)
 * @param string|null $flashMessage Flash message
 */
function redirect_back($flashType = null, $flashMessage = null) {
    if ($flashType && $flashMessage) {
        flash($flashType, $flashMessage);
    }
    
    $referer = $_SERVER['HTTP_REFERER'] ?? '/dashboard';
    redirect($referer);
}

/**
 * Redirect with a flash message
 */
function redirect_with($url, $type, $message) {
    flash($type, $message);
    redirect($url);
}

/**
 * Redirect to intended URL (after login) or default
 */
function redirect_intended($default = '/dashboard') {
    $url = $_SESSION['intended_url'] ?? $default;
    unset($_SESSION['intended_url']);
    redirect($url);
}

/**
 * Redirect back with validation errors and old input
 */
function redirect_back_with_errors($errors) {
    $_SESSION['validation_errors'] = $errors;
    store_old_input();
    redirect_back();
}
