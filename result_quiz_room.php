<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$id = (int)($_GET['id'] ?? 0);
$pdo = db();

$stmt = $pdo->prepare("
    SELECT a.*, qr.title, qr.start_number, qr.end_number, qr.created_by room_owner, u.name
    FROM attempts a
    JOIN quiz_rooms qr ON qr.id = a.quiz_room_id
    JOIN users u ON u.id = a.user_id
    WHERE a.id=?
");
$stmt->execute([$id]);
$result = $stmt->fetch();
if (!$result) exit('Hasil tidak ditemukan.');

if (current_user()['role'] !== 'admin' && (int)$result['user_id'] !== (int)current_user()['id'] && (int)$result['room_owner'] !== (int)current_user()['id']) {
    http_response_code(403);
    exit('Akses ditolak.');
}

$answers = $pdo->prepare("
    SELECT aq.position, aq.vocab_number, aq.question_text, aq.correct_answer, aq.option_a, aq.option_b, aq.option_c, aq.option_d, aq.correct_option,
           aq.selected_option, aq.is_correct
    FROM attempt_quiz_questions aq
    WHERE aq.attempt_id=?
    ORDER BY aq.position
");
$answers->execute([$id]);
$details = $answers->fetchAll();
$boardStmt = $pdo->prepare("
    SELECT u.name,a.score,a.correct_count,a.accuracy_percent,a.completion_seconds
    FROM attempts a JOIN users u ON u.id=a.user_id
    WHERE a.quiz_room_id=? AND a.submitted_at IS NOT NULL
    ORDER BY a.score DESC,u.name
");
$boardStmt->execute([(int)$result['quiz_room_id']]);
$leaderboard = $boardStmt->fetchAll();
$rank = 0;
$previousScore = null;
foreach ($leaderboard as $index => &$entry) {
    $score = (int)$entry['score'];
    if ($previousScore === null || $score !== $previousScore) $rank = $index + 1;
    $entry['rank'] = $rank;
    $previousScore = $score;
}
unset($entry);

$pageTitle = 'Quiz Result';
require __DIR__ . '/includes/header.php';

// Tampilkan notifikasi badge baru jika ada
$newBadges = $_SESSION['new_badges'] ?? null;
if ($newBadges) {
    unset($_SESSION['new_badges']);
}
?>
<div class="eyebrow">Quiz completed</div>
<?php if (!empty($newBadges)): ?>
    <div class="admin-info">
        <strong>🏅 Achievement Baru Dibuka!</strong>
        <?php foreach ($newBadges as $badge): ?>
            <div style="margin-top:6px;">
                <span style="font-size:24px; margin-right:8px;"><?= e($badge['icon'] ?? '🏅') ?></span>
                <b><?= e($badge['name']) ?></b>
                <?php if (!empty($badge['description'])): ?>
                    <br><small class="muted"><?= e($badge['description']) ?></small>
                <?php endif; ?>
                <?php if (!empty($badge['points_reward'])): ?>
                    <br><small class="muted">Bonus XP: +<?= (int)$badge['points_reward'] ?></small>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<div class="result-header">
    <div>
        <h1><?= e($result['title']) ?></h1>
        <p class="muted"><?= e($result['name']) ?> · Range: <?= (int)$result['start_number'] ?>–<?= (int)$result['end_number'] ?></p>
    </div>
    <div class="grid result-summary">
        <div class="card"><span>Total soal</span><strong><?= (int)$result['correct_count'] + (int)$result['incorrect_count'] + (int)$result['unanswered_count'] ?></strong></div>
        <div class="card"><span>Benar</span><strong><?= (int)$result['correct_count'] ?></strong></div>
        <div class="card"><span>Salah</span><strong><?= (int)$result['incorrect_count'] ?></strong></div>
        <div class="card"><span>Tidak dijawab</span><strong><?= (int)$result['unanswered_count'] ?></strong></div>
        <div class="card"><span>Akurasi</span><strong><?= e((string)$result['accuracy_percent']) ?>%</strong></div>
        <div class="card"><span>Waktu pengerjaan</span><strong><?= e((string)($result['completion_seconds'] ?? 0)) ?> dtk</strong></div>
    </div>
    <div class="result-score"><?= e((string)$result['score']) ?> <span class="muted">/ <?= e((string)$result['max_score']) ?></span></div>
    <p class="muted">Submitted: <?= e($result['submitted_at'] ?? '-') ?></p>
</div>
<h2 class="section-title">Leaderboard</h2>
<div class="table-wrap">
    <table class="table">
        <thead><tr><th>Peringkat</th><th>Peserta</th><th>Skor</th><th>Benar</th><th>Akurasi</th></tr></thead>
        <tbody>
        <?php foreach ($leaderboard as $entry): ?>
            <tr><td><?= (int)$entry['rank'] ?></td><td><?= e($entry['name']) ?></td><td><?= (int)$entry['score'] ?></td><td><?= (int)$entry['correct_count'] ?></td><td><?= e((string)$entry['accuracy_percent']) ?>%</td></tr>
        <?php endforeach; ?>
        <?php if (!$leaderboard): ?><tr><td colspan="5" class="muted">Belum ada hasil.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<div class="progress-wrap">
    <div class="progress-label"><span>Score</span><span><?= e((string)$result['score']) ?> / <?= e((string)$result['max_score']) ?></span></div>
    <div class="progress-track"><div class="progress-fill" style="width:<?= ($result['max_score'] > 0 ? (int)round(($result['score'] / $result['max_score']) * 100) : 0) ?>%"></div></div>
</div>
<h2 class="section-title">Answer Review</h2>
<div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th style="width:60px;">No</th>
                <th>Kosakata</th>
                <th>Pertanyaan</th>
                <th style="width:160px;">Jawaban Anda</th>
                <th style="width:160px;">Jawaban Benar</th>
                <th style="width:90px;">Status</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($details as $i => $a): ?>
            <?php
            $optionMap = ['A' => 'option_a', 'B' => 'option_b', 'C' => 'option_c', 'D' => 'option_d'];
            $col = $optionMap[$a['correct_option']] ?? '';
            $correctText = $a[$col] ?? $a['correct_answer'];
            $selCol = $optionMap[$a['selected_option'] ?? ''] ?? '';
            $selectedText = $selCol !== '' ? $a[$selCol] : 'No answer';
            ?>
            <tr>
                <td><b><?= ($i + 1) ?></b></td>
                <td><?= (int)$a['vocab_number'] ?></td>
                <td><?= e($a['question_text']) ?></td>
                <td><?= e($selectedText) ?></td>
                <td><?= e($correctText) ?></td>
                <td>
                    <span class="badge <?= (int)$a['is_correct'] === 1 ? 'badge-open' : 'badge-closed' ?>">
                        <?= (int)$a['is_correct'] === 1 ? 'Correct' : 'Incorrect' ?>
                    </span>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<div class="hero-actions">
    <a class="button" href="<?= BASE_URL ?>/dashboard.php">Back to dashboard</a>
    <?php if (current_user()['role'] === 'admin'): ?>
        <a class="button secondary" href="<?= BASE_URL ?>/admin/quiz_rooms.php">Manage rooms</a>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>