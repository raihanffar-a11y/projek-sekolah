<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
$error='';
$categories=db()->query("SELECT * FROM categories ORDER BY name")->fetchAll();
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf(); $title=trim($_POST['title']??''); $desc=trim($_POST['description']??''); $cat=(int)($_POST['category_id']??0); $difficulty=$_POST['difficulty']??'beginner'; $visibility=$_POST['visibility']??'private'; $duration=max(1,min(180,(int)($_POST['duration']??15)));
 if($title==='') $error='Judul kuis wajib diisi.';
 elseif(!in_array($difficulty,['beginner','intermediate','advanced'],true)) $error='Tingkat kesulitan tidak valid.';
 elseif(!in_array($visibility,['private','public'],true)) $error='Visibilitas kuis tidak valid.';
 else{
  $stmt=db()->prepare("INSERT INTO quizzes(category_id,created_by,title,description,difficulty,visibility,duration_minutes,status) VALUES(?,?,?,?,?,?,?,'draft')");
  $stmt->execute([$cat?:null,current_user()['id'],$title,$desc,$difficulty,$visibility,$duration]);
  redirect('admin/questions.php?id='.(int)db()->lastInsertId());
 }
}
$pageTitle='Create quiz';require __DIR__ . '/../includes/header.php';
?>
<div class="form-card"><div class="eyebrow">Quiz builder · Step 1</div><h1>Create new quiz</h1>
<?php if($error): ?><div class="alert"><?=e($error)?></div><?php endif; ?>
<form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
<div class="field-group"><label>Quiz title</label><input name="title" required maxlength="150" placeholder="Example: Simple Past Tense"></div>
<div class="field-group"><label>Description</label><textarea name="description" rows="3"></textarea></div>
<div class="field-group"><label>Category</label><select name="category_id"><option value="0">Choose category</option><?php foreach($categories as $c): ?><option value="<?=$c['id']?>"><?=e($c['name'])?></option><?php endforeach;?></select></div>
<div class="field-group"><label>Difficulty</label><select name="difficulty"><option value="beginner">Beginner</option><option value="intermediate">Intermediate</option><option value="advanced">Advanced</option></select></div>
<div class="field-group"><label>Visibility</label><select name="visibility"><option value="private">Private (invite code only)</option><option value="public">Public</option></select></div>
<div class="field-group"><label>Duration (minutes)</label><input type="number" name="duration" min="1" max="180" value="15"><p class="helper">How long students have to complete the quiz.</p></div>
<button class="button full">Continue to questions</button></form></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
