<?php
/**
 * SIMKlinik — PCare BPJS Health Web Service Helper (FKTP)
 * Mendukung Spesifikasi Web Service BPJS Kesehatan v4.0 / REST API PCare
 */

if (!defined('DB_NAME')) {
    require_once dirname(__DIR__) . '/config.php';
}

class PCareService {

    /**
     * Ambil konfigurasi kredensial PCare dari mlite_settings
     */
    public static function getConfig(): array {
        global $conn;
        $cfg = [];
        $res = $conn->query("SELECT module, field, value FROM mlite_settings WHERE module IN ('icare', 'pcare')");
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $cfg[$r['module']][$r['field']] = $r['value'];
            }
        }

        $cons_id     = $cfg['pcare']['consumerID'] ?? ($cfg['pcare']['consid'] ?? ($cfg['icare']['consid'] ?? ''));
        $secret_key  = $cfg['pcare']['consumerSecret'] ?? ($cfg['pcare']['secretkey'] ?? ($cfg['icare']['secretkey'] ?? ''));
        $user_key    = $cfg['pcare']['consumerUserKey'] ?? ($cfg['pcare']['userkey'] ?? ($cfg['icare']['userkey'] ?? ''));
        $username    = $cfg['pcare']['usernamePcare'] ?? ($cfg['pcare']['username'] ?? ($cfg['icare']['usernameICare'] ?? ''));
        $password    = $cfg['pcare']['passwordPcare'] ?? ($cfg['pcare']['password'] ?? ($cfg['icare']['passwordICare'] ?? ''));
        $kd_aplikasi = $cfg['pcare']['kd_aplikasi'] ?? ($cfg['icare']['kd_aplikasi'] ?? '095');
        $kode_ppk    = $cfg['pcare']['kode_fktp'] ?? ($cfg['pcare']['kode_faskes'] ?? ($cfg['icare']['kode_faskes'] ?? '0169B012'));
        $base_url    = $cfg['pcare']['PCareApiUrl'] ?? ($cfg['pcare']['urlPCare'] ?? ($cfg['icare']['urlPCare'] ?? 'https://apijkn.bpjs-kesehatan.go.id/pcare-rest'));

        return [
            'cons_id'     => $cons_id,
            'secret_key'  => $secret_key,
            'user_key'    => $user_key,
            'username'    => $username,
            'password'    => $password,
            'kd_aplikasi' => $kd_aplikasi,
            'kode_ppk'    => $kode_ppk,
            'base_url'    => rtrim($base_url, '/'),
            'is_valid'    => !empty($cons_id) && !empty($secret_key) && !empty($user_key)
        ];
    }

    /**
     * Generate Header Autentikasi Standar BPJS (HMAC-SHA256 & Basic Auth)
     */
    public static function generateHeaders(string $method = 'GET'): array {
        $cfg = self::getConfig();
        $cons_id     = $cfg['cons_id'];
        $secret_key  = $cfg['secret_key'];
        $user_key    = $cfg['user_key'];
        $username    = $cfg['username'];
        $password    = $cfg['password'];
        $kd_aplikasi = $cfg['kd_aplikasi'];

        date_default_timezone_set('UTC');
        $timestamp = strval(time() - strtotime('1970-01-01 00:00:00'));
        date_default_timezone_set('Asia/Jakarta');

        $signature = base64_encode(hash_hmac('sha256', $cons_id . '&' . $timestamp, $secret_key, true));
        $auth_hash = base64_encode("{$username}:{$password}:{$kd_aplikasi}");

        // BPJS PCare REST API TrustMark standard: POST/PUT menggunakan Content-Type: text/plain
        $contentType = in_array(strtoupper($method), ['POST', 'PUT']) ? 'text/plain' : 'application/json; charset=utf-8';

        return [
            'headers' => [
                "X-cons-id: {$cons_id}",
                "X-timestamp: {$timestamp}",
                "X-signature: {$signature}",
                "X-authorization: Basic {$auth_hash}",
                "user_key: {$user_key}",
                "Content-Type: {$contentType}",
                "Accept: application/json"
            ],
            'timestamp' => $timestamp,
            'key'       => $cons_id . $secret_key . $timestamp
        ];
    }

    /**
     * Kirim HTTP Request cURL ke Server PCare BPJS
     */
    public static function request(string $endpoint, string $method = 'GET', array $payload = []): array {
        $cfg = self::getConfig();
        if (!$cfg['is_valid']) {
            return [
                'metadata' => ['code' => 400, 'message' => 'Kredensial PCare belum lengkap (ConsID, SecretKey, UserKey).'],
                'response' => null
            ];
        }

        $auth = self::generateHeaders($method);
        $url  = $cfg['base_url'] . '/' . ltrim($endpoint, '/');

        $start_time = microtime(true);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 6);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $auth['headers']);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        $raw         = curl_exec($ch);
        $err         = curl_error($ch);
        $http_code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $duration_ms = round((microtime(true) - $start_time) * 1000);
        curl_close($ch);

        if ($err) {
            return [
                'metadata' => ['code' => 500, 'message' => 'Koneksi cURL gagal: ' . $err],
                'response' => null,
                'debug'    => [
                    'url'         => $url,
                    'method'      => $method,
                    'http_code'   => $http_code,
                    'duration_ms' => $duration_ms,
                    'headers'     => $auth['headers'],
                    'error'       => $err
                ]
            ];
        }

        $json = json_decode($raw, true);
        if (!$json) {
            $clean_msg = strip_tags($raw);
            $clean_msg = preg_replace('/\s+/', ' ', trim($clean_msg));
            return [
                'metadata' => ['code' => $http_code ?: 500, 'message' => 'Respon BPJS [Code ' . $http_code . ']: ' . (substr($clean_msg, 0, 150) ?: 'Format respon tidak valid')],
                'response' => null,
                'debug'    => [
                    'url'          => $url,
                    'method'       => $method,
                    'http_code'    => $http_code,
                    'duration_ms'  => $duration_ms,
                    'headers'      => $auth['headers'],
                    'raw_response' => $raw
                ]
            ];
        }

        // Normalisasi format metaData / metadata
        $meta = $json['metaData'] ?? ($json['metadata'] ?? ['code' => $http_code ?: 200, 'message' => 'OK']);
        $json['metadata'] = $meta;
        $json['metaData'] = $meta;

        // Dekripsi Payload jika response terenkripsi string
        if (isset($json['response']) && is_string($json['response']) && !empty($json['response'])) {
            $decrypted = self::decryptResponse($json['response'], $auth['key']);
            $decoded   = json_decode($decrypted, true);
            $json['response'] = $decoded !== null ? $decoded : $decrypted;
        }

        $json['debug'] = [
            'url'          => $url,
            'method'       => $method,
            'http_code'    => $http_code,
            'duration_ms'  => $duration_ms,
            'timestamp'    => $auth['timestamp'],
            'headers'      => $auth['headers'],
            'raw_response' => $raw,
            'payload'      => $payload
        ];

        return $json;
    }

    /**
     * Dekripsi AES-256-CBC + LZString untuk response BPJS
     */
    public static function decryptResponse(string $string, string $key): string {
        try {
            $encrypt_method = 'AES-256-CBC';
            $key_hash = hex2bin(hash('sha256', $key));
            $iv = substr(hex2bin(hash('sha256', $key)), 0, 16);
            $output = openssl_decrypt(base64_decode($string), $encrypt_method, $key_hash, OPENSSL_RAW_DATA, $iv);
            if ($output) {
                $decompressed = self::decompressLZString($output);
                return $decompressed ?: $output;
            }
            return $string;
        } catch (\Exception $e) {
            return $string;
        }
    }

    /**
     * LZString Decompressor (Standar BPJS TrustMark)
     */
    public static function decompressLZString(string $input): string {
        if (empty($input)) return "";
        $input = str_replace(' ', '+', $input);
        $keyStrUriSafe = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+-$";
        $length = strlen($input);
        
        $dictionary = [];
        $enlargeIn = 4;
        $dictSize = 4;
        $numBits = 3;
        $entry = "";
        $result = [];
        
        $getNextValue = function($index) use ($input, $keyStrUriSafe) {
            if ($index >= strlen($input)) return 0;
            $pos = strpos($keyStrUriSafe, $input[$index]);
            return $pos !== false ? $pos : 0;
        };

        $data = (object)[
            'val' => $getNextValue(0),
            'position' => 32,
            'index' => 1
        ];

        for ($i = 0; $i < 3; $i++) {
            $dictionary[$i] = chr($i);
        }

        $bits = 0; $maxpower = 4; $power = 1;
        while ($power != $maxpower) {
            $resb = $data->val & $data->position;
            $data->position >>= 1;
            if ($data->position == 0) {
                $data->position = 32;
                $data->val = $getNextValue($data->index++);
            }
            $bits |= ($resb > 0 ? 1 : 0) * $power;
            $power <<= 1;
        }

        $next = $bits;
        switch ($next) {
            case 0:
                $bits = 0; $maxpower = 256; $power = 1;
                while ($power != $maxpower) {
                    $resb = $data->val & $data->position;
                    $data->position >>= 1;
                    if ($data->position == 0) {
                        $data->position = 32;
                        $data->val = $getNextValue($data->index++);
                    }
                    $bits |= ($resb > 0 ? 1 : 0) * $power;
                    $power <<= 1;
                }
                $c = chr($bits);
                break;
            case 1:
                $bits = 0; $maxpower = 65536; $power = 1;
                while ($power != $maxpower) {
                    $resb = $data->val & $data->position;
                    $data->position >>= 1;
                    if ($data->position == 0) {
                        $data->position = 32;
                        $data->val = $getNextValue($data->index++);
                    }
                    $bits |= ($resb > 0 ? 1 : 0) * $power;
                    $power <<= 1;
                }
                $c = chr($bits);
                break;
            case 2:
                return "";
        }

        $dictionary[3] = $c;
        $w = $c;
        $result[] = $c;

        while (true) {
            if ($data->index > $length) {
                return "";
            }

            $bits = 0; $maxpower = 1 << $numBits; $power = 1;
            while ($power != $maxpower) {
                $resb = $data->val & $data->position;
                $data->position >>= 1;
                if ($data->position == 0) {
                    $data->position = 32;
                    $data->val = $getNextValue($data->index++);
                }
                $bits |= ($resb > 0 ? 1 : 0) * $power;
                $power <<= 1;
            }

            switch ($c = $bits) {
                case 0:
                    $bits = 0; $maxpower = 256; $power = 1;
                    while ($power != $maxpower) {
                        $resb = $data->val & $data->position;
                        $data->position >>= 1;
                        if ($data->position == 0) {
                            $data->position = 32;
                            $data->val = $getNextValue($data->index++);
                        }
                        $bits |= ($resb > 0 ? 1 : 0) * $power;
                        $power <<= 1;
                    }
                    $dictionary[$dictSize++] = chr($bits);
                    $c = $dictSize - 1;
                    $enlargeIn--;
                    break;
                case 1:
                    $bits = 0; $maxpower = 65536; $power = 1;
                    while ($power != $maxpower) {
                        $resb = $data->val & $data->position;
                        $data->position >>= 1;
                        if ($data->position == 0) {
                            $data->position = 32;
                            $data->val = $getNextValue($data->index++);
                        }
                        $bits |= ($resb > 0 ? 1 : 0) * $power;
                        $power <<= 1;
                    }
                    $dictionary[$dictSize++] = chr($bits);
                    $c = $dictSize - 1;
                    $enlargeIn--;
                    break;
                case 2:
                    return implode('', $result);
            }

            if ($enlargeIn == 0) {
                $enlargeIn = 1 << $numBits;
                $numBits++;
            }

            if (isset($dictionary[$c])) {
                $entry = $dictionary[$c];
            } else {
                if ($c === $dictSize) {
                    $entry = $w . $w[0];
                } else {
                    return "";
                }
            }
            $result[] = $entry;

            $dictionary[$dictSize++] = $w . $entry[0];
            $enlargeIn--;

            $w = $entry;

            if ($enlargeIn == 0) {
                $enlargeIn = 1 << $numBits;
                $numBits++;
            }
        }
    }

    // ─────────────────────────────────────────────────────────────
    // METHOD-METHOD API RESMI PCARE BPJS
    // ─────────────────────────────────────────────────────────────

    /**
     * 1. Cek Peserta BPJS berdasarkan No Kartu BPJS (13 digit)
     */
    public static function getPesertaByNoKartu(string $noKartu): array {
        return self::request("peserta/{$noKartu}", 'GET');
    }

    /**
     * 2. Cek Peserta BPJS berdasarkan NIK (16 digit)
     */
    public static function getPesertaByNIK(string $nik): array {
        return self::request("peserta/nik/{$nik}", 'GET');
    }

    /**
     * 3. Ambil Referensi Diagnosa ICD-10
     */
    public static function getDiagnosa(string $keyword, int $start = 0, int $limit = 15): array {
        return self::request("diagnosa/{$keyword}/{$start}/{$limit}", 'GET');
    }

    /**
     * 4. Ambil Referensi Poli FKTP
     */
    public static function getPoli(int $start = 0, int $limit = 50): array {
        return self::request("poli/fktp/{$start}/{$limit}", 'GET');
    }

    /**
     * 5. Ambil Referensi Dokter FKTP
     */
    public static function getDokter(int $start = 0, int $limit = 50): array {
        return self::request("dokter/{$start}/{$limit}", 'GET');
    }

    /**
     * 6. Ambil Data Pendaftaran Pasien per Tanggal
     */
    public static function getPendaftaran(string $tglDaftar, int $start = 0, int $limit = 15): array {
        $tgl_formatted = date('d-m-Y', strtotime($tglDaftar));
        return self::request("pendaftaran/tglDaftar/{$tgl_formatted}/{$start}/{$limit}", 'GET');
    }

    /**
     * 7. Tambah Pendaftaran Pasien ke PCare (POST /pendaftaran)
     */
    public static function tambahPendaftaran(array $data): array {
        return self::request("pendaftaran", 'POST', $data);
    }

    /**
     * 8. Hapus Pendaftaran Pasien di PCare (DELETE /pendaftaran/peserta/...)
     */
    public static function hapusPendaftaran(string $noKartu, string $tglDaftar, string $noUrut, string $kdPoli): array {
        $tgl_formatted = date('d-m-Y', strtotime($tglDaftar));
        return self::request("pendaftaran/peserta/{$noKartu}/tglDaftar/{$tgl_formatted}/noUrut/{$noUrut}/kdPoli/{$kdPoli}", 'DELETE');
    }

    /**
     * 9. Tambah Kunjungan Pasien ke PCare (POST /kunjungan)
     */
    public static function tambahKunjungan(array $data): array {
        return self::request("kunjungan", 'POST', $data);
    }

    /**
     * 10. Update Kunjungan Pasien di PCare (PUT /kunjungan)
     */
    public static function updateKunjungan(array $data): array {
        return self::request("kunjungan", 'PUT', $data);
    }

    /**
     * 11. Hapus Kunjungan Pasien di PCare (DELETE /kunjungan/{noKunjungan})
     */
    public static function hapusKunjungan(string $noKunjungan): array {
        return self::request("kunjungan/{$noKunjungan}", 'DELETE');
    }

    /**
     * 12. Tambah Tindakan Pasien ke PCare (POST /tindakan)
     */
    public static function tambahTindakan(array $data): array {
        return self::request("tindakan", 'POST', $data);
    }

    /**
     * 13. Tambah Obat Pasien ke PCare (POST /obat/kunjungan)
     */
    public static function tambahObat(array $data): array {
        return self::request("obat/kunjungan", 'POST', $data);
    }

    /**
     * 14. Ambil Riwayat Kunjungan Peserta (GET /kunjungan/peserta/{noKartu})
     */
    public static function getRiwayatKunjunganPeserta(string $noKartu): array {
        return self::request("kunjungan/peserta/{$noKartu}", 'GET');
    }

    /**
     * 15. Ambil Referensi Kesadaran (GET /kesadaran)
     */
    public static function getKesadaran(): array {
        return self::request("kesadaran", 'GET');
    }

    /**
     * 16. Ambil Referensi Status Pulang (GET /statuspulang/rawatInap/{0|1})
     */
    public static function getStatusPulang(bool $isRawatInap = false): array {
        $flag = $isRawatInap ? 'true' : 'false';
        return self::request("statuspulang/rawatInap/{$flag}", 'GET');
    }

    /**
     * 17. Ambil Referensi Provider / Profil Faskes (GET /provider)
     */
    public static function getProvider(): array {
        return self::request("provider", 'GET');
    }

    /**
     * 18. Ambil Referensi Spesialis Rujukan (GET /spesialis)
     */
    public static function getSpesialis(): array {
        return self::request("spesialis", 'GET');
    }

    /**
     * 19. Ambil Referensi SubSpesialis (GET /spesialis/subspesialis/{kdSpesialis})
     */
    public static function getSubSpesialis(string $kdSpesialis): array {
        return self::request("spesialis/subspesialis/{$kdSpesialis}", 'GET');
    }

    /**
     * 20. Ambil Referensi Sarana Faskes (GET /spesialis/sarana)
     */
    public static function getSarana(): array {
        return self::request("spesialis/sarana", 'GET');
    }

    /**
     * 21. Cari Faskes Rujukan Subspesialis (GET /faskes/subspesialis/...)
     */
    public static function getFaskesRujukan(string $kdSubSpesialis, string $kdSarana, string $tglRujuk): array {
        $tgl = date('d-m-Y', strtotime($tglRujuk));
        return self::request("faskes/subspesialis/{$kdSubSpesialis}/sarana/{$kdSarana}/tglRujuk/{$tgl}", 'GET');
    }

    /**
     * 22. Ambil Referensi Rujukan Khusus (GET /spesialis/khusus)
     */
    public static function getKhusus(): array {
        return self::request("spesialis/khusus", 'GET');
    }

    /**
     * 23. Ambil Referensi TACC (GET /spesialis/tacc)
     */
    public static function getTacc(): array {
        return self::request("spesialis/tacc", 'GET');
    }

    /**
     * 24. Cari Faskes Rujukan Khusus (GET /faskes/khusus/...)
     */
    public static function getFaskesKhusus(string $kdKhusus, string $kdSubSpesialis, string $kdSarana, string $tglRujuk): array {
        $tgl = date('d-m-Y', strtotime($tglRujuk));
        return self::request("faskes/khusus/{$kdKhusus}/subspesialis/{$kdSubSpesialis}/sarana/{$kdSarana}/tglRujuk/{$tgl}", 'GET');
    }

    /**
     * 25. Ambil Detail Lembar Rujukan berdasarkan No. Kunjungan (GET /kunjungan/rujukan/{noKunjungan})
     */
    public static function getRujukanByNoKunjungan(string $noKunjungan): array {
        return self::request("kunjungan/rujukan/{$noKunjungan}", 'GET');
    }

    /**
     * 26. Kirim / Simpan Kunjungan Rujukan ke PCare (POST /kunjungan)
     */
    public static function kirimKunjunganRujukan(array $data): array {
        return self::request("kunjungan", 'POST', $data);
    }
}

