<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
$quizId=(int)($_GET['id']??0);$s=db()->prepare("SELECT * FROM quizzes WHERE id=?");$s->execute([$quizId]);$quiz=$s->fetch();if(!$quiz)exit('Quiz tidak ditemukan.');
if((current_user()['role']??'')!=='admin'&&(int)$quiz['created_by']!==(int)current_user()['id']){http_response_code(403);exit('Akses ditolak.');}
$error='';
function parse_assignment_datetime(string $value): ?string
{
 $value=trim($value);if($value==='')return null;
 $date=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$value);$errors=DateTimeImmutable::getLastErrors();
 if(!$date||($errors&&($errors['warning_count']||$errors['error_count']))||$date->format('Y-m-d\TH:i')!==$value)return null;
 return $date->format('Y-m-d H:i:s');
}
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();
 $count=db()->prepare("SELECT COUNT(*) FROM questions WHERE quiz_id=?");$count->execute([$quizId]);
 $questionCount=(int)$count->fetchColumn();
 $invalid=db()->prepare("SELECT COUNT(*) FROM questions q LEFT JOIN options o ON o.question_id=q.id WHERE q.quiz_id=? GROUP BY q.id HAVING COUNT(o.id)<>4 OR SUM(o.is_correct)<>1");$invalid->execute([$quizId]);
 if($quiz['status']!=='published')$error='Publikasikan kuis sebelum membuat assignment.';
 elseif($questionCount<1)$error='Tambahkan minimal satu soal sebelum membuat sesi.';
 elseif($invalid->fetch())$error='Setiap soal harus memiliki empat pilihan dan satu jawaban benar.';
 else{
  $startInput=trim($_POST['start_at']??'');$endInput=trim($_POST['end_at']??'');
  $startAt=parse_assignment_datetime($startInput);$endAt=parse_assignment_datetime($endInput);
  if(($startInput!==''&&$startAt===null)||$endInput===''||$endAt===null){$error='Waktu mulai tidak valid dan batas pengerjaan wajib diisi.';}
  elseif($startAt&&$endAt<=$startAt){$error='Batas akhir harus setelah waktu mulai.';}
  elseif($endAt<=date('Y-m-d H:i:s')){$error='Batas pengerjaan harus berada di waktu mendatang.';}
  else{
  do{$code=strtoupper(bin2hex(random_bytes(6)));$check=db()->prepare('SELECT 1 FROM sessions WHERE session_code=?');$check->execute([$code]);}while($check->fetchColumn());
  $ins=db()->prepare("INSERT INTO sessions(quiz_id,host_id,mode,session_code,status,start_at,end_at) VALUES(?,?,'assignment',?,'open',?,?)");$ins->execute([$quizId,(int)current_user()['id'],$code,$startAt,$endAt]);
  $up=db()->prepare("UPDATE quizzes SET status='published' WHERE id=?");$up->execute([$quizId]);
  $newCode=$code;
  }
 }
}
$list=db()->prepare("SELECT s.*,COALESCE(a_cnt.cnt,0) AS attempts FROM sessions s LEFT JOIN (SELECT session_id,COUNT(*) AS cnt FROM attempts GROUP BY session_id) a_cnt ON a_cnt.session_id=s.id WHERE s.quiz_id=? ORDER BY s.created_at DESC");$list->execute([$quizId]);$sessions=$list->fetchAll();
$pageTitle='Session Management';require __DIR__ . '/../includes/header.php';
?>
<div class="eyebrow">Assignment management</div><h1><?=e($quiz['title'])?></h1><p class="muted">Buat kode assignment untuk dibagikan kepada peserta. Setiap peserta mendapat satu kali pengerjaan.</p>
<?php if($error):?><div class="alert"><?=e($error)?></div><?php endif;?>
<?php if(!empty($newCode)):?><div class="card success"><div class="muted">Kode assignment</div><div class="code-display"><?=e($newCode)?></div><p>Bagikan kode ini kepada peserta.</p></div><?php endif;?>
<form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><div class="field"><label>Start at (optional)</label><input type="datetime-local" name="start_at"></div><div class="field"><label>End at (required)</label><input type="datetime-local" name="end_at" required></div><button class="button">+ Create session code</button></form>
<h2 class="section-title">Existing sessions</h2><div class="table-wrap"><table class="table"><tr><th>Code</th><th>Status</th><th>Attempts</th><th>Action</th></tr>
<?php foreach($sessions as $row):?><tr><td><b><?=e($row['session_code'])?></b></td><td><?=e($row['status'])?></td><td><?=$row['attempts']?></td><td><a href="<?=BASE_URL?>/admin/results.php?session_id=<?=$row['id']?>">Results</a></td></tr><?php endforeach;?>
</table></div><?php require __DIR__ . '/../includes/footer.php'; ?>
