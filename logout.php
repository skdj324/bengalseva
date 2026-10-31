<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
Auth::logout($pdo);
setFlash('info', 'You have been safely logged out.');
header('Location: ' . APP_URL . '/login.php');
exit;
