-- Jalankan sekali di database (phpMyAdmin > tab SQL)
-- Satu baris = nilai Pengetahuan dan Keterampilan satu siswa pada satu mata pelajaran.
CREATE TABLE IF NOT EXISTS nilai (
  id           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  siswa_id     INT           NOT NULL,
  mapel_id     INT           NOT NULL,
  pengetahuan  DECIMAL(5,2)  NULL,
  keterampilan DECIMAL(5,2)  NULL,
  updated_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_siswa_mapel (siswa_id, mapel_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Jika sebelumnya sudah menjalankan versi lama (tabel nilai dengan kolom "nilai"),
-- jalankan baris di bawah ini sekali (hapus tanda -- di depannya), jangan jalankan CREATE di atas:
-- ALTER TABLE nilai CHANGE nilai pengetahuan DECIMAL(5,2) NULL,
--                   ADD COLUMN keterampilan DECIMAL(5,2) NULL AFTER pengetahuan;
