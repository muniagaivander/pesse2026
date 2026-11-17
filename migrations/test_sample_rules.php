<?php
if(PHP_SAPI!=='cli') exit;
require __DIR__.'/../pes_master.php';
function check($ok,$message) {if(!$ok) throw new RuntimeException($message);echo "PASS $message\n";}
check((int)db()->query("SELECT COUNT(*) FROM master_subsls WHERE status_sampel='Ya'")->fetchColumn()===160,'160 sample');
check((int)db()->query('SELECT COUNT(*) FROM pes_sample_kab')->fetchColumn()===8,'8 districts');
foreach(['2026-10-31','2026-12-01','2026-11-31'] as $d) {try{pes_require_period($d);throw new LogicException('accepted');}catch(RuntimeException $e){check(true,'reject '.$d);}}
foreach(['2026-11-01','2026-11-30'] as $d) pes_require_period($d);
check(abs(progress_color_settings('2026-11-30')['target']-100)<0.001,'30 November target 100%');
check(progress_color_settings('2026-10-31')['target']==0,'before campaign target 0%');
check(progress_color_settings('2026-12-15')['day']===27,'fieldwork capped at 27 days');
try {pes_allocate(['role'=>'superadmin'],'6401010009000100','none@example.invalid','none2@example.invalid');throw new LogicException('non sample accepted');}catch(RuntimeException $e){check(str_contains($e->getMessage(),'bukan sampel'),'reject nonsample allocation');}
$count=(int)db()->query('SELECT COUNT(*) FROM pes_sample_daily')->fetchColumn();
db()->beginTransaction();
try {
 $q=db()->prepare('INSERT INTO daily_status(tanggal,subsls_id,kab_id,pengawas_email,pencacah_email,updated_by) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE updated_by=VALUES(updated_by)');
 foreach([['2026-10-31','6401010011000600'],['2026-12-01','6401010011000600'],['2026-11-01','6401010009000100']] as [$d,$id]) $q->execute([$d,$id,'6401','','','SAMPLE_SCOPE_TEST']);
 check((int)db()->query('SELECT COUNT(*) FROM pes_sample_daily')->fetchColumn()===$count,'nonsample and out-of-period rows excluded');
} finally {db()->rollBack();}
