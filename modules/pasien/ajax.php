<?php
/**
 * SIMKlinik — AJAX Handler: Cari Pasien
 * GET ?action=cari&q=... → JSON array pasien
 * GET ?action=detail&rm=... → JSON satu pasien
 * GET ?action=poli → JSON daftar poliklinik aktif
 * GET ?action=dokter&kd_poli=... → JSON dokter di poli
 */

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

header('Content-Type: application/json');

// Hanya untuk AJAX
if (empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    http_response_code(403);
    exit(json_encode(['error' => 'Forbidden']));
}

$action = $_GET['action'] ?? '';

switch ($action) {

    // ─── Cari Pasien (untuk autocomplete) ──────────────────
    case 'cari':
        $q   = $conn->real_escape_string(sanitize($_GET['q'] ?? ''));
        $res = $conn->query("
            SELECT p.no_rkm_medis, p.nm_pasien, p.jk, p.tgl_lahir, p.alamat,
                   p.no_ktp, p.no_peserta, p.no_tlp, p.kd_pj, pj.png_jawab as nm_penjab
            FROM pasien p
            LEFT JOIN penjab pj ON p.kd_pj = pj.kd_pj
            WHERE p.nm_pasien LIKE '%$q%'
               OR p.no_rkm_medis LIKE '%$q%'
               OR p.no_ktp LIKE '%$q%'
               OR p.no_peserta LIKE '%$q%'
               OR p.no_tlp LIKE '%$q%'
               OR p.alamat LIKE '%$q%'
            ORDER BY p.nm_pasien ASC
            LIMIT 15
        ");
        $data = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $row['umur'] = hitung_umur($row['tgl_lahir']);
                $data[]      = $row;
            }
        }
        echo json_encode(['success' => true, 'data' => $data]);
        break;

    // ─── Detail Pasien ──────────────────────────────────────
    case 'detail':
        $rm  = $conn->real_escape_string(sanitize($_GET['rm'] ?? ''));
        $res = $conn->query("
            SELECT p.*, pj.png_jawab as nm_penjab
            FROM pasien p
            LEFT JOIN penjab pj ON p.kd_pj = pj.kd_pj
            WHERE p.no_rkm_medis = '$rm' LIMIT 1
        ");
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $row['umur'] = hitung_umur($row['tgl_lahir']);
            echo json_encode(['success' => true, 'data' => $row]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Pasien tidak ditemukan']);
        }
        break;

    // ─── Daftar Poliklinik ──────────────────────────────────
    case 'poli':
        $res  = $conn->query("SELECT kd_poli, nm_poli FROM poliklinik WHERE status = '1' ORDER BY nm_poli");
        $data = [];
        if ($res) while ($row = $res->fetch_assoc()) $data[] = $row;
        echo json_encode(['success' => true, 'data' => $data]);
        break;

    // ─── Dokter berdasarkan Poli ────────────────────────────
    case 'dokter':
        $kd_poli = $conn->real_escape_string(sanitize($_GET['kd_poli'] ?? ''));
        $res     = $conn->query("
            SELECT d.kd_dokter, d.nm_dokter, s.nm_sps as nm_spesialis
            FROM dokter d
            LEFT JOIN spesialis s ON d.kd_sps = s.kd_sps
            WHERE d.status = '1'
            ORDER BY d.nm_dokter
        ");
        $data = [];
        if ($res) while ($row = $res->fetch_assoc()) $data[] = $row;
        echo json_encode(['success' => true, 'data' => $data]);
        break;

    // ─── Generate No. Rawat Preview ─────────────────────────
    case 'no_rawat':
        echo json_encode(['success' => true, 'no_rawat' => generate_no_rawat()]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
}
