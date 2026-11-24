-- EnglishHub learning features migration
-- Additive only: does not modify or delete existing tables or user data.

USE englishhub;

CREATE TABLE IF NOT EXISTS grammar_lessons (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(150) NOT NULL,
  summary VARCHAR(500) NOT NULL,
  formula VARCHAR(255) NOT NULL,
  example_en VARCHAR(500) NOT NULL,
  example_id VARCHAR(500) NOT NULL,
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS vocabulary_bookmarks (
  user_id BIGINT UNSIGNED NOT NULL,
  vocabulary_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, vocabulary_id),
  CONSTRAINT fk_vocab_bookmark_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_vocab_bookmark_word FOREIGN KEY (vocabulary_id) REFERENCES vocabularies(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS user_vocabulary_progress (
  user_id BIGINT UNSIGNED NOT NULL,
  vocabulary_id BIGINT UNSIGNED NOT NULL,
  practice_count INT UNSIGNED NOT NULL DEFAULT 1,
  last_studied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, vocabulary_id),
  CONSTRAINT fk_vocab_progress_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_vocab_progress_word FOREIGN KEY (vocabulary_id) REFERENCES vocabularies(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO grammar_lessons (title, summary, formula, example_en, example_id, sort_order)
SELECT 'Simple Present Tense', 'Untuk kebiasaan, fakta umum, dan jadwal tetap.',
       'S + V1 (s/es) + O', 'She studies English every day.',
       'Dia belajar bahasa Inggris setiap hari.', 1
WHERE NOT EXISTS (SELECT 1 FROM grammar_lessons WHERE title = 'Simple Present Tense');

INSERT INTO grammar_lessons (title, summary, formula, example_en, example_id, sort_order)
SELECT 'Simple Past Tense', 'Untuk tindakan yang selesai pada waktu lampau.',
       'S + V2 + O', 'I studied English yesterday.',
       'Saya belajar bahasa Inggris kemarin.', 2
WHERE NOT EXISTS (SELECT 1 FROM grammar_lessons WHERE title = 'Simple Past Tense');

INSERT INTO grammar_lessons (title, summary, formula, example_en, example_id, sort_order)
SELECT 'Simple Future Tense', 'Untuk rencana atau tindakan yang akan datang.',
       'S + will + V1 + O', 'They will practice tomorrow.',
       'Mereka akan berlatih besok.', 3
WHERE NOT EXISTS (SELECT 1 FROM grammar_lessons WHERE title = 'Simple Future Tense');

INSERT INTO grammar_lessons (title, summary, formula, example_en, example_id, sort_order)
SELECT 'Present Continuous', 'Untuk tindakan yang sedang berlangsung sekarang.',
       'S + am/is/are + V-ing', 'We are learning English now.',
       'Kami sedang belajar bahasa Inggris sekarang.', 4
WHERE NOT EXISTS (SELECT 1 FROM grammar_lessons WHERE title = 'Present Continuous');
