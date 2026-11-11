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
<div class="success"><p style="margin:0;font-size:13px;">Admin? Role ditentukan saat pendaftaran. Jika ingin mengelola kuis, daftar sebagai admin atau hubungi administrator untuk mengubah role akun Anda di database.</p></div>
<form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
<div class="field"><label>Email</label><input type="email" name="email" required></div>
<div class="field field-toggle"><label>Password</label><input type="password" name="password" required><button type="button" class="toggle-password" aria-toggle="password"><svg viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg></button></div>
<button class="button full">Login</button></form><p class="muted">Belum punya akun? <a href="<?= BASE_URL ?>/register.php">Daftar</a></p></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
