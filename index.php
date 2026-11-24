<?php
$reactBuild = __DIR__ . '/frontend/dist/index.html';
if (is_file($reactBuild)) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    readfile($reactBuild);
    exit;
}

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
if (current_user()) redirect('dashboard.php');
$pageTitle = 'Learn English';
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
  <div class="hero-inner">
    <div class="hero-content">
      <div class="eyebrow">English Learning Platform</div>
      <h1>Learn English.<br>Practice Together.<br><span class="hero-highlight">Grow Every Day.</span></h1>
      <p class="hero-sub">Gabunglah dengan komunitas belajar, kerjakan kuis berbasis rentang kosakata, latihan pengucapan, dan pantau progres belajarmu dalam satu tempat yang terintegrasi.</p>
      <div class="hero-actions">
        <a class="button" href="<?= BASE_URL ?>/register.php">Create account</a>
        <a class="button secondary" href="<?= BASE_URL ?>/login.php">Sign in</a>
      </div>
      <div class="hero-stats">
        <div class="hero-stat"><strong>500</strong><span>Kosakata</span></div>
        <div class="hero-stat"><strong>4</strong><span>Format soal</span></div>
        <div class="hero-stat"><strong>XP</strong><span>Leaderboard</span></div>
      </div>
    </div>
    <div class="hero-visual">
      <div class="preview-card">
        <div class="preview-header">Vocabulary Challenge</div>
        <div class="preview-list">
          <div class="preview-item"><span class="preview-word">Wake up</span><span class="preview-divider">—</span><span class="preview-meaning">Bangun tidur</span></div>
          <div class="preview-item"><span class="preview-word">Sleep</span><span class="preview-divider">—</span><span class="preview-meaning">Tidur</span></div>
          <div class="preview-item"><span class="preview-word">Eat</span><span class="preview-divider">—</span><span class="preview-meaning">Makan</span></div>
          <div class="preview-item"><span class="preview-word">Drink</span><span class="preview-divider">—</span><span class="preview-meaning">Minum</span></div>
          <div class="preview-item"><span class="preview-word">Study</span><span class="preview-divider">—</span><span class="preview-meaning">Belajar</span></div>
        </div>
        <div class="preview-footer">500 kosakata dari modul asli · Rentang 1–500</div>
      </div>
      <div class="preview-card quiz-preview">
        <div class="preview-header">Interactive Quiz</div>
        <div class="progress-step">
          <span class="step">1</span><span class="step">2</span><span class="step current">3</span><span class="step">4</span><span class="step">5</span>
        </div>
        <div class="preview-question">Apa arti bahasa Indonesia dari kata "<strong>Study</strong>"?</div>
        <div class="preview-options">
          <label class="preview-option"><input type="radio" name="q1" disabled> A. Belajar</label>
          <label class="preview-option"><input type="radio" name="q1" disabled> B. Tidur</label>
          <label class="preview-option"><input type="radio" name="q1" disabled> C. Makan</label>
          <label class="preview-option"><input type="radio" name="q1" disabled> D. Minum</label>
        </div>
        <div class="preview-footer">4 format soal · Acak per siswa</div>
      </div>
    </div>
  </div>
</section>

<hr class="section-divider">

<section class="features">
  <div class="section-inner">
    <h2 class="section-title">Fitur Utama</h2>
    <p class="section-sub">Semua fitur dirancang untuk pembelajaran bahasa Inggris yang nyaman dan terukur.</p>
    <div class="feature-grid">
      <div class="feature-card">
        <div class="feature-icon">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"></path><path d="M2 17l10 5 10-5"></path><path d="M2 12l10 5 10-5"></path></svg>
        </div>
        <h3>Vocabulary Learning</h3>
        <p>Perluas kosakata bahasa Inggrismu secara bertahap dengan 500 kata dari modul asli.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9 L9 3l-3 9H2"></path></svg>
        </div>
        <h3>Interactive Quiz</h3>
        <p>Uji pemahaman melalui kuis pilihan ganda dengan timer yang dipaksa oleh server dan randomisasi soal.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
        </div>
        <h3>English Night</h3>
        <p>Praktikkan kemampuan bahasa Inggris bersama komunitas belajar dalam sesi quiz bersama.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 10V3L4 14h7v7l9-11h-9z"></path></svg>
        </div>
        <h3>Learning Progress</h3>
        <p>Lihat histori kuis, total XP, level, dan leaderboard berdasarkan aktivitas nyata.</p>
      </div>
    </div>
  </div>
</section>

<hr class="section-divider">

<section class="how-it-works">
  <div class="section-inner">
    <h2 class="section-title">Bagaimana Cara Kerja</h2>
    <p class="section-sub">Mulai belajar bahasa Inggris dalam empat langkah sederhana.</p>
    <div class="steps-grid">
      <div class="step-card">
        <div class="step-number">01</div>
        <h3>Create your account</h3>
        <p>Daftar akun EnglishHub sebagai siswa.</p>
      </div>
      <div class="step-card">
        <div class="step-number">02</div>
        <h3>Start learning</h3>
        <p>Pelajari vocabulary dan ikuti quiz interaktif dengan rentang kosakata yang dipilih.</p>
      </div>
      <div class="step-card">
        <div class="step-number">03</div>
        <h3>Join English Night</h3>
        <p>Masukkan kode sesi dan praktikkan bahasa Inggris bersama komunitas belajar.</p>
      </div>
      <div class="step-card">
        <div class="step-number">04</div>
        <h3>Track your progress</h3>
        <p>Pantau total XP, level, dan leaderboard berdasarkan aktivitas belajar nyata.</p>
      </div>
    </div>
  </div>
</section>

<hr class="section-divider">

<section class="final-cta">
  <div class="section-inner">
    <h2 class="section-title">Ready to improve your English?</h2>
    <p class="section-sub">Bergabunglah dengan EnglishHub dan mulai berlatih kosakata bahasa Inggris hari ini.</p>
    <div class="cta-actions">
      <a class="button" href="<?= BASE_URL ?>/register.php">Create account</a>
      <a class="button secondary" href="<?= BASE_URL ?>/login.php">Sign in</a>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
