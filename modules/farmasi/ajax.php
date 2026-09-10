<?php
/**
 * SIMKlinik — Farmasi: AJAX Endpoint Helper
 */

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

// Pastikan tabel telaah_resep tersedia
$conn->query("
    CREATE TABLE IF NOT EXISTS `telaah_resep` (
      `no_resep` varchar(14) NOT NULL,
      `tgl_telaah` date DEFAULT NULL,
      `jam_telaah` time DEFAULT NULL,
      `petugas` varchar(50) DEFAULT NULL,
      `tepat_identitas` enum('Ya','Tidak') DEFAULT 'Ya',
      `tepat_indikasi` enum('Ya','Tidak') DEFAULT 'Ya',
      `tepat_dosis` enum('Ya','Tidak') DEFAULT 'Ya',
      `tepat_waktu` enum('Ya','Tidak') DEFAULT 'Ya',
      `duplikasi` enum('Ya','Tidak') DEFAULT 'Tidak',
      `alergi` enum('Ya','Tidak') DEFAULT 'Tidak',
      `interaksi` enum('Ya','Tidak') DEFAULT 'Tidak',
      `status_telaah` varchar(20) DEFAULT 'Lolos',
      `catatan_telaah` text DEFAULT NULL,
      PRIMARY KEY (`no_resep`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

header('Content-Type: application/json; charset=utf-8');

$action = sanitize($_GET['action'] ?? $_POST['action'] ?? '');
$user   = current_user();
$petugas = $user['fullname'] ?? 'Petugas Farmasi';

// ─── 1. Get Detail Resep & Items ──────────────────────────────
if ($action === 'get_resep_detail') {
    $no_resep = $conn->real_escape_string($_GET['no_resep'] ?? '');

    $res = $conn->query("
        SELECT ro.*, r.no_rkm_medis, p.nm_pasien, p.jk, p.tgl_lahir,
               d.nm_dokter, pol.nm_poli, pj.png_jawab as nm_penjab
        FROM resep_obat ro
        JOIN reg_periksa r ON ro.no_rawat = r.no_rawat
        JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
        LEFT JOIN dokter d ON ro.kd_dokter = d.kd_dokter
        LEFT JOIN poliklinik pol ON r.kd_poli = pol.kd_poli
        LEFT JOIN penjab pj ON p.kd_pj = pj.kd_pj
        WHERE ro.no_resep = '$no_resep'
    ");

    $header = $res ? $res->fetch_assoc() : null;
    if (!$header) {
        echo json_encode(['status' => 'error', 'message' => 'Data resep tidak ditemukan']);
        exit;
    }

    $items_res = $conn->query("
        SELECT rd.no_resep, rd.kode_brng, rd.jml, rd.aturan_pakai,
               db.nama_brng, db.ralan as harga, db.h_beli, ks.satuan,
               COALESCE((SELECT SUM(stok) FROM gudangbarang WHERE kode_brng = db.kode_brng), 0) as total_stok
        FROM resep_dokter rd
        JOIN databarang db ON rd.kode_brng = db.kode_brng
        LEFT JOIN kodesatuan ks ON db.kode_sat = ks.kode_sat
        WHERE rd.no_resep = '$no_resep'
        ORDER BY db.nama_brng ASC
    ");

    $items = [];
    if ($items_res) while ($row = $items_res->fetch_assoc()) $items[] = $row;

    // Ambil data obat racikan
    $racikan_res = $conn->query("
        SELECT rdr.*, mr.nm_racik
        FROM resep_dokter_racikan rdr
        LEFT JOIN metode_racik mr ON rdr.kd_racik = mr.kd_racik
        WHERE rdr.no_resep = '$no_resep'
        ORDER BY rdr.no_racik ASC
    ");
    $racikan = [];
    if ($racikan_res) {
        while ($rck = $racikan_res->fetch_assoc()) {
            $no_rck = $rck['no_racik'];
            $detail_res = $conn->query("
                SELECT rdrd.*, db.nama_brng, db.ralan as harga, db.h_beli, ks.satuan,
                       COALESCE((SELECT SUM(stok) FROM gudangbarang WHERE kode_brng = db.kode_brng), 0) as total_stok
                FROM resep_dokter_racikan_detail rdrd
                JOIN databarang db ON rdrd.kode_brng = db.kode_brng
                LEFT JOIN kodesatuan ks ON db.kode_sat = ks.kode_sat
                WHERE rdrd.no_resep = '$no_resep' AND rdrd.no_racik = '$no_rck'
                ORDER BY db.nama_brng ASC
            ");
            $details = [];
            if ($detail_res) while ($det = $detail_res->fetch_assoc()) $details[] = $det;
            $rck['detail'] = $details;
            $racikan[] = $rck;
        }
    }

    // Ambil data telaah jika ada
    $telaah_res = $conn->query("SELECT * FROM telaah_resep WHERE no_resep = '$no_resep'");
    $telaah = $telaah_res ? $telaah_res->fetch_assoc() : null;

    echo json_encode([
        'status'  => 'success',
        'header'  => $header,
        'items'   => $items,
        'racikan' => $racikan,
        'telaah'  => $telaah
    ]);
    exit;
}

// ─── 2. Simpan / Update Item Resep Dokter ──────────────────────
if ($action === 'simpan_item_resep') {
    $no_resep     = $conn->real_escape_string($_POST['no_resep'] ?? '');
    $kode_brng    = $conn->real_escape_string($_POST['kode_brng'] ?? '');
    $jml          = (float)($_POST['jml'] ?? 1);
    $aturan_pakai = $conn->real_escape_string($_POST['aturan_pakai'] ?? '');

    if (empty($no_resep) || empty($kode_brng)) {
        echo json_encode(['status' => 'error', 'message' => 'No resep dan kode obat wajib diisi']);
        exit;
    }

    $conn->query("DELETE FROM resep_dokter WHERE no_resep = '$no_resep' AND kode_brng = '$kode_brng'");
    $ins = $conn->query("INSERT INTO resep_dokter (no_resep, kode_brng, jml, aturan_pakai) VALUES ('$no_resep', '$kode_brng', '$jml', '$aturan_pakai')");

    if ($ins) {
        echo json_encode(['status' => 'success', 'message' => 'Obat berhasil disimpan']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }
    exit;
}

// ─── 3. Hapus Item Resep ──────────────────────────────────────
if ($action === 'hapus_item_resep') {
    $no_resep  = $conn->real_escape_string($_POST['no_resep'] ?? '');
    $kode_brng = $conn->real_escape_string($_POST['kode_brng'] ?? '');

    if (empty($no_resep) || empty($kode_brng)) {
        echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap']);
        exit;
    }

    $del = $conn->query("DELETE FROM resep_dokter WHERE no_resep = '$no_resep' AND kode_brng = '$kode_brng'");
    if ($del) {
        echo json_encode(['status' => 'success', 'message' => 'Obat berhasil dihapus dari resep']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }
    exit;
}

// ─── 4. Simpan Telaah Resep ────────────────────────────────────
if ($action === 'simpan_telaah') {
    $no_resep        = $conn->real_escape_string($_POST['no_resep'] ?? '');
    $tepat_identitas = $conn->real_escape_string($_POST['tepat_identitas'] ?? 'Ya');
    $tepat_indikasi  = $conn->real_escape_string($_POST['tepat_indikasi'] ?? 'Ya');
    $tepat_dosis     = $conn->real_escape_string($_POST['tepat_dosis'] ?? 'Ya');
    $tepat_waktu     = $conn->real_escape_string($_POST['tepat_waktu'] ?? 'Ya');
    $duplikasi       = $conn->real_escape_string($_POST['duplikasi'] ?? 'Tidak');
    $alergi          = $conn->real_escape_string($_POST['alergi'] ?? 'Tidak');
    $interaksi       = $conn->real_escape_string($_POST['interaksi'] ?? 'Tidak');
    $status_telaah   = $conn->real_escape_string($_POST['status_telaah'] ?? 'Lolos');
    $catatan_telaah  = $conn->real_escape_string($_POST['catatan_telaah'] ?? '');
    $tgl_telaah      = date('Y-m-d');
    $jam_telaah      = date('H:i:s');

    if (empty($no_resep)) {
        echo json_encode(['status' => 'error', 'message' => 'Nomor resep wajib ada']);
        exit;
    }

    $sql = "
        INSERT INTO telaah_resep (
            no_resep, tgl_telaah, jam_telaah, petugas, tepat_identitas, tepat_indikasi, tepat_dosis, tepat_waktu,
            duplikasi, alergi, interaksi, status_telaah, catatan_telaah
        ) VALUES (
            '$no_resep', '$tgl_telaah', '$jam_telaah', '$petugas', '$tepat_identitas', '$tepat_indikasi', '$tepat_dosis', '$tepat_waktu',
            '$duplikasi', '$alergi', '$interaksi', '$status_telaah', '$catatan_telaah'
        ) ON DUPLICATE KEY UPDATE
            tgl_telaah = '$tgl_telaah',
            jam_telaah = '$jam_telaah',
            petugas = '$petugas',
            tepat_identitas = '$tepat_identitas',
            tepat_indikasi = '$tepat_indikasi',
            tepat_dosis = '$tepat_dosis',
            tepat_waktu = '$tepat_waktu',
            duplikasi = '$duplikasi',
            alergi = '$alergi',
            interaksi = '$interaksi',
            status_telaah = '$status_telaah',
            catatan_telaah = '$catatan_telaah'
    ";

    if ($conn->query($sql)) {
        // Auto-trigger BPJS Antrean Task 6 (Mulai Pelayanan / Racik Farmasi)
        require_once dirname(__DIR__, 2) . '/includes/bpjs_antrean.php';
        $r_chk = $conn->query("SELECT no_rawat FROM resep_obat WHERE no_resep = '$no_resep' LIMIT 1");
        if ($r_chk && $r_chk->num_rows > 0) {
            $no_rawat_farm = $r_chk->fetch_assoc()['no_rawat'];
            BpjsAntreanService::triggerTaskByRawat($no_rawat_farm, 6);
        }

        echo json_encode(['status' => 'success', 'message' => 'Hasil telaah resep berhasil disimpan']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }
    exit;
}

// ─── 5b. Update Racikan dari Farmasi (jml_dr, aturan_pakai, keterangan) ───
if ($action === 'simpan_racikan_farmasi') {
    $no_resep    = $conn->real_escape_string($_POST['no_resep'] ?? '');
    $no_racik    = $conn->real_escape_string($_POST['no_racik'] ?? '');
    $jml_dr      = (int)($_POST['jml_dr'] ?? 1);
    $aturan      = $conn->real_escape_string($_POST['aturan_pakai'] ?? '');
    $keterangan  = $conn->real_escape_string($_POST['keterangan'] ?? '');

    if (empty($no_resep) || empty($no_racik)) {
        echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap']);
        exit;
    }

    $upd = $conn->query("
        UPDATE resep_dokter_racikan
        SET jml_dr = '$jml_dr', aturan_pakai = '$aturan', keterangan = '$keterangan'
        WHERE no_resep = '$no_resep' AND no_racik = '$no_racik'
    ");

    if ($upd) {
        echo json_encode(['status' => 'success', 'message' => 'Racikan berhasil diperbarui']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }
    exit;
}

// ─── 5c. Hapus Racikan dari Farmasi ────────────────────────────
if ($action === 'hapus_racikan_farmasi') {
    $no_resep = $conn->real_escape_string($_POST['no_resep'] ?? '');
    $no_racik = $conn->real_escape_string($_POST['no_racik'] ?? '');

    if (empty($no_resep) || empty($no_racik)) {
        echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap']);
        exit;
    }

    $conn->query("DELETE FROM resep_dokter_racikan_detail WHERE no_resep = '$no_resep' AND no_racik = '$no_racik'");
    $conn->query("DELETE FROM resep_dokter_racikan WHERE no_resep = '$no_resep' AND no_racik = '$no_racik'");

    echo json_encode(['status' => 'success', 'message' => 'Racikan berhasil dihapus']);
    exit;
}

// ─── 5d. Update 1 Bahan Racikan (jml, p1, p2, kandungan) ──────
if ($action === 'update_bahan_racikan') {
    $no_resep  = $conn->real_escape_string($_POST['no_resep']  ?? '');
    $no_racik  = $conn->real_escape_string($_POST['no_racik']  ?? '');
    $kode_lama = $conn->real_escape_string($_POST['kode_lama'] ?? ''); // kode obat asal
    $kode_brng = $conn->real_escape_string($_POST['kode_brng'] ?? ''); // kode obat baru (boleh sama)
    $jml       = (float)($_POST['jml'] ?? 0);
    $p1        = (float)($_POST['p1']  ?? 0);
    $p2        = (float)($_POST['p2']  ?? 0);
    $kandungan = $conn->real_escape_string($_POST['kandungan'] ?? '');

    if (empty($no_resep) || empty($no_racik) || empty($kode_lama)) {
        echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap']);
        exit;
    }
    if (empty($kode_brng)) $kode_brng = $kode_lama;

    // Hapus baris lama, insert baris baru (menangani ganti obat sekaligus)
    $conn->query("DELETE FROM resep_dokter_racikan_detail WHERE no_resep='$no_resep' AND no_racik='$no_racik' AND kode_brng='$kode_lama'");
    $ins = $conn->query("INSERT INTO resep_dokter_racikan_detail (no_resep, no_racik, kode_brng, p1, p2, kandungan, jml)
                         VALUES ('$no_resep','$no_racik','$kode_brng','$p1','$p2','$kandungan','$jml')");

    if ($ins) {
        echo json_encode(['status' => 'success', 'message' => 'Bahan berhasil diperbarui']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }
    exit;
}

// ─── 5e. Hapus 1 Bahan Racikan ─────────────────────────────────
if ($action === 'hapus_bahan_racikan') {
    $no_resep  = $conn->real_escape_string($_POST['no_resep']  ?? '');
    $no_racik  = $conn->real_escape_string($_POST['no_racik']  ?? '');
    $kode_brng = $conn->real_escape_string($_POST['kode_brng'] ?? '');

    if (empty($no_resep) || empty($no_racik) || empty($kode_brng)) {
        echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap']);
        exit;
    }

    $del = $conn->query("DELETE FROM resep_dokter_racikan_detail WHERE no_resep='$no_resep' AND no_racik='$no_racik' AND kode_brng='$kode_brng'");
    if ($del) {
        echo json_encode(['status' => 'success', 'message' => 'Bahan berhasil dihapus']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }
    exit;
}

// ─── 5f. Tambah Bahan Baru ke Racikan ──────────────────────────
if ($action === 'tambah_bahan_racikan') {
    $no_resep  = $conn->real_escape_string($_POST['no_resep']  ?? '');
    $no_racik  = $conn->real_escape_string($_POST['no_racik']  ?? '');
    $kode_brng = $conn->real_escape_string($_POST['kode_brng'] ?? '');
    $jml       = (float)($_POST['jml'] ?? 0);
    $p1        = (float)($_POST['p1']  ?? 0);
    $p2        = (float)($_POST['p2']  ?? 0);
    $kandungan = $conn->real_escape_string($_POST['kandungan'] ?? '');

    if (empty($no_resep) || empty($no_racik) || empty($kode_brng)) {
        echo json_encode(['status' => 'error', 'message' => 'Pilih obat terlebih dahulu']);
        exit;
    }

    // Cek jika sudah ada
    $cek = $conn->query("SELECT 1 FROM resep_dokter_racikan_detail WHERE no_resep='$no_resep' AND no_racik='$no_racik' AND kode_brng='$kode_brng'");
    if ($cek && $cek->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Bahan ini sudah ada dalam racikan']);
        exit;
    }

    $ins = $conn->query("INSERT INTO resep_dokter_racikan_detail (no_resep, no_racik, kode_brng, p1, p2, kandungan, jml)
                         VALUES ('$no_resep','$no_racik','$kode_brng','$p1','$p2','$kandungan','$jml')");
    if ($ins) {
        echo json_encode(['status' => 'success', 'message' => 'Bahan berhasil ditambahkan']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }
    exit;
}

// ─── 5. Search Obat Autocomplete ──────────────────────────────
if ($action === 'search_obat') {
    $q = $conn->real_escape_string($_GET['q'] ?? '');
    $res = $conn->query("
        SELECT db.kode_brng, db.nama_brng, db.ralan as harga, ks.satuan,
               COALESCE((SELECT SUM(stok) FROM gudangbarang WHERE kode_brng = db.kode_brng), 0) as total_stok
        FROM databarang db
        LEFT JOIN kodesatuan ks ON db.kode_sat = ks.kode_sat
        WHERE db.status = '1' AND (db.kode_brng LIKE '%$q%' OR db.nama_brng LIKE '%$q%')
        ORDER BY db.nama_brng ASC
        LIMIT 15
    ");

    $list = [];
    if ($res) while ($r = $res->fetch_assoc()) $list[] = $r;

    echo json_encode(['status' => 'success', 'data' => $list]);
    exit;
}

// ─── 6. Live Polling Antrian Resep Farmasi ────────────────────
if ($action === 'get_live_resep') {
    $today  = date('Y-m-d');
    $tgl    = sanitize($_GET['tgl'] ?? $today);
    $status = sanitize($_GET['status'] ?? 'aktif');
    $search = sanitize($_GET['q'] ?? '');

    $where = "ro.tgl_peresepan = '$tgl'";
    if ($status === 'menunggu_telaah') {
        $where .= " AND (ro.tgl_penyerahan = '0000-00-00' OR ro.tgl_penyerahan IS NULL) AND tr.status_telaah IS NULL";
    } elseif ($status === 'siap_serah') {
        $where .= " AND (ro.tgl_penyerahan = '0000-00-00' OR ro.tgl_penyerahan IS NULL) AND tr.status_telaah = 'Lolos'";
    } elseif ($status === 'selesai') {
        $where .= " AND ro.tgl_penyerahan != '0000-00-00' AND ro.tgl_penyerahan IS NOT NULL";
    } elseif ($status === 'aktif') {
        $where .= " AND (ro.tgl_penyerahan = '0000-00-00' OR ro.tgl_penyerahan IS NULL)";
    }

    if ($search) {
        $s = $conn->real_escape_string($search);
        $where .= " AND (ro.no_resep LIKE '%$s%' OR p.nm_pasien LIKE '%$s%' OR r.no_rkm_medis LIKE '%$s%' OR d.nm_dokter LIKE '%$s%')";
    }

    $result = $conn->query("
        SELECT ro.*, r.no_rkm_medis, p.nm_pasien, p.jk, p.tgl_lahir,
               d.nm_dokter, pol.nm_poli, pj.png_jawab as nm_penjab,
               tr.status_telaah, tr.tgl_telaah, tr.petugas as petugas_telaah, tr.catatan_telaah,
               (SELECT COUNT(*) FROM resep_dokter rd WHERE rd.no_resep = ro.no_resep) as total_item,
               (SELECT SUM(rd.jml * db.ralan)
                FROM resep_dokter rd
                JOIN databarang db ON rd.kode_brng = db.kode_brng
                WHERE rd.no_resep = ro.no_resep) as total_biaya
        FROM resep_obat ro
        JOIN reg_periksa r ON ro.no_rawat = r.no_rawat
        JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
        LEFT JOIN dokter d ON ro.kd_dokter = d.kd_dokter
        LEFT JOIN poliklinik pol ON r.kd_poli = pol.kd_poli
        LEFT JOIN penjab pj ON p.kd_pj = pj.kd_pj
        LEFT JOIN telaah_resep tr ON ro.no_resep = tr.no_resep
        WHERE $where
        ORDER BY ro.no_resep DESC, ro.jam_peresepan DESC
    ");

    $resep_list = [];
    if ($result) while ($row = $result->fetch_assoc()) $resep_list[] = $row;

    // Counters
    $c_total = (int)($conn->query("SELECT COUNT(*) as t FROM resep_obat WHERE tgl_peresepan = '$tgl'")->fetch_assoc()['t'] ?? 0);
    $c_menunggu_telaah = (int)($conn->query("
        SELECT COUNT(*) as t FROM resep_obat ro
        LEFT JOIN telaah_resep tr ON ro.no_resep = tr.no_resep
        WHERE ro.tgl_peresepan = '$tgl' AND (ro.tgl_penyerahan = '0000-00-00' OR ro.tgl_penyerahan IS NULL) AND tr.status_telaah IS NULL
    ")->fetch_assoc()['t'] ?? 0);
    $c_siap_serah = (int)($conn->query("
        SELECT COUNT(*) as t FROM resep_obat ro
        JOIN telaah_resep tr ON ro.no_resep = tr.no_resep
        WHERE ro.tgl_peresepan = '$tgl' AND (ro.tgl_penyerahan = '0000-00-00' OR ro.tgl_penyerahan IS NULL) AND tr.status_telaah = 'Lolos'
    ")->fetch_assoc()['t'] ?? 0);
    $c_selesai = (int)($conn->query("SELECT COUNT(*) as t FROM resep_obat WHERE tgl_peresepan = '$tgl' AND tgl_penyerahan != '0000-00-00' AND tgl_penyerahan IS NOT NULL")->fetch_assoc()['t'] ?? 0);

    ob_start();
    if (empty($resep_list)): ?>
      <tr>
        <td colspan="7" style="text-align:center;padding:40px;color:#94a3b8;">
          <i class="fas fa-clipboard-check" style="font-size:32px;color:#10b981;margin-bottom:10px;display:block;"></i>
          Tidak ada antrian resep yang sesuai kriteria pencarian ini.
        </td>
      </tr>
    <?php else: ?>
      <?php foreach ($resep_list as $r): ?>
        <?php
          $is_diserahkan = !empty($r['tgl_penyerahan']) && $r['tgl_penyerahan'] !== '0000-00-00';
          $has_telaah    = !empty($r['status_telaah']);
          $is_lolos      = ($r['status_telaah'] === 'Lolos');

          $items_res = $conn->query("
              SELECT rd.jml, rd.aturan_pakai, db.nama_brng, ks.satuan
              FROM resep_dokter rd
              JOIN databarang db ON rd.kode_brng = db.kode_brng
              LEFT JOIN kodesatuan ks ON db.kode_sat = ks.kode_sat
              WHERE rd.no_resep = '{$r['no_resep']}'
          ");
          $items_arr = [];
          if ($items_res) while ($it = $items_res->fetch_assoc()) $items_arr[] = $it;
        ?>
        <tr>
          <td>
            <code style="font-weight:700;color:#0f172a;background:#f1f5f9;padding:3px 6px;border-radius:4px;font-size:12px;">
              <?= htmlspecialchars($r['no_resep']) ?>
            </code>
            <div style="font-size:11px;color:#64748b;margin-top:3px;">
              <i class="fas fa-clock"></i> <?= substr($r['jam_peresepan'], 0, 5) ?> WIB
            </div>
          </td>
          <td>
            <div style="font-weight:700;font-size:13px;color:#0f172a;">
              <?= htmlspecialchars($r['nm_pasien']) ?>
            </div>
            <div style="font-size:11px;color:#64748b;">
              No. RM: <strong><?= htmlspecialchars($r['no_rkm_medis']) ?></strong> &bull; <?= hitung_umur($r['tgl_lahir']) ?>
            </div>
            <span class="badge badge-light" style="font-size:10.5px;color:#0284c7;"><?= htmlspecialchars($r['nm_penjab'] ?: 'Umum') ?></span>
          </td>
          <td>
            <div style="font-weight:600;font-size:12.5px;color:#0f172a;"><?= htmlspecialchars($r['nm_poli']) ?></div>
            <div style="font-size:11px;color:#64748b;">Dr: <?= htmlspecialchars($r['nm_dokter']) ?></div>
          </td>
          <td>
            <div style="display:flex;flex-direction:column;gap:3px;max-width:280px;">
              <?php if (empty($items_arr)): ?>
                <span style="font-size:11px;color:#94a3b8;font-style:italic;">(Belum ada item obat)</span>
              <?php else: ?>
                <?php foreach ($items_arr as $it): ?>
                  <div style="background:#f8fafc;padding:3px 6px;border-radius:4px;border:1px solid #e2e8f0;font-size:11px;">
                    <strong><?= htmlspecialchars($it['nama_brng']) ?></strong> (<?= (int)$it['jml'] ?> <?= htmlspecialchars($it['satuan']?:'item') ?>)
                    <?php if (!empty($it['aturan_pakai'])): ?>
                      <div style="color:#0284c7;font-size:10px;"><i class="fas fa-circle-info"></i> <?= htmlspecialchars($it['aturan_pakai']) ?></div>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </td>
          <td style="text-align:right;font-family:monospace;font-size:12.5px;font-weight:700;color:#0f172a;">
            <?= rupiah((float)$r['total_biaya']) ?>
          </td>
          <td style="text-align:center;">
            <?php if ($is_diserahkan): ?>
              <span class="badge badge-success" style="font-size:11px;font-weight:700;"><i class="fas fa-check"></i> Diserahkan</span>
              <div style="font-size:10px;color:#64748b;margin-top:2px;"><?= substr($r['jam_penyerahan'], 0, 5) ?> WIB</div>
            <?php elseif ($is_lolos): ?>
              <span class="badge badge-primary" style="background:#0284c7;font-size:11px;font-weight:700;"><i class="fas fa-check-double"></i> Lolos Telaah</span>
              <div style="font-size:10px;color:#059669;margin-top:2px;">Siap Diserahkan</div>
            <?php elseif ($has_telaah): ?>
              <span class="badge badge-warning" style="font-size:11px;font-weight:700;"><?= htmlspecialchars($r['status_telaah']) ?></span>
            <?php else: ?>
              <span class="badge badge-warning" style="font-size:11px;"><i class="fas fa-hourglass-half"></i> Perlu Telaah</span>
            <?php endif; ?>
          </td>
          <td style="text-align:center;">
            <div style="display:flex;flex-direction:column;gap:5px;">
              <div style="display:flex;gap:4px;justify-content:center;">
                <?php if (!$is_diserahkan): ?>
                  <button type="button" class="btn btn-sm btn-secondary" title="Validasi & Edit Resep Obat"
                          onclick="openEditResepModal('<?= htmlspecialchars($r['no_resep']) ?>')"
                          style="padding:4px 8px;font-size:11.5px;display:inline-flex;align-items:center;gap:4px;">
                    <i class="fas fa-edit" style="color:#2563eb;"></i> Edit Resep
                  </button>

                  <button type="button" class="btn btn-sm <?= $is_lolos ? 'btn-outline' : 'btn-primary' ?>"
                          title="Lakukan Telaah / Skrining Resep"
                          onclick="openTelaahModal('<?= htmlspecialchars($r['no_resep']) ?>')"
                          style="padding:4px 8px;font-size:11.5px;display:inline-flex;align-items:center;gap:4px;<?= $is_lolos?'background:#f5f3ff;color:#7c3aed;border-color:#ddd6fe;':'' ?>">
                    <i class="fas fa-clipboard-check"></i> <?= $has_telaah ? 'Edit Telaah' : 'Telaah Resep' ?>
                  </button>
                <?php endif; ?>

                <a href="<?= BASE_URL ?>modules/farmasi/cetak_etiket.php?no_resep=<?= urlencode($r['no_resep']) ?>" target="_blank"
                   class="btn btn-sm btn-secondary" title="Cetak Etiket Obat"
                   style="padding:4px 8px;font-size:11.5px;display:inline-flex;align-items:center;gap:4px;">
                  <i class="fas fa-tag"></i> Etiket
                </a>
              </div>

              <?php if (!$is_diserahkan): ?>
                <?php if ($is_lolos): ?>
                  <form method="POST" action="" onsubmit="return confirm('Konfirmasi penyerahan obat ke pasien? Stok obat akan dipotong otomatis.')">
                    <input type="hidden" name="no_resep" value="<?= htmlspecialchars($r['no_resep']) ?>">
                    <input type="hidden" name="no_rawat" value="<?= htmlspecialchars($r['no_rawat']) ?>">
                    <button type="submit" name="serahkan_obat" value="1" class="btn btn-sm btn-success" style="width:100%;font-size:11.5px;padding:5px;">
                      <i class="fas fa-hand-holding-medical"></i> Serahkan Obat Ke Pasien
                    </button>
                  </form>
                <?php else: ?>
                  <button type="button" class="btn btn-sm btn-secondary" disabled title="Harus lolos telaah resep terlebih dahulu"
                          style="width:100%;font-size:11px;opacity:0.7;cursor:not-allowed;">
                    <i class="fas fa-lock"></i> Selesaikan Telaah Dulu
                  </button>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    <?php endif;
    $html = ob_get_clean();

    echo json_encode([
        'status' => 'success',
        'count'  => count($resep_list),
        'c_menunggu_telaah' => $c_menunggu_telaah,
        'c_siap_serah' => $c_siap_serah,
        'c_selesai' => $c_selesai,
        'c_total' => $c_total,
        'html'   => $html
    ]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Action tidak dikenali']);
exit;
