<?php
/**
 * SIMKlinik — Input & Edit Nilai Hasil Pemeriksaan Laboratorium
 */

$page_title    = 'Input Hasil Laboratorium';
$active_module = 'laboratorium';

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

$noorder = sanitize($_GET['noorder'] ?? '');
if (empty($noorder)) redirect(BASE_URL . 'modules/laboratorium/index.php');

$noorder_esc = $conn->real_escape_string($noorder);

// ─── Load Data Permintaan & Pasien ───────────────────────────
$res_ord = $conn->query("
    SELECT pl.*, 
           r.no_reg, r.kd_poli, r.kd_pj,
           p.nm_pasien, p.no_rkm_medis, p.jk, p.tgl_lahir, p.alamat, p.no_ktp,
           d.nm_dokter as nm_dokter_perujuk,
           pol.nm_poli, pj.png_jawab as nm_penjab
    FROM permintaan_lab pl
    JOIN reg_periksa r ON pl.no_rawat = r.no_rawat
    JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
    LEFT JOIN dokter d ON pl.dokter_perujuk = d.kd_dokter
    LEFT JOIN poliklinik pol ON r.kd_poli = pol.kd_poli
    LEFT JOIN penjab pj ON p.kd_pj = pj.kd_pj
    WHERE pl.noorder = '$noorder_esc'
    LIMIT 1
");

if (!$res_ord || $res_ord->num_rows === 0) {
    set_flash('danger', 'Data permintaan laboratorium tidak ditemukan.');
    redirect(BASE_URL . 'modules/laboratorium/index.php');
}
$order = $res_ord->fetch_assoc();
$no_rawat = $order['no_rawat'];
$rawat_esc = $conn->real_escape_string($no_rawat);

// Hitung Umur dan Kategori Usia (Anak < 12 tahun, Dewasa >= 12 tahun)
$umur_thn = 20;
if (!empty($order['tgl_lahir']) && $order['tgl_lahir'] !== '0000-00-00') {
    $dob = new DateTime($order['tgl_lahir']);
    $diff = (new DateTime())->diff($dob);
    $umur_thn = $diff->y;
}
$is_anak = ($umur_thn < 12);
$is_pria = ($order['jk'] === 'L');

// ─── Load Master Dokter & Petugas Lab ─────────────────────────
$petugas_list = [];
$res_pg = $conn->query("SELECT nip, nama FROM petugas WHERE status = '1' ORDER BY nama ASC");
if ($res_pg) while ($r = $res_pg->fetch_assoc()) $petugas_list[] = $r;

$dokter_list = [];
$res_dr = $conn->query("SELECT kd_dokter, nm_dokter FROM dokter WHERE status = '1' ORDER BY nm_dokter ASC");
if ($res_dr) while ($r = $res_dr->fetch_assoc()) $dokter_list[] = $r;

// ─── Load Paket yang Diminta & Detail Parameter ───────────────
$res_pkgs = $conn->query("
    SELECT ppl.kd_jenis_prw, jpl.nm_perawatan, jpl.kategori, jpl.total_byr,
           jpl.bagian_rs, jpl.bhp, jpl.tarif_perujuk, jpl.tarif_tindakan_dokter, jpl.tarif_tindakan_petugas, jpl.kso, jpl.menejemen
    FROM permintaan_pemeriksaan_lab ppl
    JOIN jns_perawatan_lab jpl ON ppl.kd_jenis_prw = jpl.kd_jenis_prw
    WHERE ppl.noorder = '$noorder_esc'
    ORDER BY jpl.kategori ASC, jpl.nm_perawatan ASC
");

$ordered_packages = [];
if ($res_pkgs) {
    while ($pkg = $res_pkgs->fetch_assoc()) {
        $kd_pkg_esc = $conn->real_escape_string($pkg['kd_jenis_prw']);

        // Load templates
        $res_tpl = $conn->query("
            SELECT t.*,
                   (SELECT d.nilai FROM detail_periksa_lab d 
                    WHERE d.no_rawat = '$rawat_esc' AND d.kd_jenis_prw = '$kd_pkg_esc' AND d.id_template = t.id_template 
                    LIMIT 1) as existing_nilai,
                   (SELECT d.keterangan FROM detail_periksa_lab d 
                    WHERE d.no_rawat = '$rawat_esc' AND d.kd_jenis_prw = '$kd_pkg_esc' AND d.id_template = t.id_template 
                    LIMIT 1) as existing_keterangan,
                   (SELECT d.nilai_rujukan FROM detail_periksa_lab d 
                    WHERE d.no_rawat = '$rawat_esc' AND d.kd_jenis_prw = '$kd_pkg_esc' AND d.id_template = t.id_template 
                    LIMIT 1) as existing_rujukan
            FROM template_laboratorium t
            WHERE t.kd_jenis_prw = '$kd_pkg_esc'
            ORDER BY t.urut ASC, t.id_template ASC
        ");

        $items = [];
        if ($res_tpl) {
            while ($tpl = $res_tpl->fetch_assoc()) {
                // Tentukan nilai rujukan yang tepat
                if ($is_pria) {
                    $rujukan = $is_anak ? ($tpl['nilai_rujukan_la'] ?: $tpl['nilai_rujukan_ld']) : $tpl['nilai_rujukan_ld'];
                } else {
                    $rujukan = $is_anak ? ($tpl['nilai_rujukan_pa'] ?: $tpl['nilai_rujukan_pd']) : $tpl['nilai_rujukan_pd'];
                }
                $tpl['rujukan_terpilih'] = $tpl['existing_rujukan'] ?: $rujukan;
                $items[] = $tpl;
            }
        }
        $pkg['items'] = $items;
        $ordered_packages[] = $pkg;
    }
}

// ─── Proses Simpan / Update Hasil Pemeriksaan ─────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_hasil'])) {
    $tgl_periksa = sanitize($_POST['tgl_periksa'] ?? date('Y-m-d'));
    $jam_periksa = sanitize($_POST['jam_periksa'] ?? date('H:i:s'));
    $petugas_nip = sanitize($_POST['petugas_nip'] ?? 'PET001');
    $dokter_pj   = sanitize($_POST['dokter_pj'] ?? ($order['dokter_perujuk'] ?: 'DR001'));
    $nilai_post  = $_POST['nilai'] ?? [];
    $ket_post    = $_POST['keterangan'] ?? [];
    $rujukan_post= $_POST['rujukan'] ?? [];

    $tgl_esc = $conn->real_escape_string($tgl_periksa);
    $jam_esc = $conn->real_escape_string($jam_periksa);
    $nip_esc = $conn->real_escape_string($petugas_nip);
    $dpj_esc = $conn->real_escape_string($dokter_pj);

    // 1. Update timestamp di permintaan_lab
    $tgl_smp = ($order['tgl_sampel'] === '0000-00-00' || empty($order['tgl_sampel'])) ? $tgl_esc : $order['tgl_sampel'];
    $jam_smp = ($order['jam_sampel'] === '00:00:00' || empty($order['jam_sampel'])) ? date('H:i:s', strtotime('-15 minutes', strtotime("$tgl_esc $jam_esc"))) : $order['jam_sampel'];

    $conn->query("
        UPDATE permintaan_lab 
        SET tgl_sampel = '$tgl_smp',
            jam_sampel = '$jam_smp',
            tgl_hasil  = '$tgl_esc',
            jam_hasil  = '$jam_esc'
        WHERE noorder = '$noorder_esc'
    ");

    // 2. Loop per paket
    foreach ($ordered_packages as $pkg) {
        $kd_pkg = $pkg['kd_jenis_prw'];
        $kd_pkg_esc = $conn->real_escape_string($kd_pkg);
        $total_byr  = (float)$pkg['total_byr'];
        $bagian_rs  = (float)$pkg['bagian_rs'];
        $bhp        = (float)$pkg['bhp'];
        $tarif_perujuk = (float)$pkg['tarif_perujuk'];
        $tarif_dr   = (float)$pkg['tarif_tindakan_dokter'];
        $tarif_pr   = (float)$pkg['tarif_tindakan_petugas'];
        $kso        = (float)$pkg['kso'];
        $menejemen  = (float)$pkg['menejemen'];
        $kategori   = $pkg['kategori'] ?: 'PK';

        // Delete existing periksa_lab & detail_periksa_lab for this package to avoid duplicate primary keys
        $conn->query("DELETE FROM periksa_lab WHERE no_rawat = '$rawat_esc' AND kd_jenis_prw = '$kd_pkg_esc'");
        $conn->query("DELETE FROM detail_periksa_lab WHERE no_rawat = '$rawat_esc' AND kd_jenis_prw = '$kd_pkg_esc'");

        // Insert periksa_lab
        $conn->query("
            INSERT INTO periksa_lab (
                no_rawat, nip, kd_jenis_prw, tgl_periksa, jam,
                dokter_perujuk, bagian_rs, bhp, tarif_perujuk, tarif_tindakan_dokter,
                tarif_tindakan_petugas, kso, menejemen, biaya, kd_dokter, status, kategori
            ) VALUES (
                '$rawat_esc', '$nip_esc', '$kd_pkg_esc', '$tgl_esc', '$jam_esc',
                '{$order['dokter_perujuk']}', $bagian_rs, $bhp, $tarif_perujuk, $tarif_dr,
                $tarif_pr, $kso, $menejemen, $total_byr, '$dpj_esc', 'Ralan', '$kategori'
            )
        ");

        // Insert detail_periksa_lab for each item
        foreach ($pkg['items'] as $item) {
            $id_tpl = (int)$item['id_template'];
            $val    = $conn->real_escape_string(trim($nilai_post[$id_tpl] ?? ''));
            $ket    = $conn->real_escape_string(trim($ket_post[$id_tpl] ?? ''));
            $ruj    = $conn->real_escape_string(trim($rujukan_post[$id_tpl] ?? $item['rujukan_terpilih']));

            $conn->query("
                INSERT INTO detail_periksa_lab (
                    no_rawat, kd_jenis_prw, tgl_periksa, jam, id_template,
                    nilai, nilai_rujukan, keterangan,
                    bagian_rs, bhp, bagian_perujuk, bagian_dokter, bagian_laborat, kso, menejemen, biaya_item
                ) VALUES (
                    '$rawat_esc', '$kd_pkg_esc', '$tgl_esc', '$jam_esc', $id_tpl,
                    '$val', '$ruj', '$ket',
                    0, 0, 0, 0, 0, 0, 0, 0
                )
            ");
        }
    }

    set_flash('success', 'Hasil pemeriksaan laboratorium berhasil disimpan.');
    redirect(BASE_URL . 'modules/laboratorium/cetak_hasil.php?noorder=' . urlencode($noorder) . '&no_rawat=' . urlencode($no_rawat));
}

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<!-- ─── Header ────────────────────────────────────────────── -->
<div class="page-header" style="margin-bottom:16px;">
  <div class="page-header-left">
    <div style="display:flex;align-items:center;gap:10px;">
      <a href="<?= BASE_URL ?>modules/laboratorium/index.php" class="btn btn-outline btn-sm" style="border-color:#cbd5e1;color:#334155;">
        <i class="fas fa-arrow-left"></i>
      </a>
      <div>
        <h1 class="page-title" style="margin:0;font-size:18px;font-weight:800;color:#0f172a;">Input & Validasi Hasil Laboratorium</h1>
        <p class="page-subtitle" style="margin:2px 0 0;font-size:12px;color:#64748b;">Pengisian nilai hasil tes, perbandingan nilai rujukan dan catatan klinis analis</p>
      </div>
    </div>
  </div>

  <div class="page-header-right" style="display:flex;gap:8px;">
    <?php if ($order['tgl_hasil'] !== '0000-00-00' && !empty($order['tgl_hasil'])): ?>
      <a href="<?= BASE_URL ?>modules/laboratorium/cetak_hasil.php?noorder=<?= urlencode($noorder) ?>&no_rawat=<?= urlencode($no_rawat) ?>" 
         target="_blank" class="btn btn-success" style="font-size:12px;">
        <i class="fas fa-print"></i> Cetak Lembar Hasil
      </a>
    <?php endif; ?>
  </div>
</div>

<!-- ─── Patient Banner Header ─────────────────────────────── -->
<div class="card" style="border-left:4px solid #0284c7;background:#ffffff;box-shadow:0 1px 3px rgba(0,0,0,0.04);border-radius:10px;margin-bottom:16px;">
  <div class="card-body" style="padding:12px 18px;">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
      
      <!-- Patient Identity Left -->
      <div style="display:flex;align-items:center;gap:12px;">
        <div style="width:42px;height:42px;border-radius:10px;background:#e0f2fe;border:1px solid #bae6fd;display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:800;color:#0284c7;flex-shrink:0;">
          <?= strtoupper(substr($order['nm_pasien'], 0, 1)) ?>
        </div>
        <div>
          <div style="font-size:14px;font-weight:800;color:#0f172a;line-height:1.2;display:flex;align-items:center;gap:8px;">
            <span><?= htmlspecialchars($order['nm_pasien']) ?></span>
            <span style="font-size:11px;font-family:monospace;background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0;padding:1px 6px;border-radius:4px;">
              RM: <?= htmlspecialchars($order['no_rkm_medis']) ?>
            </span>
            <?= badge_penjab($order['nm_penjab'] ?? 'Umum') ?>
          </div>
          <div style="font-size:11.5px;color:#64748b;margin-top:3px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            <span><?= icon_jk($order['jk']) ?> <?= hitung_umur($order['tgl_lahir']) ?> (<?= $is_anak ? 'Anak' : 'Dewasa' ?>)</span>
            <span>&bull; No. Rawat: <strong style="color:#0f172a;font-family:monospace;"><?= htmlspecialchars($order['no_rawat']) ?></strong></span>
            <span>&bull; Dr. Pengirim: <strong style="color:#059669;"><?= htmlspecialchars($order['nm_dokter_perujuk'] ?: '-') ?></strong></span>
            <span>&bull; Diagnosa: <em><?= htmlspecialchars($order['diagnosa_klinis'] ?: '-') ?></em></span>
          </div>
        </div>
      </div>

      <!-- Order Code & Sample Status -->
      <div style="text-align:right;">
        <div style="font-size:12px;font-family:monospace;font-weight:700;color:#0284c7;background:#f0f9ff;border:1px solid #bae6fd;padding:4px 8px;border-radius:6px;display:inline-block;">
          Order: <?= htmlspecialchars($order['noorder']) ?>
        </div>
        <div style="font-size:11px;color:#64748b;margin-top:3px;">
          Tgl Order: <?= tgl_indo($order['tgl_permintaan']) ?> <?= substr($order['jam_permintaan'], 0, 5) ?>
        </div>
      </div>

    </div>
  </div>
</div>

<form method="POST" action="" id="formInputHasil">

  <!-- ─── Form Pengaturan Pemeriksa & Waktu ─────────────────── -->
  <div class="card" style="border-radius:10px;background:#ffffff;box-shadow:0 1px 3px rgba(0,0,0,0.04);margin-bottom:16px;">
    <div class="card-header" style="background:#f8fafc;padding:10px 18px;">
      <div class="card-title" style="font-size:13px;font-weight:700;color:#0f172a;"><i class="fas fa-user-check text-primary"></i> Petugas & Waktu Hasil</div>
    </div>
    <div class="card-body" style="padding:14px 18px;">
      <div class="form-row col-4">
        
        <div class="form-group" style="margin:0;">
          <label class="form-label" style="font-size:11.5px;font-weight:700;color:#334155;">Petugas / Analis Laborat <span class="text-danger">*</span></label>
          <select name="petugas_nip" class="form-control form-control-sm" required style="font-size:12px;">
            <?php foreach ($petugas_list as $pg): ?>
              <option value="<?= htmlspecialchars($pg['nip']) ?>">
                <?= htmlspecialchars($pg['nama']) ?> (<?= htmlspecialchars($pg['nip']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group" style="margin:0;">
          <label class="form-label" style="font-size:11.5px;font-weight:700;color:#334155;">Dokter Penanggung Jawab Lab <span class="text-danger">*</span></label>
          <select name="dokter_pj" class="form-control form-control-sm" required style="font-size:12px;">
            <?php foreach ($dokter_list as $dr): ?>
              <?php $sel = ($order['dokter_perujuk'] === $dr['kd_dokter']) ? 'selected' : ''; ?>
              <option value="<?= htmlspecialchars($dr['kd_dokter']) ?>" <?= $sel ?>>
                <?= htmlspecialchars($dr['nm_dokter']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group" style="margin:0;">
          <label class="form-label" style="font-size:11.5px;font-weight:700;color:#334155;">Tgl Selesai / Keluar Hasil</label>
          <input type="date" name="tgl_periksa" class="form-control form-control-sm" value="<?= ($order['tgl_hasil'] !== '0000-00-00' && !empty($order['tgl_hasil'])) ? $order['tgl_hasil'] : date('Y-m-d') ?>" style="font-size:12px;">
        </div>

        <div class="form-group" style="margin:0;">
          <label class="form-label" style="font-size:11.5px;font-weight:700;color:#334155;">Jam Selesai</label>
          <input type="time" name="jam_periksa" class="form-control form-control-sm" value="<?= ($order['jam_hasil'] !== '00:00:00' && !empty($order['jam_hasil'])) ? substr($order['jam_hasil'], 0, 5) : date('H:i') ?>" style="font-size:12px;">
        </div>

      </div>
    </div>
  </div>

  <!-- ─── Table Input Parameter Hasil Per Paket ─────────────── -->
  <div style="display:flex;flex-direction:column;gap:16px;">
    <?php foreach ($ordered_packages as $pkg): ?>
      <div class="card" style="border-radius:10px;background:#ffffff;box-shadow:0 1px 3px rgba(0,0,0,0.04);overflow:hidden;">
        
        <!-- Header Paket -->
        <div class="card-header" style="background:#f1f5f9;padding:12px 18px;display:flex;justify-content:space-between;align-items:center;">
          <div style="display:flex;align-items:center;gap:8px;">
            <span style="width:28px;height:28px;background:#0284c7;color:#fff;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:12px;">
              <i class="fas fa-flask"></i>
            </span>
            <div>
              <div style="font-size:13.5px;font-weight:800;color:#0f172a;">
                <?= htmlspecialchars($pkg['nm_perawatan']) ?>
              </div>
              <div style="font-size:11px;color:#64748b;">
                Kode: <span style="font-family:monospace;"><?= $pkg['kd_jenis_prw'] ?></span> &bull; Kategori: <strong><?= $pkg['kategori'] ?></strong> &bull; Tarif: <strong><?= rupiah((float)$pkg['total_byr']) ?></strong>
              </div>
            </div>
          </div>

          <!-- Quick Fill Buttons -->
          <div style="display:flex;gap:6px;">
            <button type="button" class="btn btn-outline btn-sm" onclick="setPresetValue('<?= $pkg['kd_jenis_prw'] ?>', 'Normal')" style="font-size:11px;padding:2px 8px;border-color:#cbd5e1;">
              Set Normal
            </button>
            <button type="button" class="btn btn-outline btn-sm" onclick="setPresetValue('<?= $pkg['kd_jenis_prw'] ?>', 'Negatif (-)')" style="font-size:11px;padding:2px 8px;border-color:#cbd5e1;">
              Set Negatif (-)
            </button>
            <button type="button" class="btn btn-outline btn-sm" onclick="setPresetValue('<?= $pkg['kd_jenis_prw'] ?>', 'Non Reaktif (-)')" style="font-size:11px;padding:2px 8px;border-color:#cbd5e1;">
              Set Non-Reaktif
            </button>
          </div>
        </div>

        <!-- Table Parameters -->
        <div class="table-responsive">
          <table class="table table-hover" style="margin:0;font-size:12px;">
            <thead style="background:#f8fafc;color:#475569;font-weight:700;">
              <tr>
                <th style="width:40px;text-align:center;">No</th>
                <th style="min-width:220px;">Parameter Pemeriksaan</th>
                <th style="width:180px;">Hasil Nilai</th>
                <th style="width:100px;">Satuan</th>
                <th style="width:180px;">Nilai Rujukan (Normal)</th>
                <th style="min-width:180px;">Keterangan / Flag</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($pkg['items'])): ?>
                <tr>
                  <td colspan="6" style="text-align:center;padding:16px;color:#94a3b8;">
                    Tidak ada parameter item untuk paket ini.
                  </td>
                </tr>
              <?php else: ?>
                <?php $no = 1; ?>
                <?php foreach ($pkg['items'] as $item): ?>
                  <?php $id_tpl = $item['id_template']; ?>
                  <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="text-align:center;font-weight:600;color:#94a3b8;"><?= $no++ ?></td>
                    
                    <!-- Nama Pemeriksaan -->
                    <td>
                      <div style="font-weight:700;color:#0f172a;font-size:12.5px;">
                        <?= htmlspecialchars($item['Pemeriksaan']) ?>
                      </div>
                    </td>

                    <!-- Input Nilai -->
                    <td>
                      <input type="text" name="nilai[<?= $id_tpl ?>]" id="val_<?= $id_tpl ?>"
                             class="form-control form-control-sm input-val-pkg-<?= $pkg['kd_jenis_prw'] ?>"
                             placeholder="cth: 14.2 / Negatif"
                             value="<?= htmlspecialchars($item['existing_nilai'] ?? '') ?>"
                             style="font-weight:700;font-size:12.5px;color:#0f172a;">
                    </td>

                    <!-- Satuan -->
                    <td style="color:#64748b;font-family:monospace;font-weight:600;">
                      <?= htmlspecialchars($item['satuan'] ?: '-') ?>
                    </td>

                    <!-- Nilai Rujukan (Editable if needed) -->
                    <td>
                      <input type="text" name="rujukan[<?= $id_tpl ?>]"
                             class="form-control form-control-sm"
                             value="<?= htmlspecialchars($item['rujukan_terpilih']) ?>"
                             style="font-size:11.5px;color:#475569;background:#f8fafc;">
                    </td>

                    <!-- Keterangan / Flag -->
                    <td>
                      <div style="display:flex;gap:4px;align-items:center;">
                        <input type="text" name="keterangan[<?= $id_tpl ?>]" id="ket_<?= $id_tpl ?>"
                               class="form-control form-control-sm"
                               placeholder="Normal / Low / High..."
                               value="<?= htmlspecialchars($item['existing_keterangan'] ?? '') ?>"
                               style="font-size:11.5px;">
                        
                        <!-- Quick badge helpers -->
                        <button type="button" class="btn btn-outline btn-sm" onclick="setRowKet(<?= $id_tpl ?>, 'Normal')" style="padding:2px 5px;font-size:10px;color:#059669;">N</button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="setRowKet(<?= $id_tpl ?>, 'High (Tinggi)')" style="padding:2px 5px;font-size:10px;color:#dc2626;">H</button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="setRowKet(<?= $id_tpl ?>, 'Low (Rendah)')" style="padding:2px 5px;font-size:10px;color:#2563eb;">L</button>
                      </div>
                    </td>

                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

      </div>
    <?php endforeach; ?>
  </div>

  <!-- ─── Bottom Actions Bar ────────────────────────────────── -->
  <div style="margin-top:20px;display:flex;justify-content:space-between;align-items:center;background:#ffffff;padding:14px 20px;border-radius:10px;box-shadow:0 1px 3px rgba(0,0,0,0.04);border:1px solid #e2e8f0;">
    <div>
      <a href="<?= BASE_URL ?>modules/laboratorium/index.php" class="btn btn-outline" style="border-color:#cbd5e1;color:#64748b;">
        <i class="fas fa-times"></i> Batal
      </a>
    </div>

    <div style="display:flex;gap:10px;">
      <button type="submit" name="simpan_hasil" class="btn btn-primary" style="padding:10px 24px;font-weight:700;font-size:13px;background:linear-gradient(135deg,#0284c7,#0369a1);border:none;">
        <i class="fas fa-save"></i> Simpan Hasil Pemeriksaan & Validasi
      </button>
    </div>
  </div>

</form>

<script>
// Quick set all values for a package
function setPresetValue(pkgCode, value) {
  const inputs = document.querySelectorAll('.input-val-pkg-' + pkgCode);
  inputs.forEach(inp => {
    if (!inp.value || inp.value === '') {
      inp.value = value;
    }
  });
}

function setRowKet(idTpl, ketText) {
  const el = document.getElementById('ket_' + idTpl);
  if (el) el.value = ketText;
}
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
