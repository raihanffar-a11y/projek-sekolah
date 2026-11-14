<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo = db();

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $vocabNumber = (int)($_POST['vocab_number'] ?? 0);
    if ($action === 'toggle' && $vocabNumber > 0) {
        $stmt = $pdo->prepare("SELECT is_active FROM vocabularies WHERE vocab_number=?");
        $stmt->execute([$vocabNumber]);
        $current = $stmt->fetchColumn();
        $newVal = ($current === '1' || $current === 1) ? 0 : 1;
        $upd = $pdo->prepare("UPDATE vocabularies SET is_active=? WHERE vocab_number=?");
        $upd->execute([$newVal, $vocabNumber]);
        $message = "Kosakata nomor {$vocabNumber} diubah ke " . ($newVal ? 'aktif' : 'nonaktif') . ".";
    } elseif ($action === 'update' && $vocabNumber > 0) {
        $wordEn = trim($_POST['word_en'] ?? '');
        $meaningId = trim($_POST['meaning_id'] ?? '');
        if ($wordEn !== '' && $meaningId !== '') {
            $upd = $pdo->prepare("UPDATE vocabularies SET word_en=?, meaning_id=? WHERE vocab_number=?");
            $upd->execute([$wordEn, $meaningId, $vocabNumber]);
            $message = "Kosakata nomor {$vocabNumber} diperbarui.";
        } else {
            $message = "Kata dan arti tidak boleh kosong.";
        }
    }
}

$all = $pdo->query("SELECT * FROM vocabularies ORDER BY vocab_number")->fetchAll();
$total = count($all);
$activeCount = 0;
$missingNumbers = [];

for ($i = 1; $i <= 500; $i++) {
    $found = false;
    foreach ($all as $row) {
        if ((int)$row['vocab_number'] === $i) {
            $found = true;
            if ((int)$row['is_active'] === 1) $activeCount++;
            break;
        }
    }
    if (!$found) {
        $missingNumbers[] = $i;
    }
}

$isComplete = ($total === 500 && empty($missingNumbers));

$pageTitle = 'Bank Kosakata';
require __DIR__ . '/../includes/header.php';
?>
<div class="eyebrow">Vocabulary Bank · 500 Speaking Vocabulary</div>
<h1>Manajemen Bank Kosakata</h1>
<p class="muted">Semua 500 kosakata dari modul Conversation Bahasa Inggris. Nomor 1-500 bersifat unik dan tidak berubah.</p>

<?php if ($message): ?><div class="alert"><?= e($message) ?></div><?php endif; ?>

<div class="admin-info">
    <strong>Status Kelengkapan Bank:</strong>
    <?php if ($isComplete): ?>
        <span class="badge badge-open">Lengkap · 500/500 kosakata tersedia</span>
    <?php else: ?>
        <span class="badge badge-closed">Belum Lengkap · <?= $total ?>/500 tersedia</span>
        <?php if (!empty($missingNumbers)): ?>
            <br><small class="muted">Nomor yang hilang: <?= e(implode(', ', $missingNumbers)) ?></small>
        <?php endif; ?>
    <?php endif; ?>
    <br><small class="muted">Aktif: <?= $activeCount ?> · Nonaktif: <?= $total - $activeCount ?></small>
</div>

<?php if (!$isComplete): ?>
    <div class="alert">Perhatian: Beberapa nomor kosakata belum tersedia. Quiz room dengan rentang yang mencakup nomor hilang tidak dapat dibuat hingga bank kosakata lengkap.</div>
<?php endif; ?>

<div class="table-header-row">
    <a class="button" href="<?= BASE_URL ?>/import_vocab.php">Ulangi Import dari Modul</a>
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
                <th style="width:160px;">Aksi</th>
            </tr>
        </thead>
        <tbody>
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
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
