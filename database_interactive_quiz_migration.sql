-- EnglishHub interactive quiz platform migration
-- Additive migration; back up the database before applying in production.
USE englishhub;

ALTER TABLE users
  MODIFY role ENUM('admin', 'teacher', 'student') NOT NULL DEFAULT 'student';

ALTER TABLE quizzes
  ADD COLUMN difficulty ENUM('beginner', 'intermediate', 'advanced') NOT NULL DEFAULT 'beginner' AFTER description,
  ADD COLUMN visibility ENUM('private', 'public') NOT NULL DEFAULT 'private' AFTER status,
  ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
  ADD COLUMN published_at DATETIME NULL AFTER updated_at,
  ADD INDEX idx_quizzes_creator_status (created_by, status),
  ADD INDEX idx_quizzes_visibility_status (visibility, status);

ALTER TABLE questions
  MODIFY points DECIMAL(7,2) NOT NULL DEFAULT 100.00,
  ADD COLUMN explanation TEXT NULL AFTER question_text,
  ADD COLUMN image_path VARCHAR(255) NULL AFTER explanation,
  ADD COLUMN duration_seconds SMALLINT UNSIGNED NOT NULL DEFAULT 30 AFTER points;

ALTER TABLE vocabularies
  ADD COLUMN category_id BIGINT UNSIGNED NULL AFTER meaning_id,
  ADD INDEX idx_vocab_category_active (category_id, is_active);

UPDATE vocabularies v
JOIN categories c ON c.name = 'Vocabulary'
SET v.category_id = c.id
WHERE v.category_id IS NULL;

ALTER TABLE sessions
  ADD COLUMN mode ENUM('assignment', 'live') NOT NULL DEFAULT 'assignment' AFTER quiz_id,
  ADD COLUMN host_id BIGINT UNSIGNED NULL AFTER quiz_id,
  ADD COLUMN live_status ENUM('waiting', 'running', 'finished') NULL DEFAULT NULL AFTER status,
  ADD COLUMN current_question_position INT UNSIGNED NOT NULL DEFAULT 0 AFTER live_status,
  ADD COLUMN question_started_at DATETIME NULL AFTER current_question_position,
  ADD COLUMN question_deadline_at DATETIME NULL AFTER question_started_at,
  ADD INDEX idx_sessions_host_mode (host_id, mode),
  ADD INDEX idx_sessions_status_mode (status, mode);

ALTER TABLE attempts
  ADD COLUMN correct_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER max_score,
  ADD COLUMN incorrect_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER correct_count,
  ADD COLUMN unanswered_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER incorrect_count,
  ADD COLUMN accuracy_percent DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER unanswered_count,
  ADD COLUMN completion_seconds INT UNSIGNED NULL AFTER accuracy_percent;

ALTER TABLE answers
  ADD COLUMN answered_at DATETIME NULL AFTER awarded_points;

UPDATE questions SET points = 100.00;
