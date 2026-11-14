<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo = db();
$message = '';
$roomId = (int)($_GET['room_id'] ?? 0);
$export = ($_GET['export'] ?? '') === 'csv';

// Ambil daftar quiz room untuk filter
$rooms = $pdo->query("SELECT id, title, session_code FROM quiz_rooms ORDER BY created_at DESC")->fetchAll();

// Build query hasil quiz room
$sql = "
    SELECT a.id, a.score, a.max_score, a.submitted_at, a.created_at, u.name, u.email,
           qr.title, qr.session_code, qr.start_number, qr.end_number
    FROM attempts a
    JOIN users u ON u.id = a.user_id
    JOIN quiz_rooms qr ON qr.id = a.quiz_room_id
    WHERE a.submitted_at IS NOT NULL
";
$params = [];

if ($roomId > 0) {
    $sql .= " AND qr.id = ?";
    $params[] = $roomId;
}

$sql .= " ORDER BY a.submitted_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Ambil statistik per room (ringkasan)
$roomStats = [];
foreach ($rooms as $r) {
    $sStmt = $pdo->prepare("
        SELECT COUNT(*) AS total,
               COALESCE(AVG(score), 0) AS avg_score,
               COALESCE(MAX(score), 0) AS max_score,
               COALESCE(MIN(score), 0) AS min_score
        FROM attempts a
        WHERE a.quiz_room_id = ? AND a.submitted_at IS NOT NULL
    ");
    $sStmt->execute([(int)$r['id']]);
    $roomStats[(int)$r['id']] = $sStmt->fetch();
}

// Export CSV
if ($export) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="englishhub_quiz_room_results.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['No', 'Nama', 'Email', 'Quiz Room', 'Kode Sesi', 'Range', 'Skor', 'Maksimal', 'Tanggal'], ',', '"');
    $no = 1;
    foreach ($rows as $r) {
        fputcsv($out, [
            $no++,
            $r['name'],
            $r['email'],
            $r['title'],
            $r['session_code'],
            $r['start_number'] . '-' . $r['end_number'],
            $r['score'],
            $r['max_score'],
            $r['submitted_at'],
        ], ',', '"');
    }
    fclose($out);
    exit;
}

$pageTitle = 'Hasil Quiz Room';
require __DIR__ . '/../includes/header.php';
?>
<div class="eyebrow">Quiz Room Results</div>
<h1>Hasil Quiz Room</h1>
<p class="muted">Lihat hasil pengerjaan quiz room dari setiap siswa.</p>

<?php if ($message): ?><div class="alert"><?= e($message) ?></div><?php endif; ?>
<div class="table-header-row">
    <form method="get" style="display:inline; margin-right:12px;">
        <label style="font-size:13px; font-weight:600; color:var(--muted);">Filter room:</label>
        <select name="room_id" style="font-size:13px; padding:4px 8px;" onchange="this.form.submit()">
            <option value="0"><?= $roomId === 0 ? 'Semua room' : 'Pilih room' ?></option>
            <?php foreach ($rooms as $r): ?>
                <option value="<?= (int)$r['id'] ?>" <?= $roomId === (int)$r['id'] ? 'selected' : '' ?>>
                    <?= e($r['title']) ?> (<?= e($r['session_code']) ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </form>
    <?php if (!empty($rows)): ?>
        <a class="button" href="?room_id=<?= $roomId ?>&export=csv">Export CSV</a>
    <?php endif; ?>
    <a class="button secondary" href="<?= BASE_URL ?>/admin/quiz_rooms.php">Kelola Room</a>
</div>

<?php if (!empty($roomId)): ?>
    <?php $stats = $roomStats[$roomId] ?? null; ?>
    <?php if ($stats): ?>
        <div class="grid" style="margin-bottom:24px;">
            <div class="grid-item card stat-card">
                <div class="muted">Total Siswa Selesai</div>
                <div class="stat"><?= (int)$stats['total'] ?></div>
            </div>
            <div class="grid-item card stat-card">
                <div class="muted">Rata-rata Skor</div>
                <div class="stat"><?= number_format((float)$stats['avg_score'], 1) ?></div>
            </div>
            <div class="grid-item card stat-card">
                <div class="muted">Skor Tertinggi</div>
                <div class="stat"><?= (int)$stats['max_score'] ?></div>
            </div>
            <div class="grid-item card stat-card">
                <div class="muted">Skor Terendah</div>
                <div class="stat"><?= (int)$stats['min_score'] ?></div>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<h2 class="section-title">Daftar Hasil (<?= count($rows) ?>)</h2>
<div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th style="width:50px;">No</th>
                <th>Siswa</th>
                <th>Quiz Room</th>
                <th style="width:100px;">Kode Sesi</th>
                <th style="width:120px;">Range</th>
                <th style="width:110px;">Skor</th>
                <th style="width:160px;">Tanggal</th>
                <th style="width:100px;">Detail</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
            <tr><td colspan="8" class="muted">Belum ada hasil pengerjaan quiz room.</td></tr>
        <?php else: ?>
            <?php $no = 1; foreach ($rows as $r): ?>
            <tr>
                <td><b><?= $no++ ?></b></td>
                <td><?= e($r['name']) ?><br><small class="muted"><?= e($r['email']) ?></small></td>
                <td><?= e($r['title']) ?></td>
                <td><code><?= e($r['session_code']) ?></code></td>
                <td><?= (int)$r['start_number'] ?>–<?= (int)$r['end_number'] ?></td>
                <td><b><?= e((string)$r['score']) ?> / <?= e((string)$r['max_score']) ?></b></td>
                <td><?= e($r['submitted_at']) ?></td>
                <td><a href="<?= BASE_URL ?>/result_quiz_room.php?id=<?= (int)$r['id'] ?>">Lihat</a></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>