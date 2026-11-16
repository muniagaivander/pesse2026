<?php
require __DIR__.'/layout.php';
require_once __DIR__.'/daily_report_data.php';
require_once __DIR__.'/daily_report_pdf.php';
$user=require_role(['superadmin','admin_kab']);
$date=(string)($_GET['tanggal']??pes_period_date(today()));
$action=(string)($_GET['action']??'');
$error=null;$data=null;
try {$data=dr_report_data($user,$date);} catch(Throwable $e) {
    $error=$e instanceof PDOException?'Data laporan belum dapat dibaca.':$e->getMessage();
}
if(in_array($action,['pdf','preview'],true)){
    if($error){http_response_code(400);exit(e($error));}
    if(!$data['total']['subsls'] || $data['total']['missing']===$data['total']['subsls']){http_response_code(422);exit('Belum ada laporan harian untuk tanggal dan wilayah ini.');}
    $bytes=dr_build_pdf($data);
    header('Content-Type: application/pdf');
    header('Content-Disposition: '.($action==='pdf'?'attachment':'inline').'; filename="daily_report_'.$data['scope_code'].'_'.$date.'.pdf"');
    header('Cache-Control: private, no-store');header('Content-Length: '.strlen($bytes));echo $bytes;exit;
}
render_header('Daily Report');
?>
<?php if($error): ?><div class="alert alert-danger"><?=e($error)?></div><?php endif; ?>
<form method="get" class="card card-body">
 <div class="form-row align-items-end">
  <div class="form-group col-md-3"><label for="report-date">Tanggal Data</label><input class="form-control" id="report-date" type="date" name="tanggal" min="2026-11-01" max="<?=e(min(today(),'2026-11-30'))?>" value="<?=e($date)?>" required></div>
  <div class="form-group col-md-5"><label>Wilayah Laporan</label><input class="form-control" value="<?=e($data['title']??'')?>" readonly></div>
  <div class="form-group col-md-4"><button class="btn btn-primary" type="submit"><i class="fas fa-rotate mr-1"></i>Tampilkan</button></div>
 </div>
 <div class="text-muted small">PDF 12 halaman. Angka menggunakan laporan terakhir setiap SubSLS sampai tanggal data yang dipilih. Tanggal sampul mengikuti tanggal generate (WITA).</div>
</form>
<?php if($data): $t=$data['total']; ?>
<div class="row">
<?php foreach(['subsls'=>'Target SubSLS','belum'=>'Belum','sedang'=>'On-progress','selesai'=>'Selesai'] as $key=>$label): ?><div class="col-md-3 col-6"><div class="small-box bg-white border"><div class="inner"><h3><?=dr_number($t[$key])?></h3><p><?=e($label)?></p></div></div></div><?php endforeach; ?>
</div>
<?php if($t['missing']): ?><div class="alert alert-warning"><?=$t['missing']?> SubSLS belum memiliki laporan sampai tanggal ini. Pada grafik progres umum, SubSLS tersebut masuk kategori Belum.</div><?php endif; ?>
<div class="card"><div class="card-header"><h3 class="card-title">Daily Report - <?=e(dr_date_label($date))?></h3></div><div class="card-body">
 <p>Isi PDF: sampul, apresiasi, pie chart progres umum, progres per <?=e($user['role']==='admin_kab'?'petugas (PML di kiri, lalu PCL)':strtolower($data['group_label']))?>, komposisi enam status assignment, progress Submit + Approve, empat grafik status Pengecekan Data, diskusi, dan penutup.</p>
 <?php if($t['subsls'] && $t['missing']<$t['subsls']): ?>
 <a class="btn btn-success" href="daily_report.php?action=pdf&amp;tanggal=<?=e($date)?>"><i class="fas fa-file-pdf mr-1"></i>Generate / Download PDF</a>
 <a class="btn btn-outline-primary ml-2" href="daily_report.php?action=preview&amp;tanggal=<?=e($date)?>" target="_blank" rel="noopener">Pratinjau PDF</a>
 <?php else: ?><div class="text-muted">PDF dapat dibuat setelah laporan harian tersedia untuk wilayah ini.</div><?php endif; ?>
 <p class="text-muted small mt-3 mb-0">Snapshot tersedia: <?=e($data['source_min']??'-')?><?=($data['source_max']!==$data['source_min'])?' s.d. '.e($data['source_max']??'-'):''?>. Selesai = Approve sama dengan target (target &gt; 0). Completed = Approve.</p>
</div></div>
<?php endif;render_footer(); ?>
