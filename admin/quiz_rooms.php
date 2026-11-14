<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo = db();
$message = '';

// Handle close/reopen room
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $roomId = (int)($_POST['room_id'] ?? 0);
    if ($action === 'toggle_status' && $roomId > 0) {
        $stmt = $pdo->prepare("SELECT status FROM quiz_rooms WHERE id=?");
        $stmt->execute([$roomId]);
        $current = $stmt->fetchColumn();
        $newStatus = $current === 'open' ? 'closed' : 'open';
        $upd = $pdo->prepare("UPDATE quiz_rooms SET status=? WHERE id=?");
        $upd->execute([$newStatus, $roomId]);
        $message = "Room diubah ke " . ($newStatus === 'open' ? 'dibuka' : 'ditutup') . ".";
    } elseif ($action === 'delete' && $roomId > 0) {
        // Hapus attempts terkait dulu (cascade akan menangani)
        $del = $pdo->prepare("DELETE FROM quiz_rooms WHERE id=?");
        $del->execute([$roomId]);
        $message = "Quiz room dihapus.";
    }
}

// Ambil daftar quiz room
$rooms = $pdo->query("
    SELECT qr.*, u.name AS creator_name,
           (SELECT COUNT(*) FROM attempts a WHERE a.quiz_room_id = qr.id AND a.submitted_at IS NOT NULL) AS submitted_count
    FROM quiz_rooms qr
    JOIN users u ON u.id = qr.created_by
    ORDER BY qr.created_at DESC
")->fetchAll();

$pageTitle = 'Quiz Rooms';
require __DIR__ . '/../includes/header.php';
?>
<div class="eyebrow">Quiz Room Management</div>
<h1>Quiz Rooms</h1>
<p class="muted">Kelola quiz room berbasis rentang kosakata. Siswa bergabung menggunakan session code.</p>

<?php if ($message): ?><div class="alert"><?= e($message) ?></div><?php endif; ?>

<div class="table-header-row">
    <a class="button" href="<?= BASE_URL ?>/admin/create_quiz_room.php">+ Buat Quiz Room Baru</a>
    <a class="button secondary" href="<?= BASE_URL ?>/dashboard.php">Kembali ke Dashboard</a>
</div>

<h2 class="section-title">Daftar Quiz Room (<?= count($rooms) ?>)</h2>
<div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th style="width:60px;">ID</th>
                <th>Judul</th>
                <th style="width:140px;">Rentang</th>
                <th style="width:100px;">Jumlah Soal</th>
                <th style="width:100px;">Kode Sesi</th>
                <th style="width:100px;">Status</th>
                <th style="width:100px;">Durasi</th>
                <th style="width:100px;">Selesai</th>
                <th style="width:140px;">Aksi</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rooms as $r): ?>
            <tr>
                <td><b><?= (int)$r['id'] ?></b></td>
                <td><?= e($r['title']) ?></td>
                <td><?= (int)$r['start_number'] ?> – <?= (int)$r['end_number'] ?></td>
                <td><b><?= (int)$r['question_count'] ?></b></td>
                <td><code><?= e($r['session_code']) ?></code></td>
                <td>
                    <span class="badge <?= $r['status'] === 'open' ? 'badge-open' : 'badge-closed' ?>">
                        <?= $r['status'] === 'open' ? 'Buka' : 'Tutup' ?>
                    </span>
                </td>
                <td><?= (int)$r['duration_minutes'] ?> menit</td>
                <td><?= e($r['submitted_count']) ?> siswa</td>
                <td>
                    <form method="post" style="display:inline;" onsubmit="return confirm('Yakin ingin mengubah status room ini?')">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="toggle_status">
                        <input type="hidden" name="room_id" value="<?= (int)$r['id'] ?>">
                        <button type="submit" class="button" style="padding:6px 12px;font-size:12px;">
                            <?= $r['status'] === 'open' ? 'Tutup' : 'Buka' ?>
                        </button>
                    </form>
                    <a href="<?= BASE_URL ?>/admin/quiz_room_results.php?room_id=<?= (int)$r['id'] ?>" class="button" style="padding:6px 12px;font-size:12px;">Hasil</a>
                    <?php if ($r['submitted_count'] === 0): ?>
                        <form method="post" style="display:inline;margin-left:6px;" onsubmit="return confirm('Hapus room ini? Tindakan tidak dapat dibatalkan.')">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="room_id" value="<?= (int)$r['id'] ?>">
                            <button type="submit" class="button" style="padding:6px 12px;font-size:12px;background:#FDF3EC;border-color:#F5C9AE;color:#D95D39;">Hapus</button>
                        </form>
                    <?php else: ?>
                        <span class="muted" style="font-size:11px;">Tidak dapat dihapus (sudah ada hasil)</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($rooms)): ?>
            <tr><td colspan="9" class="muted">Belum ada quiz room. Buat room baru untuk memulai.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>