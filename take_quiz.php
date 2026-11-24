<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

// Toleransi keterlambatan submit (detik) untuk latensi jaringan.
const SUBMIT_GRACE_SECONDS = 60;

function find_session_attempt(PDO $pdo, int $sessionId, int $userId)
{
    $s = $pdo->prepare("SELECT id, score, submitted_at, created_at FROM attempts WHERE session_id=? AND user_id=?");
    $s->execute([$sessionId, $userId]);
    return $s->fetch();
}

/** Nilai jawaban di server dan kunci attempt agar tidak dapat dikumpulkan dua kali. */
function submit_session_attempt(PDO $pdo, int $attemptId, int $userId, array $session, array $questions, array $posted): void
{
    $pdo->beginTransaction();
    try {
        $lock = $pdo->prepare("SELECT submitted_at, created_at FROM attempts WHERE id=? AND user_id=? FOR UPDATE");
        $lock->execute([$attemptId, $userId]);
        $row = $lock->fetch();
        if (!$row || $row['submitted_at']) { $pdo->commit(); return; }

        $get = $pdo->prepare("SELECT id, is_correct FROM options WHERE id=? AND question_id=?");
        $save = $pdo->prepare("INSERT INTO answers(attempt_id,question_id,selected_option_id,is_correct,awarded_points) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE selected_option_id=VALUES(selected_option_id),is_correct=VALUES(is_correct),awarded_points=VALUES(awarded_points)");

        $score = 0;
        foreach ($questions as $question) {
            $raw = $posted[$question['id']] ?? 0;
            $selected = is_scalar($raw) ? (int)$raw : 0;
            $correct = false;
            $points = 0;
            if ($selected > 0) {
                $get->execute([$selected, $question['id']]);
                $opt = $get->fetch();
                if (!$opt) $selected = 0; // opsi bukan milik soal ini
                $correct = $opt && (int)$opt['is_correct'] === 1;
                $points = $correct ? 100 : 0;
                $score += $points;
            }
            $save->execute([$attemptId, $question['id'], $selected > 0 ? $selected : null, $correct ? 1 : 0, $points]);
        }

        $summaryStmt = $pdo->prepare(
            'SELECT COALESCE(SUM(selected_option_id IS NOT NULL), 0) answered_count, COALESCE(SUM(is_correct), 0) correct_count,
                    COALESCE(SUM(awarded_points), 0) score
             FROM answers WHERE attempt_id = ?'
        );
        $summaryStmt->execute([$attemptId]);
        $summary = $summaryStmt->fetch();
        $correctCount = (int)$summary['correct_count'];
        $answeredCount = (int)$summary['answered_count'];
        $totalQuestions = count($questions);
        $finalScore = (int)$summary['score'];
        $accuracy = $totalQuestions > 0 ? round($correctCount * 100 / $totalQuestions, 2) : 0;
        $seconds = max(0, time() - strtotime($row['created_at']));
        $pdo->prepare(
            'UPDATE attempts
             SET score=?, max_score=?, correct_count=?, incorrect_count=?, unanswered_count=?,
                 accuracy_percent=?, completion_seconds=?, submitted_at=NOW()
             WHERE id=?'
        )->execute([
            $finalScore, $totalQuestions * 100, $correctCount,
            max(0, $answeredCount - $correctCount), max(0, $totalQuestions - $answeredCount),
            $accuracy, $seconds, $attemptId,
        ]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

$pdo = db();
$sessionId = (int)($_GET['session_id'] ?? 0);
$userId = (int)current_user()['id'];

$stmt = $pdo->prepare("SELECT s.*, q.title, q.description, q.duration_minutes FROM sessions s JOIN quizzes q ON q.id=s.quiz_id WHERE s.id=? AND s.status='open' AND s.mode='assignment'");
$stmt->execute([$sessionId]);
$session = $stmt->fetch();
if (!$session) exit('Sesi tidak tersedia.');

$now = new DateTimeImmutable('now');
if ($session['start_at'] && new DateTimeImmutable($session['start_at']) > $now) exit('Sesi belum dibuka. Silakan coba lagi pada waktu yang ditentukan.');

$existing = find_session_attempt($pdo, $sessionId, $userId);
if ($existing && $existing['submitted_at']) {
    redirect('result.php?id=' . (int)$existing['id']);
    exit;
}

$q = $pdo->prepare("SELECT id, question_text, points FROM questions WHERE quiz_id=? ORDER BY sort_order, id");
$q->execute([$session['quiz_id']]);
$questions = $q->fetchAll();
if (!$questions) exit('Kuis belum memiliki soal.');

if (!$existing) {
    exit('Masuk ke assignment menggunakan kode room sebelum mulai mengerjakan.');
}

$attemptId = (int)$existing['id'];
$durationDeadline = (new DateTimeImmutable($existing['created_at']))
    ->modify('+' . (int)$session['duration_minutes'] . ' minutes')
    ->getTimestamp();
$deadlineTs = $session['end_at']
    ? min($durationDeadline, (new DateTimeImmutable($session['end_at']))->getTimestamp())
    : $durationDeadline;

if (time() > $deadlineTs && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    submit_session_attempt($pdo, $attemptId, $userId, $session, $questions, []);
    redirect('result.php?id=' . $attemptId);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $late = time() > $deadlineTs + SUBMIT_GRACE_SECONDS;
    $posted = (!$late && isset($_POST['answers']) && is_array($_POST['answers'])) ? $_POST['answers'] : [];
    submit_session_attempt($pdo, $attemptId, $userId, $session, $questions, $posted);
    redirect('result.php?id=' . $attemptId);
    exit;
}

// Waktu habis tanpa submit -> kunci attempt
if (time() > $deadlineTs) {
    submit_session_attempt($pdo, $attemptId, $userId, $session, $questions, []);
    redirect('result.php?id=' . $attemptId);
    exit;
}
$remaining = $deadlineTs - time();

$optionStmt = $pdo->prepare("SELECT id, option_text FROM options WHERE question_id=? ORDER BY id");
$pageTitle = 'Quiz Arena';
require __DIR__ . '/includes/header.php';
?>
<div class="eyebrow">Quiz in progress</div><h1><?= e($session['title']) ?></h1>
<p class="muted"><?= count($questions) ?> questions · <?= (int)$session['duration_minutes'] ?> minutes</p>
<hr class="section-divider">
<div class="alert">Sisa waktu: <b id="timeLeft">--:--</b>. Jawaban dikumpulkan otomatis saat waktu habis.</div>
<form method="post" id="quizForm" onsubmit="return confirm('Kumpulkan jawaban sekarang?')"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
<?php foreach ($questions as $i => $qu): ?>
<div class="question"><span class="question-number"><?= ($i + 1) ?></span><b><?= e($qu['question_text']) ?></b><p class="muted">100 points</p>
<?php $optionStmt->execute([$qu['id']]); foreach ($optionStmt->fetchAll() as $o): ?>
<label class="option"><input type="radio" name="answers[<?= (int)$qu['id'] ?>]" value="<?= (int)$o['id'] ?>"><?= e($o['option_text']) ?></label>
<?php endforeach; ?>
</div>
<?php endforeach; ?>
<button class="button">Submit answers</button></form>
<script>
(function () {
  var left = <?= (int)$remaining ?>;
  var el = document.getElementById('timeLeft');
  var form = document.getElementById('quizForm');
  function fmt(s) { var m = Math.floor(s / 60), r = s % 60; return m + ':' + (r < 10 ? '0' : '') + r; }
  function tick() {
    el.textContent = fmt(Math.max(left, 0));
    if (left <= 0) { form.submit(); return; }
    left--;
    setTimeout(tick, 1000);
  }
  tick();
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
