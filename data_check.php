<?php
require_once __DIR__ . '/bootstrap.php';

function dc_definitions(): array
{
    return [
        'keluarga' => ['label' => 'Keluarga', 'columns' => [
            'tidak_ditemukan' => 'Tidak Ditemukan', 'ditemukan' => 'Ditemukan',
            'baru' => 'Baru', 'meninggal' => 'Meninggal',
            'tidak_dapat_ditemui_sampai_akhir_pendataan' => 'Tidak Dapat Ditemui sampai Akhir Pendataan',
        ]],
        'usaha_keluarga' => ['label' => 'Usaha (Keluarga)', 'columns' => [
            'tidak_ditemukan' => 'Tidak Ditemukan', 'ditemukan' => 'Ditemukan',
            'baru' => 'Baru', 'tutup' => 'Tutup', 'ganda' => 'Ganda',
            'data_diperoleh_dari_kantor_pusat' => 'Data Diperoleh dari Kantor Pusat',
        ]],
        'usaha_bku' => ['label' => 'Usaha (BKU)', 'columns' => [
            'tidak_ditemukan' => 'Tidak Ditemukan', 'ditemukan' => 'Ditemukan',
            'baru' => 'Baru', 'tutup' => 'Tutup', 'ganda' => 'Ganda', 'non_respon' => 'Non Respon',
        ]],
        'individu' => ['label' => 'Individu', 'columns' => [
            'tinggal_di_rumah_ini' => 'Tinggal di Rumah Ini', 'meninggal' => 'Meninggal',
            'pindah_ke_wilayah_lain_di_indonesia' => 'Pindah ke Wilayah Lain di Indonesia',
            'pindah_ke_luar_negeri' => 'Pindah ke Luar Negeri', 'anggota_keluarga_baru' => 'Anggota Keluarga Baru',
            'sudah_pisah_kk' => 'Sudah Pisah KK', 'tidak_ditemukan_atau_tidak_dikenal' => 'Tidak Ditemukan atau Tidak Dikenal',
        ]],
    ];
}

function dc_definition(string $kind): array
{
    return dc_definitions()[$kind] ?? throw new RuntimeException('Jenis data tidak valid.');
}

function dc_can_upload(array $user): bool
{
    // Check the current account, not an email shared by admin/viewer or a stale session role.
    $q = db()->prepare("SELECT COUNT(*) FROM users WHERE id=? AND username='admin6400' AND role='superadmin' AND active=1");
    $q->execute([(int)($user['id'] ?? 0)]);
    return (int)$q->fetchColumn() === 1;
}

function dc_headers(string $kind): array
{
    return array_merge(['tanggal', 'subsls_id'], array_keys(dc_definition($kind)['columns']));
}

function dc_xml(string $text): SimpleXMLElement
{
    if (stripos($text, '<!DOCTYPE') !== false || stripos($text, '<!ENTITY') !== false) {
        throw new RuntimeException('Struktur XML file tidak didukung.');
    }
    $previous = libxml_use_internal_errors(true);
    try {
        $xml = simplexml_load_string($text, SimpleXMLElement::class, LIBXML_NONET);
        if (!$xml) throw new RuntimeException('File XLSX rusak atau XML tidak valid.');
        return $xml;
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
}

function dc_zip_xml(ZipArchive $zip, string $path): SimpleXMLElement
{
    $info = $zip->statName($path);
    if (!$info || $info['size'] > 12 * 1024 * 1024) throw new RuntimeException('Bagian XLSX hilang atau terlalu besar: ' . $path);
    $content = $zip->getFromName($path);
    if ($content === false) throw new RuntimeException('Bagian XLSX tidak dapat dibaca.');
    return dc_xml($content);
}

function dc_xlsx_rows(string $path): array
{
    if (!is_file($path) || filesize($path) > 8 * 1024 * 1024) throw new RuntimeException('Ukuran file maksimal 8 MB.');
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) throw new RuntimeException('File harus berupa XLSX yang valid.');
    try {
        $book = dc_zip_xml($zip, 'xl/workbook.xml');
        $first = ($book->xpath('//*[local-name()="sheets"]/*[local-name()="sheet"]') ?: [])[0] ?? null;
        if (!$first) throw new RuntimeException('Worksheet tidak ditemukan.');
        $rid = (string)$first->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')->id;
        $rels = dc_zip_xml($zip, 'xl/_rels/workbook.xml.rels');
        $target = '';
        foreach ($rels->xpath('//*[local-name()="Relationship"]') ?: [] as $rel) {
            if ((string)$rel['Id'] === $rid && (string)$rel['TargetMode'] !== 'External') $target = (string)$rel['Target'];
        }
        if ($target === '' || str_contains($target, '..') || str_contains($target, '\\')) throw new RuntimeException('Lokasi worksheet tidak valid.');
        $sheetPath = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/' . $target;
        $strings = [];
        if ($zip->locateName('xl/sharedStrings.xml') !== false) {
            foreach (dc_zip_xml($zip, 'xl/sharedStrings.xml')->xpath('//*[local-name()="si"]') ?: [] as $si) {
                $strings[] = implode('', array_map('strval', $si->xpath('.//*[local-name()="t"]') ?: []));
            }
        }
        $result = [];
        foreach (dc_zip_xml($zip, $sheetPath)->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]') ?: [] as $row) {
            $cells = [];
            $rowNumber = (int)$row['r'];
            foreach ($row->xpath('./*[local-name()="c"]') ?: [] as $cell) {
                if ($cell->xpath('./*[local-name()="f"]')) throw new RuntimeException('Baris ' . $rowNumber . ': gunakan angka/nilai, bukan rumus.');
                if ((string)$cell['t'] === 'e') throw new RuntimeException('Baris ' . $rowNumber . ': terdapat error Excel.');
                if (!preg_match('/^([A-Z]+)[0-9]+$/', (string)$cell['r'], $m)) throw new RuntimeException('Alamat sel tidak valid.');
                $col = 0;
                foreach (str_split($m[1]) as $letter) $col = $col * 26 + ord($letter) - 64;
                if ($col > 32) throw new RuntimeException('Kolom file melebihi template.');
                $value = (string)(($cell->xpath('./*[local-name()="v"]') ?: [])[0] ?? '');
                if ((string)$cell['t'] === 's') {
                    if (!ctype_digit($value) || !array_key_exists((int)$value, $strings)) throw new RuntimeException('Teks XLSX tidak valid.');
                    $value = $strings[(int)$value];
                } elseif ((string)$cell['t'] === 'inlineStr') {
                    $value = implode('', array_map('strval', $cell->xpath('.//*[local-name()="t"]') ?: []));
                }
                $cells[$col - 1] = trim($value);
            }
            if (!array_filter($cells, fn($v) => $v !== '')) continue;
            $result[$rowNumber] = $cells;
            if (count($result) > 1000) throw new RuntimeException('Terlalu banyak baris. Gunakan template SubSLS sampel.');
        }
        return $result;
    } finally {
        $zip->close();
    }
}

function dc_date(string $value): string
{
    if (preg_match('/^\d+(?:\.0+)?$/D', $value)) {
        $value = (new DateTimeImmutable('1899-12-30'))->modify('+' . (int)$value . ' days')->format('Y-m-d');
    }
    pes_require_period($value);
    if ($value > today()) throw new RuntimeException('Tanggal data tidak boleh melebihi hari ini.');
    return $value;
}

function dc_parse_upload(string $kind, string $path): array
{
    $headers = dc_headers($kind);
    $rows = dc_xlsx_rows($path);
    if (!$rows) throw new RuntimeException('File kosong.');
    $firstRow = array_key_first($rows);
    $head = $rows[$firstRow];
    unset($rows[$firstRow]);
    $head = array_map(fn($v) => strtolower(trim($v, "\xEF\xBB\xBF \t\n\r")), $head);
    // November 12 source exports used "subls_id"; newer exports correct it to "subsls_id".
    if (($head[1] ?? '') === 'subls_id') $head[1] = 'subsls_id';
    while ($head && end($head) === '') array_pop($head);
    if ($head !== $headers) throw new RuntimeException('Header tidak cocok. Gunakan template ' . dc_definition($kind)['label'] . '.');
    $sample = array_fill_keys(db()->query('SELECT id FROM pes_sample_subsls')->fetchAll(PDO::FETCH_COLUMN), true);
    $data = [];
    $date = null;
    foreach ($rows as $line => $row) {
        try {
            $rowDate = dc_date($row[0] ?? '');
            if ($date !== null && $date !== $rowDate) throw new RuntimeException('Satu file harus berisi satu tanggal data.');
            $date = $rowDate;
            $id = $row[1] ?? '';
            if (!preg_match('/^64[0-9]{14}$/D', $id) || !isset($sample[$id])) throw new RuntimeException('ID SubSLS tidak terdaftar sebagai sampel PES: ' . $id);
            if (isset($data[$id])) throw new RuntimeException('ID SubSLS berulang: ' . $id);
            $counts = [];
            foreach (array_slice($headers, 2) as $i => $field) {
                $value = $row[$i + 2] ?? '';
                if (!preg_match('/^\d+(?:\.0+)?$/D', $value) || (float)$value > 2147483647) throw new RuntimeException('Kolom ' . $field . ' harus berupa bilangan bulat >= 0; isi 0 jika nihil.');
                $counts[$field] = (int)$value;
            }
            foreach ($row as $col => $value) if ($col >= count($headers) && $value !== '') throw new RuntimeException('Ada kolom tambahan di luar template.');
            $data[$id] = $counts;
        } catch (Throwable $e) {
            throw new RuntimeException('Baris ' . $line . ': ' . $e->getMessage());
        }
    }
    $missing = array_diff_key($sample, $data);
    if ($missing) throw new RuntimeException('Snapshot harus lengkap ' . count($sample) . ' SubSLS. Belum ada: ' . implode(', ', array_slice(array_keys($missing), 0, 5)) . (count($missing) > 5 ? ', ...' : ''));
    if (!$data) throw new RuntimeException('Tidak ada data untuk disimpan.');
    return ['jenis' => $kind, 'tanggal' => $date, 'rows' => $data];
}

function dc_save_uploads(array $user, array $files): array
{
    if (!dc_can_upload($user)) throw new RuntimeException('Hanya admin6400 yang dapat mengunggah data.');
    if (!$files) throw new RuntimeException('Pilih minimal satu file XLSX.');
    $parsed = [];
    foreach ($files as $kind => $file) {
        try {
            if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'xlsx') throw new RuntimeException('File harus berformat .xlsx.');
            $snapshot = dc_parse_upload($kind, $file['path']);
            $snapshot['filename'] = mb_substr(basename(str_replace('\\', '/', $file['name'])), 0, 255);
            $parsed[] = $snapshot;
        } catch (Throwable $e) {
            throw new RuntimeException(dc_definition($kind)['label'] . ': ' . $e->getMessage());
        }
    }
    db()->beginTransaction();
    try {
        $audit = db()->prepare('INSERT INTO data_check_uploads(jenis,tanggal,filename,row_count,uploaded_by,uploaded_at) VALUES(?,?,?,?,?,?)');
        $save = db()->prepare('INSERT INTO data_check_rows(jenis,tanggal,subsls_id,counts_json,upload_id) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE counts_json=VALUES(counts_json), upload_id=VALUES(upload_id)');
        $summary = [];
        foreach ($parsed as $snapshot) {
            $audit->execute([$snapshot['jenis'], $snapshot['tanggal'], $snapshot['filename'], count($snapshot['rows']), $user['id'], date('Y-m-d H:i:s')]);
            $uploadId = db()->lastInsertId();
            foreach ($snapshot['rows'] as $id => $counts) $save->execute([$snapshot['jenis'], $snapshot['tanggal'], $id, json_encode($counts, JSON_THROW_ON_ERROR), $uploadId]);
            $summary[] = dc_definition($snapshot['jenis'])['label'] . ': ' . count($snapshot['rows']) . ' SubSLS (' . $snapshot['tanggal'] . ')';
        }
        db()->commit();
        return $summary;
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        throw $e;
    }
}

function dc_scope(array $user, array $filters = []): array
{
    $where = []; $params = [];
    if (in_array($user['role'], ['admin_kab', 'viewer_kab'], true)) {
        $where[] = 'kc.kab_id=?'; $params[] = (string)$user['kab_id'];
    } elseif (!empty($filters['kab_id'])) {
        $where[] = 'kc.kab_id=?'; $params[] = $filters['kab_id'];
    }
    foreach (['kec_id' => 'kc.id', 'desa_id' => 'd.id'] as $key => $col) {
        if (!empty($filters[$key])) { $where[] = "$col=?"; $params[] = $filters[$key]; }
    }
    if (in_array($user['role'], ['pengawas', 'pencacah'], true)) {
        $field = $user['role'] === 'pengawas' ? 'pengawas_email' : 'pencacah_email';
        $where[] = "ms.$field=?"; $params[] = normalize_email($user['email']);
    }
    return [$where ? ' WHERE ' . implode(' AND ', $where) : '', $params];
}

function dc_area_rows(array $user, array $filters = []): array
{
    [$where, $params] = dc_scope($user, $filters);
    $q = db()->prepare("SELECT ms.id, ms.pengawas_email, ms.pencacah_email,
        sl.nmsls, ms.kdsubsls, d.id desa_id, d.nmdesa, kc.id kec_id, kc.nmkec, k.id kab_id, k.nmkab,
        up.name pengawas_name, uc.name pencacah_name
        FROM pes_sample_subsls ms
        JOIN pes_sample_sls sl ON sl.id=ms.sls_id JOIN pes_sample_desa d ON d.id=sl.desa_id
        JOIN pes_sample_kec kc ON kc.id=d.kec_id JOIN pes_sample_kab k ON k.id=kc.kab_id
        LEFT JOIN users up ON up.email=ms.pengawas_email AND up.role='pengawas'
        LEFT JOIN users uc ON uc.email=ms.pencacah_email AND uc.role='pencacah'
        $where ORDER BY ms.id");
    $q->execute($params);
    return $q->fetchAll();
}

function dc_latest_date(string $kind): ?string
{
    dc_definition($kind);
    $q = db()->prepare('SELECT MAX(tanggal) FROM data_check_rows WHERE jenis=? AND tanggal<=?');
    $q->execute([$kind, today()]);
    return $q->fetchColumn() ?: null;
}

function dc_display_rows(array $user, string $kind, array $filters): array
{
    $definition = dc_definition($kind);
    $date = dc_latest_date($kind);
    $counts = [];
    if ($date) {
        // Join the scoped sample population even for direct requests and exports.
        [$where, $params] = dc_scope($user, $filters);
        $q = db()->prepare("SELECT cr.subsls_id,cr.counts_json FROM data_check_rows cr
            JOIN pes_sample_subsls ms ON ms.id=cr.subsls_id JOIN pes_sample_sls sl ON sl.id=ms.sls_id
            JOIN pes_sample_desa d ON d.id=sl.desa_id JOIN pes_sample_kec kc ON kc.id=d.kec_id
            " . ($where ?: ' WHERE 1=1') . ' AND cr.jenis=? AND cr.tanggal=?');
        $q->execute(array_merge($params, [$kind, $date]));
        foreach ($q->fetchAll() as $r) $counts[$r['subsls_id']] = json_decode($r['counts_json'], true, 512, JSON_THROW_ON_ERROR);
    }
    $out = [];
    foreach (dc_area_rows($user, $filters) as $area) {
        $staff = ($filters['petugas_type'] ?? 'pcl') === 'pml' ? 'pengawas' : 'pencacah';
        $perStaff = ($filters['view'] ?? 'subsls') === 'petugas';
        $email = (string)($area[$staff . '_email'] ?? '');
        // Keep unallocated SubSLS visible and reconcile the totals in either view.
        $key = $perStaff ? $area['kab_id'] . ':' . ($email !== '' ? $email : 'unassigned') : $area['id'];
        if (!isset($out[$key])) {
            $out[$key] = ['key' => $key, 'name' => $perStaff ? ($email === '' ? 'Belum dialokasikan' : ($area[$staff . '_name'] ?: $email)) : $area['id'],
                'email' => $email, 'sls' => $area['nmsls'] . ' / Sub ' . $area['kdsubsls'],
                'kab_id' => $area['kab_id'], 'kec_id' => $area['kec_id'], 'kabupaten' => [], 'kecamatan' => [], 'desa' => [], 'subsls_total' => 0, 'loaded' => 0,
                'details' => [], 'counts' => array_fill_keys(array_keys($definition['columns']), 0)];
        }
        $r = &$out[$key];
        $r['kabupaten'][$area['kab_id']] = $area['nmkab'];
        $r['kecamatan'][$area['kec_id']] = $area['nmkec'];
        $r['desa'][$area['desa_id']] = $area['nmdesa'];
        $r['subsls_total']++;
        if ($perStaff) $r['details'][] = [
            'id'=>$area['id'], 'sls'=>$area['nmsls'].' / Sub '.$area['kdsubsls'],
            'kecamatan'=>$area['nmkec'], 'desa'=>$area['nmdesa'],
            'counts'=>$counts[$area['id']] ?? null,
        ];
        if (isset($counts[$area['id']])) {
            $r['loaded']++;
            foreach ($r['counts'] as $field => &$value) $value += (int)$counts[$area['id']][$field];
            unset($value);
        }
        unset($r);
    }
    $rows = array_values($out);
    foreach ($rows as &$r) {
        foreach (['kabupaten', 'kecamatan', 'desa'] as $field) { ksort($r[$field]); $r[$field] = implode(', ', $r[$field]); }
        $r['total'] = array_sum($r['counts']);
    }
    unset($r);
    $needle = mb_strtolower(trim($filters['q'] ?? ''));
    if ($needle !== '') $rows = array_values(array_filter($rows, fn($r) => str_contains(mb_strtolower(implode(' ', [$r['name'], $r['email'], $r['sls'], $r['kabupaten'], $r['kecamatan'], $r['desa']])), $needle)));
    $sort = $filters['sort'] ?? 'name';
    if (!in_array($sort, array_merge(['name','kabupaten','kecamatan','desa','total','subsls_total'], array_keys($definition['columns'])), true)) $sort = 'name';
    $direction = ($filters['dir'] ?? 'asc') === 'desc' ? -1 : 1;
    $perStaff = ($filters['view'] ?? 'subsls') === 'petugas';
    if ($perStaff) {
        $sort = $kind === 'individu' ? 'tidak_ditemukan_atau_tidak_dikenal' : 'tidak_ditemukan';
        $direction = -1;
    }
    usort($rows, function($a, $b) use($sort, $direction, $perStaff) {
        if ($perStaff && $a['kab_id'] !== $b['kab_id']) return strcmp($a['kab_id'], $b['kab_id']);
        $av = $a['counts'][$sort] ?? $a[$sort]; $bv = $b['counts'][$sort] ?? $b[$sort];
        $compare = is_string($av) ? strnatcasecmp($av, $bv) : $av <=> $bv;
        return $direction * $compare ?: strnatcasecmp($a['name'], $b['name']) ?: strcmp($a['key'], $b['key']);
    });
    return ['date' => $date, 'rows' => $rows];
}

function dc_xlsx_column(int $number): string
{
    $out = '';
    while ($number > 0) { $number--; $out = chr(65 + $number % 26) . $out; $number = intdiv($number, 26); }
    return $out;
}

function dc_district_download(array $user): string
{
    $account = db()->prepare("SELECT * FROM users WHERE id=? AND active=1 AND (role='admin_kab' OR (username='admin6400' AND role='superadmin'))");
    $account->execute([(int)($user['id'] ?? 0)]);
    $current = $account->fetch();
    if (!$current || ($current['role']==='admin_kab' && !$current['kab_id'])) {
        http_response_code(403);
        exit('Unduhan ini khusus admin6400 dan admin kabupaten/kota aktif.');
    }
    // Use the current account scope, never a requested district or table filter.
    $temp = tempnam(sys_get_temp_dir(), 'pes_export_');
    try {
        file_put_contents($temp, dc_template_bytes('keluarga'));
        $zip = new ZipArchive();
        if ($zip->open($temp) !== true) throw new RuntimeException('Unduhan tidak dapat dibuat.');
        $sheets = ''; $relationships = ''; $overrides = ''; $index = 0;
        foreach (dc_definitions() as $kind => $definition) {
            $index++;
            $result = dc_display_rows($current, $kind, ['view'=>'subsls']);
            $headers = dc_headers($kind);
            $data = [$headers];
            foreach ($result['rows'] as $row) {
                $data[] = array_merge([$result['date'] ?? '', $row['name']], $row['loaded'] ? array_values($row['counts']) : array_fill(0, count($headers)-2, ''));
            }
            $xml = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols><col min="1" max="1" width="14" customWidth="1"/><col min="2" max="2" width="23" customWidth="1"/><col min="3" max="'.count($headers).'" width="27" customWidth="1"/></cols><sheetData>';
            foreach ($data as $r => $values) {
                $number = $r+1;
                $xml .= '<row r="'.$number.'"'.($r===0?' ht="60" customHeight="1"':'').'>';
                foreach ($values as $c => $value) {
                    $address = dc_xlsx_column($c+1).$number;
                    if ($r>0 && $c>=2 && is_int($value)) $xml .= '<c r="'.$address.'"><v>'.$value.'</v></c>';
                    else $xml .= '<c r="'.$address.'" t="inlineStr" s="'.($r===0?1:0).'"><is><t>'.htmlspecialchars((string)$value, ENT_XML1, 'UTF-8').'</t></is></c>';
                }
                $xml .= '</row>';
            }
            $xml .= '</sheetData><autoFilter ref="A1:'.dc_xlsx_column(count($headers)).count($data).'"/></worksheet>';
            $zip->addFromString('xl/worksheets/sheet'.$index.'.xml', $xml);
            $sheets .= '<sheet name="'.htmlspecialchars($definition['label'],ENT_XML1,'UTF-8').'" sheetId="'.$index.'" r:id="rId'.$index.'"/>';
            $relationships .= '<Relationship Id="rId'.$index.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$index.'.xml"/>';
            $overrides .= '<Override PartName="/xl/worksheets/sheet'.$index.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        $zip->addFromString('xl/workbook.xml','<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'.$sheets.'</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels','<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$relationships.'<Relationship Id="rId5" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $types = $zip->getFromName('[Content_Types].xml');
        $types = preg_replace('~<Override PartName="/xl/worksheets/[^" ]+"[^>]*/>~','',$types);
        $zip->addFromString('[Content_Types].xml',str_replace('</Types>',$overrides.'</Types>',$types));
        $zip->close();
        return (string)file_get_contents($temp);
    } finally {
        if (is_file($temp)) unlink($temp);
    }
}

function dc_template_bytes(string $kind): string
{
    $headers = dc_headers($kind);
    $q = db()->query('SELECT id FROM pes_sample_subsls ORDER BY id');
    $rows = [$headers];
    foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $id) $rows[] = array_merge([pes_period_date(today()), (string)$id], array_fill(0, count($headers) - 2, ''));
    $sheet = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols><col min="1" max="1" width="14" customWidth="1"/><col min="2" max="2" width="23" customWidth="1"/><col min="3" max="' . count($headers) . '" width="27" customWidth="1"/></cols><sheetData>';
    foreach ($rows as $i => $row) {
        $n = $i + 1; $sheet .= '<row r="' . $n . '"' . ($i === 0 ? ' ht="48" customHeight="1"' : '') . '>';
        foreach ($row as $j => $value) $sheet .= '<c r="' . dc_xlsx_column($j + 1) . $n . '" t="inlineStr" s="' . ($i === 0 ? 1 : 0) . '"><is><t xml:space="preserve">' . htmlspecialchars((string)$value, ENT_XML1, 'UTF-8') . '</t></is></c>';
        $sheet .= '</row>';
    }
    $sheet .= '</sheetData><autoFilter ref="A1:' . dc_xlsx_column(count($headers)) . count($rows) . '"/></worksheet>';
    $temp = tempnam(sys_get_temp_dir(), 'pes_check_');
    $zip = new ZipArchive();
    try {
        if ($zip->open($temp, ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Template tidak dapat dibuat.');
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
        $zip->addFromString('_rels/.rels','<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml','<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Rekap" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels','<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/styles.xml','<?xml version="1.0"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment wrapText="1" vertical="center"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();
        return (string)file_get_contents($temp);
    } finally {
        if (is_file($temp)) unlink($temp);
    }
}
