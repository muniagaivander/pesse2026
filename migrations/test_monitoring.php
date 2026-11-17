<?php
if (PHP_SAPI !== 'cli') exit;
require __DIR__.'/../bootstrap.php';
function verify_monitor($condition, $message) { if (!$condition) throw new RuntimeException($message); echo "PASS $message\n"; }
foreach (['2026-10-31'=>0, '2026-11-01'=>100/27, '2026-11-17'=>1700/27, '2026-11-27'=>100, '2026-11-30'=>100] as $date=>$expected) {
    verify_monitor(abs(pes_fieldwork_schedule($date)['target']-$expected)<0.00001, "target $date");
    verify_monitor(abs(progress_color_settings($date)['target']-$expected)<0.00001, "map target $date");
}
$schedule = pes_fieldwork_schedule('2026-11-17');
verify_monitor($schedule['remaining'] === 10, 'ten days remaining after 17 November');
$m = pes_monitor_metrics(2700, 1700, $schedule);
verify_monitor(abs($m['achievement']-100)<0.00001 && $m['status']==='Sesuai' && $m['per_day']===100, 'on-target pace');
verify_monitor(pes_monitor_metrics(2700,1189,$schedule)['status']==='Kritis', 'critical boundary');
verify_monitor(pes_monitor_metrics(2700,1190,$schedule)['status']==='Waspada', 'warning boundary');
verify_monitor(pes_monitor_metrics(0,0,$schedule)['achievement']===null, 'zero allocation');
verify_monitor(pes_monitor_metrics(2700,1700,pes_fieldwork_schedule('2026-11-27'))['per_day']===null, 'deadline avoids division by zero');
// Render with live read-only data and the actual dashboard scope implementation.
$source = file_get_contents(__DIR__.'/../index.php');
$start = strpos($source, 'function dashboard_where(');
$end = strpos($source, 'function dashboard_grouping(', $start);
eval(substr($source, $start, $end-$start));
foreach ([['role'=>'superadmin'], ['role'=>'admin_kab','kab_id'=>'6472']] as $user) {
    $filters = [];
    ob_start(); require __DIR__.'/../monitoring_wilayah.php'; $html = ob_get_clean();
    if ($user['role']==='superadmin') {
        verify_monitor(count($monitorRegions) === 8, 'province sees eight districts');
        $provinceSamarinda = $monitorRegions[6472];
    } else {
        $expected = db()->query("SELECT id FROM pes_sample_kec WHERE kab_id='6472' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
        verify_monitor(array_map('strval', array_keys($monitorRegions)) === array_map('strval', $expected), 'Samarinda sample kecamatan only');
        verify_monitor($monitorAreaLabel === 'Kecamatan', 'kecamatan title');
        foreach (['allocation','done','draft','subsls'] as $field) {
            verify_monitor(array_sum(array_column($monitorRegions, $field)) === $provinceSamarinda[$field], 'kecamatan totals reconcile '.$field);
        }
    }
    verify_monitor(str_contains($html, '27 November 2026'), 'render deadline');
    foreach ($monitorRegions as $region) {
        $metrics = monitoring_region_metrics($region['allocation'], $region['done'], $region['draft'], $schedule);
        if ($region['allocation'] > 0) {
            verify_monitor($metrics['color'] === progress_color_hex($region['done'] / $region['allocation'] * 100), 'card color matches map');
            verify_monitor(abs($metrics['with_draft'] - ($region['done'] + $region['draft']) / $region['allocation'] * 100) < 0.00001, 'progress includes draft');
        }
    }
    verify_monitor(!str_contains($html, 'Capaian'), 'old achievement label removed');
}

// Exercise the same GET filters used by the form and map navigation.
$sample = db()->query("SELECT ms.id subsls_id, ms.pencacah_email, ms.pengawas_email,
    d.id desa_id, kc.id kec_id, kc.kab_id
    FROM pes_sample_subsls ms JOIN pes_sample_sls sl ON sl.id=ms.sls_id
    JOIN pes_sample_desa d ON d.id=sl.desa_id JOIN pes_sample_kec kc ON kc.id=d.kec_id
    WHERE ms.pencacah_email <> '' AND ms.pengawas_email <> '' LIMIT 1")->fetch();
verify_monitor((bool)$sample, 'assigned sample available');
$user = ['role'=>'superadmin'];
foreach ([
    [['kab_id'=>$sample['kab_id']], 'kecamatan'],
    [['kab_id'=>$sample['kab_id'],'kec_id'=>$sample['kec_id']], 'desa'],
    [['desa_id'=>$sample['desa_id']], 'subsls'],
    [['kab_id'=>$sample['kab_id'],'kec_id'=>$sample['kec_id'],'pengawas_email'=>$sample['pengawas_email']], 'petugas'],
    [['pengawas_email'=>$sample['pengawas_email'],'pencacah_email'=>$sample['pencacah_email']], 'subsls'],
    [['subsls_id'=>$sample['subsls_id'],'pengawas_email'=>$sample['pengawas_email']], 'subsls'],
] as [$filters,$expectedLevel]) {
    ob_start(); require __DIR__.'/../monitoring_wilayah.php'; ob_end_clean();
    verify_monitor($monitorLevel === $expectedLevel && count($monitorRegions)>0, 'filter level '.$expectedLevel);
    if (isset($filters['subsls_id'])) verify_monitor(array_map('strval',array_keys($monitorRegions)) === [$sample['subsls_id']], 'selected SubSLS only');
    if (isset($filters['pengawas_email'])) {
        foreach ($monitorRegions as $region) foreach ($region['staff'] as $staff) {
            if (isset($filters['pencacah_email'])) verify_monitor($staff['email']===$sample['pencacah_email'], 'selected PCL only');
        }
    }
}
