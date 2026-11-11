<?php
require_once __DIR__ . '/includes/auth.php';
require_role('student');
$sessionId=(int)($_GET['session_id']??0);
$stmt=db()->prepare("SELECT s.*,q.title,q.description,q.duration_minutes FROM sessions s JOIN quizzes q ON q.id=s.quiz_id WHERE s.id=? AND s.status='open'");$stmt->execute([$sessionId]);$session=$stmt->fetch();if(!$session)exit('Sesi tidak tersedia.');

$now=new DateTimeImmutable('now');
if($session['start_at']&&$session['start_at']!==''){
    $startAt=new DateTimeImmutable($session['start_at']);
    if($now<$startAt)exit('Sesi belum dibuka. Silakan coba lagi pada waktu yang ditentukan.');
}
if($session['end_at']&&$session['end_at']!==''){
    $endAt=new DateTimeImmutable($session['end_at']);
    if($now>$endAt)exit('Sesi sudah ditutup. Waktu pengerjaan telah berakhir.');
}

$userId=(int)current_user()['id'];
$check=db()->prepare("SELECT id,score,submitted_at,created_at FROM attempts WHERE session_id=? AND user_id=?");$check->execute([$sessionId,current_user()['id']]);$existing=$check->fetch();
if($existing&&$existing['submitted_at'])redirect('result.php?id='.(int)$existing['id']);
$q=db()->prepare("SELECT id,question_text,points FROM questions WHERE quiz_id=? ORDER BY sort_order,id");$q->execute([$session['quiz_id']]);$questions=$q->fetchAll();
if(!$questions)exit('Kuis belum memiliki soal.');

$existingAnswers=[];
$attemptId=null;
if($existing&&!$existing['submitted_at']){
    $attemptId=(int)$existing['id'];
    $ansStmt=db()->prepare("SELECT question_id,selected_option_id FROM answers WHERE attempt_id=?");
    $ansStmt->execute([$attemptId]);
    foreach($ansStmt->fetchAll() as $row){
        $existingAnswers[(int)$row['question_id']]=(int)($row['selected_option_id']??0);
    }
}

$deadline=null;
if($existing&&!$existing['submitted_at']){
    $startedAt=new DateTimeImmutable($existing['created_at']);
    $deadline=$startedAt->modify("+{$session['duration_minutes']} minutes");
}

$timeExpired=false;
if($deadline){
    $nowCheck=new DateTimeImmutable('now');
    if($nowCheck>$deadline)$timeExpired=true;
}

if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();

 if($existing&&$existing['submitted_at']){
  redirect('result.php?id='.(int)$existing['id']);
 }

 if($deadline){
  $nowPost=new DateTimeImmutable('now');
  if($nowPost>$deadline){
   http_response_code(429);
   exit('Waktu pengerjaan telah habis. Silakan hubungi admin.');
  }
 }

 $pdo=db();$pdo->beginTransaction();
 try{
  $max=array_sum(array_map(fn($x)=>(float)$x['points'],$questions));

  if(!$attemptId){
   $ins=$pdo->prepare("INSERT INTO attempts(session_id,user_id,max_score) VALUES(?,?,?)");$ins->execute([$sessionId,$userId,$max]);$attemptId=(int)$pdo->lastInsertId();
  }

  $get=$pdo->prepare("SELECT id,is_correct FROM options WHERE id=? AND question_id=?");
  $save=$pdo->prepare("INSERT INTO answers(attempt_id,question_id,selected_option_id,is_correct,awarded_points) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE selected_option_id=VALUES(selected_option_id),is_correct=VALUES(is_correct),awarded_points=VALUES(awarded_points)");
  $score=0;
  foreach($questions as $question){
   $selected=(int)($_POST['answers'][$question['id']]??0);
   if($selected>0){
    $get->execute([$selected,$question['id']]);$opt=$get->fetch();
    $correct=$opt&&(int)$opt['is_correct']===1;$points=$correct?(float)$question['points']:0;$score+=$points;
   } else {
    $correct=false;$points=0;
   }
   $save->execute([$attemptId,$question['id'],$selected>0?$selected:null,$correct?1:0,$points]);
  }

  $upd=$pdo->prepare("UPDATE attempts SET score=?,submitted_at=NOW() WHERE id=?");$upd->execute([$score,$attemptId]);
  $pdo->prepare("INSERT INTO xp_history(user_id,points,reason) VALUES(?,?,?)")->execute([$userId,(int)round($score),'Completed quiz: '.$session['title']]);
  $pdo->commit();redirect('result.php?id='.$attemptId);
 }catch(Throwable $e){$pdo->rollBack();throw $e;}
}
$optionStmt=db()->prepare("SELECT id,option_text FROM options WHERE question_id=? ORDER BY id");
$pageTitle='Quiz Arena';require __DIR__ . '/includes/header.php';
?>
<div class="eyebrow">Quiz in progress</div><h1><?=e($session['title'])?></h1><p class="muted"><?=count($questions)?> questions · <?= (int)$session['duration_minutes']?> minutes</p>
<hr class="section-divider">
<?php if($timeExpired): ?><div class="alert">Waktu pengerjaan telah habis. Jawaban yang tersimpan tidak dapat diubah.</div><?php else: ?><div class="alert">Periksa jawaban sebelum submit. Timer dipaksa oleh server berdasarkan durasi kuis.</div><?php endif; ?>
<form method="post"<?= $timeExpired ? ' onsubmit="return false;"' : ' onsubmit="return confirm(\'Kumpulkan jawaban sekarang?\')"'; ?>><input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
<?php foreach($questions as $i=>$q):?><div class="question"><span class="question-number"><?=($i+1)?></span><b><?=e($q['question_text'])?></b><p class="muted"><?=e((string)$q['points'])?> points</p>
<?php $optionStmt->execute([$q['id']]);foreach($optionStmt->fetchAll() as $o):?><label class="option"><input type="radio" name="answers[<?=$q['id']?>]" value="<?=$o['id']?>"<?= (isset($existingAnswers[$q['id']])&&$existingAnswers[$q['id']]===(int)$o['id'])?' checked':'' ?><?= $timeExpired ? ' disabled' : '' ?>><?=e($o['option_text'])?></label><?php endforeach;?>
</div><?php endforeach;?>
<button class="button"<?= $timeExpired ? ' disabled' : '' ?>><?= $existing&&!$existing['submitted_at']?'Update jawaban':'Submit answers' ?></button></form>
<?php require __DIR__ . '/includes/footer.php'; ?>
