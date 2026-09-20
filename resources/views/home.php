<div class="card">
    <h1><?= e($appName) ?></h1>
    <p>A native PHP REST API with session and token (JWT) authentication.</p>
    <p class="muted">API base: <code>api/v1</code> - see <code>docs/API.md</code>.</p>
<?php if (!empty($loggedIn)) { ?>
    <p><a href="<?= e(url('/dashboard')) ?>">Go to your dashboard</a></p>
<?php } else { ?>
    <p><a href="<?= e(url('/login')) ?>">Login</a> to access the dashboard.</p>
<?php } ?>
</div>
