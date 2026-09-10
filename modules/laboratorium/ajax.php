<?php
/**
 * SIMKlinik — AJAX Handler Laboratorium
 */

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$action = sanitize($_GET['action'] ?? $_POST['action'] ?? '');

switch ($action) {
    // ─── 1. Ambil Sampel (Quick Sample Acceptance) ────────────
    case 'ambil_sampel':
        $noorder = sanitize($_POST['noorder'] ?? '');
        if (empty($noorder)) {
            echo json_encode(['status' => 'error', 'message' => 'No. Order tidak valid']);
            exit;
        }

        $noorder_esc = $conn->real_escape_string($noorder);
        $tgl_sampel  = date('Y-m-d');
        $jam_sampel  = date('H:i:s');

        $upd = $conn->query("
            UPDATE permintaan_lab 
            SET tgl_sampel = '$tgl_sampel', 
                jam_sampel = '$jam_sampel'
            WHERE noorder = '$noorder_esc'
        ");

        if ($upd) {
            echo json_encode([
                'status'     => 'success',
                'message'    => 'Sampel berhasil diterima pada ' . tgl_indo($tgl_sampel) . ' ' . $jam_sampel,
                'tgl_sampel' => $tgl_sampel,
                'jam_sampel' => $jam_sampel
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Gagal memperbarui status sampel: ' . $conn->error]);
        }
        exit;

    // ─── 2. Hapus / Batalkan Permintaan Lab ───────────────────
    case 'hapus_order':
        $noorder = sanitize($_POST['noorder'] ?? '');
        if (empty($noorder)) {
            echo json_encode(['status' => 'error', 'message' => 'No. Order tidak valid']);
            exit;
        }

        $noorder_esc = $conn->real_escape_string($noorder);

        // Check if results already entered
        $chk = $conn->query("SELECT no_rawat, tgl_hasil FROM permintaan_lab WHERE noorder = '$noorder_esc'");
        if (!$chk || $chk->num_rows === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Data permintaan tidak ditemukan']);
            exit;
        }
        $row = $chk->fetch_assoc();
        if ($row['tgl_hasil'] !== '0000-00-00' && !empty($row['tgl_hasil'])) {
            echo json_encode(['status' => 'error', 'message' => 'Permintaan tidak dapat dihapus karena hasil pemeriksaan sudah dientri']);
            exit;
        }

        $conn->query("DELETE FROM permintaan_detail_permintaan_lab WHERE noorder = '$noorder_esc'");
        $conn->query("DELETE FROM permintaan_pemeriksaan_lab WHERE noorder = '$noorder_esc'");
        $conn->query("DELETE FROM permintaan_lab WHERE noorder = '$noorder_esc'");

        echo json_encode(['status' => 'success', 'message' => 'Permintaan laboratorium berhasil dibatalkan']);
        exit;

    // ─── 3. Search Pasien / Kunjungan Aktif ───────────────────
    case 'search_pasien':
        $keyword = sanitize($_GET['q'] ?? '');
        $kw_esc  = $conn->real_escape_string($keyword);

        $sql = "
            SELECT r.no_rawat, r.no_reg, r.tgl_registrasi, r.jam_reg, r.kd_dokter,
                   p.no_rkm_medis, p.nm_pasien, p.jk, p.tgl_lahir, p.alamat,
                   d.nm_dokter, pol.nm_poli, pj.png_jawab as nm_penjab
            FROM reg_periksa r
            JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
            LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
            LEFT JOIN poliklinik pol ON r.kd_poli = pol.kd_poli
            LEFT JOIN penjab pj ON p.kd_pj = pj.kd_pj
            WHERE (r.no_rawat LIKE '%$kw_esc%' OR p.no_rkm_medis LIKE '%$kw_esc%' OR p.nm_pasien LIKE '%$kw_esc%')
            ORDER BY r.tgl_registrasi DESC, r.jam_reg DESC
            LIMIT 15
        ";
        $res = $conn->query($sql);
        $data = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $r['umur'] = hitung_umur($r['tgl_lahir']);
                $r['tgl_indo'] = tgl_indo($r['tgl_registrasi']);
                $data[] = $r;
            }
        }
        echo json_encode(['status' => 'success', 'data' => $data]);
        exit;

    // ─── 4. Get Templates for Package ────────────────────────
    case 'get_templates':
        $kd_jenis_prw = sanitize($_GET['kd_jenis_prw'] ?? '');
        $kd_esc       = $conn->real_escape_string($kd_jenis_prw);

        $res = $conn->query("
            SELECT * FROM template_laboratorium 
            WHERE kd_jenis_prw = '$kd_esc'
            ORDER BY urut ASC, id_template ASC
        ");
        $templates = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $templates[] = $r;
            }
        }
        echo json_encode(['status' => 'success', 'data' => $templates]);
        exit;

    // ─── 5. Toggle Status Bayar Permintaan Lab ───────────────
    case 'toggle_bayar':
        $noorder = sanitize($_POST['noorder'] ?? '');
        $status  = sanitize($_POST['status'] ?? 'Sudah');
        $noorder_esc = $conn->real_escape_string($noorder);
        $stts_esc    = ($status === 'Sudah') ? 'Sudah' : 'Belum';

        $conn->query("UPDATE permintaan_pemeriksaan_lab SET stts_bayar = '$stts_esc' WHERE noorder = '$noorder_esc'");
        $conn->query("UPDATE permintaan_detail_permintaan_lab SET stts_bayar = '$stts_esc' WHERE noorder = '$noorder_esc'");

        echo json_encode(['status' => 'success', 'message' => 'Status pembayaran berhasil diperbarui']);
        exit;

    // ─── 6. Toggle Status Paket Lab (Aktif / Non-Aktif) ───────
    case 'toggle_status_pkg':
        $kd_jenis_prw = sanitize($_POST['kd_jenis_prw'] ?? '');
        $status       = sanitize($_POST['status'] ?? '1');
        $kd_esc       = $conn->real_escape_string($kd_jenis_prw);
        $stts_esc     = ($status === '1') ? '1' : '0';

        $upd = $conn->query("UPDATE jns_perawatan_lab SET status = '$stts_esc' WHERE kd_jenis_prw = '$kd_esc'");
        if ($upd) {
            echo json_encode(['status' => 'success', 'message' => 'Status paket lab berhasil diubah', 'new_status' => $stts_esc]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Gagal mengubah status: ' . $conn->error]);
        }
        exit;

    // ─── 7. Reorder Template Items (Naik / Turun) ─────────────
    case 'reorder_template':
        $kd_jenis_prw = sanitize($_POST['kd_jenis_prw'] ?? '');
        $id_template  = (int)($_POST['id_template'] ?? 0);
        $direction    = sanitize($_POST['direction'] ?? 'up'); // 'up' or 'down'
        $kd_esc       = $conn->real_escape_string($kd_jenis_prw);

        if (empty($kd_jenis_prw) || $id_template <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Parameter tidak valid']);
            exit;
        }

        // Ambil semua item berurutan
        $res = $conn->query("SELECT id_template, urut FROM template_laboratorium WHERE kd_jenis_prw = '$kd_esc' ORDER BY urut ASC, id_template ASC");
        $items = [];
        if ($res) while ($r = $res->fetch_assoc()) $items[] = $r;

        $target_index = -1;
        for ($i = 0; $i < count($items); $i++) {
            if ((int)$items[$i]['id_template'] === $id_template) {
                $target_index = $i;
                break;
            }
        }

        if ($target_index === -1) {
            echo json_encode(['status' => 'error', 'message' => 'Item tidak ditemukan']);
            exit;
        }

        $swap_index = ($direction === 'up') ? $target_index - 1 : $target_index + 1;
        if ($swap_index < 0 || $swap_index >= count($items)) {
            echo json_encode(['status' => 'info', 'message' => 'Item sudah di posisi paling ' . ($direction === 'up' ? 'atas' : 'bawah')]);
            exit;
        }

        // Swap urut
        $curr_id = (int)$items[$target_index]['id_template'];
        $swap_id = (int)$items[$swap_index]['id_template'];

        // Re-index all to be clean: 1, 2, 3...
        $temp = $items[$target_index];
        $items[$target_index] = $items[$swap_index];
        $items[$swap_index] = $temp;

        foreach ($items as $idx => $it) {
            $new_urut = $idx + 1;
            $it_id = (int)$it['id_template'];
            $conn->query("UPDATE template_laboratorium SET urut = $new_urut WHERE id_template = $it_id AND kd_jenis_prw = '$kd_esc'");
        }

        echo json_encode(['status' => 'success', 'message' => 'Urutan parameter berhasil diperbarui']);
        exit;

    // ─── 8. Apply Preset Template ─────────────────────────────
    case 'apply_preset':
        $kd_jenis_prw = sanitize($_POST['kd_jenis_prw'] ?? '');
        $preset_type  = sanitize($_POST['preset_type'] ?? '');
        $mode         = sanitize($_POST['mode'] ?? 'append'); // 'append' or 'replace'
        $kd_esc       = $conn->real_escape_string($kd_jenis_prw);

        if (empty($kd_jenis_prw)) {
            echo json_encode(['status' => 'error', 'message' => 'Pilih paket lab terlebih dahulu']);
            exit;
        }

        $presets = [
            'darah_lengkap' => [
                ['pemeriksaan' => 'Hemoglobin (Hb)', 'satuan' => 'g/dL', 'ld' => '13.0 - 17.5', 'la' => '11.5 - 15.5', 'pd' => '12.0 - 16.0', 'pa' => '11.5 - 15.5', 'loinc' => '718-7', 'loinc_disp' => 'Hemoglobin [Mass/volume] in Blood'],
                ['pemeriksaan' => 'Leukosit (WBC)', 'satuan' => '/uL', 'ld' => '4.000 - 10.000', 'la' => '5.000 - 12.000', 'pd' => '4.000 - 10.000', 'pa' => '5.000 - 12.000', 'loinc' => '6690-2', 'loinc_disp' => 'Leukocytes [#/volume] in Blood by Automated count'],
                ['pemeriksaan' => 'Eritrosit (RBC)', 'satuan' => '10^6/uL', 'ld' => '4.5 - 5.5', 'la' => '4.0 - 5.2', 'pd' => '4.0 - 5.0', 'pa' => '4.0 - 5.2', 'loinc' => '789-8', 'loinc_disp' => 'Erythrocytes [#/volume] in Blood by Automated count'],
                ['pemeriksaan' => 'Hematokrit (Ht)', 'satuan' => '%', 'ld' => '40 - 52', 'la' => '35 - 45', 'pd' => '36 - 48', 'pa' => '35 - 45', 'loinc' => '4544-3', 'loinc_disp' => 'Hematocrit [Volume Fraction] of Blood by Automated count'],
                ['pemeriksaan' => 'Trombosit (PLT)', 'satuan' => '/uL', 'ld' => '150.000 - 450.000', 'la' => '150.000 - 450.000', 'pd' => '150.000 - 450.000', 'pa' => '150.000 - 450.000', 'loinc' => '777-3', 'loinc_disp' => 'Platelets [#/volume] in Blood by Automated count'],
                ['pemeriksaan' => 'Laju Endap Darah (LED)', 'satuan' => 'mm/jam', 'ld' => '0 - 15', 'la' => '0 - 10', 'pd' => '0 - 20', 'pa' => '0 - 10', 'loinc' => '30341-2', 'loinc_disp' => 'Erythrocyte sedimentation rate by Westergren method'],
                ['pemeriksaan' => 'MCV', 'satuan' => 'fL', 'ld' => '80 - 100', 'la' => '77 - 95', 'pd' => '80 - 100', 'pa' => '77 - 95', 'loinc' => '787-2', 'loinc_disp' => 'MCV [Entitic volume] by Automated count'],
                ['pemeriksaan' => 'MCH', 'satuan' => 'pg', 'ld' => '27 - 34', 'la' => '25 - 33', 'pd' => '27 - 34', 'pa' => '25 - 33', 'loinc' => '785-6', 'loinc_disp' => 'MCH [Entitic mass] by Automated count'],
                ['pemeriksaan' => 'MCHC', 'satuan' => 'g/dL', 'ld' => '32 - 36', 'la' => '32 - 36', 'pd' => '32 - 36', 'pa' => '32 - 36', 'loinc' => '786-4', 'loinc_disp' => 'MCHC [Mass/volume] by Automated count'],
                ['pemeriksaan' => 'Basofil', 'satuan' => '%', 'ld' => '0 - 1', 'la' => '0 - 1', 'pd' => '0 - 1', 'pa' => '0 - 1', 'loinc' => '706-2', 'loinc_disp' => 'Basophils/100 leukocytes in Blood'],
                ['pemeriksaan' => 'Eosinofil', 'satuan' => '%', 'ld' => '1 - 3', 'la' => '1 - 3', 'pd' => '1 - 3', 'pa' => '1 - 3', 'loinc' => '711-2', 'loinc_disp' => 'Eosinophils/100 leukocytes in Blood'],
                ['pemeriksaan' => 'Batang (Neutrofil)', 'satuan' => '%', 'ld' => '2 - 6', 'la' => '2 - 6', 'pd' => '2 - 6', 'pa' => '2 - 6', 'loinc' => '764-1', 'loinc_disp' => 'Neutrophils.band/100 leukocytes in Blood'],
                ['pemeriksaan' => 'Segmen (Neutrofil)', 'satuan' => '%', 'ld' => '50 - 70', 'la' => '40 - 60', 'pd' => '50 - 70', 'pa' => '40 - 60', 'loinc' => '769-0', 'loinc_disp' => 'Neutrophils.segmented/100 leukocytes in Blood'],
                ['pemeriksaan' => 'Limfosit', 'satuan' => '%', 'ld' => '20 - 40', 'la' => '30 - 50', 'pd' => '20 - 40', 'pa' => '30 - 50', 'loinc' => '736-9', 'loinc_disp' => 'Lymphocytes/100 leukocytes in Blood'],
                ['pemeriksaan' => 'Monosit', 'satuan' => '%', 'ld' => '2 - 8', 'la' => '2 - 8', 'pd' => '2 - 8', 'pa' => '2 - 8', 'loinc' => '742-7', 'loinc_disp' => 'Monocytes/100 leukocytes in Blood']
            ],
            'fungsi_ginjal' => [
                ['pemeriksaan' => 'Ureum', 'satuan' => 'mg/dL', 'ld' => '15 - 45', 'la' => '10 - 40', 'pd' => '15 - 45', 'pa' => '10 - 40', 'loinc' => '3094-0', 'loinc_disp' => 'Urea nitrogen [Mass/volume] in Serum or Plasma'],
                ['pemeriksaan' => 'Kreatinin', 'satuan' => 'mg/dL', 'ld' => '0.7 - 1.3', 'la' => '0.3 - 0.7', 'pd' => '0.6 - 1.1', 'pa' => '0.3 - 0.7', 'loinc' => '2160-0', 'loinc_disp' => 'Creatinine [Mass/volume] in Serum or Plasma'],
                ['pemeriksaan' => 'Asam Urat (Uric Acid)', 'satuan' => 'mg/dL', 'ld' => '3.5 - 7.2', 'la' => '2.0 - 5.5', 'pd' => '2.6 - 6.0', 'pa' => '2.0 - 5.5', 'loinc' => '3084-1', 'loinc_disp' => 'Urate [Mass/volume] in Serum or Plasma'],
                ['pemeriksaan' => 'eGFR', 'satuan' => 'mL/min/1.73m2', 'ld' => '> 90', 'la' => '> 90', 'pd' => '> 90', 'pa' => '> 90', 'loinc' => '33914-3', 'loinc_disp' => 'Glomerular filtration rate/1.73 sq M.predicted']
            ],
            'fungsi_hati' => [
                ['pemeriksaan' => 'SGOT / AST', 'satuan' => 'U/L', 'ld' => '< 37', 'la' => '< 40', 'pd' => '< 31', 'pa' => '< 40', 'loinc' => '1920-8', 'loinc_disp' => 'Aspartate aminotransferase [Enzymatic activity/volume] in Serum or Plasma'],
                ['pemeriksaan' => 'SGPT / ALT', 'satuan' => 'U/L', 'ld' => '< 41', 'la' => '< 35', 'pd' => '< 31', 'pa' => '< 35', 'loinc' => '1742-6', 'loinc_disp' => 'Alanine aminotransferase [Enzymatic activity/volume] in Serum or Plasma'],
                ['pemeriksaan' => 'Bilirubin Total', 'satuan' => 'mg/dL', 'ld' => '0.2 - 1.2', 'la' => '0.2 - 1.0', 'pd' => '0.2 - 1.2', 'pa' => '0.2 - 1.0', 'loinc' => '1975-2', 'loinc_disp' => 'Bilirubin.total [Mass/volume] in Serum or Plasma'],
                ['pemeriksaan' => 'Bilirubin Direk', 'satuan' => 'mg/dL', 'ld' => '< 0.3', 'la' => '< 0.3', 'pd' => '< 0.3', 'pa' => '< 0.3', 'loinc' => '1968-7', 'loinc_disp' => 'Bilirubin.direct [Mass/volume] in Serum or Plasma'],
                ['pemeriksaan' => 'Bilirubin Indirek', 'satuan' => 'mg/dL', 'ld' => '< 0.8', 'la' => '< 0.8', 'pd' => '< 0.8', 'pa' => '< 0.8', 'loinc' => '1971-1', 'loinc_disp' => 'Bilirubin.indirect [Mass/volume] in Serum or Plasma'],
                ['pemeriksaan' => 'Protein Total', 'satuan' => 'g/dL', 'ld' => '6.4 - 8.3', 'la' => '6.0 - 8.0', 'pd' => '6.4 - 8.3', 'pa' => '6.0 - 8.0', 'loinc' => '2885-2', 'loinc_disp' => 'Protein [Mass/volume] in Serum or Plasma'],
                ['pemeriksaan' => 'Albumin', 'satuan' => 'g/dL', 'ld' => '3.5 - 5.0', 'la' => '3.8 - 5.4', 'pd' => '3.5 - 5.0', 'pa' => '3.8 - 5.4', 'loinc' => '1751-7', 'loinc_disp' => 'Albumin [Mass/volume] in Serum or Plasma'],
                ['pemeriksaan' => 'Globulin', 'satuan' => 'g/dL', 'ld' => '2.3 - 3.5', 'la' => '2.0 - 3.2', 'pd' => '2.3 - 3.5', 'pa' => '2.0 - 3.2', 'loinc' => '2342-4', 'loinc_disp' => 'Globulin [Mass/volume] in Serum by calculation']
            ],
            'profil_lipid' => [
                ['pemeriksaan' => 'Kolesterol Total', 'satuan' => 'mg/dL', 'ld' => '< 200', 'la' => '< 170', 'pd' => '< 200', 'pa' => '< 170', 'loinc' => '2093-3', 'loinc_disp' => 'Cholesterol [Mass/volume] in Serum or Plasma'],
                ['pemeriksaan' => 'Trigliserida', 'satuan' => 'mg/dL', 'ld' => '< 150', 'la' => '< 100', 'pd' => '< 150', 'pa' => '< 100', 'loinc' => '2571-8', 'loinc_disp' => 'Triglyceride [Mass/volume] in Serum or Plasma'],
                ['pemeriksaan' => 'HDL Kolesterol', 'satuan' => 'mg/dL', 'ld' => '> 40', 'la' => '> 45', 'pd' => '> 50', 'pa' => '> 45', 'loinc' => '2085-9', 'loinc_disp' => 'Cholesterol in HDL [Mass/volume] in Serum or Plasma'],
                ['pemeriksaan' => 'LDL Kolesterol', 'satuan' => 'mg/dL', 'ld' => '< 100', 'la' => '< 110', 'pd' => '< 100', 'pa' => '< 110', 'loinc' => '2089-1', 'loinc_disp' => 'Cholesterol in LDL [Mass/volume] in Serum or Plasma']
            ],
            'glukosa' => [
                ['pemeriksaan' => 'Glukosa Sewaktu (GDS)', 'satuan' => 'mg/dL', 'ld' => '< 140', 'la' => '< 140', 'pd' => '< 140', 'pa' => '< 140', 'loinc' => '2339-0', 'loinc_disp' => 'Glucose [Mass/volume] in Blood'],
                ['pemeriksaan' => 'Glukosa Puasa (GDP)', 'satuan' => 'mg/dL', 'ld' => '70 - 100', 'la' => '70 - 100', 'pd' => '70 - 100', 'pa' => '70 - 100', 'loinc' => '1558-6', 'loinc_disp' => 'Fasting glucose [Mass/volume] in Serum or Plasma'],
                ['pemeriksaan' => 'Glukosa 2 Jam PP (GD2PP)', 'satuan' => 'mg/dL', 'ld' => '< 140', 'la' => '< 140', 'pd' => '< 140', 'pa' => '< 140', 'loinc' => '1521-4', 'loinc_disp' => 'Glucose 2 hours post 75 g glucose PO [Mass/volume] in Serum or Plasma'],
                ['pemeriksaan' => 'HbA1c', 'satuan' => '%', 'ld' => '< 5.7', 'la' => '< 5.7', 'pd' => '< 5.7', 'pa' => '< 5.7', 'loinc' => '4548-4', 'loinc_disp' => 'Hemoglobin A1c/Hemoglobin.total in Blood']
            ],
            'urin_lengkap' => [
                ['pemeriksaan' => 'Warna', 'satuan' => '', 'ld' => 'Kuning Muda', 'la' => 'Kuning Muda', 'pd' => 'Kuning Muda', 'pa' => 'Kuning Muda', 'loinc' => '5778-6', 'loinc_disp' => 'Color of Urine'],
                ['pemeriksaan' => 'Kejernihan', 'satuan' => '', 'ld' => 'Jernih', 'la' => 'Jernih', 'pd' => 'Jernih', 'pa' => 'Jernih', 'loinc' => '5777-8', 'loinc_disp' => 'Appearance of Urine'],
                ['pemeriksaan' => 'Berat Jenis (BJ)', 'satuan' => '', 'ld' => '1.005 - 1.030', 'la' => '1.005 - 1.030', 'pd' => '1.005 - 1.030', 'pa' => '1.005 - 1.030', 'loinc' => '5811-5', 'loinc_disp' => 'Specific gravity of Urine by Test strip'],
                ['pemeriksaan' => 'pH Urin', 'satuan' => '', 'ld' => '5.0 - 8.0', 'la' => '5.0 - 8.0', 'pd' => '5.0 - 8.0', 'pa' => '5.0 - 8.0', 'loinc' => '5803-2', 'loinc_disp' => 'pH of Urine by Test strip'],
                ['pemeriksaan' => 'Protein Urin', 'satuan' => '', 'ld' => 'Negatif', 'la' => 'Negatif', 'pd' => 'Negatif', 'pa' => 'Negatif', 'loinc' => '5804-0', 'loinc_disp' => 'Protein [Mass/volume] in Urine by Test strip'],
                ['pemeriksaan' => 'Glukosa Urin', 'satuan' => '', 'ld' => 'Negatif', 'la' => 'Negatif', 'pd' => 'Negatif', 'pa' => 'Negatif', 'loinc' => '5792-7', 'loinc_disp' => 'Glucose [Mass/volume] in Urine by Test strip'],
                ['pemeriksaan' => 'Keton', 'satuan' => '', 'ld' => 'Negatif', 'la' => 'Negatif', 'pd' => 'Negatif', 'pa' => 'Negatif', 'loinc' => '5797-6', 'loinc_disp' => 'Ketones [Mass/volume] in Urine by Test strip'],
                ['pemeriksaan' => 'Bilirubin Urin', 'satuan' => '', 'ld' => 'Negatif', 'la' => 'Negatif', 'pd' => 'Negatif', 'pa' => 'Negatif', 'loinc' => '5770-3', 'loinc_disp' => 'Bilirubin [Mass/volume] in Urine by Test strip'],
                ['pemeriksaan' => 'Urobilinogen', 'satuan' => 'mg/dL', 'ld' => '0.2 - 1.0', 'la' => '0.2 - 1.0', 'pd' => '0.2 - 1.0', 'pa' => '0.2 - 1.0', 'loinc' => '5809-9', 'loinc_disp' => 'Urobilinogen [Mass/volume] in Urine by Test strip'],
                ['pemeriksaan' => 'Nitrit', 'satuan' => '', 'ld' => 'Negatif', 'la' => 'Negatif', 'pd' => 'Negatif', 'pa' => 'Negatif', 'loinc' => '5802-4', 'loinc_disp' => 'Nitrite in Urine by Test strip'],
                ['pemeriksaan' => 'Sedimen Leukosit', 'satuan' => '/LPB', 'ld' => '0 - 5', 'la' => '0 - 5', 'pd' => '0 - 5', 'pa' => '0 - 5', 'loinc' => '5799-2', 'loinc_disp' => 'Leukocytes [#/area] in Urine sediment by Microscopy high power field'],
                ['pemeriksaan' => 'Sedimen Eritrosit', 'satuan' => '/LPB', 'ld' => '0 - 2', 'la' => '0 - 2', 'pd' => '0 - 2', 'pa' => '0 - 2', 'loinc' => '5794-3', 'loinc_disp' => 'Erythrocytes [#/area] in Urine sediment by Microscopy high power field'],
                ['pemeriksaan' => 'Sedimen Epitel', 'satuan' => '/LPK', 'ld' => 'Positif (+)', 'la' => 'Positif (+)', 'pd' => 'Positif (+)', 'pa' => 'Positif (+)', 'loinc' => '5788-5', 'loinc_disp' => 'Epithelial cells [#/area] in Urine sediment by Microscopy low power field'],
                ['pemeriksaan' => 'Kristal', 'satuan' => '', 'ld' => 'Negatif', 'la' => 'Negatif', 'pd' => 'Negatif', 'pa' => 'Negatif', 'loinc' => '5782-8', 'loinc_disp' => 'Crystals [#/area] in Urine sediment by Microscopy'],
                ['pemeriksaan' => 'Silinder', 'satuan' => '', 'ld' => 'Negatif', 'la' => 'Negatif', 'pd' => 'Negatif', 'pa' => 'Negatif', 'loinc' => '5773-7', 'loinc_disp' => 'Casts [#/area] in Urine sediment by Microscopy'],
                ['pemeriksaan' => 'Bakteri', 'satuan' => '', 'ld' => 'Negatif', 'la' => 'Negatif', 'pd' => 'Negatif', 'pa' => 'Negatif', 'loinc' => '5769-5', 'loinc_disp' => 'Bacteria [#/area] in Urine sediment by Microscopy']
            ],
            'imunoserologi' => [
                ['pemeriksaan' => 'Widal S. Typhi O', 'satuan' => 'Titer', 'ld' => '< 1/80', 'la' => '< 1/80', 'pd' => '< 1/80', 'pa' => '< 1/80', 'loinc' => '20942-9', 'loinc_disp' => 'Salmonella enterica serovar Typhi O Ab in Serum by Agglutination'],
                ['pemeriksaan' => 'Widal S. Typhi H', 'satuan' => 'Titer', 'ld' => '< 1/80', 'la' => '< 1/80', 'pd' => '< 1/80', 'pa' => '< 1/80', 'loinc' => '20941-1', 'loinc_disp' => 'Salmonella enterica serovar Typhi H Ab in Serum by Agglutination'],
                ['pemeriksaan' => 'HBsAg Kualitatif', 'satuan' => '', 'ld' => 'Non Reaktif', 'la' => 'Non Reaktif', 'pd' => 'Non Reaktif', 'pa' => 'Non Reaktif', 'loinc' => '5196-1', 'loinc_disp' => 'Hepatitis B virus surface Ag [Presence] in Serum or Plasma by Rapid test'],
                ['pemeriksaan' => 'Anti HIV Kualitatif', 'satuan' => '', 'ld' => 'Non Reaktif', 'la' => 'Non Reaktif', 'pd' => 'Non Reaktif', 'pa' => 'Non Reaktif', 'loinc' => '75622-1', 'loinc_disp' => 'HIV 1 and 2 Ab [Presence] in Serum or Plasma by Rapid test'],
                ['pemeriksaan' => 'NS1 Dengue Ag', 'satuan' => '', 'ld' => 'Negatif', 'la' => 'Negatif', 'pd' => 'Negatif', 'pa' => 'Negatif', 'loinc' => '69741-7', 'loinc_disp' => 'Dengue virus NS1 Ag [Presence] in Serum or Plasma by Rapid test'],
                ['pemeriksaan' => 'Golongan Darah + Rhesus', 'satuan' => '', 'ld' => '-', 'la' => '-', 'pd' => '-', 'pa' => '-', 'loinc' => '883-9', 'loinc_disp' => 'ABO and Rh group [Type] in Blood']
            ]
        ];

        if (!isset($presets[$preset_type])) {
            echo json_encode(['status' => 'error', 'message' => 'Jenis preset tidak ditemukan']);
            exit;
        }

        if ($mode === 'replace') {
            $conn->query("DELETE FROM template_laboratorium WHERE kd_jenis_prw = '$kd_esc'");
            $current_urut = 1;
        } else {
            $res_u = $conn->query("SELECT MAX(urut) as max_u FROM template_laboratorium WHERE kd_jenis_prw = '$kd_esc'");
            $current_urut = ($res_u && $ru = $res_u->fetch_assoc()) ? (int)$ru['max_u'] + 1 : 1;
        }

        $items_to_add = $presets[$preset_type];
        $added_count = 0;

        foreach ($items_to_add as $it) {
            $pem_esc = $conn->real_escape_string($it['pemeriksaan']);
            $sat_esc = $conn->real_escape_string($it['satuan']);
            $ld_esc  = $conn->real_escape_string($it['ld']);
            $la_esc  = $conn->real_escape_string($it['la']);
            $pd_esc  = $conn->real_escape_string($it['pd']);
            $pa_esc  = $conn->real_escape_string($it['pa']);

            // Next ID
            $res_m = $conn->query("SELECT MAX(id_template) as m FROM template_laboratorium");
            $next_id = ($res_m && $row_m = $res_m->fetch_assoc()) ? (int)$row_m['m'] + 1 : 1;

            $ins = $conn->query("
                INSERT INTO template_laboratorium (
                    kd_jenis_prw, id_template, Pemeriksaan, satuan,
                    nilai_rujukan_ld, nilai_rujukan_la, nilai_rujukan_pd, nilai_rujukan_pa,
                    bagian_rs, bhp, bagian_perujuk, bagian_dokter, bagian_laborat,
                    kso, menejemen, biaya_item, urut
                ) VALUES (
                    '$kd_esc', $next_id, '$pem_esc', '$sat_esc',
                    '$ld_esc', '$la_esc', '$pd_esc', '$pa_esc',
                    0, 0, 0, 0, 0, 0, 0, 0, $current_urut
                )
            ");

            if ($ins) {
                // If LOINC mapping provided
                if (!empty($it['loinc'])) {
                    $l_code = $conn->real_escape_string($it['loinc']);
                    $l_disp = $conn->real_escape_string($it['loinc_disp']);
                    $sampel_code = '119297000'; // Blood specimen default
                    $sampel_disp = 'Blood specimen';
                    if ($preset_type === 'urin_lengkap') {
                        $sampel_code = '122575003';
                        $sampel_disp = 'Urine specimen';
                    }
                    $conn->query("
                        INSERT INTO mlite_satu_sehat_mapping_lab (
                            id_template, kd_jenis_prw, code, system, display,
                            sampel_code, sampel_system, sampel_display
                        ) VALUES (
                            $next_id, '$kd_esc', '$l_code', 'http://loinc.org', '$l_disp',
                            '$sampel_code', 'http://snomed.info/sct', '$sampel_disp'
                        ) ON DUPLICATE KEY UPDATE
                            code = '$l_code', display = '$l_disp'
                    ");
                }
                $current_urut++;
                $added_count++;
            }
        }

        echo json_encode(['status' => 'success', 'message' => "Berhasil menerapkan preset. $added_count parameter tindakan berhasil ditambahkan."]);
        exit;

    // ─── 9. Save Satu Sehat Mapping Lab ───────────────────────
    case 'save_satusehat_mapping':
        $id_template    = (int)($_POST['id_template'] ?? 0);
        $kd_jenis_prw   = sanitize($_POST['kd_jenis_prw'] ?? '');
        $code           = sanitize($_POST['code'] ?? '');
        $display        = sanitize($_POST['display'] ?? '');
        $system         = sanitize($_POST['system'] ?? 'http://loinc.org');
        $sampel_code    = sanitize($_POST['sampel_code'] ?? '119297000');
        $sampel_system  = sanitize($_POST['sampel_system'] ?? 'http://snomed.info/sct');
        $sampel_display = sanitize($_POST['sampel_display'] ?? 'Blood specimen');

        if ($id_template <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'ID template parameter tidak valid']);
            exit;
        }

        $kd_esc      = $conn->real_escape_string($kd_jenis_prw);
        $code_esc    = $conn->real_escape_string($code);
        $disp_esc    = $conn->real_escape_string($display);
        $sys_esc     = $conn->real_escape_string($system);
        $scode_esc   = $conn->real_escape_string($sampel_code);
        $ssys_esc    = $conn->real_escape_string($sampel_system);
        $sdisp_esc   = $conn->real_escape_string($sampel_display);

        if (empty($code)) {
            // Delete mapping if empty code
            $conn->query("DELETE FROM mlite_satu_sehat_mapping_lab WHERE id_template = $id_template");
            echo json_encode(['status' => 'success', 'message' => 'Pemetaan Satu Sehat berhasil dihapus']);
            exit;
        }

        $sql = "
            INSERT INTO mlite_satu_sehat_mapping_lab (
                id_template, kd_jenis_prw, code, system, display,
                sampel_code, sampel_system, sampel_display
            ) VALUES (
                $id_template, '$kd_esc', '$code_esc', '$sys_esc', '$disp_esc',
                '$scode_esc', '$ssys_esc', '$sdisp_esc'
            ) ON DUPLICATE KEY UPDATE
                kd_jenis_prw   = '$kd_esc',
                code           = '$code_esc',
                system         = '$sys_esc',
                display        = '$disp_esc',
                sampel_code    = '$scode_esc',
                sampel_system  = '$ssys_esc',
                sampel_display = '$sdisp_esc'
        ";

        if ($conn->query($sql)) {
            echo json_encode(['status' => 'success', 'message' => 'Pemetaan Satu Sehat LOINC berhasil disimpan']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan pemetaan: ' . $conn->error]);
        }
        exit;

    // ─── 10. Toggle Status Paket Lab (Aktif / Non-Aktif) ───────
    case 'toggle_status_pkg':
        $kd_jenis_prw = sanitize($_POST['kd_jenis_prw'] ?? '');
        $status       = sanitize($_POST['status'] ?? '1');

        if (empty($kd_jenis_prw)) {
            echo json_encode(['status' => 'error', 'message' => 'Kode paket tidak valid']);
            exit;
        }

        $kd_esc = $conn->real_escape_string($kd_jenis_prw);
        $st_esc = ($status === '1') ? '1' : '0';

        $upd = $conn->query("UPDATE jns_perawatan_lab SET status = '$st_esc' WHERE kd_jenis_prw = '$kd_esc'");
        if ($upd) {
            echo json_encode([
                'status'  => 'success',
                'message' => 'Status paket ' . $kd_jenis_prw . ' berhasil diubah menjadi ' . ($status === '1' ? 'Aktif' : 'Non-Aktif')
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Gagal mengubah status: ' . $conn->error]);
        }
        exit;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Aksi tidak dikenali']);
        exit;
}

