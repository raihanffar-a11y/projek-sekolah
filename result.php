<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$id=(int)($_GET['id']??0);
$stmt=db()->prepare("SELECT a.*,q.title,q.created_by quiz_owner,s.id session_id,s.session_code,u.name FROM attempts a JOIN sessions s ON s.id=a.session_id JOIN quizzes q ON q.id=s.quiz_id JOIN users u ON u.id=a.user_id WHERE a.id=?");$stmt->execute([$id]);$result=$stmt->fetch();
if(!$result)exit('Hasil tidak ditemukan.');
if(current_user()['role']!=='admin'&&(int)$result['user_id']!==(int)current_user()['id']&&(int)$result['quiz_owner']!==(int)current_user()['id']){http_response_code(403);exit('Akses ditolak.');}
$answers=db()->prepare("SELECT an.is_correct,an.awarded_points,qu.question_text,qu.explanation,op.option_text selected,correct_option.option_text correct_answer FROM answers an JOIN questions qu ON qu.id=an.question_id LEFT JOIN options op ON op.id=an.selected_option_id LEFT JOIN options correct_option ON correct_option.question_id=qu.id AND correct_option.is_correct=1 WHERE an.attempt_id=? ORDER BY qu.sort_order,qu.id");$answers->execute([$id]);$details=$answers->fetchAll();
$boardStmt=db()->prepare("SELECT u.name,a.score,a.correct_count,a.accuracy_percent,a.completion_seconds FROM attempts a JOIN users u ON u.id=a.user_id WHERE a.session_id=? AND a.submitted_at IS NOT NULL ORDER BY a.score DESC,u.name");$boardStmt->execute([(int)$result['session_id']]);$leaderboard=$boardStmt->fetchAll();
$rank=0;$previousScore=null;foreach($leaderboard as $index=>&$entry){$score=(int)$entry['score'];if($previousScore===null||$score!==$previousScore)$rank=$index+1;$entry['rank']=$rank;$previousScore=$score;}unset($entry);
$pageTitle='Quiz result';require __DIR__ . '/includes/header.php';
?>
<div class="eyebrow">Quiz completed</div>
<div class="result-header">
  <div>
    <h1><?=e($result['title'])?></h1>
    <p class="muted"><?=e($result['name'])?></p>
  </div>
  <div class="result-score"><?=e((string)$result['score'])?> <span class="muted">/ <?=e((string)$result['max_score'])?></span></div>
  <p class="muted">Submitted: <?=e($result['submitted_at']??'-')?></p>
</div>
<div class="grid result-summary">
  <div class="card"><span>Total soal</span><strong><?= (int)$result['correct_count']+(int)$result['incorrect_count']+(int)$result['unanswered_count'] ?></strong></div>
  <div class="card"><span>Benar</span><strong><?= (int)$result['correct_count'] ?></strong></div>
  <div class="card"><span>Salah</span><strong><?= (int)$result['incorrect_count'] ?></strong></div>
  <div class="card"><span>Tidak dijawab</span><strong><?= (int)$result['unanswered_count'] ?></strong></div>
  <div class="card"><span>Akurasi</span><strong><?= e((string)$result['accuracy_percent']) ?>%</strong></div>
  <div class="card"><span>Waktu pengerjaan</span><strong><?= e((string)($result['completion_seconds']??0)) ?> dtk</strong></div>
</div>
<div class="progress-wrap">
  <div class="progress-label"><span>Score</span><span><?=e((string)$result['score'])?> / <?=e((string)$result['max_score'])?></span></div>
  <div class="progress-track"><div class="progress-fill" style="width:<?= ($result['max_score']>0?(int)round(($result['score']/$result['max_score'])*100):0) ?>%"></div></div>
</div>
<h2 class="section-title">Answer review</h2>
<?php foreach($details as $i=>$a):?><div class="question"><span class="question-number"><?=($i+1)?></span><b><?=e($a['question_text'])?></b><p>Jawaban Anda: <?=e($a['selected']??'Tidak dijawab')?></p><p>Jawaban benar: <?=e($a['correct_answer']??'-')?></p><?php if($a['explanation']):?><p class="muted"><?=e($a['explanation'])?></p><?php endif;?><span class="badge"><?=((int)$a['is_correct']===1)?'Correct':'Incorrect'?> · <?=e((string)$a['awarded_points'])?> poin</span></div><?php endforeach;?>
<h2 class="section-title">Leaderboard</h2>
<div class="table-wrap"><table class="table"><thead><tr><th>Peringkat</th><th>Peserta</th><th>Skor</th><th>Benar</th><th>Akurasi</th></tr></thead><tbody>
<?php foreach($leaderboard as $entry):?><tr><td><?= (int)$entry['rank'] ?></td><td><?=e($entry['name'])?></td><td><?= (int)$entry['score'] ?></td><td><?= (int)$entry['correct_count'] ?></td><td><?=e((string)$entry['accuracy_percent'])?>%</td></tr><?php endforeach;?>
<?php if(!$leaderboard):?><tr><td colspan="5" class="muted">Belum ada hasil.</td></tr><?php endif;?>
</tbody></table></div>
<div class="hero-actions"><a class="button" href="<?=BASE_URL?>/dashboard.php">Back to dashboard</a></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
