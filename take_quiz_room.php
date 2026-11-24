<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/achievements.php';
require_login();

// Toleransi keterlambatan submit (detik) untuk latensi jaringan.
const SUBMIT_GRACE_SECONDS = 60;

// ---------------------------------------------------------------------------
// Helper
// ---------------------------------------------------------------------------

function find_room_attempt(PDO $pdo, int $roomId, int $userId)
{
    $s = $pdo->prepare("SELECT id, score, submitted_at, created_at FROM attempts WHERE quiz_room_id=? AND user_id=?");
    $s->execute([$roomId, $userId]);
    return $s->fetch();
}

/** Samarkan kata: huruf pertama tetap, sisanya "_" (spasi dan "-" dipertahankan). */
function mask_word(string $word): string
{
    $chars = preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY);
    $out = '';
    foreach ($chars as $i => $ch) {
        $out .= ($i === 0 || $ch === ' ' || $ch === '-') ? $ch : '_';
    }
    return $out;
}

/**
 * Ambil 3 pengecoh unik dari $field.
 * Kata/arti yang sama dengan soal dilewati, supaya tidak ada dua jawaban benar.
 */
function pick_distractors(array $pool, string $field, string $word, string $meaning, int $n = 3): array
{
    shuffle($pool);
    $w = mb_strtolower($word);
    $m = mb_strtolower($meaning);
    $seen = [];
    $out = [];
    foreach ($pool as $v) {
        if (mb_strtolower($v['word_en']) === $w || mb_strtolower($v['meaning_id']) === $m) continue;
        $key = mb_strtolower($v[$field]);
        if ($key === '' || isset($seen[$key])) continue;
        $seen[$key] = true;
        $out[] = $v[$field];
        if (count($out) === $n) break;
    }
    return $out;
}

/** Buat satu soal. Teks disimpan MENTAH (escape dilakukan saat ditampilkan). */
function build_question(string $format, array $v, array $pool, bool $randomAnswers): array
{
    $word = $v['word_en'];
    $meaning = $v['meaning_id'];

    switch ($format) {
        case 'english_to_indonesian':
            $text = 'Apa arti bahasa Indonesia dari kata "' . $word . '"?';
            $correct = $meaning; $field = 'meaning_id';
            break;
        case 'context':
            $text = 'Dalam penggunaan sehari-hari, "' . $word . '" paling tepat berarti ...';
            $correct = $meaning; $field = 'meaning_id';
            break;
        case 'indonesian_to_english':
            $text = 'Kata bahasa Inggris untuk "' . $meaning . '" adalah?';
            $correct = $word; $field = 'word_en';
            break;
        default: // fill_blank
            $text = 'Lengkapi kata bahasa Inggris untuk "' . $meaning . '":  ' . mask_word($word);
            $correct = $word; $field = 'word_en';
            break;
    }

    $options = array_merge([$correct], pick_distractors($pool, $field, $word, $meaning));
    if (count($options) < 4) {
        throw new RuntimeException('Kosakata aktif terlalu sedikit untuk membuat pilihan jawaban.');
    }

    if ($randomAnswers) {
        shuffle($options);
    } else {
        sort($options, SORT_NATURAL | SORT_FLAG_CASE); // urutan tetap, posisi jawaban benar tetap bervariasi
    }

    $idx = array_search($correct, $options, true);

    return [
        'question_text'  => $text,
        'correct_answer' => $correct,
        'option_a' => $options[0], 'option_b' => $options[1],
        'option_c' => $options[2], 'option_d' => $options[3],
        'correct_option' => ['A', 'B', 'C', 'D'][$idx],
    ];
}

/** Buat attempt + soal. Jumlah soal mengikuti question_count room. */
function create_room_attempt(PDO $pdo, array $room, int $userId): void
{
    $vocabStmt = $pdo->prepare("SELECT vocab_number, word_en, meaning_id FROM vocabularies WHERE vocab_number BETWEEN ? AND ? AND is_active=1 ORDER BY vocab_number");
    $vocabStmt->execute([(int)$room['start_number'], (int)$room['end_number']]);
    $vocabs = $vocabStmt->fetchAll();
    if (!$vocabs) exit('Tidak ada kosakata dalam rentang ini.');

    $pool = $pdo->query("SELECT word_en, meaning_id FROM vocabularies WHERE is_active=1")->fetchAll();
    if (count($pool) < 4) exit('Kosakata aktif terlalu sedikit untuk membuat kuis.');

    if ((int)$room['random_questions'] === 1) shuffle($vocabs);
    $count = min(max(1, (int)$room['question_count']), count($vocabs));
    $vocabs = array_slice($vocabs, 0, $count);

    $formats = ['english_to_indonesian', 'indonesian_to_english', 'context', 'fill_blank'];
    $randomAnswers = (int)$room['random_answers'] === 1;
    $startedAt = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s'); // dari PHP, agar konsisten dengan hitungan deadline

    $pdo->beginTransaction();
    try {
        $pdo->prepare("INSERT INTO attempts (session_id, user_id, quiz_room_id, score, max_score, created_at) VALUES (NULL, ?, ?, 0, ?, ?)")
            ->execute([$userId, (int)$room['id'], count($vocabs) * 100, $startedAt]);
        $attemptId = (int)$pdo->lastInsertId();

        $ins = $pdo->prepare("
            INSERT INTO attempt_quiz_questions
              (attempt_id, vocab_number, position, format_type, question_text, correct_answer, option_a, option_b, option_c, option_d, correct_option)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($vocabs as $i => $v) {
            $format = $formats[$i % 4];
            $q = build_question($format, $v, $pool, $randomAnswers);
            $ins->execute([
                $attemptId, (int)$v['vocab_number'], $i + 1, $format,
                $q['question_text'], $q['correct_answer'],
                $q['option_a'], $q['option_b'], $q['option_c'], $q['option_d'],
                $q['correct_option'],
            ]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/** Nilai jawaban, simpan, beri XP + achievement. Aman dipanggil dua kali (attempt dikunci). */
function submit_room_attempt(PDO $pdo, int $attemptId, int $userId, array $room, array $questions, array $posted): void
{
    $pdo->beginTransaction();
    try {
        $lock = $pdo->prepare("SELECT submitted_at, created_at FROM attempts WHERE id=? AND user_id=? FOR UPDATE");
        $lock->execute([$attemptId, $userId]);
        $row = $lock->fetch();
        if (!$row || $row['submitted_at']) { $pdo->commit(); return; }

        $upd = $pdo->prepare("UPDATE attempt_quiz_questions SET selected_option=?, is_correct=? WHERE id=? AND attempt_id=?");
        foreach ($questions as $q) {
            $raw = $posted[$q['id']] ?? '';
            $sel = is_scalar($raw) ? strtoupper(trim((string)$raw)) : '';
            if (!in_array($sel, ['A', 'B', 'C', 'D'], true)) $sel = null;
            $ok = ($sel !== null && $sel === $q['correct_option']) ? 1 : 0;
            $upd->execute([$sel, $ok, $q['id'], $attemptId]);
        }

        $summaryStmt = $pdo->prepare(
            'SELECT COUNT(*) answered_count, COALESCE(SUM(is_correct), 0) correct_count
             FROM attempt_quiz_questions WHERE attempt_id = ? AND selected_option IS NOT NULL'
        );
        $summaryStmt->execute([$attemptId]);
        $summary = $summaryStmt->fetch();
        $correctCount = (int)$summary['correct_count'];
        $answeredCount = (int)$summary['answered_count'];
        $totalQuestions = count($questions);
        $score = $correctCount * 100;
        $accuracy = $totalQuestions > 0 ? round($correctCount * 100 / $totalQuestions, 2) : 0;
        $seconds = max(0, time() - strtotime($row['created_at']));
        $pdo->prepare(
            'UPDATE attempts
             SET score=?, max_score=?, correct_count=?, incorrect_count=?, unanswered_count=?,
                 accuracy_percent=?, completion_seconds=?, submitted_at=NOW()
             WHERE id=?'
        )->execute([
            $score, $totalQuestions * 100, $correctCount,
            max(0, $answeredCount - $correctCount), max(0, $totalQuestions - $answeredCount),
            $accuracy, $seconds, $attemptId,
        ]);

        $c = $pdo->prepare("SELECT COUNT(*) FROM attempts WHERE user_id=? AND submitted_at IS NOT NULL");
        $c->execute([$userId]);
        $totalQuiz = (int)$c->fetchColumn();

        $x = $pdo->prepare("SELECT COALESCE(SUM(points), 0) FROM xp_history WHERE user_id=?");
        $x->execute([$userId]);
        $totalXp = (float)$x->fetchColumn();

        $newBadges = award_achievements($userId, $score, $totalQuestions * 100, $totalQuiz, $totalXp);
        if (!empty($newBadges)) $_SESSION['new_badges'] = $newBadges;

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

// ---------------------------------------------------------------------------
// Alur halaman
// ---------------------------------------------------------------------------

$pdo = db();
$roomId = (int)($_GET['room_id'] ?? 0);
$userId = (int)current_user()['id'];

$roomStmt = $pdo->prepare("SELECT * FROM quiz_rooms WHERE id=? AND status='open'");
$roomStmt->execute([$roomId]);
$room = $roomStmt->fetch();
if (!$room) exit('Quiz room tidak tersedia atau sudah ditutup.');

$now = new DateTimeImmutable('now');
if ($room['start_at'] && new DateTimeImmutable($room['start_at']) > $now) exit('Quiz room belum dibuka.');
if ($room['end_at'] && new DateTimeImmutable($room['end_at']) < $now) exit('Quiz room sudah ditutup.');

$existing = find_room_attempt($pdo, $roomId, $userId);
if ($existing && $existing['submitted_at']) {
    redirect('result_quiz_room.php?id=' . (int)$existing['id']);
    exit;
}

if (!$existing) {
    try {
        create_room_attempt($pdo, $room, $userId);
    } catch (PDOException $e) {
        if ($e->getCode() !== '23000') throw $e; // 23000 = attempt sudah dibuat request lain (double-click / 2 tab)
    }
    $existing = find_room_attempt($pdo, $roomId, $userId);
    if (!$existing) exit('Gagal membuat sesi pengerjaan.');
}

$attemptId = (int)$existing['id'];
$qStmt = $pdo->prepare("SELECT * FROM attempt_quiz_questions WHERE attempt_id=? ORDER BY position");
$qStmt->execute([$attemptId]);
$questions = $qStmt->fetchAll();
if (!$questions) exit('Gagal memuat soal.');

$deadlineTs = (new DateTimeImmutable($existing['created_at']))
    ->modify('+' . (int)$room['duration_minutes'] . ' minutes')
    ->getTimestamp();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $late = time() > $deadlineTs + SUBMIT_GRACE_SECONDS;
    $posted = (!$late && isset($_POST['answers']) && is_array($_POST['answers'])) ? $_POST['answers'] : [];
    submit_room_attempt($pdo, $attemptId, $userId, $room, $questions, $posted);
    redirect('result_quiz_room.php?id=' . $attemptId);
    exit;
}

// Waktu habis sebelum sempat submit -> kunci attempt agar tidak menggantung
if (time() > $deadlineTs) {
    submit_room_attempt($pdo, $attemptId, $userId, $room, $questions, []);
    redirect('result_quiz_room.php?id=' . $attemptId);
    exit;
}
$remaining = $deadlineTs - time();

$pageTitle = 'Quiz Room';
require __DIR__ . '/includes/header.php';
?>
<div class="eyebrow">Quiz in progress</div>
<h1><?= e($room['title']) ?></h1>
<p class="muted"><?= count($questions) ?> questions · <?= (int)$room['duration_minutes'] ?> minutes · Range: <?= (int)$room['start_number'] ?>–<?= (int)$room['end_number'] ?></p>
<hr class="section-divider">

<div class="alert">Sisa waktu: <b id="timeLeft">--:--</b>. Jawaban dikumpulkan otomatis saat waktu habis.</div>

<form method="post" id="quizForm" onsubmit="return confirm('Kumpulkan jawaban sekarang?')">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <?php foreach ($questions as $i => $q): ?>
        <div class="question">
            <span class="question-number"><?= ($i + 1) ?></span>
            <b><?= e($q['question_text']) ?></b>
            <p class="muted">Format: <?= e(ucfirst(str_replace('_', ' ', $q['format_type']))) ?></p>
            <?php foreach (['A' => 'option_a', 'B' => 'option_b', 'C' => 'option_c', 'D' => 'option_d'] as $key => $col): ?>
            <label class="option">
                <input type="radio" name="answers[<?= (int)$q['id'] ?>]" value="<?= $key ?>">
                <strong><?= $key ?>.</strong> <?= e($q[$col]) ?>
            </label>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
    <button class="button">Submit answers</button>
</form>

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
