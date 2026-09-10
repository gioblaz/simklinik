<?php
/**
 * SIMKlinik — Buat Form Permintaan / Order Laboratorium Baru
 */

$page_title    = 'Order Permintaan Laboratorium';
$active_module = 'laboratorium';

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

$no_rawat_get = sanitize($_GET['no_rawat'] ?? '');
$pasien_terpilih = null;

// Jika no_rawat disertakan di URL
if (!empty($no_rawat_get)) {
    $rawat_esc = $conn->real_escape_string($no_rawat_get);
    $res_p = $conn->query("
        SELECT r.*, p.nm_pasien, p.jk, p.tgl_lahir, p.no_ktp, p.no_peserta,
               p.alamat, p.no_tlp,
               d.nm_dokter, d.kd_dokter,
               pol.nm_poli, pj.png_jawab as nm_penjab
        FROM reg_periksa r
        JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
        LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
        LEFT JOIN poliklinik pol ON r.kd_poli = pol.kd_poli
        LEFT JOIN penjab pj ON p.kd_pj = pj.kd_pj
        WHERE r.no_rawat = '$rawat_esc'
        LIMIT 1
    ");
    if ($res_p && $res_p->num_rows > 0) {
        $pasien_terpilih = $res_p->fetch_assoc();
    }
}

// ─── Load Master Dokter ───────────────────────────────────────
$dokter_list = [];
$res_d = $conn->query("SELECT kd_dokter, nm_dokter FROM dokter WHERE status = '1' ORDER BY nm_dokter ASC");
if ($res_d) {
    while ($r = $res_d->fetch_assoc()) $dokter_list[] = $r;
}

// ─── Load Master Paket Lab & Sub-item Template ────────────────
$lab_packages = [];
$res_pkg = $conn->query("
    SELECT j.*, 
           (SELECT COUNT(*) FROM template_laboratorium t WHERE t.kd_jenis_prw = j.kd_jenis_prw) as total_items
    FROM jns_perawatan_lab j
    WHERE j.status = '1'
    ORDER BY j.kategori ASC, j.nm_perawatan ASC
");

if ($res_pkg) {
    while ($pkg = $res_pkg->fetch_assoc()) {
        $kd_esc = $conn->real_escape_string($pkg['kd_jenis_prw']);
        $res_tpl = $conn->query("
            SELECT * FROM template_laboratorium 
            WHERE kd_jenis_prw = '$kd_esc' 
            ORDER BY urut ASC, id_template ASC
        ");
        $items = [];
        if ($res_tpl) {
            while ($tpl = $res_tpl->fetch_assoc()) {
                $items[] = $tpl;
            }
        }
        $pkg['templates'] = $items;
        $lab_packages[] = $pkg;
    }
}

// ─── Proses Simpan Permintaan Lab ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_order'])) {
    $no_rawat_post  = sanitize($_POST['no_rawat'] ?? '');
    $dokter_perujuk = sanitize($_POST['dokter_perujuk'] ?? '');
    $status_rawat   = sanitize($_POST['status_rawat'] ?? 'ralan');
    $diagnosa       = sanitize($_POST['diagnosa_klinis'] ?? '');
    $informasi      = sanitize($_POST['informasi_tambahan'] ?? '');
    $selected_pkgs  = $_POST['paket_lab'] ?? [];
    $selected_items = $_POST['item_lab'] ?? [];

    if (empty($no_rawat_post)) {
        set_flash('danger', 'Silakan pilih pasien terlebih dahulu.');
    } elseif (empty($selected_pkgs)) {
        set_flash('warning', 'Pilih minimal satu paket pemeriksaan laboratorium.');
    } else {
        $tgl_today = date('Y-m-d');
        $jam_today = date('H:i:s');
        $prefix    = 'PK' . date('Ymd');

        // Generate No Order
        $res_max = $conn->query("SELECT MAX(noorder) as max_order FROM permintaan_lab WHERE noorder LIKE '$prefix%'");
        if ($res_max && $row_m = $res_max->fetch_assoc()) {
            $last_no = $row_m['max_order'];
            $seq     = $last_no ? (int)substr($last_no, -4) + 1 : 1;
        } else {
            $seq = 1;
        }
        $noorder = $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);

        $noorder_esc = $conn->real_escape_string($noorder);
        $rawat_esc   = $conn->real_escape_string($no_rawat_post);
        $dr_esc      = $conn->real_escape_string($dokter_perujuk);
        $stts_esc    = $conn->real_escape_string($status_rawat);
        $diag_esc    = $conn->real_escape_string($diagnosa);
        $info_esc    = $conn->real_escape_string($informasi);

        // 1. Insert permintaan_lab
        $sql_order = "
            INSERT INTO permintaan_lab (
                noorder, no_rawat, tgl_permintaan, jam_permintaan,
                tgl_sampel, jam_sampel, tgl_hasil, jam_hasil,
                dokter_perujuk, status, informasi_tambahan, diagnosa_klinis
            ) VALUES (
                '$noorder_esc', '$rawat_esc', '$tgl_today', '$jam_today',
                '0000-00-00', '00:00:00', '0000-00-00', '00:00:00',
                '$dr_esc', '$stts_esc', '$info_esc', '$diag_esc'
            )
        ";

        if ($conn->query($sql_order)) {
            // 2. Insert permintaan_pemeriksaan_lab & detail items
            foreach ($selected_pkgs as $kd_pkg) {
                $kd_pkg_esc = $conn->real_escape_string($kd_pkg);
                $conn->query("INSERT INTO permintaan_pemeriksaan_lab (noorder, kd_jenis_prw, stts_bayar) VALUES ('$noorder_esc', '$kd_pkg_esc', 'Belum')");

                // Get all template items for this package
                $res_tpl = $conn->query("SELECT id_template FROM template_laboratorium WHERE kd_jenis_prw = '$kd_pkg_esc'");
                if ($res_tpl) {
                    while ($tpl = $res_tpl->fetch_assoc()) {
                        $id_tpl = (int)$tpl['id_template'];
                        // If specific items were checked or by default all items included
                        $conn->query("INSERT INTO permintaan_detail_permintaan_lab (noorder, kd_jenis_prw, id_template, stts_bayar) VALUES ('$noorder_esc', '$kd_pkg_esc', $id_tpl, 'Belum')");
                    }
                }
            }

            set_flash('success', "Permintaan Laboratorium berhasil dibuat dengan No. Order <strong>$noorder</strong>.");
            redirect(BASE_URL . 'modules/laboratorium/index.php');
        } else {
            set_flash('danger', 'Gagal membuat permintaan lab: ' . $conn->error);
        }
    }
}

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<!-- ─── Header ────────────────────────────────────────────── -->
<div class="page-header" style="margin-bottom:16px;">
  <div class="page-header-left">
    <div style="display:flex;align-items:center;gap:10px;">
      <a href="<?= BASE_URL ?>modules/laboratorium/index.php" class="btn btn-outline btn-sm" style="border-color:#cbd5e1;color:#334155;" title="Kembali ke Antrean">
        <i class="fas fa-arrow-left"></i>
      </a>
      <div>
        <h1 class="page-title" style="margin:0;font-size:18px;font-weight:800;color:#0f172a;">Form Permintaan Laboratorium Baru</h1>
        <p class="page-subtitle" style="margin:2px 0 0;font-size:12px;color:#64748b;">Pilih Pasien, Dokter Pengirim, dan Paket Pemeriksaan Laboratorium</p>
      </div>
    </div>
  </div>
</div>

<form method="POST" action="" id="formOrderLab">

  <div style="display:grid;grid-template-columns:1fr 340px;gap:16px;align-items:start;">

    <!-- ─── Kolom Kiri: Pasien & Pilihan Paket Lab ─────────── -->
    <div style="display:flex;flex-direction:column;gap:16px;">
      
      <!-- 1. Identitas Pasien Terpilih / Pencarian Pasien -->
      <div class="card" style="border-radius:10px;background:#ffffff;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
        <div class="card-header" style="background:#f8fafc;padding:12px 18px;display:flex;justify-content:space-between;align-items:center;">
          <div class="card-title" style="font-size:13.5px;font-weight:700;color:#0f172a;">
            <i class="fas fa-user-injured text-primary"></i> Identitas Pasien
          </div>
          <?php if ($pasien_terpilih): ?>
            <button type="button" class="btn btn-outline btn-sm" onclick="showSearchModal()" style="font-size:11px;padding:3px 8px;">
              <i class="fas fa-exchange-alt"></i> Ganti Pasien
            </button>
          <?php endif; ?>
        </div>
        
        <div class="card-body" style="padding:16px 18px;">
          <input type="hidden" name="no_rawat" id="selectedNoRawat" value="<?= htmlspecialchars($pasien_terpilih['no_rawat'] ?? '') ?>">

          <?php if ($pasien_terpilih): ?>
            <div id="selectedPatientBox" style="display:flex;align-items:center;gap:14px;background:#f0f9ff;border:1px solid #bae6fd;padding:12px 16px;border-radius:8px;">
              <div style="width:44px;height:44px;border-radius:10px;background:#0284c7;color:#fff;display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:800;flex-shrink:0;">
                <?= strtoupper(substr($pasien_terpilih['nm_pasien'], 0, 1)) ?>
              </div>
              <div style="flex:1;">
                <div style="display:flex;align-items:center;gap:8px;">
                  <span style="font-size:14px;font-weight:800;color:#0f172a;"><?= htmlspecialchars($pasien_terpilih['nm_pasien']) ?></span>
                  <span style="font-size:11px;font-family:monospace;background:#e0f2fe;color:#0369a1;padding:2px 6px;border-radius:4px;font-weight:700;">
                    RM: <?= htmlspecialchars($pasien_terpilih['no_rkm_medis']) ?>
                  </span>
                  <?= badge_penjab($pasien_terpilih['nm_penjab'] ?? 'Umum') ?>
                </div>
                <div style="font-size:11.5px;color:#64748b;margin-top:3px;display:flex;gap:10px;flex-wrap:wrap;">
                  <span><?= icon_jk($pasien_terpilih['jk']) ?> <?= hitung_umur($pasien_terpilih['tgl_lahir']) ?></span>
                  <span>&bull; No. Rawat: <strong style="color:#0f172a;"><?= htmlspecialchars($pasien_terpilih['no_rawat']) ?></strong></span>
                  <span>&bull; Poli: <?= htmlspecialchars($pasien_terpilih['nm_poli'] ?? 'Rawat Jalan') ?></span>
                </div>
              </div>
            </div>
          <?php else: ?>
            <!-- Jika belum memilih pasien -->
            <div id="noPatientBox" style="text-align:center;padding:20px;background:#f8fafc;border:2px dashed #cbd5e1;border-radius:8px;">
              <i class="fas fa-search" style="font-size:28px;color:#94a3b8;display:block;margin-bottom:8px;"></i>
              <div style="font-weight:700;color:#334155;font-size:13px;">Belum Ada Pasien Terpilih</div>
              <p style="font-size:11.5px;color:#64748b;margin:4px 0 12px;">Cari pasien dari daftar kunjungan rawat jalan aktif untuk membuat order laboratorium.</p>
              <button type="button" class="btn btn-primary btn-sm" onclick="showSearchModal()">
                <i class="fas fa-search"></i> Cari & Pilih Pasien
              </button>
            </div>
          <?php endif; ?>

        </div>
      </div>

      <!-- 2. Katalog Paket Pemeriksaan Laboratorium -->
      <div class="card" style="border-radius:10px;background:#ffffff;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
        <div class="card-header" style="background:#f8fafc;padding:12px 18px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
          <div class="card-title" style="font-size:13.5px;font-weight:700;color:#0f172a;display:flex;align-items:center;gap:8px;">
            <i class="fas fa-flask text-primary"></i> Pilih Paket / Jenis Pemeriksaan Laboratorium
          </div>
          
          <!-- Filter Kategori & Search Paket -->
          <div style="display:flex;gap:8px;align-items:center;">
            <input type="text" id="filterPaketInput" onkeyup="filterPaketLab()" placeholder="Cari paket / nama tes..." class="form-control form-control-sm" style="font-size:11.5px;padding:4px 8px;width:180px;">
          </div>
        </div>

        <div class="card-body" style="padding:16px 18px;">
          
          <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(280px, 1fr));gap:12px;" id="paketGrid">
            <?php foreach ($lab_packages as $pkg): ?>
              <div class="paket-card" data-nama="<?= strtolower(htmlspecialchars($pkg['nm_perawatan'])) ?>" data-kategori="<?= $pkg['kategori'] ?>"
                   style="border:1px solid #e2e8f0;border-radius:8px;padding:12px 14px;background:#ffffff;transition:all 0.15s;cursor:pointer;"
                   onclick="togglePaketCheckbox('chk_<?= $pkg['kd_jenis_prw'] ?>', event)">
                
                <div style="display:flex;align-items:flex-start;gap:10px;">
                  <input type="checkbox" name="paket_lab[]" value="<?= $pkg['kd_jenis_prw'] ?>" id="chk_<?= $pkg['kd_jenis_prw'] ?>"
                         data-harga="<?= (float)$pkg['total_byr'] ?>" data-nama="<?= htmlspecialchars($pkg['nm_perawatan']) ?>"
                         onchange="updateTotalBiaya()" style="width:17px;height:17px;margin-top:2px;cursor:pointer;" onclick="event.stopPropagation()">
                  
                  <div style="flex:1;">
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                      <span style="font-size:10px;font-weight:700;color:#0284c7;background:#e0f2fe;padding:1px 5px;border-radius:4px;font-family:monospace;">
                        <?= $pkg['kd_jenis_prw'] ?> &bull; <?= $pkg['kategori'] ?>
                      </span>
                      <span style="font-size:12.5px;font-weight:800;color:#059669;">
                        <?= rupiah((float)$pkg['total_byr']) ?>
                      </span>
                    </div>

                    <div style="font-size:13px;font-weight:700;color:#0f172a;margin-top:4px;line-height:1.3;">
                      <?= htmlspecialchars($pkg['nm_perawatan']) ?>
                    </div>

                    <div style="font-size:11px;color:#64748b;margin-top:4px;">
                      <i class="fas fa-list-check" style="font-size:10px;"></i> <?= count($pkg['templates']) ?> Parameter Pemeriksaan
                    </div>

                    <!-- Collapsible preview parameters -->
                    <?php if (!empty($pkg['templates'])): ?>
                      <div style="margin-top:6px;font-size:10.5px;color:#475569;background:#f8fafc;padding:6px 8px;border-radius:6px;border:1px dashed #e2e8f0;max-height:60px;overflow-y:auto;">
                        <?= implode(', ', array_map(function($t) { return htmlspecialchars($t['Pemeriksaan']); }, $pkg['templates'])) ?>
                      </div>
                    <?php endif; ?>

                  </div>
                </div>

              </div>
            <?php endforeach; ?>
          </div>

        </div>
      </div>

    </div>

    <!-- ─── Kolom Kanan: Detail Order, Form Dokter & Ringkasan Biaya ─── -->
    <div style="display:flex;flex-direction:column;gap:16px;">
      
      <div class="card" style="border-radius:10px;background:#ffffff;box-shadow:0 1px 3px rgba(0,0,0,0.04);position:sticky;top:80px;">
        <div class="card-header" style="background:#f8fafc;padding:12px 18px;">
          <div class="card-title" style="font-size:13.5px;font-weight:700;color:#0f172a;">
            <i class="fas fa-file-signature text-primary"></i> Data Permintaan
          </div>
        </div>

        <div class="card-body" style="padding:16px 18px;display:flex;flex-direction:column;gap:12px;">
          
          <!-- Dokter Perujuk -->
          <div class="form-group" style="margin:0;">
            <label class="form-label" style="font-size:11.5px;font-weight:700;color:#334155;">Dokter Pengirim / Perujuk <span class="text-danger">*</span></label>
            <select name="dokter_perujuk" class="form-control" required style="font-size:12px;">
              <option value="">-- Pilih Dokter --</option>
              <?php foreach ($dokter_list as $dr): ?>
                <?php $sel = ($pasien_terpilih && $pasien_terpilih['kd_dokter'] === $dr['kd_dokter']) ? 'selected' : ''; ?>
                <option value="<?= htmlspecialchars($dr['kd_dokter']) ?>" <?= $sel ?>>
                  <?= htmlspecialchars($dr['nm_dokter']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Jenis Rawat -->
          <div class="form-group" style="margin:0;">
            <label class="form-label" style="font-size:11.5px;font-weight:700;color:#334155;">Jenis Pelayanan</label>
            <select name="status_rawat" class="form-control" style="font-size:12px;">
              <option value="ralan" selected>Rawat Jalan (Ralan)</option>
              <option value="ranap">Rawat Inap (Ranap)</option>
            </select>
          </div>

          <!-- Diagnosa Klinis -->
          <div class="form-group" style="margin:0;">
            <label class="form-label" style="font-size:11.5px;font-weight:700;color:#334155;">Diagnosa / Indikasi Klinis</label>
            <input type="text" name="diagnosa_klinis" class="form-control" placeholder="cth: Febris H-3, Hipertensi..." style="font-size:12px;">
          </div>

          <!-- Informasi Tambahan -->
          <div class="form-group" style="margin:0;">
            <label class="form-label" style="font-size:11.5px;font-weight:700;color:#334155;">Catatan Tambahan untuk Lab</label>
            <textarea name="informasi_tambahan" class="form-control" rows="2" placeholder="cth: Cito! Pasien puasa 10 jam..." style="font-size:12px;"></textarea>
          </div>

          <hr style="border:none;border-top:1px dashed #e2e8f0;margin:6px 0;">

          <!-- Ringkasan Biaya -->
          <div>
            <div style="font-size:11.5px;font-weight:700;color:#64748b;margin-bottom:6px;">Ringkasan Order Lab:</div>
            <div id="selectedPaketList" style="font-size:11px;color:#334155;max-height:100px;overflow-y:auto;margin-bottom:8px;">
              <em style="color:#94a3b8;">Belum ada paket dipilih</em>
            </div>

            <div style="background:#f8fafc;padding:10px 12px;border-radius:8px;border:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;">
              <span style="font-size:12px;font-weight:700;color:#334155;">Estimasi Biaya:</span>
              <span id="grandTotalBiaya" style="font-size:15px;font-weight:800;color:#059669;">Rp 0</span>
            </div>
          </div>

          <!-- Submit Button -->
          <button type="submit" name="simpan_order" class="btn btn-primary" style="padding:10px;font-size:13px;font-weight:700;margin-top:6px;background:linear-gradient(135deg, #0284c7, #0369a1);border:none;">
            <i class="fas fa-paper-plane"></i> Kirim Order Permintaan Lab
          </button>

        </div>
      </div>

    </div>

  </div>

</form>

<!-- ─── Modal Cari Pasien ─────────────────────────────────── -->
<div id="searchPasienModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(15,23,42,0.6);z-index:9999;align-items:center;justify-content:center;padding:16px;">
  <div style="background:#ffffff;border-radius:12px;width:100%;max-width:600px;max-height:85vh;display:flex;flex-direction:column;box-shadow:0 10px 25px rgba(0,0,0,0.2);">
    
    <div style="padding:16px 20px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;">
      <h3 style="margin:0;font-size:15px;font-weight:800;color:#0f172a;"><i class="fas fa-search text-primary"></i> Cari & Pilih Pasien Kunjungan</h3>
      <button type="button" onclick="closeSearchModal()" style="background:none;border:none;font-size:18px;color:#94a3b8;cursor:pointer;">&times;</button>
    </div>

    <div style="padding:16px 20px;border-bottom:1px solid #f1f5f9;">
      <div style="position:relative;">
        <input type="text" id="inputModalSearch" class="form-control" placeholder="Ketik No. RM, Nama Pasien, atau No. Rawat..." style="padding-left:36px;font-size:13px;">
        <i class="fas fa-search" style="position:absolute;left:12px;top:10px;color:#94a3b8;"></i>
      </div>
    </div>

    <div style="padding:10px 20px;flex:1;overflow-y:auto;" id="modalSearchResults">
      <div style="text-align:center;padding:24px;color:#94a3b8;font-size:12px;">
        Ketik kata kunci untuk mencari data pasien...
      </div>
    </div>

    <div style="padding:12px 20px;border-top:1px solid #e2e8f0;text-align:right;">
      <button type="button" class="btn btn-outline btn-sm" onclick="closeSearchModal()">Tutup</button>
    </div>

  </div>
</div>

<script>
// ─── Interactive Checkbox & Total Calculation ──────────────────
function togglePaketCheckbox(chkId, event) {
  const chk = document.getElementById(chkId);
  if (chk && event.target !== chk) {
    chk.checked = !chk.checked;
    updateTotalBiaya();
  }
}

function updateTotalBiaya() {
  const checkboxes = document.querySelectorAll('input[name="paket_lab[]"]:checked');
  let total = 0;
  let html = '';

  if (checkboxes.length === 0) {
    html = '<em style="color:#94a3b8;">Belum ada paket dipilih</em>';
  } else {
    checkboxes.forEach(cb => {
      const harga = parseFloat(cb.dataset.harga || 0);
      const nama = cb.dataset.nama || '';
      total += harga;
      html += `<div style="display:flex;justify-content:space-between;margin-bottom:3px;">
                <span>• ${nama}</span>
                <span style="font-weight:600;">Rp ${harga.toLocaleString('id-ID')}</span>
              </div>`;
    });
  }

  document.getElementById('selectedPaketList').innerHTML = html;
  document.getElementById('grandTotalBiaya').innerText = 'Rp ' + total.toLocaleString('id-ID');
}

// ─── Filter Paket Lab ──────────────────────────────────────────
function filterPaketLab() {
  const query = document.getElementById('filterPaketInput').value.toLowerCase();
  const cards = document.querySelectorAll('.paket-card');

  cards.forEach(card => {
    const nama = card.dataset.nama || '';
    if (nama.includes(query)) {
      card.style.display = 'block';
    } else {
      card.style.display = 'none';
    }
  });
}

// ─── Modal Search Pasien ───────────────────────────────────────
function showSearchModal() {
  document.getElementById('searchPasienModal').style.display = 'flex';
  document.getElementById('inputModalSearch').focus();
  loadInitialPasien();
}

function closeSearchModal() {
  document.getElementById('searchPasienModal').style.display = 'none';
}

function loadInitialPasien() {
  fetch('<?= BASE_URL ?>modules/laboratorium/ajax.php?action=search_pasien&q=')
    .then(res => res.json())
    .then(data => renderSearchResults(data.data));
}

document.getElementById('inputModalSearch').addEventListener('keyup', function() {
  const q = this.value;
  fetch('<?= BASE_URL ?>modules/laboratorium/ajax.php?action=search_pasien&q=' + encodeURIComponent(q))
    .then(res => res.json())
    .then(data => renderSearchResults(data.data));
});

function renderSearchResults(list) {
  const container = document.getElementById('modalSearchResults');
  if (!list || list.length === 0) {
    container.innerHTML = '<div style="text-align:center;padding:20px;color:#94a3b8;font-size:12px;">Pasien tidak ditemukan.</div>';
    return;
  }

  let html = '<div style="display:flex;flex-direction:column;gap:8px;">';
  list.forEach(p => {
    html += `
      <div onclick="selectPasien('${p.no_rawat}')" style="padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;cursor:pointer;background:#ffffff;transition:background 0.15s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='#ffffff'">
        <div style="display:flex;justify-content:space-between;align-items:center;">
          <strong style="font-size:13px;color:#0f172a;">${p.nm_pasien}</strong>
          <span style="font-size:11px;font-family:monospace;background:#e0f2fe;color:#0284c7;padding:1px 6px;border-radius:4px;">RM: ${p.no_rkm_medis}</span>
        </div>
        <div style="font-size:11.5px;color:#64748b;margin-top:2px;">
          ${p.no_rawat} &bull; ${p.nm_poli || 'Rawat Jalan'} &bull; ${p.nm_dokter || '-'}
        </div>
      </div>
    `;
  });
  html += '</div>';
  container.innerHTML = html;
}

function selectPasien(noRawat) {
  window.location.href = '<?= BASE_URL ?>modules/laboratorium/permintaan.php?no_rawat=' + encodeURIComponent(noRawat);
}
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
