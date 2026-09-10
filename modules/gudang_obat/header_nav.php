<?php
/**
 * SIMKlinik — Gudang Obat: Sub-Navigation Header
 */

if (!isset($sub_active)) $sub_active = 'master';

// Hitung badge cepat
$badge_kritis = 0;
try {
    $res_k = $conn->query("
        SELECT COUNT(*) as t FROM (
            SELECT db.kode_brng, db.stokminimal, COALESCE(SUM(gb.stok), 0) as total_stok
            FROM databarang db
            LEFT JOIN gudangbarang gb ON db.kode_brng = gb.kode_brng
            WHERE db.status = '1'
            GROUP BY db.kode_brng
            HAVING total_stok <= db.stokminimal
        ) as sub
    ");
    $badge_kritis = $res_k ? (int)$res_k->fetch_assoc()['t'] : 0;
} catch (Exception $e) {}

$sub_menus = [
    [
        'id'    => 'master',
        'label' => 'Master Data Obat',
        'icon'  => 'fa-pills',
        'url'   => 'index.php',
        'desc'  => 'Katalog & tarif obat'
    ],
    [
        'id'    => 'monitoring',
        'label' => 'Monitoring Stok',
        'icon'  => 'fa-triangle-exclamation',
        'url'   => 'monitoring_stok.php',
        'desc'  => 'Stok darurat & expired',
        'badge' => $badge_kritis > 0 ? $badge_kritis : null,
        'badge_class' => 'badge-danger'
    ],
    [
        'id'    => 'opname',
        'label' => 'Stok Opname',
        'icon'  => 'fa-clipboard-check',
        'url'   => 'stok_opname.php',
        'desc'  => 'Koreksi fisik vs sistem'
    ],
    [
        'id'    => 'pemesanan',
        'label' => 'Order / Pemesanan',
        'icon'  => 'fa-cart-shopping',
        'url'   => 'pemesanan.php',
        'desc'  => 'Surat Pesanan / PO'
    ],
    [
        'id'    => 'penerimaan',
        'label' => 'Penerimaan Obat',
        'icon'  => 'fa-truck-ramp-box',
        'url'   => 'penerimaan.php',
        'desc'  => 'Faktur masuk & batch'
    ],
    [
        'id'    => 'mutasi',
        'label' => 'Mutasi Obat',
        'icon'  => 'fa-arrows-split-up-and-left',
        'url'   => 'mutasi.php',
        'desc'  => 'Distribusi antar unit'
    ],
];
?>

<div class="gudang-subnav-bar" style="background:#ffffff;border:1px solid #e2e8f0;border-radius:10px;padding:6px 10px;margin-bottom:18px;box-shadow:0 1px 2px rgba(0,0,0,0.03);">
  <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
    <?php foreach ($sub_menus as $sm): ?>
      <?php $isActive = ($sub_active === $sm['id']); ?>
      <a href="<?= BASE_URL ?>modules/gudang_obat/<?= $sm['url'] ?>" 
         class="gudang-nav-item <?= $isActive ? 'active' : '' ?>"
         style="display:flex;align-items:center;gap:7px;padding:6px 11px;border-radius:7px;text-decoration:none;font-size:12px;font-weight:<?= $isActive ? '700' : '500' ?>;color:<?= $isActive ? '#0284c7' : '#475569' ?>;background:<?= $isActive ? '#f0f9ff' : 'transparent' ?>;border:1px solid <?= $isActive ? '#bae6fd' : 'transparent' ?>;white-space:nowrap;transition:all 0.15s ease;">
        <i class="fas <?= $sm['icon'] ?>" style="font-size:13px;color:<?= $isActive ? '#0284c7' : '#64748b' ?>;"></i>
        <span><?= $sm['label'] ?></span>
        <?php if (!empty($sm['badge'])): ?>
          <span class="badge <?= $sm['badge_class'] ?? 'badge-warning' ?>" style="font-size:10px;padding:1px 5px;border-radius:99px;font-weight:700;">
            <?= $sm['badge'] ?>
          </span>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>
</div>
