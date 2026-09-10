<?php
/**
 * SIMKlinik — Satu Sehat (Kemenkes RI) HL7 FHIR R4 Service Library
 *
 * Mendukung autentikasi OAuth2, pengujian koneksi, pencarian IHS NIK pasien/praktisi,
 * dan penyusunan serta transmisi Bundle Transaction (Encounter, Condition, Observation,
 * DiagnosticReport Lab, MedicationRequest & MedicationDispense).
 */

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

// Pastikan skema tabel Satu Sehat lengkap
function ensure_satusehat_schema() {
    global $conn;
    static $checked = false;
    if ($checked) return;
    $checked = true;

    // Pastikan tabel mlite_satu_sehat_response ada
    $conn->query("
        CREATE TABLE IF NOT EXISTS `mlite_satu_sehat_response` (
            `no_rawat` varchar(17) NOT NULL,
            `id_encounter` varchar(50) DEFAULT NULL,
            `id_condition` varchar(50) DEFAULT NULL,
            `id_medication_request` varchar(50) DEFAULT NULL,
            `status_sync` varchar(20) DEFAULT 'Unsynced',
            `last_sync_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `bundle_payload` longtext DEFAULT NULL,
            `response_payload` longtext DEFAULT NULL,
            PRIMARY KEY (`no_rawat`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Pastikan kolom-kolom baru ada
    $cols = [];
    $res = $conn->query("SHOW COLUMNS FROM mlite_satu_sehat_response");
    if ($res) {
        while ($r = $res->fetch_assoc()) $cols[] = $r['Field'];
    }

    $needed = [
        'status_sync'      => "VARCHAR(20) DEFAULT 'Unsynced'",
        'last_sync_at'     => "DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
        'bundle_payload'   => "LONGTEXT DEFAULT NULL",
        'response_payload' => "LONGTEXT DEFAULT NULL"
    ];
    foreach ($needed as $col => $def) {
        if (!in_array($col, $cols)) {
            $conn->query("ALTER TABLE mlite_satu_sehat_response ADD COLUMN `$col` $def");
        }
    }
}
ensure_satusehat_schema();

// ─── 1. Ambil Pengaturan Satu Sehat ──────────────────────────
function get_satusehat_config() {
    global $conn;
    $cfg = [
        'organizationid' => '100023305',
        'clientid'       => '',
        'secretkey'      => '',
        'authurl'        => 'https://api-satusehat.kemkes.go.id/oauth2/v1',
        'fhirurl'        => 'https://api-satusehat.kemkes.go.id/fhir-r4/v1',
        'location_id'    => ''
    ];

    $res = $conn->query("SELECT field, value FROM mlite_settings WHERE module='satu_sehat'");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $cfg[$r['field']] = $r['value'];
        }
    }
    return $cfg;
}

// ─── 2. Manajemen Access Token (OAuth2) ───────────────────────
function get_satusehat_token($force_refresh = false) {
    global $conn;
    $cfg = get_satusehat_config();

    if (empty($cfg['clientid']) || empty($cfg['secretkey'])) {
        return ['success' => false, 'message' => 'Client ID dan Client Secret Satu Sehat belum dikonfigurasi.'];
    }

    // Cek cache token di session jika belum expired
    if (!$force_refresh && !empty($_SESSION['satusehat_token']) && !empty($_SESSION['satusehat_token_exp'])) {
        if (time() < ($_SESSION['satusehat_token_exp'] - 60)) {
            return [
                'success'      => true,
                'access_token' => $_SESSION['satusehat_token'],
                'cached'       => true,
                'expires_in'   => $_SESSION['satusehat_token_exp'] - time()
            ];
        }
    }

    $auth_endpoint = rtrim($cfg['authurl'], '/') . '/accesstoken?grant_type=client_credentials';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $auth_endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'client_id'     => $cfg['clientid'],
        'client_secret' => $cfg['secretkey']
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);

    $start_time = microtime(true);
    $response = curl_exec($ch);
    $latency  = round((microtime(true) - $start_time) * 1000);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err = curl_error($ch);
    curl_close($ch);

    if ($curl_err) {
        return [
            'success'   => false,
            'message'   => "Gagal menghubungi Auth Server Satu Sehat: $curl_err",
            'http_code' => $http_code,
            'latency'   => $latency
        ];
    }

    $data = json_decode($response, true);

    if ($http_code === 200 && !empty($data['access_token'])) {
        $_SESSION['satusehat_token']     = $data['access_token'];
        $_SESSION['satusehat_token_exp'] = time() + (int)($data['expires_in'] ?? 14400);

        return [
            'success'      => true,
            'access_token' => $data['access_token'],
            'expires_in'   => (int)($data['expires_in'] ?? 14400),
            'org_name'     => $data['organization_name'] ?? '',
            'developer'    => $data['developer.email'] ?? '',
            'latency'      => $latency,
            'raw'          => $data
        ];
    }

    return [
        'success'   => false,
        'message'   => $data['issue'][0]['details']['text'] ?? $data['message'] ?? 'Autentikasi Satu Sehat ditolak Kemenkes.',
        'http_code' => $http_code,
        'latency'   => $latency,
        'raw'       => $data
    ];
}

// ─── 3. Test Koneksi Satu Sehat Lengkap ───────────────────────
function test_satusehat_connection() {
    $token_res = get_satusehat_token(true);
    if (!$token_res['success']) {
        return $token_res;
    }

    $token = $token_res['access_token'];
    $cfg   = get_satusehat_config();
    $org_id = $cfg['organizationid'];

    if (empty($org_id)) {
        return [
            'success'   => false,
            'message'   => 'Token OAuth berhasil diperoleh, namun Organization ID belum diisi.',
            'auth_info' => $token_res
        ];
    }

    $fhir_endpoint = rtrim($cfg['fhirurl'], '/') . "/Organization/$org_id";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fhir_endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $token",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);

    $start_time = microtime(true);
    $response   = curl_exec($ch);
    $latency    = round((microtime(true) - $start_time) * 1000);
    $http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err   = curl_error($ch);
    curl_close($ch);

    if ($curl_err) {
        return [
            'success'   => false,
            'message'   => "Gagal menghubungi FHIR Server: $curl_err",
            'http_code' => $http_code,
            'latency'   => $latency
        ];
    }

    $data = json_decode($response, true);

    if ($http_code === 200 && !empty($data['resourceType']) && $data['resourceType'] === 'Organization') {
        return [
            'success'      => true,
            'message'      => 'Koneksi ke Gateway Satu Sehat Kemenkes RI Berhasil (Terverifikasi).',
            'http_code'    => $http_code,
            'latency'      => $latency,
            'org_name'     => $data['name'] ?? $token_res['org_name'] ?? 'Faskes Terdaftar',
            'org_id'       => $data['id'] ?? $org_id,
            'org_type'     => $data['type'][0]['coding'][0]['display'] ?? 'Klinik / Faskes',
            'address'      => ($data['address'][0]['line'][0] ?? '') . ', ' . ($data['address'][0]['city'] ?? '') . ', ' . ($data['address'][0]['state'] ?? ''),
            'contact'      => $data['contact'][0]['telecom'][1]['value'] ?? $data['contact'][0]['telecom'][0]['value'] ?? '-',
            'token_sample' => substr($token, 0, 12) . '...',
            'raw'          => $data
        ];
    }

    return [
        'success'   => false,
        'message'   => $data['issue'][0]['details']['text'] ?? "FHIR Server merespons HTTP $http_code",
        'http_code' => $http_code,
        'latency'   => $latency,
        'raw'       => $data
    ];
}

// ─── 4. Lookup IHS Pasien berdasarkan NIK ─────────────────────
function lookup_satusehat_patient_ihs($nik) {
    $nik = trim($nik);
    if (empty($nik) || strlen($nik) < 16) {
        return ['success' => false, 'message' => 'NIK tidak valid (minimal 16 digit).'];
    }

    $token_res = get_satusehat_token();
    if (!$token_res['success']) return $token_res;

    $cfg = get_satusehat_config();
    $url = rtrim($cfg['fhirurl'], '/') . "/Patient?identifier=https://fhir.kemkes.go.id/id/nik|$nik";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$token_res['access_token']}",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);

    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);
    if ($http_code === 200 && !empty($data['entry'][0]['resource']['id'])) {
        $patient = $data['entry'][0]['resource'];
        return [
            'success'    => true,
            'ihs_number' => $patient['id'],
            'name'       => $patient['name'][0]['text'] ?? '',
            'birth_date' => $patient['birthDate'] ?? '',
            'gender'     => $patient['gender'] ?? '',
            'raw'        => $patient
        ];
    }

    return [
        'success'   => false,
        'message'   => "Pasien dengan NIK $nik belum terdaftar di Satu Sehat Kemenkes (HTTP $http_code).",
        'raw'       => $data
    ];
}

// ─── 5. Lookup IHS Praktisi / Dokter ──────────────────────────
function lookup_satusehat_practitioner_ihs($nik) {
    $nik = trim($nik);
    if (empty($nik)) {
        return ['success' => false, 'message' => 'NIK Praktisi tidak boleh kosong.'];
    }

    $token_res = get_satusehat_token();
    if (!$token_res['success']) return $token_res;

    $cfg = get_satusehat_config();
    $url = rtrim($cfg['fhirurl'], '/') . "/Practitioner?identifier=https://fhir.kemkes.go.id/id/nik|$nik";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$token_res['access_token']}",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);

    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);
    if ($http_code === 200 && !empty($data['entry'][0]['resource']['id'])) {
        $prac = $data['entry'][0]['resource'];
        return [
            'success'         => true,
            'practitioner_id' => $prac['id'],
            'name'            => $prac['name'][0]['text'] ?? '',
            'raw'             => $prac
        ];
    }

    return [
        'success'   => false,
        'message'   => "Praktisi NIK $nik tidak ditemukan di Satu Sehat (HTTP $http_code).",
    ];
}

// ─── 5.b Mapping SNOMED-CT untuk AllergyIntolerance ──────────
function map_satusehat_allergy($allergy_text) {
    $txt = trim(strtolower($allergy_text));
    
    // Default fallback: Drug allergy
    $category = 'medication';
    $code     = '416098002';
    $display  = 'Drug allergy';

    if (preg_match('/(amox|amoxici|amoxicillin|amoksisilin|penisilin|penicillin|ampicillin|ampisilin)/i', $txt)) {
        $category = 'medication';
        $code     = '372687004';
        $display  = 'Amoxicillin';
    } elseif (preg_match('/(paracetamol|parasetamol|panadol|sanmol|acetaminophen)/i', $txt)) {
        $category = 'medication';
        $code     = '387517004';
        $display  = 'Paracetamol';
    } elseif (preg_match('/(mefenamat|mefenamic|ponstan|mefinal)/i', $txt)) {
        $category = 'medication';
        $code     = '387584000';
        $display  = 'Mefenamic acid';
    } elseif (preg_match('/(cef|cefa|cefixime|cefadroxil|sefadroksil|sefiksim|sefalosporin|cephalosporin)/i', $txt)) {
        $category = 'medication';
        $code     = '372665008';
        $display  = 'Cefadroxil';
    } elseif (preg_match('/(cipro|ciprofloxacin|siprofloksasin)/i', $txt)) {
        $category = 'medication';
        $code     = '387532008';
        $display  = 'Ciprofloxacin';
    } elseif (preg_match('/(sulfa|cotrim|kotrimoksazol|sulfamethoxazole)/i', $txt)) {
        $category = 'medication';
        $code     = '387406002';
        $display  = 'Sulfonamide';
    } elseif (preg_match('/(ibuprofen|proris)/i', $txt)) {
        $category = 'medication';
        $code     = '387207008';
        $display  = 'Ibuprofen';
    } elseif (preg_match('/(aspirin|asetosal)/i', $txt)) {
        $category = 'medication';
        $code     = '387458008';
        $display  = 'Aspirin';
    } elseif (preg_match('/(udang|kepiting|seafood|kerang|shellfish|makanan laut|cumi)/i', $txt)) {
        $category = 'food';
        $code     = '227038007';
        $display  = 'Shellfish';
    } elseif (preg_match('/(ikan|fish|tongkol|tenggiri)/i', $txt)) {
        $category = 'food';
        $code     = '227037002';
        $display  = 'Fish';
    } elseif (preg_match('/(telur|egg|putih telur|kuning telur)/i', $txt)) {
        $category = 'food';
        $code     = '91930004';
        $display  = 'Allergy to egg';
    } elseif (preg_match('/(kacang|peanut|nut|kedelai)/i', $txt)) {
        $category = 'food';
        $code     = '91935009';
        $display  = 'Allergy to peanut';
    } elseif (preg_match('/(susu|milk|laktosa|dairy)/i', $txt)) {
        $category = 'food';
        $code     = '226789007';
        $display  = 'Allergy to cow\'s milk';
    } elseif (preg_match('/(gluten|gandum|terigu|tepung)/i', $txt)) {
        $category = 'food';
        $code     = '89811004';
        $display  = 'Gluten';
    } elseif (preg_match('/(makan|food)/i', $txt)) {
        $category = 'food';
        $code     = '414285001';
        $display  = 'Food allergy';
    } elseif (preg_match('/(debu|dust|house dust)/i', $txt)) {
        $category = 'environment';
        $code     = '390952000';
        $display  = 'Allergy to house dust';
    } elseif (preg_match('/(dingin|cold|cuaca dingin)/i', $txt)) {
        $category = 'environment';
        $code     = '24079001';
        $display  = 'Cold urticaria';
    } elseif (preg_match('/(bulu|hewan|kucing|anjing|dander)/i', $txt)) {
        $category = 'environment';
        $code     = '232350006';
        $display  = 'Allergy to dander';
    } elseif (preg_match('/(lingkungan|cuaca|environment)/i', $txt)) {
        $category = 'environment';
        $code     = '426232007';
        $display  = 'Environmental allergy';
    }

    return [
        'category' => $category,
        'code'     => $code,
        'display'  => $display
    ];
}

// ─── 6. Build FHIR R4 Bundle Transaction ──────────────────────
function build_satusehat_bundle($no_rawat) {
    global $conn;
    $cfg = get_satusehat_config();
    $org_id = $cfg['organizationid'] ?: '100023305';

    $no_rawat_esc = $conn->real_escape_string($no_rawat);

    // Ambil Data Registrasi & Pasien
    $res_reg = $conn->query("
        SELECT r.*, p.nm_pasien, p.no_ktp, p.jk, p.tgl_lahir, p.alamat,
               d.nm_dokter, d.no_ijn_praktek,
               pol.nm_poli
        FROM reg_periksa r
        JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
        LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
        LEFT JOIN poliklinik pol ON r.kd_poli = pol.kd_poli
        WHERE r.no_rawat = '$no_rawat_esc'
        LIMIT 1
    ");

    if (!$res_reg || $res_reg->num_rows === 0) {
        return ['success' => false, 'message' => "Data kunjungan $no_rawat tidak ditemukan."];
    }

    $reg = $res_reg->fetch_assoc();

    // Ambil & Agregasi Semua Data SOAP / Pemeriksaan Rawat Jalan pada Kunjungan Ini
    // (Jika terdapat lebih dari 1 entri SOAP, ambil data yang terisi dari entri yang ada datanya)
    $res_soaps = $conn->query("
        SELECT * FROM pemeriksaan_ralan 
        WHERE no_rawat = '$no_rawat_esc' 
        ORDER BY tgl_perawatan DESC, jam_rawat DESC
    ");

    $soap_data = [
        'suhu_tubuh'    => '',
        'tensi'         => '',
        'nadi'          => '',
        'respirasi'     => '',
        'tinggi'        => '',
        'berat'         => '',
        'spo2'          => '',
        'gcs'           => '',
        'keluhan'       => '',
        'hasil_fisik'   => '',
        'alergi'        => '',
        'lingkar_perut' => ''
    ];

    if ($res_soaps && $res_soaps->num_rows > 0) {
        while ($s_row = $res_soaps->fetch_assoc()) {
            if (empty($soap_data['suhu_tubuh']) && !empty($s_row['suhu_tubuh']) && (float)$s_row['suhu_tubuh'] > 0) {
                $soap_data['suhu_tubuh'] = $s_row['suhu_tubuh'];
            }
            if (empty($soap_data['tensi']) && !empty($s_row['tensi']) && strpos($s_row['tensi'], '/') !== false && trim($s_row['tensi']) !== '0/0') {
                $soap_data['tensi'] = trim($s_row['tensi']);
            }
            if (empty($soap_data['nadi']) && !empty($s_row['nadi']) && (float)$s_row['nadi'] > 0) {
                $soap_data['nadi'] = $s_row['nadi'];
            }
            if (empty($soap_data['respirasi']) && !empty($s_row['respirasi']) && (float)$s_row['respirasi'] > 0) {
                $soap_data['respirasi'] = $s_row['respirasi'];
            }
            if (empty($soap_data['tinggi']) && !empty($s_row['tinggi']) && (float)$s_row['tinggi'] > 0) {
                $soap_data['tinggi'] = $s_row['tinggi'];
            }
            if (empty($soap_data['berat']) && !empty($s_row['berat']) && (float)$s_row['berat'] > 0) {
                $soap_data['berat'] = $s_row['berat'];
            }
            if (empty($soap_data['spo2']) && !empty($s_row['spo2']) && (float)$s_row['spo2'] > 0) {
                $soap_data['spo2'] = $s_row['spo2'];
            }
            if (empty($soap_data['gcs']) && !empty(trim($s_row['gcs'] ?? '')) && trim($s_row['gcs']) !== '-') {
                $soap_data['gcs'] = trim($s_row['gcs']);
            }
            if (empty($soap_data['keluhan']) && !empty(trim($s_row['keluhan'] ?? '')) && trim($s_row['keluhan']) !== '-') {
                $soap_data['keluhan'] = trim($s_row['keluhan']);
            }
            if (empty($soap_data['hasil_fisik']) && !empty(trim($s_row['pemeriksaan'] ?? '')) && trim($s_row['pemeriksaan']) !== '-') {
                $soap_data['hasil_fisik'] = trim($s_row['pemeriksaan']);
            }
            if (empty($soap_data['lingkar_perut']) && !empty($s_row['lingkar_perut']) && (float)$s_row['lingkar_perut'] > 0) {
                $soap_data['lingkar_perut'] = $s_row['lingkar_perut'];
            }
            if (empty($soap_data['alergi'])) {
                $alg = trim($s_row['alergi'] ?? '');
                if (!empty($alg) && $alg !== '-' && !in_array(strtolower($alg), ['tidak ada', 'tidak ada alergi', 'tidak', 't', 'non', 'none'])) {
                    $soap_data['alergi'] = $alg;
                }
            }
        }
    }

    // 1. Identitas Pasien (Lookup IHS via NIK)
    $patient_nik = trim($reg['no_ktp'] ?? '');
    if (empty($patient_nik) || strlen($patient_nik) < 16) {
        return ['success' => false, 'message' => "NIK Pasien ({$reg['nm_pasien']}) tidak valid / kurang dari 16 digit. Lengkapi NIK pada data Pasien terlebih dahulu."];
    }

    $pat_lookup = lookup_satusehat_patient_ihs($patient_nik);
    if (!$pat_lookup['success'] || empty($pat_lookup['ihs_number'])) {
        return ['success' => false, 'message' => "Pasien {$reg['nm_pasien']} (NIK: $patient_nik) belum terdaftar di Satu Sehat Kemenkes: " . ($pat_lookup['message'] ?? 'IHS tidak ditemukan.')];
    }
    $patient_ihs = $pat_lookup['ihs_number'];
    $patient_ref = "Patient/$patient_ihs";

    // 2. Mapping Praktisi Dokter (Lookup IHS via mapping atau NIK Pegawai)
    $kd_dokter_esc = $conn->real_escape_string($reg['kd_dokter']);
    $prac_mapping = $conn->query("SELECT practitioner_id FROM mlite_satu_sehat_mapping_praktisi WHERE kd_dokter = '$kd_dokter_esc' LIMIT 1");
    $practitioner_id = ($prac_mapping && $prow = $prac_mapping->fetch_assoc()) ? $prow['practitioner_id'] : '';

    if (empty($practitioner_id)) {
        // Coba cari NIK dokter di tabel pegawai
        $peg_res = $conn->query("SELECT no_ktp FROM pegawai WHERE nik = '$kd_dokter_esc' OR nama = '{$conn->real_escape_string($reg['nm_dokter'])}' LIMIT 1");
        if ($peg_res && $peg_row = $peg_res->fetch_assoc()) {
            $doc_ktp = trim($peg_row['no_ktp'] ?? '');
            if (!empty($doc_ktp) && strlen($doc_ktp) >= 16) {
                $doc_lookup = lookup_satusehat_practitioner_ihs($doc_ktp);
                if ($doc_lookup['success'] && !empty($doc_lookup['practitioner_id'])) {
                    $practitioner_id = $doc_lookup['practitioner_id'];
                    $conn->query("INSERT INTO mlite_satu_sehat_mapping_praktisi (practitioner_id, kd_dokter) VALUES ('$practitioner_id', '$kd_dokter_esc') ON DUPLICATE KEY UPDATE kd_dokter = '$kd_dokter_esc'");
                }
            }
        }
    }

    if (empty($practitioner_id)) {
        // Fallback default praktisi
        $practitioner_id = '10032174406'; // dr. Laelatun nafillah
    }

    // 3. Mapping Lokasi Poli
    $kd_poli_esc = $conn->real_escape_string(substr($reg['kd_poli'], 0, 5));
    $loc_mapping = $conn->query("SELECT id_lokasi_satusehat FROM mlite_satu_sehat_lokasi WHERE kode = '$kd_poli_esc' LIMIT 1");
    $location_id = ($loc_mapping && $lrow = $loc_mapping->fetch_assoc()) ? $lrow['id_lokasi_satusehat'] : '';
    if (empty($location_id)) {
        $location_id = 'ba5939fb-abce-4dc3-975d-9d347810412e'; // Ruang Pemeriksaan Rawat Jalan PKU Muhammadiyah
    }

    // UUID Urn Reference untuk Encounter
    $uuid_encounter = "urn:uuid:" . guidv4();

    $tgl_reg = $reg['tgl_registrasi'] ?: date('Y-m-d');
    $jam_reg = $reg['jam_reg'] ?: '08:00:00';
    $start_iso = date('c', strtotime("$tgl_reg $jam_reg"));
    $end_iso   = date('c', strtotime("$tgl_reg $jam_reg +30 minutes"));

    $entries = [];

    // ── 4. Persiapan Condition & Encounter.diagnosis (Rule 10457 Kemenkes) ──
    $res_diag = $conn->query("
        SELECT dp.*, pen.nm_penyakit 
        FROM diagnosa_pasien dp
        LEFT JOIN penyakit pen ON dp.kd_penyakit = pen.kd_penyakit
        WHERE dp.no_rawat = '$no_rawat_esc'
          AND dp.kd_penyakit IS NOT NULL
          AND TRIM(dp.kd_penyakit) != ''
          AND TRIM(dp.kd_penyakit) != '-'
        ORDER BY dp.prioritas ASC
    ");

    $diagnosis_encounter = [];
    $condition_entries   = [];

    if ($res_diag && $res_diag->num_rows > 0) {
        $rank_idx = 1;
        while ($drow = $res_diag->fetch_assoc()) {
            $code_icd = trim($drow['kd_penyakit'] ?? '');
            if (empty($code_icd) || $code_icd === '-' || $code_icd === '.') {
                continue;
            }

            $uuid_cond = "urn:uuid:" . guidv4();
            $diag_name = !empty(trim($drow['nm_penyakit'] ?? '')) ? trim($drow['nm_penyakit']) : $code_icd;
            $is_primary = ($rank_idx === 1 || (int)$drow['prioritas'] === 1);

            // Item untuk Encounter.diagnosis
            $diagnosis_encounter[] = [
                "condition" => [
                    "reference" => $uuid_cond,
                    "display"   => $diag_name
                ],
                "use" => [
                    "coding" => [
                        [
                            "system"  => "http://terminology.hl7.org/CodeSystem/diagnosis-role",
                            "code"    => ($is_primary ? "DD" : "AD"),
                            "display" => ($is_primary ? "Discharge diagnosis" : "Admission diagnosis")
                        ]
                    ]
                ],
                "rank" => $rank_idx
            ];

            // Resource Condition FHIR R4
            $cond_resource = [
                "resourceType" => "Condition",
                "clinicalStatus" => [
                    "coding" => [
                        [
                            "system" => "http://terminology.hl7.org/CodeSystem/condition-clinical",
                            "code"   => "active"
                        ]
                    ]
                ],
                "verificationStatus" => [
                    "coding" => [
                        [
                            "system" => "http://terminology.hl7.org/CodeSystem/condition-ver-status",
                            "code"   => "confirmed"
                        ]
                    ]
                ],
                "category" => [
                    [
                        "coding" => [
                            [
                               "system"  => "http://terminology.hl7.org/CodeSystem/condition-category",
                                "code"    => "encounter-diagnosis",
                                "display" => "Encounter Diagnosis"
                            ]
                        ]
                    ]
                ],
                "code" => [
                    "coding" => [
                        [
                            "system"  => "http://hl7.org/fhir/sid/icd-10",
                            "code"    => $code_icd,
                            "display" => $diag_name
                        ]
                    ]
                ],
                "subject" => [
                    "reference" => $patient_ref,
                    "display"   => $reg['nm_pasien']
                ],
                "encounter" => [
                    "reference" => $uuid_encounter,
                    "display"   => "Kunjungan Rawat Jalan $no_rawat"
                ],
                "recordedDate" => $start_iso
            ];

            $condition_entries[] = [
                "fullUrl"  => $uuid_cond,
                "resource" => $cond_resource,
                "request"  => ["method" => "POST", "url" => "Condition"]
            ];

            $rank_idx++;
        }
    }

    if (empty($condition_entries)) {
        // Fallback Diagnosa default (Pemeriksaan Medis Umum - Z00.0) agar Encounter.diagnosis tidak kosong
        $uuid_cond = "urn:uuid:" . guidv4();
        $diagnosis_encounter[] = [
            "condition" => [
                "reference" => $uuid_cond,
                "display"   => "General medical examination"
            ],
            "use" => [
                "coding" => [
                    [
                        "system"  => "http://terminology.hl7.org/CodeSystem/diagnosis-role",
                        "code"    => "DD",
                        "display" => "Discharge diagnosis"
                    ]
                ]
            ],
            "rank" => 1
        ];

        $condition_entries[] = [
            "fullUrl"  => $uuid_cond,
            "resource" => [
                "resourceType" => "Condition",
                "clinicalStatus" => ["coding" => [["system" => "http://terminology.hl7.org/CodeSystem/condition-clinical", "code" => "active"]]],
                "verificationStatus" => ["coding" => [["system" => "http://terminology.hl7.org/CodeSystem/condition-ver-status", "code" => "confirmed"]]],
                "category" => [["coding" => [["system" => "http://terminology.hl7.org/CodeSystem/condition-category", "code" => "encounter-diagnosis", "display" => "Encounter Diagnosis"]]]],
                "code" => ["coding" => [["system" => "http://hl7.org/fhir/sid/icd-10", "code" => "Z00.0", "display" => "General medical examination"]]],
                "subject" => ["reference" => $patient_ref, "display" => $reg['nm_pasien']],
                "encounter" => ["reference" => $uuid_encounter, "display" => "Kunjungan Rawat Jalan $no_rawat"],
                "recordedDate" => $start_iso
            ],
            "request" => ["method" => "POST", "url" => "Condition"]
        ];
    }

    // ── 5. Resource Encounter (Rawat Jalan Selesai) ───────────
    $encounter_resource = [
        "resourceType" => "Encounter",
        "identifier" => [
            [
                "system" => "http://sys-ids.kemkes.go.id/encounter/$org_id",
                "value"  => $no_rawat
            ]
        ],
        "status" => "finished",
        "class" => [
            "system"  => "http://terminology.hl7.org/CodeSystem/v3-ActCode",
            "code"    => "AMB",
            "display" => "ambulatory"
        ],
        "subject" => [
            "reference" => $patient_ref,
            "display"   => $reg['nm_pasien']
        ],
        "participant" => [
            [
                "type" => [
                    [
                        "coding" => [
                            [
                                "system"  => "http://terminology.hl7.org/CodeSystem/v3-ParticipationType",
                                "code"    => "ATND",
                                "display" => "attender"
                            ]
                        ]
                    ]
                ],
                "individual" => [
                    "reference" => "Practitioner/$practitioner_id",
                    "display"   => $reg['nm_dokter']
                ]
            ]
        ],
        "period" => [
            "start" => $start_iso,
            "end"   => $end_iso
        ],
        "location" => [
            [
                "location" => [
                    "reference" => "Location/$location_id",
                    "display"   => $reg['nm_poli']
                ]
            ]
        ],
        "diagnosis" => $diagnosis_encounter,
        "statusHistory" => [
            [
                "status" => "arrived",
                "period" => ["start" => $start_iso, "end" => $start_iso]
            ],
            [
                "status" => "in-progress",
                "period" => ["start" => $start_iso, "end" => $end_iso]
            ],
            [
                "status" => "finished",
                "period" => ["start" => $end_iso, "end" => $end_iso]
            ]
        ],
        "serviceProvider" => [
            "reference" => "Organization/$org_id"
        ]
    ];

    // Cek apakah Encounter sudah terdaftar di Satu Sehat (agar menggunakan PUT update, bukan duplicate POST)
    $existing_encounter_id = '';
    $res_ss = $conn->query("SELECT id_encounter FROM mlite_satu_sehat_response WHERE no_rawat = '$no_rawat_esc' AND id_encounter IS NOT NULL AND id_encounter != '' LIMIT 1");
    if ($res_ss && $ss_row = $res_ss->fetch_assoc()) {
        $existing_encounter_id = $ss_row['id_encounter'];
    }

    if (empty($existing_encounter_id)) {
        $token_chk = get_satusehat_token();
        if ($token_chk['success']) {
            $chk_url = rtrim($cfg['fhirurl'], '/') . "/Encounter?identifier=http://sys-ids.kemkes.go.id/encounter/{$org_id}|$no_rawat";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $chk_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$token_chk['access_token']}", "Content-Type: application/json"]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $chk_res = curl_exec($ch);
            curl_close($ch);
            if ($chk_res) {
                $chk_data = json_decode($chk_res, true);
                if (!empty($chk_data['entry'][0]['resource']['id'])) {
                    $existing_encounter_id = $chk_data['entry'][0]['resource']['id'];
                }
            }
        }
    }

    if (!empty($existing_encounter_id)) {
        $encounter_resource['id'] = $existing_encounter_id;
        $entries[] = [
            "fullUrl"  => $uuid_encounter,
            "resource" => $encounter_resource,
            "request"  => [
                "method" => "PUT",
                "url"    => "Encounter/$existing_encounter_id"
            ]
        ];
    } else {
        $entries[] = [
            "fullUrl"  => $uuid_encounter,
            "resource" => $encounter_resource,
            "request"  => [
                "method" => "POST",
                "url"    => "Encounter"
            ]
        ];
    }

    // Gabungkan entri Condition
    foreach ($condition_entries as $ce) {
        $entries[] = $ce;
    }

    // ── 3. Observation - Vital Signs (Agregasi Data dari Semua Entri SOAP Kunjungan Ini) ──
    $performer_ref = [
        [
            "reference" => "Practitioner/$practitioner_id",
            "display"   => $reg['nm_dokter']
        ]
    ];

    // a. Suhu Tubuh (LOINC 8310-5)
    if (!empty($soap_data['suhu_tubuh']) && (float)$soap_data['suhu_tubuh'] > 0) {
        $entries[] = [
            "fullUrl"  => "urn:uuid:" . guidv4(),
            "resource" => [
                "resourceType" => "Observation",
                "status" => "final",
                "category" => [["coding" => [["system" => "http://terminology.hl7.org/CodeSystem/observation-category", "code" => "vital-signs", "display" => "Vital Signs"]]]],
                "code" => ["coding" => [["system" => "http://loinc.org", "code" => "8310-5", "display" => "Body temperature"]]],
                "subject" => ["reference" => $patient_ref],
                "performer" => $performer_ref,
                "encounter" => ["reference" => $uuid_encounter],
                "effectiveDateTime" => $start_iso,
                "valueQuantity" => ["value" => (float)$soap_data['suhu_tubuh'], "unit" => "C", "system" => "http://unitsofmeasure.org", "code" => "Cel"]
            ],
            "request" => ["method" => "POST", "url" => "Observation"]
        ];
    }

    // b. Tekanan Darah (Sistole & Diastole - LOINC 85354-9)
    if (!empty($soap_data['tensi']) && strpos($soap_data['tensi'], '/') !== false) {
        list($sys, $dia) = explode('/', $soap_data['tensi']);
        $entries[] = [
            "fullUrl"  => "urn:uuid:" . guidv4(),
            "resource" => [
                "resourceType" => "Observation",
                "status" => "final",
                "category" => [["coding" => [["system" => "http://terminology.hl7.org/CodeSystem/observation-category", "code" => "vital-signs"]]]],
                "code" => ["coding" => [["system" => "http://loinc.org", "code" => "85354-9", "display" => "Blood pressure panel"]]],
                "subject" => ["reference" => $patient_ref],
                "performer" => $performer_ref,
                "encounter" => ["reference" => $uuid_encounter],
                "effectiveDateTime" => $start_iso,
                "component" => [
                    [
                        "code" => ["coding" => [["system" => "http://loinc.org", "code" => "8480-6", "display" => "Systolic blood pressure"]]],
                        "valueQuantity" => ["value" => (float)$sys, "unit" => "mm[Hg]", "system" => "http://unitsofmeasure.org", "code" => "mm[Hg]"]
                    ],
                    [
                        "code" => ["coding" => [["system" => "http://loinc.org", "code" => "8462-4", "display" => "Diastolic blood pressure"]]],
                        "valueQuantity" => ["value" => (float)$dia, "unit" => "mm[Hg]", "system" => "http://unitsofmeasure.org", "code" => "mm[Hg]"]
                    ]
                ]
            ],
            "request" => ["method" => "POST", "url" => "Observation"]
        ];
    }

    // c. Denyut Nadi (Heart Rate - LOINC 8867-4)
    if (!empty($soap_data['nadi']) && (float)$soap_data['nadi'] > 0) {
        $entries[] = [
            "fullUrl"  => "urn:uuid:" . guidv4(),
            "resource" => [
                "resourceType" => "Observation",
                "status" => "final",
                "category" => [["coding" => [["system" => "http://terminology.hl7.org/CodeSystem/observation-category", "code" => "vital-signs"]]]],
                "code" => ["coding" => [["system" => "http://loinc.org", "code" => "8867-4", "display" => "Heart rate"]]],
                "subject" => ["reference" => $patient_ref],
                "performer" => $performer_ref,
                "encounter" => ["reference" => $uuid_encounter],
                "effectiveDateTime" => $start_iso,
                "valueQuantity" => ["value" => (float)$soap_data['nadi'], "unit" => "/min", "system" => "http://unitsofmeasure.org", "code" => "/min"]
            ],
            "request" => ["method" => "POST", "url" => "Observation"]
        ];
    }

    // d. Laju Pernapasan (Respiration Rate - LOINC 9279-1)
    if (!empty($soap_data['respirasi']) && (float)$soap_data['respirasi'] > 0) {
        $entries[] = [
            "fullUrl"  => "urn:uuid:" . guidv4(),
            "resource" => [
                "resourceType" => "Observation",
                "status" => "final",
                "category" => [["coding" => [["system" => "http://terminology.hl7.org/CodeSystem/observation-category", "code" => "vital-signs"]]]],
                "code" => ["coding" => [["system" => "http://loinc.org", "code" => "9279-1", "display" => "Respiratory rate"]]],
                "subject" => ["reference" => $patient_ref],
                "performer" => $performer_ref,
                "encounter" => ["reference" => $uuid_encounter],
                "effectiveDateTime" => $start_iso,
                "valueQuantity" => ["value" => (float)$soap_data['respirasi'], "unit" => "/min", "system" => "http://unitsofmeasure.org", "code" => "/min"]
            ],
            "request" => ["method" => "POST", "url" => "Observation"]
        ];
    }

    // e. SpO2 (Saturasi Oksigen - LOINC 2708-6)
    if (!empty($soap_data['spo2']) && (float)$soap_data['spo2'] > 0) {
        $entries[] = [
            "fullUrl"  => "urn:uuid:" . guidv4(),
            "resource" => [
                "resourceType" => "Observation",
                "status" => "final",
                "category" => [["coding" => [["system" => "http://terminology.hl7.org/CodeSystem/observation-category", "code" => "vital-signs"]]]],
                "code" => ["coding" => [["system" => "http://loinc.org", "code" => "2708-6", "display" => "Oxygen saturation in Arterial blood"]]],
                "subject" => ["reference" => $patient_ref],
                "performer" => $performer_ref,
                "encounter" => ["reference" => $uuid_encounter],
                "effectiveDateTime" => $start_iso,
                "valueQuantity" => ["value" => (float)$soap_data['spo2'], "unit" => "%", "system" => "http://unitsofmeasure.org", "code" => "%"]
            ],
            "request" => ["method" => "POST", "url" => "Observation"]
        ];
    }

    // f. Tinggi Badan (LOINC 8302-2)
    if (!empty($soap_data['tinggi']) && (float)$soap_data['tinggi'] > 0) {
        $entries[] = [
            "fullUrl"  => "urn:uuid:" . guidv4(),
            "resource" => [
                "resourceType" => "Observation",
                "status" => "final",
                "category" => [["coding" => [["system" => "http://terminology.hl7.org/CodeSystem/observation-category", "code" => "vital-signs"]]]],
                "code" => ["coding" => [["system" => "http://loinc.org", "code" => "8302-2", "display" => "Body height"]]],
                "subject" => ["reference" => $patient_ref],
                "performer" => $performer_ref,
                "encounter" => ["reference" => $uuid_encounter],
                "effectiveDateTime" => $start_iso,
                "valueQuantity" => ["value" => (float)$soap_data['tinggi'], "unit" => "cm", "system" => "http://unitsofmeasure.org", "code" => "cm"]
            ],
            "request" => ["method" => "POST", "url" => "Observation"]
        ];
    }

    // g. Berat Badan (LOINC 29463-7)
    if (!empty($soap_data['berat']) && (float)$soap_data['berat'] > 0) {
        $entries[] = [
            "fullUrl"  => "urn:uuid:" . guidv4(),
            "resource" => [
                "resourceType" => "Observation",
                "status" => "final",
                "category" => [["coding" => [["system" => "http://terminology.hl7.org/CodeSystem/observation-category", "code" => "vital-signs"]]]],
                "code" => ["coding" => [["system" => "http://loinc.org", "code" => "29463-7", "display" => "Body weight"]]],
                "subject" => ["reference" => $patient_ref],
                "performer" => $performer_ref,
                "encounter" => ["reference" => $uuid_encounter],
                "effectiveDateTime" => $start_iso,
                "valueQuantity" => ["value" => (float)$soap_data['berat'], "unit" => "kg", "system" => "http://unitsofmeasure.org", "code" => "kg"]
            ],
            "request" => ["method" => "POST", "url" => "Observation"]
        ];
    }

    // h. Lingkar Perut (Waist Circumference - LOINC 8280-0)
    if (!empty($soap_data['lingkar_perut']) && (float)$soap_data['lingkar_perut'] > 0) {
        $entries[] = [
            "fullUrl"  => "urn:uuid:" . guidv4(),
            "resource" => [
                "resourceType" => "Observation",
                "status" => "final",
                "category" => [["coding" => [["system" => "http://terminology.hl7.org/CodeSystem/observation-category", "code" => "vital-signs"]]]],
                "code" => ["coding" => [["system" => "http://loinc.org", "code" => "8280-0", "display" => "Waist Circumference at umbilicus by Tape measure"]]],
                "subject" => ["reference" => $patient_ref],
                "performer" => $performer_ref,
                "encounter" => ["reference" => $uuid_encounter],
                "effectiveDateTime" => $start_iso,
                "valueQuantity" => ["value" => (float)$soap_data['lingkar_perut'], "unit" => "cm", "system" => "http://unitsofmeasure.org", "code" => "cm"]
            ],
            "request" => ["method" => "POST", "url" => "Observation"]
        ];
    }

    // ── 3.b AllergyIntolerance (Riwayat Alergi Pasien - Kemenkes FHIR R4) ──
    if (!empty($soap_data['alergi'])) {
        $uuid_allergy = "urn:uuid:" . guidv4();
        $alg_info = map_satusehat_allergy($soap_data['alergi']);

        $entries[] = [
            "fullUrl"  => $uuid_allergy,
            "resource" => [
                "resourceType" => "AllergyIntolerance",
                "identifier" => [
                    [
                        "system" => "http://sys-ids.kemkes.go.id/allergy/$org_id",
                        "use"    => "official",
                        "value"  => $no_rawat
                    ]
                ],
                "clinicalStatus" => [
                    "coding" => [
                        [
                            "system"  => "http://terminology.hl7.org/CodeSystem/allergyintolerance-clinical",
                            "code"    => "active",
                            "display" => "Active"
                        ]
                    ]
                ],
                "verificationStatus" => [
                    "coding" => [
                        [
                            "system"  => "http://terminology.hl7.org/CodeSystem/allergyintolerance-verification",
                            "code"    => "confirmed",
                            "display" => "Confirmed"
                        ]
                    ]
                ],
                "category" => [
                    $alg_info['category']
                ],
                "code" => [
                    "coding" => [
                        [
                            "system"  => "http://snomed.info/sct",
                            "code"    => $alg_info['code'],
                            "display" => $alg_info['display']
                        ]
                    ],
                    "text" => $soap_data['alergi']
                ],
                "patient" => [
                    "reference" => $patient_ref,
                    "display"   => $reg['nm_pasien']
                ],
                "encounter" => [
                    "reference" => $uuid_encounter,
                    "display"   => "Kunjungan Rawat Jalan $no_rawat"
                ],
                "recordedDate" => $start_iso,
                "recorder" => [
                    "reference" => "Practitioner/$practitioner_id",
                    "display"   => $reg['nm_dokter']
                ]
            ],
            "request" => ["method" => "POST", "url" => "AllergyIntolerance"]
        ];
    }

    // ── 4. DiagnosticReport & Observation Laboratorium ────────
    $res_lab = $conn->query("
        SELECT pl.noorder, pl.tgl_hasil, pl.jam_hasil,
               dp.kd_jenis_prw, j.nm_perawatan,
               m.code as loinc_code, m.display as loinc_display,
               m.sampel_code, m.sampel_display,
               (SELECT dhl.nilai FROM detail_periksa_lab dhl WHERE dhl.no_rawat = '$no_rawat_esc' AND dhl.kd_jenis_prw = dp.kd_jenis_prw LIMIT 1) as hasil_lab,
               (SELECT dhl.nilai_rujukan FROM detail_periksa_lab dhl WHERE dhl.no_rawat = '$no_rawat_esc' AND dhl.kd_jenis_prw = dp.kd_jenis_prw LIMIT 1) as rujukan_lab
        FROM permintaan_lab pl
        JOIN permintaan_pemeriksaan_lab dp ON pl.noorder = dp.noorder
        JOIN jns_perawatan_lab j ON dp.kd_jenis_prw = j.kd_jenis_prw
        LEFT JOIN mlite_satu_sehat_mapping_lab m ON dp.kd_jenis_prw = m.kd_jenis_prw
        WHERE pl.no_rawat = '$no_rawat_esc'
    ");

    if ($res_lab && $res_lab->num_rows > 0) {
        $lab_obs_uuids = [];
        while ($lrow = $res_lab->fetch_assoc()) {
            $loinc_c = $lrow['loinc_code'] ?: '58410-2';
            $loinc_d = $lrow['loinc_display'] ?: $lrow['nm_perawatan'];
            $sampel_c = $lrow['sampel_code'] ?: '119297000';
            $sampel_d = $lrow['sampel_display'] ?: 'Blood specimen';

            $uuid_lab_obs = "urn:uuid:" . guidv4();
            $lab_obs_uuids[] = ["reference" => $uuid_lab_obs, "display" => $loinc_d];

            $entries[] = [
                "fullUrl"  => $uuid_lab_obs,
                "resource" => [
                    "resourceType" => "Observation",
                    "status" => "final",
                    "category" => [["coding" => [["system" => "http://terminology.hl7.org/CodeSystem/observation-category", "code" => "laboratory", "display" => "Laboratory"]]]],
                    "code" => ["coding" => [["system" => "http://loinc.org", "code" => $loinc_c, "display" => $loinc_d]]],
                    "subject" => ["reference" => $patient_ref],
                    "performer" => $performer_ref,
                    "encounter" => ["reference" => $uuid_encounter],
                    "effectiveDateTime" => $start_iso,
                    "valueString" => $lrow['hasil_lab'] ?: 'Normal',
                    "specimen" => [
                        "display" => "$sampel_d ($sampel_c)"
                    ]
                ],
                "request" => ["method" => "POST", "url" => "Observation"]
            ];
        }

        // DiagnosticReport Lab
        $entries[] = [
            "fullUrl"  => "urn:uuid:" . guidv4(),
            "resource" => [
                "resourceType" => "DiagnosticReport",
                "status" => "final",
                "category" => [["coding" => [["system" => "http://terminology.hl7.org/CodeSystem/v2-0074", "code" => "LAB", "display" => "Laboratory"]]]],
                "code" => ["coding" => [["system" => "http://loinc.org", "code" => "58410-2", "display" => "Laboratory Diagnostic Report"]]],
                "subject" => ["reference" => $patient_ref],
                "performer" => $performer_ref,
                "encounter" => ["reference" => $uuid_encounter],
                "effectiveDateTime" => $start_iso,
                "result" => $lab_obs_uuids
            ],
            "request" => ["method" => "POST", "url" => "DiagnosticReport"]
        ];
    }

    // ── 5. Medication & MedicationRequest (Resep Obat) ───────
    $res_obat = $conn->query("
        SELECT ro.no_resep, dro.kode_brng, db.nama_brng, dro.jml, dro.aturan_pakai,
               m.kode_kfa, m.nama_kfa, m.kode_sediaan, m.nama_sediaan, m.kode_route, m.nama_route
        FROM resep_obat ro
        JOIN resep_dokter dro ON ro.no_resep = dro.no_resep
        JOIN databarang db ON dro.kode_brng = db.kode_brng
        LEFT JOIN mlite_satu_sehat_mapping_obat m ON dro.kode_brng = m.kode_brng
        WHERE ro.no_rawat = '$no_rawat_esc'
        LIMIT 10
    ");

    if ($res_obat && $res_obat->num_rows > 0) {
        while ($orow = $res_obat->fetch_assoc()) {
            $kfa_code = !empty($orow['kode_kfa']) ? $orow['kode_kfa'] : '93000200';
            $kfa_name = $orow['nama_kfa'] ?: $orow['nama_brng'];
            $uuid_med = "urn:uuid:" . guidv4();
            $uuid_med_req = "urn:uuid:" . guidv4();

            // a. Resource Medication
            $entries[] = [
                "fullUrl"  => $uuid_med,
                "resource" => [
                    "resourceType" => "Medication",
                    "identifier" => [
                        [
                            "system" => "http://sys-ids.kemkes.go.id/medication/$org_id",
                            "use"    => "official",
                            "value"  => "{$orow['no_resep']}/{$orow['kode_brng']}"
                        ]
                    ],
                    "code" => [
                        "coding" => [
                            [
                                "system"  => "http://sys-ids.kemkes.go.id/kfa",
                                "code"    => $kfa_code,
                                "display" => $kfa_name
                            ]
                        ]
                    ],
                    "status" => "active",
                    "extension" => [
                        [
                            "url" => "https://fhir.kemkes.go.id/r4/StructureDefinition/MedicationType",
                            "valueCodeableConcept" => [
                                "coding" => [
                                    [
                                        "system"  => "http://terminology.kemkes.go.id/CodeSystem/medication-type",
                                        "code"    => "NC",
                                        "display" => "Non-compound"
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                "request" => ["method" => "POST", "url" => "Medication"]
            ];

            // b. Resource MedicationRequest (Rule 10135: Wajib medicationReference)
            $entries[] = [
                "fullUrl"  => $uuid_med_req,
                "resource" => [
                    "resourceType" => "MedicationRequest",
                    "identifier" => [
                        [
                            "system" => "http://sys-ids.kemkes.go.id/prescription/$org_id",
                            "use"    => "official",
                            "value"  => "{$orow['no_resep']}/{$orow['kode_brng']}"
                        ]
                    ],
                    "status" => "completed",
                    "intent" => "order",
                    "category" => [
                        [
                            "coding" => [
                                [
                                    "system"  => "http://terminology.hl7.org/CodeSystem/medicationrequest-category",
                                    "code"    => "outpatient",
                                    "display" => "Outpatient"
                                ]
                            ]
                        ]
                    ],
                    "medicationReference" => [
                        "reference" => $uuid_med,
                        "display"   => $kfa_name
                    ],
                    "subject" => [
                        "reference" => $patient_ref,
                        "display"   => $reg['nm_pasien']
                    ],
                    "encounter" => [
                        "reference" => $uuid_encounter,
                        "display"   => "Kunjungan Rawat Jalan $no_rawat"
                    ],
                    "authoredOn" => $start_iso,
                    "requester" => [
                        "reference" => "Practitioner/$practitioner_id",
                        "display"   => $reg['nm_dokter']
                    ],
                    "dosageInstruction" => [
                        [
                            "text"               => $orow['aturan_pakai'] ?: '3x1 tablet sesudah makan',
                            "patientInstruction" => $orow['aturan_pakai'] ?: '3x1 tablet sesudah makan'
                        ]
                    ],
                    "dispenseRequest" => [
                        "quantity" => [
                            "value"  => (float)$orow['jml'],
                            "unit"   => "TAB",
                            "system" => "http://terminology.hl7.org/CodeSystem/v3-orderableDrugForm",
                            "code"   => "TAB"
                        ],
                        "performer" => [
                            "reference" => "Organization/$org_id"
                        ]
                    ]
                ],
                "request" => ["method" => "POST", "url" => "MedicationRequest"]
            ];
        }
    }

    // Susun Struktur Utama Bundle Transaction
    $bundle = [
        "resourceType" => "Bundle",
        "type"         => "transaction",
        "entry"        => $entries
    ];

    return [
        'success'      => true,
        'no_rawat'     => $no_rawat,
        'patient_name' => $reg['nm_pasien'],
        'nik'          => $reg['no_ktp'],
        'entry_count'  => count($entries),
        'bundle'       => $bundle
    ];
}

// ─── 7. Kirim Bundle Transaction ke Satu Sehat Kemenkes ───────
function send_satusehat_bundle($no_rawat) {
    global $conn;

    // 1. Build Bundle
    $bundle_res = build_satusehat_bundle($no_rawat);
    if (!$bundle_res['success']) {
        return $bundle_res;
    }

    $bundle_payload = json_encode($bundle_res['bundle'], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

    // 2. Dapatkan Token OAuth
    $token_res = get_satusehat_token();
    if (!$token_res['success']) {
        return [
            'success'        => false,
            'message'        => 'Gagal autentikasi OAuth Satu Sehat: ' . $token_res['message'],
            'bundle_payload' => $bundle_payload
        ];
    }

    $cfg = get_satusehat_config();
    $fhir_url = rtrim($cfg['fhirurl'], '/');

    // 3. POST Bundle Transaction
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fhir_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $bundle_payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$token_res['access_token']}",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $start_time = microtime(true);
    $response   = curl_exec($ch);
    $latency    = round((microtime(true) - $start_time) * 1000);
    $http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err   = curl_error($ch);
    curl_close($ch);

    if ($curl_err) {
        return [
            'success'        => false,
            'message'        => "Koneksi ke FHIR Server terputus: $curl_err",
            'http_code'      => $http_code,
            'latency'        => $latency,
            'bundle_payload' => $bundle_payload
        ];
    }

    $resp_data = json_decode($response, true);
    $no_rawat_esc = $conn->real_escape_string($no_rawat);

    // Ekstraksi Resource IDs dari Bundle Response
    $encounter_id = '';
    $condition_id = '';
    $obs_ids      = [];

    if ($http_code === 200 && !empty($resp_data['entry'])) {
        foreach ($resp_data['entry'] as $e) {
            $resp_e   = $e['response'] ?? [];
            $res_type = $resp_e['resourceType'] ?? '';
            $res_id   = $resp_e['resourceID'] ?? '';
            $loc      = $resp_e['location'] ?? '';

            if (empty($res_id) && preg_match('#/([a-zA-Z0-9\-]+)(?:/_history|$)#', $loc, $m)) {
                $res_id = $m[1];
            }

            if (($res_type === 'Encounter' || strpos($loc, 'Encounter/') !== false) && empty($encounter_id)) {
                $encounter_id = $res_id;
            } elseif (($res_type === 'Condition' || strpos($loc, 'Condition/') !== false) && empty($condition_id)) {
                $condition_id = $res_id;
            } elseif ($res_type === 'Observation' || strpos($loc, 'Observation/') !== false) {
                $obs_ids[] = $res_id;
            }
        }

        // Simpan ke mlite_satu_sehat_response
        $enc_esc  = $conn->real_escape_string($encounter_id);
        $cond_esc = $conn->real_escape_string($condition_id);
        $b_payload_esc = $conn->real_escape_string($bundle_payload);
        $r_payload_esc = $conn->real_escape_string($response);

        $conn->query("
            INSERT INTO mlite_satu_sehat_response (
                no_rawat, id_encounter, id_condition, status_sync, bundle_payload, response_payload
            ) VALUES (
                '$no_rawat_esc', '$enc_esc', '$cond_esc', 'Synced', '$b_payload_esc', '$r_payload_esc'
            ) ON DUPLICATE KEY UPDATE
                id_encounter     = '$enc_esc',
                id_condition     = '$cond_esc',
                status_sync      = 'Synced',
                bundle_payload   = '$b_payload_esc',
                response_payload = '$r_payload_esc'
        ");

        return [
            'success'         => true,
            'message'         => "Bundle Satu Sehat ($no_rawat) berhasil dikirim! Encounter ID: $encounter_id",
            'http_code'       => $http_code,
            'latency'         => $latency,
            'encounter_id'    => $encounter_id,
            'condition_id'    => $condition_id,
            'observation_ids' => $obs_ids,
            'patient_name'    => $bundle_res['patient_name'],
            'bundle_payload'  => $bundle_payload,
            'raw_response'    => $resp_data
        ];
    }

    // Jika Kemenkes mengembalikan OperationOutcome error
    $err_detail = $resp_data['issue'][0]['details']['text'] ?? $resp_data['issue'][0]['diagnostics'] ?? "Server mengembalikan HTTP $http_code";
    return [
        'success'        => false,
        'message'        => "Gagal transmisi bundle Satu Sehat: $err_detail",
        'http_code'      => $http_code,
        'latency'        => $latency,
        'bundle_payload' => $bundle_payload,
        'raw_response'   => $resp_data
    ];
}

// ─── Helper GUID / UUID Generator ─────────────────────────────
if (!function_exists('guidv4')) {
    function guidv4() {
        if (function_exists('random_bytes')) {
            $data = random_bytes(16);
            $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
            $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
            return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
        }
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
