<?php
// Who may delete saves.
//
// If config.php defines CLOUDVMU_ADMIN_PASSWORD_HASH, deleting requires
// logging in with that password (login.php). Without it, deleting is only
// allowed from loopback/private network addresses, which keeps a DreamPi or
// LAN install working out of the box while a public host stays read/upload-only.
//
// config.php is gitignored; copy config.example.php to create it.

// Log errors, never show them to visitors (they leak server paths).
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if (is_file(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

function cloudvmu_password_configured() {
    return defined('CLOUDVMU_ADMIN_PASSWORD_HASH') && CLOUDVMU_ADMIN_PASSWORD_HASH !== '';
}

function cloudvmu_is_private_client() {
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    if ($ip === '') return false;
    // NO_PRIV_RANGE|NO_RES_RANGE rejects private and loopback addresses, so a
    // valid IP that fails the filter is a local one.
    return filter_var($ip, FILTER_VALIDATE_IP) !== false
        && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
}

function cloudvmu_can_delete() {
    if (cloudvmu_password_configured()) {
        return !empty($_SESSION['cloudvmu_admin']);
    }
    return cloudvmu_is_private_client();
}

function cloudvmu_csrf_token() {
    if (empty($_SESSION['cloudvmu_csrf'])) {
        $_SESSION['cloudvmu_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['cloudvmu_csrf'];
}

function cloudvmu_csrf_valid($token) {
    return is_string($token) && !empty($_SESSION['cloudvmu_csrf'])
        && hash_equals($_SESSION['cloudvmu_csrf'], $token);
}

// Escape VMU-supplied text for the Shift-JIS page.
function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'Shift_JIS');
}
