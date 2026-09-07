<?php

declare(strict_types=1);

function auth_start(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function current_user(): ?array
{
    auth_start();
    $id = (int) ($_SESSION['user_id'] ?? 0);
    if ($id < 1) {
        return null;
    }
    static $cache = null;
    if (is_array($cache) && (int) ($cache['id'] ?? 0) === $id) {
        return $cache;
    }
    try {
        $stmt = db()->prepare(
            'SELECT u.id, u.email, u.name, u.role, u.is_active,
                    p.headline, p.linkedin_url, p.website_url, p.portfolio_url, p.x_url, p.instagram_url, p.phone,
                    p.location, p.bio, p.preferred_market
             FROM users u
             LEFT JOIN profiles p ON p.user_id = u.id
             WHERE u.id = ? AND u.is_active = 1
             LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        $cache = $row ?: null;
        return $cache;
    } catch (Throwable $e) {
        return null;
    }
}

function require_login(): array
{
    $user = current_user();
    if ($user) {
        return $user;
    }
    $next = $_SERVER['REQUEST_URI'] ?? '/index.php';
    header('Location: ' . url('/login.php?next=' . rawurlencode($next)));
    exit;
}

function login_user(array $user): void
{
    auth_start();
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    try {
        $stmt = db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?');
        $stmt->execute([(int) $user['id']]);
    } catch (Throwable $e) {
        // ignore
    }
}

function logout_user(): void
{
    auth_start();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], (bool) $p['secure'], (bool) $p['httponly']);
    }
    session_destroy();
}

function attempt_login(string $email, string $password): ?array
{
    $email = strtolower(trim($email));
    if ($email === '' || $password === '') {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($password, (string) $user['password_hash'])) {
        return null;
    }
    return $user;
}

function create_user(string $email, string $password, string $name, string $role = 'user'): int
{
    $email = strtolower(trim($email));
    $name = trim($name);
    if ($email === '' || $name === '' || strlen($password) < 8) {
        throw new InvalidArgumentException('Datos de usuario inválidos.');
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $ins = $pdo->prepare(
            'INSERT INTO users (email, password_hash, name, role) VALUES (?, ?, ?, ?)'
        );
        $ins->execute([$email, $hash, $name, $role]);
        $id = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO profiles (user_id) VALUES (?)')->execute([$id]);
        $pdo->prepare(
            'INSERT INTO campaign_settings (user_id, target_applications, apps_per_day) VALUES (?, 465, 5)'
        )->execute([$id]);
        $pdo->prepare(
            'INSERT INTO ats_lakes (user_id, language, body_text) VALUES (?, "es", ""), (?, "en", "")'
        )->execute([$id, $id]);
        seed_user_document_groups($pdo, $id);
        $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
