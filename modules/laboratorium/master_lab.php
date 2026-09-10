<?php
/**
 * SIMKlinik — Master Tarif & Pemetaan Detail Tindakan Laboratorium
 */

$page_title    = 'Master Tarif & Template Lab';
$active_module = 'master_lab';

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

// Pastikan tabel mlite_satu_sehat_mapping_lab ada
$conn->query("
    CREATE TABLE IF NOT EXISTS `mlite_satu_sehat_mapping_lab` (
      `id_template` int NOT NULL,
      `kd_jenis_prw` varchar(15) DEFAULT NULL,
      `code` varchar(15) DEFAULT NULL,
      `system` varchar(100) NOT NULL DEFAULT 'http://loinc.org',
      `display` varchar(80) DEFAULT NULL,
      `sampel_code` varchar(15) NOT NULL DEFAULT '119297000',
      `sampel_system` varchar(100) NOT NULL DEFAULT 'http://snomed.info/sct',
      `sampel_display` varchar(80) NOT NULL DEFAULT 'Blood specimen',
      PRIMARY KEY (`id_template`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// ─── Filter & Parameter Halaman ──────────────────────────────
$active_tab   = sanitize($_GET['tab'] ?? 'paket');
$selected_pkg = sanitize($_GET['kd_pkg'] ?? '');

// Filter Tab 1: Paket
$search_q     = sanitize($_GET['q'] ?? '');
$filter_kat   = sanitize($_GET['kat'] ?? '');
$filter_stts  = sanitize($_GET['status'] ?? '');
$page_p       = max(1, (int)($_GET['page_p'] ?? 1));
$limit_p      = max(5, min(100, (int)($_GET['limit_p'] ?? 15)));

// Filter Tab 2: Template
$search_t     = sanitize($_GET['q_t'] ?? '');
$page_t       = max(1, (int)($_GET['page_t'] ?? 1));
$limit_t      = max(5, min(100, (int)($_GET['limit_t'] ?? 25)));

// Filter Tab 3: Matriks
$search_m     = sanitize($_GET['q_m'] ?? '');
$page_m       = max(1, (int)($_GET['page_m'] ?? 1));
$limit_m      = max(5, min(50, (int)($_GET['limit_m'] ?? 10)));

// Filter Tab 4: Satu Sehat
$search_ss    = sanitize($_GET['q_ss'] ?? '');
$filter_ss    = sanitize($_GET['status_ss'] ?? '');
$page_ss      = max(1, (int)($_GET['page_ss'] ?? 1));
$limit_ss     = max(5, min(100, (int)($_GET['limit_ss'] ?? 20)));

// ─── Pagination Helper Function ──────────────────────────────
if (!function_exists('render_lab_pagination')) {
    function render_lab_pagination($current_page, $total_pages, $total_items, $per_page, $base_params = []) {
        if ($total_pages <= 1 && $total_items <= $per_page) {
            if ($total_items > 0) {
                return '<div style="display:flex;justify-content:space-between;align-items:center;padding:12px 18px;font-size:12px;color:#64748b;border-top:1px solid #f1f5f9;">' .
                       '<span>Menampilkan <strong>' . number_format($total_items) . '</strong> data</span>' .
                       '</div>';
            }
            return '';
        }

        $page_key = isset($base_params['page_key']) ? $base_params['page_key'] : 'page';
        unset($base_params['page_key']);

        $start_item = (($current_page - 1) * $per_page) + 1;
        $end_item   = min($total_items, $current_page * $per_page);

        $html = '<div style="display:flex;justify-content:space-between;align-items:center;padding:12px 18px;font-size:12px;color:#64748b;border-top:1px solid #f1f5f9;flex-wrap:wrap;gap:10px;">';
        
        $html .= '<div>Menampilkan <strong>' . number_format($start_item) . '</strong> - <strong>' . number_format($end_item) . '</strong> dari <strong>' . number_format($total_items) . '</strong> data</div>';

        $html .= '<div style="display:flex;gap:4px;align-items:center;flex-wrap:wrap;">';

        $build_url = function($p) use ($base_params, $page_key) {
            $params = $base_params;
            $params[$page_key] = $p;
            return '?' . http_build_query($params);
        };

        // First & Prev
        if ($current_page > 1) {
            $html .= '<a href="' . $build_url(1) . '" class="btn btn-outline btn-sm" style="padding:3px 8px;font-size:11px;" title="Halaman Pertama">&laquo;</a>';
            $html .= '<a href="' . $build_url($current_page - 1) . '" class="btn btn-outline btn-sm" style="padding:3px 8px;font-size:11px;" title="Sebelumnya">&lsaquo;</a>';
        } else {
            $html .= '<button class="btn btn-outline btn-sm" disabled style="padding:3px 8px;font-size:11px;opacity:0.5;">&laquo;</button>';
            $html .= '<button class="btn btn-outline btn-sm" disabled style="padding:3px 8px;font-size:11px;opacity:0.5;">&lsaquo;</button>';
        }

        // Window of numbers
        $window = 2;
        $start_p = max(1, $current_page - $window);
        $end_p   = min($total_pages, $current_page + $window);

        if ($start_p > 1) {
            $html .= '<a href="' . $build_url(1) . '" class="btn btn-outline btn-sm" style="padding:3px 8px;font-size:11px;">1</a>';
            if ($start_p > 2) $html .= '<span style="padding:0 4px;color:#94a3b8;">...</span>';
        }

        for ($p = $start_p; $p <= $end_p; $p++) {
            if ($p == $current_page) {
                $html .= '<span class="btn btn-primary btn-sm" style="padding:3px 9px;font-size:11px;font-weight:700;background:#0284c7;border-color:#0284c7;">' . $p . '</span>';
            } else {
                $html .= '<a href="' . $build_url($p) . '" class="btn btn-outline btn-sm" style="padding:3px 9px;font-size:11px;">' . $p . '</a>';
            }
        }

        if ($end_p < $total_pages) {
            if ($end_p < $total_pages - 1) $html .= '<span style="padding:0 4px;color:#94a3b8;">...</span>';
            $html .= '<a href="' . $build_url($total_pages) . '" class="btn btn-outline btn-sm" style="padding:3px 8px;font-size:11px;">' . $total_pages . '</a>';
        }

        // Next & Last
        if ($current_page < $total_pages) {
            $html .= '<a href="' . $build_url($current_page + 1) . '" class="btn btn-outline btn-sm" style="padding:3px 8px;font-size:11px;" title="Selanjutnya">&rsaquo;</a>';
            $html .= '<a href="' . $build_url($total_pages) . '" class="btn btn-outline btn-sm" style="padding:3px 8px;font-size:11px;" title="Halaman Terakhir">&raquo;</a>';
        } else {
            $html .= '<button class="btn btn-outline btn-sm" disabled style="padding:3px 8px;font-size:11px;opacity:0.5;">&rsaquo;</button>';
            $html .= '<button class="btn btn-outline btn-sm" disabled style="padding:3px 8px;font-size:11px;opacity:0.5;">&raquo;</button>';
        }

        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }
}

// ─── POST Handler: Paket / Jenis Perawatan Lab ────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_pkg'])) {
    $act = sanitize($_POST['action_pkg']);

    if ($act === 'save') {
        $kd_jenis_prw = strtoupper(sanitize($_POST['kd_jenis_prw'] ?? ''));
        $nm_perawatan = sanitize($_POST['nm_perawatan'] ?? '');
        $kategori     = sanitize($_POST['kategori'] ?? 'PK');
        $kelas        = sanitize($_POST['kelas'] ?? 'Rawat Jalan');
        $status       = ($_POST['status'] ?? '1') === '1' ? '1' : '0';
        $bagian_rs    = (float)($_POST['bagian_rs'] ?? 0);
        $bhp          = (float)($_POST['bhp'] ?? 0);
        $perujuk      = (float)($_POST['tarif_perujuk'] ?? 0);
        $dr           = (float)($_POST['tarif_tindakan_dokter'] ?? 0);
        $pr           = (float)($_POST['tarif_tindakan_petugas'] ?? 0);
        $kso          = (float)($_POST['kso'] ?? 0);
        $menejemen    = (float)($_POST['menejemen'] ?? 0);
        $total        = $bagian_rs + $bhp + $perujuk + $dr + $pr + $kso + $menejemen;
        $is_edit      = !empty($_POST['is_edit']);

        $kd_esc  = $conn->real_escape_string($kd_jenis_prw);
        $nm_esc  = $conn->real_escape_string($nm_perawatan);
        $kat_esc = $conn->real_escape_string($kategori);
        $kls_esc = $conn->real_escape_string($kelas);

        if (empty($kd_jenis_prw) || empty($nm_perawatan)) {
            set_flash('danger', 'Kode dan Nama Pemeriksaan Lab wajib diisi.');
        } else {
            if ($is_edit) {
                $sql = "
                    UPDATE jns_perawatan_lab SET
                        nm_perawatan          = '$nm_esc',
                        kategori              = '$kat_esc',
                        kelas                 = '$kls_esc',
                        status                = '$status',
                        bagian_rs             = $bagian_rs,
                        bhp                   = $bhp,
                        tarif_perujuk         = $perujuk,
                        tarif_tindakan_dokter = $dr,
                        tarif_tindakan_petugas= $pr,
                        kso                   = $kso,
                        menejemen             = $menejemen,
                        total_byr             = $total
                    WHERE kd_jenis_prw = '$kd_esc'
                ";
            } else {
                $sql = "
                    INSERT INTO jns_perawatan_lab (
                        kd_jenis_prw, nm_perawatan, bagian_rs, bhp, tarif_perujuk,
                        tarif_tindakan_dokter, tarif_tindakan_petugas, kso, menejemen,
                        total_byr, kd_pj, status, kelas, kategori
                    ) VALUES (
                        '$kd_esc', '$nm_esc', $bagian_rs, $bhp, $perujuk,
                        $dr, $pr, $kso, $menejemen,
                        $total, '-', '$status', '$kls_esc', '$kat_esc'
                    )
                ";
            }

            if ($conn->query($sql)) {
                set_flash('success', "Paket Pemeriksaan Lab <strong>$nm_perawatan</strong> ($kd_jenis_prw) berhasil disimpan.");
                redirect(BASE_URL . 'modules/laboratorium/master_lab.php?tab=paket');
            } else {
                set_flash('danger', 'Gagal menyimpan paket: ' . $conn->error);
            }
        }
    } elseif ($act === 'clone') {
        $source_kd = sanitize($_POST['source_kd'] ?? '');
        $new_kd    = strtoupper(sanitize($_POST['new_kd'] ?? ''));
        $new_nm    = sanitize($_POST['new_nm'] ?? '');

        $src_esc = $conn->real_escape_string($source_kd);
        $new_kd_esc = $conn->real_escape_string($new_kd);
        $new_nm_esc = $conn->real_escape_string($new_nm);

        if (empty($new_kd) || empty($new_nm)) {
            set_flash('danger', 'Kode baru dan Nama baru untuk duplikasi paket wajib diisi.');
        } else {
            // Check existing
            $chk = $conn->query("SELECT kd_jenis_prw FROM jns_perawatan_lab WHERE kd_jenis_prw = '$new_kd_esc'");
            if ($chk && $chk->num_rows > 0) {
                set_flash('danger', "Kode paket <strong>$new_kd</strong> sudah digunakan. Gunakan kode lain.");
            } else {
                // Copy package header
                $res_src = $conn->query("SELECT * FROM jns_perawatan_lab WHERE kd_jenis_prw = '$src_esc'");
                if ($res_src && $src = $res_src->fetch_assoc()) {
                    $ins_pkg = $conn->query("
                        INSERT INTO jns_perawatan_lab (
                            kd_jenis_prw, nm_perawatan, bagian_rs, bhp, tarif_perujuk,
                            tarif_tindakan_dokter, tarif_tindakan_petugas, kso, menejemen,
                            total_byr, kd_pj, status, kelas, kategori
                        ) VALUES (
                            '$new_kd_esc', '$new_nm_esc', {$src['bagian_rs']}, {$src['bhp']}, {$src['tarif_perujuk']},
                            {$src['tarif_tindakan_dokter']}, {$src['tarif_tindakan_petugas']}, {$src['kso']}, {$src['menejemen']},
                            {$src['total_byr']}, '{$src['kd_pj']}', '{$src['status']}', '{$src['kelas']}', '{$src['kategori']}'
                        )
                    ");

                    if ($ins_pkg) {
                        // Copy mapped templates
                        $res_tpl = $conn->query("SELECT * FROM template_laboratorium WHERE kd_jenis_prw = '$src_esc' ORDER BY urut ASC, id_template ASC");
                        if ($res_tpl) {
                            while ($tpl = $res_tpl->fetch_assoc()) {
                                $res_m = $conn->query("SELECT MAX(id_template) as m FROM template_laboratorium");
                                $next_id = ($res_m && $row_m = $res_m->fetch_assoc()) ? (int)$row_m['m'] + 1 : 1;

                                $pem_esc = $conn->real_escape_string($tpl['Pemeriksaan']);
                                $sat_esc = $conn->real_escape_string($tpl['satuan']);
                                $ld_esc  = $conn->real_escape_string($tpl['nilai_rujukan_ld']);
                                $la_esc  = $conn->real_escape_string($tpl['nilai_rujukan_la']);
                                $pd_esc  = $conn->real_escape_string($tpl['nilai_rujukan_pd']);
                                $pa_esc  = $conn->real_escape_string($tpl['nilai_rujukan_pa']);

                                $conn->query("
                                    INSERT INTO template_laboratorium (
                                        kd_jenis_prw, id_template, Pemeriksaan, satuan,
                                        nilai_rujukan_ld, nilai_rujukan_la, nilai_rujukan_pd, nilai_rujukan_pa,
                                        bagian_rs, bhp, bagian_perujuk, bagian_dokter, bagian_laborat,
                                        kso, menejemen, biaya_item, urut
                                    ) VALUES (
                                        '$new_kd_esc', $next_id, '$pem_esc', '$sat_esc',
                                        '$ld_esc', '$la_esc', '$pd_esc', '$pa_esc',
                                        {$tpl['bagian_rs']}, {$tpl['bhp']}, {$tpl['bagian_perujuk']}, {$tpl['bagian_dokter']}, {$tpl['bagian_laborat']},
                                        " . ($tpl['kso'] ?? 0) . ", " . ($tpl['menejemen'] ?? 0) . ", {$tpl['biaya_item']}, {$tpl['urut']}
                                    )
                                ");
                            }
                        }
                        set_flash('success', "Berhasil menduplikasi paket <strong>$new_nm</strong> ($new_kd) beserta detail parameternya.");
                        redirect(BASE_URL . 'modules/laboratorium/master_lab.php?tab=template&kd_pkg=' . urlencode($new_kd));
                    }
                }
            }
        }
    } elseif ($act === 'delete') {
        $kd_del = sanitize($_POST['kd_jenis_prw'] ?? '');
        $kd_del_esc = $conn->real_escape_string($kd_del);

        $conn->query("DELETE FROM mlite_satu_sehat_mapping_lab WHERE kd_jenis_prw = '$kd_del_esc'");
        $conn->query("DELETE FROM template_laboratorium WHERE kd_jenis_prw = '$kd_del_esc'");
        $conn->query("DELETE FROM jns_perawatan_lab WHERE kd_jenis_prw = '$kd_del_esc'");

        set_flash('success', 'Paket Lab beserta pemetaan detail parameternya berhasil dihapus.');
        redirect(BASE_URL . 'modules/laboratorium/master_lab.php?tab=paket');
    }
}

// ─── POST Handler: Template Parameter Item ───────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_tpl'])) {
    $act = sanitize($_POST['action_tpl']);

    if ($act === 'save') {
        $kd_jenis_prw = sanitize($_POST['kd_jenis_prw'] ?? '');
        $id_template  = (int)($_POST['id_template'] ?? 0);
        $pemeriksaan  = sanitize($_POST['Pemeriksaan'] ?? '');
        $satuan       = sanitize($_POST['satuan'] ?? '');
        $rujukan_ld   = sanitize($_POST['nilai_rujukan_ld'] ?? '');
        $rujukan_la   = sanitize($_POST['nilai_rujukan_la'] ?? '');
        $rujukan_pd   = sanitize($_POST['nilai_rujukan_pd'] ?? '');
        $rujukan_pa   = sanitize($_POST['nilai_rujukan_pa'] ?? '');
        $urut         = (int)($_POST['urut'] ?? 1);
        $biaya_item   = (float)($_POST['biaya_item'] ?? 0);
        $is_edit      = !empty($_POST['is_edit']);

        $kd_esc  = $conn->real_escape_string($kd_jenis_prw);
        $pem_esc = $conn->real_escape_string($pemeriksaan);
        $sat_esc = $conn->real_escape_string($satuan);
        $ld_esc  = $conn->real_escape_string($rujukan_ld);
        $la_esc  = $conn->real_escape_string($rujukan_la);
        $pd_esc  = $conn->real_escape_string($rujukan_pd);
        $pa_esc  = $conn->real_escape_string($rujukan_pa);

        if (empty($kd_jenis_prw) || empty($pemeriksaan)) {
            set_flash('danger', 'Paket dan Nama Parameter Pemeriksaan wajib diisi.');
        } else {
            if ($is_edit && $id_template > 0) {
                $sql = "
                    UPDATE template_laboratorium SET
                        Pemeriksaan      = '$pem_esc',
                        satuan           = '$sat_esc',
                        nilai_rujukan_ld = '$ld_esc',
                        nilai_rujukan_la = '$la_esc',
                        nilai_rujukan_pd = '$pd_esc',
                        nilai_rujukan_pa = '$pa_esc',
                        biaya_item       = $biaya_item,
                        urut             = $urut
                    WHERE kd_jenis_prw = '$kd_esc' AND id_template = $id_template
                ";
            } else {
                $res_m = $conn->query("SELECT MAX(id_template) as m FROM template_laboratorium");
                $next_id = ($res_m && $row_m = $res_m->fetch_assoc()) ? (int)$row_m['m'] + 1 : 1;

                $sql = "
                    INSERT INTO template_laboratorium (
                        kd_jenis_prw, id_template, Pemeriksaan, satuan,
                        nilai_rujukan_ld, nilai_rujukan_la, nilai_rujukan_pd, nilai_rujukan_pa,
                        bagian_rs, bhp, bagian_perujuk, bagian_dokter, bagian_laborat,
                        kso, menejemen, biaya_item, urut
                    ) VALUES (
                        '$kd_esc', $next_id, '$pem_esc', '$sat_esc',
                        '$ld_esc', '$la_esc', '$pd_esc', '$pa_esc',
                        0, 0, 0, 0, 0, 0, 0, $biaya_item, $urut
                    )
                ";
            }

            if ($conn->query($sql)) {
                set_flash('success', "Parameter pemeriksaan <strong>$pemeriksaan</strong> berhasil disimpan.");
                redirect(BASE_URL . 'modules/laboratorium/master_lab.php?tab=template&kd_pkg=' . urlencode($kd_jenis_prw));
            } else {
                set_flash('danger', 'Gagal menyimpan parameter: ' . $conn->error);
            }
        }
    } elseif ($act === 'delete') {
        $kd_pkg = sanitize($_POST['kd_jenis_prw'] ?? '');
        $id_tpl = (int)($_POST['id_template'] ?? 0);
        $kd_esc = $conn->real_escape_string($kd_pkg);

        $conn->query("DELETE FROM mlite_satu_sehat_mapping_lab WHERE id_template = $id_tpl");
        $conn->query("DELETE FROM template_laboratorium WHERE kd_jenis_prw = '$kd_esc' AND id_template = $id_tpl");
        set_flash('success', 'Parameter pemeriksaan berhasil dihapus.');
        redirect(BASE_URL . 'modules/laboratorium/master_lab.php?tab=template&kd_pkg=' . urlencode($kd_pkg));
    }
}

// ─── POST Handler: Satu Sehat Mapping Lab ────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_ss'])) {
    $act = sanitize($_POST['action_ss']);

    if ($act === 'save') {
        $kd_jenis_prw   = sanitize($_POST['kd_jenis_prw'] ?? '');
        $code           = sanitize($_POST['code'] ?? '');
        $system         = sanitize($_POST['system'] ?? 'http://loinc.org');
        $display        = sanitize($_POST['display'] ?? '');
        $sampel_code    = sanitize($_POST['sampel_code'] ?? '119297000');
        $sampel_system  = sanitize($_POST['sampel_system'] ?? 'http://snomed.info/sct');
        $sampel_display = sanitize($_POST['sampel_display'] ?? 'Blood specimen');

        if (empty($kd_jenis_prw)) {
            set_flash('danger', 'Silahkan pilih perawatan laboratorium yang akan dipetakan.');
        } else {
            $kd_esc    = $conn->real_escape_string($kd_jenis_prw);
            $code_esc  = $conn->real_escape_string($code);
            $sys_esc   = $conn->real_escape_string($system ?: 'http://loinc.org');
            $disp_esc  = $conn->real_escape_string($display);
            $scode_esc = $conn->real_escape_string($sampel_code ?: '119297000');
            $ssys_esc  = $conn->real_escape_string($sampel_system ?: 'http://snomed.info/sct');
            $sdisp_esc = $conn->real_escape_string($sampel_display ?: 'Blood specimen');

            if (empty($code)) {
                // Delete mapping if empty code
                $conn->query("DELETE FROM mlite_satu_sehat_mapping_lab WHERE kd_jenis_prw = '$kd_esc'");
                set_flash('success', "Pemetaan Satu Sehat untuk perawatan <strong>$kd_jenis_prw</strong> berhasil dihapus.");
            } else {
                $sql = "
                    INSERT INTO mlite_satu_sehat_mapping_lab (
                        kd_jenis_prw, id_template, code, system, display,
                        sampel_code, sampel_system, sampel_display
                    ) VALUES (
                        '$kd_esc', 0, '$code_esc', '$sys_esc', '$disp_esc',
                        '$scode_esc', '$ssys_esc', '$sdisp_esc'
                    ) ON DUPLICATE KEY UPDATE
                        code           = '$code_esc',
                        system         = '$sys_esc',
                        display        = '$disp_esc',
                        sampel_code    = '$scode_esc',
                        sampel_system  = '$ssys_esc',
                        sampel_display = '$sdisp_esc'
                ";
                $conn->query($sql);

                // Sinkronkan ke sub-template jika ada
                $res_tpl = $conn->query("SELECT id_template FROM template_laboratorium WHERE kd_jenis_prw = '$kd_esc'");
                if ($res_tpl && $res_tpl->num_rows > 0) {
                    while ($rt = $res_tpl->fetch_assoc()) {
                        $id_t = (int)$rt['id_template'];
                        $conn->query("
                            INSERT INTO mlite_satu_sehat_mapping_lab (
                                id_template, kd_jenis_prw, code, system, display,
                                sampel_code, sampel_system, sampel_display
                            ) VALUES (
                                $id_t, '$kd_esc', '$code_esc', '$sys_esc', '$disp_esc',
                                '$scode_esc', '$ssys_esc', '$sdisp_esc'
                            ) ON DUPLICATE KEY UPDATE
                                kd_jenis_prw   = '$kd_esc',
                                code           = '$code_esc',
                                system         = '$sys_esc',
                                display        = '$disp_esc',
                                sampel_code    = '$scode_esc',
                                sampel_system  = '$ssys_esc',
                                sampel_display = '$sdisp_esc'
                        ");
                    }
                }

                set_flash('success', "Pemetaan Satu Sehat (LOINC: <strong>$code</strong> - $display) untuk <strong>$kd_jenis_prw</strong> berhasil disimpan.");
            }
            redirect(BASE_URL . 'modules/laboratorium/master_lab.php?tab=satusehat&kd_ss=' . urlencode($kd_jenis_prw));
        }
    } elseif ($act === 'delete') {
        $kd_del = sanitize($_POST['kd_jenis_prw'] ?? '');
        $kd_esc = $conn->real_escape_string($kd_del);
        $conn->query("DELETE FROM mlite_satu_sehat_mapping_lab WHERE kd_jenis_prw = '$kd_esc'");
        set_flash('success', "Pemetaan Satu Sehat untuk perawatan <strong>$kd_del</strong> berhasil dihapus.");
        redirect(BASE_URL . 'modules/laboratorium/master_lab.php?tab=satusehat');
    }
}

// ─── Query Master All Packages (for dropdowns) ────────────────
$all_packages = [];
$res_all = $conn->query("SELECT kd_jenis_prw, nm_perawatan, kategori, status, total_byr FROM jns_perawatan_lab ORDER BY kategori ASC, nm_perawatan ASC");
if ($res_all) while ($r = $res_all->fetch_assoc()) $all_packages[] = $r;

// Default kd_pkg if empty on template tab
if (empty($selected_pkg) && !empty($all_packages)) {
    $selected_pkg = $all_packages[0]['kd_jenis_prw'];
}

// Selected Package Details
$current_pkg_info = null;
if (!empty($selected_pkg)) {
    $pkg_esc = $conn->real_escape_string($selected_pkg);
    $res_cur = $conn->query("SELECT * FROM jns_perawatan_lab WHERE kd_jenis_prw = '$pkg_esc' LIMIT 1");
    if ($res_cur && $row = $res_cur->fetch_assoc()) $current_pkg_info = $row;
}

// Global Statistics
$stat_total_pkg = count($all_packages);
$stat_active_pkg = 0;
foreach ($all_packages as $ap) if ($ap['status'] === '1') $stat_active_pkg++;
$stat_inactive_pkg = $stat_total_pkg - $stat_active_pkg;

$stat_total_items = ($conn->query("SELECT COUNT(*) as c FROM template_laboratorium")->fetch_assoc()['c'] ?? 0);
$stat_total_loinc = ($conn->query("SELECT COUNT(*) as c FROM mlite_satu_sehat_mapping_lab WHERE code IS NOT NULL AND code != ''")->fetch_assoc()['c'] ?? 0);

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<!-- ─── Header Section ────────────────────────────────────── -->
<div class="page-header" style="margin-bottom:16px;">
  <div class="page-header-left">
    <div style="display:flex;align-items:center;gap:12px;">
      <a href="<?= BASE_URL ?>modules/laboratorium/index.php" class="btn btn-outline btn-sm" style="border-color:#cbd5e1;color:#334155;" title="Kembali ke Pelayanan Lab">
        <i class="fas fa-arrow-left"></i>
      </a>
      <div style="width:40px;height:40px;background:linear-gradient(135deg, #0284c7, #0369a1);border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:18px;box-shadow:0 2px 6px rgba(2,132,199,0.25);">
        <i class="fas fa-flask-vial"></i>
      </div>
      <div>
        <h1 class="page-title" style="margin:0;font-size:18px;font-weight:800;color:#0f172a;">Master Tarif & Pemetaan Laboratorium</h1>
        <p class="page-subtitle" style="margin:2px 0 0;font-size:12px;color:#64748b;">Pengaturan rincian tarif paket pemeriksaan, pemetaan parameter detail tindakan, nilai rujukan & standarisasi Satu Sehat</p>
      </div>
    </div>
  </div>

  <div class="page-header-right" style="display:flex;gap:8px;flex-wrap:wrap;">
    <?php if ($active_tab === 'paket'): ?>
      <button type="button" class="btn btn-primary btn-sm" onclick="openModalPaket('add')" style="background:linear-gradient(135deg,#0284c7,#0369a1);border:none;font-weight:700;">
        <i class="fas fa-plus-circle"></i> + Tambah Paket Lab
      </button>
    <?php elseif ($active_tab === 'template'): ?>
      <button type="button" class="btn btn-outline btn-sm" onclick="openModalPreset()" style="border-color:#0284c7;color:#0284c7;font-weight:700;">
        <i class="fas fa-wand-magic-sparkles"></i> Preset Standar Klinis
      </button>
      <button type="button" class="btn btn-primary btn-sm" onclick="openModalTemplate('add')" style="background:linear-gradient(135deg,#0284c7,#0369a1);border:none;font-weight:700;">
        <i class="fas fa-plus-circle"></i> + Tambah Parameter Item
      </button>
    <?php endif; ?>
  </div>
</div>

<!-- ─── Statistics Summary Bar ────────────────────────────── -->
<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:12px;margin-bottom:18px;">
  <div class="card" style="padding:12px 16px;border-left:4px solid #0284c7;background:#fff;border-radius:10px;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
    <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;">Total Paket Pemeriksaan</div>
    <div style="font-size:20px;font-weight:800;color:#0f172a;margin-top:2px;">
      <?= number_format($stat_total_pkg) ?> <span style="font-size:11.5px;font-weight:500;color:#64748b;">Paket (<?= $stat_active_pkg ?> Aktif)</span>
    </div>
  </div>

  <div class="card" style="padding:12px 16px;border-left:4px solid #059669;background:#fff;border-radius:10px;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
    <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;">Total Parameter Terpetakan</div>
    <div style="font-size:20px;font-weight:800;color:#059669;margin-top:2px;">
      <?= number_format($stat_total_items) ?> <span style="font-size:11.5px;font-weight:500;color:#64748b;">Item Tindakan</span>
    </div>
  </div>

  <div class="card" style="padding:12px 16px;border-left:4px solid #7c3aed;background:#fff;border-radius:10px;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
    <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;">Bridging Satu Sehat (LOINC)</div>
    <div style="font-size:20px;font-weight:800;color:#7c3aed;margin-top:2px;">
      <?= number_format($stat_total_loinc) ?> / <?= number_format($stat_total_items) ?> <span style="font-size:11.5px;font-weight:500;color:#64748b;">Item Mapped</span>
    </div>
  </div>
</div>

<!-- ─── Navigation Tabs ───────────────────────────────────── -->
<div style="display:flex;gap:6px;margin-bottom:16px;border-bottom:1px solid #e2e8f0;padding-bottom:8px;overflow-x:auto;">
  <a href="?tab=paket" class="btn btn-sm <?= $active_tab === 'paket' ? 'btn-primary' : 'btn-outline' ?>" style="font-weight:700;font-size:12px;white-space:nowrap;">
    <i class="fas fa-boxes-stacked"></i> 1. Master Paket & Tarif Lab
  </a>
  <a href="?tab=template&kd_pkg=<?= urlencode($selected_pkg) ?>" class="btn btn-sm <?= $active_tab === 'template' ? 'btn-primary' : 'btn-outline' ?>" style="font-weight:700;font-size:12px;white-space:nowrap;">
    <i class="fas fa-list-check"></i> 2. Pemetaan Detail Tindakan & Nilai Rujukan
  </a>
  <a href="?tab=matriks" class="btn btn-sm <?= $active_tab === 'matriks' ? 'btn-primary' : 'btn-outline' ?>" style="font-weight:700;font-size:12px;white-space:nowrap;">
    <i class="fas fa-sitemap"></i> 3. Matriks & Ringkasan Hierarki
  </a>
  <a href="?tab=satusehat" class="btn btn-sm <?= $active_tab === 'satusehat' ? 'btn-primary' : 'btn-outline' ?>" style="font-weight:700;font-size:12px;white-space:nowrap;">
    <i class="fas fa-shield-heart"></i> 4. Standarisasi Satu Sehat (LOINC)
  </a>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- TAB 1: MASTER PAKET & TARIF LABORATORIUM                    -->
<!-- ═══════════════════════════════════════════════════════════ -->
<?php if ($active_tab === 'paket'): ?>
  <?php
  $where_pkg = "1=1";
  if (!empty($search_q)) {
      $s = $conn->real_escape_string($search_q);
      $where_pkg .= " AND (j.kd_jenis_prw LIKE '%$s%' OR j.nm_perawatan LIKE '%$s%')";
  }
  if (!empty($filter_kat)) {
      $kat_s = $conn->real_escape_string($filter_kat);
      $where_pkg .= " AND j.kategori = '$kat_s'";
  }
  if ($filter_stts !== '') {
      $stts_s = $conn->real_escape_string($filter_stts);
      $where_pkg .= " AND j.status = '$stts_s'";
  }

  // Count & Pagination
  $count_res_p = $conn->query("SELECT COUNT(*) as c FROM jns_perawatan_lab j WHERE $where_pkg");
  $total_pkg_rows = $count_res_p ? (int)$count_res_p->fetch_assoc()['c'] : 0;
  $total_pages_p = max(1, ceil($total_pkg_rows / $limit_p));
  $offset_p = ($page_p - 1) * $limit_p;

  $packages_list = [];
  $res_p = $conn->query("
      SELECT j.*, 
             (SELECT COUNT(*) FROM template_laboratorium t WHERE t.kd_jenis_prw = j.kd_jenis_prw) as total_items,
             (SELECT COUNT(*) FROM mlite_satu_sehat_mapping_lab m 
              JOIN template_laboratorium t ON m.id_template = t.id_template 
              WHERE t.kd_jenis_prw = j.kd_jenis_prw AND m.code IS NOT NULL AND m.code != '') as mapped_loinc
      FROM jns_perawatan_lab j
      WHERE $where_pkg
      ORDER BY j.status DESC, j.kategori ASC, j.nm_perawatan ASC
      LIMIT $limit_p OFFSET $offset_p
  ");
  if ($res_p) while ($r = $res_p->fetch_assoc()) $packages_list[] = $r;
  ?>
  
  <!-- Filter & Search Bar -->
  <div class="card" style="margin-bottom:14px;border-radius:10px;background:#ffffff;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
    <div class="card-body" style="padding:12px 18px;">
      <form method="GET" action="" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin:0;">
        <input type="hidden" name="tab" value="paket">

        <div style="flex:1;min-width:200px;">
          <input type="text" name="q" class="form-control form-control-sm" placeholder="Cari kode atau nama paket lab..." value="<?= htmlspecialchars($search_q) ?>" style="font-size:12px;">
        </div>

        <div style="width:170px;">
          <select name="kat" class="form-control form-control-sm" style="font-size:12px;" onchange="this.form.submit()">
            <option value="">Semua Kategori</option>
            <option value="PK" <?= ($filter_kat === 'PK') ? 'selected' : '' ?>>PK (Patologi Klinik)</option>
            <option value="PA" <?= ($filter_kat === 'PA') ? 'selected' : '' ?>>PA (Patologi Anatomi)</option>
            <option value="MB" <?= ($filter_kat === 'MB') ? 'selected' : '' ?>>MB (Mikrobiologi)</option>
          </select>
        </div>

        <div style="width:140px;">
          <select name="status" class="form-control form-control-sm" style="font-size:12px;" onchange="this.form.submit()">
            <option value="">Semua Status</option>
            <option value="1" <?= ($filter_stts === '1') ? 'selected' : '' ?>>Aktif Saja</option>
            <option value="0" <?= ($filter_stts === '0') ? 'selected' : '' ?>>Non-Aktif</option>
          </select>
        </div>

        <div style="width:110px;">
          <select name="limit_p" class="form-control form-control-sm" style="font-size:12px;" onchange="this.form.submit()">
            <option value="15" <?= ($limit_p === 15) ? 'selected' : '' ?>>15 / hal</option>
            <option value="25" <?= ($limit_p === 25) ? 'selected' : '' ?>>25 / hal</option>
            <option value="50" <?= ($limit_p === 50) ? 'selected' : '' ?>>50 / hal</option>
            <option value="100" <?= ($limit_p === 100) ? 'selected' : '' ?>>100 / hal</option>
          </select>
        </div>

        <button type="submit" class="btn btn-outline btn-sm" style="font-size:12px;">
          <i class="fas fa-search"></i> Filter
        </button>

        <?php if (!empty($search_q) || !empty($filter_kat) || $filter_stts !== ''): ?>
          <a href="?tab=paket" class="btn btn-outline btn-sm" style="color:#ef4444;border-color:#fca5a5;font-size:12px;" title="Reset Filter">
            <i class="fas fa-times"></i> Reset
          </a>
        <?php endif; ?>
      </form>
    </div>
  </div>

  <!-- Table Paket Lab -->
  <div class="card" style="border-radius:10px;background:#ffffff;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
    <div class="table-responsive">
      <table class="table table-hover" style="margin:0;font-size:12px;">
        <thead style="background:#f8fafc;color:#475569;font-weight:700;border-bottom:1px solid #e2e8f0;">
          <tr>
            <th style="width:80px;">Kode</th>
            <th>Nama Pemeriksaan Laboratorium</th>
            <th style="width:85px;text-align:center;">Kategori</th>
            <th style="width:75px;text-align:center;">Status</th>
            <th style="width:120px;text-align:right;">Total Tarif</th>
            <th style="min-width:280px;">Rincian Komponen Tarif (Rp)</th>
            <th style="width:120px;text-align:center;">Pemetaan Item</th>
            <th style="width:150px;text-align:center;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($packages_list)): ?>
            <tr>
              <td colspan="8" style="text-align:center;padding:30px;color:#94a3b8;">
                <i class="fas fa-flask-vial" style="font-size:32px;color:#cbd5e1;display:block;margin-bottom:8px;"></i>
                Tidak ada data paket pemeriksaan laboratorium yang sesuai filter.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($packages_list as $p): ?>
              <tr style="border-bottom:1px solid #f1f5f9; <?= ($p['status'] === '0') ? 'background:#f8fafc;opacity:0.75;' : '' ?>">
                <td style="font-family:monospace;font-weight:700;color:#0284c7;">
                  <?= htmlspecialchars($p['kd_jenis_prw']) ?>
                </td>
                <td>
                  <div style="font-weight:700;color:#0f172a;font-size:13px;">
                    <?= htmlspecialchars($p['nm_perawatan']) ?>
                  </div>
                  <div style="font-size:11px;color:#64748b;">Kelas: <?= htmlspecialchars($p['kelas'] ?: 'Rawat Jalan') ?></div>
                </td>
                <td style="text-align:center;">
                  <?php
                  $kat_badges = [
                    'PK' => 'background:#e0f2fe;color:#0369a1;border:1px solid #bae6fd;',
                    'PA' => 'background:#fef3c7;color:#92400e;border:1px solid #fde68a;',
                    'MB' => 'background:#f3e8ff;color:#6b21a8;border:1px solid #e9d5ff;'
                  ];
                  $b_style = $kat_badges[$p['kategori']] ?? 'background:#f1f5f9;color:#475569;';
                  ?>
                  <span style="font-size:11px;font-weight:700;padding:2px 8px;border-radius:4px;display:inline-block;<?= $b_style ?>">
                    <?= htmlspecialchars($p['kategori']) ?>
                  </span>
                </td>
                <td style="text-align:center;">
                  <?php if ($p['status'] === '1'): ?>
                    <span class="badge badge-success" style="font-size:10px;cursor:pointer;" onclick="toggleStatusPkg('<?= $p['kd_jenis_prw'] ?>', '0')" title="Klik untuk nonaktifkan">
                      <i class="fas fa-check"></i> Aktif
                    </span>
                  <?php else: ?>
                    <span class="badge badge-danger" style="font-size:10px;cursor:pointer;" onclick="toggleStatusPkg('<?= $p['kd_jenis_prw'] ?>', '1')" title="Klik untuk aktifkan">
                      <i class="fas fa-ban"></i> Non-Aktif
                    </span>
                  <?php endif; ?>
                </td>
                <td style="text-align:right;font-weight:800;color:#059669;font-size:13px;">
                  <?= rupiah((float)$p['total_byr']) ?>
                </td>
                <td style="font-size:11px;color:#64748b;line-height:1.5;">
                  <span>Sarana: <strong><?= rupiah((float)$p['bagian_rs']) ?></strong></span> &bull; 
                  <span>BHP: <strong><?= rupiah((float)$p['bhp']) ?></strong></span> &bull; 
                  <span>Dokter: <strong><?= rupiah((float)$p['tarif_tindakan_dokter']) ?></strong></span> &bull; 
                  <span>Petugas: <strong><?= rupiah((float)$p['tarif_tindakan_petugas']) ?></strong></span>
                  <?php if ((float)$p['tarif_perujuk'] > 0 || (float)$p['kso'] > 0 || (float)$p['menejemen'] > 0): ?>
                    <br>
                    <span style="color:#94a3b8;">
                      Perujuk: <?= rupiah((float)$p['tarif_perujuk']) ?> | KSO: <?= rupiah((float)$p['kso']) ?> | Adm: <?= rupiah((float)$p['menejemen']) ?>
                    </span>
                  <?php endif; ?>
                </td>
                <td style="text-align:center;">
                  <a href="?tab=template&kd_pkg=<?= urlencode($p['kd_jenis_prw']) ?>" class="btn btn-outline btn-sm" style="padding:2px 8px;font-size:11px;border-color:#0284c7;color:#0284c7;font-weight:700;" title="Kelola Pemetaan Parameter Tindakan">
                    <i class="fas fa-list-check"></i> <?= $p['total_items'] ?> Item
                    <?php if ($p['mapped_loinc'] > 0): ?>
                      <span style="color:#7c3aed;font-size:10px;" title="LOINC Mapped: <?= $p['mapped_loinc'] ?>">(<?= $p['mapped_loinc'] ?> FHIR)</span>
                    <?php endif; ?>
                  </a>
                </td>
                <td style="text-align:center;">
                  <div style="display:flex;gap:4px;justify-content:center;align-items:center;">
                    <a href="?tab=template&kd_pkg=<?= urlencode($p['kd_jenis_prw']) ?>" class="btn btn-outline btn-sm" style="padding:4px 7px;font-size:11px;color:#0284c7;border-color:#bae6fd;" title="Petakan Detail Tindakan">
                      <i class="fas fa-sliders"></i>
                    </a>
                    <button type="button" class="btn btn-outline btn-sm" onclick="openCloneModal(<?= htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8') ?>)" style="padding:4px 7px;font-size:11px;color:#d97706;border-color:#fde68a;" title="Duplikasi / Klon Paket">
                      <i class="fas fa-clone"></i>
                    </button>
                    <button type="button" class="btn btn-outline btn-sm" onclick="editPaket(<?= htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8') ?>)" style="padding:4px 7px;font-size:11px;color:#334155;" title="Edit Komponen Tarif">
                      <i class="fas fa-edit"></i>
                    </button>
                    <form method="POST" action="" onsubmit="return confirm('Hapus paket <?= htmlspecialchars($p['nm_perawatan']) ?> beserta semua detail parameternya?')" style="margin:0;">
                      <input type="hidden" name="action_pkg" value="delete">
                      <input type="hidden" name="kd_jenis_prw" value="<?= htmlspecialchars($p['kd_jenis_prw']) ?>">
                      <button type="submit" class="btn btn-outline btn-sm" style="padding:4px 7px;font-size:11px;color:#ef4444;border-color:#fca5a5;" title="Hapus Paket">
                        <i class="fas fa-trash-alt"></i>
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

    <!-- Paginasi Tab 1 -->
    <?= render_lab_pagination($page_p, $total_pages_p, $total_pkg_rows, $limit_p, [
        'tab'      => 'paket',
        'q'        => $search_q,
        'kat'      => $filter_kat,
        'status'   => $filter_stts,
        'limit_p'  => $limit_p,
        'page_key' => 'page_p'
    ]) ?>
  </div>

<?php endif; ?>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- TAB 2: PEMETAAN DETAIL TINDAKAN & NILAI RUJUKAN             -->
<!-- ═══════════════════════════════════════════════════════════ -->
<?php if ($active_tab === 'template'): ?>
  <?php
  $where_t = "t.kd_jenis_prw = '$pkg_esc'";
  if (!empty($search_t)) {
      $st_esc = $conn->real_escape_string($search_t);
      $where_t .= " AND (t.Pemeriksaan LIKE '%$st_esc%' OR t.satuan LIKE '%$st_esc%')";
  }

  // Count template items
  $count_res_t = $conn->query("SELECT COUNT(*) as c FROM template_laboratorium t WHERE $where_t");
  $total_t_rows = $count_res_t ? (int)$count_res_t->fetch_assoc()['c'] : 0;
  $total_pages_t = max(1, ceil($total_t_rows / $limit_t));
  $offset_t = ($page_t - 1) * $limit_t;

  $templates_list = [];
  if (!empty($selected_pkg)) {
      $res_t = $conn->query("
          SELECT t.*, j.nm_perawatan,
                 m.code as loinc_code, m.display as loinc_display,
                 m.sampel_code, m.sampel_display
          FROM template_laboratorium t
          JOIN jns_perawatan_lab j ON t.kd_jenis_prw = j.kd_jenis_prw
          LEFT JOIN mlite_satu_sehat_mapping_lab m ON t.id_template = m.id_template
          WHERE $where_t
          ORDER BY t.urut ASC, t.id_template ASC
          LIMIT $limit_t OFFSET $offset_t
      ");
      if ($res_t) while ($r = $res_t->fetch_assoc()) $templates_list[] = $r;
  }
  ?>

  <!-- Selector Paket Terpilih -->
  <div class="card" style="margin-bottom:14px;border-radius:10px;background:#ffffff;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
    <div class="card-body" style="padding:14px 18px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
      
      <div style="display:flex;align-items:center;gap:12px;flex:1;min-width:300px;">
        <label style="font-size:12px;font-weight:700;color:#334155;margin:0;white-space:nowrap;">Pilih Paket Pemeriksaan:</label>
        <select class="form-control form-control-sm" style="max-width:460px;font-size:12.5px;font-weight:700;color:#0f172a;" onchange="window.location.href='?tab=template&kd_pkg=' + encodeURIComponent(this.value)">
          <?php foreach ($all_packages as $pkg): ?>
            <option value="<?= htmlspecialchars($pkg['kd_jenis_prw']) ?>" <?= ($selected_pkg === $pkg['kd_jenis_prw']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($pkg['kd_jenis_prw']) ?> — <?= htmlspecialchars($pkg['nm_perawatan']) ?> [<?= $pkg['kategori'] ?>] (<?= rupiah((float)$pkg['total_byr']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <?php if ($current_pkg_info): ?>
        <div style="display:flex;gap:10px;align-items:center;">
          <span style="font-size:11.5px;color:#64748b;">
            Tarif Paket: <strong style="color:#059669;font-size:13px;"><?= rupiah((float)$current_pkg_info['total_byr']) ?></strong>
          </span>
          <button type="button" class="btn btn-outline btn-sm" onclick='editPaket(<?= json_encode($current_pkg_info) ?>)' style="font-size:11.5px;padding:3px 8px;">
            <i class="fas fa-edit"></i> Edit Tarif
          </button>
        </div>
      <?php endif; ?>

    </div>
  </div>

  <!-- Quick Action & Search Header -->
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;flex-wrap:wrap;gap:8px;">
    <div style="display:flex;align-items:center;gap:10px;">
      <div style="font-size:13px;font-weight:700;color:#334155;">
        Daftar Parameter Item Tindakan (<?= number_format($total_t_rows) ?> Parameter)
      </div>
      <form method="GET" action="" style="margin:0;display:flex;gap:6px;">
        <input type="hidden" name="tab" value="template">
        <input type="hidden" name="kd_pkg" value="<?= htmlspecialchars($selected_pkg) ?>">
        <input type="text" name="q_t" class="form-control form-control-sm" placeholder="Cari parameter..." value="<?= htmlspecialchars($search_t) ?>" style="font-size:11.5px;width:180px;height:28px;">
        <button type="submit" class="btn btn-outline btn-sm" style="padding:2px 8px;font-size:11px;height:28px;"><i class="fas fa-search"></i></button>
        <?php if (!empty($search_t)): ?>
          <a href="?tab=template&kd_pkg=<?= urlencode($selected_pkg) ?>" class="btn btn-outline btn-sm" style="padding:2px 8px;font-size:11px;height:28px;color:#ef4444;"><i class="fas fa-times"></i></a>
        <?php endif; ?>
      </form>
    </div>
    
    <div style="display:flex;gap:8px;">
      <button type="button" class="btn btn-outline btn-sm" onclick="openModalPreset()" style="font-size:11.5px;border-color:#0284c7;color:#0284c7;font-weight:700;">
        <i class="fas fa-wand-magic-sparkles"></i> 1-Click Preset Generator
      </button>
      <button type="button" class="btn btn-primary btn-sm" onclick="openModalTemplate('add')" style="background:linear-gradient(135deg,#0284c7,#0369a1);border:none;font-size:11.5px;font-weight:700;">
        <i class="fas fa-plus-circle"></i> + Tambah Parameter Item
      </button>
    </div>
  </div>

  <!-- Table Mapped Template Items -->
  <div class="card" style="border-radius:10px;background:#ffffff;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
    <div class="table-responsive">
      <table class="table table-hover" style="margin:0;font-size:12px;">
        <thead style="background:#f8fafc;color:#475569;font-weight:700;border-bottom:1px solid #e2e8f0;">
          <tr>
            <th style="width:70px;text-align:center;">Urutan</th>
            <th>Detail Tindakan / Parameter</th>
            <th style="width:90px;">Satuan</th>
            <th style="width:140px;">Rujukan Laki Dewasa</th>
            <th style="width:140px;">Rujukan Laki Anak</th>
            <th style="width:140px;">Rujukan Wanita Dewasa</th>
            <th style="width:140px;">Rujukan Wanita Anak</th>
            <th style="width:130px;text-align:center;">Satu Sehat (LOINC)</th>
            <th style="width:120px;text-align:center;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($templates_list)): ?>
            <tr>
              <td colspan="9" style="text-align:center;padding:36px;color:#94a3b8;">
                <i class="fas fa-flask" style="font-size:36px;color:#cbd5e1;display:block;margin-bottom:10px;"></i>
                <div style="font-weight:700;color:#64748b;font-size:13px;">Belum ada detail tindakan / parameter untuk paket ini.</div>
                <div style="font-size:11.5px;margin-top:4px;">Klik <strong>+ Tambah Parameter Item</strong> atau gunakan <strong>1-Click Preset Generator</strong> untuk memasukkan daftar parameter standar.</div>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($templates_list as $idx => $t): ?>
              <tr style="border-bottom:1px solid #f1f5f9;">
                <td style="text-align:center;">
                  <div style="display:flex;align-items:center;justify-content:center;gap:3px;">
                    <span style="font-weight:800;color:#475569;width:18px;text-align:center;"><?= $t['urut'] ?></span>
                    <div style="display:flex;flex-direction:column;gap:1px;">
                      <button type="button" class="btn btn-outline btn-sm" onclick="reorderItem('<?= $t['kd_jenis_prw'] ?>', <?= $t['id_template'] ?>, 'up')" style="padding:0 3px;font-size:9px;line-height:1;height:14px;" title="Naikkan Urutan">▲</button>
                      <button type="button" class="btn btn-outline btn-sm" onclick="reorderItem('<?= $t['kd_jenis_prw'] ?>', <?= $t['id_template'] ?>, 'down')" style="padding:0 3px;font-size:9px;line-height:1;height:14px;" title="Turunkan Urutan">▼</button>
                    </div>
                  </div>
                </td>
                <td>
                  <div style="font-weight:700;color:#0f172a;font-size:12.5px;">
                    <?= htmlspecialchars($t['Pemeriksaan']) ?>
                  </div>
                  <?php if ((float)$t['biaya_item'] > 0): ?>
                    <span style="font-size:10.5px;color:#059669;font-weight:600;">Biaya Item: <?= rupiah((float)$t['biaya_item']) ?></span>
                  <?php endif; ?>
                </td>
                <td style="font-family:monospace;color:#64748b;font-weight:600;">
                  <?= htmlspecialchars($t['satuan'] ?: '-') ?>
                </td>
                <td style="color:#0f172a;font-weight:500;">
                  <?= htmlspecialchars($t['nilai_rujukan_ld'] ?: '-') ?>
                </td>
                <td style="color:#64748b;">
                  <?= htmlspecialchars($t['nilai_rujukan_la'] ?: '-') ?>
                </td>
                <td style="color:#0f172a;font-weight:500;">
                  <?= htmlspecialchars($t['nilai_rujukan_pd'] ?: '-') ?>
                </td>
                <td style="color:#64748b;">
                  <?= htmlspecialchars($t['nilai_rujukan_pa'] ?: '-') ?>
                </td>
                <td style="text-align:center;">
                  <?php if (!empty($t['loinc_code'])): ?>
                    <span class="badge" style="background:#f3e8ff;color:#6b21a8;border:1px solid #e9d5ff;font-size:10.5px;cursor:pointer;" onclick="openModalSatuSehat(<?= htmlspecialchars(json_encode($t), ENT_QUOTES, 'UTF-8') ?>)" title="<?= htmlspecialchars($t['loinc_display']) ?>">
                      <i class="fas fa-shield-heart"></i> <?= htmlspecialchars($t['loinc_code']) ?>
                    </span>
                  <?php else: ?>
                    <button type="button" class="btn btn-outline btn-sm" onclick="openModalSatuSehat(<?= htmlspecialchars(json_encode($t), ENT_QUOTES, 'UTF-8') ?>)" style="padding:2px 6px;font-size:10px;border-color:#e2e8f0;color:#64748b;">
                      <i class="fas fa-link"></i> Petakan
                    </button>
                  <?php endif; ?>
                </td>
                <td style="text-align:center;">
                  <div style="display:flex;gap:4px;justify-content:center;">
                    <button type="button" class="btn btn-outline btn-sm" onclick="openModalSatuSehat(<?= htmlspecialchars(json_encode($t), ENT_QUOTES, 'UTF-8') ?>)" style="padding:3px 7px;font-size:11px;color:#7c3aed;border-color:#e9d5ff;" title="Pemetaan Satu Sehat LOINC">
                      <i class="fas fa-shield-heart"></i>
                    </button>
                    <button type="button" class="btn btn-outline btn-sm" onclick="editTemplate(<?= htmlspecialchars(json_encode($t), ENT_QUOTES, 'UTF-8') ?>)" style="padding:3px 7px;font-size:11px;color:#334155;" title="Edit Parameter">
                      <i class="fas fa-edit"></i>
                    </button>
                    <form method="POST" action="" onsubmit="return confirm('Hapus parameter <?= htmlspecialchars($t['Pemeriksaan']) ?>?')" style="margin:0;">
                      <input type="hidden" name="action_tpl" value="delete">
                      <input type="hidden" name="kd_jenis_prw" value="<?= htmlspecialchars($t['kd_jenis_prw']) ?>">
                      <input type="hidden" name="id_template" value="<?= $t['id_template'] ?>">
                      <button type="submit" class="btn btn-outline btn-sm" style="padding:3px 7px;font-size:11px;color:#ef4444;border-color:#fca5a5;" title="Hapus">
                        <i class="fas fa-trash-alt"></i>
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

    <!-- Paginasi Tab 2 -->
    <?= render_lab_pagination($page_t, $total_pages_t, $total_t_rows, $limit_t, [
        'tab'      => 'template',
        'kd_pkg'   => $selected_pkg,
        'q_t'      => $search_t,
        'limit_t'  => $limit_t,
        'page_key' => 'page_t'
    ]) ?>
  </div>

<?php endif; ?>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- TAB 3: MATRIKS & RINGKASAN HIERARKI LAB                     -->
<!-- ═══════════════════════════════════════════════════════════ -->
<?php if ($active_tab === 'matriks'): ?>
  <?php
  $where_m = "1=1";
  if (!empty($search_m)) {
      $sm_esc = $conn->real_escape_string($search_m);
      $where_m .= " AND (j.kd_jenis_prw LIKE '%$sm_esc%' OR j.nm_perawatan LIKE '%$sm_esc%')";
  }

  $count_res_m = $conn->query("SELECT COUNT(*) as c FROM jns_perawatan_lab j WHERE $where_m");
  $total_m_rows = $count_res_m ? (int)$count_res_m->fetch_assoc()['c'] : 0;
  $total_pages_m = max(1, ceil($total_m_rows / $limit_m));
  $offset_m = ($page_m - 1) * $limit_m;

  $matrix_packages = [];
  $res_mp = $conn->query("
      SELECT j.* 
      FROM jns_perawatan_lab j
      WHERE $where_m
      ORDER BY j.kategori ASC, j.nm_perawatan ASC
      LIMIT $limit_m OFFSET $offset_m
  ");
  if ($res_mp) while ($r = $res_mp->fetch_assoc()) $matrix_packages[] = $r;
  ?>

  <div class="card" style="margin-bottom:14px;border-radius:10px;background:#ffffff;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
    <div class="card-body" style="padding:14px 18px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
      <div>
        <h2 style="margin:0;font-size:14px;font-weight:800;color:#0f172a;">Struktur Hierarkis Paket dan Detail Tindakan Lab</h2>
        <p style="margin:2px 0 0;font-size:12px;color:#64748b;">Gambaran utuh seluruh paket pemeriksaan beserta parameter detail yang sudah dipetakan</p>
      </div>
      <div style="display:flex;gap:8px;align-items:center;">
        <form method="GET" action="" style="display:flex;gap:6px;margin:0;">
          <input type="hidden" name="tab" value="matriks">
          <input type="text" name="q_m" class="form-control form-control-sm" placeholder="Cari paket di matriks..." value="<?= htmlspecialchars($search_m) ?>" style="font-size:12px;width:200px;">
          <button type="submit" class="btn btn-outline btn-sm"><i class="fas fa-search"></i></button>
          <?php if (!empty($search_m)): ?>
            <a href="?tab=matriks" class="btn btn-outline btn-sm" style="color:#ef4444;"><i class="fas fa-times"></i></a>
          <?php endif; ?>
        </form>
        <button type="button" class="btn btn-outline btn-sm" onclick="window.print()">
          <i class="fas fa-print"></i> Cetak Matriks
        </button>
      </div>
    </div>
  </div>

  <div style="display:flex;flex-direction:column;gap:12px;">
    <?php if (empty($matrix_packages)): ?>
      <div class="card" style="padding:30px;text-align:center;color:#94a3b8;border-radius:10px;background:#fff;">
        Tidak ada data paket pemeriksaan yang sesuai pencarian.
      </div>
    <?php else: ?>
      <?php foreach ($matrix_packages as $pkg): ?>
        <?php
        $kd_p_esc = $conn->real_escape_string($pkg['kd_jenis_prw']);
        $res_items = $conn->query("
            SELECT t.*, m.code as loinc_code 
            FROM template_laboratorium t 
            LEFT JOIN mlite_satu_sehat_mapping_lab m ON t.id_template = m.id_template
            WHERE t.kd_jenis_prw = '$kd_p_esc' 
            ORDER BY t.urut ASC, t.id_template ASC
        ");
        $items = [];
        if ($res_items) while ($it = $res_items->fetch_assoc()) $items[] = $it;
        ?>
        <div class="card" style="border-radius:10px;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,0.04);overflow:hidden;">
          <div style="background:#f8fafc;padding:10px 16px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;">
            <div style="display:flex;align-items:center;gap:10px;">
              <span style="font-family:monospace;font-weight:700;color:#0284c7;"><?= htmlspecialchars($pkg['kd_jenis_prw']) ?></span>
              <span style="font-weight:800;color:#0f172a;font-size:13px;"><?= htmlspecialchars($pkg['nm_perawatan']) ?></span>
              <span class="badge badge-primary" style="font-size:10px;"><?= htmlspecialchars($pkg['kategori']) ?></span>
              <?php if ($pkg['status'] === '0'): ?>
                <span class="badge badge-danger" style="font-size:10px;">Non-Aktif</span>
              <?php endif; ?>
            </div>
            <div style="display:flex;align-items:center;gap:12px;">
              <span style="font-weight:800;color:#059669;font-size:12.5px;"><?= rupiah((float)$pkg['total_byr']) ?></span>
              <a href="?tab=template&kd_pkg=<?= urlencode($pkg['kd_jenis_prw']) ?>" class="btn btn-outline btn-sm" style="font-size:11px;padding:2px 8px;">
                <i class="fas fa-edit"></i> Kelola (<?= count($items) ?> Item)
              </a>
            </div>
          </div>

          <div style="padding:0;">
            <?php if (empty($items)): ?>
              <div style="padding:14px 18px;font-size:11.5px;color:#94a3b8;font-style:italic;">
                Belum ada parameter detail tindakan untuk paket ini.
              </div>
            <?php else: ?>
              <table class="table" style="margin:0;font-size:11.5px;">
                <thead style="background:#ffffff;color:#64748b;font-weight:600;border-bottom:1px solid #f1f5f9;">
                  <tr>
                    <th style="width:40px;text-align:center;">#</th>
                    <th>Nama Detail Tindakan</th>
                    <th style="width:80px;">Satuan</th>
                    <th style="width:140px;">Rujukan Laki Dewasa</th>
                    <th style="width:140px;">Rujukan Perempuan Dewasa</th>
                    <th style="width:110px;">Kode LOINC</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($items as $it): ?>
                    <tr style="border-bottom:1px solid #f8fafc;">
                      <td style="text-align:center;color:#94a3b8;"><?= $it['urut'] ?></td>
                      <td style="font-weight:700;color:#1e293b;"><?= htmlspecialchars($it['Pemeriksaan']) ?></td>
                      <td style="font-family:monospace;color:#64748b;"><?= htmlspecialchars($it['satuan'] ?: '-') ?></td>
                      <td style="color:#334155;"><?= htmlspecialchars($it['nilai_rujukan_ld'] ?: '-') ?></td>
                      <td style="color:#334155;"><?= htmlspecialchars($it['nilai_rujukan_pd'] ?: '-') ?></td>
                      <td>
                        <?php if (!empty($it['loinc_code'])): ?>
                          <span style="font-family:monospace;font-weight:700;color:#7c3aed;background:#f3e8ff;padding:1px 6px;border-radius:4px;font-size:10.5px;">
                            <?= htmlspecialchars($it['loinc_code']) ?>
                          </span>
                        <?php else: ?>
                          <span style="color:#cbd5e1;">-</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <!-- Paginasi Tab 3 -->
    <div class="card" style="border-radius:10px;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
      <?= render_lab_pagination($page_m, $total_pages_m, $total_m_rows, $limit_m, [
          'tab'      => 'matriks',
          'q_m'      => $search_m,
          'limit_m'  => $limit_m,
          'page_key' => 'page_m'
      ]) ?>
    </div>
  </div>

<?php endif; ?>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- TAB 4: STANDARISASI SATU SEHAT (LOINC & SNOMED CT)          -->
<!-- ═══════════════════════════════════════════════════════════ -->
<?php if ($active_tab === 'satusehat'): ?>
  <?php
  // Ambil data pilihan jika ada parameter kd_ss
  $selected_ss_kd = sanitize($_GET['kd_ss'] ?? '');
  $current_ss_data = null;
  if (!empty($selected_ss_kd)) {
      $kd_ss_esc = $conn->real_escape_string($selected_ss_kd);
      $res_ss_item = $conn->query("
          SELECT j.kd_jenis_prw, j.nm_perawatan, j.kategori,
                 m.code, m.system, m.display,
                 m.sampel_code, m.sampel_system, m.sampel_display
          FROM jns_perawatan_lab j
          LEFT JOIN mlite_satu_sehat_mapping_lab m ON j.kd_jenis_prw = m.kd_jenis_prw
          WHERE j.kd_jenis_prw = '$kd_ss_esc'
          LIMIT 1
      ");
      if ($res_ss_item && $row = $res_ss_item->fetch_assoc()) {
          $current_ss_data = $row;
      }
  }

  // Filter Table List
  $where_ss = "1=1";
  if (!empty($search_ss)) {
      $sss_esc = $conn->real_escape_string($search_ss);
      $where_ss .= " AND (j.kd_jenis_prw LIKE '%$sss_esc%' OR j.nm_perawatan LIKE '%$sss_esc%' OR m.code LIKE '%$sss_esc%' OR m.display LIKE '%$sss_esc%')";
  }
  if ($filter_ss === 'mapped') {
      $where_ss .= " AND (m.code IS NOT NULL AND m.code != '')";
  } elseif ($filter_ss === 'unmapped') {
      $where_ss .= " AND (m.code IS NULL OR m.code = '')";
  }

  // Count & Pagination
  $count_res_ss = $conn->query("
      SELECT COUNT(*) as c 
      FROM jns_perawatan_lab j
      LEFT JOIN mlite_satu_sehat_mapping_lab m ON j.kd_jenis_prw = m.kd_jenis_prw
      WHERE $where_ss
  ");
  $total_ss_rows = $count_res_ss ? (int)$count_res_ss->fetch_assoc()['c'] : 0;
  $total_pages_ss = max(1, ceil($total_ss_rows / $limit_ss));
  $offset_ss = ($page_ss - 1) * $limit_ss;

  $res_ss_list = $conn->query("
      SELECT j.kd_jenis_prw, j.nm_perawatan, j.kategori, j.status,
             m.code as loinc_code, m.display as loinc_display, m.system as loinc_system,
             m.sampel_code, m.sampel_display, m.sampel_system
      FROM jns_perawatan_lab j
      LEFT JOIN mlite_satu_sehat_mapping_lab m ON j.kd_jenis_prw = m.kd_jenis_prw
      WHERE $where_ss
      ORDER BY (m.code IS NOT NULL AND m.code != '') DESC, j.nm_perawatan ASC
      LIMIT $limit_ss OFFSET $offset_ss
  ");
  $ss_table_list = [];
  if ($res_ss_list) while ($r = $res_ss_list->fetch_assoc()) $ss_table_list[] = $r;

  $stat_mapped_count = ($conn->query("SELECT COUNT(*) as c FROM mlite_satu_sehat_mapping_lab WHERE code IS NOT NULL AND code != ''")->fetch_assoc()['c'] ?? 0);
  ?>

  <!-- ─── FORM PEMETAAN SATU SEHAT (MATCHING SCREENSHOT) ───── -->
  <div class="card" style="margin-bottom:20px;border-radius:10px;background:#ffffff;box-shadow:0 1px 3px rgba(0,0,0,0.05);border:1px solid #e2e8f0;">
    <div class="card-header" style="background:#f8fafc;padding:14px 20px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
      <div>
        <h3 style="margin:0;font-size:14px;font-weight:800;color:#0f172a;">
          <i class="fas fa-network-wired" style="color:#16a34a;margin-right:6px;"></i> Pemetaan Terminologi Satu Sehat Laboratorium
        </h3>
        <p style="margin:2px 0 0;font-size:12px;color:#64748b;">Standarisasi kode LOINC & SNOMED CT Spesimen untuk Interoperabilitas Satu Sehat (Kemenkes RI)</p>
      </div>
      <div style="font-size:12px;font-weight:700;color:#16a34a;background:#f0fdf4;padding:4px 12px;border-radius:20px;border:1px solid #bbf7d0;">
        <i class="fas fa-check-circle"></i> Terpetakan: <?= number_format($stat_mapped_count) ?> dari <?= number_format($stat_total_pkg) ?> Perawatan Lab
      </div>
    </div>

    <div class="card-body" style="padding:20px;">
      <form method="POST" action="" id="formSatuSehat" style="margin:0;">
        <input type="hidden" name="action_ss" value="save">

        <!-- 1. Pilih Perawatan -->
        <div class="form-group" style="margin-bottom:14px;">
          <label class="form-label" style="font-size:12px;font-weight:700;color:#334155;margin-bottom:6px;display:block;">
            Pilih Perawatan <span class="text-danger">*</span>
          </label>
          <select name="kd_jenis_prw" id="ss_kd_jenis_prw" class="form-control" style="font-size:13px;" required onchange="onSelectSsPerawatan(this.value)">
            <option value="">-- Pilih Perawatan Laboratorium --</option>
            <?php foreach ($all_packages as $idx => $pkg): ?>
              <?php 
              $is_sel = ($current_ss_data && $current_ss_data['kd_jenis_prw'] === $pkg['kd_jenis_prw']) || ($selected_ss_kd === $pkg['kd_jenis_prw']);
              ?>
              <option value="<?= htmlspecialchars($pkg['kd_jenis_prw']) ?>" 
                      data-name="<?= htmlspecialchars($pkg['nm_perawatan']) ?>"
                      data-kat="<?= htmlspecialchars($pkg['kategori']) ?>"
                      <?= $is_sel ? 'selected' : '' ?>>
                [<?= $idx + 1001 ?>] -&gt; -&gt; <?= htmlspecialchars($pkg['kd_jenis_prw']) ?> -&gt; <?= htmlspecialchars($pkg['nm_perawatan']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- 2. Terminologi LOINC Laboratorium -->
        <div class="form-group" style="margin-bottom:14px;">
          <label class="form-label" style="font-size:12px;font-weight:700;color:#334155;margin-bottom:6px;display:block;">
            Terminologi Loinc Laboratorium
          </label>
          <div style="display:flex;gap:8px;">
            <div style="flex:1;position:relative;">
              <input type="text" id="ss_loinc_search" list="loinc_presets" class="form-control" placeholder="Silahkan cari terminologi..." style="font-size:13px;" oninput="onLoincSearchSelect(this.value)">
              <datalist id="loinc_presets">
                <option value="24338-6 | Arterial blood gas panel - Blood | Blood specimen">
                <option value="58410-2 | Complete blood count (CBC) panel - Blood | Blood specimen">
                <option value="718-7 | Hemoglobin [Mass/volume] in Blood | Blood specimen">
                <option value="6690-2 | Leukocytes [#/volume] in Blood by Automated count | Blood specimen">
                <option value="777-3 | Platelets [#/volume] in Blood by Automated count | Blood specimen">
                <option value="789-8 | Erythrocytes [#/volume] in Blood by Automated count | Blood specimen">
                <option value="4544-3 | Hematocrit [Volume Fraction] of Blood | Blood specimen">
                <option value="30341-2 | Erythrocyte sedimentation rate by Westergren method | Blood specimen">
                <option value="24357-6 | Urinalysis panel - Urine | Urine specimen">
                <option value="2339-0 | Glucose [Mass/volume] in Blood | Blood specimen">
                <option value="1558-6 | Fasting glucose [Mass/volume] in Serum or Plasma | Serum specimen">
                <option value="1557-8 | Fasting glucose 2 hours post meal [Mass/volume] | Serum specimen">
                <option value="4548-4 | Hemoglobin A1c/Hemoglobin.total in Blood | Blood specimen">
                <option value="24331-1 | Lipid 1996 panel - Serum or Plasma | Serum specimen">
                <option value="2093-3 | Cholesterol [Mass/volume] in Serum or Plasma | Serum specimen">
                <option value="2571-8 | Triglyceride [Mass/volume] in Serum or Plasma | Serum specimen">
                <option value="2085-9 | Cholesterol in HDL [Mass/volume] in Serum or Plasma | Serum specimen">
                <option value="2089-1 | Cholesterol in LDL [Mass/volume] in Serum or Plasma | Serum specimen">
                <option value="3084-1 | Urate [Mass/volume] in Serum or Plasma | Serum specimen">
                <option value="3094-0 | Urea nitrogen [Mass/volume] in Serum or Plasma | Serum specimen">
                <option value="2160-0 | Creatinine [Mass/volume] in Serum or Plasma | Serum specimen">
                <option value="1920-8 | Aspartate aminotransferase [Enzymatic activity/volume] in Serum | Serum specimen">
                <option value="1742-6 | Alanine aminotransferase [Enzymatic activity/volume] in Serum | Serum specimen">
                <option value="1975-2 | Bilirubin.total [Mass/volume] in Serum or Plasma | Serum specimen">
                <option value="1968-7 | Bilirubin.direct [Mass/volume] in Serum or Plasma | Serum specimen">
                <option value="24326-1 | Electrolytes 1998 panel - Serum or Plasma | Serum specimen">
                <option value="2951-2 | Sodium [Moles/volume] in Serum or Plasma | Serum specimen">
                <option value="2823-3 | Potassium [Moles/volume] in Serum or Plasma | Serum specimen">
                <option value="2075-0 | Chloride [Moles/volume] in Serum or Plasma | Serum specimen">
                <option value="5196-1 | Hepatitis B virus surface Ag [Presence] in Serum or Plasma | Serum specimen">
                <option value="16128-1 | Hepatitis C virus Ab [Presence] in Serum or Plasma | Serum specimen">
                <option value="48345-3 | HIV 1 and 2 Ab [Presence] in Serum, Plasma or Blood | Blood specimen">
                <option value="22557-3 | Salmonella typhi Ab panel - Serum | Serum specimen">
                <option value="69981-9 | Dengue virus NS1 Ag [Presence] in Serum or Plasma | Serum specimen">
                <option value="29548-5 | Dengue virus IgG and IgM [Presence] in Serum or Plasma | Serum specimen">
                <option value="883-9 | ABO and Rh group [Type] in Blood | Blood specimen">
                <option value="2106-3 | Choriogonadotropin (Pregnancy test) in Urine | Urine specimen">
                <option value="10701-1 | Stool examination panel | Stool specimen">
                <option value="94558-4 | SARS-CoV-2 Ag [Presence] in Upper respiratory specimen | Nasopharyngeal swab">
                <option value="11477-7 | Mycobacterium tuberculosis identified in Sputum by Smear | Sputum specimen">
              </datalist>
            </div>
            <button type="button" class="btn btn-success" id="btn_ai_loinc" onclick="generateAiLoinc()" style="white-space:nowrap;background:#16a34a;border-color:#16a34a;color:#fff;font-weight:700;padding:6px 16px;">
              <i class="fas fa-magic"></i> AI Generate LOINC
            </button>
          </div>
        </div>

        <!-- 3. Kode LOINC -->
        <div class="form-group" style="margin-bottom:14px;">
          <label class="form-label" style="font-size:12px;font-weight:700;color:#334155;margin-bottom:6px;display:block;">
            Kode
          </label>
          <input type="text" name="code" id="ss_code" class="form-control" value="<?= htmlspecialchars($current_ss_data['code'] ?? '') ?>" placeholder="24338-6" style="font-size:13px;font-family:monospace;font-weight:700;">
        </div>

        <!-- 4. Nama Pemeriksaan -->
        <div class="form-group" style="margin-bottom:14px;">
          <label class="form-label" style="font-size:12px;font-weight:700;color:#334155;margin-bottom:6px;display:block;">
            Nama Pemeriksaan
          </label>
          <input type="text" name="display" id="ss_display" class="form-control" value="<?= htmlspecialchars($current_ss_data['display'] ?? '') ?>" placeholder="Arterial blood gas panel - Blood" style="font-size:13px;">
        </div>

        <!-- 5. Code System -->
        <div class="form-group" style="margin-bottom:14px;">
          <label class="form-label" style="font-size:12px;font-weight:700;color:#334155;margin-bottom:6px;display:block;">
            Code System
          </label>
          <input type="text" name="system" id="ss_system" class="form-control" value="<?= htmlspecialchars($current_ss_data['system'] ?? 'http://loinc.org') ?>" style="font-size:13px;">
        </div>

        <!-- 6. Sample Code & AI Generate -->
        <div class="form-group" style="margin-bottom:14px;">
          <label class="form-label" style="font-size:12px;font-weight:700;color:#334155;margin-bottom:6px;display:block;">
            Sample Code
          </label>
          <div style="display:flex;gap:8px;">
            <input type="text" name="sampel_code" id="ss_sampel_code" class="form-control" value="<?= htmlspecialchars($current_ss_data['sampel_code'] ?? '119297000') ?>" placeholder="119297000" style="font-size:13px;font-family:monospace;">
            <button type="button" class="btn btn-success" id="btn_ai_sample" onclick="generateAiSample()" style="white-space:nowrap;background:#16a34a;border-color:#16a34a;color:#fff;font-weight:700;padding:6px 16px;">
              <i class="fas fa-magic"></i> AI Generate
            </button>
          </div>
        </div>

        <!-- 7. Sample Display -->
        <div class="form-group" style="margin-bottom:14px;">
          <label class="form-label" style="font-size:12px;font-weight:700;color:#334155;margin-bottom:6px;display:block;">
            Sample Display
          </label>
          <input type="text" name="sampel_display" id="ss_sampel_display" class="form-control" value="<?= htmlspecialchars($current_ss_data['sampel_display'] ?? 'Blood specimen') ?>" placeholder="Blood specimen" style="font-size:13px;">
        </div>

        <!-- 8. Sample System -->
        <div class="form-group" style="margin-bottom:20px;">
          <label class="form-label" style="font-size:12px;font-weight:700;color:#334155;margin-bottom:6px;display:block;">
            Sample System
          </label>
          <input type="text" name="sampel_system" id="ss_sampel_system" class="form-control" value="<?= htmlspecialchars($current_ss_data['sampel_system'] ?? 'http://snomed.info/sct') ?>" style="font-size:13px;">
        </div>

        <!-- Tombol Aksi Form -->
        <div style="display:flex;gap:10px;align-items:center;">
          <button type="submit" class="btn btn-primary" style="background:#16a34a;border:none;font-weight:700;padding:8px 20px;">
            <i class="fas fa-save"></i> Simpan Pemetaan Satu Sehat
          </button>
          <button type="button" class="btn btn-outline" onclick="resetSsForm()" style="font-size:13px;">
            <i class="fas fa-undo"></i> Reset / Bersihkan Form
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- ─── TABEL DAFTAR PEMETAAN SATU SEHAT ──────────────────── -->
  <div class="card" style="margin-bottom:14px;border-radius:10px;background:#ffffff;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
    <div class="card-body" style="padding:14px 18px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
      <div>
        <h3 style="margin:0;font-size:14px;font-weight:800;color:#0f172a;">Daftar Pemetaan Perawatan Laboratorium</h3>
        <p style="margin:2px 0 0;font-size:12px;color:#64748b;">Pilih tindakan untuk melihat atau mengubah pemetaan standar LOINC & SNOMED CT</p>
      </div>
    </div>
    
    <div style="padding:0 18px 14px 18px;">
      <form method="GET" action="" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin:0;">
        <input type="hidden" name="tab" value="satusehat">

        <div style="flex:1;min-width:220px;">
          <input type="text" name="q_ss" class="form-control form-control-sm" placeholder="Cari nama pemeriksaan lab, kode LOINC..." value="<?= htmlspecialchars($search_ss) ?>" style="font-size:12px;">
        </div>

        <div style="width:180px;">
          <select name="status_ss" class="form-control form-control-sm" style="font-size:12px;" onchange="this.form.submit()">
            <option value="">Semua Status Bridging</option>
            <option value="mapped" <?= ($filter_ss === 'mapped') ? 'selected' : '' ?>>Sudah Terpetakan (LOINC)</option>
            <option value="unmapped" <?= ($filter_ss === 'unmapped') ? 'selected' : '' ?>>Belum Terpetakan</option>
          </select>
        </div>

        <div style="width:110px;">
          <select name="limit_ss" class="form-control form-control-sm" style="font-size:12px;" onchange="this.form.submit()">
            <option value="10" <?= ($limit_ss === 10) ? 'selected' : '' ?>>10 / hal</option>
            <option value="20" <?= ($limit_ss === 20) ? 'selected' : '' ?>>20 / hal</option>
            <option value="50" <?= ($limit_ss === 50) ? 'selected' : '' ?>>50 / hal</option>
            <option value="100" <?= ($limit_ss === 100) ? 'selected' : '' ?>>100 / hal</option>
          </select>
        </div>

        <button type="submit" class="btn btn-outline btn-sm" style="font-size:12px;">
          <i class="fas fa-search"></i> Filter
        </button>

        <?php if (!empty($search_ss) || !empty($filter_ss)): ?>
          <a href="?tab=satusehat" class="btn btn-outline btn-sm" style="color:#ef4444;border-color:#fca5a5;font-size:12px;" title="Reset Filter">
            <i class="fas fa-times"></i> Reset
          </a>
        <?php endif; ?>
      </form>
    </div>
  </div>

  <div class="card" style="border-radius:10px;background:#ffffff;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
    <div class="table-responsive">
      <table class="table table-hover" style="margin:0;font-size:12px;">
        <thead style="background:#f8fafc;color:#475569;font-weight:700;border-bottom:1px solid #e2e8f0;">
          <tr>
            <th style="width:240px;">Perawatan Laboratorium</th>
            <th style="width:120px;">Kode LOINC</th>
            <th>Nama Pemeriksaan (LOINC Display)</th>
            <th style="width:140px;">Code System</th>
            <th style="width:180px;">Spesimen SNOMED CT</th>
            <th style="width:100px;text-align:center;">Status</th>
            <th style="width:120px;text-align:center;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($ss_table_list)): ?>
            <tr>
              <td colspan="7" style="text-align:center;padding:30px;color:#94a3b8;">
                Tidak ada data perawatan laboratorium yang sesuai filter.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($ss_table_list as $row_ss): ?>
              <tr style="border-bottom:1px solid #f1f5f9;<?= ($selected_ss_kd === $row_ss['kd_jenis_prw']) ? 'background:#f0fdf4;' : '' ?>">
                <td>
                  <span style="font-family:monospace;font-weight:700;color:#0284c7;font-size:11px;"><?= htmlspecialchars($row_ss['kd_jenis_prw']) ?></span>
                  <div style="font-weight:700;color:#0f172a;font-size:12.5px;margin-top:2px;"><?= htmlspecialchars($row_ss['nm_perawatan']) ?></div>
                </td>
                <td>
                  <?php if (!empty($row_ss['loinc_code'])): ?>
                    <span style="font-family:monospace;font-weight:800;color:#16a34a;background:#f0fdf4;padding:3px 8px;border-radius:4px;border:1px solid #bbf7d0;">
                      <?= htmlspecialchars($row_ss['loinc_code']) ?>
                    </span>
                  <?php else: ?>
                    <span class="badge badge-warning" style="font-size:10px;">Belum Mapped</span>
                  <?php endif; ?>
                </td>
                <td style="font-size:12px;color:#334155;font-weight:600;">
                  <?= htmlspecialchars($row_ss['loinc_display'] ?: '-') ?>
                </td>
                <td style="font-family:monospace;font-size:11px;color:#64748b;">
                  <?= htmlspecialchars($row_ss['loinc_system'] ?: 'http://loinc.org') ?>
                </td>
                <td style="font-size:11.5px;color:#475569;">
                  <?php if (!empty($row_ss['sampel_code'])): ?>
                    <div><span style="font-family:monospace;font-weight:700;color:#6366f1;"><?= htmlspecialchars($row_ss['sampel_code']) ?></span></div>
                    <div style="font-size:11px;color:#64748b;"><?= htmlspecialchars($row_ss['sampel_display'] ?: 'Blood specimen') ?></div>
                  <?php else: ?>
                    <span style="color:#94a3b8;">-</span>
                  <?php endif; ?>
                </td>
                <td style="text-align:center;">
                  <?php if (!empty($row_ss['loinc_code'])): ?>
                    <span class="badge badge-success" style="font-size:10px;background:#dcfce7;color:#15803d;border:1px solid #bbf7d0;">Terpetakan</span>
                  <?php else: ?>
                    <span class="badge badge-warning" style="font-size:10px;">Belum</span>
                  <?php endif; ?>
                </td>
                <td style="text-align:center;">
                  <div style="display:flex;gap:4px;justify-content:center;">
                    <button type="button" class="btn btn-outline btn-sm" onclick="loadSsToForm(<?= htmlspecialchars(json_encode($row_ss), ENT_QUOTES, 'UTF-8') ?>)" style="padding:3px 8px;font-size:11px;color:#16a34a;border-color:#bbf7d0;" title="Pilih & Edit Pemetaan">
                      <i class="fas fa-edit"></i> Edit
                    </button>
                    <?php if (!empty($row_ss['loinc_code'])): ?>
                      <form method="POST" action="" onsubmit="return confirm('Hapus pemetaan Satu Sehat untuk perawatan ini?');" style="margin:0;display:inline;">
                        <input type="hidden" name="action_ss" value="delete">
                        <input type="hidden" name="kd_jenis_prw" value="<?= htmlspecialchars($row_ss['kd_jenis_prw']) ?>">
                        <button type="submit" class="btn btn-outline btn-sm" style="padding:3px 8px;font-size:11px;color:#ef4444;border-color:#fca5a5;" title="Hapus Mapping">
                          <i class="fas fa-trash"></i>
                        </button>
                      </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Paginasi Tab 4 -->
    <?= render_lab_pagination($page_ss, $total_pages_ss, $total_ss_rows, $limit_ss, [
        'tab'       => 'satusehat',
        'q_ss'      => $search_ss,
        'status_ss' => $filter_ss,
        'limit_ss'  => $limit_ss,
        'page_key'  => 'page_ss'
    ]) ?>
  </div>

<?php endif; ?>

<!-- ─── MODAL 1: FORM PAKET LAB ───────────────────────────── -->
<div id="modalPaket" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(15,23,42,0.6);z-index:9999;align-items:center;justify-content:center;padding:16px;">
  <div style="background:#ffffff;border-radius:12px;width:100%;max-width:580px;max-height:92vh;display:flex;flex-direction:column;box-shadow:0 10px 25px rgba(0,0,0,0.2);">
    
    <div style="padding:14px 20px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;">
      <h3 style="margin:0;font-size:15px;font-weight:800;color:#0f172a;" id="modalPaketTitle">Tambah Paket Pemeriksaan Lab</h3>
      <button type="button" onclick="closeModalPaket()" style="background:none;border:none;font-size:20px;color:#94a3b8;cursor:pointer;">&times;</button>
    </div>

    <form method="POST" action="" style="margin:0;">
      <input type="hidden" name="action_pkg" value="save">
      <input type="hidden" name="is_edit" id="pkg_is_edit" value="0">

      <div style="padding:16px 20px;display:flex;flex-direction:column;gap:12px;overflow-y:auto;max-height:calc(92vh - 130px);">
        
        <div class="form-row col-3">
          <div class="form-group" style="margin:0;">
            <label class="form-label" style="font-size:11.5px;font-weight:700;">Kode Lab <span class="text-danger">*</span></label>
            <input type="text" name="kd_jenis_prw" id="pkg_kd" class="form-control" required placeholder="cth: LAB015" style="font-size:12px;font-family:monospace;font-weight:700;">
          </div>
          <div class="form-group" style="margin:0;">
            <label class="form-label" style="font-size:11.5px;font-weight:700;">Kategori</label>
            <select name="kategori" id="pkg_kat" class="form-control" style="font-size:12px;">
              <option value="PK">PK (Patologi Klinik)</option>
              <option value="PA">PA (Patologi Anatomi)</option>
              <option value="MB">MB (Mikrobiologi)</option>
            </select>
          </div>
          <div class="form-group" style="margin:0;">
            <label class="form-label" style="font-size:11.5px;font-weight:700;">Status</label>
            <select name="status" id="pkg_status" class="form-control" style="font-size:12px;">
              <option value="1">Aktif</option>
              <option value="0">Non-Aktif</option>
            </select>
          </div>
        </div>

        <div class="form-row col-2">
          <div class="form-group" style="margin:0;flex:2;">
            <label class="form-label" style="font-size:11.5px;font-weight:700;">Nama Pemeriksaan Lab <span class="text-danger">*</span></label>
            <input type="text" name="nm_perawatan" id="pkg_nm" class="form-control" required placeholder="cth: Profil Lipid Lengkap" style="font-size:12px;font-weight:700;">
          </div>
          <div class="form-group" style="margin:0;flex:1;">
            <label class="form-label" style="font-size:11.5px;font-weight:700;">Kelas Layanan</label>
            <input type="text" name="kelas" id="pkg_kelas" class="form-control" value="Rawat Jalan" style="font-size:12px;">
          </div>
        </div>

        <div style="font-size:11.5px;font-weight:700;color:#64748b;margin-top:6px;border-top:1px solid #f1f5f9;padding-top:10px;">
          Rincian Komponen Tarif (Rupiah):
        </div>
        
        <div class="form-row col-2">
          <div class="form-group" style="margin:0;">
            <label class="form-label" style="font-size:11px;">Jasa RS / Sarana Klinik</label>
            <input type="number" name="bagian_rs" id="pkg_rs" class="form-control form-control-sm" value="0" min="0" onkeyup="calcPkgTotal()" onchange="calcPkgTotal()">
          </div>
          <div class="form-group" style="margin:0;">
            <label class="form-label" style="font-size:11px;">BHP & Reagen Laborat</label>
            <input type="number" name="bhp" id="pkg_bhp" class="form-control form-control-sm" value="0" min="0" onkeyup="calcPkgTotal()" onchange="calcPkgTotal()">
          </div>
        </div>

        <div class="form-row col-2">
          <div class="form-group" style="margin:0;">
            <label class="form-label" style="font-size:11px;">Jasa Dokter (PJ Lab)</label>
            <input type="number" name="tarif_tindakan_dokter" id="pkg_dr" class="form-control form-control-sm" value="0" min="0" onkeyup="calcPkgTotal()" onchange="calcPkgTotal()">
          </div>
          <div class="form-group" style="margin:0;">
            <label class="form-label" style="font-size:11px;">Jasa Petugas / Analis</label>
            <input type="number" name="tarif_tindakan_petugas" id="pkg_pr" class="form-control form-control-sm" value="0" min="0" onkeyup="calcPkgTotal()" onchange="calcPkgTotal()">
          </div>
        </div>

        <div class="form-row col-3">
          <div class="form-group" style="margin:0;">
            <label class="form-label" style="font-size:11px;">Tarif Perujuk</label>
            <input type="number" name="tarif_perujuk" id="pkg_perujuk" class="form-control form-control-sm" value="0" min="0" onkeyup="calcPkgTotal()" onchange="calcPkgTotal()">
          </div>
          <div class="form-group" style="margin:0;">
            <label class="form-label" style="font-size:11px;">KSO Alat</label>
            <input type="number" name="kso" id="pkg_kso" class="form-control form-control-sm" value="0" min="0" onkeyup="calcPkgTotal()" onchange="calcPkgTotal()">
          </div>
          <div class="form-group" style="margin:0;">
            <label class="form-label" style="font-size:11px;">Manajemen / Adm</label>
            <input type="number" name="menejemen" id="pkg_menejemen" class="form-control form-control-sm" value="0" min="0" onkeyup="calcPkgTotal()" onchange="calcPkgTotal()">
          </div>
        </div>

        <div style="background:#f0fdf4;border:1px solid #bbf7d0;padding:10px 14px;border-radius:8px;display:flex;justify-content:space-between;align-items:center;margin-top:4px;">
          <div>
            <span style="font-size:12px;font-weight:700;color:#166534;display:block;">Total Tarif Pasien:</span>
            <span style="font-size:10.5px;color:#15803d;">Kalkulasi otomatis dari seluruh komponen tarif</span>
          </div>
          <span id="pkg_total_label" style="font-size:16px;font-weight:800;color:#16a34a;">Rp 0</span>
        </div>

      </div>

      <div style="padding:12px 20px;border-top:1px solid #e2e8f0;display:flex;justify-content:flex-end;gap:8px;">
        <button type="button" class="btn btn-outline btn-sm" onclick="closeModalPaket()">Batal</button>
        <button type="submit" class="btn btn-primary btn-sm" style="background:linear-gradient(135deg,#0284c7,#0369a1);border:none;font-weight:700;">Simpan Paket</button>
      </div>

    </form>
  </div>
</div>

<!-- ─── MODAL 2: DUPLIKASI / KLON PAKET LAB ────────────────── -->
<div id="modalClone" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(15,23,42,0.6);z-index:9999;align-items:center;justify-content:center;padding:16px;">
  <div style="background:#ffffff;border-radius:12px;width:100%;max-width:500px;display:flex;flex-direction:column;box-shadow:0 10px 25px rgba(0,0,0,0.2);">
    
    <div style="padding:14px 20px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;">
      <h3 style="margin:0;font-size:15px;font-weight:800;color:#0f172a;">Duplikasi / Klon Paket Pemeriksaan</h3>
      <button type="button" onclick="closeModalClone()" style="background:none;border:none;font-size:20px;color:#94a3b8;cursor:pointer;">&times;</button>
    </div>

    <form method="POST" action="" style="margin:0;">
      <input type="hidden" name="action_pkg" value="clone">
      <input type="hidden" name="source_kd" id="clone_src_kd" value="">

      <div style="padding:16px 20px;display:flex;flex-direction:column;gap:12px;">
        <div style="background:#f8fafc;border:1px solid #e2e8f0;padding:10px 14px;border-radius:8px;font-size:12px;">
          <div>Paket Sumber: <strong id="clone_src_label">-</strong></div>
          <div style="color:#64748b;font-size:11px;margin-top:2px;">Seluruh komponen tarif dan parameter tindakan akan disalin ke paket baru.</div>
        </div>

        <div class="form-group" style="margin:0;">
          <label class="form-label" style="font-size:11.5px;font-weight:700;">Kode Paket Baru <span class="text-danger">*</span></label>
          <input type="text" name="new_kd" id="clone_new_kd" class="form-control" required placeholder="cth: LAB020" style="font-size:12px;font-family:monospace;font-weight:700;">
        </div>

        <div class="form-group" style="margin:0;">
          <label class="form-label" style="font-size:11.5px;font-weight:700;">Nama Paket Baru <span class="text-danger">*</span></label>
          <input type="text" name="new_nm" id="clone_new_nm" class="form-control" required placeholder="cth: Paket Checkup Basic" style="font-size:12px;">
        </div>
      </div>

      <div style="padding:12px 20px;border-top:1px solid #e2e8f0;display:flex;justify-content:flex-end;gap:8px;">
        <button type="button" class="btn btn-outline btn-sm" onclick="closeModalClone()">Batal</button>
        <button type="submit" class="btn btn-primary btn-sm" style="background:linear-gradient(135deg,#d97706,#b45309);border:none;font-weight:700;">Duplikasi Sekarang</button>
      </div>

    </form>
  </div>
</div>

<!-- ─── MODAL 3: FORM TEMPLATE PARAMETER ───────────────────── -->
<div id="modalTemplate" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(15,23,42,0.6);z-index:9999;align-items:center;justify-content:center;padding:16px;">
  <div style="background:#ffffff;border-radius:12px;width:100%;max-width:580px;max-height:92vh;display:flex;flex-direction:column;box-shadow:0 10px 25px rgba(0,0,0,0.2);">
    
    <div style="padding:14px 20px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;">
      <h3 style="margin:0;font-size:15px;font-weight:800;color:#0f172a;" id="modalTemplateTitle">Tambah Parameter Lab</h3>
      <button type="button" onclick="closeModalTemplate()" style="background:none;border:none;font-size:20px;color:#94a3b8;cursor:pointer;">&times;</button>
    </div>

    <form method="POST" action="" style="margin:0;">
      <input type="hidden" name="action_tpl" value="save">
      <input type="hidden" name="is_edit" id="tpl_is_edit" value="0">
      <input type="hidden" name="id_template" id="tpl_id" value="0">

      <div style="padding:16px 20px;display:flex;flex-direction:column;gap:12px;overflow-y:auto;max-height:calc(92vh - 130px);">
        
        <div class="form-group" style="margin:0;">
          <label class="form-label" style="font-size:11.5px;font-weight:700;">Paket Pemeriksaan <span class="text-danger">*</span></label>
          <select name="kd_jenis_prw" id="tpl_kd_pkg" class="form-control" required style="font-size:12px;font-weight:700;">
            <?php foreach ($all_packages as $pkg): ?>
              <option value="<?= htmlspecialchars($pkg['kd_jenis_prw']) ?>" <?= ($selected_pkg === $pkg['kd_jenis_prw']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($pkg['kd_jenis_prw']) ?> — <?= htmlspecialchars($pkg['nm_perawatan']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-row col-2">
          <div class="form-group" style="margin:0;flex:2;">
            <label class="form-label" style="font-size:11.5px;font-weight:700;">Nama Parameter / Detail Tindakan <span class="text-danger">*</span></label>
            <input type="text" name="Pemeriksaan" id="tpl_pem" class="form-control" required placeholder="cth: Hemoglobin (Hb)" style="font-size:12px;font-weight:700;">
          </div>
          <div class="form-group" style="margin:0;flex:1;">
            <label class="form-label" style="font-size:11.5px;font-weight:700;">Satuan Unit</label>
            <input type="text" name="satuan" id="tpl_sat" class="form-control" placeholder="cth: g/dL, mg/dL" style="font-size:12px;">
          </div>
        </div>

        <div class="form-row col-2">
          <div class="form-group" style="margin:0;">
            <label class="form-label" style="font-size:11.5px;font-weight:700;">Nomor Urutan Tampilan</label>
            <input type="number" name="urut" id="tpl_urut" class="form-control form-control-sm" value="1" min="1" style="font-size:12px;max-width:120px;">
          </div>
          <div class="form-group" style="margin:0;">
            <label class="form-label" style="font-size:11.5px;font-weight:700;">Biaya Khusus Item (Opsional)</label>
            <input type="number" name="biaya_item" id="tpl_biaya_item" class="form-control form-control-sm" value="0" min="0" style="font-size:12px;">
          </div>
        </div>

        <div style="font-size:11.5px;font-weight:700;color:#64748b;margin-top:6px;border-top:1px solid #f1f5f9;padding-top:10px;">
          Nilai Rujukan / Rentang Normal (4 Kategori Pasien):
        </div>

        <div class="form-row col-2">
          <div class="form-group" style="margin:0;">
            <label class="form-label" style="font-size:11px;">Laki-laki Dewasa (&ge; 12 thn)</label>
            <input type="text" name="nilai_rujukan_ld" id="tpl_ld" class="form-control form-control-sm" placeholder="cth: 13.0 - 17.5">
          </div>
          <div class="form-group" style="margin:0;">
            <label class="form-label" style="font-size:11px;">Laki-laki Anak (&lt; 12 thn)</label>
            <input type="text" name="nilai_rujukan_la" id="tpl_la" class="form-control form-control-sm" placeholder="cth: 11.5 - 15.5">
          </div>
        </div>

        <div class="form-row col-2">
          <div class="form-group" style="margin:0;">
            <label class="form-label" style="font-size:11px;">Perempuan Dewasa (&ge; 12 thn)</label>
            <input type="text" name="nilai_rujukan_pd" id="tpl_pd" class="form-control form-control-sm" placeholder="cth: 12.0 - 16.0">
          </div>
          <div class="form-group" style="margin:0;">
            <label class="form-label" style="font-size:11px;">Perempuan Anak (&lt; 12 thn)</label>
            <input type="text" name="nilai_rujukan_pa" id="tpl_pa" class="form-control form-control-sm" placeholder="cth: 11.5 - 15.5">
          </div>
        </div>

      </div>

      <div style="padding:12px 20px;border-top:1px solid #e2e8f0;display:flex;justify-content:flex-end;gap:8px;">
        <button type="button" class="btn btn-outline btn-sm" onclick="closeModalTemplate()">Batal</button>
        <button type="submit" class="btn btn-primary btn-sm" style="background:linear-gradient(135deg,#0284c7,#0369a1);border:none;font-weight:700;">Simpan Parameter</button>
      </div>

    </form>
  </div>
</div>

<!-- ─── MODAL 4: 1-CLICK PRESET GENERATOR ──────────────────── -->
<div id="modalPreset" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(15,23,42,0.6);z-index:9999;align-items:center;justify-content:center;padding:16px;">
  <div style="background:#ffffff;border-radius:12px;width:100%;max-width:550px;display:flex;flex-direction:column;box-shadow:0 10px 25px rgba(0,0,0,0.2);">
    
    <div style="padding:14px 20px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;">
      <h3 style="margin:0;font-size:15px;font-weight:800;color:#0f172a;">1-Click Template Preset Standar Medis</h3>
      <button type="button" onclick="closeModalPreset()" style="background:none;border:none;font-size:20px;color:#94a3b8;cursor:pointer;">&times;</button>
    </div>

    <div style="padding:16px 20px;display:flex;flex-direction:column;gap:12px;">
      <div style="font-size:12px;color:#475569;">
        Pilih panel standar medis laboratorium klinis untuk diterapkan secara instan ke paket <strong><?= htmlspecialchars($current_pkg_info['nm_perawatan'] ?? '') ?></strong> (<?= htmlspecialchars($selected_pkg) ?>).
      </div>

      <div class="form-group" style="margin:0;">
        <label class="form-label" style="font-size:11.5px;font-weight:700;">Pilih Standar Panel Medis:</label>
        <select id="preset_select" class="form-control" style="font-size:12.5px;font-weight:700;">
          <option value="darah_lengkap">🩸 Darah Lengkap / Hematologi (15 Parameter: Hb, WBC, RBC, Ht, PLT, LED, Diff Count)</option>
          <option value="profil_lipid">🧪 Profil Lipid (4 Parameter: Kolesterol Total, Trigliserida, HDL, LDL)</option>
          <option value="fungsi_ginjal">💧 Fungsi Ginjal (4 Parameter: Ureum, Kreatinin, Asam Urat, eGFR)</option>
          <option value="fungsi_hati">🫀 Fungsi Hati (8 Parameter: SGOT, SGPT, Bilirubin Total/Direk, Albumin, Globulin)</option>
          <option value="glukosa">🍬 Panel Glukosa / Diabetes (4 Parameter: GDS, GDP, GD2PP, HbA1c)</option>
          <option value="urin_lengkap">🧫 Urin Lengkap (16 Parameter: Makroskopis, Strip Kimia, Sedimen)</option>
          <option value="imunoserologi">🔬 Imunoserologi & Rapid (6 Parameter: Widal O/H, HBsAg, HIV, NS1, Gol. Darah)</option>
        </select>
      </div>

      <div class="form-group" style="margin:0;">
        <label class="form-label" style="font-size:11.5px;font-weight:700;">Metode Penerapan:</label>
        <div style="display:flex;gap:16px;margin-top:4px;">
          <label style="font-size:12px;display:flex;align-items:center;gap:6px;cursor:pointer;">
            <input type="radio" name="preset_mode" value="append" checked> Tambahkan ke item yang ada (Append)
          </label>
          <label style="font-size:12px;display:flex;align-items:center;gap:6px;cursor:pointer;color:#ef4444;">
            <input type="radio" name="preset_mode" value="replace"> Timpa & Hapus item lama (Replace)
          </label>
        </div>
      </div>
    </div>

    <div style="padding:12px 20px;border-top:1px solid #e2e8f0;display:flex;justify-content:flex-end;gap:8px;">
      <button type="button" class="btn btn-outline btn-sm" onclick="closeModalPreset()">Batal</button>
      <button type="submit" class="btn btn-primary btn-sm" onclick="applyPreset()" style="background:linear-gradient(135deg,#0284c7,#0369a1);border:none;font-weight:700;">
        <i class="fas fa-bolt"></i> Terapkan Preset
      </button>
    </div>

  </div>
</div>

<!-- ─── MODAL 5: PEMETAAN SATU SEHAT (LOINC) ────────────────── -->
<div id="modalSatuSehat" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(15,23,42,0.6);z-index:9999;align-items:center;justify-content:center;padding:16px;">
  <div style="background:#ffffff;border-radius:12px;width:100%;max-width:540px;display:flex;flex-direction:column;box-shadow:0 10px 25px rgba(0,0,0,0.2);">
    
    <div style="padding:14px 20px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;">
      <h3 style="margin:0;font-size:15px;font-weight:800;color:#0f172a;">Pemetaan Satu Sehat (LOINC Code)</h3>
      <button type="button" onclick="closeModalSatuSehat()" style="background:none;border:none;font-size:20px;color:#94a3b8;cursor:pointer;">&times;</button>
    </div>

    <div style="padding:16px 20px;display:flex;flex-direction:column;gap:12px;">
      <div style="background:#f3e8ff;border:1px solid #e9d5ff;padding:10px 14px;border-radius:8px;font-size:12px;">
        <div>Parameter Lab: <strong id="ss_param_label" style="color:#6b21a8;">-</strong></div>
        <div style="color:#7e22ce;font-size:11px;margin-top:2px;">Standarisasi terminologi FHIR Observation Diagnostic Report.</div>
      </div>

      <input type="hidden" id="ss_id_template" value="0">
      <input type="hidden" id="ss_kd_pkg" value="">

      <div class="form-group" style="margin:0;">
        <label class="form-label" style="font-size:11.5px;font-weight:700;">Kode LOINC <span class="text-danger">*</span></label>
        <input type="text" id="ss_code" class="form-control form-control-sm" placeholder="cth: 718-7" style="font-family:monospace;font-weight:700;">
      </div>

      <div class="form-group" style="margin:0;">
        <label class="form-label" style="font-size:11.5px;font-weight:700;">Nama Display LOINC</label>
        <input type="text" id="ss_display" class="form-control form-control-sm" placeholder="cth: Hemoglobin [Mass/volume] in Blood">
      </div>

      <div class="form-row col-2">
        <div class="form-group" style="margin:0;">
          <label class="form-label" style="font-size:11px;font-weight:700;">Kode Spesimen (SNOMED)</label>
          <input type="text" id="ss_sampel_code" class="form-control form-control-sm" value="119297000" placeholder="119297000">
        </div>
        <div class="form-group" style="margin:0;">
          <label class="form-label" style="font-size:11px;font-weight:700;">Display Spesimen</label>
          <input type="text" id="ss_sampel_disp" class="form-control form-control-sm" value="Blood specimen" placeholder="Blood specimen / Urine specimen">
        </div>
      </div>

      <div style="font-size:11px;color:#64748b;">
        * Kosongkan Kode LOINC jika ingin menghapus pemetaan Satu Sehat untuk item ini.
      </div>
    </div>

    <div style="padding:12px 20px;border-top:1px solid #e2e8f0;display:flex;justify-content:flex-end;gap:8px;">
      <button type="button" class="btn btn-outline btn-sm" onclick="closeModalSatuSehat()">Batal</button>
      <button type="submit" class="btn btn-primary btn-sm" onclick="saveSatuSehatMapping()" style="background:linear-gradient(135deg,#7c3aed,#6d28d9);border:none;font-weight:700;">
        <i class="fas fa-save"></i> Simpan Pemetaan
      </button>
    </div>

  </div>
</div>

<script>
// ─── Modal Paket Handler ──────────────────────────────────────
function openModalPaket(mode) {
  document.getElementById('modalPaket').style.display = 'flex';
  if (mode === 'add') {
    document.getElementById('modalPaketTitle').innerText = 'Tambah Paket Pemeriksaan Lab';
    document.getElementById('pkg_is_edit').value = '0';
    document.getElementById('pkg_kd').readOnly = false;
    document.getElementById('pkg_kd').value = '';
    document.getElementById('pkg_nm').value = '';
    document.getElementById('pkg_kat').value = 'PK';
    document.getElementById('pkg_status').value = '1';
    document.getElementById('pkg_kelas').value = 'Rawat Jalan';
    document.getElementById('pkg_rs').value = '0';
    document.getElementById('pkg_bhp').value = '0';
    document.getElementById('pkg_dr').value = '0';
    document.getElementById('pkg_pr').value = '0';
    document.getElementById('pkg_perujuk').value = '0';
    document.getElementById('pkg_kso').value = '0';
    document.getElementById('pkg_menejemen').value = '0';
    calcPkgTotal();
  }
}

function editPaket(p) {
  if (typeof p === 'string') {
    try { p = JSON.parse(p); } catch(e) { console.error('Parse error:', e); }
  }
  openModalPaket('edit');
  document.getElementById('modalPaketTitle').innerText = 'Edit Paket Pemeriksaan Lab';
  document.getElementById('pkg_is_edit').value = '1';
  document.getElementById('pkg_kd').value = p.kd_jenis_prw || '';
  document.getElementById('pkg_kd').readOnly = true;
  document.getElementById('pkg_nm').value = p.nm_perawatan || '';
  document.getElementById('pkg_kat').value = p.kategori || 'PK';
  document.getElementById('pkg_status').value = (p.status !== undefined && p.status !== null) ? p.status : '1';
  document.getElementById('pkg_kelas').value = p.kelas || 'Rawat Jalan';
  document.getElementById('pkg_rs').value = parseFloat(p.bagian_rs || 0);
  document.getElementById('pkg_bhp').value = parseFloat(p.bhp || 0);
  document.getElementById('pkg_dr').value = parseFloat(p.tarif_tindakan_dokter || 0);
  document.getElementById('pkg_pr').value = parseFloat(p.tarif_tindakan_petugas || 0);
  document.getElementById('pkg_perujuk').value = parseFloat(p.tarif_perujuk || 0);
  document.getElementById('pkg_kso').value = parseFloat(p.kso || 0);
  document.getElementById('pkg_menejemen').value = parseFloat(p.menejemen || 0);
  calcPkgTotal();
}

function closeModalPaket() {
  document.getElementById('modalPaket').style.display = 'none';
}

function calcPkgTotal() {
  const rs = parseFloat(document.getElementById('pkg_rs').value || 0);
  const bhp = parseFloat(document.getElementById('pkg_bhp').value || 0);
  const dr = parseFloat(document.getElementById('pkg_dr').value || 0);
  const pr = parseFloat(document.getElementById('pkg_pr').value || 0);
  const perujuk = parseFloat(document.getElementById('pkg_perujuk').value || 0);
  const kso = parseFloat(document.getElementById('pkg_kso').value || 0);
  const menejemen = parseFloat(document.getElementById('pkg_menejemen').value || 0);
  const tot = rs + bhp + dr + pr + perujuk + kso + menejemen;
  document.getElementById('pkg_total_label').innerText = 'Rp ' + tot.toLocaleString('id-ID');
}

// ─── Modal Clone Handler ──────────────────────────────────────
function openCloneModal(p) {
  if (typeof p === 'string') {
    try { p = JSON.parse(p); } catch(e) { console.error('Parse error:', e); }
  }
  document.getElementById('modalClone').style.display = 'flex';
  document.getElementById('clone_src_kd').value = p.kd_jenis_prw || '';
  document.getElementById('clone_src_label').innerText = (p.kd_jenis_prw || '') + ' - ' + (p.nm_perawatan || '');
  document.getElementById('clone_new_kd').value = (p.kd_jenis_prw || '') + '_COPY';
  document.getElementById('clone_new_nm').value = (p.nm_perawatan || '') + ' (Salinan)';
}

function closeModalClone() {
  document.getElementById('modalClone').style.display = 'none';
}

// ─── Modal Template Parameter Handler ─────────────────────────
function openModalTemplate(mode) {
  document.getElementById('modalTemplate').style.display = 'flex';
  if (mode === 'add') {
    document.getElementById('modalTemplateTitle').innerText = 'Tambah Parameter Item Lab';
    document.getElementById('tpl_is_edit').value = '0';
    document.getElementById('tpl_id').value = '0';
    document.getElementById('tpl_kd_pkg').value = '<?= $selected_pkg ?>';
    document.getElementById('tpl_pem').value = '';
    document.getElementById('tpl_sat').value = '';
    document.getElementById('tpl_urut').value = '<?= count($templates_list) + 1 ?>';
    document.getElementById('tpl_biaya_item').value = '0';
    document.getElementById('tpl_ld').value = '';
    document.getElementById('tpl_la').value = '';
    document.getElementById('tpl_pd').value = '';
    document.getElementById('tpl_pa').value = '';
  }
}

function editTemplate(t) {
  if (typeof t === 'string') {
    try { t = JSON.parse(t); } catch(e) { console.error('Parse error:', e); }
  }
  openModalTemplate('edit');
  document.getElementById('modalTemplateTitle').innerText = 'Edit Parameter Item Lab';
  document.getElementById('tpl_is_edit').value = '1';
  document.getElementById('tpl_id').value = t.id_template || '0';
  document.getElementById('tpl_kd_pkg').value = t.kd_jenis_prw || '<?= $selected_pkg ?>';
  document.getElementById('tpl_pem').value = t.Pemeriksaan || '';
  document.getElementById('tpl_sat').value = t.satuan || '';
  document.getElementById('tpl_urut').value = t.urut || '1';
  document.getElementById('tpl_biaya_item').value = parseFloat(t.biaya_item || 0);
  document.getElementById('tpl_ld').value = t.nilai_rujukan_ld || '';
  document.getElementById('tpl_la').value = t.nilai_rujukan_la || '';
  document.getElementById('tpl_pd').value = t.nilai_rujukan_pd || '';
  document.getElementById('tpl_pa').value = t.nilai_rujukan_pa || '';
}

function closeModalTemplate() {
  document.getElementById('modalTemplate').style.display = 'none';
}

// ─── Modal Preset Handler ─────────────────────────────────────
function openModalPreset() {
  document.getElementById('modalPreset').style.display = 'flex';
}

function closeModalPreset() {
  document.getElementById('modalPreset').style.display = 'none';
}

function applyPreset() {
  const pkg = '<?= $selected_pkg ?>';
  if (!pkg) {
    alert('Pilih paket terlebih dahulu');
    return;
  }
  const presetType = document.getElementById('preset_select').value;
  const mode = document.querySelector('input[name="preset_mode"]:checked').value;

  if (mode === 'replace') {
    if (!confirm('Perhatian! Mode "Replace" akan menghapus parameter item yang lama pada paket ini. Lanjutkan?')) {
      return;
    }
  }

  const formData = new FormData();
  formData.append('action', 'apply_preset');
  formData.append('kd_jenis_prw', pkg);
  formData.append('preset_type', presetType);
  formData.append('mode', mode);

  fetch('<?= BASE_URL ?>modules/laboratorium/ajax.php', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    if (data.status === 'success') {
      alert(data.message);
      window.location.reload();
    } else {
      alert(data.message || 'Gagal menerapkan preset');
    }
  })
  .catch(err => {
    console.error(err);
    alert('Terjadi kesalahan jaringan');
  });
}

// ─── Satu Sehat Form & AI Generate Handlers ─────────────────
const CLINICAL_LOINC_KB = [
  { match: ['agd', 'lactat', 'analisa gas darah', 'blood gas', 'fio2'], code: '24338-6', display: 'Arterial blood gas panel - Blood', sample_code: '119297000', sample_display: 'Blood specimen' },
  { match: ['darah lengkap', 'cbc', 'hematologi lengkap', 'dl'], code: '58410-2', display: 'Complete blood count (CBC) panel - Blood', sample_code: '119297000', sample_display: 'Blood specimen' },
  { match: ['darah rutin', 'hematologi rutin'], code: '58410-2', display: 'Complete blood count (CBC) panel - Blood', sample_code: '119297000', sample_display: 'Blood specimen' },
  { match: ['hemoglobin', 'hb'], code: '718-7', display: 'Hemoglobin [Mass/volume] in Blood', sample_code: '119297000', sample_display: 'Blood specimen' },
  { match: ['leukosit', 'wbc', 'leukocyte'], code: '6690-2', display: 'Leukocytes [#/volume] in Blood by Automated count', sample_code: '119297000', sample_display: 'Blood specimen' },
  { match: ['trombosit', 'plt', 'platelet'], code: '777-3', display: 'Platelets [#/volume] in Blood by Automated count', sample_code: '119297000', sample_display: 'Blood specimen' },
  { match: ['eritrosit', 'rbc'], code: '789-8', display: 'Erythrocytes [#/volume] in Blood by Automated count', sample_code: '119297000', sample_display: 'Blood specimen' },
  { match: ['hematokrit', 'ht', 'hct'], code: '4544-3', display: 'Hematocrit [Volume Fraction] of Blood', sample_code: '119297000', sample_display: 'Blood specimen' },
  { match: ['led', 'laju endap darah', 'esr'], code: '30341-2', display: 'Erythrocyte sedimentation rate by Westergren method', sample_code: '119297000', sample_display: 'Blood specimen' },
  { match: ['urin lengkap', 'urinalisis', 'urin rutin'], code: '24357-6', display: 'Urinalysis panel - Urine', sample_code: '122575003', sample_display: 'Urine specimen' },
  { match: ['gula darah sewaktu', 'gds', 'glukosa sewaktu'], code: '2339-0', display: 'Glucose [Mass/volume] in Blood', sample_code: '119297000', sample_display: 'Blood specimen' },
  { match: ['gula darah puasa', 'gdp', 'glukosa puasa'], code: '1558-6', display: 'Fasting glucose [Mass/volume] in Serum or Plasma', sample_code: '119364003', sample_display: 'Serum specimen' },
  { match: ['gula darah 2 jam', 'gd2pp', 'glukosa 2 jam'], code: '1557-8', display: 'Fasting glucose 2 hours post meal [Mass/volume]', sample_code: '119364003', sample_display: 'Serum specimen' },
  { match: ['hba1c', 'hemoglobin a1c'], code: '4548-4', display: 'Hemoglobin A1c/Hemoglobin.total in Blood', sample_code: '119297000', sample_display: 'Blood specimen' },
  { match: ['profil lipid', 'panel lipid', 'lipid'], code: '24331-1', display: 'Lipid 1996 panel - Serum or Plasma', sample_code: '119364003', sample_display: 'Serum specimen' },
  { match: ['kolesterol total', 'cholesterol', 'kolesterol'], code: '2093-3', display: 'Cholesterol [Mass/volume] in Serum or Plasma', sample_code: '119364003', sample_display: 'Serum specimen' },
  { match: ['trigliserida', 'triglyceride'], code: '2571-8', display: 'Triglyceride [Mass/volume] in Serum or Plasma', sample_code: '119364003', sample_display: 'Serum specimen' },
  { match: ['hdl', 'kolesterol hdl'], code: '2085-9', display: 'Cholesterol in HDL [Mass/volume] in Serum or Plasma', sample_code: '119364003', sample_display: 'Serum specimen' },
  { match: ['ldl', 'kolesterol ldl'], code: '2089-1', display: 'Cholesterol in LDL [Mass/volume] in Serum or Plasma', sample_code: '119364003', sample_display: 'Serum specimen' },
  { match: ['asam urat', 'uric acid', 'urate'], code: '3084-1', display: 'Urate [Mass/volume] in Serum or Plasma', sample_code: '119364003', sample_display: 'Serum specimen' },
  { match: ['fungsi ginjal', 'renal panel'], code: '24362-6', display: 'Renal function 2000 panel - Serum or Plasma', sample_code: '119364003', sample_display: 'Serum specimen' },
  { match: ['ureum', 'urea'], code: '3094-0', display: 'Urea nitrogen [Mass/volume] in Serum or Plasma', sample_code: '119364003', sample_display: 'Serum specimen' },
  { match: ['kreatinin', 'creatinine'], code: '2160-0', display: 'Creatinine [Mass/volume] in Serum or Plasma', sample_code: '119364003', sample_display: 'Serum specimen' },
  { match: ['fungsi hati', 'lft', 'liver panel'], code: '24325-3', display: 'Hepatic function 2000 panel - Serum or Plasma', sample_code: '119364003', sample_display: 'Serum specimen' },
  { match: ['sgot', 'ast'], code: '1920-8', display: 'Aspartate aminotransferase [Enzymatic activity/volume] in Serum', sample_code: '119364003', sample_display: 'Serum specimen' },
  { match: ['sgpt', 'alt'], code: '1742-6', display: 'Alanine aminotransferase [Enzymatic activity/volume] in Serum', sample_code: '119364003', sample_display: 'Serum specimen' },
  { match: ['bilirubin total'], code: '1975-2', display: 'Bilirubin.total [Mass/volume] in Serum or Plasma', sample_code: '119364003', sample_display: 'Serum specimen' },
  { match: ['bilirubin direk'], code: '1968-7', display: 'Bilirubin.direct [Mass/volume] in Serum or Plasma', sample_code: '119364003', sample_display: 'Serum specimen' },
  { match: ['elektrolit', 'electrolyte'], code: '24326-1', display: 'Electrolytes 1998 panel - Serum or Plasma', sample_code: '119364003', sample_display: 'Serum specimen' },
  { match: ['natrium', 'sodium', 'na'], code: '2951-2', display: 'Sodium [Moles/volume] in Serum or Plasma', sample_code: '119364003', sample_display: 'Serum specimen' },
  { match: ['kalium', 'potassium', 'k'], code: '2823-3', display: 'Potassium [Moles/volume] in Serum or Plasma', sample_code: '119364003', sample_display: 'Serum specimen' },
  { match: ['klorida', 'chloride', 'cl'], code: '2075-0', display: 'Chloride [Moles/volume] in Serum or Plasma', sample_code: '119364003', sample_display: 'Serum specimen' },
  { match: ['hbsag', 'hepatitis b'], code: '5196-1', display: 'Hepatitis B virus surface Ag [Presence] in Serum or Plasma', sample_code: '119364003', sample_display: 'Serum specimen' },
  { match: ['anti hcv', 'hcv', 'hepatitis c'], code: '16128-1', display: 'Hepatitis C virus Ab [Presence] in Serum or Plasma', sample_code: '119364003', sample_display: 'Serum specimen' },
  { match: ['hiv', 'anti hiv'], code: '48345-3', display: 'HIV 1 and 2 Ab [Presence] in Serum, Plasma or Blood', sample_code: '119297000', sample_display: 'Blood specimen' },
  { match: ['widal', 'salmonella', 'typhi'], code: '22557-3', display: 'Salmonella typhi Ab panel - Serum', sample_code: '119364003', sample_display: 'Serum specimen' },
  { match: ['dengue ns1', 'ns1'], code: '69981-9', display: 'Dengue virus NS1 Ag [Presence] in Serum or Plasma', sample_code: '119364003', sample_display: 'Serum specimen' },
  { match: ['dengue igg', 'dengue igm', 'dengue'], code: '29548-5', display: 'Dengue virus IgG and IgM [Presence] in Serum or Plasma', sample_code: '119364003', sample_display: 'Serum specimen' },
  { match: ['golongan darah', 'goldar', 'abo', 'rhesus'], code: '883-9', display: 'ABO and Rh group [Type] in Blood', sample_code: '119297000', sample_display: 'Blood specimen' },
  { match: ['kehamilan', 'plano', 'tes hamil', 'hcg'], code: '2106-3', display: 'Choriogonadotropin (Pregnancy test) in Urine', sample_code: '122575003', sample_display: 'Urine specimen' },
  { match: ['feses', 'tinja', 'stool'], code: '10701-1', display: 'Stool examination panel', sample_code: '119339001', sample_display: 'Stool specimen' },
  { match: ['swab', 'antigen', 'covid', 'sars-cov-2'], code: '94558-4', display: 'SARS-CoV-2 Ag [Presence] in Upper respiratory specimen', sample_code: '258500001', sample_display: 'Nasopharyngeal swab' },
  { match: ['sputum', 'bta', 'tb', 'tbc', 'dahak'], code: '11477-7', display: 'Mycobacterium tuberculosis identified in Sputum by Smear', sample_code: '119334006', sample_display: 'Sputum specimen' }
];

function onSelectSsPerawatan(kdPrw) {
  if (!kdPrw) return;
  const sel = document.getElementById('ss_kd_jenis_prw');
  const opt = sel.options[sel.selectedIndex];
  const name = (opt.getAttribute('data-name') || opt.text || '').toLowerCase();

  // Find match in knowledge base
  for (const item of CLINICAL_LOINC_KB) {
    if (item.match.some(m => name.includes(m))) {
      document.getElementById('ss_code').value = item.code;
      document.getElementById('ss_display').value = item.display;
      document.getElementById('ss_system').value = 'http://loinc.org';
      document.getElementById('ss_sampel_code').value = item.sample_code;
      document.getElementById('ss_sampel_display').value = item.sample_display;
      document.getElementById('ss_sampel_system').value = 'http://snomed.info/sct';
      document.getElementById('ss_loinc_search').value = item.code + ' | ' + item.display;
      return;
    }
  }
}

function onLoincSearchSelect(val) {
  if (!val) return;
  const parts = val.split('|').map(s => s.trim());
  if (parts.length >= 2) {
    document.getElementById('ss_code').value = parts[0];
    document.getElementById('ss_display').value = parts[1];
    document.getElementById('ss_system').value = 'http://loinc.org';
    if (parts.length >= 3) {
      document.getElementById('ss_sampel_display').value = parts[2];
      generateAiSample();
    }
  }
}

function generateAiLoinc() {
  const sel = document.getElementById('ss_kd_jenis_prw');
  if (!sel || !sel.value) {
    alert('Silahkan pilih perawatan laboratorium terlebih dahulu pada dropdown di atas.');
    sel.focus();
    return;
  }

  const opt = sel.options[sel.selectedIndex];
  const name = (opt.getAttribute('data-name') || opt.text || '').toLowerCase();
  let matched = null;

  for (const item of CLINICAL_LOINC_KB) {
    if (item.match.some(m => name.includes(m))) {
      matched = item;
      break;
    }
  }

  if (matched) {
    document.getElementById('ss_code').value = matched.code;
    document.getElementById('ss_display').value = matched.display;
    document.getElementById('ss_system').value = 'http://loinc.org';
    document.getElementById('ss_sampel_code').value = matched.sample_code;
    document.getElementById('ss_sampel_display').value = matched.sample_display;
    document.getElementById('ss_sampel_system').value = 'http://snomed.info/sct';
    document.getElementById('ss_loinc_search').value = matched.code + ' | ' + matched.display;
  } else {
    // Generic fallback
    const rawName = opt.getAttribute('data-name') || 'Pemeriksaan Laboratorium';
    document.getElementById('ss_code').value = '58410-2';
    document.getElementById('ss_display').value = rawName + ' panel - Blood';
    document.getElementById('ss_system').value = 'http://loinc.org';
    generateAiSample();
  }
}

function generateAiSample() {
  const sel = document.getElementById('ss_kd_jenis_prw');
  const opt = sel ? sel.options[sel.selectedIndex] : null;
  const prwName = opt ? (opt.getAttribute('data-name') || '').toLowerCase() : '';
  const dispName = (document.getElementById('ss_display').value || '').toLowerCase();
  const searchName = (prwName + ' ' + dispName);

  if (searchName.includes('urin') || searchName.includes('urine') || searchName.includes('plano') || searchName.includes('kehamilan')) {
    document.getElementById('ss_sampel_code').value = '122575003';
    document.getElementById('ss_sampel_display').value = 'Urine specimen';
  } else if (searchName.includes('feses') || searchName.includes('tinja') || searchName.includes('stool')) {
    document.getElementById('ss_sampel_code').value = '119339001';
    document.getElementById('ss_sampel_display').value = 'Stool specimen';
  } else if (searchName.includes('swab') || searchName.includes('antigen') || searchName.includes('naso')) {
    document.getElementById('ss_sampel_code').value = '258500001';
    document.getElementById('ss_sampel_display').value = 'Nasopharyngeal swab';
  } else if (searchName.includes('sputum') || searchName.includes('dahak') || searchName.includes('bta')) {
    document.getElementById('ss_sampel_code').value = '119334006';
    document.getElementById('ss_sampel_display').value = 'Sputum specimen';
  } else if (searchName.includes('serum') || searchName.includes('puasa') || searchName.includes('lipid') || searchName.includes('kolesterol') || searchName.includes('hati') || searchName.includes('ginjal') || searchName.includes('sgot') || searchName.includes('sgpt') || searchName.includes('ureum') || searchName.includes('kreatinin') || searchName.includes('asam urat') || searchName.includes('elektrolit') || searchName.includes('widal') || searchName.includes('hbsag')) {
    document.getElementById('ss_sampel_code').value = '119364003';
    document.getElementById('ss_sampel_display').value = 'Serum specimen';
  } else {
    document.getElementById('ss_sampel_code').value = '119297000';
    document.getElementById('ss_sampel_display').value = 'Blood specimen';
  }
  document.getElementById('ss_sampel_system').value = 'http://snomed.info/sct';
}

function loadSsToForm(row) {
  const sel = document.getElementById('ss_kd_jenis_prw');
  if (sel) {
    sel.value = row.kd_jenis_prw;
  }
  document.getElementById('ss_code').value = row.loinc_code || '';
  document.getElementById('ss_display').value = row.loinc_display || row.nm_perawatan || '';
  document.getElementById('ss_system').value = row.loinc_system || 'http://loinc.org';
  document.getElementById('ss_sampel_code').value = row.sampel_code || '119297000';
  document.getElementById('ss_sampel_display').value = row.sampel_display || 'Blood specimen';
  document.getElementById('ss_sampel_system').value = row.sampel_system || 'http://snomed.info/sct';
  document.getElementById('ss_loinc_search').value = (row.loinc_code ? (row.loinc_code + ' | ' + (row.loinc_display || '')) : '');

  // Scroll to form smoothly
  const formEl = document.getElementById('formSatuSehat');
  if (formEl) {
    formEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }
}

function resetSsForm() {
  document.getElementById('ss_kd_jenis_prw').value = '';
  document.getElementById('ss_loinc_search').value = '';
  document.getElementById('ss_code').value = '';
  document.getElementById('ss_display').value = '';
  document.getElementById('ss_system').value = 'http://loinc.org';
  document.getElementById('ss_sampel_code').value = '119297000';
  document.getElementById('ss_sampel_display').value = 'Blood specimen';
  document.getElementById('ss_sampel_system').value = 'http://snomed.info/sct';
}

// ─── Reorder Item Ajax ────────────────────────────────────────
function reorderItem(kdPkg, idTpl, direction) {
  const formData = new FormData();
  formData.append('action', 'reorder_template');
  formData.append('kd_jenis_prw', kdPkg);
  formData.append('id_template', idTpl);
  formData.append('direction', direction);

  fetch('<?= BASE_URL ?>modules/laboratorium/ajax.php', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    if (data.status === 'success') {
      window.location.reload();
    } else if (data.status === 'info') {
      // already top or bottom
    } else {
      alert(data.message || 'Gagal mengubah urutan');
    }
  })
  .catch(err => console.error(err));
}

// ─── Toggle Status Paket Lab ──────────────────────────────────
function toggleStatusPkg(kdPkg, newStatus) {
  const formData = new FormData();
  formData.append('action', 'toggle_status_pkg');
  formData.append('kd_jenis_prw', kdPkg);
  formData.append('status', newStatus);

  fetch('<?= BASE_URL ?>modules/laboratorium/ajax.php', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    if (data.status === 'success') {
      window.location.reload();
    } else {
      alert(data.message || 'Gagal mengubah status');
    }
  })
  .catch(err => console.error(err));
}
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
