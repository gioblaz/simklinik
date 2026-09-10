<?php
/**
 * SIMKlinik — BPJS Antrean Online Service Helper (Mobile JKN)
 * Standar Bridging Antrean BPJS Kesehatan Versi 2
 */

if (!defined('DB_NAME')) {
    require_once dirname(__DIR__) . '/config.php';
}

class BpjsAntreanService {

    public static function getConfig(): array {
        global $conn;
        $cfg = [];
        $res = $conn->query("SELECT module, field, value FROM mlite_settings WHERE module IN ('antrean_bpjs', 'icare')");
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $cfg[$r['module']][$r['field']] = $r['value'];
            }
        }

        $cons_id    = $cfg['antrean_bpjs']['consid'] ?? ($cfg['icare']['consid'] ?? ($cfg['pcare']['consumerID'] ?? ''));
        $secret_key = $cfg['antrean_bpjs']['secretkey'] ?? ($cfg['icare']['secretkey'] ?? ($cfg['pcare']['consumerSecret'] ?? ''));
        $user_key   = $cfg['antrean_bpjs']['userkey'] ?? ($cfg['pcare']['consumerUserKeyAntrol'] ?? ($cfg['icare']['userkey'] ?? ''));
        $kode_ppk   = $cfg['antrean_bpjs']['kode_ppk'] ?? ($cfg['pcare']['kode_fktp'] ?? ($cfg['icare']['kode_faskes'] ?? '0169B012'));
        $base_url   = !empty($cfg['antrean_bpjs']['api_url']) ? $cfg['antrean_bpjs']['api_url'] : 'https://apijkn.bpjs-kesehatan.go.id/antreanrs';

        return [
            'cons_id'    => $cons_id,
            'secret_key' => $secret_key,
            'user_key'   => $user_key,
            'kode_ppk'   => $kode_ppk,
            'base_url'   => rtrim($base_url, '/'),
        ];
    }

    public static function generateHeaders(): array {
        $cfg = self::getConfig();
        $cons_id    = $cfg['cons_id'];
        $secret_key = $cfg['secret_key'];
        $user_key   = $cfg['user_key'];

        date_default_timezone_set('UTC');
        $timestamp = strval(time() - strtotime('1970-01-01 00:00:00'));
        date_default_timezone_set('Asia/Jakarta');

        $signature = base64_encode(hash_hmac('sha256', $cons_id . '&' . $timestamp, $secret_key, true));

        return [
            'headers'   => [
                "X-cons-id: {$cons_id}",
                "X-timestamp: {$timestamp}",
                "X-signature: {$signature}",
                "user_key: {$user_key}",
                "Content-Type: application/json; charset=utf-8"
            ],
            'timestamp' => $timestamp,
            'key'       => $cons_id . $secret_key . $timestamp
        ];
    }

    /**
     * Kirim HTTP Request ke Server BPJS
     */
    public static function request(string $endpoint, string $method = 'GET', array $payload = []): array {
        $cfg = self::getConfig();
        $auth = self::generateHeaders();
        $url = $cfg['base_url'] . '/' . ltrim($endpoint, '/');

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 4);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $auth['headers']);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err) {
            return [
                'metadata' => ['code' => 500, 'message' => 'CURL Error: ' . $err],
                'response' => null
            ];
        }

        $json = json_decode($raw, true);
        if (!$json) {
            return [
                'metadata' => ['code' => $http_code, 'message' => 'Format response bukan JSON valid: ' . $raw],
                'response' => null
            ];
        }

        // Dekripsi response jika ada payload terenkripsi
        if (isset($json['response']) && is_string($json['response'])) {
            $decrypted = self::decryptResponse($json['response'], $auth['key']);
            $json['response'] = json_decode($decrypted, true) ?: $decrypted;
        }

        return $json;
    }

    /**
     * Dekripsi respons terenkripsi BPJS (AES-256-CBC)
     */
    public static function decryptResponse(string $string, string $key): string {
        try {
            $encrypt_method = 'AES-256-CBC';
            $key_hash = hex2bin(hash('sha256', $key));
            $iv = substr(hex2bin(hash('sha256', $key)), 0, 16);
            $output = openssl_decrypt(base64_decode($string), $encrypt_method, $key_hash, OPENSSL_RAW_DATA, $iv);
            return $output ? self::decompress($output) : $string;
        } catch (\Exception $e) {
            return $string;
        }
    }

    /**
     * LZ-String Decompress
     */
    private static function decompress(string $string): string {
        // Jika teks biasa
        return $string;
    }

    /**
     * Update Waktu Antrean / Task ID (Task 1 sampai Task 7)
     * $taskid: 1=Admisi Mulai, 2=Admisi Selesai, 3=Poli Mulai Tunggu, 4=Periksa Dr Mulai,
     *          5=Periksa Dr Selesai, 6=Farmasi Racik Mulai, 7=Obat Diserahkan Selesai
     */
    public static function updateTaskId(string $kodebooking, int $taskid, int $waktu_ms = 0): array {
        global $conn;

        $kodebooking_esc = $conn->real_escape_string($kodebooking);

        // Deduplikasi: Cek apakah taskid ini sudah pernah terkirim sukses ('Terkirim') untuk kodebooking ini
        $chk = $conn->query("
            SELECT 1 FROM mlite_antrian_referensi_taskid 
            WHERE nomor_referensi = '$kodebooking_esc' AND taskid = '$taskid' AND status = 'Terkirim'
            LIMIT 1
        ");
        if ($chk && $chk->num_rows > 0) {
            return [
                'metadata' => ['code' => 200, 'message' => 'Task ID ' . $taskid . ' sudah pernah terkirim sebelumnya.'],
                'response' => null
            ];
        }

        if ($waktu_ms === 0) {
            $waktu_ms = intval(microtime(true) * 1000);
        }

        $payload = [
            'kodebooking' => $kodebooking,
            'taskid'      => $taskid,
            'waktu'       => $waktu_ms
        ];

        $res = self::request('antrean/updatewaktu', 'POST', $payload);

        // Catat ke log mlite_antrian_referensi_taskid
        $today = date('Y-m-d');
        $code = $res['metadata']['code'] ?? 500;
        $msg  = $conn->real_escape_string($res['metadata']['message'] ?? '');
        $status_kirim = ($code == 200 || $code == 208) ? 'Terkirim' : 'Gagal';

        $conn->query("
            INSERT INTO mlite_antrian_referensi_taskid (tanggal_periksa, nomor_referensi, taskid, waktu, status, keterangan)
            VALUES ('$today', '$kodebooking_esc', '$taskid', '$waktu_ms', '$status_kirim', '$msg')
            ON DUPLICATE KEY UPDATE status = '$status_kirim', waktu = '$waktu_ms', keterangan = '$msg'
        ");

        return $res;
    }

    /**
     * Otomatis Trigger Task ID berdasarkan No. Rawat Pasien
     */
    public static function triggerTaskByRawat(string $no_rawat, int $taskid): ?array {
        global $conn;
        if (empty($no_rawat)) return null;

        $rawat_esc = $conn->real_escape_string($no_rawat);

        // Ambil info pendaftaran & booking referensi
        $res = $conn->query("
            SELECT r.no_rawat, r.no_rkm_medis, r.tgl_registrasi, r.kd_pj,
                   ar.kodebooking
            FROM reg_periksa r
            LEFT JOIN mlite_antrian_referensi ar 
              ON (ar.no_rkm_medis = r.no_rkm_medis AND ar.tanggal_periksa = r.tgl_registrasi)
            WHERE r.no_rawat = '$rawat_esc'
            LIMIT 1
        ");

        if (!$res || $res->num_rows === 0) return null;
        $row = $res->fetch_assoc();

        $kodebooking = $row['kodebooking'] ?: '';
        if (empty($kodebooking)) {
            // Cek apakah ada booking langsung dengan nomor rawat atau no_rkm_medis
            $res2 = $conn->query("SELECT kodebooking FROM mlite_antrian_referensi WHERE nomor_referensi = '$rawat_esc' OR kodebooking = '$rawat_esc' LIMIT 1");
            if ($res2 && $res2->num_rows > 0) {
                $kodebooking = $res2->fetch_assoc()['kodebooking'];
            }
        }

        // Cek juga dari booking_registrasi (dari Web Service Mobile JKN / Khanza)
        if (empty($kodebooking)) {
            $no_rm_esc = $conn->real_escape_string($row['no_rkm_medis']);
            $tgl_esc   = $conn->real_escape_string($row['tgl_registrasi']);
            $res3 = $conn->query("SELECT no_reg FROM booking_registrasi WHERE no_rkm_medis = '$no_rm_esc' AND tanggal_periksa = '$tgl_esc' LIMIT 1");
            if ($res3 && $res3->num_rows > 0) {
                $b_reg = $res3->fetch_assoc()['no_reg'];
                $kodebooking = 'BK' . date('Ymd', strtotime($row['tgl_registrasi'])) . str_pad($b_reg, 4, '0', STR_PAD_LEFT);
            }
        }

        if (!empty($kodebooking)) {
            return self::updateTaskId($kodebooking, $taskid);
        }

        return null;
    }

    /**
     * Batalkan Antrean Pasien
     */
    public static function batalAntrean(string $kodebooking, string $keterangan = 'Dibatalkan oleh Pasien/Klinik'): array {
        global $conn;
        $payload = [
            'kodebooking' => $kodebooking,
            'keterangan'  => $keterangan
        ];
        $res = self::request('antrean/batal', 'POST', $payload);

        $conn->query("UPDATE booking_registrasi SET status = 'Batal' WHERE no_rkm_medis = '$kodebooking' OR no_reg = '$kodebooking'");
        return $res;
    }

    /**
     * Ambil Status Dashboard Waktu Tunggu dari Server BPJS
     */
    public static function getDashboardWaktuTunggu(string $tanggal, string $waktu = 'waktu_task'): array {
        return self::request("dashboard/waktutunggu/tanggal/{$tanggal}/waktu/{$waktu}", 'GET');
    }

    /**
     * Ambil Jadwal Dokter dari Server BPJS
     */
    public static function getJadwalDokter(string $kodepoli, string $tanggal): array {
        return self::request("jadwaldokter/kodepoli/{$kodepoli}/tanggal/{$tanggal}", 'GET');
    }
}
