<?php
require_once __DIR__ . '/includes/auth.php';
require_role('student');
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();$code=strtoupper(trim($_POST['code']??''));
 if($code===''){$error='Kode sesi wajib diisi.';}
 else{
  $stmt=db()->prepare("SELECT qr.id,qr.status,qr.start_at,qr.end_at,qr.title FROM quiz_rooms qr WHERE qr.session_code=?");$stmt->execute([$code]);$room=$stmt->fetch();
  if(!$room)$error='Kode sesi tidak ditemukan.';
  elseif($room['status']!=='open')$error='Quiz room sudah ditutup.';
  else{
   $now=new DateTimeImmutable('now');
   if($room['start_at']&&new DateTimeImmutable($room['start_at'])>$now)$error='Quiz room belum dibuka.';
   elseif($room['end_at']&&new DateTimeImmutable($room['end_at'])<$now)$error='Quiz room sudah ditutup.';
   else{
    // Cek apakah sudah ada attempt yang disubmit
    $uid=(int)current_user()['id'];
    $chk=db()->prepare("SELECT id,submitted_at FROM attempts WHERE quiz_room_id=? AND user_id=?");
    $chk->execute([(int)$room['id'],$uid]);$existing=$chk->fetch();
    if($existing&&$existing['submitted_at'])redirect('result_quiz_room.php?id='.(int)$existing['id']);
    redirect('take_quiz_room.php?room_id='.(int)$room['id']);
   }
  }
 }
}
$pageTitle='Join Quiz Room';require __DIR__ . '/includes/header.php';
?>
<div class="form-card"><div class="eyebrow">Quiz Arena</div><h1>Join a quiz room</h1><p class="muted">Masukkan kode sesi dari guru atau admin.</p>
<?php if($error):?><div class="alert"><?=e($error)?></div><?php endif;?>
<form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><div class="field"><label>Session code</label><input name="code" maxlength="12" required placeholder="Ex. A1B2C3" class="code-input"></div><button class="button full">Find session</button></form></div>
<?php require __DIR__ . '/includes/footer.php'; ?>