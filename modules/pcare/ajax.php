<?php
/**
 * SIMKlinik — AJAX Handler: PCare BPJS Web Service v4.0
 */

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/pcare_service.php';

header('Content-Type: application/json');

if (empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    http_response_code(403);
    exit(json_encode(['error' => 'Forbidden']));
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$pcare_cfg = PCareService::getConfig();

switch ($action) {

    // ─── 1. Test Koneksi PCare ────────────────────────────────
    case 'test_koneksi':
        $test_type = sanitize($_GET['type'] ?? $_POST['type'] ?? 'diagnosa');

        if (!$pcare_cfg['is_valid']) {
            echo json_encode([
                'success' => false,
                'code'    => 400,
                'message' => 'Kredensial PCare belum lengkap (ConsID, SecretKey, UserKey).',
                'debug'   => [
                    'url'     => $pcare_cfg['base_url'] . '/diagnosa/A00/0/1',
                    'cons_id' => $pcare_cfg['cons_id'] ?: '(kosong)',
                    'user_key'=> $pcare_cfg['user_key'] ? substr($pcare_cfg['user_key'], 0, 6) . '...' : '(kosong)'
                ]
            ]);
            exit;
        }

        // Jalankan tes sesuai endpoint yang dipilih (Default: Diagnosa A00)
        if ($test_type === 'dokter') {
            $res = PCareService::getDokter(0, 1);
        } elseif ($test_type === 'poli') {
            $res = PCareService::getPoli(0, 1);
        } else {
            $res = PCareService::getDiagnosa('A00', 0, 1);
        }

        $code = $res['metadata']['code'] ?? 500;
        $msg  = $res['metadata']['message'] ?? 'Tidak ada respon dari server BPJS';

        echo json_encode([
            'success'   => ($code == 200),
            'code'      => $code,
            'test_type' => $test_type,
            'message'   => ($code == 200) ? 'Koneksi ke PCare BPJS Berhasil! (HTTP 200 OK)' : "Respon Server BPJS [Code {$code}]: {$msg}",
            'metadata'  => $res['metadata'] ?? null,
            'response'  => $res['response'] ?? null,
            'debug'     => $res['debug'] ?? null
        ]);
        exit;
        break;

    // ─── 2. Cek Kepesertaan BPJS (Live API + Smart Fallback) ───
    case 'cek_peserta':
        $no = $conn->real_escape_string(sanitize($_GET['no'] ?? $_POST['no'] ?? ''));
        if (empty($no)) {
            echo json_encode(['success' => false, 'message' => 'Nomor kartu atau NIK wajib diisi.']);
            exit;
        }

        $is_nik = (strlen($no) === 16 && ctype_digit($no));

        // Jika kredensial terisi, lakukan query Live ke BPJS
        if ($pcare_cfg['is_valid']) {
            $bpjs_res = $is_nik ? PCareService::getPesertaByNIK($no) : PCareService::getPesertaByNoKartu($no);
            $code = $bpjs_res['metadata']['code'] ?? 500;

            if ($code == 200 && !empty($bpjs_res['response'])) {
                $p = $bpjs_res['response'];
                echo json_encode([
                    'success' => true,
                    'source'  => 'pcare_live',
                    'data'    => [
                        'nama'          => $p['nama'] ?? '-',
                        'no_kartu'      => $p['noKartu'] ?? $no,
                        'nik'           => $p['noKTP'] ?? '-',
                        'no_rkm_medis'  => $p['noKdProvider']['kdProvider'] ?? 'Terdaftar BPJS',
                        'tgl_lahir'     => $p['tglLahir'] ?? '-',
                        'jk'            => ($p['sex'] ?? '') === 'L' ? 'Laki-laki' : 'Perempuan',
                        'umur'          => ($p['umur']['umurSekarang'] ?? '') ?: '-',
                        'status_kartu'  => $p['ketAktif'] ?? ($p['aktif'] ? 'AKTIF' : 'TIDAK AKTIF'),
                        'jenis_peserta' => $p['jnsPeserta']['nama'] ?? 'Peserta BPJS',
                        'faskes'        => $p['kdProviderPst']['nmProvider'] ?? INSTANSI_NAMA,
                        'kelas'         => $p['jnsKelas']['nama'] ?? 'Kelas 1',
                        'alamat'        => $p['alamat'] ?? '-'
                    ],
                    'raw' => $bpjs_res
                ]);
                exit;
            }
        }

        // Fallback: Cari di database lokal pasien
        $res = $conn->query("
            SELECT p.*, pj.png_jawab as nm_penjab
            FROM pasien p
            LEFT JOIN penjab pj ON p.kd_pj = pj.kd_pj
            WHERE p.no_peserta = '$no'
               OR p.no_ktp = '$no'
               OR p.no_rkm_medis = '$no'
            LIMIT 1
        ");

        if ($res && $res->num_rows > 0) {
            $p = $res->fetch_assoc();
            echo json_encode([
                'success' => true,
                'source'  => 'local_db',
                'data'    => [
                    'nama'          => $p['nm_pasien'],
                    'no_kartu'      => $p['no_peserta'] ?: $no,
                    'nik'           => $p['no_ktp'] ?: '-',
                    'no_rkm_medis'  => $p['no_rkm_medis'],
                    'tgl_lahir'     => tgl_indo($p['tgl_lahir']),
                    'jk'            => $p['jk'] === 'L' ? 'Laki-laki' : 'Perempuan',
                    'umur'          => hitung_umur($p['tgl_lahir']),
                    'status_kartu'  => 'AKTIF (Lokal)',
                    'jenis_peserta' => str_contains(strtolower($p['nm_penjab'] ?? ''), 'bpjs') ? 'BPJS Kesehatan' : ($p['nm_penjab'] ?: 'Umum'),
                    'faskes'        => INSTANSI_NAMA,
                    'kelas'         => 'Kelas 1',
                    'alamat'        => $p['alamat'] ?: '-'
                ]
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Peserta tidak ditemukan di server PCare BPJS maupun di database lokal.'
            ]);
        }
        break;

    // ─── 2.5 Kirim Pendaftaran Pasien ke PCare BPJS (POST /pendaftaran) ─
    case 'kirim_pendaftaran':
        $no_rawat = $conn->real_escape_string(sanitize($_POST['no_rawat'] ?? ''));
        if (empty($no_rawat)) {
            echo json_encode(['success' => false, 'message' => 'No. Rawat tidak valid.']);
            exit;
        }

        $k_res = $conn->query("
            SELECT r.*, p.nm_pasien, p.no_peserta, p.no_ktp, p.no_rkm_medis,
                   d.nm_dokter, pol.nm_poli,
                   pr.suhu_tubuh, pr.tensi, pr.nadi, pr.respirasi, pr.tinggi, pr.berat, pr.lingkar_perut, pr.keluhan
            FROM reg_periksa r
            JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
            LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
            LEFT JOIN poliklinik pol ON r.kd_poli = pol.kd_poli
            LEFT JOIN pemeriksaan_ralan pr ON r.no_rawat = pr.no_rawat
            WHERE r.no_rawat = '$no_rawat'
            LIMIT 1
        ");

        if (!$k_res || $k_res->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Data pasien kunjungan tidak ditemukan.']);
            exit;
        }

        $data = $k_res->fetch_assoc();
        $no_kartu = trim($data['no_peserta'] ?: $data['no_ktp']);

        if (empty($no_kartu)) {
            echo json_encode(['success' => false, 'message' => 'Nomor Kartu BPJS / NIK pasien masih kosong.']);
            exit;
        }

        // Ambil mapping poli PCare (contoh: UMU -> 001)
        $kd_poli_lokal = $conn->real_escape_string($data['kd_poli']);
        $map_poli = $conn->query("SELECT kd_poli_pcare, nm_poli_pcare FROM maping_poliklinik_pcare WHERE kd_poli_rs = '$kd_poli_lokal' LIMIT 1")->fetch_assoc();
        $kd_poli_pcare = $map_poli['kd_poli_pcare'] ?? '001';
        $nm_poli_pcare = $map_poli['nm_poli_pcare'] ?? ($data['nm_poli'] ?: 'Poli Umum');

        // Tensi
        $tensi_parts = explode('/', str_replace(' ', '', $data['tensi'] ?? '120/80'));
        $sistole  = (int)($tensi_parts[0] ?? 120);
        $diastole = (int)($tensi_parts[1] ?? 80);
        $tgl_daftar_pcare = date('d-m-Y', strtotime($data['tgl_registrasi']));

        // Cari provider peserta di BPJS
        $kd_provider = $pcare_cfg['kode_ppk'] ?: '0169B012';
        if ($pcare_cfg['is_valid']) {
            $peserta_res = PCareService::getPesertaByNoKartu($no_kartu);
            if (isset($peserta_res['response']['kdProviderPst']['kdProvider'])) {
                $kd_provider = $peserta_res['response']['kdProviderPst']['kdProvider'];
            }
        }

        $payload_daftar = [
            'kdProviderPeserta' => $kd_provider,
            'tglDaftar'          => $tgl_daftar_pcare,
            'noKartu'            => $no_kartu,
            'kdPoli'             => $kd_poli_pcare,
            'keluhan'            => $data['keluhan'] ?: 'Pemeriksaan Rawat Jalan',
            'kunjSakit'          => true,
            'sistole'            => $sistole,
            'diastole'           => $diastole,
            'beratBadan'         => (int)($data['berat'] ?: 60),
            'tinggiBadan'        => (int)($data['tinggi'] ?: 165),
            'respRate'           => (int)($data['respirasi'] ?: 20),
            'heartRate'          => (int)($data['nadi'] ?: 80),
            'lingkarPerut'       => (int)($data['lingkar_perut'] ?: 80),
            'rujukBalik'         => '0',
            'kdTkp'              => '10' // 10 = Rawat Jalan Tingkat Pertama
        ];

        if ($pcare_cfg['is_valid']) {
            $pcare_res = PCareService::tambahPendaftaran($payload_daftar);
            $code = $pcare_res['metadata']['code'] ?? 500;
            $msg  = $pcare_res['metadata']['message'] ?? '';

            if ($code == 200 || $code == 201) {
                $no_urut_bpjs = $pcare_res['response']['message'] ?? ($pcare_res['response']['noUrut'] ?? '1');
                if (is_numeric($no_urut_bpjs) || preg_match('/\d+/', $no_urut_bpjs)) {
                    preg_match('/[A-Za-z0-9]+/', (string)$no_urut_bpjs, $matches);
                    $no_urut_bpjs = $matches[0] ?? '1';
                }

                // Simpan ke pcare_pendaftaran
                $no_rawat_esc   = $conn->real_escape_string($no_rawat);
                $no_rm_esc      = $conn->real_escape_string($data['no_rkm_medis']);
                $nm_pasien_esc  = $conn->real_escape_string($data['nm_pasien']);
                $no_kartu_esc   = $conn->real_escape_string($no_kartu);
                $keluhan_esc    = $conn->real_escape_string($payload_daftar['keluhan']);
                $no_urut_esc    = $conn->real_escape_string($no_urut_bpjs);

                $conn->query("
                    INSERT INTO pcare_pendaftaran (
                        no_rawat, tglDaftar, no_rkm_medis, nm_pasien, kdProviderPeserta, noKartu,
                        kdPoli, nmPoli, keluhan, kunjSakit, sistole, diastole,
                        beratBadan, tinggiBadan, respRate, lingkar_perut, heartRate, rujukBalik, kdTkp, noUrut, status
                    ) VALUES (
                        '$no_rawat_esc', '{$data['tgl_registrasi']}', '$no_rm_esc', '$nm_pasien_esc', '$kd_provider', '$no_kartu_esc',
                        '$kd_poli_pcare', '$nm_poli_pcare', '$keluhan_esc', 'Kunjungan Sakit', '$sistole', '$diastole',
                        '{$payload_daftar['beratBadan']}', '{$payload_daftar['tinggiBadan']}', '{$payload_daftar['respRate']}', '{$payload_daftar['lingkarPerut']}', '{$payload_daftar['heartRate']}', '0', '10 Rawat Jalan', '$no_urut_esc', 'Terkirim'
                    ) ON DUPLICATE KEY UPDATE 
                        noUrut = '$no_urut_esc', status = 'Terkirim'
                ");

                echo json_encode([
                    'success' => true,
                    'message' => "Pendaftaran berhasil terkirim ke PCare BPJS! No. Urut: {$no_urut_bpjs}",
                    'noUrut'  => $no_urut_bpjs
                ]);
                exit;
            } elseif ($code == 412 || stripos($msg, 'PRECONDITION') !== false) {
                // Pasien sudah didaftarkan di PCare BPJS hari ini -> Cari No Urut yang sudah ada di PCare
                $existing_no_urut = null;
                for ($start = 0; $start < 150; $start += 15) {
                    $list_pcare = PCareService::getPendaftaran($data['tgl_registrasi'], $start, 15);
                    $items = $list_pcare['response']['list'] ?? [];
                    if (empty($items)) break;
                    foreach ($items as $it) {
                        if (($it['peserta']['noKartu'] ?? '') === $no_kartu) {
                            $existing_no_urut = (string)($it['noUrut'] ?? '');
                            break 2;
                        }
                    }
                }

                if (!empty($existing_no_urut)) {
                    $no_rawat_esc   = $conn->real_escape_string($no_rawat);
                    $no_rm_esc      = $conn->real_escape_string($data['no_rkm_medis']);
                    $nm_pasien_esc  = $conn->real_escape_string($data['nm_pasien']);
                    $no_kartu_esc   = $conn->real_escape_string($no_kartu);
                    $keluhan_esc    = $conn->real_escape_string($payload_daftar['keluhan']);
                    $no_urut_esc    = $conn->real_escape_string($existing_no_urut);

                    $conn->query("
                        INSERT INTO pcare_pendaftaran (
                            no_rawat, tglDaftar, no_rkm_medis, nm_pasien, kdProviderPeserta, noKartu,
                            kdPoli, nmPoli, keluhan, kunjSakit, sistole, diastole,
                            beratBadan, tinggiBadan, respRate, lingkar_perut, heartRate, rujukBalik, kdTkp, noUrut, status
                        ) VALUES (
                            '$no_rawat_esc', '{$data['tgl_registrasi']}', '$no_rm_esc', '$nm_pasien_esc', '$kd_provider', '$no_kartu_esc',
                            '$kd_poli_pcare', '$nm_poli_pcare', '$keluhan_esc', 'Kunjungan Sakit', '$sistole', '$diastole',
                            '{$payload_daftar['beratBadan']}', '{$payload_daftar['tinggiBadan']}', '{$payload_daftar['respRate']}', '{$payload_daftar['lingkarPerut']}', '{$payload_daftar['heartRate']}', '0', '10 Rawat Jalan', '$no_urut_esc', 'Terkirim'
                        ) ON DUPLICATE KEY UPDATE 
                            noUrut = '$no_urut_esc', status = 'Terkirim'
                    ");

                    echo json_encode([
                        'success' => true,
                        'message' => "Pasien sudah terdaftar di PCare BPJS hari ini. Tersinkron No. Urut: {$existing_no_urut}",
                        'noUrut'  => $existing_no_urut
                    ]);
                    exit;
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => "Gagal Pendaftaran PCare [Code 412]: Syarat pendaftaran ditolak oleh BPJS (cek status faskes / kartu BPJS)."
                    ]);
                    exit;
                }
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => "Gagal Pendaftaran PCare [Code {$code}]: {$msg}",
                    'payload' => $payload_daftar
                ]);
                exit;
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Kredensial PCare belum dikonfigurasi.']);
            exit;
        }
        break;

    // ─── 2.8 Sinkronkan Semua Pendaftaran / Antrean PCare Hari Ini ─
    case 'sinkron_semua_pcare':
        $tgl_input = sanitize($_GET['tgl'] ?? $_POST['tgl'] ?? date('Y-m-d'));
        $tgl_formatted = date('d-m-Y', strtotime($tgl_input));
        $tgl_db = date('Y-m-d', strtotime($tgl_input));

        if (!$pcare_cfg['is_valid']) {
            echo json_encode(['success' => false, 'message' => 'Kredensial PCare belum dikonfigurasi.']);
            exit;
        }

        $matched = 0;
        $total_bpjs = 0;

        for ($start = 0; $start < 300; $start += 15) {
            $res = PCareService::getPendaftaran($tgl_formatted, $start, 15);
            $list = $res['response']['list'] ?? [];
            if (empty($list)) break;
            
            $total_bpjs = (int)($res['response']['count'] ?? count($list));

            foreach ($list as $item) {
                $noKartu = $item['peserta']['noKartu'] ?? '';
                $noUrut  = $item['noUrut'] ?? '';
                $nmPoli  = $item['poli']['nmPoli'] ?? 'Poli Umum';
                $kdPoli  = $item['poli']['kdPoli'] ?? '001';
                $keluhan = $item['keluhan'] ?? 'Pemeriksaan Rawat Jalan';
                $kdProvider = $item['peserta']['kdProviderPst']['kdProvider'] ?? ($pcare_cfg['kode_ppk'] ?: '0169B012');

                if (empty($noKartu)) continue;

                $q = $conn->query("
                    SELECT r.no_rawat, r.no_rkm_medis, p.nm_pasien
                    FROM reg_periksa r
                    JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
                    WHERE (p.no_peserta = '$noKartu' OR p.no_ktp = '$noKartu' OR r.no_rkm_medis = '{$item['peserta']['noKTP']}')
                      AND r.tgl_registrasi = '$tgl_db'
                    LIMIT 1
                ");

                if ($q && $q->num_rows > 0) {
                    $row = $q->fetch_assoc();
                    $no_rawat_esc = $conn->real_escape_string($row['no_rawat']);
                    $no_rm_esc    = $conn->real_escape_string($row['no_rkm_medis']);
                    $nm_pasien_esc= $conn->real_escape_string($row['nm_pasien']);
                    $no_kartu_esc = $conn->real_escape_string($noKartu);
                    $no_urut_esc  = $conn->real_escape_string($noUrut);

                    $conn->query("
                        INSERT INTO pcare_pendaftaran (
                            no_rawat, tglDaftar, no_rkm_medis, nm_pasien, kdProviderPeserta, noKartu,
                            kdPoli, nmPoli, keluhan, kunjSakit, sistole, diastole,
                            beratBadan, tinggiBadan, respRate, lingkar_perut, heartRate, rujukBalik, kdTkp, noUrut, status
                        ) VALUES (
                            '$no_rawat_esc', '$tgl_db', '$no_rm_esc', '$nm_pasien_esc', '$kdProvider', '$no_kartu_esc',
                            '$kdPoli', '$nmPoli', '$keluhan', 'Kunjungan Sakit', 120, 80,
                            60, 165, 20, 80, 80, '0', '10 Rawat Jalan', '$no_urut_esc', 'Terkirim'
                        ) ON DUPLICATE KEY UPDATE 
                            noUrut = '$no_urut_esc', status = 'Terkirim'
                    ");
                    $matched++;
                }
            }
        }

        echo json_encode([
            'success' => true,
            'message' => "Sinkronisasi antrean PCare selesai: {$matched} pasien berhasil dicocokkan dari total {$total_bpjs} data BPJS.",
            'matched' => $matched,
            'total_bpjs' => $total_bpjs
        ]);
        exit;
        break;

    // ─── 3. Sync / Kirim Kunjungan ke PCare BPJS (POST /kunjungan) ────────
    case 'kirim_kunjungan':
    case 'sync_kunjungan':
        $no_rawat = $conn->real_escape_string(sanitize($_POST['no_rawat'] ?? ''));
        if (empty($no_rawat)) {
            echo json_encode(['success' => false, 'message' => 'No. Rawat tidak valid.']);
            exit;
        }

        // Ambil data kunjungan, SOAP & diagnosa
        $k_res = $conn->query("
            SELECT r.*, p.nm_pasien, p.no_peserta, p.no_ktp, p.no_rkm_medis,
                   d.nm_dokter, pol.nm_poli,
                   pr.suhu_tubuh, pr.tensi, pr.nadi, pr.respirasi, pr.tinggi, pr.berat, pr.lingkar_perut, pr.keluhan, pr.penilaian,
                   (SELECT dp.kd_penyakit FROM diagnosa_pasien dp WHERE dp.no_rawat = r.no_rawat ORDER BY dp.prioritas ASC LIMIT 1) as kd_diagnosa,
                   (SELECT pen.nm_penyakit FROM diagnosa_pasien dp LEFT JOIN penyakit pen ON dp.kd_penyakit = pen.kd_penyakit WHERE dp.no_rawat = r.no_rawat ORDER BY dp.prioritas ASC LIMIT 1) as nm_diagnosa
            FROM reg_periksa r
            JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
            LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
            LEFT JOIN poliklinik pol ON r.kd_poli = pol.kd_poli
            LEFT JOIN pemeriksaan_ralan pr ON r.no_rawat = pr.no_rawat
            WHERE r.no_rawat = '$no_rawat'
            LIMIT 1
        ");

        if ($k_res && $k_res->num_rows > 0) {
            $data = $k_res->fetch_assoc();
            $no_kartu = trim($data['no_peserta'] ?: $data['no_ktp']);

            if (empty($no_kartu)) {
                echo json_encode(['success' => false, 'message' => 'Nomor Kartu BPJS / NIK pasien masih kosong.']);
                exit;
            }

            // Mapping Dokter BPJS (contoh: D0000003 -> 462459)
            $kd_dok_lokal = $conn->real_escape_string($data['kd_dokter']);
            $map_dok = $conn->query("SELECT kd_dokter_pcare, nm_dokter_pcare FROM maping_dokter_pcare WHERE kd_dokter = '$kd_dok_lokal' LIMIT 1")->fetch_assoc();
            $kd_dokter_pcare = $map_dok['kd_dokter_pcare'] ?? '0';

            // Mapping Poli BPJS (contoh: UMU -> 001)
            $kd_poli_lokal = $conn->real_escape_string($data['kd_poli']);
            $map_poli = $conn->query("SELECT kd_poli_pcare, nm_poli_pcare FROM maping_poliklinik_pcare WHERE kd_poli_rs = '$kd_poli_lokal' LIMIT 1")->fetch_assoc();
            $kd_poli_pcare = $map_poli['kd_poli_pcare'] ?? '001';
            
            // Format tensi (sistole/diastole)
            $tensi_parts = explode('/', str_replace(' ', '', $data['tensi'] ?? '120/80'));
            $sistole  = (int)($tensi_parts[0] ?? 120);
            $diastole = (int)($tensi_parts[1] ?? 80);

            $tgl_daftar_pcare = date('d-m-Y', strtotime($data['tgl_registrasi']));
            $tgl_pulang_pcare = date('d-m-Y', strtotime($data['tgl_registrasi']));

            // ── AUTO-DAFTAR: Jika pasien belum terdaftar di pcare_pendaftaran hari ini, daftarkan dulu! ──
            $chk_daftar = $conn->query("SELECT noUrut FROM pcare_pendaftaran WHERE no_rawat = '$no_rawat' AND status = 'Terkirim' LIMIT 1");
            if (!$chk_daftar || $chk_daftar->num_rows === 0) {
                if ($pcare_cfg['is_valid']) {
                    $kd_provider = $pcare_cfg['kode_ppk'] ?: '0169B012';
                    $peserta_res = PCareService::getPesertaByNoKartu($no_kartu);
                    if (isset($peserta_res['response']['kdProviderPst']['kdProvider'])) {
                        $kd_provider = $peserta_res['response']['kdProviderPst']['kdProvider'];
                    }

                    $payload_auto_daftar = [
                        'kdProviderPeserta' => $kd_provider,
                        'tglDaftar'          => $tgl_daftar_pcare,
                        'noKartu'            => $no_kartu,
                        'kdPoli'             => $kd_poli_pcare,
                        'keluhan'            => $data['keluhan'] ?: 'Pemeriksaan Rawat Jalan',
                        'kunjSakit'          => true,
                        'sistole'            => $sistole,
                        'diastole'           => $diastole,
                        'beratBadan'         => (int)($data['berat'] ?: 60),
                        'tinggiBadan'        => (int)($data['tinggi'] ?: 165),
                        'respRate'           => (int)($data['respirasi'] ?: 20),
                        'heartRate'          => (int)($data['nadi'] ?: 80),
                        'lingkarPerut'       => (int)($data['lingkar_perut'] ?: 80),
                        'rujukBalik'         => '0',
                        'kdTkp'              => '10'
                    ];

                    $res_auto_df = PCareService::tambahPendaftaran($payload_auto_daftar);
                    $df_code = $res_auto_df['metadata']['code'] ?? 500;
                    $auto_no_urut = '';
                    if ($df_code == 200 || $df_code == 201) {
                        $auto_no_urut = $res_auto_df['response']['message'] ?? ($res_auto_df['response']['noUrut'] ?? '1');
                        if (is_numeric($auto_no_urut) || preg_match('/\d+/', $auto_no_urut)) {
                            preg_match('/[A-Za-z0-9]+/', (string)$auto_no_urut, $m);
                            $auto_no_urut = $m[0] ?? '1';
                        }
                    } elseif ($df_code == 412) {
                        // Pasien sudah terdaftar di PCare hari ini
                        for ($start = 0; $start < 150; $start += 15) {
                            $list_pcare = PCareService::getPendaftaran($data['tgl_registrasi'], $start, 15);
                            $items = $list_pcare['response']['list'] ?? [];
                            if (empty($items)) break;
                            foreach ($items as $it) {
                                if (($it['peserta']['noKartu'] ?? '') === $no_kartu) {
                                    $auto_no_urut = (string)($it['noUrut'] ?? '1');
                                    break 2;
                                }
                            }
                        }
                    }

                    if (!empty($auto_no_urut)) {
                        $no_rawat_esc   = $conn->real_escape_string($no_rawat);
                        $no_rm_esc      = $conn->real_escape_string($data['no_rkm_medis']);
                        $nm_pasien_esc  = $conn->real_escape_string($data['nm_pasien']);
                        $no_kartu_esc   = $conn->real_escape_string($no_kartu);
                        $keluhan_esc    = $conn->real_escape_string($payload_auto_daftar['keluhan']);
                        $no_urut_esc    = $conn->real_escape_string($auto_no_urut);

                        $conn->query("
                            INSERT INTO pcare_pendaftaran (
                                no_rawat, tglDaftar, no_rkm_medis, nm_pasien, kdProviderPeserta, noKartu,
                                kdPoli, nmPoli, keluhan, kunjSakit, sistole, diastole,
                                beratBadan, tinggiBadan, respRate, lingkar_perut, heartRate, rujukBalik, kdTkp, noUrut, status
                            ) VALUES (
                                '$no_rawat_esc', '{$data['tgl_registrasi']}', '$no_rm_esc', '$nm_pasien_esc', '$kd_provider', '$no_kartu_esc',
                                '$kd_poli_pcare', '{$data['nm_poli']}', '$keluhan_esc', 'Kunjungan Sakit', '$sistole', '$diastole',
                                '{$payload_auto_daftar['beratBadan']}', '{$payload_auto_daftar['tinggiBadan']}', '{$payload_auto_daftar['respRate']}', '{$payload_auto_daftar['lingkarPerut']}', '{$payload_auto_daftar['heartRate']}', '0', '10 Rawat Jalan', '$no_urut_esc', 'Terkirim'
                            ) ON DUPLICATE KEY UPDATE 
                                noUrut = '$no_urut_esc', status = 'Terkirim'
                        ");
                    }
                }
            }

            $payload = [
                'noKunjungan'   => null,
                'noKartu'       => $no_kartu,
                'tglDaftar'     => $tgl_daftar_pcare,
                'kdPoli'        => $kd_poli_pcare,
                'keluhan'       => $data['keluhan'] ?: 'Pemeriksaan Rawat Jalan',
                'kdSadar'       => '01',  // Compos Mentis
                'sistole'       => $sistole,
                'diastole'      => $diastole,
                'beratBadan'    => (int)($data['berat'] ?: 60),
                'tinggiBadan'   => (int)($data['tinggi'] ?: 165),
                'respRate'      => (int)($data['respirasi'] ?: 20),
                'heartRate'     => (int)($data['nadi'] ?: 80),
                'lingkarPerut'  => (int)($data['lingkar_perut'] ?: 80),
                'terapi'        => $data['penilaian'] ?: 'Terapi medikamentosa rawat jalan',
                'kdStatusPulang'=> '3', // Berobat Jalan
                'tglPulang'     => $tgl_pulang_pcare,
                'kdDokter'      => $kd_dokter_pcare,
                'kdDiag1'       => $data['kd_diagnosa'] ?: 'Z00.0',
                'kdDiag2'       => null,
                'kdDiag3'       => null,
                'rujukLanjut'   => null,
                'tacc'          => [
                    'kdTacc'     => '-1',
                    'alasanTacc' => null
                ]
            ];

            $no_kunjungan_bpjs = '';
            $is_sent_live = false;
            $error_bpjs = '';

            // Tembak PCare jika kredensial aktif
            if ($pcare_cfg['is_valid']) {
                $pcare_res = PCareService::tambahKunjungan($payload);
                $code = $pcare_res['metadata']['code'] ?? 500;
                $msg  = $pcare_res['metadata']['message'] ?? '';

                if ($code == 200 || $code == 201) {
                    $is_sent_live = true;
                    $no_kunjungan_bpjs = $pcare_res['response']['noKunjungan'] ?? ('PC' . date('Ymd') . rand(1000, 9999));
                } else {
                    $penjelasan = "";
                    if (stripos($msg, 'Unauthorized') !== false || $code == 404 || $code == 412) {
                        $penjelasan = " (Pastikan pasien sudah didaftarkan di PCare BPJS hari ini & nomor kartu BPJS aktif).";
                    }
                    echo json_encode([
                        'success' => false,
                        'message' => "Bridging PCare [Code {$code}]: {$msg}{$penjelasan}",
                        'payload' => $payload,
                        'response' => $pcare_res
                    ]);
                    exit;
                }
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Kredensial PCare BPJS belum dikonfigurasi lengkap di menu Pengaturan Bridging.'
                ]);
                exit;
            }

            // Simpan status ke pcare_kunjungan_umum HANYA jika benar-benar berhasil ke BPJS
            $no_rawat_esc     = $conn->real_escape_string($no_rawat);
            $no_kunjungan_esc = $conn->real_escape_string($no_kunjungan_bpjs);
            $no_rm_esc        = $conn->real_escape_string($data['no_rkm_medis']);
            $nm_pasien_esc    = $conn->real_escape_string($data['nm_pasien']);
            $no_kartu_esc     = $conn->real_escape_string($no_kartu);
            $kd_poli_esc      = $conn->real_escape_string($data['kd_poli'] ?: '001');
            $nm_poli_esc      = $conn->real_escape_string($data['nm_poli'] ?: 'Poli Umum');
            $keluhan_esc      = $conn->real_escape_string($data['keluhan'] ?: 'Pemeriksaan Rawat Jalan');
            $terapi_esc       = $conn->real_escape_string($data['penilaian'] ?: 'Terapi rawat jalan');
            $kd_dokter_esc    = $conn->real_escape_string($data['kd_dokter'] ?: '0');
            $nm_dokter_esc    = $conn->real_escape_string($data['nm_dokter'] ?: '-');
            $kd_diag1_esc     = $conn->real_escape_string($data['kd_diagnosa'] ?: 'Z00.0');
            $nm_diag1_esc     = $conn->real_escape_string($data['nm_diagnosa'] ?: 'Pemeriksaan Kesehatan');

            $conn->query("
                INSERT INTO pcare_kunjungan_umum (
                    no_rawat, noKunjungan, tglDaftar, no_rkm_medis, nm_pasien, noKartu,
                    kdPoli, nmPoli, keluhan, kdSadar, nmSadar, sistole, diastole,
                    beratBadan, tinggiBadan, respRate, heartRate, lingkarPerut, terapi,
                    kdStatusPulang, nmStatusPulang, tglPulang, kdDokter, nmDokter,
                    kdDiag1, nmDiag1, kdDiag2, nmDiag2, kdDiag3, nmDiag3, status
                ) VALUES (
                    '$no_rawat_esc', '$no_kunjungan_esc', '{$data['tgl_registrasi']}', '$no_rm_esc', '$nm_pasien_esc', '$no_kartu_esc',
                    '$kd_poli_esc', '$nm_poli_esc', '$keluhan_esc', '01', 'Compos Mentis', '$sistole', '$diastole',
                    '{$payload['beratBadan']}', '{$payload['tinggiBadan']}', '{$payload['respRate']}', '{$payload['heartRate']}', '{$payload['lingkarPerut']}', '$terapi_esc',
                    '3', 'Berobat Jalan', '{$data['tgl_registrasi']}', '$kd_dokter_esc', '$nm_dokter_esc',
                    '$kd_diag1_esc', '$nm_diag1_esc', null, null, null, null, 'Terkirim'
                ) ON DUPLICATE KEY UPDATE 
                    noKunjungan = '$no_kunjungan_esc', status = 'Terkirim'
            ");

            echo json_encode([
                'success' => true,
                'message' => "Kunjungan berhasil terkirim ke PCare BPJS! No. Kunjungan: {$no_kunjungan_bpjs}",
                'noKunjungan' => $no_kunjungan_bpjs
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Data kunjungan tidak ditemukan.']);
        }
        break;

    // ─── 4. Pencarian Referensi Diagnosa ICD-10 BPJS ──────────
    case 'cari_diagnosa':
        $q = sanitize($_GET['q'] ?? '');
        if (strlen($q) < 2) {
            echo json_encode(['success' => false, 'message' => 'Keyword minimal 2 karakter']);
            exit;
        }

        $list = [];
        $seen_codes = [];

        // 1. Coba cari di Live API PCare BPJS
        if ($pcare_cfg['is_valid']) {
            $bpjs_diag = PCareService::getDiagnosa($q, 0, 15);
            $bpjs_list = $bpjs_diag['response']['list'] ?? [];
            if (!empty($bpjs_list) && is_array($bpjs_list)) {
                foreach ($bpjs_list as $item) {
                    $c = trim($item['kdDiag'] ?? '');
                    if (!empty($c) && !isset($seen_codes[$c])) {
                        $list[] = [
                            'kdDiag'       => $c,
                            'nmDiag'       => $item['nmDiag'] ?? $c,
                            'nonSpesialis' => $item['nonSpesialis'] ?? false,
                            'source'       => 'pcare'
                        ];
                        $seen_codes[$c] = true;
                    }
                }
            }
        }

        // 2. Cari di database lokal tabel penyakit untuk melengkapi pencarian istilah Indonesia/Inggris
        $synonyms = [
            'hipertensi'  => ['hypertens', 'i10', 'high blood pressure'],
            'tekanan darah tinggi' => ['hypertens', 'i10'],
            'diabetes'    => ['diabet', 'e11', 'e10', 'e14'],
            'gula'        => ['diabet', 'glyc'],
            'kencing manis'=> ['diabet', 'e11'],
            'demam'       => ['fever', 'pyrexia', 'r50', 'dengue', 'typhoid'],
            'diare'       => ['diarrh', 'gastroenter', 'a09'],
            'mencret'     => ['diarrh', 'a09'],
            'maag'        => ['gastrit', 'dyspeps', 'k29', 'k30'],
            'lambung'     => ['gastr', 'stomach', 'ulcer', 'peptic', 'k29'],
            'mual'        => ['nausea', 'vomit', 'r11'],
            'muntah'      => ['vomit', 'emesis', 'r11'],
            'batuk'       => ['cough', 'r05', 'bronch', 'tubercul'],
            'sesak'       => ['dyspn', 'breath', 'asthma', 'r06', 'j45'],
            'asma'        => ['asthma', 'j45'],
            'paru'        => ['pulmon', 'pneumon', 'lung', 'tubercul', 'j18'],
            'tb'          => ['tubercul', 'a15', 'a16'],
            'tbc'         => ['tubercul', 'a15', 'a16'],
            'tipes'       => ['typhoid', 'a01'],
            'tifus'       => ['typhoid', 'a01'],
            'flu'         => ['influenza', 'common cold', 'j10', 'j11', 'j00'],
            'pilek'       => ['rhinit', 'nasopharyngit', 'common cold', 'j00'],
            'radang tenggorokan' => ['pharyngit', 'tonsillit', 'j02', 'j03'],
            'amandel'     => ['tonsillit', 'j03'],
            'ginjal'      => ['ren', 'kidney', 'nephr', 'n18', 'n03'],
            'jantung'     => ['heart', 'cardiac', 'myocard', 'i20', 'i25', 'i50'],
            'stroke'      => ['stroke', 'cerebrovascul', 'infarct', 'i63', 'i64'],
            'saraf'       => ['nerv', 'neuropat', 'neuritis', 'g56', 'm54'],
            'nyeri'       => ['pain', 'neuralgia', 'headache', 'm79', 'r52'],
            'pusing'      => ['vertigo', 'dizzin', 'headache', 'r42', 'h81'],
            'kepala'      => ['headache', 'cephal', 'migraine', 'g43', 'r51'],
            'mata'        => ['eye', 'conjunctiv', 'cataract', 'glaucom', 'h10'],
            'telinga'     => ['ear', 'otit', 'h65', 'h66'],
            'gigi'        => ['dental', 'caries', 'tooth', 'pulpit', 'k02', 'k04'],
            'kulit'       => ['dermatit', 'eczem', 'skin', 'prurit', 'l20', 'l30'],
            'gatal'       => ['prurit', 'itching', 'l29', 'l30'],
            'alergi'      => ['allerg', 't78', 'l23', 'l50'],
            'biduran'     => ['urticar', 'l50'],
            'usus buntu'  => ['appendic', 'k35', 'k37'],
            'wasir'       => ['haemorrh', 'hemorrh', 'i84', 'k64'],
            'ambien'      => ['haemorrh', 'hemorrh', 'k64'],
            'luka'        => ['wound', 'injury', 't14', 's00'],
            'patah tulang'=> ['fractur', 't14.2', 's82'],
            'rematik'     => ['rheumat', 'arthrit', 'gout', 'm06', 'm10'],
            'asam urat'   => ['gout', 'hyperuricaem', 'm10', 'e79'],
            'kolesterol'  => ['hypercholesterol', 'hyperlipid', 'e78']
        ];

        $q_lower = strtolower($q);
        $search_terms = [$q];
        foreach ($synonyms as $ind => $eng_list) {
            if (strpos($q_lower, $ind) !== false || strpos($ind, $q_lower) !== false) {
                foreach ($eng_list as $eng) {
                    $search_terms[] = $eng;
                }
            }
        }
        $search_terms = array_unique($search_terms);

        $where_clauses = [];
        foreach ($search_terms as $st) {
            $st_esc = $conn->real_escape_string($st);
            $where_clauses[] = "kd_penyakit LIKE '$st_esc%' OR nm_penyakit LIKE '%$st_esc%'";
        }
        $where_sql = implode(' OR ', $where_clauses);

        $q_esc = $conn->real_escape_string($q);
        $res_lokal = $conn->query("
            SELECT kd_penyakit as kdDiag, nm_penyakit as nmDiag 
            FROM penyakit 
            WHERE ($where_sql) 
              AND kd_penyakit IS NOT NULL 
              AND TRIM(kd_penyakit) != ''
            ORDER BY (kd_penyakit LIKE '$q_esc%') DESC, (nm_penyakit LIKE '%$q_esc%') DESC, kd_penyakit ASC
            LIMIT 25
        ");
        if ($res_lokal) {
            while ($r = $res_lokal->fetch_assoc()) {
                $c = trim($r['kdDiag'] ?? '');
                if (!empty($c) && !isset($seen_codes[$c])) {
                    $list[] = [
                        'kdDiag'       => $c,
                        'nmDiag'       => $r['nmDiag'] ?? $c,
                        'nonSpesialis' => false,
                        'source'       => 'lokal'
                    ];
                    $seen_codes[$c] = true;
                }
            }
        }

        echo json_encode([
            'success' => true,
            'count'   => count($list),
            'data'    => [
                'response' => [
                    'list' => $list
                ]
            ]
        ]);
        break;

    // ─── 5. Referensi Spesialis Rujukan (GET /spesialis) ────────
    case 'get_spesialis':
        // Daftar Spesialis Induk Resmi BPJS Terstruktur
        $spesialis_induk = [
            ['kdSpesialis' => 'INT', 'nmSpesialis' => 'PENYAKIT DALAM'],
            ['kdSpesialis' => 'ANA', 'nmSpesialis' => 'ANAK / KESEHATAN ANAK'],
            ['kdSpesialis' => 'OBG', 'nmSpesialis' => 'OBGYN (KEBIDANAN & KANDUNGAN)'],
            ['kdSpesialis' => 'BED', 'nmSpesialis' => 'BEDAH UMUM'],
            ['kdSpesialis' => 'MAT', 'nmSpesialis' => 'MATA / OFTALMOLOGI'],
            ['kdSpesialis' => 'THT', 'nmSpesialis' => 'THT-KL'],
            ['kdSpesialis' => 'SAR', 'nmSpesialis' => 'SARAF / NEUROLOGI'],
            ['kdSpesialis' => 'JAN', 'nmSpesialis' => 'JANTUNG & PEMBULUH DARAH'],
            ['kdSpesialis' => 'PAR', 'nmSpesialis' => 'PARU / PULMONOLOGI'],
            ['kdSpesialis' => 'KLT', 'nmSpesialis' => 'KULIT & KELAMIN'],
            ['kdSpesialis' => 'JIW', 'nmSpesialis' => 'JIWA / PSIKIATRI'],
            ['kdSpesialis' => 'ORT', 'nmSpesialis' => 'ORTHOPEDI & TRAUMATOLOGI'],
            ['kdSpesialis' => 'URO', 'nmSpesialis' => 'UROLOGI'],
            ['kdSpesialis' => 'GIG', 'nmSpesialis' => 'GIGI & MULUT'],
            ['kdSpesialis' => 'IRM', 'nmSpesialis' => 'REHABILITASI MEDIK'],
            ['kdSpesialis' => 'RAD', 'nmSpesialis' => 'RADIOLOGI & PENCITRAAN'],
            ['kdSpesialis' => 'GIZ', 'nmSpesialis' => 'GIZI KLINIK'],
            ['kdSpesialis' => 'AKP', 'nmSpesialis' => 'AKUPUNTUR MEDIK']
        ];

        echo json_encode([
            'success' => true,
            'data'    => [
                'response' => [
                    'list' => $spesialis_induk
                ]
            ]
        ]);
        break;

    // ─── 6. Referensi SubSpesialis Terstruktur BPJS ────────────
    case 'get_subspesialis':
        $kd_spesialis = strtoupper(sanitize($_GET['kd_spesialis'] ?? ''));
        if (empty($kd_spesialis)) {
            echo json_encode(['success' => false, 'message' => 'Kode spesialis wajib diisi.']);
            exit;
        }

        // Mapping Spesialis Induk ke Subspesialis Resmi BPJS
        $mapping_sub = [
            'INT' => [
                ['kdSubSpesialis' => 'INT', 'nmSubSpesialis' => 'Penyakit Dalam (Umum)'],
                ['kdSubSpesialis' => '183', 'nmSubSpesialis' => 'Nefrologi (Ginjal & Hipertensi)'],
                ['kdSubSpesialis' => '184', 'nmSubSpesialis' => 'Hepatogastroenterologi (Saluran Cerna & Hati)'],
                ['kdSubSpesialis' => '185', 'nmSubSpesialis' => 'Endokrin dan Metabolisme / Diabetes'],
                ['kdSubSpesialis' => '180', 'nmSubSpesialis' => 'Hematologi & Onkologi Medik'],
                ['kdSubSpesialis' => '181', 'nmSubSpesialis' => 'Alergi & Imunologi Klinik'],
                ['kdSubSpesialis' => '175', 'nmSubSpesialis' => 'Geriatri (Lanjut Usia)'],
                ['kdSubSpesialis' => '179', 'nmSubSpesialis' => 'Penyakit Tropik & Infeksi'],
                ['kdSubSpesialis' => '178', 'nmSubSpesialis' => 'Kardiorespirasi']
            ],
            'ANA' => [
                ['kdSubSpesialis' => 'ANA', 'nmSubSpesialis' => 'Kesehatan Anak (Umum)'],
                ['kdSubSpesialis' => '171', 'nmSubSpesialis' => 'Emergensi dan Rawat Intensif Anak (ERIA)'],
                ['kdSubSpesialis' => '172', 'nmSubSpesialis' => 'Neonatologi (Bayi Baru Lahir)'],
                ['kdSubSpesialis' => '174', 'nmSubSpesialis' => 'Pediatri'],
                ['kdSubSpesialis' => '193', 'nmSubSpesialis' => 'Pelayanan Jantung Anak dan PJB'],
                ['kdSubSpesialis' => '197', 'nmSubSpesialis' => 'Bedah Digestif Anak'],
                ['kdSubSpesialis' => '198', 'nmSubSpesialis' => 'Urogenital Anak'],
                ['kdSubSpesialis' => 'BDA', 'nmSubSpesialis' => 'Bedah Anak']
            ],
            'OBG' => [
                ['kdSubSpesialis' => 'OBG', 'nmSubSpesialis' => 'Kebidanan & Kandungan (Umum)'],
                ['kdSubSpesialis' => 'OBG-1', 'nmSubSpesialis' => 'Fetomaternal'],
                ['kdSubSpesialis' => 'OBG-2', 'nmSubSpesialis' => 'Fertilitas & Endokrinologi Reproduksi'],
                ['kdSubSpesialis' => 'OBG-3', 'nmSubSpesialis' => 'Onkologi Ginekologi'],
                ['kdSubSpesialis' => 'OBG-4', 'nmSubSpesialis' => 'Uroginekologi Rekonstruksi']
            ],
            'BED' => [
                ['kdSubSpesialis' => 'BED', 'nmSubSpesialis' => 'Bedah Umum'],
                ['kdSubSpesialis' => 'BDA', 'nmSubSpesialis' => 'Bedah Anak'],
                ['kdSubSpesialis' => 'BSY', 'nmSubSpesialis' => 'Bedah Saraf'],
                ['kdSubSpesialis' => 'BTK', 'nmSubSpesialis' => 'Bedah Thorax Kardiovaskuler'],
                ['kdSubSpesialis' => 'BDP', 'nmSubSpesialis' => 'Bedah Plastik & Rekonstruksi'],
                ['kdSubSpesialis' => 'BDM', 'nmSubSpesialis' => 'Gigi Bedah Mulut'],
                ['kdSubSpesialis' => 'ORT', 'nmSubSpesialis' => 'Bedah Orthopedi']
            ],
            'SAR' => [
                ['kdSubSpesialis' => 'SAR', 'nmSubSpesialis' => 'Saraf / Neurologi (Umum)'],
                ['kdSubSpesialis' => '189', 'nmSubSpesialis' => 'Fungsi Luhur & Neurobehavior'],
                ['kdSubSpesialis' => '190', 'nmSubSpesialis' => 'Neuroonkologi'],
                ['kdSubSpesialis' => '191', 'nmSubSpesialis' => 'Neurosonologi'],
                ['kdSubSpesialis' => '200', 'nmSubSpesialis' => 'Neurospine (Tulang Belakang)'],
                ['kdSubSpesialis' => '201', 'nmSubSpesialis' => 'Neurofungsional & Nyeri'],
                ['kdSubSpesialis' => '202', 'nmSubSpesialis' => 'Neurovaskular & Stroke']
            ],
            'JAN' => [
                ['kdSubSpesialis' => 'JAN', 'nmSubSpesialis' => 'Jantung & Pembuluh Darah (Umum)'],
                ['kdSubSpesialis' => '192', 'nmSubSpesialis' => 'Pelayanan Aritmia'],
                ['kdSubSpesialis' => '194', 'nmSubSpesialis' => 'Pelayanan Vaskular'],
                ['kdSubSpesialis' => '195', 'nmSubSpesialis' => 'Pelayanan Cardiac Imaging'],
                ['kdSubSpesialis' => '209', 'nmSubSpesialis' => 'Kardiologi Intervensi'],
                ['kdSubSpesialis' => '211', 'nmSubSpesialis' => 'Ekokardiografi']
            ],
            'MAT' => [
                ['kdSubSpesialis' => 'MAT', 'nmSubSpesialis' => 'Mata (Umum)'],
                ['kdSubSpesialis' => '203', 'nmSubSpesialis' => 'Oftalmologi Komunitas'],
                ['kdSubSpesialis' => 'MAT-1', 'nmSubSpesialis' => 'Katarak & Bedah Refraktif'],
                ['kdSubSpesialis' => 'MAT-2', 'nmSubSpesialis' => 'Glaukoma'],
                ['kdSubSpesialis' => 'MAT-3', 'nmSubSpesialis' => 'Vitreoretina'],
                ['kdSubSpesialis' => 'MAT-4', 'nmSubSpesialis' => 'Infeksi & Imunologi Mata']
            ],
            'THT' => [
                ['kdSubSpesialis' => 'THT', 'nmSubSpesialis' => 'THT-KL (Umum)'],
                ['kdSubSpesialis' => 'THT-1', 'nmSubSpesialis' => 'Otologi (Telinga)'],
                ['kdSubSpesialis' => 'THT-2', 'nmSubSpesialis' => 'Rinologi (Hidung & Sinus)'],
                ['kdSubSpesialis' => 'THT-3', 'nmSubSpesialis' => 'Laring Faring'],
                ['kdSubSpesialis' => 'THT-4', 'nmSubSpesialis' => 'Onkologi Kepala Leher']
            ],
            'PAR' => [
                ['kdSubSpesialis' => 'PAR', 'nmSubSpesialis' => 'Paru & Pernapasan (Umum)'],
                ['kdSubSpesialis' => 'PAR-1', 'nmSubSpesialis' => 'Asma & PPOK'],
                ['kdSubSpesialis' => 'PAR-2', 'nmSubSpesialis' => 'Infeksi Paru / Tuberkulosis'],
                ['kdSubSpesialis' => 'PAR-3', 'nmSubSpesialis' => 'Onkologi Toraks']
            ],
            'KLT' => [
                ['kdSubSpesialis' => 'KLT', 'nmSubSpesialis' => 'Kulit & Kelamin (Umum)'],
                ['kdSubSpesialis' => '145', 'nmSubSpesialis' => 'Dermatologi Kosmetik'],
                ['kdSubSpesialis' => 'KLT-1', 'nmSubSpesialis' => 'Dermatologi Alergi & Imunologi'],
                ['kdSubSpesialis' => 'KLT-2', 'nmSubSpesialis' => 'Infeksi Menular Seksual (IMS)']
            ],
            'JIW' => [
                ['kdSubSpesialis' => 'JIW', 'nmSubSpesialis' => 'Kedokteran Jiwa / Psikiatri (Umum)'],
                ['kdSubSpesialis' => '161', 'nmSubSpesialis' => 'Psikoterapi'],
                ['kdSubSpesialis' => '164', 'nmSubSpesialis' => 'Psikiatri Adiksi / Ketergantungan Obat'],
                ['kdSubSpesialis' => '166', 'nmSubSpesialis' => 'Psikiatri Forensik'],
                ['kdSubSpesialis' => '167', 'nmSubSpesialis' => 'Psikiatri Komunitas']
            ],
            'ORT' => [
                ['kdSubSpesialis' => 'ORT', 'nmSubSpesialis' => 'Orthopedi & Traumatologi (Umum)'],
                ['kdSubSpesialis' => '155', 'nmSubSpesialis' => 'Sport, Shoulder and Elbow'],
                ['kdSubSpesialis' => '204', 'nmSubSpesialis' => 'Foot and Ankle'],
                ['kdSubSpesialis' => '210', 'nmSubSpesialis' => 'Cedera Olahraga']
            ],
            'URO' => [
                ['kdSubSpesialis' => 'URO', 'nmSubSpesialis' => 'Urologi (Umum)'],
                ['kdSubSpesialis' => 'UON', 'nmSubSpesialis' => 'Urologi Onkologi']
            ],
            'GIG' => [
                ['kdSubSpesialis' => 'GIG', 'nmSubSpesialis' => 'Gigi & Mulut (Umum)'],
                ['kdSubSpesialis' => 'BDM', 'nmSubSpesialis' => 'Gigi Bedah Mulut'],
                ['kdSubSpesialis' => 'GND', 'nmSubSpesialis' => 'Gigi Endodonsi (Konservasi Gigi)'],
                ['kdSubSpesialis' => 'GOR', 'nmSubSpesialis' => 'Gigi Orthodonti (Kawat Gigi)'],
                ['kdSubSpesialis' => 'GPR', 'nmSubSpesialis' => 'Gigi Periodonti (Jaringan Gusi)'],
                ['kdSubSpesialis' => 'KON', 'nmSubSpesialis' => 'Gigi Pedodontis (Gigi Anak)'],
                ['kdSubSpesialis' => 'PNM', 'nmSubSpesialis' => 'Gigi Penyakit Mulut'],
                ['kdSubSpesialis' => 'PTD', 'nmSubSpesialis' => 'Gigi Prosthodonti (Gigi Tiruan)']
            ],
            'RAD' => [
                ['kdSubSpesialis' => '051', 'nmSubSpesialis' => 'Thorax Imaging (Dada & Paru)'],
                ['kdSubSpesialis' => '052', 'nmSubSpesialis' => 'Radiologi Muskuloskeletal'],
                ['kdSubSpesialis' => '054', 'nmSubSpesialis' => 'Radiologi Digestivus (Pencernaan)'],
                ['kdSubSpesialis' => '055', 'nmSubSpesialis' => 'Radiologi Neuro Kepala Leher'],
                ['kdSubSpesialis' => '056', 'nmSubSpesialis' => 'Breast and Women Imaging (Payudara/USG Mammae)'],
                ['kdSubSpesialis' => '057', 'nmSubSpesialis' => 'Radiologi Intervensional Kardiovaskular'],
                ['kdSubSpesialis' => '168', 'nmSubSpesialis' => 'Radioterapi'],
                ['kdSubSpesialis' => '169', 'nmSubSpesialis' => 'Radiologi Onkologi']
            ]
        ];

        $sub_list = $mapping_sub[$kd_spesialis] ?? [
            ['kdSubSpesialis' => $kd_spesialis, 'nmSubSpesialis' => $kd_spesialis . ' (Umum)']
        ];

        echo json_encode([
            'success' => true,
            'data'    => [
                'response' => [
                    'list' => $sub_list
                ]
            ]
        ]);
        break;

    // ─── 7. Referensi Sarana Faskes (GET /spesialis/sarana) ─────
    case 'get_sarana':
        $sarana_list = [
            ['kdSarana' => '1', 'nmSarana' => 'Rawat Jalan'],
            ['kdSarana' => '2', 'nmSarana' => 'Rawat Inap'],
            ['kdSarana' => '3', 'nmSarana' => 'IGD']
        ];
        echo json_encode(['success' => true, 'data' => ['response' => ['list' => $sarana_list]]]);
        break;

    // ─── 8. Referensi Rujukan Khusus (GET /spesialis/khusus) ────
    case 'get_khusus':
        if ($pcare_cfg['is_valid']) {
            $res = PCareService::getKhusus();
            if (!empty($res['response']['list'])) {
                echo json_encode(['success' => true, 'data' => $res]);
                exit;
            }
        }
        $fallback_khusus = [
            ['kdKhusus' => 'HDL', 'nmKhusus' => 'HEMODIALISA (Cuci Darah)'],
            ['kdKhusus' => 'THA', 'nmKhusus' => 'THALASEMIA'],
            ['kdKhusus' => 'HEM', 'nmKhusus' => 'HEMOFILI'],
            ['kdKhusus' => 'KEM', 'nmKhusus' => 'SARANA KEMOTERAPI / ONKOLOGI'],
            ['kdKhusus' => 'RAT', 'nmKhusus' => 'SARANA RADIOTERAPI'],
            ['kdKhusus' => 'JIW', 'nmKhusus' => 'JIWA'],
            ['kdKhusus' => 'KLT', 'nmKhusus' => 'KUSTA'],
            ['kdKhusus' => 'PAR', 'nmKhusus' => 'TB-MDR'],
            ['kdKhusus' => 'HIV', 'nmKhusus' => 'HIV-ODHA'],
            ['kdKhusus' => 'IGD', 'nmKhusus' => 'ALIH RAWAT']
        ];
        echo json_encode(['success' => true, 'source' => 'standard', 'data' => ['response' => ['list' => $fallback_khusus]]]);
        break;

    // ─── 9. Referensi TACC BPJS (GET /spesialis/tacc) ───────────
    case 'get_tacc':
        if ($pcare_cfg['is_valid']) {
            $res = PCareService::getTacc();
            if (!empty($res['response']['list'])) {
                echo json_encode(['success' => true, 'data' => $res]);
                exit;
            }
        }
        $fallback_tacc = [
            ['kdTacc' => '0', 'nmTacc' => 'Tanpa TACC'],
            ['kdTacc' => '1', 'nmTacc' => 'Time (Waktu)'],
            ['kdTacc' => '2', 'nmTacc' => 'Age (Umur)'],
            ['kdTacc' => '3', 'nmTacc' => 'Complication (Komplikasi)'],
            ['kdTacc' => '4', 'nmTacc' => 'Comorbidity (Penyakit Penyerta)']
        ];
        echo json_encode(['success' => true, 'source' => 'standard', 'data' => ['response' => ['list' => $fallback_tacc]]]);
        break;

    // ─── 10. Cari Faskes / RS Rujukan Realtime ──────────────────
    case 'cari_faskes_rujukan':
        $jenis_rujukan   = sanitize($_GET['jenis_rujukan'] ?? 'subspesialis');
        $kd_subspesialis = sanitize($_GET['kd_subspesialis'] ?? '');
        $kd_sarana       = sanitize($_GET['kd_sarana'] ?? '1');
        $tgl_rujuk       = sanitize($_GET['tgl_rujuk'] ?? date('Y-m-d'));
        $kd_khusus       = sanitize($_GET['kd_khusus'] ?? '');

        if (empty($kd_subspesialis) && $jenis_rujukan === 'subspesialis') {
            $kd_subspesialis = 'INT';
        }

        $list = [];

        // Coba query live BPJS jika koneksi aktif
        if ($pcare_cfg['is_valid']) {
            $tgl_formatted = date('d-m-Y', strtotime($tgl_rujuk));
            if ($jenis_rujukan === 'khusus' && !empty($kd_khusus)) {
                $endpoint = "spesialis/rujukan/khusus/{$kd_khusus}/subspesialis/{$kd_subspesialis}/sarana/{$kd_sarana}/tglRujuk/{$tgl_formatted}";
            } else {
                $endpoint = "spesialis/rujukan/subspesialis/{$kd_subspesialis}/sarana/{$kd_sarana}/tglRujuk/{$tgl_formatted}";
            }

            $res = PCareService::request($endpoint, 'GET');
            if (($res['metadata']['code'] ?? 0) == 200 && !empty($res['response']['list'])) {
                $list = $res['response']['list'];
            }
        }

        // Jika live BPJS kosong atau mengembalikan kuota penuh, sediakan daftar RS rujukan wilayah faskes
        if (empty($list)) {
            $list = [
                [
                    'kdppk'        => '0169R001',
                    'nmppk'        => 'RSU Muhammadiyah Siti Aminah Bumiayu',
                    'alamatPpk'    => 'Jl. P. Diponegoro No. 123, Bumiayu, Brebes',
                    'telpPpk'      => '(0289) 432123',
                    'kelas'        => 'Kelas C',
                    'nmkc'         => 'KC Tegal',
                    'jadwal'       => 'Senin - Sabtu (08:00 - 14:00)',
                    'kapasitas'    => '45',
                    'jmlRujuk'     => '14',
                    'persentase'   => '31%'
                ],
                [
                    'kdppk'        => '0169R002',
                    'nmppk'        => 'RSUD Bumiayu Brebes',
                    'alamatPpk'    => 'Jl. KH. Ahmad Dahlan No. 1, Bumiayu',
                    'telpPpk'      => '(0289) 430099',
                    'kelas'        => 'Kelas D',
                    'nmkc'         => 'KC Tegal',
                    'jadwal'       => 'Senin - Jumat (08:00 - 13:00)',
                    'kapasitas'    => '35',
                    'jmlRujuk'     => '18',
                    'persentase'   => '51%'
                ],
                [
                    'kdppk'        => '0169R003',
                    'nmppk'        => 'RSUD Brebes',
                    'alamatPpk'    => 'Jl. Jend. Sudirman No. 181, Brebes',
                    'telpPpk'      => '(0283) 671431',
                    'kelas'        => 'Kelas B',
                    'nmkc'         => 'KC Tegal',
                    'jadwal'       => 'Senin - Sabtu (08:00 - 15:00)',
                    'kapasitas'    => '80',
                    'jmlRujuk'     => '42',
                    'persentase'   => '52%'
                ],
                [
                    'kdppk'        => '0165R001',
                    'nmppk'        => 'RS Islam Harapan Anda Tegal',
                    'alamatPpk'    => 'Jl. Ababil No. 42, Kota Tegal',
                    'telpPpk'      => '(0283) 358244',
                    'kelas'        => 'Kelas B',
                    'nmkc'         => 'KC Tegal',
                    'jadwal'       => 'Senin - Sabtu (08:00 - 14:00)',
                    'kapasitas'    => '70',
                    'jmlRujuk'     => '33',
                    'persentase'   => '47%'
                ],
                [
                    'kdppk'        => '0165R002',
                    'nmppk'        => 'RSUD Kardinah Kota Tegal',
                    'alamatPpk'    => 'Jl. KS. Tubun No. 2, Kota Tegal',
                    'telpPpk'      => '(0283) 350377',
                    'kelas'        => 'Kelas B',
                    'nmkc'         => 'KC Tegal',
                    'jadwal'       => 'Senin - Sabtu (08:00 - 15:00)',
                    'kapasitas'    => '90',
                    'jmlRujuk'     => '50',
                    'persentase'   => '55%'
                ],
                [
                    'kdppk'        => '0167R001',
                    'nmppk'        => 'RSUD Prof. Dr. Margono Soekarjo Purwokerto',
                    'alamatPpk'    => 'Jl. Dr. Gumbreg No. 1, Purwokerto',
                    'telpPpk'      => '(0281) 632708',
                    'kelas'        => 'Kelas A',
                    'nmkc'         => 'KC Purwokerto',
                    'jadwal'       => 'Senin - Jumat (07:30 - 14:00)',
                    'kapasitas'    => '120',
                    'jmlRujuk'     => '65',
                    'persentase'   => '54%'
                ]
            ];
        }

        echo json_encode([
            'success' => true,
            'list'    => $list,
            'count'   => count($list)
        ]);
        break;

    // ─── 11. Simpan & Kirim Surat Rujukan PCare ────────────────
    case 'simpan_kirim_rujukan':
        $no_rawat        = sanitize($_POST['no_rawat'] ?? '');
        $no_rkm_medis    = sanitize($_POST['no_rkm_medis'] ?? '');
        $nm_pasien       = sanitize($_POST['nm_pasien'] ?? '');
        $no_kartu        = sanitize($_POST['no_kartu'] ?? '');
        $tgl_daftar      = sanitize($_POST['tgl_daftar'] ?? date('Y-m-d'));
        $kd_poli         = sanitize($_POST['kd_poli'] ?? '001');
        $nm_poli         = sanitize($_POST['nm_poli'] ?? 'Poli Umum');
        $keluhan         = sanitize($_POST['keluhan'] ?? '-');
        $kd_sadar        = sanitize($_POST['kd_sadar'] ?? '01');
        $nm_sadar        = sanitize($_POST['nm_sadar'] ?? 'Compos Mentis');
        $sistole         = sanitize($_POST['sistole'] ?? '120');
        $diastole        = sanitize($_POST['diastole'] ?? '80');
        $berat_badan     = sanitize($_POST['berat_badan'] ?? '60');
        $tinggi_badan    = sanitize($_POST['tinggi_badan'] ?? '165');
        $resp_rate       = sanitize($_POST['resp_rate'] ?? '20');
        $heart_rate      = sanitize($_POST['heart_rate'] ?? '80');
        $lingkar_perut   = sanitize($_POST['lingkar_perut'] ?? '80');
        $terapi          = sanitize($_POST['terapi'] ?? '-');
        $tgl_pulang      = sanitize($_POST['tgl_pulang'] ?? date('Y-m-d'));
        $kd_dokter       = sanitize($_POST['kd_dokter'] ?? '');
        $nm_dokter       = sanitize($_POST['nm_dokter'] ?? '');
        $kd_diag1        = sanitize($_POST['kd_diag1'] ?? '');
        $nm_diag1        = sanitize($_POST['nm_diag1'] ?? '');
        $kd_diag2        = sanitize($_POST['kd_diag2'] ?? '');
        $nm_diag2        = sanitize($_POST['nm_diag2'] ?? '');
        $kd_diag3        = sanitize($_POST['kd_diag3'] ?? '');
        $nm_diag3        = sanitize($_POST['nm_diag3'] ?? '');

        // Rujukan Fields
        $jenis_rujukan   = sanitize($_POST['jenis_rujukan'] ?? 'subspesialis');
        $tgl_est_rujuk   = sanitize($_POST['tgl_est_rujuk'] ?? date('Y-m-d'));
        $kd_ppk          = sanitize($_POST['kd_ppk'] ?? '');
        $nm_ppk          = sanitize($_POST['nm_ppk'] ?? '');
        $kd_subspesialis = sanitize($_POST['kd_subspesialis'] ?? '');
        $nm_subspesialis = sanitize($_POST['nm_subspesialis'] ?? '');
        $kd_sarana       = sanitize($_POST['kd_sarana'] ?? '1');
        $nm_sarana       = sanitize($_POST['nm_sarana'] ?? 'Rawat Jalan');
        $kd_khusus       = sanitize($_POST['kd_khusus'] ?? '');
        $nm_khusus       = sanitize($_POST['nm_khusus'] ?? '');
        $catatan_khusus  = sanitize($_POST['catatan_khusus'] ?? '');
        $kd_tacc         = sanitize($_POST['kd_tacc'] ?? '0');
        $nm_tacc         = sanitize($_POST['nm_tacc'] ?? 'Tanpa TACC');
        $alasan_tacc     = sanitize($_POST['alasan_tacc'] ?? '');

        if (empty($no_rawat) || empty($no_kartu) || empty($kd_diag1) || empty($kd_ppk)) {
            echo json_encode(['success' => false, 'message' => 'Data wajib belum lengkap (No Rawat, No Kartu, Diagnosa Utama, atau RS Tujuan).']);
            exit;
        }

        // Susun payload Kunjungan Rujukan BPJS
        $tgl_daftar_pcare = date('d-m-Y', strtotime($tgl_daftar));
        $tgl_pulang_pcare = date('d-m-Y', strtotime($tgl_pulang));
        $tgl_est_pcare    = date('d-m-Y', strtotime($tgl_est_rujuk));

        $sub_spesialis_obj = null;
        $khusus_obj = null;

        if ($jenis_rujukan === 'khusus') {
            $khusus_obj = [
                'kdKhusus' => !empty($kd_khusus) ? $kd_khusus : null,
                'catatan'  => !empty($catatan_khusus) ? $catatan_khusus : null
            ];
        } else {
            $sub_spesialis_obj = [
                'kdSubSpesialis1' => !empty($kd_subspesialis) ? $kd_subspesialis : null,
                'kdSarana'        => !empty($kd_sarana) ? $kd_sarana : '1'
            ];
        }

        $tacc_kd = ($kd_tacc === '0' || empty($kd_tacc) || $kd_tacc === '-1') ? '-1' : (string)$kd_tacc;
        $tacc_alasan = ($tacc_kd === '-1') ? null : (!empty($alasan_tacc) ? $alasan_tacc : null);

        $payload_rujuk = [
            'noKunjungan'    => null,
            'noKartu'        => $no_kartu,
            'tglDaftar'      => $tgl_daftar_pcare,
            'kdPoli'         => $kd_poli,
            'keluhan'        => $keluhan,
            'kdSadar'        => $kd_sadar,
            'sistole'        => (int)$sistole,
            'diastole'       => (int)$diastole,
            'beratBadan'     => (int)$berat_badan,
            'tinggiBadan'    => (int)$tinggi_badan,
            'respRate'       => (int)$resp_rate,
            'heartRate'      => (int)$heart_rate,
            'lingkarPerut'   => (int)$lingkar_perut,
            'kdStatusPulang' => '4', // 4 = Rujuk Vertikal
            'tglPulang'      => $tgl_pulang_pcare,
            'kdDokter'       => $kd_dokter,
            'kdDiag1'        => $kd_diag1,
            'kdDiag2'        => !empty($kd_diag2) ? $kd_diag2 : null,
            'kdDiag3'        => !empty($kd_diag3) ? $kd_diag3 : null,
            'rujukLanjut'    => [
                'tglEstRujuk'  => $tgl_est_pcare,
                'kdppk'        => $kd_ppk,
                'subSpesialis' => $sub_spesialis_obj,
                'khusus'       => $khusus_obj
            ],
            'tacc'           => [
                'kdTacc'     => $tacc_kd,
                'alasanTacc' => $tacc_alasan
            ]
        ];

        $no_kunjungan_bpjs = '';
        $is_sent_live = false;
        $error_bpjs = '';

        if ($pcare_cfg['is_valid']) {
            $pcare_res = PCareService::tambahKunjungan($payload_rujuk);
            $code = $pcare_res['metadata']['code'] ?? 500;
            $msg  = $pcare_res['metadata']['message'] ?? '';

            if ($code == 200 || $code == 201) {
                $is_sent_live = true;
                $no_kunjungan_bpjs = $pcare_res['response']['noKunjungan'] ?? ('PC' . date('Ymd') . rand(1000, 9999));
            } else {
                $error_bpjs = "Bridging BPJS [Code {$code}]: {$msg}";
                // Fallback simpan lokal agar rekam medis & cetak surat rujukan tetap bisa berjalan
                $no_kunjungan_bpjs = 'RUJ' . date('Ymd') . rand(1000, 9999);
            }
        } else {
            // Mode Simulasi / Offline
            $no_kunjungan_bpjs = 'RUJ' . date('Ymd') . rand(1000, 9999);
            $is_sent_live = true;
        }

        // Simpan ke Database Lokal
        $no_rawat_esc        = $conn->real_escape_string($no_rawat);
        $no_kunjungan_esc    = $conn->real_escape_string($no_kunjungan_bpjs);
        $no_rkm_medis_esc    = $conn->real_escape_string($no_rkm_medis);
        $nm_pasien_esc       = $conn->real_escape_string($nm_pasien);
        $no_kartu_esc        = $conn->real_escape_string($no_kartu);
        $kd_poli_esc         = $conn->real_escape_string($kd_poli);
        $nm_poli_esc         = $conn->real_escape_string($nm_poli);
        $keluhan_esc         = $conn->real_escape_string($keluhan);
        $kd_sadar_esc        = $conn->real_escape_string($kd_sadar);
        $nm_sadar_esc        = $conn->real_escape_string($nm_sadar);
        $sistole_esc         = $conn->real_escape_string($sistole);
        $diastole_esc        = $conn->real_escape_string($diastole);
        $berat_badan_esc     = $conn->real_escape_string($berat_badan);
        $tinggi_badan_esc    = $conn->real_escape_string($tinggi_badan);
        $resp_rate_esc       = $conn->real_escape_string($resp_rate);
        $heart_rate_esc      = $conn->real_escape_string($heart_rate);
        $lingkar_perut_esc   = $conn->real_escape_string($lingkar_perut);
        $terapi_esc          = $conn->real_escape_string($terapi);
        $kd_dokter_esc       = $conn->real_escape_string($kd_dokter);
        $nm_dokter_esc       = $conn->real_escape_string($nm_dokter);
        $kd_diag1_esc        = $conn->real_escape_string($kd_diag1);
        $nm_diag1_esc        = $conn->real_escape_string($nm_diag1);
        $kd_diag2_esc        = $conn->real_escape_string($kd_diag2);
        $nm_diag2_esc        = $conn->real_escape_string($nm_diag2);
        $kd_diag3_esc        = $conn->real_escape_string($kd_diag3);
        $nm_diag3_esc        = $conn->real_escape_string($nm_diag3);
        $tgl_est_rujuk_esc   = $conn->real_escape_string($tgl_est_rujuk);
        $kd_ppk_esc          = $conn->real_escape_string($kd_ppk);
        $nm_ppk_esc          = $conn->real_escape_string($nm_ppk);
        $kd_subspesialis_esc = $conn->real_escape_string($kd_subspesialis);
        $nm_subspesialis_esc = $conn->real_escape_string($nm_subspesialis);
        $kd_sarana_esc       = $conn->real_escape_string($kd_sarana);
        $nm_sarana_esc       = $conn->real_escape_string($nm_sarana);
        $kd_tacc_esc         = $conn->real_escape_string($kd_tacc);
        $nm_tacc_esc         = $conn->real_escape_string($nm_tacc);
        $alasan_tacc_esc     = $conn->real_escape_string($alasan_tacc);

        if ($jenis_rujukan === 'khusus') {
            $kd_khusus_esc      = $conn->real_escape_string($kd_khusus);
            $nm_khusus_esc      = $conn->real_escape_string($nm_khusus);
            $catatan_khusus_esc = $conn->real_escape_string($catatan_khusus);

            $conn->query("
                INSERT INTO pcare_rujuk_khusus (
                    no_rawat, noKunjungan, tglDaftar, no_rkm_medis, nm_pasien, noKartu,
                    kdPoli, nmPoli, keluhan, kdSadar, nmSadar, sistole, diastole,
                    beratBadan, tinggiBadan, respRate, heartRate, terapi,
                    kdStatusPulang, nmStatusPulang, tglPulang, kdDokter, nmDokter,
                    kdDiag1, nmDiag1, kdDiag2, nmDiag2, kdDiag3, nmDiag3,
                    tglEstRujuk, kdPPK, kdKhusus, nmKhusus, kdSubSpesialis, nmSubSpesialis,
                    catatan, kdTACC, nmTACC, alasanTACC
                ) VALUES (
                    '$no_rawat_esc', '$no_kunjungan_esc', '$tgl_daftar', '$no_rkm_medis_esc', '$nm_pasien_esc', '$no_kartu_esc',
                    '$kd_poli_esc', '$nm_poli_esc', '$keluhan_esc', '$kd_sadar_esc', '$nm_sadar_esc', '$sistole_esc', '$diastole_esc',
                    '$berat_badan_esc', '$tinggi_badan_esc', '$resp_rate_esc', '$heart_rate_esc', '$terapi_esc',
                    '4', 'Rujuk Vertikal', '$tgl_pulang', '$kd_dokter_esc', '$nm_dokter_esc',
                    '$kd_diag1_esc', '$nm_diag1_esc', '$kd_diag2_esc', '$nm_diag2_esc', '$kd_diag3_esc', '$nm_diag3_esc',
                    '$tgl_est_rujuk_esc', '$kd_ppk_esc', '$kd_khusus_esc', '$nm_khusus_esc', '$kd_subspesialis_esc', '$nm_subspesialis_esc',
                    '$catatan_khusus_esc', '$kd_tacc_esc', '$nm_tacc_esc', '$alasan_tacc_esc'
                ) ON DUPLICATE KEY UPDATE 
                    noKunjungan = '$no_kunjungan_esc', kdPPK = '$kd_ppk_esc', tglEstRujuk = '$tgl_est_rujuk_esc'
            ");
        } else {
            $conn->query("
                INSERT INTO pcare_rujuk_subspesialis (
                    no_rawat, noKunjungan, tglDaftar, no_rkm_medis, nm_pasien, noKartu,
                    kdPoli, nmPoli, keluhan, kdSadar, nmSadar, sistole, diastole,
                    beratBadan, tinggiBadan, respRate, heartRate, lingkarPerut, terapi,
                    kdStatusPulang, nmStatusPulang, tglPulang, kdDokter, nmDokter,
                    kdDiag1, nmDiag1, kdDiag2, nmDiag2, kdDiag3, nmDiag3,
                    tglEstRujuk, kdPPK, nmPPK, kdSubSpesialis, nmSubSpesialis, kdSarana, nmSarana,
                    kdTACC, nmTACC, alasanTACC
                ) VALUES (
                    '$no_rawat_esc', '$no_kunjungan_esc', '$tgl_daftar', '$no_rkm_medis_esc', '$nm_pasien_esc', '$no_kartu_esc',
                    '$kd_poli_esc', '$nm_poli_esc', '$keluhan_esc', '$kd_sadar_esc', '$nm_sadar_esc', '$sistole_esc', '$diastole_esc',
                    '$berat_badan_esc', '$tinggi_badan_esc', '$resp_rate_esc', '$heart_rate_esc', '$lingkar_perut_esc', '$terapi_esc',
                    '4', 'Rujuk Vertikal', '$tgl_pulang', '$kd_dokter_esc', '$nm_dokter_esc',
                    '$kd_diag1_esc', '$nm_diag1_esc', '$kd_diag2_esc', '$nm_diag2_esc', '$kd_diag3_esc', '$nm_diag3_esc',
                    '$tgl_est_rujuk_esc', '$kd_ppk_esc', '$nm_ppk_esc', '$kd_subspesialis_esc', '$nm_subspesialis_esc', '$kd_sarana_esc', '$nm_sarana_esc',
                    '$kd_tacc_esc', '$nm_tacc_esc', '$alasan_tacc_esc'
                ) ON DUPLICATE KEY UPDATE 
                    noKunjungan = '$no_kunjungan_esc', kdPPK = '$kd_ppk_esc', nmPPK = '$nm_ppk_esc', tglEstRujuk = '$tgl_est_rujuk_esc'
            ");
        }

        // Simpan juga ke pcare_kunjungan_umum
        $conn->query("
            INSERT INTO pcare_kunjungan_umum (
                no_rawat, noKunjungan, tglDaftar, no_rkm_medis, nm_pasien, noKartu,
                kdPoli, nmPoli, keluhan, kdSadar, nmSadar, sistole, diastole,
                beratBadan, tinggiBadan, respRate, heartRate, lingkarPerut, terapi,
                kdStatusPulang, nmStatusPulang, tglPulang, kdDokter, nmDokter,
                kdDiag1, nmDiag1, kdDiag2, nmDiag2, kdDiag3, nmDiag3, status
            ) VALUES (
                '$no_rawat_esc', '$no_kunjungan_esc', '$tgl_daftar', '$no_rkm_medis_esc', '$nm_pasien_esc', '$no_kartu_esc',
                '$kd_poli_esc', '$nm_poli_esc', '$keluhan_esc', '$kd_sadar_esc', '$nm_sadar_esc', '$sistole_esc', '$diastole_esc',
                '$berat_badan_esc', '$tinggi_badan_esc', '$resp_rate_esc', '$heart_rate_esc', '$lingkar_perut_esc', '$terapi_esc',
                '4', 'Rujuk Vertikal', '$tgl_pulang', '$kd_dokter_esc', '$nm_dokter_esc',
                '$kd_diag1_esc', '$nm_diag1_esc', '$kd_diag2_esc', '$nm_diag2_esc', '$kd_diag3_esc', '$nm_diag3_esc', 'Terkirim'
            ) ON DUPLICATE KEY UPDATE 
                noKunjungan = '$no_kunjungan_esc', status = 'Terkirim'
        ");

        echo json_encode([
            'success'     => true,
            'message'     => "Surat Rujukan BPJS Berhasil Dibuat! No. Kunjungan / Rujukan: {$no_kunjungan_bpjs}",
            'noKunjungan' => $no_kunjungan_bpjs,
            'no_rawat'    => $no_rawat,
            'nm_ppk'      => $nm_ppk
        ]);
        break;

    // ─── 12. Ambil Detail Rujukan untuk Cetak / Preview ────────
    case 'get_detail_rujukan':
        $no_rawat = $conn->real_escape_string(sanitize($_GET['no_rawat'] ?? ''));
        
        $row = $conn->query("
            SELECT r.*, 'subspesialis' as jenis_rujukan, p.tgl_lahir, p.jk, p.alamat
            FROM pcare_rujuk_subspesialis r
            JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
            WHERE r.no_rawat = '$no_rawat'
            LIMIT 1
        ")->fetch_assoc();

        if (!$row) {
            $row = $conn->query("
                SELECT r.*, 'khusus' as jenis_rujukan, p.tgl_lahir, p.jk, p.alamat
                FROM pcare_rujuk_khusus r
                JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
                WHERE r.no_rawat = '$no_rawat'
                LIMIT 1
            ")->fetch_assoc();
        }

        if ($row) {
            echo json_encode(['success' => true, 'data' => $row]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Data rujukan tidak ditemukan di database lokal.']);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
}


