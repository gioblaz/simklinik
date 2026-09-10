<?php
/**
 * SIMKlinik — AJAX Handler: Integrasi Satu Sehat (Kemenkes RI) FHIR R4
 */

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/satusehat_service.php';

header('Content-Type: application/json; charset=utf-8');

$action = sanitize($_GET['action'] ?? $_POST['action'] ?? '');

switch ($action) {
    // ─── 1. Test Koneksi Satu Sehat ───────────────────────────
    case 'test_koneksi':
        $result = test_satusehat_connection();
        echo json_encode($result);
        exit;

    // ─── 2. Lookup IHS Pasien / Praktisi berdasarkan NIK ──────
    case 'lookup_nik':
        $nik  = sanitize($_POST['nik'] ?? $_GET['nik'] ?? '');
        $type = sanitize($_POST['type'] ?? $_GET['type'] ?? 'patient'); // 'patient' or 'practitioner'

        if ($type === 'practitioner') {
            $result = lookup_satusehat_practitioner_ihs($nik);
        } else {
            $result = lookup_satusehat_patient_ihs($nik);
        }
        echo json_encode($result);
        exit;

    // ─── 3. Preview FHIR Bundle JSON ──────────────────────────
    case 'preview_bundle':
        $no_rawat = sanitize($_POST['no_rawat'] ?? $_GET['no_rawat'] ?? '');
        if (empty($no_rawat)) {
            echo json_encode(['success' => false, 'message' => 'No. Rawat tidak valid.']);
            exit;
        }

        $bundle_res = build_satusehat_bundle($no_rawat);
        if ($bundle_res['success']) {
            echo json_encode([
                'success'      => true,
                'no_rawat'     => $no_rawat,
                'patient_name' => $bundle_res['patient_name'],
                'entry_count'  => $bundle_res['entry_count'],
                'json_payload' => json_encode($bundle_res['bundle'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            ]);
        } else {
            echo json_encode($bundle_res);
        }
        exit;

    // ─── 4. Kirim Single Bundle Transaction ───────────────────
    case 'kirim_bundle':
        $no_rawat = sanitize($_POST['no_rawat'] ?? $_GET['no_rawat'] ?? '');
        if (empty($no_rawat)) {
            echo json_encode(['success' => false, 'message' => 'No. Rawat tidak valid.']);
            exit;
        }

        $result = send_satusehat_bundle($no_rawat);
        echo json_encode($result);
        exit;

    // ─── 5. Kirim Massal / Batch Bundles ──────────────────────
    case 'kirim_massal':
        $raw_list = $_POST['no_rawat_list'] ?? [];
        if (is_string($raw_list)) {
            $raw_list = json_decode($raw_list, true) ?: explode(',', $raw_list);
        }

        if (empty($raw_list) || !is_array($raw_list)) {
            echo json_encode(['success' => false, 'message' => 'Pilih setidaknya satu kunjungan untuk dikirim.']);
            exit;
        }

        $success_count = 0;
        $fail_count    = 0;
        $logs          = [];

        foreach ($raw_list as $nr) {
            $nr = trim(sanitize($nr));
            if (empty($nr)) continue;

            $res = send_satusehat_bundle($nr);
            if ($res['success']) {
                $success_count++;
                $logs[] = [
                    'no_rawat'     => $nr,
                    'status'       => 'success',
                    'message'      => $res['message'],
                    'encounter_id' => $res['encounter_id'] ?? ''
                ];
            } else {
                $fail_count++;
                $logs[] = [
                    'no_rawat' => $nr,
                    'status'   => 'error',
                    'message'  => $res['message']
                ];
            }
        }

        echo json_encode([
            'success'       => ($success_count > 0),
            'total'         => count($raw_list),
            'success_count' => $success_count,
            'fail_count'    => $fail_count,
            'message'       => "Pengiriman batch selesai: $success_count berhasil, $fail_count gagal.",
            'logs'          => $logs
        ]);
        exit;

    // ─── 6. Ambil Response Payload & Log Sync ─────────────────
    case 'get_response_log':
        $no_rawat = sanitize($_POST['no_rawat'] ?? $_GET['no_rawat'] ?? '');
        $no_rawat_esc = $conn->real_escape_string($no_rawat);

        $res = $conn->query("
            SELECT * FROM mlite_satu_sehat_response 
            WHERE no_rawat = '$no_rawat_esc' 
            LIMIT 1
        ");

        if ($res && $row = $res->fetch_assoc()) {
            echo json_encode([
                'success'           => true,
                'no_rawat'          => $row['no_rawat'],
                'id_encounter'      => $row['id_encounter'],
                'id_condition'      => $row['id_condition'],
                'status_sync'       => $row['status_sync'],
                'last_sync_at'      => $row['last_sync_at'],
                'bundle_payload'    => $row['bundle_payload'],
                'response_payload'  => $row['response_payload']
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Belum ada riwayat pengiriman untuk kunjungan ini.']);
        }
        exit;

    default:
        echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenali.']);
        exit;
}
