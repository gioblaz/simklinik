<?php
/**
 * SIMKlinik — Cetak Surat Persetujuan Umum / General Consent Pasien
 */

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

$no_rawat = sanitize($_GET['no_rawat'] ?? '');
if (empty($no_rawat)) die('No. Rawat tidak valid.');

$rawat_esc = $conn->real_escape_string($no_rawat);

// Load Pasien & Registrasi
$res = $conn->query("
    SELECT r.*, p.nm_pasien, p.jk, p.tgl_lahir, p.no_ktp, p.no_peserta,
           p.alamat, p.gol_darah, p.no_tlp, p.agama, p.pekerjaan,
           p.namakeluarga, p.alamatpj, p.keluarga,
           d.nm_dokter, pol.nm_poli, pj.png_jawab as nm_penjab
    FROM reg_periksa r
    JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
    LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
    LEFT JOIN poliklinik pol ON r.kd_poli = pol.kd_poli
    LEFT JOIN penjab pj ON p.kd_pj = pj.kd_pj
    WHERE r.no_rawat = '$rawat_esc'
    LIMIT 1
");

if (!$res || $res->num_rows === 0) die('Data pasien tidak ditemukan.');
$pasien = $res->fetch_assoc();
$pasien['umur'] = hitung_umur($pasien['tgl_lahir']);

// Load Data General Consent
$gc_res = $conn->query("SELECT * FROM surat_persetujuan_umum WHERE no_rawat = '$rawat_esc' LIMIT 1");
$gc = ($gc_res && $gc_res->num_rows > 0) ? $gc_res->fetch_assoc() : null;

// Jika belum disimpan, gunakan default data pasien
if (!$gc) {
    $gc = [
        'no_surat' => 'GC-' . date('Ymd', strtotime($pasien['tgl_registrasi'])) . '-' . substr(preg_replace('/[^0-9]/', '', $no_rawat), -4),
        'tgl_persetujuan' => $pasien['tgl_registrasi'] ?? date('Y-m-d'),
        'jam_persetujuan' => $pasien['jam_reg'] ?? date('H:i:s'),
        'nama_pj' => $pasien['nm_pasien'],
        'hubungan_pj' => 'Diri Sendiri',
        'jk_pj' => $pasien['jk'],
        'tgl_lahir_pj' => $pasien['tgl_lahir'],
        'umur_pj' => $pasien['umur'],
        'alamat_pj' => $pasien['alamat'],
        'no_ktp_pj' => $pasien['no_ktp'],
        'no_telp_pj' => $pasien['no_tlp'],
        'setuju_rawat_inap_jalan' => 'Setuju',
        'setuju_pelepasan_informasi' => 'Setuju',
        'nama_keluarga_informasi' => '-',
        'setuju_hak_kewajiban' => 'Setuju',
        'setuju_privasi_khusus' => 'Tidak Ada',
        'detail_privasi_khusus' => '-',
        'setuju_barang_pribadi' => 'Setuju',
        'setuju_pembayaran' => 'Setuju',
        'tipe_penjamin' => $pasien['nm_penjab'] ?? 'Umum',
        'keterangan_lain' => '-',
        'ttd_pasien' => '',
        'ttd_petugas' => '',
        'nip_petugas' => '-',
        'nama_petugas' => $_SESSION['nama'] ?? 'Petugas Admisi'
    ];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>General Consent - <?= htmlspecialchars($pasien['nm_pasien']) ?> (<?= htmlspecialchars($pasien['no_rkm_medis']) ?>)</title>
  <style>
    @page { 
      size: A4 portrait; 
      margin: 10mm 15mm; 
    }
    * { box-sizing: border-box; }
    body { 
      font-family: "Segoe UI", Arial, Helvetica, sans-serif; 
      font-size: 9.5pt; 
      color: #1a202c; 
      line-height: 1.35; 
      margin: 0; 
      padding: 15px 25px; 
      background: #f8fafc;
    }
    .print-sheet {
      max-width: 800px;
      margin: 0 auto;
      background: #fff;
      padding: 25px 30px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.06);
      border-radius: 6px;
    }
    
    /* Kop Surat */
    .header-kop { 
      border-bottom: 2.5px double #1e293b; 
      padding-bottom: 8px; 
      margin-bottom: 12px; 
      display: flex; 
      align-items: center; 
      gap: 15px; 
    }
    .kop-logo { 
      width: 65px; 
      height: 65px; 
      object-fit: contain; 
    }
    .kop-text { 
      flex: 1; 
      text-align: center; 
    }
    .kop-title { 
      font-size: 15pt; 
      font-weight: 800; 
      text-transform: uppercase; 
      letter-spacing: 0.5px;
      margin: 0; 
      color: #0f172a;
    }
    .kop-sub { 
      font-size: 8.5pt; 
      color: #475569; 
      margin-top: 3px; 
      line-height: 1.25;
    }

    /* Document Title */
    .doc-header {
      text-align: center;
      margin-bottom: 12px;
    }
    .doc-title { 
      font-size: 12.5pt; 
      font-weight: 800; 
      text-decoration: underline; 
      margin: 0; 
      text-transform: uppercase; 
      color: #0f172a;
      letter-spacing: 0.5px;
    }
    .doc-no {
      font-size: 9pt;
      font-weight: 600;
      color: #334155;
      margin-top: 2px;
    }

    /* Tables */
    .table-info { 
      width: 100%; 
      border-collapse: collapse; 
      margin-bottom: 8px; 
    }
    .table-info td { 
      padding: 2px 4px; 
      font-size: 9pt; 
      vertical-align: top; 
    }
    .table-info td.label { 
      width: 28%; 
      color: #334155; 
      font-weight: 500; 
    }
    .table-info td.sep { 
      width: 2%; 
      text-align: center; 
      font-weight: 600; 
    }
    .table-info td.val { 
      width: 70%; 
      color: #0f172a; 
      font-weight: 600; 
    }

    .section-head { 
      background: #f1f5f9; 
      padding: 4px 8px; 
      font-weight: 700; 
      font-size: 9.5pt; 
      border: 1px solid #cbd5e1; 
      margin-top: 8px; 
      margin-bottom: 6px; 
      border-radius: 4px;
      color: #0f172a;
    }
    
    .clause-block {
      margin-bottom: 7px;
      font-size: 8.5pt;
      text-align: justify;
    }
    .clause-title {
      font-weight: 700;
      color: #1e293b;
      margin-bottom: 2px;
    }
    .clause-text {
      color: #334155;
      line-height: 1.3;
      margin-left: 12px;
    }

    .badge-check {
      display: inline-block;
      padding: 1px 6px;
      background: #e0f2fe;
      color: #0369a1;
      border: 1px solid #bae6fd;
      border-radius: 3px;
      font-size: 8pt;
      font-weight: 600;
    }

    /* Signatures */
    .sign-section {
      margin-top: 15px;
      width: 100%;
      border-collapse: collapse;
      page-break-inside: avoid;
    }
    .sign-section td {
      width: 50%;
      text-align: center;
      vertical-align: top;
      padding: 0 10px;
    }
    .sign-box {
      border: 1px dashed #cbd5e1;
      border-radius: 6px;
      padding: 8px;
      background: #fafafa;
      min-height: 125px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      align-items: center;
    }
    .sign-title {
      font-size: 9pt;
      font-weight: 600;
      color: #334155;
      margin-bottom: 4px;
    }
    .sign-img-container {
      height: 75px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 4px 0;
    }
    .sign-img {
      max-height: 75px;
      max-width: 180px;
      object-fit: contain;
    }
    .sign-empty {
      color: #94a3b8;
      font-style: italic;
      font-size: 8pt;
      border: 1px dashed #e2e8f0;
      padding: 15px 20px;
      border-radius: 4px;
    }
    .sign-name {
      font-size: 9pt;
      font-weight: 700;
      text-decoration: underline;
      color: #0f172a;
    }
    .sign-role {
      font-size: 8pt;
      color: #64748b;
    }

    /* Floating Print Bar */
    .no-print-bar {
      position: fixed;
      top: 12px;
      right: 15px;
      display: flex;
      gap: 8px;
      z-index: 9999;
      background: rgba(255, 255, 255, 0.95);
      padding: 6px 12px;
      border-radius: 30px;
      box-shadow: 0 4px 14px rgba(0,0,0,0.15);
      border: 1px solid #e2e8f0;
    }
    .btn-print {
      background: #2563eb;
      color: #fff;
      border: none;
      padding: 7px 16px;
      border-radius: 20px;
      font-weight: 600;
      font-size: 9pt;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: all 0.2s;
    }
    .btn-print:hover {
      background: #1d4ed8;
      transform: translateY(-1px);
    }
    .btn-close-print {
      background: #64748b;
      color: #fff;
      border: none;
      padding: 7px 14px;
      border-radius: 20px;
      font-weight: 600;
      font-size: 9pt;
      cursor: pointer;
      transition: all 0.2s;
    }
    .btn-close-print:hover {
      background: #475569;
    }

    @media print {
      body {
        background: #fff;
        padding: 0;
      }
      .print-sheet {
        box-shadow: none;
        padding: 0;
        max-width: 100%;
      }
      .no-print-bar {
        display: none !important;
      }
      .sign-box {
        border-color: #94a3b8;
        background: transparent;
      }
    }
  </style>
</head>
<body>

  <!-- Floating Print Controls -->
  <div class="no-print-bar">
    <button type="button" class="btn-print" onclick="window.print()">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V2h12v7"></path><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
      Cetak / Simpan PDF
    </button>
    <button type="button" class="btn-close-print" onclick="window.close()">Tutup</button>
  </div>

  <div class="print-sheet">
    
    <!-- Kop Surat -->
    <div class="header-kop">
      <?php if (!empty(INSTANSI_LOGO) && file_exists(BASE_PATH . INSTANSI_LOGO)): ?>
        <img src="<?= BASE_URL . INSTANSI_LOGO ?>" class="kop-logo" alt="Logo">
      <?php else: ?>
        <div style="width:55px;height:55px;border-radius:50%;background:#2563eb;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:bold;font-size:18pt;">
          <?= strtoupper(substr(INSTANSI_NAMA, 0, 1)) ?>
        </div>
      <?php endif; ?>
      <div class="kop-text">
        <h1 class="kop-title"><?= htmlspecialchars(INSTANSI_NAMA) ?></h1>
        <div class="kop-sub">
          <?= htmlspecialchars(INSTANSI_ALAMAT) ?>, <?= htmlspecialchars(INSTANSI_KOTA) ?>, <?= htmlspecialchars(INSTANSI_PROVINSI) ?><br>
          Telp: <?= htmlspecialchars(INSTANSI_TELP) ?> | Layanan Kesehatan Terakreditasi
        </div>
      </div>
    </div>

    <!-- Judul Dokumen -->
    <div class="doc-header">
      <div class="doc-title">PERSETUJUAN UMUM (GENERAL CONSENT)</div>
      <div class="doc-no">Nomor Dokumen: <strong><?= htmlspecialchars($gc['no_surat']) ?></strong></div>
    </div>

    <!-- Identitas Pasien & Penanggung Jawab -->
    <div style="display: flex; gap: 15px; margin-bottom: 6px;">
      
      <!-- Box Kiri: Pasien -->
      <div style="flex: 1; border: 1px solid #e2e8f0; border-radius: 4px; padding: 6px 8px; background: #fafafa;">
        <div style="font-weight: 700; font-size: 8.5pt; color: #1e3a8a; border-bottom: 1px solid #e2e8f0; padding-bottom: 2px; margin-bottom: 4px; text-transform: uppercase;">
          Identitas Pasien
        </div>
        <table class="table-info">
          <tr>
            <td class="label">No. Rekam Medis</td>
            <td class="sep">:</td>
            <td class="val"><?= htmlspecialchars($pasien['no_rkm_medis']) ?></td>
          </tr>
          <tr>
            <td class="label">Nama Pasien</td>
            <td class="sep">:</td>
            <td class="val"><?= htmlspecialchars($pasien['nm_pasien']) ?> (<?= $pasien['jk'] === 'L' ? 'Laki-laki' : 'Perempuan' ?>)</td>
          </tr>
          <tr>
            <td class="label">Tgl. Lahir / Umur</td>
            <td class="sep">:</td>
            <td class="val"><?= tgl_indo($pasien['tgl_lahir']) ?> (<?= $pasien['umur'] ?>)</td>
          </tr>
          <tr>
            <td class="label">No. KTP / NIK</td>
            <td class="sep">:</td>
            <td class="val"><?= htmlspecialchars($pasien['no_ktp'] ?: '-') ?></td>
          </tr>
          <tr>
            <td class="label">Poliklinik / Dokter</td>
            <td class="sep">:</td>
            <td class="val"><?= htmlspecialchars($pasien['nm_poli']) ?> / <?= htmlspecialchars($pasien['nm_dokter']) ?></td>
          </tr>
          <tr>
            <td class="label">Jenis Penjamin</td>
            <td class="sep">:</td>
            <td class="val"><span class="badge-check"><?= htmlspecialchars($pasien['nm_penjab'] ?: 'Umum/Mandiri') ?></span></td>
          </tr>
        </table>
      </div>

      <!-- Box Kanan: Pemberi Persetujuan -->
      <div style="flex: 1; border: 1px solid #e2e8f0; border-radius: 4px; padding: 6px 8px; background: #fafafa;">
        <div style="font-weight: 700; font-size: 8.5pt; color: #1e3a8a; border-bottom: 1px solid #e2e8f0; padding-bottom: 2px; margin-bottom: 4px; text-transform: uppercase;">
          Pemberi Persetujuan (Pasien / Wali)
        </div>
        <table class="table-info">
          <tr>
            <td class="label">Nama Lengkap</td>
            <td class="sep">:</td>
            <td class="val"><?= htmlspecialchars($gc['nama_pj']) ?></td>
          </tr>
          <tr>
            <td class="label">Hubungan Pasien</td>
            <td class="sep">:</td>
            <td class="val"><strong><?= htmlspecialchars($gc['hubungan_pj']) ?></strong></td>
          </tr>
          <tr>
            <td class="label">Jenis Kelamin / Usia</td>
            <td class="sep">:</td>
            <td class="val"><?= $gc['jk_pj'] === 'L' ? 'Laki-laki' : 'Perempuan' ?> / <?= htmlspecialchars($gc['umur_pj'] ?: '-') ?></td>
          </tr>
          <tr>
            <td class="label">No. KTP / NIK</td>
            <td class="sep">:</td>
            <td class="val"><?= htmlspecialchars($gc['no_ktp_pj'] ?: '-') ?></td>
          </tr>
          <tr>
            <td class="label">No. Telepon / HP</td>
            <td class="sep">:</td>
            <td class="val"><?= htmlspecialchars($gc['no_telp_pj'] ?: '-') ?></td>
          </tr>
          <tr>
            <td class="label">Alamat Domisili</td>
            <td class="sep">:</td>
            <td class="val"><?= htmlspecialchars($gc['alamat_pj'] ?: '-') ?></td>
          </tr>
        </table>
      </div>

    </div>

    <!-- Klausul Pernyataan Persetujuan -->
    <div class="section-head">KETENTUAN & BUTIR-BUTIR PERSETUJUAN UMUM (GENERAL CONSENT)</div>

    <div class="clause-block">
      <div class="clause-title">1. PERSETUJUAN PELAYANAN DAN TINDAKAN MEDIS UMUM</div>
      <div class="clause-text">
        Saya menyetujui untuk mendapatkan pelayanan kesehatan, pemeriksaan fisik umum, pemeriksaan tanda vital, penunjang diagnostik (laboratorium/EKG jika diperlukan), tindakan non-invasif, serta asuhan keperawatan dan pengobatan rawat jalan sesuai dengan indikasi medis dan standar prosedur operasional di <?= htmlspecialchars(INSTANSI_NAMA) ?>.
      </div>
    </div>

    <div class="clause-block">
      <div class="clause-title">2. HAK DAN KEWAJIBAN PASIEN</div>
      <div class="clause-text">
        Saya telah memahami hak dan kewajiban saya sebagai pasien, termasuk hak mendapatkan informasi yang jelas mengenai rencana tindakan, dokter pemeriksa, perkiraan biaya, dan hak privasi. Saya berkewajiban memberikan informasi kesehatan secara jujur dan lengkap serta mematuhi seluruh tata tertib yang berlaku di klinik.
      </div>
    </div>

    <div class="clause-block">
      <div class="clause-title">3. PERSETUJUAN PELEPASAN INFORMASI MEDIS (PRIVASI REKAM MEDIS)</div>
      <div class="clause-text">
        Saya memberikan wewenang kepada klinik untuk membuka ringkasan rekam medis saya kepada pihak penjamin pembayaran (BPJS Kesehatan / Asuransi / Perusahaan) untuk keperluan klaim pembiayaan, serta kepada anggota keluarga berikut:
        <br>
        <strong>Keluarga yang diberi izin akses informasi medis:</strong> 
        <span style="color: #0369a1; font-weight: 600;"><?= htmlspecialchars($gc['nama_keluarga_informasi'] ?: 'Diri Sendiri / Keluarga Inti') ?></span>
      </div>
    </div>

    <div class="clause-block">
      <div class="clause-title">4. KEINGINAN PRIVASI KHUSUS</div>
      <div class="clause-text">
        Status Permintaan Privasi Khusus: <strong><?= htmlspecialchars($gc['setuju_privasi_khusus']) ?></strong>
        <?php if ($gc['setuju_privasi_khusus'] === 'Ada' && !empty($gc['detail_privasi_khusus'])): ?>
          <br><em>Detail Privasi: <?= htmlspecialchars($gc['detail_privasi_khusus']) ?></em>
        <?php endif; ?>
      </div>
    </div>

    <div class="clause-block">
      <div class="clause-title">5. TANGGUNG JAWAB BARANG PRIBADI & KETENTUAN PEMBIAYAAN</div>
      <div class="clause-text">
        Saya memahami bahwa klinik tidak bertanggung jawab atas kehilangan atau kerusakan barang-barang berharga milik pribadi yang dibawa ke lingkungan klinik. Saya menyetujui dan bersedia memenuhi segala ketentuan pembayaran atas biaya pelayanan kesehatan yang saya terima sesuai tarif yang berlaku (Penjamin: <strong><?= htmlspecialchars($gc['tipe_penjamin'] ?: 'Umum') ?></strong>).
      </div>
    </div>

    <div style="font-size: 8.5pt; color: #475569; margin-top: 6px; font-style: italic;">
      Demikian surat persetujuan umum ini dibuat dan ditandatangani secara sadar, tanpa paksaan dari pihak manapun, serta menjadi dasar pemberian pelayanan kesehatan yang sah.
    </div>

    <!-- Tanda Tangan Digital -->
    <table class="sign-section">
      <tr>
        <td colspan="2" style="text-align: right; font-size: 8.5pt; color: #475569; padding-bottom: 6px;">
          <?= htmlspecialchars(INSTANSI_KOTA) ?>, <?= tgl_indo($gc['tgl_persetujuan']) ?> pukul <?= substr($gc['jam_persetujuan'], 0, 5) ?> WIB
        </td>
      </tr>
      <tr>
        <!-- Petugas Admisi -->
        <td>
          <div class="sign-box">
            <div class="sign-title">Petugas / Saksi Admisi</div>
            <div class="sign-img-container">
              <?php if (!empty($gc['ttd_petugas'])): ?>
                <img src="<?= $gc['ttd_petugas'] ?>" class="sign-img" alt="TTD Petugas">
              <?php else: ?>
                <div class="sign-empty">Tanda Tangan Digital</div>
              <?php endif; ?>
            </div>
            <div>
              <div class="sign-name"><?= htmlspecialchars($gc['nama_petugas'] ?: 'Petugas Admisi') ?></div>
              <div class="sign-role">NIP / ID: <?= htmlspecialchars($gc['nip_petugas'] ?: '-') ?></div>
            </div>
          </div>
        </td>

        <!-- Pasien / Penanggung Jawab -->
        <td>
          <div class="sign-box">
            <div class="sign-title">Pemberi Persetujuan (Pasien / Wali)</div>
            <div class="sign-img-container">
              <?php if (!empty($gc['ttd_pasien'])): ?>
                <img src="<?= $gc['ttd_pasien'] ?>" class="sign-img" alt="TTD Pasien">
              <?php else: ?>
                <div class="sign-empty">Tanda Tangan Digital</div>
              <?php endif; ?>
            </div>
            <div>
              <div class="sign-name"><?= htmlspecialchars($gc['nama_pj'] ?: $pasien['nm_pasien']) ?></div>
              <div class="sign-role">Hubungan: <?= htmlspecialchars($gc['hubungan_pj'] ?: 'Diri Sendiri') ?></div>
            </div>
          </div>
        </td>
      </tr>
    </table>

  </div>

</body>
</html>
