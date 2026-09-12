<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/pcare_service.php';

$tgl = date('d-m-Y');
$tgl_db = date('Y-m-d');
$matched = 0;

for ($start = 0; $start < 150; $start += 15) {
    $res = PCareService::getPendaftaran($tgl, $start, 15);
    $list = $res['response']['list'] ?? [];
    if (empty($list)) break;
    
    foreach ($list as $item) {
        $noKartu = $item['peserta']['noKartu'] ?? '';
        $noUrut  = $item['noUrut'] ?? '';
        $nmPoli  = $item['poli']['nmPoli'] ?? 'Poli Umum';
        $kdPoli  = $item['poli']['kdPoli'] ?? '001';
        $keluhan = $item['keluhan'] ?? 'Pemeriksaan Rawat Jalan';
        $kdProvider = $item['peserta']['kdProviderPst']['kdProvider'] ?? '0169B012';

        if (empty($noKartu)) continue;

        // Cari no_rawat di database lokal untuk hari ini
        $q = $conn->query("
            SELECT r.no_rawat, r.no_rkm_medis, p.nm_pasien
            FROM reg_periksa r
            JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
            WHERE (p.no_peserta = '$noKartu' OR p.no_ktp = '$noKartu' OR r.no_rkm_medis = '{$item['peserta']['noKTP']}')
              AND r.tgl_registrasi = '$tgl_db'
            LIMIT 1
        ");

        if ($q && $q->num_rows > 0) {
            $row = $q->fetch_assoc();
            $no_rawat_esc = $conn->real_escape_string($row['no_rawat']);
            $no_rm_esc    = $conn->real_escape_string($row['no_rkm_medis']);
            $nm_pasien_esc= $conn->real_escape_string($row['nm_pasien']);
            $no_kartu_esc = $conn->real_escape_string($noKartu);
            $no_urut_esc  = $conn->real_escape_string($noUrut);

            $conn->query("
                INSERT INTO pcare_pendaftaran (
                    no_rawat, tglDaftar, no_rkm_medis, nm_pasien, kdProviderPeserta, noKartu,
                    kdPoli, nmPoli, keluhan, kunjSakit, sistole, diastole,
                    beratBadan, tinggiBadan, respRate, lingkar_perut, heartRate, rujukBalik, kdTkp, noUrut, status
                ) VALUES (
                    '$no_rawat_esc', '$tgl_db', '$no_rm_esc', '$nm_pasien_esc', '$kdProvider', '$no_kartu_esc',
                    '$kdPoli', '$nmPoli', '$keluhan', 'Kunjungan Sakit', 120, 80,
                    60, 165, 20, 80, 80, '0', '10 Rawat Jalan', '$no_urut_esc', 'Terkirim'
                ) ON DUPLICATE KEY UPDATE 
                    noUrut = '$no_urut_esc', status = 'Terkirim'
            ");
            $matched++;
            echo "Matched & Synced: {$row['nm_pasien']} ({$no_rawat_esc}) => No. Urut: {$no_urut_esc}\n";
        }
    }
}

echo "\nTotal tersinkronisasi: {$matched} pasien.\n";
