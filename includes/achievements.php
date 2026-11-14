<?php
/**
 * EnglishHub Achievement Helper
 * Sistem pencapaian / badges berbasis aktivitas nyata siswa.
 */
require_once __DIR__ . '/auth.php';

/**
 * Cek dan berikan achievement yang baru dibuka setelah siswa menyelesaikan kuis.
 * Dipanggil setelah attempt disubmit dan skor disimpan.
 *
 * @param int $userId ID siswa
 * @param int $score Skor yang diperoleh
 * @param int $maxScore Skor maksimal
 * @param int $totalQuiz Total kuis yang telah diselesaikan (termasuk yang baru)
 * @param float $totalXp Total XP terkini
 * @return array Daftar kode achievement yang baru dibuka
 */
function award_achievements(int $userId, int $score, int $maxScore, int $totalQuiz, float $totalXp): array {
    $pdo = db();
    $newlyUnlocked = [];

    // Ambil semua achievement aktif yang belum dibuka user ini
    $stmt = $pdo->prepare("
        SELECT a.id, a.code, a.name, a.condition_type, a.condition_value, a.points_reward
        FROM achievements a
        LEFT JOIN user_achievements ua ON ua.achievement_id = a.id AND ua.user_id = ?
        WHERE a.is_active = 1 AND ua.id IS NULL
    ");
    $stmt->execute([$userId]);
    $candidates = $stmt->fetchAll();

    foreach ($candidates as $ach) {
        $unlock = false;

        switch ($ach['condition_type']) {
            case 'quiz_count':
                $unlock = $totalQuiz >= (int)$ach['condition_value'];
                break;
            case 'perfect_score':
                $unlock = ($maxScore > 0 && $score >= $maxScore);
                break;
            case 'high_score':
                $unlock = ($maxScore > 0 && ($score / $maxScore) * 100 >= (int)$ach['condition_value']);
                break;
            case 'total_xp':
                $unlock = $totalXp >= (int)$ach['condition_value'];
                break;
            case 'streak':
                // Streak akan diimplementasikan di versi mendatang
                $unlock = false;
                break;
        }

        if ($unlock) {
            // Simpan ke user_achievements
            $ins = $pdo->prepare("
                INSERT INTO user_achievements (user_id, achievement_id)
                VALUES (?, ?)
            ");
            $ins->execute([$userId, (int)$ach['id']]);

            // Berikan bonus XP jika ada
            if ((int)$ach['points_reward'] > 0) {
                $pdo->prepare("
                    INSERT INTO xp_history (user_id, points, reason)
                    VALUES (?, ?, ?)
                ")->execute([$userId, (int)$ach['points_reward'], 'Unlocked achievement: ' . $ach['name']]);
            }

            $newlyUnlocked[] = [
                'code' => $ach['code'],
                'name' => $ach['name'],
                'description' => $ach['description'] ?? '',
                'icon' => $ach['icon'] ?? '🏅',
                'points_reward' => (int)$ach['points_reward'],
            ];
        }
    }

    return $newlyUnlocked;
}

/**
 * Ambil semua achievement yang telah dibuka oleh user, beserta yang belum dibuka.
 */
function get_user_achievements(int $userId): array {
    $pdo = db();

    // Achievement yang sudah dibuka
    $unlocked = [];
    $stmt = $pdo->prepare("
        SELECT a.code, a.name, a.description, a.icon, ua.unlocked_at
        FROM user_achievements ua
        JOIN achievements a ON a.id = ua.achievement_id
        WHERE ua.user_id = ?
        ORDER BY ua.unlocked_at DESC
    ");
    $stmt->execute([$userId]);
    foreach ($stmt->fetchAll() as $row) {
        $unlocked[] = $row;
    }

    // Achievement yang belum dibuka
    $locked = [];
    $stmt = $pdo->prepare("
        SELECT a.code, a.name, a.description, a.icon, a.condition_type, a.condition_value
        FROM achievements a
        LEFT JOIN user_achievements ua ON ua.achievement_id = a.id AND ua.user_id = ?
        WHERE a.is_active = 1 AND ua.id IS NULL
        ORDER BY a.condition_type, a.condition_value
    ");
    $stmt->execute([$userId]);
    foreach ($stmt->fetchAll() as $row) {
        $locked[] = $row;
    }

    return ['unlocked' => $unlocked, 'locked' => $locked];
}