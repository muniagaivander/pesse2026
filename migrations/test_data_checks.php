<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../data_check.php';
function check(bool $ok, string $label): void { if (!$ok) throw new RuntimeException($label); echo "PASS $label\n"; }
$owner = db()->query("SELECT * FROM users WHERE username='admin6400'")->fetch();
check(dc_can_upload($owner), 'owner upload');
foreach (db()->query("SELECT * FROM users WHERE username<>'admin6400'") as $u) {
    if (dc_can_upload($u)) throw new RuntimeException('nonowner upload allowed');
}
echo "PASS all other accounts denied upload\n";
$expected = ['keluarga'=>5413,'usaha_keluarga'=>2579,'usaha_bku'=>1901,'individu'=>10097];
foreach ($expected as $kind=>$sum) {
    foreach (['subsls','pcl','pml'] as $view) {
        $r = dc_display_rows($owner,$kind,['view'=>$view==='subsls'?'subsls':'petugas','petugas_type'=>$view]);
        check($r['date']==='2026-11-12' && array_sum(array_column($r['rows'],'total'))===$sum && array_sum(array_column($r['rows'],'loaded'))===160, "$kind $view totals and latest date");
    }
    $temp=tempnam(sys_get_temp_dir(),'dc_test_');
    try {
        file_put_contents($temp,dc_template_bytes($kind));
        $rows=dc_xlsx_rows($temp);
        check(count($rows)===161 && $rows[1]===dc_headers($kind), "$kind template header and sample IDs");
        try { dc_parse_upload($kind,$temp); throw new LogicException('empty counts accepted'); }
        catch (RuntimeException $e) { check(str_contains($e->getMessage(),'bilangan bulat'), "$kind blank values rejected"); }
    } finally { unlink($temp); }
}
$kab=db()->query("SELECT * FROM users WHERE username='admin6401'")->fetch();
$rows=dc_area_rows($kab,['kab_id'=>'6471']);
check(count($rows)===17 && count(array_filter($rows,fn($r)=>$r['kab_id']!=='6401'))===0,'district scope ignores forged filter');
foreach (['pengawas','pencacah'] as $role) {
    $u=db()->query("SELECT * FROM users WHERE role='$role' AND active=1 LIMIT 1")->fetch();
    $rows=dc_area_rows($u);
    check(count(array_filter($rows,fn($r)=>$r[$role.'_email']!==$u['email']))===0,"$role scoped allocation");
}
db()->beginTransaction();
try {
    db()->exec("INSERT INTO data_check_rows SELECT jenis,'2026-11-11',subsls_id,counts_json,upload_id FROM data_check_rows WHERE tanggal='2026-11-12' ON DUPLICATE KEY UPDATE counts_json=VALUES(counts_json)");
    check(dc_latest_date('keluarga')==='2026-11-12','older snapshot does not replace latest');
    $r=dc_display_rows($owner,'keluarga',[]);
    check(array_sum(array_column($r['rows'],'total'))===5413,'history is not summed');
    db()->exec("DELETE FROM data_check_rows WHERE jenis='keluarga' AND tanggal='2026-11-12'");
    check(dc_latest_date('keluarga')==='2026-11-11','fallback to last available day');
} finally { db()->rollBack(); }
echo "All checks passed; historical test changes rolled back.\n";
