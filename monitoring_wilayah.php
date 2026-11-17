<?php
// Included only by the authenticated dashboard. Reuse all role and area restrictions.
if (!isset($user, $filters) || !function_exists('dashboard_where')) { http_response_code(404); exit; }
if (!function_exists('monitoring_region_metrics')) {
function monitoring_region_metrics(int $allocation, int $done, int $draft, array $schedule): array
{
    $metrics = pes_monitor_metrics($allocation, $done, $schedule);
    $metrics['with_draft'] = $allocation > 0 ? ($done + $draft) / $allocation * 100 : null;
    $key = $metrics['progress'] === null ? null : progress_color_key($metrics['progress']);
    $metrics['color'] = $key === null ? '#64748b' : progress_color_hex($metrics['progress']);
    $metrics['status'] = ['low'=>'Kritis', 'warning'=>'Waspada', 'good'=>'Sesuai'][$key] ?? 'Belum ada target';
    return $metrics;
}
}
$monitorSchedule = pes_fieldwork_schedule();
// More specific selections take priority over their parent area filters.
if (!empty($filters['subsls_id']) || !empty($filters['pencacah_email']) || $user['role'] === 'pencacah') {
    $monitorLevel = 'subsls';
} elseif (!empty($filters['pengawas_email']) || $user['role'] === 'pengawas') {
    $monitorLevel = 'petugas';
} elseif (!empty($filters['desa_id'])) {
    $monitorLevel = 'subsls';
} elseif (!empty($filters['kec_id'])) {
    $monitorLevel = 'desa';
} elseif (!empty($filters['kab_id']) || in_array($user['role'], ['admin_kab','viewer_kab'], true)) {
    $monitorLevel = 'kecamatan';
} else {
    $monitorLevel = 'kabupaten';
}
[$monitorAreaLabel, $monitorCodeExpr, $monitorNameExpr] = match ($monitorLevel) {
    'subsls' => ['SubSLS', 'ms.id', "CONCAT(sl.nmsls, ' - Sub ', ms.kdsubsls)"],
    'petugas' => ['Petugas (PCL)', "COALESCE(ms.pencacah_email,'')", "COALESCE(ms.pencacah_email,'')"],
    'desa' => ['Desa/Kelurahan', 'd.id', 'd.nmdesa'],
    'kecamatan' => ['Kecamatan', 'kc.id', 'kc.nmkec'],
    default => ['Kabupaten/Kota', 'k.id', 'k.nmkab'],
};
[$monitorWhere, $monitorParams] = dashboard_where($user, $filters);
$monitorToday = (new DateTimeImmutable('today', new DateTimeZone('Asia/Makassar')))->format('Y-m-d');
$monitorStmt = db()->prepare("SELECT $monitorCodeExpr code, $monitorNameExpr name, COALESCE(ms.pencacah_email,'') email,
    COUNT(*) subsls, COALESCE(SUM(ss.target),0) allocation, COALESCE(SUM(ss.draft_count),0) draft,
    COUNT(td.subsls_id) today_count,
    COALESCE(SUM(CASE WHEN td.subsls_id IS NOT NULL AND prev.subsls_id IS NULL THEN 1 ELSE 0 END),0) missing_baseline,
    COALESCE(SUM(CASE WHEN td.subsls_id IS NOT NULL AND prev.subsls_id IS NOT NULL THEN
        (td.submitted_by_pencacah + td.rejected_by_pengawas + td.pending_count + td.approved_by_pengawas)
        - (prev.submitted_by_pencacah + prev.rejected_by_pengawas + prev.pending_count + prev.approved_by_pengawas)
        ELSE 0 END),0) daily_gain,
    COALESCE(SUM(ss.submitted_by_pencacah + ss.rejected_by_pengawas + ss.pending_count + ss.approved_by_pengawas),0) done
    FROM pes_sample_subsls ms
    JOIN pes_sample_sls sl ON sl.id=ms.sls_id
    JOIN pes_sample_desa d ON d.id=sl.desa_id
    JOIN pes_sample_kec kc ON kc.id=d.kec_id
    JOIN pes_sample_kab k ON k.id=kc.kab_id
    LEFT JOIN pes_sample_status ss ON ss.subsls_id=ms.id
    LEFT JOIN pes_sample_daily td ON td.subsls_id=ms.id AND td.tanggal=?
    LEFT JOIN pes_sample_daily prev ON prev.subsls_id=ms.id AND prev.tanggal=(
        SELECT MAX(history.tanggal) FROM pes_sample_daily history WHERE history.subsls_id=ms.id AND history.tanggal<?
    )
    $monitorWhere GROUP BY $monitorCodeExpr, $monitorNameExpr, ms.pencacah_email ORDER BY $monitorCodeExpr");
$monitorStmt->execute(array_merge([$monitorToday, $monitorToday], $monitorParams));
$monitorRegions = [];
foreach ($monitorStmt->fetchAll() as $r) {
    $id = $r['code'];
    if (!isset($monitorRegions[$id])) $monitorRegions[$id] = ['name'=>$r['name'], 'allocation'=>0, 'done'=>0, 'draft'=>0, 'subsls'=>0, 'today_count'=>0, 'missing_baseline'=>0, 'daily_gain'=>0, 'staff'=>[], 'critical'=>0];
    foreach (['allocation','done','draft','subsls','today_count','missing_baseline','daily_gain'] as $key) $monitorRegions[$id][$key] += (int)$r[$key];
    $r['metrics'] = monitoring_region_metrics((int)$r['allocation'], (int)$r['done'], (int)$r['draft'], $monitorSchedule);
    if ($r['email'] !== '' && $r['metrics']['status'] === 'Kritis') $monitorRegions[$id]['critical']++;
    $monitorRegions[$id]['staff'][] = $r;
}
foreach ($monitorRegions as $id => &$monitorRegion) {
    $monitorRegion['label'] = $monitorLevel === 'petugas'
        ? ($id === '' ? 'Belum dialokasikan ke PCL' : petugas_label_by_email((string)$id))
        : $id . ' - ' . $monitorRegion['name'];
    $monitorRegion['anchor'] = 'monitor-staff-' . hash('sha256', (string)$id);
}
unset($monitorRegion);
$monitorNumber = static fn($n, $digits = 1) => $n === null ? '—' : number_format($n, $digits, ',', '.');
?>
<section class="card" id="monitoring-wilayah">
  <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
    <strong>Monitoring Progres Wilayah (<?= count($monitorRegions) ?> <?= e($monitorAreaLabel) ?>)</strong>
    <div><button type="button" class="btn btn-sm btn-primary" data-monitor-view="cards" aria-pressed="true">Kartu</button> <button type="button" class="btn btn-sm btn-outline-primary" data-monitor-view="table" aria-pressed="false">Tabel Ringkasan</button></div>
  </div>
  <div class="card-body">
    <p class="mb-1"><strong>Target ideal H-<?= $monitorSchedule['day'] ?>: <?= $monitorNumber($monitorSchedule['target'], 2) ?>%</strong> · Batas pendataan 27 November 2026 · <?= $monitorSchedule['remaining'] ?> hari tersisa</p>
    <p class="text-muted small">Progres = (Submit + Reject + Pending + Approve) / alokasi. Progress + Draft = (Submit + Reject + Pending + Approve + Draft) / alokasi. Data mengikuti pembaruan terakhir dan filter dashboard.</p>
    <p class="small">Warna mengikuti progres pada peta: <?php foreach (progress_color_ranges() as $range): ?><span class="mr-2"><span style="color:<?= e($range['color']) ?>">●</span> <?= e($range['label']) ?></span><?php endforeach; ?></p>
    <?php if (!$monitorRegions): ?><p>Tidak ada wilayah sesuai filter.</p><?php endif; ?>
    <div class="row" data-monitor-panel="cards">
    <?php foreach ($monitorRegions as $id => $region): $m = monitoring_region_metrics($region['allocation'], $region['done'], $region['draft'], $monitorSchedule); ?>
      <div class="col-xl-3 col-md-6 mb-3"><article class="border rounded p-3 h-100" style="border-top:4px solid <?= $m['color'] ?> !important">
        <strong><?= e($region['label']) ?></strong>
        <div class="small text-muted mb-2 d-flex flex-wrap align-items-center" style="gap:6px">
          <span><?= count(array_filter($region['staff'], fn($s) => $s['email'] !== '')) ?> PCL · <?= $region['subsls'] ?> SubSLS</span>
          <?php $gainAvailable = $region['today_count'] > 0 && $region['missing_baseline'] === 0;
            $gainTooltip = !$region['today_count'] ? 'Belum ada data hari ini' : ($region['missing_baseline'] ? 'Data pembanding sebelum hari ini belum tersedia' : 'Perubahan realisasi hari ini dibanding snapshot terakhir sebelum hari ini; ' . $region['today_count'] . ' dari ' . $region['subsls'] . ' SubSLS diperbarui hari ini'); ?>
          <span class="px-2 py-1 rounded font-weight-bold" style="background:#dcfce7;color:#166534;border:1px solid #86efac" title="<?= e($gainTooltip) ?>">
            <i class="fas fa-chart-line mr-1" aria-hidden="true"></i><?= $gainAvailable ? ($region['daily_gain'] >= 0 ? '+' : '−') . $monitorNumber(abs($region['daily_gain']),0) : '—' ?> hari ini
          </span>
        </div>
        <?php $monitorGap = $m['progress'] === null ? null : round($m['progress'] - $monitorSchedule['target'], 2); ?>
        <div class="d-flex flex-wrap justify-content-between"><span>Progres pendataan</span><strong><?= $monitorNumber($m['progress'],2) ?>% <span title="Selisih terhadap target ideal hari ini (poin persentase)">(<?= $monitorGap === null ? '—' : ($monitorGap > 0 ? '+' : ($monitorGap < 0 ? '−' : '')) . $monitorNumber(abs($monitorGap),2) ?>%)</span></strong></div>
        <div class="position-relative my-2" title="Target hari ini: <?= $monitorNumber($monitorSchedule['target'],2) ?>%">
          <div class="progress" style="height:10px" aria-label="Progres pendataan"><div class="progress-bar" style="width:<?= max(0,min(100,$m['progress'] ?? 0)) ?>%;background:<?= $m['color'] ?>"></div></div>
          <span role="img" aria-label="Target hari ini <?= $monitorNumber($monitorSchedule['target'],2) ?> persen" style="position:absolute;left:<?= max(0,min(100,$monitorSchedule['target'])) ?>%;top:-3px;bottom:-3px;width:2px;background:#000;transform:translateX(-50%);pointer-events:none"></span>
        </div>
        <div class="mt-2">Progress + Draft: <strong style="color:<?= $m['color'] ?>"><?= $monitorNumber($m['with_draft'],2) ?>%</strong></div>
        <div>Realisasi / alokasi: <strong><?= $monitorNumber($region['done'],0) ?> / <?= $monitorNumber($region['allocation'],0) ?></strong></div>
        <div>Beban sisa: <strong><?= $m['remaining'] === 0 ? '0' : ($m['per_day'] === null ? 'Tenggat berakhir' : $monitorNumber($m['per_day']).'/hari') ?></strong></div>
        <a class="btn btn-sm btn-danger mt-2" href="#<?= e($region['anchor']) ?>" data-monitor-staff="<?= e($region['anchor']) ?>"><?= $region['critical'] ?> PCL kritis · Lihat petugas</a>
      </article></div>
    <?php endforeach; ?>
    </div>
    <div class="table-responsive" data-monitor-panel="table" hidden><table class="table table-bordered table-sm">
      <thead><tr><th><?= e($monitorAreaLabel) ?></th><th>Alokasi</th><th>Target H-<?= $monitorSchedule['day'] ?></th><th>Realisasi</th><th>Progres</th><th>Progress + Draft</th><th>Status</th><th>Sisa/hari</th></tr></thead><tbody>
      <?php foreach ($monitorRegions as $id=>$region): $m=monitoring_region_metrics($region['allocation'],$region['done'],$region['draft'],$monitorSchedule); ?>
        <tr><td><a href="#<?= e($region['anchor']) ?>" data-monitor-staff="<?= e($region['anchor']) ?>"><?= e($region['label']) ?></a></td><td><?= $monitorNumber($region['allocation'],0) ?></td><td><?= $monitorNumber($m['ideal']) ?></td><td><?= $monitorNumber($region['done'],0) ?></td><td><?= $monitorNumber($m['progress'],2) ?>%</td><td><?= $monitorNumber($m['with_draft'],2) ?>%</td><td style="color:<?= $m['color'] ?>"><?= e($m['status']) ?></td><td><?= $m['remaining'] === 0 ? '0' : ($m['per_day'] === null ? 'Tenggat berakhir' : $monitorNumber($m['per_day'])) ?></td></tr>
      <?php endforeach; ?></tbody></table></div>
    <?php foreach ($monitorRegions as $id=>$region): usort($region['staff'], fn($a,$b)=>($a['metrics']['achievement'] ?? -1)<=>($b['metrics']['achievement'] ?? -1)); ?>
    <details id="<?= e($region['anchor']) ?>" class="mt-2"><summary>Rincian PCL · <?= e($region['label']) ?></summary>
      <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Petugas</th><th>SubSLS</th><th>Alokasi</th><th>Progress</th><th>Draft</th><th>Progress + Draft</th><th>Status</th></tr></thead><tbody>
      <?php foreach ($region['staff'] as $staff): ?>
        <tr>
          <td><?= e($staff['email'] === '' ? 'Belum dialokasikan ke PCL' : petugas_label_by_email($staff['email'])) ?></td>
          <td><?= $staff['subsls'] ?></td>
          <td><?= $monitorNumber($staff['allocation'],0) ?></td>
          <td><?= $monitorNumber($staff['done'],0) ?> (<?= $monitorNumber($staff['metrics']['progress'],2) ?>%)</td>
          <td><?= $monitorNumber($staff['draft'],0) ?> (<?= $monitorNumber($staff['allocation'] > 0 ? $staff['draft'] / $staff['allocation'] * 100 : null,2) ?>%)</td>
          <td><?= $monitorNumber($staff['metrics']['with_draft'],2) ?>%</td>
          <td style="color:<?= $staff['metrics']['color'] ?>"><?= e($staff['metrics']['status']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody></table></div>
    </details><?php endforeach; ?>
  </div>
</section>
<script>
document.querySelectorAll('[data-monitor-view]').forEach(button => button.addEventListener('click', () => {
  document.querySelectorAll('[data-monitor-panel]').forEach(panel => panel.hidden = panel.dataset.monitorPanel !== button.dataset.monitorView);
  document.querySelectorAll('[data-monitor-view]').forEach(b => { const active = b === button; b.setAttribute('aria-pressed', String(active)); b.classList.toggle('btn-primary', active); b.classList.toggle('btn-outline-primary', !active); });
}));
document.querySelectorAll('[data-monitor-staff]').forEach(link => link.addEventListener('click', () => { document.getElementById(link.dataset.monitorStaff).open = true; }));
</script>
