<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$pdo = db();
$error = '';

/** Nomor kosakata aktif yang TIDAK ada dalam rentang (1 query, bukan 1 query per nomor). */
function missing_vocab(PDO $pdo, int $from, int $to): array
{
    $st = $pdo->prepare("SELECT vocab_number FROM vocabularies WHERE vocab_number BETWEEN ? AND ? AND is_active = 1");
    $st->execute([$from, $to]);
    $have = array_flip(array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN)));
    $missing = [];
    for ($i = $from; $i <= $to; $i++) {
        if (!isset($have[$i])) $missing[] = $i;
    }
    return $missing;
}

/** "datetime-local" (2026-11-19T10:00) -> "2026-11-19 10:00:00", atau null bila kosong/tidak valid. */
function parse_local_datetime(string $v): ?string
{
    $v = trim($v);
    if ($v === '') return null;
    try {
        return (new DateTimeImmutable(str_replace('T', ' ', $v)))->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        return null;
    }
}

$totalRange = (int)$pdo->query("SELECT COUNT(*) FROM vocabularies WHERE vocab_number BETWEEN 1 AND 500 AND is_active = 1")->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $title = trim($_POST['title'] ?? '');
    $startNum = (int)($_POST['start_number'] ?? 0);
    $endNum = (int)($_POST['end_number'] ?? 0);
    $duration = max(1, min(180, (int)($_POST['duration'] ?? 15)));
    $randomQuestions = isset($_POST['random_questions']) ? 1 : 0;
    $randomAnswers = isset($_POST['random_answers']) ? 1 : 0;
    $startAt = parse_local_datetime($_POST['start_at'] ?? '');
    $endAt = parse_local_datetime($_POST['end_at'] ?? '');

    if ($title === '') {
        $error = 'Judul quiz room wajib diisi.';
    } elseif ($startNum < 1 || $endNum > 500 || $startNum > $endNum) {
        $error = 'Rentang nomor harus antara 1 dan 500, dan nomor awal tidak boleh lebih besar dari nomor akhir.';
    } elseif ($startAt && $endAt && $endAt <= $startAt) {
        $error = 'Waktu berakhir harus setelah waktu mulai.';
    } else {
        $missing = missing_vocab($pdo, $startNum, $endNum);
        if ($missing) {
            $shown = array_slice($missing, 0, 20);
            $error = 'Beberapa kosakata dalam rentang tidak tersedia atau nonaktif: ' . implode(', ', $shown)
                . (count($missing) > 20 ? ' ... dan ' . (count($missing) - 20) . ' lainnya' : '');
        } else {
            $exists = $pdo->prepare("SELECT 1 FROM quiz_rooms WHERE session_code=?");
            do {
                $code = strtoupper(bin2hex(random_bytes(3)));
                $exists->execute([$code]);
            } while ($exists->fetchColumn());

            try {
                $ins = $pdo->prepare("
                    INSERT INTO quiz_rooms (title, start_number, end_number, question_count, random_questions, random_answers, duration_minutes, start_at, end_at, status, session_code, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'open', ?, ?)
                ");
                $ins->execute([
                    $title, $startNum, $endNum, $endNum - $startNum + 1,
                    $randomQuestions, $randomAnswers, $duration, $startAt, $endAt,
                    $code, (int)current_user()['id'],
                ]);
                redirect('admin/quiz_rooms.php');
            } catch (PDOException $ex) {
                error_log('create_quiz_room: ' . $ex->getMessage());
                $error = 'Gagal membuat quiz room. Coba lagi.';
            }
        }
    }
}

$pageTitle = 'Buat Quiz Room';
require __DIR__ . '/../includes/header.php';
?>
<div class="eyebrow">Quiz Room Builder · Step 1</div>
<h1>Buat Quiz Room</h1>
<p class="muted">Pilih rentang nomor kosakata. Semua kosakata dalam rentang otomatis menjadi soal.</p>

<?php if ($error): ?><div class="alert"><?= e($error) ?></div><?php endif; ?>

<?php if ($totalRange < 500): ?>
    <div class="alert">Bank kosakata belum lengkap (<?= $totalRange ?>/500 aktif). Quiz room hanya bisa dibuat untuk rentang yang seluruh nomornya tersedia.</div>
<?php endif; ?>

<form method="post" id="quizRoomForm">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <div class="field-group"><label>Judul Quiz Room</label><input name="title" required maxlength="150" value="<?= e($_POST['title'] ?? '') ?>" placeholder="Contoh: Kosakata 1-34"></div>
    <div class="field-group"><label>Nomor Kosakata Awal</label><input type="number" name="start_number" min="1" max="500" required value="<?= e($_POST['start_number'] ?? '') ?>" oninput="updatePreview()"></div>
    <div class="field-group"><label>Nomor Kosakata Akhir</label><input type="number" name="end_number" min="1" max="500" required value="<?= e($_POST['end_number'] ?? '') ?>" oninput="updatePreview()"></div>
    <div class="field-group"><label>Durasi (menit)</label><input type="number" name="duration" min="1" max="180" value="<?= e($_POST['duration'] ?? '15') ?>"><p class="field-helper">Waktu pengerjaan untuk setiap siswa.</p></div>
    <div class="field-group"><label>Waktu Mulai (opsional)</label><input type="datetime-local" name="start_at" value="<?= e($_POST['start_at'] ?? '') ?>"><p class="field-helper">Kosongkan jika room bisa diakses segera.</p></div>
    <div class="field-group"><label>Waktu Berakhir (opsional)</label><input type="datetime-local" name="end_at" value="<?= e($_POST['end_at'] ?? '') ?>"><p class="field-helper">Kosongkan jika room tidak ada tenggat waktu.</p></div>
    <div class="field-group"><label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" name="random_questions" value="1" <?= isset($_POST['random_questions']) ? 'checked' : '' ?>> Acak urutan soal untuk setiap siswa</label><p class="field-helper">Setiap siswa mendapat urutan soal berbeda tetapi kosakata yang sama.</p></div>
    <div class="field-group"><label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" name="random_answers" value="1" <?= isset($_POST['random_answers']) ? 'checked' : '' ?>> Acak urutan pilihan jawaban</label><p class="field-helper">Jika tidak dicentang, pilihan jawaban diurutkan sesuai abjad.</p></div>
    <div id="previewResult" style="margin-bottom:12px;"></div>
    <button type="submit" class="button full">Buat Quiz Room</button>
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
