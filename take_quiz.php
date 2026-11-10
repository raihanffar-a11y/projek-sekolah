<?php
require_once __DIR__ . '/includes/auth.php';
require_role('student');
$sessionId=(int)($_GET['session_id']??0);
$stmt=db()->prepare("SELECT s.*,q.title,q.description,q.duration_minutes FROM sessions s JOIN quizzes q ON q.id=s.quiz_id WHERE s.id=? AND s.status='open'");$stmt->execute([$sessionId]);$session=$stmt->fetch();if(!$session)exit('Sesi tidak tersedia.');
$check=db()->prepare("SELECT id,submitted_at FROM attempts WHERE session_id=? AND user_id=?");$check->execute([$sessionId,current_user()['id']]);$existing=$check->fetch();
if($existing){if($existing['submitted_at'])redirect('result.php?id='.(int)$existing['id']);exit('Percobaan sudah dimulai. Hubungi admin jika terjadi kendala.');}
$q=db()->prepare("SELECT id,question_text,points FROM questions WHERE quiz_id=? ORDER BY sort_order,id");$q->execute([$session['quiz_id']]);$questions=$q->fetchAll();
if(!$questions)exit('Kuis belum memiliki soal.');
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();
 $pdo=db();$pdo->beginTransaction();
 try{
  $max=array_sum(array_map(fn($x)=>(float)$x['points'],$questions));
  $ins=$pdo->prepare("INSERT INTO attempts(session_id,user_id,max_score) VALUES(?,?,?)");$ins->execute([$sessionId,current_user()['id'],$max]);$attemptId=(int)$pdo->lastInsertId();
  $get=$pdo->prepare("SELECT id,is_correct FROM options WHERE id=? AND question_id=?");
  $save=$pdo->prepare("INSERT INTO answers(attempt_id,question_id,selected_option_id,is_correct,awarded_points) VALUES(?,?,?,?,?)");
  $score=0;
  foreach($questions as $question){
   $selected=(int)($_POST['answers'][$question['id']]??0);$get->execute([$selected,$question['id']]);$opt=$get->fetch();
   $correct=$opt && (int)$opt['is_correct']===1;$points=$correct?(float)$question['points']:0;$score+=$points;
   $save->execute([$attemptId,$question['id'],$opt?(int)$opt['id']:null,$correct?1:0,$points]);
  }
  $upd=$pdo->prepare("UPDATE attempts SET score=?,submitted_at=NOW() WHERE id=?");$upd->execute([$score,$attemptId]);
  $pdo->prepare("INSERT INTO xp_history(user_id,points,reason) VALUES(?,?,?)")->execute([current_user()['id'],(int)round($score),'Completed quiz: '.$session['title']]);
  $pdo->commit();redirect('result.php?id='.$attemptId);
 }catch(Throwable $e){$pdo->rollBack();throw $e;}
}
$optionStmt=db()->prepare("SELECT id,option_text FROM options WHERE question_id=? ORDER BY id");
$pageTitle='Quiz Arena';require __DIR__ . '/includes/header.php';
?>
<div class="eyebrow">Quiz in progress</div><h1><?=e($session['title'])?></h1><p class="muted"><?=count($questions)?> questions · <?= (int)$session['duration_minutes']?> minutes</p>
<div class="alert">Periksa jawaban sebelum submit. MVP ini belum menyediakan fitur resume jika halaman ditutup.</div>
<form method="post" onsubmit="return confirm('Kumpulkan jawaban sekarang?')"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
<?php foreach($questions as $i=>$q):?><div class="question"><b><?=($i+1)?>. <?=e($q['question_text'])?></b><p class="muted"><?=e((string)$q['points'])?> points</p>
<?php $optionStmt->execute([$q['id']]);foreach($optionStmt->fetchAll() as $o):?><label class="option"><input type="radio" name="answers[<?=$q['id']?>]" value="<?=$o['id']?>"> <?=e($o['option_text'])?></label><?php endforeach;?>
</div><?php endforeach;?>
<button class="button">Submit answers</button></form>
<?php require __DIR__ . '/includes/footer.php'; ?>
