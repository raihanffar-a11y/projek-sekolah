-- EnglishHub Vocabulary Bank Migration
-- Menambahkan tabel vocabularies untuk bank 500 kosakata dari modul
-- Tidak menghapus atau mengubah tabel yang sudah ada

USE englishhub;

CREATE TABLE IF NOT EXISTS vocabularies (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vocab_number INT NOT NULL UNIQUE COMMENT 'Nomor kosakata 1-500, unik dan tidak berubah',
  word_en VARCHAR(100) NOT NULL COMMENT 'Kata bahasa Inggris',
  meaning_id VARCHAR(255) NOT NULL COMMENT 'Arti bahasa Indonesia',
  is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Status aktif/nonaktif',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_vocab_number (vocab_number),
  INDEX idx_word_en (word_en),
  INDEX idx_is_active (is_active)
) ENGINE=InnoDB COMMENT='Bank kosakata EnglishHub 500 kata dari modul';