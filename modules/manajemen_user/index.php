<?php
/**
 * SIMKlinik — Manajemen User (Akun Sistem)
 * CRUD untuk mlite_users: admin, medis, petugas
 */

$page_title    = 'Manajemen User';
$active_module = 'manajemen_user';

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_module_access('manajemen_user');

// Only admin can manage users
if (($_SESSION['user_role'] ?? '') !== 'admin') {
    set_flash('danger', 'Akses ditolak. Hanya Administrator yang dapat mengelola akun user.');
    redirect(BASE_URL . 'modules/dashboard/index.php');
}

// ─── HAPUS USER ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_user'])) {
    $del_id = (int)($_POST['user_id'] ?? 0);
    if ($del_id === (int)($_SESSION['user_id'] ?? 0)) {
        set_flash('danger', 'Tidak dapat menghapus akun yang sedang aktif digunakan.');
    } elseif ($del_id > 0) {
        if ($conn->query("DELETE FROM mlite_users WHERE id = $del_id")) {
            set_flash('success', 'User berhasil dihapus.');
        } else {
            set_flash('danger', 'Gagal menghapus: ' . $conn->error);
        }
    }
    redirect(BASE_URL . 'modules/manajemen_user/index.php');
}

// ─── SIMPAN / UPDATE USER ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_user'])) {
    $edit_id   = (int)($_POST['edit_id'] ?? 0);
    $username  = $conn->real_escape_string(trim($_POST['username'] ?? ''));
    $fullname  = $conn->real_escape_string(trim($_POST['fullname'] ?? ''));
    $email     = $conn->real_escape_string(trim($_POST['email'] ?? ''));
    $role      = $conn->real_escape_string(trim($_POST['role'] ?? 'petugas'));
    $access    = $conn->real_escape_string(trim($_POST['access'] ?? ''));
    $password  = trim($_POST['password'] ?? '');
    $description = $conn->real_escape_string(trim($_POST['description'] ?? ''));

    // Build access string
    $access_list = $_POST['access_modules'] ?? [];
    $access_str  = (in_array('all', $access_list) || $role === 'admin') ? 'all' : implode(',', array_filter($access_list));
    $access_str  = $conn->real_escape_string($access_str);

    if (empty($username) || empty($fullname)) {
        set_flash('danger', 'Username dan Nama Lengkap wajib diisi.');
        redirect(BASE_URL . 'modules/manajemen_user/index.php');
    }

    if ($edit_id > 0) {
        // UPDATE existing user
        $pass_sql = '';
        if (!empty($password)) {
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $hashed = $conn->real_escape_string($hashed);
            $pass_sql = ", password = '$hashed'";
        }
        $sql = "UPDATE mlite_users SET
                    username = '$username',
                    fullname = '$fullname',
                    email    = '$email',
                    role     = '$role',
                    access   = '$access_str',
                    description = '$description'
                    $pass_sql
                WHERE id = $edit_id";
        if ($conn->query($sql)) {
            set_flash('success', "Akun <strong>$username</strong> berhasil diperbarui.");
        } else {
            set_flash('danger', 'Gagal update: ' . $conn->error);
        }
    } else {
        // INSERT new user
        if (empty($password)) {
            set_flash('danger', 'Password wajib diisi untuk user baru.');
            redirect(BASE_URL . 'modules/manajemen_user/index.php');
        }
        // Check username unique
        $chk = $conn->query("SELECT id FROM mlite_users WHERE username = '$username' LIMIT 1");
        if ($chk && $chk->num_rows > 0) {
            set_flash('danger', "Username <strong>$username</strong> sudah digunakan. Pilih username lain.");
            redirect(BASE_URL . 'modules/manajemen_user/index.php');
        }
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $hashed = $conn->real_escape_string($hashed);
        $sql = "INSERT INTO mlite_users (username, fullname, email, password, role, access, description, avatar)
                VALUES ('$username', '$fullname', '$email', '$hashed', '$role', '$access_str', '$description', '')";
        if ($conn->query($sql)) {
            set_flash('success', "Akun <strong>$username</strong> berhasil dibuat.");
        } else {
            set_flash('danger', 'Gagal membuat user: ' . $conn->error);
        }
    }
    redirect(BASE_URL . 'modules/manajemen_user/index.php');
}

// ─── LOAD DATA ────────────────────────────────────────────────────────────────
$search   = $conn->real_escape_string(trim($_GET['q'] ?? ''));
$filter_role = $conn->real_escape_string($_GET['role'] ?? '');
$where_parts = [];
if ($search) $where_parts[] = "(username LIKE '%$search%' OR fullname LIKE '%$search%' OR email LIKE '%$search%')";
if ($filter_role) $where_parts[] = "role = '$filter_role'";
$where = $where_parts ? 'WHERE ' . implode(' AND ', $where_parts) : '';

$users_res = $conn->query("SELECT * FROM mlite_users $where ORDER BY role ASC, fullname ASC");
$users = [];
if ($users_res) while ($row = $users_res->fetch_assoc()) $users[] = $row;

// Counts by role
$count_admin   = count(array_filter($users, fn($u) => $u['role'] === 'admin'));
$count_medis   = count(array_filter($users, fn($u) => $u['role'] === 'medis'));
$count_petugas = count(array_filter($users, fn($u) => !in_array($u['role'], ['admin','medis'])));

// Edit mode - load specific user
$edit_user = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $er = $conn->query("SELECT * FROM mlite_users WHERE id = $edit_id LIMIT 1");
    if ($er && $er->num_rows > 0) $edit_user = $er->fetch_assoc();
}

// Available access modules
$all_modules = [
    'dashboard'        => 'Dashboard',
    'pasien'           => 'Data Pasien',
    'pendaftaran'      => 'Pendaftaran Antrean',
    'rekam_medis'      => 'Rawat Jalan (E-RM)',
    'laboratorium'     => 'Laboratorium',
    'farmasi'          => 'Farmasi & Apotek',
    'kasir'            => 'Kasir & Pembayaran',
    'kepegawaian'      => 'Kepegawaian & Dokter',
    'tarif_ralan'      => 'Tarif Tindakan Ralan',
    'master_lab'       => 'Master Tarif & Lab',
    'gudang_obat'      => 'Gudang Obat & Stok',
    'laporan'          => 'Laporan & Rekap',
    'bridging_monitor' => 'Monitoring PCare Live',
    'pcare'            => 'PCare BPJS',
    'satu_sehat'       => 'Satu Sehat Kemenkes',
    'antrean_bpjs'     => 'Antrean Mobile JKN',
    'bpjs_emr'         => 'E-RM BPJS',
    'manajemen_user'   => 'Manajemen User',
    'settings'         => 'Pengaturan Sistem',
];

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<!-- ─── Page Header ──────────────────────────────────────── -->
<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fas fa-users-cog text-primary" style="margin-right:8px;"></i>Manajemen User & Akun</h1>
    <p class="page-subtitle">Kelola akun login, hak akses, dan peran setiap pengguna sistem</p>
  </div>
  <div class="page-actions">
    <button type="button" class="btn btn-primary" data-open-modal="modalTambahUser" id="btnTambahUser">
      <i class="fas fa-user-plus"></i> Tambah User Baru
    </button>
  </div>
</div>

<!-- ─── Stats Cards ───────────────────────────────────────── -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:20px;">
  <div class="card" style="padding:16px;border-left:4px solid #dc2626;">
    <div style="display:flex;align-items:center;gap:12px;">
      <div style="width:44px;height:44px;background:#fef2f2;border-radius:12px;display:flex;align-items:center;justify-content:center;color:#dc2626;font-size:20px;">
        <i class="fas fa-shield-halved"></i>
      </div>
      <div>
        <div style="font-size:24px;font-weight:800;color:#dc2626;"><?= $count_admin ?></div>
        <div style="font-size:12px;color:var(--gray-500);">Administrator</div>
      </div>
    </div>
  </div>
  <div class="card" style="padding:16px;border-left:4px solid #2563eb;">
    <div style="display:flex;align-items:center;gap:12px;">
      <div style="width:44px;height:44px;background:#eff6ff;border-radius:12px;display:flex;align-items:center;justify-content:center;color:#2563eb;font-size:20px;">
        <i class="fas fa-user-doctor"></i>
      </div>
      <div>
        <div style="font-size:24px;font-weight:800;color:#2563eb;"><?= $count_medis ?></div>
        <div style="font-size:12px;color:var(--gray-500);">Tenaga Medis</div>
      </div>
    </div>
  </div>
  <div class="card" style="padding:16px;border-left:4px solid #059669;">
    <div style="display:flex;align-items:center;gap:12px;">
      <div style="width:44px;height:44px;background:#ecfdf5;border-radius:12px;display:flex;align-items:center;justify-content:center;color:#059669;font-size:20px;">
        <i class="fas fa-user-tie"></i>
      </div>
      <div>
        <div style="font-size:24px;font-weight:800;color:#059669;"><?= $count_petugas ?></div>
        <div style="font-size:12px;color:var(--gray-500);">Petugas / Staf</div>
      </div>
    </div>
  </div>
</div>

<!-- ─── Filter & Search ───────────────────────────────────── -->
<div class="card" style="margin-bottom:16px;">
  <div class="card-body" style="padding:12px 16px;">
    <form method="GET" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
      <div style="flex:1;min-width:200px;">
        <div style="position:relative;">
          <i class="fas fa-search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--gray-400);font-size:13px;"></i>
          <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" 
                 class="form-control" placeholder="Cari nama, username, email..."
                 style="padding-left:32px;">
        </div>
      </div>
      <select name="role" class="form-control" style="width:160px;">
        <option value="">Semua Role</option>
        <option value="admin" <?= $filter_role==='admin'?'selected':'' ?>>Admin</option>
        <option value="medis" <?= $filter_role==='medis'?'selected':'' ?>>Medis</option>
        <option value="petugas" <?= $filter_role==='petugas'?'selected':'' ?>>Petugas</option>
      </select>
      <button type="submit" class="btn btn-outline-primary"><i class="fas fa-filter"></i> Filter</button>
      <?php if ($search || $filter_role): ?>
        <a href="?" class="btn btn-outline"><i class="fas fa-times"></i> Reset</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<!-- ─── Users Table ───────────────────────────────────────── -->
<div class="card">
  <div class="card-header">
    <div class="card-title"><i class="fas fa-table text-primary"></i> Daftar Akun Pengguna</div>
    <span style="font-size:12px;color:var(--gray-500);"><?= count($users) ?> user ditemukan</span>
  </div>
  <div class="card-body" style="padding:0;">
    <?php if (empty($users)): ?>
      <div class="empty-state">
        <div class="empty-state-icon"><i class="fas fa-users-slash"></i></div>
        <div class="empty-state-title">Tidak ada user ditemukan</div>
        <div class="empty-state-desc">Coba ubah filter atau tambah user baru.</div>
      </div>
    <?php else: ?>
      <div class="table-wrapper">
        <table class="table">
          <thead>
            <tr>
              <th style="width:40px;">#</th>
              <th>Username</th>
              <th>Nama Lengkap</th>
              <th>Email</th>
              <th>Role</th>
              <th>Hak Akses</th>
              <th style="width:120px;text-align:center;">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $i => $u): 
              $role_label = match($u['role']) {
                'admin' => ['label'=>'Admin','cls'=>'badge-danger'],
                'medis' => ['label'=>'Medis','cls'=>'badge-primary'],
                default => ['label'=>ucfirst($u['role']),'cls'=>'badge-secondary']
              };
              $access_str = $u['access'] === 'all' ? '<span class="badge badge-warning">ALL ACCESS</span>' : 
                            implode(' ', array_map(fn($m) => '<span class="badge badge-secondary" style="margin:1px;font-size:10px;">'.htmlspecialchars($m).'</span>', array_filter(explode(',', $u['access']))));
              $is_me = ($u['id'] == ($_SESSION['user_id'] ?? 0));
            ?>
            <tr <?= $is_me ? 'style="background:var(--primary-50);"' : '' ?>>
              <td style="color:var(--gray-400);font-size:12px;"><?= $i+1 ?></td>
              <td>
                <div style="display:flex;align-items:center;gap:8px;">
                  <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,<?= $u['role']==='admin'?'#dc2626,#b91c1c':($u['role']==='medis'?'#2563eb,#1d4ed8':'#059669,#047857') ?>);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:13px;flex-shrink:0;">
                    <?= strtoupper(mb_substr($u['fullname'],0,1)) ?>
                  </div>
                  <div>
                    <div style="font-weight:700;font-family:monospace;font-size:13px;color:var(--gray-800);"><?= htmlspecialchars($u['username']) ?></div>
                    <?php if ($is_me): ?><div style="font-size:10px;color:#059669;font-weight:600;">● Anda (Login Aktif)</div><?php endif; ?>
                  </div>
                </div>
              </td>
              <td>
                <div style="font-weight:600;font-size:13px;"><?= htmlspecialchars($u['fullname']) ?></div>
                <?php if($u['description']): ?><div style="font-size:11px;color:var(--gray-400);"><?= htmlspecialchars($u['description']) ?></div><?php endif; ?>
              </td>
              <td style="font-size:12px;color:var(--gray-600);"><?= htmlspecialchars($u['email'] ?: '-') ?></td>
              <td><span class="badge <?= $role_label['cls'] ?>"><?= $role_label['label'] ?></span></td>
              <td style="max-width:280px;"><?= $access_str ?: '-' ?></td>
              <td style="text-align:center;">
                <div style="display:flex;gap:4px;justify-content:center;">
                  <button type="button" class="btn btn-sm btn-outline-primary btn-edit-user"
                          data-user='<?= htmlspecialchars(json_encode($u), ENT_QUOTES) ?>'
                          title="Edit User">
                    <i class="fas fa-edit"></i>
                  </button>
                  <?php if (!$is_me): ?>
                  <form method="POST" style="display:inline;" 
                        onsubmit="return confirm('Hapus akun <?= htmlspecialchars($u['username']) ?>? Tindakan ini tidak bisa dibatalkan.')">
                    <input type="hidden" name="hapus_user" value="1">
                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                    <button type="submit" class="btn btn-sm" style="color:#dc2626;border-color:#fca5a5;" title="Hapus User">
                      <i class="fas fa-trash"></i>
                    </button>
                  </form>
                  <?php endif; ?>
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

<!-- ─── Modal Tambah / Edit User ──────────────────────────── -->
<div class="modal-overlay" id="modalTambahUser">
  <div class="modal" style="max-width:680px;">
    <div class="modal-header">
      <h3 class="modal-title" id="modalUserTitle"><i class="fas fa-user-plus text-primary"></i> Tambah User Baru</h3>
      <button class="modal-close" data-close-modal="modalTambahUser"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST" id="formUser">
      <input type="hidden" name="simpan_user" value="1">
      <input type="hidden" name="edit_id" id="editUserId" value="0">
      <div class="modal-body">

        <!-- Row 1: Username + Fullname -->
        <div class="form-row col-2">
          <div class="form-group">
            <label class="form-label">Username <span class="required">*</span></label>
            <input type="text" name="username" id="fUsername" class="form-control" placeholder="contoh: dr.budi" required autocomplete="off">
            <small style="color:var(--gray-400);font-size:11px;">Huruf kecil, angka, titik, underscore</small>
          </div>
          <div class="form-group">
            <label class="form-label">Nama Lengkap <span class="required">*</span></label>
            <input type="text" name="fullname" id="fFullname" class="form-control" placeholder="cth: dr. Budi Santoso, Sp.A" required>
          </div>
        </div>

        <!-- Row 2: Email + Role -->
        <div class="form-row col-2">
          <div class="form-group">
            <label class="form-label">Email</label>
            <input type="email" name="email" id="fEmail" class="form-control" placeholder="email@klinik.com">
          </div>
          <div class="form-group">
            <label class="form-label">Role / Peran <span class="required">*</span></label>
            <select name="role" id="fRole" class="form-control" onchange="onRoleChange(this.value)">
              <option value="petugas">Petugas / Staf</option>
              <option value="medis">Tenaga Medis (Dokter)</option>
              <option value="admin">Administrator</option>
            </select>
          </div>
        </div>

        <!-- Row 3: Password -->
        <div class="form-row col-2">
          <div class="form-group">
            <label class="form-label">Password <span class="required" id="passRequired">*</span></label>
            <div style="position:relative;">
              <input type="password" name="password" id="fPassword" class="form-control" 
                     placeholder="Min. 6 karakter" autocomplete="new-password">
              <button type="button" onclick="this.previousElementSibling.type=this.previousElementSibling.type==='text'?'password':'text';this.querySelector('i').className='fas fa-eye'+(this.previousElementSibling.type==='text'?'-slash':'')"
                      style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--gray-400);">
                <i class="fas fa-eye"></i>
              </button>
            </div>
            <small id="passHint" style="color:var(--gray-400);font-size:11px;">Kosongkan untuk tidak mengubah password (mode edit)</small>
          </div>
          <div class="form-group">
            <label class="form-label">Keterangan / Jabatan</label>
            <input type="text" name="description" id="fDescription" class="form-control" placeholder="cth: Dokter Umum, Kasir, dll">
          </div>
        </div>

        <!-- Hak Akses Modul -->
        <div class="form-group" id="accessSection">
          <label class="form-label">Hak Akses Modul</label>
          <div style="background:var(--gray-50);border:1px solid var(--gray-200);border-radius:8px;padding:12px;">
            <div style="margin-bottom:8px;">
              <label style="display:flex;align-items:center;gap:8px;font-weight:600;color:#dc2626;cursor:pointer;">
                <input type="checkbox" name="access_modules[]" value="all" id="chkAll" onchange="toggleAllAccess(this.checked)">
                <i class="fas fa-infinity"></i> Akses Penuh (Semua Modul)
              </label>
            </div>
            <hr style="margin:8px 0;border-color:var(--gray-200);">
            <div id="moduleCheckboxes" style="display:grid;grid-template-columns:repeat(3,1fr);gap:6px;">
              <?php foreach ($all_modules as $mod_key => $mod_label): ?>
              <label style="display:flex;align-items:center;gap:6px;font-size:12px;cursor:pointer;padding:4px 6px;border-radius:6px;transition:background .15s;" 
                     onmouseover="this.style.background='var(--gray-100)'" onmouseout="this.style.background=''"
                     class="module-check-label">
                <input type="checkbox" name="access_modules[]" value="<?= $mod_key ?>" class="module-checkbox">
                <?= htmlspecialchars($mod_label) ?>
              </label>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-close-modal="modalTambahUser">Batal</button>
        <button type="submit" class="btn btn-primary" id="btnSaveUser">
          <i class="fas fa-save"></i> <span id="btnSaveUserTxt">Simpan User</span>
        </button>
      </div>
    </form>
  </div>
</div>

<script>
// ─── Role Change Handler ──────────────────────────────────────────────────────
function onRoleChange(role) {
  const accessSection = document.getElementById('accessSection');
  const chkAll = document.getElementById('chkAll');
  if (role === 'admin') {
    chkAll.checked = true;
    toggleAllAccess(true);
    accessSection.style.opacity = '0.5';
    accessSection.style.pointerEvents = 'none';
  } else {
    accessSection.style.opacity = '1';
    accessSection.style.pointerEvents = 'auto';
  }
}

function toggleAllAccess(checked) {
  const checkboxes = document.querySelectorAll('.module-checkbox');
  const moduleDiv = document.getElementById('moduleCheckboxes');
  checkboxes.forEach(cb => { cb.checked = checked; cb.disabled = checked; });
  moduleDiv.style.opacity = checked ? '0.4' : '1';
}

// ─── Edit User Button ─────────────────────────────────────────────────────────
document.querySelectorAll('.btn-edit-user').forEach(btn => {
  btn.addEventListener('click', function() {
    const user = JSON.parse(this.dataset.user);
    document.getElementById('modalUserTitle').innerHTML = '<i class="fas fa-user-edit text-primary"></i> Edit Akun: ' + user.username;
    document.getElementById('editUserId').value = user.id;
    document.getElementById('fUsername').value = user.username;
    document.getElementById('fFullname').value = user.fullname;
    document.getElementById('fEmail').value = user.email || '';
    document.getElementById('fRole').value = user.role;
    document.getElementById('fDescription').value = user.description || '';
    document.getElementById('fPassword').value = '';
    document.getElementById('passRequired').textContent = '';
    document.getElementById('btnSaveUserTxt').textContent = 'Perbarui User';

    // Set access checkboxes
    const accessMods = user.access === 'all' ? ['all'] : (user.access || '').split(',').map(s => s.trim());
    const hasAll = accessMods.includes('all') || user.role === 'admin';
    
    document.getElementById('chkAll').checked = hasAll;
    toggleAllAccess(hasAll);
    
    if (!hasAll) {
      document.querySelectorAll('.module-checkbox').forEach(cb => {
        cb.checked = accessMods.includes(cb.value);
        cb.disabled = false;
      });
    }

    onRoleChange(user.role);

    // Open modal
    openModal('modalTambahUser');
  });
});

// ─── Tambah User Button reset ─────────────────────────────────────────────────
document.getElementById('btnTambahUser').addEventListener('click', function() {
  document.getElementById('modalUserTitle').innerHTML = '<i class="fas fa-user-plus text-primary"></i> Tambah User Baru';
  document.getElementById('editUserId').value = '0';
  document.getElementById('formUser').reset();
  document.getElementById('passRequired').textContent = '*';
  document.getElementById('btnSaveUserTxt').textContent = 'Simpan User';
  document.querySelectorAll('.module-checkbox').forEach(cb => { cb.checked = false; cb.disabled = false; });
  document.getElementById('chkAll').checked = false;
  document.getElementById('moduleCheckboxes').style.opacity = '1';
  const accessSection = document.getElementById('accessSection');
  accessSection.style.opacity = '1';
  accessSection.style.pointerEvents = 'auto';
});
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
