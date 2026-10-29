<?php
// Called by ceklatlong.php only. No database or respondent data is used.
class LatlongSearchException extends RuntimeException {}

function latlong_normalize_address(string $query): string
{
    $query = preg_replace('/\s+/u', ' ', trim($query));
    $query = preg_replace('/\b(?:jl|jln)(?:\.\s*|\s+)/iu', 'Jalan ', $query);
    return preg_replace('/\bgg(?:\.\s*|\s+)/iu', 'Gang ', $query);
}

function latlong_search_address($query): array
{
    if (!function_exists('mb_strlen') || !function_exists('curl_init')) {
        throw new LatlongSearchException('Pencarian alamat belum siap: ekstensi PHP cURL dan mbstring harus aktif. Hubungi pengelola aplikasi.');
    }
    if (!is_string($query) || strlen($query) > 200 || mb_strlen(trim($query)) < 3) {
        throw new InvalidArgumentException('Isi nama jalan atau tempat dan kota, minimal 3 karakter.');
    }
    $query = latlong_normalize_address($query);
    $gangSearch = preg_match('/^gang\s+/iu', $query) === 1;
    $directory = __DIR__ . '/cache/latlong/search';
    if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new LatlongSearchException('Folder cache pencarian tidak dapat dibuat. Pengelola perlu memberi izin tulis PHP pada cache/latlong/search.');
    }
    // Separate cache namespace so older nationwide results cannot be reused.
    $cache = $directory . '/' . hash('sha256', 'kaltim-v2:' . mb_strtolower($query)) . '.json';
    if (is_file($cache) && filemtime($cache) > time() - 604800) {
        $cached = json_decode(@file_get_contents($cache) ?: '', true);
        if (is_array($cached) && isset($cached['results']) && is_array($cached['results'])) return $cached;
    }
    $lock = @fopen($directory . '/request.lock', 'c+');
    if (!$lock) throw new LatlongSearchException('Cache pencarian tidak dapat ditulis. Pengelola perlu memeriksa izin tulis cache/latlong/search.');
    if (!flock($lock, LOCK_EX | LOCK_NB)) {
        fclose($lock);
        throw new LatlongSearchException('Ada pencarian alamat lain yang sedang diproses. Coba beberapa saat lagi.', 429);
    }
    try {
        $last = (float)stream_get_contents($lock);
        if (microtime(true) - $last < 1.1) throw new LatlongSearchException('Tunggu dua detik sebelum mencari lagi.', 429);
        rewind($lock); ftruncate($lock, 0); fwrite($lock, (string)microtime(true)); fflush($lock);
        $url = 'https://photon.komoot.io/api/?' . http_build_query(['q' => $query, 'limit' => 20, 'lat' => -0.5, 'lon' => 117.15]);
        $curl = curl_init($url);
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 15,
            CURLOPT_USERAGENT => 'DailySE2026-SubSLS-Lookup/1.0 (https://pesse2026.dataetam.com/ceklatlong)',
            CURLOPT_HTTPHEADER => ['Accept: application/json']]);
        $body = curl_exec($curl); $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlCode = curl_errno($curl); $curlMessage = curl_error($curl);
        curl_close($curl);
        if ($body === false) {
            error_log('Cek latlong Photon cURL ' . $curlCode . ': ' . $curlMessage);
            $reason = match ($curlCode) {
                5, 6 => 'Server gagal menemukan alamat layanan Photon (DNS).',
                7 => 'Server tidak dapat terhubung ke Photon. Periksa akses HTTPS keluar dari server.',
                28 => 'Layanan Photon belum merespons dalam 15 detik. Coba lagi beberapa saat.',
                35, 51, 58, 60, 77 => 'Koneksi HTTPS ke Photon gagal diverifikasi. Pengelola perlu memeriksa sertifikat CA pada server.',
                default => 'Koneksi server ke layanan Photon gagal. Kode koneksi: ' . $curlCode . '.',
            };
            throw new LatlongSearchException($reason);
        }
        if ($status !== 200) {
            throw new LatlongSearchException($status === 429
                ? 'Batas permintaan layanan Photon sedang tercapai. Coba lagi nanti.'
                : 'Layanan Photon mengembalikan HTTP ' . (int)$status . '. Coba lagi nanti atau hubungi pengelola.', $status === 429 ? 429 : 503);
        }
        $data = json_decode($body, true);
        if (!isset($data['features']) || !is_array($data['features'])) throw new LatlongSearchException('Layanan Photon mengirim respons yang tidak valid. Coba lagi nanti.');
        $results = [];
        foreach ($data['features'] as $feature) {
            $p = $feature['properties'] ?? []; $c = $feature['geometry']['coordinates'] ?? [];
            if (strtoupper($p['countrycode'] ?? '') !== 'ID' || count($c) < 2) continue;
            $province = mb_strtolower(trim($p['state'] ?? ''));
            if (!in_array($province, ['kalimantan timur', 'east kalimantan'], true)) continue;
            // An explicit gang search should return streets, not similarly named worship places.
            if ($gangSearch && ($p['type'] ?? '') !== 'street' && ($p['osm_key'] ?? '') !== 'highway') continue;
            $parts = array_values(array_unique(array_filter([$p['name'] ?? '', $p['street'] ?? '', $p['district'] ?? '', $p['city'] ?? $p['county'] ?? '', $p['state'] ?? '', $p['country'] ?? ''])));
            $results[] = ['label' => implode(', ', $parts), 'lat' => (float)$c[1], 'lng' => (float)$c[0]];
        }
        $answer = ['results' => $results];
        if (@file_put_contents($cache, json_encode($answer, JSON_THROW_ON_ERROR), LOCK_EX) === false) {
            error_log('Cek latlong: hasil Photon berhasil, tetapi cache pencarian gagal disimpan.');
        }
        return $answer;
    } finally { flock($lock, LOCK_UN); fclose($lock); }
}
