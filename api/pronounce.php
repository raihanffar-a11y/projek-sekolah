<?php
/**
 * EnglishHub — Pronunciation Assessment Proxy
 * Backend-only: API key Azure Speech tidak pernah bocor ke frontend.
 *
 * Menerima: POST { audio: base64 WAV/PCM, word: kata referensi }
 * Mengembalikan: JSON { accuracy_score, fluency_score, completeness_score, prosody_score, error }
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/config.php';
require_role('student');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method tidak diizinkan.']);
    exit;
}

verify_csrf();

if (!azure_pronunciation_available()) {
    http_response_code(503);
    echo json_encode([
        'error' => 'Layanan pronunciation assessment belum dikonfigurasi. Silakan hubungi administrator.',
    ]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['audio'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Data audio tidak diterima.']);
    exit;
}

$audioBase64 = $input['audio'];
$referenceText = trim($input['word'] ?? '');

// Audio harus berupa base64 WAV/PCM (16kHz mono)
// Hapus data URL prefix jika ada (data:audio/wav;base64,...)
if (strpos($audioBase64, ',') !== false) {
    $parts = explode(',', $audioBase64, 2);
    $audioBase64 = $parts[1];
}

$audioBinary = base64_decode($audioBase64);
if ($audioBinary === false || strlen($audioBinary) < 100) {
    http_response_code(400);
    echo json_encode(['error' => 'File audio tidak valid atau terlalu pendek.']);
    exit;
}

// Siapkan konfigurasi Pronunciation Assessment (base64 JSON)
$pronunciationConfig = [
    'ReferenceText' => $referenceText,
    'GradingSystem' => 'HundredMark',
    'Granularity' => 'Phoneme',
    'EnableMiscue' => true,
];
$pronunciationConfigJson = json_encode($pronunciationConfig);
$pronunciationConfigBase64 = base64_encode($pronunciationConfigJson);

// Endpoint Azure Speech-to-Text dengan Pronunciation Assessment
$locale = AZURE_SPEECH_LOCALE;
$url = "https://" . AZURE_SPEECH_REGION . ".stt.speech.microsoft.com/speech/recognition/conversation/cognitiveservices/v1"
    . "?language=" . urlencode($locale)
    . "&format=detailed";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $audioBinary);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Ocp-Apim-Subscription-Key: ' . AZURE_SPEECH_KEY,
    'Content-Type: audio/wav; codecs=audio/pcm; samplerate=16000',
    'Accept: application/json',
    'Pronunciation-Assessment-Configuration: ' . $pronunciationConfigBase64,
    'Pronunciation-Assessment-Context: ' . base64_encode(json_encode(['ReferenceText' => $referenceText])),
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError || $response === false) {
    http_response_code(502);
    echo json_encode([
        'error' => 'Gagal menghubungi layanan pronunciation assessment.',
        'detail' => $curlError ?: 'Empty response',
    ]);
    exit;
}

$data = json_decode($response, true);

if (!$data || !isset($data['NBest'])) {
    http_response_code(502);
    echo json_encode([
        'error' => 'Respons layanan tidak valid.',
        'raw' => $response,
    ]);
    exit;
}

// Ekstrak skor pronunciation dari NBest[0]
$nBest = $data['NBest'][0] ?? [];
$pronunciation = $nBest['PronunciationAssessment'] ?? [];

$result = [
    'accuracy_score' => $pronunciation['AccuracyScore'] ?? null,
    'fluency_score' => $pronunciation['FluencyScore'] ?? null,
    'completeness_score' => $pronunciation['CompletenessScore'] ?? null,
    'prosody_score' => $pronunciation['ProsodyScore'] ?? null,
    'recognized_text' => $nBest['Display'] ?? $nBest['Lexical'] ?? null,
    'reference_text' => $referenceText,
    'success' => true,
];

echo json_encode($result, JSON_UNESCAPED_UNICODE);