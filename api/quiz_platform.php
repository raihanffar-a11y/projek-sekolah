<?php

function quiz_platform_admin(array $user): bool
{
    return ($user['role'] ?? '') === 'admin';
}

function quiz_platform_can_manage(array $quiz, array $user): bool
{
    return quiz_platform_admin($user) || (int)$quiz['created_by'] === (int)$user['id'];
}

function quiz_platform_quiz(PDO $pdo, int $quizId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM quizzes WHERE id = ?');
    $stmt->execute([$quizId]);
    $quiz = $stmt->fetch();
    return $quiz ?: null;
}

function quiz_platform_code(PDO $pdo): string
{
    $check = $pdo->prepare('SELECT 1 FROM sessions WHERE session_code = ?');
    do {
        $code = strtoupper(bin2hex(random_bytes(6)));
        $check->execute([$code]);
    } while ($check->fetchColumn());
    return $code;
}

function quiz_platform_parse_datetime(string $value): ?string
{
    if ($value === '') return null;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || ($errors && ($errors['warning_count'] || $errors['error_count']))
        || $date->format('Y-m-d\TH:i') !== $value) {
        return null;
    }
    return $date->format('Y-m-d H:i:s');
}

function quiz_platform_assert_editable(PDO $pdo, int $quizId): void
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM sessions WHERE quiz_id = ?');
    $stmt->execute([$quizId]);
    if ((int)$stmt->fetchColumn() > 0) {
        api_response(409, ['error' => 'Kuis sudah memiliki sesi. Duplikasi kuis untuk mengubah soal tanpa mengubah riwayat peserta.']);
    }
}

function quiz_platform_validate_question_set(PDO $pdo, int $quizId): int
{
    $stmt = $pdo->prepare(
        'SELECT q.id, COUNT(o.id) option_count, COALESCE(SUM(o.is_correct), 0) correct_count
         FROM questions q
         LEFT JOIN options o ON o.question_id = q.id
         WHERE q.quiz_id = ?
         GROUP BY q.id'
    );
    $stmt->execute([$quizId]);
    $questions = $stmt->fetchAll();
    if (!$questions) {
        api_response(422, ['error' => 'Tambahkan minimal satu soal sebelum menerbitkan kuis.']);
    }
    foreach ($questions as $question) {
        if ((int)$question['option_count'] !== 4 || (int)$question['correct_count'] !== 1) {
            api_response(422, ['error' => 'Setiap soal harus memiliki empat pilihan dan tepat satu jawaban benar.']);
        }
    }
    return count($questions);
}

function quiz_platform_finalize_live(PDO $pdo, int $sessionId): void
{
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM questions q JOIN sessions s ON s.quiz_id = q.quiz_id WHERE s.id = ?');
    $countStmt->execute([$sessionId]);
    $totalQuestions = (int)$countStmt->fetchColumn();
    $attemptsStmt = $pdo->prepare('SELECT id, created_at FROM attempts WHERE session_id = ? AND submitted_at IS NULL');
    $attemptsStmt->execute([$sessionId]);
    $update = $pdo->prepare(
        'UPDATE attempts
         SET score = ?, max_score = ?, correct_count = ?, incorrect_count = ?, unanswered_count = ?,
             accuracy_percent = ?, completion_seconds = ?, submitted_at = NOW()
         WHERE id = ? AND submitted_at IS NULL'
    );
    foreach ($attemptsStmt->fetchAll() as $attempt) {
        $answerStmt = $pdo->prepare(
            'SELECT COUNT(*) answered_count, COALESCE(SUM(is_correct), 0) correct_count,
                    COALESCE(SUM(awarded_points), 0) score
             FROM answers WHERE attempt_id = ?'
        );
        $answerStmt->execute([(int)$attempt['id']]);
        $summary = $answerStmt->fetch();
        $correct = (int)$summary['correct_count'];
        $answered = (int)$summary['answered_count'];
        $accuracy = $totalQuestions > 0 ? round($correct * 100 / $totalQuestions, 2) : 0;
        $seconds = max(0, time() - strtotime($attempt['created_at']));
        $update->execute([
            (int)$summary['score'],
            $totalQuestions * 100,
            $correct,
            max(0, $answered - $correct),
            max(0, $totalQuestions - $answered),
            $accuracy,
            $seconds,
            (int)$attempt['id'],
        ]);
    }
    $pdo->prepare("UPDATE sessions SET status = 'closed', live_status = 'finished', question_deadline_at = NULL WHERE id = ?")
        ->execute([$sessionId]);
}

function quiz_platform_advance_if_expired(PDO $pdo, int $sessionId): void
{
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM sessions WHERE id = ? FOR UPDATE');
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch();
        if ($session && $session['mode'] === 'live' && $session['live_status'] === 'running'
            && $session['question_deadline_at'] !== null && strtotime($session['question_deadline_at']) <= time()) {
            $totalStmt = $pdo->prepare('SELECT COUNT(*) FROM questions WHERE quiz_id = ?');
            $totalStmt->execute([(int)$session['quiz_id']]);
            $total = (int)$totalStmt->fetchColumn();
            $next = (int)$session['current_question_position'] + 1;
            if ($next > $total) {
                quiz_platform_finalize_live($pdo, $sessionId);
            } else {
                $questionStmt = $pdo->prepare('SELECT duration_seconds FROM questions WHERE quiz_id = ? ORDER BY sort_order, id LIMIT 1 OFFSET ?');
                $questionStmt->bindValue(1, (int)$session['quiz_id'], PDO::PARAM_INT);
                $questionStmt->bindValue(2, $next - 1, PDO::PARAM_INT);
                $questionStmt->execute();
                $duration = max(5, (int)$questionStmt->fetchColumn());
                $pdo->prepare(
                    'UPDATE sessions
                     SET current_question_position = ?, question_started_at = NOW(),
                         question_deadline_at = DATE_ADD(NOW(), INTERVAL ? SECOND)
                     WHERE id = ?'
                )->execute([$next, $duration, $sessionId]);
            }
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
}

function quiz_platform_leaderboard(PDO $pdo, int $sessionId): array
{
    $stmt = $pdo->prepare(
        'SELECT u.name, a.score, a.correct_count, a.accuracy_percent, a.completion_seconds
         FROM attempts a JOIN users u ON u.id = a.user_id
         WHERE a.session_id = ? AND a.submitted_at IS NOT NULL
         ORDER BY a.score DESC, u.name ASC'
    );
    $stmt->execute([$sessionId]);
    $rows = $stmt->fetchAll();
    $rank = 0;
    $previousScore = null;
    foreach ($rows as $index => &$row) {
        $score = (int)$row['score'];
        if ($previousScore === null || $score !== $previousScore) $rank = $index + 1;
        $row['rank'] = $rank;
        $row['score'] = $score;
        $row['correct_count'] = (int)$row['correct_count'];
        $row['accuracy_percent'] = (float)$row['accuracy_percent'];
        $row['completion_seconds'] = $row['completion_seconds'] === null ? null : (int)$row['completion_seconds'];
        $previousScore = $score;
    }
    unset($row);
    return $rows;
}

function quiz_platform_room(PDO $pdo, int $sessionId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT s.*, q.title, q.duration_minutes, q.created_by AS quiz_owner,
                (SELECT COUNT(*) FROM questions WHERE quiz_id = q.id) question_count
         FROM sessions s JOIN quizzes q ON q.id = s.quiz_id WHERE s.id = ?'
    );
    $stmt->execute([$sessionId]);
    $room = $stmt->fetch();
    return $room ?: null;
}

function quiz_platform_current_question(PDO $pdo, int $quizId, int $position): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM questions WHERE quiz_id = ? ORDER BY sort_order, id LIMIT 1 OFFSET ?');
    $stmt->bindValue(1, $quizId, PDO::PARAM_INT);
    $stmt->bindValue(2, max(0, $position - 1), PDO::PARAM_INT);
    $stmt->execute();
    $question = $stmt->fetch();
    return $question ?: null;
}

function quiz_platform_handle(string $method, string $action): bool
{
    $pdo = db();

    if ($method === 'GET' && $action === 'quiz_list') {
        $user = api_require_user();
        if (quiz_platform_admin($user)) {
            $stmt = $pdo->query(
                'SELECT q.*, c.name category, u.name creator_name,
                        (SELECT COUNT(*) FROM questions qu WHERE qu.quiz_id = q.id) question_count,
                        (SELECT COUNT(*) FROM sessions se WHERE se.quiz_id = q.id) session_count
                 FROM quizzes q LEFT JOIN categories c ON c.id = q.category_id
                 JOIN users u ON u.id = q.created_by
                 WHERE q.status <> "archived" ORDER BY q.updated_at DESC, q.created_at DESC'
            );
        } else {
            $stmt = $pdo->prepare(
                'SELECT q.*, c.name category, u.name creator_name,
                        (SELECT COUNT(*) FROM questions qu WHERE qu.quiz_id = q.id) question_count,
                        (SELECT COUNT(*) FROM sessions se WHERE se.quiz_id = q.id) session_count
                 FROM quizzes q LEFT JOIN categories c ON c.id = q.category_id
                 JOIN users u ON u.id = q.created_by
                 WHERE q.status <> "archived" AND (q.created_by = ? OR (q.visibility = "public" AND q.status = "published"))
                 ORDER BY (q.created_by = ?) DESC, q.updated_at DESC, q.created_at DESC'
            );
            $stmt->execute([(int)$user['id'], (int)$user['id']]);
        }
        $quizzes = $stmt->fetchAll();
        foreach ($quizzes as &$quiz) {
            $quiz['id'] = (int)$quiz['id'];
            $quiz['created_by'] = (int)$quiz['created_by'];
            $quiz['question_count'] = (int)$quiz['question_count'];
            $quiz['session_count'] = (int)$quiz['session_count'];
            $quiz['is_owner'] = quiz_platform_can_manage($quiz, $user);
        }
        unset($quiz);
        api_response(200, ['quizzes' => $quizzes]);
    }

    if ($method === 'GET' && $action === 'quiz_categories') {
        api_require_user();
        $categories = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
        $vocabularyCategories = $pdo->query(
            'SELECT DISTINCT c.id, c.name FROM categories c
             JOIN vocabularies v ON v.category_id = c.id
             WHERE v.is_active = 1 ORDER BY c.name'
        )->fetchAll();
        api_response(200, ['categories' => $categories, 'vocabularyCategories' => $vocabularyCategories]);
    }

    if ($method === 'GET' && $action === 'quiz_vocabularies') {
        api_require_user();
        $categoryId = filter_var($_GET['category_id'] ?? null, FILTER_VALIDATE_INT);
        $limit = filter_var($_GET['limit'] ?? 30, FILTER_VALIDATE_INT);
        $limit = max(1, min(100, $limit ?: 30));
        if ($categoryId) {
            $stmt = $pdo->prepare(
                'SELECT vocab_number, word_en, meaning_id FROM vocabularies
                 WHERE is_active = 1 AND category_id = ? ORDER BY RAND() LIMIT ' . $limit
            );
            $stmt->execute([$categoryId]);
        } else {
            $stmt = $pdo->query(
                'SELECT vocab_number, word_en, meaning_id FROM vocabularies
                 WHERE is_active = 1 ORDER BY RAND() LIMIT ' . $limit
            );
        }
        api_response(200, ['items' => $stmt->fetchAll()]);
    }

    if ($method === 'GET' && $action === 'quiz_detail') {
        $user = api_require_user();
        $quizId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$quizId) api_response(422, ['error' => 'ID kuis tidak valid.']);
        $quiz = quiz_platform_quiz($pdo, (int)$quizId);
        if (!$quiz || $quiz['status'] === 'archived') api_response(404, ['error' => 'Kuis tidak ditemukan.']);
        $owner = quiz_platform_can_manage($quiz, $user);
        if (!$owner && !($quiz['status'] === 'published' && $quiz['visibility'] === 'public')) {
            api_response(403, ['error' => 'Kuis privat hanya dapat dibuka oleh pemilik atau admin.']);
        }
        $questionsStmt = $pdo->prepare('SELECT * FROM questions WHERE quiz_id = ? ORDER BY sort_order, id');
        $questionsStmt->execute([(int)$quizId]);
        $questions = $questionsStmt->fetchAll();
        $options = $pdo->prepare('SELECT id, option_text, is_correct FROM options WHERE question_id = ? ORDER BY id');
        foreach ($questions as &$question) {
            $options->execute([(int)$question['id']]);
            $question['options'] = $options->fetchAll();
            if (!$owner) {
                foreach ($question['options'] as &$option) unset($option['is_correct']);
                unset($option);
            } else {
                foreach ($question['options'] as &$option) {
                    $option['id'] = (int)$option['id'];
                    $option['is_correct'] = (int)$option['is_correct'];
                }
                unset($option);
            }
        }
        unset($question);
        api_response(200, ['quiz' => $quiz, 'questions' => $questions, 'canManage' => $owner]);
    }

    if ($method === 'POST' && $action === 'quiz_save') {
        $user = api_require_user();
        api_require_csrf();
        $body = api_body();
        $quizId = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
        $title = trim((string)($body['title'] ?? ''));
        $description = trim((string)($body['description'] ?? ''));
        $difficulty = (string)($body['difficulty'] ?? 'beginner');
        $visibility = (string)($body['visibility'] ?? 'private');
        $categoryId = filter_var($body['category_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
        $questions = $body['questions'] ?? [];
        if ($title === '' || mb_strlen($title) > 150) api_response(422, ['error' => 'Judul kuis wajib diisi (maksimal 150 karakter).']);
        if (mb_strlen($description) > 5000) api_response(422, ['error' => 'Deskripsi maksimal 5.000 karakter.']);
        if (!in_array($difficulty, ['beginner', 'intermediate', 'advanced'], true)) api_response(422, ['error' => 'Tingkat kesulitan tidak valid.']);
        if (!in_array($visibility, ['private', 'public'], true)) api_response(422, ['error' => 'Visibilitas kuis tidak valid.']);
        if (!is_array($questions) || count($questions) > 100) api_response(422, ['error' => 'Kuis dapat memuat maksimal 100 soal.']);
        if ($categoryId) {
            $categoryStmt = $pdo->prepare('SELECT 1 FROM categories WHERE id = ?');
            $categoryStmt->execute([$categoryId]);
            if (!$categoryStmt->fetchColumn()) api_response(422, ['error' => 'Kategori tidak ditemukan.']);
        }

        if ($quizId > 0) {
            $quiz = quiz_platform_quiz($pdo, $quizId);
            if (!$quiz || $quiz['status'] === 'archived') api_response(404, ['error' => 'Kuis tidak ditemukan.']);
            if (!quiz_platform_can_manage($quiz, $user)) api_response(403, ['error' => 'Anda hanya dapat mengubah kuis milik sendiri.']);
            quiz_platform_assert_editable($pdo, $quizId);
        }

        $pdo->beginTransaction();
        try {
            if ($quizId > 0) {
                $stmt = $pdo->prepare(
                    'UPDATE quizzes SET title = ?, description = ?, category_id = ?, difficulty = ?, visibility = ?, status = "draft", published_at = NULL WHERE id = ?'
                );
                $stmt->execute([$title, $description, $categoryId, $difficulty, $visibility, $quizId]);
                $pdo->prepare('DELETE FROM questions WHERE quiz_id = ?')->execute([$quizId]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO quizzes (category_id, created_by, title, description, difficulty, duration_minutes, status, visibility)
                     VALUES (?, ?, ?, ?, ?, 15, "draft", ?)'
                );
                $stmt->execute([$categoryId, (int)$user['id'], $title, $description, $difficulty, $visibility]);
                $quizId = (int)$pdo->lastInsertId();
            }

            $insertQuestion = $pdo->prepare(
                'INSERT INTO questions (quiz_id, question_text, explanation, image_path, question_type, points, duration_seconds, sort_order)
                 VALUES (?, ?, ?, ?, "multiple_choice", 100, ?, ?)'
            );
            $insertOption = $pdo->prepare('INSERT INTO options (question_id, option_text, is_correct) VALUES (?, ?, ?)');
            foreach (array_values($questions) as $position => $question) {
                if (!is_array($question)) continue;
                $text = trim((string)($question['question_text'] ?? ''));
                if ($text === '' || mb_strlen($text) > 5000) continue;
                $duration = filter_var($question['duration_seconds'] ?? 30, FILTER_VALIDATE_INT);
                $duration = max(5, min(600, $duration ?: 30));
                $imagePath = trim((string)($question['image_path'] ?? ''));
                if ($imagePath !== '' && !preg_match('~^uploads/quiz-images/[a-f0-9]{32}\.(?:jpg|png|webp)$~', $imagePath)) {
                    api_response(422, ['error' => 'Gambar soal tidak valid. Unggah ulang gambar tersebut.']);
                }
                $insertQuestion->execute([
                    $quizId,
                    $text,
                    trim((string)($question['explanation'] ?? '')) ?: null,
                    $imagePath ?: null,
                    $duration,
                    $position + 1,
                ]);
                $questionId = (int)$pdo->lastInsertId();
                $questionOptions = $question['options'] ?? [];
                if (!is_array($questionOptions)) continue;
                foreach (array_slice($questionOptions, 0, 4) as $index => $option) {
                    if (!is_array($option)) continue;
                    $optionText = trim((string)($option['option_text'] ?? ''));
                    if ($optionText === '' || mb_strlen($optionText) > 500) continue;
                    $insertOption->execute([$questionId, $optionText, !empty($option['is_correct']) ? 1 : 0]);
                }
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
        api_response(200, ['id' => $quizId, 'message' => 'Draft kuis berhasil disimpan.']);
    }

    if ($method === 'POST' && $action === 'quiz_publish') {
        $user = api_require_user();
        api_require_csrf();
        $body = api_body();
        $quizId = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$quizId) api_response(422, ['error' => 'ID kuis tidak valid.']);
        $quiz = quiz_platform_quiz($pdo, (int)$quizId);
        if (!$quiz) api_response(404, ['error' => 'Kuis tidak ditemukan.']);
        if (!quiz_platform_can_manage($quiz, $user)) api_response(403, ['error' => 'Anda hanya dapat menerbitkan kuis milik sendiri.']);
        quiz_platform_assert_editable($pdo, (int)$quizId);
        quiz_platform_validate_question_set($pdo, (int)$quizId);
        $pdo->prepare('UPDATE quizzes SET status = "published", published_at = NOW() WHERE id = ?')->execute([(int)$quizId]);
        api_response(200, ['message' => 'Kuis berhasil dipublikasikan.']);
    }

    if ($method === 'POST' && $action === 'quiz_duplicate') {
        $user = api_require_user();
        api_require_csrf();
        $body = api_body();
        $quizId = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$quizId) api_response(422, ['error' => 'ID kuis tidak valid.']);
        $source = quiz_platform_quiz($pdo, (int)$quizId);
        if (!$source || (!quiz_platform_can_manage($source, $user) && !($source['status'] === 'published' && $source['visibility'] === 'public'))) {
            api_response(404, ['error' => 'Kuis tidak ditemukan.']);
        }
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'INSERT INTO quizzes (category_id, created_by, title, description, difficulty, duration_minutes, status, visibility)
                 VALUES (?, ?, ?, ?, ?, ?, "draft", "private")'
            )->execute([
                $source['category_id'], (int)$user['id'], $source['title'] . ' (Salinan)',
                $source['description'], $source['difficulty'], $source['duration_minutes'],
            ]);
            $copyId = (int)$pdo->lastInsertId();
            $questionsStmt = $pdo->prepare('SELECT * FROM questions WHERE quiz_id = ? ORDER BY sort_order, id');
            $questionsStmt->execute([(int)$quizId]);
            $insertQuestion = $pdo->prepare(
                'INSERT INTO questions (quiz_id, question_text, explanation, image_path, question_type, points, duration_seconds, sort_order)
                 VALUES (?, ?, ?, ?, ?, 100, ?, ?)'
            );
            $insertOption = $pdo->prepare('INSERT INTO options (question_id, option_text, is_correct) VALUES (?, ?, ?)');
            $optionsStmt = $pdo->prepare('SELECT option_text, is_correct FROM options WHERE question_id = ? ORDER BY id');
            foreach ($questionsStmt->fetchAll() as $question) {
                $insertQuestion->execute([
                    $copyId, $question['question_text'], $question['explanation'], $question['image_path'],
                    $question['question_type'], (int)$question['duration_seconds'], (int)$question['sort_order'],
                ]);
                $newQuestionId = (int)$pdo->lastInsertId();
                $optionsStmt->execute([(int)$question['id']]);
                foreach ($optionsStmt->fetchAll() as $option) {
                    $insertOption->execute([$newQuestionId, $option['option_text'], (int)$option['is_correct']]);
                }
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
        api_response(200, ['id' => $copyId, 'message' => 'Salinan kuis dibuat sebagai draft privat.']);
    }

    if ($method === 'POST' && $action === 'quiz_delete') {
        $user = api_require_user();
        api_require_csrf();
        $body = api_body();
        $quizId = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$quizId) api_response(422, ['error' => 'ID kuis tidak valid.']);
        $quiz = quiz_platform_quiz($pdo, (int)$quizId);
        if (!$quiz) api_response(404, ['error' => 'Kuis tidak ditemukan.']);
        if (!quiz_platform_can_manage($quiz, $user)) api_response(403, ['error' => 'Anda hanya dapat menghapus kuis milik sendiri.']);
        $activeSessionsStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM sessions
             WHERE quiz_id = ? AND status = 'open'
               AND (mode = 'live' OR end_at IS NULL OR end_at > NOW())"
        );
        $activeSessionsStmt->execute([(int)$quizId]);
        if ((int)$activeSessionsStmt->fetchColumn() > 0) {
            api_response(409, ['error' => 'Tutup semua sesi aktif sebelum mengarsipkan kuis ini.']);
        }
        $sessionsStmt = $pdo->prepare('SELECT COUNT(*) FROM sessions WHERE quiz_id = ?');
        $sessionsStmt->execute([(int)$quizId]);
        if ((int)$sessionsStmt->fetchColumn() > 0) {
            $pdo->prepare('UPDATE quizzes SET status = "archived", visibility = "private" WHERE id = ?')->execute([(int)$quizId]);
            api_response(200, ['message' => 'Kuis diarsipkan agar riwayat sesi dan nilai peserta tetap tersedia.']);
        }
        $pdo->prepare('DELETE FROM quizzes WHERE id = ?')->execute([(int)$quizId]);
        api_response(200, ['message' => 'Kuis berhasil dihapus.']);
    }

    if ($method === 'POST' && $action === 'quiz_create_session') {
        $user = api_require_user();
        api_require_csrf();
        $body = api_body();
        $quizId = filter_var($body['quiz_id'] ?? null, FILTER_VALIDATE_INT);
        $mode = (string)($body['mode'] ?? 'assignment');
        $startAt = trim((string)($body['start_at'] ?? ''));
        $endAt = trim((string)($body['end_at'] ?? ''));
        if (!$quizId || !in_array($mode, ['assignment', 'live'], true)) api_response(422, ['error' => 'Kuis atau mode tidak valid.']);
        $quiz = quiz_platform_quiz($pdo, (int)$quizId);
        if (!$quiz || $quiz['status'] !== 'published') api_response(404, ['error' => 'Kuis belum dipublikasikan atau tidak ditemukan.']);
        if (!quiz_platform_can_manage($quiz, $user)) api_response(403, ['error' => 'Hanya pemilik kuis atau admin yang dapat membuat sesi.']);
        $questionCount = quiz_platform_validate_question_set($pdo, (int)$quizId);
        $parsedStartAt = quiz_platform_parse_datetime($startAt);
        $parsedEndAt = quiz_platform_parse_datetime($endAt);
        if (($startAt !== '' && $parsedStartAt === null) || ($endAt !== '' && $parsedEndAt === null)) {
            api_response(422, ['error' => 'Format waktu sesi tidak valid.']);
        }
        if ($mode === 'assignment' && $parsedEndAt === null) {
            api_response(422, ['error' => 'Assignment wajib memiliki batas pengerjaan.']);
        }
        if ($parsedStartAt && $parsedEndAt && $parsedEndAt <= $parsedStartAt) {
            api_response(422, ['error' => 'Batas akhir harus setelah waktu mulai.']);
        }
        if ($mode === 'assignment' && $parsedEndAt <= date('Y-m-d H:i:s')) {
            api_response(422, ['error' => 'Batas pengerjaan harus berada di waktu mendatang.']);
        }
        $code = quiz_platform_code($pdo);
        $liveStatus = $mode === 'live' ? 'waiting' : null;
        $stmt = $pdo->prepare(
            'INSERT INTO sessions (quiz_id, host_id, mode, session_code, start_at, end_at, status, live_status)
             VALUES (?, ?, ?, ?, ?, ?, "open", ?)'
        );
        $stmt->execute([
            (int)$quizId, (int)$user['id'], $mode, $code,
            $parsedStartAt,
            $parsedEndAt,
            $liveStatus,
        ]);
        api_response(200, [
            'session_id' => (int)$pdo->lastInsertId(),
            'session_code' => $code,
            'mode' => $mode,
            'question_count' => $questionCount,
        ]);
    }

    if ($method === 'GET' && $action === 'quiz_sessions') {
        $user = api_require_user();
        if (quiz_platform_admin($user)) {
            $stmt = $pdo->query(
                'SELECT s.*, q.title FROM sessions s JOIN quizzes q ON q.id = s.quiz_id
                 WHERE q.status <> "archived" ORDER BY s.created_at DESC'
            );
        } else {
            $stmt = $pdo->prepare(
                'SELECT s.*, q.title FROM sessions s JOIN quizzes q ON q.id = s.quiz_id
                 WHERE q.created_by = ? AND q.status <> "archived" ORDER BY s.created_at DESC'
            );
            $stmt->execute([(int)$user['id']]);
        }
        api_response(200, ['sessions' => $stmt->fetchAll()]);
    }

    if ($method === 'GET' && $action === 'assignment_leaderboard') {
        $user = api_require_user();
        $sessionId = filter_var($_GET['session_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$sessionId) api_response(422, ['error' => 'ID assignment tidak valid.']);
        $room = quiz_platform_room($pdo, (int)$sessionId);
        if (!$room || $room['mode'] !== 'assignment') api_response(404, ['error' => 'Assignment tidak ditemukan.']);
        $isOwner = (int)$room['quiz_owner'] === (int)$user['id'] || quiz_platform_admin($user);
        $attempt = $pdo->prepare('SELECT submitted_at FROM attempts WHERE session_id = ? AND user_id = ?');
        $attempt->execute([(int)$sessionId, (int)$user['id']]);
        $participantAttempt = $attempt->fetch();
        if (!$isOwner && (!$participantAttempt || !$participantAttempt['submitted_at'])) {
            api_response(403, ['error' => 'Hasil hanya tersedia bagi pemilik atau peserta yang sudah mengumpulkan assignment.']);
        }
        api_response(200, [
            'session' => ['id' => (int)$room['id'], 'title' => $room['title'], 'code' => $room['session_code'], 'status' => $room['status']],
            'leaderboard' => quiz_platform_leaderboard($pdo, (int)$sessionId),
        ]);
    }

    if ($method === 'POST' && $action === 'room_join') {
        $user = api_require_user();
        api_require_csrf();
        $body = api_body();
        $code = strtoupper(trim((string)($body['code'] ?? '')));
        if (!preg_match('/^[A-F0-9]{6,12}$/', $code)) api_response(422, ['error' => 'Masukkan kode room yang valid.']);
        $stmt = $pdo->prepare(
            'SELECT s.*, q.title, q.status quiz_status FROM sessions s
             JOIN quizzes q ON q.id = s.quiz_id WHERE s.session_code = ?'
        );
        $stmt->execute([$code]);
        $session = $stmt->fetch();
        $now = time();
        if (!$session) {
            $legacy = $pdo->prepare('SELECT id,status,start_at,end_at,title FROM quiz_rooms WHERE session_code=?');
            $legacy->execute([$code]);
            $legacyRoom = $legacy->fetch();
            if (!$legacyRoom) api_response(404, ['error' => 'Kode room tidak ditemukan.']);
            if ($legacyRoom['status'] !== 'open') api_response(409, ['error' => 'Room sudah ditutup.']);
            if ($legacyRoom['start_at'] && strtotime($legacyRoom['start_at']) > $now) api_response(409, ['error' => 'Room belum dibuka.']);
            if ($legacyRoom['end_at'] && strtotime($legacyRoom['end_at']) < $now) api_response(409, ['error' => 'Batas waktu room telah berakhir.']);
            api_response(200, ['mode' => 'legacy-room', 'redirect' => 'join_room.php?code=' . rawurlencode($code)]);
        }
        if ($session['quiz_status'] !== 'published') api_response(404, ['error' => 'Kode room tidak ditemukan.']);
        if ($session['status'] !== 'open') api_response(409, ['error' => 'Room sudah ditutup.']);
        if ($session['start_at'] && strtotime($session['start_at']) > $now) api_response(409, ['error' => 'Room belum dibuka.']);
        if ($session['end_at'] && strtotime($session['end_at']) < $now) api_response(409, ['error' => 'Batas waktu room telah berakhir.']);
        if ($session['mode'] === 'live' && $session['live_status'] !== 'waiting') api_response(409, ['error' => 'Live Quiz sudah dimulai. Room tidak menerima peserta baru.']);
        if ($session['mode'] === 'assignment') {
            $questionCountStmt = $pdo->prepare('SELECT COUNT(*) FROM questions WHERE quiz_id = ?');
            $questionCountStmt->execute([(int)$session['quiz_id']]);
            $maxScore = (int)$questionCountStmt->fetchColumn() * 100;
            $attemptStmt = $pdo->prepare(
                'INSERT IGNORE INTO attempts (session_id, user_id, score, max_score, created_at)
                 VALUES (?, ?, 0, ?, NOW())'
            );
            $attemptStmt->execute([(int)$session['id'], (int)$user['id'], $maxScore]);
            api_response(200, ['mode' => 'assignment', 'session_id' => (int)$session['id'], 'redirect' => 'take_quiz.php?session_id=' . (int)$session['id']]);
        }

        $questionCountStmt = $pdo->prepare('SELECT COUNT(*) FROM questions WHERE quiz_id = ?');
        $questionCountStmt->execute([(int)$session['quiz_id']]);
        $maxScore = (int)$questionCountStmt->fetchColumn() * 100;
        $attempt = $pdo->prepare(
            'INSERT IGNORE INTO attempts (session_id, user_id, score, max_score, created_at) VALUES (?, ?, 0, ?, NOW())'
        );
        $attempt->execute([(int)$session['id'], (int)$user['id'], $maxScore]);
        api_response(200, ['mode' => 'live', 'session_id' => (int)$session['id'], 'session_code' => $code]);
    }

    if ($method === 'GET' && $action === 'room_state') {
        $user = api_require_user();
        $sessionId = filter_var($_GET['session_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$sessionId) api_response(422, ['error' => 'ID room tidak valid.']);
        $room = quiz_platform_room($pdo, (int)$sessionId);
        if (!$room || $room['mode'] !== 'live') api_response(404, ['error' => 'Live Quiz tidak ditemukan.']);
        $isHost = (int)$room['host_id'] === (int)$user['id'] || quiz_platform_admin($user);
        $attemptStmt = $pdo->prepare('SELECT id FROM attempts WHERE session_id = ? AND user_id = ?');
        $attemptStmt->execute([(int)$sessionId, (int)$user['id']]);
        $attemptId = $attemptStmt->fetchColumn();
        if (!$isHost && !$attemptId) api_response(403, ['error' => 'Anda belum bergabung ke room ini.']);

        quiz_platform_advance_if_expired($pdo, (int)$sessionId);
        $room = quiz_platform_room($pdo, (int)$sessionId);
        $participantsStmt = $pdo->prepare(
            'SELECT u.name, a.score, a.submitted_at,
                    (SELECT COUNT(*) FROM answers an WHERE an.attempt_id = a.id) answered_count
             FROM attempts a JOIN users u ON u.id = a.user_id
             WHERE a.session_id = ? ORDER BY a.created_at, u.name'
        );
        $participantsStmt->execute([(int)$sessionId]);
        $participants = $participantsStmt->fetchAll();
        foreach ($participants as &$participant) {
            $participant['score'] = (int)$participant['score'];
            $participant['answered_count'] = (int)$participant['answered_count'];
            $participant['status'] = $participant['submitted_at'] ? 'Selesai' : 'Bergabung';
        }
        unset($participant);

        $currentQuestion = null;
        $answered = false;
        if ($room['live_status'] === 'running') {
            $currentQuestion = quiz_platform_current_question($pdo, (int)$room['quiz_id'], (int)$room['current_question_position']);
            if ($currentQuestion) {
                $currentQuestion = [
                    'id' => (int)$currentQuestion['id'],
                    'question_text' => $currentQuestion['question_text'],
                    'image_path' => $currentQuestion['image_path'],
                    'duration_seconds' => (int)$currentQuestion['duration_seconds'],
                ];
            }
            if ($currentQuestion) {
                $answeredStmt = $pdo->prepare('SELECT 1 FROM answers WHERE attempt_id = ? AND question_id = ?');
                $answeredStmt->execute([(int)$attemptId, (int)$currentQuestion['id']]);
                $answered = (bool)$answeredStmt->fetchColumn();
                if (!$answered && !$isHost) {
                    $optionsStmt = $pdo->prepare('SELECT id, option_text FROM options WHERE question_id = ? ORDER BY id');
                    $optionsStmt->execute([(int)$currentQuestion['id']]);
                    $currentQuestion['options'] = $optionsStmt->fetchAll();
                }
                $currentQuestion['position'] = (int)$room['current_question_position'];
                $currentQuestion['deadline_at'] = $room['question_deadline_at'];
                $currentQuestion['seconds_remaining'] = $room['question_deadline_at']
                    ? max(0, strtotime($room['question_deadline_at']) - time())
                    : 0;
            }
        }
        $leaderboard = $room['live_status'] === 'finished' ? quiz_platform_leaderboard($pdo, (int)$sessionId) : [];
        api_response(200, [
            'session' => [
                'id' => (int)$room['id'],
                'title' => $room['title'],
                'code' => $room['session_code'],
                'status' => $room['live_status'],
                'mode' => $room['mode'],
                'current_question_position' => (int)$room['current_question_position'],
                'question_count' => (int)$room['question_count'],
            ],
            'is_host' => $isHost,
            'answered' => $answered,
            'participants' => $participants,
            'question' => $currentQuestion,
            'leaderboard' => $leaderboard,
        ]);
    }

    if ($method === 'POST' && $action === 'live_host_action') {
        $user = api_require_user();
        api_require_csrf();
        $body = api_body();
        $sessionId = filter_var($body['session_id'] ?? null, FILTER_VALIDATE_INT);
        $hostAction = (string)($body['action'] ?? '');
        if (!$sessionId || !in_array($hostAction, ['start', 'next', 'end'], true)) api_response(422, ['error' => 'Perintah host tidak valid.']);
        $room = quiz_platform_room($pdo, (int)$sessionId);
        if (!$room || $room['mode'] !== 'live') api_response(404, ['error' => 'Live Quiz tidak ditemukan.']);
        if ((int)$room['host_id'] !== (int)$user['id'] && !quiz_platform_admin($user)) api_response(403, ['error' => 'Hanya host atau admin yang dapat mengontrol room.']);

        $pdo->beginTransaction();
        try {
            $lock = $pdo->prepare('SELECT * FROM sessions WHERE id = ? FOR UPDATE');
            $lock->execute([(int)$sessionId]);
            $session = $lock->fetch();
            if ($hostAction === 'start') {
                if ($session['live_status'] !== 'waiting' || $session['status'] !== 'open') api_response(409, ['error' => 'Room tidak berada dalam status menunggu.']);
                $questionStmt = $pdo->prepare('SELECT duration_seconds FROM questions WHERE quiz_id = ? ORDER BY sort_order, id LIMIT 1');
                $questionStmt->execute([(int)$session['quiz_id']]);
                $duration = max(5, (int)$questionStmt->fetchColumn());
                $pdo->prepare("UPDATE sessions SET live_status = 'running', current_question_position = 1, question_started_at = NOW(), question_deadline_at = DATE_ADD(NOW(), INTERVAL ? SECOND) WHERE id = ?")
                    ->execute([$duration, (int)$sessionId]);
            } elseif ($hostAction === 'end') {
                if ($session['live_status'] === 'finished') api_response(409, ['error' => 'Room sudah selesai.']);
                quiz_platform_finalize_live($pdo, (int)$sessionId);
            } else {
                if ($session['live_status'] !== 'running') api_response(409, ['error' => 'Kuis belum berjalan.']);
                $totalStmt = $pdo->prepare('SELECT COUNT(*) FROM questions WHERE quiz_id = ?');
                $totalStmt->execute([(int)$session['quiz_id']]);
                $next = (int)$session['current_question_position'] + 1;
                if ($next > (int)$totalStmt->fetchColumn()) {
                    quiz_platform_finalize_live($pdo, (int)$sessionId);
                } else {
                    $questionStmt = $pdo->prepare('SELECT duration_seconds FROM questions WHERE quiz_id = ? ORDER BY sort_order, id LIMIT 1 OFFSET ?');
                    $questionStmt->bindValue(1, (int)$session['quiz_id'], PDO::PARAM_INT);
                    $questionStmt->bindValue(2, $next - 1, PDO::PARAM_INT);
                    $questionStmt->execute();
                    $duration = max(5, (int)$questionStmt->fetchColumn());
                    $pdo->prepare('UPDATE sessions SET current_question_position = ?, question_started_at = NOW(), question_deadline_at = DATE_ADD(NOW(), INTERVAL ? SECOND) WHERE id = ?')
                        ->execute([$next, $duration, (int)$sessionId]);
                }
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
        api_response(200, ['message' => 'Status Live Quiz diperbarui.']);
    }

    if ($method === 'POST' && $action === 'live_answer') {
        $user = api_require_user();
        api_require_csrf();
        $body = api_body();
        $sessionId = filter_var($body['session_id'] ?? null, FILTER_VALIDATE_INT);
        $questionId = filter_var($body['question_id'] ?? null, FILTER_VALIDATE_INT);
        $optionId = filter_var($body['option_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$sessionId || !$questionId || !$optionId) api_response(422, ['error' => 'Pilih salah satu jawaban.']);

        $pdo->beginTransaction();
        try {
            $lock = $pdo->prepare('SELECT * FROM sessions WHERE id = ? FOR UPDATE');
            $lock->execute([(int)$sessionId]);
            $session = $lock->fetch();
            if (!$session || $session['mode'] !== 'live' || $session['live_status'] !== 'running' || $session['status'] !== 'open') {
                api_response(409, ['error' => 'Live Quiz tidak sedang menerima jawaban.']);
            }
            if (!$session['question_deadline_at'] || strtotime($session['question_deadline_at']) <= time()) {
                api_response(409, ['error' => 'Waktu soal ini sudah habis.']);
            }
            $attemptStmt = $pdo->prepare('SELECT id FROM attempts WHERE session_id = ? AND user_id = ? AND submitted_at IS NULL');
            $attemptStmt->execute([(int)$sessionId, (int)$user['id']]);
            $attemptId = $attemptStmt->fetchColumn();
            if (!$attemptId) api_response(403, ['error' => 'Anda belum bergabung ke room ini.']);
            $currentQuestion = quiz_platform_current_question($pdo, (int)$session['quiz_id'], (int)$session['current_question_position']);
            if (!$currentQuestion || (int)$currentQuestion['id'] !== (int)$questionId) api_response(409, ['error' => 'Soal ini bukan soal aktif.']);
            $optionStmt = $pdo->prepare('SELECT is_correct FROM options WHERE id = ? AND question_id = ?');
            $optionStmt->execute([(int)$optionId, (int)$questionId]);
            $correct = $optionStmt->fetchColumn();
            if ($correct === false) api_response(422, ['error' => 'Pilihan jawaban tidak valid.']);
            $isCorrect = (int)$correct === 1;
            $save = $pdo->prepare(
                'INSERT INTO answers (attempt_id, question_id, selected_option_id, is_correct, awarded_points, answered_at)
                 VALUES (?, ?, ?, ?, ?, NOW())'
            );
            try {
                $save->execute([(int)$attemptId, (int)$questionId, (int)$optionId, $isCorrect ? 1 : 0, $isCorrect ? 100 : 0]);
            } catch (PDOException $exception) {
                if ((string)$exception->getCode() === '23000') api_response(409, ['error' => 'Jawaban soal ini sudah tercatat.']);
                throw $exception;
            }
            if ($isCorrect) {
                $pdo->prepare('UPDATE attempts SET score = score + 100 WHERE id = ?')->execute([(int)$attemptId]);
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
        api_response(200, ['message' => 'Jawaban berhasil dikirim.']);
    }

    if ($method === 'POST' && $action === 'quiz_upload_image') {
        $user = api_require_user();
        api_require_csrf();
        $quizId = filter_var($_POST['quiz_id'] ?? null, FILTER_VALIDATE_INT);
        $quiz = $quizId ? quiz_platform_quiz($pdo, (int)$quizId) : null;
        if (!$quiz || !quiz_platform_can_manage($quiz, $user)) api_response(403, ['error' => 'Anda tidak dapat mengunggah gambar untuk kuis ini.']);
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) api_response(422, ['error' => 'Pilih gambar yang berhasil diunggah.']);
        if ((int)$_FILES['image']['size'] > 5 * 1024 * 1024) api_response(422, ['error' => 'Ukuran gambar maksimal 5 MB.']);
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['image']['tmp_name']);
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($extensions[$mime])) api_response(422, ['error' => 'Format gambar harus JPG, PNG, atau WebP.']);
        $relativeDirectory = 'uploads/quiz-images';
        $directory = dirname(__DIR__) . DIRECTORY_SEPARATOR . $relativeDirectory;
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('Folder upload gambar tidak dapat dibuat.');
        }
        $fileName = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
        if (!move_uploaded_file($_FILES['image']['tmp_name'], $directory . DIRECTORY_SEPARATOR . $fileName)) {
            throw new RuntimeException('Gambar soal gagal disimpan.');
        }
        api_response(200, ['image_path' => $relativeDirectory . '/' . $fileName]);
    }

    return false;
}
