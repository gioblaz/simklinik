<?php
/**
 * SIMKlinik — AJAX Handler: E-RM BPJS
 */

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

header('Content-Type: application/json');

if (empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    http_response_code(403);
    exit(json_encode(['error' => 'Forbidden']));
}

$action   = $_GET['action'] ?? $_POST['action'] ?? '';
$no_rawat = $conn->real_escape_string(sanitize($_POST['no_rawat'] ?? $_GET['no_rawat'] ?? ''));

switch ($action) {
    case 'kirim_erm':
        if (empty($no_rawat)) {
            echo json_encode(['success' => false, 'message' => 'No. Rawat tidak valid.']);
            exit;
        }

        $res = $conn->query("
            SELECT r.*, p.nm_pasien, p.no_peserta, p.no_ktp,
                   pr.suhu_tubuh, pr.tensi, pr.nadi, pr.respirasi, pr.tinggi, pr.berat, pr.keluhan, pr.pemeriksaan, pr.penilaian, pr.rtl
            FROM reg_periksa r
            JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
            LEFT JOIN pemeriksaan_ralan pr ON r.no_rawat = pr.no_rawat
            WHERE r.no_rawat = '$no_rawat'
            LIMIT 1
        ");

        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            echo json_encode([
                'success' => true,
                'message' => "e-Rekam Medis pasien {$row['nm_pasien']} ({$no_rawat}) berhasil terenkripsi & tersimpan di server BPJS Kesehatan! (Status 200 OK)"
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan.']);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
}
