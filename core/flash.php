<?php
/**
 * Flash messages (success/error yang tampil sekali)
 */

/**
 * Set a flash message
 * @param string $type 'success', 'error', 'warning', 'info'
 * @param string $message
 */
function flash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

/**
 * Get and clear flash message
 * @return array|null ['type' => ..., 'message' => ...]
 */
function get_flash() {
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

/**
 * Check if flash message exists
 */
function has_flash() {
    return isset($_SESSION['flash']);
}

/**
 * Render flash message as HTML
 */
function render_flash() {
    $flash = get_flash();
    if (!$flash) return '';
    
    $type = $flash['type'];
    // Validasi icon SweetAlert
    if (!in_array($type, ['success', 'error', 'warning', 'info', 'question'])) {
        $type = 'info';
    }
    
    $message = e($flash['message']);
    
    return "<script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof showToast === 'function') {
                showToast('$type', '$message');
            } else if (typeof Swal !== 'undefined') {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: '$type',
                    title: '$message',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true
                });
            }
        });
    </script>";
}
