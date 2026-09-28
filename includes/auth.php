<?php

declare(strict_types=1);

function auth_session_save_path(): ?string
{
    $candidates = [
        dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'sessions',
        rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'jobkit_sessions',
    ];
    foreach ($candidates as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        if (!is_dir($dir)) {
            continue;
        }
        @chmod($dir, 0777);
        $probe = $dir . DIRECTORY_SEPARATOR . '.write';
        if (@file_put_contents($probe, '1') === false) {
            continue;
        }
        @unlink($probe);
        return $dir;
    }
    return null;
}

function auth_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        auth_touch_cookie();
        return;
    }

    $lifetime = 86400;
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $savePath = auth_session_save_path();
    if ($savePath !== null) {
        session_save_path($savePath);
    }
    ini_set('session.gc_maxlifetime', (string) $lifetime);
    ini_set('session.cookie_lifetime', (string) $lifetime);
    session_name('jobkit_session');
    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start([
        'cookie_lifetime' => $lifetime,
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure' => $secure,
        'use_strict_mode' => true,
        'use_only_cookies' => true,
    ]);
    auth_touch_cookie($lifetime, $secure);
}

function auth_touch_cookie(?int $lifetime = null, ?bool $secure = null): void
{
    if (session_status() !== PHP_SESSION_ACTIVE || session_id() === '') {
        return;
    }
    $lifetime = $lifetime ?? 86400;
    $lifetime = max(86400, $lifetime);
    if ($secure === null) {
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    }
    $params = session_get_cookie_params();
    setcookie(session_name(), session_id(), [
        'expires' => time() + $lifetime,
        'path' => $params['path'] ?: '/',
        'domain' => $params['domain'] ?? '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_SESSION['_last_activity'] = time();
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
    auth_touch_cookie();
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
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $p['path'] ?: '/',
            'domain' => $p['domain'] ?? '',
            'secure' => (bool) $p['secure'],
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
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
        throw new InvalidArgumentException('Datos de usuario invÃ¡lidos.');
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