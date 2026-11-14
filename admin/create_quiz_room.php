<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo = db();
$error = '';
$preview = null;

$vocabComplete = true;
$missingVocab = [];
$stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM vocabularies WHERE is_active = 1");
$totalActive = (int)$stmt->fetchColumn();
$stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM vocabularies WHERE vocab_number BETWEEN 1 AND 500 AND is_active = 1");
$totalRange = (int)$stmt->fetchColumn();
if ($totalRange < 500) {
    $vocabComplete = false;
    for ($i = 1; $i <= 500; $i++) {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM vocabularies WHERE vocab_number=? AND is_active=1");
        $chk->execute([$i]);
        if ((int)$chk->fetchColumn() === 0) {
            $missingVocab[] = $i;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $title = trim($_POST['title'] ?? '');
    $startNum = (int)($_POST['start_number'] ?? 0);
    $endNum = (int)($_POST['end_number'] ?? 0);
    $duration = max(1, min(180, (int)($_POST['duration'] ?? 15)));
    $randomQuestions = isset($_POST['random_questions']) ? 1 : 0;
    $randomAnswers = isset($_POST['random_answers']) ? 1 : 0;
    $startAt = trim($_POST['start_at'] ?? '');
    $endAt = trim($_POST['end_at'] ?? '');

    if ($title === '') {
        $error = 'Judul quiz room wajib diisi.';
    } elseif ($startNum < 1 || $endNum > 500 || $startNum > $endNum) {
        $error = 'Rentang nomor harus antara 1 dan 500, dan nomor awal tidak boleh lebih besar dari nomor akhir.';
    } else {
        $missing = [];
        for ($i = $startNum; $i <= $endNum; $i++) {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM vocabularies WHERE vocab_number=? AND is_active=1");
            $chk->execute([$i]);
            if ((int)$chk->fetchColumn() === 0) {
                $missing[] = $i;
            }
        }
        if (!empty($missing)) {
            $error = 'Beberapa kosakata dalam rentang tidak tersedia atau nonaktif: ' . implode(', ', $missing);
        } else {
            $questionCount = $endNum - $startNum + 1;
            $code = strtoupper(bin2hex(random_bytes(3)));
            while ($pdo->prepare("SELECT COUNT(*) FROM quiz_rooms WHERE session_code=?")->execute([$code])->fetchColumn() > 0) {
                $code = strtoupper(bin2hex(random_bytes(3)));
            }
            try {
                $ins = $pdo->prepare("
                    INSERT INTO quiz_rooms (title, start_number, end_number, question_count, random_questions, random_answers, duration_minutes, start_at, end_at, status, session_code, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'open', ?, ?)
                ");
                $ins->execute([$title, $startNum, $endNum, $questionCount, $randomQuestions, $randomAnswers, $duration, $startAt ?: null, $endAt ?: null, $code, (int)current_user()['id']]);
                header('Location: ' . BASE_URL . '/admin/quiz_rooms.php');
                exit;
            } catch (PDOException $e) {
                $error = 'Gagal membuat quiz room: ' . $e->getMessage();
            }
        }
    }

    if ($error === '' && $startNum > 0 && $endNum > 0 && $startNum <= $endNum) {
        $preview = ['start_number' => $startNum, 'end_number' => $endNum, 'question_count' => $endNum - $startNum + 1];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['start_number'], $_GET['end_number'])) {
    $startNum = (int)($_GET['start_number'] ?? 0);
    $endNum = (int)($_GET['end_number'] ?? 0);
    if ($startNum >= 1 && $endNum <= 500 && $startNum <= $endNum) {
        $missing = [];
        for ($i = $startNum; $i <= $endNum; $i++) {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM vocabularies WHERE vocab_number=? AND is_active=1");
            $chk->execute([$i]);
            if ((int)$chk->fetchColumn() === 0) {
                $missing[] = $i;
            }
        }
        $preview = ['start_number' => $startNum, 'end_number' => $endNum, 'question_count' => $endNum - $startNum + 1, 'missing' => $missing];
    }
}

$pageTitle = 'Buat Quiz Room';
require __DIR__ . '/../includes/header.php';
?>
<div class="eyebrow">Quiz Room Builder · Step 1</div>
<h1>Buat Quiz Room</h1>
<p class="muted">Pilih rentang nomor kosakata. Semua kosakata dalam rentang otomatis menjadi soal.</p>

<?php if ($error): ?><div class="alert"><?= e($error) ?></div><?php endif; ?>

<?php if (!$vocabComplete): ?>
    <div class="alert">Bank kosakata belum lengkap (<?= $totalRange ?>/500 aktif).</div>
<?php endif; ?>

<form method="post" id="quizRoomForm">
    <div class="field-group"><label>Judul Quiz Room</label><input name="title" required maxlength="150" value="<?= e($_POST['title'] ?? '') ?>" placeholder="Contoh: Kosakata 1-34"></div>
    <div class="field-group"><label>Nomor Kosakata Awal</label><input type="number" name="start_number" min="1" max="500" required value="<?= e($_POST['start_number'] ?? '') ?>" onchange="updatePreview()"></div>
    <div class="field-group"><label>Nomor Kosakata Akhir</label><input type="number" name="end_number" min="1" max="500" required value="<?= e($_POST['end_number'] ?? '') ?>" onchange="updatePreview()"></div>
    <div class="field-group"><label>Durasi (menit)</label><input type="number" name="duration" min="1" max="180" value="15"><p class="field-helper">Waktu pengerjaan untuk setiap siswa.</p></div>
    <div class="field-group"><label>Waktu Mulai (opsional)</label><input type="datetime-local" name="start_at"><p class="field-helper">Kosongkan jika room bisa diakses segera.</p></div>
    <div class="field-group"><label>Waktu Berakhir (opsional)</label><input type="datetime-local" name="end_at"><p class="field-helper">Kosongkan jika room tidak tenggat waktu.</p></div>
    <div class="field-group"><label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" name="random_questions" value="1" <?= isset($_POST['random_questions']) ? 'checked' : '' ?>> Acak urutan soal untuk setiap siswa</label><p class="field-helper">Setiap siswa mendapat urutan soal berbeda tetapi kosakata yang sama.</p></div>
    <?php if ($preview): ?>
        <div class="admin-info" id="previewBox">
            <strong>Preview:</strong> Rentang <?= $preview['start_number'] ?> – <?= $preview['end_number'] ?> = <b><?= $preview['question_count'] ?></b> soal.
            <?php if (!empty($preview['missing'])): ?>
                <br><span style="color:#D95D39;">Nomor tidak tersedia: <?= e(implode(', ', $preview['missing'])) ?></span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <div id="previewResult"></div>
    <button type="submit" class="button full" <?= !$vocabComplete ? 'disabled' : '' ?>>Buat Quiz Room</button>
</form>

<script>
function updatePreview() {
    const start = parseInt(document.querySelector('input[name="start_number"]').value) || 0;
    const end = parseInt(document.querySelector('input[name="end_number"]').value) || 0;
    const result = document.getElementById('previewResult');
    if (start >= 1 && end <= 500 && start <= end) {
        result.innerHTML = '<strong>Estimasi:</strong> ' + (end - start + 1) + ' soal';
        result.style.color = '#4E7A1E';
    } else if (start > 0 && end > 0) {
        result.innerHTML = '<strong>Rentang tidak valid.</strong>';
        result.style.color = '#D95D39';
    } else {
        result.innerHTML = '';
    }
}
updatePreview();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>