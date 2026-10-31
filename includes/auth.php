<?php
declare(strict_types=1);

final class Auth
{
    public static function attempt(PDO $pdo, string $email, string $password): bool
    {
        $stmt = $pdo->prepare('SELECT id, full_name, email, password_hash, role, status FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if (!$user) {
            return false;
        }

        if ($user['status'] !== 'active') {
            return false;
        }

        if (password_verify($password, $user['password_hash'])) {
            // Secure Session Regeneration
            session_regenerate_id(true);

            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];

            // Update last login
            $update = $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
            $update->execute([':id' => $user['id']]);

            // Audit
            logAudit($pdo, (int)$user['id'], 'USER_LOGIN_SUCCESS', 'users', (int)$user['id'], 'User logged in successfully.');
            return true;
        }

        logAudit($pdo, null, 'USER_LOGIN_FAILED', 'users', null, 'Failed login attempt for: ' . substr($email, 0, 80));
        return false;
    }

    public static function check(): bool
    {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_role']);
    }

    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }
        return [
            'id'    => (int)$_SESSION['user_id'],
            'name'  => $_SESSION['user_name'],
            'email' => $_SESSION['user_email'],
            'role'  => $_SESSION['user_role'],
        ];
    }

    public static function role(): ?string
    {
        return $_SESSION['user_role'] ?? null;
    }

    public static function requireRole(array $allowedRoles): void
    {
        if (!self::check()) {
            setFlash('danger', 'Please login to access this section.');
            header('Location: ' . APP_URL . '/login.php');
            exit;
        }

        $currentRole = self::role();
        if (!in_array($currentRole, $allowedRoles, true)) {
            setFlash('danger', __('unauthorized'));
            header('Location: ' . APP_URL . '/portal/dashboard.php');
            exit;
        }
    }

    public static function logout(PDO $pdo): void
    {
        if (self::check()) {
            logAudit($pdo, (int)$_SESSION['user_id'], 'USER_LOGOUT', 'users', (int)$_SESSION['user_id'], 'User logged out.');
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
    }
}
