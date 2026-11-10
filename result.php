<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$id=(int)($_GET['id']??0);
$stmt=db()->prepare("SELECT a.*,q.title,u.name FROM attempts a JOIN sessions s ON s.id=a.session_id JOIN quizzes q ON q.id=s.quiz_id JOIN users u ON u.id=a.user_id WHERE a.id=?");$stmt->execute([$id]);$result=$stmt->fetch();
if(!$result)exit('Hasil tidak ditemukan.');
if(current_user()['role']!=='admin'&&(int)$result['user_id']!==(int)current_user()['id']){http_response_code(403);exit('Akses ditolak.');}
$answers=db()->prepare("SELECT an.is_correct,an.awarded_points,qu.question_text,op.option_text selected FROM answers an JOIN questions qu ON qu.id=an.question_id LEFT JOIN options op ON op.id=an.selected_option_id WHERE an.attempt_id=? ORDER BY qu.id");$answers->execute([$id]);$details=$answers->fetchAll();
$pageTitle='Quiz result';require __DIR__ . '/includes/header.php';
?>
<div class="eyebrow">Quiz completed</div><div class="card"><h1><?=e($result['title'])?></h1><p class="muted"><?=e($result['name'])?></p><div class="stat"><?=e((string)$result['score'])?> <span class="muted">/ <?=e((string)$result['max_score'])?></span></div>
<p class="muted">Submitted: <?=e($result['submitted_at']??'-')?></p></div>
<h2 class="section-title">Answer review</h2>
<?php foreach($details as $i=>$a):?><div class="question"><b><?=($i+1)?>. <?=e($a['question_text'])?></b><p><?=e($a['selected']??'No answer')?></p><span class="badge"><?=((int)$a['is_correct']===1)?'Correct':'Incorrect'?></span></div><?php endforeach;?>
<div class="hero-actions"><a class="button" href="<?=BASE_URL?>/dashboard.php">Back to dashboard</a></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
