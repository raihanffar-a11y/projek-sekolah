<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/quiz_platform.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function api_response(int $status, array $data): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function api_body(): array
{
    $body = json_decode(file_get_contents('php://input'), true);
    return is_array($body) ? $body : [];
}

function api_require_csrf(): void
{
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $known = $_SESSION['csrf'] ?? '';
    if (!is_string($sent) || $known === '' || !hash_equals($known, $sent)) {
        api_response(419, ['error' => 'Sesi formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.']);
    }
}

function api_require_user(): array
{
    $user = current_user();
    if (!$user) {
        api_response(401, ['error' => 'Silakan masuk untuk melanjutkan.']);
    }
    return $user;
}

function api_user_payload(array $user): array
{
    return [
        'id' => (int)$user['id'],
        'name' => (string)$user['name'],
        'email' => (string)$user['email'],
        'role' => (string)$user['role'],
    ];
}

$action = (string)($_GET['action'] ?? '');
$method = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');

try {
    if ($method === 'GET' && $action === 'me') {
        api_response(200, [
            'user' => current_user() ? api_user_payload(current_user()) : null,
            'csrfToken' => csrf_token(),
        ]);
    }

    if ($method === 'POST' && in_array($action, ['login', 'register'], true)) {
        api_require_csrf();
        $body = api_body();
        $email = strtolower(trim((string)($body['email'] ?? '')));
        $password = (string)($body['password'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            api_response(422, ['error' => 'Masukkan alamat email yang valid.']);
        }

        if ($action === 'login') {
            if ($password === '') {
                api_response(422, ['error' => 'Password wajib diisi.']);
            }
            $stmt = db()->prepare('SELECT id, name, email, password, role FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            if (!$user || !password_verify($password, $user['password'])) {
                api_response(422, ['error' => 'Email atau password salah.']);
            }
            session_regenerate_id(true);
            unset($user['password']);
        } else {
            $name = trim((string)($body['name'] ?? ''));
            if ($name === '' || mb_strlen($name) > 100) {
                api_response(422, ['error' => 'Nama wajib diisi (maksimal 100 karakter).']);
            }
            if (strlen($password) < 8) {
                api_response(422, ['error' => 'Password minimal 8 karakter.']);
            }
            $stmt = db()->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'student')");
            try {
                $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
            } catch (PDOException $exception) {
                if ((string)$exception->getCode() === '23000') {
                    api_response(409, ['error' => 'Email ini sudah terdaftar. Silakan masuk.']);
                }
                throw $exception;
            }
            session_regenerate_id(true);
            $user = [
                'id' => (int)db()->lastInsertId(),
                'name' => $name,
                'email' => $email,
                'role' => 'student',
            ];
        }

        $_SESSION['user'] = [
            'id' => (int)$user['id'],
            'name' => (string)$user['name'],
            'email' => (string)$user['email'],
            'role' => (string)$user['role'],
        ];
        unset($_SESSION['csrf']);
        api_response(200, ['user' => api_user_payload($_SESSION['user']), 'csrfToken' => csrf_token()]);
    }

    if ($method === 'POST' && $action === 'logout') {
        api_require_csrf();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        api_response(200, ['message' => 'Anda sudah keluar.']);
    }

    if ($method === 'GET' && $action === 'dashboard') {
        $user = api_require_user();
        if ($user['role'] === 'admin') {
            $counts = db()->query(
                "SELECT (SELECT COUNT(*) FROM users WHERE role='student') students,
                        (SELECT COUNT(*) FROM quizzes) quizzes,
                        (SELECT COUNT(*) FROM sessions) sessions,
                        (SELECT COUNT(*) FROM vocabularies WHERE is_active=1) vocabularies,
                        (SELECT COUNT(*) FROM quiz_rooms) quiz_rooms"
            )->fetch();
            api_response(200, ['role' => 'admin', 'counts' => $counts]);
        }

        $pdo = db();
        $stats = $pdo->prepare(
            'SELECT COUNT(*) total_quiz,
                    COALESCE(AVG(score / NULLIF(max_score, 0) * 100), 0) avg_score,
                    COALESCE(SUM(COALESCE(completion_seconds, 0)), 0) study_seconds
             FROM attempts WHERE user_id = ? AND submitted_at IS NOT NULL'
        );
        $stats->execute([(int)$user['id']]);
        $activity = $pdo->prepare('SELECT COUNT(*) FROM user_vocabulary_progress WHERE user_id = ?');
        $activity->execute([(int)$user['id']]);
        $wordsLearned = (int)$activity->fetchColumn();
        $vocabularyCount = (int)$pdo->query('SELECT COUNT(*) FROM vocabularies WHERE is_active = 1')->fetchColumn();
        $recent = $pdo->prepare(
            "SELECT a.score, a.max_score, a.submitted_at, COALESCE(qr.title, q.title) title
             FROM attempts a
             LEFT JOIN quiz_rooms qr ON qr.id = a.quiz_room_id
             LEFT JOIN sessions s ON s.id = a.session_id
             LEFT JOIN quizzes q ON q.id = s.quiz_id
             WHERE a.user_id = ? AND a.submitted_at IS NOT NULL
             ORDER BY a.submitted_at DESC LIMIT 5"
        );
        $recent->execute([(int)$user['id']]);
        $recentResults = $recent->fetchAll();
        $recentVocabularyStmt = $pdo->prepare(
            'SELECT v.word_en, v.meaning_id, p.last_studied_at
             FROM user_vocabulary_progress p
             JOIN vocabularies v ON v.id = p.vocabulary_id
             WHERE p.user_id = ?
             ORDER BY p.last_studied_at DESC LIMIT 5'
        );
        $recentVocabularyStmt->execute([(int)$user['id']]);
        $recentVocabulary = $recentVocabularyStmt->fetchAll();
        $activities = [];
        foreach ($recentResults as $result) {
            $activities[] = [
                'type' => 'quiz',
                'title' => $result['title'] ?: 'Quiz',
                'detail' => 'Skor ' . $result['score'] . ' / ' . $result['max_score'],
                'occurred_at' => $result['submitted_at'],
            ];
        }
        foreach ($recentVocabulary as $vocabulary) {
            $activities[] = [
                'type' => 'vocabulary',
                'title' => $vocabulary['word_en'],
                'detail' => $vocabulary['meaning_id'],
                'occurred_at' => $vocabulary['last_studied_at'],
            ];
        }
        usort($activities, static function (array $left, array $right): int {
            return strtotime($right['occurred_at']) <=> strtotime($left['occurred_at']);
        });
        api_response(200, [
            'role' => 'student',
            'stats' => $stats->fetch(),
            'wordsLearned' => $wordsLearned,
            'vocabularyCount' => $vocabularyCount,
            'recentResults' => $recentResults,
            'latestVocabulary' => $recentVocabulary[0] ?? null,
            'recentActivities' => array_slice($activities, 0, 6),
        ]);
    }

    if ($method === 'GET' && $action === 'vocabulary') {
        $user = api_require_user();
        $query = trim((string)($_GET['q'] ?? ''));
        $stmt = db()->prepare(
            "SELECT v.id, v.vocab_number, v.word_en, v.meaning_id,
                    EXISTS(SELECT 1 FROM vocabulary_bookmarks b WHERE b.user_id = ? AND b.vocabulary_id = v.id) bookmarked,
                    EXISTS(SELECT 1 FROM user_vocabulary_progress p WHERE p.user_id = ? AND p.vocabulary_id = v.id) studied
             FROM vocabularies v
             WHERE v.is_active = 1 AND (? = '' OR v.word_en LIKE ? OR v.meaning_id LIKE ?)
             ORDER BY v.vocab_number LIMIT 500"
        );
        $pattern = '%' . $query . '%';
        $stmt->execute([(int)$user['id'], (int)$user['id'], $query, $pattern, $pattern]);
        api_response(200, ['items' => $stmt->fetchAll()]);
    }

    if ($method === 'GET' && $action === 'grammar') {
        api_require_user();
        $items = db()->query(
            'SELECT id, title, summary, formula, example_en, example_id, sort_order
             FROM grammar_lessons WHERE is_active = 1 ORDER BY sort_order, id'
        )->fetchAll();
        api_response(200, ['items' => $items]);
    }

    if ($method === 'GET' && $action === 'progress') {
        $user = api_require_user();
        $pdo = db();
        $stats = $pdo->prepare(
            'SELECT COUNT(*) total_quiz,
                    COALESCE(AVG(score / NULLIF(max_score, 0) * 100), 0) avg_score,
                    COALESCE(SUM(score), 0) points_earned
             FROM attempts WHERE user_id = ? AND submitted_at IS NOT NULL'
        );
        $stats->execute([(int)$user['id']]);
        $history = $pdo->prepare(
            "SELECT a.score, a.max_score, a.submitted_at, COALESCE(qr.title, q.title) title,
                    qr.start_number, qr.end_number
             FROM attempts a
             LEFT JOIN quiz_rooms qr ON qr.id = a.quiz_room_id
             LEFT JOIN sessions s ON s.id = a.session_id
             LEFT JOIN quizzes q ON q.id = s.quiz_id
             WHERE a.user_id = ? AND a.submitted_at IS NOT NULL
             ORDER BY a.submitted_at DESC LIMIT 10"
        );
        $history->execute([(int)$user['id']]);
        $learned = $pdo->prepare('SELECT COUNT(*) FROM user_vocabulary_progress WHERE user_id = ?');
        $learned->execute([(int)$user['id']]);
        api_response(200, [
            'stats' => $stats->fetch(),
            'wordsLearned' => (int)$learned->fetchColumn(),
            'history' => $history->fetchAll(),
        ]);
    }

    if ($method === 'POST' && in_array($action, ['bookmark', 'study'], true)) {
        $user = api_require_user();
        api_require_csrf();
        $body = api_body();
        $vocabularyId = filter_var($body['vocabularyId'] ?? null, FILTER_VALIDATE_INT);
        if (!$vocabularyId || $vocabularyId < 1) {
            api_response(422, ['error' => 'Kosakata tidak valid.']);
        }
        $pdo = db();
        $exists = $pdo->prepare('SELECT id FROM vocabularies WHERE id = ? AND is_active = 1');
        $exists->execute([$vocabularyId]);
        if (!$exists->fetchColumn()) {
            api_response(404, ['error' => 'Kosakata tidak ditemukan.']);
        }
        if ($action === 'bookmark') {
            $bookmarked = filter_var($body['bookmarked'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if ($bookmarked) {
                $stmt = $pdo->prepare('INSERT IGNORE INTO vocabulary_bookmarks (user_id, vocabulary_id) VALUES (?, ?)');
                $stmt->execute([(int)$user['id'], $vocabularyId]);
            } else {
                $stmt = $pdo->prepare('DELETE FROM vocabulary_bookmarks WHERE user_id = ? AND vocabulary_id = ?');
                $stmt->execute([(int)$user['id'], $vocabularyId]);
            }
            api_response(200, ['bookmarked' => $bookmarked]);
        }
        $stmt = $pdo->prepare(
            'INSERT INTO user_vocabulary_progress (user_id, vocabulary_id, practice_count)
             VALUES (?, ?, 1)
             ON DUPLICATE KEY UPDATE practice_count = practice_count + 1, last_studied_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([(int)$user['id'], $vocabularyId]);
        api_response(200, ['message' => 'Kosakata ditandai sudah dipelajari.']);
    }

    if ($method === 'POST' && $action === 'profile') {
        $user = api_require_user();
        api_require_csrf();
        $body = api_body();
        $name = trim((string)($body['name'] ?? ''));
        $email = strtolower(trim((string)($body['email'] ?? '')));
        if ($name === '' || mb_strlen($name) > 100) {
            api_response(422, ['error' => 'Nama wajib diisi (maksimal 100 karakter).']);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            api_response(422, ['error' => 'Masukkan alamat email yang valid.']);
        }
        try {
            $stmt = db()->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?');
            $stmt->execute([$name, $email, (int)$user['id']]);
        } catch (PDOException $exception) {
            if ((string)$exception->getCode() === '23000') {
                api_response(409, ['error' => 'Email ini sudah digunakan akun lain.']);
            }
            throw $exception;
        }
        $_SESSION['user']['name'] = $name;
        $_SESSION['user']['email'] = $email;
        api_response(200, ['user' => api_user_payload($_SESSION['user'])]);
    }

    if (quiz_platform_handle($method, $action)) {
        api_response(404, ['error' => 'API endpoint tidak ditemukan.']);
    }

    api_response(404, ['error' => 'API endpoint tidak ditemukan.']);
} catch (Throwable $exception) {
    error_log('EnglishHub API error: ' . $exception->getMessage());
    api_response(500, ['error' => 'Terjadi kesalahan server. Periksa konfigurasi dan migrasi database.']);
}
