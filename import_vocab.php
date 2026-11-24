<?php
/**
 * EnglishHub - Import 500 kosakata dari modul ke database
 * Usage: Buka http://localhost/EnglishHub/import_vocab.php di browser
 * Hanya bisa dijalankan sekali (idempotent: INSERT ... ON DUPLICATE KEY UPDATE)
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_role('admin');

$jsonFile = __DIR__ . '/../Temp/vocab_500.json';
// Fallback jika file tidak ada di path di atas
if (!file_exists($jsonFile)) {
    $jsonFile = 'C:/Temp/vocab_500.json';
}

if (!file_exists($jsonFile)) {
    die('File vocab_500.json tidak ditemukan. Jalankan ekstraksi DOCX terlebih dahulu.');
}

$vocab = json_decode(file_get_contents($jsonFile), true);
if (!$vocab || !is_array($vocab)) {
    die('File vocab_500.json tidak valid.');
}

echo "<h1>Import Bank Kosakata EnglishHub</h1>";
echo "<p>Total entri dalam file: " . count($vocab) . "</p>";

$pdo = db();
$categoryId = $pdo->query("SELECT id FROM categories WHERE name = 'Vocabulary' LIMIT 1")->fetchColumn();
if (!$categoryId) {
    die('Kategori Vocabulary tidak ditemukan. Pastikan migrasi platform kuis sudah diterapkan.');
}
$success = 0;
$errors = [];

foreach ($vocab as $index => $item) {
    $vocabNumber = $index + 1;
    $wordEn = trim($item['en'] ?? '');
    $meaningId = trim($item['id'] ?? '');

    if ($wordEn === '') {
        $errors[] = "Baris {$vocabNumber}: kata Inggris kosong, dilewati.";
        continue;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO vocabularies (vocab_number, word_en, meaning_id, category_id, is_active)
            VALUES (?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE
                word_en = VALUES(word_en),
                meaning_id = VALUES(meaning_id),
                category_id = VALUES(category_id),
                is_active = VALUES(is_active),
                updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$vocabNumber, $wordEn, $meaningId, (int)$categoryId]);
        $success++;
    } catch (PDOException $e) {
        $errors[] = "Baris {$vocabNumber} ({$wordEn}): " . $e->getMessage();
    }
}

echo "<h2>Hasil Import</h2>";
echo "<p><strong>Berhasil:</strong> {$success} kosakata</p>";
if (!empty($errors)) {
    echo "<p><strong>Gagal:</strong> " . count($errors) . "</p>";
    echo "<ul>";
    foreach (array_slice($errors, 0, 20) as $e) {
        echo "<li>" . htmlspecialchars($e) . "</li>";
    }
    echo "</ul>";
    if (count($errors) > 20) {
        echo "<p>... dan " . (count($errors) - 20) . " error lainnya.</p>";
    }
} else {
    echo "<p>Semua kosakata berhasil diimpor tanpa error.</p>";
}

echo "<hr>";
echo "<a href='" . BASE_URL . "/admin/vocabularies.php'>Lihat Manajemen Kosakata</a> | ";
echo "<a href='" . BASE_URL . "/dashboard.php'>Kembali ke Dashboard</a>";