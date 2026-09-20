<div class="card">
    <h1>Login</h1>
<?php if (!empty($error)) { ?>
    <div class="error"><?= e($error) ?></div>
<?php } ?>
    <form method="post" action="<?= e(url('/login')) ?>">
        <?= csrf_field() ?>
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= e($email) ?>" required autofocus>
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
        <button type="submit">Login</button>
    </form>
</div>
