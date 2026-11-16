<?php
require __DIR__.'/layout.php';
require_once __DIR__.'/data_check.php';
$user=require_role(['superadmin','admin_kab','viewer_prov','viewer_kab','pengawas','pencacah']);
$canUpload=dc_can_upload($user); $definitions=dc_definitions();
$tab=(string)($_GET['tab']??'keluarga');
if(!isset($definitions[$tab]) && $tab!=='upload') $tab='keluarga';
$action=(string)($_GET['action']??'');
if($action==='download'){
 if(!$canUpload && $user['role']!=='admin_kab'){http_response_code(403);exit('Unduhan ini khusus admin6400 dan admin kabupaten/kota.');}
 $bytes=dc_district_download($user);
 header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
 header('Content-Disposition: attachment; filename="pengecekan_data_'.($canUpload?'6400':preg_replace('/[^0-9]/','',(string)$user['kab_id'])).'_'.date('Ymd_His').'.xlsx"');
 header('Cache-Control: no-store');echo $bytes;exit;
}
if(($tab==='upload'||$action==='template'||$_SERVER['REQUEST_METHOD']==='POST')&&!$canUpload){http_response_code(403);exit('Hanya admin6400 yang dapat mengakses upload dan template pengecekan data.');}
if($action==='template'){
 $kind=(string)($_GET['jenis']??''); if(!isset($definitions[$kind])){http_response_code(400);exit('Jenis template tidak valid.');}
 $bytes=dc_template_bytes($kind);header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');header('Content-Disposition: attachment; filename="template_pengecekan_'.$kind.'.xlsx"');header('Cache-Control: no-store');echo $bytes;exit;
}
$error=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
 pes_check_csrf();$tab='upload';
 try{
  $files=[];foreach($definitions as $kind=>$definition){$file=$_FILES[$kind]??null;if(!$file||$file['error']===UPLOAD_ERR_NO_FILE)continue;if($file['error']!==UPLOAD_ERR_OK||!is_uploaded_file($file['tmp_name']))throw new RuntimeException($definition['label'].': upload tidak berhasil. Periksa ukuran file dan batas upload server.');$files[$kind]=['name'=>$file['name'],'path'=>$file['tmp_name']];}
  $summary=dc_save_uploads($user,$files);flash('success','Data tersimpan. '.implode('; ',$summary).'.');redirect('pengecekan_data.php?tab=upload');
 }catch(Throwable $e){$error=$e instanceof PDOException?'Penyimpanan database gagal. Tidak ada perubahan disimpan.':$e->getMessage().' Tidak ada perubahan disimpan.';}
}
$filters=['view'=>($_GET['view']??'')==='petugas'?'petugas':'subsls','petugas_type'=>($_GET['petugas_type']??'')==='pml'?'pml':'pcl','dir'=>($_GET['dir']??'')==='desc'?'desc':'asc'];
foreach(['kab_id','kec_id','desa_id','q','sort'] as $key)$filters[$key]=trim((string)($_GET[$key]??($key==='sort'?'name':'')));
if($filters['view']==='petugas'){$filters['sort']=$tab==='individu'?'tidak_ditemukan_atau_tidak_dikenal':'tidak_ditemukan';$filters['dir']='desc';}
if(in_array($user['role'],['admin_kab','viewer_kab'],true))$filters['kab_id']=(string)$user['kab_id'];
$baseQuery=array_merge(['tab'=>$tab],$filters);
function dc_page_url(array $base,array $changes):string{return 'pengecekan_data.php?'.http_build_query(array_merge($base,$changes));}
function dc_header_link(string $key,string $label,array $base):string{$active=$base['sort']===$key;if(($base['view']??'')==='petugas')return e($label).($active?' ↓':'');return '<a href="'.e(dc_page_url($base,['sort'=>$key,'dir'=>$active&&$base['dir']==='asc'?'desc':'asc'])).'">'.e($label).($active?($base['dir']==='asc'?' ↑':' ↓'):'').'</a>';}
function dc_group_button(string $label,string $target,bool $expanded):string{return '<button type="button" class="dc-toggle" data-dc-toggle="'.e($target).'" aria-expanded="'.($expanded?'true':'false').'" aria-label="'.e(($expanded?'Lipat':'Buka').' rincian '.$label).'"><span data-dc-sign aria-hidden="true">'.($expanded?'−':'+').'</span> '.e($label).'</button>';}
function dc_group_metrics(array $group):string{$html='';foreach(array_merge(array_values($group['counts']),[array_sum($group['counts'])]) as $v)$html.='<td class="text-right font-weight-bold">'.($group['loaded']?number_format($v,0,',','.'):'—').'</td>';return $html;}
if($tab!=='upload'){
 $options=['kab_id'=>[],'kec_id'=>[],'desa_id'=>[]];foreach(dc_area_rows($user) as $area){$options['kab_id'][$area['kab_id']]=$area['kab_id'].' - '.$area['nmkab'];if($filters['kab_id']===$area['kab_id'])$options['kec_id'][$area['kec_id']]=$area['kec_id'].' - '.$area['nmkec'];if($filters['kec_id']===$area['kec_id'])$options['desa_id'][$area['desa_id']]=$area['desa_id'].' - '.$area['nmdesa'];}
 $result=dc_display_rows($user,$tab,$filters);$rows=$result['rows'];$columns=$definitions[$tab]['columns'];$totals=array_fill_keys(array_keys($columns),0);$covered=0;$expected=0;$staffView=$filters['view']==='petugas';$groups=[];
 foreach($rows as $row){$covered+=$row['loaded'];$expected+=$row['subsls_total'];foreach($totals as $key=>&$total)$total+=$row['counts'][$key];unset($total);
  if(!$staffView){$code=substr($row['name'],0,4);$kec=$row['kec_id'];
   if(!isset($groups[$code]))$groups[$code]=['label'=>$code.'-'.mb_convert_case(mb_strtolower(preg_replace('/^(Kabupaten|Kota)\s+/iu','',$row['kabupaten'])),MB_CASE_TITLE,'UTF-8'),'rows'=>[],'kecamatan'=>[],'counts'=>array_fill_keys(array_keys($columns),0),'loaded'=>0];
   if(!isset($groups[$code]['kecamatan'][$kec]))$groups[$code]['kecamatan'][$kec]=['label'=>$kec.'-'.mb_convert_case(mb_strtolower($row['kecamatan']),MB_CASE_TITLE,'UTF-8'),'rows'=>[],'counts'=>array_fill_keys(array_keys($columns),0),'loaded'=>0];
   $groups[$code]['rows'][]=$row;$groups[$code]['loaded']+=$row['loaded'];$groups[$code]['kecamatan'][$kec]['rows'][]=$row;$groups[$code]['kecamatan'][$kec]['loaded']+=$row['loaded'];
   foreach($columns as $key=>$label){$groups[$code]['counts'][$key]+=$row['counts'][$key];$groups[$code]['kecamatan'][$kec]['counts'][$key]+=$row['counts'][$key];}
  }
 }
 ksort($groups);foreach($groups as &$group)ksort($group['kecamatan']);unset($group);
 $displayRows=$rows;$displayGroups=$groups;
}
$EXTRA_HEAD='<link rel="stylesheet" href="assets/pengecekan-data.css?v=20261112e">';render_header('Pengecekan Data');
?>
<?php if($message=flash('success')): ?><div class="alert alert-success" role="status"><?=e($message)?></div><?php endif; ?>
<?php if($error): ?><div class="alert alert-danger" role="alert"><?=e($error)?></div><?php endif; ?>
<div class="card dc-tabs-card"><div class="card-header p-0 pt-1"><ul class="nav nav-tabs" aria-label="Jenis pengecekan data">
<?php foreach($definitions as $key=>$definition): ?><li class="nav-item"><a class="nav-link <?=$tab===$key?'active':''?>" href="<?=e(dc_page_url($baseQuery,['tab'=>$key,'sort'=>'name']))?>"><?=e($definition['label'])?></a></li><?php endforeach; ?>
<?php if($canUpload): ?><li class="nav-item ml-md-auto"><a class="nav-link <?=$tab==='upload'?'active':''?>" href="pengecekan_data.php?tab=upload"><i class="fas fa-file-upload mr-1" aria-hidden="true"></i>Upload</a></li><?php endif; ?>
<?php if($canUpload): ?><li class="nav-item"><a class="nav-link" href="pengecekan_data.php?action=download" title="Unduh empat sheet data terbaru seluruh wilayah"><i class="fas fa-file-excel mr-1" aria-hidden="true"></i>Download</a></li><?php endif; ?>
<?php if($user['role']==='admin_kab'): ?><li class="nav-item ml-md-auto"><a class="nav-link" href="pengecekan_data.php?action=download" title="Unduh empat sheet data terbaru wilayah Anda"><i class="fas fa-file-excel mr-1" aria-hidden="true"></i>Unduh</a></li><?php endif; ?>
</ul></div></div>
<?php if($tab==='upload'): ?>
<div class="alert alert-info">Pilih satu atau beberapa file sekaligus. Kolom dan urutannya sama dengan Excel sumber: <strong>tanggal, subsls_id</strong>, lalu kolom hitungan masing-masing jenis data. Setiap file harus memuat seluruh <strong><?=(int)db()->query('SELECT COUNT(*) FROM pes_sample_subsls')->fetchColumn()?> SubSLS sampel</strong> untuk satu tanggal. Isi <strong>0</strong> untuk nilai nihil.</div>
<form method="post" enctype="multipart/form-data" data-progress-submit data-progress-title="Menyimpan pengecekan data..." data-progress-text="File sedang divalidasi sebelum disimpan."><input type="hidden" name="csrf" value="<?=e(pes_csrf())?>"><div class="row">
<?php foreach($definitions as $kind=>$definition): ?><div class="col-lg-6"><div class="card card-outline card-primary"><div class="card-body"><h2 class="h5"><?=e($definition['label'])?></h2><a class="btn btn-outline-success btn-sm mb-3" href="pengecekan_data.php?action=template&amp;jenis=<?=e($kind)?>">Download Template XLSX</a><div class="form-group mb-2"><label for="upload_<?=e($kind)?>">File <?=e($definition['label'])?></label><input class="form-control-file" type="file" name="<?=e($kind)?>" id="upload_<?=e($kind)?>" accept=".xlsx"></div><?php $latest=dc_latest_date($kind); ?><small class="text-muted">Tanggal data terbaru: <strong><?=e($latest?date('d/m/Y',strtotime($latest)):'Belum tersedia')?></strong></small></div></div></div><?php endforeach; ?>
</div><p class="text-muted">Histori tanggal sebelumnya tetap disimpan. Upload ulang jenis dan tanggal yang sama memperbarui snapshot tanggal tersebut. Jika satu file bermasalah, seluruh unggahan pada proses ini dibatalkan.</p><button class="btn btn-primary mb-4" type="submit">Validasi dan Simpan</button></form>
<div class="card"><div class="card-header"><h2 class="card-title">Riwayat Upload Terakhir</h2></div><div class="card-body table-responsive p-0"><table class="table table-sm table-bordered table-striped mb-0"><thead><tr><th>Jenis</th><th>Tanggal Data</th><th>File</th><th>SubSLS</th><th>Waktu Upload (WITA)</th></tr></thead><tbody>
<?php $logs=db()->query('SELECT * FROM data_check_uploads ORDER BY id DESC LIMIT 20')->fetchAll();foreach($logs as $log): ?><tr><td><?=e($definitions[$log['jenis']]['label']??$log['jenis'])?></td><td><?=e($log['tanggal'])?></td><td><?=e($log['filename'])?></td><td><?=(int)$log['row_count']?></td><td><?=e($log['uploaded_at'])?></td></tr><?php endforeach;if(!$logs): ?><tr><td colspan="5" class="text-center text-muted p-3">Belum ada upload.</td></tr><?php endif; ?></tbody></table></div></div>
<?php else: ?>
<div class="dc-data-info"><strong><?=e($definitions[$tab]['label'])?></strong> · Tanggal data: <strong><?=e($result['date']?date('d/m/Y',strtotime($result['date'])):'Belum tersedia')?></strong><span class="d-block small font-weight-normal mt-1">Menampilkan snapshot terbaru yang tersedia.</span></div>
<form method="get" class="card card-body" id="dc-filter-form"><input type="hidden" name="tab" value="<?=e($tab)?>"><input type="hidden" name="sort" value="<?=e($filters['sort'])?>"><input type="hidden" name="dir" value="<?=e($filters['dir'])?>">
<div class="form-row"><div class="form-group col-md-3"><label for="dc-view">Tampilan</label><select class="form-control" name="view" id="dc-view" data-auto-submit><option value="subsls" <?=!$staffView?'selected':''?>>Per SubSLS</option><option value="petugas" <?=$staffView?'selected':''?>>Per Petugas</option></select></div><div class="form-group col-md-3"><label for="dc-staff">Jenis Petugas</label><select class="form-control" name="petugas_type" id="dc-staff" data-auto-submit <?=$staffView?'':'disabled'?>><option value="pcl" <?=$filters['petugas_type']==='pcl'?'selected':''?>>Pencacah / PCL</option><option value="pml" <?=$filters['petugas_type']==='pml'?'selected':''?>>Pengawas / PML</option></select></div><div class="form-group col-md-6"><label for="dc-search">Cari <?=$staffView?'nama/email petugas atau wilayah':'ID SubSLS, SLS, atau wilayah'?></label><input class="form-control" id="dc-search" type="search" name="q" value="<?=e($filters['q'])?>" placeholder="Ketik kata pencarian"></div></div>
<div class="form-row align-items-end"><?php foreach(['kab_id'=>'Kabupaten/Kota','kec_id'=>'Kecamatan','desa_id'=>'Desa/Kelurahan'] as $field=>$label): ?><div class="form-group col-md-3"><label for="dc-<?=e($field)?>"><?=e($label)?></label><select class="form-control" name="<?=e($field)?>" id="dc-<?=e($field)?>" data-area="<?=e($field)?>"><option value="">Semua <?=e($label)?></option><?php foreach($options[$field] as $id=>$text): ?><option value="<?=e($id)?>" <?=$filters[$field]===(string)$id?'selected':''?>><?=e($text)?></option><?php endforeach; ?></select></div><?php endforeach; ?><div class="form-group col-md-3"><button class="btn btn-primary" type="submit">Tampilkan</button> <a class="btn btn-outline-secondary mt-1" href="<?=e(dc_page_url(['tab'=>$tab],[]))?>">Reset</a></div></div></form>
<div class="card"><div class="card-header d-flex flex-wrap justify-content-between align-items-center"><span><strong><?=count($rows)?></strong> <?=$staffView?'petugas/kelompok':'SubSLS'?> · Data tersedia <strong><?=$covered?> / <?=$expected?></strong> SubSLS</span><small class="text-muted ml-auto"><?=$staffView?'Dikelompokkan per kabupaten/kota · '.e($columns[$filters['sort']]).' terbanyak lebih dahulu':'Klik judul kolom untuk mengurutkan · −/+ untuk subtotal kabupaten dan kecamatan'?></small></div>
<div class="card-body table-responsive p-0 dc-table-pane"><table class="table table-sm table-bordered table-striped table-hover mb-0 dc-table"><thead><tr>
<?php if(!$staffView): ?><th class="dc-first">Kabupaten/Kota</th><th>Kecamatan</th><?php endif; ?><th class="<?=$staffView?'dc-first':''?>"><?=dc_header_link('name',$staffView?'Nama Petugas':'ID SubSLS',$baseQuery)?></th>
<?php if($staffView): ?><th><?=dc_header_link('kecamatan','Wilayah Kerja Kecamatan',$baseQuery)?></th><?php else: ?><th>Nama SLS / SubSLS</th><?php endif; ?><th><?=dc_header_link('desa',$staffView?'Wilayah Kerja Desa':'Desa/Kelurahan',$baseQuery)?></th><?php if($staffView): ?><th><?=dc_header_link('kabupaten','Kabupaten',$baseQuery)?></th><?php endif; ?>
<?php foreach($columns as $key=>$label): ?><th class="dc-metric <?=in_array($key,['ditemukan','baru','tinggal_di_rumah_ini'],true)?'dc-green':'dc-yellow'?>"><?=dc_header_link($key,$label,$baseQuery)?></th><?php endforeach; ?><th class="dc-blue"><?=dc_header_link('total','Total',$baseQuery)?></th><?php if($staffView): ?><th class="dc-blue"><?=dc_header_link('subsls_total','Jumlah SubSLS',$baseQuery)?></th><?php endif; ?></tr></thead>
<?php if($staffView): ?><tbody><?php $previousKab=null;foreach($displayRows as $staffIndex=>$row): ?><?php if($previousKab!==$row['kab_id']): $previousKab=$row['kab_id']; ?><tr class="dc-staff-kab"><th colspan="<?=6+count($columns)?>"><?=e($row['kab_id'].' - '.$row['kabupaten'])?></th></tr><?php endif; ?><tr><td class="dc-first"><strong><?=e($row['name'])?></strong> <button type="button" class="btn btn-link btn-sm p-0 ml-1" data-dc-staff-detail="<?=$staffIndex?>" data-staff-name="<?=e($row['name'])?>" aria-label="<?=e('Lihat SubSLS '.$row['name'])?>" title="Lihat rincian SubSLS"><i class="fas fa-info-circle" aria-hidden="true"></i></button><?php if($row['email']): ?><small class="d-block text-muted dc-email"><?=e($row['email'])?></small><?php endif; ?></td><td><?=e($row['kecamatan'])?></td><td><?=e($row['desa'])?></td><td><?=e($row['kabupaten'])?></td><?=dc_group_metrics($row)?><td class="text-right"><?=$row['subsls_total']?></td></tr><?php endforeach; ?><?php if(!$displayRows): ?><tr><td colspan="<?=6+count($columns)?>" class="text-center text-muted p-4">Tidak ada data pada wilayah/pencarian ini.</td></tr><?php endif; ?></tbody>
<?php else: foreach($displayGroups as $code=>$group): ?><tbody data-dc-group="<?=e($code)?>">
<tr class="dc-subtotal" hidden><td class="dc-first"><?=dc_group_button($group['label'],'kab',false)?></td><td colspan="4"><strong>Subtotal Kabupaten/Kota</strong> <small>(<?=count($group['rows'])?> SubSLS sesuai filter)</small></td><?=dc_group_metrics($group)?></tr>
<?php $firstKec=true;foreach($group['kecamatan'] as $kec=>$subgroup): ?><tr class="dc-kec-subtotal" data-kec="<?=e($kec)?>" hidden><td class="dc-first"><?=$firstKec?dc_group_button($group['label'],'kab',true):''?></td><td><?=dc_group_button($subgroup['label'],(string)$kec,false)?></td><td colspan="3"><strong>Subtotal Kecamatan</strong> <small>(<?=count($subgroup['rows'])?> SubSLS sesuai filter)</small></td><?=dc_group_metrics($subgroup)?></tr>
<?php foreach($subgroup['rows'] as $index=>$row): ?><tr class="dc-detail" data-kec="<?=e($kec)?>"><td class="dc-first"><?=$firstKec&&$index===0?dc_group_button($group['label'],'kab',true):''?></td><td><?=$index===0?dc_group_button($subgroup['label'],(string)$kec,true):''?></td><td><strong><?=e($row['name'])?></strong></td><td><?=e($row['sls'])?></td><td><?=e($row['desa'])?></td><?php foreach($columns as $key=>$label): ?><td class="text-right dc-number"><?=$row['loaded']?number_format($row['counts'][$key],0,',','.'):'—'?></td><?php endforeach; ?><td class="text-right font-weight-bold"><?=$row['loaded']?number_format($row['total'],0,',','.'):'—'?></td></tr><?php endforeach;$firstKec=false;endforeach; ?></tbody><?php endforeach;if(!$displayGroups): ?><tbody><tr><td colspan="<?=6+count($columns)?>" class="text-center text-muted p-4">Tidak ada data pada wilayah/pencarian ini.</td></tr></tbody><?php endif;endif; ?>
<tfoot><tr><th colspan="<?=$staffView?4:5?>">Total seluruh hasil filter</th><?php foreach($totals as $total): ?><th class="text-right"><?=$covered?number_format($total,0,',','.'):'—'?></th><?php endforeach; ?><th class="text-right"><?=$covered?number_format(array_sum($totals),0,',','.'):'—'?></th><?php if($staffView): ?><th class="text-right"><?=$expected?></th><?php endif; ?></tr></tfoot></table></div>
<div class="card-footer"><small>Seluruh hasil filter ditampilkan dalam satu tabel. Gulir ke bawah untuk melihat rincian lainnya. Tanda — berarti belum ada data, bukan nilai nol.</small></div></div>
<?php if($staffView): ?>
<div class="modal fade" id="dcStaffDetailModal" tabindex="-1" role="dialog" aria-labelledby="dcStaffDetailTitle" aria-hidden="true">
 <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document"><div class="modal-content">
  <div class="modal-header bg-info text-white"><div><h5 class="modal-title" id="dcStaffDetailTitle">Rincian SubSLS Petugas</h5><small><?=e($definitions[$tab]['label'])?> · Tanggal data: <?=e($result['date']?date('d/m/Y',strtotime($result['date'])):'Belum tersedia')?></small></div><button type="button" class="close text-white" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button></div>
  <div class="modal-body" id="dcStaffDetailBody"></div>
 </div></div>
</div>
<?php foreach($displayRows as $staffIndex=>$staffRow): ?>
<template id="dc-staff-detail-<?=$staffIndex?>">
 <p><strong><?=e($staffRow['kab_id'].' - '.$staffRow['kabupaten'])?></strong> · <?=$staffRow['subsls_total']?> SubSLS sesuai filter aktif</p>
 <div class="table-responsive"><table class="table table-sm table-bordered table-striped dc-table"><thead><tr><th>ID SubSLS</th><th>Nama SLS / SubSLS</th><th>Kecamatan</th><th>Desa/Kelurahan</th><?php foreach($columns as $label): ?><th><?=e($label)?></th><?php endforeach; ?><th>Total</th></tr></thead><tbody>
 <?php foreach($staffRow['details'] as $detail): ?><tr><td><?=e($detail['id'])?></td><td><?=e($detail['sls'])?></td><td><?=e($detail['kecamatan'])?></td><td><?=e($detail['desa'])?></td><?php foreach($columns as $key=>$label): ?><td class="text-right"><?=$detail['counts']!==null?number_format($detail['counts'][$key],0,',','.'):'—'?></td><?php endforeach; ?><td class="text-right"><?=$detail['counts']!==null?number_format(array_sum($detail['counts']),0,',','.'):'—'?></td></tr><?php endforeach; ?>
 </tbody><tfoot><tr><th colspan="4">Total Petugas</th><?=dc_group_metrics($staffRow)?></tr></tfoot></table></div>
</template>
<?php endforeach; endif; ?>
<script src="assets/pengecekan-data.js?v=20261112f"></script><?php endif;render_footer(); ?>
