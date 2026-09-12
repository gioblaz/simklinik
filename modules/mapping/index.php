<?php
/**
 * SIMKlinik — Modul Mapping Terpadu (PCare BPJS & Satu Sehat Kemenkes)
 * Menyatukan pemadanan Dokter, Poli, Obat, Tindakan, Praktisi IHS, Lokasi, dan LOINC Lab
 */

$page_title    = 'Mapping Bridging (PCare & Satu Sehat)';
$active_module = 'mapping';

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_module_access('mapping');
require_once dirname(__DIR__, 2) . '/includes/pcare_service.php';
require_once dirname(__DIR__, 2) . '/includes/satusehat_service.php';

// Active Sub-tab selector
$current_tab = sanitize($_GET['tab'] ?? 'pcare_dokter');

// ─── DATA QUERY SECTION ──────────────────────────────────────────

// 1. Data Dokter untuk Mapping PCare & Satu Sehat
$res_dok = $conn->query("
    SELECT d.kd_dokter, d.nm_dokter, COALESCE(peg.no_ktp, '') as no_ktp, d.jk, d.kd_sps, s.nm_sps,
           mp.kd_dokter_pcare, mp.nm_dokter_pcare,
           ms.practitioner_id
    FROM dokter d
    LEFT JOIN pegawai peg ON d.kd_dokter = peg.nik
    LEFT JOIN spesialis s ON d.kd_sps = s.kd_sps
    LEFT JOIN maping_dokter_pcare mp ON d.kd_dokter = mp.kd_dokter
    LEFT JOIN mlite_satu_sehat_mapping_praktisi ms ON d.kd_dokter = ms.kd_dokter
    WHERE d.status = '1'
    ORDER BY d.nm_dokter ASC
");
$list_dokter = [];
if ($res_dok) while ($r = $res_dok->fetch_assoc()) $list_dokter[] = $r;

// 2. Data Poliklinik untuk Mapping PCare & Satu Sehat
$res_poli = $conn->query("
    SELECT p.kd_poli, p.nm_poli,
           mpp.kd_poli_pcare, mpp.nm_poli_pcare,
           sl.id_organisasi_satusehat, sl.id_lokasi_satusehat, sl.longitude, sl.latitude, sl.altittude
    FROM poliklinik p
    LEFT JOIN maping_poliklinik_pcare mpp ON p.kd_poli = mpp.kd_poli_rs
    LEFT JOIN satu_sehat_mapping_lokasi_ralan sl ON p.kd_poli = sl.kd_poli
    WHERE p.status = '1'
    ORDER BY p.nm_poli ASC
");
$list_poli = [];
if ($res_poli) while ($r = $res_poli->fetch_assoc()) $list_poli[] = $r;

// 3. Data Obat untuk Mapping PCare (DPHO) & Satu Sehat (KFA)
$q_obat = sanitize($_GET['q_obat'] ?? '');
$page_obat = max(1, (int)($_GET['p_obat'] ?? 1));
$limit_obat = 20;
$offset_obat = ($page_obat - 1) * $limit_obat;

$where_obat = "db.status = '1'";
if (!empty($q_obat)) {
    $qo_esc = $conn->real_escape_string($q_obat);
    $where_obat .= " AND (db.kode_brng LIKE '%$qo_esc%' OR db.nama_brng LIKE '%$qo_esc%' OR mo.nama_brng_pcare LIKE '%$qo_esc%' OR so.obat_display LIKE '%$qo_esc%')";
}
$tot_obat = ($conn->query("SELECT COUNT(*) as c FROM databarang db LEFT JOIN maping_obat_pcare mo ON db.kode_brng = mo.kode_brng LEFT JOIN satu_sehat_mapping_obat so ON db.kode_brng = so.kode_brng WHERE $where_obat")->fetch_assoc()['c'] ?? 0);
$total_pages_obat = max(1, ceil($tot_obat / $limit_obat));

$res_obat = $conn->query("
    SELECT db.kode_brng, db.nama_brng, db.kode_sat, k.nama as nm_kategori,
           mo.kode_brng_pcare, mo.nama_brng_pcare,
           so.obat_code, so.obat_display, so.form_code, so.form_display, so.route_code, so.route_display
    FROM databarang db
    LEFT JOIN kategori_barang k ON db.kode_kategori = k.kode
    LEFT JOIN maping_obat_pcare mo ON db.kode_brng = mo.kode_brng
    LEFT JOIN satu_sehat_mapping_obat so ON db.kode_brng = so.kode_brng
    WHERE $where_obat
    ORDER BY db.nama_brng ASC
    LIMIT $limit_obat OFFSET $offset_obat
");
$list_obat = [];
if ($res_obat) while ($r = $res_obat->fetch_assoc()) $list_obat[] = $r;

// 4. Data Tindakan untuk Mapping PCare
$q_tindakan = sanitize($_GET['q_tindakan'] ?? '');
$where_tind = "jp.status = '1'";
if (!empty($q_tindakan)) {
    $qt_esc = $conn->real_escape_string($q_tindakan);
    $where_tind .= " AND (jp.kd_jenis_prw LIKE '%$qt_esc%' OR jp.nm_perawatan LIKE '%$qt_esc%')";
}
$res_tindakan = $conn->query("
    SELECT jp.kd_jenis_prw, jp.nm_perawatan, jp.total_byrdr, jp.total_byrpr,
           k.nm_kategori,
           mt.kd_tindakan_pcare, mt.nm_tindakan_pcare
    FROM jns_perawatan jp
    LEFT JOIN kategori_perawatan k ON jp.kd_kategori = k.kd_kategori
    LEFT JOIN maping_tindakan_pcare mt ON jp.kd_jenis_prw = mt.kd_jenis_prw
    WHERE $where_tind
    ORDER BY jp.nm_perawatan ASC
    LIMIT 100
");
$list_tindakan = [];
if ($res_tindakan) while ($r = $res_tindakan->fetch_assoc()) $list_tindakan[] = $r;

// 5. Data Laboratorium LOINC untuk Mapping Satu Sehat
$res_lab = $conn->query("
    SELECT j.kd_jenis_prw, j.nm_perawatan,
           t.id_template, t.Pemeriksaan as nm_pemeriksaan, t.satuan, t.nilai_rujukan_ld,
           ml.code as loinc_code, ml.system as loinc_system, ml.display as loinc_display
    FROM jns_perawatan_lab j
    LEFT JOIN template_laboratorium t ON j.kd_jenis_prw = t.kd_jenis_prw
    LEFT JOIN mlite_satu_sehat_mapping_lab ml ON (t.id_template = ml.id_template OR (t.id_template IS NULL AND j.kd_jenis_prw = ml.kd_jenis_prw))
    WHERE j.status = '1'
    ORDER BY j.nm_perawatan ASC, t.urut ASC
    LIMIT 150
");
$list_lab = [];
if ($res_lab) while ($r = $res_lab->fetch_assoc()) $list_lab[] = $r;

// Hitung Statistik Mapping
$stat_pcare_dok_mapped  = count(array_filter($list_dokter, fn($d) => !empty($d['kd_dokter_pcare'])));
$stat_pcare_poli_mapped = count(array_filter($list_poli, fn($p) => !empty($p['kd_poli_pcare'])));
$stat_ss_dok_mapped     = count(array_filter($list_dokter, fn($d) => !empty($d['practitioner_id'])));
$stat_ss_poli_mapped    = count(array_filter($list_poli, fn($p) => !empty($p['id_lokasi_satusehat'])));

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="container-fluid" style="padding:16px 20px;">

  <!-- ─── Page Header ──────────────────────────────────────── -->
  <div class="page-header" style="margin-bottom:16px;">
    <div>
      <div style="display:flex;align-items:center;gap:10px;">
        <div style="width:40px;height:40px;background:linear-gradient(135deg, #0891b2, #0e7490);border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:19px;box-shadow:0 3px 8px rgba(8,145,178,0.25);">
          <i class="fas fa-shuffle"></i>
        </div>
        <div>
          <h1 class="page-title" style="margin:0;font-size:19px;font-weight:800;color:#0f172a;">Mapping Bridging (PCare &amp; Satu Sehat)</h1>
          <p class="page-subtitle" style="margin:2px 0 0;font-size:12.5px;color:#64748b;">
            Pusat Pemadanan Master Data SIMKlinik dengan Standar Resmi BPJS PCare &amp; Satu Sehat Kemenkes RI
          </p>
        </div>
      </div>
    </div>
    <div class="page-actions" style="display:flex;gap:8px;flex-wrap:wrap;">
      <a href="<?= BASE_URL ?>modules/pcare/index.php" class="btn btn-sm btn-outline-info" style="font-size:12px;">
        <i class="fas fa-hospital"></i> Menu PCare
      </a>
      <a href="<?= BASE_URL ?>modules/satu_sehat/index.php" class="btn btn-sm btn-outline-danger" style="font-size:12px;">
        <i class="fas fa-shield-heart"></i> Menu Satu Sehat
      </a>
      <a href="<?= BASE_URL ?>modules/settings/bridging.php" class="btn btn-sm btn-outline" style="font-size:12px;">
        <i class="fas fa-cog"></i> Pengaturan Kredensial
      </a>
    </div>
  </div>

  <!-- ─── Summary Badges ───────────────────────────────────── -->
  <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:12px;margin-bottom:16px;">
    <div class="card" style="margin:0;padding:12px 16px;border-left:4px solid #0891b2;background:#f8fafc;">
      <div style="font-size:11px;font-weight:700;color:#0891b2;text-transform:uppercase;">Dokter PCare BPJS</div>
      <div style="font-size:18px;font-weight:800;color:#0f172a;margin-top:2px;">
        <?= $stat_pcare_dok_mapped ?> <span style="font-size:12px;font-weight:500;color:#64748b;">/ <?= count($list_dokter) ?> Terpetakan</span>
      </div>
    </div>
    <div class="card" style="margin:0;padding:12px 16px;border-left:4px solid #0284c7;background:#f8fafc;">
      <div style="font-size:11px;font-weight:700;color:#0284c7;text-transform:uppercase;">Poliklinik PCare BPJS</div>
      <div style="font-size:18px;font-weight:800;color:#0f172a;margin-top:2px;">
        <?= $stat_pcare_poli_mapped ?> <span style="font-size:12px;font-weight:500;color:#64748b;">/ <?= count($list_poli) ?> Terpetakan</span>
      </div>
    </div>
    <div class="card" style="margin:0;padding:12px 16px;border-left:4px solid #e11d48;background:#f8fafc;">
      <div style="font-size:11px;font-weight:700;color:#e11d48;text-transform:uppercase;">Praktisi Satu Sehat (IHS)</div>
      <div style="font-size:18px;font-weight:800;color:#0f172a;margin-top:2px;">
        <?= $stat_ss_dok_mapped ?> <span style="font-size:12px;font-weight:500;color:#64748b;">/ <?= count($list_dokter) ?> Terpetakan</span>
      </div>
    </div>
    <div class="card" style="margin:0;padding:12px 16px;border-left:4px solid #7c3aed;background:#f8fafc;">
      <div style="font-size:11px;font-weight:700;color:#7c3aed;text-transform:uppercase;">Lokasi Satu Sehat (FHIR)</div>
      <div style="font-size:18px;font-weight:800;color:#0f172a;margin-top:2px;">
        <?= $stat_ss_poli_mapped ?> <span style="font-size:12px;font-weight:500;color:#64748b;">/ <?= count($list_poli) ?> Terpetakan</span>
      </div>
    </div>
  </div>

  <!-- ─── Unified Sub-Menu Navigation Tabs ─────────────────── -->
  <div class="card" style="padding:0;overflow:hidden;margin-bottom:16px;">
    
    <!-- Level 1: Category Header (PCare BPJS vs Satu Sehat) -->
    <div style="background:#f1f5f9;padding:6px 14px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #e2e8f0;flex-wrap:wrap;gap:8px;">
      <div style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:700;color:#475569;">
        <i class="fas fa-layer-group text-primary"></i> PILIH SUB MENU MAPPING:
      </div>
      <div style="font-size:11.5px;color:#64748b;">
        <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#10b981;margin-right:4px;"></span> Database Terhubung
      </div>
    </div>

    <!-- Level 2: Sub-Menu Tabs List -->
    <div style="display:flex;background:#ffffff;border-bottom:1px solid #e2e8f0;overflow-x:auto;padding:0 8px;">
      
      <!-- Group PCare -->
      <a href="?tab=pcare_dokter" class="mapping-nav-tab <?= $current_tab==='pcare_dokter'?'active':'' ?>">
        <i class="fas fa-user-md" style="color:#0891b2;"></i> 1. Dokter PCare
      </a>
      <a href="?tab=pcare_poli" class="mapping-nav-tab <?= $current_tab==='pcare_poli'?'active':'' ?>">
        <i class="fas fa-hospital" style="color:#0284c7;"></i> 2. Poli PCare
      </a>
      <a href="?tab=pcare_obat" class="mapping-nav-tab <?= $current_tab==='pcare_obat'?'active':'' ?>">
        <i class="fas fa-pills" style="color:#d97706;"></i> 3. Obat PCare (DPHO)
      </a>
      <a href="?tab=pcare_tindakan" class="mapping-nav-tab <?= $current_tab==='pcare_tindakan'?'active':'' ?>">
        <i class="fas fa-hand-holding-medical" style="color:#db2777;"></i> 4. Tindakan PCare
      </a>

      <!-- Divider -->
      <div style="width:1px;background:#e2e8f0;margin:6px 8px;"></div>

      <!-- Group Satu Sehat -->
      <a href="?tab=ss_praktisi" class="mapping-nav-tab <?= $current_tab==='ss_praktisi'?'active':'' ?>">
        <i class="fas fa-id-badge" style="color:#e11d48;"></i> 5. Praktisi Satu Sehat (IHS)
      </a>
      <a href="?tab=ss_lokasi" class="mapping-nav-tab <?= $current_tab==='ss_lokasi'?'active':'' ?>">
        <i class="fas fa-map-location-dot" style="color:#7c3aed;"></i> 6. Lokasi Poli Satu Sehat
      </a>
      <a href="?tab=ss_lab" class="mapping-nav-tab <?= $current_tab==='ss_lab'?'active':'' ?>">
        <i class="fas fa-flask-vial" style="color:#059669;"></i> 7. Laboratorium (LOINC)
      </a>
      <a href="?tab=ss_obat" class="mapping-nav-tab <?= $current_tab==='ss_obat'?'active':'' ?>">
        <i class="fas fa-capsules" style="color:#ea580c;"></i> 8. Obat (KFA Kemenkes)
      </a>
    </div>

    <!-- ─── TAB CONTENT CONTAINER ──────────────────────────── -->
    <div style="padding:16px 20px;">

      <!-- ===================================================================== -->
      <!-- SUB-TAB 1: MAPPING DOKTER PCARE                                       -->
      <!-- ===================================================================== -->
      <?php if ($current_tab === 'pcare_dokter'): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px;">
          <div>
            <h3 style="font-size:15px;font-weight:700;color:#0f172a;margin:0;">Mapping Dokter SIMKlinik &rarr; PCare BPJS</h3>
            <p style="font-size:12px;color:#64748b;margin:2px 0 0;">Padankan kode dokter internal SIMKlinik dengan Kode Dokter resmi BPJS Kesehatan (FKTP)</p>
          </div>
          <button type="button" class="btn btn-sm btn-outline-info" onclick="tarikDokterPcare(this)">
            <i class="fas fa-rotate" id="iconTarikDokter"></i> Tarik Daftar Dokter dari PCare API
          </button>
        </div>

        <div class="table-responsive">
          <table class="table table-hover table-striped">
            <thead style="background:#f8fafc;font-size:12px;text-transform:uppercase;color:#475569;">
              <tr>
                <th style="width:50px;text-align:center;">No</th>
                <th>Kode &amp; Nama Dokter SIMKlinik</th>
                <th>Spesialisasi</th>
                <th>Kode Dokter BPJS</th>
                <th>Nama Dokter BPJS (PCare)</th>
                <th style="width:130px;text-align:center;">Status</th>
                <th style="width:140px;text-align:center;">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($list_dokter)): ?>
                <tr><td colspan="7" style="text-align:center;color:#94a3b8;padding:24px;">Tidak ada data dokter aktif.</td></tr>
              <?php else: ?>
                <?php foreach ($list_dokter as $idx => $d): ?>
                  <?php $is_mapped = !empty($d['kd_dokter_pcare']); ?>
                  <tr>
                    <td style="text-align:center;font-weight:600;color:#64748b;"><?= $idx + 1 ?></td>
                    <td>
                      <strong style="color:#0f172a;font-size:13px;"><?= h_esc($d['nm_dokter']) ?></strong>
                      <div style="font-size:11px;font-family:monospace;color:#64748b;">Kode: <?= h_esc($d['kd_dokter']) ?></div>
                    </td>
                    <td style="font-size:12px;color:#334155;"><?= h_esc($d['nm_sps'] ?: 'Umum') ?></td>
                    <td>
                      <?php if ($is_mapped): ?>
                        <span style="font-family:monospace;font-weight:700;color:#0369a1;background:#e0f2fe;padding:3px 8px;border-radius:4px;font-size:12px;">
                          <?= h_esc($d['kd_dokter_pcare']) ?>
                        </span>
                      <?php else: ?>
                        <span style="color:#94a3b8;font-size:12px;">—</span>
                      <?php endif; ?>
                    </td>
                    <td style="font-size:12.5px;color:#0f172a;">
                      <?= h_esc($d['nm_dokter_pcare'] ?: '—') ?>
                    </td>
                    <td style="text-align:center;">
                      <?php if ($is_mapped): ?>
                        <span class="badge badge-success" style="font-size:11px;"><i class="fas fa-check-circle"></i> Terpetakan</span>
                      <?php else: ?>
                        <span class="badge badge-warning" style="font-size:11px;"><i class="fas fa-exclamation-circle"></i> Belum</span>
                      <?php endif; ?>
                    </td>
                    <td style="text-align:center;">
                      <button type="button" class="btn btn-sm btn-primary" style="padding:4px 10px;font-size:11.5px;"
                              data-kd="<?= h_esc($d['kd_dokter']) ?>"
                              data-nm="<?= h_esc($d['nm_dokter']) ?>"
                              data-kd-pc="<?= h_esc($d['kd_dokter_pcare']) ?>"
                              data-nm-pc="<?= h_esc($d['nm_dokter_pcare']) ?>"
                              onclick="openModalEditDokter(this.dataset.kd, this.dataset.nm, this.dataset.kdPc, this.dataset.nmPc)">
                        <i class="fas fa-edit"></i> Petakan
                      </button>
                      <?php if ($is_mapped): ?>
                        <button type="button" class="btn btn-sm btn-outline-danger" style="padding:4px 8px;font-size:11.5px;" title="Hapus Mapping"
                                data-kd="<?= h_esc($d['kd_dokter']) ?>"
                                onclick="hapusMappingDokterPcare(this.dataset.kd)">
                          <i class="fas fa-trash"></i>
                        </button>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

      <!-- ===================================================================== -->
      <!-- SUB-TAB 2: MAPPING POLI PCARE                                         -->
      <!-- ===================================================================== -->
      <?php elseif ($current_tab === 'pcare_poli'): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px;">
          <div>
            <h3 style="font-size:15px;font-weight:700;color:#0f172a;margin:0;">Mapping Poliklinik SIMKlinik &rarr; PCare BPJS</h3>
            <p style="font-size:12px;color:#64748b;margin:2px 0 0;">Padankan kode poli klinik internal (cth: UMU) dengan Kode Poli resmi BPJS (cth: 001 POLI UMUM)</p>
          </div>
          <button type="button" class="btn btn-sm btn-outline-info" onclick="tarikPoliPcare(this)">
            <i class="fas fa-rotate" id="iconTarikPoli"></i> Tarik Daftar Poli dari PCare API
          </button>
        </div>

        <div class="table-responsive">
          <table class="table table-hover table-striped">
            <thead style="background:#f8fafc;font-size:12px;text-transform:uppercase;color:#475569;">
              <tr>
                <th style="width:50px;text-align:center;">No</th>
                <th>Kode &amp; Nama Poli SIMKlinik</th>
                <th>Kode Poli BPJS</th>
                <th>Nama Poli BPJS (PCare)</th>
                <th style="width:130px;text-align:center;">Status</th>
                <th style="width:140px;text-align:center;">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($list_poli)): ?>
                <tr><td colspan="6" style="text-align:center;color:#94a3b8;padding:24px;">Tidak ada data poliklinik aktif.</td></tr>
              <?php else: ?>
                <?php foreach ($list_poli as $idx => $p): ?>
                  <?php $is_mapped = !empty($p['kd_poli_pcare']); ?>
                  <tr>
                    <td style="text-align:center;font-weight:600;color:#64748b;"><?= $idx + 1 ?></td>
                    <td>
                      <strong style="color:#0f172a;font-size:13px;"><?= h_esc($p['nm_poli']) ?></strong>
                      <div style="font-size:11px;font-family:monospace;color:#64748b;">Kode Internal: <?= h_esc($p['kd_poli']) ?></div>
                    </td>
                    <td>
                      <?php if ($is_mapped): ?>
                        <span style="font-family:monospace;font-weight:700;color:#0369a1;background:#e0f2fe;padding:3px 8px;border-radius:4px;font-size:12px;">
                          <?= h_esc($p['kd_poli_pcare']) ?>
                        </span>
                      <?php else: ?>
                        <span style="color:#94a3b8;font-size:12px;">—</span>
                      <?php endif; ?>
                    </td>
                    <td style="font-size:12.5px;color:#0f172a;">
                      <?= h_esc($p['nm_poli_pcare'] ?: '—') ?>
                    </td>
                    <td style="text-align:center;">
                      <?php if ($is_mapped): ?>
                        <span class="badge badge-success" style="font-size:11px;"><i class="fas fa-check-circle"></i> Terpetakan</span>
                      <?php else: ?>
                        <span class="badge badge-warning" style="font-size:11px;"><i class="fas fa-exclamation-circle"></i> Belum</span>
                      <?php endif; ?>
                    </td>
                    <td style="text-align:center;">
                      <button type="button" class="btn btn-sm btn-primary" style="padding:4px 10px;font-size:11.5px;"
                              data-kd="<?= h_esc($p['kd_poli']) ?>"
                              data-nm="<?= h_esc($p['nm_poli']) ?>"
                              data-kd-pc="<?= h_esc($p['kd_poli_pcare']) ?>"
                              data-nm-pc="<?= h_esc($p['nm_poli_pcare']) ?>"
                              onclick="openModalEditPoli(this.dataset.kd, this.dataset.nm, this.dataset.kdPc, this.dataset.nmPc)">
                        <i class="fas fa-edit"></i> Petakan
                      </button>
                      <?php if ($is_mapped): ?>
                        <button type="button" class="btn btn-sm btn-outline-danger" style="padding:4px 8px;font-size:11.5px;" title="Hapus Mapping"
                                data-kd="<?= h_esc($p['kd_poli']) ?>"
                                onclick="hapusMappingPoliPcare(this.dataset.kd)">
                          <i class="fas fa-trash"></i>
                        </button>
                      <?php endif; ?>
                    </td>

                  </tr>
                <?php endforeach; ?>

              <?php endif; ?>
            </tbody>
          </table>
        </div>

      <!-- ===================================================================== -->
      <!-- SUB-TAB 3: MAPPING OBAT PCARE (DPHO)                                  -->
      <!-- ===================================================================== -->
      <?php elseif ($current_tab === 'pcare_obat'): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px;">
          <div>
            <h3 style="font-size:15px;font-weight:700;color:#0f172a;margin:0;">Mapping Obat SIMKlinik &rarr; DPHO PCare BPJS</h3>
            <p style="font-size:12px;color:#64748b;margin:2px 0 0;">Padankan master obat klinik dengan Kode Obat resmi DPHO BPJS Kesehatan</p>
          </div>
          <form method="GET" style="display:flex;gap:6px;">
            <input type="hidden" name="tab" value="pcare_obat">
            <input type="text" name="q_obat" value="<?= h_esc($q_obat) ?>" placeholder="Cari nama / kode obat..." class="form-control" style="height:32px;font-size:12px;width:220px;">
            <button type="submit" class="btn btn-sm btn-secondary" style="height:32px;font-size:12px;"><i class="fas fa-search"></i> Cari</button>
          </form>
        </div>

        <div class="table-responsive">
          <table class="table table-hover table-striped">
            <thead style="background:#f8fafc;font-size:12px;text-transform:uppercase;color:#475569;">
              <tr>
                <th style="width:50px;text-align:center;">No</th>
                <th>Kode &amp; Nama Obat SIMKlinik</th>
                <th>Kategori / Satuan</th>
                <th>Kode Obat PCare (DPHO)</th>
                <th>Nama Obat PCare (DPHO)</th>
                <th style="width:120px;text-align:center;">Status</th>
                <th style="width:130px;text-align:center;">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($list_obat)): ?>
                <tr><td colspan="7" style="text-align:center;color:#94a3b8;padding:24px;">Tidak ada data obat yang sesuai pencarian.</td></tr>
              <?php else: ?>
                <?php foreach ($list_obat as $idx => $o): ?>
                  <?php $is_mapped = !empty($o['kode_brng_pcare']); ?>
                  <tr>
                    <td style="text-align:center;font-weight:600;color:#64748b;"><?= $offset_obat + $idx + 1 ?></td>
                    <td>
                      <strong style="color:#0f172a;font-size:13px;"><?= h_esc($o['nama_brng']) ?></strong>
                      <div style="font-size:11px;font-family:monospace;color:#64748b;">Kode: <?= h_esc($o['kode_brng']) ?></div>
                    </td>
                    <td style="font-size:11.5px;color:#475569;">
                      <?= h_esc($o['nm_kategori'] ?: '-') ?> (<?= h_esc($o['kode_sat'] ?: '-') ?>)
                    </td>
                    <td>
                      <?php if ($is_mapped): ?>
                        <span style="font-family:monospace;font-weight:700;color:#0369a1;background:#e0f2fe;padding:3px 8px;border-radius:4px;font-size:12px;">
                          <?= h_esc($o['kode_brng_pcare']) ?>
                        </span>
                      <?php else: ?>
                        <span style="color:#94a3b8;font-size:12px;">—</span>
                      <?php endif; ?>
                    </td>
                    <td style="font-size:12.5px;color:#0f172a;">
                      <?= h_esc($o['nama_brng_pcare'] ?: '—') ?>
                    </td>
                    <td style="text-align:center;">
                      <?php if ($is_mapped): ?>
                        <span class="badge badge-success" style="font-size:11px;"><i class="fas fa-check-circle"></i> Terpetakan</span>
                      <?php else: ?>
                        <span class="badge badge-warning" style="font-size:11px;"><i class="fas fa-exclamation-circle"></i> Belum</span>
                      <?php endif; ?>
                    </td>
                    <td style="text-align:center;">
                      <button type="button" class="btn btn-sm btn-primary" style="padding:4px 8px;font-size:11.5px;"
                              data-kd="<?= h_esc($o['kode_brng']) ?>"
                              data-nm="<?= h_esc($o['nama_brng']) ?>"
                              data-kd-pc="<?= h_esc($o['kode_brng_pcare']) ?>"
                              data-nm-pc="<?= h_esc($o['nama_brng_pcare']) ?>"
                              onclick="openModalEditObatPcare(this.dataset.kd, this.dataset.nm, this.dataset.kdPc, this.dataset.nmPc)">
                        <i class="fas fa-edit"></i> Petakan
                      </button>
                      <?php if ($is_mapped): ?>
                        <button type="button" class="btn btn-sm btn-outline-danger" style="padding:4px 6px;font-size:11.5px;" title="Hapus Mapping"
                                data-kd="<?= h_esc($o['kode_brng']) ?>"
                                onclick="hapusMappingObatPcare(this.dataset.kd)">
                          <i class="fas fa-trash"></i>
                        </button>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- Paginasi Obat -->
        <?php if ($total_pages_obat > 1): ?>
          <div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px;font-size:12px;">
            <span style="color:#64748b;">Menampilkan <?= count($list_obat) ?> dari total <?= $tot_obat ?> obat</span>
            <div style="display:flex;gap:4px;">
              <?php for ($p = 1; $p <= min(10, $total_pages_obat); $p++): ?>
                <a href="?tab=pcare_obat&q_obat=<?= urlencode($q_obat) ?>&p_obat=<?= $p ?>" class="btn btn-sm <?= $p===$page_obat ? 'btn-primary' : 'btn-outline' ?>" style="padding:2px 8px;font-size:11.5px;">
                  <?= $p ?>
                </a>
              <?php endfor; ?>
            </div>
          </div>
        <?php endif; ?>

      <!-- ===================================================================== -->
      <!-- SUB-TAB 4: MAPPING TINDAKAN PCARE                                     -->
      <!-- ===================================================================== -->
      <?php elseif ($current_tab === 'pcare_tindakan'): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px;">
          <div>
            <h3 style="font-size:15px;font-weight:700;color:#0f172a;margin:0;">Mapping Tindakan Ralan &rarr; PCare BPJS</h3>
            <p style="font-size:12px;color:#64748b;margin:2px 0 0;">Padankan tarif &amp; prosedur ralan internal dengan Kode Tindakan resmi PCare BPJS</p>
          </div>
          <form method="GET" style="display:flex;gap:6px;">
            <input type="hidden" name="tab" value="pcare_tindakan">
            <input type="text" name="q_tindakan" value="<?= h_esc($q_tindakan) ?>" placeholder="Cari tindakan..." class="form-control" style="height:32px;font-size:12px;width:200px;">
            <button type="submit" class="btn btn-sm btn-secondary" style="height:32px;font-size:12px;"><i class="fas fa-search"></i> Cari</button>
          </form>
        </div>

        <div class="table-responsive">
          <table class="table table-hover table-striped">
            <thead style="background:#f8fafc;font-size:12px;text-transform:uppercase;color:#475569;">
              <tr>
                <th style="width:50px;text-align:center;">No</th>
                <th>Kode &amp; Nama Tindakan SIMKlinik</th>
                <th>Kategori</th>
                <th>Kode Tindakan PCare</th>
                <th>Nama Tindakan PCare</th>
                <th style="width:120px;text-align:center;">Status</th>
                <th style="width:130px;text-align:center;">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($list_tindakan)): ?>
                <tr><td colspan="7" style="text-align:center;color:#94a3b8;padding:24px;">Tidak ada data tindakan.</td></tr>
              <?php else: ?>
                <?php foreach ($list_tindakan as $idx => $t): ?>
                  <?php $is_mapped = !empty($t['kd_tindakan_pcare']); ?>
                  <tr>
                    <td style="text-align:center;font-weight:600;color:#64748b;"><?= $idx + 1 ?></td>
                    <td>
                      <strong style="color:#0f172a;font-size:13px;"><?= h_esc($t['nm_perawatan']) ?></strong>
                      <div style="font-size:11px;font-family:monospace;color:#64748b;">Kode: <?= h_esc($t['kd_jenis_prw']) ?></div>
                    </td>
                    <td style="font-size:11.5px;color:#475569;"><?= h_esc($t['nm_kategori'] ?: '-') ?></td>
                    <td>
                      <?php if ($is_mapped): ?>
                        <span style="font-family:monospace;font-weight:700;color:#0369a1;background:#e0f2fe;padding:3px 8px;border-radius:4px;font-size:12px;">
                          <?= h_esc($t['kd_tindakan_pcare']) ?>
                        </span>
                      <?php else: ?>
                        <span style="color:#94a3b8;font-size:12px;">—</span>
                      <?php endif; ?>
                    </td>
                    <td style="font-size:12.5px;color:#0f172a;"><?= h_esc($t['nm_tindakan_pcare'] ?: '—') ?></td>
                    <td style="text-align:center;">
                      <?php if ($is_mapped): ?>
                        <span class="badge badge-success" style="font-size:11px;"><i class="fas fa-check-circle"></i> Terpetakan</span>
                      <?php else: ?>
                        <span class="badge badge-warning" style="font-size:11px;"><i class="fas fa-exclamation-circle"></i> Belum</span>
                      <?php endif; ?>
                    </td>
                    <td style="text-align:center;">
                      <button type="button" class="btn btn-sm btn-primary" style="padding:4px 8px;font-size:11.5px;"
                              data-kd="<?= h_esc($t['kd_jenis_prw']) ?>"
                              data-nm="<?= h_esc($t['nm_perawatan']) ?>"
                              data-kd-pc="<?= h_esc($t['kd_tindakan_pcare']) ?>"
                              data-nm-pc="<?= h_esc($t['nm_tindakan_pcare']) ?>"
                              onclick="openModalEditTindakanPcare(this.dataset.kd, this.dataset.nm, this.dataset.kdPc, this.dataset.nmPc)">
                        <i class="fas fa-edit"></i> Petakan
                      </button>
                      <?php if ($is_mapped): ?>
                        <button type="button" class="btn btn-sm btn-outline-danger" style="padding:4px 6px;font-size:11.5px;" title="Hapus Mapping"
                                data-kd="<?= h_esc($t['kd_jenis_prw']) ?>"
                                onclick="hapusMappingTindakanPcare(this.dataset.kd)">
                          <i class="fas fa-trash"></i>
                        </button>
                      <?php endif; ?>
                    </td>

                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

      <!-- ===================================================================== -->
      <!-- SUB-TAB 5: MAPPING PRAKTISI SATU SEHAT (IHS)                          -->
      <!-- ===================================================================== -->
      <?php elseif ($current_tab === 'ss_praktisi'): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px;">
          <div>
            <h3 style="font-size:15px;font-weight:700;color:#0f172a;margin:0;">Mapping Praktisi Dokter / Tenaga Medis (IHS Practitioner Satu Sehat)</h3>
            <p style="font-size:12px;color:#64748b;margin:2px 0 0;">Cari &amp; hubungkan NIK KTP dokter ke ID Practitioner Satu Sehat Kemenkes RI</p>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-hover table-striped">
            <thead style="background:#f8fafc;font-size:12px;text-transform:uppercase;color:#475569;">
              <tr>
                <th style="width:50px;text-align:center;">No</th>
                <th>Kode &amp; Nama Dokter</th>
                <th>NIK KTP</th>
                <th>IHS Practitioner ID (Kemenkes)</th>
                <th style="width:130px;text-align:center;">Status</th>
                <th style="width:160px;text-align:center;">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($list_dokter)): ?>
                <tr><td colspan="6" style="text-align:center;color:#94a3b8;padding:24px;">Tidak ada data dokter.</td></tr>
              <?php else: ?>
                <?php foreach ($list_dokter as $idx => $d): ?>
                  <?php $is_mapped = !empty($d['practitioner_id']); ?>
                  <tr>
                    <td style="text-align:center;font-weight:600;color:#64748b;"><?= $idx + 1 ?></td>
                    <td>
                      <strong style="color:#0f172a;font-size:13px;"><?= h_esc($d['nm_dokter']) ?></strong>
                      <div style="font-size:11px;font-family:monospace;color:#64748b;">Kode: <?= h_esc($d['kd_dokter']) ?></div>
                    </td>
                    <td style="font-size:12.5px;font-family:monospace;color:#334155;">
                      <?= h_esc($d['no_ktp'] ?: '— (Belum diisi)') ?>
                    </td>
                    <td>
                      <?php if ($is_mapped): ?>
                        <span style="font-family:monospace;font-weight:700;color:#e11d48;background:#ffe4e6;padding:3px 8px;border-radius:4px;font-size:12px;">
                          <?= h_esc($d['practitioner_id']) ?>
                        </span>
                      <?php else: ?>
                        <span style="color:#94a3b8;font-size:12px;">—</span>
                      <?php endif; ?>
                    </td>
                    <td style="text-align:center;">
                      <?php if ($is_mapped): ?>
                        <span class="badge badge-success" style="font-size:11px;"><i class="fas fa-check-circle"></i> Terhubung</span>
                      <?php else: ?>
                        <span class="badge badge-warning" style="font-size:11px;"><i class="fas fa-exclamation-circle"></i> Belum</span>
                      <?php endif; ?>
                    </td>
                    <td style="text-align:center;">
                      <?php if (!empty($d['no_ktp'])): ?>
                        <button type="button" class="btn btn-sm btn-outline-danger" style="padding:4px 8px;font-size:11.5px;" title="Lookup NIK ke Kemenkes"
                                data-kd="<?= h_esc($d['kd_dokter']) ?>"
                                data-ktp="<?= h_esc($d['no_ktp']) ?>"
                                onclick="lookupNikKemenkes(this.dataset.kd, this.dataset.ktp, this)">
                          <i class="fas fa-search"></i> Cari NIK
                        </button>
                      <?php endif; ?>
                      <button type="button" class="btn btn-sm btn-primary" style="padding:4px 8px;font-size:11.5px;"
                              data-kd="<?= h_esc($d['kd_dokter']) ?>"
                              data-nm="<?= h_esc($d['nm_dokter']) ?>"
                              data-ihs="<?= h_esc($d['practitioner_id']) ?>"
                              data-ktp="<?= h_esc($d['no_ktp']) ?>"
                              onclick="openModalEditPraktisi(this.dataset.kd, this.dataset.nm, this.dataset.ihs, this.dataset.ktp)">
                        <i class="fas fa-edit"></i> Edit
                      </button>
                      <?php if ($is_mapped): ?>
                        <button type="button" class="btn btn-sm btn-outline-secondary" style="padding:4px 6px;font-size:11.5px;" title="Hapus Mapping"
                                data-kd="<?= h_esc($d['kd_dokter']) ?>"
                                onclick="hapusMappingPraktisiSS(this.dataset.kd)">
                          <i class="fas fa-trash"></i>
                        </button>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

      <!-- ===================================================================== -->
      <!-- SUB-TAB 6: MAPPING LOKASI SATU SEHAT                                  -->
      <!-- ===================================================================== -->
      <?php elseif ($current_tab === 'ss_lokasi'): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px;">
          <div>
            <h3 style="font-size:15px;font-weight:700;color:#0f172a;margin:0;">Mapping Poliklinik &rarr; Lokasi FHIR Satu Sehat</h3>
            <p style="font-size:12px;color:#64748b;margin:2px 0 0;">Padankan ruang poliklinik klinik dengan Location &amp; Organization ID Kemenkes</p>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-hover table-striped">
            <thead style="background:#f8fafc;font-size:12px;text-transform:uppercase;color:#475569;">
              <tr>
                <th style="width:50px;text-align:center;">No</th>
                <th>Poliklinik SIMKlinik</th>
                <th>Organization ID</th>
                <th>Location ID Satu Sehat</th>
                <th>Koordinat (Long, Lat)</th>
                <th style="width:120px;text-align:center;">Status</th>
                <th style="width:130px;text-align:center;">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($list_poli)): ?>
                <tr><td colspan="7" style="text-align:center;color:#94a3b8;padding:24px;">Tidak ada data poli.</td></tr>
              <?php else: ?>
                <?php foreach ($list_poli as $idx => $p): ?>
                  <?php $is_mapped = !empty($p['id_lokasi_satusehat']); ?>
                  <tr>
                    <td style="text-align:center;font-weight:600;color:#64748b;"><?= $idx + 1 ?></td>
                    <td>
                      <strong style="color:#0f172a;font-size:13px;"><?= h_esc($p['nm_poli']) ?></strong>
                      <div style="font-size:11px;font-family:monospace;color:#64748b;">Kode: <?= h_esc($p['kd_poli']) ?></div>
                    </td>
                    <td style="font-size:11.5px;font-family:monospace;color:#475569;">
                      <?= h_esc($p['id_organisasi_satusehat'] ?: 'Default Instansi') ?>
                    </td>
                    <td>
                      <?php if ($is_mapped): ?>
                        <span style="font-family:monospace;font-weight:700;color:#7c3aed;background:#f5f3ff;padding:3px 8px;border-radius:4px;font-size:12px;">
                          <?= h_esc($p['id_lokasi_satusehat']) ?>
                        </span>
                      <?php else: ?>
                        <span style="color:#94a3b8;font-size:12px;">—</span>
                      <?php endif; ?>
                    </td>
                    <td style="font-size:11px;color:#64748b;font-family:monospace;">
                      <?= $p['longitude'] ? h_esc("{$p['longitude']}, {$p['latitude']}") : '—' ?>
                    </td>
                    <td style="text-align:center;">
                      <?php if ($is_mapped): ?>
                        <span class="badge badge-success" style="font-size:11px;"><i class="fas fa-check-circle"></i> Terpetakan</span>
                      <?php else: ?>
                        <span class="badge badge-warning" style="font-size:11px;"><i class="fas fa-exclamation-circle"></i> Belum</span>
                      <?php endif; ?>
                    </td>
                    <td style="text-align:center;">
                      <button type="button" class="btn btn-sm btn-primary" style="padding:4px 8px;font-size:11.5px;"
                              data-kd="<?= h_esc($p['kd_poli']) ?>"
                              data-nm="<?= h_esc($p['nm_poli']) ?>"
                              data-org="<?= h_esc($p['id_organisasi_satusehat']) ?>"
                              data-loc="<?= h_esc($p['id_lokasi_satusehat']) ?>"
                              data-lng="<?= h_esc($p['longitude']) ?>"
                              data-lat="<?= h_esc($p['latitude']) ?>"
                              data-alt="<?= h_esc($p['altittude']) ?>"
                              onclick="openModalEditLokasiSS(this.dataset.kd, this.dataset.nm, this.dataset.org, this.dataset.loc, this.dataset.lng, this.dataset.lat, this.dataset.alt)">
                        <i class="fas fa-edit"></i> Petakan
                      </button>
                      <?php if ($is_mapped): ?>
                        <button type="button" class="btn btn-sm btn-outline-danger" style="padding:4px 6px;font-size:11.5px;" title="Hapus Mapping"
                                data-kd="<?= h_esc($p['kd_poli']) ?>"
                                onclick="hapusMappingLokasiSS(this.dataset.kd)">
                          <i class="fas fa-trash"></i>
                        </button>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

      <!-- ===================================================================== -->
      <!-- SUB-TAB 7: MAPPING LAB (LOINC)                                        -->
      <!-- ===================================================================== -->
      <?php elseif ($current_tab === 'ss_lab'): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:gap:8px;">
          <div>
            <h3 style="font-size:15px;font-weight:700;color:#0f172a;margin:0;">Mapping Pemeriksaan Laboratorium &rarr; LOINC Satu Sehat</h3>
            <p style="font-size:12px;color:#64748b;margin:2px 0 0;">Standarisasi kode LOINC untuk pengiriman hasil pemeriksaan Lab ke Satu Sehat</p>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-hover table-striped">
            <thead style="background:#f8fafc;font-size:12px;text-transform:uppercase;color:#475569;">
              <tr>
                <th style="width:50px;text-align:center;">No</th>
                <th>Jenis Tes / Paket Lab</th>
                <th>Parameter Pemeriksaan</th>
                <th>Satuan / Rujukan</th>
                <th>Kode LOINC</th>
                <th>Display LOINC</th>
                <th style="width:110px;text-align:center;">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($list_lab)): ?>
                <tr><td colspan="7" style="text-align:center;color:#94a3b8;padding:24px;">Tidak ada data template laboratorium.</td></tr>
              <?php else: ?>
                <?php foreach ($list_lab as $idx => $l): ?>
                  <?php $is_mapped = !empty($l['loinc_code']); ?>
                  <tr>
                    <td style="text-align:center;font-weight:600;color:#64748b;"><?= $idx + 1 ?></td>
                    <td>
                      <strong style="color:#0f172a;font-size:12.5px;"><?= h_esc($l['nm_perawatan']) ?></strong>
                      <div style="font-size:10.5px;font-family:monospace;color:#64748b;"><?= h_esc($l['kd_jenis_prw']) ?></div>
                    </td>
                    <td style="font-size:12.5px;font-weight:600;color:#0284c7;">
                      <?= h_esc($l['nm_pemeriksaan'] ?: '(Paket Utama)') ?>
                    </td>
                    <td style="font-size:11.5px;color:#64748b;">
                      <?= h_esc($l['satuan'] ?: '-') ?> (<?= h_esc($l['nilai_rujukan_ld'] ?: '-') ?>)
                    </td>
                    <td>
                      <?php if ($is_mapped): ?>
                        <span style="font-family:monospace;font-weight:700;color:#059669;background:#d1fae5;padding:2px 7px;border-radius:4px;font-size:12px;">
                          <?= h_esc($l['loinc_code']) ?>
                        </span>
                      <?php else: ?>
                        <span style="color:#94a3b8;font-size:12px;">—</span>
                      <?php endif; ?>
                    </td>
                    <td style="font-size:11.5px;color:#334155;"><?= h_esc($l['loinc_display'] ?: '—') ?></td>
                    <td style="text-align:center;">
                      <button type="button" class="btn btn-sm btn-primary" style="padding:4px 8px;font-size:11px;"
                              data-id="<?= (int)($l['id_template'] ?? 0) ?>"
                              data-kd="<?= h_esc($l['kd_jenis_prw']) ?>"
                              data-nm="<?= h_esc($l['nm_pemeriksaan'] ?: $l['nm_perawatan']) ?>"
                              data-code="<?= h_esc($l['loinc_code']) ?>"
                              data-disp="<?= h_esc($l['loinc_display']) ?>"
                              onclick="openModalEditLabSS(this.dataset.id, this.dataset.kd, this.dataset.nm, this.dataset.code, this.dataset.disp)">
                        <i class="fas fa-edit"></i> Edit
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

      <!-- ===================================================================== -->
      <!-- SUB-TAB 8: MAPPING OBAT (KFA KEMENKES)                                 -->
      <!-- ===================================================================== -->
      <?php elseif ($current_tab === 'ss_obat'): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px;">
          <div>
            <h3 style="font-size:15px;font-weight:700;color:#0f172a;margin:0;">Mapping Obat SIMKlinik &rarr; KFA (Kamus Farmasi &amp; Alkes Satu Sehat)</h3>
            <p style="font-size:12px;color:#64748b;margin:2px 0 0;">Standarisasi Kode KFA (PO / Generik) Kemenkes untuk pengiriman resep &amp; dispensing</p>
          </div>
          <form method="GET" style="display:flex;gap:6px;">
            <input type="hidden" name="tab" value="ss_obat">
            <input type="text" name="q_obat" value="<?= h_esc($q_obat) ?>" placeholder="Cari obat..." class="form-control" style="height:32px;font-size:12px;width:200px;">
            <button type="submit" class="btn btn-sm btn-secondary" style="height:32px;font-size:12px;"><i class="fas fa-search"></i> Cari</button>
          </form>
        </div>

        <div class="table-responsive">
          <table class="table table-hover table-striped">
            <thead style="background:#f8fafc;font-size:12px;text-transform:uppercase;color:#475569;">
              <tr>
                <th style="width:50px;text-align:center;">No</th>
                <th>Kode &amp; Nama Obat SIMKlinik</th>
                <th>Kode KFA</th>
                <th>Nama KFA / Display</th>
                <th>Bentuk &amp; Rute</th>
                <th style="width:120px;text-align:center;">Status</th>
                <th style="width:130px;text-align:center;">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($list_obat)): ?>
                <tr><td colspan="7" style="text-align:center;color:#94a3b8;padding:24px;">Tidak ada data obat.</td></tr>
              <?php else: ?>
                <?php foreach ($list_obat as $idx => $o): ?>
                  <?php $is_mapped = !empty($o['obat_code']); ?>
                  <tr>
                    <td style="text-align:center;font-weight:600;color:#64748b;"><?= $offset_obat + $idx + 1 ?></td>
                    <td>
                      <strong style="color:#0f172a;font-size:13px;"><?= h_esc($o['nama_brng']) ?></strong>
                      <div style="font-size:11px;font-family:monospace;color:#64748b;">Kode: <?= h_esc($o['kode_brng']) ?></div>
                    </td>
                    <td>
                      <?php if ($is_mapped): ?>
                        <span style="font-family:monospace;font-weight:700;color:#ea580c;background:#ffedd5;padding:3px 8px;border-radius:4px;font-size:12px;">
                          <?= h_esc($o['obat_code']) ?>
                        </span>
                      <?php else: ?>
                        <span style="color:#94a3b8;font-size:12px;">—</span>
                      <?php endif; ?>
                    </td>
                    <td style="font-size:12px;color:#0f172a;"><?= h_esc($o['obat_display'] ?: '—') ?></td>
                    <td style="font-size:11px;color:#64748b;">
                      <?= h_esc($o['form_display'] ?: '-') ?> &bull; <?= h_esc($o['route_display'] ?: '-') ?>
                    </td>
                    <td style="text-align:center;">
                      <?php if ($is_mapped): ?>
                        <span class="badge badge-success" style="font-size:11px;"><i class="fas fa-check-circle"></i> Terpetakan</span>
                      <?php else: ?>
                        <span class="badge badge-warning" style="font-size:11px;"><i class="fas fa-exclamation-circle"></i> Belum</span>
                      <?php endif; ?>
                    </td>
                    <td style="text-align:center;">
                      <button type="button" class="btn btn-sm btn-primary" style="padding:4px 8px;font-size:11.5px;"
                              data-kd="<?= h_esc($o['kode_brng']) ?>"
                              data-nm="<?= h_esc($o['nama_brng']) ?>"
                              data-code="<?= h_esc($o['obat_code']) ?>"
                              data-disp="<?= h_esc($o['obat_display']) ?>"
                              data-form="<?= h_esc($o['form_code']) ?>"
                              data-form-disp="<?= h_esc($o['form_display']) ?>"
                              data-route="<?= h_esc($o['route_code']) ?>"
                              data-route-disp="<?= h_esc($o['route_display']) ?>"
                              onclick="openModalEditObatSS(this.dataset.kd, this.dataset.nm, this.dataset.code, this.dataset.disp, this.dataset.form, this.dataset.formDisp, this.dataset.route, this.dataset.routeDisp)">
                        <i class="fas fa-edit"></i> Petakan
                      </button>
                    </td>

                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

    </div>
  </div>

</div>

<!-- ─── MODALS SECTION ───────────────────────────────────── -->

<!-- Modal 1: Edit Mapping Dokter PCare -->
<div class="modal-overlay" id="modalDokterPcare" onclick="if(event.target===this) closeModal('modalDokterPcare')">
  <div class="modal" style="max-width:520px;">
    <div class="modal-header">
      <h3 class="modal-title" style="font-size:15px;font-weight:700;"><i class="fas fa-user-md text-info"></i> Petakan Dokter ke PCare BPJS</h3>
      <button type="button" class="modal-close" onclick="closeModal('modalDokterPcare')">&times;</button>
    </div>
    <form onsubmit="submitFormMapping(event, 'pcare_save_dokter', 'modalDokterPcare')">
      <div class="modal-body" style="display:flex;flex-direction:column;gap:12px;">
        <input type="hidden" name="kd_dokter" id="mDok_kd_dokter">
        <div>
          <label style="font-size:12px;font-weight:700;color:#334155;">Dokter SIMKlinik:</label>
          <input type="text" id="mDok_nm_dokter" class="form-control" style="background:#f8fafc;" readonly>
        </div>
        <div>
          <label style="font-size:12px;font-weight:700;color:#334155;">Kode Dokter BPJS (PCare) <span style="color:#ef4444;">*</span>:</label>
          <input type="text" name="kd_dokter_pcare" id="mDok_kd_pcare" class="form-control" required placeholder="Contoh: 512701">
        </div>
        <div>
          <label style="font-size:12px;font-weight:700;color:#334155;">Nama Dokter di PCare BPJS:</label>
          <input type="text" name="nm_dokter_pcare" id="mDok_nm_pcare" class="form-control" placeholder="Nama resmi di PCare">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalDokterPcare')">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Mapping</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal 2: Edit Mapping Poli PCare -->
<div class="modal-overlay" id="modalPoliPcare" onclick="if(event.target===this) closeModal('modalPoliPcare')">
  <div class="modal" style="max-width:520px;">
    <div class="modal-header">
      <h3 class="modal-title" style="font-size:15px;font-weight:700;"><i class="fas fa-hospital text-info"></i> Petakan Poliklinik ke PCare BPJS</h3>
      <button type="button" class="modal-close" onclick="closeModal('modalPoliPcare')">&times;</button>
    </div>
    <form onsubmit="submitFormMapping(event, 'pcare_save_poli', 'modalPoliPcare')">
      <div class="modal-body" style="display:flex;flex-direction:column;gap:12px;">
        <input type="hidden" name="kd_poli_rs" id="mPoli_kd_poli">
        <div>
          <label style="font-size:12px;font-weight:700;color:#334155;">Poliklinik SIMKlinik:</label>
          <input type="text" id="mPoli_nm_poli" class="form-control" style="background:#f8fafc;" readonly>
        </div>
        <div>
          <label style="font-size:12px;font-weight:700;color:#334155;">Kode Poli BPJS (PCare) <span style="color:#ef4444;">*</span>:</label>
          <input type="text" name="kd_poli_pcare" id="mPoli_kd_pcare" class="form-control" required placeholder="Contoh: 001, 002, 003">
        </div>
        <div>
          <label style="font-size:12px;font-weight:700;color:#334155;">Nama Poli di PCare BPJS:</label>
          <input type="text" name="nm_poli_pcare" id="mPoli_nm_pcare" class="form-control" placeholder="Contoh: POLI UMUM">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalPoliPcare')">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Mapping</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal 3: Edit Mapping Obat PCare -->
<div class="modal-overlay" id="modalObatPcare" onclick="if(event.target===this) closeModal('modalObatPcare')">
  <div class="modal" style="max-width:520px;">
    <div class="modal-header">
      <h3 class="modal-title" style="font-size:15px;font-weight:700;"><i class="fas fa-pills text-warning"></i> Petakan Obat ke DPHO PCare BPJS</h3>
      <button type="button" class="modal-close" onclick="closeModal('modalObatPcare')">&times;</button>
    </div>
    <form onsubmit="submitFormMapping(event, 'pcare_save_obat', 'modalObatPcare')">
      <div class="modal-body" style="display:flex;flex-direction:column;gap:12px;">
        <input type="hidden" name="kode_brng" id="mObat_kode_brng">
        <div>
          <label style="font-size:12px;font-weight:700;color:#334155;">Obat SIMKlinik:</label>
          <input type="text" id="mObat_nama_brng" class="form-control" style="background:#f8fafc;" readonly>
        </div>
        <div>
          <label style="font-size:12px;font-weight:700;color:#334155;">Kode Obat PCare (DPHO) <span style="color:#ef4444;">*</span>:</label>
          <input type="text" name="kode_brng_pcare" id="mObat_kd_pcare" class="form-control" required placeholder="Kode obat DPHO">
        </div>
        <div>
          <label style="font-size:12px;font-weight:700;color:#334155;">Nama Obat DPHO BPJS:</label>
          <input type="text" name="nama_brng_pcare" id="mObat_nm_pcare" class="form-control" placeholder="Nama obat resmi DPHO">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalObatPcare')">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Mapping</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal 4: Edit Mapping Tindakan PCare -->
<div class="modal-overlay" id="modalTindakanPcare" onclick="if(event.target===this) closeModal('modalTindakanPcare')">
  <div class="modal" style="max-width:520px;">
    <div class="modal-header">
      <h3 class="modal-title" style="font-size:15px;font-weight:700;"><i class="fas fa-hand-holding-medical text-primary"></i> Petakan Tindakan ke PCare BPJS</h3>
      <button type="button" class="modal-close" onclick="closeModal('modalTindakanPcare')">&times;</button>
    </div>
    <form onsubmit="submitFormMapping(event, 'pcare_save_tindakan', 'modalTindakanPcare')">
      <div class="modal-body" style="display:flex;flex-direction:column;gap:12px;">
        <input type="hidden" name="kd_jenis_prw" id="mTind_kd_jenis">
        <div>
          <label style="font-size:12px;font-weight:700;color:#334155;">Tindakan SIMKlinik:</label>
          <input type="text" id="mTind_nm_perawatan" class="form-control" style="background:#f8fafc;" readonly>
        </div>
        <div>
          <label style="font-size:12px;font-weight:700;color:#334155;">Kode Tindakan PCare <span style="color:#ef4444;">*</span>:</label>
          <input type="text" name="kd_tindakan_pcare" id="mTind_kd_pcare" class="form-control" required placeholder="Kode tindakan PCare">
        </div>
        <div>
          <label style="font-size:12px;font-weight:700;color:#334155;">Nama Tindakan PCare:</label>
          <input type="text" name="nm_tindakan_pcare" id="mTind_nm_pcare" class="form-control" placeholder="Nama tindakan resmi PCare">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalTindakanPcare')">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Mapping</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal 5: Edit Mapping Praktisi Satu Sehat -->
<div class="modal-overlay" id="modalPraktisiSS" onclick="if(event.target===this) closeModal('modalPraktisiSS')">
  <div class="modal" style="max-width:520px;">
    <div class="modal-header">
      <h3 class="modal-title" style="font-size:15px;font-weight:700;"><i class="fas fa-id-badge text-danger"></i> Mapping Praktisi Satu Sehat (IHS)</h3>
      <button type="button" class="modal-close" onclick="closeModal('modalPraktisiSS')">&times;</button>
    </div>
    <form onsubmit="submitFormMapping(event, 'satusehat_save_praktisi', 'modalPraktisiSS')">
      <div class="modal-body" style="display:flex;flex-direction:column;gap:12px;">
        <input type="hidden" name="kd_dokter" id="mPrak_kd_dokter">
        <div>
          <label style="font-size:12px;font-weight:700;color:#334155;">Dokter:</label>
          <input type="text" id="mPrak_nm_dokter" class="form-control" style="background:#f8fafc;" readonly>
        </div>
        <div>
          <label style="font-size:12px;font-weight:700;color:#334155;">NIK KTP:</label>
          <input type="text" id="mPrak_no_ktp" class="form-control" style="background:#f8fafc;" readonly>
        </div>
        <div>
          <label style="font-size:12px;font-weight:700;color:#334155;">IHS Practitioner ID <span style="color:#ef4444;">*</span>:</label>
          <input type="text" name="practitioner_id" id="mPrak_id" class="form-control" required placeholder="Contoh: 10001234567">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalPraktisiSS')">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Mapping</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal 6: Edit Mapping Lokasi Satu Sehat -->
<div class="modal-overlay" id="modalLokasiSS" onclick="if(event.target===this) closeModal('modalLokasiSS')">
  <div class="modal" style="max-width:540px;">
    <div class="modal-header">
      <h3 class="modal-title" style="font-size:15px;font-weight:700;"><i class="fas fa-map-location-dot text-violet"></i> Mapping Lokasi FHIR Satu Sehat</h3>
      <button type="button" class="modal-close" onclick="closeModal('modalLokasiSS')">&times;</button>
    </div>
    <form onsubmit="submitFormMapping(event, 'satusehat_save_lokasi', 'modalLokasiSS')">
      <div class="modal-body" style="display:flex;flex-direction:column;gap:12px;">
        <input type="hidden" name="kd_poli" id="mLok_kd_poli">
        <div>
          <label style="font-size:12px;font-weight:700;color:#334155;">Poliklinik SIMKlinik:</label>
          <input type="text" id="mLok_nm_poli" class="form-control" style="background:#f8fafc;" readonly>
        </div>
        <div>
          <label style="font-size:12px;font-weight:700;color:#334155;">Organization ID (Opsional jika ikut instansi):</label>
          <input type="text" name="id_organisasi_satusehat" id="mLok_org_id" class="form-control" placeholder="Kosongkan jika sama dengan Organization ID Faskes">
        </div>
        <div>
          <label style="font-size:12px;font-weight:700;color:#334155;">Location ID Satu Sehat <span style="color:#ef4444;">*</span>:</label>
          <input type="text" name="id_lokasi_satusehat" id="mLok_loc_id" class="form-control" required placeholder="UUID / Location ID Kemenkes">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;">
          <div>
            <label style="font-size:11px;color:#64748b;">Longitude:</label>
            <input type="text" name="longitude" id="mLok_long" class="form-control" placeholder="cth: 109.00">
          </div>
          <div>
            <label style="font-size:11px;color:#64748b;">Latitude:</label>
            <input type="text" name="latitude" id="mLok_lat" class="form-control" placeholder="cth: -7.24">
          </div>
          <div>
            <label style="font-size:11px;color:#64748b;">Altitude:</label>
            <input type="text" name="altittude" id="mLok_alt" class="form-control" placeholder="0">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalLokasiSS')">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Lokasi</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal 7: Edit Mapping Lab LOINC -->
<div class="modal-overlay" id="modalLabSS" onclick="if(event.target===this) closeModal('modalLabSS')">
  <div class="modal" style="max-width:520px;">
    <div class="modal-header">
      <h3 class="modal-title" style="font-size:15px;font-weight:700;"><i class="fas fa-flask-vial text-success"></i> Mapping Standar LOINC Laboratorium</h3>
      <button type="button" class="modal-close" onclick="closeModal('modalLabSS')">&times;</button>
    </div>
    <form onsubmit="submitFormMapping(event, 'satusehat_save_lab', 'modalLabSS')">
      <div class="modal-body" style="display:flex;flex-direction:column;gap:12px;">
        <input type="hidden" name="id_template" id="mLab_id_template">
        <input type="hidden" name="kd_jenis_prw" id="mLab_kd_jenis">
        <div>
          <label style="font-size:12px;font-weight:700;color:#334155;">Pemeriksaan Lab:</label>
          <input type="text" id="mLab_nm_pemeriksaan" class="form-control" style="background:#f8fafc;" readonly>
        </div>
        <div>
          <label style="font-size:12px;font-weight:700;color:#334155;">Kode LOINC <span style="color:#ef4444;">*</span>:</label>
          <input type="text" name="code" id="mLab_code" class="form-control" required placeholder="Contoh: 718-7, 2345-7">
        </div>
        <div>
          <label style="font-size:12px;font-weight:700;color:#334155;">Display LOINC:</label>
          <input type="text" name="display" id="mLab_display" class="form-control" placeholder="Contoh: Hemoglobin [Mass/volume] in Blood">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalLabSS')">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan LOINC</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal 8: Edit Mapping Obat KFA -->
<div class="modal-overlay" id="modalObatSS" onclick="if(event.target===this) closeModal('modalObatSS')">
  <div class="modal" style="max-width:540px;">
    <div class="modal-header">
      <h3 class="modal-title" style="font-size:15px;font-weight:700;"><i class="fas fa-capsules text-warning"></i> Mapping KFA Obat Satu Sehat</h3>
      <button type="button" class="modal-close" onclick="closeModal('modalObatSS')">&times;</button>
    </div>
    <form onsubmit="submitFormMapping(event, 'satusehat_save_obat', 'modalObatSS')">
      <div class="modal-body" style="display:flex;flex-direction:column;gap:12px;">
        <input type="hidden" name="kode_brng" id="mObatSS_kode_brng">
        <div>
          <label style="font-size:12px;font-weight:700;color:#334155;">Obat SIMKlinik:</label>
          <input type="text" id="mObatSS_nama_brng" class="form-control" style="background:#f8fafc;" readonly>
        </div>
        <div>
          <label style="font-size:12px;font-weight:700;color:#334155;">Kode KFA (PO / Generik):</label>
          <input type="text" name="obat_code" id="mObatSS_code" class="form-control" placeholder="Contoh: 93000123">
        </div>
        <div>
          <label style="font-size:12px;font-weight:700;color:#334155;">Display KFA:</label>
          <input type="text" name="obat_display" id="mObatSS_display" class="form-control" placeholder="Nama obat resmi KFA">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
          <div>
            <label style="font-size:11px;color:#64748b;">Bentuk Sediaan (Display):</label>
            <input type="text" name="form_display" id="mObatSS_form" class="form-control" placeholder="cth: Tablet, Sirup">
          </div>
          <div>
            <label style="font-size:11px;color:#64748b;">Rute Pemberian:</label>
            <input type="text" name="route_display" id="mObatSS_route" class="form-control" placeholder="cth: Oral, Topikal">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalObatSS')">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan KFA</button>
      </div>
    </form>
  </div>
</div>

<style>
.mapping-nav-tab {
  padding: 10px 14px;
  font-size: 12.5px;
  font-weight: 600;
  color: #64748b;
  text-decoration: none;
  border-bottom: 3px solid transparent;
  white-space: nowrap;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition: all 0.2s ease;
}
.mapping-nav-tab:hover {
  color: #0f172a;
  background: #f8fafc;
}
.mapping-nav-tab.active {
  color: var(--primary-700);
  border-bottom-color: var(--primary-600);
  background: #f0fdfa;
  font-weight: 700;
}

/* ─── DEFINITIVE MODAL OVERLAY STYLES ─── */
.modal-overlay {
  position: fixed !important;
  top: 0 !important;
  left: 0 !important;
  right: 0 !important;
  bottom: 0 !important;
  width: 100vw !important;
  height: 100vh !important;
  background: rgba(15, 23, 42, 0.65) !important;
  backdrop-filter: blur(4px) !important;
  -webkit-backdrop-filter: blur(4px) !important;
  z-index: 99999 !important;
  display: none !important;
  align-items: center !important;
  justify-content: center !important;
  padding: 16px !important;
  box-sizing: border-box !important;
  opacity: 0;
  transition: opacity 0.2s ease-in-out;
}
.modal-overlay.active {
  display: flex !important;
  opacity: 1 !important;
}
.modal-overlay .modal {
  background: #ffffff !important;
  border-radius: 14px !important;
  width: 100% !important;
  max-width: 530px !important;
  max-height: 90vh !important;
  overflow-y: auto !important;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35) !important;
  display: flex !important;
  flex-direction: column !important;
  transform: translateY(15px) scale(0.97);
  transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1) !important;
  position: relative !important;
}
.modal-overlay.active .modal {
  transform: translateY(0) scale(1) !important;
}
.modal-overlay .modal-header {
  display: flex !important;
  align-items: center !important;
  justify-content: space-between !important;
  padding: 14px 18px !important;
  border-bottom: 1px solid #e2e8f0 !important;
  background: #f8fafc !important;
}
.modal-overlay .modal-body {
  padding: 18px 20px !important;
}
.modal-overlay .modal-footer {
  padding: 12px 18px !important;
  border-top: 1px solid #e2e8f0 !important;
  background: #f8fafc !important;
  display: flex !important;
  justify-content: flex-end !important;
  gap: 8px !important;
}
.modal-overlay .modal-close {
  background: none !important;
  border: none !important;
  font-size: 26px !important;
  line-height: 1 !important;
  color: #64748b !important;
  cursor: pointer !important;
  padding: 2px 8px !important;
  border-radius: 6px !important;
}
.modal-overlay .modal-close:hover {
  color: #ef4444 !important;
}
</style>

<script>
const AJAX_URL = '<?= BASE_URL ?>modules/mapping/ajax.php';

function closeModal(modalId) {
  const m = document.getElementById(modalId);
  if (m) {
    m.classList.remove('active');
    setTimeout(() => {
      m.style.display = 'none';
    }, 200);
  }
}

function openModal(modalId) {
  const m = document.getElementById(modalId);
  if (m) {
    m.style.display = 'flex';
    setTimeout(() => {
      m.classList.add('active');
    }, 10);
  }
}

// ─── Generic Form Submitter ─────────────────────────────────────
function submitFormMapping(e, actionName, modalId) {
  e.preventDefault();
  const form = e.target;
  const fd = new FormData(form);
  fd.append('action', actionName);

  const btn = form.querySelector('button[type="submit"]');
  const origText = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';

  fetch(AJAX_URL, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      btn.disabled = false;
      btn.innerHTML = origText;
      if (res.success) {
        showToast(res.message, 'success');
        closeModal(modalId);
        setTimeout(() => location.reload(), 600);
      } else {
        alert(res.message || 'Gagal menyimpan mapping.');
      }
    })
    .catch(err => {
      btn.disabled = false;
      btn.innerHTML = origText;
      alert('Terjadi kesalahan jaringan: ' + err);
    });
}

// ─── Modal Openers ──────────────────────────────────────────────
function openModalEditDokter(kd, nm, kdPc, nmPc) {
  document.getElementById('mDok_kd_dokter').value = kd;
  document.getElementById('mDok_nm_dokter').value = nm;
  document.getElementById('mDok_kd_pcare').value = kdPc || '';
  document.getElementById('mDok_nm_pcare').value = nmPc || '';
  openModal('modalDokterPcare');
}

function openModalEditPoli(kd, nm, kdPc, nmPc) {
  document.getElementById('mPoli_kd_poli').value = kd;
  document.getElementById('mPoli_nm_poli').value = nm;
  document.getElementById('mPoli_kd_pcare').value = kdPc || '';
  document.getElementById('mPoli_nm_pcare').value = nmPc || '';
  openModal('modalPoliPcare');
}

function openModalEditObatPcare(kd, nm, kdPc, nmPc) {
  document.getElementById('mObat_kode_brng').value = kd;
  document.getElementById('mObat_nama_brng').value = nm;
  document.getElementById('mObat_kd_pcare').value = kdPc || '';
  document.getElementById('mObat_nm_pcare').value = nmPc || '';
  openModal('modalObatPcare');
}

function openModalEditTindakanPcare(kd, nm, kdPc, nmPc) {
  document.getElementById('mTind_kd_jenis').value = kd;
  document.getElementById('mTind_nm_perawatan').value = nm;
  document.getElementById('mTind_kd_pcare').value = kdPc || '';
  document.getElementById('mTind_nm_pcare').value = nmPc || '';
  openModal('modalTindakanPcare');
}

function openModalEditPraktisi(kd, nm, ihs, ktp) {
  document.getElementById('mPrak_kd_dokter').value = kd;
  document.getElementById('mPrak_nm_dokter').value = nm;
  document.getElementById('mPrak_no_ktp').value = ktp || '-';
  document.getElementById('mPrak_id').value = ihs || '';
  openModal('modalPraktisiSS');
}

function openModalEditLokasiSS(kd, nm, org, loc, lng, lat, alt) {
  document.getElementById('mLok_kd_poli').value = kd;
  document.getElementById('mLok_nm_poli').value = nm;
  document.getElementById('mLok_org_id').value = org || '';
  document.getElementById('mLok_loc_id').value = loc || '';
  document.getElementById('mLok_long').value = lng || '';
  document.getElementById('mLok_lat').value = lat || '';
  document.getElementById('mLok_alt').value = alt || '';
  openModal('modalLokasiSS');
}

function openModalEditLabSS(idTpl, kdJenis, nm, code, disp) {
  document.getElementById('mLab_id_template').value = idTpl || 0;
  document.getElementById('mLab_kd_jenis').value = kdJenis || '';
  document.getElementById('mLab_nm_pemeriksaan').value = nm;
  document.getElementById('mLab_code').value = code || '';
  document.getElementById('mLab_display').value = disp || '';
  openModal('modalLabSS');
}

function openModalEditObatSS(kd, nm, code, disp, form, formDisp, route, routeDisp) {
  document.getElementById('mObatSS_kode_brng').value = kd;
  document.getElementById('mObatSS_nama_brng').value = nm;
  document.getElementById('mObatSS_code').value = code || '';
  document.getElementById('mObatSS_display').value = disp || '';
  document.getElementById('mObatSS_form').value = formDisp || '';
  document.getElementById('mObatSS_route').value = routeDisp || '';
  openModal('modalObatSS');
}

// ─── Tarik Data Dokter dari PCare API ────────────────────────────
function tarikDokterPcare(btn) {
  const icon = document.getElementById('iconTarikDokter');
  if (icon) icon.classList.add('fa-spin');
  btn.disabled = true;

  fetch(AJAX_URL + '?action=pcare_tarik_dokter')
    .then(r => r.json())
    .then(res => {
      if (icon) icon.classList.remove('fa-spin');
      btn.disabled = false;
      if (res.success) {
        let msg = res.message + '\n\nDaftar Dokter PCare:\n';
        res.list.forEach((d, i) => {
          msg += (i+1) + '. ' + d.kdDokter + ' - ' + d.nmDokter + '\n';
        });
        alert(msg);
      } else {
        alert(res.message || 'Gagal menarik data dokter dari PCare.');
      }
    })
    .catch(err => {
      if (icon) icon.classList.remove('fa-spin');
      btn.disabled = false;
      alert('Terjadi kesalahan jaringan: ' + err);
    });
}

// ─── Tarik Data Poli dari PCare API ──────────────────────────────
function tarikPoliPcare(btn) {
  const icon = document.getElementById('iconTarikPoli');
  if (icon) icon.classList.add('fa-spin');
  btn.disabled = true;

  fetch(AJAX_URL + '?action=pcare_tarik_poli')
    .then(r => r.json())
    .then(res => {
      if (icon) icon.classList.remove('fa-spin');
      btn.disabled = false;
      if (res.success) {
        let msg = res.message + '\n\nDaftar Poliklinik PCare:\n';
        res.list.forEach((p, i) => {
          msg += (i+1) + '. ' + p.kdPoli + ' - ' + p.nmPoli + '\n';
        });
        alert(msg);
      } else {
        alert(res.message || 'Gagal menarik data poli dari PCare.');
      }
    })
    .catch(err => {
      if (icon) icon.classList.remove('fa-spin');
      btn.disabled = false;
      alert('Terjadi kesalahan jaringan: ' + err);
    });
}

// ─── Lookup NIK Dokter ke Satu Sehat Kemenkes ────────────────────
function lookupNikKemenkes(kdDokter, nik, btn) {
  const origHtml = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mencari...';

  const fd = new FormData();
  fd.append('action', 'satusehat_lookup_praktisi');
  fd.append('nik', nik);

  fetch(AJAX_URL, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      btn.disabled = false;
      btn.innerHTML = origHtml;
      if (res.success && res.practitioner_id) {
        if (confirm(res.message + '\n\nSimpan mapping IHS ID ini sekarang?')) {
          const saveFd = new FormData();
          saveFd.append('action', 'satusehat_save_praktisi');
          saveFd.append('kd_dokter', kdDokter);
          saveFd.append('practitioner_id', res.practitioner_id);
          fetch(AJAX_URL, { method: 'POST', body: saveFd })
            .then(sr => sr.json())
            .then(sres => {
              showToast(sres.message, 'success');
              setTimeout(() => location.reload(), 600);
            });
        }
      } else {
        alert(res.message || 'Praktisi tidak ditemukan di Satu Sehat.');
      }
    })
    .catch(err => {
      btn.disabled = false;
      btn.innerHTML = origHtml;
      alert('Terjadi kesalahan jaringan: ' + err);
    });
}

// ─── Delete Handlers ─────────────────────────────────────────────
function hapusMappingDokterPcare(kd) {
  if (!confirm('Hapus mapping dokter PCare ini?')) return;
  const fd = new FormData();
  fd.append('action', 'pcare_del_dokter');
  fd.append('kd_dokter', kd);
  fetch(AJAX_URL, { method: 'POST', body: fd }).then(r => r.json()).then(res => {
    showToast(res.message, 'success');
    setTimeout(() => location.reload(), 500);
  });
}

function hapusMappingPoliPcare(kd) {
  if (!confirm('Hapus mapping poliklinik PCare ini?')) return;
  const fd = new FormData();
  fd.append('action', 'pcare_del_poli');
  fd.append('kd_poli_rs', kd);
  fetch(AJAX_URL, { method: 'POST', body: fd }).then(r => r.json()).then(res => {
    showToast(res.message, 'success');
    setTimeout(() => location.reload(), 500);
  });
}

function hapusMappingObatPcare(kd) {
  if (!confirm('Hapus mapping obat PCare ini?')) return;
  const fd = new FormData();
  fd.append('action', 'pcare_del_obat');
  fd.append('kode_brng', kd);
  fetch(AJAX_URL, { method: 'POST', body: fd }).then(r => r.json()).then(res => {
    showToast(res.message, 'success');
    setTimeout(() => location.reload(), 500);
  });
}

function hapusMappingTindakanPcare(kd) {
  if (!confirm('Hapus mapping tindakan PCare ini?')) return;
  const fd = new FormData();
  fd.append('action', 'pcare_del_tindakan');
  fd.append('kd_jenis_prw', kd);
  fetch(AJAX_URL, { method: 'POST', body: fd }).then(r => r.json()).then(res => {
    showToast(res.message, 'success');
    setTimeout(() => location.reload(), 500);
  });
}

function hapusMappingPraktisiSS(kd) {
  if (!confirm('Hapus mapping IHS Practitioner ini?')) return;
  const fd = new FormData();
  fd.append('action', 'satusehat_del_praktisi');
  fd.append('kd_dokter', kd);
  fetch(AJAX_URL, { method: 'POST', body: fd }).then(r => r.json()).then(res => {
    showToast(res.message, 'success');
    setTimeout(() => location.reload(), 500);
  });
}

function hapusMappingLokasiSS(kd) {
  if (!confirm('Hapus mapping lokasi Satu Sehat ini?')) return;
  const fd = new FormData();
  fd.append('action', 'satusehat_del_lokasi');
  fd.append('kd_poli', kd);
  fetch(AJAX_URL, { method: 'POST', body: fd }).then(r => r.json()).then(res => {
    showToast(res.message, 'success');
    setTimeout(() => location.reload(), 500);
  });
}
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
