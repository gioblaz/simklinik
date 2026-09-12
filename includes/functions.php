<?php
/**
 * SIMKlinik — Functions Helper
 * Fungsi-fungsi bantu untuk tampilan dan data
 */

if (!defined('DB_NAME')) {
    require_once dirname(__DIR__) . '/config.php';
}

/**
 * Statistik dashboard — kunjungan hari ini
 */
function stat_kunjungan_hari_ini(): int {
    global $conn;
    $today  = date('Y-m-d');
    $result = $conn->query("SELECT COUNT(*) as total FROM reg_periksa WHERE tgl_registrasi = '$today'");
    return $result ? (int)$result->fetch_assoc()['total'] : 0;
}

/**
 * Statistik dashboard — pasien baru hari ini
 */
function stat_pasien_baru_hari_ini(): int {
    global $conn;
    $today  = date('Y-m-d');
    $result = $conn->query("SELECT COUNT(*) as total FROM pasien WHERE tgl_daftar = '$today'");
    return $result ? (int)$result->fetch_assoc()['total'] : 0;
}

/**
 * Statistik dashboard — total pasien terdaftar
 */
function stat_total_pasien(): int {
    global $conn;
    $result = $conn->query("SELECT COUNT(*) as total FROM pasien");
    return $result ? (int)$result->fetch_assoc()['total'] : 0;
}

/**
 * Statistik dashboard — antrian menunggu hari ini
 */
function stat_antrian_menunggu(): int {
    global $conn;
    $today  = date('Y-m-d');
    $result = $conn->query("SELECT COUNT(*) as total FROM reg_periksa WHERE tgl_registrasi = '$today' AND stts = 'Belum'");
    return $result ? (int)$result->fetch_assoc()['total'] : 0;
}

/**
 * Statistik — kunjungan 7 hari terakhir (untuk chart)
 */
function stat_kunjungan_mingguan(): array {
    global $conn;
    $data = [];
    for ($i = 6; $i >= 0; $i--) {
        $date   = date('Y-m-d', strtotime("-$i days"));
        $label  = date('d/m', strtotime($date));
        $result = $conn->query("SELECT COUNT(*) as total FROM reg_periksa WHERE tgl_registrasi = '$date'");
        $total  = $result ? (int)$result->fetch_assoc()['total'] : 0;
        $data[] = ['label' => $label, 'total' => $total];
    }
    return $data;
}

/**
 * 10 Besar Penyakit bulan ini
 */
function stat_10_besar_penyakit(): array {
    global $conn;
    $month  = date('Y-m');
    $result = $conn->query("
        SELECT p.kd_penyakit, p.nm_penyakit, COUNT(*) as total
        FROM diagnosa_pasien dp
        JOIN reg_periksa r ON dp.no_rawat = r.no_rawat
        JOIN penyakit p ON dp.kd_penyakit = p.kd_penyakit
        WHERE DATE_FORMAT(r.tgl_registrasi, '%Y-%m') = '$month'
          AND dp.kd_penyakit IS NOT NULL 
          AND dp.kd_penyakit != '' 
          AND dp.kd_penyakit != '-'
          AND TRIM(dp.kd_penyakit) != ''
          AND p.kd_penyakit IS NOT NULL 
          AND p.kd_penyakit != '' 
          AND p.kd_penyakit != '-'
          AND TRIM(p.kd_penyakit) != ''
          AND p.nm_penyakit IS NOT NULL
          AND TRIM(p.nm_penyakit) != ''
          AND TRIM(p.nm_penyakit) != '-'
        GROUP BY dp.kd_penyakit
        ORDER BY total DESC
        LIMIT 10
    ");
    $rows = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return $rows;
}

/**
 * Daftar kunjungan hari ini
 */
function get_kunjungan_hari_ini(int $limit = 10): array {
    global $conn;
    $today  = date('Y-m-d');
    $result = $conn->query("
        SELECT r.no_rawat, r.no_reg, r.tgl_registrasi, r.jam_reg,
               r.stts, r.status_lanjut,
               p.nm_pasien, p.no_rkm_medis, p.jk, p.tgl_lahir,
               d.nm_dokter, pol.nm_poli
        FROM reg_periksa r
        JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
        LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
        LEFT JOIN poliklinik pol ON r.kd_poli = pol.kd_poli
        WHERE r.tgl_registrasi = '$today'
        ORDER BY r.jam_reg ASC
        LIMIT $limit
    ");
    $rows = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return $rows;
}

/**
 * Stok obat hampir habis (di bawah stok minimal)
 */
function get_stok_hampir_habis(int $limit = 5): array {
    global $conn;
    $result = $conn->query("
        SELECT db.nama_brng, db.stokminimal,
               COALESCE(SUM(gb.stok), 0) as stok_total
        FROM databarang db
        LEFT JOIN gudangbarang gb ON db.kode_brng = gb.kode_brng
        WHERE db.status = '1'
        GROUP BY db.kode_brng
        HAVING stok_total <= db.stokminimal
        ORDER BY stok_total ASC
        LIMIT $limit
    ");
    $rows = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return $rows;
}

/**
 * Badge status kunjungan (Model Modern Outlined Pill)
 */
function badge_status(string $status): string {
    $map = [
        'Belum'            => 'badge-status-warning',
        'Menunggu'         => 'badge-status-warning',
        'TTV'              => 'badge-status-ttv',
        'Sudah'            => 'badge-status-danger',
        'Selesai'          => 'badge-status-danger',
        'Batal'            => 'badge-status-warning',
        'Berkas Diterima'  => 'badge-status-info',
        'Dirujuk'          => 'badge-status-secondary',
        'Meninggal'        => 'badge-status-dark',
        'Dirawat'          => 'badge-status-success',
        'Sedang Dirawat'   => 'badge-status-success',
        'Pulang Paksa'     => 'badge-status-danger',
    ];
    $cls = $map[$status] ?? 'badge-status-secondary';
    return "<span class=\"badge {$cls}\">" . htmlspecialchars($status) . "</span>";
}

/**
 * Mendapatkan class CSS untuk baris tabel berdasarkan status registrasi
 * Status: Belum (putih), Sudah (merah), TTV (hijau), Berkas Diterima (biru), Batal (kuning)
 */
function get_status_row_class(string $status): string {
    $s = strtolower(trim($status));
    switch ($s) {
        case 'sudah':
        case 'selesai':
            return 'row-stts-sudah';
        case 'ttv':
            return 'row-stts-ttv';
        case 'berkas diterima':
            return 'row-stts-berkas-diterima';
        case 'batal':
            return 'row-stts-batal';
        case 'belum':
        case 'menunggu':
        default:
            return 'row-stts-belum';
    }
}

/**
 * Mendapatkan inline style warna baris tabel berdasarkan status registrasi
 */
function get_status_row_style(string $status): string {
    $s = strtolower(trim($status));
    switch ($s) {
        case 'sudah':
        case 'selesai':
            return 'background-color:#fee2e2 !important;'; // Merah
        case 'ttv':
            return 'background-color:#dcfce7 !important;'; // Hijau
        case 'berkas diterima':
            return 'background-color:#dbeafe !important;'; // Biru
        case 'batal':
            return 'background-color:#fef3c7 !important;'; // Kuning
        case 'belum':
        case 'menunggu':
        default:
            return 'background-color:#ffffff;'; // Putih
    }
}

/**
 * Badge penjamin (BPJS / Asuransi -> Vibrant Magenta Pill, Umum -> Soft Teal Pill)
 */
function badge_penjab(string $penjab, ?string $no_peserta = null): string {
    $is_asuransi = (bool) preg_match('/bpjs|asuransi|jaminan|inhealth/i', $penjab);
    $cls = $is_asuransi ? 'badge-asuransi' : 'badge-umum';
    $out = "<span class=\"badge {$cls}\">" . htmlspecialchars($penjab) . "</span>";
    if (!empty($no_peserta)) {
        $out .= "<div style=\"font-size:10px;color:#64748b;font-family:monospace;margin-top:2px;\">" . htmlspecialchars($no_peserta) . "</div>";
    }
    return $out;
}

/**
 * Icon jenis kelamin
 */
function icon_jk(string $jk): string {
    return $jk === 'L'
        ? '<i class="fas fa-mars text-blue" title="Laki-laki"></i>'
        : '<i class="fas fa-venus text-pink" title="Perempuan"></i>';
}

/**
 * Sanitize input
 */
function sanitize(string $input): string {
    return htmlspecialchars(trim(strip_tags($input)), ENT_QUOTES, 'UTF-8');
}

/**
 * Pagination helper
 */
function paginate(string $table, string $where = '1=1', int $per_page = 20): array {
    global $conn;
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($page - 1) * $per_page;

    $count_result = $conn->query("SELECT COUNT(*) as total FROM $table WHERE $where");
    $total        = $count_result ? (int)$count_result->fetch_assoc()['total'] : 0;
    $total_pages  = (int)ceil($total / $per_page);

    return [
        'page'        => $page,
        'per_page'    => $per_page,
        'total'       => $total,
        'total_pages' => $total_pages,
        'offset'      => $offset,
        'has_prev'    => $page > 1,
        'has_next'    => $page < $total_pages,
    ];
}

/**
 * Render pagination HTML
 */
function render_pagination(array $pag, string $base_url = ''): string {
    if (($pag['total_pages'] ?? 0) <= 1) return '';

    $params = $_GET;
    unset($params['page']);

    $build_url = function($p) use ($base_url, $params) {
        $p_arr = array_merge($params, ['page' => $p]);
        $query = http_build_query($p_arr);
        $path  = $base_url ?: strtok($_SERVER['REQUEST_URI'] ?? '', '?');
        return $path . ($query ? '?' . $query : '');
    };

    $page = (int)($pag['page'] ?? 1);
    $total_pages = (int)($pag['total_pages'] ?? 1);
    $total_data = number_format($pag['total'] ?? 0);

    $html = '<div style="display:flex;justify-content:space-between;align-items:center;padding:12px 18px;background:#f8fafc;border-top:1px solid #e2e8f0;flex-wrap:wrap;gap:10px;">';
    $html .= "<div style=\"font-size:12px;color:#64748b;\">Halaman <strong>{$page}</strong> dari <strong>{$total_pages}</strong> (Total <strong>{$total_data}</strong> data)</div>";
    $html .= '<nav class="pagination-nav"><ul class="pagination" style="display:flex;align-items:center;gap:4px;margin:0;list-style:none;padding:0;">';

    if ($page > 1) {
        $html .= "<li><a href=\"" . $build_url(1) . "\" class=\"btn btn-sm btn-outline\" style=\"padding:4px 9px;font-size:11.5px;border-radius:6px;\" title=\"Halaman Pertama\"><i class=\"fas fa-angles-left\"></i></a></li>";
        $html .= "<li><a href=\"" . $build_url($page - 1) . "\" class=\"btn btn-sm btn-outline\" style=\"padding:4px 9px;font-size:11.5px;border-radius:6px;\" title=\"Sebelumnya\"><i class=\"fas fa-chevron-left\"></i></a></li>";
    }

    $start_p = max(1, $page - 2);
    $end_p   = min($total_pages, $page + 2);

    for ($i = $start_p; $i <= $end_p; $i++) {
        if ($i === $page) {
            $html .= "<li><span class=\"btn btn-sm btn-primary\" style=\"padding:4px 10px;font-size:11.5px;border-radius:6px;min-width:30px;text-align:center;font-weight:700;\">{$i}</span></li>";
        } else {
            $html .= "<li><a href=\"" . $build_url($i) . "\" class=\"btn btn-sm btn-outline\" style=\"padding:4px 10px;font-size:11.5px;border-radius:6px;min-width:30px;text-align:center;\">{$i}</a></li>";
        }
    }

    if ($page < $total_pages) {
        $html .= "<li><a href=\"" . $build_url($page + 1) . "\" class=\"btn btn-sm btn-outline\" style=\"padding:4px 9px;font-size:11.5px;border-radius:6px;\" title=\"Berikutnya\"><i class=\"fas fa-chevron-right\"></i></a></li>";
        $html .= "<li><a href=\"" . $build_url($total_pages) . "\" class=\"btn btn-sm btn-outline\" style=\"padding:4px 9px;font-size:11.5px;border-radius:6px;\" title=\"Halaman Terakhir\"><i class=\"fas fa-angles-right\"></i></a></li>";
    }

    $html .= '</ul></nav>';
    $html .= '</div>';
    return $html;
}

/**
 * Statistik — jumlah obat stok menipis / kritis / darurat
 */
function stat_stok_kritis_count(): int {
    global $conn;
    try {
        $res = $conn->query("
            SELECT COUNT(*) as total FROM (
                SELECT db.kode_brng, db.stokminimal, COALESCE(SUM(gb.stok), 0) as total_stok
                FROM databarang db
                LEFT JOIN gudangbarang gb ON db.kode_brng = gb.kode_brng
                WHERE db.status = '1'
                GROUP BY db.kode_brng
                HAVING total_stok <= db.stokminimal
            ) as sub
        ");
        return $res ? (int)$res->fetch_assoc()['total'] : 0;
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Statistik — jumlah permintaan lab menunggu hasil
 */
function stat_permintaan_lab_menunggu(): int {
    global $conn;
    try {
        $res = $conn->query("
            SELECT COUNT(*) as total FROM permintaan_lab
            WHERE tgl_hasil = '0000-00-00' OR jam_hasil = '00:00:00'
        ");
        return $res ? (int)$res->fetch_assoc()['total'] : 0;
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Helper deteksi apakah diagnosa ICD-10 masuk dalam 144 Diagnosa Non-Spesialistik (TACC)
 */
function is_diagnosa_tacc(string $kd_penyakit): bool {
    $tacc_prefixes = [
        'A01', 'A03', 'A06', 'A09', 'A15', 'A16', 'A27', 'A35', 'A37', 'A46', 
        'A51', 'A54', 'A59', 'A63', 'A74', 'A82', 'A90', 'A91', 
        'B00', 'B01', 'B02', 'B05', 'B07', 'B08', 'B15', 'B20', 'B35', 'B36', 'B37', 'B50', 'B51', 'B52', 'B53', 'B54', 'B65', 'B68', 'B74', 'B76', 'B77', 'B79', 'B80', 'B85', 'B86',
        'E11', 'E14', 'E16', 'E46', 'E50', 'E56', 'E66', 'E78', 'E79',
        'F41', 'F45',
        'G43', 'G44', 'G45', 'G47', 'G51',
        'H00', 'H01', 'H02', 'H04', 'H10', 'H11', 'H15', 'H25', 'H52', 'H60', 'H61', 'H66',
        'I10', 'I46', 'I84',
        'J00', 'J01', 'J02', 'J03', 'J04', 'J10', 'J11', 'J18', 'J20', 'J30', 'J45',
        'K12', 'K21', 'K29', 'K30', 'K35', 'K64', 'K81', 'K90', 'K92',
        'L01', 'L02', 'L03', 'L08', 'L20', 'L21', 'L23', 'L24', 'L42', 'L50', 'L70', 'L73', 'L74',
        'M10', 'M19',
        'N39', 'N47', 'N61', 'N70', 'N72', 'N76', 'N89',
        'O21', 'O42', 'O70', 'O72', 'O80', 'O92', 'O99',
        'P55',
        'R04', 'R56',
        'T14', 'T15', 'T16', 'T17', 'T20', 'T21', 'T22', 'T23', 'T24', 'T25', 'T30', 'T31', 'T32', 'T62', 'T63', 'T75', 'T78',
        'Z34'
    ];
    $clean = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($kd_penyakit)));
    if (strlen($clean) < 3) return false;
    $prefix3 = substr($clean, 0, 3);
    return in_array($prefix3, $tacc_prefixes);
}



