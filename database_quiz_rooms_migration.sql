-- EnglishHub Quiz Rooms Migration
-- Tabel untuk quiz room berbasis rentang kosakata
-- Tidak menghapus atau mengubah tabel yang sudah ada

USE englishhub;

CREATE TABLE IF NOT EXISTS quiz_rooms (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(150) NOT NULL,
  start_number INT NOT NULL,
  end_number INT NOT NULL,
  question_count INT NOT NULL,
  random_questions TINYINT(1) NOT NULL DEFAULT 0,
  random_answers TINYINT(1) NOT NULL DEFAULT 0,
  duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 15,
  start_at DATETIME NULL,
  end_at DATETIME NULL,
  status ENUM('open','closed') NOT NULL DEFAULT 'open',
  session_code VARCHAR(12) NOT NULL UNIQUE,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_qr_creator FOREIGN KEY (created_by) REFERENCES users(id),
  INDEX idx_session_code (session_code),
  INDEX idx_status (status),
  INDEX idx_created_by (created_by)
) ENGINE=InnoDB COMMENT='Quiz room berbasis rentang kosakata';

-- Per-attempt generated questions (untuk randomisasi yang konsisten)
CREATE TABLE IF NOT EXISTS attempt_quiz_questions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  attempt_id BIGINT UNSIGNED NOT NULL,
  vocab_number INT NOT NULL,
  position INT NOT NULL,
  format_type ENUM('english_to_indonesian','indonesian_to_english','context','fill_blank') NOT NULL,
  question_text TEXT NOT NULL,
  correct_answer VARCHAR(255) NOT NULL,
  option_a VARCHAR(255) NOT NULL,
  option_b VARCHAR(255) NOT NULL,
  option_c VARCHAR(255) NOT NULL,
  option_d VARCHAR(255) NOT NULL,
  correct_option ENUM('A','B','C','D') NOT NULL,
  CONSTRAINT fk_aq_attempt FOREIGN KEY (attempt_id) REFERENCES attempts(id) ON DELETE CASCADE,
  INDEX idx_attempt_id (attempt_id),
  INDEX idx_position (position)
) ENGINE=InnoDB COMMENT='Soal yang dihasilkan per attempt untuk quiz room';

-- Tambahkan quiz_room_id ke attempts (nullable, untuk quiz room)
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = 'englishhub' AND table_name = 'attempts' AND column_name = 'quiz_room_id'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE attempts ADD COLUMN quiz_room_id BIGINT UNSIGNED NULL AFTER session_id, ADD CONSTRAINT fk_attempt_qr FOREIGN KEY (quiz_room_id) REFERENCES quiz_rooms(id) ON DELETE CASCADE',
  'SELECT "quiz_room_id already exists"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;