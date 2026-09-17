<?php
/**
 * SIMKlinik — Antrian & Pendaftaran Pasien
 * Dilengkapi Form Tambah / Edit Pendaftaran Expandable & Collapsible Real-Time
 */

$page_title    = 'Pendaftaran & Antrian';
$active_module = 'pendaftaran';

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_module_access('pendaftaran');

// ─── AJAX Actions ─────────────────────────────────────────────
if (isset($_GET['action']) || isset($_POST['action'])) {
    $action = sanitize($_GET['action'] ?? $_POST['action'] ?? '');

    // 1. Ambil Detail Pendaftaran untuk Edit
    if ($action === 'get_detail') {
        header('Content-Type: application/json');
        $no_rawat = $conn->real_escape_string($_GET['no_rawat'] ?? '');
        $res = $conn->query("
            SELECT r.*, p.nm_pasien, p.jk, p.tgl_lahir, p.no_ktp, p.no_tlp, p.no_peserta,
                   d.nm_dokter, pol.nm_poli, pj.png_jawab as nm_penjab
            FROM reg_periksa r
            JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
            LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
            LEFT JOIN poliklinik pol ON r.kd_poli = pol.kd_poli
            LEFT JOIN penjab pj ON r.kd_pj = pj.kd_pj
            WHERE r.no_rawat = '$no_rawat'
            LIMIT 1
        ");
        if ($res && $res->num_rows > 0) {
            $data = $res->fetch_assoc();
            $data['umur'] = hitung_umur($data['tgl_lahir']);
            echo json_encode(['success' => true, 'data' => $data]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan']);
        }
        exit;
    }

    // 2. Ambil No. Rawat Baru & No. Reg Otomatis
    if ($action === 'get_new_noreg') {
        header('Content-Type: application/json');
        $kd_poli = $conn->real_escape_string($_GET['kd_poli'] ?? '');
        $tgl_reg = $conn->real_escape_string($_GET['tgl'] ?? date('Y-m-d'));

        $r_noreg = $conn->query("SELECT COUNT(*) as cnt FROM reg_periksa WHERE tgl_registrasi = '$tgl_reg' AND kd_poli = '$kd_poli'");
        $cnt     = $r_noreg ? (int)$r_noreg->fetch_assoc()['cnt'] : 0;
        $no_reg  = sprintf('%03d', $cnt + 1);
        $no_rawat = generate_no_rawat();

        echo json_encode(['success' => true, 'no_rawat' => $no_rawat, 'no_reg' => $no_reg, 'jam' => date('H:i:s')]);
        exit;
    }

    // 3. Simpan Pendaftaran (Tambah Baru atau Update Edit)
    if ($action === 'simpan_pendaftaran') {
        ob_start();
        header('Content-Type: application/json');
        $mode        = sanitize($_POST['form_mode'] ?? 'tambah');
        $no_rawat    = $conn->real_escape_string(trim($_POST['no_rawat'] ?? ''));
        $no_rm       = $conn->real_escape_string(trim($_POST['no_rkm_medis'] ?? ''));
        $tgl_reg     = $conn->real_escape_string(trim($_POST['tgl_registrasi'] ?? date('Y-m-d')));
        $jam_reg     = $conn->real_escape_string(trim($_POST['jam_reg'] ?? date('H:i:s')));
        $kd_poli     = $conn->real_escape_string(trim($_POST['kd_poli'] ?? ''));
        $kd_dok      = $conn->real_escape_string(trim($_POST['kd_dokter'] ?? ''));
        $kd_pj       = $conn->real_escape_string(trim($_POST['kd_pj'] ?? ''));
        $no_reg      = $conn->real_escape_string(trim($_POST['no_reg'] ?? '001'));
        $no_peserta  = $conn->real_escape_string(trim($_POST['no_peserta'] ?? ''));

        if (empty($no_rm)) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Silakan pilih pasien terlebih dahulu.']);
            exit;
        }
        if (empty($kd_poli)) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Pilih poliklinik tujuan.']);
            exit;
        }
        if (empty($kd_dok)) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Pilih dokter pemeriksa.']);
            exit;
        }

        if ($mode === 'edit') {
            // Update Existing Pendaftaran
            $sql = "UPDATE reg_periksa SET
                        tgl_registrasi = '$tgl_reg',
                        jam_reg        = '$jam_reg',
                        kd_poli        = '$kd_poli',
                        kd_dokter      = '$kd_dok',
                        kd_pj          = '$kd_pj',
                        no_reg         = '$no_reg'
                    WHERE no_rawat = '$no_rawat'";

            if ($conn->query($sql)) {
                if (!empty($no_peserta) || !empty($kd_pj)) {
                    $conn->query("UPDATE pasien SET no_peserta = '$no_peserta', kd_pj = '$kd_pj' WHERE no_rkm_medis = '$no_rm'");
                }
                ob_end_clean();
                echo json_encode(['success' => true, 'message' => "Pendaftaran No. Rawat $no_rawat berhasil diperbarui."]);
            } else {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Gagal update: ' . $conn->error]);
            }
            exit;
        } else {
            // Tambah Baru
            if (empty($no_rawat)) $no_rawat = generate_no_rawat();

            // Cek duplikasi pasien di poli yang sama hari ini
            $cek = $conn->query("SELECT no_rawat FROM reg_periksa WHERE no_rkm_medis = '$no_rm' AND tgl_registrasi = '$tgl_reg' AND kd_poli = '$kd_poli' AND stts != 'Batal' LIMIT 1");
            if ($cek && $cek->num_rows > 0) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Pasien sudah terdaftar di poliklinik ini pada tanggal tersebut.']);
                exit;
            }

            // Ambil info pendukung pasien
            $p_info = $conn->query("
                SELECT p.nm_pasien, p.no_ktp, p.namakeluarga, p.alamatpj, p.keluarga, p.tgl_lahir, pol.registrasi as biaya_reg
                FROM pasien p
                LEFT JOIN poliklinik pol ON pol.kd_poli = '$kd_poli'
                WHERE p.no_rkm_medis = '$no_rm'
                LIMIT 1
            ")->fetch_assoc();

            $p_nm_pasien= $p_info['nm_pasien'] ?? '';
            $p_no_ktp   = $p_info['no_ktp'] ?? '';
            $p_jawab    = $conn->real_escape_string($p_info['namakeluarga'] ?? '-');
            $almt_pj    = $conn->real_escape_string($p_info['alamatpj'] ?? '-');
            $hubunganpj = $conn->real_escape_string($p_info['keluarga'] ?? '-');
            $biaya_reg  = (float)($p_info['biaya_reg'] ?? 0);
            $umurdaftar = 0;
            $sttsumur   = 'Th';
            if (!empty($p_info['tgl_lahir'])) {
                $diff = date_diff(date_create($p_info['tgl_lahir']), date_create($tgl_reg));
                $umurdaftar = $diff->y > 0 ? $diff->y : ($diff->m > 0 ? $diff->m : $diff->d);
                $sttsumur   = $diff->y > 0 ? 'Th' : ($diff->m > 0 ? 'Bl' : 'Hr');
            }

            // Update info penjamin di master pasien
            if (!empty($no_peserta) || !empty($kd_pj)) {
                $conn->query("UPDATE pasien SET no_peserta = '$no_peserta', kd_pj = '$kd_pj' WHERE no_rkm_medis = '$no_rm'");
            }

            $sql = "INSERT INTO reg_periksa (
                no_rawat, tgl_registrasi, jam_reg, no_rkm_medis,
                kd_dokter, kd_poli, kd_pj, p_jawab, almt_pj, hubunganpj,
                biaya_reg, stts, stts_daftar, status_lanjut, no_reg,
                umurdaftar, sttsumur, status_bayar, status_poli
            ) VALUES (
                '$no_rawat', '$tgl_reg', '$jam_reg', '$no_rm',
                '$kd_dok', '$kd_poli', '$kd_pj', '$p_jawab', '$almt_pj', '$hubunganpj',
                $biaya_reg, 'Belum', 'Lama', 'Ralan', '$no_reg',
                $umurdaftar, '$sttsumur', 'Belum Bayar', 'Lama'
            )";

            if ($conn->query($sql)) {
                // Auto-link antrean referensi & kirim Task 1, 2, 3 ke Antrean BPJS
                require_once dirname(__DIR__, 2) . '/includes/bpjs_antrean.php';
                $cek_ref = $conn->query("SELECT kodebooking FROM mlite_antrian_referensi WHERE no_rkm_medis = '$no_rm' AND tanggal_periksa = '$tgl_reg' LIMIT 1");
                $kodebooking = ($cek_ref && $cek_ref->num_rows > 0) ? $cek_ref->fetch_assoc()['kodebooking'] : '';
                if (empty($kodebooking)) {
                    $kodebooking = 'BK' . date('Ymd') . str_pad($no_reg, 4, '0', STR_PAD_LEFT);
                    $conn->query("
                        INSERT INTO mlite_antrian_referensi (tanggal_periksa, nomor_referensi, kodebooking, no_rkm_medis, status_kirim)
                        VALUES ('$tgl_reg', '$no_rawat', '$kodebooking', '$no_rm', 'Terkirim')
                        ON DUPLICATE KEY UPDATE kodebooking = '$kodebooking'
                    ");
                }
                $now_ms = intval(microtime(true) * 1000);
                BpjsAntreanService::updateTaskId($kodebooking, 1, $now_ms - 60000);
                BpjsAntreanService::updateTaskId($kodebooking, 2, $now_ms - 30000);
                BpjsAntreanService::updateTaskId($kodebooking, 3, $now_ms);

                // ── Auto Kirim Pendaftaran PCare BPJS ────────────────────────
                $pcare_info = '';
                $pj_row = $conn->query("SELECT png_jawab FROM penjab WHERE kd_pj='$kd_pj' LIMIT 1");
                $nm_penjab_lower = strtolower($pj_row ? ($pj_row->fetch_assoc()['png_jawab'] ?? '') : '');
                $is_bpjs_pj = (strtoupper($kd_pj) === 'BPJ' || str_contains($nm_penjab_lower, 'bpjs'));

                if ($is_bpjs_pj && !empty($no_peserta)) {
                    require_once dirname(__DIR__, 2) . '/includes/pcare_service.php';
                    $pcare_cfg = PCareService::getConfig();

                    if ($pcare_cfg['is_valid']) {
                        // Ambil mapping poli PCare
                        $map_poli_res  = $conn->query("SELECT kd_poli_pcare, nm_poli_pcare FROM maping_poliklinik_pcare WHERE kd_poli_rs = '$kd_poli' LIMIT 1");
                        $map_poli      = $map_poli_res ? $map_poli_res->fetch_assoc() : null;
                        $kd_poli_pcare = $map_poli['kd_poli_pcare'] ?? '001';
                        $nm_poli_pcare = $map_poli['nm_poli_pcare'] ?? 'Poli Umum';
                        $tgl_pcare     = date('d-m-Y', strtotime($tgl_reg));

                        // Ambil kd_provider peserta dari BPJS
                        $kd_provider = $pcare_cfg['kode_ppk'] ?: '0169B012';
                        $peserta_res = PCareService::getPesertaByNoKartu($no_peserta);
                        if (isset($peserta_res['response']['kdProviderPst']['kdProvider'])) {
                            $kd_provider = $peserta_res['response']['kdProviderPst']['kdProvider'];
                        }

                        // Ambil mapping dokter BPJS
                        $kd_dok_esc   = $conn->real_escape_string($kd_dok);
                        $map_dok_res  = $conn->query("SELECT kd_dokter_pcare, nm_dokter_pcare FROM maping_dokter_pcare WHERE kd_dokter = '$kd_dok_esc' LIMIT 1");
                        $map_dok      = $map_dok_res ? $map_dok_res->fetch_assoc() : null;
                        $kd_dok_bpjs  = (int)($map_dok['kd_dokter_pcare'] ?? 512701);
                        $nm_dok_bpjs  = $map_dok['nm_dokter_pcare'] ?? 'Dokter Jaga';

                        // ── 1. Ambil Jadwal Dokter & Jam Praktek Sesuai HFIS BPJS ──
                        $day_map  = ['Sun' => 'AKHAD', 'Mon' => 'SENIN', 'Tue' => 'SELASA', 'Wed' => 'RABU', 'Thu' => 'KAMIS', 'Fri' => 'JUMAT', 'Sat' => 'SABTU'];
                        $hari_reg = $day_map[date('D', strtotime($tgl_reg))] ?? 'SENIN';
                        $jam_praktek_bpjs = '08:00-12:00';

                        $q_jadwal = $conn->query("
                            SELECT jam_mulai, jam_selesai 
                            FROM jadwal 
                            WHERE kd_dokter = '$kd_dok_esc' AND hari_kerja = '$hari_reg'
                            LIMIT 1
                        ");
                        if ($q_jadwal && $r_jadwal = $q_jadwal->fetch_assoc()) {
                            $jam_praktek_bpjs = date('H:i', strtotime($r_jadwal['jam_mulai'])) . '-' . date('H:i', strtotime($r_jadwal['jam_selesai']));
                        } else {
                            $kd_poli_esc = $conn->real_escape_string($kd_poli);
                            $q_jadwal2 = $conn->query("
                                SELECT jam_mulai, jam_selesai 
                                FROM jadwal 
                                WHERE kd_poli = '$kd_poli_esc' AND hari_kerja = '$hari_reg'
                                LIMIT 1
                            ");
                            if ($q_jadwal2 && $r_jadwal2 = $q_jadwal2->fetch_assoc()) {
                                $jam_praktek_bpjs = date('H:i', strtotime($r_jadwal2['jam_mulai'])) . '-' . date('H:i', strtotime($r_jadwal2['jam_selesai']));
                            }
                        }

                        // ── 2. Kirim via API Antrean Online BPJS (/antrean/add) ──
                        $est_ts = strtotime("$tgl_reg 08:30:00") + (((int)$no_reg - 1) * 600);
                        $payload_antrean = [
                            'nomorkartu'     => $no_peserta,
                            'nik'            => $p_no_ktp,
                            'nohp'           => '081234567890',
                            'kodepoli'       => $kd_poli_pcare,
                            'namapoli'       => $nm_poli_pcare,
                            'pasienbaru'     => 0,
                            'norm'           => $no_rm,
                            'tanggalperiksa' => $tgl_reg,
                            'kodedokter'     => $kd_dok_bpjs,
                            'namadokter'     => $nm_dok_bpjs,
                            'jampraktek'     => $jam_praktek_bpjs,
                            'jeniskunjungan' => 1,
                            'nomorreferensi' => '',
                            'nomorantrean'   => 'A-' . sprintf('%03d', (int)$no_reg),
                            'angkaantrean'   => (int)$no_reg,
                            'estimasidilayani'=> $est_ts * 1000,
                            'sisakuotajkn'   => 30,
                            'kuotajkn'       => 50,
                            'sisakuotanonjkn'=> 20,
                            'kuotanonjkn'    => 50,
                            'keterangan'     => 'Pendaftaran Onsite SIMKlinik'
                        ];

                        $antrean_res  = BpjsAntreanService::tambahAntrean($payload_antrean);
                        $antrean_code = $antrean_res['metadata']['code'] ?? 500;
                        $antrean_msg  = $antrean_res['metadata']['message'] ?? '';

                        $no_urut_terdaftar = '';

                        // Jika Bridging Antrean sukses (200) atau sudah terdaftar (208)
                        if ($antrean_code == 200 || $antrean_code == 208) {
                            // Cari nomor urut di PCare yang digenerate oleh Antrean Online
                            for ($start_idx = 0; $start_idx < 300; $start_idx += 15) {
                                $list_res = PCareService::getPendaftaran($tgl_reg, $start_idx, 15);
                                $items    = $list_res['response']['list'] ?? [];
                                if (empty($items)) break;
                                foreach ($items as $item) {
                                    $it_kartu = $item['peserta']['noKartu'] ?? '';
                                    $it_ktp   = $item['peserta']['noKTP'] ?? '';
                                    $it_nama  = trim(strtolower($item['peserta']['nama'] ?? ''));
                                    if ($it_kartu === $no_peserta || (!empty($p_no_ktp) && $it_ktp === $p_no_ktp) || (!empty($p_nm_pasien) && $it_nama === trim(strtolower($p_nm_pasien)))) {
                                        $no_urut_terdaftar = (string)($item['noUrut'] ?? '');
                                        break 2;
                                    }
                                }
                            }
                            if (empty($no_urut_terdaftar)) {
                                $no_urut_terdaftar = 'A' . (int)$no_reg;
                            }

                            $no_urut_clean = preg_replace('/[^0-9A-Za-z]/', '', (string)$no_urut_terdaftar) ?: $no_urut_terdaftar;
                            $no_urut_esc   = $conn->real_escape_string($no_urut_clean);
                            $nm_poli_esc   = $conn->real_escape_string($nm_poli_pcare);
                            $no_peserta_esc= $conn->real_escape_string($no_peserta);
                            $kd_prov_esc   = $conn->real_escape_string($kd_provider);

                            $conn->query("
                                INSERT INTO pcare_pendaftaran (
                                    no_rawat, tglDaftar, no_rkm_medis, kdProviderPeserta, noKartu,
                                    kdPoli, nmPoli, keluhan, kunjSakit, sistole, diastole,
                                    beratBadan, tinggiBadan, respRate, lingkar_perut, heartRate,
                                    rujukBalik, kdTkp, noUrut, status
                                ) VALUES (
                                    '$no_rawat', '$tgl_reg', '$no_rm', '$kd_prov_esc', '$no_peserta_esc',
                                    '$kd_poli_pcare', '$nm_poli_esc', 'Pemeriksaan Rawat Jalan',
                                    'Kunjungan Sakit', 120, 80, 60, 165, 20, 80, 80, '0', '10', '$no_urut_esc', 'Terkirim'
                                ) ON DUPLICATE KEY UPDATE noUrut = '$no_urut_esc', status = 'Terkirim'
                            ");
                            $pcare_info = " | ✅ Bridging Antrean BPJS: No. Urut #{$no_urut_clean}";

                        } else {
                            // Fallback jika API Antrean gagal (misal jadwal dokter belum dibuat di HFIS) → kirim via PCare REST
                            $payload_pcare = [
                                'kdProviderPeserta' => $kd_provider,
                                'tglDaftar'         => $tgl_pcare,
                                'noKartu'           => $no_peserta,
                                'kdPoli'            => $kd_poli_pcare,
                                'keluhan'           => 'Pemeriksaan Rawat Jalan',
                                'kunjSakit'         => true,
                                'sistole'           => 120,
                                'diastole'          => 80,
                                'beratBadan'        => 60,
                                'tinggiBadan'       => 165,
                                'respRate'          => 20,
                                'heartRate'         => 80,
                                'lingkarPerut'      => 80,
                                'rujukBalik'        => '0',
                                'kdTkp'             => '10',
                            ];

                            $pcare_res  = PCareService::tambahPendaftaran($payload_pcare);
                            $pcare_code = $pcare_res['metadata']['code'] ?? 500;

                            if ($pcare_code == 200 || $pcare_code == 201) {
                                $no_urut       = $pcare_res['response']['message'] ?? ($pcare_res['response']['noUrut'] ?? '');
                                $no_urut_clean = preg_replace('/[^0-9A-Za-z]/', '', (string)$no_urut) ?: $no_urut;
                                $no_urut_esc   = $conn->real_escape_string($no_urut_clean);
                                $nm_poli_esc   = $conn->real_escape_string($nm_poli_pcare);
                                $no_peserta_esc= $conn->real_escape_string($no_peserta);
                                $kd_prov_esc   = $conn->real_escape_string($kd_provider);
                                $conn->query("
                                    INSERT INTO pcare_pendaftaran (
                                        no_rawat, tglDaftar, no_rkm_medis, kdProviderPeserta, noKartu,
                                        kdPoli, nmPoli, keluhan, kunjSakit, sistole, diastole,
                                        beratBadan, tinggiBadan, respRate, lingkar_perut, heartRate,
                                        rujukBalik, kdTkp, noUrut, status
                                    ) VALUES (
                                        '$no_rawat', '$tgl_reg', '$no_rm', '$kd_prov_esc', '$no_peserta_esc',
                                        '$kd_poli_pcare', '$nm_poli_esc', 'Pemeriksaan Rawat Jalan',
                                        'Kunjungan Sakit', 120, 80, 60, 165, 20, 80, 80, '0', '10', '$no_urut_esc', 'Terkirim'
                                    ) ON DUPLICATE KEY UPDATE noUrut = '$no_urut_esc', status = 'Terkirim'
                                ");
                                $pcare_info = " | ✅ PCare REST: No. Urut #{$no_urut_clean}";

                            } elseif ($pcare_code == 412) {
                                $existing_urut = null;
                                for ($start_idx = 0; $start_idx < 300; $start_idx += 15) {
                                    $list_res = PCareService::getPendaftaran($tgl_reg, $start_idx, 15);
                                    $items    = $list_res['response']['list'] ?? [];
                                    if (empty($items)) break;
                                    foreach ($items as $item) {
                                        $it_kartu = $item['peserta']['noKartu'] ?? '';
                                        $it_ktp   = $item['peserta']['noKTP'] ?? '';
                                        $it_nama  = trim(strtolower($item['peserta']['nama'] ?? ''));
                                        if ($it_kartu === $no_peserta || (!empty($p_no_ktp) && $it_ktp === $p_no_ktp) || (!empty($p_nm_pasien) && $it_nama === trim(strtolower($p_nm_pasien)))) {
                                            $existing_urut = (string)($item['noUrut'] ?? '');
                                            break 2;
                                        }
                                    }
                                }
                                if (!empty($existing_urut)) {
                                    $no_urut_esc    = $conn->real_escape_string($existing_urut);
                                    $nm_poli_esc    = $conn->real_escape_string($nm_poli_pcare);
                                    $no_peserta_esc = $conn->real_escape_string($no_peserta);
                                    $kd_prov_esc    = $conn->real_escape_string($kd_provider);
                                    $conn->query("
                                        INSERT INTO pcare_pendaftaran (
                                            no_rawat, tglDaftar, no_rkm_medis, kdProviderPeserta, noKartu,
                                            kdPoli, nmPoli, keluhan, kunjSakit, sistole, diastole,
                                            beratBadan, tinggiBadan, respRate, lingkar_perut, heartRate,
                                            rujukBalik, kdTkp, noUrut, status
                                        ) VALUES (
                                            '$no_rawat', '$tgl_reg', '$no_rm', '$kd_prov_esc', '$no_peserta_esc',
                                            '$kd_poli_pcare', '$nm_poli_esc', 'Pemeriksaan Rawat Jalan',
                                            'Kunjungan Sakit', 120, 80, 60, 165, 20, 80, 80, '0', '10', '$no_urut_esc', 'Terkirim'
                                        ) ON DUPLICATE KEY UPDATE noUrut = '$no_urut_esc', status = 'Terkirim'
                                    ");
                                    $pcare_info = " | ✅ PCare BPJS: No. Urut #{$existing_urut} (Tersinkron)";
                                } else {
                                    $pcare_info = ' | ⚠️ PCare: Pasien sudah terdaftar di PCare';
                                }
                            } else {
                                $pcare_msg  = $pcare_res['metadata']['message'] ?? ($antrean_msg ?: 'Gagal');
                                $pcare_info = " | ❌ BPJS: {$pcare_msg}";
                            }
                        }
                    } else {
                        $pcare_info = ' | ⚠️ PCare: Kredensial belum dikonfigurasi';
                    }
                }
                // ─────────────────────────────────────────────────────────────

                ob_end_clean();
                echo json_encode(['success' => true, 'message' => "Pasien berhasil didaftarkan. No. Antrian: $no_reg | No. Rawat: $no_rawat{$pcare_info}"]);
            } else {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Gagal mendaftar: ' . $conn->error]);
            }
            exit;
        }
    }

    // 4. Update Status Cepat (Batal)
    if ($action === 'batal') {
        header('Content-Type: application/json');
        $no_rawat = $conn->real_escape_string($_POST['no_rawat'] ?? '');
        $conn->query("UPDATE reg_periksa SET stts = 'Batal' WHERE no_rawat = '$no_rawat'");
        echo json_encode(['success' => true]);
        exit;
    }

    // 5. Hapus Registrasi Permanen (cascade delete semua data terkait)
    if ($action === 'hapus_registrasi') {
        ob_start(); // buffer stray output / PHP warnings
        header('Content-Type: application/json');
        $no_rawat = $conn->real_escape_string($_POST['no_rawat'] ?? '');
        if (empty($no_rawat)) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'No. Rawat tidak valid.']);
            exit;
        }
        // Nonaktifkan FK checks & error reporting agar semua child table ikut terhapus
        mysqli_report(MYSQLI_REPORT_OFF);
        $conn->query("SET FOREIGN_KEY_CHECKS=0");
        // Hapus tabel child yang TIDAK ON DELETE CASCADE (harus manual)
        @$conn->query("DELETE FROM rawat_jl_dr              WHERE no_rawat = '$no_rawat'");
        @$conn->query("DELETE FROM rawat_jl_pr              WHERE no_rawat = '$no_rawat'");
        @$conn->query("DELETE FROM rawat_jl_drpr            WHERE no_rawat = '$no_rawat'");
        @$conn->query("DELETE FROM rawat_inap_dr            WHERE no_rawat = '$no_rawat'");
        @$conn->query("DELETE FROM rawat_inap_pr            WHERE no_rawat = '$no_rawat'");
        @$conn->query("DELETE FROM rawat_inap_drpr          WHERE no_rawat = '$no_rawat'");
        @$conn->query("DELETE FROM diagnosa_pasien          WHERE no_rawat = '$no_rawat'");
        @$conn->query("DELETE FROM periksa_lab              WHERE no_rawat = '$no_rawat'");
        @$conn->query("DELETE FROM detail_periksa_lab       WHERE no_rawat = '$no_rawat'");
        @$conn->query("DELETE FROM periksa_radiologi        WHERE no_rawat = '$no_rawat'");
        @$conn->query("DELETE FROM resep_obat               WHERE no_rawat = '$no_rawat'");
        @$conn->query("DELETE FROM resep_pulang             WHERE no_rawat = '$no_rawat'");
        @$conn->query("DELETE FROM obat_racikan             WHERE no_rawat = '$no_rawat'");
        @$conn->query("DELETE FROM detail_obat_racikan      WHERE no_rawat = '$no_rawat'");
        @$conn->query("DELETE FROM detail_pemberian_obat    WHERE no_rawat = '$no_rawat'");
        @$conn->query("DELETE FROM tambahan_biaya           WHERE no_rawat = '$no_rawat'");
        @$conn->query("DELETE FROM operasi                  WHERE no_rawat = '$no_rawat'");
        @$conn->query("DELETE FROM booking_operasi          WHERE no_rawat = '$no_rawat'");
        @$conn->query("DELETE FROM dpjp_ranap               WHERE no_rawat = '$no_rawat'");
        @$conn->query("DELETE FROM pcare_pendaftaran        WHERE no_rawat = '$no_rawat'");
        @$conn->query("DELETE FROM pcare_kunjungan_umum     WHERE no_rawat = '$no_rawat'");
        @$conn->query("DELETE FROM pcare_rujuk_subspesialis WHERE no_rawat = '$no_rawat'");
        @$conn->query("DELETE FROM pcare_rujuk_khusus       WHERE no_rawat = '$no_rawat'");
        @$conn->query("DELETE FROM mlite_antrian_referensi  WHERE no_rawat = '$no_rawat'");
        // Hapus registrasi utama (ON DELETE CASCADE akan hapus sisanya otomatis)
        $ok  = $conn->query("DELETE FROM reg_periksa WHERE no_rawat = '$no_rawat'");
        $aff = $conn->affected_rows;
        $err = $conn->error;
        $conn->query("SET FOREIGN_KEY_CHECKS=1");
        ob_end_clean();
        if ($ok && $aff > 0) {
            echo json_encode(['success' => true,  'message' => "Registrasi No. Rawat $no_rawat beserta seluruh data terkait berhasil dihapus."]);
        } elseif ($ok) {
            echo json_encode(['success' => false, 'message' => "Data registrasi $no_rawat tidak ditemukan."]);
        } else {
            echo json_encode(['success' => false, 'message' => "Gagal menghapus: $err"]);
        }
        exit;
    }

    // 6. Sinkronisasi Antrean dari PCare / Mobile JKN
    if ($action === 'sinkron_pcare') {
        ob_start();
        header('Content-Type: application/json');
        require_once dirname(__DIR__, 2) . '/includes/pcare_service.php';
        
        $pcare_cfg = PCareService::getConfig();
        if (!$pcare_cfg['is_valid']) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Kredensial PCare belum dikonfigurasi.']);
            exit;
        }

        $tgl_target = sanitize($_POST['tgl'] ?? date('Y-m-d'));
        $matched = 0;
        $total_bpjs = 0;

        for ($start_idx = 0; $start_idx < 300; $start_idx += 15) {
            $res = PCareService::getPendaftaran($tgl_target, $start_idx, 15);
            $list = $res['response']['list'] ?? [];
            if (empty($list)) break;

            foreach ($list as $item) {
                $total_bpjs++;
                $noKartu = $item['peserta']['noKartu'] ?? '';
                $noKTP   = $item['peserta']['noKTP'] ?? '';
                $nmPeserta = $conn->real_escape_string(trim($item['peserta']['nama'] ?? ''));
                $noUrut  = $item['noUrut'] ?? '';
                $nmPoli  = $item['poli']['nmPoli'] ?? 'Poli Umum';
                $kdPoli  = $item['poli']['kdPoli'] ?? '001';
                $keluhan = $item['keluhan'] ?? 'Pemeriksaan Rawat Jalan';
                $kdProvider = $item['peserta']['kdProviderPst']['kdProvider'] ?? ($pcare_cfg['kode_ppk'] ?: '0169B012');

                if (empty($noKartu) && empty($noKTP) && empty($nmPeserta)) continue;

                $noKartu_esc = $conn->real_escape_string($noKartu);
                $noKTP_esc   = $conn->real_escape_string($noKTP);
                $q = $conn->query("
                    SELECT r.no_rawat, r.no_rkm_medis, p.nm_pasien, p.no_peserta
                    FROM reg_periksa r
                    JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
                    WHERE (
                        (p.no_peserta != '' AND p.no_peserta = '$noKartu_esc')
                        OR (p.no_ktp != '' AND p.no_ktp = '$noKTP_esc')
                        OR (p.no_ktp != '' AND p.no_ktp = '$noKartu_esc')
                        OR (p.nm_pasien = '$nmPeserta')
                    )
                    AND r.tgl_registrasi = '$tgl_target'
                    LIMIT 1
                ");

                if ($q && $q->num_rows > 0) {
                    $row = $q->fetch_assoc();
                    $no_rawat_esc = $conn->real_escape_string($row['no_rawat']);
                    $no_rm_esc    = $conn->real_escape_string($row['no_rkm_medis']);
                    $nm_pasien_esc= $conn->real_escape_string($row['nm_pasien']);
                    $kartu_save   = $conn->real_escape_string($row['no_peserta'] ?: $noKartu);
                    $no_urut_esc  = $conn->real_escape_string($noUrut);
                    $nm_poli_esc  = $conn->real_escape_string($nmPoli);
                    $keluhan_esc  = $conn->real_escape_string($keluhan);
                    $kd_prov_esc  = $conn->real_escape_string($kdProvider);

                    $conn->query("
                        INSERT INTO pcare_pendaftaran (
                            no_rawat, tglDaftar, no_rkm_medis, nm_pasien, kdProviderPeserta, noKartu,
                            kdPoli, nmPoli, keluhan, kunjSakit, sistole, diastole,
                            beratBadan, tinggiBadan, respRate, lingkar_perut, heartRate, rujukBalik, kdTkp, noUrut, status
                        ) VALUES (
                            '$no_rawat_esc', '$tgl_target', '$no_rm_esc', '$nm_pasien_esc', '$kd_prov_esc', '$kartu_save',
                            '$kdPoli', '$nm_poli_esc', '$keluhan_esc', 'Kunjungan Sakit', 120, 80,
                            60, 165, 20, 80, 80, '0', '10 Rawat Jalan', '$no_urut_esc', 'Terkirim'
                        ) ON DUPLICATE KEY UPDATE 
                            noUrut = '$no_urut_esc', status = 'Terkirim'
                    ");
                    $matched++;
                }
            }
        }
        ob_end_clean();
        echo json_encode([
            'success' => true,
            'message' => "Tarik antrean selesai: {$matched} pasien berhasil dicocokkan & disinkronkan dari total {$total_bpjs} data di PCare BPJS.",
            'matched' => $matched,
            'total_bpjs' => $total_bpjs
        ]);
        exit;
    }

    // 7. Kirim Antrean Manual dari Form Sebelum Simpan
    if ($action === 'kirim_antrean_manual_form') {
        ob_start();
        header('Content-Type: application/json');
        require_once dirname(__DIR__, 2) . '/includes/pcare_service.php';
        require_once dirname(__DIR__, 2) . '/includes/bpjs_antrean.php';

        $no_rm      = sanitize($_POST['no_rkm_medis'] ?? '');
        $no_peserta = sanitize($_POST['no_peserta'] ?? '');
        $kd_poli    = sanitize($_POST['kd_poli'] ?? '');
        $kd_dok     = sanitize($_POST['kd_dokter'] ?? '');
        $tgl_reg    = sanitize($_POST['tgl_registrasi'] ?? date('Y-m-d'));
        $no_reg     = sanitize($_POST['no_reg'] ?? '001');

        if (empty($no_peserta)) {
            $p_row = $conn->query("SELECT no_peserta, no_ktp, nm_pasien FROM pasien WHERE no_rkm_medis='$no_rm' LIMIT 1")->fetch_assoc();
            $no_peserta = $p_row['no_peserta'] ?? '';
            $no_ktp     = $p_row['no_ktp'] ?? '';
            $nm_pasien  = $p_row['nm_pasien'] ?? '';
        } else {
            $p_row = $conn->query("SELECT no_ktp, nm_pasien FROM pasien WHERE no_rkm_medis='$no_rm' LIMIT 1")->fetch_assoc();
            $no_ktp    = $p_row['no_ktp'] ?? '';
            $nm_pasien = $p_row['nm_pasien'] ?? '';
        }

        if (empty($no_peserta)) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Nomor Kartu BPJS pasien masih kosong. Silakan lengkapi terlebih dahulu.']);
            exit;
        }

        // Mapping Poli & Dokter
        $map_poli_res  = $conn->query("SELECT kd_poli_pcare, nm_poli_pcare FROM maping_poliklinik_pcare WHERE kd_poli_rs = '$kd_poli' LIMIT 1");
        $map_poli      = $map_poli_res ? $map_poli_res->fetch_assoc() : null;
        $kd_poli_pcare = $map_poli['kd_poli_pcare'] ?? '001';
        $nm_poli_pcare = $map_poli['nm_poli_pcare'] ?? 'Poli Umum';

        $kd_dok_esc   = $conn->real_escape_string($kd_dok);
        $map_dok_res  = $conn->query("SELECT kd_dokter_pcare, nm_dokter_pcare FROM maping_dokter_pcare WHERE kd_dokter = '$kd_dok_esc' LIMIT 1");
        $map_dok      = $map_dok_res ? $map_dok_res->fetch_assoc() : null;
        $kd_dok_bpjs  = (int)($map_dok['kd_dokter_pcare'] ?? 512701);
        $nm_dok_bpjs  = $map_dok['nm_dokter_pcare'] ?? 'Dokter Jaga';

        // Ambil Jadwal Dokter Sesuai HFIS BPJS
        $day_map  = ['Sun' => 'AKHAD', 'Mon' => 'SENIN', 'Tue' => 'SELASA', 'Wed' => 'RABU', 'Thu' => 'KAMIS', 'Fri' => 'JUMAT', 'Sat' => 'SABTU'];
        $hari_reg = $day_map[date('D', strtotime($tgl_reg))] ?? 'SENIN';
        $jam_praktek_bpjs = '08:00-12:00';

        $q_jadwal = $conn->query("
            SELECT jam_mulai, jam_selesai 
            FROM jadwal 
            WHERE kd_dokter = '$kd_dok_esc' AND hari_kerja = '$hari_reg'
            LIMIT 1
        ");
        if ($q_jadwal && $r_jadwal = $q_jadwal->fetch_assoc()) {
            $jam_praktek_bpjs = date('H:i', strtotime($r_jadwal['jam_mulai'])) . '-' . date('H:i', strtotime($r_jadwal['jam_selesai']));
        } else {
            $kd_poli_esc = $conn->real_escape_string($kd_poli);
            $q_jadwal2 = $conn->query("
                SELECT jam_mulai, jam_selesai 
                FROM jadwal 
                WHERE kd_poli = '$kd_poli_esc' AND hari_kerja = '$hari_reg'
                LIMIT 1
            ");
            if ($q_jadwal2 && $r_jadwal2 = $q_jadwal2->fetch_assoc()) {
                $jam_praktek_bpjs = date('H:i', strtotime($r_jadwal2['jam_mulai'])) . '-' . date('H:i', strtotime($r_jadwal2['jam_selesai']));
            }
        }

        // Kirim via Antrean BPJS Online (/antrean/add)
        $est_ts = strtotime("$tgl_reg 08:30:00") + (((int)$no_reg - 1) * 600);
        $payload_antrean = [
            'nomorkartu'     => $no_peserta,
            'nik'            => $no_ktp,
            'nohp'           => '081234567890',
            'kodepoli'       => $kd_poli_pcare,
            'namapoli'       => $nm_poli_pcare,
            'pasienbaru'     => 0,
            'norm'           => $no_rm,
            'tanggalperiksa' => $tgl_reg,
            'kodedokter'     => $kd_dok_bpjs,
            'namadokter'     => $nm_dok_bpjs,
            'jampraktek'     => $jam_praktek_bpjs,
            'jeniskunjungan' => 1,
            'nomorreferensi' => '',
            'nomorantrean'   => 'A-' . sprintf('%03d', (int)$no_reg),
            'angkaantrean'   => (int)$no_reg,
            'estimasidilayani'=> $est_ts * 1000,
            'sisakuotajkn'   => 30,
            'kuotajkn'       => 50,
            'sisakuotanonjkn'=> 20,
            'kuotanonjkn'    => 50,
            'keterangan'     => 'Pendaftaran Onsite SIMKlinik'
        ];

        $antrean_res  = BpjsAntreanService::tambahAntrean($payload_antrean);
        $antrean_code = $antrean_res['metadata']['code'] ?? 500;
        $antrean_msg  = $antrean_res['metadata']['message'] ?? '';

        $no_urut = '';

        if ($antrean_code == 200 || $antrean_code == 208) {
            // Berhasil terdaftar via Bridging Antrean → Scan PCare untuk mengambil nomor urut antrean asli
            for ($start_idx = 0; $start_idx < 300; $start_idx += 15) {
                $list_res = PCareService::getPendaftaran($tgl_reg, $start_idx, 15);
                $items    = $list_res['response']['list'] ?? [];
                if (empty($items)) break;
                foreach ($items as $item) {
                    $it_kartu = $item['peserta']['noKartu'] ?? '';
                    $it_ktp   = $item['peserta']['noKTP'] ?? '';
                    $it_nama  = trim(strtolower($item['peserta']['nama'] ?? ''));
                    if ($it_kartu === $no_peserta || (!empty($no_ktp) && $it_ktp === $no_ktp) || (!empty($nm_pasien) && $it_nama === trim(strtolower($nm_pasien)))) {
                        $no_urut = (string)($item['noUrut'] ?? '');
                        break 2;
                    }
                }
            }
            if (empty($no_urut)) {
                $no_urut = 'A' . (int)$no_reg;
            }

            ob_end_clean();
            echo json_encode([
                'success' => true,
                'no_urut' => $no_urut,
                'message' => "Antrean BPJS Berhasil Terkirim via Bridging Antrean! No. Urut: {$no_urut}. Silakan klik 'Simpan Pendaftaran' untuk menyelesaikan."
            ]);
            exit;
        } else {
            ob_end_clean();
            echo json_encode([
                'success' => false,
                'message' => "Respon BPJS Antrean [Code {$antrean_code}]: {$antrean_msg}"
            ]);
            exit;
        }
    }
}

// ─── Filter & Data Antrian ────────────────────────────────────
$today     = date('Y-m-d');
$tgl       = sanitize($_GET['tgl'] ?? $today);
$kd_poli   = sanitize($_GET['kd_poli'] ?? '');
$kd_dokter = sanitize($_GET['kd_dokter'] ?? '');
$status    = sanitize($_GET['status'] ?? '');
$search    = sanitize($_GET['q'] ?? '');
$sort_by   = sanitize($_GET['sort_by'] ?? 'dokter_noreg');

$where  = "r.tgl_registrasi = '$tgl'";
if ($kd_poli)   $where .= " AND r.kd_poli = '" . $conn->real_escape_string($kd_poli) . "'";
if ($kd_dokter) $where .= " AND r.kd_dokter = '" . $conn->real_escape_string($kd_dokter) . "'";
if ($status)    $where .= " AND r.stts = '" . $conn->real_escape_string($status) . "'";
if ($search) {
    $s = $conn->real_escape_string($search);
    $where .= " AND (p.nm_pasien LIKE '%$s%' OR p.no_rkm_medis LIKE '%$s%' OR r.no_rawat LIKE '%$s%' OR p.alamat LIKE '%$s%')";
}

// Pengurutan (Sorting)
switch ($sort_by) {
    case 'noreg':
        $order_sql = "CAST(r.no_reg AS UNSIGNED) ASC, r.jam_reg ASC";
        break;
    case 'belum_jam':
        $order_sql = "(r.stts = 'Belum') DESC, r.jam_reg ASC";
        break;
    case 'jam_asc':
        $order_sql = "r.jam_reg ASC";
        break;
    case 'jam_desc':
        $order_sql = "r.jam_reg DESC";
        break;
    case 'nama_pasien':
        $order_sql = "p.nm_pasien ASC";
        break;
    case 'dokter_noreg':
    default:
        $order_sql = "d.nm_dokter ASC, CAST(r.no_reg AS UNSIGNED) ASC, r.jam_reg ASC";
        $sort_by   = 'dokter_noreg';
        break;
}

// Paginasi Antrian Pendaftaran
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 25;
$offset   = ($page - 1) * $per_page;

$count_result = $conn->query("
    SELECT COUNT(DISTINCT r.no_rawat) as total
    FROM reg_periksa r
    JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
    WHERE $where
");
$total_rows  = $count_result ? (int)$count_result->fetch_assoc()['total'] : 0;
$total_pages = max(1, (int)ceil($total_rows / $per_page));

$pag = [
    'page'        => $page,
    'per_page'    => $per_page,
    'total'       => $total_rows,
    'total_pages' => $total_pages,
    'offset'      => $offset,
    'has_prev'    => $page > 1,
    'has_next'    => $page < $total_pages,
];

// Master Poliklinik, Penjab & Dokter
$poli_list = [];
$rp = $conn->query("SELECT kd_poli, nm_poli FROM poliklinik WHERE status='1' ORDER BY nm_poli");
if ($rp) while ($row = $rp->fetch_assoc()) $poli_list[] = $row;

$penjab_list = [];
$rj = $conn->query("SELECT kd_pj, png_jawab as nm_penjab FROM penjab WHERE status = '1' ORDER BY png_jawab");
if ($rj) while ($row = $rj->fetch_assoc()) $penjab_list[] = $row;

$dokter_list = [];
$rd = $conn->query("SELECT kd_dokter, nm_dokter FROM dokter WHERE status='1' ORDER BY nm_dokter");
if ($rd) while ($row = $rd->fetch_assoc()) $dokter_list[] = $row;

// Data antrian dengan data penunjang PCare BPJS & EMR tanpa duplikasi
$result = $conn->query("
    SELECT r.no_rawat, r.no_reg, r.tgl_registrasi, r.jam_reg,
           r.stts, r.status_lanjut, r.kd_dokter, r.kd_poli, r.kd_pj,
           p.nm_pasien, p.no_rkm_medis, p.jk, p.tgl_lahir, p.no_peserta, p.no_ktp, p.alamat,
           d.nm_dokter, pol.nm_poli, pj.png_jawab as nm_penjab,
           pr.suhu_tubuh, pr.tensi, pr.nadi, pr.respirasi, pr.tinggi, pr.berat, pr.spo2, pr.gcs, pr.keluhan,
           (SELECT dp.kd_penyakit FROM diagnosa_pasien dp WHERE dp.no_rawat = r.no_rawat ORDER BY dp.prioritas ASC LIMIT 1) as kd_diag_utama,
           (SELECT pen.nm_penyakit FROM diagnosa_pasien dp LEFT JOIN penyakit pen ON dp.kd_penyakit = pen.kd_penyakit WHERE dp.no_rawat = r.no_rawat ORDER BY dp.prioritas ASC LIMIT 1) as nm_diag_utama,
           (SELECT pd.noUrut FROM pcare_pendaftaran pd WHERE pd.no_rawat = r.no_rawat LIMIT 1) as no_urut_pcare,
           (SELECT rj.noKunjungan FROM pcare_rujuk_subspesialis rj WHERE rj.no_rawat = r.no_rawat LIMIT 1) as no_rujukan_sub,
           (SELECT rk.noKunjungan FROM pcare_rujuk_khusus rk WHERE rk.no_rawat = r.no_rawat LIMIT 1) as no_rujukan_khusus,
           (SELECT ku.noKunjungan FROM pcare_kunjungan_umum ku WHERE ku.no_rawat = r.no_rawat LIMIT 1) as no_kunjungan_pcare
    FROM reg_periksa r
    JOIN pasien p      ON r.no_rkm_medis = p.no_rkm_medis
    LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
    LEFT JOIN poliklinik pol ON r.kd_poli = pol.kd_poli
    LEFT JOIN penjab pj ON r.kd_pj = pj.kd_pj
    LEFT JOIN (
        SELECT pr1.* FROM pemeriksaan_ralan pr1
        JOIN (SELECT no_rawat, MAX(CONCAT(tgl_perawatan, ' ', jam_rawat)) as max_time FROM pemeriksaan_ralan GROUP BY no_rawat) pr2
          ON pr1.no_rawat = pr2.no_rawat AND CONCAT(pr1.tgl_perawatan, ' ', pr1.jam_rawat) = pr2.max_time
    ) pr ON r.no_rawat = pr.no_rawat
    WHERE $where
    GROUP BY r.no_rawat
    ORDER BY $order_sql
    LIMIT $per_page OFFSET $offset
");

$list = [];
if ($result) while ($row = $result->fetch_assoc()) $list[] = $row;

// ─── Live Polling Table HTML Output ───────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'get_live_pendaftaran') {
    header('Content-Type: application/json');
    ob_start();
    if (empty($list)): ?>
      <tr>
        <td colspan="8" style="text-align:center;padding:36px;color:#94a3b8;">
          <i class="fas fa-clipboard-user" style="font-size:28px;color:#cbd5e1;display:block;margin-bottom:8px;"></i>
          Belum ada pasien yang terdaftar pada filter ini.
        </td>
      </tr>
    <?php else: ?>
      <?php foreach ($list as $i => $k): 
        $is_bpjs = ($k['kd_pj'] === 'BPJ' || str_contains(strtolower($k['nm_penjab'] ?? ''), 'bpjs'));
        $has_daftar_pcare = !empty($k['no_urut_pcare']);
        $has_rujukan = !empty($k['no_rujukan_sub']) || !empty($k['no_rujukan_khusus']);
        $has_kunj_pcare = !empty($k['no_kunjungan_pcare']) || $has_rujukan;
        $no_rujukan = $k['no_rujukan_sub'] ?: ($k['no_rujukan_khusus'] ?: '');
        $data_json = htmlspecialchars(json_encode($k), ENT_QUOTES, 'UTF-8');
      ?>
        <tr id="row-<?= htmlspecialchars($k['no_rawat']) ?>">
          <td style="text-align:center;font-weight:700;color:var(--primary-700);font-size:13px;padding:9px 10px;">
            <?= htmlspecialchars($k['no_reg'] ?: sprintf('%03d', $i+1)) ?>
          </td>
          <td style="padding:9px 12px;">
            <div style="display:flex;align-items:center;gap:10px;">
              <span style="width:8px;height:8px;border-radius:50%;background:#f59e0b;box-shadow:0 0 0 2px #fef3c7;display:inline-block;flex-shrink:0;"></span>
              <div>
                <div style="font-size:13px;font-weight:700;color:#0f172a;line-height:1.2;"><?= htmlspecialchars($k['nm_pasien']) ?> <?= icon_jk($k['jk']) ?></div>
                <div style="font-size:11px;color:#64748b;margin-top:3px;">
                  MR: <strong style="color:#0f766e;"><?= $k['no_rkm_medis'] ?></strong> &bull; <?= hitung_umur($k['tgl_lahir']) ?>
                </div>
                <?php if (!empty($k['alamat'])): ?>
                  <div style="font-size:11px;color:#475569;margin-top:3px;display:flex;align-items:center;gap:4px;">
                    <i class="fas fa-map-marker-alt" style="color:#0891b2;font-size:10px;flex-shrink:0;"></i>
                    <span style="max-width:240px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?= htmlspecialchars($k['alamat']) ?>"><?= htmlspecialchars($k['alamat']) ?></span>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </td>
          <td style="padding:9px 12px;">
            <div style="font-size:12.5px;font-weight:600;color:#0f172a;"><?= htmlspecialchars($k['nm_poli'] ?: '-') ?></div>
            <div style="font-size:11px;color:#64748b;margin-top:2px;"><?= htmlspecialchars($k['nm_dokter'] ?: '-') ?></div>
          </td>
          <td style="padding:9px 12px;">
            <?= badge_penjab($k['nm_penjab'] ?? 'Umum', $is_bpjs ? ($k['no_peserta'] ?? null) : null) ?>
          </td>
          <td style="font-size:11.5px;color:#475569;font-weight:600;padding:9px 10px;">
            <i class="fas fa-clock" style="font-size:10px;color:#94a3b8;margin-right:2px;"></i> <?= substr($k['jam_reg'],0,5) ?>
          </td>
          <td style="padding:9px 10px;"><?= badge_status($k['stts']) ?></td>
          <td style="padding:9px 10px;">
            <?php if ($is_bpjs): ?>
              <div style="display:flex;flex-direction:column;gap:3px;">
                <?php if ($has_daftar_pcare): ?>
                  <div style="display:flex;align-items:center;gap:4px;">
                    <span style="font-size:10px;font-weight:700;color:#0369a1;background:#e0f2fe;padding:2px 6px;border-radius:4px;display:inline-flex;align-items:center;gap:3px;" title="Terdaftar di PCare BPJS">
                      <i class="fas fa-id-badge"></i> Urut #<?= htmlspecialchars($k['no_urut_pcare']) ?>
                    </span>
                  </div>
                <?php endif; ?>

                <!-- Kunjungan PCare -->
                <div style="display:flex;align-items:center;gap:4px;">
                  <?php if ($has_kunj_pcare): ?>
                    <span style="font-size:10px;font-weight:700;color:#047857;background:#d1fae5;padding:2px 6px;border-radius:4px;display:inline-flex;align-items:center;gap:3px;" title="Kunjungan PCare Terkirim">
                      <i class="fas fa-check-circle"></i> Kunjungan OK
                    </span>
                  <?php else: ?>
                    <button type="button" class="btn btn-sm btn-outline-primary" style="padding:2px 6px;font-size:10.5px;white-space:nowrap;" title="Kirim Kunjungan / SOAP ke PCare BPJS" onclick="kirimKunjunganPcareRow('<?= htmlspecialchars($k['no_rawat']) ?>')">
                      <i class="fas fa-cloud-arrow-up"></i> Kirim Kunjungan PCare
                    </button>
                  <?php endif; ?>
                </div>

                <!-- Rujukan RS (Opsional) -->
                <div style="display:flex;align-items:center;gap:4px;">
                  <?php if ($has_rujukan): ?>
                    <a href="<?= BASE_URL ?>modules/pcare/cetak_rujukan.php?no_rawat=<?= urlencode($k['no_rawat']) ?>" target="_blank" class="btn btn-sm btn-outline-success" style="padding:2px 6px;font-size:10.5px;white-space:nowrap;" title="Cetak Surat Rujukan BPJS">
                      <i class="fas fa-print"></i> Cetak Rujuk
                    </a>
                  <?php else: ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary" style="padding:2px 6px;font-size:10.5px;white-space:nowrap;" title="Buat & Kirim Surat Rujukan BPJS" onclick='bukaModalRujukan(<?= $data_json ?>)'>
                      <i class="fas fa-share-nodes"></i> Rujuk RS
                    </button>
                  <?php endif; ?>
                </div>
              </div>
            <?php else: ?>
              <span style="font-size:12px;color:#cbd5e1;font-weight:600;display:block;text-align:center;">-</span>
            <?php endif; ?>
          </td>
          <td style="text-align:right;padding:9px 14px;">
            <div style="display:inline-flex;align-items:center;gap:4px;">
              <a href="<?= BASE_URL ?>modules/rekam_medis/periksa.php?no_rawat=<?= urlencode($k['no_rawat']) ?>"
                 class="btn btn-sm <?= $k['stts']==='Sudah' ? 'btn-outline-primary' : 'btn-primary' ?>"
                 style="padding:5px 10px;font-size:11.5px;display:inline-flex;align-items:center;gap:4px;" title="Periksa Pasien">
                <i class="fas fa-stethoscope"></i> <?= $k['stts']==='Sudah' ? 'EMR' : 'Periksa' ?>
              </a>
              <button type="button" class="btn btn-sm btn-secondary" style="padding:5px 8px;font-size:11.5px;color:#0f766e;" title="Edit Pendaftaran"
                      onclick="openFormEdit('<?= htmlspecialchars($k['no_rawat']) ?>')">
                <i class="fas fa-edit"></i>
              </button>
              <a href="<?= BASE_URL ?>modules/pasien/detail.php?rm=<?= urlencode($k['no_rkm_medis']) ?>"
                 class="btn btn-sm btn-secondary" style="padding:5px 8px;font-size:11.5px;" title="Profil Pasien">
                <i class="fas fa-user"></i>
              </a>
              <button type="button" class="btn btn-sm btn-secondary" style="padding:5px 8px;font-size:11.5px;color:#4f46e5;" title="General Consent & Tanda Tangan Digital" onclick="bukaModalGeneralConsent('<?= htmlspecialchars($k['no_rawat']) ?>')">
                <i class="fas fa-file-signature"></i>
              </button>
              <?php if ($k['stts'] !== 'Batal' && $k['stts'] !== 'Sudah'): ?>
                <button type="button" class="btn btn-sm btn-outline" style="padding:5px 8px;font-size:11.5px;color:#ef4444;border-color:#fca5a5;" title="Batalkan Kunjungan"
                        onclick="ubahStatus('<?= $k['no_rawat'] ?>', 'batal')">
                  <i class="fas fa-times"></i>
                </button>
              <?php endif; ?>
              <?php if ($k['stts'] === 'Batal' || $k['stts'] === 'Belum'): ?>
                <button type="button" class="btn btn-sm btn-outline" style="padding:5px 8px;font-size:11.5px;color:#dc2626;border-color:#dc2626;background:#fff5f5;" title="Hapus Registrasi Permanen"
                        onclick="hapusRegistrasi('<?= htmlspecialchars($k['no_rawat']) ?>', '<?= htmlspecialchars($k['nm_pasien']) ?>')">
                  <i class="fas fa-trash-alt"></i>
                </button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    <?php endif;
    $html = ob_get_clean();
    echo json_encode(['success' => true, 'count' => $total_rows, 'html' => $html]);
    exit;
}

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<style>
.collapsible-form-wrapper {
  max-height: 0;
  opacity: 0;
  overflow: hidden;
  transform: translateY(-10px);
  transition: max-height 0.45s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.35s ease, transform 0.35s ease, margin-bottom 0.35s ease;
  margin-bottom: 0;
}
.collapsible-form-wrapper.is-expanded {
  max-height: 950px;
  opacity: 1;
  transform: translateY(0);
  margin-bottom: 16px;
}
</style>

<!-- ─── Page Header (Modern Medical Style) ──────────────── -->
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:14px;">
  <div>
    <h1 class="page-title" style="font-size:20px;font-weight:800;color:#0f2b2b;margin-bottom:2px;">Pendaftaran Rawat Jalan & Antrian</h1>
    <p class="page-subtitle" style="font-size:12px;margin:0;color:#64748b;">Kelola pendaftaran kunjungan harian pasien &mdash; <?= tgl_indo($tgl, true) ?></p>
  </div>
  <div class="page-actions" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
    <button type="button" class="btn btn-outline-pill" style="border-color:#0284c7;color:#0369a1;background:#f0f9ff;" onclick="sinkronSemuaPcare()" title="Tarik & Sinkronkan Nomor Urut Antrean dari PCare BPJS">
      <i class="fas fa-rotate" id="iconSyncPcare"></i> TARIK ANTREAN PCARE
    </button>
    <a href="<?= BASE_URL ?>modules/tarif_ralan/index.php" class="btn btn-outline-pill">
      <i class="fas fa-info-circle"></i> HARGA & TARIF
    </a>
    <a href="<?= BASE_URL ?>modules/kasir/index.php" class="btn btn-outline-pill">
      <i class="fas fa-edit"></i> BIAYA PERAWATAN
    </a>
    <button type="button" id="btnToggleForm" class="btn btn-primary btn-pill" onclick="toggleFormPendaftaran()">
      <i class="fas fa-plus-circle" id="toggleIcon"></i> <span id="toggleText">BUAT PENDAFTARAN</span>
    </button>
  </div>
</div>

<!-- ─── Medical Sub Tabs ──────────────────────────────────── -->
<div class="nav-tabs-medical">
  <a href="<?= BASE_URL ?>modules/pendaftaran/index.php" class="active"><i class="fas fa-user-injured" style="margin-right:4px;"></i> Pasien Hari Ini</a>
  <a href="<?= BASE_URL ?>modules/rekam_medis/index.php"><i class="fas fa-stethoscope" style="margin-right:4px;"></i> Rawat Jalan (E-RM)</a>
  <a href="<?= BASE_URL ?>modules/kasir/index.php"><i class="fas fa-receipt" style="margin-right:4px;"></i> Kasir & Pembayaran</a>
</div>

<!-- ─── Expandable / Collapsible Form Pendaftaran Pasien ──── -->
<div id="pendaftaranFormContainer" class="collapsible-form-wrapper">
  <div class="card" style="border-top:4px solid var(--primary-600);box-shadow:0 6px 18px rgba(0,0,0,0.06);margin-bottom:0;">
    
    <div class="card-header" style="padding:10px 18px;background:#f8fafc;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #e2e8f0;">
      <div class="card-title" style="font-size:13.5px;display:flex;align-items:center;gap:8px;margin:0;">
        <i class="fas fa-calendar-plus text-primary"></i>
        <span id="formHeaderTitle">Formulir Pendaftaran Pasien</span>
        <span id="formModeBadge" class="badge badge-primary" style="font-size:10.5px;padding:2px 8px;">Pendaftaran Baru</span>
      </div>
      <button type="button" class="btn btn-sm btn-secondary" style="padding:3px 8px;font-size:12px;" onclick="closeFormPendaftaran()">
        <i class="fas fa-times"></i> Tutup
      </button>
    </div>

    <div class="card-body" style="padding:18px 24px;">
      <form id="formPendaftaranInline" onsubmit="submitPendaftaranForm(event)">
        <input type="hidden" name="action" value="simpan_pendaftaran">
        <input type="hidden" name="form_mode" id="formMode" value="tambah">
        <input type="hidden" name="no_rkm_medis" id="formNoRm" value="">

        <div style="max-width:760px;margin:0 auto;display:flex;flex-direction:column;gap:12px;">

          <!-- Baris 1: Tanggal & Jam -->
          <div style="display:grid;grid-template-columns:110px 1fr 60px 1fr;gap:12px;align-items:center;">
            <label style="font-size:13px;font-weight:700;color:#334155;text-align:right;">Tanggal</label>
            <input type="date" name="tgl_registrasi" id="formTglReg" class="form-control"
                   value="<?= date('Y-m-d') ?>" style="height:36px;font-size:13px;" onchange="onDateOrPoliChange()">

            <label style="font-size:13px;font-weight:700;color:#334155;text-align:right;">Jam</label>
            <input type="text" name="jam_reg" id="formJamReg" class="form-control"
                   value="<?= date('H:i:s') ?>" style="height:36px;font-size:13px;font-family:monospace;">
          </div>

          <!-- Baris 2: Pasien -->
          <div style="display:grid;grid-template-columns:110px 1fr;gap:12px;align-items:start;">
            <label style="font-size:13px;font-weight:700;color:#334155;text-align:right;margin-top:8px;">Pasien <span style="color:#ef4444;">*</span></label>
            <div style="position:relative;">
              
              <!-- Input Search Pasien -->
              <input type="text" id="formSearchPasien" class="form-control" style="height:36px;font-size:13px;"
                     placeholder="Cari nama pasien / nomor rekam medik / NIK..." autocomplete="off"
                     oninput="onSearchPasien(this.value)">

              <!-- Selected Pasien Badge -->
              <div id="formSelectedPasienPill" style="display:none;align-items:center;justify-content:space-between;background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;padding:6px 12px;font-size:12.5px;">
                <div style="display:flex;flex-direction:column;gap:2px;">
                  <div style="display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-user-check" style="color:#2563eb;"></i>
                    <strong id="pillNamaPasien" style="color:#1e3a8a;"></strong>
                    <span id="pillRmPasien" style="color:#3b82f6;font-size:11.5px;"></span>
                    <span id="pillUmurPasien" style="color:#64748b;font-size:11.5px;"></span>
                  </div>
                  <div id="pillAlamatPasien" style="font-size:11px;color:#475569;margin-left:22px;display:none;"></div>
                </div>
                <button type="button" class="btn btn-sm btn-outline" style="padding:2px 6px;font-size:11px;color:#ef4444;border-color:#fca5a5;" onclick="clearSelectedPasien()">
                  <i class="fas fa-times"></i> Ganti
                </button>
              </div>

              <!-- Autocomplete Results Dropdown -->
              <div id="pasienDropdownResults" style="display:none;position:absolute;top:100%;left:0;right:0;background:#ffffff;border:1px solid #cbd5e1;border-radius:8px;box-shadow:0 8px 20px rgba(0,0,0,0.12);max-height:220px;overflow-y:auto;z-index:999;margin-top:3px;"></div>
            </div>
          </div>

          <!-- Baris 3: Poliklinik -->
          <div style="display:grid;grid-template-columns:110px 1fr;gap:12px;align-items:center;">
            <label style="font-size:13px;font-weight:700;color:#334155;text-align:right;">Poliklinik <span style="color:#ef4444;">*</span></label>
            <select name="kd_poli" id="formSelectPoli" class="form-control" style="height:36px;font-size:13px;" required onchange="onPoliChange(this.value)">
              <option value="">— Pilih Poliklinik —</option>
              <?php foreach ($poli_list as $pl): ?>
                <option value="<?= $pl['kd_poli'] ?>"><?= htmlspecialchars($pl['nm_poli']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Baris 4: Dokter -->
          <div style="display:grid;grid-template-columns:110px 1fr;gap:12px;align-items:center;">
            <label style="font-size:13px;font-weight:700;color:#334155;text-align:right;">Dokter <span style="color:#ef4444;">*</span></label>
            <select name="kd_dokter" id="formSelectDokter" class="form-control" style="height:36px;font-size:13px;" required>
              <option value="">— Pilih Dokter —</option>
            </select>
          </div>

          <!-- Baris 5: Penjamin -->
          <div style="display:grid;grid-template-columns:110px 1fr;gap:12px;align-items:center;">
            <label style="font-size:13px;font-weight:700;color:#334155;text-align:right;">Penjamin <span style="color:#ef4444;">*</span></label>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
              <select name="kd_pj" id="formSelectPj" class="form-control" style="height:36px;font-size:13px;" required>
                <?php foreach ($penjab_list as $pj): ?>
                  <option value="<?= $pj['kd_pj'] ?>"><?= htmlspecialchars($pj['nm_penjab']) ?></option>
                <?php endforeach; ?>
              </select>
              <input type="text" name="no_peserta" id="formNoPeserta" class="form-control" style="height:36px;font-size:13px;" placeholder="No. Kartu BPJS / Asuransi">
            </div>
          </div>

          <!-- Baris 6: No. Rawat & No. Reg -->
          <div style="display:grid;grid-template-columns:110px 1fr 60px 1fr;gap:12px;align-items:center;">
            <label style="font-size:13px;font-weight:700;color:#334155;text-align:right;">No. Rawat</label>
            <input type="text" name="no_rawat" id="formNoRawat" class="form-control"
                   value="" style="height:36px;font-size:13px;font-family:monospace;background:#f8fafc;" readonly>

            <label style="font-size:13px;font-weight:700;color:#334155;text-align:right;">No. Reg</label>
            <input type="text" name="no_reg" id="formNoReg" class="form-control"
                   value="001" style="height:36px;font-size:13px;font-weight:700;color:var(--primary-700);font-family:monospace;">
          </div>

          <!-- Action Buttons -->
          <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-top:8px;padding-top:10px;border-top:1px solid #f1f5f9;flex-wrap:wrap;">
            <div id="badgeStatusAntreanBpjsForm" style="display:none;align-items:center;gap:6px;font-size:12px;font-weight:700;color:#0369a1;background:#e0f2fe;padding:6px 12px;border-radius:6px;border:1px solid #bae6fd;">
              <i class="fas fa-check-circle" style="color:#0284c7;"></i> Antrean BPJS Terkirim: <span id="textNoUrutBpjsForm"></span>
            </div>
            <div style="display:flex;gap:8px;margin-left:auto;">
              <button type="button" class="btn btn-secondary" style="padding:8px 18px;font-size:12.5px;" onclick="closeFormPendaftaran()">
                Batal / Tutup
              </button>
              <button type="button" id="btnKirimAntreanManual" class="btn btn-outline" style="padding:8px 16px;font-size:12.5px;color:#0369a1;border-color:#0284c7;background:#f0f9ff;display:inline-flex;align-items:center;gap:6px;" onclick="kirimAntreanSebelumSimpan(this)" title="Kirim data antrean ke BPJS sebelum menyimpan">
                <i class="fas fa-tower-broadcast"></i> Kirim Antrean BPJS
              </button>
              <button type="submit" id="btnSubmitForm" class="btn btn-primary" style="padding:8px 24px;font-size:12.5px;font-weight:700;">
                <i class="fas fa-save"></i> Simpan Pendaftaran
              </button>
            </div>
          </div>

        </div>
      </form>
    </div>

  </div>
</div>

<!-- ─── Compact Filter Toolbar ───────────────────────────── -->
<div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:10px;padding:8px 14px;margin-bottom:12px;box-shadow:0 1px 2px rgba(0,0,0,0.03);">
  <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
    
    <div style="display:flex;align-items:center;gap:6px;">
      <label style="font-size:11.5px;color:#64748b;font-weight:600;white-space:nowrap;">Tanggal:</label>
      <input type="date" name="tgl" class="form-control" style="width:135px;padding:4px 8px;font-size:12px;height:32px;"
             value="<?= $tgl ?>" max="<?= date('Y-m-d') ?>">
    </div>

    <div style="display:flex;align-items:center;gap:6px;">
      <label style="font-size:11.5px;color:#64748b;font-weight:600;white-space:nowrap;">Poli:</label>
      <select name="kd_poli" class="form-control" style="width:135px;padding:4px 8px;font-size:12px;height:32px;">
        <option value="">— Semua Poli —</option>
        <?php foreach ($poli_list as $pl): ?>
          <option value="<?= $pl['kd_poli'] ?>" <?= $kd_poli===$pl['kd_poli'] ? 'selected':'' ?>>
            <?= htmlspecialchars($pl['nm_poli']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div style="display:flex;align-items:center;gap:6px;">
      <label style="font-size:11.5px;color:#64748b;font-weight:600;white-space:nowrap;">Dokter:</label>
      <select name="kd_dokter" class="form-control" style="width:150px;padding:4px 8px;font-size:12px;height:32px;">
        <option value="">— Semua Dokter —</option>
        <?php foreach ($dokter_list as $dl): ?>
          <option value="<?= $dl['kd_dokter'] ?>" <?= $kd_dokter===$dl['kd_dokter'] ? 'selected':'' ?>>
            <?= htmlspecialchars($dl['nm_dokter']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div style="display:flex;align-items:center;gap:6px;">
      <label style="font-size:11.5px;color:#64748b;font-weight:600;white-space:nowrap;">Status:</label>
      <select name="status" class="form-control" style="width:105px;padding:4px 8px;font-size:12px;height:32px;">
        <option value="">— Semua —</option>
        <option value="Belum"  <?= $status==='Belum'  ? 'selected':'' ?>>Menunggu</option>
        <option value="Sudah"  <?= $status==='Sudah'  ? 'selected':'' ?>>Selesai</option>
        <option value="Batal"  <?= $status==='Batal'  ? 'selected':'' ?>>Batal</option>
      </select>
    </div>

    <div style="display:flex;align-items:center;gap:6px;">
      <label style="font-size:11.5px;color:#64748b;font-weight:600;white-space:nowrap;">Urutkan:</label>
      <select name="sort_by" class="form-control" style="width:160px;padding:4px 8px;font-size:12px;height:32px;font-weight:600;color:var(--primary-700);">
        <option value="dokter_noreg" <?= $sort_by==='dokter_noreg' ? 'selected':'' ?>>👨‍⚕️ Dokter & No. Antrian</option>
        <option value="noreg"        <?= $sort_by==='noreg' ? 'selected':'' ?>>🔢 No. Antrian Saja</option>
        <option value="belum_jam"    <?= $sort_by==='belum_jam' ? 'selected':'' ?>>⏳ Menunggu & Jam</option>
        <option value="jam_asc"      <?= $sort_by==='jam_asc' ? 'selected':'' ?>>🕒 Jam Masuk (Terlama)</option>
        <option value="jam_desc"     <?= $sort_by==='jam_desc' ? 'selected':'' ?>>🕒 Jam Masuk (Terbaru)</option>
        <option value="nama_pasien"  <?= $sort_by==='nama_pasien' ? 'selected':'' ?>>🔤 Nama Pasien (A-Z)</option>
      </select>
    </div>

    <div style="flex:1;min-width:160px;max-width:240px;">
      <input type="text" name="q" class="form-control" style="padding:4px 10px;font-size:12px;height:32px;"
             placeholder="Cari Pasien / No. RM..." value="<?= htmlspecialchars($search) ?>">
    </div>

    <button type="submit" class="btn btn-primary btn-sm" style="height:32px;padding:4px 12px;font-size:12px;"><i class="fas fa-filter"></i> Filter</button>
    
    <?php if ($search || $kd_poli || $kd_dokter || $status || $sort_by !== 'dokter_noreg' || $tgl !== $today): ?>
      <a href="<?= BASE_URL ?>modules/pendaftaran/index.php" class="btn btn-secondary btn-sm" style="height:32px;padding:4px 10px;font-size:12px;"><i class="fas fa-times"></i> Reset</a>
    <?php endif; ?>

    <div style="margin-left:auto;display:flex;align-items:center;gap:6px;">
      <span class="badge-online" style="font-size:10px;font-weight:600;padding:2px 8px;">
        <i class="fas fa-circle"></i> Live Sync Aktif
      </span>
    </div>

  </form>
</div>

<!-- ─── Tabel Antrian Pasien ─────────────────────────────── -->
<div class="card" style="margin-bottom:0;">
  <div class="card-header" style="padding:10px 16px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;">
    <div class="card-title" style="font-size:13px;display:flex;align-items:center;gap:6px;margin:0;">
      <i class="fas fa-clipboard-list text-primary"></i> 
      <span>Daftar Antrian Kunjungan Pasien</span>
    </div>
    <span style="font-size:12px;color:#64748b;" id="totalDaftarText">
      Total: <strong><?= count($list) ?></strong> pasien
    </span>
  </div>

  <div class="card-body" style="padding:0;">
    <div class="table-responsive">
      <table class="table table-hover mb-0" style="font-size:12.5px;">
        <thead>
          <tr>
            <th style="width:50px;text-align:center;">Antrian</th>
            <th>Pasien & No. RM</th>
            <th>Poliklinik / Dokter</th>
            <th>Penjamin</th>
            <th style="width:90px;">Jam</th>
            <th style="width:95px;">Status</th>
            <th style="width:145px;">Bridging PCare</th>
            <th style="width:140px;text-align:right;">Aksi</th>
          </tr>
        </thead>
        <tbody id="pendaftaranTableBody">
          <?php if (empty($list)): ?>
            <tr>
              <td colspan="8" style="text-align:center;padding:36px;color:#94a3b8;">
                <i class="fas fa-clipboard-user" style="font-size:28px;color:#cbd5e1;display:block;margin-bottom:8px;"></i>
                Belum ada pasien yang terdaftar pada filter ini.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($list as $i => $k): 
              $is_bpjs = ($k['kd_pj'] === 'BPJ' || str_contains(strtolower($k['nm_penjab'] ?? ''), 'bpjs'));
              $has_daftar_pcare = !empty($k['no_urut_pcare']);
              $has_rujukan = !empty($k['no_rujukan_sub']) || !empty($k['no_rujukan_khusus']);
              $has_kunj_pcare = !empty($k['no_kunjungan_pcare']) || $has_rujukan;
              $no_rujukan = $k['no_rujukan_sub'] ?: ($k['no_rujukan_khusus'] ?: '');
              $data_json = htmlspecialchars(json_encode($k), ENT_QUOTES, 'UTF-8');
            ?>
              <tr id="row-<?= htmlspecialchars($k['no_rawat']) ?>">
                <td style="text-align:center;font-weight:700;color:var(--primary-700);font-size:13px;padding:9px 10px;">
                  <?= htmlspecialchars($k['no_reg'] ?: sprintf('%03d', $i+1)) ?>
                </td>
                <td style="padding:9px 12px;">
                  <div style="display:flex;align-items:center;gap:10px;">
                    <span style="width:8px;height:8px;border-radius:50%;background:#f59e0b;box-shadow:0 0 0 2px #fef3c7;display:inline-block;flex-shrink:0;"></span>
                    <div>
                      <div style="font-size:13px;font-weight:700;color:#0f172a;line-height:1.2;"><?= htmlspecialchars($k['nm_pasien']) ?> <?= icon_jk($k['jk']) ?></div>
                      <div style="font-size:11px;color:#64748b;margin-top:3px;">
                        MR: <strong style="color:#0f766e;"><?= $k['no_rkm_medis'] ?></strong> &bull; <?= hitung_umur($k['tgl_lahir']) ?>
                      </div>
                      <?php if (!empty($k['alamat'])): ?>
                        <div style="font-size:11px;color:#475569;margin-top:3px;display:flex;align-items:center;gap:4px;">
                          <i class="fas fa-map-marker-alt" style="color:#0891b2;font-size:10px;flex-shrink:0;"></i>
                          <span style="max-width:240px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?= htmlspecialchars($k['alamat']) ?>"><?= htmlspecialchars($k['alamat']) ?></span>
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>
                </td>
                <td style="padding:9px 12px;">
                  <div style="font-size:12.5px;font-weight:600;color:#0f172a;"><?= htmlspecialchars($k['nm_poli'] ?: '-') ?></div>
                  <div style="font-size:11px;color:#64748b;margin-top:2px;"><?= htmlspecialchars($k['nm_dokter'] ?: '-') ?></div>
                </td>
                <td style="padding:9px 12px;">
                  <?= badge_penjab($k['nm_penjab'] ?? 'Umum', $is_bpjs ? ($k['no_peserta'] ?? null) : null) ?>
                </td>
                <td style="font-size:11.5px;color:#475569;font-weight:600;padding:9px 10px;">
                  <i class="fas fa-clock" style="font-size:10px;color:#94a3b8;margin-right:2px;"></i> <?= substr($k['jam_reg'],0,5) ?>
                </td>
                <td style="padding:9px 10px;"><?= badge_status($k['stts']) ?></td>
                <td style="padding:9px 10px;">
                  <?php if ($is_bpjs): ?>
                    <div style="display:flex;flex-direction:column;gap:3px;">
                      <?php if ($has_daftar_pcare): ?>
                        <div style="display:flex;align-items:center;gap:4px;">
                          <span style="font-size:10px;font-weight:700;color:#0369a1;background:#e0f2fe;padding:2px 6px;border-radius:4px;display:inline-flex;align-items:center;gap:3px;" title="Terdaftar di PCare BPJS">
                            <i class="fas fa-id-badge"></i> Urut #<?= htmlspecialchars($k['no_urut_pcare']) ?>
                          </span>
                        </div>
                      <?php endif; ?>

                      <!-- Kunjungan PCare -->
                      <div style="display:flex;align-items:center;gap:4px;">
                        <?php if ($has_kunj_pcare): ?>
                          <span style="font-size:10px;font-weight:700;color:#047857;background:#d1fae5;padding:2px 6px;border-radius:4px;display:inline-flex;align-items:center;gap:3px;" title="Kunjungan PCare Terkirim">
                            <i class="fas fa-check-circle"></i> Kunjungan OK
                          </span>
                        <?php else: ?>
                          <button type="button" class="btn btn-sm btn-outline-primary" style="padding:2px 6px;font-size:10.5px;white-space:nowrap;" title="Kirim Kunjungan / SOAP ke PCare BPJS" onclick="kirimKunjunganPcareRow('<?= htmlspecialchars($k['no_rawat']) ?>')">
                            <i class="fas fa-cloud-arrow-up"></i> Kirim Kunjungan PCare
                          </button>
                        <?php endif; ?>
                      </div>

                      <!-- Rujukan RS (Opsional) -->
                      <div style="display:flex;align-items:center;gap:4px;">
                        <?php if ($has_rujukan): ?>
                          <a href="<?= BASE_URL ?>modules/pcare/cetak_rujukan.php?no_rawat=<?= urlencode($k['no_rawat']) ?>" target="_blank" class="btn btn-sm btn-outline-success" style="padding:2px 6px;font-size:10.5px;white-space:nowrap;" title="Cetak Surat Rujukan BPJS">
                            <i class="fas fa-print"></i> Cetak Rujuk
                          </a>
                        <?php else: ?>
                          <button type="button" class="btn btn-sm btn-outline-secondary" style="padding:2px 6px;font-size:10.5px;white-space:nowrap;" title="Buat & Kirim Surat Rujukan BPJS" onclick='bukaModalRujukan(<?= $data_json ?>)'>
                            <i class="fas fa-share-nodes"></i> Rujuk RS
                          </button>
                        <?php endif; ?>
                      </div>
                    </div>
                  <?php else: ?>
                    <span style="font-size:12px;color:#cbd5e1;font-weight:600;display:block;text-align:center;">-</span>
                  <?php endif; ?>
                </td>
                <td style="text-align:right;padding:9px 14px;">
                  <div style="display:inline-flex;align-items:center;gap:4px;">
                    <a href="<?= BASE_URL ?>modules/rekam_medis/periksa.php?no_rawat=<?= urlencode($k['no_rawat']) ?>"
                       class="btn btn-sm <?= $k['stts']==='Sudah' ? 'btn-outline-primary' : 'btn-primary' ?>"
                       style="padding:5px 10px;font-size:11.5px;display:inline-flex;align-items:center;gap:4px;" title="Periksa Pasien">
                      <i class="fas fa-stethoscope"></i> <?= $k['stts']==='Sudah' ? 'EMR' : 'Periksa' ?>
                    </a>
                    <button type="button" class="btn btn-sm btn-secondary" style="padding:5px 8px;font-size:11.5px;color:#0f766e;" title="Edit Pendaftaran"
                            onclick="openFormEdit('<?= htmlspecialchars($k['no_rawat']) ?>')">
                      <i class="fas fa-edit"></i>
                    </button>
                    <a href="<?= BASE_URL ?>modules/pasien/detail.php?rm=<?= urlencode($k['no_rkm_medis']) ?>"
                       class="btn btn-sm btn-secondary" style="padding:5px 8px;font-size:11.5px;" title="Profil Pasien">
                      <i class="fas fa-user"></i>
                    </a>
                    <button type="button" class="btn btn-sm btn-secondary" style="padding:5px 8px;font-size:11.5px;color:#4f46e5;" title="General Consent & Tanda Tangan Digital" onclick="bukaModalGeneralConsent('<?= htmlspecialchars($k['no_rawat']) ?>')">
                      <i class="fas fa-file-signature"></i>
                    </button>
                    <?php if ($k['stts'] !== 'Batal' && $k['stts'] !== 'Sudah'): ?>
                      <button type="button" class="btn btn-sm btn-outline" style="padding:5px 8px;font-size:11.5px;color:#ef4444;border-color:#fca5a5;" title="Batalkan Kunjungan"
                              onclick="ubahStatus('<?= $k['no_rawat'] ?>', 'batal')">
                        <i class="fas fa-times"></i>
                      </button>
                    <?php endif; ?>
                    <?php if ($k['stts'] === 'Batal' || $k['stts'] === 'Belum'): ?>
                      <button type="button" class="btn btn-sm btn-outline" style="padding:5px 8px;font-size:11.5px;color:#dc2626;border-color:#dc2626;background:#fff5f5;" title="Hapus Registrasi Permanen"
                              onclick="hapusRegistrasi('<?= htmlspecialchars($k['no_rawat']) ?>', '<?= htmlspecialchars($k['nm_pasien']) ?>')">
                        <i class="fas fa-trash-alt"></i>
                      </button>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?= render_pagination($pag) ?>
  </div>
</div>

<script>
// ─── State Form Expand / Collapse ─────────────────────────────
let isFormOpen = false;
let searchTimer = null;

function toggleFormPendaftaran() {
  if (isFormOpen) {
    closeFormPendaftaran();
  } else {
    openFormTambah();
  }
}

function openFormTambah() {
  isFormOpen = true;
  const container = document.getElementById('pendaftaranFormContainer');
  const btnText = document.getElementById('toggleText');
  const btnIcon = document.getElementById('toggleIcon');
  const badge = document.getElementById('formModeBadge');
  const title = document.getElementById('formHeaderTitle');
  const submitBtn = document.getElementById('btnSubmitForm');

  document.getElementById('formMode').value = 'tambah';
  title.innerText = 'Formulir Pendaftaran Pasien';
  badge.className = 'badge badge-primary';
  badge.innerText = 'Pendaftaran Baru';
  submitBtn.innerHTML = '<i class="fas fa-save"></i> Simpan Pendaftaran';

  // Reset fields
  clearSelectedPasien();
  document.getElementById('formTglReg').value = '<?= date('Y-m-d') ?>';
  document.getElementById('formJamReg').value = new Date().toTimeString().split(' ')[0];
  document.getElementById('formSelectPoli').value = '';
  document.getElementById('formSelectDokter').innerHTML = '<option value="">— Pilih Poliklinik Dulu —</option>';
  document.getElementById('formNoPeserta').value = '';

  // Get auto new No Rawat & No Reg
  fetchNewNoreg();

  container.classList.add('is-expanded');
  btnText.innerText = 'Tutup Form';
  btnIcon.className = 'fas fa-chevron-up';

  setTimeout(() => {
    document.getElementById('formSearchPasien').focus();
    container.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }, 120);
}

function openFormEdit(noRawat) {
  fetch(`<?= BASE_URL ?>modules/pendaftaran/index.php?action=get_detail&no_rawat=${encodeURIComponent(noRawat)}`)
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        const d = res.data;
        isFormOpen = true;
        const container = document.getElementById('pendaftaranFormContainer');
        const btnText = document.getElementById('toggleText');
        const btnIcon = document.getElementById('toggleIcon');
        const badge = document.getElementById('formModeBadge');
        const title = document.getElementById('formHeaderTitle');
        const submitBtn = document.getElementById('btnSubmitForm');

        document.getElementById('formMode').value = 'edit';
        title.innerText = 'Edit Data Pendaftaran Pasien';
        badge.className = 'badge badge-warning';
        badge.innerText = 'Mode Edit';
        submitBtn.innerHTML = '<i class="fas fa-save"></i> Simpan Perubahan';

        // Populate fields
        document.getElementById('formNoRawat').value = d.no_rawat;
        document.getElementById('formNoReg').value = d.no_reg;
        document.getElementById('formTglReg').value = d.tgl_registrasi;
        document.getElementById('formJamReg').value = d.jam_reg;
        document.getElementById('formSelectPoli').value = d.kd_poli;
        document.getElementById('formSelectPj').value = d.kd_pj;
        document.getElementById('formNoPeserta').value = d.no_peserta || '';

        // Select Pasien Pill
        selectPasienItem({
          no_rkm_medis: d.no_rkm_medis,
          nm_pasien: d.nm_pasien,
          umur: d.umur || '',
          kd_pj: d.kd_pj,
          no_peserta: d.no_peserta,
          alamat: d.alamat || ''
        });

        // Load doctors for this poli and select existing doctor
        fetchDokterByPoli(d.kd_poli, d.kd_dokter);

        container.classList.add('is-expanded');
        btnText.innerText = 'Tutup Form';
        btnIcon.className = 'fas fa-chevron-up';

        setTimeout(() => {
          container.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 120);
      } else {
        alert(res.message || 'Gagal mengambil data pendaftaran');
      }
    });
}

function closeFormPendaftaran() {
  isFormOpen = false;
  const container = document.getElementById('pendaftaranFormContainer');
  const btnText = document.getElementById('toggleText');
  const btnIcon = document.getElementById('toggleIcon');

  container.classList.remove('is-expanded');
  btnText.innerText = 'Daftar Pasien';
  btnIcon.className = 'fas fa-plus';
}

function fetchNewNoreg() {
  const poli = document.getElementById('formSelectPoli').value;
  const tgl = document.getElementById('formTglReg').value;

  fetch(`<?= BASE_URL ?>modules/pendaftaran/index.php?action=get_new_noreg&kd_poli=${encodeURIComponent(poli)}&tgl=${encodeURIComponent(tgl)}`)
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        if (document.getElementById('formMode').value === 'tambah') {
          document.getElementById('formNoRawat').value = res.no_rawat;
          document.getElementById('formNoReg').value = res.no_reg;
          document.getElementById('formJamReg').value = res.jam;
        }
      }
    });
}

function onDateOrPoliChange() {
  if (document.getElementById('formMode').value === 'tambah') {
    fetchNewNoreg();
  }
}

function onPoliChange(kd_poli) {
  onDateOrPoliChange();
  fetchDokterByPoli(kd_poli, '');
}

function fetchDokterByPoli(kd_poli, selectedDokter) {
  const sel = document.getElementById('formSelectDokter');
  if (!kd_poli) {
    sel.innerHTML = '<option value="">— Pilih Poliklinik Dulu —</option>';
    return;
  }
  sel.innerHTML = '<option value="">Memuat dokter...</option>';

  fetch(`<?= BASE_URL ?>modules/pasien/ajax.php?action=dokter&kd_poli=${encodeURIComponent(kd_poli)}`, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(r => r.json())
  .then(data => {
    if (data.data && data.data.length > 0) {
      sel.innerHTML = '<option value="">— Pilih Dokter —</option>' +
        data.data.map(d => `<option value="${d.kd_dokter}" ${d.kd_dokter===selectedDokter?'selected':''}>${d.nm_dokter}${d.nm_spesialis?' ('+d.nm_spesialis+')':''}</option>`).join('');
    } else {
      sel.innerHTML = '<option value="">Tidak ada dokter di poli ini</option>';
    }
  });
}

// ─── Autocomplete Pasien ──────────────────────────────────────
function onSearchPasien(query) {
  clearTimeout(searchTimer);
  const dropdown = document.getElementById('pasienDropdownResults');
  if (!query || query.trim().length < 1) {
    dropdown.style.display = 'none';
    dropdown.innerHTML = '';
    return;
  }

  searchTimer = setTimeout(() => {
    fetch(`<?= BASE_URL ?>modules/pasien/ajax.php?action=cari&q=${encodeURIComponent(query)}`, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(res => {
      if (res.success && res.data && res.data.length > 0) {
        dropdown.innerHTML = res.data.map(p => `
          <div style="padding:8px 12px;border-bottom:1px solid #f1f5f9;cursor:pointer;display:flex;justify-content:space-between;align-items:center;gap:10px;"
               onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='#fff'"
               onclick='selectPasienItem(${JSON.stringify(p)})'>
            <div style="flex:1;min-width:0;">
              <strong style="color:#0f172a;font-size:13px;">${p.nm_pasien}</strong>
              <div style="font-size:11px;color:#64748b;margin-top:2px;">No. RM: <b>${p.no_rkm_medis}</b> &bull; ${p.umur||''} &bull; ${p.no_ktp||'-'}</div>
              ${p.alamat ? `<div style="font-size:11px;color:#475569;margin-top:3px;display:flex;align-items:center;gap:4px;"><i class="fas fa-map-marker-alt" style="color:#0891b2;font-size:10px;flex-shrink:0;"></i> <span style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${p.alamat}</span></div>` : ''}
            </div>
            <span class="badge badge-light" style="font-size:10.5px;flex-shrink:0;">${p.nm_penjab||'Umum'}</span>
          </div>
        `).join('');
        dropdown.style.display = 'block';
      } else {
        dropdown.innerHTML = '<div style="padding:10px 14px;color:#94a3b8;font-size:12px;">Pasien tidak ditemukan.</div>';
        dropdown.style.display = 'block';
      }
    });
  }, 250);
}

function selectPasienItem(p) {
  document.getElementById('formNoRm').value = p.no_rkm_medis;
  document.getElementById('pillNamaPasien').innerText = p.nm_pasien;
  document.getElementById('pillRmPasien').innerText = `(RM: ${p.no_rkm_medis})`;
  document.getElementById('pillUmurPasien').innerText = p.umur ? `• ${p.umur}` : '';

  const elAlamat = document.getElementById('pillAlamatPasien');
  if (elAlamat) {
    if (p.alamat) {
      elAlamat.innerHTML = `<i class="fas fa-map-marker-alt" style="color:#0891b2;font-size:10px;margin-right:4px;"></i>${p.alamat}`;
      elAlamat.style.display = 'block';
    } else {
      elAlamat.style.display = 'none';
      elAlamat.innerHTML = '';
    }
  }

  if (p.kd_pj) document.getElementById('formSelectPj').value = p.kd_pj;
  if (p.no_peserta) document.getElementById('formNoPeserta').value = p.no_peserta;

  document.getElementById('formSearchPasien').style.display = 'none';
  document.getElementById('formSelectedPasienPill').style.display = 'flex';
  document.getElementById('pasienDropdownResults').style.display = 'none';
}

function clearSelectedPasien() {
  document.getElementById('formNoRm').value = '';
  document.getElementById('formSearchPasien').value = '';
  const elAlamat = document.getElementById('pillAlamatPasien');
  if (elAlamat) {
    elAlamat.style.display = 'none';
    elAlamat.innerHTML = '';
  }
  document.getElementById('formSearchPasien').style.display = 'block';
  document.getElementById('formSelectedPasienPill').style.display = 'none';
  document.getElementById('pasienDropdownResults').style.display = 'none';
}

// ─── Ubah Status (Batal, dll) ────────────────────────────────
function ubahStatus(noRawat, statusBaru) {
  const labelMap = { batal: 'membatalkan' };
  const label = labelMap[statusBaru] || statusBaru;
  if (!confirm(`Yakin ingin ${label} kunjungan No. Rawat ${noRawat}?`)) return;

  const fd = new FormData();
  fd.append('action', statusBaru);
  fd.append('no_rawat', noRawat);

  fetch('<?= BASE_URL ?>modules/pendaftaran/index.php', {
    method: 'POST',
    body: fd
  })
  .then(r => r.json())
  .then(res => {
    if (res.success) {
      showToast('Status kunjungan berhasil diperbarui.', 'success');
      pollLivePendaftaran();
    } else {
      showToast(res.message || 'Gagal mengubah status.', 'danger');
    }
  })
  .catch(err => showToast('Terjadi kesalahan jaringan: ' + err, 'danger'));
}

// ─── Hapus Registrasi Permanen ────────────────────────────────
function hapusRegistrasi(noRawat, nmPasien) {
  if (!confirm(`⚠️ HAPUS REGISTRASI PERMANEN\n\nPasien : ${nmPasien}\nNo. Rawat : ${noRawat}\n\nTindakan ini TIDAK DAPAT DIBATALKAN.\nData registrasi dan antrian akan dihapus.\n\nLanjutkan penghapusan?`)) return;

  // Konfirmasi kedua
  if (!confirm(`Konfirmasi terakhir: hapus registrasi ${noRawat} untuk ${nmPasien}?`)) return;

  const fd = new FormData();
  fd.append('action', 'hapus_registrasi');
  fd.append('no_rawat', noRawat);

  fetch('<?= BASE_URL ?>modules/pendaftaran/index.php', {
    method: 'POST',
    body: fd
  })
  .then(r => r.json())
  .then(res => {
    if (res.success) {
      showToast(res.message || 'Registrasi berhasil dihapus.', 'success');
      // Hapus baris dari tabel secara langsung
      const row = document.getElementById('row-' + noRawat);
      if (row) {
        row.style.transition = 'opacity 0.4s, transform 0.4s';
        row.style.opacity = '0';
        row.style.transform = 'translateX(30px)';
        setTimeout(() => { row.remove(); pollLivePendaftaran(); }, 420);
      } else {
        pollLivePendaftaran();
      }
    } else {
      showToast(res.message || 'Gagal menghapus registrasi.', 'danger');
    }
  })
  .catch(err => showToast('Terjadi kesalahan jaringan: ' + err, 'danger'));
}

// ─── Kirim Antrean Manual ke BPJS Sebelum Simpan ───────────────
function kirimAntreanSebelumSimpan(btn) {
  const form = document.getElementById('formPendaftaranInline');
  const noRm = document.getElementById('formNoRm').value;
  const kdPoli = document.getElementById('formSelectPoli').value;
  const kdDok = document.getElementById('formSelectDokter').value;

  if (!noRm) {
    alert('Silakan cari dan pilih data pasien terlebih dahulu!');
    return;
  }
  if (!kdPoli) {
    alert('Silakan pilih poliklinik tujuan terlebih dahulu!');
    return;
  }
  if (!kdDok) {
    alert('Silakan pilih dokter terlebih dahulu!');
    return;
  }

  const origHtml = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengirim ke BPJS...';

  const fd = new FormData(form);
  fd.set('action', 'kirim_antrean_manual_form');

  fetch('<?= BASE_URL ?>modules/pendaftaran/index.php', {
    method: 'POST',
    body: fd
  })
  .then(r => r.json())
  .then(res => {
    btn.disabled = false;
    btn.innerHTML = origHtml;

    if (res.success) {
      alert(res.message);
      const badge = document.getElementById('badgeStatusAntreanBpjsForm');
      const text = document.getElementById('textNoUrutBpjsForm');
      if (badge && text) {
        text.innerText = '#' + res.no_urut;
        badge.style.display = 'inline-flex';
      }
    } else {
      alert(res.message || 'Gagal mengirim antrean ke BPJS.');
    }
  })
  .catch(err => {
    btn.disabled = false;
    btn.innerHTML = origHtml;
    alert('Terjadi kesalahan koneksi: ' + err);
  });
}

// ─── Submit Form via AJAX ─────────────────────────────────────
function submitPendaftaranForm(e) {
  e.preventDefault();
  const form = document.getElementById('formPendaftaranInline');
  const fd = new FormData(form);

  fetch('<?= BASE_URL ?>modules/pendaftaran/index.php', {
    method: 'POST',
    body: fd
  })
  .then(r => r.json())
  .then(res => {
    if (res.success) {
      showToast(res.message || 'Pendaftaran berhasil disimpan', 'success');
      closeFormPendaftaran();
      pollLivePendaftaran(); // Refresh antrian tabel langsung
    } else {
      alert(res.message || 'Gagal menyimpan pendaftaran');
    }
  })
  .catch(err => alert('Terjadi kesalahan jaringan: ' + err));
}

// ─── Real-Time Live Background Polling ────────────────────────
function pollLivePendaftaran() {
  const filterTgl = '<?= urlencode($tgl) ?>';
  const filterPoli = '<?= urlencode($kd_poli) ?>';
  const filterStatus = '<?= urlencode($status) ?>';
  const filterQ = '<?= urlencode($search) ?>';

  fetch(`<?= BASE_URL ?>modules/pendaftaran/index.php?action=get_live_pendaftaran&tgl=${filterTgl}&kd_poli=${filterPoli}&status=${filterStatus}&q=${filterQ}`, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(r => r.json())
  .then(res => {
    if (res.success) {
      const tbody = document.getElementById('pendaftaranTableBody');
      const countEl = document.getElementById('totalDaftarText');

      if (countEl) countEl.innerHTML = `Total: <strong>${res.count}</strong> pasien`;
      if (tbody) tbody.innerHTML = res.html;
    }
  })
  .catch(err => console.debug('Live sync pendaftaran:', err));
}

// ─── Bridging PCare: Tarik & Sinkronkan Semua Antrean Hari Ini ───
function sinkronSemuaPcare() {
  const icon = document.getElementById('iconSyncPcare');
  if (icon) icon.classList.add('fa-spin');
  showToast('Menghubungi PCare BPJS untuk menarik data antrean hari ini...', 'info');

  fetch('<?= BASE_URL ?>modules/pcare/ajax.php?action=sinkron_semua_pcare&tgl=<?= urlencode($tgl) ?>', {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(r => r.json())
  .then(res => {
    if (icon) icon.classList.remove('fa-spin');
    if (res.success) {
      showToast(res.message, 'success');
      pollLivePendaftaran();
    } else {
      showToast(res.message || 'Gagal sinkron antrean PCare', 'danger');
    }
  })
  .catch(err => {
    if (icon) icon.classList.remove('fa-spin');
    showToast('Terjadi kesalahan saat sinkronisasi PCare: ' + err, 'danger');
  });
}

// ─── Bridging PCare: Kirim Pendaftaran Pasien (Step 1) ─────
function kirimPendaftaranPcareRow(no_rawat) {
  if (!confirm(`Kirim pendaftaran pasien No. Rawat ${no_rawat} ke PCare BPJS (Step 1)?`)) return;
  showToast('Mendaftarkan pasien ke PCare BPJS...', 'info');

  const fd = new FormData();
  fd.append('action', 'kirim_pendaftaran');
  fd.append('no_rawat', no_rawat);

  fetch('<?= BASE_URL ?>modules/pcare/ajax.php', {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    body: fd
  })
  .then(r => r.json())
  .then(res => {
    if (res.success) {
      showToast(res.message || 'Pendaftaran berhasil dikirim ke PCare BPJS', 'success');
      pollLivePendaftaran();
    } else {
      showToast(res.message || 'Gagal mendaftarkan pasien ke PCare BPJS', 'danger');
    }
  })
  .catch(err => {
    showToast('Terjadi kesalahan jaringan saat mendaftar PCare: ' + err, 'danger');
  });
}

// ─── Bridging PCare: Kirim Kunjungan Cepat (Step 2) ─────────
function kirimKunjunganPcareRow(no_rawat) {
  if (!confirm(`Kirim data kunjungan / SOAP pasien No. Rawat ${no_rawat} ke PCare BPJS (Step 2)?`)) return;
  showToast('Mengirim kunjungan ke PCare BPJS...', 'info');

  const fd = new FormData();
  fd.append('action', 'kirim_kunjungan');
  fd.append('no_rawat', no_rawat);

  fetch('<?= BASE_URL ?>modules/pcare/ajax.php', {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    body: fd
  })
  .then(r => r.json())
  .then(res => {
    if (res.success) {
      showToast(res.message, 'success');
      pollLivePendaftaran();
    } else {
      showToast(res.message || 'Gagal kirim kunjungan ke PCare', 'danger');
    }
  })
  .catch(err => {
    showToast('Terjadi kesalahan jaringan saat bridging PCare: ' + err, 'danger');
  });
}

// ─── Modal Rujukan Logic ──────────────────────────────────────
function bukaModalRujukan(v) {
  const modal = document.getElementById('modalBuatRujukan');
  if (!modal) return;

  // Isi data identitas
  document.getElementById('rujukNoRawat').value     = v.no_rawat || '';
  document.getElementById('rujukNoRm').value        = v.no_rkm_medis || '';
  document.getElementById('rujukNmPasien').value    = v.nm_pasien || '';
  document.getElementById('rujukNoKartu').value     = v.no_peserta || v.no_ktp || '';
  document.getElementById('rujukTglDaftar').value   = v.tgl_registrasi || '<?= date('Y-m-d') ?>';
  document.getElementById('rujukKdPoli').value      = v.kd_poli || '001';
  document.getElementById('rujukKdDokter').value    = v.kd_dokter || '';

  // Pemeriksaan fisik & diagnosa
  document.getElementById('rujukKeluhan').value     = v.keluhan || '';
  document.getElementById('rujukKdDiag1').value     = v.kd_diag_utama || '';
  document.getElementById('rujukNmDiag1').value     = v.nm_diag_utama || '';
  document.getElementById('rujukSistole').value     = v.tensi ? (v.tensi.split('/')[0] || 120) : 120;
  document.getElementById('rujukDiastole').value    = v.tensi ? (v.tensi.split('/')[1] || 80) : 80;
  document.getElementById('rujukHeartRate').value   = v.nadi || 80;
  document.getElementById('rujukRespRate').value    = v.respirasi || 20;
  document.getElementById('rujukTinggi').value      = v.tinggi || 165;
  document.getElementById('rujukBerat').value       = v.berat || 60;

  // Reset RS Terpilih
  document.getElementById('rujukKdPPK').value = '';
  document.getElementById('rujukNmPPK').value = '';
  document.getElementById('boxFaskesTerpilih').style.display = 'none';
  document.getElementById('boxHasilFaskes').style.display = 'none';

  modal.style.display = 'flex';
  loadMasterRujukan();
}

function tutupModalRujukan() {
  const modal = document.getElementById('modalBuatRujukan');
  if (modal) modal.style.display = 'none';
}

function toggleJenisRujukan() {
  const jenis = document.getElementById('rujukJenis').value;
  const boxSp = document.getElementById('boxPilihSpesialis');
  const boxSub = document.getElementById('boxPilihSubspesialis');
  const boxKh = document.getElementById('boxPilihKhusus');
  const boxCatKh = document.getElementById('boxCatatanKhusus');

  if (jenis === 'khusus') {
    boxSp.style.display = 'none';
    boxSub.style.display = 'none';
    boxKh.style.display = 'block';
    boxCatKh.style.display = 'block';
    loadKhususDropdown();
  } else {
    boxSp.style.display = 'block';
    boxSub.style.display = 'block';
    boxKh.style.display = 'none';
    boxCatKh.style.display = 'none';
  }
}

function onTaccChange() {
  const tacc = document.getElementById('rujukTacc');
  const opt = tacc.options[tacc.selectedIndex];
  document.getElementById('rujukNmTacc').value = opt ? opt.text : '';
}

let cachedSpesialis = [];
let cachedKhusus = [];

function loadMasterRujukan() {
  fetch(`<?= BASE_URL ?>modules/pcare/ajax.php?action=get_spesialis`, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(r => r.json())
  .then(res => {
    const list = res.data?.response?.list || [];
    cachedSpesialis = list;
    const sel = document.getElementById('rujukSpesialis');
    sel.innerHTML = '';
    list.forEach((s, idx) => {
      const selected = (s.kdSpesialis === 'INT' || idx === 0) ? 'selected' : '';
      sel.innerHTML += `<option value="${s.kdSpesialis}" ${selected}>${s.nmSpesialis}</option>`;
    });

    onSpesialisChange();
  })
  .catch(() => {
    onSpesialisChange();
  });
}

function loadKhususDropdown() {
  if (cachedKhusus.length > 0) return;
  fetch(`<?= BASE_URL ?>modules/pcare/ajax.php?action=get_khusus`, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(r => r.json())
  .then(res => {
    const list = res.data?.response?.list || [];
    cachedKhusus = list;
    const sel = document.getElementById('rujukKhusus');
    sel.innerHTML = '';
    list.forEach(k => {
      sel.innerHTML += `<option value="${k.kdKhusus}">${k.nmKhusus}</option>`;
    });
  });
}

function onSpesialisChange() {
  const kdSp = document.getElementById('rujukSpesialis').value || 'INT';
  const selSub = document.getElementById('rujukSubSpesialis');
  selSub.innerHTML = '<option value="">Memuat Subspesialis...</option>';

  fetch(`<?= BASE_URL ?>modules/pcare/ajax.php?action=get_subspesialis&kd_spesialis=${encodeURIComponent(kdSp)}`, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(r => r.json())
  .then(res => {
    const list = res.data?.response?.list || [];
    selSub.innerHTML = '';
    if (list.length === 0) {
      selSub.innerHTML = `<option value="${kdSp}">${kdSp} (Umum)</option>`;
    } else {
      list.forEach(sub => {
        selSub.innerHTML += `<option value="${sub.kdSubSpesialis}">${sub.nmSubSpesialis} [${sub.kdSubSpesialis}]</option>`;
      });
    }
  })
  .catch(() => {
    selSub.innerHTML = `<option value="${kdSp}">${kdSp} (Umum)</option>`;
  });
}

// ─── Inline ICD-10 Live Search Autocomplete Inside Modal ───────
let diagDebounceTimer = null;

function onDiagInputLive(val) {
  clearTimeout(diagDebounceTimer);
  const q = val.trim();
  if (q.length < 2) {
    document.getElementById('modalDiagDropdown').style.display = 'none';
    return;
  }

  diagDebounceTimer = setTimeout(() => {
    lakukanPencarianDiagnosaModal(q);
  }, 250);
}

function toggleDiagDropdown() {
  const q = document.getElementById('rujukKdDiag1').value.trim();
  if (!q) {
    showToast('Ketik kode atau nama diagnosa pada kolom input', 'info');
    document.getElementById('rujukKdDiag1').focus();
    return;
  }
  lakukanPencarianDiagnosaModal(q);
}

function lakukanPencarianDiagnosaModal(q) {
  const dropdown = document.getElementById('modalDiagDropdown');
  const container = document.getElementById('modalDiagResults');
  
  dropdown.style.display = 'block';
  container.innerHTML = '<div style="text-align:center;padding:10px;color:#0284c7;"><i class="fas fa-spinner fa-spin"></i> Mencari diagnosa ICD-10 di BPJS &amp; Database...</div>';

  fetch(`<?= BASE_URL ?>modules/pcare/ajax.php?action=cari_diagnosa&q=${encodeURIComponent(q)}`, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(r => r.json())
  .then(res => {
    const list = res.data?.response?.list || [];
    container.innerHTML = '';

    if (list.length === 0) {
      container.innerHTML = '<div style="text-align:center;padding:12px;color:#94a3b8;font-size:12px;">Diagnosa tidak ditemukan. Coba ketik kode atau nama lainnya.</div>';
      return;
    }

    list.forEach(item => {
      const row = document.createElement('div');
      row.style.cssText = 'display:flex;align-items:center;justify-content:space-between;padding:8px 10px;border-radius:6px;cursor:pointer;border-bottom:1px solid #f1f5f9;transition:all 0.15s;';
      row.onmouseenter = () => { row.style.background = '#f0f9ff'; };
      row.onmouseleave = () => { row.style.background = 'transparent'; };
      
      const badgeSource = item.source === 'pcare' ? '<span style="font-size:9px;background:#d1fae5;color:#065f46;padding:1px 5px;border-radius:3px;font-weight:700;">BPJS</span>' : '<span style="font-size:9px;background:#e0f2fe;color:#0369a1;padding:1px 5px;border-radius:3px;font-weight:700;">LOKAL</span>';
      const isTacc = typeof isDiagnosaTACC === 'function' ? isDiagnosaTACC(item.kdDiag) : false;
      const badgeTacc = isTacc ? '<span style="font-size:9.5px;background:#fef3c7;color:#b45309;border:1px solid #fde68a;padding:1px 6px;border-radius:3px;font-weight:800;margin-left:4px;"><i class="fas fa-exclamation-triangle"></i> TACC</span>' : '';

      row.innerHTML = `
        <div style="display:flex;align-items:center;gap:8px;">
          <span style="font-family:monospace;font-weight:800;color:#0284c7;background:#e0f2fe;padding:2px 6px;border-radius:4px;font-size:12px;">${item.kdDiag}</span>
          <span style="font-size:12.5px;font-weight:600;color:#1e293b;">${item.nmDiag}</span>
          ${badgeTacc}
        </div>
        <div>${badgeSource}</div>
      `;

      row.onclick = () => pilihDiag(item.kdDiag, item.nmDiag);
      container.appendChild(row);
    });
  })
  .catch(() => {
    container.innerHTML = '<div style="text-align:center;padding:10px;color:#ef4444;">Gagal memuat hasil pencarian</div>';
  });
}

function pilihDiag(kd, nm) {
  document.getElementById('rujukKdDiag1').value = kd;
  document.getElementById('rujukNmDiag1').value = nm;
  document.getElementById('modalDiagDropdown').style.display = 'none';

  if (typeof isDiagnosaTACC === 'function' && isDiagnosaTACC(kd)) {
    showTaccWarningModal({
      kdDiag: kd,
      nmDiag: nm,
      targetTaccSelectId: 'rujukTacc',
      targetAlasanInputId: 'rujukAlasanTacc'
    });
  } else {
    showToast(`Diagnosa dipilih: [${kd}] ${nm}`, 'info');
  }
}

function cariFaskesRujukan(btn) {
  const jenis = document.getElementById('rujukJenis').value;
  let kdSub = document.getElementById('rujukSubSpesialis').value;
  const kdKh = document.getElementById('rujukKhusus').value;
  const kdSar = document.getElementById('rujukSarana').value || '1';
  const tglRujuk = document.getElementById('rujukTglEst').value || '<?= date('Y-m-d') ?>';

  if (jenis === 'subspesialis' && !kdSub) {
    const kdSp = document.getElementById('rujukSpesialis').value;
    if (kdSp) {
      kdSub = kdSp;
    } else {
      showToast('Pilih Poli Spesialis / Subspesialis terlebih dahulu', 'warning');
      return;
    }
  }
  if (jenis === 'khusus' && !kdKh) {
    showToast('Pilih Kasus Khusus terlebih dahulu', 'warning');
    return;
  }

  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mencari RS...';
  }

  const url = `<?= BASE_URL ?>modules/pcare/ajax.php?action=cari_faskes_rujukan&jenis_rujukan=${jenis}&kd_subspesialis=${encodeURIComponent(kdSub || '')}&kd_khusus=${encodeURIComponent(kdKh || '')}&kd_sarana=${encodeURIComponent(kdSar)}&tgl_rujuk=${encodeURIComponent(tglRujuk)}`;

  fetch(url, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(r => r.json())
  .then(res => {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-search-location"></i> Cari Faskes / RS Rujukan Realtime';
    }

    const box = document.getElementById('boxHasilFaskes');
    const container = document.getElementById('listFaskesCards');
    container.innerHTML = '';

    const list = res.list || [];
    if (!res.success || list.length === 0) {
      showToast(res.message || 'Tidak ada faskes rujukan yang tersedia pada tanggal ini.', 'warning');
      box.style.display = 'none';
      return;
    }

    list.forEach(f => {
      const card = document.createElement('div');
      card.style.cssText = 'background:#ffffff;border:1.5px solid #cbd5e1;border-radius:10px;padding:12px;cursor:pointer;transition:all 0.2s;';
      card.onmouseenter = () => { card.style.borderColor = '#0284c7'; card.style.boxShadow = '0 4px 12px rgba(2,132,199,0.15)'; };
      card.onmouseleave = () => { card.style.borderColor = '#cbd5e1'; card.style.boxShadow = 'none'; };
      
      const persentase = f.persentase || '0%';
      const jadwal = f.jadwal || 'Tersedia';

      card.innerHTML = `
        <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:6px;">
          <strong style="font-size:13px;color:#0f172a;display:block;">${f.nmppk}</strong>
          <span style="font-size:10px;font-weight:800;background:#e0f2fe;color:#0284c7;padding:2px 6px;border-radius:4px;">${f.kelas || 'RS'}</span>
        </div>
        <div style="font-size:11px;color:#64748b;margin-bottom:8px;">${f.alamatPpk || '-'} &bull; Telp: ${f.telpPpk || '-'}</div>
        <div style="display:flex;justify-content:space-between;font-size:11px;background:#f8fafc;padding:6px 8px;border-radius:6px;border:1px solid #e2e8f0;">
          <span>Kapasitas: <strong>${f.jmlRujuk || 0}/${f.kapasitas || 0}</strong></span>
          <span style="color:#059669;font-weight:700;">Kuota: ${persentase}</span>
        </div>
        <div style="margin-top:6px;font-size:10.5px;color:#0369a1;">📅 Jadwal: ${jadwal}</div>
      `;

      card.onclick = () => pilihFaskes(f.kdppk, f.nmppk, f.kelas, f.alamatPpk);
      container.appendChild(card);
    });

    box.style.display = 'block';
    showToast(`Ditemukan ${list.length} Faskes Rujukan`, 'success');
  })
  .catch(err => {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-search-location"></i> Cari Faskes / RS Rujukan Realtime';
    }
    showToast('Gagal mencari faskes rujukan', 'danger');
  });
}

function pilihFaskes(kdppk, nmppk, kelas, alamat) {
  document.getElementById('rujukKdPPK').value = kdppk;
  document.getElementById('rujukNmPPK').value = nmppk;

  document.getElementById('selectedRsNama').textContent = `${nmppk} (${kdppk})`;
  document.getElementById('selectedRsInfo').textContent = `${kelas || ''} &bull; ${alamat || ''}`;

  document.getElementById('boxFaskesTerpilih').style.display = 'block';
  document.getElementById('boxHasilFaskes').style.display = 'none';

  showToast(`RS Tujuan dipilih: ${nmppk}`, 'info');
}

function simpanKirimRujukan(andPrint = false) {
  const form = document.getElementById('formRujukan');
  const kdPPK = document.getElementById('rujukKdPPK').value;
  const kdDiag1 = document.getElementById('rujukKdDiag1').value.trim();

  if (!kdDiag1) { showToast('Diagnosa utama ICD-10 wajib diisi', 'warning'); return; }
  if (!kdPPK) { showToast('Silakan cari dan pilih Rumah Sakit Rujukan terlebih dahulu', 'warning'); return; }

  const selSub = document.getElementById('rujukSubSpesialis');
  if (selSub && selSub.selectedIndex >= 0) document.getElementById('rujukNmSubSpesialis').value = selSub.options[selSub.selectedIndex].text;
  
  const selSar = document.getElementById('rujukSarana');
  if (selSar && selSar.selectedIndex >= 0) document.getElementById('rujukNmSarana').value = selSar.options[selSar.selectedIndex].text;

  const selKh = document.getElementById('rujukKhusus');
  if (selKh && selKh.selectedIndex >= 0) document.getElementById('rujukNmKhusus').value = selKh.options[selKh.selectedIndex].text;

  const selTacc = document.getElementById('rujukTacc');
  if (selTacc && selTacc.selectedIndex >= 0) document.getElementById('rujukNmTacc').value = selTacc.options[selTacc.selectedIndex].text;

  const btn1 = document.getElementById('btnSimpanOnly');
  const btn2 = document.getElementById('btnSimpanPrint');
  btn1.disabled = true;
  btn2.disabled = true;
  btn2.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengirim Rujukan...';

  const formData = new FormData(form);
  formData.append('action', 'simpan_kirim_rujukan');

  fetch(`<?= BASE_URL ?>modules/pcare/ajax.php`, {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    body: formData
  })
  .then(r => r.json())
  .then(res => {
    btn1.disabled = false;
    btn2.disabled = false;
    btn2.innerHTML = '<i class="fas fa-print"></i> Kirim &amp; Cetak Surat Rujukan';

    if (res.success) {
      showToast(res.message, 'success');
      tutupModalRujukan();
      if (andPrint) {
        window.open(`<?= BASE_URL ?>modules/pcare/cetak_rujukan.php?no_rawat=${encodeURIComponent(res.no_rawat)}`, '_blank');
      }
      pollLivePendaftaran();
    } else {
      showToast(res.message || 'Gagal mengirim surat rujukan', 'danger');
    }
  })
  .catch(err => {
    btn1.disabled = false;
    btn2.disabled = false;
    btn2.innerHTML = '<i class="fas fa-print"></i> Kirim &amp; Cetak Surat Rujukan';
    showToast('Terjadi kesalahan saat memproses rujukan', 'danger');
  });
}

function sinkronSemuaPcare() {
  const icon = document.getElementById('iconSyncPcare');
  if (icon) icon.className = 'fas fa-rotate fa-spin';

  const formData = new FormData();
  formData.append('action', 'sinkron_pcare');
  formData.append('tgl', document.querySelector('input[name="tgl"]')?.value || '<?= $tgl ?>');

  fetch('<?= BASE_URL ?>modules/pendaftaran/index.php', {
    method: 'POST',
    body: formData
  })
  .then(r => r.json())
  .then(res => {
    if (icon) icon.className = 'fas fa-rotate';
    if (res.success) {
      showToast(res.message, 'success');
      pollLivePendaftaran();
    } else {
      showToast(res.message || 'Gagal menyinkronkan antrean PCare', 'danger');
    }
  })
  .catch(err => {
    if (icon) icon.className = 'fas fa-rotate';
    showToast('Terjadi kesalahan saat menyinkronkan antrean PCare', 'danger');
  });
}

function kirimPendaftaranPcareRow(noRawat) {
  const formData = new FormData();
  formData.append('action', 'kirim_pendaftaran');
  formData.append('no_rawat', noRawat);

  fetch('<?= BASE_URL ?>modules/pcare/ajax.php', {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    body: formData
  })
  .then(r => r.json())
  .then(res => {
    if (res.success) {
      showToast(res.message, 'success');
      pollLivePendaftaran();
    } else {
      showToast(res.message || 'Gagal mendaftarkan pasien ke PCare', 'danger');
    }
  })
  .catch(err => {
    showToast('Terjadi kesalahan koneksi ke PCare', 'danger');
  });
}

function kirimKunjunganPcareRow(noRawat) {
  const formData = new FormData();
  formData.append('action', 'kirim_kunjungan');
  formData.append('no_rawat', noRawat);

  fetch('<?= BASE_URL ?>modules/pcare/ajax.php', {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    body: formData
  })
  .then(r => r.json())
  .then(res => {
    if (res.success) {
      showToast(res.message, 'success');
      pollLivePendaftaran();
    } else {
      showToast(res.message || 'Gagal mengirim kunjungan ke PCare', 'danger');
    }
  })
  .catch(err => {
    showToast('Terjadi kesalahan koneksi ke PCare', 'danger');
  });
}
</script>

<!-- ─── Modal Pembuatan & Pengiriman Surat Rujukan BPJS PCare ──── -->
<div class="modal-overlay" id="modalBuatRujukan" style="position:fixed;inset:0;top:0;left:0;right:0;bottom:0;width:100vw;height:100vh;background:rgba(15,23,42,0.6);z-index:9999;display:none;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(4px);box-sizing:border-box;">
  <div class="modal-content" style="background:#ffffff;border-radius:14px;max-width:820px;width:100%;max-height:92vh;display:flex;flex-direction:column;box-shadow:0 25px 50px -12px rgba(0,0,0,0.3);overflow:hidden;animation:modalFadeIn 0.2s ease-out;">
    
    <!-- Modal Header -->
    <div style="padding:16px 22px;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;background:linear-gradient(135deg, #f0fdf4 0%, #e0f2fe 100%);">
      <div style="display:flex;align-items:center;gap:12px;">
        <div style="width:38px;height:38px;border-radius:10px;background:#0284c7;color:#ffffff;display:flex;align-items:center;justify-content:center;font-size:18px;">
          <i class="fas fa-paper-plane"></i>
        </div>
        <div>
          <h3 style="font-size:15px;font-weight:800;color:#0f172a;margin:0;">Pembuatan Surat Rujukan FKTP BPJS (PCare)</h3>
          <p style="font-size:11.5px;color:#64748b;margin:0;">Rujuk Vertikal Subspesialis &amp; Khusus Terhubung Realtime ke PCare BPJS</p>
        </div>
      </div>
      <button type="button" onclick="tutupModalRujukan()" style="background:none;border:none;color:#64748b;font-size:20px;cursor:pointer;padding:4px 8px;border-radius:6px;">
        &times;
      </button>
    </div>

    <!-- Modal Body Form -->
    <div style="padding:20px 24px;overflow-y:auto;flex:1;background:#f8fafc;">
      
      <form id="formRujukan">
        <input type="hidden" id="rujukNoRawat" name="no_rawat">
        <input type="hidden" id="rujukNoRm" name="no_rkm_medis">
        <input type="hidden" id="rujukKdPPK" name="kd_ppk">
        <input type="hidden" id="rujukNmPPK" name="nm_ppk">
        <input type="hidden" id="rujukNmSubSpesialis" name="nm_subspesialis">
        <input type="hidden" id="rujukNmKhusus" name="nm_khusus">
        <input type="hidden" id="rujukNmSarana" name="nm_sarana" value="Rawat Jalan">
        <input type="hidden" id="rujukNmTacc" name="nm_tacc" value="Tanpa TACC">

        <!-- 1. DATA IDENTITAS PASIEN -->
        <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;padding:16px 18px;">
          <div style="font-size:12.5px;font-weight:800;color:#0369a1;margin-bottom:12px;text-transform:uppercase;display:flex;align-items:center;gap:6px;">
            <i class="fas fa-id-card"></i> 1. Identitas Pasien &amp; Kunjungan
          </div>
          <div style="display:grid;grid-template-columns:1.5fr 1fr 1fr;gap:12px;">
            <div class="form-group">
              <label class="form-label">Nama Pasien</label>
              <input type="text" id="rujukNmPasien" name="nm_pasien" class="form-control" readonly style="font-weight:700;background:#f8fafc;">
            </div>
            <div class="form-group">
              <label class="form-label">Nomor Kartu BPJS</label>
              <input type="text" id="rujukNoKartu" name="no_kartu" class="form-control" readonly style="font-family:monospace;font-weight:700;color:#0284c7;background:#f8fafc;">
            </div>
            <div class="form-group">
              <label class="form-label">Tgl. Daftar Kunjungan</label>
              <input type="date" id="rujukTglDaftar" name="tgl_daftar" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:10px;">
            <div class="form-group">
              <label class="form-label">Poli Klinik</label>
              <select id="rujukKdPoli" name="kd_poli" class="form-control">
                <?php foreach ($poli_list as $pl): ?>
                  <option value="<?= htmlspecialchars($pl['kd_poli']) ?>"><?= htmlspecialchars($pl['nm_poli']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Dokter Pemeriksa</label>
              <select id="rujukKdDokter" name="kd_dokter" class="form-control">
                <?php foreach ($dokter_list as $dl): ?>
                  <option value="<?= htmlspecialchars($dl['kd_dokter']) ?>"><?= htmlspecialchars($dl['nm_dokter']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>

        <!-- 2. PEMERIKSAAN FISIK & DIAGNOSA ICD-10 -->
        <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;padding:16px 18px;margin-top:14px;">
          <div style="font-size:12.5px;font-weight:800;color:#0369a1;margin-bottom:12px;text-transform:uppercase;display:flex;align-items:center;gap:6px;">
            <i class="fas fa-stethoscope"></i> 2. Pemeriksaan Klinis &amp; Diagnosa (ICD-10)
          </div>
          
          <div class="form-group mb-10">
            <label class="form-label">Keluhan Utama</label>
            <textarea id="rujukKeluhan" name="keluhan" class="form-control" rows="2" placeholder="Tuliskan keluhan atau indikasi medis rujukan..."></textarea>
          </div>

          <div style="display:grid;grid-template-columns:repeat(6, 1fr);gap:8px;">
            <div class="form-group">
              <label class="form-label" style="font-size:11px;">Sistole (mmHg)</label>
              <input type="number" id="rujukSistole" name="sistole" class="form-control" value="120">
            </div>
            <div class="form-group">
              <label class="form-label" style="font-size:11px;">Diastole (mmHg)</label>
              <input type="number" id="rujukDiastole" name="diastole" class="form-control" value="80">
            </div>
            <div class="form-group">
              <label class="form-label" style="font-size:11px;">Nadi (x/m)</label>
              <input type="number" id="rujukHeartRate" name="heart_rate" class="form-control" value="80">
            </div>
            <div class="form-group">
              <label class="form-label" style="font-size:11px;">Resp (x/m)</label>
              <input type="number" id="rujukRespRate" name="resp_rate" class="form-control" value="20">
            </div>
            <div class="form-group">
              <label class="form-label" style="font-size:11px;">Tinggi (cm)</label>
              <input type="number" id="rujukTinggi" name="tinggi_badan" class="form-control" value="165">
            </div>
            <div class="form-group">
              <label class="form-label" style="font-size:11px;">Berat (kg)</label>
              <input type="number" id="rujukBerat" name="berat_badan" class="form-control" value="60">
            </div>
          </div>

          <!-- Inline Autocomplete ICD-10 Search Box -->
          <div style="position:relative;">
            <div style="display:grid;grid-template-columns:1.2fr 2fr;gap:12px;margin-top:10px;">
              <div class="form-group" style="position:relative;">
                <label class="form-label" style="display:flex;justify-content:space-between;">
                  <span>Kode ICD-10 Utama *</span>
                  <span style="font-size:10.5px;color:#0284c7;font-weight:normal;">Ketik kode / nama penyakit</span>
                </label>
                <div style="display:flex;gap:6px;">
                  <input type="text" id="rujukKdDiag1" name="kd_diag1" class="form-control" placeholder="Contoh: I10 / Hipertensi / Diare" required style="font-family:monospace;font-weight:700;text-transform:uppercase;" oninput="onDiagInputLive(this.value)" autocomplete="off">
                  <button type="button" class="btn btn-primary" onclick="toggleDiagDropdown()" title="Cari Diagnosa"><i class="fas fa-search"></i> Cari</button>
                </div>
              </div>
              <div class="form-group">
                <label class="form-label">Nama Diagnosa Utama (BPJS)</label>
                <input type="text" id="rujukNmDiag1" name="nm_diag1" class="form-control" placeholder="Nama penyakit terpilih" style="background:#f8fafc;font-weight:600;color:#0369a1;">
              </div>
            </div>

            <!-- Dropdown Popover Hasil Pencarian Diagnosa -->
            <div id="modalDiagDropdown" style="display:none;position:absolute;top:70px;left:0;right:0;background:#ffffff;border:2px solid #0284c7;border-radius:10px;box-shadow:0 18px 40px rgba(0,0,0,0.3);z-index:9999;max-height:260px;overflow-y:auto;padding:8px;">
              <div style="padding:6px 10px;background:#f0f9ff;border-radius:6px;font-size:11px;font-weight:700;color:#0369a1;display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                <span><i class="fas fa-book-medical"></i> Hasil Pencarian Diagnosa ICD-10 (Klik untuk memilih):</span>
                <button type="button" onclick="document.getElementById('modalDiagDropdown').style.display='none'" style="background:none;border:none;color:#64748b;font-weight:bold;cursor:pointer;font-size:13px;">&times; Tutup</button>
              </div>
              <div id="modalDiagResults" style="display:flex;flex-direction:column;gap:4px;">
                <div style="text-align:center;color:#64748b;padding:12px;font-size:11.5px;">Ketik minimal 2 huruf kode atau nama penyakit (contoh: K30, I10, Demam, Diare)...</div>
              </div>
            </div>
          </div>

          <div style="display:grid;grid-template-columns:1fr 2fr;gap:12px;margin-top:8px;">
            <div class="form-group">
              <label class="form-label">Kode ICD-10 Sekunder (Opsional)</label>
              <input type="text" id="rujukKdDiag2" name="kd_diag2" class="form-control" placeholder="ICD-10 Tambahan" style="font-family:monospace;text-transform:uppercase;">
            </div>
            <div class="form-group">
              <label class="form-label">Terapi yang Diberikan</label>
              <input type="text" id="rujukTerapi" name="terapi" class="form-control" placeholder="Terapi awal / obat yang telah diberikan...">
            </div>
          </div>
        </div>

        <!-- 3. TUJUAN RUJUKAN & SPESIALIS -->
        <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;padding:16px 18px;margin-top:14px;">
          <div style="font-size:12.5px;font-weight:800;color:#0369a1;margin-bottom:12px;text-transform:uppercase;display:flex;align-items:center;gap:6px;">
            <i class="fas fa-hospital"></i> 3. Spesialis &amp; Pencarian Rumah Sakit Rujukan
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:10px;margin-bottom:12px;">
            <div class="form-group">
              <label class="form-label">Jenis Rujukan</label>
              <select id="rujukJenis" name="jenis_rujukan" class="form-control" onchange="toggleJenisRujukan()">
                <option value="subspesialis">Rujuk Vertikal (Subspesialis)</option>
                <option value="khusus">Rujukan Kasus Khusus</option>
              </select>
            </div>
            <div class="form-group" id="boxPilihSpesialis">
              <label class="form-label">Poli Spesialis</label>
              <select id="rujukSpesialis" class="form-control" onchange="onSpesialisChange()">
                <option value="">Memuat Spesialis...</option>
              </select>
            </div>
            <div class="form-group" id="boxPilihSubspesialis">
              <label class="form-label">Subspesialis *</label>
              <select id="rujukSubSpesialis" name="kd_subspesialis" class="form-control">
                <option value="">Pilih Spesialis Dulu</option>
              </select>
            </div>
            <div class="form-group" id="boxPilihKhusus" style="display:none;">
              <label class="form-label">Kategori Kasus Khusus</label>
              <select id="rujukKhusus" name="kd_khusus" class="form-control">
                <option value="">Memuat Khusus...</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Sarana RS</label>
              <select id="rujukSarana" name="kd_sarana" class="form-control">
                <option value="1">Rawat Jalan</option>
                <option value="2">Rawat Inap</option>
                <option value="3">IGD</option>
              </select>
            </div>
          </div>

          <div style="display:flex;align-items:center;gap:12px;background:#f0f9ff;padding:12px 14px;border-radius:10px;border:1px solid #bae6fd;margin-bottom:12px;">
            <div style="flex:1;">
              <label class="form-label" style="color:#0369a1;font-weight:700;">Tanggal Rencana Kunjungan Rujuk *</label>
              <input type="date" id="rujukTglEst" name="tgl_est_rujuk" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
            <div style="padding-top:20px;">
              <button type="button" class="btn btn-primary" onclick="cariFaskesRujukan(this)">
                <i class="fas fa-search-location"></i> Cari Faskes / RS Rujukan Realtime
              </button>
            </div>
          </div>

          <!-- Live Result Cards Faskes Rujukan -->
          <div id="boxHasilFaskes" style="display:none;">
            <div style="font-size:12px;font-weight:700;color:#334155;margin-bottom:8px;">
              <i class="fas fa-list"></i> Pilih Rumah Sakit Tujuan:
            </div>
            <div id="listFaskesCards" style="display:grid;grid-template-columns:repeat(auto-fill, minmax(280px, 1fr));gap:10px;max-height:260px;overflow-y:auto;padding-right:4px;">
              <!-- Cards dynamically inserted -->
            </div>
          </div>

          <!-- RS Terpilih Card -->
          <div id="boxFaskesTerpilih" style="display:none;background:#ecfdf5;border:1.5px solid #10b981;padding:12px 16px;border-radius:10px;margin-top:10px;">
            <div style="display:flex;align-items:center;justify-content:space-between;">
              <div>
                <span style="font-size:10px;font-weight:800;color:#047857;background:#d1fae5;padding:2px 8px;border-radius:12px;text-transform:uppercase;">
                  Faskes Rujukan Terpilih
                </span>
                <div id="selectedRsNama" style="font-size:14px;font-weight:800;color:#065f46;margin-top:4px;"></div>
                <div id="selectedRsInfo" style="font-size:11.5px;color:#047857;"></div>
              </div>
              <button type="button" class="btn btn-sm btn-outline-success" onclick="document.getElementById('boxHasilFaskes').style.display='block';">
                <i class="fas fa-pencil"></i> Ganti RS
              </button>
            </div>
          </div>

        </div>

        <!-- 4. TACC & ALASAN RUJUKAN -->
        <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;padding:16px 18px;margin-top:14px;">
          <div style="font-size:12.5px;font-weight:800;color:#0369a1;margin-bottom:12px;text-transform:uppercase;display:flex;align-items:center;gap:6px;">
            <i class="fas fa-clipboard-check"></i> 4. Kriteria TACC (Time, Age, Complication, Comorbidity)
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div class="form-group">
              <label class="form-label">Kategori TACC</label>
              <select id="rujukTacc" name="kd_tacc" class="form-control" onchange="onTaccChange()">
                <option value="0">Tanpa TACC</option>
                <option value="1">Time (Waktu)</option>
                <option value="2">Age (Umur)</option>
                <option value="3">Complication (Komplikasi)</option>
                <option value="4">Comorbidity (Penyakit Penyerta)</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Alasan TACC / Catatan Rujukan</label>
              <input type="text" id="rujukAlasanTacc" name="alasan_tacc" class="form-control" placeholder="Contoh: Memerlukan penanganan spesialis lanjutan">
            </div>
          </div>
          <div class="form-group mt-10" id="boxCatatanKhusus" style="display:none;">
            <label class="form-label">Catatan Rujukan Kasus Khusus</label>
            <input type="text" id="rujukCatatanKhusus" name="catatan_khusus" class="form-control" placeholder="Catatan program rujukan khusus...">
          </div>
        </div>

      </form>

    </div>

    <!-- Modal Footer -->
    <div style="padding:14px 24px;border-top:1px solid #e2e8f0;background:#ffffff;display:flex;justify-content:space-between;align-items:center;">
      <button type="button" class="btn btn-secondary" onclick="tutupModalRujukan()">Batal</button>
      <div style="display:flex;gap:10px;">
        <button type="button" class="btn btn-outline-primary" id="btnSimpanOnly" onclick="simpanKirimRujukan(false)">
          <i class="fas fa-paper-plane"></i> Simpan &amp; Kirim BPJS
        </button>
        <button type="button" class="btn btn-primary" id="btnSimpanPrint" onclick="simpanKirimRujukan(true)">
          <i class="fas fa-print"></i> Kirim &amp; Cetak Surat Rujukan
        </button>
      </div>
    </div>

  </div>
</div>

<!-- ─── MODAL GENERAL CONSENT & DIGITAL SIGNATURE DRAWING PAD (CENTERED & WIDE) ─── -->
<style>
@keyframes gcModalZoomIn {
  from { opacity: 0; transform: scale(0.96); }
  to { opacity: 1; transform: scale(1); }
}
#modalGeneralConsent {
  position: fixed !important;
  inset: 0 !important;
  top: 0 !important;
  left: 0 !important;
  right: 0 !important;
  bottom: 0 !important;
  width: 100vw !important;
  height: 100vh !important;
  background: rgba(15, 23, 42, 0.6) !important;
  backdrop-filter: blur(4px) !important;
  -webkit-backdrop-filter: blur(4px) !important;
  z-index: 99999 !important;
  display: none;
  align-items: center !important;
  justify-content: center !important;
  padding: 20px !important;
  box-sizing: border-box !important;
  box-shadow: none !important;
  border-radius: 0 !important;
  transform: none !important;
  max-width: none !important;
  max-height: none !important;
}
#modalGeneralConsent .gc-wide-modal-box {
  background: #ffffff;
  border-radius: 14px;
  max-width: 1240px;
  width: 95vw;
  height: 90vh;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 25px 50px -12px rgba(0,0,0,0.35);
  border: 1px solid #cbd5e1;
  overflow: hidden;
  animation: gcModalZoomIn 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  margin: auto;
}
.gc-split-grid {
  display: grid;
  grid-template-columns: 1.15fr 1fr;
  gap: 18px;
  align-items: start;
}
@media (max-width: 992px) {
  .gc-split-grid {
    grid-template-columns: 1fr;
  }
  #modalGeneralConsent .gc-wide-modal-box {
    height: 95vh;
    max-height: 95vh;
  }
}
</style>

<div class="modal-overlay" id="modalGeneralConsent">
  <div class="gc-wide-modal-box">
    
    <!-- Modal Header -->
    <div style="padding:16px 24px;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;background:linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);color:#ffffff;flex-shrink:0;">
      <div style="display:flex;align-items:center;gap:12px;">
        <div style="width:40px;height:40px;border-radius:10px;background:rgba(255,255,255,0.2);display:flex;align-items:center;justify-content:center;font-size:20px;">
          <i class="fas fa-file-signature"></i>
        </div>
        <div>
          <h3 style="font-size:16px;font-weight:800;margin:0;color:#ffffff;line-height:1.2;">Persetujuan Umum (General Consent) &amp; Tanda Tangan Digital</h3>
          <p style="font-size:12px;color:rgba(255,255,255,0.85);margin:2px 0 0 0;">Standar Akreditasi Klinik &amp; Rekam Medis Elektronik (Permenkes RI)</p>
        </div>
      </div>
      <button type="button" onclick="tutupModalGeneralConsent()" style="background:rgba(255,255,255,0.15);border:none;color:#ffffff;font-size:22px;cursor:pointer;width:34px;height:34px;border-radius:8px;display:flex;align-items:center;justify-content:center;transition:all 0.15s;" title="Tutup">
        &times;
      </button>
    </div>

    <!-- Modal Body Form (Scrollable Content) -->
    <div style="padding:20px 24px;overflow-y:auto;flex:1;background:#f8fafc;" id="bodyModalGC">
      
      <!-- Loading State -->
      <div id="loaderModalGC" style="text-align:center;padding:60px 20px;color:#0284c7;">
        <i class="fas fa-spinner fa-spin fa-3x"></i>
        <div style="margin-top:14px;font-size:14px;font-weight:700;">Memuat data pasien &amp; persetujuan...</div>
      </div>

      <!-- Main Form Container -->
      <form id="formModalGC" style="display:none;" autocomplete="off">
        <input type="hidden" name="no_rawat" id="mgc_no_rawat">
        <input type="hidden" name="ttd_pasien" id="mgc_input_ttd_pasien">
        <input type="hidden" name="ttd_petugas" id="mgc_input_ttd_petugas">

        <!-- Top Banner Info Pasien -->
        <div style="background:#eff6ff;border:1.5px solid #bfdbfe;border-radius:12px;padding:12px 18px;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
          <div>
            <div style="font-size:16px;font-weight:800;color:#1e3a8a;" id="mgc_lbl_pasien">-</div>
            <div style="font-size:12px;color:#475569;margin-top:3px;display:flex;gap:14px;flex-wrap:wrap;">
              <span><i class="fas fa-id-card text-primary"></i> <strong>No. RM:</strong> <span id="mgc_lbl_rm">-</span></span>
              <span><i class="fas fa-barcode text-primary"></i> <strong>No. Rawat:</strong> <span id="mgc_lbl_rawat">-</span></span>
              <span><i class="fas fa-user text-primary"></i> <strong>JK / Umur:</strong> <span id="mgc_lbl_jk_umur">-</span></span>
              <span><i class="fas fa-clinic-medical text-primary"></i> <strong>Poli:</strong> <span id="mgc_lbl_poli">-</span></span>
              <span><i class="fas fa-user-md text-primary"></i> <strong>Dokter:</strong> <span id="mgc_lbl_dokter">-</span></span>
            </div>
          </div>
          <div id="mgc_badge_status_saved"></div>
        </div>

        <!-- 2-Column Wide Split Layout -->
        <div class="gc-split-grid">
          
          <!-- ─── LEFT COLUMN: IDENTITAS & KLAUSUL ─── -->
          <div>
            
            <!-- 1. Informasi Surat -->
            <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;padding:14px 16px;margin-bottom:14px;box-shadow:0 1px 3px rgba(0,0,0,0.03);">
              <div style="font-size:12.5px;font-weight:800;color:#1e3a8a;margin-bottom:10px;text-transform:uppercase;display:flex;align-items:center;gap:6px;">
                <i class="fas fa-file-invoice"></i> 1. Informasi Dokumen &amp; Waktu Persetujuan
              </div>
              <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(140px, 1fr));gap:10px;">
                <div class="form-group">
                  <label class="form-label" style="font-size:11px;font-weight:700;">Nomor Dokumen GC</label>
                  <input type="text" name="no_surat" id="mgc_no_surat" class="form-control form-control-sm" required>
                </div>
                <div class="form-group">
                  <label class="form-label" style="font-size:11px;font-weight:700;">Tanggal</label>
                  <input type="date" name="tgl_persetujuan" id="mgc_tgl_persetujuan" class="form-control form-control-sm" required>
                </div>
                <div class="form-group">
                  <label class="form-label" style="font-size:11px;font-weight:700;">Jam</label>
                  <input type="time" name="jam_persetujuan" id="mgc_jam_persetujuan" class="form-control form-control-sm" required>
                </div>
                <div class="form-group">
                  <label class="form-label" style="font-size:11px;font-weight:700;">Penjamin</label>
                  <input type="text" name="tipe_penjamin" id="mgc_tipe_penjamin" class="form-control form-control-sm" required>
                </div>
              </div>
            </div>

            <!-- 2. Identitas Pemberi Persetujuan -->
            <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;padding:14px 16px;margin-bottom:14px;box-shadow:0 1px 3px rgba(0,0,0,0.03);">
              <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;flex-wrap:wrap;gap:8px;">
                <div style="font-size:12.5px;font-weight:800;color:#1e3a8a;text-transform:uppercase;display:flex;align-items:center;gap:6px;">
                  <i class="fas fa-user-edit"></i> 2. Pemberi Persetujuan (Pasien / Wali)
                </div>
                <div style="display:flex;gap:6px;">
                  <button type="button" class="btn btn-xs btn-outline-primary" onclick="salinPasienModalGC()" style="font-size:11px;padding:3px 8px;border-radius:5px;font-weight:600;">
                    <i class="fas fa-user-check"></i> Pasien Sendiri
                  </button>
                  <button type="button" class="btn btn-xs btn-outline-info" onclick="salinKeluargaModalGC()" style="font-size:11px;padding:3px 8px;border-radius:5px;font-weight:600;">
                    <i class="fas fa-users"></i> Salin PJ Pasien
                  </button>
                </div>
              </div>

              <div style="display:grid;grid-template-columns:1.8fr 1.2fr 1fr 1fr;gap:10px;margin-bottom:10px;">
                <div class="form-group">
                  <label class="form-label" style="font-size:11px;font-weight:700;">Nama Lengkap <span class="text-danger">*</span></label>
                  <input type="text" name="nama_pj" id="mgc_nama_pj" class="form-control form-control-sm" required>
                </div>
                <div class="form-group">
                  <label class="form-label" style="font-size:11px;font-weight:700;">Hubungan <span class="text-danger">*</span></label>
                  <select name="hubungan_pj" id="mgc_hubungan_pj" class="form-control form-control-sm" required>
                    <option value="Diri Sendiri">Diri Sendiri</option>
                    <option value="Suami">Suami</option>
                    <option value="Istri">Istri</option>
                    <option value="Anak">Anak</option>
                    <option value="Orang Tua">Orang Tua</option>
                    <option value="Saudara">Saudara</option>
                    <option value="Keluarga">Keluarga</option>
                    <option value="Wali">Wali</option>
                    <option value="Lain-lain">Lain-lain</option>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label" style="font-size:11px;font-weight:700;">Kelamin</label>
                  <select name="jk_pj" id="mgc_jk_pj" class="form-control form-control-sm">
                    <option value="L">Laki-laki</option>
                    <option value="P">Perempuan</option>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label" style="font-size:11px;font-weight:700;">Usia</label>
                  <input type="text" name="umur_pj" id="mgc_umur_pj" class="form-control form-control-sm" placeholder="Contoh: 30 Th">
                </div>
              </div>

              <div style="display:grid;grid-template-columns:1.2fr 1.1fr 2fr;gap:10px;">
                <div class="form-group">
                  <label class="form-label" style="font-size:11px;font-weight:700;">No. KTP / NIK</label>
                  <input type="text" name="no_ktp_pj" id="mgc_no_ktp_pj" class="form-control form-control-sm" placeholder="16 digit NIK">
                </div>
                <div class="form-group">
                  <label class="form-label" style="font-size:11px;font-weight:700;">No. HP / Telp</label>
                  <input type="text" name="no_telp_pj" id="mgc_no_telp_pj" class="form-control form-control-sm" placeholder="08xxxxxxxx">
                </div>
                <div class="form-group">
                  <label class="form-label" style="font-size:11px;font-weight:700;">Alamat</label>
                  <input type="text" name="alamat_pj" id="mgc_alamat_pj" class="form-control form-control-sm" placeholder="Alamat domisili">
                </div>
              </div>
            </div>

            <!-- 3. Klausul Butir Persetujuan -->
            <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;padding:14px 16px;box-shadow:0 1px 3px rgba(0,0,0,0.03);">
              <div style="font-size:12.5px;font-weight:800;color:#1e3a8a;margin-bottom:10px;text-transform:uppercase;display:flex;align-items:center;gap:6px;">
                <i class="fas fa-clipboard-check"></i> 3. Klausul &amp; Butir-Butir Persetujuan
              </div>
              
              <div style="font-size:11.5px;display:flex;flex-direction:column;gap:8px;">
                <div style="display:flex;align-items:center;justify-content:space-between;background:#f8fafc;padding:8px 12px;border-radius:8px;border:1px solid #e2e8f0;">
                  <span><strong>I. Pelayanan &amp; Tindakan Medis Standar</strong></span>
                  <select name="setuju_rawat_inap_jalan" id="mgc_setuju_rawat_inap_jalan" class="form-control form-control-sm" style="width:105px;">
                    <option value="Setuju">Setuju</option>
                    <option value="Tidak">Tidak</option>
                  </select>
                </div>
                <div style="display:flex;align-items:center;justify-content:space-between;background:#f8fafc;padding:8px 12px;border-radius:8px;border:1px solid #e2e8f0;">
                  <span><strong>II. Hak &amp; Kewajiban Pasien</strong></span>
                  <select name="setuju_hak_kewajiban" id="mgc_setuju_hak_kewajiban" class="form-control form-control-sm" style="width:105px;">
                    <option value="Setuju">Setuju</option>
                    <option value="Tidak">Tidak</option>
                  </select>
                </div>
                <div style="background:#f8fafc;padding:10px 12px;border-radius:8px;border:1px solid #e2e8f0;">
                  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                    <span><strong>III. Pelepasan Informasi Medis ke Keluarga</strong></span>
                    <select name="setuju_pelepasan_informasi" id="mgc_setuju_pelepasan_informasi" class="form-control form-control-sm" style="width:105px;">
                      <option value="Setuju">Setuju</option>
                      <option value="Tidak">Tidak</option>
                    </select>
                  </div>
                  <input type="text" name="nama_keluarga_informasi" id="mgc_nama_keluarga_informasi" class="form-control form-control-sm" placeholder="Nama keluarga yang diberi akses informasi (contoh: Diri Sendiri / Suami / Orang Tua)">
                </div>
              </div>
            </div>

          </div>

          <!-- ─── RIGHT COLUMN: DIGITAL SIGNATURE DRAWING PAD STATION ─── -->
          <div>
            <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;padding:16px;box-shadow:0 1px 3px rgba(0,0,0,0.03);">
              <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                <div style="font-size:12.5px;font-weight:800;color:#1e3a8a;text-transform:uppercase;display:flex;align-items:center;gap:6px;">
                  <i class="fas fa-signature text-primary"></i> 4. Drawing Pad Tanda Tangan Digital
                </div>
                <span style="font-size:11px;color:#64748b;"><i class="fas fa-hand-pointer"></i> Touch / Mouse / Stylus</span>
              </div>

              <!-- Pad Pasien / Wali -->
              <div style="border:1.5px solid #cbd5e1;border-radius:10px;padding:12px;background:#fafafa;margin-bottom:14px;" id="mgc_card_pad_pasien">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                  <span style="font-size:12.5px;font-weight:800;color:#1e293b;">Tanda Tangan Pasien / Wali</span>
                  <span id="mgc_badge_sig_pasien" style="font-size:10.5px;padding:3px 8px;border-radius:12px;font-weight:700;background:#fee2e2;color:#991b1b;">Belum TTD</span>
                </div>
                
                <div style="position:relative;width:100%;height:190px;background:#ffffff;border:1.5px dashed #94a3b8;border-radius:8px;overflow:hidden;touch-action:none;cursor:crosshair;">
                  <canvas id="mgc_canvas_pasien" style="width:100%;height:100%;display:block;"></canvas>
                  <div id="mgc_ph_pasien" style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);color:#94a3b8;font-size:12px;pointer-events:none;display:flex;flex-direction:column;align-items:center;gap:4px;">
                    <i class="fas fa-signature" style="font-size:26px;opacity:0.4;"></i>
                    <span>Bubuhkan tanda tangan pasien / wali di sini</span>
                  </div>
                </div>

                <div style="display:flex;align-items:center;justify-content:space-between;margin-top:8px;">
                  <div style="display:flex;gap:6px;align-items:center;">
                    <span style="font-size:11px;color:#64748b;font-weight:600;">Tinta:</span>
                    <button type="button" class="btn-color-dot active" style="width:20px;height:20px;border-radius:50%;background:#1e3a8a;border:2px solid transparent;cursor:pointer;" onclick="setModalPadColor('pasien','#1e3a8a',this)" title="Biru Tua"></button>
                    <button type="button" class="btn-color-dot" style="width:20px;height:20px;border-radius:50%;background:#111827;border:2px solid transparent;cursor:pointer;" onclick="setModalPadColor('pasien','#111827',this)" title="Hitam"></button>
                  </div>
                  <div style="display:flex;gap:6px;">
                    <button type="button" class="btn btn-xs btn-outline-secondary" onclick="undoModalPad('pasien')" style="font-size:11px;padding:3px 8px;">
                      <i class="fas fa-undo"></i> Undo
                    </button>
                    <button type="button" class="btn btn-xs btn-outline-danger" onclick="clearModalPad('pasien')" style="font-size:11px;padding:3px 8px;">
                      <i class="fas fa-trash-alt"></i> Bersihkan
                    </button>
                  </div>
                </div>
              </div>

              <!-- Pad Petugas Admisi -->
              <div style="border:1.5px solid #cbd5e1;border-radius:10px;padding:12px;background:#fafafa;" id="mgc_card_pad_petugas">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                  <span style="font-size:12.5px;font-weight:800;color:#1e293b;">Tanda Tangan Petugas Admisi / Saksi</span>
                  <span id="mgc_badge_sig_petugas" style="font-size:10.5px;padding:3px 8px;border-radius:12px;font-weight:700;background:#fee2e2;color:#991b1b;">Belum TTD</span>
                </div>
                
                <div style="position:relative;width:100%;height:150px;background:#ffffff;border:1.5px dashed #94a3b8;border-radius:8px;overflow:hidden;touch-action:none;cursor:crosshair;">
                  <canvas id="mgc_canvas_petugas" style="width:100%;height:100%;display:block;"></canvas>
                  <div id="mgc_ph_petugas" style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);color:#94a3b8;font-size:12px;pointer-events:none;display:flex;flex-direction:column;align-items:center;gap:4px;">
                    <i class="fas fa-signature" style="font-size:24px;opacity:0.4;"></i>
                    <span>Bubuhkan tanda tangan petugas di sini</span>
                  </div>
                </div>

                <div style="display:flex;align-items:center;justify-content:space-between;margin-top:8px;">
                  <div style="display:flex;gap:6px;align-items:center;">
                    <span style="font-size:11px;color:#64748b;font-weight:600;">Tinta:</span>
                    <button type="button" class="btn-color-dot active" style="width:20px;height:20px;border-radius:50%;background:#1e3a8a;border:2px solid transparent;cursor:pointer;" onclick="setModalPadColor('petugas','#1e3a8a',this)" title="Biru Tua"></button>
                    <button type="button" class="btn-color-dot" style="width:20px;height:20px;border-radius:50%;background:#111827;border:2px solid transparent;cursor:pointer;" onclick="setModalPadColor('petugas','#111827',this)" title="Hitam"></button>
                  </div>
                  <div style="display:flex;gap:6px;">
                    <button type="button" class="btn btn-xs btn-outline-secondary" onclick="undoModalPad('petugas')" style="font-size:11px;padding:3px 8px;">
                      <i class="fas fa-undo"></i> Undo
                    </button>
                    <button type="button" class="btn btn-xs btn-outline-danger" onclick="clearModalPad('petugas')" style="font-size:11px;padding:3px 8px;">
                      <i class="fas fa-trash-alt"></i> Bersihkan
                    </button>
                  </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:8px;">
                  <input type="text" name="nama_petugas" id="mgc_nama_petugas" class="form-control form-control-sm" placeholder="Nama Petugas" required style="font-size:11.5px;">
                  <input type="text" name="nip_petugas" id="mgc_nip_petugas" class="form-control form-control-sm" placeholder="NIP / ID Petugas" style="font-size:11.5px;">
                </div>
              </div>

            </div>
          </div>

        </div>

      </form>

    </div>

    <!-- Modal Footer -->
    <div style="padding:14px 24px;border-top:1px solid #e2e8f0;background:#ffffff;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;flex-shrink:0;">
      <button type="button" class="btn btn-secondary" onclick="tutupModalGeneralConsent()" style="padding:7px 16px;font-weight:600;">Tutup</button>
      <div style="display:flex;gap:10px;">
        <button type="button" class="btn btn-outline-primary" id="mgc_btn_cetak" onclick="cetakModalGeneralConsent()" style="font-weight:600;padding:7px 16px;">
          <i class="fas fa-print"></i> Cetak Lembar GC
        </button>
        <button type="button" class="btn btn-primary" id="mgc_btn_simpan" onclick="simpanModalGeneralConsent()" style="font-weight:700;padding:7px 22px;">
          <i class="fas fa-save"></i> Simpan General Consent
        </button>
      </div>
    </div>

  </div>
</div>

<script>
// ─── MODAL GENERAL CONSENT JAVASCRIPT ENGINE (CENTERED & WIDE) ───
let mgcPasienData = null;
let mgcConsentData = null;

const mgcPads = {
  pasien: {
    canvas: null,
    ctx: null,
    isDrawing: false,
    strokes: [],
    currentStroke: [],
    color: '#1e3a8a',
    lineWidth: 2.5,
    hasContent: false,
    existingImg: ''
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
    existingImg: ''
  }
};

function bukaModalGeneralConsent(no_rawat) {
  const modal = document.getElementById('modalGeneralConsent');
  const loader = document.getElementById('loaderModalGC');
  const form = document.getElementById('formModalGC');
  
  if (!modal) return;
  modal.style.display = 'flex';
  loader.style.display = 'block';
  form.style.display = 'none';

  fetch(`<?= BASE_URL ?>modules/rekam_medis/ajax.php?action=get_general_consent&no_rawat=${encodeURIComponent(no_rawat)}`, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(r => r.json())
  .then(res => {
    loader.style.display = 'none';
    if (!res.success) {
      alert(res.message || 'Gagal memuat data');
      tutupModalGeneralConsent();
      return;
    }

    form.style.display = 'block';
    mgcPasienData = res.pasien;
    mgcConsentData = res.consent;

    // Populate Info Pasien
    document.getElementById('mgc_no_rawat').value = res.pasien.no_rawat;
    document.getElementById('mgc_lbl_pasien').textContent = `${res.pasien.nm_pasien} (${res.pasien.jk === 'L' ? 'Laki-laki' : 'Perempuan'})`;
    document.getElementById('mgc_lbl_rm').textContent = res.pasien.no_rkm_medis;
    document.getElementById('mgc_lbl_rawat').textContent = res.pasien.no_rawat;
    document.getElementById('mgc_lbl_jk_umur').textContent = res.pasien.umur || '-';
    document.getElementById('mgc_lbl_poli').textContent = res.pasien.nm_poli || '-';
    document.getElementById('mgc_lbl_dokter').textContent = res.pasien.nm_dokter || '-';

    const statusBadge = document.getElementById('mgc_badge_status_saved');
    if (res.consent) {
      statusBadge.innerHTML = '<span style="background:#dcfce7;color:#15803d;font-size:12px;padding:4px 12px;border-radius:20px;font-weight:700;"><i class="fas fa-check-circle"></i> Sudah Ditandatangani</span>';
    } else {
      statusBadge.innerHTML = '<span style="background:#fef3c7;color:#b45309;font-size:12px;padding:4px 12px;border-radius:20px;font-weight:700;"><i class="fas fa-clock"></i> Belum Mengisi</span>';
    }

    // Populate Fields
    const cleanRawat = (res.pasien.no_rawat || '').replace(/[^0-9]/g, '');
    const dateStr = (res.pasien.tgl_registrasi || '').replace(/-/g, '');
    const defaultNoSurat = `GC-${dateStr}-${cleanRawat.slice(-4)}`;

    document.getElementById('mgc_no_surat').value = res.consent?.no_surat || defaultNoSurat;
    document.getElementById('mgc_tgl_persetujuan').value = res.consent?.tgl_persetujuan || res.pasien.tgl_registrasi || '<?= date('Y-m-d') ?>';
    document.getElementById('mgc_jam_persetujuan').value = res.consent?.jam_persetujuan || res.pasien.jam_reg || '<?= date('H:i:s') ?>';
    document.getElementById('mgc_tipe_penjamin').value = res.consent?.tipe_penjamin || res.pasien.nm_penjab || 'Umum';

    document.getElementById('mgc_nama_pj').value = res.consent?.nama_pj || res.pasien.nm_pasien;
    document.getElementById('mgc_hubungan_pj').value = res.consent?.hubungan_pj || 'Diri Sendiri';
    document.getElementById('mgc_jk_pj').value = res.consent?.jk_pj || res.pasien.jk || 'L';
    document.getElementById('mgc_umur_pj').value = res.consent?.umur_pj || res.pasien.umur || '';
    document.getElementById('mgc_no_ktp_pj').value = res.consent?.no_ktp_pj || res.pasien.no_ktp || '';
    document.getElementById('mgc_no_telp_pj').value = res.consent?.no_telp_pj || res.pasien.no_tlp || '';
    document.getElementById('mgc_alamat_pj').value = res.consent?.alamat_pj || res.pasien.alamat || '';

    document.getElementById('mgc_setuju_rawat_inap_jalan').value = res.consent?.setuju_rawat_inap_jalan || 'Setuju';
    document.getElementById('mgc_setuju_hak_kewajiban').value = res.consent?.setuju_hak_kewajiban || 'Setuju';
    document.getElementById('mgc_setuju_pelepasan_informasi').value = res.consent?.setuju_pelepasan_informasi || 'Setuju';
    document.getElementById('mgc_nama_keluarga_informasi').value = res.consent?.nama_keluarga_informasi || res.pasien.namakeluarga || 'Diri Sendiri / Keluarga Inti';

    document.getElementById('mgc_nama_petugas').value = res.consent?.nama_petugas || res.petugas_default?.nama || 'Petugas Admisi';
    document.getElementById('mgc_nip_petugas').value = res.consent?.nip_petugas || res.petugas_default?.nip || '-';

    document.getElementById('mgc_input_ttd_pasien').value = res.consent?.ttd_pasien || '';
    document.getElementById('mgc_input_ttd_petugas').value = res.consent?.ttd_petugas || '';

    // Initialize Canvas Pads after DOM rendered
    setTimeout(() => {
      initModalPad('pasien', res.consent?.ttd_pasien || '');
      initModalPad('petugas', res.consent?.ttd_petugas || '');
    }, 120);
  })
  .catch(err => {
    loader.style.display = 'none';
    alert('Terjadi kesalahan jaringan: ' + err);
    tutupModalGeneralConsent();
  });
}

function tutupModalGeneralConsent() {
  const modal = document.getElementById('modalGeneralConsent');
  if (modal) modal.style.display = 'none';
}

function initModalPad(key, existingImg = '') {
  const pad = mgcPads[key];
  const canvas = document.getElementById(key === 'pasien' ? 'mgc_canvas_pasien' : 'mgc_canvas_petugas');
  if (!canvas) return;

  pad.canvas = canvas;
  pad.ctx = canvas.getContext('2d');
  pad.strokes = [];
  pad.currentStroke = [];
  pad.hasContent = false;
  pad.existingImg = existingImg;

  const rect = canvas.getBoundingClientRect();
  const dpr = window.devicePixelRatio || 2;
  canvas.width = (rect.width || 450) * dpr;
  canvas.height = (rect.height || (key === 'pasien' ? 190 : 150)) * dpr;
  pad.ctx.scale(dpr, dpr);

  const ph = document.getElementById(key === 'pasien' ? 'mgc_ph_pasien' : 'mgc_ph_petugas');
  const badge = document.getElementById(key === 'pasien' ? 'mgc_badge_sig_pasien' : 'mgc_badge_sig_petugas');

  if (existingImg) {
    const img = new Image();
    img.crossOrigin = 'anonymous';
    img.onload = function() {
      pad.ctx.drawImage(img, 0, 0, rect.width, rect.height);
      pad.hasContent = true;
      if (ph) ph.style.display = 'none';
      if (badge) {
        badge.style.background = '#dcfce7';
        badge.style.color = '#15803d';
        badge.textContent = '✓ TTD Terisi';
      }
    };
    img.src = existingImg;
  } else {
    if (ph) ph.style.display = 'flex';
    if (badge) {
      badge.style.background = '#fee2e2';
      badge.style.color = '#991b1b';
      badge.textContent = 'Belum TTD';
    }
  }

  // Pointer & Touch Handlers
  function getPos(e) {
    const r = canvas.getBoundingClientRect();
    let cx = e.clientX, cy = e.clientY;
    if (e.touches && e.touches.length > 0) {
      cx = e.touches[0].clientX;
      cy = e.touches[0].clientY;
    }
    return { x: cx - r.left, y: cy - r.top };
  }

  function start(e) {
    e.preventDefault();
    pad.isDrawing = true;
    const pos = getPos(e);
    pad.currentStroke = [{ x: pos.x, y: pos.y, color: pad.color, width: pad.lineWidth }];
    if (ph) ph.style.display = 'none';
  }

  function move(e) {
    if (!pad.isDrawing) return;
    e.preventDefault();
    const pos = getPos(e);
    pad.currentStroke.push({ x: pos.x, y: pos.y, color: pad.color, width: pad.lineWidth });

    const pts = pad.currentStroke;
    if (pts.length < 2) return;
    const ctx = pad.ctx;
    ctx.strokeStyle = pad.color;
    ctx.lineWidth = pad.lineWidth;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.beginPath();
    ctx.moveTo(pts[pts.length - 2].x, pts[pts.length - 2].y);
    ctx.lineTo(pts[pts.length - 1].x, pts[pts.length - 1].y);
    ctx.stroke();
  }

  function stop(e) {
    if (!pad.isDrawing) return;
    pad.isDrawing = false;
    if (pad.currentStroke.length > 0) {
      pad.strokes.push([...pad.currentStroke]);
      pad.currentStroke = [];
      pad.hasContent = true;
      if (badge) {
        badge.style.background = '#dcfce7';
        badge.style.color = '#15803d';
        badge.textContent = '✓ TTD Terisi';
      }
    }
  }

  canvas.onmousedown = start;
  window.addEventListener('mousemove', move);
  window.addEventListener('mouseup', stop);

  canvas.addEventListener('touchstart', start, { passive: false });
  window.addEventListener('touchmove', move, { passive: false });
  window.addEventListener('touchend', stop, { passive: false });
}

function clearModalPad(key) {
  const pad = mgcPads[key];
  pad.strokes = [];
  pad.currentStroke = [];
  pad.hasContent = false;
  pad.existingImg = '';
  const rect = pad.canvas.getBoundingClientRect();
  pad.ctx.clearRect(0, 0, rect.width, rect.height);

  const ph = document.getElementById(key === 'pasien' ? 'mgc_ph_pasien' : 'mgc_ph_petugas');
  const badge = document.getElementById(key === 'pasien' ? 'mgc_badge_sig_pasien' : 'mgc_badge_sig_petugas');
  if (ph) ph.style.display = 'flex';
  if (badge) {
    badge.style.background = '#fee2e2';
    badge.style.color = '#991b1b';
    badge.textContent = 'Belum TTD';
  }
  document.getElementById(key === 'pasien' ? 'mgc_input_ttd_pasien' : 'mgc_input_ttd_petugas').value = '';
}

function undoModalPad(key) {
  const pad = mgcPads[key];
  if (pad.strokes.length > 0) {
    pad.strokes.pop();
    const rect = pad.canvas.getBoundingClientRect();
    pad.ctx.clearRect(0, 0, rect.width, rect.height);
    
    pad.strokes.forEach(stroke => {
      if (stroke.length < 2) return;
      pad.ctx.beginPath();
      pad.ctx.strokeStyle = stroke[0].color || pad.color;
      pad.ctx.lineWidth = stroke[0].width || pad.lineWidth;
      pad.ctx.lineCap = 'round';
      pad.ctx.lineJoin = 'round';
      pad.ctx.moveTo(stroke[0].x, stroke[0].y);
      for (let i = 1; i < stroke.length; i++) {
        pad.ctx.lineTo(stroke[i].x, stroke[i].y);
      }
      pad.ctx.stroke();
    });

    if (pad.strokes.length === 0 && !pad.existingImg) {
      pad.hasContent = false;
      const ph = document.getElementById(key === 'pasien' ? 'mgc_ph_pasien' : 'mgc_ph_petugas');
      const badge = document.getElementById(key === 'pasien' ? 'mgc_badge_sig_pasien' : 'mgc_badge_sig_petugas');
      if (ph) ph.style.display = 'flex';
      if (badge) {
        badge.style.background = '#fee2e2';
        badge.style.color = '#991b1b';
        badge.textContent = 'Belum TTD';
      }
    }
  }
}

function setModalPadColor(key, color, btn) {
  mgcPads[key].color = color;
  btn.parentElement.querySelectorAll('.btn-color-dot').forEach(b => {
    b.style.borderColor = 'transparent';
  });
  btn.style.borderColor = '#0284c7';
}

function salinPasienModalGC() {
  if (!mgcPasienData) return;
  document.getElementById('mgc_nama_pj').value = mgcPasienData.nm_pasien;
  document.getElementById('mgc_hubungan_pj').value = 'Diri Sendiri';
  document.getElementById('mgc_jk_pj').value = mgcPasienData.jk || 'L';
  document.getElementById('mgc_umur_pj').value = mgcPasienData.umur || '';
  document.getElementById('mgc_no_ktp_pj').value = mgcPasienData.no_ktp || '';
  document.getElementById('mgc_no_telp_pj').value = mgcPasienData.no_tlp || '';
  document.getElementById('mgc_alamat_pj').value = mgcPasienData.alamat || '';
}

function salinKeluargaModalGC() {
  if (!mgcPasienData) return;
  if (mgcPasienData.namakeluarga) document.getElementById('mgc_nama_pj').value = mgcPasienData.namakeluarga;
  if (mgcPasienData.keluarga) {
    const hub = document.getElementById('mgc_hubungan_pj');
    for (let i = 0; i < hub.options.length; i++) {
      if (hub.options[i].value.toLowerCase() === mgcPasienData.keluarga.toLowerCase()) {
        hub.selectedIndex = i;
        break;
      }
    }
  }
  if (mgcPasienData.alamatpj) document.getElementById('mgc_alamat_pj').value = mgcPasienData.alamatpj;
  if (mgcPasienData.no_tlp) document.getElementById('mgc_no_telp_pj').value = mgcPasienData.no_tlp;
}

function simpanModalGeneralConsent(andPrint = false) {
  const form = document.getElementById('formModalGC');
  const btn = document.getElementById('mgc_btn_simpan');

  if (mgcPads.pasien.hasContent && mgcPads.pasien.strokes.length > 0) {
    document.getElementById('mgc_input_ttd_pasien').value = mgcPads.pasien.canvas.toDataURL('image/png');
  }
  if (mgcPads.petugas.hasContent && mgcPads.petugas.strokes.length > 0) {
    document.getElementById('mgc_input_ttd_petugas').value = mgcPads.petugas.canvas.toDataURL('image/png');
  }

  const origHtml = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';

  const fd = new FormData(form);
  fd.append('action', 'simpan_general_consent');

  fetch('<?= BASE_URL ?>modules/rekam_medis/ajax.php', {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    body: fd
  })
  .then(r => r.json())
  .then(res => {
    btn.disabled = false;
    btn.innerHTML = origHtml;

    if (res.success) {
      showToast(res.message, 'success');
      document.getElementById('mgc_badge_status_saved').innerHTML = '<span style="background:#dcfce7;color:#15803d;font-size:12px;padding:4px 12px;border-radius:20px;font-weight:700;"><i class="fas fa-check-circle"></i> Sudah Ditandatangani</span>';
      if (andPrint) {
        cetakModalGeneralConsent();
      }
      setTimeout(() => {
        tutupModalGeneralConsent();
      }, 700);
    } else {
      alert(res.message || 'Gagal menyimpan General Consent');
    }
  })
  .catch(err => {
    btn.disabled = false;
    btn.innerHTML = origHtml;
    alert('Terjadi kesalahan koneksi: ' + err);
  });
}

function cetakModalGeneralConsent() {
  const noRawat = document.getElementById('mgc_no_rawat').value;
  if (!noRawat) return;
  window.open(`<?= BASE_URL ?>modules/rekam_medis/cetak_general_consent.php?no_rawat=${encodeURIComponent(noRawat)}`, '_blank');
}
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>

