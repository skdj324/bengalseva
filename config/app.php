<?php
declare(strict_types=1);

define('APP_NAME', 'BengalSeva');
define('APP_TAGLINE', 'West Bengal\'s Premier Service & Commerce Marketplace');
define('APP_URL', 'https://searchkaro.midexa.in');
define('APP_ENV', 'production'); // 'production' or 'development'
define('DEFAULT_LANG', 'bn');
define('SESSION_NAME', 'BENGALSEVA_SESSID');

// Supported Languages
define('SUPPORTED_LANGUAGES', ['en' => 'English', 'bn' => 'বাংলা', 'hi' => 'हिन्दी']);

// All 11 Enforced Roles
define('SYSTEM_ROLES', [
    'super_admin',
    'admin',
    'manager',
    'agent',
    'vendor',
    'provider',
    'rider',
    'customer',
    'accountant',
    'support',
    'hr'
]);
