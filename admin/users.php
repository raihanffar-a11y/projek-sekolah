<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
    $role = (string)($_POST['role'] ?? '');
    if (!$userId || !in_array($role, ['student', 'teacher'], true)) {
        $error = 'Data akun atau role tidak valid.';
    } else {
        $pdo = db();
        $currentRole = $pdo->prepare('SELECT role FROM users WHERE id = ?');
        $currentRole->execute([$userId]);
        $currentRole = $currentRole->fetchColumn();
        if ($currentRole === false) {
            $error = 'Akun tidak ditemukan.';
        } elseif ($currentRole === 'admin') {
            $error = 'Akun admin tidak dapat diubah dari halaman ini.';
        } else {
            $pdo->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$role, $userId]);
            $message = 'Role akun berhasil diperbarui.';
        }
    }
}

$users = db()->query('SELECT id, name, email, role, created_at FROM users ORDER BY created_at DESC')->fetchAll();
$pageTitle = 'Manajemen Pengguna';
require __DIR__ . '/../includes/header.php';
?>
<div class="eyebrow">Administration</div>
<h1>Manajemen Pengguna</h1>
<p class="muted">Admin dapat mengatur akun siswa dan menetapkan Teacher. Semua role yang sudah login dapat membuat kuis dan room.</p>
<?php if ($message): ?><div class="alert"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<div class="table-wrap">
  <table class="table">
    <thead><tr><th>Nama</th><th>Email</th><th>Role</th><th>Terdaftar</th><th>Aksi</th></tr></thead>
    <tbody>
      <?php foreach ($users as $account): ?>
        <tr>
          <td><?= e($account['name']) ?></td>
          <td><?= e($account['email']) ?></td>
          <td><?= e(ucfirst($account['role'])) ?></td>
          <td><?= e($account['created_at']) ?></td>
          <td>
            <?php if ($account['role'] === 'admin'): ?>
              Admin
            <?php else: ?>
              <form method="post" class="inline-form">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="user_id" value="<?= (int)$account['id'] ?>">
                <select name="role" aria-label="Role <?= e($account['name']) ?>">
                  <option value="student" <?= $account['role'] === 'student' ? 'selected' : '' ?>>Student</option>
                  <option value="teacher" <?= $account['role'] === 'teacher' ? 'selected' : '' ?>>Teacher</option>
                </select>
                <button class="button secondary" type="submit">Simpan</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$users): ?><tr><td colspan="5" class="muted">Belum ada akun.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
