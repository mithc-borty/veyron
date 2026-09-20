<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> - <?= e($appName) ?></title>
<style>
    body { font-family: system-ui, sans-serif; margin: 0; background: #f5f6f8; color: #1f2430; }
    header { background: #ffffff; border-bottom: 1px solid #e3e6ea; padding: 14px 24px; display: flex; justify-content: space-between; align-items: center; }
    header a { color: #2563eb; text-decoration: none; font-weight: 600; }
    main { max-width: 640px; margin: 40px auto; padding: 0 16px; }
    .card { background: #ffffff; border: 1px solid #e3e6ea; border-radius: 8px; padding: 24px; }
    .error { background: #fdecec; border: 1px solid #f5b5b5; color: #a12622; border-radius: 6px; padding: 10px 14px; margin-bottom: 16px; }
    label { display: block; margin: 12px 0 4px; font-weight: 600; }
    input[type="email"], input[type="password"] { width: 100%; box-sizing: border-box; padding: 8px 10px; border: 1px solid #c9ced6; border-radius: 6px; }
    button { margin-top: 16px; background: #2563eb; border: 0; color: #ffffff; padding: 10px 18px; border-radius: 6px; cursor: pointer; }
    .muted { color: #6b7280; font-size: 0.9rem; }
</style>
</head>
<body>
<header>
    <a href="<?= e(url('/')) ?>"><?= e($appName) ?></a>
    <nav>
        <a href="<?= e(url('/')) ?>">Home</a>
<?php if (session_has_user()) { ?>
        <a href="<?= e(url('/dashboard')) ?>">Dashboard</a>
        <form method="post" action="<?= e(url('/logout')) ?>" style="display:inline"><?= csrf_field() ?><button type="submit" style="margin:0 0 0 12px;padding:6px 12px">Logout</button></form>
<?php } else { ?>
        <a href="<?= e(url('/login')) ?>">Login</a>
<?php } ?>
    </nav>
</header>
<main>
<?= $content ?>
</main>
</body>
</html>
