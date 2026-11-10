<?php
/**
 * Authentication helper functions
 * Pengganti Laravel Auth:: facade
 */

/**
 * Get current authenticated user data from session
 */
function auth_user() {
    global $pdo;
    if (!isset($_SESSION['user_id'])) return null;

    $needsRefresh = !isset($_SESSION['user_data'])
        || $_SESSION['user_data']['id'] != $_SESSION['user_id']
        || !isset($_SESSION['user_checked_at'])
        || (time() - $_SESSION['user_checked_at']) > 60;

    if ($needsRefresh) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        if (!$user || (int)($user['is_active'] ?? 1) !== 1) {
            unset($_SESSION['user_id'], $_SESSION['user_data'], $_SESSION['user_checked_at'], $_SESSION['active_branch_id']);
            return null;
        }
        $_SESSION['user_data'] = $user;
        $_SESSION['user_checked_at'] = time();
    }

    return $_SESSION['user_data'];
}

/**
 * Get current user ID
 */
function auth_id() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Check if user is authenticated
 */
function auth_check() {
    return auth_user() !== null;
}

/**
 * Login user by ID
 */
function auth_login($userId) {
    global $pdo;
    $_SESSION['user_id'] = $userId;
    
    // Load user data into session
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    $_SESSION['user_data'] = $user;
    
    // Set active branch
    if ($user) {
        if ($user['role'] === 'owner') {
            // Owner gets first active branch
            $branchStmt = $pdo->query("SELECT id FROM branches WHERE is_active = 1 ORDER BY id ASC LIMIT 1");
            $branch = $branchStmt->fetch();
            $_SESSION['active_branch_id'] = $branch ? $branch['id'] : null;
        } else {
            $_SESSION['active_branch_id'] = $user['branch_id'];
        }
    }
    
    $_SESSION['user_checked_at'] = time();
    $_SESSION['_token'] = bin2hex(random_bytes(32));

    // Regenerate session ID for security
    session_regenerate_id(true);
}

/**
 * Logout user
 */
function auth_logout() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

/**
 * Attempt to authenticate with email and password
 * Returns user array on success, false on failure
 */
function auth_attempt($email, $password) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        return $user;
    }
    
    return false;
}

/**
 * Attempt to authenticate customer with phone and password
 * Returns user array on success, false on failure
 */
function auth_attempt_phone($phone, $password) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE phone = ? AND role = 'customer' AND is_active = 1");
    $stmt->execute([$phone]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        return $user;
    }
    
    return false;
}

/**
 * Refresh user data in session (after profile update etc)
 */
function auth_refresh() {
    global $pdo;
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $_SESSION['user_data'] = $stmt->fetch();
        $_SESSION['user_checked_at'] = time();
    }
}
