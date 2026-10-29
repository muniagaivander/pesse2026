<?php
require __DIR__.'/layout.php'; require __DIR__.'/pes_master.php'; $user=require_role(['superadmin','admin_kab']);
$headers=['idsubsls','email_pengawas','email_pencacah']; if(isset($_GET['template'])) pes_template($headers,'template_alokasi_subsls'); $error=null;
if($_SERVER['REQUEST_METHOD']==='POST') { pes_check_csrf(); try { $rows=pes_csv($headers); db()->beginTransaction(); $seen=[];
foreach($rows as $line=>$r) { try { if(isset($seen[$r['idsubsls']])) throw new RuntimeException('ID SubSLS berulang di file.'); $seen[$r['idsubsls']]=true; pes_allocate($user,$r['idsubsls'],normalize_email($r['email_pengawas']),normalize_email($r['email_pencacah'])); } catch(Throwable $e) { throw new RuntimeException('Baris '.$line.': '.$e->getMessage()); } }
db()->commit(); flash('success',count($rows).' alokasi SubSLS berhasil disimpan.'); redirect('assignment.php');
} catch(Throwable $e) { if(db()->inTransaction()) db()->rollBack(); $error=$e->getMessage().' Tidak ada perubahan disimpan.'; } }
render_header('Upload Alokasi SubSLS'); ?>
<?php if($error): ?><div class="alert alert-danger"><?=e($error)?></div><?php endif ?>
<div class="card card-body"><p>Isi ID SubSLS, email pengawas, dan email pencacah. Kedua email harus terdaftar di master petugas dengan role serta satker sesuai wilayah.</p><a class="btn btn-outline-success mb-3 align-self-start" href="?template=1">Download Template CSV</a><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=e(pes_csrf())?>"><div class="form-group"><label>File alokasi (CSV UTF-8)</label><input class="form-control-file" type="file" name="csv" accept=".csv" required></div><button class="btn btn-primary">Upload Alokasi</button></form></div><?php render_footer(); ?>
