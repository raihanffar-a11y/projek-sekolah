<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
if (current_user()) redirect('index.php');

$maxFails = 5;       // percobaan gagal sebelum dikunci
$lockSeconds = 300;  // lama kunci (5 menit)

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $lockedUntil = (int)($_SESSION['login_locked_until'] ?? 0);

    if ($lockedUntil > time()) {
        $minutes = (int)ceil(($lockedUntil - time()) / 60);
        $error = "Terlalu banyak percobaan gagal. Coba lagi sekitar {$minutes} menit lagi.";
    } elseif ($email === '' || $password === '') {
        $error = 'Email dan password wajib diisi.';
    } else {
        $stmt = db()->prepare("SELECT id,name,email,password,role FROM users WHERE email=?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            unset($user['password'], $_SESSION['login_fails'], $_SESSION['login_locked_until']);
            $_SESSION['user'] = $user;
            redirect('index.php');
        }

        $fails = (int)($_SESSION['login_fails'] ?? 0) + 1;
        if ($fails >= $maxFails) {
            $_SESSION['login_fails'] = 0;
            $_SESSION['login_locked_until'] = time() + $lockSeconds;
            $error = 'Terlalu banyak percobaan gagal. Coba lagi dalam ' . ($lockSeconds / 60) . ' menit.';
        } else {
            $_SESSION['login_fails'] = $fails;
            $error = 'Email atau password salah. Sisa percobaan: ' . ($maxFails - $fails) . '.';
        }
    }
}

$pageTitle = 'Masuk';
require __DIR__ . '/includes/header.php';
?>
<div class="form-card auth-card">
  <div class="eyebrow">Selamat datang kembali</div>
  <h1>Masuk</h1>
  <p class="auth-sub">Masuk dengan email dan password akun EnglishHub kamu.</p>

  <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>

  <form method="post" data-auth>
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

    <div class="field">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" required autocomplete="email" placeholder="nama@email.com"
             value="<?= e($email) ?>" <?= $email === '' ? 'autofocus' : '' ?>>
    </div>

    <div class="field">
      <label for="password">Password</label>
      <div class="input-wrap">
        <input type="password" id="password" name="password" required autocomplete="current-password"
               placeholder="Masukkan password" <?= $email !== '' ? 'autofocus' : '' ?>>
        <button type="button" class="toggle-password" aria-label="Tampilkan password" aria-pressed="false">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        </button>
      </div>
    </div>

    <button type="submit" class="button full">Masuk</button>
  </form>

  <p class="auth-footer">Belum punya akun? <a href="<?= BASE_URL ?>/register.php">Daftar sekarang</a></p>
  <p class="auth-note">Akun admin dibuat oleh pengelola sistem. Pendaftaran umum hanya untuk siswa.</p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
