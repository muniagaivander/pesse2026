<?php
require __DIR__.'/layout.php'; require __DIR__.'/pes_master.php';
$user=require_role(['superadmin','admin_kab','viewer_prov','viewer_kab']);
$kab=in_array($user['role'],['admin_kab','viewer_kab'],true)?$user['kab_id']:trim($_GET['kab_id']??'');
$q=db()->prepare('SELECT p.*,u.active FROM petugas p JOIN users u ON u.id=p.user_id WHERE (?=\'\' OR p.satker=?) ORDER BY p.satker,p.role,p.nama'); $q->execute([$kab,$kab]); $rows=$q->fetchAll();
render_header('Daftar Petugas');
if($msg=flash('success')) echo '<div class="alert alert-success">'.e($msg).'</div>';
?>
<?php if(in_array($user['role'],['superadmin','admin_kab'],true)): ?><a class="btn btn-primary mb-3" href="upload_petugas.php">Upload Master Petugas</a><?php endif ?>
<a class="btn btn-outline-primary mb-3" href="assignment.php">Alokasi / Ganti Petugas</a>
<form method="get" class="card card-body"><label>Satker</label><select class="form-control" name="kab_id" onchange="this.form.submit()"><option value="">Semua satker</option><?php foreach(db()->query('SELECT id,nmkab FROM pes_sample_kab ORDER BY id') as $r): if(in_array($user['role'],['admin_kab','viewer_kab'],true)&&$r['id']!==$user['kab_id']) continue; ?><option value="<?=e($r['id'])?>" <?=$kab===$r['id']?'selected':''?>><?=e(pes_satker($r['id']))?></option><?php endforeach ?></select></form>
<div class="card"><div class="card-body table-responsive"><table class="table table-bordered"><thead><tr><th>ID Petugas</th><th>Email Petugas</th><th>Nama Petugas</th><th>Role</th><th>Satker</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?=e($r['idpetugas'])?></td><td><?=e($r['email'])?></td><td><?=e($r['nama'])?></td><td><?=e($r['role'])?></td><td><?=e(pes_satker($r['satker']))?></td></tr><?php endforeach ?><?php if(!$rows): ?><tr><td colspan="5">Belum ada petugas. Upload master petugas terlebih dahulu.</td></tr><?php endif ?></tbody></table></div></div><?php render_footer(); ?>
