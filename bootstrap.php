<?php
session_name('PESSE2026');
session_start();
require __DIR__ . '/config.php';
require_once __DIR__ . '/fieldwork.php';

function db(): PDO {
    static $pdo;
    global $DB_HOST, $DB_NAME, $DB_USER, $DB_PASS;
    if (!$pdo) {
        $pdo = new PDO("mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4", $DB_USER, $DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirect($url): void {
    header("Location: {$url}");
    exit;
}

function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function require_login(): array {
    $user = current_user();
    if (!$user) redirect('login.php');
    return $user;
}

function require_role(array $roles): array {
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        exit('Akses ditolak');
    }
    return $user;
}

function user_active_status(?string $email): bool {
    if (!$email) return false;
    $stmt = db()->prepare("SELECT active FROM users WHERE email=?");
    $stmt->execute([$email]);
    return (bool)$stmt->fetchColumn();
}

function sync_petugas_user_active_status(): void { /* Aktivasi dikelola di master petugas. */ }

function ensure_completion_status_table(): void {
    db()->exec("CREATE TABLE IF NOT EXISTS subsls_completion_status (
        subsls_id VARCHAR(20) PRIMARY KEY,
        status_selesai ENUM('Belum Selesai','Selesai') NOT NULL DEFAULT 'Belum Selesai',
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        updated_by VARCHAR(150) NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function flash($key, $message = null) {
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }
    $value = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $value;
}

function admin_kab_codes(): array {
    return ['01','02','03','04','05','09','11','71','72','74'];
}

function ensure_default_admins(): void { /* Akun awal dibuat oleh setup CLI. */ }

function total_status(array $row): int {
    return (int)$row['open_count'] + (int)$row['draft_count'] + (int)$row['submitted_by_pencacah'] + (int)$row['approved_by_pengawas'] + (int)$row['rejected_by_pengawas'] + (int)($row['pending_count'] ?? 0);
}

function normalize_email($email): string {
    return strtolower(trim((string)$email));
}

function petugas_label(?string $email, ?string $name = null): string {
    $email = normalize_email($email ?? '');
    $name = trim((string)($name ?? ''));
    if ($email === '') {
        return '-';
    }
    if ($name === '' || strcasecmp($name, $email) === 0) {
        return $email;
    }
    return $name . ' (' . $email . ')';
}

function petugas_name_by_email(?string $email): string {
    static $cache = [];
    $email = normalize_email($email ?? '');
    if ($email === '') {
        return '';
    }
    if (!array_key_exists($email, $cache)) {
        $stmt = db()->prepare("SELECT name FROM users WHERE email=?");
        $stmt->execute([$email]);
        $cache[$email] = trim((string)($stmt->fetchColumn() ?: ''));
    }
    return $cache[$email];
}

function petugas_label_by_email(?string $email): string {
    return petugas_label($email, petugas_name_by_email($email));
}

function petugas_short_area_label(?string $name, ?string $area): string {
    $name = trim((string)($name ?? ''));
    $area = trim((string)($area ?? ''));
    if ($name === '') {
        $name = '-';
    }
    return $area !== '' ? $name . ' (' . $area . ')' : $name;
}

function today(): string {
    return date('Y-m-d');
}

function status_fields(): array {
    return [
        'open_count' => 'Open',
        'draft_count' => 'Draft',
        'submitted_by_pencacah' => 'Submit',
        'rejected_by_pengawas' => 'Reject',
        'pending_count' => 'Pending',
        'approved_by_pengawas' => 'Approved',
    ];
}

function progress_color_settings(?string $asOfDate = null): array {
    $timezone = new DateTimeZone('Asia/Makassar');
    $start = new DateTimeImmutable('2026-11-01', $timezone);
    try {
        $asOf = $asOfDate
            ? new DateTimeImmutable($asOfDate, $timezone)
            : new DateTimeImmutable('today', $timezone);
    } catch (Throwable $e) {
        $asOf = new DateTimeImmutable('today', $timezone);
    }
    $schedule = pes_fieldwork_schedule($asOf->format('Y-m-d'));
    $days = $schedule['day'];
    $dailyTarget = 100 / $schedule['duration'];
    $target = min(100.0, $days * $dailyTarget);
    $warningMin = min(100.0, max(0.0, ($days - 3) * $dailyTarget));
    return [
        'start_date' => $start->format('Y-m-d'),
        'as_of_date' => $asOf->format('Y-m-d'),
        'day' => $days,
        'daily_target' => $dailyTarget,
        'warning_min' => $warningMin,
        'target' => $target,
        'colors' => [
            'low' => '#dc2626',
            'warning' => '#facc15',
            'good' => '#16a34a',
        ],
    ];
}

function progress_color_key(float $pct, ?string $asOfDate = null): string {
    $settings = progress_color_settings($asOfDate);
    if ($pct < (float)$settings['warning_min']) {
        return 'low';
    }
    if ($pct < (float)$settings['target']) {
        return 'warning';
    }
    return 'good';
}

function progress_color_hex(float $pct, ?string $asOfDate = null): string {
    $settings = progress_color_settings($asOfDate);
    return $settings['colors'][progress_color_key($pct, $asOfDate)] ?? '#16a34a';
}

function progress_color_ranges(?string $asOfDate = null): array {
    $settings = progress_color_settings($asOfDate);
    $warningMin = (float)$settings['warning_min'];
    $target = (float)$settings['target'];
    return [
        [
            'key' => 'low',
            'label' => '< ' . number_format($warningMin, 2, ',', '.') . '%',
            'color' => $settings['colors']['low'],
            'max' => $warningMin,
        ],
        [
            'key' => 'warning',
            'label' => number_format($warningMin, 2, ',', '.') . '% - < ' . number_format($target, 2, ',', '.') . '%',
            'color' => $settings['colors']['warning'],
            'min' => $warningMin,
            'max' => $target,
        ],
        [
            'key' => 'good',
            'label' => '>= ' . number_format($target, 2, ',', '.') . '%',
            'color' => $settings['colors']['good'],
            'min' => $target,
        ],
    ];
}

function progress_color_legend_title(?string $asOfDate = null): string {
    $settings = progress_color_settings($asOfDate);
    $months = [
        '01' => 'Januari',
        '02' => 'Februari',
        '03' => 'Maret',
        '04' => 'April',
        '05' => 'Mei',
        '06' => 'Juni',
        '07' => 'Juli',
        '08' => 'Agustus',
        '09' => 'September',
        '10' => 'Oktober',
        '11' => 'November',
        '12' => 'Desember',
    ];
    $date = DateTimeImmutable::createFromFormat('Y-m-d', $settings['as_of_date']);
    if (!$date) {
        return 'Legenda';
    }
    return 'Legenda ' . $date->format('j') . ' ' . $months[$date->format('m')] . ' ' . $date->format('Y');
}

function daily_form_status_fields(): array {
    $fields = status_fields();
    return [
        'open_count' => $fields['open_count'],
        'draft_count' => $fields['draft_count'],
        'submitted_by_pencacah' => $fields['submitted_by_pencacah'],
        'rejected_by_pengawas' => $fields['rejected_by_pengawas'],
        'pending_count' => $fields['pending_count'],
        'approved_by_pengawas' => $fields['approved_by_pengawas'],
    ];
}

function mobile_update_content_path(): string {
    return __DIR__ . '/mobile_update_content.json';
}

function mobile_update_default_content(): array {
    return [
        [
            'title' => 'Versi Fasih Mobile Terbaru v4.4.0',
            'subtitle' => 'Tanggal 21 Juni 2026',
            'details' => [
                'Perbaikan kunjungan PML hanya untuk usaha non respon',
                'Perbaikan rule validasi profesi supir dan fotografer di halaman sosek',
                'Perbaikan force submit untuk assignment yang dipilih assignment keluarga',
                'Perbaikan Role PML',
                'Perbaikan CEK NIK, CEK NIB, CEK ID PLN, CEK NO METERAN',
                'Profesi 999 hanya untuk keberadaan 3 / 4',
                'NIK 8888 untuk yang belum memiliki NIK',
                'Rule jika profesi petani (Halaman sosek) harus memiliki usaha kategori A',
                'JK anggota keluarga prelist dikunci',
                'ID Pelanggan boleh 11 / 12 digit',
                'Range pengeluaran blok IV no 15a, 15b, 16a, 16b, 16c',
            ],
        ],
        [
            'title' => 'Versi Template dan Rule Validasi 4.7.0',
            'subtitle' => '',
            'details' => [
                'Update Rule kepala keluarga perempuan, memiliki anak, cerai hidup/cerai mati.',
                'Update Rule Validasi Force Submit pada akun PML',
                'Update no meteran 11-13 Digit',
                'Update nama orang dengan karakter / []',
                'Update rule R17 dan R18 kode 9 tidak tahu padahal keberadaan kode 3/4',
                'Update rule badan usaha PT pada CAWI',
            ],
        ],
        [
            'title' => 'Versi Fasih FORM Engine v0.2.0',
            'subtitle' => '',
            'details' => [
                'Dukungan multi-bahasa baru: rusia dan mandarin untuk kebutuhan pendataan keluarga khusus',
                'Peningkatan Konsistensi & Desain UI/UX (Input tandatangan, ambil waktu, geotag, file, foto, dan navigasi)',
                'Penanganan hasil force submit agar dapat di-approve oleh PML',
            ],
        ],
    ];
}

function mobile_update_content(): array {
    $path = mobile_update_content_path();
    if (!is_file($path)) {
        return mobile_update_default_content();
    }
    $json = json_decode((string)file_get_contents($path), true);
    if (!is_array($json) || count($json) !== 3) {
        return mobile_update_default_content();
    }
    foreach ($json as $item) {
        if (!isset($item['title'], $item['subtitle'], $item['details']) || !is_array($item['details'])) {
            return mobile_update_default_content();
        }
    }
    return $json;
}

function save_mobile_update_content(array $items): bool {
    $clean = [];
    for ($i = 0; $i < 3; $i++) {
        $raw = $items[$i] ?? [];
        $detailsText = str_replace(["\r\n", "\r"], "\n", (string)($raw['details_text'] ?? ''));
        $details = array_values(array_filter(array_map('trim', explode("\n", $detailsText)), fn($line) => $line !== ''));
        $clean[] = [
            'title' => trim((string)($raw['title'] ?? '')),
            'subtitle' => trim((string)($raw['subtitle'] ?? '')),
            'details' => $details,
        ];
    }
    $json = json_encode($clean, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return $json !== false && file_put_contents(mobile_update_content_path(), $json . PHP_EOL, LOCK_EX) !== false;
}

function fetch_area_options(array $user, array $filters = []): array {
    $where = [];
    $params = [];
    if ($user['role'] === 'admin_kab') {
        $where[] = 'k.id = ?';
        $params[] = $user['kab_id'];
    }
    foreach (['kab_id' => 'k.id', 'kec_id' => 'kc.id', 'desa_id' => 'd.id'] as $key => $col) {
        if (!empty($filters[$key])) {
            $where[] = "$col = ?";
            $params[] = $filters[$key];
        }
    }
    $sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $base = "FROM pes_sample_subsls ms
        JOIN pes_sample_sls sl ON sl.id=ms.sls_id
        JOIN pes_sample_desa d ON d.id=sl.desa_id
        JOIN pes_sample_kec kc ON kc.id=d.kec_id
        JOIN pes_sample_kab k ON k.id=kc.kab_id
        JOIN master_prov p ON p.id=k.prov_id
        $sqlWhere";
    $out = [];
    foreach ([
        'kabupaten' => "SELECT DISTINCT k.id value, CONCAT(k.id,' - ',k.nmkab) label $base ORDER BY label",
        'kecamatan' => "SELECT DISTINCT kc.id value, CONCAT(kc.kdkec,' - ',kc.nmkec) label $base ORDER BY label",
        'desa' => "SELECT DISTINCT d.id value, CONCAT(d.kddesa,' - ',d.nmdesa) label $base ORDER BY label",
        'sls' => "SELECT DISTINCT sl.id value, CONCAT(sl.kdsls,' - ',sl.nmsls) label $base ORDER BY label",
    ] as $key => $sql) {
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $out[$key] = $stmt->fetchAll();
    }
    return $out;
}

function joined_master_sql(): string {
    return "FROM pes_sample_subsls ms
        JOIN pes_sample_sls sl ON sl.id=ms.sls_id
        JOIN pes_sample_desa d ON d.id=sl.desa_id
        JOIN pes_sample_kec kc ON kc.id=d.kec_id
        JOIN pes_sample_kab k ON k.id=kc.kab_id
        JOIN master_prov p ON p.id=k.prov_id
        LEFT JOIN pes_sample_status ss ON ss.subsls_id=ms.id";
}

function render_rekap_petugas_tabs(string $active): void {
    $user = current_user();
    $tabs = [
        'today' => ['label' => 'Hari Ini', 'href' => 'rekap_petugas.php', 'icon' => 'fa-clipboard-list'],
        'weekly' => ['label' => '1 Minggu', 'href' => 'rekap_petugas_weekly.php', 'icon' => 'fa-calendar-week'],
    ];
    if ($user && in_array($user['role'], ['superadmin', 'admin_kab', 'viewer_prov', 'viewer_kab'], true)) {
        $tabs['daily'] = ['label' => 'Seluruh Hari', 'href' => 'rekap_petugas_daily.php', 'icon' => 'fa-calendar-days'];
    }
    ?>
    <style>
      .rekap-petugas-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 14px;
      }
      .rekap-petugas-tabs .rekap-tab-card {
        align-items: center;
        background: #ffffff;
        border: 1px solid #dbe3ef;
        border-radius: 8px;
        color: #334155;
        display: inline-flex;
        font-weight: 800;
        gap: 8px;
        min-height: 42px;
        padding: 9px 14px;
      }
      .rekap-petugas-tabs .rekap-tab-card:hover {
        background: #fff7ed;
        border-color: #fb923c;
        color: #9a3412;
        text-decoration: none;
      }
      .rekap-petugas-tabs .rekap-tab-card.active {
        background: #2563eb;
        border-color: #2563eb;
        color: #ffffff;
      }
    </style>
    <div class="rekap-petugas-tabs">
      <?php foreach ($tabs as $key => $tab): ?>
        <a class="rekap-tab-card <?= $active === $key ? 'active' : '' ?>" href="<?= e($tab['href']) ?>">
          <i class="fas <?= e($tab['icon']) ?>"></i>
          <span><?= e($tab['label']) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
    <?php
}

function pes_csrf(): string { return $_SESSION['pes_csrf'] ??= bin2hex(random_bytes(32)); }
function pes_check_csrf(): void {
 if (!hash_equals(pes_csrf(), (string)($_POST['csrf'] ?? ''))) { http_response_code(403); exit('Token formulir tidak valid.'); }
}
function pes_satker(string $code): string {
 if ($code === '6400') return 'BPS Provinsi Kalimantan Timur';
 $q=db()->prepare('SELECT nmkab FROM master_kab WHERE id=?'); $q->execute([$code]); $name=$q->fetchColumn();
 if (!$name) throw new RuntimeException('Satker tidak ditemukan.');
 return 'BPS '.(substr($code,2,1)==='7'?'Kota ':'Kabupaten ').ucwords(strtolower($name));
}

// Periode pelaksanaan PES SE 2026 dan cakupan sampel.
const PES_START = '2026-11-01';
const PES_END = '2026-11-30';
function pes_period_date(string $date): string { return min(PES_END, max(PES_START, $date)); }
function pes_require_period(string $date): void {
 if (!preg_match('/^2026-11-(0[1-9]|[12][0-9]|30)$/D', $date)) throw new RuntimeException('Tanggal kegiatan harus 1–30 November 2026.');
}
function pes_require_sample(string $id): void {
 $q=db()->prepare('SELECT id FROM pes_sample_subsls WHERE id=?'); $q->execute([$id]);
 if (!$q->fetchColumn()) throw new RuntimeException('SubSLS bukan sampel PES: '.$id);
}
