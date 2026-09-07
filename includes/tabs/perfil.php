<?php

declare(strict_types=1);

/** @var array $user */

$name = (string) ($user['name'] ?? '');
$headline = (string) ($user['headline'] ?? '');
$linkedin = (string) ($user['linkedin_url'] ?? '');
$website = (string) ($user['website_url'] ?? '');
$portfolio = (string) ($user['portfolio_url'] ?? '');
$xUrl = (string) ($user['x_url'] ?? '');
$instagram = (string) ($user['instagram_url'] ?? '');
$phone = (string) ($user['phone'] ?? '');
$location = (string) ($user['location'] ?? '');
$bio = (string) ($user['bio'] ?? '');
$email = (string) ($user['email'] ?? '');
$firstName = trim((string) explode(' ', $name !== '' ? $name : 'mago')[0]);
?>
<section class="profile-hero">
  <?php require __DIR__ . '/../wizard.php'; ?>
  <div class="profile-hero__copy">
    <p class="eyebrow">Tu ficha</p>
    <h1><?= e($name !== '' ? $name : 'Sin nombre') ?></h1>
    <?php if ($headline !== ''): ?>
      <p class="profile-hero__headline"><?= e($headline) ?></p>
    <?php else: ?>
      <p class="muted">Todavía no hay headline. Completalo abajo.</p>
    <?php endif; ?>
    <div class="profile-hero__meta">
      <?php if ($location !== ''): ?>
        <span><?= e($location) ?></span>
      <?php endif; ?>
      <?php if ($email !== ''): ?>
        <span><?= e($email) ?></span>
      <?php endif; ?>
      <?php if ($phone !== ''): ?>
        <span><?= e($phone) ?></span>
      <?php endif; ?>
      <?php if ($linkedin !== ''): ?>
        <a href="<?= e($linkedin) ?>" target="_blank" rel="noopener">LinkedIn</a>
      <?php endif; ?>
      <?php if ($website !== ''): ?>
        <a href="<?= e($website) ?>" target="_blank" rel="noopener">Sitio</a>
      <?php endif; ?>
      <?php if ($portfolio !== ''): ?>
        <a href="<?= e($portfolio) ?>" target="_blank" rel="noopener">Portfolio</a>
      <?php endif; ?>
      <?php if ($xUrl !== ''): ?>
        <a href="<?= e($xUrl) ?>" target="_blank" rel="noopener">X</a>
      <?php endif; ?>
      <?php if ($instagram !== ''): ?>
        <a href="<?= e($instagram) ?>" target="_blank" rel="noopener">Instagram</a>
      <?php endif; ?>
    </div>
  </div>
</section>

<form method="post" action="<?= e(url('/actions/save_profile.php')) ?>" class="profile-layout">
  <section class="panel">
    <div class="panel-head">
      <div>
        <h2>Cómo te presentás</h2>
        <p class="muted" style="margin:0.25rem 0 0">Lo que ves en la ficha y lo que pegás en portales.</p>
      </div>
    </div>
    <div class="profile-fields">
      <div class="field">
        <label for="name">Nombre</label>
        <input id="name" name="name" required maxlength="120" value="<?= e($name) ?>">
      </div>
      <div class="field">
        <label for="headline">Headline</label>
        <input id="headline" name="headline" maxlength="255" value="<?= e($headline) ?>" placeholder="Tech Lead · PHP / Producto">
      </div>
      <div class="field">
        <label for="location">Ubicación</label>
        <input id="location" name="location" maxlength="120" value="<?= e($location) ?>" placeholder="Buenos Aires · remoto">
      </div>
      <div class="field">
        <label for="phone">Teléfono</label>
        <input id="phone" type="tel" name="phone" maxlength="40" value="<?= e($phone) ?>" placeholder="+54 11 …">
      </div>
      <div class="field">
        <label for="linkedin_url">LinkedIn</label>
        <input id="linkedin_url" name="linkedin_url" maxlength="512" value="<?= e($linkedin) ?>" placeholder="https://www.linkedin.com/in/…">
      </div>
      <div class="field">
        <label for="website_url">Sitio web</label>
        <input id="website_url" name="website_url" maxlength="512" value="<?= e($website) ?>" placeholder="https://…">
      </div>
      <div class="field">
        <label for="portfolio_url">Portfolio</label>
        <input id="portfolio_url" name="portfolio_url" maxlength="512" value="<?= e($portfolio) ?>" placeholder="https://…">
      </div>
      <div class="field">
        <label for="x_url">X</label>
        <input id="x_url" name="x_url" maxlength="512" value="<?= e($xUrl) ?>" placeholder="@usuario o https://x.com/…">
      </div>
      <div class="field">
        <label for="instagram_url">Instagram</label>
        <input id="instagram_url" name="instagram_url" maxlength="512" value="<?= e($instagram) ?>" placeholder="@usuario o https://instagram.com/…">
      </div>
      <div class="field profile-fields__bio">
        <label for="bio">Bio</label>
        <textarea id="bio" name="bio" rows="6" placeholder="Quién sos en 4–6 líneas, para copiar a LinkedIn o a un mail."><?= e($bio) ?></textarea>
      </div>
    </div>
  </section>

  <section class="panel">
    <div class="panel-head">
      <div>
        <h2>Cuenta</h2>
        <p class="muted" style="margin:0.25rem 0 0">Acceso. El mail no se cambia acá.</p>
      </div>
    </div>
    <div class="field">
      <label for="email_ro">Email</label>
      <input id="email_ro" type="email" value="<?= e($email) ?>" readonly disabled>
    </div>
    <div class="profile-pass">
      <h3>Cambiar contraseña</h3>
      <p class="muted">Dejá vacío si no la querés tocar.</p>
      <div class="field">
        <label for="current_password">Actual</label>
        <input id="current_password" type="password" name="current_password" autocomplete="current-password">
      </div>
      <div class="field">
        <label for="new_password">Nueva</label>
        <input id="new_password" type="password" name="new_password" minlength="8" autocomplete="new-password">
      </div>
      <div class="field">
        <label for="new_password_confirm">Confirmar</label>
        <input id="new_password_confirm" type="password" name="new_password_confirm" minlength="8" autocomplete="new-password">
      </div>
    </div>
  </section>

  <div class="profile-save">
    <p class="muted">Hola <?= e($firstName) ?>. Guardá cuando la ficha te represente.</p>
    <button type="submit" class="btn btn-accent">Guardar perfil</button>
  </div>
</form>
