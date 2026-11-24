<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/achievements.php';
require_once __DIR__ . '/config/config.php';
require_role('student');

$pdo = db();

$pronounceEnabled = azure_pronunciation_available();

$stmt = $pdo->query("SELECT vocab_number, word_en, meaning_id FROM vocabularies WHERE is_active=1 ORDER BY RAND() LIMIT 10");
$vocabs = $stmt->fetchAll();

$pageTitle = 'Pronunciation Training';
require __DIR__ . '/includes/header.php';
?>
<div class="eyebrow">Pronunciation Training</div>
<h1>Latihan Pengucapan</h1>
<p class="muted">Latih pelafalan bahasa Inggris dengan fitur listen dan pronunciation checker.</p>

<div class="grid">
  <div class="grid-item" style="min-width:300px;">
    <div class="card">
      <h2>🔊 Listen</h2>
      <p class="muted">Klik tombol speaker untuk mendengarkan pelafalan kata bahasa Inggris.</p>
      <div id="listenWords">
        <?php foreach ($vocabs as $v): ?>
          <div class="listen-item">
            <button type="button" class="speaker-btn" data-word="<?= e($v['word_en']) ?>" aria-label="Dengarkan <?= e($v['word_en']) ?>">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 5L6 9l2 4v9l5-5z"></path><path d="M19 12l-5 5V7l5 5z"></path></svg>
            </button>
            <span class="listen-word"><?= e($v['word_en']) ?></span>
            <span class="listen-meaning"><?= e($v['meaning_id']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<div class="grid">
  <div class="grid-item" style="min-width:300px;">
    <div class="card">
      <h2>🎤 Pronunciation Checker</h2>
      <p class="muted">Ucapkan kata yang ditampilkan, sistem akan mengevaluasi pengucapan Anda.</p>

      <div class="pronounce-status" id="pronounceStatus">Siap. Klik microphone untuk mulai.</div>

      <div id="wordToPronounce" class="word-display" style="margin-bottom:16px; display:none;">
        <span class="word-target">Kata target:</span>
        <span class="word-text">—</span>
      </div>

      <button type="button" id="micBtn" class="button full" aria-label="Mulai pengucapan">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"></path><path d="M19 10v2a7 7 0 0 1-14 0v-2"></path><line x1="12" y1="19" x2="12" y2="23"></line><line x1="8" y1="23" x2="16" y2="23"></line></svg>
      </button>

      <div id="pronounceResult" class="pronounce-result" style="margin-top:16px; display:none;"></div>
      <div id="pronounceInfo" class="muted" style="margin-top:8px;font-size:13px;"></div>
    </div>
  </div>
</div>

<div class="admin-info">
  <strong>Catatan:</strong> Fitur Listen menggunakan SpeechSynthesis API browser (gratis, tidak perlu API key).
  <?php if ($pronounceEnabled): ?>
    Fitur Pronunciation Checker menggunakan Azure AI Speech Pronunciation Assessment (backend, API key aman).
  <?php else: ?>
    Fitur Pronunciation Checker memerlukan konfigurasi Azure AI Speech API key di config/config.php.
    Saat ini tersedia fallback speech-to-text browser (tidak memberikan skor pronunciation asli).
  <?php endif; ?>
</div>
<script>
// Listen - SpeechSynthesis API
document.querySelectorAll('.speaker-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const word = btn.getAttribute('data-word');
    if (!('speechSynthesis' in window)) {
      alert('Browser Anda tidak mendukung SpeechSynthesis API.');
      return;
    }
    window.speechSynthesis.cancel();
    const utterance = new SpeechSynthesisUtterance(word);
    utterance.lang = 'en-US';
    utterance.rate = 0.9;
    window.speechSynthesis.speak(utterance);
  });
});

// Pronunciation Checker - Azure AI Speech integration
const micBtn = document.getElementById('micBtn');
const pronounceStatus = document.getElementById('pronounceStatus');
const pronounceResult = document.getElementById('pronounceResult');
const pronounceInfo = document.getElementById('pronounceInfo');
const wordDisplay = document.getElementById('wordToPronounce');
const wordText = wordDisplay.querySelector('.word-text');

// Jalur Azure dimatikan di sisi JS sampai startMediaRecorder(), sendToAzurePronunciation() dan api/pronounce.php tersedia.
const PRONOUNCE_ENABLED = false;
const PRONOUNCE_API = '<?= BASE_URL ?>/api/pronounce.php';

let isRecording = false;
let recognition = null;
let mediaRecorder = null;
let audioChunks = [];
let currentTargetWord = '';

// Pilih kata target acak dari daftar kosakata
const availableWords = <?= json_encode(array_column($vocabs, 'word_en'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

function pickRandomWord() {
  if (availableWords.length === 0) return 'hello';
  return availableWords[Math.floor(Math.random() * availableWords.length)];
}

function setTargetWord(word) {
  currentTargetWord = word;
  wordText.textContent = word;
  wordDisplay.style.display = 'block';
}

if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
  const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
  recognition = new SpeechRecognition();
  recognition.lang = 'en-US';
  recognition.interimResults = false;
  recognition.maxAlternatives = 1;

  recognition.onstart = () => {
    isRecording = true;
    micBtn.classList.add('recording');
    micBtn.innerHTML = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10" stroke-linecap="round" stroke-linejoin="round"></circle></svg> Listening...';
    pronounceStatus.textContent = 'Mendengarkan... ucapkan kata sekarang.';
    pronounceStatus.style.color = '#80827F';
    pronounceResult.innerHTML = '';
    pronounceInfo.textContent = '';
    micBtn.disabled = true;
  };

  recognition.onresult = (event) => {
    const transcript = event.results[0][0].transcript;
    const heard = transcript.toLowerCase().replace(/[^a-z' ]/g, '').trim();
    const target = currentTargetWord.toLowerCase().trim();
    const ok = heard === target;
    pronounceResult.innerHTML = '<strong>Terdengar:</strong> ' + e(transcript) + '<br>' +
      (ok ? '✅ Pengucapan terdeteksi benar!' : '❌ Belum cocok dengan "' + e(currentTargetWord) + '". Coba lagi.');
    pronounceResult.style.display = 'block';
    pronounceInfo.textContent = 'Catatan: ini speech-to-text browser (bukan penilaian pronunciation). Skor akurat butuh Azure AI Speech.';
    if (ok) setTimeout(() => setTargetWord(pickRandomWord()), 1500);
  };

  recognition.onend = () => {
    isRecording = false;
    micBtn.classList.remove('recording');
    micBtn.disabled = false;
    micBtn.innerHTML = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"></path><path d="M19 10v2a7 7 0 0 1-14 0v-2"></path><line x1="12" y1="19" x2="12" y2="23"></line><line x1="8" y1="23" x2="16" y2="23"></line></svg>';
    pronounceStatus.textContent = 'Siap. Klik microphone untuk mulai.';
  };

  recognition.onerror = () => {
    isRecording = false;
    micBtn.classList.remove('recording');
    micBtn.disabled = false;
    micBtn.innerHTML = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"></path><path d="M19 10v2a7 7 0 0 1-14 0v-2"></path><line x1="12" y1="19" x2="12" y2="23"></line><line x1="8" y1="23" x2="16" y2="23"></line></svg>';
    pronounceStatus.textContent = 'Gagal mengakses mikrofon.';
  };

  micBtn.addEventListener('click', () => {
    if (isRecording) {
      if (mediaRecorder && mediaRecorder.state !== 'inactive') {
        mediaRecorder.stop();
      } else if (recognition) {
        recognition.stop();
      }
    } else {
      pronounceResult.style.display = 'none';
      pronounceInfo.textContent = '';

      if (PRONOUNCE_ENABLED) {
        startMediaRecorder();
      } else if (recognition) {
        recognition.start();
      }
    }
  });

  setTargetWord(pickRandomWord());
} else {
  setTargetWord(pickRandomWord());
  micBtn.disabled = true;
  micBtn.textContent = 'Pronunciation checker tidak tersedia';
  pronounceStatus.textContent = 'Browser Anda tidak mendukung SpeechRecognition (coba Chrome atau Edge).';
  pronounceInfo.textContent = 'Fitur pronunciation checker tidak tersedia.';
}

function e(s) {
  return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>