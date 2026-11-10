<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
$quizId=(int)($_GET['id']??0);$s=db()->prepare("SELECT * FROM quizzes WHERE id=?");$s->execute([$quizId]);$quiz=$s->fetch();if(!$quiz)exit('Quiz tidak ditemukan.');
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();
 $count=db()->prepare("SELECT COUNT(*) FROM questions WHERE quiz_id=?");$count->execute([$quizId]);
 if((int)$count->fetchColumn()<1)$error='Tambahkan minimal satu soal sebelum membuat sesi.';
 else{
  $code=strtoupper(bin2hex(random_bytes(3)));
  $ins=db()->prepare("INSERT INTO sessions(quiz_id,session_code,status) VALUES(?,?,'open')");$ins->execute([$quizId,$code]);
  $up=db()->prepare("UPDATE quizzes SET status='published' WHERE id=?");$up->execute([$quizId]);
  $newCode=$code;
 }
}
$list=db()->prepare("SELECT s.*,COUNT(a.id) attempts FROM sessions s LEFT JOIN attempts a ON a.session_id=s.id WHERE s.quiz_id=? GROUP BY s.id ORDER BY s.created_at DESC");$list->execute([$quizId]);$sessions=$list->fetchAll();
$pageTitle='Session Management';require __DIR__ . '/../includes/header.php';
?>
<div class="eyebrow">Session management</div><h1><?=e($quiz['title'])?></h1><p class="muted">Buat kode untuk dibagikan kepada siswa.</p>
<?php if($error):?><div class="alert"><?=e($error)?></div><?php endif;?>
<?php if(!empty($newCode)):?><div class="card success"><div class="muted">New session code</div><div class="stat" style="letter-spacing:5px"><?=e($newCode)?></div><p>Bagikan kode ini kepada siswa.</p></div><?php endif;?>
<form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><button class="button">+ Create session code</button></form>
<h2 class="section-title">Existing sessions</h2><div class="table-wrap"><table class="table"><tr><th>Code</th><th>Status</th><th>Attempts</th><th>Action</th></tr>
<?php foreach($sessions as $row):?><tr><td><b><?=e($row['session_code'])?></b></td><td><?=e($row['status'])?></td><td><?=$row['attempts']?></td><td><a style="color:#8dbaff" href="<?=BASE_URL?>/admin/results.php?session_id=<?=$row['id']?>">Results</a></td></tr><?php endforeach;?>
</table></div><?php require __DIR__ . '/../includes/footer.php'; ?>
