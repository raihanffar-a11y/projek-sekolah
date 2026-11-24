<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
if (current_user()) redirect('index.php');

$errors = [];
$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if ($name === '') {
        $errors[] = 'Nama lengkap wajib diisi.';
    } elseif (mb_strlen($name) > 100) {
        $errors[] = 'Nama terlalu panjang (maksimal 100 karakter).';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password minimal 8 karakter.';
    } elseif ($password !== $password2) {
        $errors[] = 'Konfirmasi password tidak sama.';
    }

    if (!$errors) {
        try {
            // Pendaftaran umum SELALU siswa. Admin hanya dibuat lewat database.
            $stmt = db()->prepare("INSERT INTO users (name,email,password,role) VALUES (?,?,?,'student')");
            $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
            session_regenerate_id(true);
            $_SESSION['user'] = ['id' => (int)db()->lastInsertId(), 'name' => $name, 'email' => $email, 'role' => 'student'];
            redirect('index.php');
        } catch (PDOException $ex) {
            $errors[] = (string)$ex->getCode() === '23000'
                ? 'Email ini sudah terdaftar. Silakan masuk.'
                : 'Pendaftaran gagal. Coba lagi beberapa saat lagi.';
        }
    }
}

$pageTitle = 'Buat akun';
require __DIR__ . '/includes/header.php';
?>
<div class="form-card auth-card">
  <div class="eyebrow">Bergabung dengan EnglishHub</div>
  <h1>Buat akun</h1>
  <p class="auth-sub">Daftar sebagai siswa untuk mengikuti kuis, latihan pengucapan, dan English Night.</p>

  <?php if ($errors): ?>
    <div class="alert alert-error" role="alert">
      <?php if (count($errors) === 1): ?>
        <?= e($errors[0]) ?>
      <?php else: ?>
        <ul style="margin:0;padding-left:18px;">
          <?php foreach ($errors as $msg): ?><li><?= e($msg) ?></li><?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <form method="post" data-auth>
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

    <div class="field">
      <label for="name">Nama lengkap</label>
      <input id="name" name="name" maxlength="100" required autocomplete="name" placeholder="Nama lengkapmu"
             value="<?= e($name) ?>" <?= $name === '' ? 'autofocus' : '' ?>>
    </div>

    <div class="field">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" required autocomplete="email" placeholder="nama@email.com"
             value="<?= e($email) ?>">
    </div>

    <div class="field">
      <label for="password">Password</label>
      <div class="input-wrap">
        <input type="password" id="password" name="password" minlength="8" required autocomplete="new-password" placeholder="Minimal 8 karakter">
        <button type="button" class="toggle-password" aria-label="Tampilkan password" aria-pressed="false">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        </button>
      </div>
      <p class="field-hint">Gunakan minimal 8 karakter.</p>
    </div>

    <div class="field">
      <label for="password2">Ulangi password</label>
      <div class="input-wrap">
        <input type="password" id="password2" name="password2" minlength="8" required autocomplete="new-password" placeholder="Ketik ulang password">
        <button type="button" class="toggle-password" aria-label="Tampilkan password" aria-pressed="false">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        </button>
      </div>
    </div>

    <button type="submit" class="button full">Buat akun</button>
  </form>

  <p class="auth-footer">Sudah punya akun? <a href="<?= BASE_URL ?>/login.php">Masuk</a></p>
  <p class="auth-note">Akun baru otomatis terdaftar sebagai siswa.</p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
