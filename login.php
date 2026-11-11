<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
if (current_user()) redirect('dashboard.php');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(trim($_POST['email'] ?? ''));
    $stmt = db()->prepare("SELECT id,name,email,password,role FROM users WHERE email=?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user && password_verify($_POST['password'] ?? '', $user['password'])) {
        session_regenerate_id(true);
        unset($user['password']);
        $_SESSION['user'] = $user;
        redirect('dashboard.php');
    }
    $error = 'Email atau password tidak cocok.';
}
$pageTitle = 'Login';
require __DIR__ . '/includes/header.php';
?>
<div class="form-card"><div class="eyebrow">Welcome back</div><h1>Sign in</h1><p class="muted">Masuk ke akun EnglishHub kamu.</p>
<?php if($error): ?><div class="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
<div class="field"><label>Email</label><input type="email" name="email" required></div>
<div class="field"><label>Password</label><input type="password" name="password" required></div>
<button class="button full">Login</button></form><p class="muted">Belum punya akun? <a href="<?= BASE_URL ?>/register.php">Daftar</a></p></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
