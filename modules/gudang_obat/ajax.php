<?php
/**
 * SIMKlinik — Gudang Obat: AJAX Endpoint Helper
 */

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$action = sanitize($_GET['action'] ?? '');

// 1. Get stok obat di bangsal tertentu
if ($action === 'get_stok') {
    $kode    = $conn->real_escape_string($_GET['kode'] ?? '');
    $bangsal = $conn->real_escape_string($_GET['bangsal'] ?? '');

    $where = "kode_brng = '$kode'";
    if ($bangsal && $bangsal !== 'ALL') {
        $where .= " AND kd_bangsal = '$bangsal'";
    }

    $res = $conn->query("SELECT COALESCE(SUM(stok), 0) as s FROM gudangbarang WHERE $where");
    $stok = $res ? (float)$res->fetch_assoc()['s'] : 0;

    $res_tot = $conn->query("SELECT COALESCE(SUM(stok), 0) as s FROM gudangbarang WHERE kode_brng = '$kode'");
    $tot = $res_tot ? (float)$res_tot->fetch_assoc()['s'] : 0;

    echo json_encode([
        'status'     => 'success',
        'kode'       => $kode,
        'bangsal'    => $bangsal,
        'stok'       => $stok,
        'total_stok' => $tot
    ]);
    exit;
}

// 2. Get detail info obat (HPP, Jual, Satuan, Kategori, Expire)
if ($action === 'get_detail') {
    $kode = $conn->real_escape_string($_GET['kode'] ?? '');
    $res  = $conn->query("
        SELECT db.*, ks.satuan, kb.nama as nama_kategori,
               COALESCE((SELECT SUM(stok) FROM gudangbarang WHERE kode_brng = db.kode_brng), 0) as total_stok
        FROM databarang db
        LEFT JOIN kodesatuan ks ON db.kode_sat = ks.kode_sat
        LEFT JOIN kategori_barang kb ON db.kode_kategori = kb.kode
        WHERE db.kode_brng = '$kode'
    ");

    if ($res && $res->num_rows > 0) {
        echo json_encode([
            'status' => 'success',
            'data'   => $res->fetch_assoc()
        ]);
    } else {
        echo json_encode([
            'status'  => 'error',
            'message' => 'Data obat tidak ditemukan'
        ]);
    }
    exit;
}

// 3. Search obat autocomplete
if ($action === 'search') {
    $q   = $conn->real_escape_string($_GET['q'] ?? '');
    $res = $conn->query("
        SELECT db.kode_brng, db.nama_brng, db.h_beli, db.ralan, ks.satuan,
               COALESCE((SELECT SUM(stok) FROM gudangbarang WHERE kode_brng = db.kode_brng), 0) as total_stok
        FROM databarang db
        LEFT JOIN kodesatuan ks ON db.kode_sat = ks.kode_sat
        WHERE db.status = '1' AND (db.kode_brng LIKE '%$q%' OR db.nama_brng LIKE '%$q%')
        LIMIT 15
    ");

    $list = [];
    if ($res) while ($row = $res->fetch_assoc()) $list[] = $row;

    echo json_encode([
        'status' => 'success',
        'data'   => $list
    ]);
    exit;
}

echo json_encode([
    'status'  => 'error',
    'message' => 'Action tidak dikenali'
]);
exit;
