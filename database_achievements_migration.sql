-- EnglishHub Achievements Migration
-- Tabel untuk sistem achievement / badges siswa
-- Tidak menghapus atau mengubah tabel yang sudah ada

USE englishhub;

CREATE TABLE IF NOT EXISTS achievements (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE COMMENT 'Kode unik achievement, contoh: first_quiz, perfect_score, etc.',
  name VARCHAR(100) NOT NULL COMMENT 'Nama achievement yang ditampilkan',
  description VARCHAR(255) NULL COMMENT 'Deskripsi singkat achievement',
  icon VARCHAR(50) NULL COMMENT 'Nama ikon atau emoji representasi',
  condition_type ENUM('quiz_count','perfect_score','high_score','total_xp','streak') NOT NULL COMMENT 'Tipe kondisi pencapaian',
  condition_value INT NOT NULL COMMENT 'Nilai ambang batas kondisi',
  points_reward INT NOT NULL DEFAULT 0 COMMENT 'Bonus XP yang diberikan saat unlock',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_code (code),
  INDEX idx_condition (condition_type, condition_value)
) ENGINE=InnoDB COMMENT='Daftar achievement / badge yang tersedia';

CREATE TABLE IF NOT EXISTS user_achievements (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  achievement_id BIGINT UNSIGNED NOT NULL,
  unlocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ua_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_ua_achievement FOREIGN KEY (achievement_id) REFERENCES achievements(id) ON DELETE CASCADE,
  UNIQUE KEY unique_user_achievement (user_id, achievement_id),
  INDEX idx_user_id (user_id)
) ENGINE=InnoDB COMMENT='Riwayat achievement yang telah dibuka siswa';

-- Data achievement default (tidak dummy, berdasarkan aturan nyata)
INSERT IGNORE INTO achievements (code, name, description, icon, condition_type, condition_value, points_reward) VALUES
('first_quiz', 'Kuis Pertama', 'Selesaikan kuis pertama Anda', '🎯', 'quiz_count', 1, 10),
('quiz_5', 'Penggiat Kuis', 'Selesaikan 5 kuis', '📚', 'quiz_count', 5, 25),
('quiz_10', 'Elite Kuis', 'Selesaikan 10 kuis', '🏆', 'quiz_count', 10, 50),
('perfect_score', 'Sempurna', 'Dapatkan skor sempurna 100%', '⭐', 'perfect_score', 1, 50),
('high_score_90', 'Skor Tinggi', 'Dapatkan skor minimal 90%', '💯', 'high_score', 90, 30),
('xp_500', 'Pahlawan 500 XP', 'Kumpulkan 500 XP', '🚀', 'total_xp', 500, 0),
('xp_1000', 'Legenda 1000 XP', 'Kumpulkan 1000 XP', '🌟', 'total_xp', 1000, 0);