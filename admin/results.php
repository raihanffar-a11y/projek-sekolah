<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
$sessionId=(int)($_GET['session_id']??0);
$sql="SELECT a.id,a.score,a.max_score,a.submitted_at,u.name,u.email,q.title,s.session_code FROM attempts a JOIN users u ON u.id=a.user_id JOIN sessions s ON s.id=a.session_id JOIN quizzes q ON q.id=s.quiz_id WHERE a.submitted_at IS NOT NULL";
$params=[];
if($sessionId){$sql.=" AND s.id=?";$params[]=$sessionId;}
$sql.=" ORDER BY a.submitted_at DESC";
$stmt=db()->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll();
$pageTitle='Results & Analytics';require __DIR__ . '/../includes/header.php';
?>
<div class="eyebrow">Performance analytics</div><h1>Results & Analytics</h1><p class="muted">Semua hasil pengerjaan yang sudah dikumpulkan.</p>
<div class="table-wrap"><table class="table"><tr><th>Student</th><th>Quiz</th><th>Session</th><th>Score</th><th>Submitted</th><th>Detail</th></tr>
<?php foreach($rows as $r):?><tr><td><?=e($r['name'])?><br><small class="muted"><?=e($r['email'])?></small></td><td><?=e($r['title'])?></td><td><?=e($r['session_code'])?></td><td><b><?=e((string)$r['score'])?> / <?=e((string)$r['max_score'])?></b></td><td><?=e($r['submitted_at'])?></td><td><a style="color:#8dbaff" href="<?=BASE_URL?>/result.php?id=<?=$r['id']?>">View</a></td></tr><?php endforeach;?>
<?php if(!$rows):?><tr><td colspan="6" class="muted">Belum ada hasil pengerjaan.</td></tr><?php endif;?></table></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
