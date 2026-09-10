<?php
/**
 * SIMKlinik — Cetak Lembar Surat Rujukan FKTP BPJS Kesehatan
 * Format Standar Resmi BPJS Kesehatan FKTP
 */

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

$no_rawat = $conn->real_escape_string(sanitize($_GET['no_rawat'] ?? ''));
if (empty($no_rawat)) {
    die("Parameter no_rawat tidak valid.");
}

// Ambil data instansi klinik
$instansi = $conn->query("SELECT * FROM setting LIMIT 1")->fetch_assoc();
$nama_klinik   = $instansi['nama_instansi'] ?? INSTANSI_NAMA;
$kab_klinik    = $instansi['kabupaten'] ?? ($instansi['kota'] ?? INSTANSI_KOTA);
$prop_klinik   = $instansi['propinsi'] ?? INSTANSI_PROVINSI;
$kd_faskes     = $instansi['kd_faskes'] ?? '02100301';

// Ambil settings dari mlite_settings jika ada
$settings_res = @$conn->query("SELECT field, value FROM mlite_settings WHERE module IN ('settings','icare','pcare')");
$all_settings = [];
if ($settings_res) {
    while ($sr = $settings_res->fetch_assoc()) {
        $all_settings[$sr['field']] = $sr['value'];
    }
}
$kd_ppk_fktp = $all_settings['userkey'] ?? ($all_settings['consid'] ?? $kd_faskes);
if (strlen($kd_ppk_fktp) > 8) $kd_ppk_fktp = substr($kd_ppk_fktp, 0, 8);
if (empty($kd_ppk_fktp) || strlen($kd_ppk_fktp) < 4) $kd_ppk_fktp = '02100301';

$kd_kab_code = $all_settings['kdkab'] ?? '0034';
$kedeputian  = $all_settings['kedeputian_wilayah'] ?? 'KEDEPUTIAN WILAYAH I';
$cabang      = $all_settings['kantor_cabang'] ?? (strtoupper($kab_klinik) ?: 'SIBOLGA');

// Ambil data rujukan dari subspesialis / khusus
$rujukan = $conn->query("
    SELECT r.*, 'subspesialis' as jenis_rujukan, p.tgl_lahir, p.jk, p.alamat, p.no_ktp, p.no_tlp,
           reg.jam_reg, d.no_ijn_praktek
    FROM pcare_rujuk_subspesialis r
    JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
    JOIN reg_periksa reg ON r.no_rawat = reg.no_rawat
    LEFT JOIN dokter d ON r.kdDokter = d.kd_dokter
    WHERE r.no_rawat = '$no_rawat'
    LIMIT 1
")->fetch_assoc();

if (!$rujukan) {
    $rujukan = $conn->query("
        SELECT r.*, 'khusus' as jenis_rujukan, p.tgl_lahir, p.jk, p.alamat, p.no_ktp, p.no_tlp,
               reg.jam_reg, d.no_ijn_praktek
        FROM pcare_rujuk_khusus r
        JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
        JOIN reg_periksa reg ON r.no_rawat = reg.no_rawat
        LEFT JOIN dokter d ON r.kdDokter = d.kd_dokter
        WHERE r.no_rawat = '$no_rawat'
        LIMIT 1
    ")->fetch_assoc();
}

if (!$rujukan) {
    // Fallback jika belum ada di tabel pcare_rujuk, ambil dari reg_periksa & diagnosa_pasien
    $reg = $conn->query("
        SELECT r.no_rawat, r.no_rkm_medis, r.tgl_registrasi as tglDaftar,
               p.nm_pasien, p.no_peserta as noKartu, p.tgl_lahir, p.jk, p.alamat, p.no_ktp,
               d.nm_dokter as nmDokter, d.no_ijn_praktek,
               (SELECT dp.kd_penyakit FROM diagnosa_pasien dp WHERE dp.no_rawat = r.no_rawat ORDER BY dp.prioritas ASC LIMIT 1) as kdDiag1,
               (SELECT py.nm_penyakit FROM diagnosa_pasien dp JOIN penyakit py ON dp.kd_penyakit = py.kd_penyakit WHERE dp.no_rawat = r.no_rawat ORDER BY dp.prioritas ASC LIMIT 1) as nmDiag1
        FROM reg_periksa r
        JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
        LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
        WHERE r.no_rawat = '$no_rawat'
        LIMIT 1
    ")->fetch_assoc();

    if ($reg) {
        $rujukan = array_merge($reg, [
            'noKunjungan'    => '02100301' . date('m') . date('y') . 'Y' . substr(preg_replace('/[^0-9]/', '', $no_rawat), -6),
            'nmSubSpesialis' => 'POLI SPESIALIS',
            'nmPPK'          => 'RS RUJUKAN',
            'terapi'         => '-',
            'catatan'        => '-',
            'tglEstRujuk'    => $reg['tglDaftar'],
            'nmSarana'       => 'Rawat Jalan'
        ]);
    } else {
        die("Data surat rujukan untuk No. Rawat {$no_rawat} tidak ditemukan.");
    }
}

// Pastikan semua field aman dari undefined array key
$nm_pasien      = $rujukan['nm_pasien'] ?? '';
$no_kartu_bpjs  = $rujukan['noKartu'] ?? ($rujukan['no_peserta'] ?? '-');
$tgl_lahir      = $rujukan['tgl_lahir'] ?? '1990-01-01';
$jk             = $rujukan['jk'] ?? 'L';
$tgl_daftar     = $rujukan['tglDaftar'] ?? date('Y-m-d');
$tgl_est_rujuk  = $rujukan['tglEstRujuk'] ?? $tgl_daftar;
$kd_diag1       = $rujukan['kdDiag1'] ?? '';
$nm_diag1       = $rujukan['nmDiag1'] ?? '';
$nm_subspes     = $rujukan['nmSubSpesialis'] ?? ($rujukan['nmKhusus'] ?? 'POLI SPESIALIS');
$nm_ppk_tujuan  = $rujukan['nmPPK'] ?? ($rujukan['kdPPK'] ?? 'RS RUJUKAN');
$terapi         = $rujukan['terapi'] ?? '';
$catatan        = $rujukan['catatan'] ?? ($rujukan['alasanTACC'] ?? '');
$nm_dokter      = $rujukan['nmDokter'] ?? ($rujukan['nm_dokter'] ?? 'DR. DOKTER PEMERIKSA');

// Tentukan nama hari dari tanggal rencana rujukan
$days_map   = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
$hari_rujuk = $days_map[(int)date('w', strtotime($tgl_est_rujuk))] ?? 'Senin';

// Format Jadwal Praktek (Jam Praktek Faskes Rujukan)
$raw_jadwal   = trim($rujukan['jadwal'] ?? ($rujukan['jadwal_praktek'] ?? ''));
$sarana_names = ['rekam medik', 'rawat jalan', 'rawat inap', 'igd', 'sarana'];

if (!empty($raw_jadwal) && !in_array(strtolower($raw_jadwal), $sarana_names)) {
    if (preg_match('/[0-9]{1,2}[:.][0-9]{2}/', $raw_jadwal)) {
        $jadwal_praktek = (!str_contains($raw_jadwal, $hari_rujuk)) ? "{$hari_rujuk} : {$raw_jadwal}" : $raw_jadwal;
    } else {
        $jadwal_praktek = "{$hari_rujuk} : {$raw_jadwal}";
    }
} else {
    // Default format resmi rujukan BPJS FKTP (contoh: Jumat : 15:00 - 17:00 atau Senin : 08:00 - 14:00)
    $jadwal_praktek = "{$hari_rujuk} : 15:00 - 17:00";
}

// Format Umur & Tanggal Lahir
$birthDate = new DateTime($tgl_lahir);
$refDate   = new DateTime($tgl_daftar);
$umur_tahun = $refDate->diff($birthDate)->y;

$tgl_lahir_fmt          = date('d-M-Y', strtotime($tgl_lahir));
$tgl_rencana_berkunjung = date('d-M-Y', strtotime($tgl_est_rujuk));
$tgl_berlaku_sampai     = date('d-M-Y', strtotime($tgl_est_rujuk . ' +90 days'));
$tgl_surat_indo         = tgl_indo($tgl_daftar);

// No. Rujukan Barcode Code128 Generator (Pure SVG)
function generate_code128_svg(string $code, int $height = 42): string {
    $patterns = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
        '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
        '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
        '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
        '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
        '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
        '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
        '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
        '114131', '311141', '411131', '211412', '211214', '211232', '2331112'
    ];
    $start_b = 104;
    $checksum = $start_b;
    $encoded = [$patterns[$start_b]];
    $len = strlen($code);
    for ($i = 0; $i < $len; $i++) {
        $val = ord($code[$i]) - 32;
        if ($val < 0 || $val > 95) $val = 0;
        $checksum += $val * ($i + 1);
        $encoded[] = $patterns[$val];
    }
    $checksum %= 103;
    $encoded[] = $patterns[$checksum];
    $encoded[] = $patterns[106]; // Stop

    $bars = implode('', $encoded);
    $total_units = 0;
    $len_bars = strlen($bars);
    for ($i = 0; $i < $len_bars; $i++) $total_units += (int)$bars[$i];

    $unit_width = 1.35;
    $svg_width = $total_units * $unit_width;
    $svg = '<svg width="' . $svg_width . '" height="' . $height . '" viewBox="0 0 ' . $svg_width . ' ' . $height . '" xmlns="http://www.w3.org/2000/svg">';
    $x = 0;
    for ($i = 0; $i < $len_bars; $i++) {
        $w = (int)$bars[$i] * $unit_width;
        if ($i % 2 === 0) {
            $svg .= '<rect x="' . $x . '" y="0" width="' . $w . '" height="' . $height . '" fill="#000000" />';
        }
        $x += $w;
    }
    $svg .= '</svg>';
    return $svg;
}

$no_rujukan = $rujukan['noKunjungan'] ?? ('02100301' . date('mY') . '0001');
$barcode_svg = generate_code128_svg($no_rujukan, 40);
$jk_code = (strtoupper(substr($jk, 0, 1)) === 'L') ? 'L' : 'P';

// Logo Path / Base64 Data URI
$logo_file_path = dirname(__DIR__, 2) . '/assets/img/logo_bpjs.png';
$logo_src = BASE_URL . 'assets/img/logo_bpjs.png';
if (file_exists($logo_file_path)) {
    $logo_data = base64_encode(file_get_contents($logo_file_path));
    $logo_src = 'data:image/png;base64,' . $logo_data;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Surat Rujukan FKTP - <?= htmlspecialchars($nm_pasien) ?> (<?= htmlspecialchars($no_rujukan) ?>)</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: Arial, Helvetica, sans-serif;
      font-size: 11.5px;
      color: #000000;
      background: #f1f5f9;
      padding: 20px;
      line-height: 1.35;
    }
    .action-bar {
      width: 210mm;
      margin: 0 auto 12px auto;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .btn-print {
      background: #00a651;
      color: white;
      border: none;
      padding: 8px 18px;
      border-radius: 6px;
      font-weight: 700;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: 12px;
    }
    .btn-back {
      background: #e2e8f0;
      color: #334155;
      text-decoration: none;
      padding: 8px 16px;
      border-radius: 6px;
      font-weight: 600;
      font-size: 12px;
    }
    
    /* Lembar Kertas Cetak */
    .print-sheet {
      width: 210mm;
      min-height: 148mm;
      margin: 0 auto;
      background: #ffffff;
      padding: 12mm 15mm;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
      position: relative;
    }

    @media print {
      body { background: #ffffff; padding: 0; }
      .print-sheet { box-shadow: none; padding: 6mm 10mm; width: 100%; }
      .no-print { display: none !important; }
      @page {
        size: A4 portrait;
        margin: 8mm 10mm;
      }
    }

    /* Top Header */
    .top-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 6px;
    }
    .top-logo {
      width: 200px;
    }
    .top-logo img {
      width: 195px;
      height: auto;
      display: block;
    }
    .top-branch {
      font-size: 11px;
      font-weight: 700;
      color: #000000;
    }
    .top-branch table {
      border-collapse: collapse;
      font-size: 11px;
    }
    .top-branch td {
      padding: 1px 4px;
      vertical-align: top;
    }

    /* Judul Surat */
    .title-doc {
      text-align: center;
      font-size: 13.5px;
      font-weight: 700;
      margin-top: 2px;
      margin-bottom: 8px;
      letter-spacing: 0.2px;
    }

    /* Main Box Wrapper */
    .main-box {
      border: 1.5px solid #000000;
      padding: 12px 14px;
    }

    /* Sub-box Header Data */
    .sub-box-header {
      border: 1px solid #000000;
      padding: 6px 12px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 12px;
    }
    .sub-box-left {
      flex: 1;
    }
    .sub-box-left table {
      border-collapse: collapse;
      font-size: 11px;
    }
    .sub-box-left td {
      padding: 2px 4px;
      vertical-align: top;
    }
    .sub-box-right {
      text-align: right;
      padding-left: 10px;
    }

    /* Content Data */
    .content-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 11px;
      margin-bottom: 8px;
    }
    .content-table td {
      padding: 3px 4px;
      vertical-align: top;
    }

    /* Status Box */
    .status-box {
      display: inline-block;
      border: 1px solid #000000;
      padding: 0 5px;
      min-width: 18px;
      text-align: center;
      font-weight: 700;
      margin-right: 3px;
      line-height: 1.2;
    }

    /* Bottom Section */
    .bottom-section {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-top: 14px;
      font-size: 11px;
    }
    .bottom-left {
      flex: 1;
      padding-right: 20px;
    }
    .bottom-right {
      width: 220px;
      text-align: center;
    }
    .sign-space {
      height: 48px;
    }
  </style>
</head>
<body>

  <div class="action-bar no-print">
    <a href="<?= BASE_URL ?>modules/pcare/" class="btn-back">&larr; Kembali ke PCare</a>
    <button type="button" class="btn-print" onclick="window.print()">
      <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4H7v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
      Cetak Surat Rujukan
    </button>
  </div>

  <div class="print-sheet">
    
    <!-- Top Header: Logo BPJS & Kedeputian Wilayah -->
    <div class="top-header">
      <div class="top-logo">
        <img src="<?= $logo_src ?>" alt="BPJS Kesehatan">
      </div>

      <div class="top-branch">
        <table>
          <tr>
            <td>Kedeputian Wilayah</td>
            <td>:</td>
            <td><?= htmlspecialchars($kedeputian) ?></td>
          </tr>
          <tr>
            <td>Kantor Cabang</td>
            <td>:</td>
            <td><?= htmlspecialchars($cabang) ?></td>
          </tr>
        </table>
      </div>
    </div>

    <!-- Judul Dokumen -->
    <div class="title-doc">
      Surat Rujukan FKTP
    </div>

    <!-- Outer Box -->
    <div class="main-box">

      <!-- Sub Box Header (No. Rujukan & Barcode) -->
      <div class="sub-box-header">
        <div class="sub-box-left">
          <table>
            <tr>
              <td style="width:125px;">No. Rujukan</td>
              <td style="width:10px;">:</td>
              <td style="font-weight:700;"><?= htmlspecialchars($no_rujukan) ?></td>
            </tr>
            <tr>
              <td>FKTP</td>
              <td>:</td>
              <td><?= htmlspecialchars(strtoupper($nama_klinik)) ?>(<?= htmlspecialchars($kd_ppk_fktp) ?>)</td>
            </tr>
            <tr>
              <td>Kabupaten / Kota</td>
              <td>:</td>
              <td>KOTA <?= htmlspecialchars(strtoupper($kab_klinik)) ?>(<?= htmlspecialchars($kd_kab_code) ?>)</td>
            </tr>
          </table>
        </div>
        <div class="sub-box-right">
          <?= $barcode_svg ?>
        </div>
      </div>

      <!-- Kepada Yth. & Di -->
      <table class="content-table" style="margin-bottom:6px;">
        <tr>
          <td style="width:140px;">Kepada Yth. TS Dokter</td>
          <td style="width:10px;">:</td>
          <td><strong><?= htmlspecialchars(strtoupper($nm_subspes)) ?></strong></td>
        </tr>
        <tr>
          <td>Di</td>
          <td>:</td>
          <td><strong><?= htmlspecialchars(strtoupper($nm_ppk_tujuan)) ?></strong></td>
        </tr>
      </table>

      <div style="margin: 8px 4px 6px 4px;">
        Mohon pemeriksaan dan penangan lebih lanjut pasien :
      </div>

      <!-- Data Pasien 2 Kolom -->
      <table class="content-table">
        <tr>
          <td style="width:140px;">Nama</td>
          <td style="width:10px;">:</td>
          <td style="width:280px;"><strong><?= htmlspecialchars(strtoupper($nm_pasien)) ?></strong></td>
          <td style="width:50px;">Umur :</td>
          <td style="width:40px;"><?= $umur_tahun ?></td>
          <td style="width:50px;">Tahun :</td>
          <td><?= $tgl_lahir_fmt ?></td>
        </tr>
        <tr>
          <td>No. Kartu BPJS</td>
          <td>:</td>
          <td><?= htmlspecialchars($no_kartu_bpjs) ?></td>
          <td>Status :</td>
          <td colspan="3">
            <span class="status-box">1</span> Utama/Tanggungan &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            <span class="status-box"><?= $jk_code ?></span> (L / P)
          </td>
        </tr>
        <tr>
          <td>Diagnosa</td>
          <td>:</td>
          <td><?= htmlspecialchars($nm_diag1 ?: 'Diagnosa Rujukan') ?> (<?= htmlspecialchars($kd_diag1 ?: '-') ?>)</td>
          <td>Catatan :</td>
          <td colspan="3"><?= htmlspecialchars($catatan) ?></td>
        </tr>
        <tr>
          <td>Telah diberikan</td>
          <td>:</td>
          <td colspan="5"><?= htmlspecialchars($terapi) ?></td>
        </tr>
      </table>

      <!-- Bottom Section -->
      <div class="bottom-section">
        <div class="bottom-left">
          <div style="margin-bottom:12px;">Atas bantuannya, diucapkan terima kasih</div>
          <table style="border-collapse:collapse;font-size:11px;width:100%;">
            <tr>
              <td style="width:140px;padding:2px 0;">Tgl. Rencana Berkunjung</td>
              <td style="width:10px;padding:2px 0;">:</td>
              <td style="padding:2px 0;"><?= $tgl_rencana_berkunjung ?></td>
            </tr>
            <tr>
              <td style="padding:2px 0;">Jadwal Praktek</td>
              <td style="padding:2px 0;">:</td>
              <td style="padding:2px 0;"><?= htmlspecialchars($jadwal_praktek) ?></td>
            </tr>
            <tr>
              <td style="padding:2px 0;" colspan="3">
                Surat rujukan berlaku 1[satu] kali kunjungan, berlaku sampai dengan : &nbsp;&nbsp; <?= $tgl_berlaku_sampai ?>
              </td>
            </tr>
          </table>
        </div>

        <div class="bottom-right">
          <div>Salam sejawat,</div>
          <div><?= $tgl_surat_indo ?></div>
          <div class="sign-space"></div>
          <div style="font-weight:700;"><?= htmlspecialchars(strtoupper($nm_dokter)) ?></div>
        </div>
      </div>

    </div>

  </div>

</body>
</html>
