<?php
/**
 * SIMKlinik — Kepegawaian (Pegawai, Dokter & Jadwal Praktek)
 * Full CRUD: Pegawai, Dokter, Jadwal, dengan linking ke mlite_users
 */

$page_title    = 'Manajemen Kepegawaian';
$active_module = 'kepegawaian';

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_module_access('kepegawaian');

$tab = $_GET['tab'] ?? 'dokter';

// ═══════════════════════════════════════════════════════════
// POST HANDLERS
// ═══════════════════════════════════════════════════════════

// ─── HAPUS DOKTER ─────────────────────────────────────────
if ($_POST['action'] ?? '' === 'hapus_dokter') {
    $kd = $conn->real_escape_string($_POST['kd_dokter'] ?? '');
    if ($kd) {
        $conn->query("UPDATE dokter SET status='0' WHERE kd_dokter='$kd'");
        set_flash('success', 'Dokter dinonaktifkan.');
    }
    redirect(BASE_URL . 'modules/kepegawaian/index.php?tab=dokter');
}

// ─── HAPUS JADWAL ─────────────────────────────────────────
if ($_POST['action'] ?? '' === 'hapus_jadwal') {
    $jkd  = $conn->real_escape_string($_POST['j_kd_dokter'] ?? '');
    $jhar = $conn->real_escape_string($_POST['j_hari_kerja'] ?? '');
    $jjam = $conn->real_escape_string($_POST['j_jam_mulai'] ?? '');
    if ($jkd && $jhar && $jjam) {
        $conn->query("DELETE FROM jadwal WHERE kd_dokter='$jkd' AND hari_kerja='$jhar' AND jam_mulai='$jjam'");
        set_flash('success', 'Jadwal praktek dihapus.');
    }
    redirect(BASE_URL . 'modules/kepegawaian/index.php?tab=jadwal');
}

// ─── HAPUS PEGAWAI ────────────────────────────────────────
if ($_POST['action'] ?? '' === 'hapus_pegawai') {
    $nik = $conn->real_escape_string($_POST['nik'] ?? '');
    if ($nik) {
        $conn->query("UPDATE pegawai SET stts_aktif='KELUAR' WHERE nik='$nik'");
        set_flash('success', 'Status pegawai diubah menjadi KELUAR.');
    }
    redirect(BASE_URL . 'modules/kepegawaian/index.php?tab=pegawai');
}

// ─── SIMPAN DOKTER ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_dokter'])) {
    $kd_dokter  = $conn->real_escape_string(sanitize($_POST['kd_dokter'] ?? ''));
    $nm_dokter  = $conn->real_escape_string(sanitize($_POST['nm_dokter'] ?? ''));
    $jk         = $conn->real_escape_string(sanitize($_POST['jk'] ?? 'L'));
    $kd_sps     = $conn->real_escape_string(sanitize($_POST['kd_sps'] ?? 'UMUM'));
    $no_telp    = $conn->real_escape_string(sanitize($_POST['no_telp'] ?? ''));
    $almt_tgl   = $conn->real_escape_string(sanitize($_POST['almt_tgl'] ?? '-'));
    $no_ijn     = $conn->real_escape_string(sanitize($_POST['no_ijn_praktek'] ?? ''));
    $tgl_lahir  = $conn->real_escape_string(sanitize($_POST['tgl_lahir'] ?? '2000-01-01'));
    $tmp_lahir  = $conn->real_escape_string(sanitize($_POST['tmp_lahir'] ?? '-'));
    $agama      = $conn->real_escape_string(sanitize($_POST['agama'] ?? 'Islam'));
    $alumni     = $conn->real_escape_string(sanitize($_POST['alumni'] ?? '-'));
    $is_edit    = !empty($_POST['edit_dokter']);

    if (empty($kd_dokter) || empty($nm_dokter)) {
        set_flash('danger', 'Kode dan Nama Dokter wajib diisi.');
    } else {
        // Insert/update pegawai (FK constraint)
        $jk_peg = ($jk === 'L') ? 'Pria' : 'Wanita';
        $conn->query("
            INSERT INTO pegawai (nik, nama, jk, jbtn, jnj_jabatan, kode_kelompok, kode_resiko, kode_emergency,
                departemen, bidang, stts_wp, stts_kerja, npwp, pendidikan, gapok, tmp_lahir, tgl_lahir,
                alamat, kota, mulai_kerja, ms_kerja, indexins, bpd, rekening, stts_aktif,
                wajibmasuk, pengurang, indek, mulai_kontrak, cuti_diambil, dankes, photo, no_ktp)
            VALUES ('$kd_dokter','$nm_dokter','$jk_peg','Dokter','-','-','-','-','-','-','-','-','-','-',
                0,'$tmp_lahir','$tgl_lahir','$almt_tgl','-',CURDATE(),'<1','-','-','-','AKTIF',
                0,0,0,CURDATE(),0,0,'-','0')
            ON DUPLICATE KEY UPDATE nama='$nm_dokter', jk='$jk_peg', alamat='$almt_tgl'
        ");

        // Insert/update dokter
        $sql = "INSERT INTO dokter (kd_dokter, nm_dokter, jk, tmp_lahir, tgl_lahir, gol_drh, agama, almt_tgl,
                    no_telp, stts_nikah, kd_sps, alumni, no_ijn_praktek, status)
                VALUES ('$kd_dokter','$nm_dokter','$jk','$tmp_lahir','$tgl_lahir','O','$agama','$almt_tgl',
                    '$no_telp','MENIKAH','$kd_sps','$alumni','$no_ijn','1')
                ON DUPLICATE KEY UPDATE
                    nm_dokter='$nm_dokter', jk='$jk', tmp_lahir='$tmp_lahir', tgl_lahir='$tgl_lahir',
                    agama='$agama', almt_tgl='$almt_tgl', no_telp='$no_telp', kd_sps='$kd_sps',
                    alumni='$alumni', no_ijn_praktek='$no_ijn', status='1'";

        if ($conn->query($sql)) {
            set_flash('success', "Data dokter <strong>$nm_dokter</strong> berhasil disimpan.");
        } else {
            set_flash('danger', 'Gagal: ' . $conn->error);
        }
    }
    redirect(BASE_URL . 'modules/kepegawaian/index.php?tab=dokter');
}

// ─── SIMPAN JADWAL ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_jadwal'])) {
    $kd_dok_j = $conn->real_escape_string(sanitize($_POST['kd_dokter'] ?? ''));
    $kd_pol_j = $conn->real_escape_string(sanitize($_POST['kd_poli'] ?? ''));
    $hari_j   = $conn->real_escape_string(sanitize($_POST['hari_kerja'] ?? 'SENIN'));
    $jam_m    = $conn->real_escape_string(sanitize($_POST['jam_mulai'] ?? '08:00'));
    $jam_s    = $conn->real_escape_string(sanitize($_POST['jam_selesai'] ?? '14:00'));
    $kuota_j  = (int)($_POST['kuota'] ?? 30);

    if (empty($kd_dok_j) || empty($kd_pol_j)) {
        set_flash('danger', 'Dokter dan Poliklinik wajib dipilih.');
    } else {
        // composite PK: kd_dokter + hari_kerja + jam_mulai
        $orig_kd  = $conn->real_escape_string(trim($_POST['orig_kd_dokter'] ?? ''));
        $orig_har = $conn->real_escape_string(trim($_POST['orig_hari_kerja'] ?? ''));
        $orig_jam = $conn->real_escape_string(trim($_POST['orig_jam_mulai'] ?? ''));
        if ($orig_kd && $orig_har && $orig_jam) {
            // UPDATE existing row
            $sql_j = "UPDATE jadwal SET kd_dokter='$kd_dok_j', hari_kerja='$hari_j', jam_mulai='$jam_m',
                      jam_selesai='$jam_s', kd_poli='$kd_pol_j', kuota=$kuota_j
                      WHERE kd_dokter='$orig_kd' AND hari_kerja='$orig_har' AND jam_mulai='$orig_jam'";
        } else {
            $sql_j = "INSERT INTO jadwal (kd_dokter, hari_kerja, jam_mulai, jam_selesai, kd_poli, kuota)
                      VALUES ('$kd_dok_j','$hari_j','$jam_m','$jam_s','$kd_pol_j',$kuota_j)
                      ON DUPLICATE KEY UPDATE jam_selesai='$jam_s', kd_poli='$kd_pol_j', kuota=$kuota_j";
        }
        if ($conn->query($sql_j)) {
            set_flash('success', 'Jadwal praktek berhasil disimpan.');
        } else {
            set_flash('danger', 'Gagal: ' . $conn->error);
        }
    }
    redirect(BASE_URL . 'modules/kepegawaian/index.php?tab=jadwal');
}

// ─── SIMPAN PEGAWAI ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_pegawai'])) {
    $edit_nik    = $conn->real_escape_string(trim($_POST['edit_nik'] ?? ''));
    $nik         = $conn->real_escape_string(sanitize($edit_nik ?: ($_POST['nik'] ?? '')));
    $nama        = $conn->real_escape_string(sanitize($_POST['nama'] ?? ''));
    $jk          = $conn->real_escape_string(sanitize($_POST['jk'] ?? 'Pria'));
    $jbtn        = $conn->real_escape_string(sanitize($_POST['jbtn'] ?? '-'));
    $pendidikan  = $conn->real_escape_string(sanitize($_POST['pendidikan'] ?? '-'));
    $tgl_lhr_raw = trim($_POST['tgl_lahir'] ?? '');
    $tgl_lahir   = empty($tgl_lhr_raw) ? '2000-01-01' : $conn->real_escape_string(sanitize($tgl_lhr_raw));
    $tmp_lahir   = $conn->real_escape_string(sanitize($_POST['tmp_lahir'] ?? '-'));
    $alamat      = $conn->real_escape_string(sanitize($_POST['alamat'] ?? '-'));
    $kota        = $conn->real_escape_string(sanitize($_POST['kota'] ?? '-'));
    $no_ktp      = $conn->real_escape_string(sanitize($_POST['no_ktp'] ?? '0'));
    $mulai_raw   = trim($_POST['mulai_kerja'] ?? '');
    $mulai_kerja = empty($mulai_raw) ? date('Y-m-d') : $conn->real_escape_string(sanitize($mulai_raw));
    $gapok       = (float)($_POST['gapok'] ?? 0);
    $stts_aktif  = $conn->real_escape_string(sanitize($_POST['stts_aktif'] ?? 'AKTIF'));
    $is_edit     = !empty($edit_nik);

    if (empty($nik) || empty($nama)) {
        set_flash('danger', 'NIK dan Nama wajib diisi.');
    } else {
        if ($is_edit) {
            // ── UPDATE ────────────────────────────────────────
            $sql = "UPDATE pegawai SET
                        nama='$nama', jk='$jk', jbtn='$jbtn', pendidikan='$pendidikan', gapok=$gapok,
                        tmp_lahir='$tmp_lahir', tgl_lahir='$tgl_lahir', alamat='$alamat', kota='$kota',
                        mulai_kerja='$mulai_kerja', stts_aktif='$stts_aktif', no_ktp='$no_ktp'
                    WHERE nik='$nik'";
        } else {
            // ── INSERT ────────────────────────────────────────
            $chk = $conn->query("SELECT id FROM pegawai WHERE nik='$nik' LIMIT 1");
            if ($chk && $chk->num_rows > 0) {
                set_flash('danger', "NIK <strong>$nik</strong> sudah terdaftar.");
                redirect(BASE_URL . 'modules/kepegawaian/index.php?tab=pegawai');
            }
            $sql = "INSERT INTO pegawai (nik, nama, jk, jbtn, jnj_jabatan, kode_kelompok, kode_resiko,
                        kode_emergency, departemen, bidang, stts_wp, stts_kerja, npwp, pendidikan, gapok,
                        tmp_lahir, tgl_lahir, alamat, kota, mulai_kerja, ms_kerja, indexins, bpd, rekening,
                        stts_aktif, wajibmasuk, pengurang, indek, mulai_kontrak, cuti_diambil, dankes, photo, no_ktp)
                    VALUES ('$nik','$nama','$jk','$jbtn','-','-','-','-','-','-','-','-','-','$pendidikan',
                        $gapok,'$tmp_lahir','$tgl_lahir','$alamat','$kota','$mulai_kerja','<1','-','-','-',
                        '$stts_aktif',0,0,0,'$mulai_kerja',0,0,'-','$no_ktp')";
        }
        if ($conn->query($sql)) {
            $lbl = $is_edit ? 'diperbarui' : 'ditambahkan';
            set_flash('success', "Data pegawai <strong>$nama</strong> berhasil $lbl.");
        } else {
            set_flash('danger', 'Gagal menyimpan: ' . $conn->error);
        }
    }
    redirect(BASE_URL . 'modules/kepegawaian/index.php?tab=pegawai');
}

// ═══════════════════════════════════════════════════════════
// LOAD DATA
// ═══════════════════════════════════════════════════════════

// Dokter List
$search_dok = $conn->real_escape_string(trim($_GET['q'] ?? ''));
$where_dok = $search_dok ? "AND (d.nm_dokter LIKE '%$search_dok%' OR d.kd_dokter LIKE '%$search_dok%')" : '';
$dokter_res = $conn->query("
    SELECT d.*, s.nm_sps AS nm_spesialis
    FROM dokter d
    LEFT JOIN spesialis s ON d.kd_sps = s.kd_sps
    WHERE d.status = '1' $where_dok
    ORDER BY d.nm_dokter ASC
");
$dokter_list = [];
if ($dokter_res) while ($row = $dokter_res->fetch_assoc()) $dokter_list[] = $row;

// Jadwal
$jadwal_res = $conn->query("
    SELECT j.kd_dokter, j.hari_kerja, j.jam_mulai, j.jam_selesai, j.kd_poli, j.kuota,
           d.nm_dokter, pol.nm_poli
    FROM jadwal j
    JOIN dokter d ON j.kd_dokter = d.kd_dokter
    JOIN poliklinik pol ON j.kd_poli = pol.kd_poli
    ORDER BY FIELD(j.hari_kerja,'SENIN','SELASA','RABU','KAMIS','JUMAT','SABTU','AKHAD','AHAD'), j.jam_mulai ASC
");
$jadwal_list = [];
if ($jadwal_res) while ($row = $jadwal_res->fetch_assoc()) $jadwal_list[] = $row;

// Pegawai List
$search_peg = $conn->real_escape_string(trim($_GET['qp'] ?? ''));
$filter_status = $conn->real_escape_string($_GET['status'] ?? 'AKTIF');
$where_peg = "WHERE stts_aktif = '$filter_status'";
if ($search_peg) $where_peg .= " AND (nama LIKE '%$search_peg%' OR nik LIKE '%$search_peg%' OR jbtn LIKE '%$search_peg%')";
$pegawai_res = $conn->query("SELECT * FROM pegawai $where_peg ORDER BY nama ASC");
$pegawai_list = [];
if ($pegawai_res) while ($row = $pegawai_res->fetch_assoc()) $pegawai_list[] = $row;

// Spesialis + Poliklinik + Jabatan for dropdowns
$sps_list  = []; $s = $conn->query("SELECT kd_sps, nm_sps as nm_spesialis FROM spesialis ORDER BY nm_sps"); if($s) while($r=$s->fetch_assoc()) $sps_list[]=$r;
$poli_list = []; $p = $conn->query("SELECT kd_poli, nm_poli FROM poliklinik WHERE status='1' ORDER BY nm_poli"); if($p) while($r=$p->fetch_assoc()) $poli_list[]=$r;
$jbtn_list = []; $j = $conn->query("SELECT kd_jbtn, nm_jbtn FROM jabatan ORDER BY nm_jbtn"); if($j) while($r=$j->fetch_assoc()) $jbtn_list[]=$r;

// Counts
$cnt_pegawai = $conn->query("SELECT COUNT(*) c FROM pegawai WHERE stts_aktif='AKTIF'")->fetch_assoc()['c'];
$cnt_dokter  = $conn->query("SELECT COUNT(*) c FROM dokter WHERE status='1'")->fetch_assoc()['c'];
$cnt_jadwal  = $conn->query("SELECT COUNT(*) c FROM jadwal")->fetch_assoc()['c'];

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<!-- ─── Page Header ──────────────────────────────────────── -->
<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fas fa-id-badge text-primary" style="margin-right:8px;"></i>Manajemen Kepegawaian</h1>
    <p class="page-subtitle">Kelola data pegawai, dokter aktif, dan jadwal praktek poliklinik</p>
  </div>
  <div class="page-actions">
    <?php if ($tab === 'dokter'): ?>
    <button type="button" class="btn btn-primary" data-open-modal="modalTambahDokter">
      <i class="fas fa-user-md"></i> Tambah Dokter
    </button>
    <?php elseif ($tab === 'jadwal'): ?>
    <button type="button" class="btn btn-primary" data-open-modal="modalTambahJadwal" id="btnAddJadwal">
      <i class="fas fa-calendar-plus"></i> Tambah Jadwal
    </button>
    <?php elseif ($tab === 'pegawai'): ?>
    <button type="button" class="btn btn-primary" data-open-modal="modalTambahPegawai" id="btnAddPegawai">
      <i class="fas fa-user-plus"></i> Tambah Pegawai
    </button>
    <?php endif; ?>
  </div>
</div>

<!-- ─── Stats Cards ───────────────────────────────────────── -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:20px;">
  <a href="?tab=pegawai" style="text-decoration:none;">
    <div class="card" style="padding:16px;border-left:4px solid #7c3aed;<?= $tab==='pegawai'?'background:var(--primary-50);':'' ?>">
      <div style="display:flex;align-items:center;gap:12px;">
        <div style="width:44px;height:44px;background:#f5f3ff;border-radius:12px;display:flex;align-items:center;justify-content:center;color:#7c3aed;font-size:20px;">
          <i class="fas fa-users"></i>
        </div>
        <div>
          <div style="font-size:26px;font-weight:800;color:#7c3aed;"><?= $cnt_pegawai ?></div>
          <div style="font-size:12px;color:var(--gray-500);">Pegawai Aktif</div>
        </div>
      </div>
    </div>
  </a>
  <a href="?tab=dokter" style="text-decoration:none;">
    <div class="card" style="padding:16px;border-left:4px solid #2563eb;<?= $tab==='dokter'?'background:var(--primary-50);':'' ?>">
      <div style="display:flex;align-items:center;gap:12px;">
        <div style="width:44px;height:44px;background:#eff6ff;border-radius:12px;display:flex;align-items:center;justify-content:center;color:#2563eb;font-size:20px;">
          <i class="fas fa-user-doctor"></i>
        </div>
        <div>
          <div style="font-size:26px;font-weight:800;color:#2563eb;"><?= $cnt_dokter ?></div>
          <div style="font-size:12px;color:var(--gray-500);">Dokter Aktif</div>
        </div>
      </div>
    </div>
  </a>
  <a href="?tab=jadwal" style="text-decoration:none;">
    <div class="card" style="padding:16px;border-left:4px solid #059669;<?= $tab==='jadwal'?'background:var(--primary-50);':'' ?>">
      <div style="display:flex;align-items:center;gap:12px;">
        <div style="width:44px;height:44px;background:#ecfdf5;border-radius:12px;display:flex;align-items:center;justify-content:center;color:#059669;font-size:20px;">
          <i class="fas fa-calendar-alt"></i>
        </div>
        <div>
          <div style="font-size:26px;font-weight:800;color:#059669;"><?= $cnt_jadwal ?></div>
          <div style="font-size:12px;color:var(--gray-500);">Jadwal Praktek</div>
        </div>
      </div>
    </div>
  </a>
</div>

<!-- ─── Tab Navigation ────────────────────────────────────── -->
<div style="display:flex;gap:4px;border-bottom:2px solid var(--gray-200);margin-bottom:20px;">
  <?php
  $tabs = ['dokter'=>['icon'=>'fa-user-doctor','label'=>'Dokter & Tenaga Medis'],
           'jadwal'=>['icon'=>'fa-calendar-alt','label'=>'Jadwal Praktek'],
           'pegawai'=>['icon'=>'fa-users','label'=>'Data Pegawai']];
  foreach($tabs as $tid => $tdata): $isActive = ($tab===$tid); ?>
  <a href="?tab=<?= $tid ?>" style="display:flex;align-items:center;gap:8px;padding:10px 20px;border-radius:8px 8px 0 0;font-size:13px;font-weight:600;text-decoration:none;border-bottom:2px solid transparent;margin-bottom:-2px;
     <?= $isActive ? 'color:var(--primary-600);border-bottom-color:var(--primary-600);background:var(--primary-50);' : 'color:var(--gray-500);' ?>">
    <i class="fas <?= $tdata['icon'] ?>"></i> <?= $tdata['label'] ?>
  </a>
  <?php endforeach; ?>
</div>

<?php if ($tab === 'dokter'): ?>
<!-- ══════════════════════════════════════════════════════════
     TAB: DOKTER
══════════════════════════════════════════════════════════ -->

<!-- Search -->
<form method="GET" style="margin-bottom:12px;display:flex;gap:8px;">
  <input type="hidden" name="tab" value="dokter">
  <div style="flex:1;position:relative;">
    <i class="fas fa-search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--gray-400);font-size:13px;"></i>
    <input type="text" name="q" value="<?= htmlspecialchars($search_dok) ?>" class="form-control" 
           placeholder="Cari nama atau kode dokter..." style="padding-left:32px;">
  </div>
  <button class="btn btn-outline-primary"><i class="fas fa-search"></i> Cari</button>
  <?php if($search_dok): ?><a href="?tab=dokter" class="btn btn-outline"><i class="fas fa-times"></i></a><?php endif; ?>
</form>

<div class="card">
  <div class="card-header">
    <div class="card-title"><i class="fas fa-user-doctor text-primary"></i> Daftar Dokter Klinik</div>
    <span style="font-size:12px;color:var(--gray-500);"><?= count($dokter_list) ?> dokter aktif</span>
  </div>
  <div class="card-body" style="padding:0;">
    <?php if (empty($dokter_list)): ?>
      <div class="empty-state">
        <div class="empty-state-icon"><i class="fas fa-user-slash"></i></div>
        <div class="empty-state-title">Belum ada dokter terdaftar</div>
      </div>
    <?php else: ?>
    <div class="table-wrapper">
      <table class="table">
        <thead>
          <tr>
            <th>Kode</th>
            <th>Nama Dokter</th>
            <th>Spesialis</th>
            <th>Alamat / Asal</th>
            <th>Kontak</th>
            <th>No. SIP</th>
            <th style="width:90px;text-align:center;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($dokter_list as $d): ?>
          <tr>
            <td>
              <span style="font-family:monospace;font-weight:700;color:var(--primary-600);font-size:12px;
                           background:var(--primary-50);padding:2px 8px;border-radius:6px;">
                <?= htmlspecialchars($d['kd_dokter']) ?>
              </span>
            </td>
            <td>
              <div style="display:flex;align-items:center;gap:10px;">
                <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#2563eb,#4f46e5);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:14px;flex-shrink:0;">
                  <?= strtoupper(mb_substr($d['nm_dokter'],0,1)) ?>
                </div>
                <div>
                  <div style="font-weight:700;font-size:13px;color:var(--gray-900);"><?= htmlspecialchars($d['nm_dokter']) ?></div>
                  <div style="font-size:11px;color:var(--gray-400);">
                    <?= $d['jk']==='L' ? '♂ Laki-laki' : '♀ Perempuan' ?>
                    <?php if ($d['tgl_lahir'] && $d['tgl_lahir'] !== '0000-00-00'): ?>
                      · <?= date('d/m/Y', strtotime($d['tgl_lahir'])) ?>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </td>
            <td>
              <span class="badge badge-primary" style="font-size:11px;"><?= htmlspecialchars($d['nm_spesialis'] ?: 'Umum') ?></span>
            </td>
            <td style="font-size:12px;color:var(--gray-600);max-width:160px;">
              <?php if ($d['tmp_lahir'] && $d['tmp_lahir'] !== '-'): ?>
                <div><?= htmlspecialchars($d['tmp_lahir']) ?></div>
              <?php endif; ?>
              <?php if ($d['alumni'] && $d['alumni'] !== '-'): ?>
                <div style="font-size:11px;color:var(--gray-400);"><?= htmlspecialchars($d['alumni']) ?></div>
              <?php endif; ?>
            </td>
            <td style="font-size:12px;color:var(--gray-600);"><?= htmlspecialchars($d['no_telp'] ?: '-') ?></td>
            <td style="font-size:11px;color:var(--gray-500);font-family:monospace;"><?= htmlspecialchars($d['no_ijn_praktek'] ?: '-') ?></td>
            <td style="text-align:center;">
              <div style="display:flex;gap:4px;justify-content:center;">
                <button class="btn btn-sm btn-outline-primary btn-edit-dokter"
                        data-dokter='<?= htmlspecialchars(json_encode($d), ENT_QUOTES) ?>'
                        title="Edit Dokter"><i class="fas fa-edit"></i></button>
                <form method="POST" onsubmit="return confirm('Nonaktifkan dokter <?= htmlspecialchars($d['nm_dokter']) ?>?')">
                  <input type="hidden" name="action" value="hapus_dokter">
                  <input type="hidden" name="kd_dokter" value="<?= htmlspecialchars($d['kd_dokter']) ?>">
                  <button type="submit" class="btn btn-sm" style="color:#dc2626;border-color:#fca5a5;" title="Nonaktifkan">
                    <i class="fas fa-user-slash"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php elseif ($tab === 'jadwal'): ?>
<!-- ══════════════════════════════════════════════════════════
     TAB: JADWAL
══════════════════════════════════════════════════════════ -->
<div class="card">
  <div class="card-header">
    <div class="card-title"><i class="fas fa-calendar-alt text-success"></i> Jadwal Praktek Poliklinik</div>
    <span style="font-size:12px;color:var(--gray-500);"><?= count($jadwal_list) ?> sesi terjadwal</span>
  </div>
  <div class="card-body" style="padding:0;">
    <?php if (empty($jadwal_list)): ?>
      <div class="empty-state">
        <div class="empty-state-icon"><i class="fas fa-calendar-times"></i></div>
        <div class="empty-state-title">Belum ada jadwal praktek</div>
      </div>
    <?php else: ?>
    <?php
    // Group by hari
    $by_hari = [];
    foreach ($jadwal_list as $j) $by_hari[$j['hari_kerja']][] = $j;
    $hari_colors = ['SENIN'=>'#2563eb','SELASA'=>'#059669','RABU'=>'#d97706','KAMIS'=>'#7c3aed','JUMAT'=>'#dc2626','SABTU'=>'#0891b2','AKHAD'=>'#be123c','AHAD'=>'#be123c'];
    ?>
    <div class="table-wrapper">
      <table class="table">
        <thead>
          <tr>
            <th>Hari</th>
            <th>Dokter</th>
            <th>Poliklinik</th>
            <th>Jam Praktek</th>
            <th>Kuota</th>
            <th style="width:90px;text-align:center;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($jadwal_list as $j): ?>
          <tr>
            <td>
              <span class="badge" style="background:<?= $hari_colors[$j['hari_kerja']] ?? '#64748b' ?>;color:#fff;font-weight:700;font-size:11px;">
                <?= htmlspecialchars($j['hari_kerja']) ?>
              </span>
            </td>
            <td style="font-weight:600;font-size:13px;"><?= htmlspecialchars($j['nm_dokter']) ?></td>
            <td>
              <span style="font-size:12px;color:var(--gray-700);"><?= htmlspecialchars($j['nm_poli']) ?></span>
            </td>
            <td>
              <span style="font-size:12px;display:flex;align-items:center;gap:6px;color:var(--gray-700);">
                <i class="fas fa-clock text-primary"></i>
                <?= substr($j['jam_mulai'],0,5) ?> — <?= substr($j['jam_selesai'],0,5) ?>
              </span>
            </td>
            <td>
              <span style="font-size:12px;font-weight:600;"><?= htmlspecialchars($j['kuota'] ?? '-') ?> pasien</span>
            </td>
            <td style="text-align:center;">
              <div style="display:flex;gap:4px;justify-content:center;">
                <button class="btn btn-sm btn-outline-primary btn-edit-jadwal"
                        data-jadwal='<?= htmlspecialchars(json_encode($j), ENT_QUOTES) ?>'
                        title="Edit Jadwal"><i class="fas fa-edit"></i></button>
                <form method="POST" onsubmit="return confirm('Hapus jadwal ini?')">
                  <input type="hidden" name="action" value="hapus_jadwal">
                  <input type="hidden" name="j_kd_dokter" value="<?= htmlspecialchars($j['kd_dokter']) ?>">
                  <input type="hidden" name="j_hari_kerja" value="<?= htmlspecialchars($j['hari_kerja']) ?>">
                  <input type="hidden" name="j_jam_mulai" value="<?= htmlspecialchars($j['jam_mulai']) ?>">
                  <button type="submit" class="btn btn-sm" style="color:#dc2626;border-color:#fca5a5;"><i class="fas fa-trash"></i></button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php elseif ($tab === 'pegawai'): ?>
<!-- ══════════════════════════════════════════════════════════
     TAB: PEGAWAI
══════════════════════════════════════════════════════════ -->

<!-- Filter + Search -->
<form method="GET" style="margin-bottom:12px;display:flex;gap:8px;flex-wrap:wrap;">
  <input type="hidden" name="tab" value="pegawai">
  <div style="flex:1;min-width:200px;position:relative;">
    <i class="fas fa-search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--gray-400);font-size:13px;"></i>
    <input type="text" name="qp" value="<?= htmlspecialchars($search_peg) ?>" class="form-control"
           placeholder="Cari nama, NIK, atau jabatan..." style="padding-left:32px;">
  </div>
  <select name="status" class="form-control" style="width:150px;">
    <option value="AKTIF" <?= $filter_status==='AKTIF'?'selected':'' ?>>Aktif</option>
    <option value="CUTI" <?= $filter_status==='CUTI'?'selected':'' ?>>Cuti</option>
    <option value="KELUAR" <?= $filter_status==='KELUAR'?'selected':'' ?>>Keluar</option>
    <option value="TENAGA LUAR" <?= $filter_status==='TENAGA LUAR'?'selected':'' ?>>Tenaga Luar</option>
  </select>
  <button class="btn btn-outline-primary"><i class="fas fa-filter"></i> Filter</button>
</form>

<div class="card">
  <div class="card-header">
    <div class="card-title"><i class="fas fa-users text-primary"></i> Data Pegawai</div>
    <span style="font-size:12px;color:var(--gray-500);"><?= count($pegawai_list) ?> pegawai</span>
  </div>
  <div class="card-body" style="padding:0;">
    <?php if (empty($pegawai_list)): ?>
      <div class="empty-state">
        <div class="empty-state-icon"><i class="fas fa-user-slash"></i></div>
        <div class="empty-state-title">Tidak ada data pegawai ditemukan</div>
      </div>
    <?php else: ?>
    <div class="table-wrapper">
      <table class="table">
        <thead>
          <tr>
            <th>NIK</th>
            <th>Nama Pegawai</th>
            <th>Jabatan</th>
            <th>Pendidikan</th>
            <th>Tgl Lahir</th>
            <th>Mulai Kerja</th>
            <th>Status</th>
            <th style="width:90px;text-align:center;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pegawai_list as $pg): 
            $status_cls = match($pg['stts_aktif']) {
              'AKTIF' => 'badge-success',
              'CUTI'  => 'badge-warning',
              'KELUAR'=> 'badge-danger',
              default => 'badge-secondary'
            };
          ?>
          <tr>
            <td>
              <span style="font-family:monospace;font-weight:700;font-size:12px;color:var(--primary-600);
                           background:var(--primary-50);padding:2px 8px;border-radius:6px;">
                <?= htmlspecialchars($pg['nik']) ?>
              </span>
            </td>
            <td>
              <div style="display:flex;align-items:center;gap:10px;">
                <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#7c3aed,#6d28d9);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:13px;flex-shrink:0;">
                  <?= strtoupper(mb_substr($pg['nama'],0,1)) ?>
                </div>
                <div>
                  <div style="font-weight:600;font-size:13px;"><?= htmlspecialchars($pg['nama']) ?></div>
                  <div style="font-size:11px;color:var(--gray-400);"><?= $pg['jk'] === 'Pria' ? '♂' : '♀' ?> <?= htmlspecialchars($pg['kota'] ?: '') ?></div>
                </div>
              </div>
            </td>
            <td style="font-size:12px;"><?= htmlspecialchars($pg['jbtn'] ?: '-') ?></td>
            <td style="font-size:12px;color:var(--gray-600);"><?= htmlspecialchars($pg['pendidikan'] ?: '-') ?></td>
            <td style="font-size:12px;color:var(--gray-600);">
              <?= ($pg['tgl_lahir'] && $pg['tgl_lahir'] !== '0000-00-00') ? date('d/m/Y', strtotime($pg['tgl_lahir'])) : '-' ?>
            </td>
            <td style="font-size:12px;color:var(--gray-600);">
              <?= ($pg['mulai_kerja'] && $pg['mulai_kerja'] !== '0000-00-00') ? date('d/m/Y', strtotime($pg['mulai_kerja'])) : '-' ?>
            </td>
            <td><span class="badge <?= $status_cls ?>"><?= htmlspecialchars($pg['stts_aktif']) ?></span></td>
            <td style="text-align:center;">
              <div style="display:flex;gap:4px;justify-content:center;">
                <button class="btn btn-sm btn-outline-primary btn-edit-pegawai"
                        data-pegawai='<?= htmlspecialchars(json_encode($pg), ENT_QUOTES) ?>'
                        title="Edit"><i class="fas fa-edit"></i></button>
                <form method="POST" onsubmit="return confirm('Ubah status pegawai <?= htmlspecialchars($pg['nama']) ?> menjadi KELUAR?')">
                  <input type="hidden" name="action" value="hapus_pegawai">
                  <input type="hidden" name="nik" value="<?= htmlspecialchars($pg['nik']) ?>">
                  <button type="submit" class="btn btn-sm" style="color:#dc2626;border-color:#fca5a5;"><i class="fas fa-user-minus"></i></button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>


<!-- ══════════════════════════════════════════════════════════
     MODALS
══════════════════════════════════════════════════════════ -->

<!-- Modal Tambah / Edit Dokter -->
<div class="modal-overlay" id="modalTambahDokter">
  <div class="modal" style="max-width:680px;">
    <div class="modal-header">
      <h3 class="modal-title" id="dokterModalTitle"><i class="fas fa-user-doctor text-primary"></i> Tambah Data Dokter</h3>
      <button class="modal-close" data-close-modal="modalTambahDokter"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="simpan_dokter" value="1">
      <input type="hidden" name="edit_dokter" id="editDokterFlag" value="">
      <div class="modal-body">
        <div class="form-row col-2">
          <div class="form-group">
            <label class="form-label">Kode / NIP Dokter <span class="required">*</span></label>
            <input type="text" name="kd_dokter" id="fKdDokter" class="form-control" placeholder="cth: D0000007" required>
          </div>
          <div class="form-group">
            <label class="form-label">Nama Lengkap & Gelar <span class="required">*</span></label>
            <input type="text" name="nm_dokter" id="fNmDokter" class="form-control" placeholder="cth: dr. Budi Santoso, Sp.A" required>
          </div>
        </div>
        <div class="form-row col-3">
          <div class="form-group">
            <label class="form-label">Jenis Kelamin</label>
            <select name="jk" id="fJkDokter" class="form-control">
              <option value="L">Laki-laki</option>
              <option value="P">Perempuan</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Spesialisasi</label>
            <select name="kd_sps" id="fKdSps" class="form-control">
              <option value="UMUM">Dokter Umum</option>
              <?php foreach ($sps_list as $sp): ?>
                <option value="<?= $sp['kd_sps'] ?>"><?= htmlspecialchars($sp['nm_spesialis']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Agama</label>
            <select name="agama" id="fAgamaDokter" class="form-control">
              <?php foreach(['Islam','Kristen','Katolik','Hindu','Buddha','Konghucu'] as $ag): ?>
              <option value="<?= $ag ?>"><?= $ag ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-row col-2">
          <div class="form-group">
            <label class="form-label">Tempat Lahir</label>
            <input type="text" name="tmp_lahir" id="fTmpLahirDokter" class="form-control" placeholder="cth: Surabaya">
          </div>
          <div class="form-group">
            <label class="form-label">Tanggal Lahir</label>
            <input type="date" name="tgl_lahir" id="fTglLahirDokter" class="form-control" value="2000-01-01">
          </div>
        </div>
        <div class="form-row col-2">
          <div class="form-group">
            <label class="form-label">No. Telepon / WA</label>
            <input type="text" name="no_telp" id="fNoTelpDokter" class="form-control" placeholder="08xxxxxxxxxx">
          </div>
          <div class="form-group">
            <label class="form-label">Nomor SIP</label>
            <input type="text" name="no_ijn_praktek" id="fNoIjn" class="form-control" placeholder="SIP.xxx/xxx/xxx">
          </div>
        </div>
        <div class="form-row col-2">
          <div class="form-group">
            <label class="form-label">Asal / Universitas</label>
            <input type="text" name="alumni" id="fAlumni" class="form-control" placeholder="cth: Universitas Airlangga">
          </div>
          <div class="form-group">
            <label class="form-label">Alamat Tinggal</label>
            <input type="text" name="almt_tgl" id="fAlmtDokter" class="form-control" placeholder="Alamat lengkap...">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-close-modal="modalTambahDokter">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Dokter</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Tambah / Edit Jadwal -->
<div class="modal-overlay" id="modalTambahJadwal">
  <div class="modal" style="max-width:560px;">
    <div class="modal-header">
      <h3 class="modal-title" id="jadwalModalTitle"><i class="fas fa-calendar-plus text-success"></i> Tambah Jadwal Praktek</h3>
      <button class="modal-close" data-close-modal="modalTambahJadwal"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="simpan_jadwal" value="1">
      <input type="hidden" name="orig_kd_dokter" id="fOrigKdDokter" value="">
      <input type="hidden" name="orig_hari_kerja" id="fOrigHariKerja" value="">
      <input type="hidden" name="orig_jam_mulai" id="fOrigJamMulai" value="">
      <div class="modal-body">
        <div class="form-row col-2">
          <div class="form-group">
            <label class="form-label">Dokter <span class="required">*</span></label>
            <select name="kd_dokter" id="fJadwalDokter" class="form-control" required>
              <option value="">— Pilih Dokter —</option>
              <?php foreach ($dokter_list as $d): ?>
                <option value="<?= $d['kd_dokter'] ?>"><?= htmlspecialchars($d['nm_dokter']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Poliklinik <span class="required">*</span></label>
            <select name="kd_poli" id="fJadwalPoli" class="form-control" required>
              <option value="">— Pilih Poli —</option>
              <?php foreach ($poli_list as $pl): ?>
                <option value="<?= $pl['kd_poli'] ?>"><?= htmlspecialchars($pl['nm_poli']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-row col-3">
          <div class="form-group">
            <label class="form-label">Hari Kerja</label>
            <select name="hari_kerja" id="fHariKerja" class="form-control">
              <?php foreach (['SENIN','SELASA','RABU','KAMIS','JUMAT','SABTU','AKHAD'] as $h): ?>
                <option value="<?= $h ?>"><?= $h ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Jam Mulai</label>
            <input type="time" name="jam_mulai" id="fJamMulai" class="form-control" value="08:00">
          </div>
          <div class="form-group">
            <label class="form-label">Jam Selesai</label>
            <input type="time" name="jam_selesai" id="fJamSelesai" class="form-control" value="14:00">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Kuota Pasien per Sesi</label>
          <input type="number" name="kuota" id="fKuota" class="form-control" value="30" min="1" max="200">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-close-modal="modalTambahJadwal">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Jadwal</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Tambah / Edit Pegawai -->
<div class="modal-overlay" id="modalTambahPegawai">
  <div class="modal" style="max-width:680px;">
    <div class="modal-header">
      <h3 class="modal-title" id="pegawaiModalTitle"><i class="fas fa-user-plus text-primary"></i> Tambah Data Pegawai</h3>
      <button class="modal-close" data-close-modal="modalTambahPegawai"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="simpan_pegawai" value="1">
      <input type="hidden" name="edit_nik" id="editNik" value="">
      <div class="modal-body">
        <div class="form-row col-2">
          <div class="form-group">
            <label class="form-label">NIK / Kode Pegawai <span class="required">*</span></label>
            <input type="text" name="nik" id="fNik" class="form-control" placeholder="cth: P0001" required>
          </div>
          <div class="form-group">
            <label class="form-label">Nama Lengkap <span class="required">*</span></label>
            <input type="text" name="nama" id="fNama" class="form-control" placeholder="Nama lengkap pegawai" required>
          </div>
        </div>
        <div class="form-row col-3">
          <div class="form-group">
            <label class="form-label">Jenis Kelamin</label>
            <select name="jk" id="fJkPegawai" class="form-control">
              <option value="Pria">Laki-laki</option>
              <option value="Wanita">Perempuan</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Jabatan</label>
            <input type="text" name="jbtn" id="fJbtn" class="form-control" list="jbtnList" placeholder="Pilih/ketik jabatan">
            <datalist id="jbtnList">
              <?php foreach ($jbtn_list as $jb): ?>
              <option value="<?= htmlspecialchars($jb['nm_jbtn']) ?>">
              <?php endforeach; ?>
            </datalist>
          </div>
          <div class="form-group">
            <label class="form-label">Pendidikan Terakhir</label>
            <input type="text" name="pendidikan" id="fPendidikan" class="form-control" placeholder="cth: S1 Keperawatan">
          </div>
        </div>
        <div class="form-row col-2">
          <div class="form-group">
            <label class="form-label">Tempat Lahir</label>
            <input type="text" name="tmp_lahir" id="fTmpLahir" class="form-control" placeholder="Kota lahir">
          </div>
          <div class="form-group">
            <label class="form-label">Tanggal Lahir</label>
            <input type="date" name="tgl_lahir" id="fTglLahir" class="form-control" value="2000-01-01">
          </div>
        </div>
        <div class="form-row col-2">
          <div class="form-group">
            <label class="form-label">Alamat</label>
            <input type="text" name="alamat" id="fAlamat" class="form-control" placeholder="Alamat lengkap">
          </div>
          <div class="form-group">
            <label class="form-label">Kota</label>
            <input type="text" name="kota" id="fKota" class="form-control" placeholder="Kota domisili">
          </div>
        </div>
        <div class="form-row col-3">
          <div class="form-group">
            <label class="form-label">No. KTP</label>
            <input type="text" name="no_ktp" id="fNoKtp" class="form-control" placeholder="16 digit NIK KTP">
          </div>
          <div class="form-group">
            <label class="form-label">Mulai Kerja</label>
            <input type="date" name="mulai_kerja" id="fMulaiKerja" class="form-control" value="<?= date('Y-m-d') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Status Kepegawaian</label>
            <select name="stts_aktif" id="fSttsAktif" class="form-control">
              <option value="AKTIF">AKTIF</option>
              <option value="CUTI">CUTI</option>
              <option value="TENAGA LUAR">TENAGA LUAR</option>
              <option value="KELUAR">KELUAR</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Gaji Pokok (Rp)</label>
          <input type="number" name="gapok" id="fGapok" class="form-control" value="0" min="0" step="50000">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-close-modal="modalTambahPegawai">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Pegawai</button>
      </div>
    </form>
  </div>
</div>

<script>
// ─── Edit Dokter ──────────────────────────────────────────────────────────────
document.querySelectorAll('.btn-edit-dokter').forEach(btn => {
  btn.addEventListener('click', function() {
    const d = JSON.parse(this.dataset.dokter);
    document.getElementById('dokterModalTitle').innerHTML = '<i class="fas fa-user-edit text-primary"></i> Edit Dokter: ' + d.nm_dokter;
    document.getElementById('fKdDokter').value = d.kd_dokter;
    document.getElementById('fKdDokter').readOnly = true;
    document.getElementById('editDokterFlag').value = '1';
    document.getElementById('fNmDokter').value = d.nm_dokter;
    document.getElementById('fJkDokter').value = d.jk;
    document.getElementById('fKdSps').value = d.kd_sps;
    document.getElementById('fNoTelpDokter').value = d.no_telp || '';
    document.getElementById('fAlmtDokter').value = d.almt_tgl || '';
    document.getElementById('fNoIjn').value = d.no_ijn_praktek || '';
    document.getElementById('fTmpLahirDokter').value = d.tmp_lahir || '';
    document.getElementById('fTglLahirDokter').value = d.tgl_lahir || '2000-01-01';
    document.getElementById('fAgamaDokter').value = d.agama || 'Islam';
    document.getElementById('fAlumni').value = d.alumni || '';
    openModal('modalTambahDokter');
  });
});

// Reset Tambah Dokter
document.querySelector('[data-open-modal="modalTambahDokter"]')?.addEventListener('click', function() {
  document.getElementById('dokterModalTitle').innerHTML = '<i class="fas fa-user-doctor text-primary"></i> Tambah Data Dokter';
  document.getElementById('fKdDokter').readOnly = false;
  document.getElementById('editDokterFlag').value = '';
  document.querySelector('#modalTambahDokter form').reset();
  document.getElementById('fTglLahirDokter').value = '2000-01-01';
});

// ─── Edit Jadwal ──────────────────────────────────────────────────────────────
document.querySelectorAll('.btn-edit-jadwal').forEach(btn => {
  btn.addEventListener('click', function() {
    const j = JSON.parse(this.dataset.jadwal);
    document.getElementById('jadwalModalTitle').innerHTML = '<i class="fas fa-calendar-edit text-success"></i> Edit Jadwal';
    // Store original composite PK for UPDATE
    document.getElementById('fOrigKdDokter').value = j.kd_dokter;
    document.getElementById('fOrigHariKerja').value = j.hari_kerja;
    document.getElementById('fOrigJamMulai').value = j.jam_mulai;
    document.getElementById('fJadwalDokter').value = j.kd_dokter;
    document.getElementById('fJadwalPoli').value = j.kd_poli;
    document.getElementById('fHariKerja').value = j.hari_kerja;
    document.getElementById('fJamMulai').value = j.jam_mulai ? j.jam_mulai.substring(0,5) : '08:00';
    document.getElementById('fJamSelesai').value = j.jam_selesai ? j.jam_selesai.substring(0,5) : '14:00';
    document.getElementById('fKuota').value = j.kuota || 30;
    openModal('modalTambahJadwal');
  });
});

// Reset Tambah Jadwal
document.getElementById('btnAddJadwal')?.addEventListener('click', function() {
  document.getElementById('jadwalModalTitle').innerHTML = '<i class="fas fa-calendar-plus text-success"></i> Tambah Jadwal Praktek';
  // Clear composite PK fields (empty = INSERT mode)
  document.getElementById('fOrigKdDokter').value = '';
  document.getElementById('fOrigHariKerja').value = '';
  document.getElementById('fOrigJamMulai').value = '';
  document.querySelector('#modalTambahJadwal form').reset();
  document.getElementById('fJamMulai').value = '08:00';
  document.getElementById('fJamSelesai').value = '14:00';
  document.getElementById('fKuota').value = '30';
});

// ─── Edit Pegawai ─────────────────────────────────────────────────────────────
document.querySelectorAll('.btn-edit-pegawai').forEach(btn => {
  btn.addEventListener('click', function() {
    const pg = JSON.parse(this.dataset.pegawai);
    document.getElementById('pegawaiModalTitle').innerHTML = '<i class="fas fa-user-edit text-primary"></i> Edit Pegawai: ' + pg.nama;
    document.getElementById('fNik').value = pg.nik;
    document.getElementById('fNik').readOnly = true;
    document.getElementById('editNik').value = pg.nik;
    document.getElementById('fNama').value = pg.nama;
    document.getElementById('fJkPegawai').value = pg.jk;
    document.getElementById('fJbtn').value = pg.jbtn || '';
    document.getElementById('fPendidikan').value = pg.pendidikan || '';
    document.getElementById('fTmpLahir').value = pg.tmp_lahir || '';
    document.getElementById('fTglLahir').value = pg.tgl_lahir || '2000-01-01';
    document.getElementById('fAlamat').value = pg.alamat || '';
    document.getElementById('fKota').value = pg.kota || '';
    document.getElementById('fNoKtp').value = pg.no_ktp || '';
    document.getElementById('fMulaiKerja').value = pg.mulai_kerja || '';
    document.getElementById('fSttsAktif').value = pg.stts_aktif || 'AKTIF';
    document.getElementById('fGapok').value = pg.gapok || 0;
    openModal('modalTambahPegawai');
  });
});

// Reset Tambah Pegawai
document.getElementById('btnAddPegawai')?.addEventListener('click', function() {
  document.getElementById('pegawaiModalTitle').innerHTML = '<i class="fas fa-user-plus text-primary"></i> Tambah Data Pegawai';
  document.getElementById('fNik').readOnly = false;
  document.getElementById('editNik').value = '';
  document.querySelector('#modalTambahPegawai form').reset();
  document.getElementById('fTglLahir').value = '2000-01-01';
  document.getElementById('fMulaiKerja').value = new Date().toISOString().split('T')[0];
});
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
