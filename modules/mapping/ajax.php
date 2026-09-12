<?php
/**
 * SIMKlinik — AJAX Handler Unified Mapping (PCare BPJS & Satu Sehat Kemenkes)
 */

ob_start();
header('Content-Type: application/json');

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/pcare_service.php';
require_once dirname(__DIR__, 2) . '/includes/satusehat_service.php';

if (!is_logged_in() || !can_access('mapping')) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
    exit;
}

$action = sanitize($_POST['action'] ?? $_GET['action'] ?? '');

// ─── 1. PCARE: Tarik Daftar Dokter dari PCare BPJS ───────────────
if ($action === 'pcare_tarik_dokter') {
    $res = PCareService::getDokter(0, 50);
    $code = $res['metadata']['code'] ?? ($res['metaData']['code'] ?? 500);
    $list = $res['response']['list'] ?? [];

    ob_end_clean();
    if ($code == 200) {
        echo json_encode(['success' => true, 'list' => $list, 'message' => 'Berhasil mengambil ' . count($list) . ' dokter dari PCare BPJS.']);
    } else {
        $msg = $res['metadata']['message'] ?? ($res['metaData']['message'] ?? 'Gagal mengambil data dokter PCare');
        echo json_encode(['success' => false, 'message' => "PCare [{$code}]: {$msg}"]);
    }
    exit;
}

// ─── 2. PCARE: Simpan Mapping Dokter ────────────────────────────
if ($action === 'pcare_save_dokter') {
    $kd_dokter = $conn->real_escape_string(trim($_POST['kd_dokter'] ?? ''));
    $kd_dokter_pcare = $conn->real_escape_string(trim($_POST['kd_dokter_pcare'] ?? ''));
    $nm_dokter_pcare = $conn->real_escape_string(trim($_POST['nm_dokter_pcare'] ?? ''));

    if (empty($kd_dokter) || empty($kd_dokter_pcare)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Dokter SIMKlinik dan Kode Dokter PCare wajib diisi.']);
        exit;
    }

    $q = $conn->query("
        INSERT INTO maping_dokter_pcare (kd_dokter, kd_dokter_pcare, nm_dokter_pcare)
        VALUES ('$kd_dokter', '$kd_dokter_pcare', '$nm_dokter_pcare')
        ON DUPLICATE KEY UPDATE kd_dokter_pcare = '$kd_dokter_pcare', nm_dokter_pcare = '$nm_dokter_pcare'
    ");

    ob_end_clean();
    if ($q) {
        echo json_encode(['success' => true, 'message' => 'Mapping Dokter PCare berhasil disimpan.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan mapping: ' . $conn->error]);
    }
    exit;
}

// ─── 3. PCARE: Hapus Mapping Dokter ─────────────────────────────
if ($action === 'pcare_del_dokter') {
    $kd_dokter = $conn->real_escape_string(trim($_POST['kd_dokter'] ?? ''));
    $conn->query("DELETE FROM maping_dokter_pcare WHERE kd_dokter = '$kd_dokter'");
    ob_end_clean();
    echo json_encode(['success' => true, 'message' => 'Mapping Dokter PCare berhasil dihapus.']);
    exit;
}

// ─── 4. PCARE: Tarik Daftar Poli dari PCare BPJS ─────────────────
if ($action === 'pcare_tarik_poli') {
    $res = PCareService::getPoli(0, 50);
    $code = $res['metadata']['code'] ?? ($res['metaData']['code'] ?? 500);
    $list = $res['response']['list'] ?? [];

    ob_end_clean();
    if ($code == 200) {
        echo json_encode(['success' => true, 'list' => $list, 'message' => 'Berhasil mengambil ' . count($list) . ' poli dari PCare BPJS.']);
    } else {
        $msg = $res['metadata']['message'] ?? ($res['metaData']['message'] ?? 'Gagal mengambil data poli PCare');
        echo json_encode(['success' => false, 'message' => "PCare [{$code}]: {$msg}"]);
    }
    exit;
}

// ─── 5. PCARE: Simpan Mapping Poli ──────────────────────────────
if ($action === 'pcare_save_poli') {
    $kd_poli_rs = $conn->real_escape_string(trim($_POST['kd_poli_rs'] ?? ''));
    $kd_poli_pcare = $conn->real_escape_string(trim($_POST['kd_poli_pcare'] ?? ''));
    $nm_poli_pcare = $conn->real_escape_string(trim($_POST['nm_poli_pcare'] ?? ''));

    if (empty($kd_poli_rs) || empty($kd_poli_pcare)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Poliklinik SIMKlinik dan Kode Poli PCare wajib diisi.']);
        exit;
    }

    $q = $conn->query("
        INSERT INTO maping_poliklinik_pcare (kd_poli_rs, kd_poli_pcare, nm_poli_pcare)
        VALUES ('$kd_poli_rs', '$kd_poli_pcare', '$nm_poli_pcare')
        ON DUPLICATE KEY UPDATE kd_poli_pcare = '$kd_poli_pcare', nm_poli_pcare = '$nm_poli_pcare'
    ");

    ob_end_clean();
    if ($q) {
        echo json_encode(['success' => true, 'message' => 'Mapping Poliklinik PCare berhasil disimpan.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan mapping: ' . $conn->error]);
    }
    exit;
}

// ─── 6. PCARE: Hapus Mapping Poli ───────────────────────────────
if ($action === 'pcare_del_poli') {
    $kd_poli_rs = $conn->real_escape_string(trim($_POST['kd_poli_rs'] ?? ''));
    $conn->query("DELETE FROM maping_poliklinik_pcare WHERE kd_poli_rs = '$kd_poli_rs'");
    ob_end_clean();
    echo json_encode(['success' => true, 'message' => 'Mapping Poliklinik PCare berhasil dihapus.']);
    exit;
}

// ─── 7. PCARE: Simpan Mapping Obat ──────────────────────────────
if ($action === 'pcare_save_obat') {
    $kode_brng = $conn->real_escape_string(trim($_POST['kode_brng'] ?? ''));
    $kode_brng_pcare = $conn->real_escape_string(trim($_POST['kode_brng_pcare'] ?? ''));
    $nama_brng_pcare = $conn->real_escape_string(trim($_POST['nama_brng_pcare'] ?? ''));

    if (empty($kode_brng) || empty($kode_brng_pcare)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Obat SIMKlinik dan Kode Obat PCare wajib diisi.']);
        exit;
    }

    $q = $conn->query("
        INSERT INTO maping_obat_pcare (kode_brng, kode_brng_pcare, nama_brng_pcare)
        VALUES ('$kode_brng', '$kode_brng_pcare', '$nama_brng_pcare')
        ON DUPLICATE KEY UPDATE kode_brng_pcare = '$kode_brng_pcare', nama_brng_pcare = '$nama_brng_pcare'
    ");

    ob_end_clean();
    if ($q) {
        echo json_encode(['success' => true, 'message' => 'Mapping Obat PCare berhasil disimpan.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan mapping: ' . $conn->error]);
    }
    exit;
}

// ─── 8. PCARE: Hapus Mapping Obat ───────────────────────────────
if ($action === 'pcare_del_obat') {
    $kode_brng = $conn->real_escape_string(trim($_POST['kode_brng'] ?? ''));
    $conn->query("DELETE FROM maping_obat_pcare WHERE kode_brng = '$kode_brng'");
    ob_end_clean();
    echo json_encode(['success' => true, 'message' => 'Mapping Obat PCare berhasil dihapus.']);
    exit;
}

// ─── 9. PCARE: Simpan Mapping Tindakan ──────────────────────────
if ($action === 'pcare_save_tindakan') {
    $kd_jenis_prw = $conn->real_escape_string(trim($_POST['kd_jenis_prw'] ?? ''));
    $kd_tindakan_pcare = $conn->real_escape_string(trim($_POST['kd_tindakan_pcare'] ?? ''));
    $nm_tindakan_pcare = $conn->real_escape_string(trim($_POST['nm_tindakan_pcare'] ?? ''));

    if (empty($kd_jenis_prw) || empty($kd_tindakan_pcare)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Tindakan SIMKlinik dan Kode Tindakan PCare wajib diisi.']);
        exit;
    }

    $conn->query("
        CREATE TABLE IF NOT EXISTS `maping_tindakan_pcare` (
            `kd_jenis_prw` varchar(15) NOT NULL,
            `kd_tindakan_pcare` varchar(15) NOT NULL,
            `nm_tindakan_pcare` varchar(100) DEFAULT NULL,
            PRIMARY KEY (`kd_jenis_prw`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $q = $conn->query("
        INSERT INTO maping_tindakan_pcare (kd_jenis_prw, kd_tindakan_pcare, nm_tindakan_pcare)
        VALUES ('$kd_jenis_prw', '$kd_tindakan_pcare', '$nm_tindakan_pcare')
        ON DUPLICATE KEY UPDATE kd_tindakan_pcare = '$kd_tindakan_pcare', nm_tindakan_pcare = '$nm_tindakan_pcare'
    ");

    ob_end_clean();
    if ($q) {
        echo json_encode(['success' => true, 'message' => 'Mapping Tindakan PCare berhasil disimpan.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan mapping: ' . $conn->error]);
    }
    exit;
}

// ─── 10. PCARE: Hapus Mapping Tindakan ──────────────────────────
if ($action === 'pcare_del_tindakan') {
    $kd_jenis_prw = $conn->real_escape_string(trim($_POST['kd_jenis_prw'] ?? ''));
    $conn->query("DELETE FROM maping_tindakan_pcare WHERE kd_jenis_prw = '$kd_jenis_prw'");
    ob_end_clean();
    echo json_encode(['success' => true, 'message' => 'Mapping Tindakan PCare berhasil dihapus.']);
    exit;
}

// ─── 11. SATU SEHAT: Lookup NIK Praktisi Dokter ke Kemenkes ──────
if ($action === 'satusehat_lookup_praktisi') {
    $nik = preg_replace('/[^0-9]/', '', trim($_POST['nik'] ?? ''));
    if (strlen($nik) < 16) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'NIK KTP tidak valid (wajib 16 digit angka).']);
        exit;
    }

    $res = get_practitioner_by_nik($nik);
    ob_end_clean();
    if ($res['success'] && !empty($res['id'])) {
        echo json_encode([
            'success' => true,
            'practitioner_id' => $res['id'],
            'nama' => $res['name'] ?? '',
            'message' => "IHS Practitioner ditemukan: {$res['id']} ({$res['name']})"
        ]);
    } else {
        $err = $res['error'] ?? 'Praktisi tidak ditemukan di Satu Sehat Kemenkes.';
        echo json_encode(['success' => false, 'message' => $err]);
    }
    exit;
}

// ─── 12. SATU SEHAT: Simpan Mapping Praktisi Dokter ─────────────
if ($action === 'satusehat_save_praktisi') {
    $kd_dokter = $conn->real_escape_string(trim($_POST['kd_dokter'] ?? ''));
    $practitioner_id = $conn->real_escape_string(trim($_POST['practitioner_id'] ?? ''));

    if (empty($kd_dokter) || empty($practitioner_id)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Dokter dan IHS Practitioner ID wajib diisi.']);
        exit;
    }

    $conn->query("
        CREATE TABLE IF NOT EXISTS `mlite_satu_sehat_mapping_praktisi` (
            `kd_dokter` varchar(20) NOT NULL,
            `practitioner_id` varchar(40) NOT NULL,
            PRIMARY KEY (`kd_dokter`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $q = $conn->query("
        INSERT INTO mlite_satu_sehat_mapping_praktisi (kd_dokter, practitioner_id)
        VALUES ('$kd_dokter', '$practitioner_id')
        ON DUPLICATE KEY UPDATE practitioner_id = '$practitioner_id'
    ");

    ob_end_clean();
    if ($q) {
        echo json_encode(['success' => true, 'message' => 'Mapping Praktisi Satu Sehat berhasil disimpan.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan mapping: ' . $conn->error]);
    }
    exit;
}

// ─── 13. SATU SEHAT: Hapus Mapping Praktisi Dokter ──────────────
if ($action === 'satusehat_del_praktisi') {
    $kd_dokter = $conn->real_escape_string(trim($_POST['kd_dokter'] ?? ''));
    $conn->query("DELETE FROM mlite_satu_sehat_mapping_praktisi WHERE kd_dokter = '$kd_dokter'");
    ob_end_clean();
    echo json_encode(['success' => true, 'message' => 'Mapping Praktisi Satu Sehat berhasil dihapus.']);
    exit;
}

// ─── 14. SATU SEHAT: Simpan Mapping Lokasi Poliklinik ───────────
if ($action === 'satusehat_save_lokasi') {
    $kd_poli = $conn->real_escape_string(trim($_POST['kd_poli'] ?? ''));
    $id_org  = $conn->real_escape_string(trim($_POST['id_organisasi_satusehat'] ?? ''));
    $id_loc  = $conn->real_escape_string(trim($_POST['id_lokasi_satusehat'] ?? ''));
    $long    = $conn->real_escape_string(trim($_POST['longitude'] ?? ''));
    $lat     = $conn->real_escape_string(trim($_POST['latitude'] ?? ''));
    $alt     = $conn->real_escape_string(trim($_POST['altittude'] ?? ''));

    if (empty($kd_poli) || empty($id_loc)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Poliklinik dan ID Lokasi Satu Sehat wajib diisi.']);
        exit;
    }

    $q = $conn->query("
        INSERT INTO satu_sehat_mapping_lokasi_ralan (kd_poli, id_organisasi_satusehat, id_lokasi_satusehat, longitude, latitude, altittude)
        VALUES ('$kd_poli', '$id_org', '$id_loc', '$long', '$lat', '$alt')
        ON DUPLICATE KEY UPDATE id_organisasi_satusehat = '$id_org', id_lokasi_satusehat = '$id_loc', longitude = '$long', latitude = '$lat', altittude = '$alt'
    ");

    ob_end_clean();
    if ($q) {
        echo json_encode(['success' => true, 'message' => 'Mapping Lokasi Satu Sehat berhasil disimpan.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan mapping: ' . $conn->error]);
    }
    exit;
}

// ─── 15. SATU SEHAT: Hapus Mapping Lokasi Poliklinik ────────────
if ($action === 'satusehat_del_lokasi') {
    $kd_poli = $conn->real_escape_string(trim($_POST['kd_poli'] ?? ''));
    $conn->query("DELETE FROM satu_sehat_mapping_lokasi_ralan WHERE kd_poli = '$kd_poli'");
    ob_end_clean();
    echo json_encode(['success' => true, 'message' => 'Mapping Lokasi Satu Sehat berhasil dihapus.']);
    exit;
}

// ─── 16. SATU SEHAT: Simpan Mapping Lab LOINC ───────────────────
if ($action === 'satusehat_save_lab') {
    $id_template = (int)($_POST['id_template'] ?? 0);
    $kd_jenis    = $conn->real_escape_string(trim($_POST['kd_jenis_prw'] ?? ''));
    $code        = $conn->real_escape_string(trim($_POST['code'] ?? ''));
    $system      = $conn->real_escape_string(trim($_POST['system'] ?: 'http://loinc.org'));
    $display     = $conn->real_escape_string(trim($_POST['display'] ?? ''));

    if (empty($id_template) && empty($kd_jenis)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Pemeriksaan Lab wajib dipilih.']);
        exit;
    }

    if (!empty($code)) {
        $conn->query("
            CREATE TABLE IF NOT EXISTS `mlite_satu_sehat_mapping_lab` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `id_template` int(11) DEFAULT NULL,
                `kd_jenis_prw` varchar(15) DEFAULT NULL,
                `code` varchar(15) DEFAULT NULL,
                `system` varchar(100) DEFAULT 'http://loinc.org',
                `display` varchar(80) DEFAULT NULL,
                `sampel_code` varchar(15) DEFAULT NULL,
                `sampel_system` varchar(100) DEFAULT NULL,
                `sampel_display` varchar(80) DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        if ($id_template > 0) {
            $conn->query("DELETE FROM mlite_satu_sehat_mapping_lab WHERE id_template = $id_template");
            $conn->query("
                INSERT INTO mlite_satu_sehat_mapping_lab (id_template, kd_jenis_prw, code, `system`, display)
                VALUES ($id_template, '$kd_jenis', '$code', '$system', '$display')
            ");
        } else {
            $conn->query("DELETE FROM mlite_satu_sehat_mapping_lab WHERE kd_jenis_prw = '$kd_jenis'");
            $conn->query("
                INSERT INTO mlite_satu_sehat_mapping_lab (kd_jenis_prw, code, `system`, display)
                VALUES ('$kd_jenis', '$code', '$system', '$display')
            ");
        }
    } else {
        if ($id_template > 0) {
            $conn->query("DELETE FROM mlite_satu_sehat_mapping_lab WHERE id_template = $id_template");
        } else {
            $conn->query("DELETE FROM mlite_satu_sehat_mapping_lab WHERE kd_jenis_prw = '$kd_jenis'");
        }
    }

    ob_end_clean();
    echo json_encode(['success' => true, 'message' => 'Mapping LOINC Laboratorium berhasil disimpan.']);
    exit;
}

// ─── 17. SATU SEHAT: Simpan Mapping Obat KFA ────────────────────
if ($action === 'satusehat_save_obat') {
    $kode_brng = $conn->real_escape_string(trim($_POST['kode_brng'] ?? ''));
    $obat_code = $conn->real_escape_string(trim($_POST['obat_code'] ?? ''));
    $obat_disp = $conn->real_escape_string(trim($_POST['obat_display'] ?? ''));
    $form_code = $conn->real_escape_string(trim($_POST['form_code'] ?? ''));
    $form_disp = $conn->real_escape_string(trim($_POST['form_display'] ?? ''));
    $route_code= $conn->real_escape_string(trim($_POST['route_code'] ?? ''));
    $route_disp= $conn->real_escape_string(trim($_POST['route_display'] ?? ''));

    if (empty($kode_brng)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Pilih obat terlebih dahulu.']);
        exit;
    }

    if (!empty($obat_code)) {
        $conn->query("
            INSERT INTO satu_sehat_mapping_obat (
                kode_brng, obat_code, obat_system, obat_display,
                form_code, form_system, form_display,
                route_code, route_system, route_display
            ) VALUES (
                '$kode_brng', '$obat_code', 'http://sys-ids.kemkes.go.id/kfa', '$obat_disp',
                '$form_code', 'http://terminology.kemkes.go.id/CodeSystem/medication-form', '$form_disp',
                '$route_code', 'http://www.whocc.no/atc', '$route_disp'
            ) ON DUPLICATE KEY UPDATE 
                obat_code = '$obat_code', obat_display = '$obat_disp',
                form_code = '$form_code', form_display = '$form_disp',
                route_code = '$route_code', route_display = '$route_disp'
        ");
    } else {
        $conn->query("DELETE FROM satu_sehat_mapping_obat WHERE kode_brng = '$kode_brng'");
    }

    ob_end_clean();
    echo json_encode(['success' => true, 'message' => 'Mapping KFA Obat berhasil disimpan.']);
    exit;
}

// Default Fallback
ob_end_clean();
echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenali.']);
exit;
