<?php
/**
 * SIMKlinik — Dashboard
 */

$page_title    = 'Dashboard';
$active_module = 'dashboard';

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

// ─── Ambil Data Statistik ───────────────────────────────────
$stat_kunjungan   = stat_kunjungan_hari_ini();
$stat_pasien_baru = stat_pasien_baru_hari_ini();
$stat_total       = stat_total_pasien();
$stat_antrian     = stat_antrian_menunggu();
$kunjungan_list   = get_kunjungan_hari_ini(8);
$stok_hampir      = get_stok_hampir_habis(5);
$top_penyakit     = stat_10_besar_penyakit();
$weekly_data      = stat_kunjungan_mingguan();

// Kunjungan bulan ini vs bulan lalu
$bulan_ini  = date('Y-m');
$bulan_lalu = date('Y-m', strtotime('-1 month'));
$r1 = $conn->query("SELECT COUNT(*) as t FROM reg_periksa WHERE DATE_FORMAT(tgl_registrasi,'%Y-%m') = '$bulan_ini'");
$r2 = $conn->query("SELECT COUNT(*) as t FROM reg_periksa WHERE DATE_FORMAT(tgl_registrasi,'%Y-%m') = '$bulan_lalu'");
$bulan_ini_total  = $r1 ? (int)$r1->fetch_assoc()['t'] : 0;
$bulan_lalu_total = $r2 ? (int)$r2->fetch_assoc()['t'] : 0;
$pct_change = $bulan_lalu_total > 0 ? round((($bulan_ini_total - $bulan_lalu_total) / $bulan_lalu_total) * 100) : 0;

// Status integrasi
$pcare_url      = ($conn->query("SELECT value FROM mlite_settings WHERE module='pcare' AND field='usernameICare' LIMIT 1")->fetch_assoc()['value'] ?? '');
$satu_sehat_org = ($conn->query("SELECT value FROM mlite_settings WHERE module='satu_sehat' AND field='organizationid' LIMIT 1")->fetch_assoc()['value'] ?? '');
$bpjs_emr_id    = ($conn->query("SELECT value FROM mlite_settings WHERE module='bpjs_emr' AND field='consid' LIMIT 1")->fetch_assoc()['value'] ?? '');

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<!-- ─── Page Header ──────────────────────────────────────── -->
<div class="page-header">
  <div>
    <h1 class="page-title">Dashboard</h1>
    <p class="page-subtitle">
      Selamat datang, <strong><?= htmlspecialchars(explode(' ', $user['fullname'])[0]) ?></strong>!
      Berikut ringkasan aktivitas klinik hari ini.
    </p>
  </div>
  <div class="page-actions">
    <a href="<?= BASE_URL ?>modules/pendaftaran/tambah.php" class="btn btn-primary">
      <i class="fas fa-plus"></i> Daftar Pasien
    </a>
  </div>
</div>

<!-- ─── Stat Cards ───────────────────────────────────────── -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon blue"><i class="fas fa-calendar-check"></i></div>
    <div class="stat-content">
      <div class="stat-label">Kunjungan Hari Ini</div>
      <div class="stat-value"><?= number_format($stat_kunjungan) ?></div>
      <div class="stat-change <?= $pct_change >= 0 ? 'up' : 'down' ?>">
        <i class="fas fa-arrow-<?= $pct_change >= 0 ? 'up' : 'down' ?>"></i>
        <?= abs($pct_change) ?>% dari bulan lalu
      </div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon orange"><i class="fas fa-user-clock"></i></div>
    <div class="stat-content">
      <div class="stat-label">Antrian Menunggu</div>
      <div class="stat-value"><?= number_format($stat_antrian) ?></div>
      <div class="stat-change flat">
        <i class="fas fa-minus"></i> Saat ini
      </div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon green"><i class="fas fa-user-plus"></i></div>
    <div class="stat-content">
      <div class="stat-label">Pasien Baru Hari Ini</div>
      <div class="stat-value"><?= number_format($stat_pasien_baru) ?></div>
      <div class="stat-change flat">
        <i class="fas fa-minus"></i> Pendaftaran baru
      </div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon teal"><i class="fas fa-users"></i></div>
    <div class="stat-content">
      <div class="stat-label">Total Pasien Terdaftar</div>
      <div class="stat-value"><?= number_format($stat_total) ?></div>
      <div class="stat-change flat">
        <i class="fas fa-minus"></i> Rekam medis aktif
      </div>
    </div>
  </div>
</div>

<!-- ─── Row: Chart + Top Penyakit ────────────────────────── -->
<div style="display:grid;grid-template-columns:1fr 320px;gap:16px;margin-bottom:16px;">

  <!-- Kunjungan Chart -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">
        <i class="fas fa-chart-line"></i> Kunjungan 7 Hari Terakhir
      </div>
      <span style="font-size:11px;color:var(--gray-400);">
        Bulan ini: <strong style="color:var(--gray-700);"><?= $bulan_ini_total ?></strong> kunjungan
      </span>
    </div>
    <div class="card-body" style="padding-bottom:16px;">
      <div class="chart-wrapper" style="height:220px;">
        <canvas id="chartKunjungan"></canvas>
      </div>
    </div>
  </div>

  <!-- 10 Besar Penyakit -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">
        <i class="fas fa-virus"></i> Top Penyakit Bulan Ini
      </div>
    </div>
    <div class="card-body" style="padding:0;">
      <?php if (empty($top_penyakit)): ?>
        <div class="empty-state" style="padding:24px;">
          <div class="empty-state-icon" style="font-size:28px;">📊</div>
          <div class="empty-state-title">Belum ada data</div>
        </div>
      <?php else: ?>
        <?php foreach ($top_penyakit as $i => $p): ?>
          <div style="display:flex;align-items:center;gap:10px;padding:10px 16px;border-bottom:1px solid var(--gray-100);">
            <span style="width:20px;height:20px;background:var(--primary-50);color:var(--primary-600);border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;flex-shrink:0;"><?= $i + 1 ?></span>
            <div style="flex:1;min-width:0;">
              <div style="font-size:12px;font-weight:500;color:var(--gray-700);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($p['nm_penyakit']) ?></div>
            </div>
            <span style="font-size:11px;font-weight:700;color:var(--primary-600);flex-shrink:0;"><?= $p['total'] ?></span>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

</div>

<!-- ─── Row: Antrian + Stok ──────────────────────────────── -->
<div style="display:grid;grid-template-columns:1fr 340px;gap:16px;margin-bottom:16px;">

  <!-- Antrian Hari Ini -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">
        <i class="fas fa-clipboard-list"></i> Antrian Kunjungan Hari Ini
      </div>
      <a href="<?= BASE_URL ?>modules/pendaftaran/index.php" class="btn btn-sm btn-outline">
        Lihat Semua <i class="fas fa-arrow-right"></i>
      </a>
    </div>
    <div class="card-body" style="padding:0;">
      <?php if (empty($kunjungan_list)): ?>
        <div class="empty-state">
          <div class="empty-state-icon">📋</div>
          <div class="empty-state-title">Belum ada kunjungan hari ini</div>
          <div class="empty-state-desc">
            <a href="<?= BASE_URL ?>modules/pendaftaran/tambah.php" class="btn btn-primary btn-sm" style="margin-top:8px;">
              <i class="fas fa-plus"></i> Daftar Pasien
            </a>
          </div>
        </div>
      <?php else: ?>
        <div class="table-wrapper">
          <table class="table">
            <thead>
              <tr>
                <th>No</th>
                <th>No. Rawat</th>
                <th>Pasien</th>
                <th>Poli / Dokter</th>
                <th>Jam</th>
                <th>Status</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($kunjungan_list as $i => $k): ?>
                <tr>
                  <td style="color:var(--gray-400);font-size:12px;"><?= $i + 1 ?></td>
                  <td><span style="font-family:monospace;font-size:11px;color:var(--gray-500);"><?= htmlspecialchars($k['no_rawat']) ?></span></td>
                  <td>
                    <div style="display:flex;align-items:center;gap:8px;">
                      <div style="width:30px;height:30px;border-radius:50%;background:var(--primary-50);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:var(--primary-600);flex-shrink:0;">
                        <?= strtoupper(substr($k['nm_pasien'], 0, 1)) ?>
                      </div>
                      <div>
                        <div style="font-size:13px;font-weight:500;"><?= htmlspecialchars($k['nm_pasien']) ?></div>
                        <div style="font-size:11px;color:var(--gray-400);">
                          <?= icon_jk($k['jk']) ?> &nbsp;<?= hitung_umur($k['tgl_lahir']) ?>
                        </div>
                        <?php if (!empty($k['alamat'])): ?>
                          <div style="font-size:11px;color:var(--gray-500);margin-top:2px;display:flex;align-items:center;gap:4px;">
                            <i class="fas fa-map-marker-alt" style="color:#0891b2;font-size:9.5px;flex-shrink:0;"></i>
                            <span style="max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?= htmlspecialchars($k['alamat']) ?>"><?= htmlspecialchars($k['alamat']) ?></span>
                          </div>
                        <?php endif; ?>
                      </div>
                    </div>
                  </td>
                  <td>
                    <div style="font-size:12px;font-weight:500;"><?= htmlspecialchars($k['nm_poli'] ?? '-') ?></div>
                    <div style="font-size:11px;color:var(--gray-400);"><?= htmlspecialchars($k['nm_dokter'] ?? '-') ?></div>
                  </td>
                  <td style="font-size:12px;color:var(--gray-500);">
                    <?= substr($k['jam_reg'], 0, 5) ?>
                  </td>
                  <td><?= badge_status($k['stts']) ?></td>
                  <td>
                    <a href="<?= BASE_URL ?>modules/rekam_medis/periksa.php?no_rawat=<?= urlencode($k['no_rawat']) ?>"
                       class="btn btn-sm btn-outline-primary" title="Buka rekam medis">
                      <i class="fas fa-stethoscope"></i>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Right Column -->
  <div style="display:flex;flex-direction:column;gap:16px;">

    <!-- Status Integrasi -->
    <div class="card">
      <div class="card-header">
        <div class="card-title"><i class="fas fa-plug"></i> Status Integrasi</div>
      </div>
      <div class="card-body" style="display:flex;flex-direction:column;gap:10px;">
        <?php
        $integrations = [
          ['label' => 'PCare BPJS',    'icon' => 'fa-hospital',       'connected' => !empty($pcare_url)],
          ['label' => 'Satu Sehat',    'icon' => 'fa-shield-heart',   'connected' => !empty($satu_sehat_org)],
          ['label' => 'E-RM BPJS',     'icon' => 'fa-laptop-medical', 'connected' => !empty($bpjs_emr_id)],
        ];
        foreach ($integrations as $int):
        ?>
          <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--gray-100);">
            <div style="display:flex;align-items:center;gap:10px;">
              <div style="width:32px;height:32px;background:var(--gray-100);border-radius:8px;display:flex;align-items:center;justify-content:center;">
                <i class="fas <?= $int['icon'] ?>" style="color:var(--gray-500);font-size:13px;"></i>
              </div>
              <span style="font-size:12px;font-weight:500;"><?= $int['label'] ?></span>
            </div>
            <?php if ($int['connected']): ?>
              <span class="integration-badge connected"><span class="dot"></span>Terhubung</span>
            <?php else: ?>
              <span class="integration-badge disconnected"><span class="dot"></span>Belum dikonfigurasi</span>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
        <a href="<?= BASE_URL ?>modules/settings/index.php" class="btn btn-outline btn-sm" style="margin-top:4px;width:100%;justify-content:center;">
          <i class="fas fa-cog"></i> Konfigurasi
        </a>
      </div>
    </div>

    <!-- Stok Hampir Habis -->
    <div class="card">
      <div class="card-header">
        <div class="card-title"><i class="fas fa-exclamation-triangle" style="color:var(--warning);"></i> Stok Hampir Habis</div>
      </div>
      <div class="card-body" style="padding:0;">
        <?php if (empty($stok_hampir)): ?>
          <div class="empty-state" style="padding:20px;">
            <div class="empty-state-icon" style="font-size:24px;">✅</div>
            <div class="empty-state-title">Stok aman</div>
          </div>
        <?php else: ?>
          <?php foreach ($stok_hampir as $s): ?>
            <div style="padding:10px 16px;border-bottom:1px solid var(--gray-100);">
              <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
                <div style="font-size:12px;font-weight:500;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                  <?= htmlspecialchars($s['nama_brng']) ?>
                </div>
                <span class="badge badge-warning"><?= $s['stok_total'] ?> sisa</span>
              </div>
              <div style="font-size:10px;color:var(--gray-400);margin-top:2px;">
                Min. stok: <?= $s['stokminimal'] ?>
              </div>
            </div>
          <?php endforeach; ?>
          <div style="padding:10px 16px;">
            <a href="<?= BASE_URL ?>modules/farmasi/stok.php" class="btn btn-sm btn-outline" style="width:100%;justify-content:center;">
              Kelola Stok
            </a>
          </div>
        <?php endif; ?>
      </div>
    </div>

  </div><!-- /.right column -->
</div>

<!-- ─── Chart Script ─────────────────────────────────────── -->
<script>
document.addEventListener('DOMContentLoaded', () => {
  const ctx = document.getElementById('chartKunjungan')?.getContext('2d');
  if (!ctx) return;

  const labels  = <?= json_encode(array_column($weekly_data, 'label')) ?>;
  const data    = <?= json_encode(array_column($weekly_data, 'total')) ?>;
  const maxVal  = Math.max(...data, 1);

  new Chart(ctx, {
    type: 'bar',
    data: {
      labels,
      datasets: [{
        label: 'Kunjungan',
        data,
        backgroundColor: (ctx) => {
          const g = ctx.chart.ctx.createLinearGradient(0, 0, 0, 220);
          g.addColorStop(0, 'rgba(37,99,235,0.8)');
          g.addColorStop(1, 'rgba(37,99,235,0.2)');
          return g;
        },
        borderRadius: 6,
        borderSkipped: false,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: ctx => ` ${ctx.parsed.y} kunjungan`
          }
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          grid: { color: '#f1f5f9' },
          ticks: {
            color: '#94a3b8',
            font: { size: 11 },
            stepSize: Math.ceil(maxVal / 5) || 1
          }
        },
        x: {
          grid: { display: false },
          ticks: { color: '#94a3b8', font: { size: 11 } }
        }
      }
    }
  });
});
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
