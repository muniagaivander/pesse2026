<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../bootstrap.php';
$ids=file(__DIR__.'/sample_ids.txt',FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
$all=db()->query('SELECT id FROM master_subsls')->fetchAll(PDO::FETCH_COLUMN);
if(count($ids)!==160 || count(array_unique($ids))!==160 || array_diff($ids,$all)) throw new RuntimeException('Daftar sampel tidak valid.');
if(!db()->query("SHOW COLUMNS FROM master_subsls LIKE 'status_sampel'")->fetch()) db()->exec("ALTER TABLE master_subsls ADD status_sampel ENUM('Ya','Tidak') NOT NULL DEFAULT 'Tidak', ADD INDEX idx_status_sampel(status_sampel)");
db()->beginTransaction();
db()->exec("UPDATE master_subsls SET status_sampel='Tidak'");
$q=db()->prepare("UPDATE master_subsls SET status_sampel='Ya' WHERE id=?"); foreach($ids as $id) $q->execute([$id]); db()->commit();
$views=[
 'pes_sample_subsls'=>"SELECT * FROM master_subsls WHERE status_sampel='Ya'",
 'pes_sample_sls'=>"SELECT * FROM master_sls WHERE id IN (SELECT sls_id FROM pes_sample_subsls)",
 'pes_sample_desa'=>"SELECT * FROM master_desa WHERE id IN (SELECT desa_id FROM pes_sample_sls)",
 'pes_sample_kec'=>"SELECT * FROM master_kec WHERE id IN (SELECT kec_id FROM pes_sample_desa)",
 'pes_sample_kab'=>"SELECT * FROM master_kab WHERE id IN (SELECT kab_id FROM pes_sample_kec)",
 'pes_sample_daily'=>"SELECT * FROM daily_status WHERE tanggal BETWEEN '2026-11-01' AND '2026-11-30' AND subsls_id IN (SELECT id FROM pes_sample_subsls)",
 'pes_sample_status'=>"SELECT * FROM subsls_status WHERE subsls_id IN (SELECT id FROM pes_sample_subsls)",
 'pes_sample_completion'=>"SELECT * FROM subsls_completion_status WHERE subsls_id IN (SELECT id FROM pes_sample_subsls)"
];
$sql=''; foreach($views as $name=>$query) { $ddl="CREATE OR REPLACE SQL SECURITY INVOKER VIEW $name AS $query"; db()->exec($ddl); $sql.=$ddl.";\n"; }
file_put_contents(__DIR__.'/sample_views.sql',$sql);
echo "160 sampel aktif; view wilayah dan laporan sampel siap.\n";
