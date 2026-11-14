<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo = db();
$sessionId=(int)($_GET['session_id']??0);
$export = ($_GET['export'] ?? '') === 'csv';

$sql="SELECT a.id,a.score,a.max_score,a.submitted_at,u.name,u.email,q.title,s.session_code FROM attempts a JOIN users u ON u.id=a.user_id JOIN sessions s ON s.id=a.session_id JOIN quizzes q ON q.id=s.quiz_id WHERE a.submitted_at IS NOT NULL";
$params=[];

if($sessionId){$sql.=" AND s.id=?";$params[]=$sessionId;}
$sql.=" ORDER BY a.submitted_at DESC";
$stmt=db()->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll();

// Export CSV
if ($export) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="englishhub_quiz_results.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['No', 'Nama', 'Email', 'Quiz', 'Session', 'Skor', 'Maksimal', 'Tanggal'], ',', '"');
    $no = 1;
    foreach ($rows as $r) {
        fputcsv($out, [
            $no++,
            $r['name'],
            $r['email'],
            $r['title'],
            $r['session_code'],
            $r['score'],
            $r['max_score'],
            $r['submitted_at'],
        ], ',', '"');
    }
    fclose($out);
    exit;
}

$pageTitle='Results & Analytics';require __DIR__ . '/../includes/header.php';
?>
<div class="eyebrow">Performance analytics</div><h1>Results & Analytics</h1><p class="muted">Semua hasil pengerjaan yang sudah dikumpulkan.</p>
<hr class="section-divider">
<div class="table-header-row">
    <form method="get" style="display:inline; margin-right:12px;">
        <label style="font-size:13px; font-weight:600; color:var(--muted);">Session:</label>
        <select name="session_id" style="font-size:13px; padding:4px 8px;" onchange="this.form.submit()">
            <option value="0">Semua session</option>
            <?php
            $sessions = $pdo->query("SELECT id, session_code FROM sessions ORDER BY created_at DESC")->fetchAll();
            foreach ($sessions as $s): ?>
                <option value="<?= (int)$s['id'] ?>" <?= $sessionId === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['session_code']) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <?php if (!empty($rows)): ?>
        <a class="button" href="?session_id=<?= $sessionId ?>&export=csv">Export CSV</a>
    <?php endif; ?>
    <a class="button secondary" href="<?= BASE_URL ?>/admin/quizzes.php">Kelola Quiz</a>
</div>
<div class="table-wrap"><table class="table"><tr><th>Student</th><th>Quiz</th><th>Session</th><th>Score</th><th>Submitted</th><th>Detail</th></tr>
<?php foreach($rows as $r):?><tr><td><?=e($r['name'])?><br><small class="muted"><?=e($r['email'])?></small></td><td><?=e($r['title'])?></td><td><?=e($r['session_code'])?></td><td><b><?=e((string)$r['score'])?> / <?=e((string)$r['max_score'])?></b></td><td><?=e($r['submitted_at'])?></td><td><a href="<?=BASE_URL?>/result.php?id=<?=$r['id']?>">View</a></td></tr><?php endforeach;?>
<?php if(!$rows):?><tr><td colspan="6" class="muted">Belum ada hasil pengerjaan.</td></tr><?php endif;?></table></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
