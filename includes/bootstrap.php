<?php
declare(strict_types=1);

/**
 * BengalSeva Master Initialization Bootstrap
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/security_helpers.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/i18n.php';

// Hardened Shared Hosting Session Settings
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', 'Lax');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', '1');
    }
    session_name(SESSION_NAME);
    session_start();
}

// Initialize I18n Engine
I18n::init();

// Global PDO Connection Instance
$pdo = Database::getConnection();

require_once __DIR__ . '/auth.php';
