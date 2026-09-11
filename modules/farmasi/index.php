<?php
/**
 * SIMKlinik — Pelayanan Farmasi & Apotek
 * Alur Kerja: 1. Validasi & Edit Resep -> 2. Telaah Resep (Skrining) -> 3. Penyerahan Obat (Dispensing)
 */

$page_title    = 'Farmasi & Apotek';
$active_module = 'farmasi';

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_module_access('farmasi');

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

$user = current_user();
$petugas_nama = $user['fullname'] ?? 'Petugas Farmasi';

// ─── Proses Penyerahan Obat (Tahap 3) ──────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['serahkan_obat'])) {
    $no_resep = $conn->real_escape_string(sanitize($_POST['no_resep'] ?? ''));
    $no_rawat = $conn->real_escape_string(sanitize($_POST['no_rawat'] ?? ''));

    if ($no_resep && $no_rawat) {
        // Cek apakah resep sudah ditelaah
        $chk_telaah = $conn->query("SELECT status_telaah FROM telaah_resep WHERE no_resep = '$no_resep'");
        $telaah_status = $chk_telaah ? ($chk_telaah->fetch_assoc()['status_telaah'] ?? '') : '';

        if ($telaah_status !== 'Lolos') {
            set_flash('danger', "Resep <strong>$no_resep</strong> belum selesai dilakukan <strong>Telaah Resep</strong> atau statusnya belum 'Lolos'. Silakan lakukan telaah resep terlebih dahulu sebelum menyerahkan obat.");
            redirect(BASE_URL . 'modules/farmasi/index.php');
        }

        $today = date('Y-m-d');
        $now   = date('H:i:s');

        // 1. Update status penyerahan di resep_obat
        $conn->query("UPDATE resep_obat SET tgl_penyerahan = '$today', jam_penyerahan = '$now' WHERE no_resep = '$no_resep'");

        // 2. Ambil detail obat di resep_dokter
        $items = $conn->query("
            SELECT rd.kode_brng, rd.jml, db.ralan, db.h_beli, db.nama_brng
            FROM resep_dokter rd
            JOIN databarang db ON rd.kode_brng = db.kode_brng
            WHERE rd.no_resep = '$no_resep'
        ");

        if ($items) {
            while ($item = $items->fetch_assoc()) {
                $kd_brng = $item['kode_brng'];
                $jml     = (float)$item['jml'];
                $h_beli  = (float)($item['h_beli'] ?? 0);
                $biaya   = (float)($item['ralan'] ?? 0);
                $total   = $biaya * $jml;

                // Masukkan ke detail_pemberian_obat
                $conn->query("
                    INSERT INTO detail_pemberian_obat (
                        tgl_perawatan, jam, no_rawat, kode_brng, h_beli, biaya_obat, jml, embalase, tuslah, total, status, kd_bangsal, no_batch, no_faktur
                    ) VALUES (
                        '$today', '$now', '$no_rawat', '$kd_brng', '$h_beli', '$biaya', '$jml', 0, 0, '$total', 'Ralan', 'APT', '-', '-'
                    ) ON DUPLICATE KEY UPDATE jml = '$jml', total = '$total'
                ");

                // Ambil stok awal sebelum dikurangi
                $stk_res = $conn->query("SELECT SUM(stok) as s FROM gudangbarang WHERE kode_brng = '$kd_brng' AND kd_bangsal IN ('APT', 'GF')");
                $stok_awal = $stk_res ? (float)($stk_res->fetch_assoc()['s'] ?? 0) : 0;
                $stok_akhir = max(0, $stok_awal - $jml);

                // Kurangi stok di gudangbarang (prioritas di APT, jika tidak ada kurangi di GF)
                $conn->query("UPDATE gudangbarang SET stok = GREATEST(0, stok - $jml) WHERE kode_brng = '$kd_brng' ORDER BY (kd_bangsal='APT') DESC LIMIT 1");

                // Catat log di riwayat_barang_medis
                $conn->query("
                    INSERT INTO riwayat_barang_medis (
                        kode_brng, stok_awal, masuk, keluar, stok_akhir, posisi, tanggal, jam, petugas, kd_bangsal, status, no_batch, no_faktur, keterangan
                    ) VALUES (
                        '$kd_brng', '$stok_awal', 0, '$jml', '$stok_akhir', 'Pemberian Obat', '$today', '$now', '$petugas_nama', 'APT', 'Simpan', '-', '-', 'Penyerahan Resep $no_resep No. Rawat $no_rawat'
                    )
                ");
            }
        }

        // 3. Ambil detail bahan obat racikan di resep_dokter_racikan_detail
        $racik_items = $conn->query("
            SELECT rdrd.kode_brng, rdrd.jml, db.ralan, db.h_beli, db.nama_brng, rdr.nama_racik
            FROM resep_dokter_racikan_detail rdrd
            JOIN resep_dokter_racikan rdr ON rdrd.no_resep = rdr.no_resep AND rdrd.no_racik = rdr.no_racik
            JOIN databarang db ON rdrd.kode_brng = db.kode_brng
            WHERE rdrd.no_resep = '$no_resep'
        ");

        if ($racik_items) {
            while ($ritem = $racik_items->fetch_assoc()) {
                $kd_brng = $ritem['kode_brng'];
                $jml     = (float)$ritem['jml'];
                $h_beli  = (float)($ritem['h_beli'] ?? 0);
                $biaya   = (float)($ritem['ralan'] ?? 0);
                $total   = $biaya * $jml;
                $nm_rck  = $ritem['nama_racik'] ?? 'Racikan';

                // Masukkan ke detail_pemberian_obat
                $conn->query("
                    INSERT INTO detail_pemberian_obat (
                        tgl_perawatan, jam, no_rawat, kode_brng, h_beli, biaya_obat, jml, embalase, tuslah, total, status, kd_bangsal, no_batch, no_faktur
                    ) VALUES (
                        '$today', '$now', '$no_rawat', '$kd_brng', '$h_beli', '$biaya', '$jml', 0, 0, '$total', 'Ralan', 'APT', '-', '-'
                    ) ON DUPLICATE KEY UPDATE jml = '$jml', total = '$total'
                ");

                // Ambil stok awal sebelum dikurangi
                $stk_res = $conn->query("SELECT SUM(stok) as s FROM gudangbarang WHERE kode_brng = '$kd_brng' AND kd_bangsal IN ('APT', 'GF')");
                $stok_awal = $stk_res ? (float)($stk_res->fetch_assoc()['s'] ?? 0) : 0;
                $stok_akhir = max(0, $stok_awal - $jml);

                // Kurangi stok di gudangbarang
                $conn->query("UPDATE gudangbarang SET stok = GREATEST(0, stok - $jml) WHERE kode_brng = '$kd_brng' ORDER BY (kd_bangsal='APT') DESC LIMIT 1");

                // Catat log di riwayat_barang_medis
                $conn->query("
                    INSERT INTO riwayat_barang_medis (
                        kode_brng, stok_awal, masuk, keluar, stok_akhir, posisi, tanggal, jam, petugas, kd_bangsal, status, no_batch, no_faktur, keterangan
                    ) VALUES (
                        '$kd_brng', '$stok_awal', 0, '$jml', '$stok_akhir', 'Pemberian Obat', '$today', '$now', '$petugas_nama', 'APT', 'Simpan', '-', '-', 'Penyerahan Obat Racik ($nm_rck) Resep $no_resep No. Rawat $no_rawat'
                    )
                ");
            }
        }

        // Auto-trigger BPJS Antrean Task 7 (Obat Diserahkan / Selesai Pelayanan)
        require_once dirname(__DIR__, 2) . '/includes/bpjs_antrean.php';
        BpjsAntreanService::triggerTaskByRawat($no_rawat, 7);

        set_flash('success', "Obat (termasuk racikan) untuk resep <strong>$no_resep</strong> berhasil diserahkan ke pasien dan stok telah dipotong otomatis.");
        redirect(BASE_URL . 'modules/farmasi/index.php');
    }
}

// ─── Filter & Search ──────────────────────────────────────────
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

// Paginasi Farmasi
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 25;
$offset   = ($page - 1) * $per_page;

$count_res = $conn->query("
    SELECT COUNT(DISTINCT ro.no_resep) as total
    FROM resep_obat ro
    JOIN reg_periksa r ON ro.no_rawat = r.no_rawat
    JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
    LEFT JOIN dokter d ON ro.kd_dokter = d.kd_dokter
    LEFT JOIN telaah_resep tr ON ro.no_resep = tr.no_resep
    WHERE $where
");
$total_rows  = $count_res ? (int)$count_res->fetch_assoc()['total'] : 0;
$total_pages = max(1, (int)ceil($total_rows / $per_page));

$pag = [
    'page'        => $page,
    'per_page'    => $per_page,
    'total'       => $total_rows,
    'total_pages' => $total_pages,
    'offset'      => $offset,
    'has_prev'    => $page > 1,
    'has_next'    => $page < $total_pages,
];

// Query Antrian Resep
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
    GROUP BY ro.no_resep
    ORDER BY ro.no_resep DESC, ro.jam_peresepan DESC
    LIMIT $per_page OFFSET $offset
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

// Master Obat untuk penambahan obat di modal edit
$obat_master = [];
$rom = $conn->query("SELECT kode_brng, nama_brng, ralan, stokminimal FROM databarang WHERE status='1' ORDER BY nama_brng ASC");
if ($rom) while ($r = $rom->fetch_assoc()) $obat_master[] = $r;

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<!-- ─── Page Header ──────────────────────────────────────── -->
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
  <div>
    <h1 class="page-title">Pelayanan Farmasi & Apotek</h1>
    <p class="page-subtitle">Alur Pelayanan: 1. Validasi / Edit Resep &rarr; 2. Telaah Resep Klinis &rarr; 3. Penyerahan Obat</p>
  </div>
  <div class="page-actions" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
    <!-- Text Summary Counter di Kanan Atas -->
    <div style="display:flex;align-items:center;gap:8px;background:#f8fafc;padding:6px 12px;border:1px solid #e2e8f0;border-radius:10px;font-size:12px;">
      <span style="color:#64748b;font-weight:500;">Hari Ini:</span>
      <span class="badge badge-warning" id="badgeMenungguTelaah" style="background:#fef3c7;color:#b45309;border:1px solid #fde68a;font-size:11px;font-weight:700;">
        <i class="fas fa-clock"></i> <?= $c_menunggu_telaah ?> Belum Tervalidasi
      </span>
      <span class="badge badge-primary" id="badgeSiapSerah" style="background:#0284c7;font-size:11px;font-weight:700;">
        <i class="fas fa-clipboard-check"></i> <?= $c_siap_serah ?> Siap Serah
      </span>
      <span class="badge badge-success" id="badgeSelesai" style="font-size:11px;font-weight:700;">
        <i class="fas fa-check"></i> <?= $c_selesai ?> Selesai
      </span>
      <span style="color:#cbd5e1;">|</span>
      <span style="font-weight:700;color:#0f172a;" id="badgeTotalResep">Total: <?= $c_total ?></span>
    </div>

    <a href="<?= BASE_URL ?>modules/gudang_obat/index.php" class="btn btn-secondary">
      <i class="fas fa-boxes-stacked"></i> Gudang Obat
    </a>
  </div>
</div>

<!-- ─── Flow Steps Banner ────────────────────────────────── -->
<div class="card mb-16" style="background:#ffffff;border:1px solid #e2e8f0;">
  <div class="card-body" style="padding:12px 18px;">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
      
      <div style="display:flex;align-items:center;gap:10px;">
        <div style="width:30px;height:30px;border-radius:50%;background:#eff6ff;color:#2563eb;font-weight:700;display:flex;align-items:center;justify-content:center;font-size:13px;border:1px solid #bfdbfe;">1</div>
        <div>
          <div style="font-size:12.5px;font-weight:700;color:#0f172a;">Validasi & Edit Resep</div>
          <div style="font-size:11px;color:#64748b;">Tambah / kurangi / ganti obat dokter</div>
        </div>
      </div>

      <div style="color:#cbd5e1;font-size:16px;">&rarr;</div>

      <div style="display:flex;align-items:center;gap:10px;">
        <div style="width:30px;height:30px;border-radius:50%;background:#f5f3ff;color:#7c3aed;font-weight:700;display:flex;align-items:center;justify-content:center;font-size:13px;border:1px solid #ddd6fe;">2</div>
        <div>
          <div style="font-size:12.5px;font-weight:700;color:#0f172a;">Telaah Resep (Skrining)</div>
          <div style="font-size:11px;color:#64748b;">Skrining administratif, farmasetis & klinis</div>
        </div>
      </div>

      <div style="color:#cbd5e1;font-size:16px;">&rarr;</div>

      <div style="display:flex;align-items:center;gap:10px;">
        <div style="width:30px;height:30px;border-radius:50%;background:#ecfdf5;color:#059669;font-weight:700;display:flex;align-items:center;justify-content:center;font-size:13px;border:1px solid #a7f3d0;">3</div>
        <div>
          <div style="font-size:12.5px;font-weight:700;color:#0f172a;">Penyerahan & Etiket</div>
          <div style="font-size:11px;color:#64748b;">Dispensing, potong stok & edukasi PIO</div>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- ─── Filter Bar ───────────────────────────────────────── -->
<div class="card mb-16">
  <div class="card-body" style="padding:12px 20px;">
    <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
      <div style="display:flex;align-items:center;gap:6px;">
        <label style="font-size:12px;color:#64748b;">Tanggal:</label>
        <input type="date" name="tgl" class="form-control" style="width:145px;" value="<?= $tgl ?>">
      </div>

      <div style="width:180px;">
        <select name="status" class="form-control" onchange="this.form.submit()">
          <option value="aktif" <?= $status==='aktif'?'selected':'' ?>>— Resep Aktif (Belum Selesai) —</option>
          <option value="menunggu_telaah" <?= $status==='menunggu_telaah'?'selected':'' ?>>Belum Tervalidasi (Perlu Telaah)</option>
          <option value="siap_serah" <?= $status==='siap_serah'?'selected':'' ?>>Siap Diserahkan</option>
          <option value="selesai" <?= $status==='selesai'?'selected':'' ?>>Selesai Diserahkan</option>
          <option value="" <?= $status===''?'selected':'' ?>>Semua Resep</option>
        </select>
      </div>

      <div style="flex:1;min-width:200px;">
        <input type="text" name="q" class="form-control" placeholder="Cari No. Resep, Pasien, No. RM, Dokter..." value="<?= htmlspecialchars($search) ?>">
      </div>

      <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
      <?php if ($search || $status !== 'aktif' || $tgl !== $today): ?>
        <a href="<?= BASE_URL ?>modules/farmasi/index.php" class="btn btn-secondary"><i class="fas fa-times"></i> Reset</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<!-- ─── Antrian Resep Table ──────────────────────────────── -->
<div class="card">
  <div class="card-header" style="padding:14px 20px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
    <h3 style="font-size:14px;font-weight:700;color:#0f172a;margin:0;display:flex;align-items:center;gap:8px;">
      <i class="fas fa-pills" style="color:#e11d48;"></i>
      <span>Daftar Antrian Resep Pasien (<?= tgl_indo($tgl, true) ?>)</span>
      <span class="badge-online" style="font-size:10px;font-weight:600;padding:2px 8px;">
        <i class="fas fa-circle"></i> Live Sync
      </span>
    </h3>
    <span style="font-size:12px;color:#64748b;" id="totalResepHeader">Total: <strong><?= count($resep_list) ?></strong> resep</span>
  </div>

  <div class="card-body" style="padding:0;">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr style="background:#f8fafc;border-bottom:1px solid #e2e8f0;">
            <th style="width:130px;">No. Resep / Jam</th>
            <th>Pasien & Penjamin</th>
            <th>Poli & Dokter Penulis</th>
            <th>Item Resep Obat</th>
            <th style="text-align:right;">Estimasi Biaya</th>
            <th style="text-align:center;width:130px;">Status Alur</th>
            <th style="text-align:center;width:220px;">Aksi Pelayanan</th>
          </tr>
        </thead>
        <tbody id="resepTableBody">
          <?php if (empty($resep_list)): ?>
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

                // Detail Item Obat
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
                    <span class="badge" style="background:#fef3c7;color:#b45309;border:1px solid #fde68a;font-size:11px;font-weight:700;display:inline-flex;align-items:center;gap:4px;">
                      <i class="fas fa-clock"></i> Belum Tervalidasi
                    </span>
                    <div style="font-size:10px;color:#92400e;margin-top:2px;font-weight:500;">Perlu Telaah Resep</div>
                  <?php endif; ?>
                </td>
                <td style="text-align:center;">
                  <div style="display:flex;flex-direction:column;gap:5px;">
                    
                    <!-- Baris Aksi 1: Validasi / Edit Resep & Telaah Resep -->
                    <div style="display:flex;gap:4px;justify-content:center;">
                      <?php if (!$is_diserahkan): ?>
                        <!-- 1. Tombol Validasi / Edit Resep -->
                        <button type="button" class="btn btn-sm btn-secondary" title="Validasi & Edit Resep Obat"
                                onclick="openEditResepModal('<?= htmlspecialchars($r['no_resep']) ?>')"
                                style="padding:4px 8px;font-size:11.5px;display:inline-flex;align-items:center;gap:4px;">
                          <i class="fas fa-edit" style="color:#2563eb;"></i> Edit Resep
                        </button>

                        <!-- 2. Tombol Telaah Resep -->
                        <button type="button" class="btn btn-sm <?= $is_lolos ? 'btn-outline' : 'btn-primary' ?>"
                                title="Lakukan Telaah / Skrining Resep"
                                onclick="openTelaahModal('<?= htmlspecialchars($r['no_resep']) ?>')"
                                style="padding:4px 8px;font-size:11.5px;display:inline-flex;align-items:center;gap:4px;<?= $is_lolos?'background:#f5f3ff;color:#7c3aed;border-color:#ddd6fe;':'' ?>">
                          <i class="fas fa-clipboard-check"></i> <?= $has_telaah ? 'Edit Telaah' : 'Telaah Resep' ?>
                        </button>
                      <?php endif; ?>

                      <!-- Tombol Cetak Etiket -->
                      <a href="<?= BASE_URL ?>modules/farmasi/cetak_etiket.php?no_resep=<?= urlencode($r['no_resep']) ?>" target="_blank"
                         class="btn btn-sm btn-secondary" title="Cetak Etiket Obat"
                         style="padding:4px 8px;font-size:11.5px;display:inline-flex;align-items:center;gap:4px;">
                        <i class="fas fa-tag"></i> Etiket
                      </a>
                    </div>

                    <!-- Baris Aksi 2: Penyerahan Obat (Dispensing) -->
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
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?= render_pagination($pag) ?>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- MODAL 1: VALIDASI & EDIT RESEP DOKTER                      -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalEditResep" tabindex="-1" style="display:none;background:rgba(15,23,42,0.6);position:fixed;top:0;left:0;right:0;bottom:0;z-index:9999;align-items:center;justify-content:center;padding:20px;">
  <div style="background:#ffffff;border-radius:14px;width:100%;max-width:760px;box-shadow:0 20px 25px -5px rgba(0,0,0,0.1);max-height:90vh;display:flex;flex-direction:column;overflow:hidden;">
    
    <div style="padding:16px 20px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;background:#f8fafc;">
      <div>
        <h3 style="font-size:16px;font-weight:700;color:#0f172a;margin:0;">
          <i class="fas fa-edit" style="color:#2563eb;margin-right:6px;"></i>
          Validasi & Edit Resep Dokter
        </h3>
        <div style="font-size:12px;color:#64748b;margin-top:2px;" id="editResepSub">Memuat...</div>
      </div>
      <button type="button" onclick="closeEditResepModal()" style="background:none;border:none;font-size:20px;color:#94a3b8;cursor:pointer;">&times;</button>
    </div>

    <div style="overflow-y:auto;padding:20px;display:flex;flex-direction:column;gap:16px;">
      
      <!-- Tabel Obat Paten -->
      <div>
        <label class="form-label" style="font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px;display:block;">
          💊 Obat Non-Racik (Paten):
        </label>
        <div class="table-responsive" style="border:1px solid #e2e8f0;border-radius:8px;">
          <table class="table mb-0" id="tableEditItems">
            <thead>
              <tr style="background:#f1f5f9;font-size:11.5px;">
                <th>Obat</th>
                <th style="width:100px;text-align:center;">Jumlah</th>
                <th>Aturan Pakai</th>
                <th style="width:80px;text-align:center;">Aksi</th>
              </tr>
            </thead>
            <tbody id="editResepBody">
              <tr><td colspan="4" style="text-align:center;padding:20px;">Memuat data obat...</td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Tabel Obat Racikan (Editable) -->
      <div id="editResepRacikanSection" style="display:none;">
        <label class="form-label" style="font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px;display:block;">
          🧪 Obat Racikan:
        </label>
        <div id="editResepRacikanBody" style="display:flex;flex-direction:column;gap:8px;"></div>
      </div>

      <!-- Form Tambah / Ganti Obat Baru -->
      <div style="background:#f8fafc;padding:14px;border-radius:10px;border:1px solid #e2e8f0;">
        <div style="font-size:12.5px;font-weight:700;color:#0f172a;margin-bottom:8px;">
          <i class="fas fa-plus-circle" style="color:#059669;margin-right:4px;"></i> Tambah Obat Ke Resep Ini:
        </div>
        <div style="display:grid;grid-template-columns:2fr 1fr 2fr auto;gap:8px;align-items:end;">
          <div>
            <label style="font-size:11px;color:#64748b;margin-bottom:2px;display:block;">Pilih Obat</label>
            <select id="add_kode_brng" class="form-control" style="font-size:12px;">
              <option value="">— Pilih Obat —</option>
              <?php foreach ($obat_master as $ob): ?>
                <option value="<?= $ob['kode_brng'] ?>"><?= htmlspecialchars($ob['nama_brng']) ?> (<?= $ob['kode_brng'] ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label style="font-size:11px;color:#64748b;margin-bottom:2px;display:block;">Jumlah</label>
            <input type="number" id="add_jml" class="form-control" min="1" value="10" style="font-size:12px;">
          </div>
          <div>
            <label style="font-size:11px;color:#64748b;margin-bottom:2px;display:block;">Aturan Pakai</label>
            <input type="text" id="add_aturan" class="form-control" placeholder="Contoh: 3 x 1 Tablet sesudah makan" style="font-size:12px;">
          </div>
          <button type="button" class="btn btn-primary" onclick="tambahObatKeResep()" style="padding:8px 14px;font-size:12px;">
            <i class="fas fa-plus"></i> Tambah
          </button>
        </div>
      </div>

    </div>

    <div style="padding:14px 20px;border-top:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;background:#f8fafc;">
      <span style="font-size:11.5px;color:#64748b;">Perubahan item obat langsung diperbarui di database resep.</span>
      <button type="button" class="btn btn-primary" onclick="closeEditResepModal()">Selesai & Tutup</button>
    </div>

  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- MODAL 2: TELAAH RESEP (SKRINING FARMASETIS & KLINIS)       -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalTelaah" tabindex="-1" style="display:none;background:rgba(15,23,42,0.6);position:fixed;top:0;left:0;right:0;bottom:0;z-index:9999;align-items:center;justify-content:center;padding:20px;">
  <div style="background:#ffffff;border-radius:14px;width:100%;max-width:680px;box-shadow:0 20px 25px -5px rgba(0,0,0,0.1);max-height:90vh;display:flex;flex-direction:column;overflow:hidden;">
    
    <div style="padding:16px 20px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;background:#f5f3ff;">
      <div>
        <h3 style="font-size:16px;font-weight:700;color:#7c3aed;margin:0;">
          <i class="fas fa-clipboard-check" style="margin-right:6px;"></i>
          Telaah Resep (Skrining Farmasi)
        </h3>
        <div style="font-size:12px;color:#64748b;margin-top:2px;" id="telaahSub">Memuat data...</div>
      </div>
      <button type="button" onclick="closeTelaahModal()" style="background:none;border:none;font-size:20px;color:#94a3b8;cursor:pointer;">&times;</button>
    </div>

    <form id="formTelaah" onsubmit="submitTelaah(event)" style="overflow-y:auto;padding:20px;display:flex;flex-direction:column;gap:14px;">
      <input type="hidden" name="no_resep" id="telaah_no_resep" value="">

      <!-- Ringkasan Obat yang Ditelaah -->
      <div style="background:#f8fafc;padding:10px 14px;border-radius:8px;border:1px solid #e2e8f0;font-size:12px;">
        <strong style="color:#0f172a;">Obat yang diresepkan:</strong>
        <div id="telaahObatList" style="margin-top:4px;color:#475569;">-</div>
      </div>

      <!-- Checklist Skrining -->
      <div style="border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;">
        <div style="background:#f1f5f9;padding:8px 12px;font-weight:700;font-size:12px;color:#334155;">
          Checklist Skrining Administratif, Farmasetis & Klinis
        </div>
        <div style="padding:12px;display:grid;grid-template-columns:1fr 1fr;gap:10px;font-size:12px;">
          
          <div>
            <label style="font-weight:600;display:block;margin-bottom:4px;">1. Tepat Identitas Pasien</label>
            <select name="tepat_identitas" id="tel_identitas" class="form-control">
              <option value="Ya">Ya (Sesuai)</option>
              <option value="Tidak">Tidak</option>
            </select>
          </div>

          <div>
            <label style="font-weight:600;display:block;margin-bottom:4px;">2. Tepat Indikasi & Obat</label>
            <select name="tepat_indikasi" id="tel_indikasi" class="form-control">
              <option value="Ya">Ya (Tepat)</option>
              <option value="Tidak">Tidak</option>
            </select>
          </div>

          <div>
            <label style="font-weight:600;display:block;margin-bottom:4px;">3. Tepat Dosis & Frekuensi</label>
            <select name="tepat_dosis" id="tel_dosis" class="form-control">
              <option value="Ya">Ya (Tepat)</option>
              <option value="Tidak">Tidak</option>
            </select>
          </div>

          <div>
            <label style="font-weight:600;display:block;margin-bottom:4px;">4. Tepat Rute & Waktu Pemberian</label>
            <select name="tepat_waktu" id="tel_waktu" class="form-control">
              <option value="Ya">Ya (Tepat)</option>
              <option value="Tidak">Tidak</option>
            </select>
          </div>

          <div>
            <label style="font-weight:600;display:block;margin-bottom:4px;">5. Duplikasi Pengobatan</label>
            <select name="duplikasi" id="tel_duplikasi" class="form-control">
              <option value="Tidak">Tidak Ada Duplikasi</option>
              <option value="Ya">Ada Duplikasi</option>
            </select>
          </div>

          <div>
            <label style="font-weight:600;display:block;margin-bottom:4px;">6. Alergi / Kontraindikasi</label>
            <select name="alergi" id="tel_alergi" class="form-control">
              <option value="Tidak">Tidak Ada Alergi</option>
              <option value="Ya">Ada Alergi / Kontraindikasi</option>
            </select>
          </div>

          <div style="grid-column: span 2;">
            <label style="font-weight:600;display:block;margin-bottom:4px;">7. Interaksi Obat Yang Signifikan</label>
            <select name="interaksi" id="tel_interaksi" class="form-control">
              <option value="Tidak">Tidak Ada Interaksi Merugikan</option>
              <option value="Ya">Ada Interaksi Obat</option>
            </select>
          </div>

        </div>
      </div>

      <!-- Keputusan Hasil Telaah -->
      <div style="display:grid;grid-template-columns:1fr 2fr;gap:12px;">
        <div class="form-group">
          <label class="form-label" style="font-size:12px;font-weight:700;color:#0f172a;">Hasil Telaah <span style="color:#ef4444;">*</span></label>
          <select name="status_telaah" id="tel_status" class="form-control" required style="font-weight:700;">
            <option value="Lolos">Lolos Telaah (Disetujui)</option>
            <option value="Konfirmasi Dokter">Perlu Konfirmasi Dokter</option>
            <option value="Ditolak">Ditolak / Batal</option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label" style="font-size:12px;font-weight:600;color:#475569;">Catatan Telaah Klinis</label>
          <input type="text" name="catatan_telaah" id="tel_catatan" class="form-control" placeholder="Contoh: Dosis telah disesuaikan dengan berat badan pasien / aman">
        </div>
      </div>

      <div style="padding-top:12px;border-top:1px solid #e2e8f0;display:flex;justify-content:flex-end;gap:8px;">
        <button type="button" class="btn btn-secondary" onclick="closeTelaahModal()">Batal</button>
        <button type="submit" class="btn btn-primary" style="background:#7c3aed;border-color:#7c3aed;">
          <i class="fas fa-save"></i> Simpan Hasil Telaah Resep
        </button>
      </div>
    </form>
  </div>
</div>

<script>
let activeEditNoResep = '';
// Data obat master dari PHP untuk dropdown dinamis bahan racikan
const OBAT_MASTER = <?= json_encode(array_map(fn($o) => ['kode'=>$o['kode_brng'],'nama'=>$o['nama_brng']], $obat_master)) ?>;

function buildObatSelect(idEl, selectedKode) {
  let opts = '<option value="">— Pilih Bahan —</option>';
  OBAT_MASTER.forEach(o => {
    const sel = o.kode === selectedKode ? ' selected' : '';
    opts += `<option value="${o.kode}"${sel}>${o.nama}</option>`;
  });
  return `<select class="form-control form-control-sm" id="${idEl}" style="font-size:11.5px;">${opts}</select>`;
}

// ─── Modal 1: Edit Resep ───────────────────────────────────────
function openEditResepModal(noResep) {
  activeEditNoResep = noResep;
  document.getElementById('modalEditResep').style.display = 'flex';
  loadEditResepData(noResep);
}

function closeEditResepModal() {
  document.getElementById('modalEditResep').style.display = 'none';
  window.location.reload();
}

function loadEditResepData(noResep) {
  document.getElementById('editResepSub').innerText = 'Memuat No. Resep: ' + noResep + '...';
  
  fetch('<?= BASE_URL ?>modules/farmasi/ajax.php?action=get_resep_detail&no_resep=' + encodeURIComponent(noResep))
    .then(r => r.json())
    .then(res => {
      if (res.status === 'success') {
        const h = res.header;
        document.getElementById('editResepSub').innerText = `Pasien: ${h.nm_pasien} (RM: ${h.no_rkm_medis}) — Dokter: ${h.nm_dokter} (${h.nm_poli})`;

        // ── Obat Paten ──
        let html = '';
        if (res.items.length === 0) {
          html = '<tr><td colspan="4" style="text-align:center;padding:16px;color:#94a3b8;">Belum ada obat paten. Silakan tambahkan obat di bawah.</td></tr>';
        } else {
          res.items.forEach(it => {
            html += `
              <tr>
                <td>
                  <strong style="color:#0f172a;font-size:13px;">${it.nama_brng}</strong>
                  <div style="font-size:11px;color:#64748b;">Kode: ${it.kode_brng} &bull; Stok: ${it.total_stok} ${it.satuan||''}</div>
                </td>
                <td style="text-align:center;">
                  <input type="number" class="form-control form-control-sm" min="1" value="${it.jml}" id="qty_${it.kode_brng}" style="text-align:center;font-weight:700;">
                </td>
                <td>
                  <input type="text" class="form-control form-control-sm" value="${it.aturan_pakai || ''}" id="aturan_${it.kode_brng}" placeholder="Aturan pakai...">
                </td>
                <td style="text-align:center;">
                  <div style="display:inline-flex;gap:4px;">
                    <button type="button" class="btn btn-sm btn-primary" title="Simpan Perubahan Obat Ini" onclick="updateItemResep('${it.kode_brng}')" style="padding:4px 8px;">
                      <i class="fas fa-check"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-danger" title="Hapus Obat" onclick="hapusItemResep('${it.kode_brng}')" style="padding:4px 8px;">
                      <i class="fas fa-trash"></i>
                    </button>
                  </div>
                </td>
              </tr>
            `;
          });
        }
        document.getElementById('editResepBody').innerHTML = html;

        // ── Obat Racikan (editable) ──
        const racikanSection = document.getElementById('editResepRacikanSection');
        const racikanBody    = document.getElementById('editResepRacikanBody');
        if (res.racikan && res.racikan.length > 0) {
          racikanSection.style.display = 'block';
          let rHtml = '';
          res.racikan.forEach(rck => {
            const noRacik = rck.no_racik || '';

            // Baris bahan editable
            let bahanRows = '';
            (rck.detail || []).forEach(d => {
              const kodeBrng = d.kode_brng || '';
              bahanRows += `
                <tr>
                  <td style="padding:4px 6px;">
                    ${buildObatSelect('bhn_kode_' + noRacik + '_' + kodeBrng, kodeBrng)}
                  </td>
                  <td style="padding:4px 6px;width:70px;">
                    <input type="number" min="0" step="0.5" value="${d.jml}" id="bhn_jml_${noRacik}_${kodeBrng}" class="form-control form-control-sm" style="text-align:center;font-weight:700;">
                  </td>
                  <td style="padding:4px 6px;width:70px;">
                    <input type="text" value="${d.satuan||'Tab'}" id="bhn_sat_${noRacik}_${kodeBrng}" class="form-control form-control-sm" placeholder="Tab" style="text-align:center;">
                  </td>
                  <td style="padding:4px 6px;width:80px;">
                    <input type="text" value="${d.kandungan||''}" id="bhn_kand_${noRacik}_${kodeBrng}" class="form-control form-control-sm" placeholder="mis: 500mg">
                  </td>
                  <td style="padding:4px 6px;text-align:center;white-space:nowrap;">
                    <button type="button" class="btn btn-sm btn-primary" title="Simpan" onclick="updateBahanRacikan('${noRacik}','${kodeBrng}')" style="padding:3px 8px;">
                      <i class="fas fa-check"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-danger" title="Hapus Bahan" onclick="hapusBahanRacikan('${noRacik}','${kodeBrng}','${d.nama_brng}')" style="padding:3px 8px;margin-left:2px;">
                      <i class="fas fa-times"></i>
                    </button>
                  </td>
                </tr>
              `;
            });

            rHtml += `
              <div style="background:#f5f3ff;border:1px solid #ddd6fe;border-radius:8px;padding:12px 14px;">
                <!-- Header racikan -->
                <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:10px;flex-wrap:wrap;gap:8px;">
                  <div style="font-weight:700;font-size:13px;color:#5b21b6;">🧪 ${rck.nama_racik}</div>
                  <button type="button" class="btn btn-sm btn-danger" onclick="hapusRacikanFarmasi('${noRacik}','${rck.nama_racik}')" style="padding:3px 8px;font-size:11px;">
                    <i class="fas fa-trash"></i> Hapus Racikan Ini
                  </button>
                </div>

                <!-- Edit header: jml kemasan, aturan pakai, keterangan -->
                <div style="display:grid;grid-template-columns:80px 1fr 1fr auto;gap:6px;align-items:end;margin-bottom:10px;">
                  <div>
                    <label style="font-size:10.5px;color:#6d28d9;font-weight:600;margin-bottom:2px;display:block;">Jml Kemasan</label>
                    <input type="number" min="1" value="${rck.jml_dr}" id="rck_jml_${noRacik}" class="form-control form-control-sm" style="font-weight:700;text-align:center;border-color:#ddd6fe;">
                  </div>
                  <div>
                    <label style="font-size:10.5px;color:#6d28d9;font-weight:600;margin-bottom:2px;display:block;">Aturan Pakai</label>
                    <input type="text" value="${rck.aturan_pakai||''}" id="rck_aturan_${noRacik}" class="form-control form-control-sm" placeholder="3 x 1 Bungkus" style="border-color:#ddd6fe;">
                  </div>
                  <div>
                    <label style="font-size:10.5px;color:#6d28d9;font-weight:600;margin-bottom:2px;display:block;">Keterangan</label>
                    <input type="text" value="${rck.keterangan||''}" id="rck_ket_${noRacik}" class="form-control form-control-sm" placeholder="Opsional" style="border-color:#ddd6fe;">
                  </div>
                  <button type="button" class="btn btn-sm btn-primary" onclick="updateRacikanFarmasi('${noRacik}')" style="padding:4px 10px;" title="Simpan Kemasan & Aturan">
                    <i class="fas fa-check"></i> Simpan
                  </button>
                </div>

                <!-- Tabel bahan -->
                <div style="border:1px solid #ddd6fe;border-radius:6px;overflow:hidden;margin-bottom:8px;">
                  <table class="table table-sm mb-0" style="font-size:11.5px;">
                    <thead style="background:#ede9fe;">
                      <tr>
                        <th style="padding:4px 6px;">Nama Bahan / Obat</th>
                        <th style="padding:4px 6px;width:70px;text-align:center;">Jumlah</th>
                        <th style="padding:4px 6px;width:70px;text-align:center;">Satuan</th>
                        <th style="padding:4px 6px;width:80px;">Kandungan</th>
                        <th style="padding:4px 6px;width:80px;text-align:center;">Aksi</th>
                      </tr>
                    </thead>
                    <tbody>${bahanRows || '<tr><td colspan="5" style="text-align:center;padding:10px;color:#94a3b8;">Belum ada bahan</td></tr>'}</tbody>
                  </table>
                </div>

                <!-- Tambah bahan baru -->
                <div style="background:#fff;border:1px dashed #c4b5fd;border-radius:6px;padding:8px;">
                  <div style="font-size:11px;font-weight:700;color:#6d28d9;margin-bottom:6px;"><i class="fas fa-plus"></i> Tambah Bahan ke Racikan Ini:</div>
                  <div style="display:grid;grid-template-columns:2fr 70px 70px 100px auto;gap:6px;align-items:end;">
                    <div>${buildObatSelect('new_bhn_kode_' + noRacik, '')}</div>
                    <div>
                      <input type="number" min="0" step="0.5" value="1" id="new_bhn_jml_${noRacik}" class="form-control form-control-sm" placeholder="Jml" style="text-align:center;">
                    </div>
                    <div>
                      <input type="text" value="Tab" id="new_bhn_sat_${noRacik}" class="form-control form-control-sm" placeholder="Tab" style="text-align:center;">
                    </div>
                    <div>
                      <input type="text" id="new_bhn_kand_${noRacik}" class="form-control form-control-sm" placeholder="mis: 500mg">
                    </div>
                    <button type="button" class="btn btn-sm btn-success" onclick="tambahBahanRacikan('${noRacik}')" style="padding:4px 10px;white-space:nowrap;">
                      <i class="fas fa-plus"></i> Tambah
                    </button>
                  </div>
                </div>
              </div>
            `;
          });
          racikanBody.innerHTML = rHtml;
        } else {
          racikanSection.style.display = 'none';
          racikanBody.innerHTML = '';
        }
      }
    });
}

function updateRacikanFarmasi(noRacik) {
  const jml    = document.getElementById('rck_jml_'   + noRacik).value;
  const aturan = document.getElementById('rck_aturan_' + noRacik).value;
  const ket    = document.getElementById('rck_ket_'   + noRacik).value;

  const fd = new FormData();
  fd.append('action',       'simpan_racikan_farmasi');
  fd.append('no_resep',     activeEditNoResep);
  fd.append('no_racik',     noRacik);
  fd.append('jml_dr',       jml);
  fd.append('aturan_pakai', aturan);
  fd.append('keterangan',   ket);

  fetch('<?= BASE_URL ?>modules/farmasi/ajax.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      if (res.status === 'success') {
        alert('Racikan berhasil diperbarui.');
        loadEditResepData(activeEditNoResep);
      } else {
        alert('Gagal: ' + res.message);
      }
    });
}

function hapusRacikanFarmasi(noRacik, namaRacik) {
  if (!confirm(`Yakin ingin menghapus racikan "${namaRacik}" dari resep ini? Semua bahan komposisinya juga akan dihapus.`)) return;

  const fd = new FormData();
  fd.append('action',   'hapus_racikan_farmasi');
  fd.append('no_resep', activeEditNoResep);
  fd.append('no_racik', noRacik);

  fetch('<?= BASE_URL ?>modules/farmasi/ajax.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      if (res.status === 'success') {
        loadEditResepData(activeEditNoResep);
      } else {
        alert('Gagal: ' + res.message);
      }
    });
}

function updateBahanRacikan(noRacik, kodeLama) {
  const selectEl = document.getElementById('bhn_kode_' + noRacik + '_' + kodeLama);
  const kode_baru = selectEl ? selectEl.value : kodeLama;
  const jml      = document.getElementById('bhn_jml_'  + noRacik + '_' + kodeLama)?.value || 0;
  const sat      = document.getElementById('bhn_sat_'  + noRacik + '_' + kodeLama)?.value || '';
  const kand     = document.getElementById('bhn_kand_' + noRacik + '_' + kodeLama)?.value || '';

  const fd = new FormData();
  fd.append('action',    'update_bahan_racikan');
  fd.append('no_resep',  activeEditNoResep);
  fd.append('no_racik',  noRacik);
  fd.append('kode_lama', kodeLama);
  fd.append('kode_brng', kode_baru);
  fd.append('jml',       jml);
  fd.append('kandungan', kand);

  fetch('<?= BASE_URL ?>modules/farmasi/ajax.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      if (res.status === 'success') {
        loadEditResepData(activeEditNoResep);
      } else {
        alert('Gagal: ' + res.message);
      }
    });
}

function hapusBahanRacikan(noRacik, kodeBrng, namaBahan) {
  if (!confirm(`Hapus bahan "${namaBahan}" dari racikan ini?`)) return;

  const fd = new FormData();
  fd.append('action',    'hapus_bahan_racikan');
  fd.append('no_resep',  activeEditNoResep);
  fd.append('no_racik',  noRacik);
  fd.append('kode_brng', kodeBrng);

  fetch('<?= BASE_URL ?>modules/farmasi/ajax.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      if (res.status === 'success') {
        loadEditResepData(activeEditNoResep);
      } else {
        alert('Gagal: ' + res.message);
      }
    });
}

function tambahBahanRacikan(noRacik) {
  const kode  = document.getElementById('new_bhn_kode_' + noRacik)?.value || '';
  const jml   = document.getElementById('new_bhn_jml_'  + noRacik)?.value || 0;
  const sat   = document.getElementById('new_bhn_sat_'  + noRacik)?.value || '';
  const kand  = document.getElementById('new_bhn_kand_' + noRacik)?.value || '';

  if (!kode) { alert('Pilih obat/bahan terlebih dahulu.'); return; }

  const fd = new FormData();
  fd.append('action',    'tambah_bahan_racikan');
  fd.append('no_resep',  activeEditNoResep);
  fd.append('no_racik',  noRacik);
  fd.append('kode_brng', kode);
  fd.append('jml',       jml);
  fd.append('kandungan', kand);

  fetch('<?= BASE_URL ?>modules/farmasi/ajax.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      if (res.status === 'success') {
        loadEditResepData(activeEditNoResep);
      } else {
        alert('Gagal: ' + res.message);
      }
    });
}

function updateItemResep(kodeBrng) {
  const qty = document.getElementById('qty_' + kodeBrng).value;
  const aturan = document.getElementById('aturan_' + kodeBrng).value;

  const fd = new FormData();
  fd.append('action', 'simpan_item_resep');
  fd.append('no_resep', activeEditNoResep);
  fd.append('kode_brng', kodeBrng);
  fd.append('jml', qty);
  fd.append('aturan_pakai', aturan);

  fetch('<?= BASE_URL ?>modules/farmasi/ajax.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      if (res.status === 'success') {
        alert('Obat berhasil diperbarui.');
        loadEditResepData(activeEditNoResep);
      } else {
        alert('Gagal: ' + res.message);
      }
    });
}

function hapusItemResep(kodeBrng) {
  if (!confirm('Yakin ingin menghapus obat ini dari resep?')) return;

  const fd = new FormData();
  fd.append('action', 'hapus_item_resep');
  fd.append('no_resep', activeEditNoResep);
  fd.append('kode_brng', kodeBrng);

  fetch('<?= BASE_URL ?>modules/farmasi/ajax.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      if (res.status === 'success') {
        loadEditResepData(activeEditNoResep);
      } else {
        alert('Gagal: ' + res.message);
      }
    });
}

function tambahObatKeResep() {
  const kode = document.getElementById('add_kode_brng').value;
  const jml  = document.getElementById('add_jml').value;
  const atr  = document.getElementById('add_aturan').value;

  if (!kode) {
    alert('Silakan pilih obat terlebih dahulu.');
    return;
  }

  const fd = new FormData();
  fd.append('action', 'simpan_item_resep');
  fd.append('no_resep', activeEditNoResep);
  fd.append('kode_brng', kode);
  fd.append('jml', jml);
  fd.append('aturan_pakai', atr);

  fetch('<?= BASE_URL ?>modules/farmasi/ajax.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      if (res.status === 'success') {
        document.getElementById('add_kode_brng').value = '';
        document.getElementById('add_aturan').value = '';
        loadEditResepData(activeEditNoResep);
      } else {
        alert('Gagal: ' + res.message);
      }
    });
}

// ─── Modal 2: Telaah Resep ─────────────────────────────────────
function openTelaahModal(noResep) {
  document.getElementById('telaah_no_resep').value = noResep;
  document.getElementById('modalTelaah').style.display = 'flex';
  document.getElementById('telaahSub').innerText = 'No. Resep: ' + noResep;

  fetch('<?= BASE_URL ?>modules/farmasi/ajax.php?action=get_resep_detail&no_resep=' + encodeURIComponent(noResep))
    .then(r => r.json())
    .then(res => {
      if (res.status === 'success') {
        const h = res.header;
        document.getElementById('telaahSub').innerText = `Pasien: ${h.nm_pasien} (RM: ${h.no_rkm_medis}) &bull; Dokter: ${h.nm_dokter} (${h.nm_poli})`;

        // Daftar obat paten & racikan
        let obatStr = res.items.map(it => `&bull; <strong>${it.nama_brng}</strong> (${it.jml} ${it.satuan||''}) — <em>${it.aturan_pakai||'-'}</em>`).join('<br>');
        if (res.racikan && res.racikan.length > 0) {
          if (obatStr) obatStr += '<div style="margin-top:8px;border-top:1px dashed #cbd5e1;padding-top:6px;"></div>';
          obatStr += '<strong>Obat Racikan:</strong><br>';
          obatStr += res.racikan.map(rck => {
            let detailsStr = (rck.detail || []).map(d => `${d.nama_brng} (${d.jml})`).join(', ');
            return `🧪 <strong>${rck.nama_racik}</strong> (${rck.jml_dr} ${rck.nm_racik||'Bungkus'}) — <em>${rck.aturan_pakai||'-'}</em><br><small style="color:#64748b;margin-left:14px;">Bahan: ${detailsStr || '-'}</small>`;
          }).join('<br>');
        }
        document.getElementById('telaahObatList').innerHTML = obatStr || '(Tidak ada item obat)';

        // Prefill form telaah jika ada
        const t = res.telaah;
        if (t) {
          document.getElementById('tel_identitas').value = t.tepat_identitas || 'Ya';
          document.getElementById('tel_indikasi').value = t.tepat_indikasi || 'Ya';
          document.getElementById('tel_dosis').value = t.tepat_dosis || 'Ya';
          document.getElementById('tel_waktu').value = t.tepat_waktu || 'Ya';
          document.getElementById('tel_duplikasi').value = t.duplikasi || 'Tidak';
          document.getElementById('tel_alergi').value = t.alergi || 'Tidak';
          document.getElementById('tel_interaksi').value = t.interaksi || 'Tidak';
          document.getElementById('tel_status').value = t.status_telaah || 'Lolos';
          document.getElementById('tel_catatan').value = t.catatan_telaah || '';
        } else {
          document.getElementById('tel_identitas').value = 'Ya';
          document.getElementById('tel_indikasi').value = 'Ya';
          document.getElementById('tel_dosis').value = 'Ya';
          document.getElementById('tel_waktu').value = 'Ya';
          document.getElementById('tel_duplikasi').value = 'Tidak';
          document.getElementById('tel_alergi').value = 'Tidak';
          document.getElementById('tel_interaksi').value = 'Tidak';
          document.getElementById('tel_status').value = 'Lolos';
          document.getElementById('tel_catatan').value = '';
        }
      }
    });
}

function closeTelaahModal() {
  document.getElementById('modalTelaah').style.display = 'none';
}

function submitTelaah(e) {
  e.preventDefault();
  const form = document.getElementById('formTelaah');
  const fd = new FormData(form);
  fd.append('action', 'simpan_telaah');

  fetch('<?= BASE_URL ?>modules/farmasi/ajax.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      if (res.status === 'success') {
        alert('Hasil telaah resep berhasil disimpan.');
        closeTelaahModal();
        window.location.reload();
      } else {
        alert('Gagal: ' + res.message);
      }
    });
}

// ─── Real-Time Live Background Polling untuk Farmasi ─────────
(function() {
  let lastResepCount = <?= count($resep_list) ?>;
  const filterTgl = '<?= urlencode($tgl) ?>';
  const filterStatus = '<?= urlencode($status) ?>';
  const filterQ = '<?= urlencode($search) ?>';
  const isToday = (filterTgl === '<?= date('Y-m-d') ?>');

  function pollLiveResep() {
    if (!isToday) return;

    // Jangan polling jika modal sedang terbuka
    const mEdit = document.getElementById('modalEditResep');
    const mTelaah = document.getElementById('modalTelaah');
    if ((mEdit && mEdit.style.display === 'flex') || (mTelaah && mTelaah.style.display === 'flex')) {
      return;
    }

    fetch(`<?= BASE_URL ?>modules/farmasi/ajax.php?action=get_live_resep&tgl=${filterTgl}&status=${filterStatus}&q=${filterQ}`)
      .then(r => r.json())
      .then(res => {
        if (res.status === 'success') {
          const tbody = document.getElementById('resepTableBody');
          const headerTotal = document.getElementById('totalResepHeader');
          const badgeMenunggu = document.getElementById('badgeMenungguTelaah');
          const badgeSiap = document.getElementById('badgeSiapSerah');
          const badgeSelesai = document.getElementById('badgeSelesai');
          const badgeTotal = document.getElementById('badgeTotalResep');

          if (res.count > lastResepCount) {
            showToast(`💊 Resep obat baru masuk dari dokter! (+${res.count - lastResepCount} Resep)`, 'info');
          }
          lastResepCount = res.count;

          if (tbody) tbody.innerHTML = res.html;
          if (headerTotal) headerTotal.innerHTML = `Total: <strong>${res.count}</strong> resep`;
          if (badgeMenunggu) badgeMenunggu.innerHTML = `<i class="fas fa-file-pen"></i> ${res.c_menunggu_telaah} Perlu Telaah`;
          if (badgeSiap) badgeSiap.innerHTML = `<i class="fas fa-clipboard-check"></i> ${res.c_siap_serah} Siap Serah`;
          if (badgeSelesai) badgeSelesai.innerHTML = `<i class="fas fa-check"></i> ${res.c_selesai} Selesai`;
          if (badgeTotal) badgeTotal.innerHTML = `Total: ${res.c_total}`;
        }
      })
      .catch(err => console.debug('Live sync farmasi:', err));
  }

  setInterval(pollLiveResep, 8000);
})();
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
