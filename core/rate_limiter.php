<?php
/**
 * Rate Limiter - File-based login throttling
 * Membatasi percobaan login untuk mencegah brute force attack
 * 
 * Storage: File JSON di /storage/rate_limits/
 */

/**
 * Get the storage directory for rate limit files
 */
function rate_limit_storage_path() {
    $path = __DIR__ . '/../storage/rate_limits';
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }
    return $path;
}

/**
 * Generate a safe filename from the rate limit key
 */
function rate_limit_filename($key) {
    return rate_limit_storage_path() . '/' . md5($key) . '.json';
}

/**
 * Get rate limit data for a key
 * @param string $key Unique identifier (e.g., IP + login type)
 * @return array ['attempts' => int, 'last_attempt' => int, 'locked_until' => int]
 */
function rate_limit_get($key) {
    $file = rate_limit_filename($key);
    
    if (!file_exists($file)) {
        return [
            'attempts' => 0,
            'last_attempt' => 0,
            'locked_until' => 0,
        ];
    }
    
    $data = json_decode(file_get_contents($file), true);
    
    if (!$data) {
        return [
            'attempts' => 0,
            'last_attempt' => 0,
            'locked_until' => 0,
        ];
    }
    
    return $data;
}

/**
 * Save rate limit data for a key
 */
function rate_limit_save($key, $data) {
    $file = rate_limit_filename($key);
    file_put_contents($file, json_encode($data), LOCK_EX);
}

/**
 * Record a failed login attempt
 * @param string $key Unique identifier
 * @param int $maxAttempts Maximum allowed attempts before lockout
 * @param int $decaySeconds Lockout duration in seconds
 */
function rate_limit_hit($key, $maxAttempts = 5, $decaySeconds = 300) {
    $data = rate_limit_get($key);
    $now = time();
    
    // If previous lockout has expired, reset attempts
    if ($data['locked_until'] > 0 && $now >= $data['locked_until']) {
        $data['attempts'] = 0;
        $data['locked_until'] = 0;
    }
    
    $data['attempts']++;
    $data['last_attempt'] = $now;
    
    // Lock if max attempts reached
    if ($data['attempts'] >= $maxAttempts) {
        $data['locked_until'] = $now + $decaySeconds;
    }
    
    rate_limit_save($key, $data);
}

/**
 * Check if rate limit is exceeded (locked out)
 * @param string $key Unique identifier
 * @param int $maxAttempts Maximum allowed attempts
 * @param int $decaySeconds Lockout duration in seconds
 * @return bool True if locked out
 */
function rate_limit_exceeded($key, $maxAttempts = 5, $decaySeconds = 300) {
    $data = rate_limit_get($key);
    $now = time();
    
    // If lockout has expired, reset and allow
    if ($data['locked_until'] > 0 && $now >= $data['locked_until']) {
        rate_limit_clear($key);
        return false;
    }
    
    // Still locked
    if ($data['locked_until'] > 0 && $now < $data['locked_until']) {
        return true;
    }
    
    return false;
}

/**
 * Get remaining lockout time in seconds
 * @param string $key Unique identifier
 * @return int Remaining seconds, 0 if not locked
 */
function rate_limit_remaining_seconds($key) {
    $data = rate_limit_get($key);
    $now = time();
    
    if ($data['locked_until'] > 0 && $now < $data['locked_until']) {
        return $data['locked_until'] - $now;
    }
    
    return 0;
}

/**
 * Get remaining attempts before lockout
 * @param string $key Unique identifier
 * @param int $maxAttempts Maximum allowed attempts
 * @return int Remaining attempts
 */
function rate_limit_remaining_attempts($key, $maxAttempts = 5) {
    $data = rate_limit_get($key);
    $now = time();
    
    // If lockout expired, full attempts available
    if ($data['locked_until'] > 0 && $now >= $data['locked_until']) {
        return $maxAttempts;
    }
    
    return max(0, $maxAttempts - $data['attempts']);
}

/**
 * Clear rate limit for a key (e.g., after successful login)
 * @param string $key Unique identifier
 */
function rate_limit_clear($key) {
    $file = rate_limit_filename($key);
    if (file_exists($file)) {
        unlink($file);
    }
}

/**
 * Generate a rate limit key based on IP and an identifier
 * @param string $identifier Additional identifier (e.g., 'admin_login', 'customer_login')
 * @return string
 */
function rate_limit_key($identifier = 'login') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    return $identifier . ':' . $ip;
}

/**
 * Clean up expired rate limit files (garbage collection)
 * Call this periodically (e.g., via cron or randomly on requests)
 */
function rate_limit_cleanup() {
    $path = rate_limit_storage_path();
    $now = time();
    
    foreach (glob($path . '/*.json') as $file) {
        // Remove files older than 1 hour
        if ($now - filemtime($file) > 3600) {
            unlink($file);
        }
    }
}

/**
 * Format remaining seconds into human-readable Indonesian text
 * @param int $seconds
 * @return string
 */
function rate_limit_format_time($seconds) {
    if ($seconds <= 0) return '0 detik';
    
    $minutes = floor($seconds / 60);
    $secs = $seconds % 60;
    
    if ($minutes > 0 && $secs > 0) {
        return $minutes . ' menit ' . $secs . ' detik';
    } elseif ($minutes > 0) {
        return $minutes . ' menit';
    } else {
        return $secs . ' detik';
    }
}
