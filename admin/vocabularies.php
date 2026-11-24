<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo = db();
$message = '';

/** [1,2,3,7,9,10] -> "1–3, 7, 9–10" */
function compress_ranges(array $nums): string
{
    if (!$nums) return '';
    sort($nums);
    $out = [];
    $start = $prev = $nums[0];
    foreach (array_slice($nums, 1) as $n) {
        if ($n === $prev + 1) { $prev = $n; continue; }
        $out[] = $start === $prev ? (string)$start : $start . '–' . $prev;
        $start = $prev = $n;
    }
    $out[] = $start === $prev ? (string)$start : $start . '–' . $prev;
    return implode(', ', $out);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $vocabNumber = (int)($_POST['vocab_number'] ?? 0);

    if ($action === 'toggle' && $vocabNumber > 0) {
        $pdo->prepare("UPDATE vocabularies SET is_active = 1 - is_active WHERE vocab_number=?")->execute([$vocabNumber]);
        $message = "Status kosakata nomor {$vocabNumber} diubah.";
    } elseif ($action === 'update' && $vocabNumber > 0) {
        $wordEn = trim($_POST['word_en'] ?? '');
        $meaningId = trim($_POST['meaning_id'] ?? '');
        if ($wordEn !== '' && $meaningId !== '') {
            $pdo->prepare("UPDATE vocabularies SET word_en=?, meaning_id=? WHERE vocab_number=?")->execute([$wordEn, $meaningId, $vocabNumber]);
            $message = "Kosakata nomor {$vocabNumber} diperbarui.";
        } else {
            $message = 'Kata dan arti tidak boleh kosong.';
        }
    }
}

$all = $pdo->query("SELECT * FROM vocabularies ORDER BY vocab_number")->fetchAll();
$total = count($all);
$activeCount = 0;
$have = [];
foreach ($all as $row) {
    $have[(int)$row['vocab_number']] = true;
    if ((int)$row['is_active'] === 1) $activeCount++;
}
$missingNumbers = [];
for ($i = 1; $i <= 500; $i++) {
    if (!isset($have[$i])) $missingNumbers[] = $i;
}
$isComplete = ($total === 500 && !$missingNumbers);

$pageTitle = 'Bank Kosakata';
require __DIR__ . '/../includes/header.php';
?>
<div class="eyebrow">Vocabulary Bank · 500 Speaking Vocabulary</div>
<h1>Manajemen Bank Kosakata</h1>
<p class="muted">Semua 500 kosakata dari modul Conversation Bahasa Inggris. Nomor 1–500 bersifat unik dan tidak berubah.</p>

<?php if ($message): ?><div class="alert"><?= e($message) ?></div><?php endif; ?>

<div class="admin-info">
    <strong>Status Kelengkapan Bank:</strong>
    <?php if ($isComplete): ?>
        <span class="badge badge-open">Lengkap · 500/500 kosakata tersedia</span>
    <?php else: ?>
        <span class="badge badge-closed">Belum Lengkap · <?= $total ?>/500 tersedia</span>
        <?php if ($missingNumbers): ?>
            <br><small class="muted">Nomor yang belum ada: <?= e(compress_ranges($missingNumbers)) ?></small>
        <?php endif; ?>
    <?php endif; ?>
    <br><small class="muted">Aktif: <?= $activeCount ?> · Nonaktif: <?= $total - $activeCount ?></small>
</div>

<?php if (!$isComplete): ?>
    <div class="alert">Perhatian: sebagian nomor kosakata belum tersedia. Quiz room hanya bisa dibuat untuk rentang yang seluruh nomornya ada dan aktif.</div>
<?php endif; ?>

<div class="table-header-row">
    <a class="button" href="<?= BASE_URL ?>/import_vocab.php">Import dari file JSON</a>
    <a class="button secondary" href="<?= BASE_URL ?>/dashboard.php">Kembali ke Dashboard</a>
</div>

<h2 class="section-title">Daftar Kosakata (<?= $total ?>/500)</h2>
<div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th style="width:80px;">No</th>
                <th style="width:160px;">English</th>
                <th>Arti Indonesia</th>
                <th style="width:110px;">Status</th>
                <th style="width:260px;">Aksi</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$all): ?>
            <tr><td colspan="5" class="muted">Bank kosakata masih kosong. Isi dulu lewat import.</td></tr>
        <?php endif; ?>
        <?php foreach ($all as $row): ?>
            <tr>
                <td><b><?= (int)$row['vocab_number'] ?></b></td>
                <td><?= e($row['word_en']) ?></td>
                <td><?= e($row['meaning_id']) ?></td>
                <td>
                    <span class="badge <?= (int)$row['is_active'] === 1 ? 'badge-open' : 'badge-closed' ?>">
                        <?= (int)$row['is_active'] === 1 ? 'Aktif' : 'Nonaktif' ?>
                    </span>
                </td>
                <td>
                    <form method="post" style="display:inline;" onsubmit="return confirm('Ubah status kosakata #<?= (int)$row['vocab_number'] ?>?')">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="vocab_number" value="<?= (int)$row['vocab_number'] ?>">
                        <button type="submit" class="button" style="padding:6px 12px;font-size:12px;"><?= (int)$row['is_active'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                    </form>
                    <details style="display:inline-block;margin-left:6px;">
                        <summary style="cursor:pointer;font-size:12px;">Edit</summary>
                        <form method="post" style="margin-top:6px;">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="vocab_number" value="<?= (int)$row['vocab_number'] ?>">
                            <input name="word_en" value="<?= e($row['word_en']) ?>" maxlength="100" required style="margin-bottom:4px;">
                            <input name="meaning_id" value="<?= e($row['meaning_id']) ?>" maxlength="255" required style="margin-bottom:4px;">
                            <button type="submit" class="button" style="padding:6px 12px;font-size:12px;">Simpan</button>
                        </form>
                    </details>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
