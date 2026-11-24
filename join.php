<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();$code=strtoupper(trim($_POST['code']??''));
 if($code===''){$error='Kode sesi wajib diisi.';}
 else{
  $stmt=db()->prepare("SELECT s.id,s.quiz_id,s.status,s.start_at,s.end_at,q.title FROM sessions s JOIN quizzes q ON q.id=s.quiz_id WHERE s.session_code=? AND s.mode='assignment' AND q.status='published'");$stmt->execute([$code]);$session=$stmt->fetch();
  if(!$session)$error='Kode sesi tidak ditemukan.';
  elseif($session['status']!=='open')$error='Sesi sudah ditutup.';
  else{
   $now=new DateTimeImmutable('now');
   if($session['start_at']&&new DateTimeImmutable($session['start_at'])>$now)$error='Sesi belum dibuka. Silakan coba lagi nanti.';
   elseif($session['end_at']&&new DateTimeImmutable($session['end_at'])<$now)$error='Sesi sudah ditutup.';
   else{
    $count=db()->prepare("SELECT COUNT(*) FROM questions WHERE quiz_id=?");$count->execute([(int)$session['quiz_id']]);
    $attempt=db()->prepare("INSERT IGNORE INTO attempts(session_id,user_id,score,max_score,created_at) VALUES(?,?,0,?,NOW())");
    $attempt->execute([(int)$session['id'],(int)current_user()['id'],(int)$count->fetchColumn()*100]);
    redirect('take_quiz.php?session_id='.(int)$session['id']);
   }
  }
 }
}
$pageTitle='Join quiz';require __DIR__ . '/includes/header.php';
?>
<div class="form-card"><div class="eyebrow">Quiz Arena</div><h1>Join a session</h1><p class="muted">Masukkan kode sesi dari guru atau admin.</p>
<?php if($error):?><div class="alert"><?=e($error)?></div><?php endif;?>
<form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><div class="field"><label>Session code</label><input name="code" maxlength="12" required placeholder="Ex. A1B2C3" class="code-input"></div><button class="button full">Find session</button></form></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
