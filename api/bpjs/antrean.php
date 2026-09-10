<?php
/**
 * SIMKlinik — REST API Webhook Server Antrean Online BPJS (Mobile JKN)
 * Endpoint yang diakses oleh Server BPJS Kesehatan untuk Mobile JKN
 */

header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/bpjs_antrean.php';

$action = $_GET['action'] ?? $_GET['endpoint'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Baca raw JSON input
$raw_input = file_get_contents('php://input');
$input     = json_decode($raw_input, true) ?: [];

function response_bpjs($data, int $code = 200, string $message = 'Ok') {
    http_response_code($code === 200 ? 200 : ($code > 500 ? 500 : 400));
    echo json_encode([
        'response' => $data,
        'metadata' => [
            'message' => $message,
            'code'    => $code
        ]
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

switch ($action) {

    // ─── 1. Status Antrean Per Poli & Tanggal ──────────────────
    case 'statusantrean':
        $kd_poli = $conn->real_escape_string($_GET['kodepoli'] ?? 'U0001');
        $tgl     = $conn->real_escape_string($_GET['tanggal'] ?? date('Y-m-d'));

        // Nama Poli
        $poli_row = $conn->query("SELECT nm_poli FROM poliklinik WHERE kd_poli = '$kd_poli' LIMIT 1")->fetch_assoc();
        $nm_poli  = $poli_row['nm_poli'] ?? 'Poli Umum';

        // Hitung antrian hari itu
        $total_res = $conn->query("SELECT COUNT(*) as t FROM reg_periksa WHERE tgl_registrasi = '$tgl' AND kd_poli = '$kd_poli' AND stts != 'Batal'");
        $total = $total_res ? (int)$total_res->fetch_assoc()['t'] : 0;

        $selesai_res = $conn->query("SELECT COUNT(*) as t FROM reg_periksa WHERE tgl_registrasi = '$tgl' AND kd_poli = '$kd_poli' AND stts = 'Sudah'");
        $selesai = $selesai_res ? (int)$selesai_res->fetch_assoc()['t'] : 0;

        $sisa = max(0, $total - $selesai);

        // Antrean terakhir dipanggil
        $last_res = $conn->query("SELECT no_reg FROM reg_periksa WHERE tgl_registrasi = '$tgl' AND kd_poli = '$kd_poli' AND stts = 'Sudah' ORDER BY jam_reg DESC LIMIT 1");
        $last_panggil = ($last_res && $last_res->num_rows > 0) ? $last_res->fetch_assoc()['no_reg'] : '-';

        $kuota_jkn = 30;
        $kuota_nonjkn = 20;

        response_bpjs([
            'namapoli'        => $nm_poli,
            'totalantrean'    => $total,
            'sisaantrean'     => $sisa,
            'antreanpanggil'  => $last_panggil,
            'sisakuotajkn'    => max(0, $kuota_jkn - $total),
            'kuotajkn'        => $kuota_jkn,
            'sisakuotanonjkn' => max(0, $kuota_nonjkn - $total),
            'kuotanonjkn'     => $kuota_nonjkn,
            'keterangan'      => 'Pelayanan buka pukul 08:00 - 16:00 WIB'
        ], 200, 'Ok');
        break;

    // ─── 2. Ambil Antrean Mobile JKN (Booking Baru) ───────────
    case 'ambilantrean':
        if ($method !== 'POST') response_bpjs(null, 405, 'Method Not Allowed');

        $nomorkartu     = $conn->real_escape_string($input['nomorkartu'] ?? '');
        $nik            = $conn->real_escape_string($input['nik'] ?? '');
        $nohp           = $conn->real_escape_string($input['nohp'] ?? '');
        $kodepoli       = $conn->real_escape_string($input['kodepoli'] ?? 'U0001');
        $norm           = $conn->real_escape_string($input['norm'] ?? '');
        $tgl_periksa    = $conn->real_escape_string($input['tanggalperiksa'] ?? date('Y-m-d'));
        $kodedokter     = $conn->real_escape_string($input['kodedokter'] ?? 'DR001');
        $jampraktek     = $conn->real_escape_string($input['jampraktek'] ?? '08:00-14:00');
        $jeniskunjungan = (int)($input['jeniskunjungan'] ?? 1);
        $nomorreferensi = $conn->real_escape_string($input['nomorreferensi'] ?? '');

        // Validasi Pasien
        $p_res = $conn->query("SELECT no_rkm_medis, nm_pasien FROM pasien WHERE no_rkm_medis = '$norm' OR no_ktp = '$nik' OR no_peserta = '$nomorkartu' LIMIT 1");
        if (!$p_res || $p_res->num_rows === 0) {
            response_bpjs(null, 201, 'Data pasien tidak ditemukan di rekam medis klinik.');
        }
        $pasien = $p_res->fetch_assoc();
        $no_rm  = $pasien['no_rkm_medis'];

        // Cek apakah sudah pernah booking di tanggal & poli yang sama
        $cek_b = $conn->query("SELECT kodebooking FROM mlite_antrian_referensi WHERE no_rkm_medis = '$no_rm' AND tanggal_periksa = '$tgl_periksa' LIMIT 1");
        if ($cek_b && $cek_b->num_rows > 0) {
            response_bpjs(null, 201, 'Pasien sudah memiliki nomor antrean aktif pada tanggal tersebut.');
        }

        // Generate No Antrean & Kode Booking
        $count_res = $conn->query("SELECT COUNT(*) as t FROM mlite_antrian_referensi WHERE tanggal_periksa = '$tgl_periksa'");
        $cnt = $count_res ? (int)$count_res->fetch_assoc()['t'] + 1 : 1;

        $angka_antrean  = $cnt;
        $nomor_antrean  = 'A-' . sprintf('%03d', $cnt);
        $kode_booking   = date('Ymd', strtotime($tgl_periksa)) . sprintf('%04d', $cnt);

        // Estimasi waktu dilayani (cth: mulai 08:30 + per pasien 10 menit)
        $estimasi_timestamp = strtotime("$tgl_periksa 08:30:00") + (($cnt - 1) * 600);
        $estimasi_ms = $estimasi_timestamp * 1000;

        // Insert ke mlite_antrian_referensi
        $conn->query("
            INSERT INTO mlite_antrian_referensi (
                tanggal_periksa, no_rkm_medis, nomor_kartu, nomor_referensi,
                kodebooking, jenis_kunjungan, status_kirim, keterangan
            ) VALUES (
                '$tgl_periksa', '$no_rm', '$nomorkartu', '$nomorreferensi',
                '$kode_booking', '$jeniskunjungan', 'Terkirim', 'Booking Mobile JKN'
            )
        ");

        // Insert ke booking_registrasi
        $conn->query("
            INSERT INTO booking_registrasi (
                tanggal_booking, jam_booking, no_rkm_medis, tanggal_periksa,
                kd_dokter, kd_poli, no_reg, kd_pj, limit_reg, waktu_kunjungan, status
            ) VALUES (
                CURDATE(), CURTIME(), '$no_rm', '$tgl_periksa',
                '$kodedokter', '$kodepoli', '$nomor_antrean', 'BPJ', 30, FROM_UNIXTIME($estimasi_timestamp), 'Terdaftar'
            )
        ");

        // Data poli & dokter
        $nm_poli_row = $conn->query("SELECT nm_poli FROM poliklinik WHERE kd_poli = '$kodepoli'")->fetch_assoc();
        $nm_dok_row  = $conn->query("SELECT nm_dokter FROM dokter WHERE kd_dokter = '$kodedokter'")->fetch_assoc();

        response_bpjs([
            'nomorantrean'    => $nomor_antrean,
            'angkaantrean'    => $angka_antrean,
            'kodebooking'     => $kode_booking,
            'norm'            => $no_rm,
            'namapoli'        => $nm_poli_row['nm_poli'] ?? 'Poli Umum',
            'namadokter'      => $nm_dok_row['nm_dokter'] ?? 'Dokter Jaga',
            'estimasidilayani'=> $estimasi_ms,
            'sisakuotajkn'    => max(0, 30 - $cnt),
            'kuotajkn'        => 30,
            'sisakuotanonjkn' => 20,
            'kuotanonjkn'     => 20,
            'keterangan'      => 'Harap hadir di klinik minimal 15 menit sebelum estimasi waktu pemeriksaan.'
        ], 200, 'Ok');
        break;

    // ─── 3. Batal Antrean Mobile JKN ──────────────────────────
    case 'batalantrean':
        if ($method !== 'POST') response_bpjs(null, 405, 'Method Not Allowed');

        $kodebooking = $conn->real_escape_string($input['kodebooking'] ?? '');
        $keterangan  = $conn->real_escape_string($input['keterangan'] ?? 'Dibatalkan oleh Pasien');

        $b_res = $conn->query("SELECT * FROM mlite_antrian_referensi WHERE kodebooking = '$kodebooking' LIMIT 1");
        if (!$b_res || $b_res->num_rows === 0) {
            response_bpjs(null, 201, 'Kode booking tidak ditemukan.');
        }

        $conn->query("UPDATE mlite_antrian_referensi SET status_kirim = 'Batal', keterangan = '$keterangan' WHERE kodebooking = '$kodebooking'");
        $conn->query("UPDATE booking_registrasi SET status = 'Batal' WHERE no_rkm_medis = (SELECT no_rkm_medis FROM mlite_antrian_referensi WHERE kodebooking = '$kodebooking')");

        response_bpjs(null, 200, 'Antrean berhasil dibatalkan.');
        break;

    // ─── 4. Check-in Pasien di Faskes (Task 1) ─────────────────
    case 'checkin':
        if ($method !== 'POST') response_bpjs(null, 405, 'Method Not Allowed');

        $kodebooking = $conn->real_escape_string($input['kodebooking'] ?? '');
        $waktu       = $input['waktu'] ?? intval(microtime(true) * 1000);

        $b_res = $conn->query("SELECT * FROM mlite_antrian_referensi WHERE kodebooking = '$kodebooking' LIMIT 1");
        if (!$b_res || $b_res->num_rows === 0) {
            response_bpjs(null, 201, 'Kode booking tidak ditemukan.');
        }

        // Catat Task 1
        BpjsAntreanService::updateTaskId($kodebooking, 1, $waktu);

        response_bpjs([
            'kodebooking' => $kodebooking,
            'status'      => 'Check-in Berhasil',
            'waktu'       => $waktu
        ], 200, 'Ok');
        break;

    // ─── 5. Jadwal Dokter ─────────────────────────────────────
    case 'jadwaldokter':
        $kd_poli = $conn->real_escape_string($_GET['kodepoli'] ?? '');
        $tgl     = $conn->real_escape_string($_GET['tanggal'] ?? date('Y-m-d'));

        $sql = "
            SELECT d.kd_dokter as kodedokter, d.nm_dokter as namadokter,
                   pol.kd_poli as kodepoli, pol.nm_poli as namapoli,
                   '08:00-14:00' as jampraktek, 30 as kapasitas
            FROM dokter d
            CROSS JOIN poliklinik pol
            WHERE d.status = '1' AND pol.status = '1'
        ";
        if ($kd_poli) $sql .= " AND pol.kd_poli = '$kd_poli'";

        $res = $conn->query($sql);
        $list = [];
        if ($res) while ($r = $res->fetch_assoc()) $list[] = $r;

        response_bpjs($list, 200, 'Ok');
        break;

    // ─── 6. Pasien Baru / Validasi NIK ────────────────────────
    case 'pasienbaru':
        $nik = $conn->real_escape_string($_GET['nik'] ?? '');
        $res = $conn->query("SELECT no_rkm_medis, nm_pasien, no_ktp, no_peserta FROM pasien WHERE no_ktp = '$nik' LIMIT 1");
        if ($res && $res->num_rows > 0) {
            response_bpjs($res->fetch_assoc(), 200, 'Pasien sudah terdaftar');
        } else {
            response_bpjs(null, 201, 'Pasien belum terdaftar');
        }
        break;

    default:
        response_bpjs([
            'service' => 'SIMKlinik Antrean Online BPJS REST API',
            'status'  => 'Active & Ready',
            'version' => '2.0.0',
            'time'    => date('Y-m-d H:i:s')
        ], 200, 'Service Available');
        break;
}
