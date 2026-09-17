<?php
/**
 * SIMKlinik — AJAX Handler: Rekam Medis (E-RM)
 */

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

header('Content-Type: application/json');

if (empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    http_response_code(403);
    exit(json_encode(['error' => 'Forbidden']));
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    // ─── 1. Cari Penyakit (ICD-10) ────────────────────────────
    case 'cari_icd10':
        $q = $conn->real_escape_string(sanitize($_GET['q'] ?? ''));
        $res = $conn->query("
            SELECT kd_penyakit, nm_penyakit, status
            FROM penyakit
            WHERE kd_penyakit LIKE '%$q%' OR nm_penyakit LIKE '%$q%'
            ORDER BY kd_penyakit ASC
            LIMIT 20
        ");
        $data = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) $data[] = $row;
        }
        echo json_encode(['success' => true, 'data' => $data]);
        break;

    // ─── 2. Cari Prosedur / Tindakan (ICD-9) ───────────────────
    case 'cari_icd9':
        $q = $conn->real_escape_string(sanitize($_GET['q'] ?? ''));
        $res = $conn->query("
            SELECT kode, deskripsi_panjang, deskripsi_pendek
            FROM icd9
            WHERE kode LIKE '%$q%' OR deskripsi_panjang LIKE '%$q%' OR deskripsi_pendek LIKE '%$q%'
            ORDER BY kode ASC
            LIMIT 20
        ");
        $data = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) $data[] = $row;
        }
        echo json_encode(['success' => true, 'data' => $data]);
        break;

    // ─── 2b. Cari Tarif Tindakan Rawat Jalan (jns_perawatan) ──
    case 'cari_tindakan':
        $q = $conn->real_escape_string(sanitize($_GET['q'] ?? ''));
        $res = $conn->query("
            SELECT kd_jenis_prw, nm_perawatan, material, bhp, tarif_tindakandr, tarif_tindakanpr,
                   kso, menejemen, total_byrdr, total_byrpr, total_byrdrpr
            FROM jns_perawatan
            WHERE status = '1' AND (kd_jenis_prw LIKE '%$q%' OR nm_perawatan LIKE '%$q%')
            ORDER BY nm_perawatan ASC
            LIMIT 20
        ");
        $data = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) $data[] = $row;
        }
        echo json_encode(['success' => true, 'data' => $data]);
        break;

    // ─── 2c. Simpan Tindakan Pasien (rawat_jl_*) ───────────────
    case 'simpan_tindakan':
        $no_rawat     = $conn->real_escape_string(sanitize($_POST['no_rawat'] ?? ''));
        $kd_jenis_prw = $conn->real_escape_string(sanitize($_POST['kd_jenis_prw'] ?? ''));
        $pelaksana    = sanitize($_POST['pelaksana'] ?? 'dr'); // 'dr', 'pr', 'drpr'
        $kd_dokter    = $conn->real_escape_string(sanitize($_POST['kd_dokter'] ?? ''));
        $nip          = $conn->real_escape_string(sanitize($_POST['nip'] ?? ''));
        $tgl_rawat    = date('Y-m-d');
        $jam_rawat    = date('H:i:s');

        if (empty($no_rawat) || empty($kd_jenis_prw)) {
            echo json_encode(['success' => false, 'message' => 'Parameter tidak lengkap']);
            exit;
        }

        $t_res = $conn->query("SELECT * FROM jns_perawatan WHERE kd_jenis_prw = '$kd_jenis_prw' LIMIT 1");
        if (!$t_res || $t_res->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Tarif tindakan tidak ditemukan']);
            exit;
        }
        $t = $t_res->fetch_assoc();

        $material   = (float)$t['material'];
        $bhp        = (float)$t['bhp'];
        $tarif_dr   = (float)$t['tarif_tindakandr'];
        $tarif_pr   = (float)$t['tarif_tindakanpr'];
        $kso        = (float)$t['kso'];
        $menejemen  = (float)$t['menejemen'];

        if ($pelaksana === 'dr') {
            $biaya = (float)$t['total_byrdr'];
            $ins = $conn->query("INSERT INTO rawat_jl_dr (
                no_rawat, kd_jenis_prw, kd_dokter, tgl_perawatan, jam_rawat,
                material, bhp, tarif_tindakandr, kso, menejemen, biaya_rawat, stts_bayar
            ) VALUES (
                '$no_rawat', '$kd_jenis_prw', '$kd_dokter', '$tgl_rawat', '$jam_rawat',
                $material, $bhp, $tarif_dr, $kso, $menejemen, $biaya, 'Belum'
            )");
        } elseif ($pelaksana === 'pr') {
            $biaya = (float)$t['total_byrpr'];
            $ins = $conn->query("INSERT INTO rawat_jl_pr (
                no_rawat, kd_jenis_prw, nip, tgl_perawatan, jam_rawat,
                material, bhp, tarif_tindakanpr, kso, menejemen, biaya_rawat, stts_bayar
            ) VALUES (
                '$no_rawat', '$kd_jenis_prw', '$nip', '$tgl_rawat', '$jam_rawat',
                $material, $bhp, $tarif_pr, $kso, $menejemen, $biaya, 'Belum'
            )");
        } else { // drpr
            $biaya = (float)$t['total_byrdrpr'];
            $ins = $conn->query("INSERT INTO rawat_jl_drpr (
                no_rawat, kd_jenis_prw, kd_dokter, nip, tgl_perawatan, jam_rawat,
                material, bhp, tarif_tindakandr, tarif_tindakanpr, kso, menejemen, biaya_rawat, stts_bayar
            ) VALUES (
                '$no_rawat', '$kd_jenis_prw', '$kd_dokter', '$nip', '$tgl_rawat', '$jam_rawat',
                $material, $bhp, $tarif_dr, $tarif_pr, $kso, $menejemen, $biaya, 'Belum'
            )");
        }

        if ($ins) {
            echo json_encode(['success' => true, 'message' => 'Tindakan berhasil ditambahkan']);
        } else {
            echo json_encode(['success' => false, 'message' => $conn->error]);
        }
        break;

    // ─── 2d. Hapus Tindakan Pasien ────────────────────────────
    case 'hapus_tindakan':
        $no_rawat     = $conn->real_escape_string(sanitize($_POST['no_rawat'] ?? ''));
        $kd_jenis_prw = $conn->real_escape_string(sanitize($_POST['kd_jenis_prw'] ?? ''));
        $pelaksana    = sanitize($_POST['pelaksana'] ?? 'dr');
        $tgl_rawat    = $conn->real_escape_string(sanitize($_POST['tgl_perawatan'] ?? ''));
        $jam_rawat    = $conn->real_escape_string(sanitize($_POST['jam_rawat'] ?? ''));

        if ($pelaksana === 'dr') {
            $del = $conn->query("DELETE FROM rawat_jl_dr WHERE no_rawat = '$no_rawat' AND kd_jenis_prw = '$kd_jenis_prw' AND tgl_perawatan = '$tgl_rawat' AND jam_rawat = '$jam_rawat'");
        } elseif ($pelaksana === 'pr') {
            $del = $conn->query("DELETE FROM rawat_jl_pr WHERE no_rawat = '$no_rawat' AND kd_jenis_prw = '$kd_jenis_prw' AND tgl_perawatan = '$tgl_rawat' AND jam_rawat = '$jam_rawat'");
        } else {
            $del = $conn->query("DELETE FROM rawat_jl_drpr WHERE no_rawat = '$no_rawat' AND kd_jenis_prw = '$kd_jenis_prw' AND tgl_perawatan = '$tgl_rawat' AND jam_rawat = '$jam_rawat'");
        }

        if ($del) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => $conn->error]);
        }
        break;

    // ─── 3. Cari Obat & BHP ───────────────────────────────────
    case 'cari_obat':
        $q = $conn->real_escape_string(sanitize($_GET['q'] ?? ''));
        $res = $conn->query("
            SELECT db.kode_brng, db.nama_brng, db.ralan as harga,
                   ks.satuan, COALESCE(SUM(gb.stok), 0) as stok
            FROM databarang db
            LEFT JOIN kodesatuan ks ON db.kode_sat = ks.kode_sat
            LEFT JOIN gudangbarang gb ON db.kode_brng = gb.kode_brng
            WHERE (db.kode_brng LIKE '%$q%' OR db.nama_brng LIKE '%$q%')
              AND db.status = '1'
            GROUP BY db.kode_brng
            ORDER BY db.nama_brng ASC
            LIMIT 20
        ");
        $data = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) $data[] = $row;
        }
        echo json_encode(['success' => true, 'data' => $data]);
        break;

    // ─── 4. Tambah Diagnosa Pasien (ICD-10) ───────────────────
    case 'tambah_diagnosa':
        $no_rawat     = $conn->real_escape_string(sanitize($_POST['no_rawat'] ?? ''));
        $kd_penyakit  = $conn->real_escape_string(sanitize($_POST['kd_penyakit'] ?? ''));
        $status_diag  = $conn->real_escape_string(sanitize($_POST['status_diag'] ?? 'Ralan'));
        $status_peny  = $conn->real_escape_string(sanitize($_POST['status_penyakit'] ?? 'Baru'));
        $prioritas    = max(1, (int)($_POST['prioritas'] ?? 1));

        if (empty($no_rawat) || empty($kd_penyakit)) {
            echo json_encode(['success' => false, 'message' => 'Parameter tidak lengkap']);
            exit;
        }

        // Cek apakah sudah ada prioritas 1
        if ($prioritas === 1) {
            // downgrade prioritas 1 lain menjadi 2
            $conn->query("UPDATE diagnosa_pasien SET prioritas = 2 WHERE no_rawat = '$no_rawat' AND prioritas = 1");
        }

        $sql = "INSERT INTO diagnosa_pasien (no_rawat, kd_penyakit, status, prioritas, status_penyakit)
                VALUES ('$no_rawat', '$kd_penyakit', '$status_diag', '$prioritas', '$status_peny')
                ON DUPLICATE KEY UPDATE prioritas = '$prioritas', status_penyakit = '$status_peny'";

        if ($conn->query($sql)) {
            echo json_encode(['success' => true, 'message' => 'Diagnosa berhasil ditambahkan']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal: ' . $conn->error]);
        }
        break;

    // ─── 5. Hapus Diagnosa Pasien ─────────────────────────────
    case 'hapus_diagnosa':
        $no_rawat    = $conn->real_escape_string(sanitize($_POST['no_rawat'] ?? ''));
        $kd_penyakit = $conn->real_escape_string(sanitize($_POST['kd_penyakit'] ?? ''));

        if ($conn->query("DELETE FROM diagnosa_pasien WHERE no_rawat = '$no_rawat' AND kd_penyakit = '$kd_penyakit'")) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => $conn->error]);
        }
        break;

    // ─── 6. Tambah Item Resep Obat ────────────────────────────
    case 'tambah_item_resep':
        $no_rawat     = $conn->real_escape_string(sanitize($_POST['no_rawat'] ?? ''));
        $kd_dokter    = $conn->real_escape_string(sanitize($_POST['kd_dokter'] ?? ''));
        $kode_brng    = $conn->real_escape_string(sanitize($_POST['kode_brng'] ?? ''));
        $jml          = (float)($_POST['jml'] ?? 1);
        $aturan_pakai = $conn->real_escape_string(sanitize($_POST['aturan_pakai'] ?? ''));

        if (empty($no_rawat) || empty($kode_brng)) {
            echo json_encode(['success' => false, 'message' => 'Pilih obat dan jumlah.']);
            exit;
        }

        // Cek atau buat nomor resep untuk kunjungan ini
        $res = $conn->query("SELECT no_resep FROM resep_obat WHERE no_rawat = '$no_rawat' LIMIT 1");
        if ($res && $res->num_rows > 0) {
            $no_resep = $res->fetch_assoc()['no_resep'];
            // Reset status penyerahan & telaah agar status di Farmasi menjadi Belum Tervalidasi
            $conn->query("UPDATE resep_obat SET tgl_penyerahan = '0000-00-00', jam_penyerahan = '00:00:00' WHERE no_resep = '$no_resep'");
            $conn->query("DELETE FROM telaah_resep WHERE no_resep = '$no_resep'");
        } else {
            $today = date('Ymd');
            $c = $conn->query("SELECT COUNT(*) as t FROM resep_obat WHERE no_resep LIKE 'R$today%'");
            $urut = ($c ? (int)$c->fetch_assoc()['t'] : 0) + 1;
            $no_resep = 'R' . $today . str_pad($urut, 4, '0', STR_PAD_LEFT);
            $tgl = date('Y-m-d');
            $jam = date('H:i:s');

            $conn->query("INSERT INTO resep_obat (no_resep, tgl_perawatan, jam, no_rawat, kd_dokter, tgl_peresepan, jam_peresepan, status, tgl_penyerahan, jam_penyerahan)
                          VALUES ('$no_resep', '$tgl', '$jam', '$no_rawat', '$kd_dokter', '$tgl', '$jam', 'ralan', '0000-00-00', '00:00:00')");
        }

        // Insert item ke resep_dokter
        $conn->query("DELETE FROM resep_dokter WHERE no_resep = '$no_resep' AND kode_brng = '$kode_brng'");
        $ins = $conn->query("INSERT INTO resep_dokter (no_resep, kode_brng, jml, aturan_pakai)
                             VALUES ('$no_resep', '$kode_brng', '$jml', '$aturan_pakai')");

        if ($ins) {
            echo json_encode(['success' => true, 'no_resep' => $no_resep]);
        } else {
            echo json_encode(['success' => false, 'message' => $conn->error]);
        }
        break;

    // ─── 7. Hapus Item Resep Obat ─────────────────────────────
    case 'hapus_item_resep':
        $no_resep  = $conn->real_escape_string(sanitize($_POST['no_resep'] ?? ''));
        $kode_brng = $conn->real_escape_string(sanitize($_POST['kode_brng'] ?? ''));

        if ($conn->query("DELETE FROM resep_dokter WHERE no_resep = '$no_resep' AND kode_brng = '$kode_brng'")) {
            // Cek jika tidak ada item tersisa, hapus header resep_obat
            $c = $conn->query("SELECT COUNT(*) as t FROM resep_dokter WHERE no_resep = '$no_resep'");
            if ($c && (int)$c->fetch_assoc()['t'] === 0) {
                $conn->query("DELETE FROM resep_obat WHERE no_resep = '$no_resep'");
            }
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => $conn->error]);
        }
        break;

    // ─── 7b. Update Item Resep Obat (Edit Jumlah & Aturan Pakai) ─
    case 'update_item_resep':
        $no_resep     = $conn->real_escape_string(sanitize($_POST['no_resep'] ?? ''));
        $kode_brng    = $conn->real_escape_string(sanitize($_POST['kode_brng'] ?? ''));
        $jml          = (float)($_POST['jml'] ?? 1);
        $aturan_pakai = $conn->real_escape_string(sanitize($_POST['aturan_pakai'] ?? ''));

        if (empty($no_resep) || empty($kode_brng)) {
            echo json_encode(['success' => false, 'message' => 'Data tidak lengkap.']);
            exit;
        }

        $upd = $conn->query("
            UPDATE resep_dokter
            SET jml = '$jml', aturan_pakai = '$aturan_pakai'
            WHERE no_resep = '$no_resep' AND kode_brng = '$kode_brng'
        ");

        if ($upd) {
            // Ketika resep diedit oleh dokter, reset status agar berstatus Belum Tervalidasi di farmasi
            $conn->query("UPDATE resep_obat SET tgl_penyerahan = '0000-00-00', jam_penyerahan = '00:00:00' WHERE no_resep = '$no_resep'");
            $conn->query("DELETE FROM telaah_resep WHERE no_resep = '$no_resep'");
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => $conn->error]);
        }
        break;

    // ─── 7c. Tambah / Simpan Resep Obat Racikan ───────────────
    case 'tambah_resep_racikan':
        $no_rawat     = $conn->real_escape_string(sanitize($_POST['no_rawat'] ?? ''));
        $kd_dokter    = $conn->real_escape_string(sanitize($_POST['kd_dokter'] ?? ''));
        $nama_racik   = $conn->real_escape_string(sanitize($_POST['nama_racik'] ?? 'Puyer Racikan'));
        $kd_racik     = $conn->real_escape_string(sanitize($_POST['kd_racik'] ?? 'R01'));
        $jml_dr       = (int)($_POST['jml_dr'] ?? 10);
        $aturan_pakai = $conn->real_escape_string(sanitize($_POST['aturan_pakai'] ?? ''));
        $keterangan   = $conn->real_escape_string(sanitize($_POST['keterangan'] ?? '-'));
        $edit_no_racik= sanitize($_POST['edit_no_racik'] ?? '');

        // Bahan racikan (array JSON atau POST)
        $bahan_raw = $_POST['bahan'] ?? '[]';
        $bahan_list = is_array($bahan_raw) ? $bahan_raw : json_decode($bahan_raw, true);

        if (empty($no_rawat) || empty($nama_racik) || empty($bahan_list)) {
            echo json_encode(['success' => false, 'message' => 'Lengkapi nama racikan, jumlah kemasan, dan minimal 1 bahan obat.']);
            exit;
        }

        // Cek atau buat nomor resep untuk kunjungan ini
        $res = $conn->query("SELECT no_resep FROM resep_obat WHERE no_rawat = '$no_rawat' LIMIT 1");
        if ($res && $res->num_rows > 0) {
            $no_resep = $res->fetch_assoc()['no_resep'];
            // Reset status penyerahan & telaah agar status di Farmasi menjadi Belum Tervalidasi
            $conn->query("UPDATE resep_obat SET tgl_penyerahan = '0000-00-00', jam_penyerahan = '00:00:00' WHERE no_resep = '$no_resep'");
            $conn->query("DELETE FROM telaah_resep WHERE no_resep = '$no_resep'");
        } else {
            $today = date('Ymd');
            $c = $conn->query("SELECT COUNT(*) as t FROM resep_obat WHERE no_resep LIKE 'R$today%'");
            $urut = ($c ? (int)$c->fetch_assoc()['t'] : 0) + 1;
            $no_resep = 'R' . $today . str_pad($urut, 4, '0', STR_PAD_LEFT);
            $tgl = date('Y-m-d');
            $jam = date('H:i:s');

            $conn->query("INSERT INTO resep_obat (no_resep, tgl_perawatan, jam, no_rawat, kd_dokter, tgl_peresepan, jam_peresepan, status, tgl_penyerahan, jam_penyerahan)
                          VALUES ('$no_resep', '$tgl', '$jam', '$no_rawat', '$kd_dokter', '$tgl', '$jam', 'ralan', '0000-00-00', '00:00:00')");
        }

        // Tentukan no_racik
        if (!empty($edit_no_racik)) {
            $no_racik = (string)$edit_no_racik;
            // Hapus data lama untuk no_racik ini
            $conn->query("DELETE FROM resep_dokter_racikan_detail WHERE no_resep = '$no_resep' AND no_racik = '$no_racik'");
            $conn->query("DELETE FROM resep_dokter_racikan WHERE no_resep = '$no_resep' AND no_racik = '$no_racik'");
        } else {
            $q_nr = $conn->query("SELECT MAX(CAST(no_racik AS UNSIGNED)) as max_nr FROM resep_dokter_racikan WHERE no_resep = '$no_resep'");
            $max_nr = $q_nr ? (int)$q_nr->fetch_assoc()['max_nr'] : 0;
            $no_racik = (string)($max_nr + 1);
        }

        // Insert header racikan
        $conn->query("
            INSERT INTO resep_dokter_racikan (no_resep, no_racik, nama_racik, kd_racik, jml_dr, aturan_pakai, keterangan)
            VALUES ('$no_resep', '$no_racik', '$nama_racik', '$kd_racik', '$jml_dr', '$aturan_pakai', '$keterangan')
        ");

        // Insert detail bahan racikan
        $saved_bahan = 0;
        foreach ($bahan_list as $b) {
            $k_brng   = $conn->real_escape_string($b['kode_brng'] ?? '');
            $p1       = (float)($b['p1'] ?? 1);
            $p2       = (float)($b['p2'] ?? 1);
            $kandungan= $conn->real_escape_string($b['kandungan'] ?? '-');
            $jml_tot  = (float)($b['jml'] ?? ($jml_dr * ($p1 / ($p2 ?: 1))));

            if (!empty($k_brng) && $jml_tot > 0) {
                $ins_b = $conn->query("
                    INSERT INTO resep_dokter_racikan_detail (no_resep, no_racik, kode_brng, p1, p2, kandungan, jml)
                    VALUES ('$no_resep', '$no_racik', '$k_brng', '$p1', '$p2', '$kandungan', '$jml_tot')
                ");
                if ($ins_b) $saved_bahan++;
            }
        }

        if ($saved_bahan > 0) {
            echo json_encode(['success' => true, 'no_resep' => $no_resep, 'no_racik' => $no_racik, 'message' => "Racikan $nama_racik berhasil disimpan."]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal menyimpan bahan racikan.']);
        }
        break;

    // ─── 7d. Hapus Resep Racikan ──────────────────────────────
    case 'hapus_resep_racikan':
        $no_resep = $conn->real_escape_string(sanitize($_POST['no_resep'] ?? ''));
        $no_racik = $conn->real_escape_string(sanitize($_POST['no_racik'] ?? ''));

        if (!empty($no_resep) && !empty($no_racik)) {
            $conn->query("DELETE FROM resep_dokter_racikan_detail WHERE no_resep = '$no_resep' AND no_racik = '$no_racik'");
            $conn->query("DELETE FROM resep_dokter_racikan WHERE no_resep = '$no_resep' AND no_racik = '$no_racik'");

            // Cek jika tidak ada item resep_dokter dan resep_dokter_racikan tersisa
            $c1 = (int)($conn->query("SELECT COUNT(*) as t FROM resep_dokter WHERE no_resep = '$no_resep'")->fetch_assoc()['t'] ?? 0);
            $c2 = (int)($conn->query("SELECT COUNT(*) as t FROM resep_dokter_racikan WHERE no_resep = '$no_resep'")->fetch_assoc()['t'] ?? 0);
            if ($c1 === 0 && $c2 === 0) {
                $conn->query("DELETE FROM resep_obat WHERE no_resep = '$no_resep'");
            } else {
                // Reset status telaah & penyerahan
                $conn->query("UPDATE resep_obat SET tgl_penyerahan = '0000-00-00', jam_penyerahan = '00:00:00' WHERE no_resep = '$no_resep'");
                $conn->query("DELETE FROM telaah_resep WHERE no_resep = '$no_resep'");
            }
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Data nomor resep tidak valid.']);
        }
        break;

    // ─── 7c. Get Riwayat Peresepan Obat Terdahulu Pasien ───────
    case 'get_riwayat_resep':
        $no_rm = $conn->real_escape_string(sanitize($_GET['no_rkm_medis'] ?? ''));
        $current_rawat = $conn->real_escape_string(sanitize($_GET['no_rawat'] ?? ''));

        if (empty($no_rm) && !empty($current_rawat)) {
            $qrm = $conn->query("SELECT no_rkm_medis FROM reg_periksa WHERE no_rawat = '$current_rawat' LIMIT 1");
            if ($qrm && $r = $qrm->fetch_assoc()) {
                $no_rm = $r['no_rkm_medis'];
            }
        }

        if (empty($no_rm)) {
            echo json_encode(['success' => false, 'message' => 'No. Rekam Medis tidak ditemukan.']);
            exit;
        }

        // Ambil daftar resep sebelumnya (exclude rawat saat ini bila ada)
        $whereExclude = !empty($current_rawat) ? "AND ro.no_rawat != '$current_rawat'" : "";
        $qResep = $conn->query("
            SELECT ro.no_resep, ro.no_rawat, ro.tgl_peresepan, ro.jam_peresepan,
                   pol.nm_poli, d.nm_dokter, reg.tgl_registrasi
            FROM resep_obat ro
            JOIN reg_periksa reg ON ro.no_rawat = reg.no_rawat
            LEFT JOIN poliklinik pol ON reg.kd_poli = pol.kd_poli
            LEFT JOIN dokter d ON ro.kd_dokter = d.kd_dokter
            WHERE reg.no_rkm_medis = '$no_rm' $whereExclude
            ORDER BY ro.tgl_peresepan DESC, ro.jam_peresepan DESC
            LIMIT 15
        ");

        $resepList = [];
        if ($qResep) {
            while ($row = $qResep->fetch_assoc()) {
                $noResep = $conn->real_escape_string($row['no_resep']);
                $qItems = $conn->query("
                    SELECT rd.kode_brng, db.nama_brng, rd.jml,
                           COALESCE(ks.satuan, db.kode_sat, 'Item') as satuan,
                           rd.aturan_pakai, db.ralan as harga
                    FROM resep_dokter rd
                    JOIN databarang db ON rd.kode_brng = db.kode_brng
                    LEFT JOIN kodesatuan ks ON db.kode_sat = ks.kode_sat
                    WHERE rd.no_resep = '$noResep'
                    ORDER BY db.nama_brng ASC
                ");
                $items = [];
                if ($qItems) {
                    while ($it = $qItems->fetch_assoc()) {
                        $items[] = $it;
                    }
                }
                $row['items'] = $items;
                $row['total_item'] = count($items);
                if (count($items) > 0) {
                    $resepList[] = $row;
                }
            }
        }

        echo json_encode(['success' => true, 'data' => $resepList]);
        break;

    // ─── 7d. Copy Resep Batch ke Resep Kunjungan Aktif ────────
    case 'copy_resep_batch':
        $no_rawat  = $conn->real_escape_string(sanitize($_POST['no_rawat'] ?? ''));
        $kd_dokter = $conn->real_escape_string(sanitize($_POST['kd_dokter'] ?? ''));
        $itemsJson = $_POST['items'] ?? '[]';
        $items     = json_decode($itemsJson, true);

        if (empty($no_rawat) || empty($items) || !is_array($items)) {
            echo json_encode(['success' => false, 'message' => 'Daftar obat tidak boleh kosong.']);
            exit;
        }

        // Cek atau buat nomor resep untuk kunjungan ini
        $res = $conn->query("SELECT no_resep FROM resep_obat WHERE no_rawat = '$no_rawat' LIMIT 1");
        if ($res && $res->num_rows > 0) {
            $no_resep = $res->fetch_assoc()['no_resep'];
            // Pastikan resep berstatus BELUM TERVALIDASI di Farmasi (tgl_penyerahan 0000-00-00 dan hapus telaah lama jika ada)
            $conn->query("UPDATE resep_obat SET tgl_penyerahan = '0000-00-00', jam_penyerahan = '00:00:00' WHERE no_resep = '$no_resep'");
            $conn->query("DELETE FROM telaah_resep WHERE no_resep = '$no_resep'");
        } else {
            $today = date('Ymd');
            $c = $conn->query("SELECT COUNT(*) as t FROM resep_obat WHERE no_resep LIKE 'R$today%'");
            $urut = ($c ? (int)$c->fetch_assoc()['t'] : 0) + 1;
            $no_resep = 'R' . $today . str_pad($urut, 4, '0', STR_PAD_LEFT);
            $tgl = date('Y-m-d');
            $jam = date('H:i:s');

            // Set tgl_penyerahan dan jam_penyerahan ke 0000-00-00 agar berstatus Belum Tervalidasi di farmasi
            $conn->query("INSERT INTO resep_obat (no_resep, tgl_perawatan, jam, no_rawat, kd_dokter, tgl_peresepan, jam_peresepan, status, tgl_penyerahan, jam_penyerahan)
                          VALUES ('$no_resep', '$tgl', '$jam', '$no_rawat', '$kd_dokter', '$tgl', '$jam', 'ralan', '0000-00-00', '00:00:00')");
        }

        $countAdded = 0;
        foreach ($items as $it) {
            $kode_brng    = $conn->real_escape_string(sanitize($it['kode_brng'] ?? ''));
            $jml          = (float)($it['jml'] ?? 1);
            $aturan_pakai = $conn->real_escape_string(sanitize($it['aturan_pakai'] ?? ''));

            if (!empty($kode_brng) && $jml > 0) {
                // Delete if exists and insert
                $conn->query("DELETE FROM resep_dokter WHERE no_resep = '$no_resep' AND kode_brng = '$kode_brng'");
                $ins = $conn->query("INSERT INTO resep_dokter (no_resep, kode_brng, jml, aturan_pakai)
                                     VALUES ('$no_resep', '$kode_brng', '$jml', '$aturan_pakai')");
                if ($ins) $countAdded++;
            }
        }

        echo json_encode([
            'success' => true,
            'no_resep' => $no_resep,
            'count'    => $countAdded,
            'message'  => "$countAdded obat berhasil disalin ke resep aktif."
        ]);
        break;

    // ─── Ubah Status Registrasi Rawat Jalan ───────────────────
    case 'ubah_status':
        $no_rawat = $conn->real_escape_string(sanitize($_POST['no_rawat'] ?? ''));
        $stts     = $conn->real_escape_string(sanitize($_POST['stts'] ?? ''));

        if (empty($no_rawat) || empty($stts)) {
            echo json_encode(['success' => false, 'message' => 'Parameter tidak lengkap']);
            exit;
        }

        $upd = $conn->query("UPDATE reg_periksa SET stts = '$stts' WHERE no_rawat = '$no_rawat'");
        if ($upd) {
            echo json_encode([
                'success'    => true,
                'message'    => "Status berhasil diubah menjadi '$stts'",
                'stts'       => $stts,
                'badge_html' => badge_status($stts),
                'row_class'  => get_status_row_class($stts),
                'row_style'  => get_status_row_style($stts)
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal mengubah status: ' . $conn->error]);
        }
        break;

    // ─── 8. Live Real-Time Polling Antrian Rawat Jalan ────────
    case 'get_live_antrian':
        $today     = date('Y-m-d');
        $tgl       = sanitize($_GET['tgl'] ?? $today);
        $kd_poli   = sanitize($_GET['kd_poli'] ?? '');
        $kd_dokter = sanitize($_GET['kd_dokter'] ?? '');
        $status    = sanitize($_GET['status'] ?? '');
        $search    = sanitize($_GET['q'] ?? '');
        $sort_by   = sanitize($_GET['sort_by'] ?? 'dokter_noreg');

        $where = "r.tgl_registrasi = '$tgl'";
        if ($kd_poli)   $where .= " AND r.kd_poli = '" . $conn->real_escape_string($kd_poli) . "'";
        if ($kd_dokter) $where .= " AND r.kd_dokter = '" . $conn->real_escape_string($kd_dokter) . "'";
        if ($status)    $where .= " AND r.stts = '" . $conn->real_escape_string($status) . "'";
        if ($search) {
            $s = $conn->real_escape_string($search);
            $where .= " AND (p.nm_pasien LIKE '%$s%' OR p.no_rkm_medis LIKE '%$s%' OR r.no_rawat LIKE '%$s%')";
        }

        // Pengurutan (Sorting)
        switch ($sort_by) {
            case 'noreg':
                $order_sql = "CAST(r.no_reg AS UNSIGNED) ASC, r.jam_reg ASC";
                break;
            case 'belum_jam':
                $order_sql = "(r.stts = 'Belum') DESC, r.jam_reg ASC";
                break;
            case 'jam_asc':
                $order_sql = "r.jam_reg ASC";
                break;
            case 'jam_desc':
                $order_sql = "r.jam_reg DESC";
                break;
            case 'nama_pasien':
                $order_sql = "p.nm_pasien ASC";
                break;
            case 'dokter_noreg':
            default:
                $order_sql = "d.nm_dokter ASC, CAST(r.no_reg AS UNSIGNED) ASC, r.jam_reg ASC";
                break;
        }

        $page     = max(1, (int)($_GET['page'] ?? 1));
        $per_page = max(1, (int)($_GET['per_page'] ?? 25));
        $offset   = ($page - 1) * $per_page;

        $count_res = $conn->query("
            SELECT COUNT(DISTINCT r.no_rawat) as total
            FROM reg_periksa r
            JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
            WHERE $where
        ");
        $total_count = $count_res ? (int)$count_res->fetch_assoc()['total'] : 0;

        $result = $conn->query("
            SELECT r.no_rawat, r.no_reg, r.tgl_registrasi, r.jam_reg,
                   r.stts, r.status_lanjut, r.kd_dokter, r.kd_poli,
                   p.nm_pasien, p.no_rkm_medis, p.jk, p.tgl_lahir, p.no_peserta,
                   d.nm_dokter, pol.nm_poli, pj.png_jawab as nm_penjab,
                   (SELECT COUNT(*) FROM pemeriksaan_ralan pr WHERE pr.no_rawat = r.no_rawat) as has_soap,
                   (SELECT GROUP_CONCAT(CONCAT(dp.kd_penyakit, ' - ', py.nm_penyakit) SEPARATOR '<br>')
                    FROM diagnosa_pasien dp
                    JOIN penyakit py ON dp.kd_penyakit = py.kd_penyakit
                    WHERE dp.no_rawat = r.no_rawat) as diagnosa_list,
                   (SELECT COUNT(*) FROM resep_obat ro WHERE ro.no_rawat = r.no_rawat) as has_resep
            FROM reg_periksa r
            JOIN pasien p      ON r.no_rkm_medis = p.no_rkm_medis
            LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
            LEFT JOIN poliklinik pol ON r.kd_poli = pol.kd_poli
            LEFT JOIN penjab pj ON p.kd_pj = pj.kd_pj
            WHERE $where
            GROUP BY r.no_rawat
            ORDER BY $order_sql
            LIMIT $per_page OFFSET $offset
        ");

        $kunjungan_list = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) $kunjungan_list[] = $row;
        }

        ob_start();
        if (empty($kunjungan_list)): ?>
          <tr>
            <td colspan="8" style="text-align:center;padding:30px;color:#94a3b8;">
              <i class="fas fa-user-clock" style="font-size:28px;color:#cbd5e1;display:block;margin-bottom:8px;"></i>
              Tidak ada pasien dalam antrian pada tanggal ini.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($kunjungan_list as $k): ?>
            <tr id="row-<?= htmlspecialchars($k['no_rawat']) ?>" class="<?= get_status_row_class($k['stts']) ?>" style="<?= get_status_row_style($k['stts']) ?>">
              <td style="text-align:center;font-weight:700;font-size:15px;color:var(--primary-700);">
                <?= htmlspecialchars($k['no_reg']) ?>
              </td>
              <td>
                <div style="display:flex;align-items:center;gap:10px;">
                  <div style="width:34px;height:34px;border-radius:50%;background:<?= $k['jk']==='L' ? 'var(--primary-50)' : '#fdf2f8' ?>;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:<?= $k['jk']==='L' ? 'var(--primary-600)' : '#9d174d' ?>;flex-shrink:0;">
                    <?= strtoupper(substr($k['nm_pasien'], 0, 1)) ?>
                  </div>
                  <div>
                    <div style="font-size:13px;font-weight:600;"><?= htmlspecialchars($k['nm_pasien']) ?></div>
                    <div style="font-size:11px;color:var(--gray-400);">
                      RM: <strong style="color:var(--primary-600);"><?= $k['no_rkm_medis'] ?></strong> &nbsp;|&nbsp;
                      <?= icon_jk($k['jk']) ?> <?= hitung_umur($k['tgl_lahir']) ?>
                    </div>
                    <div style="font-size:10px;color:var(--gray-400);font-family:monospace;">
                      <?= $k['no_rawat'] ?>
                    </div>
                  </div>
                </div>
              </td>
              <td>
                <div style="font-size:12px;font-weight:600;color:var(--gray-800);"><?= htmlspecialchars($k['nm_poli'] ?? '-') ?></div>
                <div style="font-size:11px;color:var(--gray-500);"><?= htmlspecialchars($k['nm_dokter'] ?? '-') ?></div>
                <div style="font-size:10px;color:var(--gray-400);"><?= substr($k['jam_reg'], 0, 5) ?> WIB</div>
              </td>
              <td>
                <span class="badge badge-<?= str_contains(strtolower($k['nm_penjab'] ?? ''), 'bpjs') ? 'primary' : 'secondary' ?>">
                  <?= htmlspecialchars($k['nm_penjab'] ?? 'Umum') ?>
                </span>
              </td>
              <td>
                <?php if (!empty($k['diagnosa_list'])): ?>
                  <div style="font-size:11px;color:var(--gray-700);line-height:1.4;">
                    <?= $k['diagnosa_list'] ?>
                  </div>
                <?php else: ?>
                  <span style="font-size:11px;color:var(--gray-300);font-style:italic;">Belum ada diagnosa</span>
                <?php endif; ?>
              </td>
              <td>
                <div style="display:flex;gap:4px;flex-wrap:wrap;">
                  <?php if ($k['has_soap'] > 0): ?>
                    <span class="badge badge-success" title="SOAP Tersimpan"><i class="fas fa-check" style="margin-right:2px;"></i> SOAP</span>
                  <?php else: ?>
                    <span class="badge badge-secondary" title="SOAP Belum Diisi">SOAP</span>
                  <?php endif; ?>

                  <?php if (!empty($k['diagnosa_list'])): ?>
                    <span class="badge badge-success" title="Diagnosa ICD-10 Tersimpan"><i class="fas fa-check" style="margin-right:2px;"></i> ICD-10</span>
                  <?php else: ?>
                    <span class="badge badge-secondary">ICD-10</span>
                  <?php endif; ?>

                  <?php if ($k['has_resep'] > 0): ?>
                    <span class="badge badge-info" title="Resep Obat Diberikan"><i class="fas fa-pills" style="margin-right:2px;"></i> Resep</span>
                  <?php endif; ?>
                </div>
              </td>
              <td id="status-cell-<?= htmlspecialchars($k['no_rawat']) ?>">
                <div style="display:flex;align-items:center;gap:6px;">
                  <span id="badge-status-<?= htmlspecialchars($k['no_rawat']) ?>"><?= badge_status($k['stts']) ?></span>
                  <button type="button" class="btn btn-sm btn-outline btn-icon" style="width:24px;height:24px;padding:0;font-size:10px;border-radius:4px;"
                          title="Ubah Status Pasien"
                          onclick="openUbahStatusModal('<?= htmlspecialchars($k['no_rawat']) ?>', '<?= htmlspecialchars(addslashes($k['nm_pasien'])) ?>', '<?= htmlspecialchars($k['stts']) ?>')">
                    <i class="fas fa-edit"></i>
                  </button>
                </div>
              </td>
              <td style="text-align:center;">
                <div style="display:flex;gap:5px;justify-content:center;align-items:center;">
                  <a href="<?= BASE_URL ?>modules/rekam_medis/periksa.php?no_rawat=<?= urlencode($k['no_rawat']) ?>"
                     class="btn btn-sm <?= $k['stts']==='Sudah' ? 'btn-outline-primary' : 'btn-primary' ?>" title="Periksa Medis (SOAP)">
                    <i class="fas fa-stethoscope"></i> <?= $k['stts']==='Sudah' ? 'Edit EMR' : 'Periksa' ?>
                  </a>
                  <?php if ($k['stts'] === 'Sudah'): ?>
                    <a href="<?= BASE_URL ?>modules/rekam_medis/cetak_resume.php?no_rawat=<?= urlencode($k['no_rawat']) ?>"
                       target="_blank" class="btn btn-sm btn-outline btn-icon" title="Cetak Resume Medis">
                      <i class="fas fa-print"></i>
                    </a>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif;
        $html = ob_get_clean();

        echo json_encode([
            'success' => true,
            'count'   => $total_count,
            'html'    => $html
        ]);
        break;

    // ─── 10. Detail Riwayat Medis Lengkap untuk Popup Modal ───
    case 'get_detail_riwayat':
        $rawat = $conn->real_escape_string(sanitize($_GET['no_rawat'] ?? $_POST['no_rawat'] ?? ''));
        if (!$rawat) {
            echo json_encode(['success' => false, 'error' => 'No. Rawat tidak valid']);
            break;
        }

        // 1. Pendaftaran & Kunjungan
        $qReg = $conn->query("
            SELECT r.no_rawat, r.no_reg, r.tgl_registrasi, r.jam_reg, r.stts, r.status_bayar,
                   p.no_rkm_medis, p.nm_pasien, p.jk, p.tgl_lahir, p.umur, p.alamat,
                   pol.nm_poli, d.nm_dokter, pj.png_jawab as nm_penjab
            FROM reg_periksa r
            JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
            LEFT JOIN poliklinik pol ON r.kd_poli = pol.kd_poli
            LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
            LEFT JOIN penjab pj ON r.kd_pj = pj.kd_pj
            WHERE r.no_rawat = '$rawat'
            LIMIT 1
        ");
        $pendaftaran = $qReg ? $qReg->fetch_assoc() : null;

        if (!$pendaftaran) {
            echo json_encode(['success' => false, 'error' => 'Data kunjungan tidak ditemukan']);
            break;
        }

        // 2. SOAP & Tanda Vital (Termasuk User Petugas & Seluruh Entri SOAP)
        $qSoap = $conn->query("
            SELECT pr.*,
                   COALESCE(pg.nama, pt.nama, d.nm_dokter, pr.nip, '-') as nama_petugas
            FROM pemeriksaan_ralan pr
            LEFT JOIN pegawai pg ON pr.nip = pg.nik
            LEFT JOIN petugas pt ON pr.nip = pt.nip
            LEFT JOIN dokter d ON pr.nip = d.kd_dokter
            WHERE pr.no_rawat = '$rawat'
            ORDER BY pr.tgl_perawatan DESC, pr.jam_rawat DESC
        ");
        $soap_list = [];
        if ($qSoap) {
            while ($row = $qSoap->fetch_assoc()) $soap_list[] = $row;
        }
        $soap = $soap_list[0] ?? null;

        // 3. Diagnosa ICD-10
        $qDiag = $conn->query("
            SELECT dp.kd_penyakit, py.nm_penyakit, dp.prioritas, dp.status_penyakit
            FROM diagnosa_pasien dp
            LEFT JOIN penyakit py ON dp.kd_penyakit = py.kd_penyakit
            WHERE dp.no_rawat = '$rawat'
            ORDER BY dp.prioritas ASC, dp.kd_penyakit ASC
        ");
        $diagnosa = [];
        if ($qDiag) {
            while ($row = $qDiag->fetch_assoc()) $diagnosa[] = $row;
        }

        // 4. Tindakan & Prosedur Medis
        $qTindakan = $conn->query("
            SELECT rj.kd_jenis_prw, jp.nm_perawatan, rj.biaya_rawat, rj.tgl_perawatan, rj.jam_rawat, d.nm_dokter
            FROM rawat_jl_dr rj
            LEFT JOIN jns_perawatan jp ON rj.kd_jenis_prw = jp.kd_jenis_prw
            LEFT JOIN dokter d ON rj.kd_dokter = d.kd_dokter
            WHERE rj.no_rawat = '$rawat'
            ORDER BY rj.jam_rawat ASC
        ");
        $tindakan = [];
        if ($qTindakan) {
            while ($row = $qTindakan->fetch_assoc()) $tindakan[] = $row;
        }

        // 5. Resep Obat & Terapi Farmasi
        $qResep = $conn->query("
            SELECT ro.no_resep, ro.tgl_peresepan, ro.jam_peresepan, ro.status, d.nm_dokter
            FROM resep_obat ro
            LEFT JOIN dokter d ON ro.kd_dokter = d.kd_dokter
            WHERE ro.no_rawat = '$rawat'
            ORDER BY ro.tgl_peresepan DESC, ro.jam_peresepan DESC
            LIMIT 1
        ");
        $resepData = null;
        if ($qResep && ($rRow = $qResep->fetch_assoc())) {
            $noResep = $conn->real_escape_string($rRow['no_resep']);
            $qItems = $conn->query("
                SELECT rd.kode_brng, db.nama_brng, rd.jml, ks.satuan, rd.aturan_pakai
                FROM resep_dokter rd
                LEFT JOIN databarang db ON rd.kode_brng = db.kode_brng
                LEFT JOIN kodesatuan ks ON db.kode_sat = ks.kode_sat
                WHERE rd.no_resep = '$noResep'
            ");
            $items = [];
            if ($qItems) {
                while ($it = $qItems->fetch_assoc()) $items[] = $it;
            }
            $rRow['items'] = $items;
            $resepData = $rRow;
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'pendaftaran' => $pendaftaran,
                'soap'        => $soap,
                'soap_list'   => $soap_list,
                'diagnosa'    => $diagnosa,
                'tindakan'    => $tindakan,
                'resep'       => $resepData
            ]
        ]);
        break;

    // ─── Trigger BPJS Antrean Task 4 (Background / Non-blocking) ───
    case 'trigger_task4':
        require_once dirname(__DIR__, 2) . '/includes/bpjs_antrean.php';
        $no_rawat = sanitize($_GET['no_rawat'] ?? $_POST['no_rawat'] ?? '');
        if (!empty($no_rawat)) {
            $res = BpjsAntreanService::triggerTaskByRawat($no_rawat, 4);
            echo json_encode(['success' => true, 'data' => $res]);
        } else {
            echo json_encode(['success' => false, 'message' => 'no_rawat parameter required']);
        }
        break;

    // ═════════════════════════════════════════════════════════════
    // ─── MODUL ODONTOGRAM (POLI GIGI & MULUT) ─────────────────────
    // ═════════════════════════════════════════════════════════════

    case 'get_odontogram':
        $no_rawat     = $conn->real_escape_string(sanitize($_GET['no_rawat'] ?? ''));
        $no_rkm_medis = $conn->real_escape_string(sanitize($_GET['no_rkm_medis'] ?? ''));

        if (empty($no_rawat) && empty($no_rkm_medis)) {
            echo json_encode(['success' => false, 'message' => 'Parameter tidak lengkap']);
            exit;
        }

        // Cari header odontogram terkini (utamakan no_rawat sekarang, jika belum ada cari kunjungan terakhir)
        $header = null;
        if (!empty($no_rawat)) {
            $qH = $conn->query("SELECT * FROM mlite_odontogram WHERE no_rawat = '$no_rawat' LIMIT 1");
            if ($qH && $qH->num_rows > 0) {
                $header = $qH->fetch_assoc();
            }
        }
        if (!$header && !empty($no_rkm_medis)) {
            $qH = $conn->query("SELECT * FROM mlite_odontogram WHERE no_rkm_medis = '$no_rkm_medis' ORDER BY tgl_perawatan DESC, jam_rawat DESC LIMIT 1");
            if ($qH && $qH->num_rows > 0) {
                $header = $qH->fetch_assoc();
                $header['is_previous'] = true;
            }
        }

        // Ambil detail gigi terkini (kumulatif atau per no_rawat / no_rkm_medis)
        $teeth = [];
        $teeth_sql = !empty($no_rawat) && $header && empty($header['is_previous'])
            ? "SELECT * FROM mlite_odontogram_detail WHERE no_rawat = '$no_rawat'"
            : "SELECT * FROM mlite_odontogram_detail WHERE no_rkm_medis = '$no_rkm_medis' ORDER BY created_at ASC";
        
        $qT = $conn->query($teeth_sql);
        if ($qT) {
            while ($row = $qT->fetch_assoc()) {
                // Key format: no_gigi_posisi
                $k = $row['no_gigi'] . '_' . $row['posisi'];
                $teeth[$k] = $row;
            }
        }

        echo json_encode([
            'success' => true,
            'header'  => $header,
            'teeth'   => $teeth
        ]);
        break;

    case 'simpan_odontogram':
        $no_rawat            = $conn->real_escape_string(sanitize($_POST['no_rawat'] ?? ''));
        $no_rkm_medis        = $conn->real_escape_string(sanitize($_POST['no_rkm_medis'] ?? ''));
        $nip                 = $conn->real_escape_string(sanitize($_POST['nip'] ?? ($_SESSION['username'] ?? '-')));
        $oklusi              = $conn->real_escape_string(sanitize($_POST['oklusi'] ?? 'Normal'));
        $torus_palatinus     = $conn->real_escape_string(sanitize($_POST['torus_palatinus'] ?? 'Tidak Ada'));
        $torus_mandibularis  = $conn->real_escape_string(sanitize($_POST['torus_mandibularis'] ?? 'Tidak Ada'));
        $palatum             = $conn->real_escape_string(sanitize($_POST['palatum'] ?? 'Sedang'));
        $diastema            = $conn->real_escape_string(sanitize($_POST['diastema'] ?? 'Tidak Ada'));
        $gigi_anomali        = $conn->real_escape_string(sanitize($_POST['gigi_anomali'] ?? 'Tidak Ada'));
        $lain_lain           = $conn->real_escape_string(sanitize($_POST['lain_lain'] ?? ''));
        $ohis_debris         = $conn->real_escape_string(sanitize($_POST['ohis_debris'] ?? '0'));
        $ohis_calculus       = $conn->real_escape_string(sanitize($_POST['ohis_calculus'] ?? '0'));
        $ohis_nilai          = $conn->real_escape_string(sanitize($_POST['ohis_nilai'] ?? '0'));
        $ohis_kriteria       = $conn->real_escape_string(sanitize($_POST['ohis_kriteria'] ?? 'Baik'));
        $d_val               = (int)($_POST['d_val'] ?? 0);
        $m_val               = (int)($_POST['m_val'] ?? 0);
        $f_val               = (int)($_POST['f_val'] ?? 0);
        $dmft_val            = (int)($_POST['dmft_val'] ?? 0);
        $tgl_perawatan       = date('Y-m-d');
        $jam_rawat           = date('H:i:s');

        if (empty($no_rawat) || empty($no_rkm_medis)) {
            echo json_encode(['success' => false, 'message' => 'Nomor rawat & RM wajib ada']);
            exit;
        }

        // Cek apakah header sudah ada
        $checkH = $conn->query("SELECT id FROM mlite_odontogram WHERE no_rawat = '$no_rawat' LIMIT 1");
        $id_odontogram = 0;
        if ($checkH && $checkH->num_rows > 0) {
            $id_odontogram = (int)$checkH->fetch_assoc()['id'];
            $conn->query("
                UPDATE mlite_odontogram SET
                    oklusi = '$oklusi',
                    torus_palatinus = '$torus_palatinus',
                    torus_mandibularis = '$torus_mandibularis',
                    palatum = '$palatum',
                    diastema = '$diastema',
                    gigi_anomali = '$gigi_anomali',
                    lain_lain = '$lain_lain',
                    ohis_debris = '$ohis_debris',
                    ohis_calculus = '$ohis_calculus',
                    ohis_nilai = '$ohis_nilai',
                    ohis_kriteria = '$ohis_kriteria',
                    d_val = $d_val,
                    m_val = $m_val,
                    f_val = $f_val,
                    dmft_val = $dmft_val,
                    nip = '$nip'
                WHERE id = $id_odontogram
            ");
        } else {
            $conn->query("
                INSERT INTO mlite_odontogram (
                    no_rawat, no_rkm_medis, tgl_perawatan, jam_rawat,
                    oklusi, torus_palatinus, torus_mandibularis, palatum, diastema, gigi_anomali,
                    lain_lain, ohis_debris, ohis_calculus, ohis_nilai, ohis_kriteria,
                    d_val, m_val, f_val, dmft_val, nip
                ) VALUES (
                    '$no_rawat', '$no_rkm_medis', '$tgl_perawatan', '$jam_rawat',
                    '$oklusi', '$torus_palatinus', '$torus_mandibularis', '$palatum', '$diastema', '$gigi_anomali',
                    '$lain_lain', '$ohis_debris', '$ohis_calculus', '$ohis_nilai', '$ohis_kriteria',
                    $d_val, $m_val, $f_val, $dmft_val, '$nip'
                )
            ");
            $id_odontogram = (int)$conn->insert_id;
        }

        // Simpan / update teeth array jika dikirim
        $teeth_raw = $_POST['teeth_data'] ?? '';
        if (!empty($teeth_raw)) {
            $teeth_list = is_array($teeth_raw) ? $teeth_raw : json_decode($teeth_raw, true);
            if (is_array($teeth_list)) {
                // Bersihkan data kunjungan ini dahulu
                $conn->query("DELETE FROM mlite_odontogram_detail WHERE no_rawat = '$no_rawat'");
                foreach ($teeth_list as $t) {
                    $no_gigi   = $conn->real_escape_string(sanitize($t['no_gigi'] ?? ''));
                    $posisi    = $conn->real_escape_string(sanitize($t['posisi'] ?? 'ALL'));
                    $kondisi   = $conn->real_escape_string(sanitize($t['kondisi'] ?? 'Sou'));
                    $ket       = $conn->real_escape_string(sanitize($t['keterangan'] ?? ''));
                    if (!empty($no_gigi)) {
                        $conn->query("
                            INSERT INTO mlite_odontogram_detail (
                                id_odontogram, no_rawat, no_rkm_medis, no_gigi, posisi, kondisi, keterangan
                            ) VALUES (
                                $id_odontogram, '$no_rawat', '$no_rkm_medis', '$no_gigi', '$posisi', '$kondisi', '$ket'
                            )
                        ");
                    }
                }
            }
        }

        echo json_encode(['success' => true, 'message' => 'Data odontogram berhasil disimpan', 'id' => $id_odontogram]);
        break;

    case 'simpan_gigi_single':
        $no_rawat     = $conn->real_escape_string(sanitize($_POST['no_rawat'] ?? ''));
        $no_rkm_medis = $conn->real_escape_string(sanitize($_POST['no_rkm_medis'] ?? ''));
        $no_gigi      = $conn->real_escape_string(sanitize($_POST['no_gigi'] ?? ''));
        $posisi       = $conn->real_escape_string(sanitize($_POST['posisi'] ?? 'ALL'));
        $kondisi      = $conn->real_escape_string(sanitize($_POST['kondisi'] ?? 'Sou'));
        $keterangan   = $conn->real_escape_string(sanitize($_POST['keterangan'] ?? ''));

        if (empty($no_rawat) || empty($no_gigi)) {
            echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
            exit;
        }

        // Hapus kondisi permukaan spesifik pada kunjungan ini
        $conn->query("DELETE FROM mlite_odontogram_detail WHERE no_rawat = '$no_rawat' AND no_gigi = '$no_gigi' AND posisi = '$posisi'");
        
        if ($kondisi !== 'Sou') {
            $conn->query("
                INSERT INTO mlite_odontogram_detail (
                    id_odontogram, no_rawat, no_rkm_medis, no_gigi, posisi, kondisi, keterangan
                ) VALUES (
                    0, '$no_rawat', '$no_rkm_medis', '$no_gigi', '$posisi', '$kondisi', '$keterangan'
                )
            ");
        }

        echo json_encode(['success' => true, 'message' => 'Status gigi diperbarui']);
        break;

    case 'get_odontogram_riwayat':
        $no_rkm_medis = $conn->real_escape_string(sanitize($_GET['no_rkm_medis'] ?? ''));
        $q = $conn->query("
            SELECT o.*, d.nm_dokter
            FROM mlite_odontogram o
            LEFT JOIN reg_periksa r ON o.no_rawat = r.no_rawat
            LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
            WHERE o.no_rkm_medis = '$no_rkm_medis'
            ORDER BY o.tgl_perawatan DESC, o.jam_rawat DESC
        ");
        $list = [];
        if ($q) {
            while ($row = $q->fetch_assoc()) $list[] = $row;
        }
        echo json_encode(['success' => true, 'data' => $list]);
        break;

    // ═════════════════════════════════════════════════════════════
    // ─── MODUL KIA (ANC KEBIDANAN, KMS & IMUNISASI ANAK, KB) ─────
    // ═════════════════════════════════════════════════════════════

    // ── ANC (Antenatal Care Ibu Hamil) ──
    case 'get_kia_anc':
        $no_rawat     = $conn->real_escape_string(sanitize($_GET['no_rawat'] ?? ''));
        $no_rkm_medis = $conn->real_escape_string(sanitize($_GET['no_rkm_medis'] ?? ''));

        $anc = null;
        if (!empty($no_rawat)) {
            $q = $conn->query("SELECT * FROM mlite_kia_anc WHERE no_rawat = '$no_rawat' LIMIT 1");
            if ($q && $q->num_rows > 0) $anc = $q->fetch_assoc();
        }

        // Riwayat ANC sebelumnya untuk ibu ini
        $history = [];
        if (!empty($no_rkm_medis)) {
            $qH = $conn->query("
                SELECT ka.*, d.nm_dokter
                FROM mlite_kia_anc ka
                LEFT JOIN reg_periksa r ON ka.no_rawat = r.no_rawat
                LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
                WHERE ka.no_rkm_medis = '$no_rkm_medis'
                ORDER BY ka.tgl_perawatan DESC, ka.jam_rawat DESC
            ");
            if ($qH) {
                while ($h = $qH->fetch_assoc()) $history[] = $h;
            }
        }

        echo json_encode(['success' => true, 'data' => $anc, 'history' => $history]);
        break;

    case 'simpan_kia_anc':
        $no_rawat          = $conn->real_escape_string(sanitize($_POST['no_rawat'] ?? ''));
        $no_rkm_medis      = $conn->real_escape_string(sanitize($_POST['no_rkm_medis'] ?? ''));
        $nip               = $conn->real_escape_string(sanitize($_POST['nip'] ?? ($_SESSION['username'] ?? '-')));
        $g_hamil           = (int)($_POST['g_hamil'] ?? 1);
        $p_partus          = (int)($_POST['p_partus'] ?? 0);
        $a_abortus         = (int)($_POST['a_abortus'] ?? 0);
        $h_hidup           = (int)($_POST['h_hidup'] ?? 0);
        $hpht              = !empty($_POST['hpht']) ? "'" . $conn->real_escape_string(sanitize($_POST['hpht'])) . "'" : "NULL";
        $hpl               = !empty($_POST['hpl']) ? "'" . $conn->real_escape_string(sanitize($_POST['hpl'])) . "'" : "NULL";
        $usia_kehamilan    = $conn->real_escape_string(sanitize($_POST['usia_kehamilan'] ?? ''));
        $tfu               = $conn->real_escape_string(sanitize($_POST['tfu'] ?? ''));
        $djj               = $conn->real_escape_string(sanitize($_POST['djj'] ?? ''));
        $letak_janin       = $conn->real_escape_string(sanitize($_POST['letak_janin'] ?? 'Kepala'));
        $leopold_1         = $conn->real_escape_string(sanitize($_POST['leopold_1'] ?? ''));
        $leopold_2         = $conn->real_escape_string(sanitize($_POST['leopold_2'] ?? ''));
        $leopold_3         = $conn->real_escape_string(sanitize($_POST['leopold_3'] ?? ''));
        $leopold_4         = $conn->real_escape_string(sanitize($_POST['leopold_4'] ?? ''));
        $edema             = $conn->real_escape_string(sanitize($_POST['edema'] ?? 'Tidak'));
        $refl_patella      = $conn->real_escape_string(sanitize($_POST['refl_patella'] ?? '+'));
        $skor_kspr         = (int)($_POST['skor_kspr'] ?? 2);
        $resiko_kehamilan  = $conn->real_escape_string(sanitize($_POST['resiko_kehamilan'] ?? 'KRR (Rendah)'));
        $tindakan_kia      = $conn->real_escape_string(sanitize($_POST['tindakan_kia'] ?? ''));
        $saran_nasehat     = $conn->real_escape_string(sanitize($_POST['saran_nasehat'] ?? ''));
        $tgl_perawatan     = date('Y-m-d');
        $jam_rawat         = date('H:i:s');

        if (empty($no_rawat) || empty($no_rkm_medis)) {
            echo json_encode(['success' => false, 'message' => 'No rawat dan RM wajib ada']);
            exit;
        }

        $check = $conn->query("SELECT id FROM mlite_kia_anc WHERE no_rawat = '$no_rawat' LIMIT 1");
        if ($check && $check->num_rows > 0) {
            $id = (int)$check->fetch_assoc()['id'];
            $conn->query("
                UPDATE mlite_kia_anc SET
                    g_hamil = $g_hamil, p_partus = $p_partus, a_abortus = $a_abortus, h_hidup = $h_hidup,
                    hpht = $hpht, hpl = $hpl, usia_kehamilan = '$usia_kehamilan',
                    tfu = '$tfu', djj = '$djj', letak_janin = '$letak_janin',
                    leopold_1 = '$leopold_1', leopold_2 = '$leopold_2', leopold_3 = '$leopold_3', leopold_4 = '$leopold_4',
                    edema = '$edema', refl_patella = '$refl_patella', skor_kspr = $skor_kspr, resiko_kehamilan = '$resiko_kehamilan',
                    tindakan_kia = '$tindakan_kia', saran_nasehat = '$saran_nasehat', nip = '$nip'
                WHERE id = $id
            ");
        } else {
            $conn->query("
                INSERT INTO mlite_kia_anc (
                    no_rawat, no_rkm_medis, tgl_perawatan, jam_rawat,
                    g_hamil, p_partus, a_abortus, h_hidup,
                    hpht, hpl, usia_kehamilan, tfu, djj, letak_janin,
                    leopold_1, leopold_2, leopold_3, leopold_4,
                    edema, refl_patella, skor_kspr, resiko_kehamilan,
                    tindakan_kia, saran_nasehat, nip
                ) VALUES (
                    '$no_rawat', '$no_rkm_medis', '$tgl_perawatan', '$jam_rawat',
                    $g_hamil, $p_partus, $a_abortus, $h_hidup,
                    $hpht, $hpl, '$usia_kehamilan', '$tfu', '$djj', '$letak_janin',
                    '$leopold_1', '$leopold_2', '$leopold_3', '$leopold_4',
                    '$edema', '$refl_patella', $skor_kspr, '$resiko_kehamilan',
                    '$tindakan_kia', '$saran_nasehat', '$nip'
                )
            ");
        }

        echo json_encode(['success' => true, 'message' => 'Pemeriksaan ANC Kehamilan berhasil disimpan']);
        break;

    // ── KMS & Tumbuh Kembang Anak ──
    case 'get_kia_kms':
        $no_rawat     = $conn->real_escape_string(sanitize($_GET['no_rawat'] ?? ''));
        $no_rkm_medis = $conn->real_escape_string(sanitize($_GET['no_rkm_medis'] ?? ''));

        $kms = null;
        if (!empty($no_rawat)) {
            $q = $conn->query("SELECT * FROM mlite_kia_kms WHERE no_rawat = '$no_rawat' LIMIT 1");
            if ($q && $q->num_rows > 0) $kms = $q->fetch_assoc();
        }

        // Riwayat grafik tumbuh kembang anak
        $chart_data = [];
        if (!empty($no_rkm_medis)) {
            $qC = $conn->query("
                SELECT tgl_perawatan, umur_bln, bb, tb, lk, lila, status_gizi_bb_u
                FROM mlite_kia_kms
                WHERE no_rkm_medis = '$no_rkm_medis'
                ORDER BY tgl_perawatan ASC
            ");
            if ($qC) {
                while ($c = $qC->fetch_assoc()) $chart_data[] = $c;
            }
        }

        echo json_encode(['success' => true, 'data' => $kms, 'chart_data' => $chart_data]);
        break;

    case 'simpan_kia_kms':
        $no_rawat              = $conn->real_escape_string(sanitize($_POST['no_rawat'] ?? ''));
        $no_rkm_medis          = $conn->real_escape_string(sanitize($_POST['no_rkm_medis'] ?? ''));
        $nip                   = $conn->real_escape_string(sanitize($_POST['nip'] ?? ($_SESSION['username'] ?? '-')));
        $umur_bln              = (int)($_POST['umur_bln'] ?? 0);
        $bb                    = $conn->real_escape_string(sanitize($_POST['bb'] ?? ''));
        $tb                    = $conn->real_escape_string(sanitize($_POST['tb'] ?? ''));
        $lk                    = $conn->real_escape_string(sanitize($_POST['lk'] ?? ''));
        $lila                  = $conn->real_escape_string(sanitize($_POST['lila'] ?? ''));
        $status_gizi_bb_u      = $conn->real_escape_string(sanitize($_POST['status_gizi_bb_u'] ?? ''));
        $status_gizi_tb_u      = $conn->real_escape_string(sanitize($_POST['status_gizi_tb_u'] ?? ''));
        $status_gizi_bb_tb     = $conn->real_escape_string(sanitize($_POST['status_gizi_bb_tb'] ?? ''));
        $asi_eksklusif         = $conn->real_escape_string(sanitize($_POST['asi_eksklusif'] ?? 'Ya'));
        $vit_a                 = $conn->real_escape_string(sanitize($_POST['vit_a'] ?? 'Tidak'));
        $obat_cacing           = $conn->real_escape_string(sanitize($_POST['obat_cacing'] ?? 'Tidak'));
        $perkembangan_motorik  = $conn->real_escape_string(sanitize($_POST['perkembangan_motorik'] ?? ''));
        $catatan               = $conn->real_escape_string(sanitize($_POST['catatan'] ?? ''));
        $tgl_perawatan         = date('Y-m-d');
        $jam_rawat             = date('H:i:s');

        if (empty($no_rawat) || empty($no_rkm_medis)) {
            echo json_encode(['success' => false, 'message' => 'No rawat dan RM wajib ada']);
            exit;
        }

        $check = $conn->query("SELECT id FROM mlite_kia_kms WHERE no_rawat = '$no_rawat' LIMIT 1");
        if ($check && $check->num_rows > 0) {
            $id = (int)$check->fetch_assoc()['id'];
            $conn->query("
                UPDATE mlite_kia_kms SET
                    umur_bln = $umur_bln, bb = '$bb', tb = '$tb', lk = '$lk', lila = '$lila',
                    status_gizi_bb_u = '$status_gizi_bb_u', status_gizi_tb_u = '$status_gizi_tb_u', status_gizi_bb_tb = '$status_gizi_bb_tb',
                    asi_eksklusif = '$asi_eksklusif', vit_a = '$vit_a', obat_cacing = '$obat_cacing',
                    perkembangan_motorik = '$perkembangan_motorik', catatan = '$catatan', nip = '$nip'
                WHERE id = $id
            ");
        } else {
            $conn->query("
                INSERT INTO mlite_kia_kms (
                    no_rawat, no_rkm_medis, tgl_perawatan, jam_rawat,
                    umur_bln, bb, tb, lk, lila,
                    status_gizi_bb_u, status_gizi_tb_u, status_gizi_bb_tb,
                    asi_eksklusif, vit_a, obat_cacing, perkembangan_motorik, catatan, nip
                ) VALUES (
                    '$no_rawat', '$no_rkm_medis', '$tgl_perawatan', '$jam_rawat',
                    $umur_bln, '$bb', '$tb', '$lk', '$lila',
                    '$status_gizi_bb_u', '$status_gizi_tb_u', '$status_gizi_bb_tb',
                    '$asi_eksklusif', '$vit_a', '$obat_cacing', '$perkembangan_motorik', '$catatan', '$nip'
                )
            ");
        }

        echo json_encode(['success' => true, 'message' => 'Data KMS & Antropometri berhasil disimpan']);
        break;

    // ── Imunisasi Anak ──
    case 'get_kia_imunisasi':
        $no_rkm_medis = $conn->real_escape_string(sanitize($_GET['no_rkm_medis'] ?? ''));
        $q = $conn->query("
            SELECT ki.*, COALESCE(pg.nama, pt.nama, d.nm_dokter, ki.nip) as nama_petugas
            FROM mlite_kia_imunisasi ki
            LEFT JOIN pegawai pg ON ki.nip = pg.nik
            LEFT JOIN petugas pt ON ki.nip = pt.nip
            LEFT JOIN dokter d ON ki.nip = d.kd_dokter
            WHERE ki.no_rkm_medis = '$no_rkm_medis'
            ORDER BY ki.tgl_imunisasi ASC, ki.id ASC
        ");
        $data = [];
        if ($q) {
            while ($r = $q->fetch_assoc()) $data[] = $r;
        }
        echo json_encode(['success' => true, 'data' => $data]);
        break;

    case 'simpan_kia_imunisasi':
        $no_rawat        = $conn->real_escape_string(sanitize($_POST['no_rawat'] ?? ''));
        $no_rkm_medis    = $conn->real_escape_string(sanitize($_POST['no_rkm_medis'] ?? ''));
        $nip             = $conn->real_escape_string(sanitize($_POST['nip'] ?? ($_SESSION['username'] ?? '-')));
        $tgl_imunisasi   = $conn->real_escape_string(sanitize($_POST['tgl_imunisasi'] ?? date('Y-m-d')));
        $jenis_imunisasi = $conn->real_escape_string(sanitize($_POST['jenis_imunisasi'] ?? ''));
        $no_batch        = $conn->real_escape_string(sanitize($_POST['no_batch'] ?? ''));
        $keterangan      = $conn->real_escape_string(sanitize($_POST['keterangan'] ?? ''));

        if (empty($no_rkm_medis) || empty($jenis_imunisasi)) {
            echo json_encode(['success' => false, 'message' => 'Jenis imunisasi wajib dipilih']);
            exit;
        }

        $conn->query("
            INSERT INTO mlite_kia_imunisasi (
                no_rawat, no_rkm_medis, tgl_imunisasi, jenis_imunisasi, no_batch, nip, keterangan
            ) VALUES (
                '$no_rawat', '$no_rkm_medis', '$tgl_imunisasi', '$jenis_imunisasi', '$no_batch', '$nip', '$keterangan'
            )
        ");

        echo json_encode(['success' => true, 'message' => 'Imunisasi berhasil ditambahkan']);
        break;

    case 'hapus_kia_imunisasi':
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $conn->query("DELETE FROM mlite_kia_imunisasi WHERE id = $id");
            echo json_encode(['success' => true, 'message' => 'Data imunisasi berhasil dihapus']);
        } else {
            echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
        }
        break;

    // ── Pelayanan KB ──
    case 'get_kia_kb':
        $no_rkm_medis = $conn->real_escape_string(sanitize($_GET['no_rkm_medis'] ?? ''));
        $q = $conn->query("
            SELECT kb.*, COALESCE(pg.nama, pt.nama, d.nm_dokter, kb.nip) as nama_petugas
            FROM mlite_kia_kb kb
            LEFT JOIN pegawai pg ON kb.nip = pg.nik
            LEFT JOIN petugas pt ON kb.nip = pt.nip
            LEFT JOIN dokter d ON kb.nip = d.kd_dokter
            WHERE kb.no_rkm_medis = '$no_rkm_medis'
            ORDER BY kb.tgl_pelayanan DESC
        ");
        $data = [];
        if ($q) {
            while ($r = $q->fetch_assoc()) $data[] = $r;
        }
        echo json_encode(['success' => true, 'data' => $data]);
        break;

    case 'simpan_kia_kb':
        $no_rawat          = $conn->real_escape_string(sanitize($_POST['no_rawat'] ?? ''));
        $no_rkm_medis      = $conn->real_escape_string(sanitize($_POST['no_rkm_medis'] ?? ''));
        $nip               = $conn->real_escape_string(sanitize($_POST['nip'] ?? ($_SESSION['username'] ?? '-')));
        $tgl_pelayanan     = $conn->real_escape_string(sanitize($_POST['tgl_pelayanan'] ?? date('Y-m-d')));
        $jenis_kontrasepsi = $conn->real_escape_string(sanitize($_POST['jenis_kontrasepsi'] ?? ''));
        $tgl_kembali       = !empty($_POST['tgl_kembali']) ? "'" . $conn->real_escape_string(sanitize($_POST['tgl_kembali'])) . "'" : "NULL";
        $keluhan           = $conn->real_escape_string(sanitize($_POST['keluhan'] ?? ''));
        $efek_samping      = $conn->real_escape_string(sanitize($_POST['efek_samping'] ?? ''));

        if (empty($no_rkm_medis) || empty($jenis_kontrasepsi)) {
            echo json_encode(['success' => false, 'message' => 'Jenis kontrasepsi wajib diisi']);
            exit;
        }

        $conn->query("
            INSERT INTO mlite_kia_kb (
                no_rawat, no_rkm_medis, tgl_pelayanan, jenis_kontrasepsi, tgl_kembali, keluhan, efek_samping, nip
            ) VALUES (
                '$no_rawat', '$no_rkm_medis', '$tgl_pelayanan', '$jenis_kontrasepsi', $tgl_kembali, '$keluhan', '$efek_samping', '$nip'
            )
        ");

        echo json_encode(['success' => true, 'message' => 'Pelayanan KB berhasil dicatat']);
        break;

    // ─── General Consent (Persetujuan Umum) ────────────────────
    case 'get_general_consent':
        $no_rawat = $conn->real_escape_string(sanitize($_GET['no_rawat'] ?? ''));
        if (empty($no_rawat)) {
            echo json_encode(['success' => false, 'message' => 'No. Rawat wajib diisi']);
            exit;
        }

        // Ambil data pasien & registrasi
        $res_pasien = $conn->query("
            SELECT r.no_rawat, r.no_rkm_medis, r.tgl_registrasi, r.jam_reg, r.kd_pj,
                   p.nm_pasien, p.jk, p.tgl_lahir, p.no_ktp, p.no_tlp, p.alamat,
                   p.namakeluarga, p.alamatpj, p.keluarga,
                   d.nm_dokter, pol.nm_poli, pj.png_jawab as nm_penjab
            FROM reg_periksa r
            JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
            LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
            LEFT JOIN poliklinik pol ON r.kd_poli = pol.kd_poli
            LEFT JOIN penjab pj ON r.kd_pj = pj.kd_pj
            WHERE r.no_rawat = '$no_rawat'
            LIMIT 1
        ");

        if (!$res_pasien || $res_pasien->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Data kunjungan tidak ditemukan']);
            exit;
        }
        $pasien_data = $res_pasien->fetch_assoc();
        $pasien_data['umur'] = hitung_umur($pasien_data['tgl_lahir']);

        // Ambil data persetujuan jika sudah ada
        $res_gc = $conn->query("SELECT * FROM surat_persetujuan_umum WHERE no_rawat = '$no_rawat' LIMIT 1");
        $gc_data = ($res_gc && $res_gc->num_rows > 0) ? $res_gc->fetch_assoc() : null;

        echo json_encode([
            'success' => true,
            'pasien'  => $pasien_data,
            'consent' => $gc_data,
            'petugas_default' => [
                'nama' => $_SESSION['nama'] ?? $_SESSION['username'] ?? 'Petugas Admisi',
                'nip'  => $_SESSION['user_id'] ?? $_SESSION['username'] ?? '-'
            ]
        ]);
        break;

    case 'simpan_general_consent':
        $no_rawat = $conn->real_escape_string(sanitize($_POST['no_rawat'] ?? ''));
        if (empty($no_rawat)) {
            echo json_encode(['success' => false, 'message' => 'No. Rawat wajib diisi']);
            exit;
        }

        $no_surat               = $conn->real_escape_string(sanitize($_POST['no_surat'] ?? ''));
        $tgl_persetujuan        = $conn->real_escape_string(sanitize($_POST['tgl_persetujuan'] ?? date('Y-m-d')));
        $jam_persetujuan        = $conn->real_escape_string(sanitize($_POST['jam_persetujuan'] ?? date('H:i:s')));
        $nama_pj                = $conn->real_escape_string(sanitize($_POST['nama_pj'] ?? ''));
        $hubungan_pj            = $conn->real_escape_string(sanitize($_POST['hubungan_pj'] ?? 'Diri Sendiri'));
        $jk_pj                  = $conn->real_escape_string(sanitize($_POST['jk_pj'] ?? 'L'));
        $tgl_lahir_pj           = !empty($_POST['tgl_lahir_pj']) ? "'" . $conn->real_escape_string(sanitize($_POST['tgl_lahir_pj'])) . "'" : "NULL";
        $umur_pj                = $conn->real_escape_string(sanitize($_POST['umur_pj'] ?? ''));
        $alamat_pj              = $conn->real_escape_string(sanitize($_POST['alamat_pj'] ?? ''));
        $no_ktp_pj              = $conn->real_escape_string(sanitize($_POST['no_ktp_pj'] ?? ''));
        $no_telp_pj             = $conn->real_escape_string(sanitize($_POST['no_telp_pj'] ?? ''));
        
        $setuju_rawat_inap_jalan   = $conn->real_escape_string(sanitize($_POST['setuju_rawat_inap_jalan'] ?? 'Setuju'));
        $setuju_pelepasan_informasi= $conn->real_escape_string(sanitize($_POST['setuju_pelepasan_informasi'] ?? 'Setuju'));
        $nama_keluarga_informasi   = $conn->real_escape_string(sanitize($_POST['nama_keluarga_informasi'] ?? ''));
        $setuju_hak_kewajiban      = $conn->real_escape_string(sanitize($_POST['setuju_hak_kewajiban'] ?? 'Setuju'));
        $setuju_privasi_khusus     = $conn->real_escape_string(sanitize($_POST['setuju_privasi_khusus'] ?? 'Tidak Ada'));
        $detail_privasi_khusus     = $conn->real_escape_string(sanitize($_POST['detail_privasi_khusus'] ?? ''));
        $setuju_barang_pribadi     = $conn->real_escape_string(sanitize($_POST['setuju_barang_pribadi'] ?? 'Setuju'));
        $setuju_pembayaran         = $conn->real_escape_string(sanitize($_POST['setuju_pembayaran'] ?? 'Setuju'));
        $tipe_penjamin             = $conn->real_escape_string(sanitize($_POST['tipe_penjamin'] ?? 'Umum'));
        $keterangan_lain           = $conn->real_escape_string(sanitize($_POST['keterangan_lain'] ?? ''));
        $nip_petugas               = $conn->real_escape_string(sanitize($_POST['nip_petugas'] ?? ($_SESSION['user_id'] ?? '-')));
        $nama_petugas              = $conn->real_escape_string(sanitize($_POST['nama_petugas'] ?? ($_SESSION['nama'] ?? 'Petugas Admisi')));

        // Digital Signatures (Base64 data url from canvas)
        $ttd_pasien_raw            = $_POST['ttd_pasien'] ?? '';
        $ttd_petugas_raw           = $_POST['ttd_petugas'] ?? '';
        
        // Sanitize / escape base64 string directly into MySQL
        $ttd_pasien                = $conn->real_escape_string($ttd_pasien_raw);
        $ttd_petugas               = $conn->real_escape_string($ttd_petugas_raw);

        if (empty($nama_pj)) {
            echo json_encode(['success' => false, 'message' => 'Nama pemberi persetujuan (pasien / wali) wajib diisi']);
            exit;
        }

        if (empty($no_surat)) {
            $date_str = date('Ymd', strtotime($tgl_persetujuan));
            $clean_rawat = preg_replace('/[^0-9]/', '', $no_rawat);
            $no_surat = "GC-" . $date_str . "-" . substr($clean_rawat, -4);
        }

        // Cek apakah data sudah ada
        $cek = $conn->query("SELECT no_rawat, ttd_pasien, ttd_petugas FROM surat_persetujuan_umum WHERE no_rawat = '$no_rawat' LIMIT 1");
        if ($cek && $cek->num_rows > 0) {
            $existing = $cek->fetch_assoc();
            // Jika ttd baru kosong, pertahankan ttd lama
            if (empty($ttd_pasien) && !empty($existing['ttd_pasien'])) {
                $ttd_pasien = $conn->real_escape_string($existing['ttd_pasien']);
            }
            if (empty($ttd_petugas) && !empty($existing['ttd_petugas'])) {
                $ttd_petugas = $conn->real_escape_string($existing['ttd_petugas']);
            }

            $sql = "UPDATE surat_persetujuan_umum SET
                        no_surat = '$no_surat',
                        tgl_persetujuan = '$tgl_persetujuan',
                        jam_persetujuan = '$jam_persetujuan',
                        nama_pj = '$nama_pj',
                        hubungan_pj = '$hubungan_pj',
                        jk_pj = '$jk_pj',
                        tgl_lahir_pj = $tgl_lahir_pj,
                        umur_pj = '$umur_pj',
                        alamat_pj = '$alamat_pj',
                        no_ktp_pj = '$no_ktp_pj',
                        no_telp_pj = '$no_telp_pj',
                        setuju_rawat_inap_jalan = '$setuju_rawat_inap_jalan',
                        setuju_pelepasan_informasi = '$setuju_pelepasan_informasi',
                        nama_keluarga_informasi = '$nama_keluarga_informasi',
                        setuju_hak_kewajiban = '$setuju_hak_kewajiban',
                        setuju_privasi_khusus = '$setuju_privasi_khusus',
                        detail_privasi_khusus = '$detail_privasi_khusus',
                        setuju_barang_pribadi = '$setuju_barang_pribadi',
                        setuju_pembayaran = '$setuju_pembayaran',
                        tipe_penjamin = '$tipe_penjamin',
                        keterangan_lain = '$keterangan_lain',
                        ttd_pasien = '$ttd_pasien',
                        ttd_petugas = '$ttd_petugas',
                        nip_petugas = '$nip_petugas',
                        nama_petugas = '$nama_petugas'
                    WHERE no_rawat = '$no_rawat'";
        } else {
            $sql = "INSERT INTO surat_persetujuan_umum (
                        no_rawat, no_surat, tgl_persetujuan, jam_persetujuan,
                        nama_pj, hubungan_pj, jk_pj, tgl_lahir_pj, umur_pj,
                        alamat_pj, no_ktp_pj, no_telp_pj,
                        setuju_rawat_inap_jalan, setuju_pelepasan_informasi, nama_keluarga_informasi,
                        setuju_hak_kewajiban, setuju_privasi_khusus, detail_privasi_khusus,
                        setuju_barang_pribadi, setuju_pembayaran, tipe_penjamin, keterangan_lain,
                        ttd_pasien, ttd_petugas, nip_petugas, nama_petugas
                    ) VALUES (
                        '$no_rawat', '$no_surat', '$tgl_persetujuan', '$jam_persetujuan',
                        '$nama_pj', '$hubungan_pj', '$jk_pj', $tgl_lahir_pj, '$umur_pj',
                        '$alamat_pj', '$no_ktp_pj', '$no_telp_pj',
                        '$setuju_rawat_inap_jalan', '$setuju_pelepasan_informasi', '$nama_keluarga_informasi',
                        '$setuju_hak_kewajiban', '$setuju_privasi_khusus', '$detail_privasi_khusus',
                        '$setuju_barang_pribadi', '$setuju_pembayaran', '$tipe_penjamin', '$keterangan_lain',
                        '$ttd_pasien', '$ttd_petugas', '$nip_petugas', '$nama_petugas'
                    )";
        }

        if ($conn->query($sql)) {
            echo json_encode([
                'success' => true,
                'message' => 'Dokumen General Consent & Tanda Tangan berhasil disimpan.',
                'no_surat' => $no_surat,
                'no_rawat' => $no_rawat
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Gagal menyimpan data: ' . $conn->error
            ]);
        }
        break;

    case 'hapus_general_consent':
        $no_rawat = $conn->real_escape_string(sanitize($_POST['no_rawat'] ?? ''));
        if (empty($no_rawat)) {
            echo json_encode(['success' => false, 'message' => 'No. Rawat wajib diisi']);
            exit;
        }
        $conn->query("DELETE FROM surat_persetujuan_umum WHERE no_rawat = '$no_rawat'");
        echo json_encode(['success' => true, 'message' => 'General consent berhasil dihapus.']);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
}
