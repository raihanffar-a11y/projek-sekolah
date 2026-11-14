<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/achievements.php';
require_role('student');

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

$check = $pdo->prepare("SELECT id, score, submitted_at, created_at FROM attempts WHERE quiz_room_id=? AND user_id=?");
$check->execute([$roomId, $userId]);
$existing = $check->fetch();

if ($existing && $existing['submitted_at']) {
    redirect('result_quiz_room.php?id=' . (int)$existing['id']);
}

$deadline = null;
if ($existing && !$existing['submitted_at']) {
    $attemptId = (int)$existing['id'];
    $startedAt = new DateTimeImmutable($existing['created_at']);
    $deadline = $startedAt->modify("+{$room['duration_minutes']} minutes");
} else {
    $attemptId = null;
}

$questions = [];
if ($attemptId) {
    $qStmt = $pdo->prepare("SELECT * FROM attempt_quiz_questions WHERE attempt_id=? ORDER BY position");
    $qStmt->execute([$attemptId]);
    $questions = $qStmt->fetchAll();
}

if (!$attemptId || empty($questions)) {
    $pdo->beginTransaction();
    try {
        $maxScore = (int)$room['question_count'];
        $ins = $pdo->prepare("INSERT INTO attempts(session_id, user_id, quiz_room_id, score, max_score, created_at) VALUES (0, ?, ?, 0, ?, NOW())");
        $ins->execute([$userId, $roomId, $maxScore]);
        $attemptId = (int)$pdo->lastInsertId();

        $vocabStmt = $pdo->prepare("SELECT vocab_number, word_en, meaning_id FROM vocabularies WHERE vocab_number BETWEEN ? AND ? AND is_active=1 ORDER BY vocab_number");
        $vocabStmt->execute([(int)$room['start_number'], (int)$room['end_number']]);
        $vocabs = $vocabStmt->fetchAll();

        if (empty($vocabs)) {
            $pdo->rollBack();
            exit('Tidak ada kosakata dalam rentang ini.');
        }

        if ((int)$room['random_questions'] === 1) {
            shuffle($vocabs);
        }

        $allVocabStmt = $pdo->query("SELECT vocab_number, word_en, meaning_id FROM vocabularies WHERE is_active=1");
        $allVocabs = $allVocabStmt->fetchAll();

        $questionStmt = $pdo->prepare("
            INSERT INTO attempt_quiz_questions (attempt_id, vocab_number, position, format_type, question_text, correct_answer, option_a, option_b, option_c, option_d, correct_option)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $formatTypes = ['english_to_indonesian', 'indonesian_to_english', 'context', 'fill_blank'];
        $position = 0;

        foreach ($vocabs as $idx => $v) {
            $position++;
            $formatIndex = ($idx) % 4;
            $formatType = $formatTypes[$formatIndex];
            $wordEn = $v['word_en'];
            $meaningId = $v['meaning_id'];

            $questionData = generateQuestion($formatType, $wordEn, $meaningId, $allVocabs);

            $options = [$questionData['option_a'], $questionData['option_b'], $questionData['option_c'], $questionData['option_d']];
            $correctOption = $questionData['correct_option'];
            if ((int)$room['random_answers'] === 1) {
                $optionKeys = ['A', 'B', 'C', 'D'];
                shuffle($optionKeys);
                $newOptions = [];
                foreach ($optionKeys as $newKey => $oldKey) {
                    $newOptions[$newKey] = $options[$oldKey - 1];
                    if ($oldKey === $correctOption) {
                        $correctOption = $newKey;
                    }
                }
                $options = array_values($newOptions);
            }

            $questionStmt->execute([
                $attemptId,
                (int)$v['vocab_number'],
                $position,
                $formatType,
                $questionData['question_text'],
                $questionData['correct_answer'],
                $options[0],
                $options[1],
                $options[2],
                $options[3],
                $correctOption
            ]);
        }

        $deadline = (new DateTimeImmutable('now'))->modify("+{$room['duration_minutes']} minutes");
        $pdo->commit();

        $qStmt = $pdo->prepare("SELECT * FROM attempt_quiz_questions WHERE attempt_id=? ORDER BY position");
        $qStmt->execute([$attemptId]);
        $questions = $qStmt->fetchAll();

    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

if (empty($questions)) exit('Gagal memuat soal.');

$timeExpired = false;
if ($deadline) {
    $nowCheck = new DateTimeImmutable('now');
    if ($nowCheck > $deadline) $timeExpired = true;
}

// Handle submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if ($existing && $existing['submitted_at']) {
        redirect('result_quiz_room.php?id=' . (int)$existing['id']);
    }
    if ($deadline) {
        $nowPost = new DateTimeImmutable('now');
        if ($nowPost > $deadline) {
            http_response_code(429);
            exit('Waktu pengerjaan telah habis.');
        }
    }

    $pdo->beginTransaction();
    try {
        $score = 0;
        $saveAnswer = $pdo->prepare("
            INSERT INTO answers (attempt_id, question_id, selected_option_id, is_correct, awarded_points)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE selected_option_id=VALUES(selected_option_id), is_correct=VALUES(is_correct), awarded_points=VALUES(awarded_points)
        ");

        foreach ($questions as $q) {
            $selected = (int)($_POST['answers'][$q['id']] ?? 0);
            $isCorrect = 0;
            $points = 0;
            if ($selected > 0) {
                $optionMap = ['A' => 'option_a', 'B' => 'option_b', 'C' => 'option_c', 'D' => 'option_d'];
                $col = $optionMap[$q['correct_option']] ?? '';
                $correctText = $q[$col] ?? '';
                if ($selected === 1) $selectedText = $q['option_a'];
                elseif ($selected === 2) $selectedText = $q['option_b'];
                elseif ($selected === 3) $selectedText = $q['option_c'];
                elseif ($selected === 4) $selectedText = $q['option_d'];
                else $selectedText = '';
                $isCorrect = ($selectedText === $q['correct_answer']) ? 1 : 0;
                $points = $isCorrect ? 1 : 0;
                $score += $points;
            }

            $saveAnswer->execute([$attemptId, $q['id'], $selected, $isCorrect, $points]);
        }

        $upd = $pdo->prepare("UPDATE attempts SET score=?, submitted_at=NOW() WHERE id=?");
        $upd->execute([$score, $attemptId]);

        $pdo->prepare("INSERT INTO xp_history(user_id, points, reason) VALUES (?, ?, ?)")
            ->execute([$userId, (int)round($score), 'Completed quiz room: ' . $room['title']]);

        // Cek dan berikan achievement
        $totalQuizStmt = $pdo->prepare("
            SELECT COUNT(*) FROM attempts WHERE user_id=? AND submitted_at IS NOT NULL
        ");
        $totalQuizStmt->execute([$userId]);
        $totalQuiz = (int)$totalQuizStmt->fetchColumn();

        $totalXpStmt = $pdo->prepare("
            SELECT COALESCE(SUM(points), 0) FROM xp_history WHERE user_id=?
        ");
        $totalXpStmt->execute([$userId]);
        $totalXp = (float)$totalXpStmt->fetchColumn();

        $newBadges = award_achievements($userId, $score, (int)$room['question_count'], $totalQuiz, $totalXp);
        if (!empty($newBadges)) {
            $_SESSION['new_badges'] = $newBadges;
        }

        $pdo->commit();
        redirect('result_quiz_room.php?id=' . $attemptId);
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// Ambil jawaban yang sudah disimpan (untuk resume)
$existingAnswers = [];
if ($attemptId) {
    $ansStmt = $pdo->prepare("SELECT question_id, selected_option_id FROM answers WHERE attempt_id=?");
    $ansStmt->execute([$attemptId]);
    foreach ($ansStmt->fetchAll() as $row) {
        $existingAnswers[(int)$row['question_id']] = (int)($row['selected_option_id'] ?? 0);
    }
}

/**
 * Generate question content based on format type
 */
function generateQuestion(string $formatType, string $wordEn, string $meaningId, array $allVocabs): array {
    $correctAnswer = $meaningId;
    $distractors = [];

    foreach ($allVocabs as $v) {
        if ($v['word_en'] === $wordEn) continue;
        $distractors[] = $v['meaning_id'];
    }

    $distractors = array_unique($distractors);
    shuffle($distractors);
    $distractors = array_slice($distractors, 0, 3);

    switch ($formatType) {
        case 'english_to_indonesian':
            $questionText = 'Apa arti bahasa Indonesia dari kata "' . e($wordEn) . '"?';
            $options = [$correctAnswer, $distractors[0] ?? '', $distractors[1] ?? '', $distractors[2] ?? ''];
            shuffle($options);
            $correctOption = 'A';
            foreach (['A', 'B', 'C', 'D'] as $i => $opt) {
                if ($opt === $correctAnswer) {
                    $correctOption = ['A', 'B', 'C', 'D'][$i];
                    break;
                }
            }
            return [
                'question_text' => $questionText,
                'correct_answer' => $correctAnswer,
                'option_a' => $options[0], 'option_b' => $options[1],
                'option_c' => $options[2], 'option_d' => $options[3],
                'correct_option' => $correctOption,
            ];

        case 'indonesian_to_english':
            $questionText = 'Kata bahasa Inggris untuk "' . e($meaningId) . '" adalah?';
            $options = [$wordEn, $distractors[0] ?? '', $distractors[1] ?? '', $distractors[2] ?? ''];
            shuffle($options);
            $correctOption = 'A';
            foreach (['A', 'B', 'C', 'D'] as $i => $opt) {
                if ($opt === $wordEn) {
                    $correctOption = ['A', 'B', 'C', 'D'][$i];
                    break;
                }
            }
            return [
                'question_text' => $questionText,
                'correct_answer' => $wordEn,
                'option_a' => $options[0], 'option_b' => $options[1],
                'option_c' => $options[2], 'option_d' => $options[3],
                'correct_option' => $correctOption,
            ];

        case 'context':
            $sentences = [
                'I always ___ every morning.',
                'She likes to ___ before bed.',
                'We need to ___ together.',
                'He can ___ very well.',
                'They ___ every day.',
            ];
            $sentence = $sentences[array_rand($sentences)];
            $questionText = 'Pilih kata yang tepat untuk kalimat: "' . str_replace('___', '<strong>' . e($wordEn) . '</strong>', $sentence) . '"';
            $options = [$wordEn, $distractors[0] ?? '', $distractors[1] ?? '', $distractors[2] ?? ''];
            shuffle($options);
            $correctOption = 'A';
            foreach (['A', 'B', 'C', 'D'] as $i => $opt) {
                if ($opt === $wordEn) {
                    $correctOption = ['A', 'B', 'C', 'D'][$i];
                    break;
                }
            }
            return [
                'question_text' => $questionText,
                'correct_answer' => $wordEn,
                'option_a' => $options[0], 'option_b' => $options[1],
                'option_c' => $options[2], 'option_d' => $options[3],
                'correct_option' => $correctOption,
            ];

        case 'fill_blank':
            $templates = [
                'Please ___ now.',
                'Don\'t forget to ___.',
                'We should ___ together.',
                'He wants to ___.',
                'They are going to ___.',
            ];
            $template = $templates[array_rand($templates)];
            $questionText = 'Isi bagian kosong: "' . str_replace('___', '_______', $template) . '" (Kata: ' . e($meaningId) . ')';
            $options = [$wordEn, $distractors[0] ?? '', $distractors[1] ?? '', $distractors[2] ?? ''];
            shuffle($options);
            $correctOption = 'A';
            foreach (['A', 'B', 'C', 'D'] as $i => $opt) {
                if ($opt === $wordEn) {
                    $correctOption = ['A', 'B', 'C', 'D'][$i];
                    break;
                }
            }
            return [
                'question_text' => $questionText,
                'correct_answer' => $wordEn,
                'option_a' => $options[0], 'option_b' => $options[1],
                'option_c' => $options[2], 'option_d' => $options[3],
                'correct_option' => $correctOption,
            ];
    }

    return [
        'question_text' => 'Apa arti dari "' . e($wordEn) . '"?',
        'correct_answer' => $meaningId,
        'option_a' => $correctAnswer, 'option_b' => $distractors[0] ?? '',
        'option_c' => $distractors[1] ?? '', 'option_d' => $distractors[2] ?? '',
        'correct_option' => 'A',
    ];
}

$pageTitle = 'Quiz Room';
require __DIR__ . '/includes/header.php';
?>
<div class="eyebrow">Quiz in progress</div>
<h1><?= e($room['title']) ?></h1>
<p class="muted"><?= count($questions) ?> questions · <?= (int)$room['duration_minutes'] ?> minutes · Range: <?= (int)$room['start_number'] ?>–<?= (int)$room['end_number'] ?></p>
<hr class="section-divider">

<?php if ($timeExpired): ?>
    <div class="alert">Waktu pengerjaan telah habis. Jawaban yang tersimpan tidak dapat diubah.</div>
<?php else: ?>
    <div class="alert">Timer dipaksa oleh server. Silakan kumpulkan sebelum waktu habis.</div>
<?php endif; ?>

<form method="post"<?= $timeExpired ? ' onsubmit="return false;"' : ' onsubmit="return confirm(\'Kumpulkan jawaban sekarang?\')"' ?>>
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <?php foreach ($questions as $i => $q): ?>
        <div class="question">
            <span class="question-number"><?= ($i + 1) ?></span>
            <b><?= e($q['question_text']) ?></b>
            <p class="muted">Format: <?= ucfirst(str_replace('_', ' ', $q['format_type'])) ?></p>
            <?php
            $options = [
                ['A', $q['option_a']],
                ['B', $q['option_b']],
                ['C', $q['option_c']],
                ['D', $q['option_d']],
            ];
            $valMap = ['A' => 1, 'B' => 2, 'C' => 3, 'D' => 4];
            foreach ($options as [$key, $text]):
            ?>
            <label class="option">
                <input type="radio" name="answers[<?= $q['id'] ?>]" value="<?= $valMap[$key] ?>"
                    <?= (isset($existingAnswers[$q['id']]) && $existingAnswers[$q['id']] === $valMap[$key]) ? ' checked' : '' ?>
                    <?= $timeExpired ? ' disabled' : '' ?>>
                <strong><?= $key ?>.</strong> <?= e($text) ?>
            </label>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
    <button class="button"<?= $timeExpired ? ' disabled' : '' ?>><?= $existing && !$existing['submitted_at'] ? 'Update jawaban' : 'Submit answers' ?></button>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>