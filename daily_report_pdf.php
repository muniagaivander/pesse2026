<?php
// Small self-contained PDF renderer: vector charts/text and embedded reference backgrounds.
// No shell commands, third-party CDN, Python runtime, or public report cache is required.
final class DailyReportPdf
{
    private array $pages=[];
    private array $images=[];
    private string $content='';
    private const HEIGHT=540;

    public function page(string $background): void
    {
        if ($this->content!=='') $this->pages[]=$this->content;
        if (!isset($this->images[$background])) $this->images[$background]=file_get_contents(__DIR__.'/assets/daily-report/'.$background.'.jpg');
        $this->content="q 960 0 0 540 0 0 cm /".$background." Do Q\n";
    }
    private function rgb(string $hex): string
    {
        return sprintf('%.4F %.4F %.4F',hexdec(substr($hex,0,2))/255,hexdec(substr($hex,2,2))/255,hexdec(substr($hex,4,2))/255);
    }
    public function rect(float $x,float $y,float $w,float $h,string $color): void
    {
        $this->content.=sprintf("%s rg %.3F %.3F %.3F %.3F re f\n",$this->rgb($color),$x,self::HEIGHT-$y-$h,$w,$h);
    }
    public function line(float $x,float $y,float $x2,float $y2,string $color='d4d4d4',float $width=1): void
    {
        $this->content.=sprintf("%s RG %.2F w %.3F %.3F m %.3F %.3F l S\n",$this->rgb($color),$width,$x,self::HEIGHT-$y,$x2,self::HEIGHT-$y2);
    }
    public function text(float $x,float $y,string $value,float $size=14,string $color='111827',string $font='F1',float $maxWidth=0): void
    {
        if ($maxWidth>0) $size=min($size,$maxWidth/max(1,mb_strlen($value)*0.55));
        $encoded=iconv('UTF-8','Windows-1252//TRANSLIT',$value);
        $escaped=str_replace(['\\','(',')',"\r","\n"],['\\\\','\\(','\\)','',' '],$encoded===false?'':$encoded);
        $this->content.=sprintf("BT /%s %.3F Tf %s rg 1 0 0 1 %.3F %.3F Tm (%s) Tj ET\n",$font,$size,$this->rgb($color),$x,self::HEIGHT-$y,$escaped);
    }
    public function centered(float $x,float $y,string $value,float $size,string $color='111827',string $font='F1'): void
    {
        // Conservative width estimate used for short chart labels; long text is placed explicitly.
        $this->text($x-mb_strlen($value)*$size*0.255,$y,$value,$size,$color,$font);
    }
    public function pie(float $cx,float $cy,float $radius,array $values,array $colors): void
    {
        $total=array_sum($values);if(!$total)return;
        $angle=-M_PI/2;
        foreach($values as $i=>$value){
            if(!$value)continue;
            $end=$angle+2*M_PI*$value/$total;
            $path=sprintf('%s rg %.3F %.3F m ',$this->rgb($colors[$i]),$cx,self::HEIGHT-$cy);
            $steps=max(2,(int)ceil(($end-$angle)*60));
            for($s=0;$s<=$steps;$s++){$a=$angle+($end-$angle)*$s/$steps;$path.=sprintf('%.3F %.3F l ',$cx+cos($a)*$radius,self::HEIGHT-($cy+sin($a)*$radius));}
            $this->content.=$path."h f\n";$angle=$end;
        }
    }
    public function polygon(array $rings,string $color): void
    {
        $path=$this->rgb($color)." rg 1 1 1 RG 0.5 w ";
        foreach($rings as $points){
            if(count($points)<3)continue;
            foreach($points as $i=>[$x,$y])$path.=sprintf('%.2F %.2F %s ',$x,self::HEIGHT-$y,$i===0?'m':'l');
            $path.='h ';
        }
        $this->content.=$path."B*\n";
    }
    public function bytes(): string
    {
        $pages=$this->pages;if($this->content!=='')$pages[]=$this->content;
        $objects=[1=>'<< /Type /Catalog /Pages 2 0 R >>',2=>''];
        foreach(['Helvetica','Helvetica-Bold','Helvetica-BoldOblique','Helvetica-Oblique'] as $i=>$name)$objects[$i+3]='<< /Type /Font /Subtype /Type1 /BaseFont /'.$name.' /Encoding /WinAnsiEncoding >>';
        $resources='';$next=7;
        foreach($this->images as $name=>$bytes){$id=$next++;$size=getimagesizefromstring($bytes);$objects[$id]='<< /Type /XObject /Subtype /Image /Width '.$size[0].' /Height '.$size[1].' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '.strlen($bytes).">>\nstream\n".$bytes."\nendstream";$resources.='/'.$name.' '.$id.' 0 R ';}
        $kids=[];
        foreach($pages as $content){$pageId=$next++;$streamId=$next++;$kids[]=$pageId.' 0 R';$objects[$pageId]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 960 540] /Resources << /Font << /F1 3 0 R /F2 4 0 R /F3 5 0 R /F4 6 0 R >> /XObject << '.$resources.'>> >> /Contents '.$streamId.' 0 R >>';$zip=gzcompress($content);$objects[$streamId]='<< /Filter /FlateDecode /Length '.strlen($zip).">>\nstream\n".$zip."\nendstream";}
        $objects[2]='<< /Type /Pages /Count '.count($kids).' /Kids ['.implode(' ',$kids).'] >>';
        ksort($objects);$pdf="%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";$offsets=[0];
        foreach($objects as $id=>$object){$offsets[$id]=strlen($pdf);$pdf.=$id." 0 obj\n".$object."\nendobj\n";}
        $xref=strlen($pdf);$pdf.="xref\n0 ".count($offsets)."\n0000000000 65535 f \n";
        for($i=1;$i<count($offsets);$i++)$pdf.=sprintf("%010d 00000 n \n",$offsets[$i]);
        return $pdf.'trailer << /Size '.count($offsets).' /Root 1 0 R >>'."\nstartxref\n".$xref."\n%%EOF\n";
    }
}

function dr_number(int|float $value,int $decimals=0): string {return number_format($value,$decimals,',','.');}
function dr_percent(int|float $value,int|float $denominator): float {return $denominator>0?$value/$denominator*100:0;}

function dr_progress_map(DailyReportPdf $pdf,array $data): void
{
    $isProvince=$data['scope_code']==='6400';
    $file=$isProvince?'kaltim_kabupaten.geojson':'kecamatan_by_kabupaten/'.$data['scope_code'].'.geojson';
    $geo=json_decode(file_get_contents(__DIR__.'/assets/maps/'.$file),true,512,JSON_THROW_ON_ERROR);
    $project=static fn($p)=>[(float)$p[0],rad2deg(log(tan(M_PI/4+deg2rad((float)$p[1])/2)))];
    $minX=$minY=INF;$maxX=$maxY=-INF;
    foreach($geo['features'] as $feature){
        $polygons=$feature['geometry']['type']==='Polygon'?[$feature['geometry']['coordinates']]:$feature['geometry']['coordinates'];
        foreach($polygons as $rings)foreach($rings as $ring)foreach($ring as $point){[$x,$y]=$project($point);$minX=min($minX,$x);$maxX=max($maxX,$x);$minY=min($minY,$y);$maxY=max($maxY,$y);}
    }
    $scale=min(285/max(0.001,$maxX-$minX),245/max(0.001,$maxY-$minY));
    $left=438+(285-($maxX-$minX)*$scale)/2;$top=222+(245-($maxY-$minY)*$scale)/2;
    $rows=array_column($data['groups'],null,'id');
    foreach($geo['features'] as $feature){
        $id=(string)($feature['properties'][$isProvince?'idkab':'idkec']??'');$row=$rows[$id]??null;
        $pct=$row?dr_percent($row['progress']+$row['rejected_by_pengawas']+$row['pending_count'],$row['target']):0;
        $color=$row?ltrim(progress_color_hex(round($pct,2),$data['date']),'#'):'9ca3af';
        $polygons=$feature['geometry']['type']==='Polygon'?[$feature['geometry']['coordinates']]:$feature['geometry']['coordinates'];
        foreach($polygons as $rings){$out=[];foreach($rings as $ring){$points=[];$previous=null;foreach($ring as $point){[$x,$y]=$project($point);$p=[$left+($x-$minX)*$scale,$top+($maxY-$y)*$scale];if($previous && hypot($p[0]-$previous[0],$p[1]-$previous[1])<0.15)continue;$points[]=$p;$previous=$p;}$out[]=$points;}$pdf->polygon($out,$color);}
    }
    $pdf->text(736,235,'PROGRESS MAP',13,'222222','F2');
    $step=min(19,160/max(1,count($rows)));$y=256;
    foreach($rows as $row){$pct=dr_percent($row['progress']+$row['rejected_by_pengawas']+$row['pending_count'],$row['target']);$pdf->rect(736,$y-8,8,8,ltrim(progress_color_hex(round($pct,2),$data['date']),'#'));$pdf->text(749,$y,substr($row['id'],-($isProvince?4:3)).'  '.dr_number($pct,2).'%',11,'222222','F2');$y+=$step;}
    $y=426;
    foreach(progress_color_ranges($data['date']) as $range){$pdf->rect(736,$y-8,8,8,ltrim($range['color'],'#'));$pdf->text(749,$y,$range['label'],9,'333333');$y+=15;}
    $pdf->rect(736,$y-8,8,8,'9ca3af');$pdf->text(749,$y,'Bukan sampel',9,'333333');
    $pdf->text(440,484,'Progress: (Submit + Reject + Pending + Approve) / target.',9,'333333');
}

function dr_chart_header(DailyReportPdf $pdf,array $data,string $title,int $page): void
{
    $pdf->page('chart');$pdf->rect(10,12,7,52,'fb8c30');
    $pdf->text(30,42,$title,25,'111111','F2',735);
    $pdf->text(30,73,$data['title'].' | Data sampai '.dr_date_label($data['date']),14,'bd5723','F2',860);
    $source=$data['source_min']?($data['source_min']===$data['source_max']?$data['source_max']:$data['source_min'].' s.d. '.$data['source_max']):'belum tersedia';
    $pdf->text(22,520,'Sumber: DailyPES | Snapshot: '.$source.' | Generate: '.$data['generated'].' WITA',9,'333333');
    $pdf->text(935,530,(string)$page,10,'111111','F2');
}

function dr_legend(DailyReportPdf $pdf,array $entries,float $y=105): void
{
    $x=40;
    foreach($entries as [$label,$color]){$pdf->rect($x,$y-10,11,11,$color);$pdf->text($x+17,$y,$label,11,'222222');$x+=strlen($label)*6+39;}
}

function dr_bar_chart(DailyReportPdf $pdf,array $groups,array $segments,string $denominator,float $top=145,float $height=305,bool $checkData=false): void
{
    $x=260;$width=620;$count=count($groups);
    if(!$count){$pdf->text(280,280,'Tidak ada SubSLS sampel dalam wilayah ini.',18);return;}
    $staff=isset($groups[0]['pml_id']);
    if($staff){$x=395;$width=485;$pdf->text(35,$top-15,'PML / Pengawas',11,'333333','F2');$pdf->text(205,$top-15,'PCL / Pencacah',11,'333333','F2');}
    $rowHeight=min(42,$height/$count);$bottom=$top+$count*$rowHeight;
    if($staff){
        for($start=0;$start<$count;){
            $end=$start+1;while($end<$count && $groups[$end]['pml_id']===$groups[$start]['pml_id'])$end++;
            $y=$top+$start*$rowHeight;$h=($end-$start)*$rowHeight;
            $pdf->rect(30,$y-4,160,$h,'fff3e6');
            $name=wordwrap($groups[$start]['pml_name'],24,"\n",false);$lines=explode("\n",$name);
            foreach($lines as $line=>$text)$pdf->text(36,$y+$h/2+(($line-(count($lines)-1)/2)*12),$text,10,'87401c','F2',148);
            $pdf->line(198,$y-4,937,$y-4,'d6d3d1',0.6);$start=$end;
        }
    }
    for($tick=0;$tick<=100;$tick+=10){$tx=$x+$width*$tick/100;$pdf->line($tx,$top-10,$tx,$bottom,'dddddd',0.6);$pdf->text($tx-9,$bottom+17,$tick.'%',9,'555555');}
    foreach($groups as $i=>$row){
        $y=$top+$i*$rowHeight;$barHeight=min(24,$rowHeight*0.64);$font=min(12,$rowHeight*0.48);
        $pdf->text($staff?205:35,$y+$barHeight*0.72,$row['label'],$font,'333333','F2',$staff?180:215);
        $total=$row[$denominator];$left=$x;
        if($total<=0){$pdf->rect($x,$y,$width,$barHeight,'e5e7eb');$empty=$checkData?(($row['loaded']??0)>0?'Total 0':'Belum ada data'):'Belum ada assignment';$pdf->text($x+10,$y+$barHeight*0.7,$empty,max(7,$font-1),'555555');continue;}
        foreach($segments as [$field,$color]){
            $value=$field==='remaining'?$total-$row['progress']:$row[$field];
            $pct=dr_percent($value,$total);$w=$width*$pct/100;
            if($w>0)$pdf->rect($left,$y,$w,$barHeight,$color);
            if($w>=43 && $barHeight>=10)$pdf->centered($left+$w/2,$y+$barHeight*0.7,dr_number($pct,1).'%',min(10,$font),'ffffff','F2');
            $left+=$w;
        }
        $pdf->text(890,$y+$barHeight*0.72,dr_number($total),max(7,$font-1),'333333','F2',53);
    }
    $pdf->text(884,$top-15,$denominator==='subsls'?'SubSLS':'Total',10,'555555','F2');
}

function dr_build_pdf(array $data): string
{
    $pdf=new DailyReportPdf();$t=$data['total'];
    $staffReport=$data['scope_code']!=='6400';
    $chartRows=$staffReport?$data['staff_groups']:$data['groups'];
    $chartGroup=$staffReport?'PETUGAS':mb_strtoupper($data['group_label']);
    $pdf->page('cover');
    $pdf->text(40,155,'EVALUASI',58,'bd5723','F3');$pdf->text(40,222,'PES SE2026',58,'000000','F3');
    $pdf->text(42,292,'Disampaikan dalam Rapat Evaluasi PES SE2026',14,'111111','F4');
    $pdf->centered(197,393,'Samarinda, '.dr_date_label(substr($data['generated'],0,10)),16,'ffffff','F2');
    $pdf->text(46,469,'Tim PES SE2026',22,'000000','F2');
    $institution='BPS Provinsi Kalimantan Timur';
    if($staffReport){
        $region=preg_replace('/^(Kabupaten|Kota)\s+/iu','',$data['title']);
        $institution='BPS '.(substr($data['scope_code'],2,1)==='7'?'Kota ':'Kabupaten ').mb_convert_case(mb_strtolower($region),MB_CASE_TITLE,'UTF-8');
    }
    $pdf->text(46,495,$institution,20,'111111','F2',475);$pdf->text(940,530,'1',10);

    $pdf->page('appreciation');
    $pdf->text(32,142,'“',68,'ffffff','F2');
    foreach(['TERIMA KASIH DAN','APRESIASI','SETINGGI-TINGGINYA'] as $i=>$line)$pdf->centered(218,166+$i*37,$line,27,'000000','F2');
    $appreciation=['Atas kerja keras, komitmen,','dan peran aktifnya bagi',
        $staffReport?'segenap Petugas Lapangan':'segenap tim di Kabupaten/Kota',
        'dalam kegiatan PES SE2026'];
    foreach($appreciation as $i=>$line)$pdf->centered(215,316+$i*35,$line,23,'ffffff','F2');
    $pdf->centered(667,105,'Pelaksanaan PES SE2026',14,'ffffff','F2');
    $pdf->rect(427,118,486,374,'ffffff');
    if($staffReport){
        foreach([[$data['sample_kecamatan'],'Kecamatan'],[$data['sample_desa'],'Desa'],[$t['subsls'],'SubSLS']] as $i=>[$count,$label]){
            $y=145+$i*31;$pdf->text(555,$y,(string)$count,27,'ff7000','F2');$pdf->text(625,$y-2,$label,21,'000000','F2');
        }
    }else{
        $pdf->text(555,163,(string)count($data['groups']),31,'ff7000','F2');$pdf->text(625,161,'Kabupaten/Kota',23,'000000','F2');
        $pdf->text(555,201,(string)$t['subsls'],29,'ff7000','F2');$pdf->text(625,199,'SubSLS',23,'000000','F2');
    }
    dr_progress_map($pdf,$data);
    $pdf->text(935,530,'2',10,'ffffff','F2');

    dr_chart_header($pdf,$data,'PROGRES UMUM PENDATAAN DOOR TO DOOR',3);
    $pdf->rect(62,101,830,31,'a80500');$pdf->centered(478,123,'Progres SubSLS pada hari ke-'.(int)substr($data['date'],8,2).' pendataan',19,'ffffff','F2');
    $pdf->pie(260,330,140,[$t['sedang'],$t['belum'],$t['selesai']],['216d8f','fa332b','81bd43']);
    $angle=-M_PI/2;
    foreach([['On-progress','sedang'],['Belum','belum'],['Selesai','selesai']] as [$label,$key]){
        $pct=dr_percent($t[$key],$t['subsls']);if($pct<=0)continue;
        $mid=$angle+M_PI*$pct/100;$angle+=2*M_PI*$pct/100;
        if($pct>=8){$x=260+cos($mid)*80;$y=330+sin($mid)*80;$pdf->centered($x,$y-4,$label,14,'ffffff','F2');$pdf->centered($x,$y+15,dr_number($pct,2).'%',16,'ffffff','F2');}
        else{$x=260+cos($mid)*167;$y=330+sin($mid)*167;$pdf->line(260+cos($mid)*137,330+sin($mid)*137,$x,$y,'555555',0.8);$pdf->rect($x-62,$y-24,124,35,'ffffff');$pdf->centered($x,$y-10,$label,12,'222222','F2');$pdf->centered($x,$y+5,dr_number($pct,2).'%',14,'222222','F2');}
    }
    $pdf->text(523,176,'Target SubSLS: '.dr_number($t['subsls']),25,'111111','F2');
    $labels=[['On-progress','sedang','216d8f'],['Belum','belum','fa332b'],['Selesai','selesai','81bd43']];
    foreach($labels as $i=>[$label,$key,$color]){$y=225+$i*50;$pdf->rect(525,$y-16,17,17,$color);$pdf->text(554,$y,$label,18,'111111','F2');$pdf->text(720,$y,dr_number(dr_percent($t[$key],$t['subsls']),2).'% ('.$t[$key].')',18,'111111','F2');}
    $pdf->text(523,390,'Belum: seluruh status selain Open bernilai 0.',13);
    $pdf->text(523,413,'Selesai: Approve = target, dengan target > 0.',13);
    $pdf->text(523,436,'On-progress: sedang dikerjakan, belum selesai.',13);
    if($t['missing'])$pdf->text(523,466,$t['missing'].' SubSLS tanpa laporan dimasukkan ke Belum.',11,'a80500');

    dr_chart_header($pdf,$data,'PROGRES UMUM PER '.$chartGroup,4);
    dr_legend($pdf,[['Belum','fa332b'],['On-progress','216d8f'],['Selesai','81bd43']]);
    dr_bar_chart($pdf,$chartRows,[['belum','fa332b'],['sedang','216d8f'],['selesai','81bd43']],'subsls');
    $pdf->text(35,486,'Selesai dihitung dari SubSLS dengan Approve = target (target > 0).',12,'333333');

    dr_chart_header($pdf,$data,'PERSENTASE CAPAIAN ASSIGNMENT',5);
    dr_legend($pdf,[['Open','ef4444'],['Draft','f59e0b'],['Submit','0284c7'],['Reject','a855f7'],['Pending','64748b'],['Completed','4d9f38']]);
    dr_bar_chart($pdf,$chartRows,[['open_count','ef4444'],['draft_count','f59e0b'],['submitted_by_pencacah','0284c7'],['rejected_by_pengawas','a855f7'],['pending_count','64748b'],['approved_by_pengawas','4d9f38']],'assignments');
    $pdf->text(35,486,'Porsi setiap status terhadap seluruh assignment. Completed = Approve oleh pengawas.',12,'333333');

    dr_chart_header($pdf,$data,'PERSENTASE PROGRESS PER '.$chartGroup,6);
    dr_legend($pdf,[['Submit + Approve','4d9f38'],['Status lainnya','ef4444']]);
    dr_bar_chart($pdf,$chartRows,[['progress','4d9f38'],['remaining','ef4444']],'assignments');
    $pdf->text(35,486,'Progress = (Submit + Approve) / (Open + Draft + Submit + Reject + Pending + Approve) x 100%.',12,'333333');
    $page=7;
    $colors=['tidak_ditemukan'=>'dc2626','ditemukan'=>'15803d','baru'=>'0284c7','meninggal'=>'7c3aed',
        'tidak_dapat_ditemui_sampai_akhir_pendataan'=>'b45309','tutup'=>'b45309','ganda'=>'7c3aed',
        'data_diperoleh_dari_kantor_pusat'=>'475569','non_respon'=>'475569',
        'tinggal_di_rumah_ini'=>'15803d','pindah_ke_wilayah_lain_di_indonesia'=>'0284c7',
        'pindah_ke_luar_negeri'=>'b45309','anggota_keluarga_baru'=>'0e7490','sudah_pisah_kk'=>'475569',
        'tidak_ditemukan_atau_tidak_dikenal'=>'dc2626'];
    foreach($data['check_slides'] as $slide){
        $context=$data;$context['source_min']=$context['source_max']=$slide['date'];
        dr_chart_header($pdf,$context,'PENGECEKAN DATA - '.mb_strtoupper($slide['label']),$page++);
        $pdf->text(35,98,'Komposisi status (%) | Snapshot: '.($slide['date']?dr_date_label($slide['date']):'belum tersedia').' | '.$slide['loaded'].'/'.$slide['expected'].' SubSLS',11,'333333');
        $segments=[];$i=0;
        foreach($slide['columns'] as $field=>$label){
            $color=$colors[$field];$segments[]=[$field,$color];
            $x=35+($i%3)*300;$y=466+intdiv($i,3)*17;
            $pdf->rect($x,$y-8,9,9,$color);$pdf->text($x+15,$y,$label,9,'222222','F1',278);$i++;
        }
        if($slide['loaded'])dr_bar_chart($pdf,$slide['rows'],$segments,'assignments',130,300,true);
        else $pdf->text(170,280,'Belum ada data pengecekan sampai tanggal laporan ini.',20,'555555','F2',650);
    }
    $pdf->page('discussion');$pdf->text(935,530,'11',10,'ffffff','F2');
    $pdf->page('closing');$pdf->rect(929,508,28,30,'fb8c30');$pdf->text(935,526,'12',10,'ffffff');
    return $pdf->bytes();
}
