<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
if (current_user()) redirect('dashboard.php');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'student';
    if (!in_array($role, ['student','admin'], true)) {
        $role = 'student';
    }
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
        $error = 'Isi nama dan email dengan benar. Password minimal 8 karakter.';
    } else {
        try {
            $stmt = db()->prepare("INSERT INTO users (name,email,password,role) VALUES (?,?,?,?)");
            $stmt->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT),$role]);
            session_regenerate_id(true);
            $_SESSION['user'] = ['id'=>(int)db()->lastInsertId(),'name'=>$name,'email'=>$email,'role'=>$role];
            redirect('dashboard.php');
        } catch (PDOException $e) {
            $error = $e->getCode() === '23000' ? 'Email sudah terdaftar.' : 'Registrasi gagal. Coba lagi.';
        }
    }
}
$pageTitle = 'Create account';
require __DIR__ . '/includes/header.php';
?>
<div class="form-card"><div class="eyebrow">Join EnglishHub</div><h1>Create account</h1><p class="muted">Daftar sebagai siswa untuk mengikuti English Night.</p>
<?php if($error): ?><div class="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
<div class="field"><label>Full name</label><input name="name" maxlength="100" required value="<?= e($_POST['name'] ?? '') ?>"></div>
<div class="field"><label>Email</label><input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>"></div>
<div class="field field-toggle"><label>Password</label><input type="password" name="password" minlength="8" required><button type="button" class="toggle-password" aria-toggle="password"><svg viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg></button></div>
<div class="field-group"><label>Role</label><select name="role"><option value="student">Siswa</option><option value="admin">Admin (pengelola kuis)</option></select><p class="helper">Pilih admin hanya jika akun ini akan mengelola kuis dan siswa.</p></div>
<button class="button full">Create account</button></form><p class="muted">Sudah punya akun? <a href="<?= BASE_URL ?>/login.php">Login</a></p></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
