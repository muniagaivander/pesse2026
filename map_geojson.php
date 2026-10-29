<?php
require __DIR__ . '/bootstrap.php';
// Geometry does not modify the session; do not block other requests from this user.
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

@ini_set('memory_limit', '512M');
@set_time_limit(120);

function map_json_error(int $status, string $message): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $message]);
    exit;
}

$level = $_GET['level'] ?? 'kabupaten';
$allowed = ['kabupaten', 'kecamatan', 'desa', 'subsls'];
if (!in_array($level, $allowed, true)) {
    map_json_error(400, 'Level peta tidak valid');
}

$ids = [];
foreach (['kab_id' => 4, 'kec_id' => 7, 'desa_id' => 10, 'subsls_id' => 16] as $key => $digits) {
    $value = $_GET[$key] ?? '';
    if (!is_string($value) || ($value !== '' && !preg_match('/^64[0-9]{' . ($digits - 2) . '}$/D', $value))) {
        map_json_error(400, 'Kode wilayah tidak valid: ' . $key);
    }
    $ids[$key] = $value;
}
$subslsId = $ids['subsls_id'];
$desaId = $ids['desa_id'] ?: ($subslsId !== '' ? substr($subslsId, 0, 10) : '');
$kecId = $ids['kec_id'] ?: ($desaId !== '' ? substr($desaId, 0, 7) : '');
$kabId = $ids['kab_id'] ?: ($kecId !== '' ? substr($kecId, 0, 4) : '');
if (($subslsId !== '' && substr($subslsId, 0, 10) !== $desaId)
    || ($desaId !== '' && substr($desaId, 0, 7) !== $kecId)
    || ($kecId !== '' && substr($kecId, 0, 4) !== $kabId)) {
    map_json_error(400, 'Kode wilayah tidak sesuai dengan wilayah induknya.');
}
$relative = 'kaltim_kabupaten.geojson';
if ($level === 'kecamatan') {
    if ($kabId === '') map_json_error(400, 'Pilih kabupaten untuk memuat peta kecamatan.');
    $relative = 'kecamatan_by_kabupaten/' . $kabId . '.geojson';
} elseif ($level === 'desa') {
    if ($kecId === '') map_json_error(400, 'Pilih kecamatan untuk memuat peta desa.');
    $relative = 'desa_by_kecamatan/' . $kecId . '.geojson';
} elseif ($level === 'subsls') {
    if ($desaId === '') map_json_error(400, 'Pilih desa untuk memuat peta SubSLS.');
    $relative = 'subsls_by_desa/' . $desaId . '.geojson';
}
$path = __DIR__ . '/assets/maps/' . $relative;
$isSplit = true;
// Permit a staged deployment: upload child files before retiring the legacy maps.
if (!is_file($path) && $level !== 'kabupaten') {
    $legacy = ['kecamatan' => 'kaltim_kecamatan.geojson', 'desa' => 'kaltim_desa.geojson', 'subsls' => 'kaltim_sls.geojson'];
    $legacyPath = __DIR__ . '/assets/maps/' . $legacy[$level];
    if (is_file($legacyPath)) {
        $path = $legacyPath;
        $isSplit = false;
    }
}
if (!is_file($path)) {
    map_json_error(404, 'File peta tidak ditemukan: assets/maps/' . $relative);
}
if (!is_readable($path)) {
    map_json_error(403, 'File peta tidak bisa dibaca: assets/maps/' . basename($path));
}

// Parent files already contain only the requested area. Stream bytes directly.
// Only an individual SubSLS still needs feature filtering within its village file.
header('X-Map-Source: ' . ($isSplit ? 'split' : 'legacy'));
if ($isSplit && ($level !== 'subsls' || $subslsId === '')) {
    header('Content-Type: application/geo+json; charset=utf-8');
    header('Cache-Control: public, max-age=3600');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}

function map_prop_id(array $props, string $level): string
{
    if ($level === 'kabupaten') {
        return (string)($props['idkab'] ?? (($props['kdprov'] ?? '') . ($props['kdkab'] ?? '')));
    }
    if ($level === 'kecamatan') {
        return (string)($props['idkec'] ?? (($props['kdprov'] ?? '') . ($props['kdkab'] ?? '') . ($props['kdkec'] ?? '')));
    }
    if ($level === 'desa') {
        return (string)($props['iddesa'] ?? (($props['kdprov'] ?? '') . ($props['kdkab'] ?? '') . ($props['kdkec'] ?? '') . ($props['kddesa'] ?? '')));
    }
    return (string)($props['idsubsls'] ?? $props['subsls_id'] ?? $props['level_6_full_code'] ?? '');
}

function map_feature_matches(array $feature, string $level, string $kabId, string $kecId, string $desaId, string $subslsId): bool
{
    $props = $feature['properties'] ?? [];
    if ($level === 'kecamatan' && $kabId !== '' && map_prop_id($props, 'kabupaten') !== $kabId) {
        return false;
    }
    if ($level === 'desa' && $kecId !== '' && map_prop_id($props, 'kecamatan') !== $kecId) {
        return false;
    }
    if ($level === 'subsls' && $desaId !== '' && map_prop_id($props, 'desa') !== $desaId) {
        return false;
    }
    if ($level === 'subsls' && $subslsId !== '' && map_prop_id($props, 'subsls') !== $subslsId) {
        return false;
    }
    return true;
}

function map_stream_filtered_features(string $path, string $level, string $kabId, string $kecId, string $desaId, string $subslsId): void
{
    $handle = fopen($path, 'rb');
    if (!$handle) {
        map_json_error(500, 'File peta gagal dibuka');
    }

    header('Content-Type: application/geo+json; charset=utf-8');
    header('Cache-Control: public, max-age=3600');
    echo '{"type":"FeatureCollection","features":[';

    $buffer = '';
    $insideFeatures = false;
    $insideFeature = false;
    $feature = '';
    $depth = 0;
    $inString = false;
    $escaped = false;
    $first = true;

    while (!feof($handle)) {
        $chunk = fread($handle, 65536);
        if ($chunk === false) {
            break;
        }

        if (!$insideFeatures) {
            $buffer .= $chunk;
            $featuresPos = strpos($buffer, '"features"');
            if ($featuresPos === false) {
                $buffer = substr($buffer, -32);
                continue;
            }
            $bracketPos = strpos($buffer, '[', $featuresPos);
            if ($bracketPos === false) {
                $buffer = substr($buffer, $featuresPos);
                continue;
            }
            $insideFeatures = true;
            $chunk = substr($buffer, $bracketPos + 1);
            $buffer = '';
        }

        $length = strlen($chunk);
        for ($i = 0; $i < $length; $i++) {
            $char = $chunk[$i];
            if (!$insideFeature) {
                if ($char === '{') {
                    $insideFeature = true;
                    $feature = '{';
                    $depth = 1;
                    $inString = false;
                    $escaped = false;
                } elseif ($char === ']') {
                    break 2;
                }
                continue;
            }

            $feature .= $char;
            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === '"') {
                    $inString = false;
                }
                continue;
            }

            if ($char === '"') {
                $inString = true;
            } elseif ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    $decoded = json_decode($feature, true);
                    if (is_array($decoded) && map_feature_matches($decoded, $level, $kabId, $kecId, $desaId, $subslsId)) {
                        echo $first ? $feature : ',' . $feature;
                        $first = false;
                    }
                    $insideFeature = false;
                    $feature = '';
                }
            }
        }
    }

    fclose($handle);
    echo ']}';
}

map_stream_filtered_features($path, $level, $kabId, $kecId, $desaId, $subslsId);
