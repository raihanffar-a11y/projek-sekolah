<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/achievements.php';
require_role('student');

$pdo = db();
$userId = (int)current_user()['id'];

// Statistik siswa
$statsStmt = $pdo->prepare("
    SELECT 
        COUNT(*) AS total_quiz,
        COALESCE(AVG(score / NULLIF(max_score, 0) * 100), 0) AS avg_score,
        COALESCE(MAX(score), 0) AS best_score,
        COALESCE(MIN(score), 0) AS worst_score
    FROM attempts
    WHERE user_id=? AND submitted_at IS NOT NULL
");
$statsStmt->execute([$userId]);
$stats = $statsStmt->fetch();

// Total XP
$xpStmt = $pdo->prepare("SELECT COALESCE(SUM(points), 0) AS total_xp FROM xp_history WHERE user_id=?");
$xpStmt->execute([$userId]);
$xpRow = $xpStmt->fetch();
$totalXp = (int)$xpRow['total_xp'];

// Level: setiap 100 XP = 1 level
$level = (int)floor($totalXp / 100) + 1;
$xpForNextLevel = ($level * 100) - $totalXp;
if ($xpForNextLevel <= 0) $xpForNextLevel = 100;

// Riwayat quiz
$historyStmt = $pdo->prepare("
    SELECT a.score, a.max_score, a.submitted_at, COALESCE(qr.title, q.title) AS title, qr.start_number, qr.end_number
    FROM attempts a
    LEFT JOIN quiz_rooms qr ON qr.id = a.quiz_room_id
    LEFT JOIN sessions s ON s.id = a.session_id
    LEFT JOIN quizzes q ON q.id = s.quiz_id
    WHERE a.user_id=? AND a.submitted_at IS NOT NULL
    ORDER BY a.submitted_at DESC
    LIMIT 10
");
$historyStmt->execute([$userId]);
$history = $historyStmt->fetchAll();

// Leaderboard: top 10 siswa berdasarkan total XP
$leaderboardStmt = $pdo->query("
    SELECT u.name, u.id,
           COALESCE((SELECT SUM(points) FROM xp_history WHERE user_id = u.id), 0) AS total_xp,
           (SELECT COUNT(*) FROM attempts WHERE user_id = u.id AND submitted_at IS NOT NULL) AS total_quiz
    FROM users u
    WHERE u.role = 'student'
    ORDER BY total_xp DESC, u.name ASC
    LIMIT 10
");
$leaderboard = $leaderboardStmt->fetchAll();

// Achievement / badges
$badgeData = get_user_achievements($userId);

$pageTitle = 'Student Progress';
require __DIR__ . '/includes/header.php';
?>
<div class="eyebrow">Student Progress</div>
<h1>Progres Belajar</h1>
<p class="muted">Pantau perkembangan belajar bahasa Inggris Anda berdasarkan aktivitas nyata.</p>

<div class="grid">
  <div class="grid-item">
    <div class="card stat-card">
      <div class="muted">Total Quiz</div>
      <div class="stat"><?= (int)$stats['total_quiz'] ?></div>
    </div>
  </div>
  <div class="grid-item">
    <div class="card stat-card">
      <div class="muted">Rata-rata Skor</div>
      <div class="stat"><?= number_format((float)$stats['avg_score'], 1) ?>%</div>
    </div>
  </div>
  <div class="grid-item">
    <div class="card stat-card">
      <div class="muted">Total XP</div>
      <div class="stat"><?= $totalXp ?></div>
    </div>
  </div>
  <div class="grid-item">
    <div class="card stat-card">
      <div class="muted">Level</div>
      <div class="stat"><?= $level ?></div>
    </div>
  </div>
</div>

<div class="card">
  <h2>Level <?= $level ?> · XP ke Level Berikutnya</h2>
  <div class="progress-wrap" style="margin-top:12px;">
    <div class="progress-label"><span>XP</span><span><?= $totalXp ?> / <?= ($level * 100) ?></span></div>
    <div class="progress-track"><div class="progress-fill" style="width:<?= min(100, (($totalXp % 100) / 100) * 100) ?>%"></div></div>
  </div>
  <p class="muted">Butuh <b><?= $xpForNextLevel ?></b> XP lagi untuk level <?= ($level + 1) ?>.</p>
</div>

<h2 class="section-title">Achievement &amp; Badges</h2>
<div class="grid" style="grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));">
  <?php if (!empty($badgeData['unlocked'])): ?>
    <?php foreach ($badgeData['unlocked'] as $badge): ?>
      <div class="grid-item card" style="text-align:center;">
        <div style="font-size:32px; margin-bottom:8px;"><?= e($badge['icon'] ?? '🏅') ?></div>
        <div class="muted" style="font-size:11px; text-transform:uppercase; letter-spacing:0.08em; margin-bottom:4px;">Dibuka</div>
        <div><b><?= e($badge['name']) ?></b></div>
        <div class="muted" style="font-size:12px; margin-top:4px;"><?= e($badge['description'] ?? '') ?></div>
        <?php if (!empty($badge['unlocked_at'])): ?>
          <div class="muted" style="font-size:11px; margin-top:6px;"><?= e($badge['unlocked_at']) ?></div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="grid-item card" style="text-align:center; padding:32px 16px; color:var(--muted);">
      <div style="font-size:32px; margin-bottom:8px;">🏅</div>
      <div>Belum ada achievement yang dibuka.</div>
      <div class="muted" style="font-size:12px; margin-top:8px;">Selesaikan kuis untuk mulai mengumpulkan badge.</div>
    </div>
  <?php endif; ?>

  <?php if (!empty($badgeData['locked'])): ?>
    <div class="grid-item card" style="text-align:center; opacity:0.6;">
      <div style="font-size:32px; margin-bottom:8px;">🔒</div>
      <div class="muted" style="font-size:11px; text-transform:uppercase; letter-spacing:0.08em; margin-bottom:4px;">Tersedia</div>
      <div><b>Terkunci</b></div>
      <div class="muted" style="font-size:12px; margin-top:4px;"><?= count($badgeData['locked']) ?> achievement menunggu Anda.</div>
    </div>
  <?php endif; ?>
</div>

<h2 class="section-title">Riwayat Quiz (Terakhir 10)</h2>
<div class="table-wrap">
  <table class="table">
    <thead>
      <tr>
        <th>Kuis</th>
        <th>Range</th>
        <th style="width:120px;">Skor</th>
        <th style="width:160px;">Tanggal</th>
      </tr>
    </thead>
    <tbody>
    <?php if (empty($history)): ?>
      <tr><td colspan="4" class="muted">Belum ada riwayat quiz. Ikuti quiz room untuk memulai.</td></tr>
    <?php else: ?>
      <?php foreach ($history as $h): ?>
        <tr>
          <td><?= e($h['title']) ?></td>
          <td><?= $h['start_number'] !== null ? (int)$h['start_number'] . '–' . (int)$h['end_number'] : '-' ?></td>
          <td><b><?= e((string)$h['score']) ?>/<?= e((string)$h['max_score']) ?></b></td>
          <td><?= e($h['submitted_at']) ?></td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<h2 class="section-title">Leaderboard (Top 10)</h2>
<div class="table-wrap">
  <table class="table">
    <thead>
      <tr>
        <th style="width:60px;">Peringkat</th>
        <th>Nama</th>
        <th style="width:100px;">Total XP</th>
        <th style="width:100px;">Total Quiz</th>
      </tr>
    </thead>
    <tbody>
    <?php if (empty($leaderboard)): ?>
      <tr><td colspan="4" class="muted">Belum ada data leaderboard.</td></tr>
    <?php else: ?>
      <?php foreach ($leaderboard as $idx => $lb): ?>
        <tr>
          <td><b><?= ($idx + 1) ?></b></td>
          <td><?= e($lb['name']) ?></td>
          <td><b><?= (int)$lb['total_xp'] ?></b></td>
          <td><?= (int)$lb['total_quiz'] ?></td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>