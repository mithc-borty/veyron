<div class="card">
    <h1>Dashboard</h1>
    <p>Welcome back, <strong><?= e($user['name'] ?? '') ?></strong>.</p>
    <ul>
        <li>Email: <?= e($user['email'] ?? '') ?></li>
        <li>Role: <?= e($user['role'] ?? '') ?></li>
        <li>Status: <?= e($user['status'] ?? '') ?></li>
    </ul>
</div>
