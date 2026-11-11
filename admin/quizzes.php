<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
$pageTitle='Quiz Management';
$quizzes=db()->query("SELECT q.*,c.name category FROM quizzes q LEFT JOIN categories c ON c.id=q.category_id ORDER BY q.created_at DESC")->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<div class="eyebrow">Content management</div><h1>Quiz Management</h1><p class="muted">Buat, terbitkan, dan kelola bank kuis kelas.</p>
<div class="hero-actions"><a class="button" href="<?= BASE_URL ?>/admin/create_quiz.php">+ Create quiz</a><a class="button secondary" href="<?= BASE_URL ?>/admin/results.php">View results</a></div>
<h2 class="section-title">Your quizzes</h2>
<div class="table-wrap"><table class="table"><tr><th>Title</th><th>Category</th><th>Status</th><th>Action</th></tr>
<?php foreach($quizzes as $q): ?><tr><td><?= e($q['title']) ?></td><td><?= e($q['category'] ?? 'Uncategorized') ?></td><td><span class="badge"><?= e($q['status']) ?></span></td><td><a href="<?= BASE_URL ?>/admin/questions.php?id=<?= (int)$q['id'] ?>">Manage questions</a> · <a href="<?= BASE_URL ?>/admin/sessions.php?id=<?= (int)$q['id'] ?>">Sessions</a></td></tr><?php endforeach; ?>
<?php if(!$quizzes): ?><tr><td colspan="4" class="muted">Belum ada kuis.</td></tr><?php endif; ?></table></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
