<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$user = current_user();
$pageTitle = 'Dashboard';
require __DIR__ . '/includes/header.php';
if ($user['role'] === 'admin'):
  $counts = db()->query("SELECT (SELECT COUNT(*) FROM users WHERE role='student') students,(SELECT COUNT(*) FROM quizzes) quizzes,(SELECT COUNT(*) FROM sessions) sessions")->fetch();
?>
<div class="eyebrow">Management overview</div><h1>Admin Dashboard</h1><p class="muted">Kelola kuis dan pantau kegiatan English Night.</p>
<div class="grid">
  <div class="card"><div class="muted">Registered students</div><div class="stat"><?= (int)$counts['students'] ?></div></div>
  <div class="card"><div class="muted">Total quizzes</div><div class="stat"><?= (int)$counts['quizzes'] ?></div></div>
  <div class="card"><div class="muted">Quiz sessions</div><div class="stat"><?= (int)$counts['sessions'] ?></div></div>
</div>
<h2 class="section-title">Quick actions</h2><div class="hero-actions"><a class="button" href="<?= BASE_URL ?>/admin/quizzes.php">Manage quizzes</a><a class="button secondary" href="<?= BASE_URL ?>/admin/create_quiz.php">Create quiz</a><a class="button secondary" href="<?= BASE_URL ?>/admin/results.php">View results</a></div>
<?php else:
  $stmt = db()->prepare("SELECT a.score,a.max_score,a.submitted_at,q.title FROM attempts a JOIN sessions s ON s.id=a.session_id JOIN quizzes q ON q.id=s.quiz_id WHERE a.user_id=? AND a.submitted_at IS NOT NULL ORDER BY a.submitted_at DESC LIMIT 5");
  $stmt->execute([$user['id']]); $recent = $stmt->fetchAll();
?>
<div class="eyebrow">Student workspace</div><h1>Good evening, <?= e($user['name']) ?></h1><p class="muted">Siap meningkatkan kemampuan bahasa Inggrismu?</p>
<div class="grid">
  <div class="grid-item"><h3>Join Quiz Session</h3><p class="muted">Punya kode sesi dari guru? Masukkan untuk mulai.</p><a class="button" href="<?= BASE_URL ?>/join.php">Enter session code</a></div>
  <div class="grid-item"><h3>My Progress</h3><p class="muted">Lihat hasil pengerjaan kuis terakhirmu.</p><div class="stat"><?= count($recent) ?></div><span class="muted">Recent attempts</span></div>
</div>
<h2 class="section-title">Recent results</h2>
<?php if(!$recent): ?><div class="card muted">Belum ada hasil kuis. Masukkan kode sesi untuk memulai.</div><?php else: ?>
<div class="table-wrap"><table class="table"><tr><th>Quiz</th><th>Score</th><th>Date</th></tr><?php foreach($recent as $r): ?><tr><td><?= e($r['title']) ?></td><td><b><?= e((string)$r['score']) ?>/<?= e((string)$r['max_score']) ?></b></td><td><?= e($r['submitted_at']) ?></td></tr><?php endforeach; ?></table></div>
<?php endif; endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
