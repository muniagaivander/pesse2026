CREATE TABLE IF NOT EXISTS data_check_uploads (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  jenis VARCHAR(24) NOT NULL,
  tanggal DATE NOT NULL,
  filename VARCHAR(255) NOT NULL,
  row_count INT UNSIGNED NOT NULL,
  uploaded_by INT NOT NULL,
  uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_check_upload_date (jenis, tanggal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS data_check_rows (
  jenis VARCHAR(24) NOT NULL,
  tanggal DATE NOT NULL,
  subsls_id VARCHAR(20) NOT NULL,
  counts_json JSON NOT NULL,
  upload_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (jenis, tanggal, subsls_id),
  INDEX idx_check_subsls (subsls_id),
  CONSTRAINT fk_check_upload FOREIGN KEY (upload_id) REFERENCES data_check_uploads(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
