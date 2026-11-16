<?php
require_once __DIR__.'/bootstrap.php';
require_once __DIR__.'/data_check.php';

function dr_check_slides(array $areas,bool $isKab,string $date): array
{
    $slides=[];
    foreach(dc_definitions() as $kind=>$definition){
        $q=db()->prepare('SELECT MAX(tanggal) FROM data_check_rows WHERE jenis=? AND tanggal<=?');
        $q->execute([$kind,$date]);$snapshot=$q->fetchColumn()?:null;
        $counts=[];
        if($snapshot && $areas){
            $ids=array_column($areas,'id');
            $q=db()->prepare('SELECT subsls_id,counts_json FROM data_check_rows WHERE jenis=? AND tanggal=? AND subsls_id IN ('.implode(',',array_fill(0,count($ids),'?')).')');
            $q->execute(array_merge([$kind,$snapshot],$ids));
            foreach($q->fetchAll() as $r)$counts[$r['subsls_id']]=json_decode($r['counts_json'],true,512,JSON_THROW_ON_ERROR);
        }
        $groups=[];$loaded=0;
        foreach($areas as $area){
            $pml=(string)($area['pengawas_email']??'');$pcl=(string)($area['pencacah_email']??'');
            $key=$isKab?$pml.'|'.$pcl:$area['kab_id'];
            if(!isset($groups[$key])){
                $groups[$key]=array_merge(array_fill_keys(array_keys($definition['columns']),0),[
                    'id'=>$key,'label'=>$isKab?($area['pcl_name']?:($pcl?:'Belum ada PCL')):$key.'-'.mb_convert_case(mb_strtolower($area['nmkab']),MB_CASE_TITLE,'UTF-8'),
                    'assignments'=>0,'loaded'=>0]);
                if($isKab)$groups[$key]+= ['pml_id'=>$pml,'pml_name'=>$area['pml_name']?:($pml?:'Belum ada PML')];
            }
            if(!isset($counts[$area['id']]))continue;
            $loaded++;$groups[$key]['loaded']++;
            foreach($definition['columns'] as $field=>$label){$value=(int)$counts[$area['id']][$field];$groups[$key][$field]+=$value;$groups[$key]['assignments']+=$value;}
        }
        ksort($groups);$groups=array_values($groups);
        if($isKab)usort($groups,static fn($a,$b)=>strnatcasecmp($a['pml_name'],$b['pml_name'])?:strcmp($a['pml_id'],$b['pml_id'])?:strnatcasecmp($a['label'],$b['label']));
        $slides[$kind]=['label'=>$definition['label'],'columns'=>$definition['columns'],'date'=>$snapshot,'rows'=>$groups,'loaded'=>$loaded,'expected'=>count($areas)];
    }
    return $slides;
}

function dr_date_label(string $date): string
{
    $months = ['01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni','07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'];
    return (int)substr($date,8,2).' '.$months[substr($date,5,2)].' '.substr($date,0,4);
}

function dr_classify(array $row): string
{
    if ((int)$row['target'] > 0 && (int)$row['approved_by_pengawas'] === (int)$row['target']) return 'selesai';
    foreach (['draft_count','submitted_by_pencacah','rejected_by_pengawas','pending_count','approved_by_pengawas'] as $field) {
        if ((int)$row[$field] > 0) return 'sedang';
    }
    return 'belum';
}

function dr_report_data(array $user, string $date): array
{
    if (!in_array($user['role'],['superadmin','admin_kab'],true)) throw new RuntimeException('Akses Daily Report tidak diizinkan.');
    pes_require_period($date);
    if ($date > today()) throw new RuntimeException('Tanggal data tidak boleh melebihi hari ini.');
    $isKab = $user['role'] === 'admin_kab';
    $where = $isKab ? 'WHERE k.id=?' : '';
    $query = db()->prepare("SELECT ms.id, k.id kab_id, k.nmkab, kc.id kec_id, kc.nmkec, d.id desa_id,
        ms.pengawas_email, ms.pencacah_email, pml.nama pml_name, pcl.nama pcl_name,
        ds.tanggal, ds.target, ds.open_count, ds.draft_count, ds.submitted_by_pencacah,
        ds.rejected_by_pengawas, ds.pending_count, ds.approved_by_pengawas
        FROM pes_sample_subsls ms JOIN pes_sample_sls sl ON sl.id=ms.sls_id
        JOIN pes_sample_desa d ON d.id=sl.desa_id JOIN pes_sample_kec kc ON kc.id=d.kec_id
        JOIN pes_sample_kab k ON k.id=kc.kab_id
        LEFT JOIN petugas pml ON pml.email=ms.pengawas_email AND pml.role='pengawas'
        LEFT JOIN petugas pcl ON pcl.email=ms.pencacah_email AND pcl.role='pencacah'
        LEFT JOIN pes_sample_daily ds ON ds.subsls_id=ms.id AND ds.tanggal=(
            SELECT MAX(d2.tanggal) FROM pes_sample_daily d2 WHERE d2.subsls_id=ms.id AND d2.tanggal<=?
        ) $where ORDER BY k.id,kc.id,ms.id");
    $query->execute($isKab?[$date,$user['kab_id']]:[$date]);
    $rows = $query->fetchAll();
    $empty = ['subsls'=>0,'belum'=>0,'sedang'=>0,'selesai'=>0,'target'=>0,'open_count'=>0,'draft_count'=>0,
        'submitted_by_pencacah'=>0,'rejected_by_pengawas'=>0,'pending_count'=>0,'approved_by_pengawas'=>0,
        'assignments'=>0,'progress'=>0,'missing'=>0];
    $total=$empty; $groups=[]; $dates=[]; $staff=[];
    foreach ($rows as $row) {
        $key=$isKab?$row['kec_id']:$row['kab_id'];
        if (!isset($groups[$key])) $groups[$key]=array_merge($empty,['id'=>$key,'label'=>$key.'-'.mb_convert_case(mb_strtolower($isKab?$row['nmkec']:$row['nmkab']),MB_CASE_TITLE,'UTF-8')]);
        $status=dr_classify($row);
        $values=$empty; $values['subsls']=1; $values[$status]=1;
        $values['missing']=$row['tanggal']===null?1:0;
        foreach (array_keys(status_fields()) as $field) $values[$field]=(int)$row[$field];
        $values['target']=(int)$row['target'];
        $values['assignments']=total_status($row);
        $values['progress']=(int)$row['submitted_by_pencacah']+(int)$row['approved_by_pengawas'];
        foreach ($values as $field=>$value) { $groups[$key][$field]+=$value; $total[$field]+=$value; }
        if($isKab){
            $pml=(string)($row['pengawas_email']??'');$pcl=(string)($row['pencacah_email']??'');
            $staffKey=$pml.'|'.$pcl;
            if(!isset($staff[$staffKey]))$staff[$staffKey]=array_merge($empty,[
                'id'=>$staffKey,'pml_id'=>$pml,'pml_name'=>$row['pml_name']?:($pml?:'Belum ada PML'),
                'label'=>$row['pcl_name']?:($pcl?:'Belum ada PCL')]);
            foreach($values as $field=>$value)$staff[$staffKey][$field]+=$value;
        }
        if ($row['tanggal']) $dates[$row['tanggal']]=true;
    }
    ksort($groups);ksort($dates);
    $staff=array_values($staff);
    usort($staff,static fn($a,$b)=>strnatcasecmp($a['pml_name'],$b['pml_name'])?:strcmp($a['pml_id'],$b['pml_id'])?:strnatcasecmp($a['label'],$b['label']));
    return ['date'=>$date,'generated'=>date('Y-m-d H:i:s'),'title'=>$isKab?($rows[0]['nmkab']??(string)$user['kab_id']):'Kalimantan Timur',
        'scope_code'=>$isKab?(string)$user['kab_id']:'6400','group_label'=>$isKab?'Kecamatan':'Kabupaten/Kota',
        'sample_kecamatan'=>count(array_unique(array_column($rows,'kec_id'))),
        'sample_desa'=>count(array_unique(array_column($rows,'desa_id'))),
        'check_slides'=>dr_check_slides($rows,$isKab,$date),
        'total'=>$total,'groups'=>array_values($groups),'staff_groups'=>$staff,'source_min'=>array_key_first($dates),'source_max'=>array_key_last($dates)];
}
