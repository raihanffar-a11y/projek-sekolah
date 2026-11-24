<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$pdo = db();
$message = '';

// Handle close/reopen room
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $roomId = (int)($_POST['room_id'] ?? 0);
    if ($action === 'toggle_status' && $roomId > 0) {
        $stmt = $pdo->prepare("SELECT status,created_by FROM quiz_rooms WHERE id=?");
        $stmt->execute([$roomId]);
        $room = $stmt->fetch();
        if ($room && ((current_user()['role']??'')==='admin'||(int)$room['created_by']===(int)current_user()['id'])) {
            $newStatus = $room['status'] === 'open' ? 'closed' : 'open';
            $upd = $pdo->prepare("UPDATE quiz_rooms SET status=? WHERE id=?");
            $upd->execute([$newStatus, $roomId]);
            $message = "Room diubah ke " . ($newStatus === 'open' ? 'dibuka' : 'ditutup') . ".";
        } else {
            http_response_code(403);
            $message = 'Anda tidak memiliki izin untuk mengelola room ini.';
        }
    } elseif ($action === 'delete' && $roomId > 0) {
        $stmt = $pdo->prepare("SELECT created_by FROM quiz_rooms WHERE id=?");
        $stmt->execute([$roomId]);
        $creatorId = $stmt->fetchColumn();
        if ($creatorId && ((current_user()['role']??'')==='admin'||(int)$creatorId===(int)current_user()['id'])) {
            $attemptCheck = $pdo->prepare("SELECT COUNT(*) FROM attempts WHERE quiz_room_id=?");
            $attemptCheck->execute([$roomId]);
            if ((int)$attemptCheck->fetchColumn() > 0) {
                $message = 'Room tidak dapat dihapus karena sudah memiliki riwayat pengerjaan.';
            } else {
                $del = $pdo->prepare("DELETE FROM quiz_rooms WHERE id=?");
                $del->execute([$roomId]);
                $message = "Quiz room dihapus.";
            }
        } else {
            http_response_code(403);
            $message = 'Anda tidak memiliki izin untuk mengelola room ini.';
        }
    }
}

// Ambil daftar quiz room
$roomQuery = "
    SELECT qr.*, u.name AS creator_name,
           (SELECT COUNT(*) FROM attempts a WHERE a.quiz_room_id = qr.id AND a.submitted_at IS NOT NULL) AS submitted_count
    FROM quiz_rooms qr
    JOIN users u ON u.id = qr.created_by
";
if ((current_user()['role']??'')==='admin') {
    $rooms = $pdo->query($roomQuery . " ORDER BY qr.created_at DESC")->fetchAll();
} else {
    $stmt = $pdo->prepare($roomQuery . " WHERE qr.created_by=? ORDER BY qr.created_at DESC");
    $stmt->execute([(int)current_user()['id']]);
    $rooms = $stmt->fetchAll();
}

$pageTitle = 'Quiz Rooms';
require __DIR__ . '/../includes/header.php';
?>
<div class="eyebrow">Quiz Room Management</div>
<h1>Quiz Rooms</h1>
<p class="muted">Kelola quiz room berbasis rentang kosakata milik Anda. Peserta bergabung menggunakan session code.</p>

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
                    <?php if ((int)$r['submitted_count'] === 0): ?>
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