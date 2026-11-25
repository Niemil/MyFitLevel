<?php
declare(strict_types=1);

layout_header('Offline');
?>
<div class="card center">
  <h2>You're offline</h2>
  <p class="muted">Check your connection and try again. Your logged workouts are safe on the server.</p>
  <a class="btn" href="<?= e(url('feed')) ?>">Retry</a>
</div>
<?php layout_footer(); ?>
