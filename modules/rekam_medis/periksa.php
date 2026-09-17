<?php
/**
 * SIMKlinik — Pemeriksaan Pasien & E-RM (SOAP, Diagnosa, Tindakan/Tarif Ralan & E-Resep)
 */

$page_title    = 'Pemeriksaan Medis (SOAP)';
$active_module = 'rekam_medis';

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

$no_rawat = sanitize($_GET['no_rawat'] ?? '');
if (empty($no_rawat)) redirect(BASE_URL . 'modules/rekam_medis/index.php');

$rawat_esc = $conn->real_escape_string($no_rawat);

// ─── Load Data Pasien & Registrasi ───────────────────────────
$res = $conn->query("
    SELECT r.*, p.nm_pasien, p.jk, p.tgl_lahir, p.no_ktp, p.no_peserta,
           p.alamat, p.gol_darah, p.no_tlp,
           d.nm_dokter, d.kd_dokter,
           pol.nm_poli, pj.png_jawab as nm_penjab
    FROM reg_periksa r
    JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
    LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
    LEFT JOIN poliklinik pol ON r.kd_poli = pol.kd_poli
    LEFT JOIN penjab pj ON p.kd_pj = pj.kd_pj
    WHERE r.no_rawat = '$rawat_esc'
    LIMIT 1
");

if (!$res || $res->num_rows === 0) {
    set_flash('danger', 'Data kunjungan tidak ditemukan.');
    redirect(BASE_URL . 'modules/rekam_medis/index.php');
}
$pasien = $res->fetch_assoc();

// Check status General Consent
$res_gc_check = $conn->query("SELECT no_rawat, ttd_pasien FROM surat_persetujuan_umum WHERE no_rawat = '$rawat_esc' LIMIT 1");
$has_gc = ($res_gc_check && $res_gc_check->num_rows > 0);

// ─── Auto-Trigger BPJS Antrean Task 4 (Dipanggil Non-Blocking via Background AJAX) ──
require_once dirname(__DIR__, 2) . '/includes/bpjs_antrean.php';
require_once dirname(__DIR__, 2) . '/includes/pcare_service.php';

// ─── Load Data Pemeriksaan Ralan (SOAP) Kunjungan Ini ───────
$today_soaps_res = $conn->query("
    SELECT pr.*,
           COALESCE(pg.nama, pt.nama, d.nm_dokter, pr.nip, '-') as nama_petugas
    FROM pemeriksaan_ralan pr
    LEFT JOIN pegawai pg ON pr.nip = pg.nik
    LEFT JOIN petugas pt ON pr.nip = pt.nip
    LEFT JOIN dokter d ON pr.nip = d.kd_dokter
    WHERE pr.no_rawat = '$rawat_esc'
    ORDER BY pr.tgl_perawatan ASC, pr.jam_rawat ASC
");
$today_soaps = [];
if ($today_soaps_res) {
    while ($row = $today_soaps_res->fetch_assoc()) $today_soaps[] = $row;
}
$soap = []; // Form default kosong saat masuk pemeriksaan medis

// ─── Load Diagnosa ICD-10 Pasien ─────────────────────────────
$diag_res = $conn->query("
    SELECT dp.*, p.nm_penyakit
    FROM diagnosa_pasien dp
    JOIN penyakit p ON dp.kd_penyakit = p.kd_penyakit
    WHERE dp.no_rawat = '$rawat_esc'
    ORDER BY dp.prioritas ASC, dp.kd_penyakit ASC
");
$diagnosa_list = [];
if ($diag_res) {
    while ($row = $diag_res->fetch_assoc()) $diagnosa_list[] = $row;
}

// ─── Load Tindakan / Prosedur Rawat Jalan Pasien ─────────────
$tindakan_res = $conn->query("
    SELECT rj.kd_jenis_prw, jp.nm_perawatan, rj.biaya_rawat, rj.tgl_perawatan, rj.jam_rawat,
           'dr' as pelaksana_type, 'Dokter' as pelaksana, d.nm_dokter as nm_pelaksana
    FROM rawat_jl_dr rj
    JOIN jns_perawatan jp ON rj.kd_jenis_prw = jp.kd_jenis_prw
    LEFT JOIN dokter d ON rj.kd_dokter = d.kd_dokter
    WHERE rj.no_rawat = '$rawat_esc'
    UNION ALL
    SELECT rj.kd_jenis_prw, jp.nm_perawatan, rj.biaya_rawat, rj.tgl_perawatan, rj.jam_rawat,
           'pr' as pelaksana_type, 'Petugas' as pelaksana, pg.nama as nm_pelaksana
    FROM rawat_jl_pr rj
    JOIN jns_perawatan jp ON rj.kd_jenis_prw = jp.kd_jenis_prw
    LEFT JOIN petugas pg ON rj.nip = pg.nip
    WHERE rj.no_rawat = '$rawat_esc'
    UNION ALL
    SELECT rj.kd_jenis_prw, jp.nm_perawatan, rj.biaya_rawat, rj.tgl_perawatan, rj.jam_rawat,
           'drpr' as pelaksana_type, 'Dokter & Petugas' as pelaksana, CONCAT(COALESCE(d.nm_dokter, '-'), ' & ', COALESCE(pg.nama, '-')) as nm_pelaksana
    FROM rawat_jl_drpr rj
    JOIN jns_perawatan jp ON rj.kd_jenis_prw = jp.kd_jenis_prw
    LEFT JOIN dokter d ON rj.kd_dokter = d.kd_dokter
    LEFT JOIN petugas pg ON rj.nip = pg.nip
    WHERE rj.no_rawat = '$rawat_esc'
    ORDER BY tgl_perawatan DESC, jam_rawat DESC
");
$tindakan_list = [];
$total_biaya_tindakan = 0;
if ($tindakan_res) {
    while ($row = $tindakan_res->fetch_assoc()) {
        $total_biaya_tindakan += (float)$row['biaya_rawat'];
        $tindakan_list[] = $row;
    }
}

// Master Dokter & Petugas untuk Form Tindakan
$dokter_select = [];
$rd = $conn->query("SELECT kd_dokter, nm_dokter FROM dokter WHERE status = '1' ORDER BY nm_dokter");
if ($rd) while ($r = $rd->fetch_assoc()) $dokter_select[] = $r;

$petugas_select = [];
$rpg = $conn->query("SELECT nip, nama FROM petugas WHERE status = '1' ORDER BY nama");
if ($rpg) while ($r = $rpg->fetch_assoc()) $petugas_select[] = $r;

// ─── Load Resep Obat Pasien (Non-Racik & Racikan) ────────────
$resep_res = $conn->query("
    SELECT ro.no_resep, rd.kode_brng, rd.jml, rd.aturan_pakai,
           db.nama_brng, db.ralan as harga, ks.satuan
    FROM resep_obat ro
    JOIN resep_dokter rd ON ro.no_resep = rd.no_resep
    JOIN databarang db ON rd.kode_brng = db.kode_brng
    LEFT JOIN kodesatuan ks ON db.kode_sat = ks.kode_sat
    WHERE ro.no_rawat = '$rawat_esc'
    ORDER BY db.nama_brng ASC
");
$resep_list = [];
$active_no_resep = '';
if ($resep_res) {
    while ($row = $resep_res->fetch_assoc()) {
        $active_no_resep = $row['no_resep'];
        $resep_list[] = $row;
    }
}

// Resep Racikan (Single Batch Query untuk Detail Bahan)
$resep_racik_res = $conn->query("
    SELECT rdr.*, mr.nm_racik as metode_nama, ro.no_rawat
    FROM resep_obat ro
    JOIN resep_dokter_racikan rdr ON ro.no_resep = rdr.no_resep
    LEFT JOIN metode_racik mr ON rdr.kd_racik = mr.kd_racik
    WHERE ro.no_rawat = '$rawat_esc'
    ORDER BY CAST(rdr.no_racik AS UNSIGNED) ASC
");
$resep_racik_list = [];
$racik_reseps = [];
if ($resep_racik_res) {
    while ($row = $resep_racik_res->fetch_assoc()) {
        if (empty($active_no_resep)) $active_no_resep = $row['no_resep'];
        $row['detail_bahan'] = [];
        $key = $row['no_resep'] . '_' . $row['no_racik'];
        $resep_racik_list[$key] = $row;
        $racik_reseps[] = "'" . $conn->real_escape_string($row['no_resep']) . "'";
    }
}

if (!empty($racik_reseps)) {
    $r_in = implode(',', array_unique($racik_reseps));
    $dtl_res = $conn->query("
        SELECT rdrd.*, db.nama_brng, db.ralan as harga, ks.satuan
        FROM resep_dokter_racikan_detail rdrd
        JOIN databarang db ON rdrd.kode_brng = db.kode_brng
        LEFT JOIN kodesatuan ks ON db.kode_sat = ks.kode_sat
        WHERE rdrd.no_resep IN ($r_in)
        ORDER BY db.nama_brng ASC
    ");
    if ($dtl_res) {
        while ($dtl = $dtl_res->fetch_assoc()) {
            $key = $dtl['no_resep'] . '_' . $dtl['no_racik'];
            if (isset($resep_racik_list[$key])) {
                $resep_racik_list[$key]['detail_bahan'][] = $dtl;
            }
        }
    }
}
$resep_racik_list = array_values($resep_racik_list);

// Master Metode Racik
$metode_racik_list = [];
$rmr = $conn->query("SELECT * FROM metode_racik ORDER BY nm_racik ASC");
if ($rmr) while ($r = $rmr->fetch_assoc()) $metode_racik_list[] = $r;
if (empty($metode_racik_list)) {
    $metode_racik_list = [
        ['kd_racik' => 'R01', 'nm_racik' => 'Puyer'],
        ['kd_racik' => 'R04', 'nm_racik' => 'Kapsul'],
        ['kd_racik' => 'R03', 'nm_racik' => 'Salep'],
        ['kd_racik' => 'R02', 'nm_racik' => 'Sirup']
    ];
}

// ─── Load Permintaan & Hasil Laboratorium ────────────────────
$lab_req_res = $conn->query("
    SELECT pl.*,
           (SELECT GROUP_CONCAT(jpl.nm_perawatan SEPARATOR ', ')
            FROM permintaan_pemeriksaan_lab ppl
            JOIN jns_perawatan_lab jpl ON ppl.kd_jenis_prw = jpl.kd_jenis_prw
            WHERE ppl.noorder = pl.noorder) as list_paket
    FROM permintaan_lab pl
    WHERE pl.no_rawat = '$rawat_esc'
    ORDER BY pl.tgl_permintaan DESC, pl.jam_permintaan DESC
");
$lab_req_list = [];
if ($lab_req_res) {
    while ($row = $lab_req_res->fetch_assoc()) $lab_req_list[] = $row;
}

$lab_hasil_res = $conn->query("
    SELECT pl.kd_jenis_prw, jpl.nm_perawatan, pl.tgl_periksa, pl.jam,
           d.id_template, t.Pemeriksaan, t.satuan, d.nilai, d.nilai_rujukan, d.keterangan
    FROM periksa_lab pl
    JOIN jns_perawatan_lab jpl ON pl.kd_jenis_prw = jpl.kd_jenis_prw
    JOIN detail_periksa_lab d ON pl.no_rawat = d.no_rawat AND pl.kd_jenis_prw = d.kd_jenis_prw
    JOIN template_laboratorium t ON d.id_template = t.id_template
    WHERE pl.no_rawat = '$rawat_esc'
    ORDER BY pl.tgl_periksa DESC, pl.jam DESC, t.urut ASC
");
$lab_hasil_list = [];
if ($lab_hasil_res) {
    while ($row = $lab_hasil_res->fetch_assoc()) $lab_hasil_list[] = $row;
}

// ─── Load Riwayat Pemeriksaan Sebelumnya ─────────────────────
$no_rm = $conn->real_escape_string($pasien['no_rkm_medis']);
$history_res = $conn->query("
    SELECT 
        r.tgl_registrasi,
        MAX(r.no_rawat) as no_rawat,
        GROUP_CONCAT(DISTINCT pol.nm_poli SEPARATOR ', ') as nm_poli,
        GROUP_CONCAT(DISTINCT d.nm_dokter SEPARATOR ', ') as nm_dokter,
        (
            SELECT GROUP_CONCAT(DISTINCT CONCAT(dp.kd_penyakit, ': ', py.nm_penyakit) SEPARATOR ', ')
            FROM reg_periksa r3
            JOIN diagnosa_pasien dp ON r3.no_rawat = dp.no_rawat
            JOIN penyakit py ON dp.kd_penyakit = py.kd_penyakit
            WHERE r3.no_rkm_medis = '$no_rm' 
              AND r3.tgl_registrasi = r.tgl_registrasi
              AND r3.no_rawat != '$rawat_esc'
              AND dp.kd_penyakit != '' AND dp.kd_penyakit != '-'
              AND py.nm_penyakit IS NOT NULL AND TRIM(py.nm_penyakit) != ''
        ) as diagnosa
    FROM reg_periksa r
    LEFT JOIN poliklinik pol ON r.kd_poli = pol.kd_poli
    LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
    WHERE r.no_rkm_medis = '$no_rm' AND r.no_rawat != '$rawat_esc'
    GROUP BY r.tgl_registrasi
    ORDER BY r.tgl_registrasi DESC
    LIMIT 10
");
$history_list = [];
$tgl_keys = [];
if ($history_res) {
    while ($row = $history_res->fetch_assoc()) {
        $row['soaps'] = [];
        $history_list[$row['tgl_registrasi']] = $row;
        $tgl_keys[] = "'" . $conn->real_escape_string($row['tgl_registrasi']) . "'";
    }
}

if (!empty($tgl_keys)) {
    $tgl_in = implode(',', $tgl_keys);
    $soap_all_res = $conn->query("
        SELECT 
            r.tgl_registrasi, pr.no_rawat, pr.tgl_perawatan, pr.jam_rawat, pr.nip,
            pr.keluhan, pr.pemeriksaan, pr.penilaian, pr.rtl,
            pr.suhu_tubuh, pr.tensi, pr.nadi, pr.respirasi,
            COALESCE(pg.nama, pt.nama, d.nm_dokter, pr.nip, '-') as nama_petugas
        FROM pemeriksaan_ralan pr
        JOIN reg_periksa r ON pr.no_rawat = r.no_rawat
        LEFT JOIN pegawai pg ON pr.nip = pg.nik
        LEFT JOIN petugas pt ON pr.nip = pt.nip
        LEFT JOIN dokter d ON pr.nip = d.kd_dokter
        WHERE r.no_rkm_medis = '$no_rm' 
          AND r.tgl_registrasi IN ($tgl_in)
          AND r.no_rawat != '$rawat_esc'
        ORDER BY r.tgl_registrasi DESC, pr.jam_rawat ASC
    ");
    if ($soap_all_res) {
        while ($s = $soap_all_res->fetch_assoc()) {
            if (isset($history_list[$s['tgl_registrasi']])) {
                $history_list[$s['tgl_registrasi']]['soaps'][] = $s;
            }
        }
    }
}
$history_list = array_values($history_list);

// ─── Proses Simpan Form SOAP ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_soap'])) {
    $tgl_perawatan = date('Y-m-d');
    $jam_rawat     = date('H:i:s');
    $suhu_tubuh    = $conn->real_escape_string(sanitize($_POST['suhu_tubuh'] ?? ''));
    $tensi         = $conn->real_escape_string(sanitize($_POST['tensi'] ?? ''));
    $nadi          = $conn->real_escape_string(sanitize($_POST['nadi'] ?? ''));
    $respirasi     = $conn->real_escape_string(sanitize($_POST['respirasi'] ?? ''));
    $tinggi        = $conn->real_escape_string(sanitize($_POST['tinggi'] ?? ''));
    $berat         = $conn->real_escape_string(sanitize($_POST['berat'] ?? ''));
    $spo2          = $conn->real_escape_string(sanitize($_POST['spo2'] ?? ''));
    $gcs           = $conn->real_escape_string(sanitize($_POST['gcs'] ?? ''));
    $kesadaran     = $conn->real_escape_string(sanitize($_POST['kesadaran'] ?? 'Compos Mentis'));
    $lingkar_perut = $conn->real_escape_string(sanitize($_POST['lingkar_perut'] ?? ''));
    $alergi        = $conn->real_escape_string(sanitize($_POST['alergi'] ?? ''));
    $keluhan       = $conn->real_escape_string(trim($_POST['keluhan'] ?? ''));
    $pemeriksaan   = $conn->real_escape_string(trim($_POST['pemeriksaan'] ?? ''));
    $penilaian     = $conn->real_escape_string(trim($_POST['penilaian'] ?? ''));
    $rtl           = $conn->real_escape_string(trim($_POST['rtl'] ?? ''));
    $instruksi     = $conn->real_escape_string(trim($_POST['instruksi'] ?? ''));
    $evaluasi      = $conn->real_escape_string(trim($_POST['evaluasi'] ?? ''));
    $nip = !empty($pasien['kd_dokter']) ? $pasien['kd_dokter'] : ($_SESSION['nik'] ?? $_SESSION['user_id'] ?? '');
    if (empty($nip)) {
        $peg_chk = $conn->query("SELECT nik FROM pegawai LIMIT 1");
        if ($peg_chk && $prow = $peg_chk->fetch_assoc()) $nip = $prow['nik'];
    }

    // Status penyelesaian
    $selesaikan = !empty($_POST['selesaikan_periksa']);

    // Upsert pemeriksaan_ralan
    $conn->query("DELETE FROM pemeriksaan_ralan WHERE no_rawat = '$rawat_esc'");
    $sql_soap = "INSERT INTO pemeriksaan_ralan (
        no_rawat, tgl_perawatan, jam_rawat, suhu_tubuh, tensi, nadi, respirasi,
        tinggi, berat, spo2, gcs, kesadaran, keluhan, pemeriksaan, alergi,
        lingkar_perut, rtl, penilaian, instruksi, evaluasi, nip
    ) VALUES (
        '$rawat_esc', '$tgl_perawatan', '$jam_rawat', '$suhu_tubuh', '$tensi', '$nadi', '$respirasi',
        '$tinggi', '$berat', '$spo2', '$gcs', '$kesadaran', '$keluhan', '$pemeriksaan', '$alergi',
        '$lingkar_perut', '$rtl', '$penilaian', '$instruksi', '$evaluasi', '$nip'
    )";

    if ($conn->query($sql_soap)) {
        // Trigger Task 5 (Selesai Pelayanan Dokter)
        BpjsAntreanService::triggerTaskByRawat($rawat_esc, 5);

        // Jika status diselesaikan & pasien BPJS, auto-sync ke PCare
        if ($selesaikan && (str_contains(strtoupper($pasien['nm_penjab'] ?? ''), 'BPJ') || !empty($pasien['no_peserta']))) {
            $tensi_parts = explode('/', str_replace(' ', '', $tensi ?: '120/80'));
            $sistole  = (int)($tensi_parts[0] ?? 120);
            $diastole = (int)($tensi_parts[1] ?? 80);

            $kd_diag = 'Z00.0';
            $d_chk = $conn->query("SELECT kd_penyakit FROM diagnosa_pasien WHERE no_rawat = '$rawat_esc' AND prioritas = 1 LIMIT 1");
            if ($d_chk && $d_chk->num_rows > 0) $kd_diag = $d_chk->fetch_assoc()['kd_penyakit'];

            $payload_pcare = [
                'noKunjungan'   => null,
                'noKartu'       => $pasien['no_peserta'] ?: $pasien['no_ktp'],
                'tglDaftar'     => date('d-m-Y', strtotime($pasien['tgl_registrasi'])),
                'kdPoli'        => '001',
                'keluhan'       => $keluhan ?: 'Pemeriksaan Rawat Jalan',
                'kdSadar'       => '01',
                'sistole'       => $sistole,
                'diastole'      => $diastole,
                'beratBadan'    => (int)($berat ?: 60),
                'tinggiBadan'   => (int)($tinggi ?: 165),
                'respRate'      => (int)($respirasi ?: 20),
                'heartRate'     => (int)($nadi ?: 80),
                'terapi'        => $penilaian ?: 'Terapi medikamentosa rawat jalan',
                'kdStatusPulang'=> '3',
                'tglPulang'     => date('d-m-Y', strtotime($pasien['tgl_registrasi'])),
                'kdDokter'      => '0',
                'kdDiag1'       => $kd_diag,
                'kdDiag2'       => null,
                'kdDiag3'       => null
            ];
            PCareService::tambahKunjungan($payload_pcare);
        }

        if ($selesaikan) {
            $conn->query("UPDATE reg_periksa SET stts = 'Sudah' WHERE no_rawat = '$rawat_esc'");
            set_flash('success', 'Pemeriksaan EMR berhasil disimpan dan status kunjungan diselesaikan.');
            redirect(BASE_URL . 'modules/rekam_medis/index.php');
        } else {
            set_flash('success', 'Data Rekam Medis (SOAP) berhasil disimpan.');
            redirect(BASE_URL . 'modules/rekam_medis/periksa.php?no_rawat=' . urlencode($no_rawat));
        }
    } else {
        set_flash('danger', 'Gagal menyimpan Rekam Medis: ' . $conn->error);
    }
}

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<!-- ─── Patient Banner Header (Compact Slim) ─────────────── -->
<div class="card" style="border-left:4px solid var(--primary-600);background:#ffffff;box-shadow:0 1px 3px rgba(0,0,0,0.04);border-radius:10px;margin-bottom:12px;">
  <div class="card-body" style="padding:8px 16px;">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
      
      <!-- Patient Identity Left -->
      <div style="display:flex;align-items:center;gap:12px;">
        <div style="width:38px;height:38px;border-radius:10px;background:#eff6ff;border:1px solid #bfdbfe;display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:800;color:#2563eb;flex-shrink:0;">
          <?= strtoupper(substr($pasien['nm_pasien'], 0, 1)) ?>
        </div>
        <div>
          <div style="font-size:13.5px;font-weight:800;color:#0f172a;line-height:1.2;"><?= htmlspecialchars($pasien['nm_pasien']) ?></div>
          <div style="font-size:11.5px;color:#64748b;margin-top:3px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            <span><i class="fas fa-id-card text-primary" style="font-size:11px;"></i> No. RM: <strong style="color:#0f172a;"><?= $pasien['no_rkm_medis'] ?></strong></span>
            <span>&bull; <?= icon_jk($pasien['jk']) ?> <?= hitung_umur($pasien['tgl_lahir']) ?> (<?= !empty($pasien['tgl_lahir']) ? tgl_indo($pasien['tgl_lahir']) : '-' ?>)</span>
            <span>&bull; <i class="fas fa-shield-alt" style="color:#0284c7;font-size:11px;"></i> <?= htmlspecialchars($pasien['nm_penjab'] ?? 'Umum') ?></span>
            <span>&bull; <i class="fas fa-hospital" style="color:#4f46e5;font-size:11px;"></i> <?= htmlspecialchars($pasien['nm_poli']) ?></span>
            <span>&bull; <i class="fas fa-user-md" style="color:#059669;font-size:11px;"></i> <?= htmlspecialchars($pasien['nm_dokter'] ?: '-') ?></span>
          </div>
        </div>
      </div>

      <!-- Right Action Badges & Cetak -->
      <div style="display:flex;align-items:center;gap:8px;">
        <span style="font-size:11.5px;font-family:monospace;background:#f8fafc;border:1px solid #e2e8f0;padding:4px 8px;border-radius:6px;color:#475569;font-weight:600;">
          <?= $pasien['no_rawat'] ?>
        </span>
        <?= badge_status($pasien['stts']) ?>
        <a href="<?= BASE_URL ?>modules/rekam_medis/general_consent.php?no_rawat=<?= urlencode($no_rawat) ?>" target="_blank"
           class="btn btn-sm <?= $has_gc ? 'btn-outline-success' : 'btn-outline-warning' ?>" 
           style="padding:4px 10px;font-size:11.5px;display:inline-flex;align-items:center;gap:5px;font-weight:600;" 
           title="<?= $has_gc ? 'General Consent Sudah Ditandatangani' : 'Isi & Tanda Tangani General Consent' ?>">
          <i class="fas fa-file-signature"></i> General Consent <?= $has_gc ? '<i class="fas fa-check-circle text-success" style="font-size:10px;"></i>' : '' ?>
        </a>
        <a href="<?= BASE_URL ?>modules/rekam_medis/cetak_resume.php?no_rawat=<?= urlencode($no_rawat) ?>" target="_blank"
           class="btn btn-sm btn-outline" style="padding:4px 10px;font-size:11.5px;display:inline-flex;align-items:center;gap:5px;border-color:#cbd5e1;color:#334155;" title="Cetak Ringkasan Medis">
          <i class="fas fa-print"></i> Cetak
        </a>
      </div>

    </div>
  </div>
</div>

<!-- ─── Form Container & Layout ──────────────────────────── -->
<form method="POST" action="" id="formEMR">

  <div style="display:grid;grid-template-columns:1fr 340px;gap:16px;align-items:start;">

    <!-- ─── Left Column: Clinical Tabs (SOAP, Diagnosa, Tindakan, E-Resep) ─── -->
    <div>
      
      <!-- ─── Tab Navigation Bar (Animated Modern Header) ─── -->
      <div class="clinical-tab-nav-bar">
        
        <!-- Tab 1: SOAP & Pemeriksaan Fisik -->
        <button type="button" class="btn btn-sm clinical-tab-btn active" id="tab-btn-soap" onclick="switchClinicalTab('soap')">
          <i class="fas fa-stethoscope text-primary"></i>
          <span>1. Pemeriksaan & SOAP</span>
        </button>

        <!-- Tab 2: Diagnosa ICD-10 -->
        <button type="button" class="btn btn-sm clinical-tab-btn btn-secondary" id="tab-btn-diagnosa" onclick="switchClinicalTab('diagnosa')">
          <i class="fas fa-book-medical" style="color:#7c3aed;"></i>
          <span>2. Diagnosa ICD-10</span>
          <span class="badge badge-primary" style="font-size:10px;padding:1px 6px;border-radius:99px;"><?= count($diagnosa_list) ?></span>
        </button>

        <!-- Tab 3: Tindakan & Prosedur Medis -->
        <button type="button" class="btn btn-sm clinical-tab-btn btn-secondary" id="tab-btn-tindakan" onclick="switchClinicalTab('tindakan')">
          <i class="fas fa-hand-holding-medical" style="color:#db2777;"></i>
          <span>3. Tindakan & Prosedur</span>
          <span class="badge badge-warning" style="font-size:10px;padding:1px 6px;border-radius:99px;" id="badgeCountTindakan"><?= count($tindakan_list) ?></span>
        </button>

        <!-- Tab 4: Resep Obat Elektronik -->
        <button type="button" class="btn btn-sm clinical-tab-btn btn-secondary" id="tab-btn-resep" onclick="switchClinicalTab('resep')">
          <i class="fas fa-pills" style="color:#059669;"></i>
          <span>4. Resep Obat (E-Resep)</span>
          <span class="badge badge-success" style="font-size:10px;padding:1px 6px;border-radius:99px;" id="badgeCountResepTotal"><?= count($resep_list) + count($resep_racik_list) ?></span>
        </button>

        <!-- Tab 5: Permintaan & Hasil Lab -->
        <button type="button" class="btn btn-sm clinical-tab-btn btn-secondary" id="tab-btn-lab" onclick="switchClinicalTab('lab')">
          <i class="fas fa-flask-vial" style="color:#0284c7;"></i>
          <span>5. Permintaan Lab</span>
          <span class="badge badge-info" style="font-size:10px;padding:1px 6px;border-radius:99px;" id="badgeCountLab"><?= count($lab_req_list) ?></span>
        </button>

        <!-- Tab 6: Odontogram (Poli Gigi) -->
        <button type="button" class="btn btn-sm clinical-tab-btn btn-secondary" id="tab-btn-odontogram" onclick="switchClinicalTab('odontogram')">
          <i class="fas fa-tooth" style="color:#0284c7;"></i>
          <span>6. Odontogram (Gigi)</span>
          <span class="badge badge-info" style="font-size:10px;padding:1px 6px;border-radius:99px;" id="badgeCountOdontogram">Gigi</span>
        </button>

        <!-- Tab 7: KIA, Kebidanan & KMS Anak -->
        <button type="button" class="btn btn-sm clinical-tab-btn btn-secondary" id="tab-btn-kia" onclick="switchClinicalTab('kia')">
          <i class="fas fa-person-breastfeeding" style="color:#db2777;"></i>
          <span>7. KIA, ANC & KMS Anak</span>
          <span class="badge" style="font-size:10px;padding:1px 6px;border-radius:99px;background:#fce7f3;color:#be185d;" id="badgeCountKia">KIA</span>
        </button>

      </div>

      <!-- ═══════════════════════════════════════════════════════ -->
      <!-- TAB PANE 1: PEMERIKSAAN & SOAP (1 LAYAR COMPACT)        -->
      <!-- ═══════════════════════════════════════════════════════ -->
      <div id="tab-pane-soap" class="clinical-tab-pane" style="display:block;">
        <div class="card" style="border-radius:0 0 10px 10px;border-top:1px solid #e2e8f0;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
          <div class="card-header" style="background:#f8fafc;padding:8px 14px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #e2e8f0;">
            <div class="card-title" style="font-size:12.5px;font-weight:700;display:flex;align-items:center;gap:6px;">
              <i class="fas fa-stethoscope text-primary"></i>
              <span>Form Pemeriksaan Fisik & CPPT (SOAP)</span>
            </div>
            <span id="formSoapStatusBadge" style="font-size:11px;color:#64748b;">
              <i class="fas fa-pencil-alt"></i> Form Baru (Kosong)
            </span>
          </div>

          <div class="card-body" style="padding:10px 14px;display:flex;flex-direction:column;gap:8px;">
            
            <!-- 1. Tanda-Tanda Vital Grid (Compact Bar) -->
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px 10px;">
              <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(105px, 1fr));gap:6px;">
                <div>
                  <label style="font-size:10.5px;color:#64748b;margin-bottom:2px;display:block;font-weight:600;">TD (mmHg)</label>
                  <input type="text" name="tensi" class="form-control form-control-sm" placeholder="120/80" style="font-size:11.5px;height:30px;padding:3px 7px;" value="">
                </div>
                <div>
                  <label style="font-size:10.5px;color:#64748b;margin-bottom:2px;display:block;font-weight:600;">Nadi (x/m)</label>
                  <input type="number" name="nadi" class="form-control form-control-sm" placeholder="80" style="font-size:11.5px;height:30px;padding:3px 7px;" value="">
                </div>
                <div>
                  <label style="font-size:10.5px;color:#64748b;margin-bottom:2px;display:block;font-weight:600;">Suhu (°C)</label>
                  <input type="text" name="suhu_tubuh" class="form-control form-control-sm" placeholder="36.5" style="font-size:11.5px;height:30px;padding:3px 7px;" value="">
                </div>
                <div>
                  <label style="font-size:10.5px;color:#64748b;margin-bottom:2px;display:block;font-weight:600;">Resp (x/m)</label>
                  <input type="number" name="respirasi" class="form-control form-control-sm" placeholder="20" style="font-size:11.5px;height:30px;padding:3px 7px;" value="">
                </div>
                <div>
                  <label style="font-size:10.5px;color:#64748b;margin-bottom:2px;display:block;font-weight:600;">SpO2 (%)</label>
                  <input type="number" name="spo2" class="form-control form-control-sm" placeholder="98" style="font-size:11.5px;height:30px;padding:3px 7px;" value="">
                </div>
                <div>
                  <label style="font-size:10.5px;color:#64748b;margin-bottom:2px;display:block;font-weight:600;">TB (cm)</label>
                  <input type="number" name="tinggi" id="inputTb" class="form-control form-control-sm" placeholder="165" style="font-size:11.5px;height:30px;padding:3px 7px;" value="">
                </div>
                <div>
                  <label style="font-size:10.5px;color:#64748b;margin-bottom:2px;display:block;font-weight:600;">BB (kg)</label>
                  <input type="number" step="0.1" name="berat" id="inputBb" class="form-control form-control-sm" placeholder="60" style="font-size:11.5px;height:30px;padding:3px 7px;" value="">
                </div>
                <div>
                  <label style="font-size:10.5px;color:#64748b;margin-bottom:2px;display:block;font-weight:600;">IMT (kg/m²)</label>
                  <input type="text" id="outputBmi" class="form-control form-control-sm" readonly placeholder="Oto" style="background:#e2e8f0;font-weight:700;font-size:11.5px;height:30px;padding:3px 7px;">
                </div>
              </div>

              <!-- Baris Pendukung: Kesadaran, GCS, Alergi, Lingkar Perut -->
              <div style="display:grid;grid-template-columns:130px 100px 1fr 110px;gap:6px;margin-top:6px;">
                <div>
                  <label style="font-size:10.5px;color:#64748b;margin-bottom:2px;display:block;font-weight:600;">Kesadaran</label>
                  <select name="kesadaran" class="form-control form-control-sm" style="font-size:11.5px;height:30px;padding:3px 7px;">
                    <?php foreach (['Compos Mentis','Somnolence','Sopor','Coma'] as $ks): ?>
                      <option value="<?= $ks ?>"><?= $ks ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div>
                  <label style="font-size:10.5px;color:#64748b;margin-bottom:2px;display:block;font-weight:600;">GCS (E,V,M)</label>
                  <input type="text" name="gcs" class="form-control form-control-sm" placeholder="E4V5M6" style="font-size:11.5px;height:30px;padding:3px 7px;" value="">
                </div>
                <div>
                  <label style="font-size:10.5px;color:#64748b;margin-bottom:2px;display:block;font-weight:600;">Riwayat Alergi</label>
                  <input type="text" name="alergi" class="form-control form-control-sm" placeholder="cth: Amoxicillin, Makanan laut (jika ada)" style="font-size:11.5px;height:30px;padding:3px 7px;" value="">
                </div>
                <div>
                  <label style="font-size:10.5px;color:#64748b;margin-bottom:2px;display:block;font-weight:600;">L. Perut (cm)</label>
                  <input type="text" name="lingkar_perut" class="form-control form-control-sm" placeholder="cth: 80" style="font-size:11.5px;height:30px;padding:3px 7px;" value="">
                </div>
              </div>
            </div>

            <!-- 2. Catatan SOAP 2x2 Grid (Side by Side!) -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
              <!-- Left Column: S & O -->
              <div style="display:flex;flex-direction:column;gap:6px;">
                <div>
                  <label style="font-size:11px;font-weight:700;color:#e11d48;margin-bottom:2px;display:flex;align-items:center;gap:4px;">
                    <span style="background:#fee2e2;color:#b91c1c;padding:1px 5px;border-radius:3px;">[S] Subjektif</span> Keluhan Utama & Anamnesis <span class="text-danger">*</span>
                  </label>
                  <textarea name="keluhan" class="form-control" rows="3" placeholder="Keluhan utama, riwayat penyakit, onset, frekuensi..." style="font-size:12px;padding:6px 8px;height:70px;resize:vertical;"></textarea>
                </div>
                <div>
                  <label style="font-size:11px;font-weight:700;color:#0284c7;margin-bottom:2px;display:flex;align-items:center;gap:4px;">
                    <span style="background:#e0f2fe;color:#0369a1;padding:1px 5px;border-radius:3px;">[O] Objektif</span> Pemeriksaan Fisik & Penunjang
                  </label>
                  <textarea name="pemeriksaan" class="form-control" rows="3" placeholder="Hasil inspeksi, palpasi, auskultasi, status lokalis..." style="font-size:12px;padding:6px 8px;height:70px;resize:vertical;"></textarea>
                </div>
              </div>

              <!-- Right Column: A & P -->
              <div style="display:flex;flex-direction:column;gap:6px;">
                <div>
                  <label style="font-size:11px;font-weight:700;color:#059669;margin-bottom:2px;display:flex;align-items:center;gap:4px;">
                    <span style="background:#d1fae5;color:#047857;padding:1px 5px;border-radius:3px;">[A] Asesmen</span> Analisis / Penilaian Klinis
                  </label>
                  <textarea name="penilaian" class="form-control" rows="3" placeholder="Diagnosa kerja, diagnosa banding, masalah klinis..." style="font-size:12px;padding:6px 8px;height:70px;resize:vertical;"></textarea>
                </div>
                <div>
                  <label style="font-size:11px;font-weight:700;color:#7c3aed;margin-bottom:2px;display:flex;align-items:center;gap:4px;">
                    <span style="background:#ede9fe;color:#6d28d9;padding:1px 5px;border-radius:3px;">[P] Plan</span> Rencana Tindak Lanjut & Terapi
                  </label>
                  <textarea name="rtl" class="form-control" rows="3" placeholder="Rencana pengobatan, terapi non obat, edukasi, diet, kontrol..." style="font-size:12px;padding:6px 8px;height:70px;resize:vertical;"></textarea>
                </div>
              </div>
            </div>

            <!-- 3. Instruksi & Evaluasi Row -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
              <div>
                <label style="font-size:10.5px;color:#64748b;margin-bottom:2px;display:block;font-weight:600;">Instruksi Medis / Petugas</label>
                <input type="text" name="instruksi" class="form-control form-control-sm" placeholder="Instruksi khusus kepada perawat atau farmasi..." style="font-size:11.5px;height:28px;padding:3px 7px;" value="">
              </div>
              <div>
                <label style="font-size:10.5px;color:#64748b;margin-bottom:2px;display:block;font-weight:600;">Evaluasi Hasil Perawatan</label>
                <input type="text" name="evaluasi" class="form-control form-control-sm" placeholder="Kondisi akhir saat selesai pemeriksaan..." style="font-size:11.5px;height:28px;padding:3px 7px;" value="">
              </div>
            </div>

          </div>
        </div>
      </div>

      <!-- ═══════════════════════════════════════════════════════ -->
      <!-- TAB PANE 2: DIAGNOSA ICD-10                             -->
      <!-- ═══════════════════════════════════════════════════════ -->
      <div id="tab-pane-diagnosa" class="clinical-tab-pane" style="display:none;">
        <div class="card" style="border-radius:0 0 10px 10px;border-top:1px solid #e2e8f0;">
          <div class="card-header" style="background:#f8fafc;padding:12px 18px;display:flex;justify-content:space-between;align-items:center;">
            <div class="card-title" style="font-size:13px;display:flex;align-items:center;gap:6px;">
              <i class="fas fa-book-medical" style="color:#7c3aed;"></i>
              <span>Klasifikasi Diagnosa Penyakit (ICD-10)</span>
            </div>
            <span style="font-size:12px;color:#64748b;">Standar Kemenkes & BPJS</span>
          </div>
          <div class="card-body" style="padding:18px 20px;">
            
            <!-- Box Tambah Diagnosa -->
            <div style="background:#f8fafc;padding:14px;border-radius:8px;border:1px solid #e2e8f0;margin-bottom:16px;">
              <div style="font-size:12px;font-weight:700;color:#0f172a;margin-bottom:8px;">
                <i class="fas fa-plus-circle text-primary" style="margin-right:4px;"></i> Tambah Diagnosa Pasien:
              </div>
              
              <div style="display:flex;flex-direction:column;gap:8px;">
                <div class="search-bar" style="background:#fff;border:1px solid #cbd5e1;">
                  <i class="fas fa-search"></i>
                  <input type="text" id="inputIcd10" placeholder="Ketik Kode ICD-10 atau Nama Penyakit (contoh: J00, Febris, ISPA, Gastritis)..." autocomplete="off" style="font-size:13px;">
                </div>
                <div id="icd10Results" style="display:none;position:relative;z-index:40;background:#fff;border:1px solid var(--gray-200);border-radius:6px;box-shadow:var(--shadow-md);max-height:220px;overflow-y:auto;"></div>

                <input type="hidden" id="selectedKdPenyakit">

                <div style="display:grid;grid-template-columns:140px 140px 1fr auto;gap:8px;align-items:center;">
                  <div>
                    <label style="font-size:11px;color:#64748b;margin-bottom:2px;display:block;">Prioritas</label>
                    <select id="selectPrioritas" class="form-control" style="font-size:12.5px;">
                      <option value="1">1 (Diagnosa Utama)</option>
                      <option value="2">2 (Sekunder / Komplikasi)</option>
                      <option value="3">3 (Sekunder)</option>
                      <option value="4">4 (Sekunder)</option>
                    </select>
                  </div>
                  <div>
                    <label style="font-size:11px;color:#64748b;margin-bottom:2px;display:block;">Status Kasus</label>
                    <select id="selectStatusPenyakit" class="form-control" style="font-size:12.5px;">
                      <option value="Baru">Kasus Baru</option>
                      <option value="Lama">Kasus Lama</option>
                    </select>
                  </div>
                  <div></div>
                  <div style="align-self:end;">
                    <button type="button" class="btn btn-primary" onclick="tambahDiagnosaPasien()" title="Tambah Diagnosa" style="padding:7px 14px;font-size:12.5px;display:inline-flex;align-items:center;gap:6px;">
                      <i class="fas fa-plus"></i> Tambah Diagnosa
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Daftar Diagnosa Pasien -->
            <div>
              <label style="font-size:12.5px;font-weight:700;color:#0f172a;margin-bottom:8px;display:block;">
                Daftar Diagnosa Yang Ditetapkan:
              </label>
              <div id="boxDiagnosaList">
                <?php if (empty($diagnosa_list)): ?>
                  <div style="font-size:12.5px;color:var(--gray-400);text-align:center;padding:24px;border:1px dashed #cbd5e1;border-radius:8px;background:#f8fafc;">
                    <i class="fas fa-file-medical" style="font-size:24px;color:#cbd5e1;display:block;margin-bottom:6px;"></i>
                    Belum ada diagnosa ICD-10 yang diinput untuk kunjungan ini.
                  </div>
                <?php else: ?>
                  <div style="display:flex;flex-direction:column;gap:8px;">
                    <?php foreach ($diagnosa_list as $d): ?>
                      <div style="display:flex;align-items:center;justify-content:space-between;background:#ffffff;padding:10px 14px;border-radius:8px;border:1px solid #e2e8f0;box-shadow:0 1px 2px rgba(0,0,0,0.02);">
                        <div>
                          <div style="font-size:13px;font-weight:700;color:#0f172a;">
                            <span class="badge badge-<?= $d['prioritas']==1?'primary':'secondary' ?>" style="font-size:10.5px;padding:2px 6px;margin-right:6px;">
                              <?= $d['prioritas']==1?'Utama (P1)':'Sekunder (P'.$d['prioritas'].')' ?>
                            </span>
                            <code><?= htmlspecialchars($d['kd_penyakit']) ?></code> &mdash; <?= htmlspecialchars($d['nm_penyakit']) ?>
                          </div>
                          <div style="font-size:11px;color:#64748b;margin-top:2px;">
                            Status Kasus: <strong><?= htmlspecialchars($d['status_penyakit'] ?: 'Baru') ?></strong> &bull;
                            Pelayanan: <strong><?= htmlspecialchars($d['status'] ?: 'Ralan') ?></strong>
                          </div>
                        </div>
                        <button type="button" onclick="hapusDiagnosaPasien('<?= $d['kd_penyakit'] ?>')" class="btn btn-sm btn-outline-danger" title="Hapus Diagnosa" style="padding:3px 8px;font-size:11px;">
                          <i class="fas fa-trash-alt"></i> Hapus
                        </button>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>

          </div>
        </div>
      </div>

      <!-- ═══════════════════════════════════════════════════════ -->
      <!-- TAB PANE 3: TINDAKAN & PROSEDUR RAWAT JALAN             -->
      <!-- ═══════════════════════════════════════════════════════ -->
      <div id="tab-pane-tindakan" class="clinical-tab-pane" style="display:none;">
        <div class="card" style="border-radius:0 0 10px 10px;border-top:1px solid #e2e8f0;">
          <div class="card-header" style="background:#f8fafc;padding:12px 18px;display:flex;justify-content:space-between;align-items:center;">
            <div class="card-title" style="font-size:13px;display:flex;align-items:center;gap:6px;">
              <i class="fas fa-hand-holding-medical" style="color:#db2777;"></i>
              <span>Input Tindakan & Prosedur Pelayanan Ralan</span>
            </div>
            <span style="font-size:12px;color:#64748b;">
              Total Biaya Tindakan: <strong style="color:#059669;" id="badgeTotalBiayaTindakan"><?= rupiah($total_biaya_tindakan) ?></strong>
            </span>
          </div>
          <div class="card-body" style="padding:18px 20px;">
            
            <!-- Box Tambah Tindakan -->
            <div style="background:#f8fafc;padding:14px;border-radius:8px;border:1px solid #e2e8f0;margin-bottom:16px;">
              <div style="font-size:12px;font-weight:700;color:#0f172a;margin-bottom:8px;">
                <i class="fas fa-plus-circle" style="color:#db2777;margin-right:4px;"></i> Tambah Tindakan Medis Pasien:
              </div>

              <div style="display:flex;flex-direction:column;gap:10px;">
                
                <!-- Autocomplete Tindakan Search -->
                <div style="position:relative;">
                  <div class="search-bar" style="background:#fff;border:1px solid #cbd5e1;">
                    <i class="fas fa-search"></i>
                    <input type="text" id="inputTindakan" placeholder="Ketik nama atau kode tindakan (contoh: Pemeriksaan, Injeksi, Nebulizer, Jahit Luka, Ganti Perban)..." autocomplete="off" style="font-size:13px;">
                  </div>
                  <div id="tindakanResults" style="display:none;position:absolute;top:100%;left:0;right:0;z-index:99;background:#fff;border:1px solid #cbd5e1;border-radius:6px;box-shadow:0 8px 20px rgba(0,0,0,0.12);max-height:220px;overflow-y:auto;margin-top:2px;"></div>
                </div>

                <input type="hidden" id="selectedKodeTindakan">
                <input type="hidden" id="selectedTarifDr" value="0">
                <input type="hidden" id="selectedTarifPr" value="0">
                <input type="hidden" id="selectedTarifDrPr" value="0">

                <div style="display:grid;grid-template-columns:160px 1fr 1fr auto;gap:8px;align-items:center;">
                  <div>
                    <label style="font-size:11px;color:#64748b;margin-bottom:2px;display:block;">Pelaksana Tindakan</label>
                    <select id="selectPelaksana" class="form-control" style="font-size:12.5px;" onchange="onPelaksanaChange(this.value)">
                      <option value="dr">Dokter Saja</option>
                      <option value="pr">Petugas / Perawat</option>
                      <option value="drpr">Dokter & Petugas</option>
                    </select>
                  </div>

                  <div id="colDokter">
                    <label style="font-size:11px;color:#64748b;margin-bottom:2px;display:block;">Dokter</label>
                    <select id="selectTindakanDokter" class="form-control" style="font-size:12.5px;">
                      <?php foreach ($dokter_select as $d): ?>
                        <option value="<?= $d['kd_dokter'] ?>" <?= $d['kd_dokter']===$pasien['kd_dokter']?'selected':'' ?>>
                          <?= htmlspecialchars($d['nm_dokter']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div id="colPetugas" style="display:none;">
                    <label style="font-size:11px;color:#64748b;margin-bottom:2px;display:block;">Petugas / Perawat</label>
                    <select id="selectTindakanPetugas" class="form-control" style="font-size:12.5px;">
                      <option value="">— Pilih Petugas —</option>
                      <?php foreach ($petugas_select as $pg): ?>
                        <option value="<?= $pg['nip'] ?>"><?= htmlspecialchars($pg['nama']) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div style="align-self:end;">
                    <button type="button" class="btn btn-primary" onclick="simpanTindakanPasien()" style="padding:7px 14px;font-size:12.5px;display:inline-flex;align-items:center;gap:6px;background:#db2777;border-color:#be185d;">
                      <i class="fas fa-plus"></i> Tambah Tindakan
                    </button>
                  </div>
                </div>

              </div>
            </div>

            <!-- Daftar Tindakan Pasien -->
            <div>
              <label style="font-size:12.5px;font-weight:700;color:#0f172a;margin-bottom:8px;display:block;">
                Daftar Tindakan / Prosedur Yang Diberikan Pada Pasien:
              </label>

              <div id="boxTindakanList">
                <?php if (empty($tindakan_list)): ?>
                  <div style="font-size:12.5px;color:var(--gray-400);text-align:center;padding:24px;border:1px dashed #cbd5e1;border-radius:8px;background:#f8fafc;">
                    <i class="fas fa-hand-holding-medical" style="font-size:24px;color:#cbd5e1;display:block;margin-bottom:6px;"></i>
                    Belum ada tindakan medis yang diinput untuk kunjungan ini.
                  </div>
                <?php else: ?>
                  <div style="display:flex;flex-direction:column;gap:8px;">
                    <?php foreach ($tindakan_list as $t): ?>
                      <div style="display:flex;align-items:center;justify-content:space-between;background:#ffffff;padding:10px 14px;border-radius:8px;border:1px solid #e2e8f0;box-shadow:0 1px 2px rgba(0,0,0,0.02);">
                        <div>
                          <div style="font-size:13px;font-weight:700;color:#0f172a;">
                            <?= htmlspecialchars($t['nm_perawatan']) ?>
                            <span style="font-size:11.5px;font-weight:600;color:#db2777;background:#fdf2f8;padding:2px 6px;border-radius:4px;margin-left:4px;">
                              <?= htmlspecialchars($t['pelaksana']) ?>
                            </span>
                          </div>
                          <div style="font-size:11.5px;color:#64748b;margin-top:2px;">
                            <i class="fas fa-user-md"></i> Pelaksana: <strong><?= htmlspecialchars($t['nm_pelaksana']) ?></strong> &bull;
                            <i class="fas fa-clock"></i> <?= substr($t['jam_rawat'],0,5) ?> WIB
                          </div>
                        </div>
                        <div style="display:flex;align-items:center;gap:12px;">
                          <strong style="font-family:monospace;font-size:13px;color:#059669;"><?= rupiah((float)$t['biaya_rawat']) ?></strong>
                          <button type="button" onclick="hapusTindakanPasien('<?= htmlspecialchars($t['kd_jenis_prw']) ?>', '<?= $t['pelaksana_type'] ?>', '<?= $t['tgl_perawatan'] ?>', '<?= $t['jam_rawat'] ?>')" class="btn btn-sm btn-outline-danger" title="Hapus Tindakan" style="padding:3px 8px;font-size:11px;">
                            <i class="fas fa-trash-alt"></i> Hapus
                          </button>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>

          </div>
        </div>
      </div>

      <!-- ═══════════════════════════════════════════════════════ -->
      <!-- TAB PANE 4: RESEP OBAT ELEKTRONIK (E-RESEP)            -->
      <!-- ═══════════════════════════════════════════════════════ -->
      <div id="tab-pane-resep" class="clinical-tab-pane" style="display:none;">
        <div class="card" style="border-radius:0 0 10px 10px;border-top:1px solid #e2e8f0;">
          <div class="card-header" style="background:#f8fafc;padding:12px 18px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
            <div class="card-title" style="font-size:13px;display:flex;align-items:center;gap:6px;">
              <i class="fas fa-pills" style="color:#059669;"></i>
              <span>Peresepan Obat Pasien (E-Prescription)</span>
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
              <button type="button" class="btn btn-sm btn-outline-primary" onclick="openModalCopyResep()" style="font-size:12px;display:inline-flex;align-items:center;gap:6px;font-weight:600;padding:5px 12px;border-radius:6px;border-color:#0284c7;color:#0284c7;background:#f0f9ff;">
                <i class="fas fa-copy" style="color:#0284c7;"></i> Copy Resep Terdahulu
              </button>
              <span style="font-size:11.5px;color:#64748b;">Terkirim langsung ke Farmasi</span>
            </div>
          </div>
          <div class="card-body" style="padding:18px 20px;">
            
            <!-- ─── Sub-Tab Switcher: Non-Racik vs Racikan ──────── -->
            <div style="display:flex;gap:8px;margin-bottom:16px;border-bottom:1px solid #e2e8f0;padding-bottom:10px;">
              <button type="button" class="btn btn-sm btn-primary" id="btnTabNonRacik" onclick="switchResepSubTab('nonracik')" style="border-radius:6px;font-weight:700;font-size:12px;padding:6px 14px;display:inline-flex;align-items:center;gap:6px;">
                <i class="fas fa-pills"></i> 1. Obat Non-Racik (Paten)
                <span class="badge badge-light" style="margin-left:4px;font-size:10.5px;"><?= count($resep_list) ?></span>
              </button>
              <button type="button" class="btn btn-sm btn-outline-primary" id="btnTabRacik" onclick="switchResepSubTab('racik')" style="border-radius:6px;font-weight:700;font-size:12px;padding:6px 14px;display:inline-flex;align-items:center;gap:6px;">
                <i class="fas fa-mortar-pestle"></i> 2. Obat Racikan (Puyer / Kapsul / Salep)
                <span class="badge badge-light" style="margin-left:4px;font-size:10.5px;"><?= count($resep_racik_list) ?></span>
              </button>
            </div>

            <!-- ═══════════════════════════════════════════════════════ -->
            <!-- SUB-PANE 1: OBAT NON-RACIK (PATEN)                     -->
            <!-- ═══════════════════════════════════════════════════════ -->
            <div id="subpane-nonracik" style="display:block;">
              <!-- Box Tambah Obat Resep -->
              <div style="background:#f8fafc;padding:14px;border-radius:8px;border:1px solid #e2e8f0;margin-bottom:16px;">
                <div style="font-size:12px;font-weight:700;color:#0f172a;margin-bottom:8px;">
                  <i class="fas fa-plus-circle" style="color:#059669;margin-right:4px;"></i> Tambah Obat Non-Racik (Paten) Ke Resep:
                </div>
                
                <div style="display:flex;flex-direction:column;gap:8px;">
                  <div class="search-bar" style="background:#fff;border:1px solid #cbd5e1;">
                    <i class="fas fa-search"></i>
                    <input type="text" id="inputObat" placeholder="Ketik nama obat atau kode barang (contoh: Paracetamol, Amoxicillin, Antasida)..." autocomplete="off" style="font-size:13px;">
                  </div>
                  <div id="obatResults" style="display:none;position:relative;z-index:40;background:#fff;border:1px solid var(--gray-200);border-radius:6px;box-shadow:var(--shadow-md);max-height:220px;overflow-y:auto;"></div>

                  <input type="hidden" id="selectedKodeBrng">

                  <div style="display:grid;grid-template-columns:100px 1fr auto;gap:8px;align-items:center;">
                    <div>
                      <label style="font-size:11px;color:#64748b;margin-bottom:2px;display:block;">Jumlah</label>
                      <input type="number" id="inputJmlObat" class="form-control" placeholder="Jml" min="1" value="10" style="font-size:12.5px;">
                    </div>
                    <div>
                      <label style="font-size:11px;color:#64748b;margin-bottom:2px;display:block;">Aturan Pakai / Dosis</label>
                      <input type="text" id="inputAturanPakai" class="form-control" placeholder="Contoh: 3 x 1 Tablet sesudah makan" style="font-size:12.5px;">
                    </div>
                    <div style="align-self:end;">
                      <button type="button" class="btn btn-success" onclick="tambahObatResep()" title="Tambah ke Resep" style="padding:7px 14px;font-size:12.5px;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-plus"></i> Tambah Obat
                      </button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Daftar Item Resep Non-Racik Pasien -->
              <div>
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                  <label style="font-size:12.5px;font-weight:700;color:#0f172a;margin:0;">
                    Daftar Obat Non-Racik Yang Diresepkan:
                  </label>
                  <?php if (!empty($resep_list)): ?>
                    <span style="font-size:11px;color:#64748b;">(<?= count($resep_list) ?> jenis obat paten aktif)</span>
                  <?php endif; ?>
                </div>
                <div id="boxResepList">
                  <?php if (empty($resep_list)): ?>
                    <div style="font-size:12.5px;color:var(--gray-400);text-align:center;padding:24px;border:1px dashed #cbd5e1;border-radius:8px;background:#f8fafc;">
                      <i class="fas fa-prescription-bottle-alt" style="font-size:24px;color:#cbd5e1;display:block;margin-bottom:6px;"></i>
                      Belum ada obat non-racik yang diresepkan untuk kunjungan ini.
                    </div>
                  <?php else: ?>
                    <div style="display:flex;flex-direction:column;gap:8px;">
                      <?php foreach ($resep_list as $r): ?>
                        <div id="row-resep-<?= htmlspecialchars($r['kode_brng']) ?>" style="display:flex;align-items:center;justify-content:space-between;background:#ffffff;padding:10px 14px;border-radius:8px;border:1px solid #e2e8f0;box-shadow:0 1px 2px rgba(0,0,0,0.02);transition:all 0.2s ease;">
                          <div>
                            <div style="font-size:13px;font-weight:700;color:#0f172a;">
                              <span class="nama-brng-label"><?= htmlspecialchars($r['nama_brng']) ?></span>
                              <span class="jml-satuan-label" style="font-size:12px;font-weight:600;color:#0369a1;background:#e0f2fe;padding:1px 7px;border-radius:4px;margin-left:4px;">
                                <?= $r['jml'] ?> <?= htmlspecialchars($r['satuan']?:'Item') ?>
                              </span>
                            </div>
                            <div style="font-size:11.5px;color:#475569;margin-top:3px;">
                              <i class="fas fa-clock" style="color:#0d9488;margin-right:3px;"></i> Aturan Pakai: <strong class="aturan-pakai-label" style="color:#0f766e;"><?= htmlspecialchars($r['aturan_pakai'] ?: '-') ?></strong>
                            </div>
                          </div>
                          <div style="display:flex;gap:6px;">
                            <button type="button" onclick="openEditItemResep('<?= $r['no_resep'] ?>', '<?= $r['kode_brng'] ?>', '<?= htmlspecialchars(addslashes($r['nama_brng'])) ?>', <?= $r['jml'] ?>, '<?= htmlspecialchars(addslashes($r['aturan_pakai'] ?: '')) ?>', '<?= htmlspecialchars($r['satuan']?:'Item') ?>')" class="btn btn-sm btn-outline-primary" title="Edit Jumlah / Aturan Pakai" style="padding:4px 9px;font-size:11px;display:inline-flex;align-items:center;gap:4px;">
                              <i class="fas fa-edit"></i> Edit
                            </button>
                            <button type="button" onclick="hapusItemResep('<?= $r['no_resep'] ?>', '<?= $r['kode_brng'] ?>')" class="btn btn-sm btn-outline-danger" title="Hapus Obat" style="padding:4px 9px;font-size:11px;display:inline-flex;align-items:center;gap:4px;">
                              <i class="fas fa-trash-alt"></i> Hapus
                            </button>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <!-- ═══════════════════════════════════════════════════════ -->
            <!-- SUB-PANE 2: OBAT RACIKAN (PUYER / KAPSUL / SALEP)       -->
            <!-- ═══════════════════════════════════════════════════════ -->
            <div id="subpane-racik" style="display:none;">
              <!-- Form Tambah/Edit Racikan -->
              <div style="background:#f8fafc;padding:16px;border-radius:10px;border:1px solid #e2e8f0;margin-bottom:16px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                  <div style="font-size:13px;font-weight:700;color:#0f172a;display:flex;align-items:center;gap:6px;" id="racik_form_title">
                    <i class="fas fa-mortar-pestle" style="color:#0284c7;"></i> Form Racik Obat Baru (Puyer / Kapsul / Salep / Sirup)
                  </div>
                  <button type="button" class="btn btn-xs btn-outline-secondary" onclick="resetFormRacikan()" style="font-size:11px;padding:3px 8px;">
                    <i class="fas fa-undo"></i> Reset Form
                  </button>
                </div>

                <input type="hidden" id="racik_edit_no_racik" value="">

                <!-- Row 1: Header Info Racikan -->
                <div style="display:grid;grid-template-columns:1.5fr 1fr 1fr 1.5fr;gap:10px;margin-bottom:10px;">
                  <div>
                    <label style="font-size:11.5px;font-weight:700;color:#334155;margin-bottom:3px;display:block;">Nama Racikan <span class="text-danger">*</span></label>
                    <input type="text" id="racik_nama" class="form-control form-control-sm" placeholder="Contoh: Puyer Batuk Pilek / Kapsul Flu" style="font-size:12px;">
                  </div>

                  <div>
                    <label style="font-size:11.5px;font-weight:700;color:#334155;margin-bottom:3px;display:block;">Metode Racik <span class="text-danger">*</span></label>
                    <select id="racik_metode" class="form-control form-control-sm" style="font-size:12px;">
                      <?php foreach ($metode_racik_list as $mr): ?>
                        <option value="<?= $mr['kd_racik'] ?>"><?= htmlspecialchars($mr['nm_racik']) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div>
                    <label style="font-size:11.5px;font-weight:700;color:#334155;margin-bottom:3px;display:block;">Jml Kemasan / Bks <span class="text-danger">*</span></label>
                    <input type="number" id="racik_jml_dr" class="form-control form-control-sm" min="1" value="10" style="font-size:12px;font-weight:700;text-align:center;" oninput="recalcAllRacikBahan()">
                  </div>

                  <div>
                    <label style="font-size:11.5px;font-weight:700;color:#334155;margin-bottom:3px;display:block;">Aturan Pakai / Signa <span class="text-danger">*</span></label>
                    <input type="text" id="racik_aturan" class="form-control form-control-sm" placeholder="Contoh: 3 x 1 bungkus sesudah makan" style="font-size:12px;">
                  </div>
                </div>

                <!-- Row 1b: Keterangan Racikan -->
                <div style="margin-bottom:12px;">
                  <label style="font-size:11px;color:#64748b;margin-bottom:2px;display:block;">Keterangan Tambahan (Opsional)</label>
                  <input type="text" id="racik_keterangan" class="form-control form-control-sm" placeholder="Contoh: Bila demam / sesak / simpan di tempat kering" style="font-size:12px;">
                </div>

                <!-- Row 2: Tambah & Tabel Bahan Campuran Racikan -->
                <div style="background:#ffffff;border:1px solid #cbd5e1;border-radius:10px;margin-bottom:14px;box-shadow:0 1px 3px rgba(0,0,0,0.04);overflow:visible;">
                  <div style="padding:10px 14px;background:#f8fafc;border-bottom:1px solid #e2e8f0;font-size:12.5px;font-weight:700;color:#0f172a;display:flex;align-items:center;gap:6px;border-radius:10px 10px 0 0;">
                    <i class="fas fa-pills" style="color:#0284c7;"></i> Komposisi Bahan Campuran Obat
                  </div>

                  <!-- Bar Pencarian & Input Dosis Bahan (Lega & Tidak Terpotong) -->
                  <div style="padding:12px 14px;background:#f0fdf4;border-bottom:1px solid #bbf7d0;position:relative;">
                    <div style="font-size:11.5px;font-weight:700;color:#166534;margin-bottom:6px;display:flex;align-items:center;gap:5px;">
                      <i class="fas fa-plus-circle" style="color:#16a34a;"></i> Tambah Bahan Campuran Ke Racikan Ini:
                    </div>
                    
                    <div style="display:grid;grid-template-columns:1fr 190px auto;gap:10px;align-items:flex-end;">
                      <!-- Cari Obat Bahan -->
                      <div style="position:relative;">
                        <label style="font-size:11px;font-weight:600;color:#334155;margin-bottom:2px;display:block;">Cari Nama Obat Bahan</label>
                        <div class="search-bar" style="background:#fff;border:1.5px solid #86efac;border-radius:6px;padding:0 10px;height:38px;display:flex;align-items:center;gap:8px;">
                          <i class="fas fa-search" style="color:#16a34a;font-size:13px;"></i>
                          <input type="text" id="inputBahanSearch" placeholder="Ketik nama obat (contoh: Paracetamol, CTM, Dexamethasone)..." autocomplete="off" style="font-size:12.5px;border:none;outline:none;width:100%;" oninput="searchBahanObatPicker(this.value)">
                        </div>
                        <input type="hidden" id="selectedBahanKode">
                        <input type="hidden" id="selectedBahanNama">
                        <input type="hidden" id="selectedBahanSatuan">
                        <input type="hidden" id="selectedBahanStok">

                        <!-- Dropdown hasil pencarian melayang bebas & jelas -->
                        <div id="bahanSearchResults" style="display:none;position:absolute;top:100%;left:0;right:0;z-index:9999;background:#fff;border:1px solid #cbd5e1;border-radius:8px;box-shadow:0 12px 28px rgba(0,0,0,0.18);max-height:220px;overflow-y:auto;margin-top:4px;"></div>
                      </div>

                      <!-- Dosis per kemasan -->
                      <div>
                        <label style="font-size:11px;font-weight:600;color:#334155;margin-bottom:2px;display:block;">Dosis / Kemasan (Bks)</label>
                        <div style="display:flex;align-items:center;gap:4px;background:#fff;padding:2px 8px;border:1px solid #cbd5e1;border-radius:6px;height:38px;">
                          <input type="number" id="inputBahanP1" step="0.1" min="0.1" value="1" class="form-control form-control-sm" style="width:52px;text-align:center;font-size:12px;font-weight:700;padding:3px;" title="Pembilang (contoh: 1 atau 0.5)">
                          <span style="font-weight:700;color:#64748b;">/</span>
                          <input type="number" id="inputBahanP2" min="1" value="1" class="form-control form-control-sm" style="width:46px;text-align:center;font-size:12px;font-weight:700;padding:3px;" title="Penyebut (contoh: 1)">
                          <span style="font-size:11px;color:#64748b;font-weight:600;">Tab</span>
                        </div>
                      </div>

                      <!-- Tombol Tambah -->
                      <div>
                        <button type="button" class="btn btn-success" onclick="tambahBahanKeTabel()" style="height:38px;padding:0 16px;font-size:12px;font-weight:700;display:inline-flex;align-items:center;gap:6px;border-radius:6px;background:#16a34a;border:none;">
                          <i class="fas fa-plus"></i> Tambah Bahan
                        </button>
                      </div>
                    </div>
                  </div>

                  <!-- Tabel Komposisi Bahan Yang Ditambahkan -->
                  <div style="overflow-x:auto;">
                    <table class="table table-bordered mb-0" style="font-size:12px;margin:0;" id="table_racik_bahan">
                      <thead style="background:#f8fafc;color:#475569;font-weight:700;">
                        <tr>
                          <th style="width:35px;text-align:center;">#</th>
                          <th>Nama Obat Bahan Campuran</th>
                          <th style="width:145px;text-align:center;">Dosis / Kemasan</th>
                          <th style="width:145px;text-align:center;">Total Diambil</th>
                          <th style="width:90px;text-align:center;">Stok</th>
                          <th style="width:50px;text-align:center;">Aksi</th>
                        </tr>
                      </thead>
                      <tbody id="tbody_racik_bahan">
                        <tr id="empty_racik_bahan_row">
                          <td colspan="6" style="text-align:center;padding:22px;color:#94a3b8;background:#fafafa;">
                            <i class="fas fa-mortar-pestle" style="font-size:22px;display:block;margin-bottom:6px;color:#cbd5e1;"></i>
                            Belum ada bahan obat dalam racikan ini. Cari nama obat di atas lalu klik <strong>+ Tambah Bahan</strong>.
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:8px;">
                  <button type="button" class="btn btn-sm btn-primary" onclick="simpanResepRacikan()" style="padding:7px 18px;font-weight:700;font-size:12.5px;background:#0284c7;border:none;display:inline-flex;align-items:center;gap:6px;">
                    <i class="fas fa-save"></i> <span id="btn_simpan_racik_text">Simpan Resep Racikan</span>
                  </button>
                </div>
              </div>

              <!-- Daftar Resep Racikan Pasien -->
              <div>
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                  <label style="font-size:12.5px;font-weight:700;color:#0f172a;margin:0;">
                    Daftar Obat Racikan Yang Diresepkan:
                  </label>
                  <?php if (!empty($resep_racik_list)): ?>
                    <span style="font-size:11px;color:#64748b;">(<?= count($resep_racik_list) ?> racikan aktif)</span>
                  <?php endif; ?>
                </div>

                <div id="boxRacikanList">
                  <?php if (empty($resep_racik_list)): ?>
                    <div style="font-size:12.5px;color:var(--gray-400);text-align:center;padding:24px;border:1px dashed #cbd5e1;border-radius:8px;background:#f8fafc;">
                      <i class="fas fa-mortar-pestle" style="font-size:24px;color:#cbd5e1;display:block;margin-bottom:6px;"></i>
                      Belum ada resep racikan untuk kunjungan ini.
                    </div>
                  <?php else: ?>
                    <div style="display:flex;flex-direction:column;gap:10px;">
                      <?php foreach ($resep_racik_list as $rc): ?>
                        <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:10px;padding:12px 16px;box-shadow:0 1px 3px rgba(0,0,0,0.03);">
                          <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px;">
                            <div>
                              <div style="display:flex;align-items:center;gap:8px;">
                                <span style="font-size:13.5px;font-weight:800;color:#0f172a;"><?= htmlspecialchars($rc['nama_racik']) ?></span>
                                <span class="badge badge-info" style="font-size:10.5px;padding:2px 8px;"><?= htmlspecialchars($rc['metode_nama'] ?: $rc['kd_racik']) ?></span>
                                <span class="badge badge-light" style="font-size:11px;font-weight:700;background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0;">
                                  <?= (int)$rc['jml_dr'] ?> Kemasan
                                </span>
                              </div>
                              <div style="font-size:12px;color:#334155;margin-top:4px;">
                                <i class="fas fa-clock" style="color:#0d9488;margin-right:3px;"></i> Aturan Pakai: <strong style="color:#0f766e;"><?= htmlspecialchars($rc['aturan_pakai'] ?: '-') ?></strong>
                                <?php if (!empty($rc['keterangan']) && $rc['keterangan'] !== '-'): ?>
                                  <span style="color:#64748b;margin-left:8px;">(<?= htmlspecialchars($rc['keterangan']) ?>)</span>
                                <?php endif; ?>
                              </div>
                            </div>

                            <div style="display:flex;gap:6px;">
                              <button type="button" onclick="editResepRacikan(<?= htmlspecialchars(json_encode($rc), ENT_QUOTES, 'UTF-8') ?>)" class="btn btn-sm btn-outline-primary" style="padding:3px 8px;font-size:11px;display:inline-flex;align-items:center;gap:4px;">
                                <i class="fas fa-edit"></i> Edit
                              </button>
                              <button type="button" onclick="hapusResepRacikan('<?= $rc['no_resep'] ?>', '<?= $rc['no_racik'] ?>', '<?= htmlspecialchars(addslashes($rc['nama_racik'])) ?>')" class="btn btn-sm btn-outline-danger" style="padding:3px 8px;font-size:11px;display:inline-flex;align-items:center;gap:4px;">
                                <i class="fas fa-trash-alt"></i> Hapus
                              </button>
                            </div>
                          </div>

                          <!-- Komposisi Bahan -->
                          <div style="margin-top:10px;padding-top:8px;border-top:1px dashed #e2e8f0;background:#f8fafc;padding:8px 10px;border-radius:6px;">
                            <div style="font-size:11px;font-weight:700;color:#64748b;margin-bottom:4px;">KOMPOSISI BAHAN OBAT:</div>
                            <div style="display:flex;flex-wrap:wrap;gap:6px;">
                              <?php foreach ($rc['detail_bahan'] as $bh): ?>
                                <span style="font-size:11.5px;background:#ffffff;border:1px solid #cbd5e1;padding:3px 8px;border-radius:5px;display:inline-flex;align-items:center;gap:4px;">
                                  <i class="fas fa-capsules" style="color:#0284c7;font-size:10px;"></i>
                                  <strong><?= htmlspecialchars($bh['nama_brng']) ?></strong>:
                                  <span style="color:#0369a1;font-weight:700;"><?= $bh['jml'] ?> <?= htmlspecialchars($bh['satuan'] ?: 'Tab') ?></span>
                                  <?php if (!empty($bh['kandungan']) && $bh['kandungan'] !== '-'): ?>
                                    <span style="color:#94a3b8;font-size:10.5px;">(<?= htmlspecialchars($bh['kandungan']) ?>)</span>
                                  <?php endif; ?>
                                </span>
                              <?php endforeach; ?>
                            </div>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>

      <!-- ═══════════════════════════════════════════════════════ -->
      <!-- TAB PANE 5: PERMINTAAN & HASIL LABORATORIUM             -->
      <!-- ═══════════════════════════════════════════════════════ -->
      <div id="tab-pane-lab" class="clinical-tab-pane" style="display:none;">
        <div style="display:flex;flex-direction:column;gap:16px;">
          
          <div class="card" style="border-radius:0 0 10px 10px;border-top:1px solid #e2e8f0;">
            <div class="card-header" style="background:#f8fafc;padding:10px 18px;display:flex;justify-content:space-between;align-items:center;">
              <div class="card-title" style="font-size:13px;font-weight:700;color:#0f172a;">
                <i class="fas fa-flask-vial text-primary"></i> Order & Riwayat Pemeriksaan Laboratorium Pasien
              </div>
              <a href="<?= BASE_URL ?>modules/laboratorium/permintaan.php?no_rawat=<?= urlencode($no_rawat) ?>" 
                 target="_blank" class="btn btn-sm btn-primary" style="font-size:11.5px;padding:5px 12px;background:linear-gradient(135deg,#0284c7,#0369a1);border:none;">
                <i class="fas fa-plus-circle"></i> + Buat Permintaan Lab
              </a>
            </div>

            <div class="card-body" style="padding:16px 18px;">
              
              <!-- Daftar Order Lab Aktif Kunjungan Ini -->
              <div style="margin-bottom:20px;">
                <h4 style="font-size:12.5px;font-weight:700;color:#334155;margin:0 0 10px;display:flex;align-items:center;gap:6px;">
                  <i class="fas fa-clock" style="color:#0284c7;"></i> Status Permintaan Lab Kunjungan Ini:
                </h4>

                <?php if (empty($lab_req_list)): ?>
                  <div style="font-size:12px;color:#94a3b8;text-align:center;padding:20px;border:1px dashed #cbd5e1;border-radius:8px;background:#f8fafc;">
                    <i class="fas fa-vial" style="font-size:24px;color:#cbd5e1;display:block;margin-bottom:6px;"></i>
                    Belum ada permintaan laboratorium untuk kunjungan ini. Klik tombol <em>+ Buat Permintaan Lab</em> di atas.
                  </div>
                <?php else: ?>
                  <div style="display:flex;flex-direction:column;gap:10px;">
                    <?php foreach ($lab_req_list as $lq): ?>
                      <?php 
                        $has_sample = ($lq['tgl_sampel'] !== '0000-00-00' && !empty($lq['tgl_sampel']));
                        $has_hasil  = ($lq['tgl_hasil'] !== '0000-00-00' && !empty($lq['tgl_hasil']));
                      ?>
                      <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:8px;padding:12px 14px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
                        <div>
                          <div style="display:flex;align-items:center;gap:8px;">
                            <span style="font-family:monospace;font-weight:700;color:#0284c7;font-size:12.5px;"><?= htmlspecialchars($lq['noorder']) ?></span>
                            <span style="font-size:11px;color:#64748b;">(<?= tgl_indo($lq['tgl_permintaan']) ?> <?= substr($lq['jam_permintaan'],0,5) ?>)</span>
                          </div>
                          <div style="font-size:12.5px;font-weight:700;color:#0f172a;margin-top:3px;">
                            <?= htmlspecialchars($lq['list_paket'] ?: 'Pemeriksaan Lab') ?>
                          </div>
                          <?php if (!empty($lq['diagnosa_klinis'])): ?>
                            <div style="font-size:11px;color:#64748b;margin-top:2px;">
                              Diagnosa: <em><?= htmlspecialchars($lq['diagnosa_klinis']) ?></em>
                            </div>
                          <?php endif; ?>
                        </div>

                        <div style="display:flex;align-items:center;gap:8px;">
                          <?php if ($has_hasil): ?>
                            <span class="badge badge-success" style="font-size:11px;padding:4px 8px;border-radius:6px;">
                              <i class="fas fa-check-circle"></i> Hasil Selesai
                            </span>
                            <a href="<?= BASE_URL ?>modules/laboratorium/cetak_hasil.php?noorder=<?= urlencode($lq['noorder']) ?>&no_rawat=<?= urlencode($no_rawat) ?>" 
                               target="_blank" class="btn btn-sm btn-outline-success" style="font-size:11px;padding:3px 8px;">
                              <i class="fas fa-print"></i> Cetak Hasil
                            </a>
                          <?php elseif ($has_sample): ?>
                            <span class="badge badge-primary" style="font-size:11px;padding:4px 8px;border-radius:6px;">
                              <i class="fas fa-spinner fa-spin"></i> Proses Analisis
                            </span>
                          <?php else: ?>
                            <span class="badge badge-warning" style="font-size:11px;padding:4px 8px;border-radius:6px;">
                              <i class="fas fa-clock"></i> Menunggu Sampel
                            </span>
                          <?php endif; ?>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>

              <!-- Hasil Pemeriksaan Laboratorium Pasien Lengkap -->
              <div>
                <h4 style="font-size:12.5px;font-weight:700;color:#334155;margin:0 0 10px;display:flex;align-items:center;gap:6px;">
                  <i class="fas fa-file-medical-alt" style="color:#059669;"></i> Lembar Hasil Nilai Laboratorium:
                </h4>

                <?php if (empty($lab_hasil_list)): ?>
                  <div style="font-size:12px;color:#94a3b8;text-align:center;padding:16px;border:1px dashed #cbd5e1;border-radius:8px;background:#f8fafc;">
                    Belum ada nilai hasil laboratorium yang keluar untuk kunjungan ini.
                  </div>
                <?php else: ?>
                  <div class="table-responsive" style="border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;">
                    <table class="table" style="margin:0;font-size:12px;">
                      <thead style="background:#f1f5f9;color:#475569;font-weight:700;">
                        <tr>
                          <th>Pemeriksaan</th>
                          <th style="width:130px;text-align:center;">Hasil</th>
                          <th style="width:90px;">Satuan</th>
                          <th style="width:140px;">Nilai Rujukan</th>
                          <th style="width:120px;">Keterangan</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php 
                          $cur_pkg = '';
                          foreach ($lab_hasil_list as $lh): 
                            if ($cur_pkg !== $lh['nm_perawatan']) {
                              $cur_pkg = $lh['nm_perawatan'];
                              echo '<tr style="background:#f0f9ff;font-weight:800;color:#0284c7;"><td colspan="5"><i class="fas fa-flask"></i> ' . htmlspecialchars($cur_pkg) . ' (' . tgl_indo($lh['tgl_periksa']) . ' ' . substr($lh['jam'],0,5) . ')</td></tr>';
                            }
                            $is_abnormal = false;
                            $ket_low = strtolower($lh['keterangan'] ?? '');
                            if (str_contains($ket_low, 'high') || str_contains($ket_low, 'low') || str_contains($ket_low, 'tinggi') || str_contains($ket_low, 'rendah') || str_contains($ket_low, 'positif') || str_contains($ket_low, 'reaktif')) {
                              $is_abnormal = true;
                            }
                        ?>
                          <tr style="border-bottom:1px solid #f1f5f9;">
                            <td style="font-weight:600;color:#0f172a;"><?= htmlspecialchars($lh['Pemeriksaan']) ?></td>
                            <td style="text-align:center;font-weight:800;color:<?= $is_abnormal ? '#dc2626' : '#0f172a' ?>;">
                              <?= htmlspecialchars($lh['nilai'] ?: '-') ?> <?= $is_abnormal ? '*' : '' ?>
                            </td>
                            <td style="color:#64748b;font-family:monospace;"><?= htmlspecialchars($lh['satuan'] ?: '-') ?></td>
                            <td style="color:#475569;"><?= htmlspecialchars($lh['nilai_rujukan'] ?: '-') ?></td>
                            <td>
                              <?php if (!empty($lh['keterangan'])): ?>
                                <span class="badge <?= $is_abnormal ? 'badge-danger' : 'badge-success' ?>" style="font-size:10px;padding:2px 6px;">
                                  <?= htmlspecialchars($lh['keterangan']) ?>
                                </span>
                              <?php else: ?>
                                <span style="color:#94a3b8;">-</span>
                              <?php endif; ?>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                <?php endif; ?>
              </div>

            </div>
          </div>

        </div>
      </div>

      <!-- ═══════════════════════════════════════════════════════ -->
      <!-- TAB PANE 6: MODUL ODONTOGRAM (POLI GIGI & MULUT)        -->
      <!-- ═══════════════════════════════════════════════════════ -->
      <div id="tab-pane-odontogram" class="clinical-tab-pane" style="display:none;">
        <div class="card" style="border-radius:0 0 10px 10px;border-top:1px solid #bae6fd;box-shadow:0 1px 4px rgba(0,0,0,0.05);">
          
          <div class="card-header" style="background:linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);padding:10px 16px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #bae6fd;flex-wrap:wrap;gap:8px;">
            <div class="card-title" style="font-size:13px;font-weight:700;color:#0369a1;display:flex;align-items:center;gap:8px;">
              <i class="fas fa-tooth" style="font-size:15px;color:#0284c7;"></i>
              <span>Rekam Medis Odontogram & Kesehatan Gigi (FDI System)</span>
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
              <a href="<?= BASE_URL ?>modules/rekam_medis/cetak_odontogram.php?no_rawat=<?= urlencode($no_rawat) ?>" target="_blank" class="btn btn-xs" style="background:#0284c7;color:#fff;border-radius:6px;font-weight:600;padding:4px 10px;display:inline-flex;align-items:center;gap:4px;">
                <i class="fas fa-print"></i> Cetak Odontogram
              </a>
              <button type="button" onclick="resetAllTeethToNormal()" class="btn btn-xs btn-outline-secondary" style="font-size:11px;padding:4px 8px;border-radius:6px;">
                <i class="fas fa-undo"></i> Reset Gigi
              </button>
            </div>
          </div>

          <div class="card-body" style="padding:16px;">
            
            <!-- Tool Palette: Kondisi Klinis & Permukaan Gigi (Clean & Spacious Layout) -->
            <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:10px;padding:14px;margin-bottom:16px;box-shadow:0 1px 3px rgba(0,0,0,0.03);">
              
              <!-- Toolbar Top Row: Title & Mode Permukaan Dropdown -->
              <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;flex-wrap:wrap;gap:12px;border-bottom:1px solid #f1f5f9;padding-bottom:10px;">
                <div style="display:flex;align-items:center;gap:8px;">
                  <div style="width:30px;height:30px;border-radius:8px;background:#e0f2fe;display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-palette" style="color:#0284c7;font-size:14px;"></i>
                  </div>
                  <div>
                    <div style="font-size:12.5px;font-weight:700;color:#0f172a;">1. Pilih Simbol Kondisi Klinis Gigi:</div>
                    <div style="font-size:11px;color:#64748b;">Klik salah satu simbol di bawah, lalu klik pada permukaan bagan gigi</div>
                  </div>
                </div>

                <div style="display:flex;align-items:center;gap:8px;background:#f8fafc;padding:5px 12px;border-radius:8px;border:1px solid #e2e8f0;">
                  <label for="odontSurfaceMode" style="font-size:11.5px;font-weight:700;color:#334155;margin:0;display:flex;align-items:center;gap:5px;white-space:nowrap;">
                    <i class="fas fa-bullseye" style="color:#0284c7;"></i> Mode Permukaan:
                  </label>
                  <select id="odontSurfaceMode" style="font-size:12px;font-weight:600;padding:4px 10px;height:32px;min-width:190px;border:1.5px solid #cbd5e1;border-radius:6px;background:#ffffff;color:#0f172a;cursor:pointer;outline:none;box-shadow:0 1px 2px rgba(0,0,0,0.04);">
                    <option value="ALL" selected>Seluruh Gigi (ALL)</option>
                    <option value="O">Oklusal / Insisal (O)</option>
                    <option value="M">Mesial (M)</option>
                    <option value="D">Distal (D)</option>
                    <option value="B">Bukal / Labial (B)</option>
                    <option value="L">Lingual / Palatal (L)</option>
                  </select>
                </div>
              </div>

              <!-- Badges Kondisi Klinis Grid -->
              <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(170px, 1fr));gap:7px;" id="odontConditionPalette">
                
                <button type="button" class="btn btn-xs odont-palette-btn active" data-cond="Sou" onclick="selectOdontCondition('Sou', this)" style="background:#ffffff;color:#334155;border:1.5px solid #cbd5e1;font-weight:700;border-radius:8px;padding:6px 10px;display:flex;align-items:center;gap:8px;justify-content:flex-start;text-align:left;">
                  <span style="width:14px;height:14px;border-radius:50%;background:#10b981;border:2px solid #fff;box-shadow:0 0 0 1px #10b981;display:inline-block;flex-shrink:0;"></span>
                  <span><strong>Sou</strong> <span style="font-weight:500;font-size:10.5px;color:#64748b;">(Sehat / Normal)</span></span>
                </button>

                <button type="button" class="btn btn-xs odont-palette-btn" data-cond="Car" onclick="selectOdontCondition('Car', this)" style="background:#fff1f2;color:#be123c;border:1.5px solid #fecdd3;font-weight:700;border-radius:8px;padding:6px 10px;display:flex;align-items:center;gap:8px;justify-content:flex-start;text-align:left;">
                  <span style="width:14px;height:14px;border-radius:50%;background:#ef4444;border:2px solid #fff;box-shadow:0 0 0 1px #ef4444;display:inline-block;flex-shrink:0;"></span>
                  <span><strong>Car</strong> <span style="font-weight:500;font-size:10.5px;color:#9f1239;">(Karies / Lubang)</span></span>
                </button>

                <button type="button" class="btn btn-xs odont-palette-btn" data-cond="Amf" onclick="selectOdontCondition('Amf', this)" style="background:#f8fafc;color:#334155;border:1.5px solid #cbd5e1;font-weight:700;border-radius:8px;padding:6px 10px;display:flex;align-items:center;gap:8px;justify-content:flex-start;text-align:left;">
                  <span style="width:14px;height:14px;border-radius:3px;background:#475569;border:2px solid #fff;box-shadow:0 0 0 1px #475569;display:inline-block;flex-shrink:0;"></span>
                  <span><strong>Amf</strong> <span style="font-weight:500;font-size:10.5px;color:#64748b;">(Amalgam)</span></span>
                </button>

                <button type="button" class="btn btn-xs odont-palette-btn" data-cond="Gif" onclick="selectOdontCondition('Gif', this)" style="background:#f0fdf4;color:#15803d;border:1.5px solid #bbf7d0;font-weight:700;border-radius:8px;padding:6px 10px;display:flex;align-items:center;gap:8px;justify-content:flex-start;text-align:left;">
                  <span style="width:14px;height:14px;border-radius:3px;background:#10b981;border:2px solid #fff;box-shadow:0 0 0 1px #10b981;display:inline-block;flex-shrink:0;"></span>
                  <span><strong>Gif</strong> <span style="font-weight:500;font-size:10.5px;color:#166534;">(Komposit / GIC)</span></span>
                </button>

                <button type="button" class="btn btn-xs odont-palette-btn" data-cond="Mis" onclick="selectOdontCondition('Mis', this)" style="background:#0f172a;color:#ffffff;border:1.5px solid #0f172a;font-weight:700;border-radius:8px;padding:6px 10px;display:flex;align-items:center;gap:8px;justify-content:flex-start;text-align:left;">
                  <i class="fas fa-times" style="color:#ef4444;font-size:13px;width:14px;text-align:center;"></i>
                  <span><strong>Mis</strong> <span style="font-weight:500;font-size:10.5px;color:#cbd5e1;">(Hilang / Cabut)</span></span>
                </button>

                <button type="button" class="btn btn-xs odont-palette-btn" data-cond="Rad" onclick="selectOdontCondition('Rad', this)" style="background:#fffbeb;color:#b45309;border:1.5px solid #fde68a;font-weight:700;border-radius:8px;padding:6px 10px;display:flex;align-items:center;gap:8px;justify-content:flex-start;text-align:left;">
                  <i class="fas fa-bolt" style="color:#d97706;font-size:13px;width:14px;text-align:center;"></i>
                  <span><strong>Rad</strong> <span style="font-weight:500;font-size:10.5px;color:#92400e;">(Sisa Akar / Radix)</span></span>
                </button>

                <button type="button" class="btn btn-xs odont-palette-btn" data-cond="Cro" onclick="selectOdontCondition('Cro', this)" style="background:#eef2ff;color:#4338ca;border:1.5px solid #c7d2fe;font-weight:700;border-radius:8px;padding:6px 10px;display:flex;align-items:center;gap:8px;justify-content:flex-start;text-align:left;">
                  <i class="fas fa-crown" style="color:#4f46e5;font-size:13px;width:14px;text-align:center;"></i>
                  <span><strong>Cro</strong> <span style="font-weight:500;font-size:10.5px;color:#3730a3;">(Mahkota / Crown)</span></span>
                </button>

                <button type="button" class="btn btn-xs odont-palette-btn" data-cond="Bdr" onclick="selectOdontCondition('Bdr', this)" style="background:#ecfeff;color:#0e7490;border:1.5px solid #a5f3fc;font-weight:700;border-radius:8px;padding:6px 10px;display:flex;align-items:center;gap:8px;justify-content:flex-start;text-align:left;">
                  <i class="fas fa-link" style="color:#0891b2;font-size:13px;width:14px;text-align:center;"></i>
                  <span><strong>Bdr</strong> <span style="font-weight:500;font-size:10.5px;color:#155e75;">(Bridge / Jembatan)</span></span>
                </button>

                <button type="button" class="btn btn-xs odont-palette-btn" data-cond="Imp" onclick="selectOdontCondition('Imp', this)" style="background:#faf5ff;color:#7e22ce;border:1.5px solid #e9d5ff;font-weight:700;border-radius:8px;padding:6px 10px;display:flex;align-items:center;gap:8px;justify-content:flex-start;text-align:left;">
                  <i class="fas fa-arrow-down" style="color:#7e22ce;font-size:13px;width:14px;text-align:center;"></i>
                  <span><strong>Imp</strong> <span style="font-weight:500;font-size:10.5px;color:#6b21a8;">(Impaksi)</span></span>
                </button>

                <button type="button" class="btn btn-xs odont-palette-btn" data-cond="Abx" onclick="selectOdontCondition('Abx', this)" style="background:#fef2f2;color:#991b1b;border:1.5px solid #fecaca;font-weight:700;border-radius:8px;padding:6px 10px;display:flex;align-items:center;gap:8px;justify-content:flex-start;text-align:left;">
                  <i class="fas fa-exclamation-triangle" style="color:#dc2626;font-size:13px;width:14px;text-align:center;"></i>
                  <span><strong>Abx</strong> <span style="font-weight:500;font-size:10.5px;color:#7f1d1d;">(Abses)</span></span>
                </button>

                <button type="button" class="btn btn-xs odont-palette-btn" data-cond="Fis" onclick="selectOdontCondition('Fis', this)" style="background:#eff6ff;color:#1d4ed8;border:1.5px solid #bfdbfe;font-weight:700;border-radius:8px;padding:6px 10px;display:flex;align-items:center;gap:8px;justify-content:flex-start;text-align:left;">
                  <i class="fas fa-shield-alt" style="color:#2563eb;font-size:13px;width:14px;text-align:center;"></i>
                  <span><strong>Fis</strong> <span style="font-weight:500;font-size:10.5px;color:#1e40af;">(Fissure Sealant)</span></span>
                </button>

              </div>
            </div>

            <!-- Interactive FDI Teeth Chart Canvas/Grid -->
            <div style="background:#ffffff;border:1px solid #cbd5e1;border-radius:10px;padding:16px;box-shadow:0 1px 3px rgba(0,0,0,0.03);margin-bottom:16px;overflow-x:auto;">
              
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;border-bottom:1px solid #f1f5f9;padding-bottom:4px;">
                <span style="font-size:11px;font-weight:700;color:#0284c7;text-transform:uppercase;letter-spacing:0.5px;">
                  <i class="fas fa-caret-up"></i> Rahang Atas (Maxilla) — Gigi Permanen
                </span>
                <span style="font-size:10px;color:#94a3b8;">Kuadran 1 (Kanan) | Kuadran 2 (Kiri)</span>
              </div>

              <!-- Upper Permanent Teeth (18..11 | 21..28) -->
              <div style="display:flex;justify-content:center;gap:5px;margin-bottom:14px;overflow-x:auto;padding:4px 0;" id="odontRowUpperPerm">
                <!-- Injected via JS -->
              </div>

              <!-- Deciduous / Gigi Susu (55..51 | 61..65 & 85..81 | 71..75) -->
              <div style="background:#f8fafc;border:1px dashed #cbd5e1;border-radius:8px;padding:10px;margin-bottom:14px;">
                <div style="text-align:center;font-size:11px;font-weight:700;color:#0284c7;margin-bottom:6px;text-transform:uppercase;letter-spacing:0.5px;">
                  <i class="fas fa-baby" style="margin-right:4px;"></i> Gigi Susu Anak (Deciduous)
                </div>
                <div style="display:flex;justify-content:center;gap:4px;margin-bottom:6px;overflow-x:auto;padding:2px 0;" id="odontRowUpperDec">
                  <!-- Injected via JS -->
                </div>
                <div style="display:flex;justify-content:center;gap:4px;overflow-x:auto;padding:2px 0;" id="odontRowLowerDec">
                  <!-- Injected via JS -->
                </div>
              </div>

              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;border-bottom:1px solid #f1f5f9;padding-bottom:4px;">
                <span style="font-size:11px;font-weight:700;color:#0284c7;text-transform:uppercase;letter-spacing:0.5px;">
                  <i class="fas fa-caret-down"></i> Rahang Bawah (Mandibula) — Gigi Permanen
                </span>
                <span style="font-size:10px;color:#94a3b8;">Kuadran 4 (Kanan) | Kuadran 3 (Kiri)</span>
              </div>

              <!-- Lower Permanent Teeth (48..41 | 31..38) -->
              <div style="display:flex;justify-content:center;gap:5px;overflow-x:auto;padding:4px 0;" id="odontRowLowerPerm">
                <!-- Injected via JS -->
              </div>

            </div>

            <!-- Ringkasan Statistik DMF-T & OHIS Index -->
            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:12px;margin-bottom:16px;">
              
              <!-- DMF-T Card -->
              <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:12px;">
                <div style="font-size:11.5px;font-weight:700;color:#166534;margin-bottom:6px;display:flex;align-items:center;justify-content:space-between;">
                  <span><i class="fas fa-calculator"></i> Indeks DMF-T</span>
                  <span class="badge" style="background:#166534;color:#fff;" id="badgeDmftTotal">Total: 0</span>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:11px;color:#166534;">
                  <div><strong>D (Decay):</strong> <span id="valD">0</span></div>
                  <div><strong>M (Missing):</strong> <span id="valM">0</span></div>
                  <div><strong>F (Filled):</strong> <span id="valF">0</span></div>
                </div>
              </div>

              <!-- OHIS Card -->
              <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:12px;">
                <div style="font-size:11.5px;font-weight:700;color:#1e40af;margin-bottom:6px;display:flex;align-items:center;justify-content:space-between;">
                  <span><i class="fas fa-broom"></i> Indeks Kebersihan Mulut (OHIS)</span>
                  <span class="badge" style="background:#1e40af;color:#fff;" id="badgeOhisKriteria">Baik</span>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;font-size:11px;">
                  <div>
                    <label style="font-size:10px;color:#475569;margin-bottom:2px;display:block;">Debris Index (DI):</label>
                    <input type="number" step="0.1" id="inputOhisDebris" class="form-control" value="0.0" onchange="calculateOhisScore()" style="font-size:11px;padding:2px 6px;height:24px;">
                  </div>
                  <div>
                    <label style="font-size:10px;color:#475569;margin-bottom:2px;display:block;">Calculus Index (CI):</label>
                    <input type="number" step="0.1" id="inputOhisCalculus" class="form-control" value="0.0" onchange="calculateOhisScore()" style="font-size:11px;padding:2px 6px;height:24px;">
                  </div>
                </div>
              </div>

            </div>

            <!-- Form Pemeriksaan Klinis Gigi Khusus -->
            <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:8px;padding:14px;margin-bottom:16px;">
              <div style="font-size:12px;font-weight:700;color:#334155;margin-bottom:10px;display:flex;align-items:center;gap:6px;">
                <i class="fas fa-clipboard-check text-primary"></i>
                <span>2. Pemeriksaan Jaringan Lunak & Oklusi</span>
              </div>

              <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));gap:10px;font-size:11.5px;">
                <div>
                  <label style="font-weight:600;color:#475569;margin-bottom:3px;display:block;">Oklusi</label>
                  <select id="odontOklusi" class="form-control" style="font-size:11.5px;padding:4px 8px;height:30px;">
                    <option value="Normal">Normal</option>
                    <option value="Crossbite">Crossbite</option>
                    <option value="Steepbite">Steepbite</option>
                    <option value="Openbite">Openbite</option>
                    <option value="Lainnya">Lainnya</option>
                  </select>
                </div>
                <div>
                  <label style="font-weight:600;color:#475569;margin-bottom:3px;display:block;">Torus Palatinus</label>
                  <select id="odontTorusPalatinus" class="form-control" style="font-size:11.5px;padding:4px 8px;height:30px;">
                    <option value="Tidak Ada">Tidak Ada</option>
                    <option value="Kecil">Kecil</option>
                    <option value="Sedang">Sedang</option>
                    <option value="Besar">Besar</option>
                    <option value="Multiple">Multiple</option>
                  </select>
                </div>
                <div>
                  <label style="font-weight:600;color:#475569;margin-bottom:3px;display:block;">Torus Mandibularis</label>
                  <select id="odontTorusMandibularis" class="form-control" style="font-size:11.5px;padding:4px 8px;height:30px;">
                    <option value="Tidak Ada">Tidak Ada</option>
                    <option value="Sisi Kiri">Sisi Kiri</option>
                    <option value="Sisi Kanan">Sisi Kanan</option>
                    <option value="Kedua Sisi">Kedua Sisi</option>
                  </select>
                </div>
                <div>
                  <label style="font-weight:600;color:#475569;margin-bottom:3px;display:block;">Palatum</label>
                  <select id="odontPalatum" class="form-control" style="font-size:11.5px;padding:4px 8px;height:30px;">
                    <option value="Dalam">Dalam</option>
                    <option value="Sedang" selected>Sedang</option>
                    <option value="Rendah">Rendah</option>
                  </select>
                </div>
                <div>
                  <label style="font-weight:600;color:#475569;margin-bottom:3px;display:block;">Diastema</label>
                  <input type="text" id="odontDiastema" class="form-control" placeholder="cth: Gigi 11-21 (2mm)" style="font-size:11.5px;padding:4px 8px;height:30px;">
                </div>
                <div>
                  <label style="font-weight:600;color:#475569;margin-bottom:3px;display:block;">Gigi Anomali</label>
                  <input type="text" id="odontGigiAnomali" class="form-control" placeholder="cth: Microdontia 12" style="font-size:11.5px;padding:4px 8px;height:30px;">
                </div>
              </div>

              <div style="margin-top:10px;">
                <label style="font-weight:600;color:#475569;margin-bottom:3px;display:block;font-size:11.5px;">Catatan Tambahan & Rencana Perawatan Gigi</label>
                <textarea id="odontLainLain" class="form-control" rows="2" placeholder="Catatan kelainan gingiva, rencana tambal / scaling / prosto..." style="font-size:11.5px;padding:6px 8px;"></textarea>
              </div>

            </div>

            <!-- Tombol Simpan Odontogram -->
            <div style="display:flex;justify-content:flex-end;gap:8px;">
              <button type="button" onclick="saveOdontogramData()" class="btn btn-primary" style="font-weight:700;font-size:12.5px;padding:8px 18px;display:inline-flex;align-items:center;gap:6px;">
                <i class="fas fa-save"></i> Simpan Odontogram & Pemeriksaan Gigi
              </button>
            </div>

          </div>

        </div>
      </div>

      <!-- ═══════════════════════════════════════════════════════ -->
      <!-- TAB PANE 7: MODUL KIA (ANC, KMS ANAK, IMUNISASI & KB)   -->
      <!-- ═══════════════════════════════════════════════════════ -->
      <div id="tab-pane-kia" class="clinical-tab-pane" style="display:none;">
        <div class="card" style="border-radius:0 0 10px 10px;border-top:1px solid #fbcfe8;box-shadow:0 1px 4px rgba(0,0,0,0.05);">
          
          <div class="card-header" style="background:linear-gradient(135deg, #fdf2f8 0%, #fce7f3 100%);padding:10px 16px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #fbcfe8;flex-wrap:wrap;gap:8px;">
            <div class="card-title" style="font-size:13px;font-weight:700;color:#be185d;display:flex;align-items:center;gap:8px;">
              <i class="fas fa-person-breastfeeding" style="font-size:15px;color:#db2777;"></i>
              <span>Rekam Medis Kesehatan Ibu & Anak (KIA / Kebidanan & KMS)</span>
            </div>
            <div style="display:flex;align-items:center;gap:6px;">
              <a href="<?= BASE_URL ?>modules/rekam_medis/cetak_kia.php?no_rawat=<?= urlencode($no_rawat) ?>&tipe=anc" target="_blank" class="btn btn-xs" style="background:#db2777;color:#fff;border-radius:6px;font-weight:600;padding:4px 10px;display:inline-flex;align-items:center;gap:4px;">
                <i class="fas fa-print"></i> Cetak Lembar KIA
              </a>
            </div>
          </div>

          <div class="card-body" style="padding:16px;">
            
            <!-- Sub-Tab Navigasi KIA -->
            <div style="display:flex;gap:6px;margin-bottom:16px;border-bottom:2px solid #f1f5f9;padding-bottom:8px;flex-wrap:wrap;">
              <button type="button" class="btn btn-sm kia-subtab-btn active" id="kia-sub-btn-anc" onclick="switchKiaSubTab('anc')" style="font-size:11.5px;font-weight:700;padding:5px 12px;border-radius:6px;display:inline-flex;align-items:center;gap:6px;">
                <i class="fas fa-person-pregnant" style="color:#db2777;"></i>
                <span>A. ANC (Ibu Hamil)</span>
              </button>
              <button type="button" class="btn btn-sm kia-subtab-btn btn-secondary" id="kia-sub-btn-kms" onclick="switchKiaSubTab('kms')" style="font-size:11.5px;font-weight:700;padding:5px 12px;border-radius:6px;display:inline-flex;align-items:center;gap:6px;">
                <i class="fas fa-baby" style="color:#059669;"></i>
                <span>B. KMS & Tumbuh Kembang</span>
              </button>
              <button type="button" class="btn btn-sm kia-subtab-btn btn-secondary" id="kia-sub-btn-imunisasi" onclick="switchKiaSubTab('imunisasi')" style="font-size:11.5px;font-weight:700;padding:5px 12px;border-radius:6px;display:inline-flex;align-items:center;gap:6px;">
                <i class="fas fa-syringe" style="color:#0284c7;"></i>
                <span>C. Imunisasi Dasar Anak</span>
              </button>
              <button type="button" class="btn btn-sm kia-subtab-btn btn-secondary" id="kia-sub-btn-kb" onclick="switchKiaSubTab('kb')" style="font-size:11.5px;font-weight:700;padding:5px 12px;border-radius:6px;display:inline-flex;align-items:center;gap:6px;">
                <i class="fas fa-venus-mars" style="color:#7c3aed;"></i>
                <span>D. Pelayanan KB</span>
              </button>
            </div>

            <!-- ─── SUB-TAB A: ANC (ANTENATAL CARE IBU HAMIL) ─── -->
            <div id="kia-sub-pane-anc" class="kia-subtab-pane" style="display:block;">
              
              <div style="background:#fff1f2;border:1px solid #fecdd3;border-radius:8px;padding:12px;margin-bottom:14px;">
                <div style="font-size:12px;font-weight:700;color:#9f1239;margin-bottom:8px;display:flex;align-items:center;gap:6px;">
                  <i class="fas fa-calculator"></i>
                  <span>1. Riwayat Obstetri & Kalkulator Kehamilan Cerdas (Naegele)</span>
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(130px, 1fr));gap:8px;font-size:11.5px;">
                  <div>
                    <label style="font-weight:600;color:#881337;margin-bottom:2px;display:block;">Gravida (G)</label>
                    <input type="number" id="ancG" class="form-control" value="1" min="1" style="font-size:11.5px;padding:3px 6px;height:28px;">
                  </div>
                  <div>
                    <label style="font-weight:600;color:#881337;margin-bottom:2px;display:block;">Partus (P)</label>
                    <input type="number" id="ancP" class="form-control" value="0" min="0" style="font-size:11.5px;padding:3px 6px;height:28px;">
                  </div>
                  <div>
                    <label style="font-weight:600;color:#881337;margin-bottom:2px;display:block;">Abortus (A)</label>
                    <input type="number" id="ancA" class="form-control" value="0" min="0" style="font-size:11.5px;padding:3px 6px;height:28px;">
                  </div>
                  <div>
                    <label style="font-weight:600;color:#881337;margin-bottom:2px;display:block;">Anak Hidup (H)</label>
                    <input type="number" id="ancH" class="form-control" value="0" min="0" style="font-size:11.5px;padding:3px 6px;height:28px;">
                  </div>
                  <div>
                    <label style="font-weight:600;color:#881337;margin-bottom:2px;display:block;">HPHT</label>
                    <input type="date" id="ancHPHT" class="form-control" onchange="calculateNaegeleHPL()" style="font-size:11.5px;padding:3px 6px;height:28px;">
                  </div>
                  <div>
                    <label style="font-weight:600;color:#881337;margin-bottom:2px;display:block;">HPL (Taksiran Lahir)</label>
                    <input type="date" id="ancHPL" class="form-control" style="font-size:11.5px;padding:3px 6px;height:28px;background:#fef2f2;font-weight:700;color:#991b1b;">
                  </div>
                  <div>
                    <label style="font-weight:600;color:#881337;margin-bottom:2px;display:block;">Usia Kehamilan</label>
                    <input type="text" id="ancUsiaKehamilan" class="form-control" placeholder="cth: 24 Minggu 3 Hari" style="font-size:11.5px;padding:3px 6px;height:28px;background:#fef2f2;font-weight:700;color:#991b1b;">
                  </div>
                </div>
              </div>

              <!-- Parameter Klinis ANC -->
              <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:8px;padding:12px;margin-bottom:14px;">
                <div style="font-size:12px;font-weight:700;color:#334155;margin-bottom:10px;display:flex;align-items:center;gap:6px;">
                  <i class="fas fa-stethoscope text-primary"></i>
                  <span>2. Pemeriksaan Fisik & Palpasi Leopold</span>
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(160px, 1fr));gap:10px;font-size:11.5px;">
                  <div>
                    <label style="font-weight:600;color:#475569;margin-bottom:2px;display:block;">TFU (cm)</label>
                    <input type="text" id="ancTFU" class="form-control" placeholder="cth: 22 cm" style="font-size:11.5px;padding:4px 8px;height:30px;">
                  </div>
                  <div>
                    <label style="font-weight:600;color:#475569;margin-bottom:2px;display:block;">Denyut Jantung Janin (DJJ)</label>
                    <div style="display:flex;align-items:center;gap:4px;">
                      <input type="text" id="ancDJJ" class="form-control" placeholder="cth: 140 dpm" style="font-size:11.5px;padding:4px 8px;height:30px;">
                      <span style="font-size:10px;color:#059669;white-space:nowrap;">(120-160 dpm)</span>
                    </div>
                  </div>
                  <div>
                    <label style="font-weight:600;color:#475569;margin-bottom:2px;display:block;">Letak / Presentasi Janin</label>
                    <select id="ancLetakJanin" class="form-control" style="font-size:11.5px;padding:4px 8px;height:30px;">
                      <option value="Kepala">Kepala (Vertex)</option>
                      <option value="Sungsang / Bokong">Sungsang / Bokong</option>
                      <option value="Melintang">Melintang (Transverse)</option>
                      <option value="Oblik">Oblik</option>
                      <option value="Belum Teraba">Belum Teraba</option>
                    </select>
                  </div>
                  <div>
                    <label style="font-weight:600;color:#475569;margin-bottom:2px;display:block;">Edema Ekstremitas</label>
                    <select id="ancEdema" class="form-control" style="font-size:11.5px;padding:4px 8px;height:30px;">
                      <option value="Tidak">Tidak Ada</option>
                      <option value="+">+ (Ringan)</option>
                      <option value="++">++ (Sedang)</option>
                      <option value="+++">+++ (Berat)</option>
                    </select>
                  </div>
                  <div>
                    <label style="font-weight:600;color:#475569;margin-bottom:2px;display:block;">Refleks Patella</label>
                    <select id="ancReflPatella" class="form-control" style="font-size:11.5px;padding:4px 8px;height:30px;">
                      <option value="+">+ / Positif (Normal)</option>
                      <option value="-">- / Negatif</option>
                    </select>
                  </div>
                  <div>
                    <label style="font-weight:600;color:#475569;margin-bottom:2px;display:block;">Skor KSPR & Tingkat Risiko</label>
                    <div style="display:flex;align-items:center;gap:4px;">
                      <input type="number" id="ancSkorKspr" class="form-control" value="2" min="2" onchange="evaluateKsprRisk()" style="font-size:11.5px;padding:4px 6px;height:30px;width:55px;">
                      <select id="ancResikoKehamilan" class="form-control" style="font-size:11px;padding:4px 6px;height:30px;">
                        <option value="KRR (Rendah)">KRR (Rendah)</option>
                        <option value="KRT (Tinggi)">KRT (Tinggi)</option>
                        <option value="KRST (Sangat Tinggi)">KRST (Sangat Tinggi)</option>
                      </select>
                    </div>
                  </div>
                </div>

                <!-- Palpasi Leopold 1-4 -->
                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:8px;margin-top:10px;font-size:11px;">
                  <div>
                    <label style="font-weight:600;color:#475569;display:block;">Leopold I (Fundus):</label>
                    <input type="text" id="ancLeopold1" class="form-control" placeholder="Tinggi fundus & bagian di fundus" style="font-size:11px;padding:3px 6px;height:26px;">
                  </div>
                  <div>
                    <label style="font-weight:600;color:#475569;display:block;">Leopold II (Samping):</label>
                    <input type="text" id="ancLeopold2" class="form-control" placeholder="Punggung kanan/kiri & ekstremitas" style="font-size:11px;padding:3px 6px;height:26px;">
                  </div>
                  <div>
                    <label style="font-weight:600;color:#475569;display:block;">Leopold III (Bawah):</label>
                    <input type="text" id="ancLeopold3" class="form-control" placeholder="Bagian terbawah janin (kepala/bokong)" style="font-size:11px;padding:3px 6px;height:26px;">
                  </div>
                  <div>
                    <label style="font-weight:600;color:#475569;display:block;">Leopold IV (Masuk PAP):</label>
                    <input type="text" id="ancLeopold4" class="form-control" placeholder="Konvergen / Divergen" style="font-size:11px;padding:3px 6px;height:26px;">
                  </div>
                </div>

                <div style="margin-top:10px;">
                  <label style="font-weight:600;color:#475569;margin-bottom:3px;display:block;font-size:11.5px;">Tindakan, Pemberian Tablet Fe & Kalsium</label>
                  <input type="text" id="ancTindakanKia" class="form-control" placeholder="cth: Fe 30 tab, Kalsium Laktat, Edukasi Tanda Bahaya Kehamilan" style="font-size:11.5px;padding:4px 8px;height:30px;">
                </div>

                <div style="margin-top:8px;">
                  <label style="font-weight:600;color:#475569;margin-bottom:3px;display:block;font-size:11.5px;">Saran & Edukasi Bidan / Dokter</label>
                  <textarea id="ancSaranNasehat" class="form-control" rows="2" placeholder="Anjuran nutrisi, pola istirahat, tanda persalinan..." style="font-size:11.5px;padding:6px 8px;"></textarea>
                </div>

              </div>

              <div style="display:flex;justify-content:flex-end;">
                <button type="button" onclick="saveKiaAncData()" class="btn btn-primary" style="background:#db2777;border-color:#db2777;font-weight:700;font-size:12px;padding:7px 18px;display:inline-flex;align-items:center;gap:6px;">
                  <i class="fas fa-save"></i> Simpan Pemeriksaan ANC
                </button>
              </div>

            </div>

            <!-- ─── SUB-TAB B: KMS & TUMBUH KEMBANG ANAK ─── -->
            <div id="kia-sub-pane-kms" class="kia-subtab-pane" style="display:none;">
              
              <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:12px;margin-bottom:14px;">
                <div style="font-size:12px;font-weight:700;color:#166534;margin-bottom:8px;display:flex;align-items:center;gap:6px;">
                  <i class="fas fa-weight-scale"></i>
                  <span>1. Pengukuran Antropometri & Evaluasi Status Gizi Standar WHO</span>
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(130px, 1fr));gap:8px;font-size:11.5px;">
                  <div>
                    <label style="font-weight:600;color:#166534;margin-bottom:2px;display:block;">Umur (Bulan)</label>
                    <input type="number" id="kmsUmurBln" class="form-control" value="0" min="0" style="font-size:11.5px;padding:3px 6px;height:28px;">
                  </div>
                  <div>
                    <label style="font-weight:600;color:#166534;margin-bottom:2px;display:block;">Berat Badan (kg)</label>
                    <input type="number" step="0.05" id="kmsBB" class="form-control" placeholder="cth: 7.2" onchange="evaluateChildNutrition()" style="font-size:11.5px;padding:3px 6px;height:28px;">
                  </div>
                  <div>
                    <label style="font-weight:600;color:#166534;margin-bottom:2px;display:block;">Tinggi / PB (cm)</label>
                    <input type="number" step="0.5" id="kmsTB" class="form-control" placeholder="cth: 68" onchange="evaluateChildNutrition()" style="font-size:11.5px;padding:3px 6px;height:28px;">
                  </div>
                  <div>
                    <label style="font-weight:600;color:#166534;margin-bottom:2px;display:block;">Lingkar Kepala (cm)</label>
                    <input type="number" step="0.1" id="kmsLK" class="form-control" placeholder="cth: 42" style="font-size:11.5px;padding:3px 6px;height:28px;">
                  </div>
                  <div>
                    <label style="font-weight:600;color:#166534;margin-bottom:2px;display:block;">LiLA (cm)</label>
                    <input type="number" step="0.1" id="kmsLiLA" class="form-control" placeholder="cth: 13.5" style="font-size:11.5px;padding:3px 6px;height:28px;">
                  </div>
                  <div>
                    <label style="font-weight:600;color:#166534;margin-bottom:2px;display:block;">Status Gizi (BB/U)</label>
                    <select id="kmsStatusGiziBBU" class="form-control" style="font-size:11px;padding:3px 6px;height:28px;font-weight:700;color:#166534;">
                      <option value="Gizi Baik (Normal)">Gizi Baik (Normal)</option>
                      <option value="Gizi Kurang">Gizi Kurang</option>
                      <option value="Gizi Buruk">Gizi Buruk</option>
                      <option value="Berisiko Gizi Lebih">Berisiko Gizi Lebih</option>
                      <option value="Gizi Lebih">Gizi Lebih</option>
                      <option value="Obesitas">Obesitas</option>
                    </select>
                  </div>
                </div>
              </div>

              <!-- Nutrisi & Perkembangan -->
              <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:8px;padding:12px;margin-bottom:14px;font-size:11.5px;">
                <div style="font-size:12px;font-weight:700;color:#334155;margin-bottom:10px;display:flex;align-items:center;gap:6px;">
                  <i class="fas fa-apple-whole text-success"></i>
                  <span>2. Suplementasi Gizi & Motorik</span>
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(160px, 1fr));gap:10px;">
                  <div>
                    <label style="font-weight:600;color:#475569;margin-bottom:2px;display:block;">ASI Eksklusif (0-6 Bln)</label>
                    <select id="kmsAsiEksklusif" class="form-control" style="font-size:11.5px;padding:4px 8px;height:30px;">
                      <option value="Ya">Ya</option>
                      <option value="Tidak">Tidak</option>
                    </select>
                  </div>
                  <div>
                    <label style="font-weight:600;color:#475569;margin-bottom:2px;display:block;">Vitamin A</label>
                    <select id="kmsVitA" class="form-control" style="font-size:11.5px;padding:4px 8px;height:30px;">
                      <option value="Tidak">Tidak Diberikan</option>
                      <option value="Ya - Kapsul Biru (100.000 IU)">Ya - Kapsul Biru (6-11 bln)</option>
                      <option value="Ya - Kapsul Merah (200.000 IU)">Ya - Kapsul Merah (12-59 bln)</option>
                    </select>
                  </div>
                  <div>
                    <label style="font-weight:600;color:#475569;margin-bottom:2px;display:block;">Obat Cacing</label>
                    <select id="kmsObatCacing" class="form-control" style="font-size:11.5px;padding:4px 8px;height:30px;">
                      <option value="Tidak">Tidak Diberikan</option>
                      <option value="Ya">Ya Diberikan</option>
                    </select>
                  </div>
                </div>

                <div style="margin-top:10px;">
                  <label style="font-weight:600;color:#475569;margin-bottom:3px;display:block;">Perkembangan Motorik & Tumbuh Kembang</label>
                  <textarea id="kmsPerkembanganMotorik" class="form-control" rows="2" placeholder="Bisa menegakkan kepala, berguling, duduk mandiri, merangkak..." style="font-size:11.5px;padding:6px 8px;"></textarea>
                </div>
              </div>

              <div style="display:flex;justify-content:flex-end;">
                <button type="button" onclick="saveKiaKmsData()" class="btn btn-primary" style="background:#059669;border-color:#059669;font-weight:700;font-size:12px;padding:7px 18px;display:inline-flex;align-items:center;gap:6px;">
                  <i class="fas fa-save"></i> Simpan Data KMS Anak
                </button>
              </div>

            </div>

            <!-- ─── SUB-TAB C: IMUNISASI DASAR ANAK ─── -->
            <div id="kia-sub-pane-imunisasi" class="kia-subtab-pane" style="display:none;">
              
              <!-- Form Tambah Vaksin -->
              <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:12px;margin-bottom:14px;">
                <div style="font-size:12px;font-weight:700;color:#1e40af;margin-bottom:8px;display:flex;align-items:center;gap:6px;">
                  <i class="fas fa-plus-circle"></i>
                  <span>Input Pelayanan Imunisasi Anak</span>
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(160px, 1fr));gap:8px;font-size:11.5px;">
                  <div>
                    <label style="font-weight:600;color:#1e40af;margin-bottom:2px;display:block;">Tanggal Imunisasi</label>
                    <input type="date" id="imunTgl" class="form-control" value="<?= date('Y-m-d') ?>" style="font-size:11.5px;padding:3px 6px;height:28px;">
                  </div>
                  <div style="grid-column: span 2;">
                    <label style="font-weight:600;color:#1e40af;margin-bottom:2px;display:block;">Jenis Vaksin / Imunisasi</label>
                    <select id="imunJenis" class="form-control" style="font-size:11.5px;padding:3px 6px;height:28px;">
                      <option value="">-- Pilih Jenis Imunisasi --</option>
                      <option value="Hepatitis B0 (HB-0) 0-24 Jam">Hepatitis B0 (HB-0) 0-24 Jam</option>
                      <option value="BCG (Bulan 1)">BCG (Bulan 1)</option>
                      <option value="Polio 1 (Tetes) Bulan 1">Polio 1 (Tetes) Bulan 1</option>
                      <option value="DPT-HB-Hib 1 (Bulan 2)">DPT-HB-Hib 1 (Bulan 2)</option>
                      <option value="Polio 2 (Tetes) Bulan 2">Polio 2 (Tetes) Bulan 2</option>
                      <option value="PCV 1 (Bulan 2)">PCV 1 (Bulan 2)</option>
                      <option value="Rotavirus 1 (Bulan 2)">Rotavirus 1 (Bulan 2)</option>
                      <option value="DPT-HB-Hib 2 (Bulan 3)">DPT-HB-Hib 2 (Bulan 3)</option>
                      <option value="Polio 3 (Tetes) Bulan 3">Polio 3 (Tetes) Bulan 3</option>
                      <option value="PCV 2 (Bulan 3)">PCV 2 (Bulan 3)</option>
                      <option value="Rotavirus 2 (Bulan 3)">Rotavirus 2 (Bulan 3)</option>
                      <option value="DPT-HB-Hib 3 (Bulan 4)">DPT-HB-Hib 3 (Bulan 4)</option>
                      <option value="Polio 4 (Tetes) Bulan 4">Polio 4 (Tetes) Bulan 4</option>
                      <option value="IPV 1 (Suntik) Bulan 4">IPV 1 (Suntik) Bulan 4</option>
                      <option value="Rotavirus 3 (Bulan 4)">Rotavirus 3 (Bulan 4)</option>
                      <option value="Campak-Rubella (MR 1) Bulan 9">Campak-Rubella (MR 1) Bulan 9</option>
                      <option value="IPV 2 (Suntik) Bulan 9">IPV 2 (Suntik) Bulan 9</option>
                      <option value="PCV 3 (Bulan 12)">PCV 3 (Bulan 12)</option>
                      <option value="DPT-HB-Hib Lanjutan (Bulan 18)">DPT-HB-Hib Lanjutan (Bulan 18)</option>
                      <option value="Campak-Rubella (MR 2) Bulan 18">Campak-Rubella (MR 2) Bulan 18</option>
                    </select>
                  </div>
                  <div>
                    <label style="font-weight:600;color:#1e40af;margin-bottom:2px;display:block;">No. Batch Vaksin</label>
                    <input type="text" id="imunBatch" class="form-control" placeholder="cth: B2026-A" style="font-size:11.5px;padding:3px 6px;height:28px;">
                  </div>
                  <div>
                    <label style="font-weight:600;color:#1e40af;margin-bottom:2px;display:block;">Keterangan</label>
                    <input type="text" id="imunKet" class="form-control" placeholder="cth: Paha kanan, tidak rewel" style="font-size:11.5px;padding:3px 6px;height:28px;">
                  </div>
                </div>
                <div style="display:flex;justify-content:flex-end;margin-top:8px;">
                  <button type="button" onclick="addImunisasiItem()" class="btn btn-primary" style="background:#0284c7;border-color:#0284c7;font-weight:700;font-size:11.5px;padding:5px 14px;display:inline-flex;align-items:center;gap:4px;">
                    <i class="fas fa-plus"></i> Tambah Imunisasi
                  </button>
                </div>
              </div>

              <!-- Tabel Riwayat Imunisasi Anak -->
              <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;">
                <div style="padding:8px 12px;background:#f8fafc;border-bottom:1px solid #e2e8f0;font-size:12px;font-weight:700;color:#334155;">
                  <i class="fas fa-history text-primary"></i> Riwayat Imunisasi Yang Pernah Diterima Pasien Ini
                </div>
                <table class="table table-bordered mb-0" style="font-size:11.5px;margin:0;">
                  <thead style="background:#f1f5f9;">
                    <tr>
                      <th style="width:5%;text-align:center;">#</th>
                      <th style="width:20%;">Tanggal</th>
                      <th style="width:35%;">Jenis Vaksin</th>
                      <th style="width:15%;">No. Batch</th>
                      <th style="width:15%;">Petugas</th>
                      <th style="width:10%;text-align:center;">Aksi</th>
                    </tr>
                  </thead>
                  <tbody id="tableBodyImunisasi">
                    <tr><td colspan="6" style="text-align:center;color:#94a3b8;padding:14px;">Memuat data imunisasi...</td></tr>
                  </tbody>
                </table>
              </div>

            </div>

            <!-- ─── SUB-TAB D: PELAYANAN KB ─── -->
            <div id="kia-sub-pane-kb" class="kia-subtab-pane" style="display:none;">
              
              <div style="background:#faf5ff;border:1px solid #e9d5ff;border-radius:8px;padding:12px;margin-bottom:14px;">
                <div style="font-size:12px;font-weight:700;color:#6b21a8;margin-bottom:8px;display:flex;align-items:center;gap:6px;">
                  <i class="fas fa-venus-mars"></i>
                  <span>Pelayanan Kontrasepsi / KB</span>
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(160px, 1fr));gap:8px;font-size:11.5px;">
                  <div>
                    <label style="font-weight:600;color:#6b21a8;margin-bottom:2px;display:block;">Tanggal Pelayanan</label>
                    <input type="date" id="kbTgl" class="form-control" value="<?= date('Y-m-d') ?>" style="font-size:11.5px;padding:3px 6px;height:28px;">
                  </div>
                  <div>
                    <label style="font-weight:600;color:#6b21a8;margin-bottom:2px;display:block;">Jenis Kontrasepsi / KB</label>
                    <select id="kbJenis" class="form-control" style="font-size:11.5px;padding:3px 6px;height:28px;">
                      <option value="Suntik 1 Bulan">Suntik 1 Bulan (Kombinasi)</option>
                      <option value="Suntik 3 Bulan (Depo)">Suntik 3 Bulan (Depo Progestin)</option>
                      <option value="Pil KB Kombinasi">Pil KB Kombinasi</option>
                      <option value="Pil KB Progestin (Minipil)">Pil KB Progestin (Minipil / Ibu Menyusui)</option>
                      <option value="IUD / AKDR (Copper-T)">IUD / AKDR (Copper-T)</option>
                      <option value="Implan 2 Batang">Implan 2 Batang</option>
                      <option value="Kondom">Kondom</option>
                      <option value="MOW / Steril Wanita">MOW / Tubektomi</option>
                      <option value="MOP / Vasektomi">MOP / Vasektomi</option>
                    </select>
                  </div>
                  <div>
                    <label style="font-weight:600;color:#6b21a8;margin-bottom:2px;display:block;">Tgl Kembali / Kunjungan Ulang</label>
                    <input type="date" id="kbTglKembali" class="form-control" style="font-size:11.5px;padding:3px 6px;height:28px;">
                  </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:8px;font-size:11px;">
                  <div>
                    <label style="font-weight:600;color:#6b21a8;display:block;">Keluhan / Alasan KB:</label>
                    <input type="text" id="kbKeluhan" class="form-control" placeholder="cth: Ingin menjarangkan kehamilan" style="font-size:11px;padding:3px 6px;height:26px;">
                  </div>
                  <div>
                    <label style="font-weight:600;color:#6b21a8;display:block;">Efek Samping / Riwayat:</label>
                    <input type="text" id="kbEfekSamping" class="form-control" placeholder="cth: Flek ringan, TD stabil" style="font-size:11px;padding:3px 6px;height:26px;">
                  </div>
                </div>

                <div style="display:flex;justify-content:flex-end;margin-top:10px;">
                  <button type="button" onclick="saveKiaKbData()" class="btn btn-primary" style="background:#7c3aed;border-color:#7c3aed;font-weight:700;font-size:11.5px;padding:5px 14px;display:inline-flex;align-items:center;gap:4px;">
                    <i class="fas fa-save"></i> Simpan Pelayanan KB
                  </button>
                </div>
              </div>

              <!-- Tabel Riwayat Pelayanan KB -->
              <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;">
                <div style="padding:8px 12px;background:#f8fafc;border-bottom:1px solid #e2e8f0;font-size:12px;font-weight:700;color:#334155;">
                  <i class="fas fa-history text-primary"></i> Riwayat Pelayanan KB Pasien Ini
                </div>
                <table class="table table-bordered mb-0" style="font-size:11.5px;margin:0;">
                  <thead style="background:#f1f5f9;">
                    <tr>
                      <th style="width:5%;text-align:center;">#</th>
                      <th style="width:20%;">Tanggal</th>
                      <th style="width:30%;">Metode Kontrasepsi</th>
                      <th style="width:20%;">Tgl Kembali</th>
                      <th style="width:25%;">Petugas</th>
                    </tr>
                  </thead>
                  <tbody id="tableBodyKb">
                    <tr><td colspan="5" style="text-align:center;color:#94a3b8;padding:14px;">Memuat riwayat KB...</td></tr>
                  </tbody>
                </table>
              </div>

            </div>

          </div>

        </div>
      </div>

    </div><!-- /.left column -->

    <!-- ─── Right Column: Actions & Riwayat Medis ──────────── -->
    <div style="display:flex;flex-direction:column;gap:16px;">

      <!-- Save Actions -->
      <div class="card" style="border-top:4px solid var(--primary-600);">
        <div class="card-body" style="padding:16px;display:flex;flex-direction:column;gap:10px;">
          <input type="hidden" name="simpan_soap" value="1">
          <input type="hidden" name="selesaikan_periksa" id="hidden_selesaikan_periksa" value="0">
          <button type="submit" onclick="document.getElementById('hidden_selesaikan_periksa').value='1'" class="btn btn-primary" style="justify-content:center;padding:11px;font-weight:700;font-size:13px;">
            <i class="fas fa-check-double"></i> Simpan & Selesaikan
          </button>
          <button type="submit" onclick="document.getElementById('hidden_selesaikan_periksa').value='0'" class="btn btn-outline-primary" style="justify-content:center;padding:8px;font-size:12.5px;">
            <i class="fas fa-save"></i> Simpan Draft EMR
          </button>
          <a href="<?= BASE_URL ?>modules/rekam_medis/index.php" class="btn btn-secondary" style="justify-content:center;padding:8px;font-size:12px;">
            <i class="fas fa-arrow-left"></i> Kembali ke Antrian
          </a>
        </div>
      </div>

      <!-- SOAP Hari Ini (Kunjungan Ini) -->
      <div class="card" style="box-shadow:0 2px 8px rgba(0,0,0,0.05);border-radius:10px;border:1px solid #e2e8f0;overflow:hidden;">
        <div class="card-header" style="background:#f8fafc;padding:10px 14px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #e2e8f0;">
          <div class="card-title" style="font-size:12.5px;font-weight:700;display:flex;align-items:center;gap:6px;">
            <i class="fas fa-notes-medical" style="color:#0d9488;"></i> SOAP Hari Ini
            <span class="badge badge-primary" style="font-size:10px;padding:1px 6px;border-radius:99px;"><?= count($today_soaps) ?></span>
          </div>
          <button type="button" onclick="resetSoapFormToBlank()" class="btn btn-xs btn-outline" style="font-size:10.5px;padding:3px 8px;border-radius:6px;border-color:#cbd5e1;color:#0f766e;display:inline-flex;align-items:center;gap:4px;" title="Kosongkan form untuk input baru">
            <i class="fas fa-plus"></i> Form Baru
          </button>
        </div>
        <div class="card-body" style="padding:0;max-height:220px;overflow-y:auto;" id="todaySoapListContainer">
          <?php if (empty($today_soaps)): ?>
            <div style="font-size:11.5px;color:#94a3b8;text-align:center;padding:16px 12px;">
              <i class="fas fa-edit" style="font-size:18px;margin-bottom:4px;color:#cbd5e1;display:block;"></i>
              Belum ada entri SOAP hari ini.<br><span style="font-size:10.5px;">Form masih kosong, silakan isi data.</span>
            </div>
          <?php else: ?>
            <div style="display:flex;flex-direction:column;">
              <?php foreach ($today_soaps as $idx => $ts): ?>
                <div class="soap-today-item" onclick="loadSoapDataToForm(<?= $idx ?>)" style="padding:8px 12px;border-bottom:1px solid #f1f5f9;cursor:pointer;transition:all 0.15s ease;" onmouseover="this.style.background='#f0fdf4'" onmouseout="this.style.background='transparent'" title="Klik untuk memuat data SOAP ini ke form">
                  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2px;">
                    <span style="font-size:11.5px;font-weight:700;color:#0f766e;display:flex;align-items:center;gap:4px;">
                      <i class="fas fa-user-edit" style="font-size:10px;"></i> <?= htmlspecialchars($ts['nama_petugas']) ?>
                    </span>
                    <span style="font-size:10.5px;color:#64748b;font-weight:600;"><?= substr($ts['jam_rawat'], 0, 5) ?></span>
                  </div>
                  <div style="font-size:11px;color:#475569;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                    <strong style="color:#e11d48;">S:</strong> <?= htmlspecialchars($ts['keluhan'] ?: '-') ?>
                  </div>
                  <?php if (!empty($ts['penilaian'])): ?>
                    <div style="font-size:10.5px;color:#059669;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:1px;">
                      <strong>A:</strong> <?= htmlspecialchars($ts['penilaian']) ?>
                    </div>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Riwayat Medis Sebelumnya (Clickable untuk Popup Lengkap) -->
      <div class="card" style="box-shadow:0 2px 8px rgba(0,0,0,0.05);border-radius:10px;border:1px solid #e2e8f0;overflow:hidden;">
        <div class="card-header" style="background:#f8fafc;padding:11px 14px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #e2e8f0;">
          <div class="card-title" style="font-size:12.5px;font-weight:700;display:flex;align-items:center;gap:6px;">
            <i class="fas fa-history" style="color:#0d9488;"></i> Riwayat Medis Lalu
          </div>
          <span style="font-size:10px;color:#0d9488;background:#ccfbf1;padding:2px 7px;border-radius:10px;font-weight:600;">Klik untuk detail</span>
        </div>
        <div class="card-body" style="padding:0;max-height:460px;overflow-y:auto;">
          <?php if (empty($history_list)): ?>
            <div style="font-size:12px;color:var(--gray-400);text-align:center;padding:20px 16px;">
              <i class="fas fa-user-clock" style="font-size:24px;margin-bottom:8px;color:#cbd5e1;display:block;"></i>
              Kunjungan pertama pasien ini
            </div>
          <?php else: ?>
            <?php foreach ($history_list as $h): ?>
              <div class="riwayat-lalu-item"
                   onclick="openDetailRiwayatMedis('<?= htmlspecialchars($h['no_rawat']) ?>')"
                   title="Klik untuk membuka riwayat lengkap kunjungan ini">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:3px;">
                  <span style="font-size:12px;font-weight:700;color:var(--gray-800);">
                    <?= tgl_indo($h['tgl_registrasi']) ?>
                  </span>
                  <span class="badge badge-detail-btn">
                    <i class="fas fa-external-link-alt" style="font-size:8.5px;"></i> Detail
                  </span>
                </div>
                <div style="font-size:11px;color:#64748b;display:flex;align-items:center;gap:4px;">
                  <i class="fas fa-clinic-medical" style="color:#0d9488;font-size:10px;"></i>
                  <span><?= htmlspecialchars($h['nm_poli'] ?: 'POLI UMUM') ?></span>
                  <?php if (!empty($h['nm_dokter'])): ?>
                    <span style="color:#cbd5e1;">&bull;</span>
                    <span style="color:#475569;"><?= htmlspecialchars($h['nm_dokter']) ?></span>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

    </div>

  </div>
</form>

<script>
// ─── Tab Switching Logic with Dynamic Animation & Ripple ──────
function createTabClickRipple(btn) {
  if (!btn) return;
  const ripple = document.createElement('span');
  ripple.className = 'tab-ripple-effect';
  btn.appendChild(ripple);
  setTimeout(() => { if (ripple.parentNode) ripple.parentNode.removeChild(ripple); }, 650);
}

function switchClinicalTab(tabName) {
  document.querySelectorAll('.clinical-tab-pane').forEach(el => {
    el.style.display = 'none';
    el.classList.remove('clinical-tab-pane-animate');
  });
  document.querySelectorAll('.clinical-tab-btn').forEach(el => {
    el.classList.remove('active');
    el.classList.add('btn-secondary');
  });

  const activePane = document.getElementById('tab-pane-' + tabName);
  const activeBtn  = document.getElementById('tab-btn-' + tabName);

  if (activePane) {
    activePane.style.display = 'block';
    // Trigger smooth fade-slide entrance animation
    void activePane.offsetWidth;
    activePane.classList.add('clinical-tab-pane-animate');
  }
  if (activeBtn) {
    activeBtn.classList.remove('btn-secondary');
    activeBtn.classList.add('active');
    createTabClickRipple(activeBtn);
  }

  if (tabName === 'odontogram') {
    const perm = document.getElementById('odontRowUpperPerm');
    if (perm && perm.children.length === 0) {
      if (typeof initOdontogramChart === 'function') initOdontogramChart();
      if (typeof loadOdontogramData === 'function') loadOdontogramData();
    }
  }

  localStorage.setItem('periksa_active_tab', tabName);
}


// Restore last tab on load & trigger Task 4 in background
document.addEventListener('DOMContentLoaded', () => {
  const poliCode = '<?= $pasien['kd_poli'] ?? '' ?>';
  let defaultTab = localStorage.getItem('periksa_active_tab');
  if (!defaultTab) {
    if (poliCode === 'GIG' || poliCode === 'POL02') {
      defaultTab = 'odontogram';
    } else if (['KIA', 'POL04', 'ANA', 'POL05'].includes(poliCode)) {
      defaultTab = 'kia';
    } else {
      defaultTab = 'soap';
    }
  }
  switchClinicalTab(defaultTab);
  calcBmi();

  // Inisialisasi Modul Odontogram & KIA
  initOdontogramChart();
  loadOdontogramData();
  loadKiaAncData();
  loadKiaKmsData();
  loadKiaImunisasi();
  loadKiaKb();

  // Non-blocking trigger BPJS Task 4 di background tanpa memperlambat loading halaman
  fetch('ajax.php?action=trigger_task4&no_rawat=' + encodeURIComponent('<?= $rawat_esc ?>'), {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  }).catch(() => {});
});

// ─── Data SOAP Hari Ini (Kunjungan Ini) ───────────────────────
const todaySoapsData = <?= json_encode($today_soaps) ?>;

function loadSoapDataToForm(index) {
  const s = todaySoapsData[index];
  if (!s) return;

  const setVal = (name, val) => {
    const el = document.querySelector(`[name="${name}"]`);
    if (el) el.value = val || '';
  };

  setVal('tensi', s.tensi);
  setVal('nadi', s.nadi);
  setVal('suhu_tubuh', s.suhu_tubuh);
  setVal('respirasi', s.respirasi);
  setVal('spo2', s.spo2);
  setVal('kesadaran', s.kesadaran || 'Compos Mentis');
  setVal('tinggi', s.tinggi);
  setVal('berat', s.berat);
  setVal('alergi', s.alergi);
  setVal('gcs', s.gcs);
  setVal('lingkar_perut', s.lingkar_perut);
  setVal('keluhan', s.keluhan);
  setVal('pemeriksaan', s.pemeriksaan);
  setVal('penilaian', s.penilaian);
  setVal('rtl', s.rtl);
  setVal('instruksi', s.instruksi);
  setVal('evaluasi', s.evaluasi);

  calcBmi();

  const badge = document.getElementById('formSoapStatusBadge');
  if (badge) {
    badge.innerHTML = `<span class="badge" style="background:#ccfbf1;color:#0f766e;font-weight:700;padding:2px 8px;border-radius:4px;"><i class="fas fa-check-circle"></i> Memuat: ${s.nama_petugas || ''} (${(s.jam_rawat || '').substring(0,5)})</span>`;
  }

  // Highlight selected item in sidebar
  document.querySelectorAll('.soap-today-item').forEach((el, i) => {
    if (i === index) {
      el.style.background = '#e6fffa';
      el.style.borderLeft = '3px solid #0d9488';
    } else {
      el.style.background = 'transparent';
      el.style.borderLeft = 'none';
    }
  });

  switchClinicalTab('soap');
}

function resetSoapFormToBlank() {
  const fields = ['tensi', 'nadi', 'suhu_tubuh', 'respirasi', 'spo2', 'tinggi', 'berat', 'alergi', 'gcs', 'lingkar_perut', 'keluhan', 'pemeriksaan', 'penilaian', 'rtl', 'instruksi', 'evaluasi'];
  fields.forEach(f => {
    const el = document.querySelector(`[name="${f}"]`);
    if (el) el.value = '';
  });
  const kes = document.querySelector('[name="kesadaran"]');
  if (kes) kes.value = 'Compos Mentis';

  const outBmi = document.getElementById('outputBmi');
  if (outBmi) outBmi.value = '';

  const badge = document.getElementById('formSoapStatusBadge');
  if (badge) {
    badge.innerHTML = `<span style="color:#64748b;"><i class="fas fa-pencil-alt"></i> Form Baru (Kosong)</span>`;
  }

  document.querySelectorAll('.soap-today-item').forEach(el => {
    el.style.background = 'transparent';
    el.style.borderLeft = 'none';
  });
}

// ─── Auto Calculate BMI ───────────────────────────────────────
const inputTb = document.getElementById('inputTb');
const inputBb = document.getElementById('inputBb');
const outputBmi = document.getElementById('outputBmi');

function calcBmi() {
  const tb = parseFloat(inputTb.value) / 100;
  const bb = parseFloat(inputBb.value);
  if (tb > 0 && bb > 0) {
    const bmi = (bb / (tb * tb)).toFixed(1);
    let cat = '';
    if (bmi < 18.5) cat = ' (Kurus)';
    else if (bmi <= 24.9) cat = ' (Normal)';
    else if (bmi <= 29.9) cat = ' (Kelebihan)';
    else cat = ' (Obesitas)';
    outputBmi.value = bmi + cat;
  } else {
    outputBmi.value = '';
  }
}

inputTb.addEventListener('input', calcBmi);
inputBb.addEventListener('input', calcBmi);

// ─── Autocomplete ICD-10 ──────────────────────────────────────
const inputIcd = document.getElementById('inputIcd10');
const boxIcd   = document.getElementById('icd10Results');
let icdTimer   = null;

inputIcd.addEventListener('input', () => {
  clearTimeout(icdTimer);
  const q = inputIcd.value.trim();
  if (q.length < 1) {
    boxIcd.style.display = 'none';
    return;
  }
  icdTimer = setTimeout(() => {
    fetch(`<?= BASE_URL ?>modules/rekam_medis/ajax.php?action=cari_icd10&q=${encodeURIComponent(q)}`, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(res => {
      if (res.data && res.data.length > 0) {
        boxIcd.innerHTML = res.data.map(p => {
          const isTacc = typeof isDiagnosaTACC === 'function' ? isDiagnosaTACC(p.kd_penyakit) : false;
          const badgeTacc = isTacc ? '<span style="font-size:9.5px;background:#fef3c7;color:#b45309;border:1px solid #fde68a;padding:1px 6px;border-radius:3px;font-weight:800;margin-left:6px;"><i class="fas fa-exclamation-triangle"></i> TACC (144 Non-Spesialistik)</span>' : '';
          return `
            <div style="padding:8px 12px;border-bottom:1px solid #f1f5f9;cursor:pointer;display:flex;align-items:center;justify-content:space-between;"
                 onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='#fff'"
                 onclick="pilihIcd('${p.kd_penyakit}', '${p.nm_penyakit.replace(/'/g, "\\'")}')">
              <div>
                <strong style="color:var(--primary-700);">${p.kd_penyakit}</strong> &mdash; ${p.nm_penyakit}
                ${badgeTacc}
              </div>
              <i class="fas fa-plus text-muted" style="font-size:11px;"></i>
            </div>
          `;
        }).join('');
        boxIcd.style.display = 'block';
      } else {
        boxIcd.innerHTML = '<div style="padding:10px 14px;color:var(--gray-400);font-size:12px;">Penyakit tidak ditemukan</div>';
        boxIcd.style.display = 'block';
      }
    });
  }, 250);
});

function pilihIcd(kd, nm) {
  document.getElementById('selectedKdPenyakit').value = kd;
  inputIcd.value = `${kd} - ${nm}`;
  boxIcd.style.display = 'none';

  if (typeof isDiagnosaTACC === 'function' && isDiagnosaTACC(kd)) {
    showTaccWarningModal({
      kdDiag: kd,
      nmDiag: nm
    });
  }
}

function tambahDiagnosaPasien() {
  const kd = document.getElementById('selectedKdPenyakit').value;
  const prio = document.getElementById('selectPrioritas').value;
  const status = document.getElementById('selectStatusPenyakit').value;

  if (!kd) {
    showToast('Pilih diagnosa ICD-10 terlebih dahulu dari hasil pencarian', 'warning');
    return;
  }

  const formData = new FormData();
  formData.append('action', 'tambah_diagnosa');
  formData.append('no_rawat', '<?= $no_rawat ?>');
  formData.append('kd_penyakit', kd);
  formData.append('prioritas', prio);
  formData.append('status_penyakit', status);

  fetch('<?= BASE_URL ?>modules/rekam_medis/ajax.php', {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    body: formData
  })
  .then(r => r.json())
  .then(res => {
    if (res.success) {
      showToast('Diagnosa berhasil ditambahkan', 'success');
      localStorage.setItem('periksa_active_tab', 'diagnosa');
      setTimeout(() => location.reload(), 400);
    } else {
      showToast(res.message || 'Gagal menambahkan diagnosa', 'danger');
    }
  });
}

function hapusDiagnosaPasien(kd) {
  if (!confirm('Hapus diagnosa ini?')) return;
  const formData = new FormData();
  formData.append('action', 'hapus_diagnosa');
  formData.append('no_rawat', '<?= $no_rawat ?>');
  formData.append('kd_penyakit', kd);

  fetch('<?= BASE_URL ?>modules/rekam_medis/ajax.php', {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    body: formData
  })
  .then(r => r.json())
  .then(res => {
    if (res.success) {
      showToast('Diagnosa berhasil dihapus', 'info');
      localStorage.setItem('periksa_active_tab', 'diagnosa');
      setTimeout(() => location.reload(), 400);
    }
  });
}

// ─── Autocomplete Tindakan / Prosedur ─────────────────────────
const inputTindakan = document.getElementById('inputTindakan');
const boxTindakan   = document.getElementById('tindakanResults');
let tindakanTimer   = null;

inputTindakan.addEventListener('input', () => {
  clearTimeout(tindakanTimer);
  const q = inputTindakan.value.trim();
  if (q.length < 1) {
    boxTindakan.style.display = 'none';
    return;
  }
  tindakanTimer = setTimeout(() => {
    fetch(`<?= BASE_URL ?>modules/rekam_medis/ajax.php?action=cari_tindakan&q=${encodeURIComponent(q)}`, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(res => {
      if (res.data && res.data.length > 0) {
        boxTindakan.innerHTML = res.data.map(t => `
          <div style="padding:8px 12px;border-bottom:1px solid #f1f5f9;cursor:pointer;display:flex;justify-content:space-between;align-items:center;"
               onmouseover="this.style.background='#fdf2f8'" onmouseout="this.style.background='#fff'"
               onclick="pilihTindakan('${t.kd_jenis_prw}', '${t.nm_perawatan.replace(/'/g, "\\'")}', ${t.total_byrdr}, ${t.total_byrpr}, ${t.total_byrdrpr})">
            <div>
              <strong style="color:#0f172a;">${t.nm_perawatan}</strong>
              <div style="font-size:11px;color:#64748b;">Kode: <code>${t.kd_jenis_prw}</code></div>
            </div>
            <div style="text-align:right;">
              <div style="font-size:12px;font-weight:700;color:#db2777;">Rp ${parseFloat(t.total_byrdr).toLocaleString('id-ID')}</div>
              <span style="font-size:10px;color:#64748b;">(Dokter)</span>
            </div>
          </div>
        `).join('');
        boxTindakan.style.display = 'block';
      } else {
        boxTindakan.innerHTML = '<div style="padding:10px 14px;color:var(--gray-400);font-size:12px;">Tindakan tidak ditemukan</div>';
        boxTindakan.style.display = 'block';
      }
    });
  }, 250);
});

function pilihTindakan(kd, nm, dr, pr, drpr) {
  document.getElementById('selectedKodeTindakan').value = kd;
  document.getElementById('selectedTarifDr').value = dr;
  document.getElementById('selectedTarifPr').value = pr;
  document.getElementById('selectedTarifDrPr').value = drpr;
  inputTindakan.value = `${kd} - ${nm}`;
  boxTindakan.style.display = 'none';
}

function onPelaksanaChange(val) {
  const colDr = document.getElementById('colDokter');
  const colPr = document.getElementById('colPetugas');

  if (val === 'dr') {
    colDr.style.display = 'block';
    colPr.style.display = 'none';
  } else if (val === 'pr') {
    colDr.style.display = 'none';
    colPr.style.display = 'block';
  } else {
    colDr.style.display = 'block';
    colPr.style.display = 'block';
  }
}

function simpanTindakanPasien() {
  const kd = document.getElementById('selectedKodeTindakan').value;
  const pelaksana = document.getElementById('selectPelaksana').value;
  const kd_dr = document.getElementById('selectTindakanDokter').value;
  const nip = document.getElementById('selectTindakanPetugas').value;

  if (!kd) {
    showToast('Pilih tindakan terlebih dahulu dari daftar pencarian', 'warning');
    return;
  }
  if (pelaksana === 'pr' && !nip) {
    showToast('Pilih petugas / perawat yang melakukan tindakan', 'warning');
    return;
  }
  if (pelaksana === 'drpr' && (!kd_dr || !nip)) {
    showToast('Pilih dokter dan petugas pendamping', 'warning');
    return;
  }

  const fd = new FormData();
  fd.append('action', 'simpan_tindakan');
  fd.append('no_rawat', '<?= $no_rawat ?>');
  fd.append('kd_jenis_prw', kd);
  fd.append('pelaksana', pelaksana);
  fd.append('kd_dokter', kd_dr);
  fd.append('nip', nip);

  fetch('<?= BASE_URL ?>modules/rekam_medis/ajax.php', {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    body: fd
  })
  .then(r => r.json())
  .then(res => {
    if (res.success) {
      showToast('Tindakan berhasil ditambahkan', 'success');
      localStorage.setItem('periksa_active_tab', 'tindakan');
      setTimeout(() => location.reload(), 400);
    } else {
      showToast(res.message || 'Gagal menyimpan tindakan', 'danger');
    }
  });
}

function hapusTindakanPasien(kd, pelaksana, tgl, jam) {
  if (!confirm('Hapus tindakan medis ini?')) return;
  const fd = new FormData();
  fd.append('action', 'hapus_tindakan');
  fd.append('no_rawat', '<?= $no_rawat ?>');
  fd.append('kd_jenis_prw', kd);
  fd.append('pelaksana', pelaksana);
  fd.append('tgl_perawatan', tgl);
  fd.append('jam_rawat', jam);

  fetch('<?= BASE_URL ?>modules/rekam_medis/ajax.php', {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    body: fd
  })
  .then(r => r.json())
  .then(res => {
    if (res.success) {
      showToast('Tindakan berhasil dihapus', 'info');
      localStorage.setItem('periksa_active_tab', 'tindakan');
      setTimeout(() => location.reload(), 400);
    }
  });
}

// ─── Autocomplete Resep Obat ──────────────────────────────────
const inputObat = document.getElementById('inputObat');
const boxObat   = document.getElementById('obatResults');
let obatTimer   = null;

inputObat.addEventListener('input', () => {
  clearTimeout(obatTimer);
  const q = inputObat.value.trim();
  if (q.length < 1) {
    boxObat.style.display = 'none';
    return;
  }
  obatTimer = setTimeout(() => {
    fetch(`<?= BASE_URL ?>modules/rekam_medis/ajax.php?action=cari_obat&q=${encodeURIComponent(q)}`, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(res => {
      if (res.data && res.data.length > 0) {
        boxObat.innerHTML = res.data.map(o => `
          <div style="padding:8px 12px;border-bottom:1px solid #f1f5f9;cursor:pointer;display:flex;justify-content:space-between;align-items:center;"
               onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='#fff'"
               onclick="pilihObat('${o.kode_brng}', '${o.nama_brng.replace(/'/g, "\\'")}')">
            <div>
              <strong style="color:var(--gray-900);">${o.nama_brng}</strong>
              <div style="font-size:11px;color:var(--gray-500);">Kode: ${o.kode_brng} &bull; Satuan: ${o.satuan || 'Pcs'}</div>
            </div>
            <div style="text-align:right;">
              <span class="badge badge-${parseInt(o.stok) > 5 ? 'success' : 'danger'}" style="font-size:10px;">Stok: ${o.stok}</span>
            </div>
          </div>
        `).join('');
        boxObat.style.display = 'block';
      } else {
        boxObat.innerHTML = '<div style="padding:10px 14px;color:var(--gray-400);font-size:12px;">Obat tidak ditemukan / Stok kosong</div>';
        boxObat.style.display = 'block';
      }
    });
  }, 250);
});

function pilihObat(kode_brng, nama_brng) {
  document.getElementById('selectedKodeBrng').value = kode_brng;
  inputObat.value = nama_brng;
  boxObat.style.display = 'none';
  document.getElementById('inputAturanPakai').focus();
}

function tambahObatResep() {
  const kode_brng = document.getElementById('selectedKodeBrng').value;
  const jml = document.getElementById('inputJmlObat').value;
  const aturan = document.getElementById('inputAturanPakai').value.trim();

  if (!kode_brng) {
    showToast('Pilih obat terlebih dahulu dari daftar pencarian', 'warning');
    return;
  }

  const formData = new FormData();
  formData.append('action', 'tambah_item_resep');
  formData.append('no_rawat', '<?= $no_rawat ?>');
  formData.append('kd_dokter', '<?= $pasien['kd_dokter'] ?: "DR001" ?>');
  formData.append('kode_brng', kode_brng);
  formData.append('jml', jml);
  formData.append('aturan_pakai', aturan);

  fetch('<?= BASE_URL ?>modules/rekam_medis/ajax.php', {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    body: formData
  })
  .then(r => r.json())
  .then(res => {
    if (res.success) {
      showToast('Obat berhasil dimasukkan ke resep', 'success');
      localStorage.setItem('periksa_active_tab', 'resep');
      setTimeout(() => location.reload(), 400);
    } else {
      showToast(res.message || 'Gagal menambahkan resep', 'danger');
    }
  });
}

function hapusItemResep(no_resep, kode_brng) {
  if (!confirm('Hapus obat ini dari resep?')) return;
  const formData = new FormData();
  formData.append('action', 'hapus_item_resep');
  formData.append('no_resep', no_resep);
  formData.append('kode_brng', kode_brng);

  fetch('<?= BASE_URL ?>modules/rekam_medis/ajax.php', {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    body: formData
  })
  .then(r => r.json())
  .then(res => {
    if (res.success) {
      showToast('Item resep berhasil dihapus', 'info');
      localStorage.setItem('periksa_active_tab', 'resep');
      localStorage.setItem('resep_sub_tab', 'nonracik');
      setTimeout(() => location.reload(), 400);
    }
  });
}

// ─── Sub-Tab Navigation for E-Resep (Non-Racik vs Racikan) ────
function switchResepSubTab(subTab) {
  const btnNon = document.getElementById('btnTabNonRacik');
  const btnRac = document.getElementById('btnTabRacik');
  const paneNon = document.getElementById('subpane-nonracik');
  const paneRac = document.getElementById('subpane-racik');

  if (subTab === 'racik') {
    if (btnNon) btnNon.className = 'btn btn-sm btn-outline-primary';
    if (btnRac) btnRac.className = 'btn btn-sm btn-primary active';
    if (paneNon) paneNon.style.display = 'none';
    if (paneRac) paneRac.style.display = 'block';
    localStorage.setItem('resep_sub_tab', 'racik');
    if (racikBahanItems.length === 0) {
      renderRacikBahanTable();
    }
  } else {
    if (btnNon) btnNon.className = 'btn btn-sm btn-primary active';
    if (btnRac) btnRac.className = 'btn btn-sm btn-outline-primary';
    if (paneNon) paneNon.style.display = 'block';
    if (paneRac) paneRac.style.display = 'none';
    localStorage.setItem('resep_sub_tab', 'nonracik');
  }
}

// ─── Racikan Dynamic Ingredients Manager ──────────────────────
let racikBahanItems = [];
let bahanSearchTimer = null;

function searchBahanObatPicker(q) {
  clearTimeout(bahanSearchTimer);
  const box = document.getElementById('bahanSearchResults');
  if (!box) return;
  if (!q || q.trim().length < 1) {
    box.style.display = 'none';
    return;
  }
  bahanSearchTimer = setTimeout(() => {
    fetch(`<?= BASE_URL ?>modules/rekam_medis/ajax.php?action=cari_obat&q=${encodeURIComponent(q.trim())}`, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(res => {
      if (res.data && res.data.length > 0) {
        box.innerHTML = res.data.map(o => `
          <div style="padding:9px 14px;border-bottom:1px solid #f1f5f9;cursor:pointer;display:flex;justify-content:space-between;align-items:center;"
               onmouseover="this.style.background='#f0fdf4'" onmouseout="this.style.background='#fff'"
               onclick="pilihBahanObatPicker('${o.kode_brng}', '${o.nama_brng.replace(/'/g, "\\'")}', '${o.satuan || 'Tab'}', '${o.stok}')">
            <div>
              <strong style="color:#0f172a;font-size:12.5px;">${o.nama_brng}</strong>
              <div style="font-size:11px;color:#64748b;margin-top:2px;">Kode: ${o.kode_brng} &bull; Satuan: ${o.satuan || 'Tab'}</div>
            </div>
            <span class="badge badge-${parseInt(o.stok) > 5 ? 'success' : 'danger'}" style="font-size:11px;font-weight:700;padding:4px 8px;">Stok: ${o.stok}</span>
          </div>
        `).join('');
        box.style.display = 'block';
      } else {
        box.innerHTML = '<div style="padding:12px;color:#94a3b8;font-size:12px;text-align:center;">Obat tidak ditemukan</div>';
        box.style.display = 'block';
      }
    });
  }, 200);
}

function pilihBahanObatPicker(kode, nama, satuan, stok) {
  document.getElementById('selectedBahanKode').value = kode;
  document.getElementById('selectedBahanNama').value = nama;
  document.getElementById('selectedBahanSatuan').value = satuan;
  document.getElementById('selectedBahanStok').value = stok;
  document.getElementById('inputBahanSearch').value = nama;
  document.getElementById('bahanSearchResults').style.display = 'none';
  document.getElementById('inputBahanP1').focus();
}

function tambahBahanKeTabel() {
  const kode = document.getElementById('selectedBahanKode').value;
  const nama = document.getElementById('selectedBahanNama').value;
  const satuan = document.getElementById('selectedBahanSatuan').value || 'Tab';
  const stok = document.getElementById('selectedBahanStok').value || '0';
  const p1 = parseFloat(document.getElementById('inputBahanP1').value) || 1;
  const p2 = parseFloat(document.getElementById('inputBahanP2').value) || 1;

  if (!kode || !nama) {
    showToast('Ketik dan pilih obat bahan terlebih dahulu dari hasil pencarian', 'warning');
    document.getElementById('inputBahanSearch').focus();
    return;
  }

  // Cek jika bahan sudah ada di racikan
  const exists = racikBahanItems.some(it => it.kode_brng === kode);
  if (exists) {
    showToast(`Obat ${nama} sudah ada di dalam racikan ini`, 'warning');
    return;
  }

  racikBahanItems.push({
    kode_brng: kode,
    nama_brng: nama,
    satuan: satuan,
    stok: stok,
    p1: p1,
    p2: p2,
    jml: ''
  });

  renderRacikBahanTable();

  // Reset input pencarian
  document.getElementById('selectedBahanKode').value = '';
  document.getElementById('selectedBahanNama').value = '';
  document.getElementById('selectedBahanSatuan').value = '';
  document.getElementById('selectedBahanStok').value = '';
  document.getElementById('inputBahanSearch').value = '';
  document.getElementById('inputBahanP1').value = '1';
  document.getElementById('inputBahanP2').value = '1';
  document.getElementById('inputBahanSearch').focus();
}

function renderRacikBahanTable() {
  const tbody = document.getElementById('tbody_racik_bahan');
  if (!tbody) return;
  const totalBks = parseFloat(document.getElementById('racik_jml_dr').value) || 10;

  if (racikBahanItems.length === 0) {
    tbody.innerHTML = `
      <tr id="empty_racik_bahan_row">
        <td colspan="6" style="text-align:center;padding:22px;color:#94a3b8;background:#fafafa;">
          <i class="fas fa-mortar-pestle" style="font-size:22px;display:block;margin-bottom:6px;color:#cbd5e1;"></i>
          Belum ada bahan obat dalam racikan ini. Cari nama obat di atas lalu klik <strong>+ Tambah Bahan</strong>.
        </td>
      </tr>
    `;
    return;
  }

  let html = '';
  racikBahanItems.forEach((it, idx) => {
    const calcJml = (it.jml !== '' && it.jml !== undefined) ? it.jml : (Math.round((totalBks * (it.p1 / it.p2)) * 100) / 100);
    html += `
      <tr>
        <td style="text-align:center;font-weight:700;color:#64748b;vertical-align:middle;">${idx + 1}</td>
        <td style="vertical-align:middle;">
          <strong style="font-size:13px;color:#0f172a;">${it.nama_brng}</strong>
          <div style="font-size:11px;color:#64748b;margin-top:2px;">Kode: ${it.kode_brng} &bull; Satuan: ${it.satuan || 'Tab'}</div>
        </td>
        <td style="vertical-align:middle;text-align:center;">
          <div style="display:flex;align-items:center;gap:3px;justify-content:center;">
            <input type="number" class="form-control form-control-sm" step="0.1" min="0.1" value="${it.p1}" style="width:50px;text-align:center;font-size:12px;font-weight:700;" oninput="updateRacikBahanP1(${idx}, this.value)">
            <span style="font-weight:700;color:#64748b;">/</span>
            <input type="number" class="form-control form-control-sm" min="1" value="${it.p2}" style="width:45px;text-align:center;font-size:12px;font-weight:700;" oninput="updateRacikBahanP2(${idx}, this.value)">
            <span style="font-size:11px;color:#64748b;font-weight:600;">Tab</span>
          </div>
        </td>
        <td style="vertical-align:middle;text-align:center;">
          <div style="display:flex;align-items:center;gap:4px;justify-content:center;">
            <input type="number" class="form-control form-control-sm" step="0.1" min="0.1" value="${calcJml}" style="width:75px;text-align:center;font-size:12.5px;font-weight:700;color:#0284c7;background:#f0f9ff;" oninput="updateRacikBahanJml(${idx}, this.value)">
            <span style="font-size:11px;color:#64748b;">${it.satuan || 'Tab'}</span>
          </div>
        </td>
        <td style="text-align:center;vertical-align:middle;">
          <span class="badge badge-${parseInt(it.stok) > 5 ? 'success' : 'danger'}" style="font-size:11px;font-weight:700;padding:4px 7px;">${it.stok}</span>
        </td>
        <td style="text-align:center;vertical-align:middle;">
          <button type="button" class="btn btn-sm btn-outline-danger" onclick="hapusBahanItem(${idx})" style="border:none;background:#fef2f2;color:#ef4444;width:28px;height:28px;border-radius:6px;display:inline-flex;align-items:center;justify-content:center;" title="Hapus bahan ini">
            <i class="fas fa-trash-alt" style="font-size:12px;"></i>
          </button>
        </td>
      </tr>
    `;
  });

  tbody.innerHTML = html;
}

function updateRacikBahanP1(idx, val) {
  if (racikBahanItems[idx]) {
    racikBahanItems[idx].p1 = parseFloat(val) || 1;
    racikBahanItems[idx].jml = '';
    renderRacikBahanTable();
  }
}

function updateRacikBahanP2(idx, val) {
  if (racikBahanItems[idx]) {
    racikBahanItems[idx].p2 = parseFloat(val) || 1;
    racikBahanItems[idx].jml = '';
    renderRacikBahanTable();
  }
}

function updateRacikBahanJml(idx, val) {
  if (racikBahanItems[idx]) {
    racikBahanItems[idx].jml = parseFloat(val) || 0;
  }
}

function hapusBahanItem(idx) {
  racikBahanItems.splice(idx, 1);
  renderRacikBahanTable();
  showToast('Bahan dikeluarkan dari racikan', 'info');
}

function recalcAllRacikBahan() {
  racikBahanItems.forEach(it => it.jml = '');
  renderRacikBahanTable();
}

// Close search dropdown on click outside
document.addEventListener('click', function(e) {
  const box = document.getElementById('bahanSearchResults');
  const input = document.getElementById('inputBahanSearch');
  if (box && input && !box.contains(e.target) && e.target !== input) {
    box.style.display = 'none';
  }
});

function simpanResepRacikan() {
  const namaRacik = document.getElementById('racik_nama').value.trim();
  const kdRacik   = document.getElementById('racik_metode').value;
  const jmlDr     = parseInt(document.getElementById('racik_jml_dr').value) || 10;
  const aturan    = document.getElementById('racik_aturan').value.trim();
  const ket       = document.getElementById('racik_keterangan').value.trim();
  const editNo    = document.getElementById('racik_edit_no_racik').value;

  if (!namaRacik) {
    showToast('Nama racikan wajib diisi (contoh: Puyer Batuk)', 'warning');
    document.getElementById('racik_nama').focus();
    return;
  }
  if (!aturan) {
    showToast('Aturan pakai wajib diisi (contoh: 3 x 1 bungkus)', 'warning');
    document.getElementById('racik_aturan').focus();
    return;
  }

  if (racikBahanItems.length === 0) {
    showToast('Tambahkan minimal 1 bahan obat campuran pada racikan ini', 'warning');
    document.getElementById('inputBahanSearch').focus();
    return;
  }

  const bahanList = racikBahanItems.map(it => {
    const calcJml = (it.jml !== '' && it.jml !== undefined) ? it.jml : (Math.round((jmlDr * (it.p1 / it.p2)) * 100) / 100);
    return {
      kode_brng: it.kode_brng,
      p1: it.p1,
      p2: it.p2,
      kandungan: `${it.p1}/${it.p2} tab`,
      jml: calcJml
    };
  });

  const fd = new FormData();
  fd.append('action', 'tambah_resep_racikan');
  fd.append('no_rawat', '<?= $no_rawat ?>');
  fd.append('kd_dokter', '<?= $pasien['kd_dokter'] ?: "DR001" ?>');
  fd.append('nama_racik', namaRacik);
  fd.append('kd_racik', kdRacik);
  fd.append('jml_dr', jmlDr);
  fd.append('aturan_pakai', aturan);
  fd.append('keterangan', ket);
  fd.append('edit_no_racik', editNo);
  fd.append('bahan', JSON.stringify(bahanList));

  fetch('<?= BASE_URL ?>modules/rekam_medis/ajax.php', {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    body: fd
  })
  .then(r => r.json())
  .then(res => {
    if (res.success) {
      showToast(res.message || 'Resep racikan berhasil disimpan', 'success');
      localStorage.setItem('periksa_active_tab', 'resep');
      localStorage.setItem('resep_sub_tab', 'racik');
      setTimeout(() => location.reload(), 400);
    } else {
      showToast(res.message || 'Gagal menyimpan resep racikan', 'danger');
    }
  });
}

function editResepRacikan(rc) {
  if (!rc) return;
  document.getElementById('racik_edit_no_racik').value = rc.no_racik;
  document.getElementById('racik_nama').value = rc.nama_racik;
  document.getElementById('racik_metode').value = rc.kd_racik;
  document.getElementById('racik_jml_dr').value = rc.jml_dr;
  document.getElementById('racik_aturan').value = rc.aturan_pakai;
  document.getElementById('racik_keterangan').value = rc.keterangan || '';
  
  document.getElementById('racik_form_title').innerHTML = `<i class="fas fa-edit" style="color:#d97706;"></i> Edit Resep Racikan (${rc.nama_racik})`;
  document.getElementById('btn_simpan_racik_text').innerText = 'Simpan Perubahan Racikan';

  racikBahanItems = [];
  if (rc.detail_bahan && rc.detail_bahan.length > 0) {
    rc.detail_bahan.forEach(b => {
      racikBahanItems.push({
        kode_brng: b.kode_brng,
        nama_brng: b.nama_brng,
        satuan: b.satuan || 'Tab',
        stok: b.stok || '-',
        p1: parseFloat(b.p1) || 1,
        p2: parseFloat(b.p2) || 1,
        jml: parseFloat(b.jml) || ''
      });
    });
  }

  renderRacikBahanTable();
  document.getElementById('racik_nama').focus();
}

function resetFormRacikan() {
  document.getElementById('racik_edit_no_racik').value = '';
  document.getElementById('racik_nama').value = '';
  document.getElementById('racik_metode').selectedIndex = 0;
  document.getElementById('racik_jml_dr').value = '10';
  document.getElementById('racik_aturan').value = '';
  document.getElementById('racik_keterangan').value = '';
  document.getElementById('racik_form_title').innerHTML = '<i class="fas fa-mortar-pestle" style="color:#0284c7;"></i> Form Racik Obat Baru (Puyer / Kapsul / Salep / Sirup)';
  document.getElementById('btn_simpan_racik_text').innerText = 'Simpan Resep Racikan';

  racikBahanItems = [];
  renderRacikBahanTable();
}

function hapusResepRacikan(no_resep, no_racik, nama_racik) {
  if (!confirm(`Hapus resep racikan "${nama_racik}" beserta seluruh bahan obatnya?`)) return;
  const fd = new FormData();
  fd.append('action', 'hapus_resep_racikan');
  fd.append('no_resep', no_resep);
  fd.append('no_racik', no_racik);

  fetch('<?= BASE_URL ?>modules/rekam_medis/ajax.php', {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    body: fd
  })
  .then(r => r.json())
  .then(res => {
    if (res.success) {
      showToast('Resep racikan berhasil dihapus', 'info');
      localStorage.setItem('periksa_active_tab', 'resep');
      localStorage.setItem('resep_sub_tab', 'racik');
      setTimeout(() => location.reload(), 400);
    } else {
      showToast(res.message || 'Gagal menghapus racikan', 'danger');
    }
  });
}

// Global click to close dropdowns
document.addEventListener('click', (e) => {
  if (!e.target.closest('#icd10Results') && !e.target.closest('#inputIcd10')) {
    boxIcd.style.display = 'none';
  }
  if (!e.target.closest('#tindakanResults') && !e.target.closest('#inputTindakan')) {
    boxTindakan.style.display = 'none';
  }
  if (!e.target.closest('#obatResults') && !e.target.closest('#inputObat')) {
    boxObat.style.display = 'none';
  }
});

// ─── Modal Detail Riwayat Perawatan Pasien ──────────────────
let currentRiwayatData = null;

function openDetailRiwayatMedis(noRawat) {
  const modal = document.getElementById('modalDetailRiwayat');
  const body  = document.getElementById('modalRiwayatBody');
  const title = document.getElementById('modalRiwayatTitle');
  const sub   = document.getElementById('modalRiwayatSubtitle');
  const btnSalin = document.getElementById('btnSalinSoap');
  const btnCetak = document.getElementById('btnCetakResumeModal');

  modal.style.display = 'flex';
  modal.classList.add('active');
  btnSalin.style.display = 'none';
  btnCetak.style.display = 'none';

  title.innerText = 'Detail Riwayat Perawatan Pasien';
  sub.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Mengambil data kunjungan ${noRawat}...`;

  body.innerHTML = `
    <div style="text-align:center;padding:50px 0;">
      <div class="spinner" style="width:36px;height:36px;border-width:3px;border-color:#0d9488;border-top-color:transparent;"></div>
      <p style="margin-top:14px;color:#64748b;font-size:13px;font-weight:500;">Mengambil seluruh data perawatan pasien...</p>
    </div>
  `;

  fetch('<?= BASE_URL ?>modules/rekam_medis/ajax.php?action=get_detail_riwayat&no_rawat=' + encodeURIComponent(noRawat), {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(r => r.json())
  .then(res => {
    if (!res.success || !res.data) {
      sub.innerHTML = `<span style="color:#fecaca;">Gagal memuat data</span>`;
      body.innerHTML = `
        <div style="text-align:center;padding:36px;background:#fff;border-radius:10px;border:1px dashed #ef4444;">
          <i class="fas fa-exclamation-triangle" style="font-size:32px;color:#ef4444;margin-bottom:10px;"></i>
          <p style="font-weight:600;color:#1e293b;margin:0 0 4px;">Data riwayat tidak ditemukan</p>
          <span style="font-size:12px;color:#64748b;">${res.error || 'Terjadi kesalahan sistem'}</span>
        </div>
      `;
      return;
    }

    currentRiwayatData = res.data;
    renderDetailRiwayatMedis(res.data);

    const reg = res.data.pendaftaran || {};
    sub.innerHTML = `No. Rawat: <strong>${reg.no_rawat || noRawat}</strong> &bull; ${reg.nm_poli || '-'} &bull; ${reg.tgl_registrasi || ''}`;

    btnCetak.href = '<?= BASE_URL ?>modules/rekam_medis/cetak_resume.php?no_rawat=' + encodeURIComponent(noRawat);
    btnCetak.style.display = 'inline-flex';

    if (res.data.soap) {
      btnSalin.style.display = 'inline-flex';
    }
  })
  .catch(err => {
    console.error(err);
    sub.innerHTML = `<span style="color:#fecaca;">Koneksi error</span>`;
    body.innerHTML = `
      <div style="text-align:center;padding:36px;background:#fff;border-radius:10px;border:1px dashed #ef4444;">
        <i class="fas fa-wifi" style="font-size:32px;color:#ef4444;margin-bottom:10px;"></i>
        <p style="font-weight:600;color:#1e293b;margin:0 0 4px;">Gagal menghubungi server</p>
        <span style="font-size:12px;color:#64748b;">Pastikan web server aktif dan koneksi lancar</span>
      </div>
    `;
  });
}

function closeDetailRiwayatMedis() {
  const modal = document.getElementById('modalDetailRiwayat');
  modal.classList.remove('active');
  modal.style.display = 'none';
}

window.addEventListener('click', (e) => {
  const modalDetail = document.getElementById('modalDetailRiwayat');
  const modalCopy   = document.getElementById('modalCopyResep');
  const modalEdit   = document.getElementById('modalEditItemResep');

  if (modalDetail && e.target === modalDetail) closeDetailRiwayatMedis();
  if (modalCopy && e.target === modalCopy) closeModalCopyResep();
  if (modalEdit && e.target === modalEdit) closeEditItemResep();
});

function renderDetailRiwayatMedis(data) {
  const body = document.getElementById('modalRiwayatBody');
  const reg  = data.pendaftaran || {};
  const soap = data.soap || {};
  const diag = data.diagnosa || [];
  const tind = data.tindakan || [];
  const resp = data.resep || null;

  // Calculate IMT
  let imtVal = '-';
  if (soap.tinggi && soap.berat) {
    const tbM = parseFloat(soap.tinggi) / 100;
    const bbKg = parseFloat(soap.berat);
    if (tbM > 0) {
      const imt = (bbKg / (tbM * tbM)).toFixed(1);
      let cat = 'Normal';
      let bg = '#dcfce7';
      let fg = '#15803d';
      if (imt < 18.5) { cat = 'Kurang'; bg = '#fef3c7'; fg = '#b45309'; }
      else if (imt >= 25 && imt < 30) { cat = 'Kelebihan'; bg = '#ffedd5'; fg = '#c2410c'; }
      else if (imt >= 30) { cat = 'Obesitas'; bg = '#fee2e2'; fg = '#b91c1c'; }
      imtVal = `${imt} <span style="font-size:9.5px;padding:2px 5px;border-radius:4px;background:${bg};color:${fg};font-weight:700;">${cat}</span>`;
    }
  }

  // Badges status
  const badgeStts = reg.stts === 'Sudah' 
    ? '<span class="badge" style="background:#dcfce7;color:#15803d;border:1px solid #86efac;font-size:11px;">Selesai Diperiksa</span>'
    : '<span class="badge" style="background:#fef3c7;color:#b45309;border:1px solid #fde68a;font-size:11px;">Sedang Dilayani</span>';

  const badgeBayar = reg.status_bayar === 'Sudah Bayar'
    ? '<span class="badge" style="background:#e0f2fe;color:#0369a1;border:1px solid #bae6fd;font-size:11px;">Lunas</span>'
    : '<span class="badge" style="background:#f1f5f9;color:#475569;border:1px solid #cbd5e1;font-size:11px;">Belum Bayar</span>';

  const badgePenjab = (reg.nm_penjab || '').toLowerCase().includes('bpjs')
    ? '<span class="badge" style="background:#e0e7ff;color:#4338ca;border:1px solid #c7d2fe;font-size:11px;font-weight:700;">' + (reg.nm_penjab || 'BPJS') + '</span>'
    : '<span class="badge" style="background:#f3e8ff;color:#7e22ce;border:1px solid #e9d5ff;font-size:11px;font-weight:700;">' + (reg.nm_penjab || 'Umum') + '</span>';

  let html = '';

  // ─── 1. DATA PENDAFTARAN & KUNJUNGAN ───
  html += `
    <div class="riwayat-section-card">
      <div class="riwayat-section-header">
        <div class="riwayat-section-title">
          <i class="fas fa-id-card-alt" style="color:#0284c7;"></i>
          <span>1. Data Pendaftaran & Kunjungan</span>
        </div>
        <div style="display:flex;gap:6px;">
          ${badgeStts}
          ${badgeBayar}
        </div>
      </div>
      <div class="riwayat-section-body">
        <div class="riwayat-grid-info">
          <div class="riwayat-info-item">
            <div class="riwayat-info-label">No. Rawat</div>
            <div class="riwayat-info-val" style="color:#0f766e;">${reg.no_rawat || '-'}</div>
          </div>
          <div class="riwayat-info-item">
            <div class="riwayat-info-label">No. Registrasi / Antrian</div>
            <div class="riwayat-info-val">No. ${reg.no_reg || '-'}</div>
          </div>
          <div class="riwayat-info-item">
            <div class="riwayat-info-label">Waktu Registrasi</div>
            <div class="riwayat-info-val">${reg.tgl_registrasi || '-'} (${(reg.jam_reg || '').substring(0,5)})</div>
          </div>
          <div class="riwayat-info-item">
            <div class="riwayat-info-label">Poliklinik Tujuan</div>
            <div class="riwayat-info-val">${reg.nm_poli || '-'}</div>
          </div>
          <div class="riwayat-info-item">
            <div class="riwayat-info-label">Dokter Penanggung Jawab</div>
            <div class="riwayat-info-val">${reg.nm_dokter || '-'}</div>
          </div>
          <div class="riwayat-info-item">
            <div class="riwayat-info-label">Jenis Penjamin / Bayar</div>
            <div class="riwayat-info-val">${badgePenjab}</div>
          </div>
        </div>
      </div>
    </div>
  `;

  // ─── 2. CATATAN PERKEMBANGAN & TANDA VITAL (SOAP) ───
  const soapList = (data.soap_list && data.soap_list.length > 0) ? data.soap_list : (data.soap ? [data.soap] : []);
  if (soapList.length === 0) {
    html += `
      <div class="riwayat-section-card">
        <div class="riwayat-section-header">
          <div class="riwayat-section-title">
            <i class="fas fa-stethoscope" style="color:#059669;"></i>
            <span>2. Catatan Perkembangan Pasien (SOAP)</span>
          </div>
        </div>
        <div class="riwayat-section-body" style="text-align:center;color:#94a3b8;padding:20px;">
          <em>Belum ada catatan SOAP pada kunjungan ini</em>
        </div>
      </div>
    `;
  } else {
    soapList.forEach((soapItem, sIdx) => {
      let sImtVal = '-';
      if (soapItem.tinggi && soapItem.berat) {
        const tbM = parseFloat(soapItem.tinggi) / 100;
        const bbKg = parseFloat(soapItem.berat);
        if (tbM > 0) {
          const imt = (bbKg / (tbM * tbM)).toFixed(1);
          let cat = 'Normal';
          let bg = '#dcfce7';
          let fg = '#15803d';
          if (imt < 18.5) { cat = 'Kurang'; bg = '#fef3c7'; fg = '#b45309'; }
          else if (imt >= 25 && imt < 30) { cat = 'Kelebihan'; bg = '#ffedd5'; fg = '#c2410c'; }
          else if (imt >= 30) { cat = 'Obesitas'; bg = '#fee2e2'; fg = '#b91c1c'; }
          sImtVal = `${imt} <span style="font-size:9.5px;padding:2px 5px;border-radius:4px;background:${bg};color:${fg};font-weight:700;">${cat}</span>`;
        }
      }

      const hasVitals = (soapItem.tensi && soapItem.tensi !== '0/0') || (soapItem.suhu_tubuh && soapItem.suhu_tubuh !== '0') || (soapItem.nadi && soapItem.nadi !== '0');

      html += `
        <div class="riwayat-section-card">
          <div class="riwayat-section-header" style="background:#f0fdf4;border-bottom:1px solid #bbf7d0;">
            <div class="riwayat-section-title">
              <i class="fas fa-user-edit" style="color:#0d9488;"></i>
              <span>Catatan SOAP #${sIdx + 1} &bull; <strong style="color:#0f766e;">${soapItem.nama_petugas || '-'}</strong></span>
            </div>
            <span style="font-size:11px;color:#0f766e;font-weight:600;"><i class="fas fa-clock"></i> ${soapItem.jam_rawat ? soapItem.jam_rawat.substring(0,5) : '-'}</span>
          </div>
          <div class="riwayat-section-body">
            
            ${hasVitals ? `
            <div class="vitals-grid-popup" style="margin-bottom:12px;">
              <div class="vitals-card-item">
                <div class="vitals-card-lbl"><i class="fas fa-tint" style="color:#ef4444;"></i> Tensi Darah</div>
                <div class="vitals-card-val">${soapItem.tensi || '-'}</div>
                <span style="font-size:10px;color:#94a3b8;">mmHg</span>
              </div>
              <div class="vitals-card-item">
                <div class="vitals-card-lbl"><i class="fas fa-heart" style="color:#f43f5e;"></i> Nadi</div>
                <div class="vitals-card-val">${soapItem.nadi || '-'}</div>
                <span style="font-size:10px;color:#94a3b8;">x/menit</span>
              </div>
              <div class="vitals-card-item">
                <div class="vitals-card-lbl"><i class="fas fa-thermometer-half" style="color:#f59e0b;"></i> Suhu Tubuh</div>
                <div class="vitals-card-val">${soapItem.suhu_tubuh || '-'}</div>
                <span style="font-size:10px;color:#94a3b8;">°C</span>
              </div>
              <div class="vitals-card-item">
                <div class="vitals-card-lbl"><i class="fas fa-lungs" style="color:#0ea5e9;"></i> Respirasi</div>
                <div class="vitals-card-val">${soapItem.respirasi || '-'}</div>
                <span style="font-size:10px;color:#94a3b8;">x/menit</span>
              </div>
              <div class="vitals-card-item">
                <div class="vitals-card-lbl"><i class="fas fa-lungs-virus" style="color:#8b5cf6;"></i> SpO2</div>
                <div class="vitals-card-val">${soapItem.spo2 || '-'}</div>
                <span style="font-size:10px;color:#94a3b8;">%</span>
              </div>
              <div class="vitals-card-item">
                <div class="vitals-card-lbl"><i class="fas fa-weight" style="color:#059669;"></i> Berat Badan</div>
                <div class="vitals-card-val">${soapItem.berat || '-'}</div>
                <span style="font-size:10px;color:#94a3b8;">kg</span>
              </div>
              <div class="vitals-card-item">
                <div class="vitals-card-lbl"><i class="fas fa-ruler-vertical" style="color:#10b981;"></i> Tinggi Badan</div>
                <div class="vitals-card-val">${soapItem.tinggi || '-'}</div>
                <span style="font-size:10px;color:#94a3b8;">cm</span>
              </div>
              <div class="vitals-card-item">
                <div class="vitals-card-lbl"><i class="fas fa-calculator" style="color:#6366f1;"></i> IMT / BMI</div>
                <div class="vitals-card-val" style="font-size:13.5px;">${sImtVal}</div>
                <span style="font-size:10px;color:#94a3b8;">kg/m²</span>
              </div>
            </div>
            ` : ''}

            <div class="soap-box soap-box-s">
              <div class="soap-lbl" style="color:#b45309;">
                <span style="background:#fef3c7;color:#b45309;padding:2px 7px;border-radius:4px;">[S] Subjektif</span>
                <span>Keluhan Utama & Anamnesis</span>
              </div>
              <div class="soap-txt">${soapItem.keluhan || '<em style="color:#94a3b8;">Tidak ada data keluhan</em>'}</div>
            </div>

            <div class="soap-box soap-box-o">
              <div class="soap-lbl" style="color:#1d4ed8;">
                <span style="background:#dbeafe;color:#1d4ed8;padding:2px 7px;border-radius:4px;">[O] Objektif</span>
                <span>Pemeriksaan Fisik & Penunjang Klinis</span>
              </div>
              <div class="soap-txt">${soapItem.pemeriksaan || '<em style="color:#94a3b8;">Tidak ada data pemeriksaan fisik</em>'}</div>
            </div>

            <div class="soap-box soap-box-a">
              <div class="soap-lbl" style="color:#047857;">
                <span style="background:#d1fae5;color:#047857;padding:2px 7px;border-radius:4px;">[A] Asesmen</span>
                <span>Analisis & Penilaian Klinis Dokter</span>
              </div>
              <div class="soap-txt">${soapItem.penilaian || '<em style="color:#94a3b8;">Tidak ada data penilaian klinis</em>'}</div>
            </div>

            <div class="soap-box soap-box-p">
              <div class="soap-lbl" style="color:#6d28d9;">
                <span style="background:#ede9fe;color:#6d28d9;padding:2px 7px;border-radius:4px;">[P] Plan</span>
                <span>Rencana Penatalaksanaan, Terapi & Tindak Lanjut (RTL)</span>
              </div>
              <div class="soap-txt">${soapItem.rtl || '<em style="color:#94a3b8;">Tidak ada rencana tindak lanjut</em>'}</div>
              ${soapItem.instruksi ? `<div style="margin-top:6px;font-size:11.5px;color:#5b21b6;background:rgba(109,40,217,0.06);padding:6px 10px;border-radius:6px;"><strong>Instruksi Medis:</strong> ${soapItem.instruksi}</div>` : ''}
              ${soapItem.evaluasi ? `<div style="margin-top:4px;font-size:11.5px;color:#475569;background:#f1f5f9;padding:6px 10px;border-radius:6px;"><strong>Evaluasi:</strong> ${soapItem.evaluasi}</div>` : ''}
            </div>

          </div>
        </div>
      `;
    });
  }

  // ─── 4. DIAGNOSA ICD-10 ───
  let diagRows = '';
  if (diag.length === 0) {
    diagRows = `<tr><td colspan="4" style="text-align:center;color:#94a3b8;padding:16px;">Tidak ada data diagnosa ICD-10 pada kunjungan ini</td></tr>`;
  } else {
    diag.forEach(d => {
      const isUtama = d.prioritas == '1';
      const badgePrio = isUtama
        ? '<span class="badge" style="background:#fee2e2;color:#b91c1c;border:1px solid #fca5a5;font-weight:700;">Diagnosa Utama</span>'
        : '<span class="badge" style="background:#f1f5f9;color:#475569;border:1px solid #cbd5e1;">Sekunder</span>';
      
      const badgeKasus = (d.status_penyakit || '').toLowerCase() === 'baru'
        ? '<span class="badge" style="background:#dbeafe;color:#1d4ed8;border:1px solid #93c5fd;">Kasus Baru</span>'
        : '<span class="badge" style="background:#f3f4f6;color:#374151;">Kasus Lama</span>';

      diagRows += `
        <tr>
          <td style="width:140px;">${badgePrio}</td>
          <td style="width:120px;font-weight:700;color:#0f766e;font-family:monospace;font-size:12.5px;">${d.kd_penyakit}</td>
          <td style="font-weight:600;color:#1e293b;">${d.nm_penyakit}</td>
          <td style="width:110px;">${badgeKasus}</td>
        </tr>
      `;
    });
  }

  html += `
    <div class="riwayat-section-card">
      <div class="riwayat-section-header">
        <div class="riwayat-section-title">
          <i class="fas fa-diagnoses" style="color:#7c3aed;"></i>
          <span>4. Klasifikasi Diagnosa Penyakit (ICD-10)</span>
        </div>
        <span style="font-size:11px;color:#64748b;">${diag.length} Diagnosa Terdata</span>
      </div>
      <div class="riwayat-section-body" style="padding:0;">
        <table class="riwayat-table">
          <thead>
            <tr>
              <th>Prioritas</th>
              <th>Kode ICD-10</th>
              <th>Nama Diagnosa / Penyakit</th>
              <th>Status Kasus</th>
            </tr>
          </thead>
          <tbody>
            ${diagRows}
          </tbody>
        </table>
      </div>
    </div>
  `;

  // ─── 5. TINDAKAN & PROSEDUR MEDIS ───
  let tindRows = '';
  if (tind.length === 0) {
    tindRows = `<tr><td colspan="5" style="text-align:center;color:#94a3b8;padding:16px;">Tidak ada tindakan atau prosedur medis yang dicatat</td></tr>`;
  } else {
    tind.forEach((t, i) => {
      const biayaFmt = 'Rp ' + Number(t.biaya_rawat || 0).toLocaleString('id-ID');
      tindRows += `
        <tr>
          <td style="width:40px;text-align:center;color:#94a3b8;">${i + 1}</td>
          <td style="width:110px;font-family:monospace;font-weight:600;color:#475569;">${t.kd_jenis_prw}</td>
          <td style="font-weight:600;color:#1e293b;">${t.nm_perawatan}</td>
          <td style="color:#64748b;">${t.nm_dokter || reg.nm_dokter || '-'}</td>
          <td style="width:130px;font-weight:700;color:#0f766e;text-align:right;">${biayaFmt}</td>
        </tr>
      `;
    });
  }

  html += `
    <div class="riwayat-section-card">
      <div class="riwayat-section-header">
        <div class="riwayat-section-title">
          <i class="fas fa-syringe" style="color:#ea580c;"></i>
          <span>5. Tindakan & Prosedur Medis</span>
        </div>
        <span style="font-size:11px;color:#64748b;">${tind.length} Prosedur Medis</span>
      </div>
      <div class="riwayat-section-body" style="padding:0;">
        <table class="riwayat-table">
          <thead>
            <tr>
              <th style="width:40px;text-align:center;">No.</th>
              <th>Kode</th>
              <th>Nama Tindakan / Layanan</th>
              <th>Pelaksana</th>
              <th style="text-align:right;">Tarif / Biaya</th>
            </tr>
          </thead>
          <tbody>
            ${tindRows}
          </tbody>
        </table>
      </div>
    </div>
  `;

  // ─── 6. RESEP OBAT & TERAPI FARMASI ───
  let resepRows = '';
  const items = resp ? (resp.items || []) : [];
  if (items.length === 0) {
    resepRows = `<tr><td colspan="5" style="text-align:center;color:#94a3b8;padding:16px;">Tidak ada resep obat pada kunjungan ini</td></tr>`;
  } else {
    items.forEach((o, i) => {
      resepRows += `
        <tr>
          <td style="width:40px;text-align:center;color:#94a3b8;">${i + 1}</td>
          <td style="width:110px;font-family:monospace;font-weight:600;color:#475569;">${o.kode_brng || '-'}</td>
          <td style="font-weight:600;color:#1e293b;">${o.nama_brng}</td>
          <td style="width:110px;">
            <span class="badge" style="background:#e0f2fe;color:#0284c7;font-weight:700;">
              ${o.jml} ${o.satuan || 'Item'}
            </span>
          </td>
          <td style="color:#0f766e;font-weight:600;">
            <i class="fas fa-clock" style="margin-right:4px;color:#14b8a6;font-size:10px;"></i>${o.aturan_pakai || '-'}
          </td>
        </tr>
      `;
    });
  }

  html += `
    <div class="riwayat-section-card" style="margin-bottom:0;">
      <div class="riwayat-section-header">
        <div class="riwayat-section-title">
          <i class="fas fa-pills" style="color:#0d9488;"></i>
          <span>6. Terapi Obat & E-Resep Farmasi</span>
        </div>
        ${resp ? `<span style="font-size:11px;color:#0f766e;background:#ccfbf1;padding:2px 8px;border-radius:6px;font-weight:600;">No. Resep: ${resp.no_resep}</span>` : '<span style="font-size:11px;color:#94a3b8;">Tanpa Resep</span>'}
      </div>
      <div class="riwayat-section-body" style="padding:0;">
        <table class="riwayat-table">
          <thead>
            <tr>
              <th style="width:40px;text-align:center;">No.</th>
              <th>Kode Obat</th>
              <th>Nama Obat & Sediaan</th>
              <th>Jumlah / Satuan</th>
              <th>Aturan Pakai (Signa)</th>
            </tr>
          </thead>
          <tbody>
            ${resepRows}
          </tbody>
        </table>
      </div>
    </div>
  `;

  body.innerHTML = html;
}

function salinSoapKeForm() {
  if (!currentRiwayatData || !currentRiwayatData.soap) {
    showToast('Tidak ada data SOAP untuk disalin', 'warning');
    return;
  }
  const s = currentRiwayatData.soap;

  // Copy Vitals
  if (s.tensi) {
    const el = document.querySelector('input[name="tensi"]');
    if (el) el.value = s.tensi;
  }
  if (s.nadi) {
    const el = document.querySelector('input[name="nadi"]');
    if (el) el.value = s.nadi;
  }
  if (s.suhu_tubuh) {
    const el = document.querySelector('input[name="suhu_tubuh"]');
    if (el) el.value = s.suhu_tubuh;
  }
  if (s.respirasi) {
    const el = document.querySelector('input[name="respirasi"]');
    if (el) el.value = s.respirasi;
  }
  if (s.spo2) {
    const el = document.querySelector('input[name="spo2"]');
    if (el) el.value = s.spo2;
  }
  if (s.tinggi) {
    const el = document.querySelector('input[name="tinggi"]');
    if (el) el.value = s.tinggi;
  }
  if (s.berat) {
    const el = document.querySelector('input[name="berat"]');
    if (el) el.value = s.berat;
  }
  if (s.gcs) {
    const el = document.querySelector('input[name="gcs"]');
    if (el) el.value = s.gcs;
  }
  if (s.kesadaran) {
    const sel = document.querySelector('select[name="kesadaran"]');
    if (sel) sel.value = s.kesadaran;
  }
  if (s.alergi) {
    const el = document.querySelector('input[name="alergi"]');
    if (el) el.value = s.alergi;
  }

  // Copy SOAP textareas
  if (s.keluhan) {
    const el = document.querySelector('textarea[name="keluhan"]');
    if (el) el.value = s.keluhan;
  }
  if (s.pemeriksaan) {
    const el = document.querySelector('textarea[name="pemeriksaan"]');
    if (el) el.value = s.pemeriksaan;
  }
  if (s.penilaian) {
    const el = document.querySelector('textarea[name="penilaian"]');
    if (el) el.value = s.penilaian;
  }
  if (s.rtl) {
    const el = document.querySelector('textarea[name="rtl"]');
    if (el) el.value = s.rtl;
  }
  if (s.instruksi) {
    const el = document.querySelector('input[name="instruksi"]');
    if (el) el.value = s.instruksi;
  }
  if (s.evaluasi) {
    const el = document.querySelector('input[name="evaluasi"]');
    if (el) el.value = s.evaluasi;
  }

  if (typeof hitungBmi === 'function') {
    hitungBmi();
  }

  closeDetailRiwayatMedis();
  switchClinicalTab('soap');
  showToast('Data TTV & SOAP riwayat sebelumnya berhasil disalin ke form!', 'success');
}

// ─── Fitur Copy Resep Terdahulu & Edit Resep Aktif ─────────
let riwayatResepList = [];
let currentCopyItems = [];

function openModalCopyResep() {
  const modal = document.getElementById('modalCopyResep');
  const body = document.getElementById('bodyModalCopyResep');
  modal.style.display = 'flex';
  modal.classList.add('active');

  body.innerHTML = `
    <div style="text-align:center;padding:40px 0;">
      <div class="spinner" style="width:34px;height:34px;border-width:3px;border-color:#059669;border-top-color:transparent;"></div>
      <p style="margin-top:12px;color:#64748b;font-size:13px;font-weight:500;">Mengambil riwayat pemberian obat pasien...</p>
    </div>
  `;

  fetch('<?= BASE_URL ?>modules/rekam_medis/ajax.php?action=get_riwayat_resep&no_rkm_medis=<?= $pasien['no_rkm_medis'] ?>&no_rawat=<?= urlencode($no_rawat) ?>', {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(r => r.json())
  .then(res => {
    if (!res.success || !res.data || res.data.length === 0) {
      body.innerHTML = `
        <div style="text-align:center;padding:36px;background:#fff;border-radius:10px;border:1px dashed #cbd5e1;">
          <i class="fas fa-prescription-bottle-alt" style="font-size:36px;color:#94a3b8;margin-bottom:12px;display:block;"></i>
          <p style="font-weight:700;color:#1e293b;margin:0 0 6px;font-size:14px;">Belum Ada Riwayat Peresepan Terdahulu</p>
          <span style="font-size:12px;color:#64748b;">Pasien ini belum memiliki catatan resep obat pada kunjungan sebelumnya.</span>
        </div>
      `;
      document.getElementById('btnTerapkanCopyResep').disabled = true;
      document.getElementById('footerSummaryCopyResep').innerText = '0 obat dipilih';
      return;
    }

    riwayatResepList = res.data;
    document.getElementById('btnTerapkanCopyResep').disabled = false;
    renderCopyResepUI();
  })
  .catch(err => {
    console.error(err);
    body.innerHTML = `
      <div style="text-align:center;padding:30px;color:#ef4444;">
        <i class="fas fa-exclamation-triangle" style="font-size:30px;margin-bottom:8px;display:block;"></i>
        Gagal memuat riwayat resep obat pasien.
      </div>
    `;
  });
}

function closeModalCopyResep() {
  const modal = document.getElementById('modalCopyResep');
  modal.classList.remove('active');
  modal.style.display = 'none';
}

function renderCopyResepUI() {
  const body = document.getElementById('bodyModalCopyResep');

  let selectOptions = '';
  riwayatResepList.forEach((r, idx) => {
    selectOptions += `<option value="${idx}">Kunjungan ${r.tgl_peresepan} &bull; ${r.nm_poli || '-'} &bull; ${r.total_item} Obat (${r.nm_dokter || 'Dokter'})</option>`;
  });

  body.innerHTML = `
    <!-- Pilih Kunjungan -->
    <div style="background:#ffffff;padding:14px 16px;border-radius:10px;border:1px solid #e2e8f0;margin-bottom:14px;box-shadow:0 1px 3px rgba(0,0,0,0.03);">
      <label style="font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px;display:flex;align-items:center;gap:6px;">
        <i class="fas fa-history" style="color:#059669;"></i> Pilih Resep Dari Riwayat Kunjungan:
      </label>
      <select id="selectPastResepDropdown" class="form-control" onchange="switchPastResep(this.value)" style="font-size:13px;font-weight:600;color:#0f172a;background:#f8fafc;">
        ${selectOptions}
      </select>
    </div>

    <!-- Info Helper Banner -->
    <div style="background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;padding:10px 14px;border-radius:8px;font-size:12px;margin-bottom:14px;display:flex;align-items:center;gap:10px;">
      <i class="fas fa-info-circle" style="color:#059669;font-size:16px;flex-shrink:0;"></i>
      <div>
        Silakan centang obat yang akan disalin. Anda dapat <strong>mengedit jumlah</strong>, <strong>mengubah aturan pakai</strong>, atau <strong>menghapus obat</strong> sebelum diterapkan ke resep aktif.
      </div>
    </div>

    <!-- Table Container -->
    <div style="background:#ffffff;border-radius:10px;border:1px solid #e2e8f0;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.03);margin-bottom:14px;">
      <table class="riwayat-table" style="margin:0;">
        <thead>
          <tr style="background:#f1f5f9;">
            <th style="width:40px;text-align:center;">
              <input type="checkbox" id="chkAllCopy" checked onchange="toggleCheckAllCopy(this.checked)" style="cursor:pointer;width:15px;height:15px;">
            </th>
            <th>Nama Obat & Satuan</th>
            <th style="width:130px;">Jumlah</th>
            <th>Aturan Pakai / Dosis</th>
            <th style="width:60px;text-align:center;">Aksi</th>
          </tr>
        </thead>
        <tbody id="tbodyCopyItems">
        </tbody>
      </table>
    </div>

    <!-- Quick Add Additional Drug to Copy List -->
    <div style="background:#ffffff;padding:12px 16px;border-radius:10px;border:1px dashed #cbd5e1;">
      <div style="display:flex;align-items:center;justify-content:space-between;cursor:pointer;" onclick="toggleBoxAddExtraDrug()">
        <span style="font-size:12px;font-weight:700;color:#0284c7;display:flex;align-items:center;gap:6px;">
          <i class="fas fa-plus-circle"></i> Tambah / Ganti Obat Lain ke Daftar Salin Ini (Opsional)
        </span>
        <i class="fas fa-chevron-down text-muted" id="iconAddExtraDrug" style="font-size:11px;"></i>
      </div>
      <div id="boxAddExtraDrug" style="display:none;margin-top:10px;padding-top:10px;border-top:1px dashed #e2e8f0;">
        <div style="display:grid;grid-template-columns:1fr 100px 1.5fr auto;gap:8px;align-items:end;">
          <div style="position:relative;">
            <label style="font-size:11px;color:#64748b;margin-bottom:2px;display:block;">Cari Obat</label>
            <input type="text" id="inputExtraObat" placeholder="Ketik nama obat..." class="form-control" autocomplete="off" style="font-size:12px;" oninput="searchExtraObatModal(this.value)">
            <div id="extraObatResults" style="display:none;position:absolute;left:0;right:0;top:100%;z-index:50;background:#fff;border:1px solid #cbd5e1;border-radius:6px;max-height:160px;overflow-y:auto;box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);"></div>
            <input type="hidden" id="selectedExtraKodeBrng">
            <input type="hidden" id="selectedExtraNamaBrng">
            <input type="hidden" id="selectedExtraSatuan">
          </div>
          <div>
            <label style="font-size:11px;color:#64748b;margin-bottom:2px;display:block;">Jumlah</label>
            <input type="number" id="inputExtraJml" min="1" value="10" class="form-control" style="font-size:12px;">
          </div>
          <div>
            <label style="font-size:11px;color:#64748b;margin-bottom:2px;display:block;">Aturan Pakai</label>
            <input type="text" id="inputExtraAturan" placeholder="cth: 3 x 1 tablet" class="form-control" style="font-size:12px;">
          </div>
          <div>
            <button type="button" class="btn btn-sm btn-primary" onclick="tambahExtraObatToCopyTable()" style="height:35px;font-size:12px;padding:0 12px;display:inline-flex;align-items:center;gap:4px;">
              <i class="fas fa-plus"></i> Tambah
            </button>
          </div>
        </div>
      </div>
    </div>
  `;

  // Initialize with the first past prescription
  switchPastResep(0);
}

function switchPastResep(idx) {
  const pastResep = riwayatResepList[idx];
  if (!pastResep || !pastResep.items) return;

  // Deep clone items for editing
  currentCopyItems = pastResep.items.map(item => ({
    kode_brng: item.kode_brng,
    nama_brng: item.nama_brng,
    jml: parseFloat(item.jml) || 1,
    satuan: item.satuan || 'Item',
    aturan_pakai: item.aturan_pakai || '',
    selected: true
  }));

  renderCopyItemsTbody();
}

function renderCopyItemsTbody() {
  const tbody = document.getElementById('tbodyCopyItems');
  if (!tbody) return;

  if (currentCopyItems.length === 0) {
    tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;padding:24px;color:#94a3b8;">Tidak ada obat dalam daftar salin</td></tr>`;
    updateFooterSummaryCopy();
    return;
  }

  let html = '';
  currentCopyItems.forEach((item, i) => {
    html += `
      <tr id="tr-copy-${i}" style="${!item.selected ? 'opacity:0.5;background:#f8fafc;' : ''}">
        <td style="text-align:center;vertical-align:middle;">
          <input type="checkbox" ${item.selected ? 'checked' : ''} onchange="toggleItemCheckCopy(${i}, this.checked)" style="cursor:pointer;width:15px;height:15px;">
        </td>
        <td style="vertical-align:middle;">
          <div style="font-weight:700;color:#0f172a;font-size:12.5px;">${item.nama_brng}</div>
          <div style="font-size:10.5px;color:#64748b;font-family:monospace;">${item.kode_brng} &bull; ${item.satuan}</div>
        </td>
        <td style="vertical-align:middle;">
          <div style="display:flex;align-items:center;gap:4px;">
            <input type="number" min="1" step="1" value="${item.jml}"
                   oninput="updateCopyItemJml(${i}, this.value)"
                   class="form-control" style="font-size:12px;padding:4px 8px;height:32px;font-weight:700;width:75px;text-align:center;"
                   ${!item.selected ? 'disabled' : ''}>
            <span style="font-size:11px;color:#64748b;">${item.satuan}</span>
          </div>
        </td>
        <td style="vertical-align:middle;">
          <input type="text" value="${item.aturan_pakai}"
                 placeholder="Aturan pakai / signa"
                 oninput="updateCopyItemAturan(${i}, this.value)"
                 class="form-control" style="font-size:12px;padding:4px 10px;height:32px;"
                 ${!item.selected ? 'disabled' : ''}>
        </td>
        <td style="text-align:center;vertical-align:middle;">
          <button type="button" onclick="removeCopyItem(${i})" class="btn btn-sm btn-outline-danger" title="Hapus dari daftar salin" style="padding:4px 8px;font-size:11px;">
            <i class="fas fa-trash-alt"></i>
          </button>
        </td>
      </tr>
    `;
  });

  tbody.innerHTML = html;
  updateFooterSummaryCopy();
}

function toggleCheckAllCopy(checked) {
  currentCopyItems.forEach(it => it.selected = checked);
  renderCopyItemsTbody();
}

function toggleItemCheckCopy(index, checked) {
  if (currentCopyItems[index]) {
    currentCopyItems[index].selected = checked;
    renderCopyItemsTbody();
  }
}

function updateCopyItemJml(index, val) {
  if (currentCopyItems[index]) {
    currentCopyItems[index].jml = parseFloat(val) || 1;
    updateFooterSummaryCopy();
  }
}

function updateCopyItemAturan(index, val) {
  if (currentCopyItems[index]) {
    currentCopyItems[index].aturan_pakai = val;
  }
}

function removeCopyItem(index) {
  currentCopyItems.splice(index, 1);
  renderCopyItemsTbody();
  showToast('Obat dihapus dari daftar salin', 'info');
}

function updateFooterSummaryCopy() {
  const selectedCount = currentCopyItems.filter(i => i.selected).length;
  const footer = document.getElementById('footerSummaryCopyResep');
  const btn = document.getElementById('btnTerapkanCopyResep');
  if (footer) {
    footer.innerHTML = `<strong>${selectedCount}</strong> dari ${currentCopyItems.length} obat dipilih untuk disalin.`;
  }
  if (btn) {
    btn.disabled = selectedCount === 0;
  }
}

function toggleBoxAddExtraDrug() {
  const box = document.getElementById('boxAddExtraDrug');
  const icon = document.getElementById('iconAddExtraDrug');
  if (box.style.display === 'none') {
    box.style.display = 'block';
    icon.className = 'fas fa-chevron-up text-muted';
  } else {
    box.style.display = 'none';
    icon.className = 'fas fa-chevron-down text-muted';
  }
}

let searchExtraTimeout = null;
function searchExtraObatModal(keyword) {
  clearTimeout(searchExtraTimeout);
  const resBox = document.getElementById('extraObatResults');
  if (!keyword || keyword.trim().length < 2) {
    resBox.style.display = 'none';
    return;
  }
  searchExtraTimeout = setTimeout(() => {
    fetch('<?= BASE_URL ?>modules/rekam_medis/ajax.php?action=cari_obat&q=' + encodeURIComponent(keyword.trim()), {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(res => {
      if (res.success && res.data && res.data.length > 0) {
        let html = '';
        res.data.forEach(o => {
          html += `
            <div style="padding:6px 10px;cursor:pointer;border-bottom:1px solid #f1f5f9;font-size:12px;"
                 onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='#fff'"
                 onclick="pilihExtraObatModal('${o.kode_brng}', '${o.nama_brng.replace(/'/g, "\\'")}', '${o.satuan || 'Item'}')">
              <strong>${o.nama_brng}</strong> <span style="color:#64748b;">(${o.kode_brng}) - Stok: ${o.stok}</span>
            </div>
          `;
        });
        resBox.innerHTML = html;
        resBox.style.display = 'block';
      } else {
        resBox.innerHTML = '<div style="padding:8px;font-size:12px;color:#94a3b8;text-align:center;">Obat tidak ditemukan</div>';
        resBox.style.display = 'block';
      }
    });
  }, 250);
}

function pilihExtraObatModal(kode, nama, satuan) {
  document.getElementById('selectedExtraKodeBrng').value = kode;
  document.getElementById('selectedExtraNamaBrng').value = nama;
  document.getElementById('selectedExtraSatuan').value = satuan;
  document.getElementById('inputExtraObat').value = nama;
  document.getElementById('extraObatResults').style.display = 'none';
  document.getElementById('inputExtraAturan').focus();
}

function tambahExtraObatToCopyTable() {
  const kode = document.getElementById('selectedExtraKodeBrng').value;
  const nama = document.getElementById('selectedExtraNamaBrng').value;
  const satuan = document.getElementById('selectedExtraSatuan').value || 'Item';
  const jml = parseFloat(document.getElementById('inputExtraJml').value) || 1;
  const aturan = document.getElementById('inputExtraAturan').value.trim();

  if (!kode) {
    showToast('Cari dan pilih obat terlebih dahulu', 'warning');
    return;
  }

  // Cek jika obat sudah ada di daftar salin
  const exists = currentCopyItems.find(i => i.kode_brng === kode);
  if (exists) {
    exists.jml += jml;
    if (aturan) exists.aturan_pakai = aturan;
    exists.selected = true;
    showToast('Jumlah obat yang ada di daftar diperbarui', 'info');
  } else {
    currentCopyItems.push({
      kode_brng: kode,
      nama_brng: nama,
      jml: jml,
      satuan: satuan,
      aturan_pakai: aturan || '3 x 1 sesudah makan',
      selected: true
    });
    showToast('Obat berhasil ditambahkan ke daftar salin', 'success');
  }

  // Reset extra fields
  document.getElementById('selectedExtraKodeBrng').value = '';
  document.getElementById('selectedExtraNamaBrng').value = '';
  document.getElementById('inputExtraObat').value = '';
  document.getElementById('inputExtraAturan').value = '';
  document.getElementById('extraObatResults').style.display = 'none';

  renderCopyItemsTbody();
}

function submitCopyResepBatch() {
  const selectedItems = currentCopyItems.filter(i => i.selected);
  if (selectedItems.length === 0) {
    showToast('Pilih minimal 1 obat untuk disalin', 'warning');
    return;
  }

  const btn = document.getElementById('btnTerapkanCopyResep');
  btn.disabled = true;
  btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Menyalin...`;

  const payload = selectedItems.map(it => ({
    kode_brng: it.kode_brng,
    jml: it.jml,
    aturan_pakai: it.aturan_pakai
  }));

  const formData = new FormData();
  formData.append('action', 'copy_resep_batch');
  formData.append('no_rawat', '<?= $no_rawat ?>');
  formData.append('kd_dokter', '<?= $pasien['kd_dokter'] ?: "DR001" ?>');
  formData.append('items', JSON.stringify(payload));

  fetch('<?= BASE_URL ?>modules/rekam_medis/ajax.php', {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    body: formData
  })
  .then(r => r.json())
  .then(res => {
    btn.disabled = false;
    btn.innerHTML = `<i class="fas fa-check"></i> Terapkan ke Resep Aktif`;
    if (res.success) {
      showToast(res.message || 'Resep terdahulu berhasil disalin!', 'success');
      closeModalCopyResep();
      localStorage.setItem('periksa_active_tab', 'resep');
      setTimeout(() => location.reload(), 450);
    } else {
      showToast(res.message || 'Gagal menyalin resep', 'danger');
    }
  })
  .catch(err => {
    btn.disabled = false;
    btn.innerHTML = `<i class="fas fa-check"></i> Terapkan ke Resep Aktif`;
    console.error(err);
    showToast('Terjadi kesalahan jaringan', 'danger');
  });
}

// ─── Modal Edit Item Resep Aktif ────────────────────────────
function openEditItemResep(noResep, kodeBrng, namaBrng, jml, aturan, satuan) {
  document.getElementById('editItemNoResep').value = noResep;
  document.getElementById('editItemKodeBrng').value = kodeBrng;
  document.getElementById('editItemNamaBrng').textContent = namaBrng;
  document.getElementById('editItemJml').value = jml;
  document.getElementById('editItemSatuan').textContent = satuan;
  document.getElementById('editItemAturan').value = aturan;

  const modal = document.getElementById('modalEditItemResep');
  modal.style.display = 'flex';
  modal.classList.add('active');
  document.getElementById('editItemJml').focus();
}

function closeEditItemResep() {
  const modal = document.getElementById('modalEditItemResep');
  modal.classList.remove('active');
  modal.style.display = 'none';
}

function simpanEditItemResep() {
  const noResep = document.getElementById('editItemNoResep').value;
  const kodeBrng = document.getElementById('editItemKodeBrng').value;
  const jml = parseFloat(document.getElementById('editItemJml').value) || 0;
  const aturan = document.getElementById('editItemAturan').value.trim();

  if (jml <= 0) {
    showToast('Jumlah obat harus lebih dari 0', 'warning');
    return;
  }

  const formData = new FormData();
  formData.append('action', 'update_item_resep');
  formData.append('no_resep', noResep);
  formData.append('kode_brng', kodeBrng);
  formData.append('jml', jml);
  formData.append('aturan_pakai', aturan);

  fetch('<?= BASE_URL ?>modules/rekam_medis/ajax.php', {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    body: formData
  })
  .then(r => r.json())
  .then(res => {
    if (res.success) {
      showToast('Resep obat berhasil diperbarui', 'success');
      closeEditItemResep();
      localStorage.setItem('periksa_active_tab', 'resep');
      setTimeout(() => location.reload(), 350);
    } else {
      showToast(res.message || 'Gagal memperbarui resep', 'danger');
    }
  })
  .catch(err => {
    console.error(err);
    showToast('Gagal menghubungi server', 'danger');
  });
}

// ═════════════════════════════════════════════════════════════
// ─── MODUL ODONTOGRAM JAVASCRIPT LOGIC ────────────────────────
// ═════════════════════════════════════════════════════════════

let selectedOdontCondition = 'Sou';
let odontTeethState = {}; // { '18_ALL': { no_gigi: '18', posisi: 'ALL', kondisi: 'Sou' }, ... }

const ODONT_COLORS = {
  Sou: '#ffffff',
  Car: '#ef4444',
  Amf: '#475569',
  Gif: '#10b981',
  Mis: '#0f172a',
  Rad: '#f59e0b',
  Cro: '#6366f1',
  Bdr: '#06b6d4',
  Imp: '#a855f7',
  Abx: '#dc2626',
  Fis: '#3b82f6'
};

function selectOdontCondition(cond, btn) {
  selectedOdontCondition = cond;
  document.querySelectorAll('.odont-palette-btn').forEach(b => b.classList.remove('active'));
  if (btn) btn.classList.add('active');
}

function initOdontogramChart() {
  const upperPerm = [18,17,16,15,14,13,12,11, 21,22,23,24,25,26,27,28];
  const upperDec  = [55,54,53,52,51, 61,62,63,64,65];
  const lowerDec  = [85,84,83,82,81, 71,72,73,74,75];
  const lowerPerm = [48,47,46,45,44,43,42,41, 31,32,33,34,35,36,37,38];

  renderTeethRow('odontRowUpperPerm', upperPerm, true, false);
  renderTeethRow('odontRowUpperDec', upperDec, true, true);
  renderTeethRow('odontRowLowerDec', lowerDec, false, true);
  renderTeethRow('odontRowLowerPerm', lowerPerm, false, false);
}

function renderTeethRow(containerId, teethArr, isUpper, isDeciduous) {
  const container = document.getElementById(containerId);
  if (!container) return;
  container.innerHTML = '';

  const mid = isDeciduous ? 5 : 8;
  teethArr.forEach((t, idx) => {
    if (idx === mid) {
      const sep = document.createElement('div');
      sep.style.cssText = 'width:12px;border-left:2px dashed #94a3b8;margin:0 4px;';
      container.appendChild(sep);
    }
    const toothBox = createToothElement(t, isUpper, isDeciduous);
    container.appendChild(toothBox);
  });
}

function createToothElement(toothNum, isUpper, isDeciduous) {
  const box = document.createElement('div');
  box.className = 'odont-tooth-wrapper';
  box.id = `tooth-wrapper-${toothNum}`;
  box.style.cssText = `
    display:flex;flex-direction:${isUpper ? 'column' : 'column-reverse'};
    align-items:center;width:${isDeciduous ? '30px' : '36px'};
    border:1.5px solid #cbd5e1;border-radius:6px;background:#fff;
    padding:3px;cursor:pointer;user-select:none;transition:all 0.18s cubic-bezier(0.34, 1.56, 0.64, 1);
    box-shadow:0 1px 3px rgba(0,0,0,0.04);
  `;
  box.title = `Gigi ${toothNum} (Klik untuk pilih/update kondisi)`;

  // Label No Gigi
  const label = document.createElement('div');
  label.className = 'tooth-num-lbl';
  label.innerText = toothNum;
  label.style.cssText = `
    font-size:${isDeciduous ? '9.5px' : '10.5px'};font-weight:800;
    color:${isDeciduous ? '#0284c7' : '#1e293b'};background:${isDeciduous ? '#e0f2fe' : '#f1f5f9'};
    width:100%;text-align:center;border-radius:4px;margin:${isUpper ? '0 0 3px 0' : '3px 0 0 0'};padding:1.5px 0;
  `;

  // SVG Surface Representation
  const svgSize = isDeciduous ? 24 : 30;
  const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
  svg.setAttribute('width', svgSize);
  svg.setAttribute('height', svgSize);
  svg.setAttribute('viewBox', '0 0 30 30');
  svg.style.display = 'block';

  // Surface paths (Top=B/L, Bottom=L/P, Left=M/D, Right=D/M, Center=O)
  const surfaces = [
    { pos: 'B', points: '0,0 30,0 22,8 8,8' },
    { pos: 'L', points: '0,30 30,30 22,22 8,22' },
    { pos: 'M', points: '0,0 0,30 8,22 8,8' },
    { pos: 'D', points: '30,0 30,30 22,22 22,8' },
    { pos: 'O', points: '8,8 22,8 22,22 8,22' }
  ];

  surfaces.forEach(s => {
    const poly = document.createElementNS('http://www.w3.org/2000/svg', 'polygon');
    poly.setAttribute('points', s.points);
    poly.setAttribute('id', `surface-${toothNum}-${s.pos}`);
    poly.setAttribute('stroke', '#64748b');
    poly.setAttribute('stroke-width', '1');
    poly.setAttribute('fill', '#ffffff');
    poly.style.cursor = 'pointer';
    poly.onclick = (e) => {
      e.stopPropagation();
      const mode = document.getElementById('odontSurfaceMode').value;
      const targetPos = mode === 'ALL' ? 'ALL' : s.pos;
      applyConditionToTooth(toothNum, targetPos, selectedOdontCondition);
    };
    svg.appendChild(poly);
  });

  // Whole tooth click
  box.onclick = () => {
    const mode = document.getElementById('odontSurfaceMode').value;
    applyConditionToTooth(toothNum, mode, selectedOdontCondition);
  };

  // Condition summary badge under/above
  const condBadge = document.createElement('div');
  condBadge.id = `cond-tag-${toothNum}`;
  condBadge.innerText = 'Sou';
  condBadge.style.cssText = `
    font-size:8.5px;font-weight:700;color:#64748b;text-align:center;
    width:100%;margin-top:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;
  `;

  box.appendChild(label);
  box.appendChild(svg);
  box.appendChild(condBadge);

  return box;
}

function applyConditionToTooth(toothNum, surface, condition) {
  const key = `${toothNum}_${surface}`;
  odontTeethState[key] = {
    no_gigi: String(toothNum),
    posisi: surface,
    kondisi: condition,
    keterangan: ''
  };

  if (surface === 'ALL') {
    // Apply to all 5 surfaces of this tooth
    ['B', 'L', 'M', 'D', 'O'].forEach(pos => {
      const pEl = document.getElementById(`surface-${toothNum}-${pos}`);
      if (pEl) pEl.setAttribute('fill', ODONT_COLORS[condition] || '#ffffff');
      odontTeethState[`${toothNum}_${pos}`] = {
        no_gigi: String(toothNum),
        posisi: pos,
        kondisi: condition,
        keterangan: ''
      };
    });
  } else {
    const pEl = document.getElementById(`surface-${toothNum}-${surface}`);
    if (pEl) pEl.setAttribute('fill', ODONT_COLORS[condition] || '#ffffff');
  }

  // Update tag
  const tag = document.getElementById(`cond-tag-${toothNum}`);
  if (tag) {
    tag.innerText = condition;
    tag.style.color = condition === 'Sou' ? '#64748b' : (ODONT_COLORS[condition] || '#dc2626');
  }

  calculateDmft();
}

function calculateDmft() {
  let d = 0, m = 0, f = 0;
  const processedTeeth = new Set();

  Object.values(odontTeethState).forEach(t => {
    if (processedTeeth.has(t.no_gigi)) return;
    if (t.kondisi === 'Car' || t.kondisi === 'Rad' || t.kondisi === 'Abx') {
      d++;
      processedTeeth.add(t.no_gigi);
    } else if (t.kondisi === 'Mis') {
      m++;
      processedTeeth.add(t.no_gigi);
    } else if (t.kondisi === 'Amf' || t.kondisi === 'Gif' || t.kondisi === 'Cro' || t.kondisi === 'Fis') {
      f++;
      processedTeeth.add(t.no_gigi);
    }
  });

  if (document.getElementById('valD')) document.getElementById('valD').innerText = d;
  if (document.getElementById('valM')) document.getElementById('valM').innerText = m;
  if (document.getElementById('valF')) document.getElementById('valF').innerText = f;
  const total = d + m + f;
  if (document.getElementById('badgeDmftTotal')) document.getElementById('badgeDmftTotal').innerText = `Total: ${total}`;
  return { d, m, f, total };
}

function calculateOhisScore() {
  const di = parseFloat(document.getElementById('inputOhisDebris')?.value) || 0;
  const ci = parseFloat(document.getElementById('inputOhisCalculus')?.value) || 0;
  const total = di + ci;
  let kriteria = 'Baik';
  let badgeColor = '#10b981';

  if (total > 3.0) {
    kriteria = 'Buruk';
    badgeColor = '#ef4444';
  } else if (total > 1.2) {
    kriteria = 'Sedang';
    badgeColor = '#f59e0b';
  }

  const badge = document.getElementById('badgeOhisKriteria');
  if (badge) {
    badge.innerText = `${total.toFixed(1)} (${kriteria})`;
    badge.style.background = badgeColor;
  }
  return { di, ci, total: total.toFixed(1), kriteria };
}

function resetAllTeethToNormal() {
  if (!confirm('Yakin ingin mereset semua kondisi gigi ke Sehat / Normal?')) return;
  odontTeethState = {};
  document.querySelectorAll('[id^="surface-"]').forEach(p => p.setAttribute('fill', '#ffffff'));
  document.querySelectorAll('[id^="cond-tag-"]').forEach(t => {
    t.innerText = 'Sou';
    t.style.color = '#64748b';
  });
  calculateDmft();
  showToast('Kondisi gigi telah direset ke normal', 'info');
}

function loadOdontogramData() {
  const rawat = '<?= $rawat_esc ?>';
  const rm = '<?= $conn->real_escape_string($pasien['no_rkm_medis'] ?? '') ?>';

  fetch(`<?= BASE_URL ?>modules/rekam_medis/ajax.php?action=get_odontogram&no_rawat=${encodeURIComponent(rawat)}&no_rkm_medis=${encodeURIComponent(rm)}`, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(r => r.json())
  .then(res => {
    if (!res.success) return;
    if (res.header) {
      const h = res.header;
      if (document.getElementById('odontOklusi')) document.getElementById('odontOklusi').value = h.oklusi || 'Normal';
      if (document.getElementById('odontTorusPalatinus')) document.getElementById('odontTorusPalatinus').value = h.torus_palatinus || 'Tidak Ada';
      if (document.getElementById('odontTorusMandibularis')) document.getElementById('odontTorusMandibularis').value = h.torus_mandibularis || 'Tidak Ada';
      if (document.getElementById('odontPalatum')) document.getElementById('odontPalatum').value = h.palatum || 'Sedang';
      if (document.getElementById('odontDiastema')) document.getElementById('odontDiastema').value = h.diastema || '';
      if (document.getElementById('odontGigiAnomali')) document.getElementById('odontGigiAnomali').value = h.gigi_anomali || '';
      if (document.getElementById('odontLainLain')) document.getElementById('odontLainLain').value = h.lain_lain || '';
      if (document.getElementById('inputOhisDebris')) document.getElementById('inputOhisDebris').value = h.ohis_debris || '0.0';
      if (document.getElementById('inputOhisCalculus')) document.getElementById('inputOhisCalculus').value = h.ohis_calculus || '0.0';
      calculateOhisScore();
    }
    if (res.teeth && Object.keys(res.teeth).length > 0) {
      Object.values(res.teeth).forEach(t => {
        applyConditionToTooth(t.no_gigi, t.posisi, t.kondisi);
      });
    }
  })
  .catch(err => console.error('Error loading odontogram:', err));
}

function saveOdontogramData() {
  const rawat = '<?= $rawat_esc ?>';
  const rm = '<?= $conn->real_escape_string($pasien['no_rkm_medis'] ?? '') ?>';
  const dmft = calculateDmft();
  const ohis = calculateOhisScore();

  const teethArray = Object.values(odontTeethState).filter(t => t.kondisi !== 'Sou');

  const formData = new FormData();
  formData.append('action', 'simpan_odontogram');
  formData.append('no_rawat', rawat);
  formData.append('no_rkm_medis', rm);
  formData.append('oklusi', document.getElementById('odontOklusi')?.value || 'Normal');
  formData.append('torus_palatinus', document.getElementById('odontTorusPalatinus')?.value || 'Tidak Ada');
  formData.append('torus_mandibularis', document.getElementById('odontTorusMandibularis')?.value || 'Tidak Ada');
  formData.append('palatum', document.getElementById('odontPalatum')?.value || 'Sedang');
  formData.append('diastema', document.getElementById('odontDiastema')?.value || 'Tidak Ada');
  formData.append('gigi_anomali', document.getElementById('odontGigiAnomali')?.value || 'Tidak Ada');
  formData.append('lain_lain', document.getElementById('odontLainLain')?.value || '');
  formData.append('ohis_debris', String(ohis.di));
  formData.append('ohis_calculus', String(ohis.ci));
  formData.append('ohis_nilai', String(ohis.total));
  formData.append('ohis_kriteria', String(ohis.kriteria));
  formData.append('d_val', String(dmft.d));
  formData.append('m_val', String(dmft.m));
  formData.append('f_val', String(dmft.f));
  formData.append('dmft_val', String(dmft.total));
  formData.append('teeth_data', JSON.stringify(teethArray));

  fetch('<?= BASE_URL ?>modules/rekam_medis/ajax.php', {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    body: formData
  })
  .then(r => r.json())
  .then(res => {
    if (res.success) {
      showToast(res.message || 'Data odontogram berhasil disimpan', 'success');
    } else {
      showToast(res.message || 'Gagal menyimpan odontogram', 'danger');
    }
  })
  .catch(err => {
    console.error(err);
    showToast('Gagal menghubungi server', 'danger');
  });
}

// ═════════════════════════════════════════════════════════════
// ─── MODUL KIA (ANC, KMS, IMUNISASI, KB) JAVASCRIPT LOGIC ────
// ═════════════════════════════════════════════════════════════

function switchKiaSubTab(subTabName) {
  document.querySelectorAll('.kia-subtab-pane').forEach(el => {
    el.style.display = 'none';
    el.classList.remove('clinical-tab-pane-animate');
  });
  document.querySelectorAll('.kia-subtab-btn').forEach(el => {
    el.classList.remove('active');
    el.classList.add('btn-secondary');
  });

  const activePane = document.getElementById('kia-sub-pane-' + subTabName);
  const activeBtn  = document.getElementById('kia-sub-btn-' + subTabName);
  if (activePane) {
    activePane.style.display = 'block';
    void activePane.offsetWidth;
    activePane.classList.add('clinical-tab-pane-animate');
  }
  if (activeBtn) {
    activeBtn.classList.remove('btn-secondary');
    activeBtn.classList.add('active');
    createTabClickRipple(activeBtn);
  }
}

// Kalkulator HPL Naegele & Usia Kehamilan
function calculateNaegeleHPL() {
  const hphtInput = document.getElementById('ancHPHT');
  if (!hphtInput || !hphtInput.value) return;

  const hpht = new Date(hphtInput.value);
  if (isNaN(hpht.getTime())) return;

  // HPL = HPHT + 7 hari - 3 bulan + 1 tahun (Rumus Naegele)
  const hpl = new Date(hpht);
  hpl.setDate(hpl.getDate() + 7);
  hpl.setMonth(hpl.getMonth() - 3);
  hpl.setFullYear(hpl.getFullYear() + 1);

  const yyyy = hpl.getFullYear();
  const mm = String(hpl.getMonth() + 1).padStart(2, '0');
  const dd = String(hpl.getDate()).padStart(2, '0');
  if (document.getElementById('ancHPL')) document.getElementById('ancHPL').value = `${yyyy}-${mm}-${dd}`;

  // Usia Kehamilan = (Hari Ini - HPHT) dlm minggu & hari
  const today = new Date();
  const diffTime = today - hpht;
  const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));
  if (diffDays > 0) {
    const weeks = Math.floor(diffDays / 7);
    const remDays = diffDays % 7;
    if (document.getElementById('ancUsiaKehamilan')) {
      document.getElementById('ancUsiaKehamilan').value = `${weeks} Minggu ${remDays} Hari (${diffDays} hari)`;
    }
  }
}

function evaluateKsprRisk() {
  const skor = parseInt(document.getElementById('ancSkorKspr')?.value) || 2;
  const select = document.getElementById('ancResikoKehamilan');
  if (!select) return;

  if (skor >= 12) {
    select.value = 'KRST (Sangat Tinggi)';
    select.style.color = '#991b1b';
  } else if (skor >= 6) {
    select.value = 'KRT (Tinggi)';
    select.style.color = '#d97706';
  } else {
    select.value = 'KRR (Rendah)';
    select.style.color = '#166534';
  }
}

function evaluateChildNutrition() {
  const bb = parseFloat(document.getElementById('kmsBB')?.value) || 0;
  const tb = parseFloat(document.getElementById('kmsTB')?.value) || 0;
  const umur = parseInt(document.getElementById('kmsUmurBln')?.value) || 0;
  const sel = document.getElementById('kmsStatusGiziBBU');
  if (!sel || bb <= 0) return;

  // Evaluasi sederhana status gizi
  if (umur > 0) {
    const approxNormalBB = umur <= 12 ? (umur / 2) + 4 : (umur * 2) + 8;
    const ratio = bb / approxNormalBB;
    if (ratio < 0.7) {
      sel.value = 'Gizi Buruk';
    } else if (ratio < 0.85) {
      sel.value = 'Gizi Kurang';
    } else if (ratio <= 1.15) {
      sel.value = 'Gizi Baik (Normal)';
    } else if (ratio <= 1.3) {
      sel.value = 'Gizi Lebih';
    } else {
      sel.value = 'Obesitas';
    }
  }
}

function loadKiaAncData() {
  const rawat = '<?= $rawat_esc ?>';
  const rm = '<?= $conn->real_escape_string($pasien['no_rkm_medis'] ?? '') ?>';

  fetch(`<?= BASE_URL ?>modules/rekam_medis/ajax.php?action=get_kia_anc&no_rawat=${encodeURIComponent(rawat)}&no_rkm_medis=${encodeURIComponent(rm)}`, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(r => r.json())
  .then(res => {
    if (!res.success || !res.data) return;
    const d = res.data;
    if (document.getElementById('ancG')) document.getElementById('ancG').value = d.g_hamil || 1;
    if (document.getElementById('ancP')) document.getElementById('ancP').value = d.p_partus || 0;
    if (document.getElementById('ancA')) document.getElementById('ancA').value = d.a_abortus || 0;
    if (document.getElementById('ancH')) document.getElementById('ancH').value = d.h_hidup || 0;
    if (document.getElementById('ancHPHT')) document.getElementById('ancHPHT').value = d.hpht || '';
    if (document.getElementById('ancHPL')) document.getElementById('ancHPL').value = d.hpl || '';
    if (document.getElementById('ancUsiaKehamilan')) document.getElementById('ancUsiaKehamilan').value = d.usia_kehamilan || '';
    if (document.getElementById('ancTFU')) document.getElementById('ancTFU').value = d.tfu || '';
    if (document.getElementById('ancDJJ')) document.getElementById('ancDJJ').value = d.djj || '';
    if (document.getElementById('ancLetakJanin')) document.getElementById('ancLetakJanin').value = d.letak_janin || 'Kepala';
    if (document.getElementById('ancLeopold1')) document.getElementById('ancLeopold1').value = d.leopold_1 || '';
    if (document.getElementById('ancLeopold2')) document.getElementById('ancLeopold2').value = d.leopold_2 || '';
    if (document.getElementById('ancLeopold3')) document.getElementById('ancLeopold3').value = d.leopold_3 || '';
    if (document.getElementById('ancLeopold4')) document.getElementById('ancLeopold4').value = d.leopold_4 || '';
    if (document.getElementById('ancEdema')) document.getElementById('ancEdema').value = d.edema || 'Tidak';
    if (document.getElementById('ancReflPatella')) document.getElementById('ancReflPatella').value = d.refl_patella || '+';
    if (document.getElementById('ancSkorKspr')) document.getElementById('ancSkorKspr').value = d.skor_kspr || 2;
    if (document.getElementById('ancResikoKehamilan')) document.getElementById('ancResikoKehamilan').value = d.resiko_kehamilan || 'KRR (Rendah)';
    if (document.getElementById('ancTindakanKia')) document.getElementById('ancTindakanKia').value = d.tindakan_kia || '';
    if (document.getElementById('ancSaranNasehat')) document.getElementById('ancSaranNasehat').value = d.saran_nasehat || '';
  })
  .catch(err => console.error('Error loading ANC:', err));
}

function saveKiaAncData() {
  const rawat = '<?= $rawat_esc ?>';
  const rm = '<?= $conn->real_escape_string($pasien['no_rkm_medis'] ?? '') ?>';

  const formData = new FormData();
  formData.append('action', 'simpan_kia_anc');
  formData.append('no_rawat', rawat);
  formData.append('no_rkm_medis', rm);
  formData.append('g_hamil', document.getElementById('ancG')?.value || 1);
  formData.append('p_partus', document.getElementById('ancP')?.value || 0);
  formData.append('a_abortus', document.getElementById('ancA')?.value || 0);
  formData.append('h_hidup', document.getElementById('ancH')?.value || 0);
  formData.append('hpht', document.getElementById('ancHPHT')?.value || '');
  formData.append('hpl', document.getElementById('ancHPL')?.value || '');
  formData.append('usia_kehamilan', document.getElementById('ancUsiaKehamilan')?.value || '');
  formData.append('tfu', document.getElementById('ancTFU')?.value || '');
  formData.append('djj', document.getElementById('ancDJJ')?.value || '');
  formData.append('letak_janin', document.getElementById('ancLetakJanin')?.value || 'Kepala');
  formData.append('leopold_1', document.getElementById('ancLeopold1')?.value || '');
  formData.append('leopold_2', document.getElementById('ancLeopold2')?.value || '');
  formData.append('leopold_3', document.getElementById('ancLeopold3')?.value || '');
  formData.append('leopold_4', document.getElementById('ancLeopold4')?.value || '');
  formData.append('edema', document.getElementById('ancEdema')?.value || 'Tidak');
  formData.append('refl_patella', document.getElementById('ancReflPatella')?.value || '+');
  formData.append('skor_kspr', document.getElementById('ancSkorKspr')?.value || 2);
  formData.append('resiko_kehamilan', document.getElementById('ancResikoKehamilan')?.value || 'KRR (Rendah)');
  formData.append('tindakan_kia', document.getElementById('ancTindakanKia')?.value || '');
  formData.append('saran_nasehat', document.getElementById('ancSaranNasehat')?.value || '');

  fetch('<?= BASE_URL ?>modules/rekam_medis/ajax.php', {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    body: formData
  })
  .then(r => r.json())
  .then(res => {
    if (res.success) {
      showToast(res.message || 'Pemeriksaan ANC berhasil disimpan', 'success');
    } else {
      showToast(res.message || 'Gagal menyimpan data ANC', 'danger');
    }
  })
  .catch(err => {
    console.error(err);
    showToast('Gagal menghubungi server', 'danger');
  });
}

function loadKiaKmsData() {
  const rawat = '<?= $rawat_esc ?>';
  const rm = '<?= $conn->real_escape_string($pasien['no_rkm_medis'] ?? '') ?>';

  fetch(`<?= BASE_URL ?>modules/rekam_medis/ajax.php?action=get_kia_kms&no_rawat=${encodeURIComponent(rawat)}&no_rkm_medis=${encodeURIComponent(rm)}`, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(r => r.json())
  .then(res => {
    if (!res.success || !res.data) return;
    const d = res.data;
    if (document.getElementById('kmsUmurBln')) document.getElementById('kmsUmurBln').value = d.umur_bln || 0;
    if (document.getElementById('kmsBB')) document.getElementById('kmsBB').value = d.bb || '';
    if (document.getElementById('kmsTB')) document.getElementById('kmsTB').value = d.tb || '';
    if (document.getElementById('kmsLK')) document.getElementById('kmsLK').value = d.lk || '';
    if (document.getElementById('kmsLiLA')) document.getElementById('kmsLiLA').value = d.lila || '';
    if (document.getElementById('kmsStatusGiziBBU')) document.getElementById('kmsStatusGiziBBU').value = d.status_gizi_bb_u || 'Gizi Baik (Normal)';
    if (document.getElementById('kmsAsiEksklusif')) document.getElementById('kmsAsiEksklusif').value = d.asi_eksklusif || 'Ya';
    if (document.getElementById('kmsVitA')) document.getElementById('kmsVitA').value = d.vit_a || 'Tidak';
    if (document.getElementById('kmsObatCacing')) document.getElementById('kmsObatCacing').value = d.obat_cacing || 'Tidak';
    if (document.getElementById('kmsPerkembanganMotorik')) document.getElementById('kmsPerkembanganMotorik').value = d.perkembangan_motorik || '';
  })
  .catch(err => console.error('Error loading KMS:', err));
}

function saveKiaKmsData() {
  const rawat = '<?= $rawat_esc ?>';
  const rm = '<?= $conn->real_escape_string($pasien['no_rkm_medis'] ?? '') ?>';

  const formData = new FormData();
  formData.append('action', 'simpan_kia_kms');
  formData.append('no_rawat', rawat);
  formData.append('no_rkm_medis', rm);
  formData.append('umur_bln', document.getElementById('kmsUmurBln')?.value || 0);
  formData.append('bb', document.getElementById('kmsBB')?.value || '');
  formData.append('tb', document.getElementById('kmsTB')?.value || '');
  formData.append('lk', document.getElementById('kmsLK')?.value || '');
  formData.append('lila', document.getElementById('kmsLiLA')?.value || '');
  formData.append('status_gizi_bb_u', document.getElementById('kmsStatusGiziBBU')?.value || 'Gizi Baik (Normal)');
  formData.append('asi_eksklusif', document.getElementById('kmsAsiEksklusif')?.value || 'Ya');
  formData.append('vit_a', document.getElementById('kmsVitA')?.value || 'Tidak');
  formData.append('obat_cacing', document.getElementById('kmsObatCacing')?.value || 'Tidak');
  formData.append('perkembangan_motorik', document.getElementById('kmsPerkembanganMotorik')?.value || '');

  fetch('<?= BASE_URL ?>modules/rekam_medis/ajax.php', {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    body: formData
  })
  .then(r => r.json())
  .then(res => {
    if (res.success) {
      showToast(res.message || 'Data KMS berhasil disimpan', 'success');
    } else {
      showToast(res.message || 'Gagal menyimpan data KMS', 'danger');
    }
  })
  .catch(err => {
    console.error(err);
    showToast('Gagal menghubungi server', 'danger');
  });
}

function loadKiaImunisasi() {
  const rm = '<?= $conn->real_escape_string($pasien['no_rkm_medis'] ?? '') ?>';
  const tbody = document.getElementById('tableBodyImunisasi');
  if (!tbody) return;

  fetch(`<?= BASE_URL ?>modules/rekam_medis/ajax.php?action=get_kia_imunisasi&no_rkm_medis=${encodeURIComponent(rm)}`, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(r => r.json())
  .then(res => {
    if (!res.success || !res.data || res.data.length === 0) {
      tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#94a3b8;padding:14px;">Belum ada riwayat imunisasi untuk pasien ini.</td></tr>';
      return;
    }
    tbody.innerHTML = res.data.map((item, idx) => `
      <tr>
        <td style="text-align:center;">${idx + 1}</td>
        <td>${item.tgl_imunisasi}</td>
        <td><strong style="color:#0284c7;">${item.jenis_imunisasi}</strong></td>
        <td>${item.no_batch || '-'}</td>
        <td>${item.nama_petugas || '-'}</td>
        <td style="text-align:center;">
          <button type="button" onclick="deleteImunisasiItem(${item.id})" class="btn btn-xs btn-outline-danger" style="padding:1px 6px;font-size:10px;">
            <i class="fas fa-trash"></i>
          </button>
        </td>
      </tr>
    `).join('');
  })
  .catch(err => console.error('Error loading imunisasi:', err));
}

function addImunisasiItem() {
  const rawat = '<?= $rawat_esc ?>';
  const rm = '<?= $conn->real_escape_string($pasien['no_rkm_medis'] ?? '') ?>';
  const tgl = document.getElementById('imunTgl')?.value;
  const jenis = document.getElementById('imunJenis')?.value;
  const batch = document.getElementById('imunBatch')?.value || '';
  const ket = document.getElementById('imunKet')?.value || '';

  if (!jenis) {
    showToast('Pilih jenis imunisasi terlebih dahulu', 'warning');
    return;
  }

  const formData = new FormData();
  formData.append('action', 'simpan_kia_imunisasi');
  formData.append('no_rawat', rawat);
  formData.append('no_rkm_medis', rm);
  formData.append('tgl_imunisasi', tgl);
  formData.append('jenis_imunisasi', jenis);
  formData.append('no_batch', batch);
  formData.append('keterangan', ket);

  fetch('<?= BASE_URL ?>modules/rekam_medis/ajax.php', {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    body: formData
  })
  .then(r => r.json())
  .then(res => {
    if (res.success) {
      showToast(res.message || 'Imunisasi berhasil ditambahkan', 'success');
      if (document.getElementById('imunJenis')) document.getElementById('imunJenis').value = '';
      if (document.getElementById('imunBatch')) document.getElementById('imunBatch').value = '';
      if (document.getElementById('imunKet')) document.getElementById('imunKet').value = '';
      loadKiaImunisasi();
    } else {
      showToast(res.message || 'Gagal menambahkan imunisasi', 'danger');
    }
  })
  .catch(err => {
    console.error(err);
    showToast('Gagal menghubungi server', 'danger');
  });
}

function deleteImunisasiItem(id) {
  if (!confirm('Hapus riwayat imunisasi ini?')) return;
  const formData = new FormData();
  formData.append('action', 'hapus_kia_imunisasi');
  formData.append('id', id);

  fetch('<?= BASE_URL ?>modules/rekam_medis/ajax.php', {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    body: formData
  })
  .then(r => r.json())
  .then(res => {
    if (res.success) {
      showToast('Data imunisasi dihapus', 'info');
      loadKiaImunisasi();
    }
  })
  .catch(err => console.error(err));
}

function loadKiaKb() {
  const rm = '<?= $conn->real_escape_string($pasien['no_rkm_medis'] ?? '') ?>';
  const tbody = document.getElementById('tableBodyKb');
  if (!tbody) return;

  fetch(`<?= BASE_URL ?>modules/rekam_medis/ajax.php?action=get_kia_kb&no_rkm_medis=${encodeURIComponent(rm)}`, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(r => r.json())
  .then(res => {
    if (!res.success || !res.data || res.data.length === 0) {
      tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#94a3b8;padding:14px;">Belum ada riwayat pelayanan KB untuk pasien ini.</td></tr>';
      return;
    }
    tbody.innerHTML = res.data.map((item, idx) => `
      <tr>
        <td style="text-align:center;">${idx + 1}</td>
        <td>${item.tgl_pelayanan}</td>
        <td><strong style="color:#7c3aed;">${item.jenis_kontrasepsi}</strong></td>
        <td>${item.tgl_kembali || '-'}</td>
        <td>${item.nama_petugas || '-'}</td>
      </tr>
    `).join('');
  })
  .catch(err => console.error('Error loading KB:', err));
}

function saveKiaKbData() {
  const rawat = '<?= $rawat_esc ?>';
  const rm = '<?= $conn->real_escape_string($pasien['no_rkm_medis'] ?? '') ?>';
  const tgl = document.getElementById('kbTgl')?.value;
  const jenis = document.getElementById('kbJenis')?.value;
  const tglKembali = document.getElementById('kbTglKembali')?.value;
  const keluhan = document.getElementById('kbKeluhan')?.value || '';
  const efek = document.getElementById('kbEfekSamping')?.value || '';

  const formData = new FormData();
  formData.append('action', 'simpan_kia_kb');
  formData.append('no_rawat', rawat);
  formData.append('no_rkm_medis', rm);
  formData.append('tgl_pelayanan', tgl);
  formData.append('jenis_kontrasepsi', jenis);
  formData.append('tgl_kembali', tglKembali);
  formData.append('keluhan', keluhan);
  formData.append('efek_samping', efek);

  fetch('<?= BASE_URL ?>modules/rekam_medis/ajax.php', {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    body: formData
  })
  .then(r => r.json())
  .then(res => {
    if (res.success) {
      showToast(res.message || 'Pelayanan KB berhasil disimpan', 'success');
      loadKiaKb();
    } else {
      showToast(res.message || 'Gagal menyimpan data KB', 'danger');
    }
  })
  .catch(err => {
    console.error(err);
    showToast('Gagal menghubungi server', 'danger');
  });
}
</script>

<!-- Modal Detail Riwayat Medis Lengkap -->
<div class="modal-overlay" id="modalDetailRiwayat" style="display:none;align-items:center;justify-content:center;z-index:9999;">
  <div class="modal" style="max-width:960px;width:95%;max-height:92vh;display:flex;flex-direction:column;border-radius:14px;overflow:hidden;box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);">
    
    <!-- Header -->
    <div class="modal-header" style="background:linear-gradient(135deg, #0f766e 0%, #14b8a6 100%);color:#fff;padding:16px 22px;border-bottom:none;flex-shrink:0;">
      <div>
        <div style="display:flex;align-items:center;gap:9px;">
          <div style="background:rgba(255,255,255,0.2);width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;">
            <i class="fas fa-file-medical-alt" style="font-size:16px;color:#fff;"></i>
          </div>
          <div>
            <h3 style="margin:0;font-size:16px;font-weight:700;color:#fff;letter-spacing:-0.2px;" id="modalRiwayatTitle">
              Detail Riwayat Perawatan Pasien
            </h3>
            <div style="font-size:11.5px;color:#ccfbf1;margin-top:2px;" id="modalRiwayatSubtitle">
              Memuat data rekam medis...
            </div>
          </div>
        </div>
      </div>
      <button type="button" class="modal-close" onclick="closeDetailRiwayatMedis()" style="background:rgba(255,255,255,0.2);color:#fff;border:none;border-radius:50%;width:32px;height:32px;display:flex;align-items:center;justify-content:center;cursor:pointer;">
        <i class="fas fa-times"></i>
      </button>
    </div>

    <!-- Body Scrollable -->
    <div class="modal-body" style="padding:20px;background:#f8fafc;overflow-y:auto;flex:1;" id="modalRiwayatBody">
      <div style="text-align:center;padding:50px 0;">
        <div class="spinner" style="width:36px;height:36px;border-width:3px;border-color:#0d9488;border-top-color:transparent;"></div>
        <p style="margin-top:14px;color:#64748b;font-size:13px;font-weight:500;">Mengambil seluruh rekam jejak klinis pasien...</p>
      </div>
    </div>

    <!-- Footer -->
    <div class="modal-footer" style="padding:12px 22px;background:#fff;border-top:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;flex-shrink:0;">
      <div>
        <button type="button" class="btn btn-outline" id="btnSalinSoap" style="display:none;font-size:12px;gap:6px;border-color:#0d9488;color:#0d9488;font-weight:600;" onclick="salinSoapKeForm()">
          <i class="fas fa-clone"></i> Salin SOAP ke Form Sekarang
        </button>
      </div>
      <div style="display:flex;gap:8px;">
        <a href="#" id="btnCetakResumeModal" target="_blank" class="btn btn-outline-primary" style="font-size:12px;gap:6px;display:none;">
          <i class="fas fa-print"></i> Cetak Resume Medis
        </a>
        <button type="button" class="btn btn-secondary" onclick="closeDetailRiwayatMedis()" style="font-size:12px;padding:6px 16px;">
          Tutup
        </button>
      </div>
    </div>

  </div>
</div>

<!-- Modal Copy Resep Obat Terdahulu -->
<div class="modal-overlay" id="modalCopyResep" style="display:none;align-items:center;justify-content:center;z-index:9999;">
  <div class="modal" style="max-width:880px;width:95%;max-height:92vh;display:flex;flex-direction:column;border-radius:14px;overflow:hidden;box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);">
    
    <!-- Header -->
    <div class="modal-header" style="background:linear-gradient(135deg, #059669 0%, #10b981 100%);color:#fff;padding:16px 22px;border-bottom:none;flex-shrink:0;">
      <div>
        <div style="display:flex;align-items:center;gap:9px;">
          <div style="background:rgba(255,255,255,0.2);width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;">
            <i class="fas fa-copy" style="font-size:16px;color:#fff;"></i>
          </div>
          <div>
            <h3 style="margin:0;font-size:16px;font-weight:700;color:#fff;" id="titleModalCopyResep">
              Salin Resep Obat Terdahulu Pasien
            </h3>
            <div style="font-size:11.5px;color:#d1fae5;margin-top:2px;">
              Pilih riwayat pemberian obat pasien &bull; Anda dapat mengubah jumlah, aturan pakai, atau menghapus item sebelum disimpan
            </div>
          </div>
        </div>
      </div>
      <button type="button" class="modal-close" onclick="closeModalCopyResep()" style="background:rgba(255,255,255,0.2);color:#fff;border:none;border-radius:50%;width:32px;height:32px;display:flex;align-items:center;justify-content:center;cursor:pointer;">
        <i class="fas fa-times"></i>
      </button>
    </div>

    <!-- Body Scrollable -->
    <div class="modal-body" style="padding:18px 22px;background:#f8fafc;overflow-y:auto;flex:1;" id="bodyModalCopyResep">
    </div>

    <!-- Footer -->
    <div class="modal-footer" style="padding:12px 22px;background:#fff;border-top:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;flex-shrink:0;">
      <div id="footerSummaryCopyResep" style="font-size:12px;color:#64748b;">
        0 obat dipilih
      </div>
      <div style="display:flex;gap:8px;">
        <button type="button" class="btn btn-secondary" onclick="closeModalCopyResep()" style="font-size:12.5px;padding:7px 16px;">
          Batal
        </button>
        <button type="button" class="btn btn-success" id="btnTerapkanCopyResep" onclick="submitCopyResepBatch()" style="font-size:12.5px;padding:7px 18px;display:inline-flex;align-items:center;gap:6px;font-weight:700;">
          <i class="fas fa-check"></i> Terapkan ke Resep Aktif
        </button>
      </div>
    </div>

  </div>
</div>

<!-- Modal Edit Item Resep Aktif -->
<div class="modal-overlay" id="modalEditItemResep" style="display:none;align-items:center;justify-content:center;z-index:9999;">
  <div class="modal" style="max-width:480px;width:92%;border-radius:12px;overflow:hidden;box-shadow:0 20px 25px -5px rgba(0,0,0,0.2);">
    <div class="modal-header" style="background:#f8fafc;padding:14px 18px;border-bottom:1px solid #e2e8f0;">
      <div style="display:flex;align-items:center;gap:8px;">
        <i class="fas fa-edit" style="color:#0284c7;"></i>
        <h4 style="margin:0;font-size:14px;font-weight:700;color:#0f172a;">Edit Resep Obat</h4>
      </div>
      <button type="button" class="modal-close" onclick="closeEditItemResep()">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <div class="modal-body" style="padding:18px;background:#fff;">
      <input type="hidden" id="editItemNoResep">
      <input type="hidden" id="editItemKodeBrng">
      
      <div style="margin-bottom:14px;">
        <label style="font-size:11.5px;color:#64748b;margin-bottom:2px;display:block;">Nama Obat</label>
        <div id="editItemNamaBrng" style="font-size:13.5px;font-weight:700;color:#0f172a;"></div>
      </div>

      <div style="margin-bottom:14px;">
        <label style="font-size:11.5px;color:#64748b;margin-bottom:4px;display:block;">Jumlah / Kuantitas</label>
        <div style="display:flex;align-items:center;gap:8px;">
          <input type="number" id="editItemJml" min="1" step="1" class="form-control" style="font-size:13px;width:120px;font-weight:700;">
          <span id="editItemSatuan" style="font-size:12.5px;color:#64748b;font-weight:600;"></span>
        </div>
      </div>

      <div style="margin-bottom:8px;">
        <label style="font-size:11.5px;color:#64748b;margin-bottom:4px;display:block;">Aturan Pakai / Signa</label>
        <input type="text" id="editItemAturan" class="form-control" placeholder="Contoh: 3 x 1 Tablet sesudah makan" style="font-size:13px;">
      </div>
    </div>
    <div class="modal-footer" style="padding:12px 18px;background:#f8fafc;border-top:1px solid #e2e8f0;display:flex;justify-content:flex-end;gap:8px;">
      <button type="button" class="btn btn-secondary btn-sm" onclick="closeEditItemResep()">Batal</button>
      <button type="button" class="btn btn-primary btn-sm" onclick="simpanEditItemResep()" style="display:inline-flex;align-items:center;gap:6px;">
        <i class="fas fa-save"></i> Simpan Perubahan
      </button>
    </div>
  </div>
</div>

<style>
.riwayat-lalu-item {
  padding: 11px 14px;
  border-bottom: 1px solid var(--gray-100);
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  background: #ffffff;
}
.riwayat-lalu-item:hover {
  background: #f0fdf4 !important;
  border-left: 3.5px solid #10b981 !important;
  padding-left: 11px !important;
}
.riwayat-lalu-item:hover .badge-detail-btn {
  background: #10b981 !important;
  color: #ffffff !important;
  border-color: #10b981 !important;
}
.badge-detail-btn {
  background: #ecfdf5;
  color: #059669;
  font-size: 10px;
  padding: 2px 7px;
  border-radius: 4px;
  display: flex;
  align-items: center;
  gap: 3px;
  border: 1px solid #a7f3d0;
  transition: all 0.2s ease;
}
.riwayat-section-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  margin-bottom: 16px;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}
.riwayat-section-header {
  background: #f8fafc;
  padding: 10px 16px;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.riwayat-section-title {
  font-size: 12.5px;
  font-weight: 700;
  color: #1e293b;
  display: flex;
  align-items: center;
  gap: 7px;
}
.riwayat-section-body {
  padding: 14px 16px;
}
.riwayat-grid-info {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 12px;
}
.riwayat-info-item {
  background: #f8fafc;
  padding: 8px 12px;
  border-radius: 8px;
  border: 1px solid #f1f5f9;
}
.riwayat-info-label {
  font-size: 11px;
  color: #64748b;
  margin-bottom: 2px;
}
.riwayat-info-val {
  font-size: 12.5px;
  font-weight: 600;
  color: #1e293b;
}
.vitals-grid-popup {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
  gap: 10px;
}
.vitals-card-item {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 10px 12px;
  text-align: center;
  transition: all 0.2s ease;
}
.vitals-card-item:hover {
  border-color: #0d9488;
  box-shadow: 0 2px 6px rgba(13,148,136,0.1);
}
.vitals-card-val {
  font-size: 15px;
  font-weight: 700;
  color: #0f766e;
  margin: 3px 0 1px;
}
.vitals-card-lbl {
  font-size: 10.5px;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.3px;
  font-weight: 600;
}
.soap-box {
  border-radius: 8px;
  padding: 12px 14px;
  margin-bottom: 10px;
  background: #fafafa;
  border-left: 4px solid #cbd5e1;
}
.soap-box-s { border-left-color: #f59e0b; background: #fffdfa; }
.soap-box-o { border-left-color: #3b82f6; background: #f8faff; }
.soap-box-a { border-left-color: #10b981; background: #f6fdf9; }
.soap-box-p { border-left-color: #8b5cf6; background: #faf8ff; }
.soap-lbl {
  font-size: 11px;
  font-weight: 700;
  margin-bottom: 4px;
  display: flex;
  align-items: center;
  gap: 6px;
}
.soap-txt {
  font-size: 12.5px;
  color: #334155;
  line-height: 1.5;
  white-space: pre-line;
}
.riwayat-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 12px;
}
.riwayat-table th {
  background: #f1f5f9;
  color: #475569;
  font-weight: 600;
  padding: 8px 10px;
  text-align: left;
  border-bottom: 1px solid #e2e8f0;
}
.riwayat-table td {
  padding: 8px 10px;
  border-bottom: 1px solid #f1f5f9;
  color: #334155;
}

/* ═════════════════════════════════════════════════════════════ */
/* ─── ANIMASI & INTERAKSI TAB MENU KLINIS (MODERN & DYNAMIC) ─── */
/* ═════════════════════════════════════════════════════════════ */

.clinical-tab-nav-bar {
  background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
  border: 1px solid #e2e8f0;
  border-radius: 14px 14px 0 0;
  padding: 10px 14px;
  display: flex;
  align-items: center;
  gap: 8px;
  border-bottom: 2px solid #e2e8f0;
  flex-wrap: wrap;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
}

.clinical-tab-btn {
  position: relative;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-weight: 600;
  font-size: 12px;
  padding: 8px 15px;
  border-radius: 9px;
  border: 1px solid #cbd5e1;
  background: linear-gradient(180deg, #ffffff 0%, #f1f5f9 100%);
  color: #334155;
  cursor: pointer;
  transition: all 0.26s cubic-bezier(0.34, 1.56, 0.64, 1);
  overflow: hidden;
  user-select: none;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

/* Shimmer Light Reflection Sweep on Hover */
.clinical-tab-btn::before {
  content: '';
  position: absolute;
  top: 0;
  left: -120%;
  width: 80%;
  height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.65), transparent);
  transform: skewX(-20deg);
  transition: all 0.55s ease;
  z-index: 2;
  pointer-events: none;
}
.clinical-tab-btn:hover::before {
  left: 140%;
}

.clinical-tab-btn i {
  font-size: 13px;
  transition: transform 0.28s cubic-bezier(0.34, 1.56, 0.64, 1), color 0.2s ease, filter 0.2s ease;
}

.clinical-tab-btn .badge {
  transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1), background-color 0.2s ease;
}

/* Hover State: 3D Floating Lift + Ambient Dynamic Glow */
.clinical-tab-btn:hover {
  transform: translateY(-3.5px) scale(1.03);
  background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
  border-color: #94a3b8;
  color: #0f172a;
  box-shadow: 0 10px 22px -3px rgba(15, 23, 42, 0.14), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
  z-index: 3;
}

.clinical-tab-btn:hover i {
  transform: scale(1.3) rotate(-8deg);
}

.clinical-tab-btn:hover .badge {
  transform: scale(1.12);
}

/* Active Click / Spring Press Feedback */
.clinical-tab-btn:active {
  transform: translateY(2px) scale(0.93) !important;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.18) !important;
  transition: transform 0.08s ease;
}

/* Dynamic Click Ripple Effect */
.tab-ripple-effect {
  position: absolute;
  border-radius: 50%;
  transform: scale(0);
  animation: tabRippleAnim 0.6s ease-out;
  background-color: rgba(255, 255, 255, 0.45);
  pointer-events: none;
  inset: 0;
  margin: auto;
  width: 110px;
  height: 110px;
}
@keyframes tabRippleAnim {
  0% { transform: scale(0); opacity: 0.8; }
  100% { transform: scale(2.5); opacity: 0; }
}

/* Active Tab Indicator Glow Animation */
@keyframes pulseIndicator {
  0%, 100% { opacity: 0.95; transform: scaleX(1); }
  50% { opacity: 0.55; transform: scaleX(0.85); }
}

/* Active Selected Tab: 3D Elevated Gradient Pill with Gloss Bevel */
.clinical-tab-btn.active {
  background: linear-gradient(135deg, #0f766e 0%, #0d9488 100%) !important;
  color: #ffffff !important;
  border-color: #0f766e !important;
  box-shadow: 0 6px 18px rgba(13, 148, 136, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.35);
  transform: translateY(-1px);
  z-index: 2;
}

.clinical-tab-btn.active i {
  color: #ffffff !important;
  transform: scale(1.15);
  filter: drop-shadow(0 1px 3px rgba(0, 0, 0, 0.3));
}

.clinical-tab-btn.active .badge {
  background: rgba(255, 255, 255, 0.3) !important;
  color: #ffffff !important;
  box-shadow: 0 0 8px rgba(255, 255, 255, 0.5);
}

.clinical-tab-btn.active::after {
  content: '';
  position: absolute;
  bottom: 0;
  left: 20%;
  right: 20%;
  height: 3px;
  background: #ffffff;
  border-radius: 99px 99px 0 0;
  box-shadow: 0 0 8px rgba(255, 255, 255, 0.85);
  animation: pulseIndicator 1.8s infinite ease-in-out;
}

/* ─── Individual Vivid Accents per Tab on Hover & Active ─── */
#tab-btn-soap:hover {
  border-color: #2dd4bf;
  color: #0f766e;
  box-shadow: 0 10px 22px -3px rgba(15, 118, 110, 0.28);
}
#tab-btn-soap.active {
  background: linear-gradient(135deg, #0f766e 0%, #0d9488 100%) !important;
  border-color: #0f766e !important;
  box-shadow: 0 6px 18px rgba(13, 148, 136, 0.42), inset 0 1px 0 rgba(255, 255, 255, 0.35);
}

#tab-btn-diagnosa:hover {
  border-color: #c084fc;
  color: #7c3aed;
  box-shadow: 0 10px 22px -3px rgba(124, 58, 237, 0.28);
}
#tab-btn-diagnosa.active {
  background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%) !important;
  border-color: #7c3aed !important;
  box-shadow: 0 6px 18px rgba(124, 58, 237, 0.42), inset 0 1px 0 rgba(255, 255, 255, 0.35);
}

#tab-btn-tindakan:hover {
  border-color: #fb7185;
  color: #e11d48;
  box-shadow: 0 10px 22px -3px rgba(225, 29, 72, 0.28);
}
#tab-btn-tindakan.active {
  background: linear-gradient(135deg, #e11d48 0%, #be123c 100%) !important;
  border-color: #e11d48 !important;
  box-shadow: 0 6px 18px rgba(225, 29, 72, 0.42), inset 0 1px 0 rgba(255, 255, 255, 0.35);
}

#tab-btn-resep:hover {
  border-color: #34d399;
  color: #059669;
  box-shadow: 0 10px 22px -3px rgba(5, 150, 105, 0.28);
}
#tab-btn-resep.active {
  background: linear-gradient(135deg, #059669 0%, #047857 100%) !important;
  border-color: #059669 !important;
  box-shadow: 0 6px 18px rgba(5, 150, 105, 0.42), inset 0 1px 0 rgba(255, 255, 255, 0.35);
}

#tab-btn-lab:hover {
  border-color: #38bdf8;
  color: #0284c7;
  box-shadow: 0 10px 22px -3px rgba(2, 132, 199, 0.28);
}
#tab-btn-lab.active {
  background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%) !important;
  border-color: #0284c7 !important;
  box-shadow: 0 6px 18px rgba(2, 132, 199, 0.42), inset 0 1px 0 rgba(255, 255, 255, 0.35);
}

#tab-btn-odontogram:hover {
  border-color: #38bdf8;
  color: #0284c7;
  box-shadow: 0 10px 22px -3px rgba(2, 132, 199, 0.3);
}
#tab-btn-odontogram.active {
  background: linear-gradient(135deg, #0284c7 0%, #075985 100%) !important;
  border-color: #0284c7 !important;
  box-shadow: 0 6px 18px rgba(2, 132, 199, 0.45), inset 0 1px 0 rgba(255, 255, 255, 0.35);
}

#tab-btn-kia:hover {
  border-color: #f472b6;
  color: #db2777;
  box-shadow: 0 10px 22px -3px rgba(219, 39, 119, 0.3);
}
#tab-btn-kia.active {
  background: linear-gradient(135deg, #db2777 0%, #be185d 100%) !important;
  border-color: #db2777 !important;
  box-shadow: 0 6px 18px rgba(219, 39, 119, 0.45), inset 0 1px 0 rgba(255, 255, 255, 0.35);
}

/* ─── Smooth Tab Pane Fade & Slide Entrance ─── */
@keyframes tabPaneEntrance {
  0% {
    opacity: 0;
    transform: translateY(12px) scale(0.995);
  }
  100% {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

.clinical-tab-pane,
.clinical-tab-pane-animate {
  animation: tabPaneEntrance 0.28s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

/* ─── KIA Sub-Tab Buttons Animated ─── */
.kia-subtab-btn {
  position: relative;
  border: 1px solid #e2e8f0;
  background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
  color: #475569;
  font-weight: 600;
  transition: all 0.24s cubic-bezier(0.34, 1.56, 0.64, 1);
  cursor: pointer;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}
.kia-subtab-btn::before {
  content: '';
  position: absolute;
  top: 0;
  left: -120%;
  width: 80%;
  height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.6), transparent);
  transform: skewX(-20deg);
  transition: all 0.5s ease;
  pointer-events: none;
}
.kia-subtab-btn:hover::before {
  left: 140%;
}
.kia-subtab-btn:hover {
  transform: translateY(-2.5px) scale(1.03);
  background: #ffffff;
  border-color: #f472b6;
  color: #db2777;
  box-shadow: 0 8px 18px rgba(219, 39, 119, 0.2);
}
.kia-subtab-btn:active {
  transform: translateY(1px) scale(0.94);
}
.kia-subtab-btn.active {
  background: linear-gradient(135deg, #db2777 0%, #be185d 100%) !important;
  color: #ffffff !important;
  border-color: #db2777 !important;
  box-shadow: 0 5px 14px rgba(219, 39, 119, 0.4), inset 0 1px 0 rgba(255,255,255,0.3);
}
.kia-subtab-btn.active i {
  color: #ffffff !important;
}

/* ─── Odontogram Palette Buttons Animated ─── */
.odont-palette-btn {
  transition: all 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
  cursor: pointer;
}
.odont-palette-btn:hover {
  transform: translateY(-3px) scale(1.06);
  box-shadow: 0 6px 14px rgba(0, 0, 0, 0.14);
}
.odont-palette-btn:active {
  transform: translateY(1px) scale(0.92);
}
.odont-palette-btn.active {
  box-shadow: 0 0 0 3px #0284c7, 0 6px 14px rgba(2, 132, 199, 0.3) !important;
  transform: translateY(-1px) scale(1.04);
}

/* ─── Interactive Tooth Hover Lift & Pulse ─── */
.odont-tooth-wrapper {
  transition: all 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.odont-tooth-wrapper:hover {
  transform: translateY(-4px) scale(1.1);
  border-color: #0284c7 !important;
  box-shadow: 0 8px 18px rgba(2, 132, 199, 0.35);
  z-index: 10;
}
.odont-tooth-wrapper:active {
  transform: translateY(1px) scale(0.92);
}
</style>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
