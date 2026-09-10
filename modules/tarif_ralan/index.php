<?php
/**
 * SIMKlinik — Master Data Tarif Tindakan Rawat Jalan (jns_perawatan)
 */

$page_title    = 'Tarif Tindakan Ralan';
$active_module = 'tarif_ralan';

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

// ─── Proses Simpan / Update Tindakan ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['form_action'] ?? '');

    if ($action === 'simpan') {
        $kd_jenis_prw     = $conn->real_escape_string(trim($_POST['kd_jenis_prw'] ?? ''));
        $nm_perawatan     = $conn->real_escape_string(trim($_POST['nm_perawatan'] ?? ''));
        $kd_kategori      = $conn->real_escape_string(trim($_POST['kd_kategori'] ?? '-'));
        $material         = (float)($_POST['material'] ?? 0);
        $bhp              = (float)($_POST['bhp'] ?? 0);
        $tarif_tindakandr = (float)($_POST['tarif_tindakandr'] ?? 0);
        $tarif_tindakanpr = (float)($_POST['tarif_tindakanpr'] ?? 0);
        $kso              = (float)($_POST['kso'] ?? 0);
        $menejemen        = (float)($_POST['menejemen'] ?? 0);
        $status           = ($_POST['status'] ?? '1') === '1' ? '1' : '0';

        $total_byrdr   = $material + $bhp + $tarif_tindakandr + $kso + $menejemen;
        $total_byrpr   = $material + $bhp + $tarif_tindakanpr + $kso + $menejemen;
        $total_byrdrpr = $material + $bhp + $tarif_tindakandr + $tarif_tindakanpr + $kso + $menejemen;

        $is_edit = !empty($_POST['is_edit']);

        if (empty($kd_jenis_prw) || empty($nm_perawatan)) {
            set_flash('danger', 'Kode dan Nama Tindakan wajib diisi.');
        } else {
            if ($is_edit) {
                $sql = "UPDATE jns_perawatan SET
                            nm_perawatan     = '$nm_perawatan',
                            kd_kategori      = '$kd_kategori',
                            material         = $material,
                            bhp              = $bhp,
                            tarif_tindakandr = $tarif_tindakandr,
                            tarif_tindakanpr = $tarif_tindakanpr,
                            kso              = $kso,
                            menejemen        = $menejemen,
                            total_byrdr      = $total_byrdr,
                            total_byrpr      = $total_byrpr,
                            total_byrdrpr    = $total_byrdrpr,
                            status           = '$status'
                        WHERE kd_jenis_prw = '$kd_jenis_prw'";
                if ($conn->query($sql)) {
                    set_flash('success', "Tarif tindakan <strong>$nm_perawatan</strong> ($kd_jenis_prw) berhasil diperbarui.");
                } else {
                    set_flash('danger', 'Gagal update: ' . $conn->error);
                }
            } else {
                $sql = "INSERT INTO jns_perawatan (
                            kd_jenis_prw, nm_perawatan, kd_kategori, material, bhp,
                            tarif_tindakandr, tarif_tindakanpr, kso, menejemen,
                            total_byrdr, total_byrpr, total_byrdrpr, kd_pj, kd_poli, status
                        ) VALUES (
                            '$kd_jenis_prw', '$nm_perawatan', '$kd_kategori', $material, $bhp,
                            $tarif_tindakandr, $tarif_tindakanpr, $kso, $menejemen,
                            $total_byrdr, $total_byrpr, $total_byrdrpr, '-', '-', '$status'
                        )";
                if ($conn->query($sql)) {
                    set_flash('success', "Tarif tindakan <strong>$nm_perawatan</strong> ($kd_jenis_prw) berhasil ditambahkan.");
                } else {
                    set_flash('danger', 'Gagal menyimpan: ' . $conn->error);
                }
            }
        }
        redirect(BASE_URL . 'modules/tarif_ralan/index.php');
    }

    if ($action === 'hapus') {
        $kd_jenis_prw = $conn->real_escape_string($_POST['kd_jenis_prw'] ?? '');
        if ($conn->query("DELETE FROM jns_perawatan WHERE kd_jenis_prw = '$kd_jenis_prw'")) {
            set_flash('success', "Tindakan $kd_jenis_prw berhasil dihapus.");
        } else {
            set_flash('danger', 'Gagal menghapus: ' . $conn->error);
        }
        redirect(BASE_URL . 'modules/tarif_ralan/index.php');
    }
}

// ─── Filter & Data Tindakan ───────────────────────────────────
$search = sanitize($_GET['q'] ?? '');
$status_filter = sanitize($_GET['status'] ?? '1');

$where = "1=1";
if ($status_filter !== '') {
    $where .= " AND status = '$status_filter'";
}
if ($search) {
    $s = $conn->real_escape_string($search);
    $where .= " AND (kd_jenis_prw LIKE '%$s%' OR nm_perawatan LIKE '%$s%')";
}

// Pagination
$per_page = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

$total_res = $conn->query("SELECT COUNT(*) as t FROM jns_perawatan WHERE $where");
$total_rows = $total_res ? (int)$total_res->fetch_assoc()['t'] : 0;
$total_pages = ceil($total_rows / $per_page);

$result = $conn->query("
    SELECT * FROM jns_perawatan
    WHERE $where
    ORDER BY nm_perawatan ASC
    LIMIT $per_page OFFSET $offset
");

$tindakan_list = [];
if ($result) while ($row = $result->fetch_assoc()) $tindakan_list[] = $row;

// Generate Next Kode Tindakan (e.g. RJ002)
$rk = $conn->query("SELECT kd_jenis_prw FROM jns_perawatan WHERE kd_jenis_prw LIKE 'RJ%' ORDER BY kd_jenis_prw DESC LIMIT 1");
$next_kd = 'RJ001';
if ($rk && $rk->num_rows > 0) {
    $last_kd = $rk->fetch_assoc()['kd_jenis_prw'];
    $num = (int)preg_replace('/[^0-9]/', '', $last_kd);
    $next_kd = 'RJ' . sprintf('%03d', $num + 1);
}

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<!-- ─── Page Header ──────────────────────────────────────── -->
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:14px;">
  <div>
    <h1 class="page-title" style="font-size:18px;margin-bottom:2px;">Master Data Tarif Tindakan Rawat Jalan</h1>
    <p class="page-subtitle" style="font-size:12px;margin:0;">Kelola daftar tarif tindakan, prosedur medis, jasa dokter, perawat, dan biaya BHP</p>
  </div>
  <div class="page-actions" style="display:flex;align-items:center;gap:8px;">
    <button type="button" class="btn btn-primary" onclick="openModalTambah()" style="padding:7px 16px;font-size:12.5px;font-weight:700;">
      <i class="fas fa-plus"></i> Tambah Tindakan Baru
    </button>
  </div>
</div>

<!-- ─── Filter Bar ───────────────────────────────────────── -->
<div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:10px;padding:8px 14px;margin-bottom:12px;box-shadow:0 1px 2px rgba(0,0,0,0.03);">
  <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
    <div style="flex:1;min-width:220px;max-width:340px;">
      <input type="text" name="q" class="form-control" style="padding:5px 10px;font-size:12px;height:32px;"
             placeholder="Cari Kode atau Nama Tindakan..." value="<?= htmlspecialchars($search) ?>">
    </div>

    <div style="display:flex;align-items:center;gap:6px;">
      <label style="font-size:11.5px;color:#64748b;font-weight:600;">Status:</label>
      <select name="status" class="form-control" style="width:120px;padding:4px 8px;font-size:12px;height:32px;">
        <option value="1" <?= $status_filter==='1'?'selected':'' ?>>Aktif</option>
        <option value="0" <?= $status_filter==='0'?'selected':'' ?>>Non-Aktif</option>
        <option value=""  <?= $status_filter===''?'selected':'' ?>>Semua</option>
      </select>
    </div>

    <button type="submit" class="btn btn-primary btn-sm" style="height:32px;padding:4px 12px;font-size:12px;"><i class="fas fa-filter"></i> Filter</button>
    <?php if ($search || $status_filter !== '1'): ?>
      <a href="<?= BASE_URL ?>modules/tarif_ralan/index.php" class="btn btn-secondary btn-sm" style="height:32px;padding:4px 10px;font-size:12px;"><i class="fas fa-times"></i> Reset</a>
    <?php endif; ?>

    <div style="margin-left:auto;font-size:12px;color:#64748b;">
      Total: <strong><?= number_format($total_rows) ?></strong> jenis tindakan
    </div>
  </form>
</div>

<!-- ─── Tabel Daftar Tindakan ────────────────────────────── -->
<div class="card" style="margin-bottom:14px;">
  <div class="card-body" style="padding:0;">
    <div class="table-responsive">
      <table class="table table-hover mb-0" style="font-size:12.5px;">
        <thead>
          <tr style="background:#f8fafc;border-bottom:1px solid #e2e8f0;">
            <th style="width:90px;padding:8px 12px;">Kode</th>
            <th style="padding:8px 12px;">Nama Tindakan / Prosedur</th>
            <th style="text-align:right;padding:8px 10px;">Jasa Dokter</th>
            <th style="text-align:right;padding:8px 10px;">Jasa Perawat</th>
            <th style="text-align:right;padding:8px 10px;">BHP / Mat</th>
            <th style="text-align:right;padding:8px 12px;">Total Tarif (Dr)</th>
            <th style="text-align:right;padding:8px 12px;">Total (Dr+Pr)</th>
            <th style="text-align:center;width:80px;padding:8px 8px;">Status</th>
            <th style="text-align:right;width:100px;padding:8px 14px;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($tindakan_list)): ?>
            <tr>
              <td colspan="9" style="text-align:center;padding:36px;color:#94a3b8;">
                <i class="fas fa-hand-holding-medical" style="font-size:28px;color:#cbd5e1;display:block;margin-bottom:8px;"></i>
                Tidak ada data tarif tindakan yang sesuai pencarian.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($tindakan_list as $t): ?>
              <tr>
                <td style="padding:7px 12px;">
                  <code style="font-weight:700;color:#0f172a;background:#f1f5f9;padding:2px 6px;border-radius:4px;font-size:11.5px;">
                    <?= htmlspecialchars($t['kd_jenis_prw']) ?>
                  </code>
                </td>
                <td style="padding:7px 12px;">
                  <strong style="color:#0f172a;"><?= htmlspecialchars($t['nm_perawatan']) ?></strong>
                </td>
                <td style="text-align:right;padding:7px 10px;font-family:monospace;color:#475569;">
                  <?= rupiah((float)$t['tarif_tindakandr']) ?>
                </td>
                <td style="text-align:right;padding:7px 10px;font-family:monospace;color:#475569;">
                  <?= rupiah((float)$t['tarif_tindakanpr']) ?>
                </td>
                <td style="text-align:right;padding:7px 10px;font-family:monospace;color:#475569;">
                  <?= rupiah((float)($t['material'] + $t['bhp'])) ?>
                </td>
                <td style="text-align:right;padding:7px 12px;font-family:monospace;font-weight:700;color:#2563eb;">
                  <?= rupiah((float)$t['total_byrdr']) ?>
                </td>
                <td style="text-align:right;padding:7px 12px;font-family:monospace;font-weight:700;color:#059669;">
                  <?= rupiah((float)$t['total_byrdrpr']) ?>
                </td>
                <td style="text-align:center;padding:7px 8px;">
                  <span class="badge badge-<?= $t['status']==='1'?'success':'secondary' ?>" style="font-size:10px;">
                    <?= $t['status']==='1'?'Aktif':'Nonaktif' ?>
                  </span>
                </td>
                <td style="text-align:right;padding:7px 14px;">
                  <div style="display:inline-flex;gap:4px;">
                    <button type="button" class="btn btn-sm btn-secondary" style="padding:3px 7px;font-size:11px;color:#2563eb;" title="Edit Tindakan"
                            onclick='openModalEdit(<?= json_encode($t) ?>)'>
                      <i class="fas fa-edit"></i>
                    </button>
                    <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Hapus tindakan ini?')">
                      <input type="hidden" name="form_action" value="hapus">
                      <input type="hidden" name="kd_jenis_prw" value="<?= htmlspecialchars($t['kd_jenis_prw']) ?>">
                      <button type="submit" class="btn btn-sm btn-outline" style="padding:3px 7px;font-size:11px;color:#ef4444;border-color:#fca5a5;" title="Hapus">
                        <i class="fas fa-trash"></i>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  
  <!-- ─── Paginasi ─────────────────────────────────────────── -->
  <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 18px;font-size:12px;color:#64748b;border-top:1px solid #f1f5f9;flex-wrap:wrap;gap:10px;">
    <div>
      Menampilkan <strong><?= number_format($offset + 1) ?></strong> - <strong><?= number_format(min($total_rows, $offset + $per_page)) ?></strong> dari <strong><?= number_format($total_rows) ?></strong> tindakan
    </div>

    <?php if ($total_pages > 1): ?>
      <div style="display:flex;gap:4px;align-items:center;flex-wrap:wrap;">
        <?php
        $base_url = "?q=" . urlencode($search) . "&status=" . urlencode($status_filter);
        $window = 2;
        $start_p = max(1, $page - $window);
        $end_p   = min($total_pages, $page + $window);
        ?>

        <?php if ($page > 1): ?>
          <a href="<?= $base_url ?>&page=1" class="btn btn-outline btn-sm" style="padding:3px 8px;font-size:11px;" title="Pertama">&laquo;</a>
          <a href="<?= $base_url ?>&page=<?= $page - 1 ?>" class="btn btn-outline btn-sm" style="padding:3px 8px;font-size:11px;" title="Sebelumnya">&lsaquo;</a>
        <?php else: ?>
          <button class="btn btn-outline btn-sm" disabled style="padding:3px 8px;font-size:11px;opacity:0.5;">&laquo;</button>
          <button class="btn btn-outline btn-sm" disabled style="padding:3px 8px;font-size:11px;opacity:0.5;">&lsaquo;</button>
        <?php endif; ?>

        <?php if ($start_p > 1): ?>
          <a href="<?= $base_url ?>&page=1" class="btn btn-outline btn-sm" style="padding:3px 8px;font-size:11px;">1</a>
          <?php if ($start_p > 2): ?><span style="padding:0 4px;color:#94a3b8;">...</span><?php endif; ?>
        <?php endif; ?>

        <?php for ($p = $start_p; $p <= $end_p; $p++): ?>
          <?php if ($p == $page): ?>
            <span class="btn btn-primary btn-sm" style="padding:3px 9px;font-size:11px;font-weight:700;background:#db2777;border-color:#db2777;"><?= $p ?></span>
          <?php else: ?>
            <a href="<?= $base_url ?>&page=<?= $p ?>" class="btn btn-outline btn-sm" style="padding:3px 9px;font-size:11px;"><?= $p ?></a>
          <?php endif; ?>
        <?php endfor; ?>

        <?php if ($end_p < $total_pages): ?>
          <?php if ($end_p < $total_pages - 1): ?><span style="padding:0 4px;color:#94a3b8;">...</span><?php endif; ?>
          <a href="<?= $base_url ?>&page=<?= $total_pages ?>" class="btn btn-outline btn-sm" style="padding:3px 8px;font-size:11px;"><?= $total_pages ?></a>
        <?php endif; ?>

        <?php if ($page < $total_pages): ?>
          <a href="<?= $base_url ?>&page=<?= $page + 1 ?>" class="btn btn-outline btn-sm" style="padding:3px 8px;font-size:11px;" title="Selanjutnya">&rsaquo;</a>
          <a href="<?= $base_url ?>&page=<?= $total_pages ?>" class="btn btn-outline btn-sm" style="padding:3px 8px;font-size:11px;" title="Terakhir">&raquo;</a>
        <?php else: ?>
          <button class="btn btn-outline btn-sm" disabled style="padding:3px 8px;font-size:11px;opacity:0.5;">&rsaquo;</button>
          <button class="btn btn-outline btn-sm" disabled style="padding:3px 8px;font-size:11px;opacity:0.5;">&raquo;</button>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- ─── Modal Tambah / Edit Tindakan ─────────────────────── -->
<div id="modalTindakan" class="modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;z-index:9999;padding:16px;">
  <div class="modal-content" style="background:#fff;border-radius:12px;max-width:580px;width:100%;box-shadow:0 10px 25px rgba(0,0,0,0.2);overflow:hidden;">
    
    <div class="modal-header" style="padding:14px 18px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;background:#f8fafc;">
      <h3 style="margin:0;font-size:14px;font-weight:700;color:#0f172a;display:flex;align-items:center;gap:6px;">
        <i class="fas fa-hand-holding-medical text-primary"></i> <span id="modalTitle">Tambah Tarif Tindakan Ralan</span>
      </h3>
      <button type="button" style="background:none;border:none;font-size:16px;cursor:pointer;color:#64748b;" onclick="closeModalTindakan()">&times;</button>
    </div>

    <form method="POST" action="">
      <input type="hidden" name="form_action" value="simpan">
      <input type="hidden" name="is_edit" id="modalIsEdit" value="0">

      <div class="modal-body" style="padding:16px 20px;max-height:75vh;overflow-y:auto;display:flex;flex-direction:column;gap:12px;">
        
        <div class="form-row col-2">
          <div class="form-group">
            <label class="form-label">Kode Tindakan <span style="color:#ef4444;">*</span></label>
            <input type="text" name="kd_jenis_prw" id="m_kd_jenis_prw" class="form-control" required
                   value="<?= htmlspecialchars($next_kd) ?>" placeholder="cth: RJ001">
          </div>
          <div class="form-group">
            <label class="form-label">Status Tindakan</label>
            <select name="status" id="m_status" class="form-control">
              <option value="1">Aktif (Dapat Digunakan)</option>
              <option value="0">Non-Aktif</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Nama Tindakan / Prosedur Medis <span style="color:#ef4444;">*</span></label>
          <input type="text" name="nm_perawatan" id="m_nm_perawatan" class="form-control" required placeholder="cth: Injeksi Intramuskular / Nebulizer">
        </div>

        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px 14px;margin-top:4px;">
          <strong style="font-size:12.5px;color:#0f172a;display:block;margin-bottom:10px;"><i class="fas fa-coins text-primary"></i> Rincian Komponen Tarif (Rp):</strong>

          <div class="form-row col-2 mb-10">
            <div class="form-group">
              <label class="form-label" style="font-size:11.5px;">Jasa Medis Dokter (Rp)</label>
              <input type="number" name="tarif_tindakandr" id="m_tarif_tindakandr" class="form-control" value="0" min="0" oninput="calcTotalTarif()">
            </div>
            <div class="form-group">
              <label class="form-label" style="font-size:11.5px;">Jasa Medis Perawat (Rp)</label>
              <input type="number" name="tarif_tindakanpr" id="m_tarif_tindakanpr" class="form-control" value="0" min="0" oninput="calcTotalTarif()">
            </div>
          </div>

          <div class="form-row col-2 mb-10">
            <div class="form-group">
              <label class="form-label" style="font-size:11.5px;">BHP / Obat Tindakan (Rp)</label>
              <input type="number" name="bhp" id="m_bhp" class="form-control" value="0" min="0" oninput="calcTotalTarif()">
            </div>
            <div class="form-group">
              <label class="form-label" style="font-size:11.5px;">Biaya Material / Alat (Rp)</label>
              <input type="number" name="material" id="m_material" class="form-control" value="0" min="0" oninput="calcTotalTarif()">
            </div>
          </div>

          <div class="form-row col-2">
            <div class="form-group">
              <label class="form-label" style="font-size:11.5px;">KSO / Kerjasama (Rp)</label>
              <input type="number" name="kso" id="m_kso" class="form-control" value="0" min="0" oninput="calcTotalTarif()">
            </div>
            <div class="form-group">
              <label class="form-label" style="font-size:11.5px;">Manajemen Klinik (Rp)</label>
              <input type="number" name="menejemen" id="m_menejemen" class="form-control" value="0" min="0" oninput="calcTotalTarif()">
            </div>
          </div>

          <!-- Total Preview -->
          <div style="margin-top:12px;padding-top:10px;border-top:1px dashed #cbd5e1;display:flex;justify-content:space-between;align-items:center;font-size:12px;">
            <div>Total Tarif (Dokter): <strong id="previewTotalDr" style="color:#2563eb;">Rp 0</strong></div>
            <div>Total Tarif (Dokter & Perawat): <strong id="previewTotalDrPr" style="color:#059669;">Rp 0</strong></div>
          </div>
        </div>

      </div>

      <div class="modal-footer" style="padding:12px 18px;border-top:1px solid #e2e8f0;background:#f8fafc;display:flex;justify-content:flex-end;gap:8px;">
        <button type="button" class="btn btn-secondary" onclick="closeModalTindakan()">Batal</button>
        <button type="submit" class="btn btn-primary" style="font-weight:700;"><i class="fas fa-save"></i> Simpan Tarif</button>
      </div>
    </form>

  </div>
</div>

<script>
function calcTotalTarif() {
  const dr  = parseFloat(document.getElementById('m_tarif_tindakandr').value) || 0;
  const pr  = parseFloat(document.getElementById('m_tarif_tindakanpr').value) || 0;
  const bhp = parseFloat(document.getElementById('m_bhp').value) || 0;
  const mat = parseFloat(document.getElementById('m_material').value) || 0;
  const kso = parseFloat(document.getElementById('m_kso').value) || 0;
  const man = parseFloat(document.getElementById('m_menejemen').value) || 0;

  const totalDr = dr + bhp + mat + kso + man;
  const totalDrPr = dr + pr + bhp + mat + kso + man;

  document.getElementById('previewTotalDr').innerText = 'Rp ' + totalDr.toLocaleString('id-ID');
  document.getElementById('previewTotalDrPr').innerText = 'Rp ' + totalDrPr.toLocaleString('id-ID');
}

function openModalTambah() {
  document.getElementById('modalTitle').innerText = 'Tambah Tarif Tindakan Ralan';
  document.getElementById('modalIsEdit').value = '0';
  document.getElementById('m_kd_jenis_prw').readOnly = false;
  document.getElementById('m_kd_jenis_prw').value = '<?= $next_kd ?>';
  document.getElementById('m_nm_perawatan').value = '';
  document.getElementById('m_tarif_tindakandr').value = '0';
  document.getElementById('m_tarif_tindakanpr').value = '0';
  document.getElementById('m_bhp').value = '0';
  document.getElementById('m_material').value = '0';
  document.getElementById('m_kso').value = '0';
  document.getElementById('m_menejemen').value = '0';
  document.getElementById('m_status').value = '1';
  calcTotalTarif();

  document.getElementById('modalTindakan').style.display = 'flex';
}

function openModalEdit(t) {
  document.getElementById('modalTitle').innerText = 'Edit Tarif Tindakan Ralan';
  document.getElementById('modalIsEdit').value = '1';
  document.getElementById('m_kd_jenis_prw').value = t.kd_jenis_prw;
  document.getElementById('m_kd_jenis_prw').readOnly = true;
  document.getElementById('m_nm_perawatan').value = t.nm_perawatan;
  document.getElementById('m_tarif_tindakandr').value = t.tarif_tindakandr || 0;
  document.getElementById('m_tarif_tindakanpr').value = t.tarif_tindakanpr || 0;
  document.getElementById('m_bhp').value = t.bhp || 0;
  document.getElementById('m_material').value = t.material || 0;
  document.getElementById('m_kso').value = t.kso || 0;
  document.getElementById('m_menejemen').value = t.menejemen || 0;
  document.getElementById('m_status').value = t.status;
  calcTotalTarif();

  document.getElementById('modalTindakan').style.display = 'flex';
}

function closeModalTindakan() {
  document.getElementById('modalTindakan').style.display = 'none';
}
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
