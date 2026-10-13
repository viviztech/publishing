<?php
declare(strict_types=1);

namespace App\Core;

final class Auth
{
    public static function attempt(string $email, string $password): bool
    {
        $stmt = Database::connection()->prepare('SELECT u.*, r.slug role FROM admin_users u JOIN roles r ON r.id=u.role_id WHERE u.email=? AND u.status="active" LIMIT 1');
        $stmt->execute([mb_strtolower(trim($email))]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) return false;
        session_regenerate_id(true);
        unset($user['password_hash']);
        $_SESSION['user'] = $user;
        $_SESSION['last_activity'] = time();
        Database::connection()->prepare('UPDATE admin_users SET last_login_at=NOW() WHERE id=?')->execute([$user['id']]);
        activity('login', 'Signed in');
        return true;
    }

    public static function user(): ?array { return $_SESSION['user'] ?? null; }
    public static function id(): ?int { return isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null; }

    public static function check(): bool
    {
        if (!self::user()) return false;
        $ttl = (int)Env::get('SESSION_LIFETIME', '7200');
        if (time() - (int)($_SESSION['last_activity'] ?? 0) > $ttl) { self::logout(); return false; }
        $statement=Database::connection()->prepare('SELECT u.id,u.role_id,u.name,u.email,u.status,u.created_at,r.slug role FROM admin_users u JOIN roles r ON r.id=u.role_id WHERE u.id=? AND u.status="active"');
        $statement->execute([self::id()]);$current=$statement->fetch();
        if(!$current){self::logout();return false;}
        $_SESSION['user']=$current;
        $_SESSION['last_activity'] = time();
        return true;
    }

    public static function require(array $roles = []): void
    {
        if (!self::check()) redirect('/admin/login');
        if ($roles && !in_array(self::user()['role'], $roles, true)) abort(403, 'You do not have permission to perform this action.');
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }
}
