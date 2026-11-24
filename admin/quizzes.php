<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
$pageTitle='Quiz Management';
$sql="SELECT q.*,c.name category,u.name creator FROM quizzes q LEFT JOIN categories c ON c.id=q.category_id JOIN users u ON u.id=q.created_by";
if((current_user()['role']??'')==='admin'){$quizzes=db()->query($sql." WHERE q.status<>'archived' ORDER BY q.created_at DESC")->fetchAll();}
else{$stmt=db()->prepare($sql." WHERE q.created_by=? AND q.status<>'archived' ORDER BY q.created_at DESC");$stmt->execute([(int)current_user()['id']]);$quizzes=$stmt->fetchAll();}
require __DIR__ . '/../includes/header.php';
?>
<div class="eyebrow">Quiz Studio</div><h1>Kuis Saya</h1><p class="muted">Buat kuis, kelola soal, dan bagikan sesi kepada peserta.</p>
<div class="admin-actions"><a class="button" href="<?= BASE_URL ?>/admin/create_quiz.php">+ Create quiz</a><a class="button secondary" href="<?= BASE_URL ?>/admin/results.php">View results</a></div>
<h2 class="section-title"><?= (current_user()['role']??'')==='admin'?'All quizzes':'Your quizzes' ?></h2>
<div class="table-wrap"><table class="table"><tr><th>Title</th><th>Creator</th><th>Category</th><th>Status</th><th>Visibility</th><th>Action</th></tr>
<?php foreach($quizzes as $q): ?><tr><td><?= e($q['title']) ?></td><td><?= e($q['creator']) ?></td><td><?= e($q['category'] ?? 'Uncategorized') ?></td><td><span class="badge"><?= e($q['status']) ?></span></td><td><?= e($q['visibility']) ?></td><td><a href="<?= BASE_URL ?>/admin/questions.php?id=<?= (int)$q['id'] ?>">Manage questions</a> · <a href="<?= BASE_URL ?>/admin/sessions.php?id=<?= (int)$q['id'] ?>">Assignment</a></td></tr><?php endforeach; ?>
<?php if(!$quizzes): ?><tr><td colspan="4" class="muted">Belum ada kuis.</td></tr><?php endif; ?></table></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
