<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
if (current_user()) redirect('dashboard.php');
$pageTitle = 'Learn English';
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
  <div class="eyebrow">English learning platform · SMK edition</div>
  <h1>Make English<br><span class="accent">your advantage.</span></h1>
  <p>Belajar, berlatih, dan berkembang bersama. Ikuti English Night, kerjakan quiz, dan pantau progres belajarmu dalam satu tempat.</p>
  <div class="hero-actions"><a class="button" href="<?= BASE_URL ?>/register.php">Create student account</a><a class="button secondary" href="<?= BASE_URL ?>/login.php">Sign in</a></div>
</section>
<div class="grid">
  <div class="grid-item"><h3>Quiz Arena</h3><p class="muted">Masukkan kode sesi dan kerjakan kuis dengan alur yang simpel.</p></div>
  <div class="grid-item"><h3>Instant Results</h3><p class="muted">Nilai otomatis tersimpan setelah kamu mengumpulkan jawaban.</p></div>
  <div class="grid-item"><h3>Keep Growing</h3><p class="muted">Pantau hasil belajar dan tingkatkan kemampuan bahasa Inggrismu.</p></div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
