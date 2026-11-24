<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
$quizId=(int)($_GET['id']??0);
$stmt=db()->prepare("SELECT * FROM quizzes WHERE id=?");$stmt->execute([$quizId]);$quiz=$stmt->fetch();
if(!$quiz) exit('Quiz tidak ditemukan.');
if((current_user()['role']??'')!=='admin'&&(int)$quiz['created_by']!==(int)current_user()['id']){http_response_code(403);exit('Akses ditolak.');}
$sessionCount=db()->prepare('SELECT COUNT(*) FROM sessions WHERE quiz_id=?');$sessionCount->execute([$quizId]);$hasSessions=(int)$sessionCount->fetchColumn()>0;
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 if($hasSessions){http_response_code(409);exit('Kuis sudah memiliki sesi. Duplikasi kuis untuk mengubah soal tanpa mengubah riwayat peserta.');}
 verify_csrf();
 $question=trim($_POST['question']??'');$explanation=trim($_POST['explanation']??'');$duration=max(5,min(600,(int)($_POST['duration']??30)));$opts=array_map('trim',$_POST['options']??[]);$correct=(int)($_POST['correct']??-1);
 if($question===''||count($opts)!==4||in_array('', $opts,true)||$correct<0||$correct>3){$error='Isi pertanyaan, empat pilihan, dan pilih jawaban benar.';}
 else{
  $pdo=db();$pdo->beginTransaction();
  try{
   $s=$pdo->prepare("INSERT INTO questions(quiz_id,question_text,explanation,question_type,points,duration_seconds,sort_order) VALUES(?,?,?,'multiple_choice',100,?,(SELECT COALESCE(MAX(sort_order),0)+1 FROM (SELECT sort_order FROM questions WHERE quiz_id=?) q))");$s->execute([$quizId,$question,$explanation?:null,$duration,$quizId]);$qid=(int)$pdo->lastInsertId();
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
<?php if($hasSessions):?><div class="alert">Kuis yang sudah memiliki sesi dikunci untuk menjaga keutuhan hasil. Duplikasi kuis jika ingin membuat versi baru.</div><?php endif;?>
<?php if($error):?><div class="alert"><?=e($error)?></div><?php endif;?>
<div class="form-card"><h2>Add question</h2><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
<div class="field-group"><label>Question</label><textarea name="question" rows="3" required <?= $hasSessions?'disabled':'' ?>></textarea></div>
<?php foreach(['A','B','C','D'] as $i=>$letter):?><div class="field-group"><label>Option <?=$letter?></label><input name="options[]" required <?= $hasSessions?'disabled':'' ?>></div><?php endforeach;?>
<div class="field-group"><label>Correct answer</label><select name="correct" <?= $hasSessions?'disabled':'' ?>><?php foreach(['A','B','C','D'] as $i=>$letter):?><option value="<?=$i?>"><?=$letter?></option><?php endforeach;?></select></div>
<div class="field-group"><label>Explanation (optional)</label><textarea name="explanation" rows="2" <?= $hasSessions?'disabled':'' ?>></textarea></div>
<div class="field-group"><label>Time per question (seconds)</label><input type="number" name="duration" value="30" min="5" max="600" <?= $hasSessions?'disabled':'' ?>></div>
<p class="helper">Setiap jawaban benar bernilai tepat 100 poin.</p>
<button class="button full" <?= $hasSessions?'disabled':'' ?>>Save question</button></form></div>
<h2 class="section-title">Questions (<?=count($questions)?>)</h2>
<?php foreach($questions as $i=>$item):?><div class="card question"><b><?=($i+1)?>. <?=e($item['question_text'])?></b><p class="muted"><?=e((string)$item['points'])?> points</p></div><?php endforeach;?>
<div class="hero-actions"><a class="button" href="<?=BASE_URL?>/admin/sessions.php?id=<?=$quizId?>">Create session</a><a class="button secondary" href="<?=BASE_URL?>/admin/quizzes.php">Back to quizzes</a></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
