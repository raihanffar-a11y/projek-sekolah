<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
$quizId=(int)($_GET['id']??0);
$stmt=db()->prepare("SELECT * FROM quizzes WHERE id=?");$stmt->execute([$quizId]);$quiz=$stmt->fetch();
if(!$quiz) exit('Quiz tidak ditemukan.');
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();
 $question=trim($_POST['question']??'');$opts=array_map('trim',$_POST['options']??[]);$correct=(int)($_POST['correct']??-1);$points=max(0.5,(float)($_POST['points']??1));
 if($question===''||count($opts)!==4||in_array('', $opts,true)||$correct<0||$correct>3){$error='Isi pertanyaan, empat pilihan, dan pilih jawaban benar.';}
 else{
  $pdo=db();$pdo->beginTransaction();
  try{
   $s=$pdo->prepare("INSERT INTO questions(quiz_id,question_text,question_type,points) VALUES(?,?,'multiple_choice',?)");$s->execute([$quizId,$question,$points]);$qid=(int)$pdo->lastInsertId();
   $o=$pdo->prepare("INSERT INTO options(question_id,option_text,is_correct) VALUES(?,?,?)");
   foreach($opts as $i=>$opt)$o->execute([$qid,$opt,$i===$correct?1:0]);
   $pdo->commit();redirect('admin/questions.php?id='.$quizId);
  }catch(Throwable $e){$pdo->rollBack();throw $e;}
 }
}
$q=db()->prepare("SELECT q.id,q.quiz_id,q.question_text,q.question_type,q.points,q.sort_order,COALESCE(o_cnt.cnt,0) AS option_count FROM questions q LEFT JOIN (SELECT question_id,COUNT(*) AS cnt FROM options GROUP BY question_id) o_cnt ON o_cnt.question_id=q.id WHERE q.quiz_id=? ORDER BY q.id");$q->execute([$quizId]);$questions=$q->fetchAll();
$pageTitle='Question Builder';require __DIR__ . '/../includes/header.php';
?>
<div class="eyebrow">Quiz builder · Step 2</div><h1><?=e($quiz['title'])?></h1><p class="muted">Tambahkan pertanyaan pilihan ganda. Kunci jawaban disimpan di server.</p>
<?php if($error):?><div class="alert"><?=e($error)?></div><?php endif;?>
<div class="form-card"><h2>Add question</h2><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
<div class="field"><label>Question</label><textarea name="question" rows="3" required></textarea></div>
<?php foreach(['A','B','C','D'] as $i=>$letter):?><div class="field"><label>Option <?=$letter?></label><input name="options[]" required></div><?php endforeach;?>
<div class="field"><label>Correct answer</label><select name="correct"><?php foreach(['A','B','C','D'] as $i=>$letter):?><option value="<?=$i?>"><?=$letter?></option><?php endforeach;?></select></div>
<div class="field"><label>Points</label><input type="number" name="points" value="1" min="0.5" step="0.5"></div>
<button class="button full">Save question</button></form></div>
<h2 class="section-title">Questions (<?=count($questions)?>)</h2>
<?php foreach($questions as $i=>$item):?><div class="card question"><b><?=($i+1)?>. <?=e($item['question_text'])?></b><p class="muted"><?=e((string)$item['points'])?> points</p></div><?php endforeach;?>
<div class="hero-actions"><a class="button" href="<?=BASE_URL?>/admin/sessions.php?id=<?=$quizId?>">Create session</a><a class="button secondary" href="<?=BASE_URL?>/admin/quizzes.php">Back to quizzes</a></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
