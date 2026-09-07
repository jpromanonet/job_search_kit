<?php

declare(strict_types=1);

require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/data.php';
require __DIR__ . '/includes/auth.php';

auth_start();
if (current_user()) {
    header('Location: ' . url('/index.php?tab=dashboard'));
    exit;
}

$error = '';
$defaultNext = url('/index.php?tab=dashboard');
$next = (string) ($_GET['next'] ?? $defaultNext);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = (string) ($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $next = (string) ($_POST['next'] ?? $next);
    try {
        $user = attempt_login($email, $password);
        if (!$user) {
            $error = 'Email o contraseña incorrectos.';
        } else {
            login_user($user);
            $target = $defaultNext;
            if ($next !== '' && (str_starts_with($next, base_path() . '/') || str_starts_with($next, 'http://') || str_starts_with($next, 'https://'))) {
                $target = $next;
            } elseif ($next !== '' && str_starts_with($next, '/') && base_path() === '') {
                $target = $next;
            }
            header('Location: ' . $target);
            exit;
        }
    } catch (Throwable $e) {
        $error = 'No se pudo conectar a MySQL. Corré install.php primero.';
    }
}

$config = require __DIR__ . '/config.php';
$appName = $config['app']['name'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Entrar · <?= e($appName) ?></title>
  <link href="<?= e(url('/assets/css/quest.css')) ?>?v=<?= e((string) filemtime(__DIR__ . '/assets/css/quest.css')) ?>" rel="stylesheet">
</head>
<body class="auth-body">
  <main class="auth-shell">
    <section class="auth-card panel">
      <div class="auth-hero">
        <?php require __DIR__ . '/includes/wizard.php'; ?>
        <h1><?= e($appName) ?></h1>
        <p class="muted">Un mago pixel te guía en la aventura de conseguir trabajo. Entrá al mapa.</p>
      </div>
      <?php if ($error !== ''): ?>
        <div class="flash flash-danger"><?= e($error) ?></div>
      <?php endif; ?>
      <form method="post" class="stack">
        <input type="hidden" name="next" value="<?= e($next) ?>">
        <div class="field">
          <label for="email">Email</label>
          <input id="email" type="email" name="email" required autocomplete="username" value="<?= e((string) ($_POST['email'] ?? '')) ?>">
        </div>
        <div class="field">
          <label for="password">Contraseña</label>
          <input id="password" type="password" name="password" required autocomplete="current-password">
        </div>
        <button type="submit" class="btn btn-accent">Empezar la campaña</button>
      </form>
    </section>
  </main>
</body>
</html>
