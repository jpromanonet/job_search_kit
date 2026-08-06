<?php
declare(strict_types=1);
?>
  </main>
  <footer class="footer">
    <span><?= e($appName ?? 'JobKit') ?></span>
    <span><?= e(date('d/m/Y H:i')) ?></span>
  </footer>
  <script src="<?= e(url('/assets/js/app.js')) ?>"></script>
</body>
</html>
