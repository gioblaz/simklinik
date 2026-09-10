<?php
/**
 * SIMKlinik — Antrian & Pendaftaran Pasien
 * Dilengkapi Form Tambah / Edit Pendaftaran Expandable & Collapsible Real-Time
 */

$page_title    = 'Pendaftaran & Antrian';
$active_module = 'pendaftaran';

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

// ─── AJAX Actions ─────────────────────────────────────────────
if (isset($_GET['action']) || isset($_POST['action'])) {
    $action = sanitize($_GET['action'] ?? $_POST['action'] ?? '');

    // 1. Ambil Detail Pendaftaran untuk Edit
    if ($action === 'get_detail') {
        header('Content-Type: application/json');
        $no_rawat = $conn->real_escape_string($_GET['no_rawat'] ?? '');
        $res = $conn->query("
            SELECT r.*, p.nm_pasien, p.jk, p.tgl_lahir, p.no_ktp, p.no_tlp, p.no_peserta,
                   d.nm_dokter, pol.nm_poli, pj.png_jawab as nm_penjab
            FROM reg_periksa r
            JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
            LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
            LEFT JOIN poliklinik pol ON r.kd_poli = pol.kd_poli
            LEFT JOIN penjab pj ON r.kd_pj = pj.kd_pj
            WHERE r.no_rawat = '$no_rawat'
            LIMIT 1
        ");
        if ($res && $res->num_rows > 0) {
            $data = $res->fetch_assoc();
            $data['umur'] = hitung_umur($data['tgl_lahir']);
            echo json_encode(['success' => true, 'data' => $data]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan']);
        }
        exit;
    }

    // 2. Ambil No. Rawat Baru & No. Reg Otomatis
    if ($action === 'get_new_noreg') {
        header('Content-Type: application/json');
        $kd_poli = $conn->real_escape_string($_GET['kd_poli'] ?? '');
        $tgl_reg = $conn->real_escape_string($_GET['tgl'] ?? date('Y-m-d'));

        $r_noreg = $conn->query("SELECT COUNT(*) as cnt FROM reg_periksa WHERE tgl_registrasi = '$tgl_reg' AND kd_poli = '$kd_poli'");
        $cnt     = $r_noreg ? (int)$r_noreg->fetch_assoc()['cnt'] : 0;
        $no_reg  = sprintf('%03d', $cnt + 1);
        $no_rawat = generate_no_rawat();

        echo json_encode(['success' => true, 'no_rawat' => $no_rawat, 'no_reg' => $no_reg, 'jam' => date('H:i:s')]);
        exit;
    }

    // 3. Simpan Pendaftaran (Tambah Baru atau Update Edit)
    if ($action === 'simpan_pendaftaran') {
        header('Content-Type: application/json');
        $mode        = sanitize($_POST['form_mode'] ?? 'tambah');
        $no_rawat    = $conn->real_escape_string(trim($_POST['no_rawat'] ?? ''));
        $no_rm       = $conn->real_escape_string(trim($_POST['no_rkm_medis'] ?? ''));
        $tgl_reg     = $conn->real_escape_string(trim($_POST['tgl_registrasi'] ?? date('Y-m-d')));
        $jam_reg     = $conn->real_escape_string(trim($_POST['jam_reg'] ?? date('H:i:s')));
        $kd_poli     = $conn->real_escape_string(trim($_POST['kd_poli'] ?? ''));
        $kd_dok      = $conn->real_escape_string(trim($_POST['kd_dokter'] ?? ''));
        $kd_pj       = $conn->real_escape_string(trim($_POST['kd_pj'] ?? ''));
        $no_reg      = $conn->real_escape_string(trim($_POST['no_reg'] ?? '001'));
        $no_peserta  = $conn->real_escape_string(trim($_POST['no_peserta'] ?? ''));

        if (empty($no_rm)) {
            echo json_encode(['success' => false, 'message' => 'Silakan pilih pasien terlebih dahulu.']);
            exit;
        }
        if (empty($kd_poli)) {
            echo json_encode(['success' => false, 'message' => 'Pilih poliklinik tujuan.']);
            exit;
        }
        if (empty($kd_dok)) {
            echo json_encode(['success' => false, 'message' => 'Pilih dokter pemeriksa.']);
            exit;
        }

        if ($mode === 'edit') {
            // Update Existing Pendaftaran
            $sql = "UPDATE reg_periksa SET
                        tgl_registrasi = '$tgl_reg',
                        jam_reg        = '$jam_reg',
                        kd_poli        = '$kd_poli',
                        kd_dokter      = '$kd_dok',
                        kd_pj          = '$kd_pj',
                        no_reg         = '$no_reg'
                    WHERE no_rawat = '$no_rawat'";

            if ($conn->query($sql)) {
                if (!empty($no_peserta) || !empty($kd_pj)) {
                    $conn->query("UPDATE pasien SET no_peserta = '$no_peserta', kd_pj = '$kd_pj' WHERE no_rkm_medis = '$no_rm'");
                }
                echo json_encode(['success' => true, 'message' => "Pendaftaran No. Rawat $no_rawat berhasil diperbarui."]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal update: ' . $conn->error]);
            }
            exit;
        } else {
            // Tambah Baru
            if (empty($no_rawat)) $no_rawat = generate_no_rawat();

            // Cek duplikasi pasien di poli yang sama hari ini
            $cek = $conn->query("SELECT no_rawat FROM reg_periksa WHERE no_rkm_medis = '$no_rm' AND tgl_registrasi = '$tgl_reg' AND kd_poli = '$kd_poli' AND stts != 'Batal' LIMIT 1");
            if ($cek && $cek->num_rows > 0) {
                echo json_encode(['success' => false, 'message' => 'Pasien sudah terdaftar di poliklinik ini pada tanggal tersebut.']);
                exit;
            }

            // Ambil info pendukung pasien
            $p_info = $conn->query("
                SELECT p.namakeluarga, p.alamatpj, p.keluarga, p.tgl_lahir, pol.registrasi as biaya_reg
                FROM pasien p
                LEFT JOIN poliklinik pol ON pol.kd_poli = '$kd_poli'
                WHERE p.no_rkm_medis = '$no_rm'
                LIMIT 1
            ")->fetch_assoc();

            $p_jawab    = $conn->real_escape_string($p_info['namakeluarga'] ?? '-');
            $almt_pj    = $conn->real_escape_string($p_info['alamatpj'] ?? '-');
            $hubunganpj = $conn->real_escape_string($p_info['keluarga'] ?? '-');
            $biaya_reg  = (float)($p_info['biaya_reg'] ?? 0);
            $umurdaftar = 0;
            $sttsumur   = 'Th';
            if (!empty($p_info['tgl_lahir'])) {
                $diff = date_diff(date_create($p_info['tgl_lahir']), date_create($tgl_reg));
                $umurdaftar = $diff->y > 0 ? $diff->y : ($diff->m > 0 ? $diff->m : $diff->d);
                $sttsumur   = $diff->y > 0 ? 'Th' : ($diff->m > 0 ? 'Bl' : 'Hr');
            }

            // Update info penjamin di master pasien
            if (!empty($no_peserta) || !empty($kd_pj)) {
                $conn->query("UPDATE pasien SET no_peserta = '$no_peserta', kd_pj = '$kd_pj' WHERE no_rkm_medis = '$no_rm'");
            }

            $sql = "INSERT INTO reg_periksa (
                no_rawat, tgl_registrasi, jam_reg, no_rkm_medis,
                kd_dokter, kd_poli, kd_pj, p_jawab, almt_pj, hubunganpj,
                biaya_reg, stts, stts_daftar, status_lanjut, no_reg,
                umurdaftar, sttsumur, status_bayar, status_poli
            ) VALUES (
                '$no_rawat', '$tgl_reg', '$jam_reg', '$no_rm',
                '$kd_dok', '$kd_poli', '$kd_pj', '$p_jawab', '$almt_pj', '$hubunganpj',
                $biaya_reg, 'Belum', 'Lama', 'Ralan', '$no_reg',
                $umurdaftar, '$sttsumur', 'Belum Bayar', 'Lama'
            )";

            if ($conn->query($sql)) {
                // Auto-link antrean referensi & kirim Task 1, 2, 3 ke Antrean BPJS
                require_once dirname(__DIR__, 2) . '/includes/bpjs_antrean.php';
                $cek_ref = $conn->query("SELECT kodebooking FROM mlite_antrian_referensi WHERE no_rkm_medis = '$no_rm' AND tanggal_periksa = '$tgl_reg' LIMIT 1");
                $kodebooking = ($cek_ref && $cek_ref->num_rows > 0) ? $cek_ref->fetch_assoc()['kodebooking'] : '';
                if (empty($kodebooking)) {
                    $kodebooking = 'BK' . date('Ymd') . str_pad($no_reg, 4, '0', STR_PAD_LEFT);
                    $conn->query("
                        INSERT INTO mlite_antrian_referensi (tanggal_periksa, nomor_referensi, kodebooking, no_rkm_medis, status_kirim)
                        VALUES ('$tgl_reg', '$no_rawat', '$kodebooking', '$no_rm', 'Terkirim')
                        ON DUPLICATE KEY UPDATE kodebooking = '$kodebooking'
                    ");
                }
                $now_ms = intval(microtime(true) * 1000);
                BpjsAntreanService::updateTaskId($kodebooking, 1, $now_ms - 60000);
                BpjsAntreanService::updateTaskId($kodebooking, 2, $now_ms - 30000);
                BpjsAntreanService::updateTaskId($kodebooking, 3, $now_ms);

                echo json_encode(['success' => true, 'message' => "Pasien berhasil didaftarkan. No. Antrian: $no_reg | No. Rawat: $no_rawat"]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal mendaftar: ' . $conn->error]);
            }
            exit;
        }
    }

    // 4. Update Status Cepat (Batal/Panggil)
    if ($action === 'batal') {
        header('Content-Type: application/json');
        $no_rawat = $conn->real_escape_string($_POST['no_rawat'] ?? '');
        $conn->query("UPDATE reg_periksa SET stts = 'Batal' WHERE no_rawat = '$no_rawat'");
        echo json_encode(['success' => true]);
        exit;
    }
}

// ─── Filter & Data Antrian ────────────────────────────────────
$today     = date('Y-m-d');
$tgl       = sanitize($_GET['tgl'] ?? $today);
$kd_poli   = sanitize($_GET['kd_poli'] ?? '');
$kd_dokter = sanitize($_GET['kd_dokter'] ?? '');
$status    = sanitize($_GET['status'] ?? '');
$search    = sanitize($_GET['q'] ?? '');
$sort_by   = sanitize($_GET['sort_by'] ?? 'dokter_noreg');

$where  = "r.tgl_registrasi = '$tgl'";
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
        $sort_by   = 'dokter_noreg';
        break;
}

// Paginasi Antrian Pendaftaran
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 25;
$offset   = ($page - 1) * $per_page;

$count_result = $conn->query("
    SELECT COUNT(DISTINCT r.no_rawat) as total
    FROM reg_periksa r
    JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
    WHERE $where
");
$total_rows  = $count_result ? (int)$count_result->fetch_assoc()['total'] : 0;
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

// Master Poliklinik, Penjab & Dokter
$poli_list = [];
$rp = $conn->query("SELECT kd_poli, nm_poli FROM poliklinik WHERE status='1' ORDER BY nm_poli");
if ($rp) while ($row = $rp->fetch_assoc()) $poli_list[] = $row;

$penjab_list = [];
$rj = $conn->query("SELECT kd_pj, png_jawab as nm_penjab FROM penjab WHERE status = '1' ORDER BY png_jawab");
if ($rj) while ($row = $rj->fetch_assoc()) $penjab_list[] = $row;

$dokter_list = [];
$rd = $conn->query("SELECT kd_dokter, nm_dokter FROM dokter WHERE status='1' ORDER BY nm_dokter");
if ($rd) while ($row = $rd->fetch_assoc()) $dokter_list[] = $row;

// Data antrian dengan data penunjang PCare BPJS & EMR tanpa duplikasi
$result = $conn->query("
    SELECT r.no_rawat, r.no_reg, r.tgl_registrasi, r.jam_reg,
           r.stts, r.status_lanjut, r.kd_dokter, r.kd_poli, r.kd_pj,
           p.nm_pasien, p.no_rkm_medis, p.jk, p.tgl_lahir, p.no_peserta, p.no_ktp, p.alamat,
           d.nm_dokter, pol.nm_poli, pj.png_jawab as nm_penjab,
           pr.suhu_tubuh, pr.tensi, pr.nadi, pr.respirasi, pr.tinggi, pr.berat, pr.spo2, pr.gcs, pr.keluhan,
           (SELECT dp.kd_penyakit FROM diagnosa_pasien dp WHERE dp.no_rawat = r.no_rawat ORDER BY dp.prioritas ASC LIMIT 1) as kd_diag_utama,
           (SELECT pen.nm_penyakit FROM diagnosa_pasien dp LEFT JOIN penyakit pen ON dp.kd_penyakit = pen.kd_penyakit WHERE dp.no_rawat = r.no_rawat ORDER BY dp.prioritas ASC LIMIT 1) as nm_diag_utama,
           (SELECT rj.noKunjungan FROM pcare_rujuk_subspesialis rj WHERE rj.no_rawat = r.no_rawat LIMIT 1) as no_rujukan_sub,
           (SELECT rk.noKunjungan FROM pcare_rujuk_khusus rk WHERE rk.no_rawat = r.no_rawat LIMIT 1) as no_rujukan_khusus,
           (SELECT ku.noKunjungan FROM pcare_kunjungan_umum ku WHERE ku.no_rawat = r.no_rawat LIMIT 1) as no_kunjungan_pcare
    FROM reg_periksa r
    JOIN pasien p      ON r.no_rkm_medis = p.no_rkm_medis
    LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
    LEFT JOIN poliklinik pol ON r.kd_poli = pol.kd_poli
    LEFT JOIN penjab pj ON r.kd_pj = pj.kd_pj
    LEFT JOIN (
        SELECT pr1.* FROM pemeriksaan_ralan pr1
        JOIN (SELECT no_rawat, MAX(CONCAT(tgl_perawatan, ' ', jam_rawat)) as max_time FROM pemeriksaan_ralan GROUP BY no_rawat) pr2
          ON pr1.no_rawat = pr2.no_rawat AND CONCAT(pr1.tgl_perawatan, ' ', pr1.jam_rawat) = pr2.max_time
    ) pr ON r.no_rawat = pr.no_rawat
    WHERE $where
    GROUP BY r.no_rawat
    ORDER BY $order_sql
    LIMIT $per_page OFFSET $offset
");

$list = [];
if ($result) while ($row = $result->fetch_assoc()) $list[] = $row;

// ─── Live Polling Table HTML Output ───────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'get_live_pendaftaran') {
    header('Content-Type: application/json');
    ob_start();
    if (empty($list)): ?>
      <tr>
        <td colspan="8" style="text-align:center;padding:36px;color:#94a3b8;">
          <i class="fas fa-clipboard-user" style="font-size:28px;color:#cbd5e1;display:block;margin-bottom:8px;"></i>
          Belum ada pasien yang terdaftar pada filter ini.
        </td>
      </tr>
    <?php else: ?>
      <?php foreach ($list as $i => $k): 
        $is_bpjs = ($k['kd_pj'] === 'BPJ' || str_contains(strtolower($k['nm_penjab'] ?? ''), 'bpjs'));
        $has_rujukan = !empty($k['no_rujukan_sub']) || !empty($k['no_rujukan_khusus']);
        $has_kunj_pcare = !empty($k['no_kunjungan_pcare']) || $has_rujukan;
        $no_rujukan = $k['no_rujukan_sub'] ?: ($k['no_rujukan_khusus'] ?: '');
        $data_json = htmlspecialchars(json_encode($k), ENT_QUOTES, 'UTF-8');
      ?>
        <tr id="row-<?= htmlspecialchars($k['no_rawat']) ?>">
          <td style="text-align:center;font-weight:700;color:var(--primary-700);font-size:13px;padding:9px 10px;">
            <?= htmlspecialchars($k['no_reg'] ?: sprintf('%03d', $i+1)) ?>
          </td>
          <td style="padding:9px 12px;">
            <div style="display:flex;align-items:center;gap:10px;">
              <span style="width:8px;height:8px;border-radius:50%;background:#f59e0b;box-shadow:0 0 0 2px #fef3c7;display:inline-block;flex-shrink:0;"></span>
              <div>
                <div style="font-size:13px;font-weight:700;color:#0f172a;line-height:1.2;"><?= htmlspecialchars($k['nm_pasien']) ?> <?= icon_jk($k['jk']) ?></div>
                <div style="font-size:11px;color:#64748b;margin-top:3px;">
                  MR: <strong style="color:#0f766e;"><?= $k['no_rkm_medis'] ?></strong> &bull; <?= hitung_umur($k['tgl_lahir']) ?>
                </div>
              </div>
            </div>
          </td>
          <td style="padding:9px 12px;">
            <div style="font-size:12.5px;font-weight:600;color:#0f172a;"><?= htmlspecialchars($k['nm_poli'] ?: '-') ?></div>
            <div style="font-size:11px;color:#64748b;margin-top:2px;"><?= htmlspecialchars($k['nm_dokter'] ?: '-') ?></div>
          </td>
          <td style="padding:9px 12px;">
            <?= badge_penjab($k['nm_penjab'] ?? 'Umum', $is_bpjs ? ($k['no_peserta'] ?? null) : null) ?>
          </td>
          <td style="font-size:11.5px;color:#475569;font-weight:600;padding:9px 10px;">
            <i class="fas fa-clock" style="font-size:10px;color:#94a3b8;margin-right:2px;"></i> <?= substr($k['jam_reg'],0,5) ?>
          </td>
          <td style="padding:9px 10px;"><?= badge_status($k['stts']) ?></td>
          <td style="padding:9px 10px;">
            <?php if ($is_bpjs): ?>
              <div style="display:flex;flex-direction:column;gap:4px;">
                <div style="display:flex;align-items:center;gap:4px;">
                  <?php if ($has_kunj_pcare): ?>
                    <span style="font-size:10.5px;font-weight:700;color:#047857;background:#d1fae5;padding:2px 6px;border-radius:4px;display:inline-flex;align-items:center;gap:3px;">
                      <i class="fas fa-check-circle"></i> Terkirim
                    </span>
                  <?php else: ?>
                    <button type="button" class="btn btn-sm btn-outline-primary" style="padding:2px 6px;font-size:11px;" title="Kirim Kunjungan ke PCare BPJS" onclick="kirimKunjunganPcareRow('<?= htmlspecialchars($k['no_rawat']) ?>')">
                      <i class="fas fa-cloud-arrow-up"></i> Kirim PCare
                    </button>
                  <?php endif; ?>
                </div>
                <div style="display:flex;align-items:center;gap:4px;">
                  <?php if ($has_rujukan): ?>
                    <a href="<?= BASE_URL ?>modules/pcare/cetak_rujukan.php?no_rawat=<?= urlencode($k['no_rawat']) ?>" target="_blank" class="btn btn-sm btn-outline-success" style="padding:2px 6px;font-size:11px;" title="Cetak Surat Rujukan BPJS">
                      <i class="fas fa-print"></i> Cetak Rujuk
                    </a>
                  <?php else: ?>
                    <button type="button" class="btn btn-sm btn-outline-info" style="padding:2px 6px;font-size:11px;" title="Buat & Kirim Surat Rujukan BPJS" onclick='bukaModalRujukan(<?= $data_json ?>)'>
                      <i class="fas fa-share-nodes"></i> Buat Rujukan
                    </button>
                  <?php endif; ?>
                </div>
              </div>
            <?php else: ?>
              <span style="font-size:12px;color:#cbd5e1;font-weight:600;display:block;text-align:center;">-</span>
            <?php endif; ?>
          </td>
          <td style="text-align:right;padding:9px 14px;">
            <div style="display:inline-flex;align-items:center;gap:4px;">
              <a href="<?= BASE_URL ?>modules/rekam_medis/periksa.php?no_rawat=<?= urlencode($k['no_rawat']) ?>"
                 class="btn btn-sm <?= $k['stts']==='Sudah' ? 'btn-outline-primary' : 'btn-primary' ?>"
                 style="padding:5px 10px;font-size:11.5px;display:inline-flex;align-items:center;gap:4px;" title="Periksa Pasien">
                <i class="fas fa-stethoscope"></i> <?= $k['stts']==='Sudah' ? 'EMR' : 'Periksa' ?>
              </a>
              <button type="button" class="btn btn-sm btn-secondary" style="padding:5px 8px;font-size:11.5px;color:#0f766e;" title="Edit Pendaftaran"
                      onclick="openFormEdit('<?= htmlspecialchars($k['no_rawat']) ?>')">
                <i class="fas fa-edit"></i>
              </button>
              <a href="<?= BASE_URL ?>modules/pasien/detail.php?rm=<?= urlencode($k['no_rkm_medis']) ?>"
                 class="btn btn-sm btn-secondary" style="padding:5px 8px;font-size:11.5px;" title="Profil Pasien">
                <i class="fas fa-user"></i>
              </a>
              <?php if ($k['stts'] !== 'Batal' && $k['stts'] !== 'Sudah'): ?>
                <button type="button" class="btn btn-sm btn-outline" style="padding:5px 8px;font-size:11.5px;color:#ef4444;border-color:#fca5a5;" title="Batalkan Kunjungan"
                        onclick="ubahStatus('<?= $k['no_rawat'] ?>', 'batal')">
                  <i class="fas fa-times"></i>
                </button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    <?php endif;
    $html = ob_get_clean();
    echo json_encode(['success' => true, 'count' => $total_rows, 'html' => $html]);
    exit;
}

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<style>
.collapsible-form-wrapper {
  max-height: 0;
  opacity: 0;
  overflow: hidden;
  transform: translateY(-10px);
  transition: max-height 0.45s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.35s ease, transform 0.35s ease, margin-bottom 0.35s ease;
  margin-bottom: 0;
}
.collapsible-form-wrapper.is-expanded {
  max-height: 950px;
  opacity: 1;
  transform: translateY(0);
  margin-bottom: 16px;
}
</style>

<!-- ─── Page Header (Modern Medical Style) ──────────────── -->
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:14px;">
  <div>
    <h1 class="page-title" style="font-size:20px;font-weight:800;color:#0f2b2b;margin-bottom:2px;">Pendaftaran Rawat Jalan & Antrian</h1>
    <p class="page-subtitle" style="font-size:12px;margin:0;color:#64748b;">Kelola pendaftaran kunjungan harian pasien &mdash; <?= tgl_indo($tgl, true) ?></p>
  </div>
  <div class="page-actions" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
    <a href="<?= BASE_URL ?>modules/tarif_ralan/index.php" class="btn btn-outline-pill">
      <i class="fas fa-info-circle"></i> HARGA & TARIF
    </a>
    <a href="<?= BASE_URL ?>modules/kasir/index.php" class="btn btn-outline-pill">
      <i class="fas fa-edit"></i> BIAYA PERAWATAN
    </a>
    <button type="button" id="btnToggleForm" class="btn btn-primary btn-pill" onclick="toggleFormPendaftaran()">
      <i class="fas fa-plus-circle" id="toggleIcon"></i> <span id="toggleText">BUAT PENDAFTARAN</span>
    </button>
  </div>
</div>

<!-- ─── Medical Sub Tabs ──────────────────────────────────── -->
<div class="nav-tabs-medical">
  <a href="<?= BASE_URL ?>modules/pendaftaran/index.php" class="active"><i class="fas fa-user-injured" style="margin-right:4px;"></i> Pasien Hari Ini</a>
  <a href="<?= BASE_URL ?>modules/rekam_medis/index.php"><i class="fas fa-stethoscope" style="margin-right:4px;"></i> Rawat Jalan (E-RM)</a>
  <a href="<?= BASE_URL ?>modules/kasir/index.php"><i class="fas fa-receipt" style="margin-right:4px;"></i> Kasir & Pembayaran</a>
</div>

<!-- ─── Expandable / Collapsible Form Pendaftaran Pasien ──── -->
<div id="pendaftaranFormContainer" class="collapsible-form-wrapper">
  <div class="card" style="border-top:4px solid var(--primary-600);box-shadow:0 6px 18px rgba(0,0,0,0.06);margin-bottom:0;">
    
    <div class="card-header" style="padding:10px 18px;background:#f8fafc;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #e2e8f0;">
      <div class="card-title" style="font-size:13.5px;display:flex;align-items:center;gap:8px;margin:0;">
        <i class="fas fa-calendar-plus text-primary"></i>
        <span id="formHeaderTitle">Formulir Pendaftaran Pasien</span>
        <span id="formModeBadge" class="badge badge-primary" style="font-size:10.5px;padding:2px 8px;">Pendaftaran Baru</span>
      </div>
      <button type="button" class="btn btn-sm btn-secondary" style="padding:3px 8px;font-size:12px;" onclick="closeFormPendaftaran()">
        <i class="fas fa-times"></i> Tutup
      </button>
    </div>

    <div class="card-body" style="padding:18px 24px;">
      <form id="formPendaftaranInline" onsubmit="submitPendaftaranForm(event)">
        <input type="hidden" name="action" value="simpan_pendaftaran">
        <input type="hidden" name="form_mode" id="formMode" value="tambah">
        <input type="hidden" name="no_rkm_medis" id="formNoRm" value="">

        <div style="max-width:760px;margin:0 auto;display:flex;flex-direction:column;gap:12px;">

          <!-- Baris 1: Tanggal & Jam -->
          <div style="display:grid;grid-template-columns:110px 1fr 60px 1fr;gap:12px;align-items:center;">
            <label style="font-size:13px;font-weight:700;color:#334155;text-align:right;">Tanggal</label>
            <input type="date" name="tgl_registrasi" id="formTglReg" class="form-control"
                   value="<?= date('Y-m-d') ?>" style="height:36px;font-size:13px;" onchange="onDateOrPoliChange()">

            <label style="font-size:13px;font-weight:700;color:#334155;text-align:right;">Jam</label>
            <input type="text" name="jam_reg" id="formJamReg" class="form-control"
                   value="<?= date('H:i:s') ?>" style="height:36px;font-size:13px;font-family:monospace;">
          </div>

          <!-- Baris 2: Pasien -->
          <div style="display:grid;grid-template-columns:110px 1fr;gap:12px;align-items:start;">
            <label style="font-size:13px;font-weight:700;color:#334155;text-align:right;margin-top:8px;">Pasien <span style="color:#ef4444;">*</span></label>
            <div style="position:relative;">
              
              <!-- Input Search Pasien -->
              <input type="text" id="formSearchPasien" class="form-control" style="height:36px;font-size:13px;"
                     placeholder="Cari nama pasien / nomor rekam medik / NIK..." autocomplete="off"
                     oninput="onSearchPasien(this.value)">

              <!-- Selected Pasien Badge -->
              <div id="formSelectedPasienPill" style="display:none;align-items:center;justify-content:space-between;background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;padding:6px 12px;font-size:12.5px;">
                <div style="display:flex;align-items:center;gap:8px;">
                  <i class="fas fa-user-check" style="color:#2563eb;"></i>
                  <strong id="pillNamaPasien" style="color:#1e3a8a;"></strong>
                  <span id="pillRmPasien" style="color:#3b82f6;font-size:11.5px;"></span>
                  <span id="pillUmurPasien" style="color:#64748b;font-size:11.5px;"></span>
                </div>
                <button type="button" class="btn btn-sm btn-outline" style="padding:2px 6px;font-size:11px;color:#ef4444;border-color:#fca5a5;" onclick="clearSelectedPasien()">
                  <i class="fas fa-times"></i> Ganti
                </button>
              </div>

              <!-- Autocomplete Results Dropdown -->
              <div id="pasienDropdownResults" style="display:none;position:absolute;top:100%;left:0;right:0;background:#ffffff;border:1px solid #cbd5e1;border-radius:8px;box-shadow:0 8px 20px rgba(0,0,0,0.12);max-height:220px;overflow-y:auto;z-index:999;margin-top:3px;"></div>
            </div>
          </div>

          <!-- Baris 3: Poliklinik -->
          <div style="display:grid;grid-template-columns:110px 1fr;gap:12px;align-items:center;">
            <label style="font-size:13px;font-weight:700;color:#334155;text-align:right;">Poliklinik <span style="color:#ef4444;">*</span></label>
            <select name="kd_poli" id="formSelectPoli" class="form-control" style="height:36px;font-size:13px;" required onchange="onPoliChange(this.value)">
              <option value="">— Pilih Poliklinik —</option>
              <?php foreach ($poli_list as $pl): ?>
                <option value="<?= $pl['kd_poli'] ?>"><?= htmlspecialchars($pl['nm_poli']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Baris 4: Dokter -->
          <div style="display:grid;grid-template-columns:110px 1fr;gap:12px;align-items:center;">
            <label style="font-size:13px;font-weight:700;color:#334155;text-align:right;">Dokter <span style="color:#ef4444;">*</span></label>
            <select name="kd_dokter" id="formSelectDokter" class="form-control" style="height:36px;font-size:13px;" required>
              <option value="">— Pilih Dokter —</option>
            </select>
          </div>

          <!-- Baris 5: Penjamin -->
          <div style="display:grid;grid-template-columns:110px 1fr;gap:12px;align-items:center;">
            <label style="font-size:13px;font-weight:700;color:#334155;text-align:right;">Penjamin <span style="color:#ef4444;">*</span></label>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
              <select name="kd_pj" id="formSelectPj" class="form-control" style="height:36px;font-size:13px;" required>
                <?php foreach ($penjab_list as $pj): ?>
                  <option value="<?= $pj['kd_pj'] ?>"><?= htmlspecialchars($pj['nm_penjab']) ?></option>
                <?php endforeach; ?>
              </select>
              <input type="text" name="no_peserta" id="formNoPeserta" class="form-control" style="height:36px;font-size:13px;" placeholder="No. Kartu BPJS / Asuransi">
            </div>
          </div>

          <!-- Baris 6: No. Rawat & No. Reg -->
          <div style="display:grid;grid-template-columns:110px 1fr 60px 1fr;gap:12px;align-items:center;">
            <label style="font-size:13px;font-weight:700;color:#334155;text-align:right;">No. Rawat</label>
            <input type="text" name="no_rawat" id="formNoRawat" class="form-control"
                   value="" style="height:36px;font-size:13px;font-family:monospace;background:#f8fafc;" readonly>

            <label style="font-size:13px;font-weight:700;color:#334155;text-align:right;">No. Reg</label>
            <input type="text" name="no_reg" id="formNoReg" class="form-control"
                   value="001" style="height:36px;font-size:13px;font-weight:700;color:var(--primary-700);font-family:monospace;">
          </div>

          <!-- Action Buttons -->
          <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:8px;padding-top:10px;border-top:1px solid #f1f5f9;">
            <button type="button" class="btn btn-secondary" style="padding:8px 18px;font-size:12.5px;" onclick="closeFormPendaftaran()">
              Batal / Tutup
            </button>
            <button type="submit" id="btnSubmitForm" class="btn btn-primary" style="padding:8px 24px;font-size:12.5px;font-weight:700;">
              <i class="fas fa-save"></i> Simpan Pendaftaran
            </button>
          </div>

        </div>
      </form>
    </div>

  </div>
</div>

<!-- ─── Compact Filter Toolbar ───────────────────────────── -->
<div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:10px;padding:8px 14px;margin-bottom:12px;box-shadow:0 1px 2px rgba(0,0,0,0.03);">
  <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
    
    <div style="display:flex;align-items:center;gap:6px;">
      <label style="font-size:11.5px;color:#64748b;font-weight:600;white-space:nowrap;">Tanggal:</label>
      <input type="date" name="tgl" class="form-control" style="width:135px;padding:4px 8px;font-size:12px;height:32px;"
             value="<?= $tgl ?>" max="<?= date('Y-m-d') ?>">
    </div>

    <div style="display:flex;align-items:center;gap:6px;">
      <label style="font-size:11.5px;color:#64748b;font-weight:600;white-space:nowrap;">Poli:</label>
      <select name="kd_poli" class="form-control" style="width:135px;padding:4px 8px;font-size:12px;height:32px;">
        <option value="">— Semua Poli —</option>
        <?php foreach ($poli_list as $pl): ?>
          <option value="<?= $pl['kd_poli'] ?>" <?= $kd_poli===$pl['kd_poli'] ? 'selected':'' ?>>
            <?= htmlspecialchars($pl['nm_poli']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div style="display:flex;align-items:center;gap:6px;">
      <label style="font-size:11.5px;color:#64748b;font-weight:600;white-space:nowrap;">Dokter:</label>
      <select name="kd_dokter" class="form-control" style="width:150px;padding:4px 8px;font-size:12px;height:32px;">
        <option value="">— Semua Dokter —</option>
        <?php foreach ($dokter_list as $dl): ?>
          <option value="<?= $dl['kd_dokter'] ?>" <?= $kd_dokter===$dl['kd_dokter'] ? 'selected':'' ?>>
            <?= htmlspecialchars($dl['nm_dokter']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div style="display:flex;align-items:center;gap:6px;">
      <label style="font-size:11.5px;color:#64748b;font-weight:600;white-space:nowrap;">Status:</label>
      <select name="status" class="form-control" style="width:105px;padding:4px 8px;font-size:12px;height:32px;">
        <option value="">— Semua —</option>
        <option value="Belum"  <?= $status==='Belum'  ? 'selected':'' ?>>Menunggu</option>
        <option value="Sudah"  <?= $status==='Sudah'  ? 'selected':'' ?>>Selesai</option>
        <option value="Batal"  <?= $status==='Batal'  ? 'selected':'' ?>>Batal</option>
      </select>
    </div>

    <div style="display:flex;align-items:center;gap:6px;">
      <label style="font-size:11.5px;color:#64748b;font-weight:600;white-space:nowrap;">Urutkan:</label>
      <select name="sort_by" class="form-control" style="width:160px;padding:4px 8px;font-size:12px;height:32px;font-weight:600;color:var(--primary-700);">
        <option value="dokter_noreg" <?= $sort_by==='dokter_noreg' ? 'selected':'' ?>>👨‍⚕️ Dokter & No. Antrian</option>
        <option value="noreg"        <?= $sort_by==='noreg' ? 'selected':'' ?>>🔢 No. Antrian Saja</option>
        <option value="belum_jam"    <?= $sort_by==='belum_jam' ? 'selected':'' ?>>⏳ Menunggu & Jam</option>
        <option value="jam_asc"      <?= $sort_by==='jam_asc' ? 'selected':'' ?>>🕒 Jam Masuk (Terlama)</option>
        <option value="jam_desc"     <?= $sort_by==='jam_desc' ? 'selected':'' ?>>🕒 Jam Masuk (Terbaru)</option>
        <option value="nama_pasien"  <?= $sort_by==='nama_pasien' ? 'selected':'' ?>>🔤 Nama Pasien (A-Z)</option>
      </select>
    </div>

    <div style="flex:1;min-width:160px;max-width:240px;">
      <input type="text" name="q" class="form-control" style="padding:4px 10px;font-size:12px;height:32px;"
             placeholder="Cari Pasien / No. RM..." value="<?= htmlspecialchars($search) ?>">
    </div>

    <button type="submit" class="btn btn-primary btn-sm" style="height:32px;padding:4px 12px;font-size:12px;"><i class="fas fa-filter"></i> Filter</button>
    
    <?php if ($search || $kd_poli || $kd_dokter || $status || $sort_by !== 'dokter_noreg' || $tgl !== $today): ?>
      <a href="<?= BASE_URL ?>modules/pendaftaran/index.php" class="btn btn-secondary btn-sm" style="height:32px;padding:4px 10px;font-size:12px;"><i class="fas fa-times"></i> Reset</a>
    <?php endif; ?>

    <div style="margin-left:auto;display:flex;align-items:center;gap:6px;">
      <span class="badge-online" style="font-size:10px;font-weight:600;padding:2px 8px;">
        <i class="fas fa-circle"></i> Live Sync Aktif
      </span>
    </div>

  </form>
</div>

<!-- ─── Tabel Antrian Pasien ─────────────────────────────── -->
<div class="card" style="margin-bottom:0;">
  <div class="card-header" style="padding:10px 16px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;">
    <div class="card-title" style="font-size:13px;display:flex;align-items:center;gap:6px;margin:0;">
      <i class="fas fa-clipboard-list text-primary"></i> 
      <span>Daftar Antrian Kunjungan Pasien</span>
    </div>
    <span style="font-size:12px;color:#64748b;" id="totalDaftarText">
      Total: <strong><?= count($list) ?></strong> pasien
    </span>
  </div>

  <div class="card-body" style="padding:0;">
    <div class="table-responsive">
      <table class="table table-hover mb-0" style="font-size:12.5px;">
        <thead>
          <tr>
            <th style="width:50px;text-align:center;">Antrian</th>
            <th>Pasien & No. RM</th>
            <th>Poliklinik / Dokter</th>
            <th>Penjamin</th>
            <th style="width:90px;">Jam</th>
            <th style="width:95px;">Status</th>
            <th style="width:130px;">Bridging PCare</th>
            <th style="width:140px;text-align:right;">Aksi</th>
          </tr>
        </thead>
        <tbody id="pendaftaranTableBody">
          <?php if (empty($list)): ?>
            <tr>
              <td colspan="8" style="text-align:center;padding:36px;color:#94a3b8;">
                <i class="fas fa-clipboard-user" style="font-size:28px;color:#cbd5e1;display:block;margin-bottom:8px;"></i>
                Belum ada pasien yang terdaftar pada filter ini.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($list as $i => $k): 
              $is_bpjs = ($k['kd_pj'] === 'BPJ' || str_contains(strtolower($k['nm_penjab'] ?? ''), 'bpjs'));
              $has_rujukan = !empty($k['no_rujukan_sub']) || !empty($k['no_rujukan_khusus']);
              $has_kunj_pcare = !empty($k['no_kunjungan_pcare']) || $has_rujukan;
              $no_rujukan = $k['no_rujukan_sub'] ?: ($k['no_rujukan_khusus'] ?: '');
              $data_json = htmlspecialchars(json_encode($k), ENT_QUOTES, 'UTF-8');
            ?>
              <tr id="row-<?= htmlspecialchars($k['no_rawat']) ?>">
                <td style="text-align:center;font-weight:700;color:var(--primary-700);font-size:13px;padding:9px 10px;">
                  <?= htmlspecialchars($k['no_reg'] ?: sprintf('%03d', $i+1)) ?>
                </td>
                <td style="padding:9px 12px;">
                  <div style="display:flex;align-items:center;gap:10px;">
                    <span style="width:8px;height:8px;border-radius:50%;background:#f59e0b;box-shadow:0 0 0 2px #fef3c7;display:inline-block;flex-shrink:0;"></span>
                    <div>
                      <div style="font-size:13px;font-weight:700;color:#0f172a;line-height:1.2;"><?= htmlspecialchars($k['nm_pasien']) ?> <?= icon_jk($k['jk']) ?></div>
                      <div style="font-size:11px;color:#64748b;margin-top:3px;">
                        MR: <strong style="color:#0f766e;"><?= $k['no_rkm_medis'] ?></strong> &bull; <?= hitung_umur($k['tgl_lahir']) ?>
                      </div>
                    </div>
                  </div>
                </td>
                <td style="padding:9px 12px;">
                  <div style="font-size:12.5px;font-weight:600;color:#0f172a;"><?= htmlspecialchars($k['nm_poli'] ?: '-') ?></div>
                  <div style="font-size:11px;color:#64748b;margin-top:2px;"><?= htmlspecialchars($k['nm_dokter'] ?: '-') ?></div>
                </td>
                <td style="padding:9px 12px;">
                  <?= badge_penjab($k['nm_penjab'] ?? 'Umum', $is_bpjs ? ($k['no_peserta'] ?? null) : null) ?>
                </td>
                <td style="font-size:11.5px;color:#475569;font-weight:600;padding:9px 10px;">
                  <i class="fas fa-clock" style="font-size:10px;color:#94a3b8;margin-right:2px;"></i> <?= substr($k['jam_reg'],0,5) ?>
                </td>
                <td style="padding:9px 10px;"><?= badge_status($k['stts']) ?></td>
                <td style="padding:9px 10px;">
                  <?php if ($is_bpjs): ?>
                    <div style="display:flex;flex-direction:column;gap:4px;">
                      <div style="display:flex;align-items:center;gap:4px;">
                        <?php if ($has_kunj_pcare): ?>
                          <span style="font-size:10.5px;font-weight:700;color:#047857;background:#d1fae5;padding:2px 6px;border-radius:4px;display:inline-flex;align-items:center;gap:3px;">
                            <i class="fas fa-check-circle"></i> Terkirim
                          </span>
                        <?php else: ?>
                          <button type="button" class="btn btn-sm btn-outline-primary" style="padding:2px 6px;font-size:11px;" title="Kirim Kunjungan ke PCare BPJS" onclick="kirimKunjunganPcareRow('<?= htmlspecialchars($k['no_rawat']) ?>')">
                            <i class="fas fa-cloud-arrow-up"></i> Kirim PCare
                          </button>
                        <?php endif; ?>
                      </div>
                      <div style="display:flex;align-items:center;gap:4px;">
                        <?php if ($has_rujukan): ?>
                          <a href="<?= BASE_URL ?>modules/pcare/cetak_rujukan.php?no_rawat=<?= urlencode($k['no_rawat']) ?>" target="_blank" class="btn btn-sm btn-outline-success" style="padding:2px 6px;font-size:11px;" title="Cetak Surat Rujukan BPJS">
                            <i class="fas fa-print"></i> Cetak Rujuk
                          </a>
                        <?php else: ?>
                          <button type="button" class="btn btn-sm btn-outline-info" style="padding:2px 6px;font-size:11px;" title="Buat & Kirim Surat Rujukan BPJS" onclick='bukaModalRujukan(<?= $data_json ?>)'>
                            <i class="fas fa-share-nodes"></i> Buat Rujukan
                          </button>
                        <?php endif; ?>
                      </div>
                    </div>
                  <?php else: ?>
                    <span style="font-size:12px;color:#cbd5e1;font-weight:600;display:block;text-align:center;">-</span>
                  <?php endif; ?>
                </td>
                <td style="text-align:right;padding:9px 14px;">
                  <div style="display:inline-flex;align-items:center;gap:4px;">
                    <a href="<?= BASE_URL ?>modules/rekam_medis/periksa.php?no_rawat=<?= urlencode($k['no_rawat']) ?>"
                       class="btn btn-sm <?= $k['stts']==='Sudah' ? 'btn-outline-primary' : 'btn-primary' ?>"
                       style="padding:5px 10px;font-size:11.5px;display:inline-flex;align-items:center;gap:4px;" title="Periksa Pasien">
                      <i class="fas fa-stethoscope"></i> <?= $k['stts']==='Sudah' ? 'EMR' : 'Periksa' ?>
                    </a>
                    <button type="button" class="btn btn-sm btn-secondary" style="padding:5px 8px;font-size:11.5px;color:#0f766e;" title="Edit Pendaftaran"
                            onclick="openFormEdit('<?= htmlspecialchars($k['no_rawat']) ?>')">
                      <i class="fas fa-edit"></i>
                    </button>
                    <a href="<?= BASE_URL ?>modules/pasien/detail.php?rm=<?= urlencode($k['no_rkm_medis']) ?>"
                       class="btn btn-sm btn-secondary" style="padding:5px 8px;font-size:11.5px;" title="Profil Pasien">
                      <i class="fas fa-user"></i>
                    </a>
                    <?php if ($k['stts'] !== 'Batal' && $k['stts'] !== 'Sudah'): ?>
                      <button type="button" class="btn btn-sm btn-outline" style="padding:5px 8px;font-size:11.5px;color:#ef4444;border-color:#fca5a5;" title="Batalkan Kunjungan"
                              onclick="ubahStatus('<?= $k['no_rawat'] ?>', 'batal')">
                        <i class="fas fa-times"></i>
                      </button>
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

<script>
// ─── State Form Expand / Collapse ─────────────────────────────
let isFormOpen = false;
let searchTimer = null;

function toggleFormPendaftaran() {
  if (isFormOpen) {
    closeFormPendaftaran();
  } else {
    openFormTambah();
  }
}

function openFormTambah() {
  isFormOpen = true;
  const container = document.getElementById('pendaftaranFormContainer');
  const btnText = document.getElementById('toggleText');
  const btnIcon = document.getElementById('toggleIcon');
  const badge = document.getElementById('formModeBadge');
  const title = document.getElementById('formHeaderTitle');
  const submitBtn = document.getElementById('btnSubmitForm');

  document.getElementById('formMode').value = 'tambah';
  title.innerText = 'Formulir Pendaftaran Pasien';
  badge.className = 'badge badge-primary';
  badge.innerText = 'Pendaftaran Baru';
  submitBtn.innerHTML = '<i class="fas fa-save"></i> Simpan Pendaftaran';

  // Reset fields
  clearSelectedPasien();
  document.getElementById('formTglReg').value = '<?= date('Y-m-d') ?>';
  document.getElementById('formJamReg').value = new Date().toTimeString().split(' ')[0];
  document.getElementById('formSelectPoli').value = '';
  document.getElementById('formSelectDokter').innerHTML = '<option value="">— Pilih Poliklinik Dulu —</option>';
  document.getElementById('formNoPeserta').value = '';

  // Get auto new No Rawat & No Reg
  fetchNewNoreg();

  container.classList.add('is-expanded');
  btnText.innerText = 'Tutup Form';
  btnIcon.className = 'fas fa-chevron-up';

  setTimeout(() => {
    document.getElementById('formSearchPasien').focus();
    container.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }, 120);
}

function openFormEdit(noRawat) {
  fetch(`<?= BASE_URL ?>modules/pendaftaran/index.php?action=get_detail&no_rawat=${encodeURIComponent(noRawat)}`)
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        const d = res.data;
        isFormOpen = true;
        const container = document.getElementById('pendaftaranFormContainer');
        const btnText = document.getElementById('toggleText');
        const btnIcon = document.getElementById('toggleIcon');
        const badge = document.getElementById('formModeBadge');
        const title = document.getElementById('formHeaderTitle');
        const submitBtn = document.getElementById('btnSubmitForm');

        document.getElementById('formMode').value = 'edit';
        title.innerText = 'Edit Data Pendaftaran Pasien';
        badge.className = 'badge badge-warning';
        badge.innerText = 'Mode Edit';
        submitBtn.innerHTML = '<i class="fas fa-save"></i> Simpan Perubahan';

        // Populate fields
        document.getElementById('formNoRawat').value = d.no_rawat;
        document.getElementById('formNoReg').value = d.no_reg;
        document.getElementById('formTglReg').value = d.tgl_registrasi;
        document.getElementById('formJamReg').value = d.jam_reg;
        document.getElementById('formSelectPoli').value = d.kd_poli;
        document.getElementById('formSelectPj').value = d.kd_pj;
        document.getElementById('formNoPeserta').value = d.no_peserta || '';

        // Select Pasien Pill
        selectPasienItem({
          no_rkm_medis: d.no_rkm_medis,
          nm_pasien: d.nm_pasien,
          umur: d.umur || '',
          kd_pj: d.kd_pj,
          no_peserta: d.no_peserta
        });

        // Load doctors for this poli and select existing doctor
        fetchDokterByPoli(d.kd_poli, d.kd_dokter);

        container.classList.add('is-expanded');
        btnText.innerText = 'Tutup Form';
        btnIcon.className = 'fas fa-chevron-up';

        setTimeout(() => {
          container.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 120);
      } else {
        alert(res.message || 'Gagal mengambil data pendaftaran');
      }
    });
}

function closeFormPendaftaran() {
  isFormOpen = false;
  const container = document.getElementById('pendaftaranFormContainer');
  const btnText = document.getElementById('toggleText');
  const btnIcon = document.getElementById('toggleIcon');

  container.classList.remove('is-expanded');
  btnText.innerText = 'Daftar Pasien';
  btnIcon.className = 'fas fa-plus';
}

function fetchNewNoreg() {
  const poli = document.getElementById('formSelectPoli').value;
  const tgl = document.getElementById('formTglReg').value;

  fetch(`<?= BASE_URL ?>modules/pendaftaran/index.php?action=get_new_noreg&kd_poli=${encodeURIComponent(poli)}&tgl=${encodeURIComponent(tgl)}`)
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        if (document.getElementById('formMode').value === 'tambah') {
          document.getElementById('formNoRawat').value = res.no_rawat;
          document.getElementById('formNoReg').value = res.no_reg;
          document.getElementById('formJamReg').value = res.jam;
        }
      }
    });
}

function onDateOrPoliChange() {
  if (document.getElementById('formMode').value === 'tambah') {
    fetchNewNoreg();
  }
}

function onPoliChange(kd_poli) {
  onDateOrPoliChange();
  fetchDokterByPoli(kd_poli, '');
}

function fetchDokterByPoli(kd_poli, selectedDokter) {
  const sel = document.getElementById('formSelectDokter');
  if (!kd_poli) {
    sel.innerHTML = '<option value="">— Pilih Poliklinik Dulu —</option>';
    return;
  }
  sel.innerHTML = '<option value="">Memuat dokter...</option>';

  fetch(`<?= BASE_URL ?>modules/pasien/ajax.php?action=dokter&kd_poli=${encodeURIComponent(kd_poli)}`, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(r => r.json())
  .then(data => {
    if (data.data && data.data.length > 0) {
      sel.innerHTML = '<option value="">— Pilih Dokter —</option>' +
        data.data.map(d => `<option value="${d.kd_dokter}" ${d.kd_dokter===selectedDokter?'selected':''}>${d.nm_dokter}${d.nm_spesialis?' ('+d.nm_spesialis+')':''}</option>`).join('');
    } else {
      sel.innerHTML = '<option value="">Tidak ada dokter di poli ini</option>';
    }
  });
}

// ─── Autocomplete Pasien ──────────────────────────────────────
function onSearchPasien(query) {
  clearTimeout(searchTimer);
  const dropdown = document.getElementById('pasienDropdownResults');
  if (!query || query.trim().length < 1) {
    dropdown.style.display = 'none';
    dropdown.innerHTML = '';
    return;
  }

  searchTimer = setTimeout(() => {
    fetch(`<?= BASE_URL ?>modules/pasien/ajax.php?action=cari&q=${encodeURIComponent(query)}`, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(res => {
      if (res.success && res.data && res.data.length > 0) {
        dropdown.innerHTML = res.data.map(p => `
          <div style="padding:8px 12px;border-bottom:1px solid #f1f5f9;cursor:pointer;display:flex;justify-content:space-between;align-items:center;"
               onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='#fff'"
               onclick='selectPasienItem(${JSON.stringify(p)})'>
            <div>
              <strong style="color:#0f172a;font-size:13px;">${p.nm_pasien}</strong>
              <div style="font-size:11px;color:#64748b;">No. RM: <b>${p.no_rkm_medis}</b> &bull; ${p.umur||''} &bull; ${p.no_ktp||'-'}</div>
            </div>
            <span class="badge badge-light" style="font-size:10.5px;">${p.nm_penjab||'Umum'}</span>
          </div>
        `).join('');
        dropdown.style.display = 'block';
      } else {
        dropdown.innerHTML = '<div style="padding:10px 14px;color:#94a3b8;font-size:12px;">Pasien tidak ditemukan.</div>';
        dropdown.style.display = 'block';
      }
    });
  }, 250);
}

function selectPasienItem(p) {
  document.getElementById('formNoRm').value = p.no_rkm_medis;
  document.getElementById('pillNamaPasien').innerText = p.nm_pasien;
  document.getElementById('pillRmPasien').innerText = `(RM: ${p.no_rkm_medis})`;
  document.getElementById('pillUmurPasien').innerText = p.umur ? `• ${p.umur}` : '';

  if (p.kd_pj) document.getElementById('formSelectPj').value = p.kd_pj;
  if (p.no_peserta) document.getElementById('formNoPeserta').value = p.no_peserta;

  document.getElementById('formSearchPasien').style.display = 'none';
  document.getElementById('formSelectedPasienPill').style.display = 'flex';
  document.getElementById('pasienDropdownResults').style.display = 'none';
}

function clearSelectedPasien() {
  document.getElementById('formNoRm').value = '';
  document.getElementById('formSearchPasien').value = '';
  document.getElementById('formSearchPasien').style.display = 'block';
  document.getElementById('formSelectedPasienPill').style.display = 'none';
  document.getElementById('pasienDropdownResults').style.display = 'none';
}

// ─── Submit Form via AJAX ─────────────────────────────────────
function submitPendaftaranForm(e) {
  e.preventDefault();
  const form = document.getElementById('formPendaftaranInline');
  const fd = new FormData(form);

  fetch('<?= BASE_URL ?>modules/pendaftaran/index.php', {
    method: 'POST',
    body: fd
  })
  .then(r => r.json())
  .then(res => {
    if (res.success) {
      showToast(res.message || 'Pendaftaran berhasil disimpan', 'success');
      closeFormPendaftaran();
      pollLivePendaftaran(); // Refresh antrian tabel langsung
    } else {
      alert(res.message || 'Gagal menyimpan pendaftaran');
    }
  })
  .catch(err => alert('Terjadi kesalahan jaringan: ' + err));
}

// ─── Real-Time Live Background Polling ────────────────────────
function pollLivePendaftaran() {
  const filterTgl = '<?= urlencode($tgl) ?>';
  const filterPoli = '<?= urlencode($kd_poli) ?>';
  const filterStatus = '<?= urlencode($status) ?>';
  const filterQ = '<?= urlencode($search) ?>';

  fetch(`<?= BASE_URL ?>modules/pendaftaran/index.php?action=get_live_pendaftaran&tgl=${filterTgl}&kd_poli=${filterPoli}&status=${filterStatus}&q=${filterQ}`, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(r => r.json())
  .then(res => {
    if (res.success) {
      const tbody = document.getElementById('pendaftaranTableBody');
      const countEl = document.getElementById('totalDaftarText');

      if (countEl) countEl.innerHTML = `Total: <strong>${res.count}</strong> pasien`;
      if (tbody) tbody.innerHTML = res.html;
    }
  })
  .catch(err => console.debug('Live sync pendaftaran:', err));
}

setInterval(pollLivePendaftaran, 8000);

// ─── Bridging PCare: Kirim Kunjungan Cepat ───────────────────
function kirimKunjunganPcareRow(no_rawat) {
  if (!confirm(`Kirim data kunjungan pasien No. Rawat ${no_rawat} ke PCare BPJS?`)) return;
  showToast('Mengirim kunjungan ke PCare BPJS...', 'info');

  const fd = new FormData();
  fd.append('action', 'kirim_kunjungan');
  fd.append('no_rawat', no_rawat);

  fetch('<?= BASE_URL ?>modules/pcare/ajax.php', {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    body: fd
  })
  .then(r => r.json())
  .then(res => {
    if (res.success) {
      showToast(res.message, 'success');
      pollLivePendaftaran();
    } else {
      showToast(res.message || 'Gagal kirim kunjungan ke PCare', 'danger');
    }
  })
  .catch(err => {
    showToast('Terjadi kesalahan jaringan saat bridging PCare: ' + err, 'danger');
  });
}

// ─── Modal Rujukan Logic ──────────────────────────────────────
function bukaModalRujukan(v) {
  const modal = document.getElementById('modalBuatRujukan');
  if (!modal) return;

  // Isi data identitas
  document.getElementById('rujukNoRawat').value     = v.no_rawat || '';
  document.getElementById('rujukNoRm').value        = v.no_rkm_medis || '';
  document.getElementById('rujukNmPasien').value    = v.nm_pasien || '';
  document.getElementById('rujukNoKartu').value     = v.no_peserta || v.no_ktp || '';
  document.getElementById('rujukTglDaftar').value   = v.tgl_registrasi || '<?= date('Y-m-d') ?>';
  document.getElementById('rujukKdPoli').value      = v.kd_poli || '001';
  document.getElementById('rujukKdDokter').value    = v.kd_dokter || '';

  // Pemeriksaan fisik & diagnosa
  document.getElementById('rujukKeluhan').value     = v.keluhan || '';
  document.getElementById('rujukKdDiag1').value     = v.kd_diag_utama || '';
  document.getElementById('rujukNmDiag1').value     = v.nm_diag_utama || '';
  document.getElementById('rujukSistole').value     = v.tensi ? (v.tensi.split('/')[0] || 120) : 120;
  document.getElementById('rujukDiastole').value    = v.tensi ? (v.tensi.split('/')[1] || 80) : 80;
  document.getElementById('rujukHeartRate').value   = v.nadi || 80;
  document.getElementById('rujukRespRate').value    = v.respirasi || 20;
  document.getElementById('rujukTinggi').value      = v.tinggi || 165;
  document.getElementById('rujukBerat').value       = v.berat || 60;

  // Reset RS Terpilih
  document.getElementById('rujukKdPPK').value = '';
  document.getElementById('rujukNmPPK').value = '';
  document.getElementById('boxFaskesTerpilih').style.display = 'none';
  document.getElementById('boxHasilFaskes').style.display = 'none';

  modal.style.display = 'flex';
  loadMasterRujukan();
}

function tutupModalRujukan() {
  const modal = document.getElementById('modalBuatRujukan');
  if (modal) modal.style.display = 'none';
}

function toggleJenisRujukan() {
  const jenis = document.getElementById('rujukJenis').value;
  const boxSp = document.getElementById('boxPilihSpesialis');
  const boxSub = document.getElementById('boxPilihSubspesialis');
  const boxKh = document.getElementById('boxPilihKhusus');
  const boxCatKh = document.getElementById('boxCatatanKhusus');

  if (jenis === 'khusus') {
    boxSp.style.display = 'none';
    boxSub.style.display = 'none';
    boxKh.style.display = 'block';
    boxCatKh.style.display = 'block';
    loadKhususDropdown();
  } else {
    boxSp.style.display = 'block';
    boxSub.style.display = 'block';
    boxKh.style.display = 'none';
    boxCatKh.style.display = 'none';
  }
}

function onTaccChange() {
  const tacc = document.getElementById('rujukTacc');
  const opt = tacc.options[tacc.selectedIndex];
  document.getElementById('rujukNmTacc').value = opt ? opt.text : '';
}

let cachedSpesialis = [];
let cachedKhusus = [];

function loadMasterRujukan() {
  fetch(`<?= BASE_URL ?>modules/pcare/ajax.php?action=get_spesialis`, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(r => r.json())
  .then(res => {
    const list = res.data?.response?.list || [];
    cachedSpesialis = list;
    const sel = document.getElementById('rujukSpesialis');
    sel.innerHTML = '';
    list.forEach((s, idx) => {
      const selected = (s.kdSpesialis === 'INT' || idx === 0) ? 'selected' : '';
      sel.innerHTML += `<option value="${s.kdSpesialis}" ${selected}>${s.nmSpesialis}</option>`;
    });

    onSpesialisChange();
  })
  .catch(() => {
    onSpesialisChange();
  });
}

function loadKhususDropdown() {
  if (cachedKhusus.length > 0) return;
  fetch(`<?= BASE_URL ?>modules/pcare/ajax.php?action=get_khusus`, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(r => r.json())
  .then(res => {
    const list = res.data?.response?.list || [];
    cachedKhusus = list;
    const sel = document.getElementById('rujukKhusus');
    sel.innerHTML = '';
    list.forEach(k => {
      sel.innerHTML += `<option value="${k.kdKhusus}">${k.nmKhusus}</option>`;
    });
  });
}

function onSpesialisChange() {
  const kdSp = document.getElementById('rujukSpesialis').value || 'INT';
  const selSub = document.getElementById('rujukSubSpesialis');
  selSub.innerHTML = '<option value="">Memuat Subspesialis...</option>';

  fetch(`<?= BASE_URL ?>modules/pcare/ajax.php?action=get_subspesialis&kd_spesialis=${encodeURIComponent(kdSp)}`, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(r => r.json())
  .then(res => {
    const list = res.data?.response?.list || [];
    selSub.innerHTML = '';
    if (list.length === 0) {
      selSub.innerHTML = `<option value="${kdSp}">${kdSp} (Umum)</option>`;
    } else {
      list.forEach(sub => {
        selSub.innerHTML += `<option value="${sub.kdSubSpesialis}">${sub.nmSubSpesialis} [${sub.kdSubSpesialis}]</option>`;
      });
    }
  })
  .catch(() => {
    selSub.innerHTML = `<option value="${kdSp}">${kdSp} (Umum)</option>`;
  });
}

// ─── Inline ICD-10 Live Search Autocomplete Inside Modal ───────
let diagDebounceTimer = null;

function onDiagInputLive(val) {
  clearTimeout(diagDebounceTimer);
  const q = val.trim();
  if (q.length < 2) {
    document.getElementById('modalDiagDropdown').style.display = 'none';
    return;
  }

  diagDebounceTimer = setTimeout(() => {
    lakukanPencarianDiagnosaModal(q);
  }, 250);
}

function toggleDiagDropdown() {
  const q = document.getElementById('rujukKdDiag1').value.trim();
  if (!q) {
    showToast('Ketik kode atau nama diagnosa pada kolom input', 'info');
    document.getElementById('rujukKdDiag1').focus();
    return;
  }
  lakukanPencarianDiagnosaModal(q);
}

function lakukanPencarianDiagnosaModal(q) {
  const dropdown = document.getElementById('modalDiagDropdown');
  const container = document.getElementById('modalDiagResults');
  
  dropdown.style.display = 'block';
  container.innerHTML = '<div style="text-align:center;padding:10px;color:#0284c7;"><i class="fas fa-spinner fa-spin"></i> Mencari diagnosa ICD-10 di BPJS &amp; Database...</div>';

  fetch(`<?= BASE_URL ?>modules/pcare/ajax.php?action=cari_diagnosa&q=${encodeURIComponent(q)}`, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(r => r.json())
  .then(res => {
    const list = res.data?.response?.list || [];
    container.innerHTML = '';

    if (list.length === 0) {
      container.innerHTML = '<div style="text-align:center;padding:12px;color:#94a3b8;font-size:12px;">Diagnosa tidak ditemukan. Coba ketik kode atau nama lainnya.</div>';
      return;
    }

    list.forEach(item => {
      const row = document.createElement('div');
      row.style.cssText = 'display:flex;align-items:center;justify-content:space-between;padding:8px 10px;border-radius:6px;cursor:pointer;border-bottom:1px solid #f1f5f9;transition:all 0.15s;';
      row.onmouseenter = () => { row.style.background = '#f0f9ff'; };
      row.onmouseleave = () => { row.style.background = 'transparent'; };
      
      const badgeSource = item.source === 'pcare' ? '<span style="font-size:9px;background:#d1fae5;color:#065f46;padding:1px 5px;border-radius:3px;font-weight:700;">BPJS</span>' : '<span style="font-size:9px;background:#e0f2fe;color:#0369a1;padding:1px 5px;border-radius:3px;font-weight:700;">LOKAL</span>';

      row.innerHTML = `
        <div style="display:flex;align-items:center;gap:8px;">
          <span style="font-family:monospace;font-weight:800;color:#0284c7;background:#e0f2fe;padding:2px 6px;border-radius:4px;font-size:12px;">${item.kdDiag}</span>
          <span style="font-size:12.5px;font-weight:600;color:#1e293b;">${item.nmDiag}</span>
        </div>
        <div>${badgeSource}</div>
      `;

      row.onclick = () => pilihDiag(item.kdDiag, item.nmDiag);
      container.appendChild(row);
    });
  })
  .catch(() => {
    container.innerHTML = '<div style="text-align:center;padding:10px;color:#ef4444;">Gagal memuat hasil pencarian</div>';
  });
}

function pilihDiag(kd, nm) {
  document.getElementById('rujukKdDiag1').value = kd;
  document.getElementById('rujukNmDiag1').value = nm;
  document.getElementById('modalDiagDropdown').style.display = 'none';
  showToast(`Diagnosa dipilih: [${kd}] ${nm}`, 'info');
}

function cariFaskesRujukan(btn) {
  const jenis = document.getElementById('rujukJenis').value;
  let kdSub = document.getElementById('rujukSubSpesialis').value;
  const kdKh = document.getElementById('rujukKhusus').value;
  const kdSar = document.getElementById('rujukSarana').value || '1';
  const tglRujuk = document.getElementById('rujukTglEst').value || '<?= date('Y-m-d') ?>';

  if (jenis === 'subspesialis' && !kdSub) {
    const kdSp = document.getElementById('rujukSpesialis').value;
    if (kdSp) {
      kdSub = kdSp;
    } else {
      showToast('Pilih Poli Spesialis / Subspesialis terlebih dahulu', 'warning');
      return;
    }
  }
  if (jenis === 'khusus' && !kdKh) {
    showToast('Pilih Kasus Khusus terlebih dahulu', 'warning');
    return;
  }

  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mencari RS...';
  }

  const url = `<?= BASE_URL ?>modules/pcare/ajax.php?action=cari_faskes_rujukan&jenis_rujukan=${jenis}&kd_subspesialis=${encodeURIComponent(kdSub || '')}&kd_khusus=${encodeURIComponent(kdKh || '')}&kd_sarana=${encodeURIComponent(kdSar)}&tgl_rujuk=${encodeURIComponent(tglRujuk)}`;

  fetch(url, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(r => r.json())
  .then(res => {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-search-location"></i> Cari Faskes / RS Rujukan Realtime';
    }

    const box = document.getElementById('boxHasilFaskes');
    const container = document.getElementById('listFaskesCards');
    container.innerHTML = '';

    const list = res.list || [];
    if (!res.success || list.length === 0) {
      showToast(res.message || 'Tidak ada faskes rujukan yang tersedia pada tanggal ini.', 'warning');
      box.style.display = 'none';
      return;
    }

    list.forEach(f => {
      const card = document.createElement('div');
      card.style.cssText = 'background:#ffffff;border:1.5px solid #cbd5e1;border-radius:10px;padding:12px;cursor:pointer;transition:all 0.2s;';
      card.onmouseenter = () => { card.style.borderColor = '#0284c7'; card.style.boxShadow = '0 4px 12px rgba(2,132,199,0.15)'; };
      card.onmouseleave = () => { card.style.borderColor = '#cbd5e1'; card.style.boxShadow = 'none'; };
      
      const persentase = f.persentase || '0%';
      const jadwal = f.jadwal || 'Tersedia';

      card.innerHTML = `
        <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:6px;">
          <strong style="font-size:13px;color:#0f172a;display:block;">${f.nmppk}</strong>
          <span style="font-size:10px;font-weight:800;background:#e0f2fe;color:#0284c7;padding:2px 6px;border-radius:4px;">${f.kelas || 'RS'}</span>
        </div>
        <div style="font-size:11px;color:#64748b;margin-bottom:8px;">${f.alamatPpk || '-'} &bull; Telp: ${f.telpPpk || '-'}</div>
        <div style="display:flex;justify-content:space-between;font-size:11px;background:#f8fafc;padding:6px 8px;border-radius:6px;border:1px solid #e2e8f0;">
          <span>Kapasitas: <strong>${f.jmlRujuk || 0}/${f.kapasitas || 0}</strong></span>
          <span style="color:#059669;font-weight:700;">Kuota: ${persentase}</span>
        </div>
        <div style="margin-top:6px;font-size:10.5px;color:#0369a1;">📅 Jadwal: ${jadwal}</div>
      `;

      card.onclick = () => pilihFaskes(f.kdppk, f.nmppk, f.kelas, f.alamatPpk);
      container.appendChild(card);
    });

    box.style.display = 'block';
    showToast(`Ditemukan ${list.length} Faskes Rujukan`, 'success');
  })
  .catch(err => {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-search-location"></i> Cari Faskes / RS Rujukan Realtime';
    }
    showToast('Gagal mencari faskes rujukan', 'danger');
  });
}

function pilihFaskes(kdppk, nmppk, kelas, alamat) {
  document.getElementById('rujukKdPPK').value = kdppk;
  document.getElementById('rujukNmPPK').value = nmppk;

  document.getElementById('selectedRsNama').textContent = `${nmppk} (${kdppk})`;
  document.getElementById('selectedRsInfo').textContent = `${kelas || ''} &bull; ${alamat || ''}`;

  document.getElementById('boxFaskesTerpilih').style.display = 'block';
  document.getElementById('boxHasilFaskes').style.display = 'none';

  showToast(`RS Tujuan dipilih: ${nmppk}`, 'info');
}

function simpanKirimRujukan(andPrint = false) {
  const form = document.getElementById('formRujukan');
  const kdPPK = document.getElementById('rujukKdPPK').value;
  const kdDiag1 = document.getElementById('rujukKdDiag1').value.trim();

  if (!kdDiag1) { showToast('Diagnosa utama ICD-10 wajib diisi', 'warning'); return; }
  if (!kdPPK) { showToast('Silakan cari dan pilih Rumah Sakit Rujukan terlebih dahulu', 'warning'); return; }

  const selSub = document.getElementById('rujukSubSpesialis');
  if (selSub && selSub.selectedIndex >= 0) document.getElementById('rujukNmSubSpesialis').value = selSub.options[selSub.selectedIndex].text;
  
  const selSar = document.getElementById('rujukSarana');
  if (selSar && selSar.selectedIndex >= 0) document.getElementById('rujukNmSarana').value = selSar.options[selSar.selectedIndex].text;

  const selKh = document.getElementById('rujukKhusus');
  if (selKh && selKh.selectedIndex >= 0) document.getElementById('rujukNmKhusus').value = selKh.options[selKh.selectedIndex].text;

  const selTacc = document.getElementById('rujukTacc');
  if (selTacc && selTacc.selectedIndex >= 0) document.getElementById('rujukNmTacc').value = selTacc.options[selTacc.selectedIndex].text;

  const btn1 = document.getElementById('btnSimpanOnly');
  const btn2 = document.getElementById('btnSimpanPrint');
  btn1.disabled = true;
  btn2.disabled = true;
  btn2.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengirim Rujukan...';

  const formData = new FormData(form);
  formData.append('action', 'simpan_kirim_rujukan');

  fetch(`<?= BASE_URL ?>modules/pcare/ajax.php`, {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    body: formData
  })
  .then(r => r.json())
  .then(res => {
    btn1.disabled = false;
    btn2.disabled = false;
    btn2.innerHTML = '<i class="fas fa-print"></i> Kirim &amp; Cetak Surat Rujukan';

    if (res.success) {
      showToast(res.message, 'success');
      tutupModalRujukan();
      if (andPrint) {
        window.open(`<?= BASE_URL ?>modules/pcare/cetak_rujukan.php?no_rawat=${encodeURIComponent(res.no_rawat)}`, '_blank');
      }
      pollLivePendaftaran();
    } else {
      showToast(res.message || 'Gagal mengirim surat rujukan', 'danger');
    }
  })
  .catch(err => {
    btn1.disabled = false;
    btn2.disabled = false;
    btn2.innerHTML = '<i class="fas fa-print"></i> Kirim &amp; Cetak Surat Rujukan';
    showToast('Terjadi kesalahan saat memproses rujukan', 'danger');
  });
}
</script>

<!-- ─── Modal Pembuatan & Pengiriman Surat Rujukan BPJS PCare ──── -->
<div class="modal" id="modalBuatRujukan" style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(15,23,42,0.65);z-index:2000;display:none;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(4px);">
  <div class="modal-content" style="background:#ffffff;border-radius:14px;max-width:820px;width:100%;max-height:92vh;display:flex;flex-direction:column;box-shadow:0 25px 50px -12px rgba(0,0,0,0.3);overflow:hidden;animation:modalFadeIn 0.2s ease-out;">
    
    <!-- Modal Header -->
    <div style="padding:16px 22px;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;background:linear-gradient(135deg, #f0fdf4 0%, #e0f2fe 100%);">
      <div style="display:flex;align-items:center;gap:12px;">
        <div style="width:38px;height:38px;border-radius:10px;background:#0284c7;color:#ffffff;display:flex;align-items:center;justify-content:center;font-size:18px;">
          <i class="fas fa-paper-plane"></i>
        </div>
        <div>
          <h3 style="font-size:15px;font-weight:800;color:#0f172a;margin:0;">Pembuatan Surat Rujukan FKTP BPJS (PCare)</h3>
          <p style="font-size:11.5px;color:#64748b;margin:0;">Rujuk Vertikal Subspesialis &amp; Khusus Terhubung Realtime ke PCare BPJS</p>
        </div>
      </div>
      <button type="button" onclick="tutupModalRujukan()" style="background:none;border:none;color:#64748b;font-size:20px;cursor:pointer;padding:4px 8px;border-radius:6px;">
        &times;
      </button>
    </div>

    <!-- Modal Body Form -->
    <div style="padding:20px 24px;overflow-y:auto;flex:1;background:#f8fafc;">
      
      <form id="formRujukan">
        <input type="hidden" id="rujukNoRawat" name="no_rawat">
        <input type="hidden" id="rujukNoRm" name="no_rkm_medis">
        <input type="hidden" id="rujukKdPPK" name="kd_ppk">
        <input type="hidden" id="rujukNmPPK" name="nm_ppk">
        <input type="hidden" id="rujukNmSubSpesialis" name="nm_subspesialis">
        <input type="hidden" id="rujukNmKhusus" name="nm_khusus">
        <input type="hidden" id="rujukNmSarana" name="nm_sarana" value="Rawat Jalan">
        <input type="hidden" id="rujukNmTacc" name="nm_tacc" value="Tanpa TACC">

        <!-- 1. DATA IDENTITAS PASIEN -->
        <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;padding:16px 18px;">
          <div style="font-size:12.5px;font-weight:800;color:#0369a1;margin-bottom:12px;text-transform:uppercase;display:flex;align-items:center;gap:6px;">
            <i class="fas fa-id-card"></i> 1. Identitas Pasien &amp; Kunjungan
          </div>
          <div style="display:grid;grid-template-columns:1.5fr 1fr 1fr;gap:12px;">
            <div class="form-group">
              <label class="form-label">Nama Pasien</label>
              <input type="text" id="rujukNmPasien" name="nm_pasien" class="form-control" readonly style="font-weight:700;background:#f8fafc;">
            </div>
            <div class="form-group">
              <label class="form-label">Nomor Kartu BPJS</label>
              <input type="text" id="rujukNoKartu" name="no_kartu" class="form-control" readonly style="font-family:monospace;font-weight:700;color:#0284c7;background:#f8fafc;">
            </div>
            <div class="form-group">
              <label class="form-label">Tgl. Daftar Kunjungan</label>
              <input type="date" id="rujukTglDaftar" name="tgl_daftar" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:10px;">
            <div class="form-group">
              <label class="form-label">Poli Klinik</label>
              <select id="rujukKdPoli" name="kd_poli" class="form-control">
                <?php foreach ($poli_list as $pl): ?>
                  <option value="<?= htmlspecialchars($pl['kd_poli']) ?>"><?= htmlspecialchars($pl['nm_poli']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Dokter Pemeriksa</label>
              <select id="rujukKdDokter" name="kd_dokter" class="form-control">
                <?php foreach ($dokter_list as $dl): ?>
                  <option value="<?= htmlspecialchars($dl['kd_dokter']) ?>"><?= htmlspecialchars($dl['nm_dokter']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>

        <!-- 2. PEMERIKSAAN FISIK & DIAGNOSA ICD-10 -->
        <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;padding:16px 18px;margin-top:14px;">
          <div style="font-size:12.5px;font-weight:800;color:#0369a1;margin-bottom:12px;text-transform:uppercase;display:flex;align-items:center;gap:6px;">
            <i class="fas fa-stethoscope"></i> 2. Pemeriksaan Klinis &amp; Diagnosa (ICD-10)
          </div>
          
          <div class="form-group mb-10">
            <label class="form-label">Keluhan Utama</label>
            <textarea id="rujukKeluhan" name="keluhan" class="form-control" rows="2" placeholder="Tuliskan keluhan atau indikasi medis rujukan..."></textarea>
          </div>

          <div style="display:grid;grid-template-columns:repeat(6, 1fr);gap:8px;">
            <div class="form-group">
              <label class="form-label" style="font-size:11px;">Sistole (mmHg)</label>
              <input type="number" id="rujukSistole" name="sistole" class="form-control" value="120">
            </div>
            <div class="form-group">
              <label class="form-label" style="font-size:11px;">Diastole (mmHg)</label>
              <input type="number" id="rujukDiastole" name="diastole" class="form-control" value="80">
            </div>
            <div class="form-group">
              <label class="form-label" style="font-size:11px;">Nadi (x/m)</label>
              <input type="number" id="rujukHeartRate" name="heart_rate" class="form-control" value="80">
            </div>
            <div class="form-group">
              <label class="form-label" style="font-size:11px;">Resp (x/m)</label>
              <input type="number" id="rujukRespRate" name="resp_rate" class="form-control" value="20">
            </div>
            <div class="form-group">
              <label class="form-label" style="font-size:11px;">Tinggi (cm)</label>
              <input type="number" id="rujukTinggi" name="tinggi_badan" class="form-control" value="165">
            </div>
            <div class="form-group">
              <label class="form-label" style="font-size:11px;">Berat (kg)</label>
              <input type="number" id="rujukBerat" name="berat_badan" class="form-control" value="60">
            </div>
          </div>

          <!-- Inline Autocomplete ICD-10 Search Box -->
          <div style="position:relative;">
            <div style="display:grid;grid-template-columns:1.2fr 2fr;gap:12px;margin-top:10px;">
              <div class="form-group" style="position:relative;">
                <label class="form-label" style="display:flex;justify-content:space-between;">
                  <span>Kode ICD-10 Utama *</span>
                  <span style="font-size:10.5px;color:#0284c7;font-weight:normal;">Ketik kode / nama penyakit</span>
                </label>
                <div style="display:flex;gap:6px;">
                  <input type="text" id="rujukKdDiag1" name="kd_diag1" class="form-control" placeholder="Contoh: I10 / Hipertensi / Diare" required style="font-family:monospace;font-weight:700;text-transform:uppercase;" oninput="onDiagInputLive(this.value)" autocomplete="off">
                  <button type="button" class="btn btn-primary" onclick="toggleDiagDropdown()" title="Cari Diagnosa"><i class="fas fa-search"></i> Cari</button>
                </div>
              </div>
              <div class="form-group">
                <label class="form-label">Nama Diagnosa Utama (BPJS)</label>
                <input type="text" id="rujukNmDiag1" name="nm_diag1" class="form-control" placeholder="Nama penyakit terpilih" style="background:#f8fafc;font-weight:600;color:#0369a1;">
              </div>
            </div>

            <!-- Dropdown Popover Hasil Pencarian Diagnosa -->
            <div id="modalDiagDropdown" style="display:none;position:absolute;top:70px;left:0;right:0;background:#ffffff;border:2px solid #0284c7;border-radius:10px;box-shadow:0 18px 40px rgba(0,0,0,0.3);z-index:9999;max-height:260px;overflow-y:auto;padding:8px;">
              <div style="padding:6px 10px;background:#f0f9ff;border-radius:6px;font-size:11px;font-weight:700;color:#0369a1;display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                <span><i class="fas fa-book-medical"></i> Hasil Pencarian Diagnosa ICD-10 (Klik untuk memilih):</span>
                <button type="button" onclick="document.getElementById('modalDiagDropdown').style.display='none'" style="background:none;border:none;color:#64748b;font-weight:bold;cursor:pointer;font-size:13px;">&times; Tutup</button>
              </div>
              <div id="modalDiagResults" style="display:flex;flex-direction:column;gap:4px;">
                <div style="text-align:center;color:#64748b;padding:12px;font-size:11.5px;">Ketik minimal 2 huruf kode atau nama penyakit (contoh: K30, I10, Demam, Diare)...</div>
              </div>
            </div>
          </div>

          <div style="display:grid;grid-template-columns:1fr 2fr;gap:12px;margin-top:8px;">
            <div class="form-group">
              <label class="form-label">Kode ICD-10 Sekunder (Opsional)</label>
              <input type="text" id="rujukKdDiag2" name="kd_diag2" class="form-control" placeholder="ICD-10 Tambahan" style="font-family:monospace;text-transform:uppercase;">
            </div>
            <div class="form-group">
              <label class="form-label">Terapi yang Diberikan</label>
              <input type="text" id="rujukTerapi" name="terapi" class="form-control" placeholder="Terapi awal / obat yang telah diberikan...">
            </div>
          </div>
        </div>

        <!-- 3. TUJUAN RUJUKAN & SPESIALIS -->
        <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;padding:16px 18px;margin-top:14px;">
          <div style="font-size:12.5px;font-weight:800;color:#0369a1;margin-bottom:12px;text-transform:uppercase;display:flex;align-items:center;gap:6px;">
            <i class="fas fa-hospital"></i> 3. Spesialis &amp; Pencarian Rumah Sakit Rujukan
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:10px;margin-bottom:12px;">
            <div class="form-group">
              <label class="form-label">Jenis Rujukan</label>
              <select id="rujukJenis" name="jenis_rujukan" class="form-control" onchange="toggleJenisRujukan()">
                <option value="subspesialis">Rujuk Vertikal (Subspesialis)</option>
                <option value="khusus">Rujukan Kasus Khusus</option>
              </select>
            </div>
            <div class="form-group" id="boxPilihSpesialis">
              <label class="form-label">Poli Spesialis</label>
              <select id="rujukSpesialis" class="form-control" onchange="onSpesialisChange()">
                <option value="">Memuat Spesialis...</option>
              </select>
            </div>
            <div class="form-group" id="boxPilihSubspesialis">
              <label class="form-label">Subspesialis *</label>
              <select id="rujukSubSpesialis" name="kd_subspesialis" class="form-control">
                <option value="">Pilih Spesialis Dulu</option>
              </select>
            </div>
            <div class="form-group" id="boxPilihKhusus" style="display:none;">
              <label class="form-label">Kategori Kasus Khusus</label>
              <select id="rujukKhusus" name="kd_khusus" class="form-control">
                <option value="">Memuat Khusus...</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Sarana RS</label>
              <select id="rujukSarana" name="kd_sarana" class="form-control">
                <option value="1">Rawat Jalan</option>
                <option value="2">Rawat Inap</option>
                <option value="3">IGD</option>
              </select>
            </div>
          </div>

          <div style="display:flex;align-items:center;gap:12px;background:#f0f9ff;padding:12px 14px;border-radius:10px;border:1px solid #bae6fd;margin-bottom:12px;">
            <div style="flex:1;">
              <label class="form-label" style="color:#0369a1;font-weight:700;">Tanggal Rencana Kunjungan Rujuk *</label>
              <input type="date" id="rujukTglEst" name="tgl_est_rujuk" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
            <div style="padding-top:20px;">
              <button type="button" class="btn btn-primary" onclick="cariFaskesRujukan(this)">
                <i class="fas fa-search-location"></i> Cari Faskes / RS Rujukan Realtime
              </button>
            </div>
          </div>

          <!-- Live Result Cards Faskes Rujukan -->
          <div id="boxHasilFaskes" style="display:none;">
            <div style="font-size:12px;font-weight:700;color:#334155;margin-bottom:8px;">
              <i class="fas fa-list"></i> Pilih Rumah Sakit Tujuan:
            </div>
            <div id="listFaskesCards" style="display:grid;grid-template-columns:repeat(auto-fill, minmax(280px, 1fr));gap:10px;max-height:260px;overflow-y:auto;padding-right:4px;">
              <!-- Cards dynamically inserted -->
            </div>
          </div>

          <!-- RS Terpilih Card -->
          <div id="boxFaskesTerpilih" style="display:none;background:#ecfdf5;border:1.5px solid #10b981;padding:12px 16px;border-radius:10px;margin-top:10px;">
            <div style="display:flex;align-items:center;justify-content:space-between;">
              <div>
                <span style="font-size:10px;font-weight:800;color:#047857;background:#d1fae5;padding:2px 8px;border-radius:12px;text-transform:uppercase;">
                  Faskes Rujukan Terpilih
                </span>
                <div id="selectedRsNama" style="font-size:14px;font-weight:800;color:#065f46;margin-top:4px;"></div>
                <div id="selectedRsInfo" style="font-size:11.5px;color:#047857;"></div>
              </div>
              <button type="button" class="btn btn-sm btn-outline-success" onclick="document.getElementById('boxHasilFaskes').style.display='block';">
                <i class="fas fa-pencil"></i> Ganti RS
              </button>
            </div>
          </div>

        </div>

        <!-- 4. TACC & ALASAN RUJUKAN -->
        <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;padding:16px 18px;margin-top:14px;">
          <div style="font-size:12.5px;font-weight:800;color:#0369a1;margin-bottom:12px;text-transform:uppercase;display:flex;align-items:center;gap:6px;">
            <i class="fas fa-clipboard-check"></i> 4. Kriteria TACC (Time, Age, Complication, Comorbidity)
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div class="form-group">
              <label class="form-label">Kategori TACC</label>
              <select id="rujukTacc" name="kd_tacc" class="form-control" onchange="onTaccChange()">
                <option value="0">Tanpa TACC</option>
                <option value="1">Time (Waktu)</option>
                <option value="2">Age (Umur)</option>
                <option value="3">Complication (Komplikasi)</option>
                <option value="4">Comorbidity (Penyakit Penyerta)</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Alasan TACC / Catatan Rujukan</label>
              <input type="text" id="rujukAlasanTacc" name="alasan_tacc" class="form-control" placeholder="Contoh: Memerlukan penanganan spesialis lanjutan">
            </div>
          </div>
          <div class="form-group mt-10" id="boxCatatanKhusus" style="display:none;">
            <label class="form-label">Catatan Rujukan Kasus Khusus</label>
            <input type="text" id="rujukCatatanKhusus" name="catatan_khusus" class="form-control" placeholder="Catatan program rujukan khusus...">
          </div>
        </div>

      </form>

    </div>

    <!-- Modal Footer -->
    <div style="padding:14px 24px;border-top:1px solid #e2e8f0;background:#ffffff;display:flex;justify-content:space-between;align-items:center;">
      <button type="button" class="btn btn-secondary" onclick="tutupModalRujukan()">Batal</button>
      <div style="display:flex;gap:10px;">
        <button type="button" class="btn btn-outline-primary" id="btnSimpanOnly" onclick="simpanKirimRujukan(false)">
          <i class="fas fa-paper-plane"></i> Simpan &amp; Kirim BPJS
        </button>
        <button type="button" class="btn btn-primary" id="btnSimpanPrint" onclick="simpanKirimRujukan(true)">
          <i class="fas fa-print"></i> Kirim &amp; Cetak Surat Rujukan
        </button>
      </div>
    </div>

  </div>
</div>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
