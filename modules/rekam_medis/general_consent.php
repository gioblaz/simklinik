<?php
/**
 * SIMKlinik — Modul General Consent (Persetujuan Umum Pasien)
 * Dilengkapi Digital Signature Drawing Pad (Pasien/Wali & Petugas)
 */

$page_title    = 'General Consent (Persetujuan Umum)';
$active_module = 'rekam_medis';

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/header.php';

$no_rawat = sanitize($_GET['no_rawat'] ?? '');
$pasien = null;
$gc = null;

if (!empty($no_rawat)) {
    $rawat_esc = $conn->real_escape_string($no_rawat);
    $res = $conn->query("
        SELECT r.*, p.nm_pasien, p.jk, p.tgl_lahir, p.no_ktp, p.no_peserta,
               p.alamat, p.gol_darah, p.no_tlp, p.namakeluarga, p.alamatpj, p.keluarga,
               d.nm_dokter, pol.nm_poli, pj.png_jawab as nm_penjab
        FROM reg_periksa r
        JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
        LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
        LEFT JOIN poliklinik pol ON r.kd_poli = pol.kd_poli
        LEFT JOIN penjab pj ON p.kd_pj = pj.kd_pj
        WHERE r.no_rawat = '$rawat_esc'
        LIMIT 1
    ");
    if ($res && $res->num_rows > 0) {
        $pasien = $res->fetch_assoc();
        $pasien['umur'] = hitung_umur($pasien['tgl_lahir']);

        // Cek data persetujuan tersimpan
        $gc_res = $conn->query("SELECT * FROM surat_persetujuan_umum WHERE no_rawat = '$rawat_esc' LIMIT 1");
        if ($gc_res && $gc_res->num_rows > 0) {
            $gc = $gc_res->fetch_assoc();
        }
    }
}
?>

<style>
/* ─── General Consent Styles ─── */
.gc-container {
  max-width: 1200px;
  margin: 0 auto;
}
.gc-card {
  background: #ffffff;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 4px 12px rgba(0,0,0,0.04);
  margin-bottom: 24px;
  overflow: hidden;
}
.gc-card-header {
  padding: 16px 20px;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 10px;
}
.gc-card-header h3 {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
  color: #1e293b;
  display: flex;
  align-items: center;
  gap: 8px;
}
.gc-card-body {
  padding: 20px;
}

/* Patient Header Banner */
.gc-patient-banner {
  background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
  color: #ffffff;
  border-radius: 12px;
  padding: 18px 24px;
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 16px;
  box-shadow: 0 6px 16px rgba(37,99,235,0.2);
}
.gc-patient-info h2 {
  margin: 0 0 4px 0;
  font-size: 20px;
  font-weight: 800;
  letter-spacing: 0.3px;
}
.gc-patient-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  font-size: 13px;
  opacity: 0.95;
}
.gc-patient-meta span {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: rgba(255,255,255,0.15);
  padding: 3px 10px;
  border-radius: 20px;
}

/* Clause Box */
.gc-clause-item {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 14px 16px;
  margin-bottom: 12px;
  transition: all 0.2s;
}
.gc-clause-item:hover {
  border-color: #cbd5e1;
  background: #ffffff;
}
.gc-clause-header {
  font-weight: 700;
  font-size: 14px;
  color: #1e293b;
  margin-bottom: 6px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.gc-clause-desc {
  font-size: 12.5px;
  color: #475569;
  line-height: 1.5;
}

/* Drawing Pad Section */
.signature-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
  gap: 20px;
}
.signature-card {
  background: #ffffff;
  border: 1.5px solid #cbd5e1;
  border-radius: 10px;
  padding: 16px;
  display: flex;
  flex-direction: column;
  position: relative;
}
.signature-card.has-sig {
  border-color: #10b981;
}
.signature-card-title {
  font-size: 14px;
  font-weight: 700;
  color: #1e293b;
  margin-bottom: 10px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.canvas-wrapper {
  position: relative;
  width: 100%;
  height: 180px;
  background: #fafafa;
  border: 1.5px dashed #94a3b8;
  border-radius: 8px;
  overflow: hidden;
  touch-action: none;
  cursor: crosshair;
}
.canvas-wrapper canvas {
  width: 100%;
  height: 100%;
  display: block;
}
.canvas-placeholder {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  color: #94a3b8;
  font-size: 12.5px;
  pointer-events: none;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
}
.canvas-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 10px;
  gap: 6px;
  flex-wrap: wrap;
}
.color-picker-group {
  display: flex;
  gap: 6px;
  align-items: center;
}
.color-btn {
  width: 22px;
  height: 22px;
  border-radius: 50%;
  border: 2px solid transparent;
  cursor: pointer;
  transition: transform 0.15s;
}
.color-btn.active {
  transform: scale(1.15);
  border-color: #0284c7;
  box-shadow: 0 0 0 2px rgba(2,132,199,0.3);
}

.badge-sig-status {
  font-size: 11px;
  padding: 3px 8px;
  border-radius: 12px;
  font-weight: 600;
}
.badge-sig-empty {
  background: #fee2e2;
  color: #b91c1c;
}
.badge-sig-filled {
  background: #dcfce7;
  color: #15803d;
}

/* Floating Action Bar */
.gc-action-footer {
  position: sticky;
  bottom: 15px;
  z-index: 100;
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(8px);
  border: 1px solid #cbd5e1;
  border-radius: 12px;
  padding: 12px 20px;
  box-shadow: 0 6px 20px rgba(0,0,0,0.12);
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
}
</style>

<div class="gc-container">
  
  <!-- Breadcrumb & Top Bar -->
  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
    <div>
      <a href="<?= BASE_URL ?>modules/rekam_medis/index.php" class="btn btn-sm btn-outline-secondary" style="margin-right: 8px;">
        <i class="fas fa-arrow-left"></i> E-RM
      </a>
      <a href="<?= BASE_URL ?>modules/pendaftaran/index.php" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-clipboard-list"></i> Pendaftaran
      </a>
    </div>
    <div>
      <?php if (!empty($no_rawat)): ?>
        <a href="<?= BASE_URL ?>modules/rekam_medis/cetak_general_consent.php?no_rawat=<?= urlencode($no_rawat) ?>" target="_blank" class="btn btn-sm btn-outline-primary" style="font-weight: 600;">
          <i class="fas fa-print"></i> Cetak Lembar GC
        </a>
      <?php endif; ?>
    </div>
  </div>

  <?php if (empty($no_rawat) || !$pasien): ?>
    <!-- Mode Pilih Pasien jika no_rawat belum ditentukan -->
    <div class="gc-card">
      <div class="gc-card-header">
        <h3><i class="fas fa-file-signature text-primary"></i> Pilih Pasien untuk General Consent</h3>
      </div>
      <div class="gc-card-body">
        <p style="color: #64748b; font-size: 13.5px;">Silakan masukkan No. Rawat atau No. Rekam Medis pasien untuk mengisi formulir Persetujuan Umum (General Consent):</p>
        <form method="GET" action="" style="display: flex; gap: 10px; max-width: 500px; margin-top: 15px;">
          <input type="text" name="no_rawat" class="form-control" placeholder="Contoh: 2026/09/17/000001" required>
          <button type="submit" class="btn btn-primary" style="white-space: nowrap;">
            <i class="fas fa-search"></i> Buka Pasien
          </button>
        </form>
      </div>
    </div>
  <?php else: ?>

    <!-- Banner Identitas Pasien -->
    <div class="gc-patient-banner">
      <div class="gc-patient-info">
        <h2><?= htmlspecialchars($pasien['nm_pasien']) ?></h2>
        <div class="gc-patient-meta">
          <span><i class="fas fa-id-card"></i> RM: <?= htmlspecialchars($pasien['no_rkm_medis']) ?></span>
          <span><i class="fas fa-barcode"></i> Rawat: <?= htmlspecialchars($pasien['no_rawat']) ?></span>
          <span><i class="fas fa-venus-mars"></i> <?= $pasien['jk'] === 'L' ? 'Laki-laki' : 'Perempuan' ?> / <?= htmlspecialchars($pasien['umur']) ?></span>
          <span><i class="fas fa-calendar-alt"></i> Tgl: <?= tgl_indo($pasien['tgl_registrasi']) ?></span>
          <span><i class="fas fa-clinic-medical"></i> <?= htmlspecialchars($pasien['nm_poli']) ?></span>
          <span><i class="fas fa-user-md"></i> <?= htmlspecialchars($pasien['nm_dokter']) ?></span>
          <span><i class="fas fa-shield-alt"></i> <?= htmlspecialchars($pasien['nm_penjab'] ?: 'Umum') ?></span>
        </div>
      </div>
      <div>
        <?php if ($gc): ?>
          <span class="badge" style="background: #10b981; color:#fff; font-size: 13px; padding: 6px 14px; border-radius: 20px;">
            <i class="fas fa-check-circle"></i> Sudah Ditandatangani
          </span>
        <?php else: ?>
          <span class="badge" style="background: #f59e0b; color:#fff; font-size: 13px; padding: 6px 14px; border-radius: 20px;">
            <i class="fas fa-clock"></i> Belum Mengisi GC
          </span>
        <?php endif; ?>
      </div>
    </div>

    <!-- Form General Consent -->
    <form id="formGeneralConsent" autocomplete="off">
      <input type="hidden" name="no_rawat" id="gc_no_rawat" value="<?= htmlspecialchars($pasien['no_rawat']) ?>">
      <input type="hidden" name="ttd_pasien" id="input_ttd_pasien" value="<?= htmlspecialchars($gc['ttd_pasien'] ?? '') ?>">
      <input type="hidden" name="ttd_petugas" id="input_ttd_petugas" value="<?= htmlspecialchars($gc['ttd_petugas'] ?? '') ?>">

      <!-- 1. Header Dokumen & Tanggal -->
      <div class="gc-card">
        <div class="gc-card-header">
          <h3><i class="fas fa-info-circle text-primary"></i> 1. Informasi Surat & Waktu Persetujuan</h3>
          <span style="font-size: 12px; color: #64748b;">Standar Akreditasi Kemenkes RI</span>
        </div>
        <div class="gc-card-body">
          <div class="row" style="display: flex; flex-wrap: wrap; gap: 15px;">
            <div style="flex: 1; min-width: 240px;">
              <label class="form-label" style="font-weight: 600; font-size: 12.5px;">Nomor Dokumen General Consent</label>
              <input type="text" name="no_surat" id="gc_no_surat" class="form-control" 
                     value="<?= htmlspecialchars($gc['no_surat'] ?? ('GC-' . date('Ymd', strtotime($pasien['tgl_registrasi'])) . '-' . substr(preg_replace('/[^0-9]/', '', $no_rawat), -4))) ?>" 
                     placeholder="GC-YYYYMMDD-XXXX" required>
            </div>
            <div style="flex: 1; min-width: 180px;">
              <label class="form-label" style="font-weight: 600; font-size: 12.5px;">Tanggal Persetujuan</label>
              <input type="date" name="tgl_persetujuan" class="form-control" 
                     value="<?= htmlspecialchars($gc['tgl_persetujuan'] ?? ($pasien['tgl_registrasi'] ?? date('Y-m-d'))) ?>" required>
            </div>
            <div style="flex: 1; min-width: 150px;">
              <label class="form-label" style="font-weight: 600; font-size: 12.5px;">Jam Persetujuan</label>
              <input type="time" name="jam_persetujuan" class="form-control" 
                     value="<?= htmlspecialchars($gc['jam_persetujuan'] ?? ($pasien['jam_reg'] ?? date('H:i:s'))) ?>" required>
            </div>
            <div style="flex: 1; min-width: 180px;">
              <label class="form-label" style="font-weight: 600; font-size: 12.5px;">Tipe Penjamin</label>
              <input type="text" name="tipe_penjamin" class="form-control" 
                     value="<?= htmlspecialchars($gc['tipe_penjamin'] ?? ($pasien['nm_penjab'] ?? 'Umum')) ?>" required>
            </div>
          </div>
        </div>
      </div>

      <!-- 2. Identitas Pemberi Persetujuan -->
      <div class="gc-card">
        <div class="gc-card-header">
          <h3><i class="fas fa-user-edit text-primary"></i> 2. Identitas Pemberi Persetujuan (Penanggung Jawab / Pasien)</h3>
          <div style="display: flex; gap: 8px;">
            <button type="button" class="btn btn-xs btn-outline-primary" id="btnSalinPasien" style="font-size: 11.5px; border-radius: 6px;">
              <i class="fas fa-user-check"></i> Pasien Sendiri
            </button>
            <button type="button" class="btn btn-xs btn-outline-info" id="btnSalinKeluarga" style="font-size: 11.5px; border-radius: 6px;">
              <i class="fas fa-users"></i> Salin PJ / Keluarga Pasien
            </button>
          </div>
        </div>
        <div class="gc-card-body">
          <div class="row" style="display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 12px;">
            <div style="flex: 2; min-width: 260px;">
              <label class="form-label" style="font-weight: 600; font-size: 12.5px;">Nama Lengkap Yang Menyatakan <span class="text-danger">*</span></label>
              <input type="text" name="nama_pj" id="pj_nama" class="form-control" 
                     value="<?= htmlspecialchars($gc['nama_pj'] ?? $pasien['nm_pasien']) ?>" required>
            </div>
            <div style="flex: 1; min-width: 180px;">
              <label class="form-label" style="font-weight: 600; font-size: 12.5px;">Hubungan dengan Pasien <span class="text-danger">*</span></label>
              <select name="hubungan_pj" id="pj_hubungan" class="form-control" required>
                <?php
                $hub_opts = ['Diri Sendiri', 'Suami', 'Istri', 'Anak', 'Orang Tua', 'Saudara', 'Keluarga', 'Wali', 'Lain-lain'];
                $curr_hub = $gc['hubungan_pj'] ?? 'Diri Sendiri';
                foreach ($hub_opts as $ho):
                ?>
                  <option value="<?= $ho ?>" <?= $curr_hub === $ho ? 'selected' : '' ?>><?= $ho ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div style="flex: 1; min-width: 140px;">
              <label class="form-label" style="font-weight: 600; font-size: 12.5px;">Jenis Kelamin</label>
              <select name="jk_pj" id="pj_jk" class="form-control">
                <option value="L" <?= ($gc['jk_pj'] ?? $pasien['jk']) === 'L' ? 'selected' : '' ?>>Laki-laki</option>
                <option value="P" <?= ($gc['jk_pj'] ?? $pasien['jk']) === 'P' ? 'selected' : '' ?>>Perempuan</option>
              </select>
            </div>
            <div style="flex: 1; min-width: 140px;">
              <label class="form-label" style="font-weight: 600; font-size: 12.5px;">Umur / Usia</label>
              <input type="text" name="umur_pj" id="pj_umur" class="form-control" 
                     value="<?= htmlspecialchars($gc['umur_pj'] ?? $pasien['umur']) ?>" placeholder="Contoh: 32 Th">
            </div>
          </div>

          <div class="row" style="display: flex; flex-wrap: wrap; gap: 15px;">
            <div style="flex: 1; min-width: 220px;">
              <label class="form-label" style="font-weight: 600; font-size: 12.5px;">No. KTP / NIK Pemberi Persetujuan</label>
              <input type="text" name="no_ktp_pj" id="pj_ktp" class="form-control" 
                     value="<?= htmlspecialchars($gc['no_ktp_pj'] ?? $pasien['no_ktp']) ?>" placeholder="16 digit NIK">
            </div>
            <div style="flex: 1; min-width: 200px;">
              <label class="form-label" style="font-weight: 600; font-size: 12.5px;">No. Telepon / HP</label>
              <input type="text" name="no_telp_pj" id="pj_telp" class="form-control" 
                     value="<?= htmlspecialchars($gc['no_telp_pj'] ?? $pasien['no_tlp']) ?>" placeholder="08xxxxxxxx">
            </div>
            <div style="flex: 2; min-width: 300px;">
              <label class="form-label" style="font-weight: 600; font-size: 12.5px;">Alamat Domisili</label>
              <input type="text" name="alamat_pj" id="pj_alamat" class="form-control" 
                     value="<?= htmlspecialchars($gc['alamat_pj'] ?? $pasien['alamat']) ?>" placeholder="Alamat lengkap">
            </div>
          </div>
        </div>
      </div>

      <!-- 3. Poin & Klausul General Consent -->
      <div class="gc-card">
        <div class="gc-card-header">
          <h3><i class="fas fa-tasks text-primary"></i> 3. Klausul Butir-Butir Persetujuan Umum</h3>
          <span style="font-size: 12px; color: #64748b;">Harap bacakan dan jelaskan kepada pasien/wali</span>
        </div>
        <div class="gc-card-body">
          
          <!-- Klausul 1 -->
          <div class="gc-clause-item">
            <div class="gc-clause-header">
              <span><i class="fas fa-check-square text-success"></i> I. Persetujuan Pelayanan Kesehatan & Pengobatan Umum</span>
              <select name="setuju_rawat_inap_jalan" class="form-control form-control-sm" style="width: 120px;">
                <option value="Setuju" <?= ($gc['setuju_rawat_inap_jalan'] ?? 'Setuju') === 'Setuju' ? 'selected' : '' ?>>Setuju</option>
                <option value="Tidak" <?= ($gc['setuju_rawat_inap_jalan'] ?? '') === 'Tidak' ? 'selected' : '' ?>>Tidak</option>
              </select>
            </div>
            <div class="gc-clause-desc">
              Memberikan persetujuan untuk dilakukan pemeriksaan fisik rutin, pemeriksaan tanda-tanda vital, penunjang diagnostik standar, pemasangan infus/injeksi obat yang diperlukan, serta asuhan keperawatan dan tindakan medis rawat jalan sesuai indikasi dokter.
            </div>
          </div>

          <!-- Klausul 2 -->
          <div class="gc-clause-item">
            <div class="gc-clause-header">
              <span><i class="fas fa-check-square text-success"></i> II. Hak dan Kewajiban Pasien</span>
              <select name="setuju_hak_kewajiban" class="form-control form-control-sm" style="width: 120px;">
                <option value="Setuju" <?= ($gc['setuju_hak_kewajiban'] ?? 'Setuju') === 'Setuju' ? 'selected' : '' ?>>Setuju</option>
                <option value="Tidak" <?= ($gc['setuju_hak_kewajiban'] ?? '') === 'Tidak' ? 'selected' : '' ?>>Tidak</option>
              </select>
            </div>
            <div class="gc-clause-desc">
              Menyatakan telah memahami hak sebagai pasien (termasuk penjelasan medis, kerahasiaan data) serta kewajiban memberikan informasi riwayat kesehatan secara jujur dan mematuhi tata tertib operasional klinik.
            </div>
          </div>

          <!-- Klausul 3 -->
          <div class="gc-clause-item">
            <div class="gc-clause-header">
              <span><i class="fas fa-shield-alt text-primary"></i> III. Pelepasan Informasi Medis & Akses Keluarga</span>
              <select name="setuju_pelepasan_informasi" class="form-control form-control-sm" style="width: 120px;">
                <option value="Setuju" <?= ($gc['setuju_pelepasan_informasi'] ?? 'Setuju') === 'Setuju' ? 'selected' : '' ?>>Setuju</option>
                <option value="Tidak" <?= ($gc['setuju_pelepasan_informasi'] ?? '') === 'Tidak' ? 'selected' : '' ?>>Tidak</option>
              </select>
            </div>
            <div class="gc-clause-desc" style="margin-bottom: 8px;">
              Memberi wewenang kepada klinik untuk memberikan informasi medis rekam medis kepada penjamin pembayaran (BPJS/Asuransi) serta anggota keluarga yang ditunjuk.
            </div>
            <div>
              <label class="form-label" style="font-size: 12px; font-weight: 600; color: #1e293b;">Nama Anggota Keluarga yang Diizinkan Menerima Informasi Medis Pasien:</label>
              <input type="text" name="nama_keluarga_informasi" class="form-control form-control-sm" 
                     value="<?= htmlspecialchars($gc['nama_keluarga_informasi'] ?? ($pasien['namakeluarga'] ?: 'Diri Sendiri / Keluarga Inti')) ?>" 
                     placeholder="Sebutkan nama-nama keluarga yang diberi kuasa (contoh: Suami, Istri, Ibu, dsb)">
            </div>
          </div>

          <!-- Klausul 4 -->
          <div class="gc-clause-item">
            <div class="gc-clause-header">
              <span><i class="fas fa-user-lock text-info"></i> IV. Keinginan Privasi Khusus Pasien</span>
              <select name="setuju_privasi_khusus" id="sel_privasi_khusus" class="form-control form-control-sm" style="width: 140px;" onchange="togglePrivasiField()">
                <option value="Tidak Ada" <?= ($gc['setuju_privasi_khusus'] ?? 'Tidak Ada') === 'Tidak Ada' ? 'selected' : '' ?>>Tidak Ada</option>
                <option value="Ada" <?= ($gc['setuju_privasi_khusus'] ?? '') === 'Ada' ? 'selected' : '' ?>>Ada Permintaan</option>
              </select>
            </div>
            <div id="box_detail_privasi" style="display: <?= ($gc['setuju_privasi_khusus'] ?? '') === 'Ada' ? 'block' : 'none' ?>; margin-top: 8px;">
              <input type="text" name="detail_privasi_khusus" class="form-control form-control-sm" 
                     value="<?= htmlspecialchars($gc['detail_privasi_khusus'] ?? '') ?>" 
                     placeholder="Jelaskan permintaan privasi khusus (misal: tidak ingin dikunjungi pihak tertentu, dsb)">
            </div>
          </div>

          <!-- Klausul 5 -->
          <div class="gc-clause-item">
            <div class="gc-clause-header">
              <span><i class="fas fa-wallet text-warning"></i> V. Tanggung Jawab Barang Bawaan & Pembiayaan</span>
              <select name="setuju_pembayaran" class="form-control form-control-sm" style="width: 120px;">
                <option value="Setuju" <?= ($gc['setuju_pembayaran'] ?? 'Setuju') === 'Setuju' ? 'selected' : '' ?>>Setuju</option>
                <option value="Tidak" <?= ($gc['setuju_pembayaran'] ?? '') === 'Tidak' ? 'selected' : '' ?>>Tidak</option>
              </select>
            </div>
            <div class="gc-clause-desc">
              Memahami bahwa klinik tidak bertanggung jawab atas barang pribadi yang hilang dan menyetujui kewajiban pelunasan biaya perawatan sesuai penjaminan yang dipilih.
            </div>
          </div>

        </div>
      </div>

      <!-- 4. Digital Signature Drawing Pad -->
      <div class="gc-card">
        <div class="gc-card-header">
          <h3><i class="fas fa-signature text-primary"></i> 4. Tanda Tangan Digital (Drawing Pad)</h3>
          <span style="font-size: 12px; color: #64748b;">Gunakan jari/stylus di touchscreen atau mouse di PC</span>
        </div>
        <div class="gc-card-body">
          
          <div class="signature-grid">
            
            <!-- Pad 1: Pasien / Penanggung Jawab -->
            <div class="signature-card <?= !empty($gc['ttd_pasien']) ? 'has-sig' : '' ?>" id="cardSigPasien">
              <div class="signature-card-title">
                <span><i class="fas fa-pen-nib text-primary"></i> Tanda Tangan Pasien / Wali</span>
                <span class="badge-sig-status <?= !empty($gc['ttd_pasien']) ? 'badge-sig-filled' : 'badge-sig-empty' ?>" id="statusSigPasien">
                  <?= !empty($gc['ttd_pasien']) ? '✓ Tanda Tangan Terisi' : 'Belum Ditandatangani' ?>
                </span>
              </div>

              <!-- Canvas Drawing Pad Pasien -->
              <div class="canvas-wrapper" id="wrapPadPasien">
                <canvas id="padPasien"></canvas>
                <div class="canvas-placeholder" id="phPadPasien" style="display: <?= !empty($gc['ttd_pasien']) ? 'none' : 'flex' ?>;">
                  <i class="fas fa-signature" style="font-size: 28px; opacity: 0.4;"></i>
                  <span>Silakan bubuhkan tanda tangan di sini</span>
                </div>
              </div>

              <!-- Canvas Toolbar -->
              <div class="canvas-toolbar">
                <div class="color-picker-group">
                  <span style="font-size: 11px; color: #64748b; margin-right: 2px;">Tinta:</span>
                  <button type="button" class="color-btn active" style="background: #1e3a8a;" data-pad="pasien" data-color="#1e3a8a" title="Biru Tua"></button>
                  <button type="button" class="color-btn" style="background: #111827;" data-pad="pasien" data-color="#111827" title="Hitam"></button>
                </div>
                <div style="display: flex; gap: 4px;">
                  <button type="button" class="btn btn-xs btn-outline-secondary" onclick="undoCanvas('pasien')" title="Undo Goresan">
                    <i class="fas fa-undo"></i> Undo
                  </button>
                  <button type="button" class="btn btn-xs btn-outline-danger" onclick="clearCanvas('pasien')" title="Hapus Canvas">
                    <i class="fas fa-trash-alt"></i> Bersihkan
                  </button>
                </div>
              </div>

              <div style="margin-top: 10px; font-size: 12px; color: #475569; text-align: center;">
                Pemberi Persetujuan: <strong id="lblPemberiPersetujuan"><?= htmlspecialchars($gc['nama_pj'] ?? $pasien['nm_pasien']) ?></strong>
              </div>
            </div>

            <!-- Pad 2: Petugas Admisi / Saksi -->
            <div class="signature-card <?= !empty($gc['ttd_petugas']) ? 'has-sig' : '' ?>" id="cardSigPetugas">
              <div class="signature-card-title">
                <span><i class="fas fa-user-shield text-info"></i> Tanda Tangan Petugas Admisi / Saksi</span>
                <span class="badge-sig-status <?= !empty($gc['ttd_petugas']) ? 'badge-sig-filled' : 'badge-sig-empty' ?>" id="statusSigPetugas">
                  <?= !empty($gc['ttd_petugas']) ? '✓ Tanda Tangan Terisi' : 'Belum Ditandatangani' ?>
                </span>
              </div>

              <!-- Canvas Drawing Pad Petugas -->
              <div class="canvas-wrapper" id="wrapPadPetugas">
                <canvas id="padPetugas"></canvas>
                <div class="canvas-placeholder" id="phPadPetugas" style="display: <?= !empty($gc['ttd_petugas']) ? 'none' : 'flex' ?>;">
                  <i class="fas fa-signature" style="font-size: 28px; opacity: 0.4;"></i>
                  <span>Silakan bubuhkan tanda tangan di sini</span>
                </div>
              </div>

              <!-- Canvas Toolbar -->
              <div class="canvas-toolbar">
                <div class="color-picker-group">
                  <span style="font-size: 11px; color: #64748b; margin-right: 2px;">Tinta:</span>
                  <button type="button" class="color-btn active" style="background: #1e3a8a;" data-pad="petugas" data-color="#1e3a8a" title="Biru Tua"></button>
                  <button type="button" class="color-btn" style="background: #111827;" data-pad="petugas" data-color="#111827" title="Hitam"></button>
                </div>
                <div style="display: flex; gap: 4px;">
                  <button type="button" class="btn btn-xs btn-outline-secondary" onclick="undoCanvas('petugas')" title="Undo Goresan">
                    <i class="fas fa-undo"></i> Undo
                  </button>
                  <button type="button" class="btn btn-xs btn-outline-danger" onclick="clearCanvas('petugas')" title="Hapus Canvas">
                    <i class="fas fa-trash-alt"></i> Bersihkan
                  </button>
                </div>
              </div>

              <div style="margin-top: 10px; display: flex; gap: 10px;">
                <div style="flex: 1;">
                  <label class="form-label" style="font-size: 11px; margin-bottom: 2px; font-weight: 600;">Nama Petugas</label>
                  <input type="text" name="nama_petugas" class="form-control form-control-sm" 
                         value="<?= htmlspecialchars($gc['nama_petugas'] ?? ($_SESSION['nama'] ?? 'Petugas Admisi')) ?>" required>
                </div>
                <div style="flex: 1;">
                  <label class="form-label" style="font-size: 11px; margin-bottom: 2px; font-weight: 600;">NIP / ID Petugas</label>
                  <input type="text" name="nip_petugas" class="form-control form-control-sm" 
                         value="<?= htmlspecialchars($gc['nip_petugas'] ?? ($_SESSION['user_id'] ?? '-')) ?>">
                </div>
              </div>

            </div>

          </div>

        </div>
      </div>

      <!-- Sticky Action Footer Bar -->
      <div class="gc-action-footer">
        <div>
          <button type="button" class="btn btn-outline-danger" id="btnHapusGC" <?= !$gc ? 'style="display:none;"' : '' ?> onclick="hapusGeneralConsent()">
            <i class="fas fa-trash"></i> Hapus GC
          </button>
        </div>
        <div style="display: flex; gap: 10px;">
          <a href="<?= BASE_URL ?>modules/rekam_medis/cetak_general_consent.php?no_rawat=<?= urlencode($no_rawat) ?>" target="_blank" class="btn btn-secondary" style="font-weight: 600;">
            <i class="fas fa-print"></i> Cetak Dokumen
          </a>
          <button type="submit" class="btn btn-primary" id="btnSimpanGC" style="font-weight: 700; padding: 8px 24px;">
            <i class="fas fa-save"></i> Simpan General Consent
          </button>
        </div>
      </div>

    </form>

  <?php endif; ?>

</div>

<!-- Data Pasien Objek untuk Quick Copy JS -->
<script>
const DATA_PASIEN = <?= json_encode($pasien ?? []) ?>;
const DATA_EXISTING_GC = <?= json_encode($gc ?? []) ?>;

// ─── Drawing Pad Engine ───
const pads = {
  pasien: {
    canvas: null,
    ctx: null,
    isDrawing: false,
    strokes: [],
    currentStroke: [],
    color: '#1e3a8a',
    lineWidth: 2.5,
    hasContent: false,
    existingImg: '<?= $gc['ttd_pasien'] ?? '' ?>'
  },
  petugas: {
    canvas: null,
    ctx: null,
    isDrawing: false,
    strokes: [],
    currentStroke: [],
    color: '#1e3a8a',
    lineWidth: 2.5,
    hasContent: false,
    existingImg: '<?= $gc['ttd_petugas'] ?? '' ?>'
  }
};

function initPad(key) {
  const pad = pads[key];
  const canvasId = key === 'pasien' ? 'padPasien' : 'padPetugas';
  const canvas = document.getElementById(canvasId);
  if (!canvas) return;

  pad.canvas = canvas;
  pad.ctx = canvas.getContext('2d');

  // Handle Resize & Retina sharp rendering
  function resizeCanvas() {
    const rect = canvas.getBoundingClientRect();
    const dpr = window.devicePixelRatio || 2;
    canvas.width = rect.width * dpr;
    canvas.height = rect.height * dpr;
    pad.ctx.scale(dpr, dpr);
    redrawStrokes(key);
  }

  resizeCanvas();
  window.addEventListener('resize', resizeCanvas);

  // Jika ada existing ttd image, render ke canvas
  if (pad.existingImg) {
    const img = new Image();
    img.crossOrigin = 'anonymous';
    img.onload = function() {
      const rect = canvas.getBoundingClientRect();
      pad.ctx.drawImage(img, 0, 0, rect.width, rect.height);
      pad.hasContent = true;
      updateStatusBadge(key, true);
    };
    img.src = pad.existingImg;
  }

  // Pointer & Touch Events
  function getPos(e) {
    const rect = canvas.getBoundingClientRect();
    let clientX = e.clientX;
    let clientY = e.clientY;
    if (e.touches && e.touches.length > 0) {
      clientX = e.touches[0].clientX;
      clientY = e.touches[0].clientY;
    }
    return {
      x: clientX - rect.left,
      y: clientY - rect.top
    };
  }

  function startDrawing(e) {
    e.preventDefault();
    pad.isDrawing = true;
    const pos = getPos(e);
    pad.currentStroke = [{ x: pos.x, y: pos.y, color: pad.color, width: pad.lineWidth }];
    
    // Hide placeholder
    const ph = document.getElementById(key === 'pasien' ? 'phPadPasien' : 'phPadPetugas');
    if (ph) ph.style.display = 'none';
  }

  function draw(e) {
    if (!pad.isDrawing) return;
    e.preventDefault();
    const pos = getPos(e);
    pad.currentStroke.push({ x: pos.x, y: pos.y, color: pad.color, width: pad.lineWidth });

    renderCurrentStroke(key);
  }

  function stopDrawing(e) {
    if (!pad.isDrawing) return;
    pad.isDrawing = false;
    if (pad.currentStroke.length > 0) {
      pad.strokes.push([...pad.currentStroke]);
      pad.currentStroke = [];
      pad.hasContent = true;
      updateStatusBadge(key, true);
    }
  }

  // Mouse / Pointer
  canvas.addEventListener('mousedown', startDrawing);
  window.addEventListener('mousemove', draw);
  window.addEventListener('mouseup', stopDrawing);

  // Touch
  canvas.addEventListener('touchstart', startDrawing, { passive: false });
  window.addEventListener('touchmove', draw, { passive: false });
  window.addEventListener('touchend', stopDrawing, { passive: false });
}

function renderCurrentStroke(key) {
  const pad = pads[key];
  const pts = pad.currentStroke;
  if (pts.length < 2) return;

  const ctx = pad.ctx;
  ctx.strokeStyle = pad.color;
  ctx.lineWidth = pad.lineWidth;
  ctx.lineCap = 'round';
  ctx.lineJoin = 'round';

  ctx.beginPath();
  const p1 = pts[pts.length - 2];
  const p2 = pts[pts.length - 1];
  ctx.moveTo(p1.x, p1.y);
  ctx.lineTo(p2.x, p2.y);
  ctx.stroke();
}

function redrawStrokes(key) {
  const pad = pads[key];
  const ctx = pad.ctx;
  const rect = pad.canvas.getBoundingClientRect();
  ctx.clearRect(0, 0, rect.width, rect.height);

  // Re-render strokes
  pad.strokes.forEach(stroke => {
    if (stroke.length < 2) return;
    ctx.beginPath();
    ctx.strokeStyle = stroke[0].color || pad.color;
    ctx.lineWidth = stroke[0].width || pad.lineWidth;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';

    ctx.moveTo(stroke[0].x, stroke[0].y);
    for (let i = 1; i < stroke.length; i++) {
      ctx.lineTo(stroke[i].x, stroke[i].y);
    }
    ctx.stroke();
  });
}

function clearCanvas(key) {
  const pad = pads[key];
  pad.strokes = [];
  pad.currentStroke = [];
  pad.hasContent = false;
  pad.existingImg = '';
  
  const rect = pad.canvas.getBoundingClientRect();
  pad.ctx.clearRect(0, 0, rect.width, rect.height);

  const ph = document.getElementById(key === 'pasien' ? 'phPadPasien' : 'phPadPetugas');
  if (ph) ph.style.display = 'flex';

  document.getElementById(key === 'pasien' ? 'input_ttd_pasien' : 'input_ttd_petugas').value = '';
  updateStatusBadge(key, false);
}

function undoCanvas(key) {
  const pad = pads[key];
  if (pad.strokes.length > 0) {
    pad.strokes.pop();
    redrawStrokes(key);
    if (pad.strokes.length === 0 && !pad.existingImg) {
      pad.hasContent = false;
      const ph = document.getElementById(key === 'pasien' ? 'phPadPasien' : 'phPadPetugas');
      if (ph) ph.style.display = 'flex';
      updateStatusBadge(key, false);
    }
  }
}

function updateStatusBadge(key, filled) {
  const badge = document.getElementById(key === 'pasien' ? 'statusSigPasien' : 'statusSigPetugas');
  const card = document.getElementById(key === 'pasien' ? 'cardSigPasien' : 'cardSigPetugas');
  if (badge && card) {
    if (filled) {
      badge.className = 'badge-sig-status badge-sig-filled';
      badge.textContent = '✓ Tanda Tangan Terisi';
      card.classList.add('has-sig');
    } else {
      badge.className = 'badge-sig-status badge-sig-empty';
      badge.textContent = 'Belum Ditandatangani';
      card.classList.remove('has-sig');
    }
  }
}

function togglePrivasiField() {
  const val = document.getElementById('sel_privasi_khusus').value;
  const box = document.getElementById('box_detail_privasi');
  if (box) {
    box.style.display = (val === 'Ada') ? 'block' : 'none';
  }
}

// ─── Document Ready ───
document.addEventListener('DOMContentLoaded', function() {
  if (document.getElementById('padPasien')) initPad('pasien');
  if (document.getElementById('padPetugas')) initPad('petugas');

  // Color Pickers
  document.querySelectorAll('.color-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      const padKey = this.dataset.pad;
      const color = this.dataset.color;
      pads[padKey].color = color;
      
      this.parentElement.querySelectorAll('.color-btn').forEach(b => b.classList.remove('active'));
      this.classList.add('active');
    });
  });

  // Salin Data Pasien Sendiri
  const btnSalinPasien = document.getElementById('btnSalinPasien');
  if (btnSalinPasien) {
    btnSalinPasien.addEventListener('click', function() {
      if (!DATA_PASIEN || !DATA_PASIEN.nm_pasien) return;
      document.getElementById('pj_nama').value = DATA_PASIEN.nm_pasien;
      document.getElementById('pj_hubungan').value = 'Diri Sendiri';
      document.getElementById('pj_jk').value = DATA_PASIEN.jk || 'L';
      document.getElementById('pj_umur').value = DATA_PASIEN.umur || '';
      document.getElementById('pj_ktp').value = DATA_PASIEN.no_ktp || '';
      document.getElementById('pj_telp').value = DATA_PASIEN.no_tlp || '';
      document.getElementById('pj_alamat').value = DATA_PASIEN.alamat || '';
      document.getElementById('lblPemberiPersetujuan').textContent = DATA_PASIEN.nm_pasien;
    });
  }

  // Salin Penanggung Jawab / Keluarga Pasien
  const btnSalinKeluarga = document.getElementById('btnSalinKeluarga');
  if (btnSalinKeluarga) {
    btnSalinKeluarga.addEventListener('click', function() {
      if (!DATA_PASIEN) return;
      if (DATA_PASIEN.namakeluarga) {
        document.getElementById('pj_nama').value = DATA_PASIEN.namakeluarga;
        document.getElementById('lblPemberiPersetujuan').textContent = DATA_PASIEN.namakeluarga;
      }
      if (DATA_PASIEN.keluarga) {
        const hubSel = document.getElementById('pj_hubungan');
        let found = false;
        for (let i = 0; i < hubSel.options.length; i++) {
          if (hubSel.options[i].value.toLowerCase() === DATA_PASIEN.keluarga.toLowerCase()) {
            hubSel.selectedIndex = i;
            found = true;
            break;
          }
        }
        if (!found) hubSel.value = 'Keluarga';
      }
      if (DATA_PASIEN.alamatpj) {
        document.getElementById('pj_alamat').value = DATA_PASIEN.alamatpj;
      }
      if (DATA_PASIEN.no_tlp) {
        document.getElementById('pj_telp').value = DATA_PASIEN.no_tlp;
      }
    });
  }

  // Update label nama pemberi persetujuan saat input diubah
  const pjNamaInput = document.getElementById('pj_nama');
  if (pjNamaInput) {
    pjNamaInput.addEventListener('input', function() {
      const lbl = document.getElementById('lblPemberiPersetujuan');
      if (lbl) lbl.textContent = this.value || '-';
    });
  }

  // Submit Form General Consent
  const form = document.getElementById('formGeneralConsent');
  if (form) {
    form.addEventListener('submit', function(e) {
      e.preventDefault();

      // Export Canvas Data URL to hidden inputs jika ada coretan baru
      if (pads.pasien.hasContent && pads.pasien.strokes.length > 0) {
        document.getElementById('input_ttd_pasien').value = pads.pasien.canvas.toDataURL('image/png');
      }
      if (pads.petugas.hasContent && pads.petugas.strokes.length > 0) {
        document.getElementById('input_ttd_petugas').value = pads.petugas.canvas.toDataURL('image/png');
      }

      const btnSimpan = document.getElementById('btnSimpanGC');
      const origHtml = btnSimpan.innerHTML;
      btnSimpan.disabled = true;
      btnSimpan.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';

      const formData = new FormData(form);
      formData.append('action', 'simpan_general_consent');

      fetch('<?= BASE_URL ?>modules/rekam_medis/ajax.php', {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
      })
      .then(res => res.json())
      .then(res => {
        btnSimpan.disabled = false;
        btnSimpan.innerHTML = origHtml;

        if (res.success) {
          alert('Berhasil! ' + res.message);
          const btnHapus = document.getElementById('btnHapusGC');
          if (btnHapus) btnHapus.style.display = 'inline-block';
        } else {
          alert('Gagal: ' + (res.message || 'Terjadi kesalahan sistem'));
        }
      })
      .catch(err => {
        btnSimpan.disabled = false;
        btnSimpan.innerHTML = origHtml;
        console.error(err);
        alert('Terjadi kesalahan jaringan atau server');
      });
    });
  }
});

function hapusGeneralConsent() {
  if (!confirm('Apakah Anda yakin ingin menghapus dokumen General Consent ini?')) return;
  const noRawat = document.getElementById('gc_no_rawat').value;

  const fd = new FormData();
  fd.append('action', 'hapus_general_consent');
  fd.append('no_rawat', noRawat);

  fetch('<?= BASE_URL ?>modules/rekam_medis/ajax.php', {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    body: fd
  })
  .then(res => res.json())
  .then(res => {
    if (res.success) {
      alert('Dokumen General Consent berhasil dihapus.');
      window.location.reload();
    } else {
      alert('Gagal: ' + res.message);
    }
  })
  .catch(err => {
    console.error(err);
    alert('Terjadi kesalahan saat menghapus data.');
  });
}
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
