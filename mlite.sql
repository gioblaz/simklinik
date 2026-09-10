-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Waktu pembuatan: 08 Sep 2026 pada 13.19
-- Versi server: 8.4.7
-- Versi PHP: 8.3.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Basis data: `mlite`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `aturan_pakai`
--

DROP TABLE IF EXISTS `aturan_pakai`;
CREATE TABLE IF NOT EXISTS `aturan_pakai` (
  `tgl_perawatan` date NOT NULL DEFAULT '0000-00-00',
  `jam` time NOT NULL DEFAULT '00:00:00',
  `no_rawat` varchar(17) NOT NULL DEFAULT '',
  `kode_brng` varchar(15) NOT NULL DEFAULT '',
  `aturan` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`tgl_perawatan`,`jam`,`no_rawat`,`kode_brng`),
  KEY `no_rawat` (`no_rawat`),
  KEY `kode_brng` (`kode_brng`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `bahasa_pasien`
--

DROP TABLE IF EXISTS `bahasa_pasien`;
CREATE TABLE IF NOT EXISTS `bahasa_pasien` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nama_bahasa` varchar(30) DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `nama_bahasa` (`nama_bahasa`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `bahasa_pasien`
--

INSERT INTO `bahasa_pasien` (`id`, `nama_bahasa`) VALUES
(1, '-'),
(4, 'Banjar'),
(5, 'Inggris'),
(2, 'Jawa'),
(3, 'Sunda');

-- --------------------------------------------------------

--
-- Struktur dari tabel `bangsal`
--

DROP TABLE IF EXISTS `bangsal`;
CREATE TABLE IF NOT EXISTS `bangsal` (
  `kd_bangsal` char(5) NOT NULL,
  `nm_bangsal` varchar(30) DEFAULT NULL,
  `status` enum('0','1') DEFAULT NULL,
  PRIMARY KEY (`kd_bangsal`),
  KEY `nm_bangsal` (`nm_bangsal`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `bangsal`
--

INSERT INTO `bangsal` (`kd_bangsal`, `nm_bangsal`, `status`) VALUES
('-', '-', '1'),
('ANG', 'Anggrek', '1'),
('AP01', 'Apotek Pelayanan Rawat Jalan', '1'),
('APT', 'Apotek Pelayanan Rawat Jalan', '1'),
('G001', 'Gudang Farmasi Utama', '1'),
('GF', 'Gudang Farmasi Induk', '1'),
('GIG', 'Depo Pelayanan Poli Gigi', '1'),
('IG01', 'Depo Farmasi IGD', '1'),
('IGD', 'Depo Farmasi IGD', '1');

-- --------------------------------------------------------

--
-- Struktur dari tabel `bank`
--

DROP TABLE IF EXISTS `bank`;
CREATE TABLE IF NOT EXISTS `bank` (
  `namabank` varchar(50) NOT NULL,
  PRIMARY KEY (`namabank`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `bank`
--

INSERT INTO `bank` (`namabank`) VALUES
('-'),
('T');

-- --------------------------------------------------------

--
-- Struktur dari tabel `barcode`
--

DROP TABLE IF EXISTS `barcode`;
CREATE TABLE IF NOT EXISTS `barcode` (
  `id` int NOT NULL,
  `barcode` varchar(25) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `barcode` (`barcode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `beri_obat_operasi`
--

DROP TABLE IF EXISTS `beri_obat_operasi`;
CREATE TABLE IF NOT EXISTS `beri_obat_operasi` (
  `no_rawat` varchar(17) NOT NULL,
  `tanggal` datetime NOT NULL,
  `kd_obat` varchar(15) NOT NULL,
  `hargasatuan` double NOT NULL,
  `jumlah` double NOT NULL,
  KEY `no_rawat` (`no_rawat`),
  KEY `kd_obat` (`kd_obat`),
  KEY `tanggal` (`tanggal`),
  KEY `hargasatuan` (`hargasatuan`),
  KEY `jumlah` (`jumlah`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `berkas_digital_perawatan`
--

DROP TABLE IF EXISTS `berkas_digital_perawatan`;
CREATE TABLE IF NOT EXISTS `berkas_digital_perawatan` (
  `no_rawat` varchar(17) NOT NULL,
  `kode` varchar(10) NOT NULL,
  `lokasi_file` varchar(600) NOT NULL,
  PRIMARY KEY (`no_rawat`,`kode`,`lokasi_file`) USING BTREE,
  KEY `kode` (`kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `bidang`
--

DROP TABLE IF EXISTS `bidang`;
CREATE TABLE IF NOT EXISTS `bidang` (
  `nama` varchar(15) NOT NULL,
  PRIMARY KEY (`nama`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `bidang`
--

INSERT INTO `bidang` (`nama`) VALUES
('-');

-- --------------------------------------------------------

--
-- Struktur dari tabel `booking_operasi`
--

DROP TABLE IF EXISTS `booking_operasi`;
CREATE TABLE IF NOT EXISTS `booking_operasi` (
  `no_rawat` varchar(17) DEFAULT NULL,
  `kode_paket` varchar(15) DEFAULT NULL,
  `tanggal` date DEFAULT NULL,
  `jam_mulai` time DEFAULT NULL,
  `jam_selesai` time DEFAULT NULL,
  `status` enum('Menunggu','Proses Operasi','Selesai') DEFAULT NULL,
  `kd_dokter` varchar(20) DEFAULT NULL,
  `kd_ruang_ok` varchar(3) NOT NULL,
  KEY `no_rawat` (`no_rawat`),
  KEY `kode_paket` (`kode_paket`),
  KEY `kd_dokter` (`kd_dokter`),
  KEY `kd_ruang_ok` (`kd_ruang_ok`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `booking_periksa`
--

DROP TABLE IF EXISTS `booking_periksa`;
CREATE TABLE IF NOT EXISTS `booking_periksa` (
  `no_booking` varchar(17) NOT NULL,
  `tanggal` date DEFAULT NULL,
  `nama` varchar(40) DEFAULT NULL,
  `alamat` varchar(200) DEFAULT NULL,
  `no_telp` varchar(40) DEFAULT NULL,
  `email` varchar(50) DEFAULT NULL,
  `kd_poli` varchar(5) DEFAULT NULL,
  `tambahan_pesan` varchar(400) DEFAULT NULL,
  `status` enum('Diterima','Ditolak','Belum Dibalas') NOT NULL,
  `tanggal_booking` datetime NOT NULL,
  PRIMARY KEY (`no_booking`),
  UNIQUE KEY `tanggal` (`tanggal`,`no_telp`),
  KEY `kd_poli` (`kd_poli`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `booking_periksa_balasan`
--

DROP TABLE IF EXISTS `booking_periksa_balasan`;
CREATE TABLE IF NOT EXISTS `booking_periksa_balasan` (
  `no_booking` varchar(17) NOT NULL,
  `balasan` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`no_booking`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `booking_periksa_diterima`
--

DROP TABLE IF EXISTS `booking_periksa_diterima`;
CREATE TABLE IF NOT EXISTS `booking_periksa_diterima` (
  `no_booking` varchar(17) NOT NULL,
  `no_rkm_medis` varchar(15) DEFAULT NULL,
  PRIMARY KEY (`no_booking`),
  KEY `no_rkm_medis` (`no_rkm_medis`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `booking_registrasi`
--

DROP TABLE IF EXISTS `booking_registrasi`;
CREATE TABLE IF NOT EXISTS `booking_registrasi` (
  `tanggal_booking` date DEFAULT NULL,
  `jam_booking` time DEFAULT NULL,
  `no_rkm_medis` varchar(15) NOT NULL,
  `tanggal_periksa` date NOT NULL,
  `kd_dokter` varchar(20) DEFAULT NULL,
  `kd_poli` varchar(5) DEFAULT NULL,
  `no_reg` varchar(8) DEFAULT NULL,
  `kd_pj` char(3) DEFAULT NULL,
  `limit_reg` int DEFAULT NULL,
  `waktu_kunjungan` datetime DEFAULT NULL,
  `status` enum('Terdaftar','Belum','Batal','Dokter Berhalangan') DEFAULT NULL,
  PRIMARY KEY (`no_rkm_medis`,`tanggal_periksa`),
  KEY `kd_dokter` (`kd_dokter`),
  KEY `kd_poli` (`kd_poli`),
  KEY `no_rkm_medis` (`no_rkm_medis`),
  KEY `kd_pj` (`kd_pj`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `bpjs_prb`
--

DROP TABLE IF EXISTS `bpjs_prb`;
CREATE TABLE IF NOT EXISTS `bpjs_prb` (
  `no_sep` varchar(40) NOT NULL,
  `prb` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`no_sep`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `bridging_rujukan_bpjs`
--

DROP TABLE IF EXISTS `bridging_rujukan_bpjs`;
CREATE TABLE IF NOT EXISTS `bridging_rujukan_bpjs` (
  `no_sep` varchar(40) NOT NULL,
  `tglRujukan` date DEFAULT NULL,
  `tglRencanaKunjungan` date NOT NULL,
  `ppkDirujuk` varchar(20) DEFAULT NULL,
  `nm_ppkDirujuk` varchar(100) DEFAULT NULL,
  `jnsPelayanan` enum('1','2') DEFAULT NULL,
  `catatan` varchar(200) DEFAULT NULL,
  `diagRujukan` varchar(10) DEFAULT NULL,
  `nama_diagRujukan` varchar(400) DEFAULT NULL,
  `tipeRujukan` enum('0. Penuh','1. Partial','2. Rujuk Balik') DEFAULT NULL,
  `poliRujukan` varchar(15) DEFAULT NULL,
  `nama_poliRujukan` varchar(50) DEFAULT NULL,
  `no_rujukan` varchar(40) NOT NULL,
  `user` varchar(25) DEFAULT NULL,
  PRIMARY KEY (`no_rujukan`),
  KEY `no_sep` (`no_sep`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `bridging_rujukan_bpjs`
--

INSERT INTO `bridging_rujukan_bpjs` (`no_sep`, `tglRujukan`, `tglRencanaKunjungan`, `ppkDirujuk`, `nm_ppkDirujuk`, `jnsPelayanan`, `catatan`, `diagRujukan`, `nama_diagRujukan`, `tipeRujukan`, `poliRujukan`, `nama_poliRujukan`, `no_rujukan`, `user`) VALUES
('0123R0010926V000001', '2026-09-08', '2026-09-15', '0123R002', 'RSUP NASIONAL DR. CIPTO MANGUNKUSUMO', '2', 'Pemeriksaan lanjutan Echocardiography', 'I10', 'Essential (primary) hypertension', '0. Penuh', 'INT', 'PENYAKIT DALAM', '0123R0010926B000001', 'admin');

-- --------------------------------------------------------

--
-- Struktur dari tabel `bridging_sep`
--

DROP TABLE IF EXISTS `bridging_sep`;
CREATE TABLE IF NOT EXISTS `bridging_sep` (
  `no_sep` varchar(40) NOT NULL DEFAULT '',
  `no_rawat` varchar(17) DEFAULT NULL,
  `tglsep` date DEFAULT NULL,
  `tglrujukan` date DEFAULT NULL,
  `no_rujukan` varchar(40) DEFAULT NULL,
  `kdppkrujukan` varchar(12) DEFAULT NULL,
  `nmppkrujukan` varchar(200) DEFAULT NULL,
  `kdppkpelayanan` varchar(12) DEFAULT NULL,
  `nmppkpelayanan` varchar(200) DEFAULT NULL,
  `jnspelayanan` enum('1','2') DEFAULT NULL,
  `catatan` varchar(100) DEFAULT NULL,
  `diagawal` varchar(10) DEFAULT NULL,
  `nmdiagnosaawal` varchar(400) DEFAULT NULL,
  `kdpolitujuan` varchar(15) DEFAULT NULL,
  `nmpolitujuan` varchar(50) DEFAULT NULL,
  `klsrawat` enum('1','2','3') DEFAULT NULL,
  `klsnaik` enum('','1','2','3','4','5','6','7') NOT NULL,
  `pembiayaan` enum('','1','2','3') NOT NULL,
  `pjnaikkelas` varchar(100) NOT NULL,
  `lakalantas` enum('0','1','2','3') DEFAULT NULL,
  `user` varchar(25) DEFAULT NULL,
  `nomr` varchar(15) DEFAULT NULL,
  `nama_pasien` varchar(100) DEFAULT NULL,
  `tanggal_lahir` date DEFAULT NULL,
  `peserta` varchar(100) DEFAULT NULL,
  `jkel` enum('L','P') DEFAULT NULL,
  `no_kartu` varchar(25) DEFAULT NULL,
  `tglpulang` datetime DEFAULT NULL,
  `asal_rujukan` enum('1. Faskes 1','2. Faskes 2(RS)') NOT NULL,
  `eksekutif` enum('0. Tidak','1.Ya') NOT NULL,
  `cob` enum('0. Tidak','1.Ya') NOT NULL,
  `notelep` varchar(40) NOT NULL,
  `katarak` enum('0. Tidak','1.Ya') NOT NULL,
  `tglkkl` date NOT NULL,
  `keterangankkl` varchar(100) NOT NULL,
  `suplesi` enum('0. Tidak','1.Ya') NOT NULL,
  `no_sep_suplesi` varchar(40) NOT NULL,
  `kdprop` varchar(10) NOT NULL,
  `nmprop` varchar(50) NOT NULL,
  `kdkab` varchar(10) NOT NULL,
  `nmkab` varchar(50) NOT NULL,
  `kdkec` varchar(10) NOT NULL,
  `nmkec` varchar(50) NOT NULL,
  `noskdp` varchar(40) NOT NULL,
  `kddpjp` varchar(10) NOT NULL,
  `nmdpdjp` varchar(100) NOT NULL,
  `tujuankunjungan` enum('0','1','2') NOT NULL,
  `flagprosedur` enum('','0','1') NOT NULL,
  `penunjang` enum('','1','2','3','4','5','6','7','8','9','10','11','12') NOT NULL,
  `asesmenpelayanan` enum('','1','2','3','4','5') NOT NULL,
  `kddpjplayanan` varchar(10) NOT NULL,
  `nmdpjplayanan` varchar(100) NOT NULL,
  PRIMARY KEY (`no_sep`),
  KEY `no_rawat` (`no_rawat`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `bridging_sep`
--

INSERT INTO `bridging_sep` (`no_sep`, `no_rawat`, `tglsep`, `tglrujukan`, `no_rujukan`, `kdppkrujukan`, `nmppkrujukan`, `kdppkpelayanan`, `nmppkpelayanan`, `jnspelayanan`, `catatan`, `diagawal`, `nmdiagnosaawal`, `kdpolitujuan`, `nmpolitujuan`, `klsrawat`, `klsnaik`, `pembiayaan`, `pjnaikkelas`, `lakalantas`, `user`, `nomr`, `nama_pasien`, `tanggal_lahir`, `peserta`, `jkel`, `no_kartu`, `tglpulang`, `asal_rujukan`, `eksekutif`, `cob`, `notelep`, `katarak`, `tglkkl`, `keterangankkl`, `suplesi`, `no_sep_suplesi`, `kdprop`, `nmprop`, `kdkab`, `nmkab`, `kdkec`, `nmkec`, `noskdp`, `kddpjp`, `nmdpdjp`, `tujuankunjungan`, `flagprosedur`, `penunjang`, `asesmenpelayanan`, `kddpjplayanan`, `nmdpjplayanan`) VALUES
('0123R0010926V000001', '2026/09/08/0001', '2026-09-08', '2026-09-01', '0123B0010926P000001', '0123B001', 'PUSKESMAS GAMBIR', '0123R001', 'SIM KLINIK UTAMA', '2', 'Kontrol Rutin Hipertensi', 'I10', 'Essential (primary) hypertension', 'INT', 'PENYAKIT DALAM', '3', '', '', '', NULL, 'admin', '000001', 'BAMBANG HERMAWAN', '1980-08-15', 'PNS / ASKES', 'L', '0001234567891', NULL, '1. Faskes 1', '0. Tidak', '0. Tidak', '081298760001', '0. Tidak', '0000-00-00', '', '0. Tidak', '', '', '', '', '', '', '', 'SKDP-2026-001', '12345', 'dr. Budi Santoso, Sp.PD', '0', '', '', '', '12345', 'dr. Budi Santoso, Sp.PD');

-- --------------------------------------------------------

--
-- Struktur dari tabel `bridging_sep_internal`
--

DROP TABLE IF EXISTS `bridging_sep_internal`;
CREATE TABLE IF NOT EXISTS `bridging_sep_internal` (
  `no_sep` varchar(40) NOT NULL DEFAULT '',
  `no_rawat` varchar(17) DEFAULT NULL,
  `tglsep` date DEFAULT NULL,
  `tglrujukan` date DEFAULT NULL,
  `no_rujukan` varchar(40) DEFAULT NULL,
  `kdppkrujukan` varchar(12) DEFAULT NULL,
  `nmppkrujukan` varchar(200) DEFAULT NULL,
  `kdppkpelayanan` varchar(12) DEFAULT NULL,
  `nmppkpelayanan` varchar(200) DEFAULT NULL,
  `jnspelayanan` enum('1','2') DEFAULT NULL,
  `catatan` varchar(100) DEFAULT NULL,
  `diagawal` varchar(10) DEFAULT NULL,
  `nmdiagnosaawal` varchar(400) DEFAULT NULL,
  `kdpolitujuan` varchar(15) DEFAULT NULL,
  `nmpolitujuan` varchar(50) DEFAULT NULL,
  `klsrawat` enum('1','2','3') DEFAULT NULL,
  `klsnaik` enum('','1','2','3','4','5','6','7') NOT NULL,
  `pembiayaan` enum('','1','2','3') NOT NULL,
  `pjnaikkelas` varchar(100) NOT NULL,
  `lakalantas` enum('0','1','2','3') DEFAULT NULL,
  `user` varchar(25) DEFAULT NULL,
  `nomr` varchar(15) DEFAULT NULL,
  `nama_pasien` varchar(100) DEFAULT NULL,
  `tanggal_lahir` date DEFAULT NULL,
  `peserta` varchar(100) DEFAULT NULL,
  `jkel` enum('L','P') DEFAULT NULL,
  `no_kartu` varchar(25) DEFAULT NULL,
  `tglpulang` datetime DEFAULT NULL,
  `asal_rujukan` enum('1. Faskes 1','2. Faskes 2(RS)') NOT NULL,
  `eksekutif` enum('0. Tidak','1.Ya') NOT NULL,
  `cob` enum('0. Tidak','1.Ya') NOT NULL,
  `notelep` varchar(40) NOT NULL,
  `katarak` enum('0. Tidak','1.Ya') NOT NULL,
  `tglkkl` date NOT NULL,
  `keterangankkl` varchar(100) NOT NULL,
  `suplesi` enum('0. Tidak','1.Ya') NOT NULL,
  `no_sep_suplesi` varchar(40) NOT NULL,
  `kdprop` varchar(10) NOT NULL,
  `nmprop` varchar(50) NOT NULL,
  `kdkab` varchar(10) NOT NULL,
  `nmkab` varchar(50) NOT NULL,
  `kdkec` varchar(10) NOT NULL,
  `nmkec` varchar(50) NOT NULL,
  `noskdp` varchar(40) NOT NULL,
  `kddpjp` varchar(10) NOT NULL,
  `nmdpdjp` varchar(100) NOT NULL,
  `tujuankunjungan` enum('0','1','2') NOT NULL,
  `flagprosedur` enum('','0','1') NOT NULL,
  `penunjang` enum('','1','2','3','4','5','6','7','8','9','10','11','12') NOT NULL,
  `asesmenpelayanan` enum('','1','2','3','4','5') NOT NULL,
  `kddpjplayanan` varchar(10) NOT NULL,
  `nmdpjplayanan` varchar(100) NOT NULL,
  KEY `no_rawat` (`no_rawat`),
  KEY `no_sep` (`no_sep`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `bridging_srb_bpjs`
--

DROP TABLE IF EXISTS `bridging_srb_bpjs`;
CREATE TABLE IF NOT EXISTS `bridging_srb_bpjs` (
  `no_sep` varchar(40) NOT NULL,
  `no_srb` varchar(10) NOT NULL,
  `tgl_srb` date DEFAULT NULL,
  `alamat` varchar(200) DEFAULT NULL,
  `email` varchar(40) DEFAULT NULL,
  `kodeprogram` varchar(3) DEFAULT NULL,
  `namaprogram` varchar(70) DEFAULT NULL,
  `kodedpjp` varchar(10) DEFAULT NULL,
  `nmdpjp` varchar(100) DEFAULT NULL,
  `user` varchar(25) DEFAULT NULL,
  `keterangan` varchar(100) DEFAULT NULL,
  `saran` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`no_sep`,`no_srb`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `bridging_surat_kontrol_bpjs`
--

DROP TABLE IF EXISTS `bridging_surat_kontrol_bpjs`;
CREATE TABLE IF NOT EXISTS `bridging_surat_kontrol_bpjs` (
  `no_sep` varchar(40) DEFAULT NULL,
  `tgl_surat` date NOT NULL,
  `no_surat` varchar(40) NOT NULL,
  `tgl_rencana` date DEFAULT NULL,
  `kd_dokter_bpjs` varchar(20) DEFAULT NULL,
  `nm_dokter_bpjs` varchar(50) DEFAULT NULL,
  `kd_poli_bpjs` varchar(15) DEFAULT NULL,
  `nm_poli_bpjs` varchar(40) DEFAULT NULL,
  PRIMARY KEY (`no_surat`),
  KEY `bridging_surat_kontrol_bpjs_ibfk_1` (`no_sep`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `bridging_surat_pri_bpjs`
--

DROP TABLE IF EXISTS `bridging_surat_pri_bpjs`;
CREATE TABLE IF NOT EXISTS `bridging_surat_pri_bpjs` (
  `no_rawat` varchar(17) DEFAULT NULL,
  `no_kartu` varchar(25) DEFAULT NULL,
  `tgl_surat` date NOT NULL,
  `no_surat` varchar(40) NOT NULL,
  `tgl_rencana` date DEFAULT NULL,
  `kd_dokter_bpjs` varchar(20) DEFAULT NULL,
  `nm_dokter_bpjs` varchar(50) DEFAULT NULL,
  `kd_poli_bpjs` varchar(15) DEFAULT NULL,
  `nm_poli_bpjs` varchar(40) DEFAULT NULL,
  `diagnosa` varchar(130) NOT NULL,
  `no_sep` varchar(40) NOT NULL,
  PRIMARY KEY (`no_surat`),
  KEY `no_rawat` (`no_rawat`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `cacat_fisik`
--

DROP TABLE IF EXISTS `cacat_fisik`;
CREATE TABLE IF NOT EXISTS `cacat_fisik` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nama_cacat` varchar(30) NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `nama_cacat` (`nama_cacat`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `cacat_fisik`
--

INSERT INTO `cacat_fisik` (`id`, `nama_cacat`) VALUES
(1, '-'),
(4, 'Tuna Daksa'),
(3, 'Tuna Netra'),
(2, 'Tuna Rungu');

-- --------------------------------------------------------

--
-- Struktur dari tabel `catatan_adime_gizi`
--

DROP TABLE IF EXISTS `catatan_adime_gizi`;
CREATE TABLE IF NOT EXISTS `catatan_adime_gizi` (
  `no_rawat` varchar(17) NOT NULL,
  `tanggal` datetime NOT NULL,
  `asesmen` text,
  `diagnosis` text,
  `intervensi` text,
  `monitoring` text,
  `evaluasi` text,
  `instruksi` text,
  `nip` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`no_rawat`,`tanggal`) USING BTREE,
  KEY `nip` (`nip`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `catatan_perawatan`
--

DROP TABLE IF EXISTS `catatan_perawatan`;
CREATE TABLE IF NOT EXISTS `catatan_perawatan` (
  `tanggal` date DEFAULT NULL,
  `jam` time DEFAULT NULL,
  `no_rawat` varchar(17) DEFAULT NULL,
  `kd_dokter` varchar(20) DEFAULT NULL,
  `catatan` varchar(700) DEFAULT NULL,
  KEY `no_rawat` (`no_rawat`),
  KEY `kd_dokter` (`kd_dokter`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `databarang`
--

DROP TABLE IF EXISTS `databarang`;
CREATE TABLE IF NOT EXISTS `databarang` (
  `kode_brng` varchar(15) NOT NULL DEFAULT '',
  `nama_brng` varchar(80) DEFAULT NULL,
  `kode_satbesar` char(4) NOT NULL,
  `kode_sat` char(4) DEFAULT NULL,
  `letak_barang` varchar(100) DEFAULT NULL,
  `dasar` double NOT NULL,
  `h_beli` double DEFAULT NULL,
  `ralan` double DEFAULT NULL,
  `kelas1` double DEFAULT NULL,
  `kelas2` double DEFAULT NULL,
  `kelas3` double DEFAULT NULL,
  `utama` double DEFAULT NULL,
  `vip` double DEFAULT NULL,
  `vvip` double DEFAULT NULL,
  `beliluar` double DEFAULT NULL,
  `jualbebas` double DEFAULT NULL,
  `karyawan` double DEFAULT NULL,
  `stokminimal` double DEFAULT NULL,
  `kdjns` char(4) DEFAULT NULL,
  `isi` double NOT NULL,
  `kapasitas` double NOT NULL,
  `expire` date DEFAULT NULL,
  `status` enum('0','1') NOT NULL,
  `kode_industri` char(5) DEFAULT NULL,
  `kode_kategori` char(4) DEFAULT NULL,
  `kode_golongan` char(4) DEFAULT NULL,
  PRIMARY KEY (`kode_brng`),
  KEY `kode_sat` (`kode_sat`),
  KEY `kdjns` (`kdjns`),
  KEY `nama_brng` (`nama_brng`),
  KEY `letak_barang` (`letak_barang`),
  KEY `h_beli` (`h_beli`),
  KEY `h_distributor` (`ralan`),
  KEY `h_grosir` (`kelas1`),
  KEY `h_retail` (`kelas2`),
  KEY `stok` (`stokminimal`),
  KEY `kapasitas` (`kapasitas`),
  KEY `kode_industri` (`kode_industri`),
  KEY `kelas3` (`kelas3`),
  KEY `utama` (`utama`),
  KEY `vip` (`vip`),
  KEY `vvip` (`vvip`),
  KEY `beliluar` (`beliluar`),
  KEY `jualbebas` (`jualbebas`),
  KEY `karyawan` (`karyawan`),
  KEY `expire` (`expire`),
  KEY `status` (`status`),
  KEY `kode_kategori` (`kode_kategori`),
  KEY `kode_golongan` (`kode_golongan`),
  KEY `kode_satbesar` (`kode_satbesar`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `databarang`
--

INSERT INTO `databarang` (`kode_brng`, `nama_brng`, `kode_satbesar`, `kode_sat`, `letak_barang`, `dasar`, `h_beli`, `ralan`, `kelas1`, `kelas2`, `kelas3`, `utama`, `vip`, `vvip`, `beliluar`, `jualbebas`, `karyawan`, `stokminimal`, `kdjns`, `isi`, `kapasitas`, `expire`, `status`, `kode_industri`, `kode_kategori`, `kode_golongan`) VALUES
('B00001', 'Paracetamol 500mg', '-', '-', '-', 5000, 5000, 5000, 5000, 5000, 5000, 5000, 5000, 5000, 5000, 5000, 5000, 100, '-', 10, 500, '2024-06-10', '1', '-', '-', '-'),
('OB001', 'Paracetamol 500 mg Tablet', 'TAB', 'TAB', 'Rak Obat A', 350, 350, 500, 600, 600, 600, 600, 600, 600, 600, 600, 500, 50, 'J01', 100, 1, '2028-12-31', '1', 'IF01', 'KB01', 'GB01'),
('OB002', 'Amoxicillin 500 mg Kaplet', 'TAB', 'KAP', 'Rak Obat A', 650, 650, 900, 1100, 1100, 1100, 1100, 1100, 1100, 1100, 1100, 800, 50, 'J02', 100, 1, '2028-12-31', '1', 'IF01', 'KB02', 'GB01'),
('OB003', 'Amlodipine 10 mg Tablet', 'TAB', 'TAB', 'Rak Obat A', 450, 450, 700, 900, 900, 900, 900, 900, 900, 900, 900, 600, 50, 'J03', 100, 1, '2028-12-31', '1', 'IF02', 'KB03', 'GB01'),
('OB004', 'Captopril 25 mg Tablet', 'TAB', 'TAB', 'Rak Obat A', 300, 300, 500, 650, 650, 650, 650, 650, 650, 650, 650, 450, 50, 'J03', 100, 1, '2028-12-31', '1', 'IF01', 'KB03', 'GB01'),
('OB005', 'Metformin 500 mg Tablet', 'TAB', 'TAB', 'Rak Obat A', 400, 400, 650, 850, 850, 850, 850, 850, 850, 850, 850, 550, 50, 'J03', 100, 1, '2028-12-31', '1', 'IF04', 'KB04', 'GB01'),
('OB006', 'Glimepiride 2 mg Tablet', 'TAB', 'TAB', 'Rak Obat A', 700, 700, 1100, 1400, 1400, 1400, 1400, 1400, 1400, 1400, 1400, 1000, 50, 'J03', 100, 1, '2028-12-31', '1', 'IF04', 'KB04', 'GB01'),
('OB007', 'Omeprazole 20 mg Kapsul', 'TAB', 'KPS', 'Rak Obat A', 800, 800, 1200, 1500, 1500, 1500, 1500, 1500, 1500, 1500, 1500, 1100, 50, 'J01', 100, 1, '2028-12-31', '1', 'IF03', 'KB05', 'GB01'),
('OB008', 'Antasida Doen Tablet Kunyah', 'TAB', 'TAB', 'Rak Obat A', 250, 250, 400, 500, 500, 500, 500, 500, 500, 500, 500, 350, 50, 'J01', 100, 1, '2028-12-31', '1', 'IF01', 'KB05', 'GB01'),
('OB009', 'Cetirizine 10 mg Tablet', 'TAB', 'TAB', 'Rak Obat A', 400, 400, 650, 850, 850, 850, 850, 850, 850, 850, 850, 550, 50, 'J01', 100, 1, '2028-12-31', '1', 'IF02', 'KB06', 'GB01'),
('OB010', 'Allopurinol 100 mg Tablet', 'TAB', 'TAB', 'Rak Obat A', 450, 450, 700, 900, 900, 900, 900, 900, 900, 900, 900, 600, 50, 'J03', 100, 1, '2028-12-31', '1', 'IF01', 'KB01', 'GB01'),
('OB011', 'Asam Mefenamat 500 mg Kaplet', 'TAB', 'KAP', 'Rak Obat A', 500, 500, 800, 1000, 1000, 1000, 1000, 1000, 1000, 1000, 1000, 700, 50, 'J01', 100, 1, '2028-12-31', '1', 'IF03', 'KB01', 'GB01'),
('OB012', 'Vitamin C 500 mg Tablet', 'TAB', 'TAB', 'Rak Obat A', 300, 300, 500, 650, 650, 650, 650, 650, 650, 650, 650, 450, 50, 'J01', 100, 1, '2028-12-31', '1', 'IF01', 'KB07', 'GB01'),
('OB013', 'Vitamin B Complex Tablet', 'TAB', 'TAB', 'Rak Obat A', 250, 250, 400, 550, 550, 550, 550, 550, 550, 550, 550, 350, 50, 'J01', 100, 1, '2028-12-31', '1', 'IF01', 'KB07', 'GB01'),
('OB014', 'Ambroxol 30 mg Tablet', 'TAB', 'TAB', 'Rak Obat A', 350, 350, 550, 700, 700, 700, 700, 700, 700, 700, 700, 500, 50, 'J01', 100, 1, '2028-12-31', '1', 'IF02', 'KB01', 'GB01'),
('OB015', 'Ciprofloxacin 500 mg Tablet', 'TAB', 'TAB', 'Rak Obat A', 850, 850, 1300, 1600, 1600, 1600, 1600, 1600, 1600, 1600, 1600, 1200, 50, 'J02', 100, 1, '2028-12-31', '1', 'IF03', 'KB02', 'GB01'),
('OB016', 'Dexamethasone 0.5 mg Tablet', 'TAB', 'TAB', 'Rak Obat A', 200, 200, 350, 450, 450, 450, 450, 450, 450, 450, 450, 300, 50, 'J01', 100, 1, '2028-12-31', '1', 'IF01', 'KB06', 'GB01'),
('OB017', 'Salbutamol 2 mg Tablet', 'TAB', 'TAB', 'Rak Obat A', 300, 300, 500, 650, 650, 650, 650, 650, 650, 650, 650, 450, 50, 'J01', 100, 1, '2028-12-31', '1', 'IF01', 'KB01', 'GB01'),
('OB018', 'Paracetamol Sirup 120mg/5ml Botol 60ml', 'TAB', 'BTL', 'Rak Obat A', 6500, 6500, 9000, 11500, 11500, 11500, 11500, 11500, 11500, 11500, 11500, 8500, 50, 'J01', 100, 1, '2028-12-31', '1', 'IF02', 'KB01', 'GB01'),
('OB019', 'Amoxicillin Sirup Kering 125mg/5ml Botol 60ml', 'TAB', 'BTL', 'Rak Obat A', 8000, 8000, 11000, 14000, 14000, 14000, 14000, 14000, 14000, 14000, 14000, 10000, 50, 'J02', 100, 1, '2028-12-31', '1', 'IF01', 'KB02', 'GB01'),
('OB020', 'Antasida Doen Suspensi Botol 60ml', 'TAB', 'BTL', 'Rak Obat A', 5500, 5500, 8000, 10000, 10000, 10000, 10000, 10000, 10000, 10000, 10000, 7500, 50, 'J01', 100, 1, '2028-12-31', '1', 'IF01', 'KB05', 'GB01');

-- --------------------------------------------------------

--
-- Struktur dari tabel `data_tb`
--

DROP TABLE IF EXISTS `data_tb`;
CREATE TABLE IF NOT EXISTS `data_tb` (
  `no_rawat` varchar(17) NOT NULL,
  `id_tb_03` varchar(30) DEFAULT NULL,
  `id_periode_laporan` enum('1=Januari - Maret','2=April - Juni','3=Juli - September','4=Oktober - Desember') DEFAULT NULL,
  `tanggal_buat_laporan` datetime DEFAULT NULL,
  `tahun_buat_laporan` year DEFAULT NULL,
  `kd_wasor` int DEFAULT NULL,
  `noregkab` int DEFAULT NULL,
  `id_propinsi` varchar(15) DEFAULT NULL,
  `kd_kabupaten` varchar(15) DEFAULT NULL,
  `id_kecamatan` varchar(15) DEFAULT NULL,
  `id_kelurahan` varchar(15) DEFAULT NULL,
  `nama_rujukan` enum('Inisiatif pasien/Keluarga','Anggota Masyarakat/Kader','Faskes','Dokter Praktek Mandiri','Poli lain','Lain-lain') DEFAULT NULL,
  `sebutkan1` varchar(100) DEFAULT NULL,
  `tipe_diagnosis` enum('Terkonfirmasi bakteriologis','Terdiagnosis klinis') DEFAULT NULL,
  `klasifikasi_lokasi_anatomi` enum('Paru','Ekstraparu') DEFAULT NULL,
  `klasifikasi_riwayat_pengobatan` enum('Baru','Kambuh','Diobati setelah gagal','Diobati Setelah Putus Berobat','Lain-lain','Riwayat Pengobatan Sebelumnya Tidak Diketahui','Pindahan') DEFAULT NULL,
  `klasifikasi_status_hiv` enum('Positif','Negatif','Tidak diketahui') DEFAULT NULL,
  `total_skoring_anak` enum('1','2','3','4','5','6','7','8','9','10','11','12','13','Tidak dilakukan') DEFAULT NULL,
  `konfirmasiSkoring5` enum('Uji Tuberkulin Positif','Ada Kontak TB Paru','Uji Tuberkulin Negatif','Tidak Ada Kontak TB Paru') DEFAULT NULL,
  `konfirmasiSkoring6` enum('Ada Kontak TB Paru','Tidak Ada','Tidak Jelas Kontak TB Paru') DEFAULT NULL,
  `tanggal_mulai_pengobatan` date DEFAULT NULL,
  `paduan_oat` text,
  `sumber_obat` enum('Program TB','Bayar Sendiri','Asuransi','Lain-lain') DEFAULT NULL,
  `sebutkan` text,
  `sebelum_pengobatan_hasil_mikroskopis` enum('Negatif','1-19','1+','2+','3+','Tidak dilakukan') DEFAULT NULL,
  `sebelum_pengobatan_hasil_tes_cepat` enum('Rif sensitif','Rif resisten','Negatif','Rif Indeterminated','Invalid','Error','No Result','Tidak dilakukan') DEFAULT NULL,
  `sebelum_pengobatan_hasil_biakan` enum('Negatif','1-19 BTA','1+','2+','3+','4+','NTM','Kontaminasi','Tidak dilakukan') DEFAULT NULL,
  `noreglab_bulan_2` varchar(15) DEFAULT NULL,
  `hasil_mikroskopis_bulan_2` enum('Negatif','1-19','1+','2+','3+','Tidak dilakukan') DEFAULT NULL,
  `noreglab_bulan_3` varchar(15) DEFAULT NULL,
  `hasil_mikroskopis_bulan_3` enum('Negatif','1-19','1+','2+','3+','Tidak dilakukan') DEFAULT NULL,
  `noreglab_bulan_5` varchar(15) DEFAULT NULL,
  `hasil_mikroskopis_bulan_5` enum('Negatif','1-19','1+','2+','3+','Tidak dilakukan') DEFAULT NULL,
  `akhir_pengobatan_noreglab` varchar(15) DEFAULT NULL,
  `akhir_pengobatan_hasil_mikroskopis` enum('Negatif','1-19','1+','2+','3+','Tidak dilakukan') DEFAULT NULL,
  `tanggal_hasil_akhir_pengobatan` date DEFAULT NULL,
  `hasil_akhir_pengobatan` enum('Belum','Sembuh','Pengobatan Lengkap','Lost To Follow Up','Meninggal','Gagal','Pindah','') DEFAULT NULL,
  `tanggal_dianjurkan_tes` date DEFAULT NULL,
  `tanggal_tes_hiv` date DEFAULT NULL,
  `hasil_tes_hiv` enum('Reaktif','Non Reaktif','Indeterminated') DEFAULT NULL,
  `ppk` enum('Ya','Tidak') DEFAULT NULL,
  `art` enum('Ya','Tidak') DEFAULT NULL,
  `tb_dm` enum('Ya','Tidak') DEFAULT NULL,
  `terapi_dm` enum('OHO','Inj. Insulin','') DEFAULT NULL,
  `pindah_ro` enum('Ya','Tidak') DEFAULT NULL,
  `status_pengobatan` enum('Sesuai Standar','Tidak Sesuai Standar') DEFAULT NULL,
  `foto_toraks` enum('Positif','Negatif','Tidak Dilakukan') DEFAULT NULL,
  `toraks_tdk_dilakukan` enum('Tidak dilakukan','Setelah terapi antibioka non OAT: tidak ada perbaikan Klinis, ada faktor resiko TB, dan atas pertimbangan dokter','Setelah terapi antibioka non OAT: ada Perbaikan Klinis') DEFAULT NULL,
  `keterangan` varchar(100) DEFAULT NULL,
  `kode_icd_x` varchar(15) DEFAULT NULL,
  PRIMARY KEY (`no_rawat`),
  KEY `kode_icd_x` (`kode_icd_x`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `departemen`
--

DROP TABLE IF EXISTS `departemen`;
CREATE TABLE IF NOT EXISTS `departemen` (
  `dep_id` char(4) NOT NULL,
  `nama` varchar(25) NOT NULL,
  PRIMARY KEY (`dep_id`),
  KEY `nama` (`nama`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `departemen`
--

INSERT INTO `departemen` (`dep_id`, `nama`) VALUES
('-', '-'),
('DP03', 'Farmasi & Apotek'),
('DEP3', 'FARMASI & LOGISTIK'),
('DEP2', 'KEPERAWATAN'),
('DP02', 'Keperawatan'),
('DEP4', 'KEUANGAN & KASIR'),
('DP05', 'Keuangan & Kasir'),
('DP06', 'Manajemen & IT'),
('DEP1', 'PELAYANAN MEDIK'),
('DP01', 'Pelayanan Medis'),
('DP04', 'Rekam Medis'),
('DEP5', 'REKAM MEDIS & ADM');

-- --------------------------------------------------------

--
-- Struktur dari tabel `detail_obat_racikan`
--

DROP TABLE IF EXISTS `detail_obat_racikan`;
CREATE TABLE IF NOT EXISTS `detail_obat_racikan` (
  `tgl_perawatan` date NOT NULL,
  `jam` time NOT NULL,
  `no_rawat` varchar(17) NOT NULL,
  `no_racik` varchar(2) NOT NULL,
  `kode_brng` varchar(15) NOT NULL,
  PRIMARY KEY (`tgl_perawatan`,`jam`,`no_rawat`,`no_racik`,`kode_brng`),
  KEY `no_rawat` (`no_rawat`),
  KEY `kode_brng` (`kode_brng`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `detail_pemberian_obat`
--

DROP TABLE IF EXISTS `detail_pemberian_obat`;
CREATE TABLE IF NOT EXISTS `detail_pemberian_obat` (
  `tgl_perawatan` date NOT NULL DEFAULT '0000-00-00',
  `jam` time NOT NULL DEFAULT '00:00:00',
  `no_rawat` varchar(17) NOT NULL DEFAULT '',
  `kode_brng` varchar(15) NOT NULL,
  `h_beli` double DEFAULT NULL,
  `biaya_obat` double DEFAULT NULL,
  `jml` double NOT NULL,
  `embalase` double DEFAULT NULL,
  `tuslah` double DEFAULT NULL,
  `total` double NOT NULL,
  `status` enum('Ralan','Ranap') DEFAULT NULL,
  `kd_bangsal` char(5) DEFAULT NULL,
  `no_batch` varchar(20) NOT NULL,
  `no_faktur` varchar(20) NOT NULL,
  PRIMARY KEY (`tgl_perawatan`,`jam`,`no_rawat`,`kode_brng`,`no_batch`,`no_faktur`) USING BTREE,
  KEY `no_rawat` (`no_rawat`),
  KEY `kd_obat` (`kode_brng`),
  KEY `tgl_perawatan` (`tgl_perawatan`),
  KEY `jam` (`jam`),
  KEY `jml` (`jml`),
  KEY `tambahan` (`embalase`),
  KEY `total` (`total`),
  KEY `biaya_obat` (`biaya_obat`),
  KEY `kd_bangsal` (`kd_bangsal`),
  KEY `tuslah` (`tuslah`) USING BTREE,
  KEY `status` (`status`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `detail_pemberian_obat`
--

INSERT INTO `detail_pemberian_obat` (`tgl_perawatan`, `jam`, `no_rawat`, `kode_brng`, `h_beli`, `biaya_obat`, `jml`, `embalase`, `tuslah`, `total`, `status`, `kd_bangsal`, `no_batch`, `no_faktur`) VALUES
('2026-09-08', '08:15:00', '2026/09/08/0001', 'OB003', 500, 800, 30, 1000, 1000, 26000, 'Ralan', 'AP01', 'BATCH-2026-001', 'FAK-2026-001'),
('2026-09-08', '08:30:00', '2026/09/08/0002', 'OB001', 500, 800, 10, 1000, 1000, 10000, 'Ralan', 'AP01', 'BATCH-2026-001', 'FAK-2026-001'),
('2026-09-08', '08:30:00', '2026/09/08/0002', 'OB002', 500, 800, 10, 1000, 1000, 10000, 'Ralan', 'AP01', 'BATCH-2026-001', 'FAK-2026-001'),
('2026-09-08', '08:30:00', '2026/09/08/0002', 'OB014', 500, 800, 10, 1000, 1000, 10000, 'Ralan', 'AP01', 'BATCH-2026-001', 'FAK-2026-001'),
('2026-09-08', '09:00:00', '2026/09/08/0003', 'OB002', 500, 800, 10, 1000, 1000, 10000, 'Ralan', 'AP01', 'BATCH-2026-001', 'FAK-2026-001'),
('2026-09-08', '09:00:00', '2026/09/08/0003', 'OB011', 500, 800, 10, 1000, 1000, 10000, 'Ralan', 'AP01', 'BATCH-2026-001', 'FAK-2026-001'),
('2026-09-08', '09:30:00', '2026/09/08/0004', 'OB012', 500, 800, 30, 1000, 1000, 26000, 'Ralan', 'AP01', 'BATCH-2026-001', 'FAK-2026-001'),
('2026-09-08', '09:30:00', '2026/09/08/0004', 'OB013', 500, 800, 30, 1000, 1000, 26000, 'Ralan', 'AP01', 'BATCH-2026-001', 'FAK-2026-001'),
('2026-09-08', '10:00:00', '2026/09/08/0005', 'OB007', 500, 800, 14, 1000, 1000, 13200, 'Ralan', 'AP01', 'BATCH-2026-001', 'FAK-2026-001'),
('2026-09-08', '10:00:00', '2026/09/08/0005', 'OB008', 500, 800, 20, 1000, 1000, 18000, 'Ralan', 'AP01', 'BATCH-2026-001', 'FAK-2026-001'),
('2026-09-08', '10:30:00', '2026/09/08/0006', 'OB005', 500, 800, 60, 1000, 1000, 50000, 'Ralan', 'AP01', 'BATCH-2026-001', 'FAK-2026-001'),
('2026-09-08', '10:30:00', '2026/09/08/0006', 'OB006', 500, 800, 30, 1000, 1000, 26000, 'Ralan', 'AP01', 'BATCH-2026-001', 'FAK-2026-001'),
('2026-09-08', '11:00:00', '2026/09/08/0007', 'OB018', 500, 800, 1, 1000, 1000, 2800, 'Ralan', 'AP01', 'BATCH-2026-001', 'FAK-2026-001'),
('2026-09-08', '11:00:00', '2026/09/08/0007', 'OB019', 500, 800, 1, 1000, 1000, 2800, 'Ralan', 'AP01', 'BATCH-2026-001', 'FAK-2026-001'),
('2026-09-08', '11:30:00', '2026/09/08/0008', 'OB001', 500, 800, 10, 1000, 1000, 10000, 'Ralan', 'AP01', 'BATCH-2026-001', 'FAK-2026-001'),
('2026-09-08', '11:30:00', '2026/09/08/0008', 'OB008', 500, 800, 10, 1000, 1000, 10000, 'Ralan', 'AP01', 'BATCH-2026-001', 'FAK-2026-001'),
('2026-09-08', '11:30:00', '2026/09/08/0008', 'OB015', 500, 800, 10, 1000, 1000, 10000, 'Ralan', 'AP01', 'BATCH-2026-001', 'FAK-2026-001'),
('2026-09-08', '12:00:00', '2026/09/08/0009', 'OB010', 500, 800, 30, 1000, 1000, 26000, 'Ralan', 'AP01', 'BATCH-2026-001', 'FAK-2026-001'),
('2026-09-08', '12:00:00', '2026/09/08/0009', 'OB011', 500, 800, 10, 1000, 1000, 10000, 'Ralan', 'AP01', 'BATCH-2026-001', 'FAK-2026-001'),
('2026-09-08', '12:30:00', '2026/09/08/0010', 'OB002', 500, 800, 10, 1000, 1000, 10000, 'Ralan', 'AP01', 'BATCH-2026-001', 'FAK-2026-001'),
('2026-09-08', '12:30:00', '2026/09/08/0010', 'OB011', 500, 800, 10, 1000, 1000, 10000, 'Ralan', 'AP01', 'BATCH-2026-001', 'FAK-2026-001');

-- --------------------------------------------------------

--
-- Struktur dari tabel `detail_periksa_lab`
--

DROP TABLE IF EXISTS `detail_periksa_lab`;
CREATE TABLE IF NOT EXISTS `detail_periksa_lab` (
  `no_rawat` varchar(17) NOT NULL,
  `kd_jenis_prw` varchar(15) NOT NULL,
  `tgl_periksa` date NOT NULL,
  `jam` time NOT NULL,
  `id_template` int NOT NULL,
  `nilai` varchar(200) NOT NULL,
  `nilai_rujukan` varchar(30) NOT NULL,
  `keterangan` varchar(60) NOT NULL,
  `bagian_rs` double NOT NULL,
  `bhp` double NOT NULL,
  `bagian_perujuk` double NOT NULL,
  `bagian_dokter` double NOT NULL,
  `bagian_laborat` double NOT NULL,
  `kso` double DEFAULT NULL,
  `menejemen` double DEFAULT NULL,
  `biaya_item` double NOT NULL,
  PRIMARY KEY (`no_rawat`,`kd_jenis_prw`,`tgl_periksa`,`jam`,`id_template`),
  KEY `id_template` (`id_template`),
  KEY `kd_jenis_prw` (`kd_jenis_prw`),
  KEY `tgl_periksa` (`tgl_periksa`),
  KEY `jam` (`jam`),
  KEY `nilai` (`nilai`),
  KEY `nilai_rujukan` (`nilai_rujukan`),
  KEY `keterangan` (`keterangan`),
  KEY `biaya_item` (`biaya_item`),
  KEY `menejemen` (`menejemen`),
  KEY `kso` (`kso`),
  KEY `bagian_rs` (`bagian_rs`),
  KEY `bhp` (`bhp`),
  KEY `bagian_perujuk` (`bagian_perujuk`),
  KEY `bagian_dokter` (`bagian_dokter`),
  KEY `bagian_laborat` (`bagian_laborat`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `detail_periksa_lab`
--

INSERT INTO `detail_periksa_lab` (`no_rawat`, `kd_jenis_prw`, `tgl_periksa`, `jam`, `id_template`, `nilai`, `nilai_rujukan`, `keterangan`, `bagian_rs`, `bhp`, `bagian_perujuk`, `bagian_dokter`, `bagian_laborat`, `kso`, `menejemen`, `biaya_item`) VALUES
('2026/09/08/0001', 'LAB006', '2026-09-08', '08:14:00', 23, 'Normal', '0 - 37', '', 0, 0, 0, 0, 0, 0, 0, 0),
('2026/09/08/0001', 'LAB006', '2026-09-08', '08:14:00', 24, 'Normal', '0 - 42', '', 0, 0, 0, 0, 0, 0, 0, 0),
('2026/09/08/0009', 'LAB004', '2026-09-08', '08:09:00', 16, '', '< 200', '', 0, 0, 0, 0, 0, 0, 0, 0),
('2026/09/08/0009', 'LAB004', '2026-09-08', '08:09:00', 17, '', '< 150', '', 0, 0, 0, 0, 0, 0, 0, 0),
('2026/09/08/0009', 'LAB004', '2026-09-08', '08:09:00', 18, '', '> 40', '', 0, 0, 0, 0, 0, 0, 0, 0),
('2026/09/08/0009', 'LAB004', '2026-09-08', '08:09:00', 19, '', '< 100', '', 0, 0, 0, 0, 0, 0, 0, 0),
('2026/09/08/0009', 'LAB005', '2026-09-08', '08:09:00', 20, 'Normal', '15.0 - 45.0', 'Low (Rendah)', 0, 0, 0, 0, 0, 0, 0, 0),
('2026/09/08/0009', 'LAB005', '2026-09-08', '08:09:00', 21, 'Normal', '0.70 - 1.30', '', 0, 0, 0, 0, 0, 0, 0, 0),
('2026/09/08/0009', 'LAB005', '2026-09-08', '08:09:00', 22, 'Normal', '3.5 - 7.2', '', 0, 0, 0, 0, 0, 0, 0, 0),
('2026/09/08/0010', 'LAB001', '2026-09-08', '09:30:00', 1, '14.2', '13.0 - 17.5', 'Normal', 0, 0, 0, 0, 0, 0, 0, 0),
('2026/09/08/0010', 'LAB001', '2026-09-08', '09:30:00', 2, '3.8', '4.5 - 11.0', 'Low (Leukopenia)', 0, 0, 0, 0, 0, 0, 0, 0),
('2026/09/08/0010', 'LAB001', '2026-09-08', '09:30:00', 3, '4.85', '4.50 - 5.90', 'Normal', 0, 0, 0, 0, 0, 0, 0, 0),
('2026/09/08/0010', 'LAB001', '2026-09-08', '09:30:00', 4, '42.5', '40.0 - 52.0', 'Normal', 0, 0, 0, 0, 0, 0, 0, 0),
('2026/09/08/0010', 'LAB001', '2026-09-08', '09:30:00', 5, '98', '150 - 450', 'Low (Trombositopenia)', 0, 0, 0, 0, 0, 0, 0, 0),
('2026/09/08/0010', 'LAB001', '2026-09-08', '09:30:00', 6, '12', '0 - 15', 'Normal', 0, 0, 0, 0, 0, 0, 0, 0),
('2026/09/08/0010', 'LAB001', '2026-09-08', '09:30:00', 7, '0', '0 - 1', 'Normal', 0, 0, 0, 0, 0, 0, 0, 0),
('2026/09/08/0010', 'LAB001', '2026-09-08', '09:30:00', 8, '1', '1 - 3', 'Normal', 0, 0, 0, 0, 0, 0, 0, 0),
('2026/09/08/0010', 'LAB001', '2026-09-08', '09:30:00', 9, '3', '2 - 6', 'Normal', 0, 0, 0, 0, 0, 0, 0, 0),
('2026/09/08/0010', 'LAB001', '2026-09-08', '09:30:00', 10, '58', '50 - 70', 'Normal', 0, 0, 0, 0, 0, 0, 0, 0),
('2026/09/08/0010', 'LAB001', '2026-09-08', '09:30:00', 11, '34', '20 - 40', 'Normal', 0, 0, 0, 0, 0, 0, 0, 0),
('2026/09/08/0010', 'LAB001', '2026-09-08', '09:30:00', 12, '4', '2 - 8', 'Normal', 0, 0, 0, 0, 0, 0, 0, 0),
('2026/09/08/0010', 'LAB002', '2026-09-08', '09:30:00', 13, '118', '< 140', 'Normal (GDS)', 0, 0, 0, 0, 0, 0, 0, 0);

-- --------------------------------------------------------

--
-- Struktur dari tabel `diagnosa_pasien`
--

DROP TABLE IF EXISTS `diagnosa_pasien`;
CREATE TABLE IF NOT EXISTS `diagnosa_pasien` (
  `no_rawat` varchar(17) NOT NULL,
  `kd_penyakit` varchar(10) NOT NULL,
  `status` enum('Ralan','Ranap') NOT NULL,
  `prioritas` tinyint NOT NULL,
  `status_penyakit` enum('Lama','Baru') DEFAULT NULL,
  PRIMARY KEY (`no_rawat`,`kd_penyakit`,`status`),
  KEY `kd_penyakit` (`kd_penyakit`),
  KEY `status` (`status`),
  KEY `prioritas` (`prioritas`),
  KEY `no_rawat` (`no_rawat`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `diagnosa_pasien`
--

INSERT INTO `diagnosa_pasien` (`no_rawat`, `kd_penyakit`, `status`, `prioritas`, `status_penyakit`) VALUES
('2026/09/08/0001', 'I10', 'Ralan', 1, 'Baru'),
('2026/09/08/0002', 'J06.9', 'Ralan', 1, 'Baru'),
('2026/09/08/0003', 'K02.9', 'Ralan', 1, 'Baru'),
('2026/09/08/0004', 'O80.0', 'Ralan', 1, 'Baru'),
('2026/09/08/0005', 'K29.7', 'Ralan', 1, 'Baru'),
('2026/09/08/0006', 'E11.9', 'Ralan', 1, 'Baru'),
('2026/09/08/0007', 'J00', 'Ralan', 1, 'Baru'),
('2026/09/08/0008', 'A09.9', 'Ralan', 1, 'Baru'),
('2026/09/08/0009', 'M10.9', 'Ralan', 1, 'Baru'),
('2026/09/08/0010', 'J06.9', 'Ralan', 1, 'Baru');

-- --------------------------------------------------------

--
-- Struktur dari tabel `dokter`
--

DROP TABLE IF EXISTS `dokter`;
CREATE TABLE IF NOT EXISTS `dokter` (
  `kd_dokter` varchar(20) NOT NULL,
  `nm_dokter` varchar(50) DEFAULT NULL,
  `jk` enum('L','P') DEFAULT NULL,
  `tmp_lahir` varchar(20) DEFAULT NULL,
  `tgl_lahir` date DEFAULT NULL,
  `gol_drh` enum('A','B','O','AB','-') DEFAULT NULL,
  `agama` varchar(12) DEFAULT NULL,
  `almt_tgl` varchar(60) DEFAULT NULL,
  `no_telp` varchar(13) DEFAULT NULL,
  `stts_nikah` enum('BELUM MENIKAH','MENIKAH','JANDA','DUDHA','JOMBLO') DEFAULT NULL,
  `kd_sps` char(5) DEFAULT NULL,
  `alumni` varchar(60) DEFAULT NULL,
  `no_ijn_praktek` varchar(120) DEFAULT NULL,
  `status` enum('0','1') NOT NULL,
  PRIMARY KEY (`kd_dokter`),
  KEY `kd_sps` (`kd_sps`),
  KEY `nm_dokter` (`nm_dokter`),
  KEY `jk` (`jk`),
  KEY `tmp_lahir` (`tmp_lahir`),
  KEY `tgl_lahir` (`tgl_lahir`),
  KEY `gol_drh` (`gol_drh`),
  KEY `agama` (`agama`),
  KEY `almt_tgl` (`almt_tgl`),
  KEY `no_telp` (`no_telp`),
  KEY `stts_nikah` (`stts_nikah`),
  KEY `alumni` (`alumni`),
  KEY `no_ijn_praktek` (`no_ijn_praktek`),
  KEY `kd_dokter` (`kd_dokter`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `dokter`
--

INSERT INTO `dokter` (`kd_dokter`, `nm_dokter`, `jk`, `tmp_lahir`, `tgl_lahir`, `gol_drh`, `agama`, `almt_tgl`, `no_telp`, `stts_nikah`, `kd_sps`, `alumni`, `no_ijn_praktek`, `status`) VALUES
('DR001', 'dr. Ataaka Muhammad, Sp.PD', 'L', 'Barabai', '2000-09-18', 'O', 'Islam', 'Barabai', '-', 'MENIKAH', 'SPS-P', 'UI', 'SIP.503/001/DOK-SP/2023', '1'),
('DR002', 'dr. Siti Nurhaliza', 'P', 'Barabai', '1985-01-01', 'O', 'Islam', 'Jl. Pahlawan No. 45', '08123456002', 'MENIKAH', 'UMUM', 'Universitas Lambung Mangkurat', 'SIP.503/002/DOK-UM/2023', '1'),
('DR003', 'dr. Budi Santoso, Sp.A', 'L', 'Barabai', '1985-01-01', 'O', 'Islam', 'Jl. Hasan Basri No. 8', '08123456003', 'MENIKAH', 'SPS-A', 'Universitas Lambung Mangkurat', 'SIP.503/003/DOK-SP/2023', '1'),
('DR004', 'drg. Amanda Putri', 'P', 'Barabai', '1985-01-01', 'O', 'Islam', 'Jl. Merdeka No. 17', '08123456004', 'MENIKAH', 'SPS-G', 'Universitas Lambung Mangkurat', 'SIP.503/004/DOK-GG/2023', '1'),
('DR005', 'dr. Hendra Wijaya, Sp.OG', 'L', 'Barabai', '1985-01-01', 'O', 'Islam', 'Jl. Sudirman No. 99', '08123456005', 'MENIKAH', 'SPS-O', 'Universitas Lambung Mangkurat', 'SIP.503/005/DOK-SP/2023', '1'),
('DR006', 'dr. Ratna Sari', 'P', 'Barabai', '1985-01-01', 'O', 'Islam', 'Jl. Veteran No. 33', '08123456006', 'MENIKAH', 'UMUM', 'Universitas Lambung Mangkurat', 'SIP.503/006/DOK-UM/2023', '1');

-- --------------------------------------------------------

--
-- Struktur dari tabel `dpjp_ranap`
--

DROP TABLE IF EXISTS `dpjp_ranap`;
CREATE TABLE IF NOT EXISTS `dpjp_ranap` (
  `no_rawat` varchar(17) NOT NULL,
  `kd_dokter` varchar(20) NOT NULL,
  PRIMARY KEY (`no_rawat`,`kd_dokter`),
  KEY `dpjp_ranap_ibfk_2` (`kd_dokter`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `emergency_index`
--

DROP TABLE IF EXISTS `emergency_index`;
CREATE TABLE IF NOT EXISTS `emergency_index` (
  `kode_emergency` varchar(3) NOT NULL,
  `nama_emergency` varchar(200) DEFAULT NULL,
  `indek` tinyint DEFAULT NULL,
  PRIMARY KEY (`kode_emergency`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `emergency_index`
--

INSERT INTO `emergency_index` (`kode_emergency`, `nama_emergency`, `indek`) VALUES
('-', '-', 1);

-- --------------------------------------------------------

--
-- Struktur dari tabel `gambar_radiologi`
--

DROP TABLE IF EXISTS `gambar_radiologi`;
CREATE TABLE IF NOT EXISTS `gambar_radiologi` (
  `no_rawat` varchar(17) NOT NULL,
  `tgl_periksa` date NOT NULL,
  `jam` time NOT NULL,
  `lokasi_gambar` varchar(255) NOT NULL,
  PRIMARY KEY (`no_rawat`,`tgl_periksa`,`jam`,`lokasi_gambar`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `golongan_barang`
--

DROP TABLE IF EXISTS `golongan_barang`;
CREATE TABLE IF NOT EXISTS `golongan_barang` (
  `kode` char(4) NOT NULL,
  `nama` varchar(30) DEFAULT NULL,
  PRIMARY KEY (`kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `golongan_barang`
--

INSERT INTO `golongan_barang` (`kode`, `nama`) VALUES
('-', '-'),
('G01', 'Obat Bebas (Hijau)'),
('G02', 'Obat Bebas Terbatas (Biru)'),
('G03', 'Obat Keras (Merah / K)'),
('G04', 'Psikotropika'),
('G05', 'Alat Kesehatan Standar'),
('GB01', 'Generik'),
('GB02', 'Paten / Branded'),
('GB03', 'Alkes & BMHP');

-- --------------------------------------------------------

--
-- Struktur dari tabel `gudangbarang`
--

DROP TABLE IF EXISTS `gudangbarang`;
CREATE TABLE IF NOT EXISTS `gudangbarang` (
  `kode_brng` varchar(15) NOT NULL,
  `kd_bangsal` char(5) NOT NULL DEFAULT '',
  `stok` double NOT NULL,
  `no_batch` varchar(20) NOT NULL,
  `no_faktur` varchar(20) NOT NULL,
  PRIMARY KEY (`kode_brng`,`kd_bangsal`,`no_batch`,`no_faktur`) USING BTREE,
  KEY `kode_brng` (`kode_brng`),
  KEY `stok` (`stok`),
  KEY `kd_bangsal` (`kd_bangsal`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `gudangbarang`
--

INSERT INTO `gudangbarang` (`kode_brng`, `kd_bangsal`, `stok`, `no_batch`, `no_faktur`) VALUES
('OB001', 'AP01', 200, 'BATCH-2026-001', 'FAK-2026-001'),
('OB002', 'AP01', 200, 'BATCH-2026-001', 'FAK-2026-001'),
('OB003', 'AP01', 200, 'BATCH-2026-001', 'FAK-2026-001'),
('OB004', 'AP01', 200, 'BATCH-2026-001', 'FAK-2026-001'),
('OB005', 'AP01', 200, 'BATCH-2026-001', 'FAK-2026-001'),
('OB006', 'AP01', 200, 'BATCH-2026-001', 'FAK-2026-001'),
('OB007', 'AP01', 200, 'BATCH-2026-001', 'FAK-2026-001'),
('OB008', 'AP01', 200, 'BATCH-2026-001', 'FAK-2026-001'),
('OB009', 'AP01', 200, 'BATCH-2026-001', 'FAK-2026-001'),
('OB010', 'AP01', 200, 'BATCH-2026-001', 'FAK-2026-001'),
('OB011', 'AP01', 200, 'BATCH-2026-001', 'FAK-2026-001'),
('OB012', 'AP01', 200, 'BATCH-2026-001', 'FAK-2026-001'),
('OB013', 'AP01', 200, 'BATCH-2026-001', 'FAK-2026-001'),
('OB014', 'AP01', 200, 'BATCH-2026-001', 'FAK-2026-001'),
('OB015', 'AP01', 200, 'BATCH-2026-001', 'FAK-2026-001'),
('OB016', 'AP01', 200, 'BATCH-2026-001', 'FAK-2026-001'),
('OB017', 'AP01', 200, 'BATCH-2026-001', 'FAK-2026-001'),
('OB018', 'AP01', 200, 'BATCH-2026-001', 'FAK-2026-001'),
('OB019', 'AP01', 200, 'BATCH-2026-001', 'FAK-2026-001'),
('OB020', 'AP01', 200, 'BATCH-2026-001', 'FAK-2026-001'),
('OB001', 'G001', 500, 'BATCH-2026-001', 'FAK-2026-001'),
('OB002', 'G001', 500, 'BATCH-2026-001', 'FAK-2026-001'),
('OB003', 'G001', 500, 'BATCH-2026-001', 'FAK-2026-001'),
('OB004', 'G001', 500, 'BATCH-2026-001', 'FAK-2026-001'),
('OB005', 'G001', 500, 'BATCH-2026-001', 'FAK-2026-001'),
('OB006', 'G001', 500, 'BATCH-2026-001', 'FAK-2026-001'),
('OB007', 'G001', 500, 'BATCH-2026-001', 'FAK-2026-001'),
('OB008', 'G001', 500, 'BATCH-2026-001', 'FAK-2026-001'),
('OB009', 'G001', 500, 'BATCH-2026-001', 'FAK-2026-001'),
('OB010', 'G001', 500, 'BATCH-2026-001', 'FAK-2026-001'),
('OB011', 'G001', 500, 'BATCH-2026-001', 'FAK-2026-001'),
('OB012', 'G001', 500, 'BATCH-2026-001', 'FAK-2026-001'),
('OB013', 'G001', 500, 'BATCH-2026-001', 'FAK-2026-001'),
('OB014', 'G001', 500, 'BATCH-2026-001', 'FAK-2026-001'),
('OB015', 'G001', 500, 'BATCH-2026-001', 'FAK-2026-001'),
('OB016', 'G001', 500, 'BATCH-2026-001', 'FAK-2026-001'),
('OB017', 'G001', 500, 'BATCH-2026-001', 'FAK-2026-001'),
('OB018', 'G001', 500, 'BATCH-2026-001', 'FAK-2026-001'),
('OB019', 'G001', 500, 'BATCH-2026-001', 'FAK-2026-001'),
('OB020', 'G001', 500, 'BATCH-2026-001', 'FAK-2026-001');

-- --------------------------------------------------------

--
-- Struktur dari tabel `hasil_radiologi`
--

DROP TABLE IF EXISTS `hasil_radiologi`;
CREATE TABLE IF NOT EXISTS `hasil_radiologi` (
  `no_rawat` varchar(17) NOT NULL,
  `tgl_periksa` date NOT NULL,
  `jam` time NOT NULL,
  `hasil` text NOT NULL,
  PRIMARY KEY (`no_rawat`,`tgl_periksa`,`jam`),
  KEY `no_rawat` (`no_rawat`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `icd9`
--

DROP TABLE IF EXISTS `icd9`;
CREATE TABLE IF NOT EXISTS `icd9` (
  `kode` varchar(8) NOT NULL,
  `deskripsi_panjang` varchar(250) DEFAULT NULL,
  `deskripsi_pendek` varchar(40) DEFAULT NULL,
  PRIMARY KEY (`kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `icd9`
--

INSERT INTO `icd9` (`kode`, `deskripsi_panjang`, `deskripsi_pendek`) VALUES
('23.09', 'Extraction of other tooth (Pencabutan Gigi)', 'Extraction of other tooth (Pencabutan Gi'),
('23.2', 'Restoration of tooth by filling (Penambalan Gigi)', 'Restoration of tooth by filling (Penamba'),
('57.94', 'Insertion of indwelling urinary catheter (Pemasangan Kateter Urine)', 'Insertion of indwelling urinary catheter'),
('86.59', 'Other suture of skin and subcutaneous tissue (Penjahitan Luka)', 'Other suture of skin and subcutaneous ti'),
('88.78', 'Diagnostic ultrasound of gravid uterus (USG Kandungan)', 'Diagnostic ultrasound of gravid uterus ('),
('89.07', 'General physical examination / Konsultasi & Pemeriksaan Fisik', 'General physical examination / Konsultas'),
('89.52', 'Electrocardiogram (EKG 12 Lead)', 'Electrocardiogram (EKG 12 Lead)'),
('93.94', 'Respiratory medication administered by nebulizer (Inhalasi / Nebulisasi)', 'Respiratory medication administered by n'),
('95.41', 'Audiometry examination / Irigasi Serumen Telinga', 'Audiometry examination / Irigasi Serumen'),
('96.07', 'Insertion of other (naso-)gastric tube (Pemasangan NGT)', 'Insertion of other (naso-)gastric tube ('),
('96.54', 'Dental scaling and polishing (Pembersihan Karang Gigi)', 'Dental scaling and polishing (Pembersiha'),
('96.59', 'Other irrigation of wound and simple dressing', 'Ganti Verband / Rawat Luka'),
('99.21', 'Injection of antibiotic (Injeksi Antibiotik)', 'Injection of antibiotic (Injeksi Antibio');

-- --------------------------------------------------------

--
-- Struktur dari tabel `industrifarmasi`
--

DROP TABLE IF EXISTS `industrifarmasi`;
CREATE TABLE IF NOT EXISTS `industrifarmasi` (
  `kode_industri` char(5) NOT NULL DEFAULT '',
  `nama_industri` varchar(50) DEFAULT NULL,
  `alamat` varchar(50) DEFAULT NULL,
  `kota` varchar(20) DEFAULT NULL,
  `no_telp` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`kode_industri`),
  KEY `nama_industri` (`nama_industri`),
  KEY `alamat` (`alamat`),
  KEY `kota` (`kota`),
  KEY `no_telp` (`no_telp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `industrifarmasi`
--

INSERT INTO `industrifarmasi` (`kode_industri`, `nama_industri`, `alamat`, `kota`, `no_telp`) VALUES
('-', '-', '-', '-', '0'),
('IF01', 'PT Kimia Farma Trading & Distribution', 'Jl. Veteran No. 9', 'Banjarmasin', '0511-3251234'),
('IF02', 'PT Kalbe Farma Tbk', 'Kawasan Industri Pulogadung', 'Jakarta Timur', '021-4608888'),
('IF03', 'PT Sanbe Farma', 'Jl. Tamansari No. 10', 'Bandung', '022-4207777'),
('IF04', 'PT Dexa Medica', 'Titan Center Bintaro', 'Tangerang Selatan', '021-7451234'),
('IF05', 'PT Bio Farma (Persero)', 'Jl. Pasteur No. 28', 'Bandung', '022-2033755');

-- --------------------------------------------------------

--
-- Struktur dari tabel `inventaris`
--

DROP TABLE IF EXISTS `inventaris`;
CREATE TABLE IF NOT EXISTS `inventaris` (
  `no_inventaris` varchar(30) NOT NULL,
  `kode_barang` varchar(20) DEFAULT NULL,
  `asal_barang` enum('Beli','Bantuan','Hibah','-') DEFAULT NULL,
  `tgl_pengadaan` date DEFAULT NULL,
  `harga` double DEFAULT NULL,
  `status_barang` enum('Ada','Rusak','Hilang','Perbaikan','Dipinjam','-') DEFAULT NULL,
  `id_ruang` char(5) DEFAULT NULL,
  `no_rak` char(3) DEFAULT NULL,
  `no_box` char(3) DEFAULT NULL,
  PRIMARY KEY (`no_inventaris`),
  KEY `kode_barang` (`kode_barang`),
  KEY `kd_ruang` (`id_ruang`),
  KEY `asal_barang` (`asal_barang`),
  KEY `tgl_pengadaan` (`tgl_pengadaan`),
  KEY `harga` (`harga`),
  KEY `status_barang` (`status_barang`),
  KEY `no_rak` (`no_rak`),
  KEY `no_box` (`no_box`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `inventaris_barang`
--

DROP TABLE IF EXISTS `inventaris_barang`;
CREATE TABLE IF NOT EXISTS `inventaris_barang` (
  `kode_barang` varchar(20) NOT NULL,
  `nama_barang` varchar(60) DEFAULT NULL,
  `jml_barang` int DEFAULT NULL,
  `kode_produsen` varchar(10) DEFAULT NULL,
  `id_merk` varchar(10) DEFAULT NULL,
  `thn_produksi` year DEFAULT NULL,
  `isbn` varchar(20) DEFAULT NULL,
  `id_kategori` char(10) DEFAULT NULL,
  `id_jenis` char(10) DEFAULT NULL,
  PRIMARY KEY (`kode_barang`),
  KEY `kode_produsen` (`kode_produsen`),
  KEY `id_merk` (`id_merk`),
  KEY `id_kategori` (`id_kategori`),
  KEY `id_jenis` (`id_jenis`),
  KEY `nama_barang` (`nama_barang`),
  KEY `jml_barang` (`jml_barang`),
  KEY `thn_produksi` (`thn_produksi`),
  KEY `isbn` (`isbn`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `inventaris_jenis`
--

DROP TABLE IF EXISTS `inventaris_jenis`;
CREATE TABLE IF NOT EXISTS `inventaris_jenis` (
  `id_jenis` char(10) NOT NULL,
  `nama_jenis` varchar(40) DEFAULT NULL,
  PRIMARY KEY (`id_jenis`),
  KEY `nama_jenis` (`nama_jenis`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `inventaris_kategori`
--

DROP TABLE IF EXISTS `inventaris_kategori`;
CREATE TABLE IF NOT EXISTS `inventaris_kategori` (
  `id_kategori` char(10) NOT NULL,
  `nama_kategori` varchar(40) DEFAULT NULL,
  PRIMARY KEY (`id_kategori`),
  KEY `nama_kategori` (`nama_kategori`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `inventaris_merk`
--

DROP TABLE IF EXISTS `inventaris_merk`;
CREATE TABLE IF NOT EXISTS `inventaris_merk` (
  `id_merk` varchar(10) NOT NULL,
  `nama_merk` varchar(40) NOT NULL,
  PRIMARY KEY (`id_merk`),
  KEY `nama_merk` (`nama_merk`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `inventaris_peminjaman`
--

DROP TABLE IF EXISTS `inventaris_peminjaman`;
CREATE TABLE IF NOT EXISTS `inventaris_peminjaman` (
  `peminjam` varchar(50) NOT NULL DEFAULT '',
  `tlp` varchar(13) NOT NULL,
  `no_inventaris` varchar(30) NOT NULL DEFAULT '',
  `tgl_pinjam` date NOT NULL DEFAULT '0000-00-00',
  `tgl_kembali` date DEFAULT NULL,
  `nip` varchar(20) NOT NULL DEFAULT '',
  `status_pinjam` enum('Masih Dipinjam','Sudah Kembali') DEFAULT NULL,
  PRIMARY KEY (`peminjam`,`no_inventaris`,`tgl_pinjam`,`nip`) USING BTREE,
  KEY `no_inventaris` (`no_inventaris`) USING BTREE,
  KEY `nip` (`nip`) USING BTREE,
  KEY `tgl_kembali` (`tgl_kembali`) USING BTREE,
  KEY `status_pinjam` (`status_pinjam`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `inventaris_produsen`
--

DROP TABLE IF EXISTS `inventaris_produsen`;
CREATE TABLE IF NOT EXISTS `inventaris_produsen` (
  `kode_produsen` varchar(10) NOT NULL,
  `nama_produsen` varchar(40) DEFAULT NULL,
  `alamat_produsen` varchar(70) DEFAULT NULL,
  `no_telp` varchar(13) DEFAULT NULL,
  `email` varchar(25) DEFAULT NULL,
  `website_produsen` varchar(30) DEFAULT NULL,
  PRIMARY KEY (`kode_produsen`),
  KEY `nama_produsen` (`nama_produsen`),
  KEY `alamat_produsen` (`alamat_produsen`),
  KEY `no_telp` (`no_telp`),
  KEY `email` (`email`),
  KEY `website_produsen` (`website_produsen`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `inventaris_ruang`
--

DROP TABLE IF EXISTS `inventaris_ruang`;
CREATE TABLE IF NOT EXISTS `inventaris_ruang` (
  `id_ruang` varchar(5) NOT NULL,
  `nama_ruang` varchar(40) NOT NULL,
  PRIMARY KEY (`id_ruang`),
  KEY `nama_ruang` (`nama_ruang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `jabatan`
--

DROP TABLE IF EXISTS `jabatan`;
CREATE TABLE IF NOT EXISTS `jabatan` (
  `kd_jbtn` char(4) NOT NULL DEFAULT '',
  `nm_jbtn` varchar(25) DEFAULT NULL,
  PRIMARY KEY (`kd_jbtn`),
  KEY `nm_jbtn` (`nm_jbtn`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `jabatan`
--

INSERT INTO `jabatan` (`kd_jbtn`, `nm_jbtn`) VALUES
('-', '-'),
('J05', 'Apoteker'),
('JB06', 'Apoteker Penanggung Jawab'),
('J04', 'Bidan'),
('JB03', 'Dokter Gigi'),
('J02', 'Dokter Spesialis'),
('JB01', 'Dokter Spesialis'),
('J01', 'Dokter Umum'),
('JB02', 'Dokter Umum'),
('J06', 'Kasir & Keuangan'),
('JB04', 'Kepala Ruangan Perawat'),
('J03', 'Perawat Pelaksana'),
('JB05', 'Perawat Pelaksana'),
('J07', 'Perekam Medis'),
('JB08', 'Perekam Medis'),
('JB09', 'Staf Kasir & Keuangan'),
('JB07', 'Tenaga Teknis Kefarmasian');

-- --------------------------------------------------------

--
-- Struktur dari tabel `jadwal`
--

DROP TABLE IF EXISTS `jadwal`;
CREATE TABLE IF NOT EXISTS `jadwal` (
  `kd_dokter` varchar(20) NOT NULL,
  `hari_kerja` enum('SENIN','SELASA','RABU','KAMIS','JUMAT','SABTU','AKHAD') NOT NULL DEFAULT 'SENIN',
  `jam_mulai` time NOT NULL DEFAULT '00:00:00',
  `jam_selesai` time DEFAULT NULL,
  `kd_poli` char(5) DEFAULT NULL,
  `kuota` int DEFAULT NULL,
  PRIMARY KEY (`kd_dokter`,`hari_kerja`,`jam_mulai`),
  KEY `kd_dokter` (`kd_dokter`),
  KEY `kd_poli` (`kd_poli`),
  KEY `jam_mulai` (`jam_mulai`),
  KEY `jam_selesai` (`jam_selesai`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `jadwal`
--

INSERT INTO `jadwal` (`kd_dokter`, `hari_kerja`, `jam_mulai`, `jam_selesai`, `kd_poli`, `kuota`) VALUES
('DR001', 'SENIN', '08:00:00', '14:00:00', 'INT', 30),
('DR001', 'SELASA', '08:00:00', '14:00:00', 'INT', 30),
('DR001', 'RABU', '08:00:00', '14:00:00', 'INT', 30),
('DR001', 'KAMIS', '08:00:00', '14:00:00', 'INT', 30),
('DR001', 'JUMAT', '08:00:00', '11:30:00', 'INT', 20),
('DR001', 'SABTU', '08:00:00', '14:00:00', 'POL03', 30),
('DR002', 'SENIN', '07:30:00', '13:30:00', 'POL01', 40),
('DR002', 'SENIN', '08:00:00', '15:00:00', 'UMU', 40),
('DR002', 'SELASA', '07:30:00', '13:30:00', 'POL01', 40),
('DR002', 'SELASA', '08:00:00', '15:00:00', 'UMU', 40),
('DR002', 'RABU', '07:30:00', '13:30:00', 'POL01', 40),
('DR002', 'RABU', '08:00:00', '15:00:00', 'UMU', 40),
('DR002', 'KAMIS', '07:30:00', '13:30:00', 'POL01', 40),
('DR002', 'KAMIS', '08:00:00', '15:00:00', 'UMU', 40),
('DR002', 'JUMAT', '07:30:00', '13:30:00', 'POL01', 40),
('DR002', 'JUMAT', '08:00:00', '11:30:00', 'UMU', 25),
('DR002', 'SABTU', '07:30:00', '13:30:00', 'POL01', 40),
('DR002', 'SABTU', '08:00:00', '13:00:00', 'UMU', 30),
('DR003', 'SENIN', '09:00:00', '13:00:00', 'ANA', 25),
('DR003', 'SELASA', '09:00:00', '13:00:00', 'ANA', 25),
('DR003', 'RABU', '09:00:00', '13:00:00', 'ANA', 25),
('DR003', 'KAMIS', '09:00:00', '13:00:00', 'ANA', 25),
('DR003', 'JUMAT', '08:30:00', '11:30:00', 'ANA', 15),
('DR003', 'JUMAT', '09:00:00', '15:00:00', 'POL02', 25),
('DR003', 'SABTU', '09:00:00', '15:00:00', 'POL02', 25),
('DR004', 'SENIN', '08:30:00', '14:00:00', 'GIG', 20),
('DR004', 'SELASA', '08:30:00', '14:00:00', 'GIG', 20),
('DR004', 'RABU', '08:30:00', '14:00:00', 'GIG', 20),
('DR004', 'KAMIS', '08:30:00', '14:00:00', 'GIG', 20),
('DR004', 'JUMAT', '08:30:00', '11:30:00', 'GIG', 15),
('DR004', 'SABTU', '08:30:00', '13:00:00', 'GIG', 15),
('DR005', 'SENIN', '10:00:00', '15:00:00', 'KIA', 25),
('DR005', 'SELASA', '10:00:00', '15:00:00', 'KIA', 25),
('DR005', 'RABU', '10:00:00', '15:00:00', 'KIA', 25),
('DR005', 'KAMIS', '10:00:00', '15:00:00', 'KIA', 25),
('DR005', 'JUMAT', '10:00:00', '16:00:00', 'POL04', 20),
('DR005', 'SABTU', '10:00:00', '16:00:00', 'POL04', 20),
('DR006', 'SENIN', '07:00:00', '21:00:00', 'IGDK', 50),
('DR006', 'SELASA', '07:00:00', '21:00:00', 'IGDK', 50),
('DR006', 'RABU', '07:00:00', '21:00:00', 'IGDK', 50),
('DR006', 'KAMIS', '07:00:00', '21:00:00', 'IGDK', 50),
('DR006', 'JUMAT', '07:00:00', '21:00:00', 'IGDK', 50),
('DR006', 'SABTU', '07:00:00', '21:00:00', 'IGDK', 50);

-- --------------------------------------------------------

--
-- Struktur dari tabel `jadwal_pegawai`
--

DROP TABLE IF EXISTS `jadwal_pegawai`;
CREATE TABLE IF NOT EXISTS `jadwal_pegawai` (
  `id` int NOT NULL,
  `tahun` year NOT NULL,
  `bulan` enum('01','02','03','04','05','06','07','08','09','10','11','12') NOT NULL,
  `h1` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h2` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h3` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h4` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h5` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h6` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h7` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h8` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h9` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h10` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h11` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h12` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h13` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h14` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h15` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h16` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h17` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h18` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h19` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h20` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h21` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h22` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h23` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h24` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h25` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h26` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h27` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h28` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h29` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h30` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h31` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  PRIMARY KEY (`id`,`tahun`,`bulan`),
  KEY `h1` (`h1`),
  KEY `h2` (`h2`),
  KEY `h3` (`h3`),
  KEY `h4` (`h4`),
  KEY `h30` (`h30`),
  KEY `h31` (`h31`),
  KEY `h29` (`h29`),
  KEY `h28` (`h28`),
  KEY `h18` (`h18`),
  KEY `h9` (`h9`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `jadwal_tambahan`
--

DROP TABLE IF EXISTS `jadwal_tambahan`;
CREATE TABLE IF NOT EXISTS `jadwal_tambahan` (
  `id` int NOT NULL,
  `tahun` year NOT NULL,
  `bulan` enum('01','02','03','04','05','06','07','08','09','10','11','12') NOT NULL,
  `h1` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h2` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h3` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h4` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h5` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h6` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h7` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h8` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h9` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h10` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h11` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h12` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h13` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h14` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h15` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h16` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h17` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h18` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h19` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h20` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h21` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h22` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h23` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h24` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h25` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h26` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h27` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h28` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h29` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h30` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  `h31` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10','') NOT NULL,
  PRIMARY KEY (`id`,`tahun`,`bulan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `jam_jaga`
--

DROP TABLE IF EXISTS `jam_jaga`;
CREATE TABLE IF NOT EXISTS `jam_jaga` (
  `no_id` int NOT NULL AUTO_INCREMENT,
  `dep_id` char(4) NOT NULL,
  `shift` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10') NOT NULL,
  `jam_masuk` time NOT NULL,
  `jam_pulang` time NOT NULL,
  PRIMARY KEY (`no_id`),
  UNIQUE KEY `dep_id_2` (`dep_id`,`shift`),
  KEY `dep_id` (`dep_id`),
  KEY `shift` (`shift`),
  KEY `jam_masuk` (`jam_masuk`),
  KEY `jam_pulang` (`jam_pulang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `jam_masuk`
--

DROP TABLE IF EXISTS `jam_masuk`;
CREATE TABLE IF NOT EXISTS `jam_masuk` (
  `shift` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10') NOT NULL,
  `jam_masuk` time NOT NULL,
  `jam_pulang` time NOT NULL,
  PRIMARY KEY (`shift`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `jam_masuk`
--

INSERT INTO `jam_masuk` (`shift`, `jam_masuk`, `jam_pulang`) VALUES
('Pagi', '06:00:00', '16:00:00'),
('Pagi2', '08:00:00', '14:00:00'),
('Pagi3', '10:00:00', '17:00:00'),
('Siang', '14:00:00', '08:00:00'),
('Siang2', '14:00:00', '21:00:00'),
('Malam', '20:00:00', '02:00:00'),
('Midle Siang1', '00:00:00', '06:00:00'),
('Midle Siang3', '00:00:00', '00:00:00'),
('Midle Siang4', '04:00:00', '16:00:00'),
('Midle Malam1', '00:00:00', '06:00:00'),
('Midle Malam5', '22:00:00', '07:00:00');

-- --------------------------------------------------------

--
-- Struktur dari tabel `jenis`
--

DROP TABLE IF EXISTS `jenis`;
CREATE TABLE IF NOT EXISTS `jenis` (
  `kdjns` char(4) NOT NULL,
  `nama` varchar(30) NOT NULL,
  `keterangan` varchar(50) NOT NULL,
  PRIMARY KEY (`kdjns`),
  KEY `nama` (`nama`),
  KEY `keterangan` (`keterangan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `jenis`
--

INSERT INTO `jenis` (`kdjns`, `nama`, `keterangan`) VALUES
('-', '-', '-'),
('J01', 'Obat Generik', 'Obat Generik Berlogo'),
('J02', 'Obat Paten / Branded', 'Obat Bermerk Dagang'),
('J03', 'Alat Kesehatan & BHP', 'Barang Habis Pakai Medis'),
('J04', 'Cairan Infus', 'Larutan Elektrolit & Nutrisi'),
('J05', 'Vaksin & Serum', 'Biological Products');

-- --------------------------------------------------------

--
-- Struktur dari tabel `jnj_jabatan`
--

DROP TABLE IF EXISTS `jnj_jabatan`;
CREATE TABLE IF NOT EXISTS `jnj_jabatan` (
  `kode` varchar(10) NOT NULL,
  `nama` varchar(50) NOT NULL,
  `tnj` double NOT NULL,
  `indek` tinyint NOT NULL,
  PRIMARY KEY (`kode`),
  KEY `nama` (`nama`),
  KEY `tnj` (`tnj`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `jnj_jabatan`
--

INSERT INTO `jnj_jabatan` (`kode`, `nama`, `tnj`, `indek`) VALUES
('-', '-', 0, 1),
('JJ01', 'Direksi / Pimpinan', 5000000, 1),
('JJ02', 'Kepala Instalasi / Unit', 3000000, 2),
('JJ03', 'Staf Medis Fungsional', 2000000, 3),
('JJ04', 'Staf Pelaksana', 1000000, 4),
('JNJ01', 'Dokter Spesialis', 5000000, 1),
('JNJ02', 'Dokter Umum', 3000000, 2),
('JNJ03', 'Perawat / Bidan', 1500000, 3),
('JNJ04', 'Apoteker / TTK', 1500000, 4),
('JNJ05', 'Staf Administrasi & Kasir', 1000000, 5);

-- --------------------------------------------------------

--
-- Struktur dari tabel `jns_perawatan`
--

DROP TABLE IF EXISTS `jns_perawatan`;
CREATE TABLE IF NOT EXISTS `jns_perawatan` (
  `kd_jenis_prw` varchar(15) NOT NULL,
  `nm_perawatan` varchar(80) DEFAULT NULL,
  `kd_kategori` char(5) DEFAULT NULL,
  `material` double DEFAULT NULL,
  `bhp` double NOT NULL,
  `tarif_tindakandr` double DEFAULT NULL,
  `tarif_tindakanpr` double DEFAULT NULL,
  `kso` double DEFAULT NULL,
  `menejemen` double DEFAULT NULL,
  `total_byrdr` double DEFAULT NULL,
  `total_byrpr` double DEFAULT NULL,
  `total_byrdrpr` double NOT NULL,
  `kd_pj` char(3) NOT NULL,
  `kd_poli` char(5) NOT NULL,
  `status` enum('0','1') NOT NULL,
  PRIMARY KEY (`kd_jenis_prw`),
  KEY `kd_kategori` (`kd_kategori`),
  KEY `kd_pj` (`kd_pj`),
  KEY `kd_poli` (`kd_poli`),
  KEY `nm_perawatan` (`nm_perawatan`),
  KEY `material` (`material`),
  KEY `tarif_tindakandr` (`tarif_tindakandr`),
  KEY `tarif_tindakanpr` (`tarif_tindakanpr`),
  KEY `total_byrdr` (`total_byrdr`),
  KEY `total_byrpr` (`total_byrpr`),
  KEY `kso` (`kso`),
  KEY `menejemen` (`menejemen`),
  KEY `status` (`status`),
  KEY `total_byrdrpr` (`total_byrdrpr`),
  KEY `bhp` (`bhp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `jns_perawatan`
--

INSERT INTO `jns_perawatan` (`kd_jenis_prw`, `nm_perawatan`, `kd_kategori`, `material`, `bhp`, `tarif_tindakandr`, `tarif_tindakanpr`, `kso`, `menejemen`, `total_byrdr`, `total_byrpr`, `total_byrdrpr`, `kd_pj`, `kd_poli`, `status`) VALUES
('RJ001', 'Pemeriksaan rutin', '-', 0, 0, 50000, 0, 0, 0, 50000, 0, 50000, '-', '-', '1'),
('TK001', 'Pemeriksaan & Konsultasi Dokter Umum', 'KP01', 5000, 5000, 25000, 5000, 2000, 3000, 40000, 20000, 45000, '', '', '1'),
('TK002', 'Konsultasi & Pemeriksaan Dokter Spesialis', 'KP01', 10000, 10000, 60000, 10000, 5000, 5000, 90000, 40000, 100000, '', '', '1'),
('TK003', 'Jahit Luka Ringan (1 - 3 Jahitan)', 'KP02', 15000, 20000, 35000, 15000, 5000, 5000, 80000, 60000, 95000, '', '', '1'),
('TK004', 'Jahit Luka Sedang (4 - 10 Jahitan)', 'KP02', 25000, 35000, 60000, 25000, 10000, 10000, 140000, 105000, 165000, '', '', '1'),
('TK005', 'Nebulisasi / Terapi Inhalasi Uap', 'KP03', 10000, 15000, 20000, 15000, 5000, 5000, 55000, 50000, 70000, '', '', '1'),
('TK006', 'Pemeriksaan Rekam Jantung (EKG 12 Lead)', 'KP03', 15000, 20000, 35000, 15000, 5000, 5000, 80000, 60000, 95000, '', '', '1'),
('TK007', 'Perawatan & Ganti Verban Luka Bersih', 'KP02', 10000, 15000, 15000, 15000, 3000, 2000, 45000, 45000, 60000, '', '', '1'),
('TK008', 'Pembersihan Karang Gigi (Scaling) Rahang Atas/Bawah', 'KP04', 25000, 25000, 80000, 25000, 10000, 10000, 150000, 95000, 175000, '', '', '1'),
('TK009', 'Pencabutan Gigi Tetap / Ekstraksi', 'KP04', 20000, 25000, 60000, 20000, 8000, 7000, 120000, 80000, 140000, '', '', '1'),
('TK010', 'Penambalan Gigi Komposit / Light Curing', 'KP04', 25000, 30000, 70000, 20000, 10000, 10000, 145000, 95000, 165000, '', '', '1'),
('TK011', 'Pemeriksaan USG Kehamilan 2 Dimensi', 'KP05', 20000, 25000, 75000, 20000, 10000, 10000, 140000, 85000, 160000, '', '', '1'),
('TK012', 'Penyuntikan Imunisasi & Konseling Anak', 'KP05', 5000, 10000, 25000, 15000, 3000, 2000, 45000, 35000, 60000, '', '', '1'),
('TK013', 'Pemasangan Infus Pasien Dewasa', 'KP06', 10000, 15000, 15000, 15000, 3000, 2000, 45000, 45000, 60000, '', '', '1'),
('TK014', 'Irigasi & Pembersihan Serumen Telinga', 'KP03', 10000, 15000, 25000, 10000, 3000, 2000, 55000, 40000, 65000, '', '', '1'),
('TK015', 'Pemasangan / Pelepasan Kateter Urine', 'KP06', 15000, 20000, 25000, 15000, 5000, 5000, 70000, 60000, 85000, '', '', '1'),
('TND001', 'Pemeriksaan & Konsultasi Dokter Umum', 'KP001', 5000, 2000, 30000, 0, 0, 3000, 40000, 0, 40000, 'UMU', 'POL01', '1'),
('TND002', 'Pemeriksaan & Konsultasi Dokter Spesialis', 'KP001', 10000, 5000, 60000, 0, 0, 5000, 80000, 0, 80000, 'UMU', 'POL03', '1'),
('TND003', 'Injeksi / Suntik Obat (Dokter & Perawat)', 'KP002', 10000, 5000, 15000, 10000, 0, 5000, 30000, 25000, 45000, 'UMU', 'POL01', '1'),
('TND004', 'Pemeriksaan EKG / Rekam Jantung', 'KP003', 25000, 10000, 35000, 10000, 0, 10000, 80000, 45000, 90000, 'UMU', 'POL03', '1'),
('TND005', 'Nebulizer Inhalasi Uap Dewasa/Anak', 'KP003', 20000, 10000, 15000, 15000, 0, 5000, 50000, 50000, 65000, 'UMU', 'POL05', '1'),
('TND006', 'Pembersihan Karang Gigi / Scaling (Per Regio)', 'KP004', 30000, 15000, 75000, 15000, 0, 15000, 135000, 60000, 150000, 'UMU', 'POL02', '1'),
('TND007', 'Penambalan Gigi Komposit / Light Curing', 'KP004', 40000, 20000, 90000, 20000, 0, 20000, 170000, 80000, 190000, 'UMU', 'POL02', '1'),
('TND008', 'Pemeriksaan USG Kandungan 2D / Fetomaternal', 'KP005', 40000, 15000, 95000, 15000, 0, 15000, 165000, 70000, 180000, 'UMU', 'POL04', '1'),
('TND009', 'Perawatan Luka Ringan / Ganti Verband', 'KP002', 15000, 10000, 0, 20000, 0, 5000, 0, 50000, 50000, 'UMU', 'POL01', '1'),
('TND010', 'Hecting / Jahit Luka Sederhana (1-5 Jahitan)', 'KP002', 25000, 15000, 45000, 15000, 0, 10000, 95000, 50000, 110000, 'UMU', 'IGD01', '1');

-- --------------------------------------------------------

--
-- Struktur dari tabel `jns_perawatan_inap`
--

DROP TABLE IF EXISTS `jns_perawatan_inap`;
CREATE TABLE IF NOT EXISTS `jns_perawatan_inap` (
  `kd_jenis_prw` varchar(15) NOT NULL,
  `nm_perawatan` varchar(80) DEFAULT NULL,
  `kd_kategori` char(5) NOT NULL,
  `material` double DEFAULT NULL,
  `bhp` double NOT NULL,
  `tarif_tindakandr` double DEFAULT NULL,
  `tarif_tindakanpr` double DEFAULT NULL,
  `kso` double DEFAULT NULL,
  `menejemen` double DEFAULT NULL,
  `total_byrdr` double DEFAULT NULL,
  `total_byrpr` double DEFAULT NULL,
  `total_byrdrpr` double NOT NULL,
  `kd_pj` char(3) NOT NULL,
  `kd_bangsal` char(5) NOT NULL,
  `status` enum('0','1') NOT NULL,
  `kelas` enum('-','Kelas 1','Kelas 2','Kelas 3','Kelas Utama','Kelas VIP','Kelas VVIP') NOT NULL,
  PRIMARY KEY (`kd_jenis_prw`),
  KEY `kd_pj` (`kd_pj`),
  KEY `kd_bangsal` (`kd_bangsal`),
  KEY `kd_kategori` (`kd_kategori`),
  KEY `nm_perawatan` (`nm_perawatan`),
  KEY `material` (`material`),
  KEY `tarif_tindakandr` (`tarif_tindakandr`),
  KEY `tarif_tindakanpr` (`tarif_tindakanpr`),
  KEY `total_byrdr` (`total_byrdr`),
  KEY `total_byrpr` (`total_byrpr`),
  KEY `bhp` (`bhp`),
  KEY `kso` (`kso`),
  KEY `menejemen` (`menejemen`),
  KEY `status` (`status`),
  KEY `total_byrdrpr` (`total_byrdrpr`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `jns_perawatan_inap`
--

INSERT INTO `jns_perawatan_inap` (`kd_jenis_prw`, `nm_perawatan`, `kd_kategori`, `material`, `bhp`, `tarif_tindakandr`, `tarif_tindakanpr`, `kso`, `menejemen`, `total_byrdr`, `total_byrpr`, `total_byrdrpr`, `kd_pj`, `kd_bangsal`, `status`, `kelas`) VALUES
('RI001', 'Pasang Infus', '-', 0, 0, 0, 25000, 0, 0, 0, 25000, 25000, '-', '-', '1', 'Kelas 1');

-- --------------------------------------------------------

--
-- Struktur dari tabel `jns_perawatan_lab`
--

DROP TABLE IF EXISTS `jns_perawatan_lab`;
CREATE TABLE IF NOT EXISTS `jns_perawatan_lab` (
  `kd_jenis_prw` varchar(15) NOT NULL,
  `nm_perawatan` varchar(80) DEFAULT NULL,
  `bagian_rs` double DEFAULT NULL,
  `bhp` double NOT NULL,
  `tarif_perujuk` double NOT NULL,
  `tarif_tindakan_dokter` double NOT NULL,
  `tarif_tindakan_petugas` double DEFAULT NULL,
  `kso` double DEFAULT NULL,
  `menejemen` double DEFAULT NULL,
  `total_byr` double DEFAULT NULL,
  `kd_pj` char(3) NOT NULL,
  `status` enum('0','1') NOT NULL,
  `kelas` enum('-','Rawat Jalan','Kelas 1','Kelas 2','Kelas 3','Kelas Utama','Kelas VIP','Kelas VVIP') NOT NULL,
  `kategori` enum('PK','PA','MB') NOT NULL,
  PRIMARY KEY (`kd_jenis_prw`),
  KEY `kd_pj` (`kd_pj`),
  KEY `nm_perawatan` (`nm_perawatan`),
  KEY `tarif_perujuk` (`tarif_perujuk`),
  KEY `tarif_tindakan_dokter` (`tarif_tindakan_dokter`),
  KEY `tarif_tindakan_petugas` (`tarif_tindakan_petugas`),
  KEY `total_byr` (`total_byr`),
  KEY `bagian_rs` (`bagian_rs`),
  KEY `bhp` (`bhp`),
  KEY `kso` (`kso`),
  KEY `menejemen` (`menejemen`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `jns_perawatan_lab`
--

INSERT INTO `jns_perawatan_lab` (`kd_jenis_prw`, `nm_perawatan`, `bagian_rs`, `bhp`, `tarif_perujuk`, `tarif_tindakan_dokter`, `tarif_tindakan_petugas`, `kso`, `menejemen`, `total_byr`, `kd_pj`, `status`, `kelas`, `kategori`) VALUES
('LAB001', 'Hematologi Lengkap (CBC + Diff)', 15000, 25000, 5000, 15000, 15000, 0, 5000, 80000, '-', '1', 'Rawat Jalan', 'PK'),
('LAB002', 'Gula Darah Sewaktu (GDS)', 5000, 10000, 0, 5000, 5000, 0, 0, 25000, '-', '1', 'Rawat Jalan', 'PK'),
('LAB003', 'Gula Darah Puasa & 2 Jam PP', 10000, 20000, 0, 10000, 10000, 0, 0, 50000, '-', '1', 'Rawat Jalan', 'PK'),
('LAB004', 'Profil Lipid / Kolesterol Lengkap', 20000, 40000, 10000, 20000, 20000, 0, 10000, 120000, '-', '1', 'Rawat Jalan', 'PK'),
('LAB005', 'Fungsi Ginjal (Ureum, Kreatinin, Asam Urat)', 15000, 30000, 5000, 15000, 15000, 0, 5000, 95000, '-', '1', 'Rawat Jalan', 'PK'),
('LAB006', 'Fungsi Hati (SGOT & SGPT)', 10000, 25000, 5000, 10000, 10000, 0, 0, 60000, '-', '1', 'Rawat Jalan', 'PK'),
('LAB007', 'Urinalisis / Urin Lengkap', 10000, 15000, 0, 10000, 10000, 0, 0, 45000, '-', '1', 'Rawat Jalan', 'PK'),
('LAB008', 'Uji Widal (Tifoid Serologi)', 10000, 25000, 5000, 10000, 10000, 0, 0, 60000, '-', '1', 'Rawat Jalan', 'PK'),
('LAB009', 'Golongan Darah & Rhesus', 5000, 10000, 0, 5000, 5000, 0, 0, 25000, '-', '1', 'Rawat Jalan', 'PK'),
('LAB010', 'HBsAg Rapid Test', 10000, 25000, 0, 10000, 10000, 0, 0, 55000, '-', '1', 'Rawat Jalan', 'PK'),
('LAB011', 'Tes Kehamilan (Plano Test / HCG)', 5000, 15000, 0, 5000, 5000, 0, 0, 30000, '-', '1', 'Rawat Jalan', 'PK'),
('LAB012', 'Elektrolit Serum (Na, K, Cl)', 25000, 45000, 10000, 25000, 20000, 0, 10000, 135000, '-', '1', 'Rawat Jalan', 'PK');

-- --------------------------------------------------------

--
-- Struktur dari tabel `jns_perawatan_radiologi`
--

DROP TABLE IF EXISTS `jns_perawatan_radiologi`;
CREATE TABLE IF NOT EXISTS `jns_perawatan_radiologi` (
  `kd_jenis_prw` varchar(15) NOT NULL,
  `nm_perawatan` varchar(80) DEFAULT NULL,
  `bagian_rs` double DEFAULT NULL,
  `bhp` double NOT NULL,
  `tarif_perujuk` double NOT NULL,
  `tarif_tindakan_dokter` double NOT NULL,
  `tarif_tindakan_petugas` double DEFAULT NULL,
  `kso` double DEFAULT NULL,
  `menejemen` double DEFAULT NULL,
  `total_byr` double DEFAULT NULL,
  `kd_pj` char(3) NOT NULL,
  `status` enum('0','1') NOT NULL,
  `kelas` enum('-','Rawat Jalan','Kelas 1','Kelas 2','Kelas 3','Kelas Utama','Kelas VIP','Kelas VVIP') NOT NULL,
  PRIMARY KEY (`kd_jenis_prw`),
  KEY `kd_pj` (`kd_pj`),
  KEY `nm_perawatan` (`nm_perawatan`),
  KEY `bagian_rs` (`bagian_rs`),
  KEY `tarif_perujuk` (`tarif_perujuk`),
  KEY `tarif_tindakan_dokter` (`tarif_tindakan_dokter`),
  KEY `tarif_tindakan_petugas` (`tarif_tindakan_petugas`),
  KEY `total_byr` (`total_byr`),
  KEY `bhp` (`bhp`),
  KEY `kso` (`kso`),
  KEY `menejemen` (`menejemen`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `jns_perawatan_radiologi`
--

INSERT INTO `jns_perawatan_radiologi` (`kd_jenis_prw`, `nm_perawatan`, `bagian_rs`, `bhp`, `tarif_perujuk`, `tarif_tindakan_dokter`, `tarif_tindakan_petugas`, `kso`, `menejemen`, `total_byr`, `kd_pj`, `status`, `kelas`) VALUES
('RAD001', 'Thorax', 0, 0, 0, 150000, 0, 0, 0, 150000, '-', '1', 'Kelas 1');

-- --------------------------------------------------------

--
-- Struktur dari tabel `kabupaten`
--

DROP TABLE IF EXISTS `kabupaten`;
CREATE TABLE IF NOT EXISTS `kabupaten` (
  `kd_kab` int NOT NULL,
  `nm_kab` varchar(60) NOT NULL,
  PRIMARY KEY (`kd_kab`),
  UNIQUE KEY `nm_kab` (`nm_kab`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `kabupaten`
--

INSERT INTO `kabupaten` (`kd_kab`, `nm_kab`) VALUES
(1, '-'),
(2, 'Bandung'),
(5, 'Hulu Sungai Tengah'),
(3, 'Semarang'),
(4, 'Surabaya');

-- --------------------------------------------------------

--
-- Struktur dari tabel `kamar`
--

DROP TABLE IF EXISTS `kamar`;
CREATE TABLE IF NOT EXISTS `kamar` (
  `kd_kamar` varchar(15) NOT NULL,
  `kd_bangsal` char(5) DEFAULT NULL,
  `trf_kamar` double DEFAULT NULL,
  `status` enum('ISI','KOSONG','DIBERSIHKAN','DIBOOKING') DEFAULT NULL,
  `kelas` enum('Kelas 1','Kelas 2','Kelas 3','Kelas Utama','Kelas VIP','Kelas VVIP') DEFAULT NULL,
  `statusdata` enum('0','1') DEFAULT NULL,
  PRIMARY KEY (`kd_kamar`),
  KEY `kd_bangsal` (`kd_bangsal`),
  KEY `trf_kamar` (`trf_kamar`),
  KEY `status` (`status`),
  KEY `kelas` (`kelas`),
  KEY `statusdata` (`statusdata`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `kamar`
--

INSERT INTO `kamar` (`kd_kamar`, `kd_bangsal`, `trf_kamar`, `status`, `kelas`, `statusdata`) VALUES
('ANG01', 'ANG', 100000, 'KOSONG', 'Kelas 1', '1'),
('ANG02', 'ANG', 100000, 'KOSONG', 'Kelas 1', '1');

-- --------------------------------------------------------

--
-- Struktur dari tabel `kamar_inap`
--

DROP TABLE IF EXISTS `kamar_inap`;
CREATE TABLE IF NOT EXISTS `kamar_inap` (
  `no_rawat` varchar(17) NOT NULL DEFAULT '',
  `kd_kamar` varchar(15) NOT NULL,
  `trf_kamar` double DEFAULT NULL,
  `diagnosa_awal` varchar(100) DEFAULT NULL,
  `diagnosa_akhir` varchar(100) DEFAULT NULL,
  `tgl_masuk` date NOT NULL DEFAULT '0000-00-00',
  `jam_masuk` time NOT NULL DEFAULT '00:00:00',
  `tgl_keluar` date DEFAULT NULL,
  `jam_keluar` time DEFAULT NULL,
  `lama` double DEFAULT NULL,
  `ttl_biaya` double DEFAULT NULL,
  `stts_pulang` enum('Sehat','Rujuk','APS','+','Meninggal','Sembuh','Membaik','Pulang Paksa','-','Pindah Kamar','Status Belum Lengkap','Atas Persetujuan Dokter','Atas Permintaan Sendiri','Isoman','Lain-lain') NOT NULL,
  PRIMARY KEY (`no_rawat`,`tgl_masuk`,`jam_masuk`),
  KEY `kd_kamar` (`kd_kamar`),
  KEY `diagnosa_awal` (`diagnosa_awal`),
  KEY `diagnosa_akhir` (`diagnosa_akhir`),
  KEY `tgl_keluar` (`tgl_keluar`),
  KEY `jam_keluar` (`jam_keluar`),
  KEY `lama` (`lama`),
  KEY `ttl_biaya` (`ttl_biaya`),
  KEY `stts_pulang` (`stts_pulang`),
  KEY `trf_kamar` (`trf_kamar`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `kategori_barang`
--

DROP TABLE IF EXISTS `kategori_barang`;
CREATE TABLE IF NOT EXISTS `kategori_barang` (
  `kode` char(4) NOT NULL,
  `nama` varchar(30) DEFAULT NULL,
  PRIMARY KEY (`kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `kategori_barang`
--

INSERT INTO `kategori_barang` (`kode`, `nama`) VALUES
('-', '-'),
('KB01', 'Antibiotik & Antimikroba'),
('KB02', 'Analgesik, Antipiretik & NSAID'),
('KB03', 'Obat Saluran Cerna (Gastrointe'),
('KB04', 'Obat Saluran Nafas & Batuk'),
('KB05', 'Obat Kardiovaskular & Antihipe'),
('KB06', 'Antidiabetes Oral'),
('KB07', 'Antihistamin & Antialergi'),
('KB08', 'Vitamin, Mineral & Suplemen'),
('KB09', 'Alat Kesehatan & Disposable');

-- --------------------------------------------------------

--
-- Struktur dari tabel `kategori_penyakit`
--

DROP TABLE IF EXISTS `kategori_penyakit`;
CREATE TABLE IF NOT EXISTS `kategori_penyakit` (
  `kd_ktg` varchar(8) NOT NULL,
  `nm_kategori` varchar(30) DEFAULT NULL,
  `ciri_umum` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`kd_ktg`),
  KEY `nm_kategori` (`nm_kategori`),
  KEY `ciri_umum` (`ciri_umum`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `kategori_penyakit`
--

INSERT INTO `kategori_penyakit` (`kd_ktg`, `nm_kategori`, `ciri_umum`) VALUES
('-', '-', '-'),
('KP01', 'Penyakit Saluran Pernapasan', 'Batuk, pilek, sesak nafas'),
('KP02', 'Penyakit Kardiovaskular & Endo', 'Tekanan darah tinggi, gula darah tinggi'),
('KP03', 'Penyakit Saluran Cerna', 'Nyeri lambung, diare, mual'),
('KP04', 'Penyakit Gigi & Mulut', 'Nyeri gigi, radang gusi, karies'),
('KP05', 'Penyakit Muskuloskeletal', 'Nyeri sendi, asam urat, rematik'),
('KTP01', 'Penyakit Infeksi Saluran Nafas', 'Batuk, demam, pilek, sesak nafas'),
('KTP02', 'Penyakit Sirkulasi & Jantung', 'Hipertensi, nyeri dada, jantung berdebar'),
('KTP03', 'Penyakit Endokrin & Metabolik', 'Diabetes, gangguan hormon, tiroid'),
('KTP04', 'Penyakit Saluran Pencernaan', 'Nyeri lambung, mual muntah, diare'),
('KTP05', 'Penyakit Gigi & Mulut', 'Karies gigi, pulpitis, karang gigi'),
('KTP06', 'Penyakit Muskuloskeletal & Sen', 'Nyeri otot, radang sendi, myalgia'),
('KTP07', 'Penyakit Kebidanan & Kandungan', 'Pemeriksaan kehamilan rutin, ANC');

-- --------------------------------------------------------

--
-- Struktur dari tabel `kategori_perawatan`
--

DROP TABLE IF EXISTS `kategori_perawatan`;
CREATE TABLE IF NOT EXISTS `kategori_perawatan` (
  `kd_kategori` char(5) NOT NULL,
  `nm_kategori` varchar(30) DEFAULT NULL,
  PRIMARY KEY (`kd_kategori`),
  KEY `nm_kategori` (`nm_kategori`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `kategori_perawatan`
--

INSERT INTO `kategori_perawatan` (`kd_kategori`, `nm_kategori`) VALUES
('-', '-'),
('KP04', 'Pelayanan Kesehatan Gigi'),
('KP05', 'Pelayanan KIA, KB & Imunisasi'),
('KP001', 'Pemeriksaan Dokter'),
('KP01', 'Pemeriksaan Klinis & Konsultas'),
('KP02', 'Tindakan Bedah Minor & Rawat L'),
('KP003', 'Tindakan Diagnostik'),
('KP03', 'Tindakan Diagnostik & TTV'),
('KP004', 'Tindakan Gigi & Mulut'),
('KP005', 'Tindakan Kebidanan'),
('KP06', 'Tindakan Kegawatdaruratan & Re'),
('KP002', 'Tindakan Keperawatan');

-- --------------------------------------------------------

--
-- Struktur dari tabel `kecamatan`
--

DROP TABLE IF EXISTS `kecamatan`;
CREATE TABLE IF NOT EXISTS `kecamatan` (
  `kd_kec` int NOT NULL,
  `nm_kec` varchar(60) NOT NULL,
  PRIMARY KEY (`kd_kec`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `kecamatan`
--

INSERT INTO `kecamatan` (`kd_kec`, `nm_kec`) VALUES
(1, '-'),
(2, 'Coblong'),
(3, 'Banyumanik'),
(4, 'Gubeng'),
(5, 'Barabai');

-- --------------------------------------------------------

--
-- Struktur dari tabel `kelompok_jabatan`
--

DROP TABLE IF EXISTS `kelompok_jabatan`;
CREATE TABLE IF NOT EXISTS `kelompok_jabatan` (
  `kode_kelompok` varchar(3) NOT NULL,
  `nama_kelompok` varchar(100) DEFAULT NULL,
  `indek` tinyint DEFAULT NULL,
  PRIMARY KEY (`kode_kelompok`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `kelompok_jabatan`
--

INSERT INTO `kelompok_jabatan` (`kode_kelompok`, `nama_kelompok`, `indek`) VALUES
('-', '-', 1),
('KJ0', 'Tenaga Medis', NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `kelurahan`
--

DROP TABLE IF EXISTS `kelurahan`;
CREATE TABLE IF NOT EXISTS `kelurahan` (
  `kd_kel` varchar(11) NOT NULL,
  `nm_kel` varchar(60) NOT NULL,
  PRIMARY KEY (`kd_kel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `kelurahan`
--

INSERT INTO `kelurahan` (`kd_kel`, `nm_kel`) VALUES
('1', '-'),
('2', 'Dago'),
('3', 'Srondol'),
('4', 'Airlangga'),
('5', 'Barabai Barat');

-- --------------------------------------------------------

--
-- Struktur dari tabel `kodesatuan`
--

DROP TABLE IF EXISTS `kodesatuan`;
CREATE TABLE IF NOT EXISTS `kodesatuan` (
  `kode_sat` char(4) NOT NULL,
  `satuan` varchar(30) DEFAULT NULL,
  PRIMARY KEY (`kode_sat`),
  KEY `satuan` (`satuan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `kodesatuan`
--

INSERT INTO `kodesatuan` (`kode_sat`, `satuan`) VALUES
('-', '-'),
('AMP', 'Ampul'),
('BTL', 'Botol / Sirup'),
('BOX', 'Box / Kotak'),
('FLS', 'Flabot Infus'),
('KAP', 'Kapsul'),
('KPS', 'Kapsul'),
('PCS', 'Pcs / Buah'),
('STR', 'Strip'),
('STRI', 'Strip / Blister'),
('TAB', 'Tablet'),
('TUB', 'Tube'),
('TBN', 'Tube Salep'),
('VIL', 'Vial'),
('VIAL', 'Vial Injeksi');

-- --------------------------------------------------------

--
-- Struktur dari tabel `laporan_operasi`
--

DROP TABLE IF EXISTS `laporan_operasi`;
CREATE TABLE IF NOT EXISTS `laporan_operasi` (
  `no_rawat` varchar(17) NOT NULL,
  `tanggal` datetime NOT NULL,
  `diagnosa_preop` varchar(100) NOT NULL,
  `diagnosa_postop` varchar(100) NOT NULL,
  `jaringan_dieksekusi` varchar(100) NOT NULL,
  `selesaioperasi` datetime NOT NULL,
  `permintaan_pa` enum('Ya','Tidak') NOT NULL,
  `laporan_operasi` text NOT NULL,
  PRIMARY KEY (`no_rawat`,`tanggal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `maping_dokter_dpjpvclaim`
--

DROP TABLE IF EXISTS `maping_dokter_dpjpvclaim`;
CREATE TABLE IF NOT EXISTS `maping_dokter_dpjpvclaim` (
  `kd_dokter` varchar(20) NOT NULL,
  `kd_dokter_bpjs` varchar(20) DEFAULT NULL,
  `nm_dokter_bpjs` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`kd_dokter`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `maping_dokter_dpjpvclaim`
--

INSERT INTO `maping_dokter_dpjpvclaim` (`kd_dokter`, `kd_dokter_bpjs`, `nm_dokter_bpjs`) VALUES
('DR001', '12345', 'dr. Budi Santoso, Sp.PD'),
('DR002', '12346', 'dr. Siti Rahmawati'),
('DR003', '12347', 'drg. Maya Lestari'),
('DR004', '12348', 'dr. Ahmad Pratama, Sp.A'),
('DR005', '12349', 'dr. Fitriani, Sp.OG');

-- --------------------------------------------------------

--
-- Struktur dari tabel `maping_dokter_pcare`
--

DROP TABLE IF EXISTS `maping_dokter_pcare`;
CREATE TABLE IF NOT EXISTS `maping_dokter_pcare` (
  `kd_dokter` varchar(20) NOT NULL,
  `kd_dokter_pcare` varchar(20) DEFAULT NULL,
  `nm_dokter_pcare` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`kd_dokter`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `maping_dokter_pcare`
--

INSERT INTO `maping_dokter_pcare` (`kd_dokter`, `kd_dokter_pcare`, `nm_dokter_pcare`) VALUES
('DR001', 'DOC001', 'dr. Budi Santoso, Sp.PD'),
('DR002', 'DOC002', 'dr. Siti Rahmawati'),
('DR003', 'DOC003', 'drg. Maya Lestari'),
('DR004', 'DOC004', 'dr. Ahmad Pratama, Sp.A'),
('DR005', 'DOC005', 'dr. Fitriani, Sp.OG');

-- --------------------------------------------------------

--
-- Struktur dari tabel `maping_poliklinik_pcare`
--

DROP TABLE IF EXISTS `maping_poliklinik_pcare`;
CREATE TABLE IF NOT EXISTS `maping_poliklinik_pcare` (
  `kd_poli_rs` char(5) NOT NULL,
  `kd_poli_pcare` char(5) DEFAULT NULL,
  `nm_poli_pcare` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`kd_poli_rs`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `maping_poliklinik_pcare`
--

INSERT INTO `maping_poliklinik_pcare` (`kd_poli_rs`, `kd_poli_pcare`, `nm_poli_pcare`) VALUES
('POL01', '001', 'POLI UMUM'),
('POL02', '002', 'POLI GIGI'),
('POL03', '003', 'POLI PENYAKIT DALAM'),
('POL04', '004', 'POLI KANDUNGAN/KIA'),
('POL05', '005', 'POLI ANAK');

-- --------------------------------------------------------

--
-- Struktur dari tabel `maping_poli_bpjs`
--

DROP TABLE IF EXISTS `maping_poli_bpjs`;
CREATE TABLE IF NOT EXISTS `maping_poli_bpjs` (
  `kd_poli_rs` varchar(5) NOT NULL,
  `kd_poli_bpjs` varchar(15) NOT NULL,
  `nm_poli_bpjs` varchar(40) NOT NULL,
  PRIMARY KEY (`kd_poli_rs`),
  UNIQUE KEY `kd_poli_bpjs` (`kd_poli_bpjs`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `maping_poli_bpjs`
--

INSERT INTO `maping_poli_bpjs` (`kd_poli_rs`, `kd_poli_bpjs`, `nm_poli_bpjs`) VALUES
('IGD01', 'IGD', 'INSTALASI GAWAT DARURAT'),
('POL01', '001', 'POLI UMUM'),
('POL02', '002', 'POLI GIGI'),
('POL03', 'INT', 'PENYAKIT DALAM'),
('POL04', 'OBG', 'OBSTETRI & GINEKOLOGI'),
('POL05', 'ANA', 'ANAK');

-- --------------------------------------------------------

--
-- Struktur dari tabel `master_aturan_pakai`
--

DROP TABLE IF EXISTS `master_aturan_pakai`;
CREATE TABLE IF NOT EXISTS `master_aturan_pakai` (
  `aturan` varchar(150) NOT NULL,
  PRIMARY KEY (`aturan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `master_aturan_pakai`
--

INSERT INTO `master_aturan_pakai` (`aturan`) VALUES
('1 x 1 Tablet Sehari Malam Sebelum Tidur'),
('1 x 1 Tablet Sehari Pagi Sesudah Makan'),
('2 x 1 Kapsul Sehari Sesudah Makan'),
('2 x 1 Tablet Sehari Sesudah Makan'),
('3 x 1 Kaplet Sehari Sesudah Makan'),
('3 x 1 Sehari'),
('3 x 1 Sendok Makan (15 ml) Sehari'),
('3 x 1 Sendok Takar (5 ml) Sehari'),
('3 x 1 Tablet Sehari Sesudah Makan'),
('Bila Demam / Sakit (Maksimal 3x Sehari)'),
('Oleskan Tipis 2-3 Kali Sehari pada Bagian yang Sakit');

-- --------------------------------------------------------

--
-- Struktur dari tabel `master_berkas_digital`
--

DROP TABLE IF EXISTS `master_berkas_digital`;
CREATE TABLE IF NOT EXISTS `master_berkas_digital` (
  `kode` varchar(10) NOT NULL,
  `nama` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `master_berkas_digital`
--

INSERT INTO `master_berkas_digital` (`kode`, `nama`) VALUES
('DIG001', 'Berkas Digital');

-- --------------------------------------------------------

--
-- Struktur dari tabel `master_masalah_keperawatan`
--

DROP TABLE IF EXISTS `master_masalah_keperawatan`;
CREATE TABLE IF NOT EXISTS `master_masalah_keperawatan` (
  `kode_masalah` varchar(3) NOT NULL,
  `nama_masalah` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`kode_masalah`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `metode_racik`
--

DROP TABLE IF EXISTS `metode_racik`;
CREATE TABLE IF NOT EXISTS `metode_racik` (
  `kd_racik` varchar(3) NOT NULL,
  `nm_racik` varchar(30) NOT NULL,
  PRIMARY KEY (`kd_racik`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `metode_racik`
--

INSERT INTO `metode_racik` (`kd_racik`, `nm_racik`) VALUES
('1', 'Puyer'),
('R01', 'Puyer / Pulveres'),
('R02', 'Kapsul'),
('R03', 'Salep Racikan / Unguentum'),
('R04', 'Sirup Racikan / Potio');

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_akun_kegiatan`
--

DROP TABLE IF EXISTS `mlite_akun_kegiatan`;
CREATE TABLE IF NOT EXISTS `mlite_akun_kegiatan` (
  `id` int NOT NULL AUTO_INCREMENT,
  `kegiatan` varchar(200) DEFAULT NULL,
  `kd_rek` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `mlite_akun_kegiatan`
--

INSERT INTO `mlite_akun_kegiatan` (`id`, `kegiatan`, `kd_rek`) VALUES
(1, 'Penerimaan Pasien Rawat Jalan', '4101'),
(2, 'Penerimaan Pasien Rawat Inap', '4102'),
(3, 'Penerimaan Penjualan Obat & BHP', '4103'),
(4, 'Penerimaan Laboratorium', '4104'),
(5, 'Penerimaan Radiologi', '4105'),
(6, 'Pembayaran Gaji Karyawan', '5101'),
(7, 'Pembelian Obat & BHP', '5201'),
(8, 'Pembayaran Biaya Operasional', '5301');

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_antrian_loket`
--

DROP TABLE IF EXISTS `mlite_antrian_loket`;
CREATE TABLE IF NOT EXISTS `mlite_antrian_loket` (
  `kd` int NOT NULL AUTO_INCREMENT,
  `type` varchar(50) NOT NULL,
  `noantrian` varchar(50) NOT NULL,
  `no_rkm_medis` varchar(50) DEFAULT NULL,
  `postdate` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL DEFAULT '00:00:00',
  `status` varchar(10) NOT NULL DEFAULT '0',
  `loket` varchar(10) NOT NULL DEFAULT '0',
  PRIMARY KEY (`kd`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `mlite_antrian_loket`
--

INSERT INTO `mlite_antrian_loket` (`kd`, `type`, `noantrian`, `no_rkm_medis`, `postdate`, `start_time`, `end_time`, `status`, `loket`) VALUES
(1, 'A', 'A-001', '000001', '2026-09-08', '08:00:00', '08:15:00', 'Selesai', 'Loket 1'),
(2, 'A', 'A-002', '000002', '2026-09-08', '08:15:00', '08:30:00', 'Selesai', 'Loket 1'),
(3, 'B', 'B-001', '000003', '2026-09-08', '08:45:00', '09:00:00', 'Selesai', 'Loket 2'),
(4, 'B', 'B-002', '000004', '2026-09-08', '09:15:00', '09:30:00', 'Selesai', 'Loket 2');

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_antrian_referensi`
--

DROP TABLE IF EXISTS `mlite_antrian_referensi`;
CREATE TABLE IF NOT EXISTS `mlite_antrian_referensi` (
  `tanggal_periksa` date NOT NULL,
  `no_rkm_medis` varchar(50) NOT NULL,
  `nomor_kartu` varchar(50) NOT NULL,
  `nomor_referensi` varchar(50) NOT NULL,
  `kodebooking` varchar(100) NOT NULL,
  `jenis_kunjungan` varchar(10) NOT NULL,
  `status_kirim` varchar(20) DEFAULT NULL,
  `keterangan` varchar(250) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `mlite_antrian_referensi`
--

INSERT INTO `mlite_antrian_referensi` (`tanggal_periksa`, `no_rkm_medis`, `nomor_kartu`, `nomor_referensi`, `kodebooking`, `jenis_kunjungan`, `status_kirim`, `keterangan`) VALUES
('2026-09-08', '000001', '0001234567891', 'REF-20260908-001', 'BOK202609080001', '1', 'Terkirim', 'Pendaftaran Antrean Mobile JKN Sukses'),
('2026-09-08', '000001', '0001234567891', 'REF-20260908-001', 'BOK202609080001', '1', 'Terkirim', 'Pendaftaran Antrean Mobile JKN Sukses'),
('2026-09-08', '000001', '0001234567891', 'REF-20260908-001', 'BOK202609080001', '1', 'Terkirim', 'Pendaftaran Antrean Mobile JKN Sukses'),
('2026-09-08', '000001', '0001234567891', 'REF-20260908-001', 'BOK202609080001', '1', 'Terkirim', 'Pendaftaran Antrean Mobile JKN Sukses'),
('2026-09-08', '000001', '0001234567891', 'REF-20260908-001', 'BOK202609080001', '1', 'Terkirim', 'Pendaftaran Antrean Mobile JKN Sukses');

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_antrian_referensi_batal`
--

DROP TABLE IF EXISTS `mlite_antrian_referensi_batal`;
CREATE TABLE IF NOT EXISTS `mlite_antrian_referensi_batal` (
  `tanggal_batal` date NOT NULL,
  `nomor_referensi` varchar(50) NOT NULL,
  `kodebooking` varchar(100) NOT NULL,
  `keterangan` varchar(250) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_antrian_referensi_taskid`
--

DROP TABLE IF EXISTS `mlite_antrian_referensi_taskid`;
CREATE TABLE IF NOT EXISTS `mlite_antrian_referensi_taskid` (
  `tanggal_periksa` date NOT NULL,
  `nomor_referensi` varchar(50) NOT NULL,
  `taskid` varchar(50) NOT NULL,
  `waktu` varchar(50) NOT NULL,
  `status` varchar(20) DEFAULT NULL,
  `keterangan` varchar(250) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `mlite_antrian_referensi_taskid`
--

INSERT INTO `mlite_antrian_referensi_taskid` (`tanggal_periksa`, `nomor_referensi`, `taskid`, `waktu`, `status`, `keterangan`) VALUES
('2026-09-08', 'REF-20260908-001', '1', '07:45:00', 'Terkirim', 'Mulai Waktu Tunggu Admisi'),
('2026-09-08', 'REF-20260908-001', '2', '08:00:00', 'Terkirim', 'Selesai Waktu Tunggu Admisi / Masuk Loket'),
('2026-09-08', 'REF-20260908-001', '3', '08:15:00', 'Terkirim', 'Mulai Waktu Tunggu Pelayanan Poli'),
('2026-09-08', 'REF-20260908-001', '4', '08:30:00', 'Terkirim', 'Selesai Waktu Tunggu Poli / Diperiksa Dokter'),
('2026-09-08', 'REF-20260908-001', '5', '09:00:00', 'Terkirim', 'Selesai Pelayanan Poli / Resep Dikirim Farmasi'),
('2026-09-08', 'REF-20260908-001', '6', '09:10:00', 'Terkirim', 'Mulai Pelayanan Farmasi / Racik & Siapkan Obat'),
('2026-09-08', 'REF-20260908-001', '7', '09:20:00', 'Terkirim', 'Selesai Pelayanan Farmasi / Penyerahan Obat ke Pasien'),
('2026-09-08', 'REF-20260908-001', '1', '07:45:00', 'Terkirim', 'Mulai Waktu Tunggu Admisi'),
('2026-09-08', 'REF-20260908-001', '2', '08:00:00', 'Terkirim', 'Selesai Waktu Tunggu Admisi / Masuk Loket'),
('2026-09-08', 'REF-20260908-001', '3', '08:15:00', 'Terkirim', 'Mulai Waktu Tunggu Pelayanan Poli'),
('2026-09-08', 'REF-20260908-001', '4', '08:30:00', 'Terkirim', 'Selesai Waktu Tunggu Poli / Diperiksa Dokter'),
('2026-09-08', 'REF-20260908-001', '5', '09:00:00', 'Terkirim', 'Selesai Pelayanan Poli / Resep Dikirim Farmasi'),
('2026-09-08', 'REF-20260908-001', '6', '09:10:00', 'Terkirim', 'Mulai Pelayanan Farmasi / Racik & Siapkan Obat'),
('2026-09-08', 'REF-20260908-001', '7', '09:20:00', 'Terkirim', 'Selesai Pelayanan Farmasi / Penyerahan Obat ke Pasien'),
('2026-09-08', 'REF-20260908-001', '1', '07:45:00', 'Terkirim', 'Mulai Waktu Tunggu Admisi'),
('2026-09-08', 'REF-20260908-001', '2', '08:00:00', 'Terkirim', 'Selesai Waktu Tunggu Admisi / Masuk Loket'),
('2026-09-08', 'REF-20260908-001', '3', '08:15:00', 'Terkirim', 'Mulai Waktu Tunggu Pelayanan Poli'),
('2026-09-08', 'REF-20260908-001', '4', '08:30:00', 'Terkirim', 'Selesai Waktu Tunggu Poli / Diperiksa Dokter'),
('2026-09-08', 'REF-20260908-001', '5', '09:00:00', 'Terkirim', 'Selesai Pelayanan Poli / Resep Dikirim Farmasi'),
('2026-09-08', 'REF-20260908-001', '6', '09:10:00', 'Terkirim', 'Mulai Pelayanan Farmasi / Racik & Siapkan Obat'),
('2026-09-08', 'REF-20260908-001', '7', '09:20:00', 'Terkirim', 'Selesai Pelayanan Farmasi / Penyerahan Obat ke Pasien'),
('2026-09-08', 'REF-20260908-001', '1', '07:45:00', 'Terkirim', 'Mulai Waktu Tunggu Admisi'),
('2026-09-08', 'REF-20260908-001', '2', '08:00:00', 'Terkirim', 'Selesai Waktu Tunggu Admisi / Masuk Loket'),
('2026-09-08', 'REF-20260908-001', '3', '08:15:00', 'Terkirim', 'Mulai Waktu Tunggu Pelayanan Poli'),
('2026-09-08', 'REF-20260908-001', '4', '08:30:00', 'Terkirim', 'Selesai Waktu Tunggu Poli / Diperiksa Dokter'),
('2026-09-08', 'REF-20260908-001', '5', '09:00:00', 'Terkirim', 'Selesai Pelayanan Poli / Resep Dikirim Farmasi'),
('2026-09-08', 'REF-20260908-001', '6', '09:10:00', 'Terkirim', 'Mulai Pelayanan Farmasi / Racik & Siapkan Obat'),
('2026-09-08', 'REF-20260908-001', '7', '09:20:00', 'Terkirim', 'Selesai Pelayanan Farmasi / Penyerahan Obat ke Pasien'),
('2026-09-08', 'REF-20260908-001', '1', '07:45:00', 'Terkirim', 'Mulai Waktu Tunggu Admisi'),
('2026-09-08', 'REF-20260908-001', '2', '08:00:00', 'Terkirim', 'Selesai Waktu Tunggu Admisi / Masuk Loket'),
('2026-09-08', 'REF-20260908-001', '3', '08:15:00', 'Terkirim', 'Mulai Waktu Tunggu Pelayanan Poli'),
('2026-09-08', 'REF-20260908-001', '4', '08:30:00', 'Terkirim', 'Selesai Waktu Tunggu Poli / Diperiksa Dokter'),
('2026-09-08', 'REF-20260908-001', '5', '09:00:00', 'Terkirim', 'Selesai Pelayanan Poli / Resep Dikirim Farmasi'),
('2026-09-08', 'REF-20260908-001', '6', '09:10:00', 'Terkirim', 'Mulai Pelayanan Farmasi / Racik & Siapkan Obat'),
('2026-09-08', 'REF-20260908-001', '7', '09:20:00', 'Terkirim', 'Selesai Pelayanan Farmasi / Penyerahan Obat ke Pasien');

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_apamregister`
--

DROP TABLE IF EXISTS `mlite_apamregister`;
CREATE TABLE IF NOT EXISTS `mlite_apamregister` (
  `nama_lengkap` varchar(225) NOT NULL,
  `email` varchar(225) NOT NULL,
  `nomor_ktp` varchar(225) NOT NULL,
  `nomor_telepon` varchar(225) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_api_key`
--

DROP TABLE IF EXISTS `mlite_api_key`;
CREATE TABLE IF NOT EXISTS `mlite_api_key` (
  `id` int NOT NULL AUTO_INCREMENT,
  `api_key` text,
  `username` varchar(100) NOT NULL,
  `method` varchar(100) NOT NULL,
  `ip_range` varchar(100) DEFAULT NULL,
  `exp_time` datetime NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `mlite_api_key_ibfk_1` (`username`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_apotek_online_log`
--

DROP TABLE IF EXISTS `mlite_apotek_online_log`;
CREATE TABLE IF NOT EXISTS `mlite_apotek_online_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `no_rawat` varchar(17) NOT NULL,
  `noresep` varchar(50) DEFAULT NULL,
  `tanggal_kirim` datetime NOT NULL,
  `status` enum('success','error') NOT NULL,
  `response_resep` text,
  `response_obat` text,
  `request` text,
  `user` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `no_rawat` (`no_rawat`) USING BTREE,
  KEY `tanggal_kirim` (`tanggal_kirim`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_apotek_online_maping_obat`
--

DROP TABLE IF EXISTS `mlite_apotek_online_maping_obat`;
CREATE TABLE IF NOT EXISTS `mlite_apotek_online_maping_obat` (
  `kode_brng` varchar(40) NOT NULL,
  `kd_obat_bpjs` varchar(20) NOT NULL,
  `nama_obat_bpjs` varchar(200) NOT NULL,
  PRIMARY KEY (`kode_brng`) USING BTREE,
  KEY `kd_obat_bpjs` (`kd_obat_bpjs`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_apotek_online_resep_response_log`
--

DROP TABLE IF EXISTS `mlite_apotek_online_resep_response_log`;
CREATE TABLE IF NOT EXISTS `mlite_apotek_online_resep_response_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `no_rawat` varchar(17) DEFAULT NULL,
  `no_sep_kunjungan` varchar(50) DEFAULT NULL,
  `no_kartu` varchar(20) DEFAULT NULL,
  `nama` varchar(100) DEFAULT NULL,
  `faskes_asal` varchar(20) DEFAULT NULL,
  `no_apotik` varchar(30) DEFAULT NULL,
  `no_resep` varchar(20) DEFAULT NULL,
  `tgl_resep` date DEFAULT NULL,
  `kd_jns_obat` varchar(5) DEFAULT NULL,
  `by_tag_rsp` varchar(10) DEFAULT NULL,
  `by_ver_rsp` varchar(10) DEFAULT NULL,
  `tgl_entry` date DEFAULT NULL,
  `meta_code` varchar(10) DEFAULT NULL,
  `meta_message` text,
  `raw_response` text,
  `tanggal_simpan` datetime DEFAULT CURRENT_TIMESTAMP,
  `user` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_no_rawat` (`no_rawat`) USING BTREE,
  KEY `idx_no_sep_kunjungan` (`no_sep_kunjungan`) USING BTREE,
  KEY `idx_no_resep` (`no_resep`) USING BTREE,
  KEY `idx_tanggal_simpan` (`tanggal_simpan`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_apotek_online_sep_data`
--

DROP TABLE IF EXISTS `mlite_apotek_online_sep_data`;
CREATE TABLE IF NOT EXISTS `mlite_apotek_online_sep_data` (
  `id` int NOT NULL AUTO_INCREMENT,
  `no_sep` varchar(50) NOT NULL,
  `faskes_asal_resep` varchar(20) DEFAULT NULL,
  `nm_faskes_asal_resep` varchar(100) DEFAULT NULL,
  `no_kartu` varchar(20) DEFAULT NULL,
  `nama_peserta` varchar(100) DEFAULT NULL,
  `jns_kelamin` char(1) DEFAULT NULL,
  `tgl_lahir` date DEFAULT NULL,
  `pisat` varchar(10) DEFAULT NULL,
  `kd_jenis_peserta` varchar(10) DEFAULT NULL,
  `nm_jenis_peserta` varchar(50) DEFAULT NULL,
  `kode_bu` varchar(20) DEFAULT NULL,
  `nama_bu` varchar(50) DEFAULT NULL,
  `tgl_sep` date DEFAULT NULL,
  `tgl_plg_sep` date DEFAULT NULL,
  `jns_pelayanan` varchar(10) DEFAULT NULL,
  `nm_diag` varchar(200) DEFAULT NULL,
  `poli` varchar(50) DEFAULT NULL,
  `flag_prb` char(1) DEFAULT NULL,
  `nama_prb` varchar(100) DEFAULT NULL,
  `kode_dokter` varchar(20) DEFAULT NULL,
  `nama_dokter` varchar(100) DEFAULT NULL,
  `tanggal_simpan` datetime NOT NULL,
  `user_simpan` varchar(50) DEFAULT NULL,
  `raw_response` text,
  `no_rawat` varchar(17) DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `no_sep` (`no_sep`) USING BTREE,
  KEY `no_kartu` (`no_kartu`) USING BTREE,
  KEY `nama_peserta` (`nama_peserta`) USING BTREE,
  KEY `tanggal_simpan` (`tanggal_simpan`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_billing`
--

DROP TABLE IF EXISTS `mlite_billing`;
CREATE TABLE IF NOT EXISTS `mlite_billing` (
  `id_billing` int NOT NULL AUTO_INCREMENT,
  `kd_billing` varchar(100) NOT NULL,
  `no_rawat` varchar(17) NOT NULL,
  `jumlah_total` int NOT NULL,
  `potongan` int NOT NULL,
  `jumlah_harus_bayar` int NOT NULL,
  `jumlah_bayar` int NOT NULL,
  `tgl_billing` date NOT NULL,
  `jam_billing` time NOT NULL,
  `id_user` int NOT NULL,
  `keterangan` varchar(100) NOT NULL,
  PRIMARY KEY (`id_billing`)
) ENGINE=MyISAM AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `mlite_billing`
--

INSERT INTO `mlite_billing` (`id_billing`, `kd_billing`, `no_rawat`, `jumlah_total`, `potongan`, `jumlah_harus_bayar`, `jumlah_bayar`, `tgl_billing`, `jam_billing`, `id_user`, `keterangan`) VALUES
(1, 'BIL-2026/09/08/0001', '2026/09/08/0001', 116000, 0, 116000, 116000, '2026-09-08', '08:15:00', 1, 'Lunas Kasir Pelayanan'),
(2, 'BIL-2026/09/08/0002', '2026/09/08/0002', 110000, 0, 110000, 110000, '2026-09-08', '08:30:00', 1, 'Lunas Kasir Pelayanan'),
(3, 'BIL-2026/09/08/0003', '2026/09/08/0003', 105000, 0, 105000, 105000, '2026-09-08', '09:00:00', 1, 'Lunas Kasir Pelayanan'),
(4, 'BIL-2026/09/08/0004', '2026/09/08/0004', 147000, 0, 147000, 147000, '2026-09-08', '09:30:00', 1, 'Lunas Kasir Pelayanan'),
(5, 'BIL-2026/09/08/0005', '2026/09/08/0005', 121200, 0, 121200, 121200, '2026-09-08', '10:00:00', 1, 'Lunas Kasir Pelayanan'),
(6, 'BIL-2026/09/08/0006', '2026/09/08/0006', 166000, 0, 166000, 166000, '2026-09-08', '10:30:00', 1, 'Lunas Kasir Pelayanan'),
(7, 'BIL-2026/09/08/0007', '2026/09/08/0007', 95600, 0, 95600, 95600, '2026-09-08', '11:00:00', 1, 'Lunas Kasir Pelayanan'),
(8, 'BIL-2026/09/08/0008', '2026/09/08/0008', 110000, 0, 110000, 110000, '2026-09-08', '11:30:00', 1, 'Lunas Kasir Pelayanan'),
(9, 'BIL-2026/09/08/0009', '2026/09/08/0009', 126000, 0, 126000, 126000, '2026-09-08', '12:00:00', 1, 'Lunas Kasir Pelayanan'),
(10, 'BIL-2026/09/08/0010', '2026/09/08/0010', 100000, 0, 100000, 100000, '2026-09-08', '12:30:00', 1, 'Lunas Kasir Pelayanan'),
(11, 'BIL-2026/09/08/0001', '2026/09/08/0001', 116000, 0, 116000, 116000, '2026-09-08', '08:15:00', 1, 'Lunas Kasir Pelayanan'),
(12, 'BIL-2026/09/08/0002', '2026/09/08/0002', 110000, 0, 110000, 110000, '2026-09-08', '08:30:00', 1, 'Lunas Kasir Pelayanan'),
(13, 'BIL-2026/09/08/0003', '2026/09/08/0003', 105000, 0, 105000, 105000, '2026-09-08', '09:00:00', 1, 'Lunas Kasir Pelayanan'),
(14, 'BIL-2026/09/08/0004', '2026/09/08/0004', 147000, 0, 147000, 147000, '2026-09-08', '09:30:00', 1, 'Lunas Kasir Pelayanan'),
(15, 'BIL-2026/09/08/0005', '2026/09/08/0005', 121200, 0, 121200, 121200, '2026-09-08', '10:00:00', 1, 'Lunas Kasir Pelayanan'),
(16, 'BIL-2026/09/08/0006', '2026/09/08/0006', 166000, 0, 166000, 166000, '2026-09-08', '10:30:00', 1, 'Lunas Kasir Pelayanan'),
(17, 'BIL-2026/09/08/0007', '2026/09/08/0007', 95600, 0, 95600, 95600, '2026-09-08', '11:00:00', 1, 'Lunas Kasir Pelayanan'),
(18, 'BIL-2026/09/08/0008', '2026/09/08/0008', 110000, 0, 110000, 110000, '2026-09-08', '11:30:00', 1, 'Lunas Kasir Pelayanan'),
(19, 'BIL-2026/09/08/0009', '2026/09/08/0009', 126000, 0, 126000, 126000, '2026-09-08', '12:00:00', 1, 'Lunas Kasir Pelayanan'),
(20, 'BIL-2026/09/08/0010', '2026/09/08/0010', 100000, 0, 100000, 100000, '2026-09-08', '12:30:00', 1, 'Lunas Kasir Pelayanan'),
(21, 'BIL-2026/09/08/0001', '2026/09/08/0001', 116000, 0, 116000, 116000, '2026-09-08', '08:15:00', 1, 'Lunas Kasir Pelayanan'),
(22, 'BIL-2026/09/08/0002', '2026/09/08/0002', 110000, 0, 110000, 110000, '2026-09-08', '08:30:00', 1, 'Lunas Kasir Pelayanan'),
(23, 'BIL-2026/09/08/0003', '2026/09/08/0003', 105000, 0, 105000, 105000, '2026-09-08', '09:00:00', 1, 'Lunas Kasir Pelayanan'),
(24, 'BIL-2026/09/08/0004', '2026/09/08/0004', 147000, 0, 147000, 147000, '2026-09-08', '09:30:00', 1, 'Lunas Kasir Pelayanan'),
(25, 'BIL-2026/09/08/0005', '2026/09/08/0005', 121200, 0, 121200, 121200, '2026-09-08', '10:00:00', 1, 'Lunas Kasir Pelayanan'),
(26, 'BIL-2026/09/08/0006', '2026/09/08/0006', 166000, 0, 166000, 166000, '2026-09-08', '10:30:00', 1, 'Lunas Kasir Pelayanan'),
(27, 'BIL-2026/09/08/0007', '2026/09/08/0007', 95600, 0, 95600, 95600, '2026-09-08', '11:00:00', 1, 'Lunas Kasir Pelayanan'),
(28, 'BIL-2026/09/08/0008', '2026/09/08/0008', 110000, 0, 110000, 110000, '2026-09-08', '11:30:00', 1, 'Lunas Kasir Pelayanan'),
(29, 'BIL-2026/09/08/0009', '2026/09/08/0009', 126000, 0, 126000, 126000, '2026-09-08', '12:00:00', 1, 'Lunas Kasir Pelayanan'),
(30, 'BIL-2026/09/08/0010', '2026/09/08/0010', 100000, 0, 100000, 100000, '2026-09-08', '12:30:00', 1, 'Lunas Kasir Pelayanan'),
(31, 'BIL-2026/09/08/0001', '2026/09/08/0001', 116000, 0, 116000, 116000, '2026-09-08', '08:15:00', 1, 'Lunas Kasir Pelayanan'),
(32, 'BIL-2026/09/08/0002', '2026/09/08/0002', 110000, 0, 110000, 110000, '2026-09-08', '08:30:00', 1, 'Lunas Kasir Pelayanan'),
(33, 'BIL-2026/09/08/0003', '2026/09/08/0003', 105000, 0, 105000, 105000, '2026-09-08', '09:00:00', 1, 'Lunas Kasir Pelayanan'),
(34, 'BIL-2026/09/08/0004', '2026/09/08/0004', 147000, 0, 147000, 147000, '2026-09-08', '09:30:00', 1, 'Lunas Kasir Pelayanan'),
(35, 'BIL-2026/09/08/0005', '2026/09/08/0005', 121200, 0, 121200, 121200, '2026-09-08', '10:00:00', 1, 'Lunas Kasir Pelayanan'),
(36, 'BIL-2026/09/08/0006', '2026/09/08/0006', 166000, 0, 166000, 166000, '2026-09-08', '10:30:00', 1, 'Lunas Kasir Pelayanan'),
(37, 'BIL-2026/09/08/0007', '2026/09/08/0007', 95600, 0, 95600, 95600, '2026-09-08', '11:00:00', 1, 'Lunas Kasir Pelayanan'),
(38, 'BIL-2026/09/08/0008', '2026/09/08/0008', 110000, 0, 110000, 110000, '2026-09-08', '11:30:00', 1, 'Lunas Kasir Pelayanan'),
(39, 'BIL-2026/09/08/0009', '2026/09/08/0009', 126000, 0, 126000, 126000, '2026-09-08', '12:00:00', 1, 'Lunas Kasir Pelayanan'),
(40, 'BIL-2026/09/08/0010', '2026/09/08/0010', 100000, 0, 100000, 100000, '2026-09-08', '12:30:00', 1, 'Lunas Kasir Pelayanan'),
(41, 'BIL-2026/09/08/0001', '2026/09/08/0001', 116000, 0, 116000, 116000, '2026-09-08', '08:15:00', 1, 'Lunas Kasir Pelayanan'),
(42, 'BIL-2026/09/08/0002', '2026/09/08/0002', 110000, 0, 110000, 110000, '2026-09-08', '08:30:00', 1, 'Lunas Kasir Pelayanan'),
(43, 'BIL-2026/09/08/0003', '2026/09/08/0003', 105000, 0, 105000, 105000, '2026-09-08', '09:00:00', 1, 'Lunas Kasir Pelayanan'),
(44, 'BIL-2026/09/08/0004', '2026/09/08/0004', 147000, 0, 147000, 147000, '2026-09-08', '09:30:00', 1, 'Lunas Kasir Pelayanan'),
(45, 'BIL-2026/09/08/0005', '2026/09/08/0005', 121200, 0, 121200, 121200, '2026-09-08', '10:00:00', 1, 'Lunas Kasir Pelayanan'),
(46, 'BIL-2026/09/08/0006', '2026/09/08/0006', 166000, 0, 166000, 166000, '2026-09-08', '10:30:00', 1, 'Lunas Kasir Pelayanan'),
(47, 'BIL-2026/09/08/0007', '2026/09/08/0007', 95600, 0, 95600, 95600, '2026-09-08', '11:00:00', 1, 'Lunas Kasir Pelayanan'),
(48, 'BIL-2026/09/08/0008', '2026/09/08/0008', 110000, 0, 110000, 110000, '2026-09-08', '11:30:00', 1, 'Lunas Kasir Pelayanan'),
(49, 'BIL-2026/09/08/0009', '2026/09/08/0009', 126000, 0, 126000, 126000, '2026-09-08', '12:00:00', 1, 'Lunas Kasir Pelayanan'),
(50, 'BIL-2026/09/08/0010', '2026/09/08/0010', 100000, 0, 100000, 100000, '2026-09-08', '12:30:00', 1, 'Lunas Kasir Pelayanan');

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_billing_pembayaran`
--

DROP TABLE IF EXISTS `mlite_billing_pembayaran`;
CREATE TABLE IF NOT EXISTS `mlite_billing_pembayaran` (
  `id` int NOT NULL AUTO_INCREMENT,
  `no_rawat` varchar(17) NOT NULL,
  `tgl_bayar` date NOT NULL,
  `jam_bayar` time NOT NULL,
  `metode` varchar(30) NOT NULL DEFAULT 'Tunai',
  `jumlah_bayar` double NOT NULL DEFAULT '0',
  `id_user` int DEFAULT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_billing_pembayaran_rawat` (`no_rawat`),
  KEY `idx_billing_pembayaran_tgl` (`tgl_bayar`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_billing_pembayaran_detail`
--

DROP TABLE IF EXISTS `mlite_billing_pembayaran_detail`;
CREATE TABLE IF NOT EXISTS `mlite_billing_pembayaran_detail` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pembayaran_id` int NOT NULL,
  `kelompok` varchar(30) NOT NULL,
  `jumlah_alokasi` double NOT NULL DEFAULT '0',
  `ref_modul` varchar(30) DEFAULT NULL,
  `kd_jenis_prw` varchar(15) DEFAULT NULL,
  `tgl_periksa` date DEFAULT NULL,
  `jam` time DEFAULT NULL,
  `status_periksa` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_billing_pembayaran_detail_pembayaran` (`pembayaran_id`),
  KEY `idx_billing_pembayaran_detail_kelompok` (`kelompok`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_bpjs_emr_device`
--

DROP TABLE IF EXISTS `mlite_bpjs_emr_device`;
CREATE TABLE IF NOT EXISTS `mlite_bpjs_emr_device` (
  `id` int NOT NULL AUTO_INCREMENT,
  `device_id` varchar(50) NOT NULL,
  `nama_alkes` varchar(150) NOT NULL,
  `kategori` varchar(50) DEFAULT 'tindakan',
  `kode_produk` varchar(100) DEFAULT NULL,
  `keterangan` text,
  `manufacturer` varchar(255) DEFAULT NULL,
  `manufacture_date` date DEFAULT NULL,
  `expiration_date` date DEFAULT NULL,
  `model` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_device_id` (`device_id`),
  KEY `idx_nama_alkes` (`nama_alkes`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_bpjs_emr_logs`
--

DROP TABLE IF EXISTS `mlite_bpjs_emr_logs`;
CREATE TABLE IF NOT EXISTS `mlite_bpjs_emr_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `no_sep` varchar(50) DEFAULT NULL,
  `no_rawat` varchar(50) DEFAULT NULL,
  `payload_json` longtext,
  `payload_encrypted` longtext,
  `response` longtext,
  `status` varchar(20) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_bpjs_emr_mapping_lab`
--

DROP TABLE IF EXISTS `mlite_bpjs_emr_mapping_lab`;
CREATE TABLE IF NOT EXISTS `mlite_bpjs_emr_mapping_lab` (
  `id_template` varchar(20) NOT NULL,
  `loinc_code` varchar(20) NOT NULL,
  `loinc_display` varchar(255) DEFAULT NULL,
  `master_device_id` int DEFAULT NULL,
  `focal_device_code` varchar(255) DEFAULT NULL,
  `focal_device_display` varchar(255) DEFAULT NULL,
  `focal_device_action` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id_template`),
  KEY `idx_mapping_lab_master_device` (`master_device_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_bpjs_emr_mapping_obat`
--

DROP TABLE IF EXISTS `mlite_bpjs_emr_mapping_obat`;
CREATE TABLE IF NOT EXISTS `mlite_bpjs_emr_mapping_obat` (
  `kode_brng` varchar(20) NOT NULL,
  `code` varchar(20) NOT NULL,
  PRIMARY KEY (`kode_brng`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `mlite_bpjs_emr_mapping_obat`
--

INSERT INTO `mlite_bpjs_emr_mapping_obat` (`kode_brng`, `code`) VALUES
('OB001', '10001'),
('OB002', '10002'),
('OB003', '10003'),
('OB005', '10004');

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_bpjs_emr_mapping_operasi`
--

DROP TABLE IF EXISTS `mlite_bpjs_emr_mapping_operasi`;
CREATE TABLE IF NOT EXISTS `mlite_bpjs_emr_mapping_operasi` (
  `kode_paket` varchar(20) NOT NULL,
  `snomed_code` varchar(20) NOT NULL,
  `snomed_display` varchar(255) DEFAULT NULL,
  `master_device_id` int DEFAULT NULL,
  `focal_device_code` varchar(255) DEFAULT NULL,
  `focal_device_display` varchar(255) DEFAULT NULL,
  `focal_device_action` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`kode_paket`),
  KEY `idx_mapping_operasi_master_device` (`master_device_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_bpjs_emr_mapping_prosedur`
--

DROP TABLE IF EXISTS `mlite_bpjs_emr_mapping_prosedur`;
CREATE TABLE IF NOT EXISTS `mlite_bpjs_emr_mapping_prosedur` (
  `kd_jenis_prw` varchar(20) NOT NULL,
  `snomed_code` varchar(20) NOT NULL,
  `snomed_display` varchar(255) DEFAULT NULL,
  `master_device_id` int DEFAULT NULL,
  `focal_device_code` varchar(255) DEFAULT NULL,
  `focal_device_display` varchar(255) DEFAULT NULL,
  `focal_device_action` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`kd_jenis_prw`),
  KEY `idx_mapping_proc_master_device` (`master_device_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `mlite_bpjs_emr_mapping_prosedur`
--

INSERT INTO `mlite_bpjs_emr_mapping_prosedur` (`kd_jenis_prw`, `snomed_code`, `snomed_display`, `master_device_id`, `focal_device_code`, `focal_device_display`, `focal_device_action`) VALUES
('TND001', '386053000', 'General medical consultation', 1, 'DEV01', 'Stethoscope', 'Examination'),
('TND004', '29303009', 'Electrocardiographic procedure', 2, 'DEV02', 'ECG Machine 12 Lead', 'Diagnostic'),
('TND006', '241031006', 'Removal of dental calculus', 3, 'DEV03', 'Ultrasonic Scaler', 'Procedure');

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_bpjs_emr_mapping_prosedur_ranap`
--

DROP TABLE IF EXISTS `mlite_bpjs_emr_mapping_prosedur_ranap`;
CREATE TABLE IF NOT EXISTS `mlite_bpjs_emr_mapping_prosedur_ranap` (
  `kd_jenis_prw` varchar(20) NOT NULL,
  `snomed_code` varchar(20) NOT NULL,
  `snomed_display` varchar(255) DEFAULT NULL,
  `master_device_id` int DEFAULT NULL,
  `focal_device_code` varchar(255) DEFAULT NULL,
  `focal_device_display` varchar(255) DEFAULT NULL,
  `focal_device_action` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`kd_jenis_prw`),
  KEY `idx_mapping_proc_ranap_master_device` (`master_device_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_bpjs_emr_mapping_radiologi`
--

DROP TABLE IF EXISTS `mlite_bpjs_emr_mapping_radiologi`;
CREATE TABLE IF NOT EXISTS `mlite_bpjs_emr_mapping_radiologi` (
  `kd_jenis_prw` varchar(20) NOT NULL,
  `standard_code` varchar(20) NOT NULL,
  `standard_display` varchar(255) DEFAULT NULL,
  `system` varchar(100) DEFAULT NULL,
  `master_device_id` int DEFAULT NULL,
  `focal_device_code` varchar(255) DEFAULT NULL,
  `focal_device_display` varchar(255) DEFAULT NULL,
  `focal_device_action` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`kd_jenis_prw`),
  KEY `idx_mapping_rad_master_device` (`master_device_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_bpjs_emr_uuid_condition`
--

DROP TABLE IF EXISTS `mlite_bpjs_emr_uuid_condition`;
CREATE TABLE IF NOT EXISTS `mlite_bpjs_emr_uuid_condition` (
  `kd_penyakit` varchar(15) NOT NULL,
  `uuid` varchar(200) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_bridging_pcare`
--

DROP TABLE IF EXISTS `mlite_bridging_pcare`;
CREATE TABLE IF NOT EXISTS `mlite_bridging_pcare` (
  `id` int NOT NULL AUTO_INCREMENT,
  `no_rawat` text NOT NULL,
  `no_rkm_medis` text,
  `tgl_daftar` text,
  `nomor_kunjungan` text,
  `kode_provider_peserta` text,
  `nomor_jaminan` text,
  `kode_poli` text,
  `nama_poli` text,
  `kunjungan_sakit` text,
  `sistole` text,
  `diastole` text,
  `nadi` text,
  `respirasi` text,
  `tinggi` text,
  `berat` text,
  `lingkar_perut` text,
  `rujuk_balik` text,
  `subyektif` text,
  `kode_tkp` text,
  `nomor_urut` text,
  `kode_kesadaran` text,
  `nama_kesadaran` text,
  `terapi` text,
  `kode_status_pulang` text,
  `nama_status_pulang` text,
  `tgl_pulang` text,
  `tgl_kunjungan` text,
  `kode_dokter` text,
  `nama_dokter` text,
  `kode_diagnosa1` text,
  `nama_diagnosa1` text,
  `kode_diagnosa2` text,
  `nama_diagnosa2` text,
  `kode_diagnosa3` text,
  `nama_diagnosa3` text,
  `tgl_estimasi_rujuk` text,
  `kode_ppk` text,
  `nama_ppk` text,
  `kode_spesialis` text,
  `nama_spesialis` text,
  `kode_subspesialis` text,
  `nama_subspesialis` text,
  `kode_sarana` text,
  `nama_sarana` text,
  `kode_referensikhusus` text,
  `nama_referensikhusus` text,
  `kode_faskeskhusus` text,
  `nama_faskeskhusus` text,
  `catatan` text,
  `kode_tacc` text,
  `nama_tacc` text,
  `alasan_tacc` text,
  `id_user` text NOT NULL,
  `tgl_input` text NOT NULL,
  `status_kirim` text NOT NULL,
  `kode_alergi_makanan` text,
  `nama_alergi_makanan` text,
  `kode_alergi_udara` text,
  `nama_alergi_udara` text,
  `kode_alergi_obat` text,
  `nama_alergi_obat` text,
  `kode_prognosa` text,
  `nama_prognosa` text,
  `terapi_obat` text,
  `terapi_non_obat` text,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `mlite_bridging_pcare`
--

INSERT INTO `mlite_bridging_pcare` (`id`, `no_rawat`, `no_rkm_medis`, `tgl_daftar`, `nomor_kunjungan`, `kode_provider_peserta`, `nomor_jaminan`, `kode_poli`, `nama_poli`, `kunjungan_sakit`, `sistole`, `diastole`, `nadi`, `respirasi`, `tinggi`, `berat`, `lingkar_perut`, `rujuk_balik`, `subyektif`, `kode_tkp`, `nomor_urut`, `kode_kesadaran`, `nama_kesadaran`, `terapi`, `kode_status_pulang`, `nama_status_pulang`, `tgl_pulang`, `tgl_kunjungan`, `kode_dokter`, `nama_dokter`, `kode_diagnosa1`, `nama_diagnosa1`, `kode_diagnosa2`, `nama_diagnosa2`, `kode_diagnosa3`, `nama_diagnosa3`, `tgl_estimasi_rujuk`, `kode_ppk`, `nama_ppk`, `kode_spesialis`, `nama_spesialis`, `kode_subspesialis`, `nama_subspesialis`, `kode_sarana`, `nama_sarana`, `kode_referensikhusus`, `nama_referensikhusus`, `kode_faskeskhusus`, `nama_faskeskhusus`, `catatan`, `kode_tacc`, `nama_tacc`, `alasan_tacc`, `id_user`, `tgl_input`, `status_kirim`, `kode_alergi_makanan`, `nama_alergi_makanan`, `kode_alergi_udara`, `nama_alergi_udara`, `kode_alergi_obat`, `nama_alergi_obat`, `kode_prognosa`, `nama_prognosa`, `terapi_obat`, `terapi_non_obat`) VALUES
(1, '2026/09/08/0002', '000002', '2026-09-08', '012300010926K000001', '0123B001', '0001234567892', '001', 'POLI UMUM', 'true', '110', '75', '80', '20', '155', '58', '75', '0', 'Batuk berdahak dan flu', '10', 'A-002', '01', 'Compos Mentis', 'Amoxicillin, Paracetamol, Ambroxol', '3', 'Berobat Jalan', '2026-09-08', '2026-09-08', 'DOC002', 'dr. Siti Rahmawati', 'J06.9', 'Acute upper respiratory infection, unspecified', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', 'Terkirim', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(2, '2026/09/08/0002', '000002', '2026-09-08', '012300010926K000001', '0123B001', '0001234567892', '001', 'POLI UMUM', 'true', '110', '75', '80', '20', '155', '58', '75', '0', 'Batuk berdahak dan flu', '10', 'A-002', '01', 'Compos Mentis', 'Amoxicillin, Paracetamol, Ambroxol', '3', 'Berobat Jalan', '2026-09-08', '2026-09-08', 'DOC002', 'dr. Siti Rahmawati', 'J06.9', 'Acute upper respiratory infection, unspecified', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', 'Terkirim', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(3, '2026/09/08/0002', '000002', '2026-09-08', '012300010926K000001', '0123B001', '0001234567892', '001', 'POLI UMUM', 'true', '110', '75', '80', '20', '155', '58', '75', '0', 'Batuk berdahak dan flu', '10', 'A-002', '01', 'Compos Mentis', 'Amoxicillin, Paracetamol, Ambroxol', '3', 'Berobat Jalan', '2026-09-08', '2026-09-08', 'DOC002', 'dr. Siti Rahmawati', 'J06.9', 'Acute upper respiratory infection, unspecified', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', 'Terkirim', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(4, '2026/09/08/0002', '000002', '2026-09-08', '012300010926K000001', '0123B001', '0001234567892', '001', 'POLI UMUM', 'true', '110', '75', '80', '20', '155', '58', '75', '0', 'Batuk berdahak dan flu', '10', 'A-002', '01', 'Compos Mentis', 'Amoxicillin, Paracetamol, Ambroxol', '3', 'Berobat Jalan', '2026-09-08', '2026-09-08', 'DOC002', 'dr. Siti Rahmawati', 'J06.9', 'Acute upper respiratory infection, unspecified', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', 'Terkirim', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(5, '2026/09/08/0002', '000002', '2026-09-08', '012300010926K000001', '0123B001', '0001234567892', '001', 'POLI UMUM', 'true', '110', '75', '80', '20', '155', '58', '75', '0', 'Batuk berdahak dan flu', '10', 'A-002', '01', 'Compos Mentis', 'Amoxicillin, Paracetamol, Ambroxol', '3', 'Berobat Jalan', '2026-09-08', '2026-09-08', 'DOC002', 'dr. Siti Rahmawati', 'J06.9', 'Acute upper respiratory infection, unspecified', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', 'Terkirim', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_clinical_pathway`
--

DROP TABLE IF EXISTS `mlite_clinical_pathway`;
CREATE TABLE IF NOT EXISTS `mlite_clinical_pathway` (
  `id` int NOT NULL AUTO_INCREMENT,
  `kode_cp` varchar(30) NOT NULL,
  `nama_cp` varchar(150) NOT NULL,
  `jenis_layanan` enum('Ralan','Ranap') NOT NULL DEFAULT 'Ranap',
  `target_los` int NOT NULL DEFAULT '0',
  `target_tarif` double NOT NULL DEFAULT '0',
  `confidence_score` decimal(5,2) NOT NULL DEFAULT '0.00',
  `evidence_note` text,
  `guideline_note` text,
  `aktif` enum('Ya','Tidak') NOT NULL DEFAULT 'Ya',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode_cp` (`kode_cp`),
  KEY `nama_cp` (`nama_cp`),
  KEY `jenis_layanan` (`jenis_layanan`),
  KEY `aktif` (`aktif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_clinical_pathway_activity`
--

DROP TABLE IF EXISTS `mlite_clinical_pathway_activity`;
CREATE TABLE IF NOT EXISTS `mlite_clinical_pathway_activity` (
  `id` int NOT NULL AUTO_INCREMENT,
  `clinical_pathway_day_id` int NOT NULL,
  `kategori` enum('Assessment','Laboratorium','Radiologi','Obat','Tindakan','Nutrisi','Edukasi','Monitoring','Outcome') NOT NULL,
  `uraian_kegiatan` varchar(255) DEFAULT NULL,
  `sumber_tabel` varchar(50) DEFAULT NULL,
  `item_kode` varchar(50) DEFAULT NULL,
  `item_nama` varchar(255) NOT NULL,
  `keterangan` text,
  `evidence_frequency` int NOT NULL DEFAULT '0',
  `evidence_percentage` decimal(5,2) NOT NULL DEFAULT '0.00',
  `evidence_status` enum('Wajib','Direkomendasikan','Opsional') NOT NULL DEFAULT 'Opsional',
  `wajib` enum('Ya','Tidak') NOT NULL DEFAULT 'Ya',
  `urutan` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `clinical_pathway_day_id` (`clinical_pathway_day_id`),
  KEY `kategori` (`kategori`),
  KEY `item_kode` (`item_kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_clinical_pathway_audit`
--

DROP TABLE IF EXISTS `mlite_clinical_pathway_audit`;
CREATE TABLE IF NOT EXISTS `mlite_clinical_pathway_audit` (
  `id` int NOT NULL AUTO_INCREMENT,
  `clinical_pathway_patient_id` int DEFAULT NULL,
  `clinical_pathway_id` int DEFAULT NULL,
  `aksi` varchar(100) NOT NULL,
  `referensi` varchar(100) DEFAULT NULL,
  `deskripsi` text,
  `user_aksi` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `clinical_pathway_patient_id` (`clinical_pathway_patient_id`),
  KEY `clinical_pathway_id` (`clinical_pathway_id`),
  KEY `aksi` (`aksi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_clinical_pathway_compliance`
--

DROP TABLE IF EXISTS `mlite_clinical_pathway_compliance`;
CREATE TABLE IF NOT EXISTS `mlite_clinical_pathway_compliance` (
  `id` int NOT NULL AUTO_INCREMENT,
  `clinical_pathway_patient_id` int NOT NULL,
  `planned_activity` int NOT NULL DEFAULT '0',
  `completed_activity` int NOT NULL DEFAULT '0',
  `missed_activity` int NOT NULL DEFAULT '0',
  `compliance_percentage` decimal(5,2) NOT NULL DEFAULT '0.00',
  `kategori_kepatuhan` enum('Sangat Patuh','Patuh','Kurang Patuh','Tidak Patuh') NOT NULL DEFAULT 'Tidak Patuh',
  `last_calculated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `clinical_pathway_patient_id` (`clinical_pathway_patient_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_clinical_pathway_cppt_template`
--

DROP TABLE IF EXISTS `mlite_clinical_pathway_cppt_template`;
CREATE TABLE IF NOT EXISTS `mlite_clinical_pathway_cppt_template` (
  `id` int NOT NULL AUTO_INCREMENT,
  `kd_penyakit` varchar(10) NOT NULL,
  `ppra` varchar(100) NOT NULL,
  `subjective` text NOT NULL,
  `objective` text NOT NULL,
  `assessment` text NOT NULL,
  `plan` text NOT NULL,
  `aktif` enum('Ya','Tidak') NOT NULL DEFAULT 'Ya',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cppt_template_kd_penyakit_ppra` (`kd_penyakit`,`ppra`),
  KEY `cppt_template_aktif` (`aktif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_clinical_pathway_day`
--

DROP TABLE IF EXISTS `mlite_clinical_pathway_day`;
CREATE TABLE IF NOT EXISTS `mlite_clinical_pathway_day` (
  `id` int NOT NULL AUTO_INCREMENT,
  `clinical_pathway_id` int NOT NULL,
  `hari_ke` int NOT NULL,
  `label_hari` varchar(100) DEFAULT NULL,
  `tujuan_harian` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cp_day_unique` (`clinical_pathway_id`,`hari_ke`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_clinical_pathway_diagnosis`
--

DROP TABLE IF EXISTS `mlite_clinical_pathway_diagnosis`;
CREATE TABLE IF NOT EXISTS `mlite_clinical_pathway_diagnosis` (
  `id` int NOT NULL AUTO_INCREMENT,
  `clinical_pathway_id` int NOT NULL,
  `kd_penyakit` varchar(10) NOT NULL,
  `prioritas` tinyint NOT NULL DEFAULT '1',
  `tipe` enum('Utama','Sekunder') NOT NULL DEFAULT 'Utama',
  PRIMARY KEY (`id`),
  UNIQUE KEY `cp_diagnosis_unique` (`clinical_pathway_id`,`kd_penyakit`,`tipe`),
  KEY `kd_penyakit` (`kd_penyakit`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_clinical_pathway_execution`
--

DROP TABLE IF EXISTS `mlite_clinical_pathway_execution`;
CREATE TABLE IF NOT EXISTS `mlite_clinical_pathway_execution` (
  `id` int NOT NULL AUTO_INCREMENT,
  `clinical_pathway_patient_id` int NOT NULL,
  `clinical_pathway_activity_id` int NOT NULL,
  `hari_ke` int NOT NULL,
  `tanggal_rencana` date DEFAULT NULL,
  `tanggal_realisasi` datetime DEFAULT NULL,
  `status` enum('Planned','Completed','Missed','Variance') NOT NULL DEFAULT 'Planned',
  `sumber_data` varchar(50) DEFAULT NULL,
  `sumber_referensi` varchar(100) DEFAULT NULL,
  `petugas` varchar(20) DEFAULT NULL,
  `catatan` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cp_exec_unique` (`clinical_pathway_patient_id`,`clinical_pathway_activity_id`,`hari_ke`),
  KEY `clinical_pathway_activity_id` (`clinical_pathway_activity_id`),
  KEY `status` (`status`),
  KEY `tanggal_rencana` (`tanggal_rencana`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_clinical_pathway_patient`
--

DROP TABLE IF EXISTS `mlite_clinical_pathway_patient`;
CREATE TABLE IF NOT EXISTS `mlite_clinical_pathway_patient` (
  `id` int NOT NULL AUTO_INCREMENT,
  `no_rawat` varchar(17) NOT NULL,
  `clinical_pathway_id` int NOT NULL,
  `kd_penyakit` varchar(10) DEFAULT NULL,
  `tanggal_mulai` datetime NOT NULL,
  `tanggal_selesai` datetime DEFAULT NULL,
  `status` enum('Draft','Aktif','Selesai','Drop') NOT NULL DEFAULT 'Aktif',
  `auto_generated` enum('Ya','Tidak') NOT NULL DEFAULT 'Ya',
  PRIMARY KEY (`id`),
  UNIQUE KEY `no_rawat` (`no_rawat`),
  KEY `clinical_pathway_id` (`clinical_pathway_id`),
  KEY `kd_penyakit` (`kd_penyakit`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_clinical_pathway_variance`
--

DROP TABLE IF EXISTS `mlite_clinical_pathway_variance`;
CREATE TABLE IF NOT EXISTS `mlite_clinical_pathway_variance` (
  `id` int NOT NULL AUTO_INCREMENT,
  `clinical_pathway_patient_id` int NOT NULL,
  `clinical_pathway_execution_id` int DEFAULT NULL,
  `kategori_variance` enum('Diagnosis','LOS','Obat','Tindakan','Lab','Radiologi','Nutrisi','Edukasi','Outcome','Administrasi') NOT NULL,
  `penyebab` varchar(255) DEFAULT NULL,
  `deskripsi` text NOT NULL,
  `severity` enum('Rendah','Sedang','Tinggi') NOT NULL DEFAULT 'Sedang',
  `tanggal_variance` datetime NOT NULL,
  `status_tindak_lanjut` enum('Open','Closed') NOT NULL DEFAULT 'Open',
  PRIMARY KEY (`id`),
  KEY `clinical_pathway_patient_id` (`clinical_pathway_patient_id`),
  KEY `clinical_pathway_execution_id` (`clinical_pathway_execution_id`),
  KEY `kategori_variance` (`kategori_variance`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_crud_permissions`
--

DROP TABLE IF EXISTS `mlite_crud_permissions`;
CREATE TABLE IF NOT EXISTS `mlite_crud_permissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user` varchar(100) NOT NULL,
  `module` varchar(100) NOT NULL,
  `can_create` varchar(10) NOT NULL DEFAULT 'true',
  `can_read` varchar(10) NOT NULL DEFAULT 'true',
  `can_update` varchar(10) NOT NULL DEFAULT 'true',
  `can_delete` varchar(10) NOT NULL DEFAULT 'true',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `user` (`user`,`module`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_detailjurnal`
--

DROP TABLE IF EXISTS `mlite_detailjurnal`;
CREATE TABLE IF NOT EXISTS `mlite_detailjurnal` (
  `no_jurnal` varchar(20) DEFAULT NULL,
  `kd_rek` varchar(15) DEFAULT NULL,
  `arus_kas` int NOT NULL,
  `debet` double NOT NULL,
  `kredit` double NOT NULL,
  KEY `no_jurnal` (`no_jurnal`),
  KEY `kd_rek` (`kd_rek`),
  KEY `debet` (`debet`),
  KEY `kredit` (`kredit`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `mlite_detailjurnal`
--

INSERT INTO `mlite_detailjurnal` (`no_jurnal`, `kd_rek`, `arus_kas`, `debet`, `kredit`) VALUES
('JU-2025-001', '1101', 0, 75000000, 0),
('JU-2025-001', '4101', 0, 0, 30000000),
('JU-2025-001', '4102', 0, 0, 20000000),
('JU-2025-001', '4103', 0, 0, 15000000),
('JU-2025-001', '4104', 0, 0, 5000000),
('JU-2025-001', '4105', 0, 0, 5000000),
('JU-2025-002', '5101', 0, 10000000, 0),
('JU-2025-002', '5102', 0, 8000000, 0),
('JU-2025-002', '5103', 0, 5000000, 0),
('JU-2025-002', '1101', 0, 0, 23000000),
('JU-2025-003', '1101', 0, 90000000, 0),
('JU-2025-003', '4101', 0, 0, 35000000),
('JU-2025-003', '4102', 0, 0, 25000000),
('JU-2025-003', '4103', 0, 0, 20000000),
('JU-2025-003', '4104', 0, 0, 5000000),
('JU-2025-003', '4105', 0, 0, 5000000),
('JU-2025-004', '5101', 0, 10000000, 0),
('JU-2025-004', '5102', 0, 8000000, 0),
('JU-2025-004', '5103', 0, 5000000, 0),
('JU-2025-004', '5201', 0, 25000000, 0),
('JU-2025-004', '5301', 0, 2000000, 0),
('JU-2025-004', '1101', 0, 0, 50000000),
('JU-2025-005', '1101', 0, 85000000, 0),
('JU-2025-005', '4101', 0, 0, 30000000),
('JU-2025-005', '4102', 0, 0, 25000000),
('JU-2025-005', '4103', 0, 0, 18000000),
('JU-2025-005', '4104', 0, 0, 7000000),
('JU-2025-005', '4105', 0, 0, 5000000),
('JU-2025-006', '5101', 0, 10000000, 0),
('JU-2025-006', '5102', 0, 8000000, 0),
('JU-2025-006', '5103', 0, 5000000, 0),
('JU-2025-006', '5201', 0, 20000000, 0),
('JU-2025-006', '5301', 0, 2000000, 0),
('JU-2025-006', '1101', 0, 0, 45000000),
('JU-2025-007', '1101', 0, 95000000, 0),
('JU-2025-007', '4101', 0, 0, 35000000),
('JU-2025-007', '4102', 0, 0, 30000000),
('JU-2025-007', '4103', 0, 0, 18000000),
('JU-2025-007', '4104', 0, 0, 7000000),
('JU-2025-007', '4105', 0, 0, 5000000),
('JU-2025-008', '5101', 0, 10000000, 0),
('JU-2025-008', '5102', 0, 8000000, 0),
('JU-2025-008', '5103', 0, 5000000, 0),
('JU-2025-008', '5201', 0, 22000000, 0),
('JU-2025-008', '5301', 0, 2000000, 0),
('JU-2025-008', '1101', 0, 0, 47000000),
('JU-2025-009', '5401', 0, 15000000, 0),
('JU-2025-009', '1601', 0, 0, 5000000),
('JU-2025-009', '1701', 0, 0, 7000000),
('JU-2025-009', '1801', 0, 0, 3000000),
('JU-2026-001', '1101', 0, 80000000, 0),
('JU-2026-001', '4101', 0, 0, 30000000),
('JU-2026-001', '4102', 0, 0, 25000000),
('JU-2026-001', '4103', 0, 0, 15000000),
('JU-2026-001', '4104', 0, 0, 5000000),
('JU-2026-001', '4105', 0, 0, 5000000),
('JU-2026-002', '5101', 0, 10000000, 0),
('JU-2026-002', '5102', 0, 8000000, 0),
('JU-2026-002', '5103', 0, 5000000, 0),
('JU-2026-002', '5201', 0, 20000000, 0),
('JU-2026-002', '5301', 0, 2500000, 0),
('JU-2026-002', '1101', 0, 0, 45500000),
('JU-2026-003', '1101', 0, 75000000, 0),
('JU-2026-003', '4101', 0, 0, 28000000),
('JU-2026-003', '4102', 0, 0, 22000000),
('JU-2026-003', '4103', 0, 0, 14000000),
('JU-2026-003', '4104', 0, 0, 6000000),
('JU-2026-003', '4105', 0, 0, 5000000),
('JU-2026-004', '5101', 0, 10000000, 0),
('JU-2026-004', '5102', 0, 8000000, 0),
('JU-2026-004', '5103', 0, 5000000, 0),
('JU-2026-004', '5201', 0, 18000000, 0),
('JU-2026-004', '5302', 0, 600000, 0),
('JU-2026-004', '1101', 0, 0, 41600000),
('JU-2026-005', '1101', 0, 85000000, 0),
('JU-2026-005', '4101', 0, 0, 32000000),
('JU-2026-005', '4102', 0, 0, 27000000),
('JU-2026-005', '4103', 0, 0, 16000000),
('JU-2026-005', '4104', 0, 0, 5000000),
('JU-2026-005', '4105', 0, 0, 5000000),
('JU-2026-006', '5101', 0, 10000000, 0),
('JU-2026-006', '5102', 0, 8000000, 0),
('JU-2026-006', '5103', 0, 5000000, 0),
('JU-2026-006', '5201', 0, 22000000, 0),
('JU-2026-006', '5301', 0, 2500000, 0),
('JU-2026-006', '5302', 0, 600000, 0),
('JU-2026-006', '1101', 0, 0, 48100000),
('JRN-20260908-001', '11301', 1, 116000, 0),
('JRN-20260908-001', '41101', 2, 0, 25000),
('JRN-20260908-001', '41103', 2, 0, 65000),
('JRN-20260908-001', '41102', 2, 0, 26000),
('JRN-20260908-002', '11101', 1, 102000, 0),
('JRN-20260908-002', '41101', 2, 0, 20000),
('JRN-20260908-002', '41103', 2, 0, 65000),
('JRN-20260908-002', '41102', 2, 0, 17000),
('JRN-20260908-001', '11301', 1, 116000, 0),
('JRN-20260908-001', '41101', 2, 0, 25000),
('JRN-20260908-001', '41103', 2, 0, 65000),
('JRN-20260908-001', '41102', 2, 0, 26000),
('JRN-20260908-002', '11101', 1, 102000, 0),
('JRN-20260908-002', '41101', 2, 0, 20000),
('JRN-20260908-002', '41103', 2, 0, 65000),
('JRN-20260908-002', '41102', 2, 0, 17000),
('JRN-20260908-001', '11301', 1, 116000, 0),
('JRN-20260908-001', '41101', 2, 0, 25000),
('JRN-20260908-001', '41103', 2, 0, 65000),
('JRN-20260908-001', '41102', 2, 0, 26000),
('JRN-20260908-002', '11101', 1, 102000, 0),
('JRN-20260908-002', '41101', 2, 0, 20000),
('JRN-20260908-002', '41103', 2, 0, 65000),
('JRN-20260908-002', '41102', 2, 0, 17000),
('JRN-20260908-001', '11301', 1, 116000, 0),
('JRN-20260908-001', '41101', 2, 0, 25000),
('JRN-20260908-001', '41103', 2, 0, 65000),
('JRN-20260908-001', '41102', 2, 0, 26000),
('JRN-20260908-002', '11101', 1, 102000, 0),
('JRN-20260908-002', '41101', 2, 0, 20000),
('JRN-20260908-002', '41103', 2, 0, 65000),
('JRN-20260908-002', '41102', 2, 0, 17000),
('JRN-20260908-001', '11301', 1, 116000, 0),
('JRN-20260908-001', '41101', 2, 0, 25000),
('JRN-20260908-001', '41103', 2, 0, 65000),
('JRN-20260908-001', '41102', 2, 0, 26000),
('JRN-20260908-002', '11101', 1, 102000, 0),
('JRN-20260908-002', '41101', 2, 0, 20000),
('JRN-20260908-002', '41103', 2, 0, 65000),
('JRN-20260908-002', '41102', 2, 0, 17000);

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_duitku`
--

DROP TABLE IF EXISTS `mlite_duitku`;
CREATE TABLE IF NOT EXISTS `mlite_duitku` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tanggal` datetime NOT NULL,
  `no_rkm_medis` varchar(15) NOT NULL,
  `paymentUrl` varchar(255) NOT NULL,
  `merchantCode` varchar(255) NOT NULL,
  `reference` varchar(255) NOT NULL,
  `vaNumber` varchar(255) NOT NULL,
  `amount` varchar(255) NOT NULL,
  `statusCode` varchar(255) NOT NULL,
  `statusMessage` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `reference` (`reference`),
  KEY `mlite_duitku_ibfk_1` (`no_rkm_medis`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_eklaim_logs`
--

DROP TABLE IF EXISTS `mlite_eklaim_logs`;
CREATE TABLE IF NOT EXISTS `mlite_eklaim_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nomor_sep` varchar(30) NOT NULL,
  `method` varchar(100) DEFAULT NULL,
  `request_data` longtext,
  `response_data` longtext,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `status` int DEFAULT '1',
  `username` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_esignatures`
--

DROP TABLE IF EXISTS `mlite_esignatures`;
CREATE TABLE IF NOT EXISTS `mlite_esignatures` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ref_type` varchar(50) NOT NULL,
  `ref_id` varchar(50) NOT NULL,
  `signer_role` varchar(50) NOT NULL,
  `signer_id` varchar(50) NOT NULL,
  `signer_name` varchar(255) NOT NULL,
  `signature_path` varchar(255) NOT NULL,
  `signature_hash` varchar(255) NOT NULL,
  `chain_hash` varchar(255) DEFAULT NULL,
  `signed_at` datetime NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` varchar(255) NOT NULL,
  `legal_basis` text,
  `audit_json` text,
  PRIMARY KEY (`id`),
  KEY `ref_idx` (`ref_type`,`ref_id`),
  KEY `hash_idx` (`signature_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_farmasi_pemesanan_obat`
--

DROP TABLE IF EXISTS `mlite_farmasi_pemesanan_obat`;
CREATE TABLE IF NOT EXISTS `mlite_farmasi_pemesanan_obat` (
  `id` int NOT NULL AUTO_INCREMENT,
  `no_pemesanan` varchar(30) NOT NULL,
  `no_pengajuan` varchar(30) NOT NULL,
  `pengajuan_id` int NOT NULL,
  `kode_brng` varchar(15) NOT NULL,
  `tanggal_pemesanan` date NOT NULL,
  `supplier_kode` text,
  `supplier` varchar(255) NOT NULL,
  `jumlah_pengajuan` int NOT NULL DEFAULT '0',
  `jumlah_pesan` int NOT NULL DEFAULT '0',
  `status_pemesanan` varchar(20) NOT NULL DEFAULT 'Draft',
  `catatan` text,
  `dibuat_oleh` varchar(100) DEFAULT '-',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_no_pemesanan` (`no_pemesanan`),
  KEY `idx_no_pengajuan_pemesanan` (`no_pengajuan`),
  KEY `idx_pengajuan_id` (`pengajuan_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `mlite_farmasi_pemesanan_obat`
--

INSERT INTO `mlite_farmasi_pemesanan_obat` (`id`, `no_pemesanan`, `no_pengajuan`, `pengajuan_id`, `kode_brng`, `tanggal_pemesanan`, `supplier_kode`, `supplier`, `jumlah_pengajuan`, `jumlah_pesan`, `status_pemesanan`, `catatan`, `dibuat_oleh`, `created_at`) VALUES
(1, 'PO-20260901-001', 'REQ-001', 1, 'OB001', '2026-09-01', 'SUP01', 'PT. ENSEVAL PUTERA MEGATRADING TBK', 500, 500, 'Selesai', 'Pemesanan Rutin Paracetamol', 'admin', '2026-09-01 09:00:00'),
(2, 'PO-20260902-002', 'REQ-002', 2, 'OB002', '2026-09-02', 'SUP02', 'PT. ANUGRAH PHARMINDO LESTARI', 300, 300, 'Selesai', 'Pemesanan Rutin Amoxicillin', 'admin', '2026-09-02 10:00:00'),
(3, 'PO-20260905-003', 'REQ-003', 3, 'OB003', '2026-09-05', 'SUP03', 'PT. KIMIA FARMA TRADING', 200, 200, 'Selesai', 'Pemesanan Rutin Amlodipine', 'admin', '2026-09-05 14:00:00');

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_farmasi_penerimaan_obat`
--

DROP TABLE IF EXISTS `mlite_farmasi_penerimaan_obat`;
CREATE TABLE IF NOT EXISTS `mlite_farmasi_penerimaan_obat` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pemesanan_id` int NOT NULL,
  `tanggal_penerimaan` date NOT NULL,
  `jumlah_terima` int NOT NULL DEFAULT '0',
  `jenis_pembayaran` varchar(10) NOT NULL DEFAULT 'Cash',
  `tanggal_jatuh_tempo` date DEFAULT NULL,
  `nomor_faktur` varchar(100) DEFAULT NULL,
  `catatan` text,
  `dibuat_oleh` varchar(100) DEFAULT '-',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pemesanan_id` (`pemesanan_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `mlite_farmasi_penerimaan_obat`
--

INSERT INTO `mlite_farmasi_penerimaan_obat` (`id`, `pemesanan_id`, `tanggal_penerimaan`, `jumlah_terima`, `jenis_pembayaran`, `tanggal_jatuh_tempo`, `nomor_faktur`, `catatan`, `dibuat_oleh`, `created_at`) VALUES
(1, 1, '2026-09-03', 500, 'Kredit', '2026-10-03', 'FAK-2026-001', 'Penerimaan Lengkap Paracetamol Sesuai PO', 'admin', '2026-09-03 11:00:00'),
(2, 2, '2026-09-04', 300, 'Kredit', '2026-10-04', 'FAK-2026-002', 'Penerimaan Lengkap Amoxicillin Sesuai PO', 'admin', '2026-09-04 13:30:00'),
(3, 3, '2026-09-06', 200, 'Kredit', '2026-10-06', 'FAK-2026-003', 'Penerimaan Lengkap Amlodipine Sesuai PO', 'admin', '2026-09-06 15:00:00');

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_farmasi_pengajuan_obat`
--

DROP TABLE IF EXISTS `mlite_farmasi_pengajuan_obat`;
CREATE TABLE IF NOT EXISTS `mlite_farmasi_pengajuan_obat` (
  `id` int NOT NULL AUTO_INCREMENT,
  `no_pengajuan` varchar(30) NOT NULL,
  `tanggal_pengajuan` date NOT NULL,
  `kode_brng` varchar(15) NOT NULL,
  `jumlah` int NOT NULL DEFAULT '0',
  `status` varchar(20) NOT NULL DEFAULT 'Menunggu',
  `catatan` text,
  `dibuat_oleh` varchar(100) DEFAULT '-',
  `disetujui_oleh` varchar(100) DEFAULT NULL,
  `disetujui_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_no_pengajuan` (`no_pengajuan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_geolocation_presensi`
--

DROP TABLE IF EXISTS `mlite_geolocation_presensi`;
CREATE TABLE IF NOT EXISTS `mlite_geolocation_presensi` (
  `id` int NOT NULL,
  `tanggal` date DEFAULT NULL,
  `latitude` varchar(200) NOT NULL,
  `longitude` varchar(200) NOT NULL,
  KEY `mlite_geolocation_presensi_ibfk_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_idr_codes`
--

DROP TABLE IF EXISTS `mlite_idr_codes`;
CREATE TABLE IF NOT EXISTS `mlite_idr_codes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `code2` varchar(20) NOT NULL,
  `description` text,
  `system` varchar(50) DEFAULT NULL,
  `validcode` tinyint(1) DEFAULT NULL,
  `accpdx` char(1) DEFAULT NULL,
  `asterisk` tinyint(1) DEFAULT NULL,
  `im` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_inacbg_codes`
--

DROP TABLE IF EXISTS `mlite_inacbg_codes`;
CREATE TABLE IF NOT EXISTS `mlite_inacbg_codes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `code2` varchar(50) NOT NULL,
  `description` text,
  `system` varchar(100) DEFAULT NULL,
  `validcode` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_jurnal`
--

DROP TABLE IF EXISTS `mlite_jurnal`;
CREATE TABLE IF NOT EXISTS `mlite_jurnal` (
  `no_jurnal` varchar(20) NOT NULL,
  `no_bukti` varchar(20) DEFAULT NULL,
  `tgl_jurnal` date DEFAULT NULL,
  `jenis` enum('U','P') DEFAULT NULL,
  `kegiatan` varchar(250) NOT NULL,
  `keterangan` varchar(350) DEFAULT NULL,
  PRIMARY KEY (`no_jurnal`),
  KEY `no_bukti` (`no_bukti`),
  KEY `tgl_jurnal` (`tgl_jurnal`),
  KEY `jenis` (`jenis`),
  KEY `keterangan` (`keterangan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `mlite_jurnal`
--

INSERT INTO `mlite_jurnal` (`no_jurnal`, `no_bukti`, `tgl_jurnal`, `jenis`, `kegiatan`, `keterangan`) VALUES
('JRN-20260908-001', 'BIL-2026/09/08/0001', '2026-09-08', 'U', 'Penerimaan Tagihan Rawat Jalan', 'Penerimaan Rawat Jalan Pasien Bambang Hermawan BPJS'),
('JRN-20260908-002', 'BIL-2026/09/08/0003', '2026-09-08', 'U', 'Penerimaan Tagihan Rawat Jalan', 'Penerimaan Rawat Jalan Pasien Hendro Kurniawan Umum'),
('JU-2025-001', 'BKT-2025-001', '2025-01-15', 'U', 'Penerimaan Kasir Rawat Jalan', 'Penerimaan pendapatan layanan Q1 Januari 2025. Diposting oleh Administrator.'),
('JU-2025-002', 'BKT-2025-002', '2025-01-31', 'U', 'Pembayaran Gaji Karyawan', 'Pembayaran gaji seluruh karyawan bulan Januari 2025. Diposting oleh Administrator.'),
('JU-2025-003', 'BKT-2025-003', '2025-04-15', 'U', 'Penerimaan Kasir Q2', 'Penerimaan pendapatan layanan Q2 April 2025. Diposting oleh Administrator.'),
('JU-2025-004', 'BKT-2025-004', '2025-04-30', 'U', 'Pembayaran Biaya Operasional Q2', 'Pembayaran biaya operasional bulan April 2025. Diposting oleh Administrator.'),
('JU-2025-005', 'BKT-2025-005', '2025-07-15', 'U', 'Penerimaan Kasir Q3', 'Penerimaan pendapatan layanan Q3 Juli 2025. Diposting oleh Administrator.'),
('JU-2025-006', 'BKT-2025-006', '2025-07-31', 'U', 'Pembayaran Biaya Operasional Q3', 'Pembayaran biaya operasional bulan Juli 2025. Diposting oleh Administrator.'),
('JU-2025-007', 'BKT-2025-007', '2025-10-15', 'U', 'Penerimaan Kasir Q4', 'Penerimaan pendapatan layanan Q4 Oktober 2025. Diposting oleh Administrator.'),
('JU-2025-008', 'BKT-2025-008', '2025-10-31', 'U', 'Pembayaran Biaya Operasional Q4', 'Pembayaran biaya operasional bulan Oktober 2025. Diposting oleh Administrator.'),
('JU-2025-009', 'BKT-2025-009', '2025-12-31', 'P', 'Penyesuaian Akhir Tahun 2025', 'Jurnal penyesuaian beban penyusutan aset tetap tahun 2025. Diposting oleh Administrator.'),
('JU-2026-001', 'BKT-2026-001', '2026-01-15', 'U', 'Penerimaan Kasir Januari 2026', 'Penerimaan pendapatan layanan bulan Januari 2026. Diposting oleh Administrator.'),
('JU-2026-002', 'BKT-2026-002', '2026-01-31', 'U', 'Pembayaran Biaya Januari 2026', 'Pembayaran biaya operasional bulan Januari 2026. Diposting oleh Administrator.'),
('JU-2026-003', 'BKT-2026-003', '2026-02-15', 'U', 'Penerimaan Kasir Februari 2026', 'Penerimaan pendapatan layanan bulan Februari 2026. Diposting oleh Administrator.'),
('JU-2026-004', 'BKT-2026-004', '2026-02-28', 'U', 'Pembayaran Biaya Februari 2026', 'Pembayaran biaya operasional bulan Februari 2026. Diposting oleh Administrator.'),
('JU-2026-005', 'BKT-2026-005', '2026-03-15', 'U', 'Penerimaan Kasir Maret 2026', 'Penerimaan pendapatan layanan bulan Maret 2026. Diposting oleh Administrator.'),
('JU-2026-006', 'BKT-2026-006', '2026-03-31', 'U', 'Pembayaran Biaya Maret 2026', 'Pembayaran biaya operasional bulan Maret 2026. Diposting oleh Administrator.');

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_kasir_shift`
--

DROP TABLE IF EXISTS `mlite_kasir_shift`;
CREATE TABLE IF NOT EXISTS `mlite_kasir_shift` (
  `id_shift` int NOT NULL AUTO_INCREMENT,
  `user_id` varchar(64) NOT NULL,
  `waktu_buka` datetime NOT NULL,
  `waktu_tutup` datetime DEFAULT NULL,
  `kas_awal` decimal(14,2) DEFAULT '0.00',
  `kas_akhir` decimal(14,2) DEFAULT '0.00',
  `total_transaksi` decimal(14,2) DEFAULT '0.00',
  `selisih` decimal(14,2) DEFAULT '0.00',
  `keterangan` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_shift`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_kfa`
--

DROP TABLE IF EXISTS `mlite_kfa`;
CREATE TABLE IF NOT EXISTS `mlite_kfa` (
  `kode_kfa` varchar(50) NOT NULL,
  `nama_kfa` text,
  `kode_bahan` varchar(50) DEFAULT NULL,
  `nama_bahan` text,
  `numerator` varchar(10) DEFAULT NULL,
  `satuan_num` varchar(10) DEFAULT NULL,
  `denominator` varchar(10) DEFAULT NULL,
  `satuan_den` varchar(10) DEFAULT NULL,
  `nama_satuan_den` varchar(10) DEFAULT NULL,
  `kode_sediaan` varchar(50) DEFAULT NULL,
  `nama_sediaan` varchar(100) DEFAULT NULL,
  `type` enum('obat','alkes') NOT NULL DEFAULT 'obat',
  PRIMARY KEY (`kode_kfa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_ktpl`
--

DROP TABLE IF EXISTS `mlite_ktpl`;
CREATE TABLE IF NOT EXISTS `mlite_ktpl` (
  `kode_ktpl` varchar(50) NOT NULL,
  `nama_ktpl` text,
  `has_modifier` int NOT NULL DEFAULT '0',
  `modifier_count` int NOT NULL DEFAULT '0',
  `status` enum('Aktif','Tidak Aktif') NOT NULL DEFAULT 'Aktif',
  PRIMARY KEY (`kode_ktpl`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_login_attempts`
--

DROP TABLE IF EXISTS `mlite_login_attempts`;
CREATE TABLE IF NOT EXISTS `mlite_login_attempts` (
  `ip` text,
  `attempts` int NOT NULL,
  `expires` int NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `mlite_login_attempts`
--

INSERT INTO `mlite_login_attempts` (`ip`, `attempts`, `expires`) VALUES
('127.0.0.1', 0, 0);

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_loinc_lab`
--

DROP TABLE IF EXISTS `mlite_loinc_lab`;
CREATE TABLE IF NOT EXISTS `mlite_loinc_lab` (
  `No` int DEFAULT NULL,
  `Kategori` text,
  `NamaPemeriksaan` text,
  `PermintaanHasil` text,
  `Spesimen` text,
  `TipeHasilPemeriksaan` text,
  `Satuan` text,
  `MetodeAnalisis` text,
  `Code` varchar(20) NOT NULL,
  `Display` text,
  `Component` text,
  `Property` text,
  `Timing` text,
  `System` text,
  `Scale` text,
  `Method` text,
  `UnitOfMeasure` text,
  `CodeSystem` text,
  PRIMARY KEY (`Code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_loinc_radiologi`
--

DROP TABLE IF EXISTS `mlite_loinc_radiologi`;
CREATE TABLE IF NOT EXISTS `mlite_loinc_radiologi` (
  `No` text,
  `Kategori` text,
  `NamaPemeriksaan` text,
  `PermintaanHasil` text,
  `Code` varchar(100) NOT NULL,
  `Display` text,
  `Component` text,
  `Property` text,
  `Timing` text,
  `System` text,
  `Scale` text,
  `Method` text,
  `UnitOfMeasure` text,
  `CodeSystem` text,
  `BodySiteCode` text,
  `BodySiteDisplay` text,
  `BodySiteCodeSystem` text,
  PRIMARY KEY (`Code`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_mapping_snomed_icd`
--

DROP TABLE IF EXISTS `mlite_mapping_snomed_icd`;
CREATE TABLE IF NOT EXISTS `mlite_mapping_snomed_icd` (
  `id` int NOT NULL AUTO_INCREMENT,
  `no_rawat` varchar(20) NOT NULL,
  `kd_penyakit` varchar(10) NOT NULL,
  `snomed_concept_id` bigint NOT NULL,
  `snomed_term` varchar(255) NOT NULL,
  `status_penyakit` enum('Baru','Lama') DEFAULT 'Baru',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_mapping` (`no_rawat`,`kd_penyakit`,`snomed_concept_id`),
  KEY `no_rawat` (`no_rawat`),
  KEY `kd_penyakit` (`kd_penyakit`),
  KEY `snomed_concept_id` (`snomed_concept_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_mapping_snomed_icd9`
--

DROP TABLE IF EXISTS `mlite_mapping_snomed_icd9`;
CREATE TABLE IF NOT EXISTS `mlite_mapping_snomed_icd9` (
  `id` int NOT NULL AUTO_INCREMENT,
  `no_rawat` varchar(17) NOT NULL,
  `kd_tindakan` varchar(10) NOT NULL,
  `snomed_concept_id` varchar(50) NOT NULL,
  `snomed_term` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_mapping` (`no_rawat`,`kd_tindakan`,`snomed_concept_id`),
  KEY `no_rawat` (`no_rawat`),
  KEY `kd_tindakan` (`kd_tindakan`),
  KEY `snomed_concept_id` (`snomed_concept_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_mini_pacs_instance`
--

DROP TABLE IF EXISTS `mlite_mini_pacs_instance`;
CREATE TABLE IF NOT EXISTS `mlite_mini_pacs_instance` (
  `id` int NOT NULL AUTO_INCREMENT,
  `series_id` int NOT NULL,
  `sop_instance_uid` varchar(100) NOT NULL,
  `file_path` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sop_instance` (`sop_instance_uid`),
  KEY `fk_series_id` (`series_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_mini_pacs_instance_metadata`
--

DROP TABLE IF EXISTS `mlite_mini_pacs_instance_metadata`;
CREATE TABLE IF NOT EXISTS `mlite_mini_pacs_instance_metadata` (
  `id` int NOT NULL AUTO_INCREMENT,
  `instance_id` int NOT NULL,
  `tag` varchar(20) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `value` text,
  PRIMARY KEY (`id`),
  KEY `idx_instance_id` (`instance_id`),
  KEY `idx_tag` (`tag`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_mini_pacs_series`
--

DROP TABLE IF EXISTS `mlite_mini_pacs_series`;
CREATE TABLE IF NOT EXISTS `mlite_mini_pacs_series` (
  `id` int NOT NULL AUTO_INCREMENT,
  `study_id` int NOT NULL,
  `series_instance_uid` varchar(100) NOT NULL,
  `series_description` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_series_instance` (`series_instance_uid`),
  KEY `fk_study_id` (`study_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_mini_pacs_study`
--

DROP TABLE IF EXISTS `mlite_mini_pacs_study`;
CREATE TABLE IF NOT EXISTS `mlite_mini_pacs_study` (
  `id` int NOT NULL AUTO_INCREMENT,
  `no_rawat` varchar(17) NOT NULL,
  `study_instance_uid` varchar(100) NOT NULL,
  `study_date` datetime DEFAULT NULL,
  `modality` varchar(10) DEFAULT NULL,
  `description` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_study_instance` (`study_instance_uid`),
  KEY `fk_no_rawat` (`no_rawat`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_mini_pacs_worklist_status`
--

DROP TABLE IF EXISTS `mlite_mini_pacs_worklist_status`;
CREATE TABLE IF NOT EXISTS `mlite_mini_pacs_worklist_status` (
  `noorder` varchar(20) NOT NULL,
  `pulled_at` datetime DEFAULT NULL,
  `notified` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`noorder`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_modules`
--

DROP TABLE IF EXISTS `mlite_modules`;
CREATE TABLE IF NOT EXISTS `mlite_modules` (
  `id` int NOT NULL AUTO_INCREMENT,
  `dir` text,
  `sequence` text,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=55 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `mlite_modules`
--

INSERT INTO `mlite_modules` (`id`, `dir`, `sequence`) VALUES
(1, 'settings', '9'),
(2, 'dashboard', '0'),
(3, 'master', '1'),
(4, 'pasien', '2'),
(5, 'rawat_jalan', '3'),
(6, 'kasir_rawat_jalan', '4'),
(7, 'kepegawaian', '5'),
(8, 'farmasi', '6'),
(9, 'users', '8'),
(10, 'modules', '7'),
(11, 'wagateway', '10'),
(12, 'apotek_ralan', '11'),
(36, 'afm', '31'),
(14, 'igd', '13'),
(15, 'dokter_igd', '14'),
(16, 'laboratorium', '15'),
(17, 'radiologi', '16'),
(18, 'rawat_inap', '17'),
(19, 'apotek_ranap', '18'),
(20, 'dokter_ranap', '19'),
(21, 'kasir_rawat_inap', '20'),
(22, 'operasi', '21'),
(23, 'anjungan', '22'),
(24, 'api', '23'),
(25, 'jkn_mobile', '24'),
(26, 'vclaim', '25'),
(27, 'keuangan', '26'),
(28, 'manajemen', '27'),
(29, 'presensi', '28'),
(30, 'vedika', '29'),
(31, 'profil', '30'),
(32, 'orthanc', '31'),
(37, 'bpjs_emr', '32'),
(38, 'bridging_hfis', '33'),
(39, 'dokter_ralan', '34'),
(54, 'esignature', '48'),
(41, 'icare', '36'),
(42, 'inventaris', '37'),
(43, 'jasa_medis', '38'),
(44, 'laporan', '39'),
(45, 'veronisa', '40'),
(46, 'mlite_api_key', '41'),
(47, 'mlite_logs', '42'),
(48, 'penjualan', '43'),
(49, 'satu_sehat', '44'),
(50, 'sertisign', '45'),
(51, 'surat', '46'),
(52, 'utd', '47');

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_news`
--

DROP TABLE IF EXISTS `mlite_news`;
CREATE TABLE IF NOT EXISTS `mlite_news` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(225) NOT NULL,
  `slug` varchar(225) NOT NULL,
  `user_id` int NOT NULL,
  `content` text NOT NULL,
  `intro` text,
  `cover_photo` text,
  `status` int NOT NULL,
  `comments` int DEFAULT '1',
  `markdown` int DEFAULT '0',
  `published_at` int DEFAULT '0',
  `updated_at` int NOT NULL,
  `created_at` int NOT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_news_tags`
--

DROP TABLE IF EXISTS `mlite_news_tags`;
CREATE TABLE IF NOT EXISTS `mlite_news_tags` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(225) DEFAULT NULL,
  `slug` varchar(225) DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_news_tags_relationship`
--

DROP TABLE IF EXISTS `mlite_news_tags_relationship`;
CREATE TABLE IF NOT EXISTS `mlite_news_tags_relationship` (
  `news_id` int NOT NULL,
  `tag_id` int NOT NULL,
  KEY `mlite_news_tags_relationship_ibfk_1` (`news_id`) USING BTREE,
  KEY `tag_id` (`tag_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_notifications`
--

DROP TABLE IF EXISTS `mlite_notifications`;
CREATE TABLE IF NOT EXISTS `mlite_notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `judul` varchar(250) NOT NULL,
  `pesan` text NOT NULL,
  `tanggal` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `no_rkm_medis` varchar(255) NOT NULL,
  `status` varchar(250) NOT NULL DEFAULT 'unread',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_odontogram`
--

DROP TABLE IF EXISTS `mlite_odontogram`;
CREATE TABLE IF NOT EXISTS `mlite_odontogram` (
  `id` int NOT NULL AUTO_INCREMENT,
  `no_rkm_medis` text NOT NULL,
  `pemeriksaan` text,
  `kondisi` text,
  `catatan` text,
  `id_user` text NOT NULL,
  `tgl_input` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_ohis`
--

DROP TABLE IF EXISTS `mlite_ohis`;
CREATE TABLE IF NOT EXISTS `mlite_ohis` (
  `id` int NOT NULL AUTO_INCREMENT,
  `no_rkm_medis` text NOT NULL,
  `d_16` text,
  `d_11` text,
  `d_26` text,
  `d_36` text,
  `d_31` text,
  `d_46` text,
  `c_16` text,
  `c_11` text,
  `c_26` text,
  `c_36` text,
  `c_31` text,
  `c_46` text,
  `debris` text,
  `calculus` text,
  `nilai` text,
  `kriteria` text,
  `id_user` text NOT NULL,
  `tgl_input` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_pendaftaran_oral_diagnostic`
--

DROP TABLE IF EXISTS `mlite_pendaftaran_oral_diagnostic`;
CREATE TABLE IF NOT EXISTS `mlite_pendaftaran_oral_diagnostic` (
  `no_reg` varchar(8) DEFAULT NULL,
  `no_rawat` varchar(17) NOT NULL,
  `tgl_registrasi` date DEFAULT NULL,
  `jam_reg` time DEFAULT NULL,
  `kd_dokter` varchar(20) DEFAULT NULL,
  `no_rkm_medis` varchar(15) DEFAULT NULL,
  `kd_poli` char(5) DEFAULT NULL,
  `p_jawab` varchar(100) DEFAULT NULL,
  `almt_pj` varchar(200) DEFAULT NULL,
  `hubunganpj` varchar(20) DEFAULT NULL,
  `biaya_reg` double DEFAULT NULL,
  `stts` enum('Belum','Sudah','Batal','Berkas Diterima','Dirujuk','Meninggal','Dirawat','Pulang Paksa') DEFAULT NULL,
  `stts_daftar` enum('-','Lama','Baru') NOT NULL,
  `status_lanjut` enum('Ralan','Ranap') NOT NULL,
  `kd_pj` char(3) NOT NULL,
  `umurdaftar` int DEFAULT NULL,
  `sttsumur` enum('Th','Bl','Hr') DEFAULT NULL,
  `status_bayar` enum('Sudah Bayar','Belum Bayar') NOT NULL,
  `status_poli` enum('Lama','Baru') NOT NULL,
  PRIMARY KEY (`no_rawat`),
  KEY `no_rkm_medis` (`no_rkm_medis`),
  KEY `kd_poli` (`kd_poli`),
  KEY `kd_pj` (`kd_pj`),
  KEY `status_lanjut` (`status_lanjut`),
  KEY `kd_dokter` (`kd_dokter`),
  KEY `status_bayar` (`status_bayar`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_pengaduan`
--

DROP TABLE IF EXISTS `mlite_pengaduan`;
CREATE TABLE IF NOT EXISTS `mlite_pengaduan` (
  `id` varchar(15) NOT NULL,
  `tanggal` datetime NOT NULL,
  `no_rkm_medis` varchar(15) NOT NULL,
  `pesan` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `no_rkm_medis` (`no_rkm_medis`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_pengaduan_detail`
--

DROP TABLE IF EXISTS `mlite_pengaduan_detail`;
CREATE TABLE IF NOT EXISTS `mlite_pengaduan_detail` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pengaduan_id` varchar(15) NOT NULL,
  `tanggal` datetime NOT NULL,
  `no_rkm_medis` varchar(15) NOT NULL,
  `pesan` varchar(225) DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `pengaduan_detail_ibfk_1` (`pengaduan_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_penjualan`
--

DROP TABLE IF EXISTS `mlite_penjualan`;
CREATE TABLE IF NOT EXISTS `mlite_penjualan` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nama_pembeli` varchar(100) DEFAULT NULL,
  `alamat_pembeli` varchar(100) DEFAULT NULL,
  `nomor_telepon` varchar(50) DEFAULT NULL,
  `email` varchar(50) DEFAULT NULL,
  `tanggal` date NOT NULL,
  `jam` time NOT NULL,
  `id_user` varchar(50) NOT NULL,
  `keterangan` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `mlite_penjualan`
--

INSERT INTO `mlite_penjualan` (`id`, `nama_pembeli`, `alamat_pembeli`, `nomor_telepon`, `email`, `tanggal`, `jam`, `id_user`, `keterangan`) VALUES
(1, 'H. Abdullah', 'Jl. Matraman Raya No. 15', '08129870001', 'abdullah@gmail.com', '2026-09-08', '08:45:00', 'admin', 'Pembelian Bebas Obat Flu'),
(2, 'Ibu Maya Safitri', 'Jl. Cikini Raya No. 40', '08129870002', 'mayasafitri@gmail.com', '2026-09-08', '11:15:00', 'admin', 'Pembelian Bebas Vitamin & Suplemen');

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_penjualan_barang`
--

DROP TABLE IF EXISTS `mlite_penjualan_barang`;
CREATE TABLE IF NOT EXISTS `mlite_penjualan_barang` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nama_barang` varchar(100) DEFAULT NULL,
  `stok` varchar(100) DEFAULT NULL,
  `harga` varchar(100) DEFAULT NULL,
  `keterangan` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_penjualan_billing`
--

DROP TABLE IF EXISTS `mlite_penjualan_billing`;
CREATE TABLE IF NOT EXISTS `mlite_penjualan_billing` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_penjualan` int NOT NULL,
  `jumlah_total` int NOT NULL,
  `potongan` int DEFAULT NULL,
  `jumlah_harus_bayar` int NOT NULL,
  `jumlah_bayar` int NOT NULL,
  `tanggal` date NOT NULL,
  `jam` time NOT NULL,
  `id_user` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `mlite_penjualan_billing`
--

INSERT INTO `mlite_penjualan_billing` (`id`, `id_penjualan`, `jumlah_total`, `potongan`, `jumlah_harus_bayar`, `jumlah_bayar`, `tanggal`, `jam`, `id_user`) VALUES
(1, 1, 11000, 0, 11000, 11000, '2026-09-08', '08:45:00', 'admin'),
(2, 2, 24000, 0, 24000, 24000, '2026-09-08', '11:15:00', 'admin');

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_penjualan_detail`
--

DROP TABLE IF EXISTS `mlite_penjualan_detail`;
CREATE TABLE IF NOT EXISTS `mlite_penjualan_detail` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_penjualan` int NOT NULL,
  `id_barang` varchar(100) NOT NULL,
  `nama_barang` varchar(100) NOT NULL,
  `harga` int NOT NULL,
  `jumlah` int NOT NULL,
  `harga_total` int NOT NULL,
  `tanggal` date NOT NULL,
  `jam` time NOT NULL,
  `id_user` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `mlite_penjualan_detail`
--

INSERT INTO `mlite_penjualan_detail` (`id`, `id_penjualan`, `id_barang`, `nama_barang`, `harga`, `jumlah`, `harga_total`, `tanggal`, `jam`, `id_user`) VALUES
(1, 1, 'OB001', 'Paracetamol 500 mg Tablet', 600, 10, 6000, '2026-09-08', '08:45:00', 'admin'),
(2, 1, 'OB008', 'Antasida Doen Tablet Kunyah', 500, 10, 5000, '2026-09-08', '08:45:00', 'admin'),
(3, 2, 'OB012', 'Vitamin C 500 mg Tablet', 650, 20, 13000, '2026-09-08', '11:15:00', 'admin'),
(4, 2, 'OB013', 'Vitamin B Complex Tablet', 550, 20, 11000, '2026-09-08', '11:15:00', 'admin');

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_query_logs`
--

DROP TABLE IF EXISTS `mlite_query_logs`;
CREATE TABLE IF NOT EXISTS `mlite_query_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sql_text` text NOT NULL,
  `bindings` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `error_message` text,
  `username` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_rekening`
--

DROP TABLE IF EXISTS `mlite_rekening`;
CREATE TABLE IF NOT EXISTS `mlite_rekening` (
  `kd_rek` varchar(15) NOT NULL DEFAULT '',
  `nm_rek` varchar(100) DEFAULT NULL,
  `tipe` enum('N','M','R') DEFAULT NULL,
  `balance` enum('D','K') DEFAULT NULL,
  `level` enum('0','1') DEFAULT NULL,
  PRIMARY KEY (`kd_rek`),
  KEY `nm_rek` (`nm_rek`),
  KEY `tipe` (`tipe`),
  KEY `balance` (`balance`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `mlite_rekening`
--

INSERT INTO `mlite_rekening` (`kd_rek`, `nm_rek`, `tipe`, `balance`, `level`) VALUES
('1101', 'Kas Umum', 'N', 'D', '1'),
('1102', 'Kas Kasir Rawat Jalan', 'N', 'D', '1'),
('1103', 'Kas Kasir Rawat Inap', 'N', 'D', '1'),
('1104', 'Kas Farmasi', 'N', 'D', '1'),
('1105', 'Kas Kecil', 'N', 'D', '1'),
('11101', 'Kas Kasir Pelayanan', 'N', 'D', '1'),
('11102', 'Kas Bank Operasional BCA', 'N', 'D', '1'),
('11301', 'Piutang Pasien BPJS', 'N', 'D', '1'),
('11302', 'Piutang Pasien Asuransi Swasta', 'N', 'D', '1'),
('11401', 'Persediaan Obat & BMHP', 'N', 'D', '1'),
('1201', 'Bank BRI', 'N', 'D', '1'),
('1301', 'Piutang BPJS', 'N', 'D', '1'),
('1302', 'Piutang Pasien Umum', 'N', 'D', '1'),
('1401', 'Persediaan Obat & BHP', 'N', 'D', '1'),
('1601', 'Gedung & Bangunan', 'N', 'D', '1'),
('1701', 'Peralatan Medis', 'N', 'D', '1'),
('1801', 'Kendaraan', 'N', 'D', '1'),
('1901', 'Inventaris Kantor', 'N', 'D', '1'),
('2101', 'Hutang Usaha', 'N', 'K', '1'),
('2102', 'Hutang Gaji', 'N', 'K', '1'),
('21101', 'Hutang Usaha Suplier Farmasi', 'N', 'K', '1'),
('2201', 'Hutang Bank', 'N', 'K', '1'),
('3101', 'Modal Disetor', 'M', 'K', '1'),
('3201', 'Laba Ditahan', 'M', 'K', '1'),
('4101', 'Pendapatan Rawat Jalan', 'R', 'K', '1'),
('4102', 'Pendapatan Rawat Inap', 'R', 'K', '1'),
('4103', 'Pendapatan Obat & BHP', 'R', 'K', '1'),
('4104', 'Pendapatan Laboratorium', 'R', 'K', '1'),
('4105', 'Pendapatan Radiologi', 'R', 'K', '1'),
('41101', 'Pendapatan Rawat Jalan', 'R', 'K', '1'),
('41102', 'Pendapatan Obat & Farmasi', 'R', 'K', '1'),
('41103', 'Pendapatan Tindakan Medis', 'R', 'K', '1'),
('4201', 'Pendapatan Lain-lain', 'R', 'K', '1'),
('5101', 'Beban Gaji Dokter', 'R', 'D', '1'),
('5102', 'Beban Gaji Paramedis', 'R', 'D', '1'),
('5103', 'Beban Gaji Karyawan', 'R', 'D', '1'),
('51101', 'Beban Pokok Penjualan Obat', 'R', 'D', '1'),
('51201', 'Beban Gaji & Jasa Medis', 'R', 'D', '1'),
('5201', 'Beban Obat & BHP', 'R', 'D', '1'),
('5301', 'Beban Listrik', 'R', 'D', '1'),
('5302', 'Beban Air & Kebersihan', 'R', 'D', '1'),
('5401', 'Beban Penyusutan', 'R', 'D', '1'),
('5501', 'Beban Administrasi Umum', 'R', 'D', '1');

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_rekeningtahun`
--

DROP TABLE IF EXISTS `mlite_rekeningtahun`;
CREATE TABLE IF NOT EXISTS `mlite_rekeningtahun` (
  `thn` year NOT NULL,
  `kd_rek` varchar(15) NOT NULL DEFAULT '',
  `saldo_awal` double NOT NULL,
  PRIMARY KEY (`thn`,`kd_rek`),
  KEY `kd_rek` (`kd_rek`),
  KEY `saldo_awal` (`saldo_awal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `mlite_rekeningtahun`
--

INSERT INTO `mlite_rekeningtahun` (`thn`, `kd_rek`, `saldo_awal`) VALUES
('2025', '1302', 15000000),
('2026', '1302', 15000000),
('2025', '2102', 20000000),
('2026', '2102', 20000000),
('2025', '2101', 25000000),
('2026', '2101', 25000000),
('2025', '1101', 50000000),
('2025', '1901', 50000000),
('2026', '1901', 50000000),
('2025', '1301', 75000000),
('2026', '1301', 75000000),
('2025', '1401', 80000000),
('2026', '1401', 80000000),
('2026', '1801', 147000000),
('2025', '1801', 150000000),
('2025', '1201', 200000000),
('2025', '2201', 200000000),
('2026', '1201', 200000000),
('2026', '2201', 200000000),
('2026', '1101', 230000000),
('2026', '1701', 293000000),
('2025', '1701', 300000000),
('2025', '3201', 375000000),
('2026', '1601', 495000000),
('2025', '1601', 500000000),
('2026', '3201', 540000000),
('2025', '3101', 800000000),
('2026', '3101', 800000000);

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_remember_me`
--

DROP TABLE IF EXISTS `mlite_remember_me`;
CREATE TABLE IF NOT EXISTS `mlite_remember_me` (
  `id` int NOT NULL AUTO_INCREMENT,
  `token` text,
  `user_id` int NOT NULL,
  `expiry` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `mlite_remember_me_ibfk_1` (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_rujukan_internal_poli`
--

DROP TABLE IF EXISTS `mlite_rujukan_internal_poli`;
CREATE TABLE IF NOT EXISTS `mlite_rujukan_internal_poli` (
  `no_rawat` varchar(17) NOT NULL,
  `kd_dokter` varchar(20) NOT NULL,
  `kd_poli` varchar(5) DEFAULT NULL,
  `isi_rujukan` text,
  `jawab_rujukan` text,
  PRIMARY KEY (`no_rawat`,`kd_dokter`) USING BTREE,
  KEY `kd_dokter` (`kd_dokter`) USING BTREE,
  KEY `kd_poli` (`kd_poli`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_satu_sehat_departemen`
--

DROP TABLE IF EXISTS `mlite_satu_sehat_departemen`;
CREATE TABLE IF NOT EXISTS `mlite_satu_sehat_departemen` (
  `dep_id` char(4) NOT NULL,
  `id_organisasi_satusehat` varchar(40) DEFAULT NULL,
  PRIMARY KEY (`dep_id`),
  UNIQUE KEY `id_organisasi_satusehat` (`id_organisasi_satusehat`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `mlite_satu_sehat_departemen`
--

INSERT INTO `mlite_satu_sehat_departemen` (`dep_id`, `id_organisasi_satusehat`) VALUES
('DEP1', 'org-100023456');

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_satu_sehat_lokasi`
--

DROP TABLE IF EXISTS `mlite_satu_sehat_lokasi`;
CREATE TABLE IF NOT EXISTS `mlite_satu_sehat_lokasi` (
  `kode` char(5) NOT NULL,
  `lokasi` varchar(40) DEFAULT NULL,
  `id_organisasi_satusehat` varchar(40) DEFAULT NULL,
  `id_lokasi_satusehat` varchar(40) DEFAULT NULL,
  `longitude` varchar(30) NOT NULL,
  `latitude` varchar(30) NOT NULL,
  `altitude` varchar(30) NOT NULL,
  PRIMARY KEY (`kode`),
  UNIQUE KEY `id_lokasi_satusehat` (`id_lokasi_satusehat`),
  KEY `id_organisasi_satusehat` (`id_organisasi_satusehat`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `mlite_satu_sehat_lokasi`
--

INSERT INTO `mlite_satu_sehat_lokasi` (`kode`, `lokasi`, `id_organisasi_satusehat`, `id_lokasi_satusehat`, `longitude`, `latitude`, `altitude`) VALUES
('AP01', 'Apotek Pelayanan', 'org-100023456', 'loc-apotek-001', '106.827153', '-6.175392', '10'),
('POL01', 'Ruang Poli Umum', 'org-100023456', 'loc-poli-umum-001', '106.827153', '-6.175392', '10'),
('POL02', 'Ruang Poli Gigi & Mulut', 'org-100023456', 'loc-poli-gigi-002', '106.827153', '-6.175392', '10'),
('POL03', 'Ruang Poli Penyakit Dalam', 'org-100023456', 'loc-poli-dalam-003', '106.827153', '-6.175392', '10'),
('POL04', 'Ruang Poli Kebidanan & Kandungan', 'org-100023456', 'loc-poli-obgyn-004', '106.827153', '-6.175392', '10'),
('POL05', 'Ruang Poli Anak', 'org-100023456', 'loc-poli-anak-005', '106.827153', '-6.175392', '10');

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_satu_sehat_mapping_lab`
--

DROP TABLE IF EXISTS `mlite_satu_sehat_mapping_lab`;
CREATE TABLE IF NOT EXISTS `mlite_satu_sehat_mapping_lab` (
  `id_template` int NOT NULL,
  `kd_jenis_prw` varchar(15) DEFAULT NULL,
  `code` varchar(15) DEFAULT NULL,
  `system` varchar(100) NOT NULL,
  `display` varchar(80) DEFAULT NULL,
  `sampel_code` varchar(15) NOT NULL,
  `sampel_system` varchar(100) NOT NULL,
  `sampel_display` varchar(80) NOT NULL,
  PRIMARY KEY (`id_template`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_satu_sehat_mapping_obat`
--

DROP TABLE IF EXISTS `mlite_satu_sehat_mapping_obat`;
CREATE TABLE IF NOT EXISTS `mlite_satu_sehat_mapping_obat` (
  `kode_brng` varchar(15) NOT NULL DEFAULT '',
  `kode_kfa` varchar(50) DEFAULT NULL,
  `nama_kfa` varchar(100) DEFAULT NULL,
  `kode_bahan` varchar(50) DEFAULT NULL,
  `nama_bahan` varchar(100) DEFAULT NULL,
  `numerator` varchar(10) DEFAULT NULL,
  `satuan_num` varchar(10) DEFAULT NULL,
  `denominator` varchar(10) DEFAULT NULL,
  `satuan_den` varchar(10) DEFAULT NULL,
  `nama_satuan_den` varchar(10) DEFAULT NULL,
  `kode_sediaan` varchar(50) DEFAULT NULL,
  `nama_sediaan` varchar(100) DEFAULT NULL,
  `kode_route` varchar(10) DEFAULT NULL,
  `nama_route` varchar(50) DEFAULT NULL,
  `type` enum('obat','vaksin') NOT NULL,
  `id_medication` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`kode_brng`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `mlite_satu_sehat_mapping_obat`
--

INSERT INTO `mlite_satu_sehat_mapping_obat` (`kode_brng`, `kode_kfa`, `nama_kfa`, `kode_bahan`, `nama_bahan`, `numerator`, `satuan_num`, `denominator`, `satuan_den`, `nama_satuan_den`, `kode_sediaan`, `nama_sediaan`, `kode_route`, `nama_route`, `type`, `id_medication`) VALUES
('OB001', '93001021', 'Paracetamol 500 mg Tablet', 'Paracetamol', 'Paracetamol', '500', 'mg', '1', 'TAB', 'Tablet', 'BS066', 'Tablet', 'RT001', 'Oral', 'obat', 'med-paracetamol-500'),
('OB002', '93002045', 'Amoxicillin 500 mg Kaplet', 'Amoxicillin', 'Amoxicillin Trihydrate', '500', 'mg', '1', 'KAP', 'Kaplet', 'BS018', 'Kaplet', 'RT001', 'Oral', 'obat', 'med-amoxicillin-500'),
('OB003', '93003012', 'Amlodipine 10 mg Tablet', 'Amlodipine', 'Amlodipine Besylate', '10', 'mg', '1', 'TAB', 'Tablet', 'BS066', 'Tablet', 'RT001', 'Oral', 'obat', 'med-amlodipine-10'),
('OB005', '93005088', 'Metformin HCl 500 mg Tablet', 'Metformin', 'Metformin Hydrochloride', '500', 'mg', '1', 'TAB', 'Tablet', 'BS066', 'Tablet', 'RT001', 'Oral', 'obat', 'med-metformin-500');

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_satu_sehat_mapping_praktisi`
--

DROP TABLE IF EXISTS `mlite_satu_sehat_mapping_praktisi`;
CREATE TABLE IF NOT EXISTS `mlite_satu_sehat_mapping_praktisi` (
  `practitioner_id` varchar(40) NOT NULL,
  `kd_dokter` varchar(20) NOT NULL,
  `jenis_praktisi` varchar(20) NOT NULL,
  PRIMARY KEY (`practitioner_id`),
  KEY `kd_dokter` (`kd_dokter`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `mlite_satu_sehat_mapping_praktisi`
--

INSERT INTO `mlite_satu_sehat_mapping_praktisi` (`practitioner_id`, `kd_dokter`, `jenis_praktisi`) VALUES
('10001234561', 'DR001', 'Dokter'),
('10001234562', 'DR002', 'Dokter'),
('10001234563', 'DR003', 'Dokter Gigi'),
('10001234564', 'DR004', 'Dokter'),
('10001234565', 'DR005', 'Dokter');

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_satu_sehat_mapping_rad`
--

DROP TABLE IF EXISTS `mlite_satu_sehat_mapping_rad`;
CREATE TABLE IF NOT EXISTS `mlite_satu_sehat_mapping_rad` (
  `kd_jenis_prw` varchar(15) NOT NULL,
  `code` varchar(15) DEFAULT NULL,
  `system` varchar(100) NOT NULL,
  `display` varchar(80) DEFAULT NULL,
  `sampel_code` varchar(15) NOT NULL,
  `sampel_system` varchar(100) NOT NULL,
  `sampel_display` varchar(80) NOT NULL,
  PRIMARY KEY (`kd_jenis_prw`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_satu_sehat_response`
--

DROP TABLE IF EXISTS `mlite_satu_sehat_response`;
CREATE TABLE IF NOT EXISTS `mlite_satu_sehat_response` (
  `no_rawat` varchar(17) NOT NULL,
  `id_encounter` varchar(50) DEFAULT NULL,
  `id_condition` varchar(50) DEFAULT NULL,
  `id_observation_ttvnadi` varchar(50) DEFAULT NULL,
  `id_observation_ttvrespirasi` varchar(50) DEFAULT NULL,
  `id_observation_ttvsuhu` varchar(50) DEFAULT NULL,
  `id_observation_ttvspo2` varchar(50) DEFAULT NULL,
  `id_observation_ttvgcs` varchar(50) DEFAULT NULL,
  `id_observation_ttvtinggi` varchar(50) DEFAULT NULL,
  `id_observation_ttvberat` varchar(50) DEFAULT NULL,
  `id_observation_ttvperut` varchar(50) DEFAULT NULL,
  `id_observation_ttvtensi` varchar(50) DEFAULT NULL,
  `id_observation_ttvkesadaran` varchar(50) DEFAULT NULL,
  `id_procedure` varchar(50) DEFAULT NULL,
  `id_clinical_impression` varchar(50) DEFAULT NULL,
  `id_composition` varchar(50) DEFAULT NULL,
  `id_immunization` varchar(50) DEFAULT NULL,
  `id_medication_request` varchar(50) DEFAULT NULL,
  `id_medication_dispense` varchar(50) DEFAULT NULL,
  `id_medication_statement` varchar(50) DEFAULT NULL,
  `id_rad_request` varchar(50) DEFAULT NULL,
  `id_rad_specimen` varchar(50) DEFAULT NULL,
  `id_rad_observation` varchar(50) DEFAULT NULL,
  `id_rad_diagnostic` varchar(50) DEFAULT NULL,
  `id_imaging_study` varchar(50) DEFAULT NULL,
  `id_lab_pk_request` varchar(50) DEFAULT NULL,
  `id_lab_pk_specimen` varchar(50) DEFAULT NULL,
  `id_lab_pk_observation` varchar(50) DEFAULT NULL,
  `id_lab_pk_diagnostic` varchar(50) DEFAULT NULL,
  `id_lab_pa_request` varchar(50) DEFAULT NULL,
  `id_lab_pa_specimen` varchar(50) DEFAULT NULL,
  `id_lab_pa_observation` varchar(50) DEFAULT NULL,
  `id_lab_pa_diagnostic` varchar(50) DEFAULT NULL,
  `id_lab_mb_request` varchar(50) DEFAULT NULL,
  `id_lab_mb_specimen` varchar(50) DEFAULT NULL,
  `id_lab_mb_observation` varchar(50) DEFAULT NULL,
  `id_lab_mb_diagnostic` varchar(50) DEFAULT NULL,
  `id_careplan` varchar(50) DEFAULT NULL,
  `id_allergy` varchar(50) DEFAULT NULL,
  `id_questionnaire` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`no_rawat`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_sertisign_webhook`
--

DROP TABLE IF EXISTS `mlite_sertisign_webhook`;
CREATE TABLE IF NOT EXISTS `mlite_sertisign_webhook` (
  `id` int NOT NULL AUTO_INCREMENT,
  `transaction_id` varchar(100) NOT NULL,
  `status` varchar(50) NOT NULL,
  `document_url` varchar(255) NOT NULL,
  `payload` text NOT NULL,
  `received_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `transaction_idx` (`transaction_id`),
  KEY `status_idx` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_settings`
--

DROP TABLE IF EXISTS `mlite_settings`;
CREATE TABLE IF NOT EXISTS `mlite_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `module` varchar(100) NOT NULL,
  `field` varchar(100) NOT NULL,
  `value` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `module` (`module`,`field`)
) ENGINE=MyISAM AUTO_INCREMENT=228 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `mlite_settings`
--

INSERT INTO `mlite_settings` (`id`, `module`, `field`, `value`) VALUES
(1, 'settings', 'logo', 'uploads/settings/logo.png'),
(2, 'settings', 'nama_instansi', 'mLITE Indonesia'),
(3, 'settings', 'alamat', 'Jl. Perintis Kemerdekaan 45'),
(4, 'settings', 'kota', 'Barabai'),
(5, 'settings', 'propinsi', 'Kalimantan Selatan'),
(6, 'settings', 'nomor_telepon', '0812345678'),
(7, 'settings', 'email', 'info@mlite.id'),
(8, 'settings', 'website', 'https://mlite.id'),
(9, 'settings', 'ppk_bpjs', '-'),
(10, 'settings', 'footer', 'Copyright {?=date(\"Y\")?} &copy; by drg. F. Basoro. All rights reserved.'),
(11, 'settings', 'homepage', 'main'),
(12, 'settings', 'wallpaper', 'uploads/settings/wallpaper.jpg'),
(13, 'settings', 'text_color', '#44813e'),
(14, 'settings', 'igd', 'IGDK'),
(15, 'settings', 'laboratorium', '-'),
(16, 'settings', 'pj_laboratorium', 'DR001'),
(17, 'settings', 'radiologi', '-'),
(18, 'settings', 'pj_radiologi', 'DR001'),
(19, 'settings', 'dokter_ralan_per_dokter', 'false'),
(20, 'settings', 'cekstatusbayar', 'false'),
(21, 'settings', 'ceklimit', 'false'),
(22, 'settings', 'responsivevoice', 'false'),
(23, 'settings', 'notif_presensi', 'true'),
(24, 'settings', 'BpjsApiUrl', 'https://apijkn-dev.bpjs-kesehatan.go.id/vclaim-rest-dev/'),
(25, 'settings', 'BpjsConsID', '-'),
(26, 'settings', 'BpjsSecretKey', '-'),
(27, 'settings', 'BpjsUserKey', '-'),
(28, 'settings', 'timezone', 'Asia/Makassar'),
(29, 'settings', 'theme', 'default'),
(30, 'settings', 'theme_admin', 'mlite'),
(31, 'settings', 'admin_mode', 'complex'),
(32, 'settings', 'input_kasir', 'tidak'),
(33, 'settings', 'editor', 'wysiwyg'),
(34, 'settings', 'version', '6.3.4'),
(35, 'settings', 'update_check', ''),
(36, 'settings', 'update_changelog', ''),
(37, 'settings', 'update_version', '0'),
(38, 'settings', 'license', ''),
(39, 'farmasi', 'deporalan', '-'),
(40, 'farmasi', 'igd', '-'),
(41, 'farmasi', 'deporanap', '-'),
(42, 'farmasi', 'gudang', '-'),
(43, 'wagateway', 'server', 'https://mlite.id'),
(44, 'wagateway', 'token', '-'),
(45, 'wagateway', 'phonenumber', '-'),
(46, 'anjungan', 'display_poli', ''),
(47, 'anjungan', 'carabayar', ''),
(48, 'anjungan', 'antrian_loket', '1'),
(49, 'anjungan', 'antrian_cs', '2'),
(50, 'anjungan', 'antrian_apotek', '3'),
(51, 'anjungan', 'panggil_loket', '1'),
(52, 'anjungan', 'panggil_loket_nomor', '1'),
(53, 'anjungan', 'panggil_cs', '1'),
(54, 'anjungan', 'panggil_cs_nomor', '1'),
(55, 'anjungan', 'panggil_apotek', '1'),
(56, 'anjungan', 'panggil_apotek_nomor', '1'),
(57, 'anjungan', 'text_anjungan', 'Running text anjungan pasien mandiri.....'),
(58, 'anjungan', 'text_loket', 'Running text display antrian loket.....'),
(59, 'anjungan', 'text_poli', 'Running text display antrian poliklinik.....'),
(60, 'anjungan', 'text_laboratorium', 'Running text display antrian laboratorium.....'),
(61, 'anjungan', 'text_apotek', 'Running text display antrian apotek.....'),
(62, 'anjungan', 'text_farmasi', 'Running text display antrian farmasi.....'),
(63, 'anjungan', 'vidio', 'PLFtgYUkJmfyx7rESNFuRDUmhtkzSeFPQo'),
(64, 'api', 'apam_key', 'qtbexUAxzqO3M8dCOo2vDMFvgYjdUEdMLVo341'),
(65, 'api', 'apam_status_daftar', 'Terdaftar'),
(66, 'api', 'apam_status_dilayani', 'Anda siap dilayani'),
(67, 'api', 'apam_webappsurl', 'http://localhost/webapps/'),
(68, 'api', 'apam_normpetugas', '000001,000002'),
(69, 'api', 'apam_limit', '2'),
(70, 'api', 'apam_smtp_host', 'ssl://smtp.gmail.com'),
(71, 'api', 'apam_smtp_port', '465'),
(72, 'api', 'apam_smtp_username', ''),
(73, 'api', 'apam_smtp_password', ''),
(74, 'api', 'apam_kdpj', ''),
(75, 'api', 'apam_kdprop', ''),
(76, 'api', 'apam_kdkab', ''),
(77, 'api', 'apam_kdkec', ''),
(78, 'api', 'duitku_merchantCode', ''),
(79, 'api', 'duitku_merchantKey', ''),
(80, 'api', 'duitku_paymentAmount', ''),
(81, 'api', 'duitku_paymentMethod', ''),
(82, 'api', 'duitku_productDetails', ''),
(83, 'api', 'duitku_expiryPeriod', ''),
(84, 'api', 'duitku_kdpj', ''),
(85, 'api', 'berkasdigital_key', 'qtbexUAxzqO3M8dCOo2vDMFvgYjdUEdMLVo341'),
(86, 'jkn_mobile', 'x_username', 'jkn'),
(87, 'jkn_mobile', 'x_password', 'mobile'),
(88, 'jkn_mobile', 'header_token', 'X-Token'),
(89, 'jkn_mobile', 'header_username', 'X-Username'),
(90, 'jkn_mobile', 'header_password', 'X-Password'),
(91, 'jkn_mobile', 'BpjsConsID', ''),
(92, 'jkn_mobile', 'BpjsSecretKey', ''),
(93, 'jkn_mobile', 'BpjsUserKey', ''),
(94, 'jkn_mobile', 'BpjsAntrianUrl', 'https://apijkn-dev.bpjs-kesehatan.go.id/antreanrs_dev/'),
(95, 'jkn_mobile', 'kd_pj_bpjs', ''),
(96, 'jkn_mobile', 'exclude_taskid', ''),
(97, 'jkn_mobile', 'display', ''),
(98, 'jkn_mobile', 'kdprop', '1'),
(99, 'jkn_mobile', 'kdkab', '1'),
(100, 'jkn_mobile', 'kdkec', '1'),
(101, 'jkn_mobile', 'kdkel', '1'),
(102, 'jkn_mobile', 'perusahaan_pasien', ''),
(103, 'jkn_mobile', 'suku_bangsa', ''),
(104, 'jkn_mobile', 'bahasa_pasien', ''),
(105, 'jkn_mobile', 'cacat_fisik', ''),
(106, 'keuangan', 'jurnal_kasir', '0'),
(107, 'keuangan', 'akun_kredit_pendaftaran', '4101'),
(108, 'keuangan', 'akun_kredit_tindakan', '4102'),
(109, 'keuangan', 'akun_kredit_obat_bhp', '4103'),
(110, 'keuangan', 'akun_kredit_laboratorium', '4104'),
(111, 'keuangan', 'akun_kredit_radiologi', '4105'),
(112, 'keuangan', 'akun_kredit_tambahan_biaya', '4201'),
(113, 'manajemen', 'penjab_umum', 'UMU'),
(114, 'manajemen', 'penjab_bpjs', 'BPJ'),
(115, 'presensi', 'lat', '-2.58'),
(116, 'presensi', 'lon', '115.37'),
(117, 'presensi', 'distance', '2'),
(118, 'presensi', 'helloworld', 'Jangan Lupa Bahagia; \nCara untuk memulai adalah berhenti berbicara dan mulai melakukan; \nWaktu yang hilang tidak akan pernah ditemukan lagi; \nKamu bisa membodohi semua orang, tetapi kamu tidak bisa membohongi pikiranmu; \nIni bukan tentang ide. Ini tentang mewujudkan ide; \nBekerja bukan hanya untuk mencari materi. Bekerja merupakan manfaat bagi banyak orang'),
(119, 'vedika', 'carabayar', ''),
(120, 'vedika', 'sep', ''),
(121, 'vedika', 'skdp', ''),
(122, 'vedika', 'operasi', ''),
(123, 'vedika', 'individual', ''),
(124, 'vedika', 'billing', 'mlite'),
(125, 'vedika', 'periode', '2023-01'),
(126, 'vedika', 'verifikasi', '2023-01'),
(127, 'vedika', 'inacbgs_prosedur_bedah', ''),
(128, 'vedika', 'inacbgs_prosedur_non_bedah', ''),
(129, 'vedika', 'inacbgs_konsultasi', ''),
(130, 'vedika', 'inacbgs_tenaga_ahli', ''),
(131, 'vedika', 'inacbgs_keperawatan', ''),
(132, 'vedika', 'inacbgs_penunjang', ''),
(133, 'vedika', 'inacbgs_pelayanan_darah', ''),
(134, 'vedika', 'inacbgs_rehabilitasi', ''),
(135, 'vedika', 'inacbgs_rawat_intensif', ''),
(136, 'vedika', 'eklaim_url', ''),
(137, 'vedika', 'eklaim_key', ''),
(138, 'vedika', 'eklaim_kelasrs', 'CP'),
(139, 'vedika', 'eklaim_payor_id', '3'),
(140, 'vedika', 'eklaim_payor_cd', 'JKN'),
(141, 'vedika', 'eklaim_cob_cd', '#'),
(142, 'orthanc', 'server', 'http://localhost:8042'),
(143, 'orthanc', 'username', 'orthanc'),
(144, 'orthanc', 'password', 'orthanc'),
(166, 'afm', 'username_finger', ''),
(148, 'jkn_mobile', 'kirimantrian', 'tidak'),
(149, 'settings', 'keamanan', 'tidak'),
(165, 'afm', 'afm_token', 'fc4eba4aa3ea79a7bba3070cba848696'),
(151, 'settings', 'websocket', 'tidak'),
(152, 'settings', 'websocket_proxy', ''),
(153, 'settings', 'username_fp', ''),
(154, 'settings', 'password_fp', ''),
(155, 'settings', 'username_frista', ''),
(156, 'settings', 'password_frista', ''),
(157, 'settings', 'billing_obat', 'false'),
(158, 'settings', 'prefix_surat', 'RS'),
(159, 'farmasi', 'keterangan_etiket', ''),
(160, 'settings', 'set_nomor_surat', '000'),
(161, 'settings', 'password_expire', 'tidak'),
(162, 'farmasi', 'embalase', '0'),
(163, 'farmasi', 'tuslah', '0'),
(164, 'settings', 'log_query', 'tidak'),
(167, 'afm', 'password_finger', ''),
(168, 'afm', 'x_header_token', 'X-Header-Token'),
(169, 'bpjs_emr', 'consid', ''),
(170, 'bpjs_emr', 'secretkey', ''),
(171, 'bpjs_emr', 'userkey', ''),
(172, 'bpjs_emr', 'koders', ''),
(173, 'bpjs_emr', 'kode_kemkes', ''),
(174, 'bpjs_emr', 'kodepos', ''),
(175, 'bpjs_emr', 'baseurl', 'https://apijkn-dev.bpjs-kesehatan.go.id/erekammedis_dev/'),
(176, 'dokter_ralan', 'set_sudah', 'tidak'),
(177, 'esignature', 'kode_berkasdigital', ''),
(178, 'icare', 'url', 'https://apijkn.bpjs-kesehatan.go.id/wsihs/api/rs/validate'),
(179, 'icare', 'consid', '16524'),
(180, 'icare', 'secretkey', '3oLC380A8A'),
(181, 'icare', 'userkey', '745459a17eee54065c0ef4f619d33487'),
(182, 'icare', 'urlPCare', 'https://apijkn.bpjs-kesehatan.go.id/pcare-rest'),
(183, 'icare', 'usernameICare', '0169B012'),
(184, 'icare', 'passwordICare', 'BPJSnonadm480#'),
(185, 'satu_sehat', 'organizationid', ''),
(186, 'satu_sehat', 'clientid', ''),
(187, 'satu_sehat', 'secretkey', ''),
(188, 'satu_sehat', 'authurl', 'https://api-satusehat-dev.dto.kemkes.go.id/oauth2/v1'),
(189, 'satu_sehat', 'fhirurl', 'https://api-satusehat-dev.dto.kemkes.go.id/fhir-r4/v1'),
(190, 'satu_sehat', 'kelurahan', ''),
(191, 'satu_sehat', 'kecamatan', ''),
(192, 'satu_sehat', 'kabupaten', ''),
(193, 'satu_sehat', 'propinsi', ''),
(194, 'satu_sehat', 'kodepos', ''),
(195, 'satu_sehat', 'longitude', ''),
(196, 'satu_sehat', 'latitude', ''),
(197, 'satu_sehat', 'zonawaktu', 'WIB'),
(198, 'satu_sehat', 'farmasi', ''),
(199, 'satu_sehat', 'laboratorium', ''),
(200, 'satu_sehat', 'radiologi', ''),
(201, 'satu_sehat', 'praktisiapotek', ''),
(202, 'satu_sehat', 'praktisilab', ''),
(203, 'satu_sehat', 'praktisirad', ''),
(205, 'sertisign', 'api_host', 'https://api-stag.sertisign.id/'),
(206, 'sertisign', 'api_key', ''),
(207, 'veronisa', 'username', ''),
(208, 'veronisa', 'password', ''),
(209, 'veronisa', 'obat_kronis', ''),
(210, 'veronisa', 'cons_id', ''),
(211, 'veronisa', 'kode_ppk', ''),
(212, 'veronisa', 'user_key', ''),
(213, 'veronisa', 'secret_key', ''),
(214, 'veronisa', 'bpjs_api_url', ''),
(215, 'settings', 'login_otp', 'tidak'),
(216, 'keuangan', 'akun_debet_kas', '1101'),
(217, 'bpjs_emr', 'kecamatan', ''),
(218, 'satu_sehat', 'imaging', ''),
(219, 'farmasi', 'pajak_obat_persen', '0'),
(220, 'settings', 'billing_parsial', 'false'),
(221, 'icare', 'kd_aplikasi', '095'),
(222, 'icare', 'kode_faskes', '0169B012'),
(223, 'antrean_bpjs', 'consid', ''),
(224, 'antrean_bpjs', 'secretkey', ''),
(225, 'antrean_bpjs', 'userkey', ''),
(226, 'antrean_bpjs', 'kode_ppk', '0115B001'),
(227, 'antrean_bpjs', 'api_url', 'https://apijkn-dev.bpjs-kesehatan.go.id/antreanrs_dev');

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_snomed`
--

DROP TABLE IF EXISTS `mlite_snomed`;
CREATE TABLE IF NOT EXISTS `mlite_snomed` (
  `id` int NOT NULL AUTO_INCREMENT,
  `kode` varchar(20) NOT NULL,
  `istilah` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode` (`kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_subrekening`
--

DROP TABLE IF EXISTS `mlite_subrekening`;
CREATE TABLE IF NOT EXISTS `mlite_subrekening` (
  `kd_rek` varchar(15) NOT NULL,
  `kd_rek2` varchar(15) NOT NULL,
  PRIMARY KEY (`kd_rek2`),
  KEY `kd_rek` (`kd_rek`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_surat_rujukan`
--

DROP TABLE IF EXISTS `mlite_surat_rujukan`;
CREATE TABLE IF NOT EXISTS `mlite_surat_rujukan` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nomor_surat` varchar(100) DEFAULT NULL,
  `no_rawat` varchar(100) DEFAULT NULL,
  `no_rkm_medis` varchar(100) DEFAULT NULL,
  `nm_pasien` varchar(100) DEFAULT NULL,
  `tgl_lahir` varchar(100) DEFAULT NULL,
  `umur` varchar(100) DEFAULT NULL,
  `jk` varchar(100) DEFAULT NULL,
  `alamat` text,
  `kepada` varchar(250) DEFAULT NULL,
  `di` varchar(250) DEFAULT NULL,
  `anamnesa` varchar(100) DEFAULT NULL,
  `pemeriksaan_fisik` varchar(100) DEFAULT NULL,
  `pemeriksaan_penunjang` varchar(100) DEFAULT NULL,
  `diagnosa` varchar(100) DEFAULT NULL,
  `terapi` varchar(100) DEFAULT NULL,
  `alasan_dirujuk` varchar(250) DEFAULT NULL,
  `dokter` varchar(100) DEFAULT NULL,
  `petugas` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_surat_sakit`
--

DROP TABLE IF EXISTS `mlite_surat_sakit`;
CREATE TABLE IF NOT EXISTS `mlite_surat_sakit` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nomor_surat` varchar(100) DEFAULT NULL,
  `no_rawat` varchar(100) DEFAULT NULL,
  `no_rkm_medis` varchar(100) DEFAULT NULL,
  `nm_pasien` varchar(100) DEFAULT NULL,
  `tgl_lahir` varchar(100) DEFAULT NULL,
  `umur` varchar(100) DEFAULT NULL,
  `jk` varchar(100) DEFAULT NULL,
  `alamat` text,
  `keadaan` varchar(100) DEFAULT NULL,
  `diagnosa` varchar(100) DEFAULT NULL,
  `lama_angka` varchar(100) DEFAULT NULL,
  `lama_huruf` varchar(100) DEFAULT NULL,
  `tanggal_mulai` varchar(100) DEFAULT NULL,
  `tanggal_selesai` varchar(100) DEFAULT NULL,
  `dokter` varchar(100) DEFAULT NULL,
  `petugas` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_surat_sehat`
--

DROP TABLE IF EXISTS `mlite_surat_sehat`;
CREATE TABLE IF NOT EXISTS `mlite_surat_sehat` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nomor_surat` varchar(100) DEFAULT NULL,
  `no_rawat` varchar(100) DEFAULT NULL,
  `no_rkm_medis` varchar(100) DEFAULT NULL,
  `nm_pasien` varchar(100) DEFAULT NULL,
  `tgl_lahir` varchar(100) DEFAULT NULL,
  `umur` varchar(100) DEFAULT NULL,
  `jk` varchar(100) DEFAULT NULL,
  `alamat` text,
  `tanggal` varchar(100) DEFAULT NULL,
  `berat_badan` varchar(100) DEFAULT NULL,
  `tinggi_badan` varchar(100) DEFAULT NULL,
  `tensi` varchar(100) DEFAULT NULL,
  `gol_darah` varchar(100) DEFAULT NULL,
  `riwayat_penyakit` varchar(100) DEFAULT NULL,
  `keperluan` varchar(100) DEFAULT NULL,
  `dokter` varchar(100) DEFAULT NULL,
  `petugas` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_temporary`
--

DROP TABLE IF EXISTS `mlite_temporary`;
CREATE TABLE IF NOT EXISTS `mlite_temporary` (
  `temp1` text,
  `temp2` text,
  `temp3` text,
  `temp4` text,
  `temp5` text,
  `temp6` text,
  `temp7` text,
  `temp8` text,
  `temp9` text,
  `temp10` text,
  `temp11` text,
  `temp12` text,
  `temp13` text,
  `temp14` text,
  `temp15` text,
  `temp16` text,
  `temp17` text,
  `temp18` text,
  `temp19` text,
  `temp20` text,
  `temp21` text,
  `temp22` text,
  `temp23` text,
  `temp24` text,
  `temp25` text,
  `temp26` text,
  `temp27` text,
  `temp28` text,
  `temp29` text,
  `temp30` text,
  `temp31` text,
  `temp32` text,
  `temp33` text,
  `temp34` text,
  `temp35` text,
  `temp36` text,
  `temp37` text,
  `temp38` text,
  `temp39` text,
  `temp40` text,
  `temp41` text,
  `temp42` text,
  `temp43` text,
  `temp44` text,
  `temp45` text,
  `temp46` text,
  `temp47` text,
  `temp48` text,
  `temp49` text,
  `temp50` text,
  `temp51` text,
  `temp52` text,
  `temp53` text,
  `temp54` text,
  `temp55` text,
  `temp56` text,
  `temp57` text,
  `temp58` text,
  `temp59` text,
  `temp60` text,
  `temp61` text,
  `temp62` text,
  `temp63` text,
  `temp64` text,
  `temp65` text,
  `temp66` text,
  `temp67` text,
  `temp68` text,
  `temp69` text,
  `temp70` text,
  `temp71` text,
  `temp72` text,
  `temp73` text,
  `temp74` text,
  `temp75` text,
  `temp76` text,
  `temp77` text,
  `temp78` text,
  `temp79` text,
  `temp80` text,
  `temp81` text,
  `temp82` text,
  `temp83` text,
  `temp84` text,
  `temp85` text,
  `temp86` text,
  `temp87` text,
  `temp88` text,
  `temp89` text,
  `temp90` text,
  `temp91` text,
  `temp92` text,
  `temp93` text,
  `temp94` text,
  `temp95` text,
  `temp96` text,
  `temp97` text,
  `temp98` text,
  `temp99` text,
  `temp100` text
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_triase_igd`
--

DROP TABLE IF EXISTS `mlite_triase_igd`;
CREATE TABLE IF NOT EXISTS `mlite_triase_igd` (
  `id_triase` int NOT NULL AUTO_INCREMENT,
  `no_rawat` varchar(17) NOT NULL,
  `no_rkm_medis` varchar(15) NOT NULL,
  `tgl_triase` datetime NOT NULL,
  `petugas_id` varchar(20) NOT NULL,
  `kesadaran` enum('Compos Mentis','Apatis','Somnolen','Sopor','Koma') NOT NULL,
  `airway` enum('Bebas','Sumbatan Parsial','Sumbatan Total') NOT NULL,
  `breathing` enum('Spontan','Tak Spontan','Distres Nafas') NOT NULL,
  `circulation` enum('Baik','Syok','Perdarahan') NOT NULL,
  `tekanan_darah` varchar(10) DEFAULT NULL,
  `nadi` int DEFAULT NULL,
  `respirasi` int DEFAULT NULL,
  `suhu` decimal(4,1) DEFAULT NULL,
  `spo2` int DEFAULT NULL,
  `gcs_e` tinyint DEFAULT NULL,
  `gcs_v` tinyint DEFAULT NULL,
  `gcs_m` tinyint DEFAULT NULL,
  `kategori` enum('Merah','Kuning','Hijau','Hitam') NOT NULL,
  `skala_triase` enum('1','2','3','4','5') DEFAULT NULL,
  `keluhan_utama` text,
  `diagnosa_awal` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_triase`) USING BTREE,
  KEY `no_rawat` (`no_rawat`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_users`
--

DROP TABLE IF EXISTS `mlite_users`;
CREATE TABLE IF NOT EXISTS `mlite_users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` text,
  `fullname` text,
  `description` text,
  `password` text,
  `password_changed_at` datetime DEFAULT NULL,
  `otp_code` varchar(10) DEFAULT NULL,
  `otp_expires` datetime DEFAULT NULL,
  `avatar` text,
  `email` text,
  `role` varchar(100) NOT NULL DEFAULT 'user',
  `cap` varchar(100) DEFAULT '',
  `access` text,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `mlite_users`
--

INSERT INTO `mlite_users` (`id`, `username`, `fullname`, `description`, `password`, `password_changed_at`, `otp_code`, `otp_expires`, `avatar`, `email`, `role`, `cap`, `access`) VALUES
(1, 'admin', 'Administrator', 'Admin ganteng baik hati, suka menabung dan tidak sombong.', '$2y$10$pgRnDiukCbiYVqsamMM3ROWViSRqbyCCL33N8.ykBKZx0dlplXe9i', NULL, NULL, NULL, 'avatar6422cb573b50c.png', 'info@mlite.id', 'admin', '', 'all'),
(2, 'DR001', 'dr. Ataaka Muhammad', '-', '$2y$10$kuf2BxvViduBpUTn.6Nxsug3AskH/PGvXTSlfCfJqK8Ayb9a0.vqC', NULL, NULL, NULL, 'avatar643a104444515.png', 'info@mlite.id', 'admin', '', 'all');

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_users_vedika`
--

DROP TABLE IF EXISTS `mlite_users_vedika`;
CREATE TABLE IF NOT EXISTS `mlite_users_vedika` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` text,
  `password` text,
  `fullname` text,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_vedika`
--

DROP TABLE IF EXISTS `mlite_vedika`;
CREATE TABLE IF NOT EXISTS `mlite_vedika` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tanggal` date DEFAULT NULL,
  `no_rkm_medis` varchar(6) NOT NULL,
  `no_rawat` varchar(100) NOT NULL,
  `tgl_registrasi` varchar(100) NOT NULL,
  `nosep` varchar(100) NOT NULL,
  `jenis` varchar(100) NOT NULL,
  `status` varchar(100) NOT NULL,
  `username` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_vedika_status_jenis_tgl` (`status`,`jenis`,`tgl_registrasi`),
  KEY `idx_vedika_nosep` (`nosep`),
  KEY `idx_vedika_no_rkm_medis` (`no_rkm_medis`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_vedika_feedback`
--

DROP TABLE IF EXISTS `mlite_vedika_feedback`;
CREATE TABLE IF NOT EXISTS `mlite_vedika_feedback` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nosep` varchar(100) NOT NULL,
  `tanggal` date DEFAULT NULL,
  `catatan` text,
  `username` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_veronisa`
--

DROP TABLE IF EXISTS `mlite_veronisa`;
CREATE TABLE IF NOT EXISTS `mlite_veronisa` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tanggal` date DEFAULT NULL,
  `no_rkm_medis` varchar(6) NOT NULL,
  `no_rawat` varchar(100) NOT NULL,
  `tgl_registrasi` varchar(100) NOT NULL,
  `nosep` varchar(100) NOT NULL,
  `status` varchar(100) NOT NULL,
  `username` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_no_rawat` (`no_rawat`),
  KEY `idx_status` (`status`),
  KEY `idx_nosep` (`nosep`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mlite_veronisa_feedback`
--

DROP TABLE IF EXISTS `mlite_veronisa_feedback`;
CREATE TABLE IF NOT EXISTS `mlite_veronisa_feedback` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nosep` varchar(100) NOT NULL,
  `tanggal` date DEFAULT NULL,
  `catatan` text,
  `username` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mutasibarang`
--

DROP TABLE IF EXISTS `mutasibarang`;
CREATE TABLE IF NOT EXISTS `mutasibarang` (
  `kode_brng` varchar(15) NOT NULL,
  `jml` double NOT NULL,
  `harga` double NOT NULL,
  `kd_bangsaldari` char(5) NOT NULL,
  `kd_bangsalke` char(5) NOT NULL,
  `tanggal` datetime NOT NULL,
  `keterangan` varchar(60) NOT NULL,
  `no_batch` varchar(20) NOT NULL,
  `no_faktur` varchar(20) NOT NULL,
  PRIMARY KEY (`kode_brng`,`kd_bangsaldari`,`kd_bangsalke`,`tanggal`,`no_batch`,`no_faktur`) USING BTREE,
  KEY `kd_bangsaldari` (`kd_bangsaldari`) USING BTREE,
  KEY `kd_bangsalke` (`kd_bangsalke`) USING BTREE,
  KEY `jml` (`jml`) USING BTREE,
  KEY `keterangan` (`keterangan`) USING BTREE,
  KEY `kode_brng` (`kode_brng`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `mutasibarang`
--

INSERT INTO `mutasibarang` (`kode_brng`, `jml`, `harga`, `kd_bangsaldari`, `kd_bangsalke`, `tanggal`, `keterangan`, `no_batch`, `no_faktur`) VALUES
('OB001', 100, 350, 'G001', 'AP01', '2026-09-07 08:30:00', 'Distribusi Stok Rutin ke Apotek Pelayanan', 'BATCH-2026-001', 'FAK-2026-001'),
('OB002', 50, 650, 'G001', 'AP01', '2026-09-07 08:30:00', 'Distribusi Stok Rutin ke Apotek Pelayanan', 'BATCH-2026-001', 'FAK-2026-001'),
('OB003', 50, 450, 'G001', 'AP01', '2026-09-07 08:30:00', 'Distribusi Stok Rutin ke Apotek Pelayanan', 'BATCH-2026-001', 'FAK-2026-001'),
('OB007', 30, 800, 'G001', 'AP01', '2026-09-07 08:30:00', 'Distribusi Stok Rutin ke Apotek Pelayanan', 'BATCH-2026-001', 'FAK-2026-001');

-- --------------------------------------------------------

--
-- Struktur dari tabel `mutasi_berkas`
--

DROP TABLE IF EXISTS `mutasi_berkas`;
CREATE TABLE IF NOT EXISTS `mutasi_berkas` (
  `no_rawat` varchar(17) NOT NULL,
  `status` enum('Sudah Dikirim','Sudah Diterima','Sudah Kembali','Tidak Ada','Masuk Ranap') DEFAULT NULL,
  `dikirim` datetime DEFAULT NULL,
  `diterima` datetime DEFAULT NULL,
  `kembali` datetime DEFAULT NULL,
  `tidakada` datetime DEFAULT NULL,
  `ranap` datetime NOT NULL,
  PRIMARY KEY (`no_rawat`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `obatbhp_ok`
--

DROP TABLE IF EXISTS `obatbhp_ok`;
CREATE TABLE IF NOT EXISTS `obatbhp_ok` (
  `kd_obat` varchar(15) NOT NULL,
  `nm_obat` varchar(50) NOT NULL,
  `kode_sat` char(4) NOT NULL,
  `hargasatuan` double NOT NULL,
  PRIMARY KEY (`kd_obat`),
  KEY `kode_sat` (`kode_sat`),
  KEY `nm_obat` (`nm_obat`),
  KEY `hargasatuan` (`hargasatuan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `obat_racikan`
--

DROP TABLE IF EXISTS `obat_racikan`;
CREATE TABLE IF NOT EXISTS `obat_racikan` (
  `tgl_perawatan` date NOT NULL,
  `jam` time NOT NULL,
  `no_rawat` varchar(17) NOT NULL,
  `no_racik` varchar(2) NOT NULL,
  `nama_racik` varchar(100) NOT NULL,
  `kd_racik` varchar(3) NOT NULL,
  `jml_dr` int NOT NULL,
  `aturan_pakai` varchar(150) NOT NULL,
  `keterangan` varchar(50) NOT NULL,
  PRIMARY KEY (`tgl_perawatan`,`jam`,`no_rawat`,`no_racik`),
  KEY `kd_racik` (`kd_racik`),
  KEY `no_rawat` (`no_rawat`),
  KEY `no_racik` (`no_racik`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `operasi`
--

DROP TABLE IF EXISTS `operasi`;
CREATE TABLE IF NOT EXISTS `operasi` (
  `no_rawat` varchar(17) NOT NULL,
  `tgl_operasi` datetime NOT NULL,
  `jenis_anasthesi` varchar(8) NOT NULL,
  `kategori` enum('-','Khusus','Besar','Sedang','Kecil','Elektive','Emergency') DEFAULT NULL,
  `operator1` varchar(20) NOT NULL,
  `operator2` varchar(20) NOT NULL,
  `operator3` varchar(20) NOT NULL,
  `asisten_operator1` varchar(20) NOT NULL,
  `asisten_operator2` varchar(20) NOT NULL,
  `asisten_operator3` varchar(20) DEFAULT NULL,
  `instrumen` varchar(20) DEFAULT NULL,
  `dokter_anak` varchar(20) NOT NULL,
  `perawaat_resusitas` varchar(20) NOT NULL,
  `dokter_anestesi` varchar(20) NOT NULL,
  `asisten_anestesi` varchar(20) NOT NULL,
  `asisten_anestesi2` varchar(20) DEFAULT NULL,
  `bidan` varchar(20) NOT NULL,
  `bidan2` varchar(20) DEFAULT NULL,
  `bidan3` varchar(20) DEFAULT NULL,
  `perawat_luar` varchar(20) NOT NULL,
  `omloop` varchar(20) DEFAULT NULL,
  `omloop2` varchar(20) DEFAULT NULL,
  `omloop3` varchar(20) DEFAULT NULL,
  `omloop4` varchar(20) DEFAULT NULL,
  `omloop5` varchar(20) DEFAULT NULL,
  `dokter_pjanak` varchar(20) DEFAULT NULL,
  `dokter_umum` varchar(20) DEFAULT NULL,
  `kode_paket` varchar(15) NOT NULL,
  `biayaoperator1` double NOT NULL,
  `biayaoperator2` double NOT NULL,
  `biayaoperator3` double NOT NULL,
  `biayaasisten_operator1` double NOT NULL,
  `biayaasisten_operator2` double NOT NULL,
  `biayaasisten_operator3` double DEFAULT NULL,
  `biayainstrumen` double DEFAULT NULL,
  `biayadokter_anak` double NOT NULL,
  `biayaperawaat_resusitas` double NOT NULL,
  `biayadokter_anestesi` double NOT NULL,
  `biayaasisten_anestesi` double NOT NULL,
  `biayaasisten_anestesi2` double DEFAULT NULL,
  `biayabidan` double NOT NULL,
  `biayabidan2` double DEFAULT NULL,
  `biayabidan3` double DEFAULT NULL,
  `biayaperawat_luar` double NOT NULL,
  `biayaalat` double NOT NULL,
  `biayasewaok` double NOT NULL,
  `akomodasi` double DEFAULT NULL,
  `bagian_rs` double NOT NULL,
  `biaya_omloop` double DEFAULT NULL,
  `biaya_omloop2` double DEFAULT NULL,
  `biaya_omloop3` double DEFAULT NULL,
  `biaya_omloop4` double DEFAULT NULL,
  `biaya_omloop5` double DEFAULT NULL,
  `biayasarpras` double DEFAULT NULL,
  `biaya_dokter_pjanak` double DEFAULT NULL,
  `biaya_dokter_umum` double DEFAULT NULL,
  `status` enum('Ranap','Ralan') DEFAULT NULL,
  PRIMARY KEY (`no_rawat`,`tgl_operasi`,`kode_paket`) USING BTREE,
  KEY `no_rawat` (`no_rawat`),
  KEY `operator1` (`operator1`),
  KEY `operator2` (`operator2`),
  KEY `operator3` (`operator3`),
  KEY `asisten_operator1` (`asisten_operator1`),
  KEY `asisten_operator2` (`asisten_operator2`),
  KEY `dokter_anak` (`dokter_anak`),
  KEY `perawaat_resusitas` (`perawaat_resusitas`),
  KEY `dokter_anestesi` (`dokter_anestesi`),
  KEY `asisten_anestesi` (`asisten_anestesi`),
  KEY `bidan` (`bidan`),
  KEY `perawat_luar` (`perawat_luar`),
  KEY `kode_paket` (`kode_paket`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `opname`
--

DROP TABLE IF EXISTS `opname`;
CREATE TABLE IF NOT EXISTS `opname` (
  `kode_brng` varchar(15) NOT NULL,
  `h_beli` double DEFAULT NULL,
  `tanggal` date NOT NULL,
  `stok` double NOT NULL,
  `real` double NOT NULL,
  `selisih` double NOT NULL,
  `nomihilang` double NOT NULL,
  `lebih` double NOT NULL,
  `nomilebih` double NOT NULL,
  `keterangan` varchar(60) NOT NULL,
  `kd_bangsal` char(5) NOT NULL,
  `no_batch` varchar(20) NOT NULL,
  `no_faktur` varchar(20) NOT NULL,
  PRIMARY KEY (`kode_brng`,`tanggal`,`kd_bangsal`,`no_batch`,`no_faktur`) USING BTREE,
  KEY `kd_bangsal` (`kd_bangsal`) USING BTREE,
  KEY `stok` (`stok`) USING BTREE,
  KEY `real` (`real`) USING BTREE,
  KEY `selisih` (`selisih`) USING BTREE,
  KEY `nomihilang` (`nomihilang`) USING BTREE,
  KEY `keterangan` (`keterangan`) USING BTREE,
  KEY `kode_brng` (`kode_brng`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `opname`
--

INSERT INTO `opname` (`kode_brng`, `h_beli`, `tanggal`, `stok`, `real`, `selisih`, `nomihilang`, `lebih`, `nomilebih`, `keterangan`, `kd_bangsal`, `no_batch`, `no_faktur`) VALUES
('OB001', 350, '2026-09-01', 500, 500, 0, 0, 0, 0, 'Stok Opname Awal Bulan September 2026 - Klop', 'G001', 'BATCH-2026-001', 'FAK-2026-001'),
('OB002', 650, '2026-09-01', 300, 300, 0, 0, 0, 0, 'Stok Opname Awal Bulan September 2026 - Klop', 'G001', 'BATCH-2026-001', 'FAK-2026-001'),
('OB003', 450, '2026-09-01', 200, 200, 0, 0, 0, 0, 'Stok Opname Awal Bulan September 2026 - Klop', 'G001', 'BATCH-2026-001', 'FAK-2026-001'),
('OB005', 400, '2026-09-01', 300, 300, 0, 0, 0, 0, 'Stok Opname Awal Bulan September 2026 - Klop', 'G001', 'BATCH-2026-001', 'FAK-2026-001');

-- --------------------------------------------------------

--
-- Struktur dari tabel `paket_operasi`
--

DROP TABLE IF EXISTS `paket_operasi`;
CREATE TABLE IF NOT EXISTS `paket_operasi` (
  `kode_paket` varchar(15) NOT NULL,
  `nm_perawatan` varchar(80) NOT NULL,
  `kategori` enum('Kebidanan','Operasi') DEFAULT NULL,
  `operator1` double NOT NULL,
  `operator2` double NOT NULL,
  `operator3` double NOT NULL,
  `asisten_operator1` double DEFAULT NULL,
  `asisten_operator2` double NOT NULL,
  `asisten_operator3` double DEFAULT NULL,
  `instrumen` double DEFAULT NULL,
  `dokter_anak` double NOT NULL,
  `perawaat_resusitas` double NOT NULL,
  `dokter_anestesi` double NOT NULL,
  `asisten_anestesi` double NOT NULL,
  `asisten_anestesi2` double DEFAULT NULL,
  `bidan` double NOT NULL,
  `bidan2` double DEFAULT NULL,
  `bidan3` double DEFAULT NULL,
  `perawat_luar` double NOT NULL,
  `sewa_ok` double NOT NULL,
  `alat` double NOT NULL,
  `akomodasi` double DEFAULT NULL,
  `bagian_rs` double NOT NULL,
  `omloop` double NOT NULL,
  `omloop2` double DEFAULT NULL,
  `omloop3` double DEFAULT NULL,
  `omloop4` double DEFAULT NULL,
  `omloop5` double DEFAULT NULL,
  `sarpras` double DEFAULT NULL,
  `dokter_pjanak` double DEFAULT NULL,
  `dokter_umum` double DEFAULT NULL,
  `kd_pj` char(3) DEFAULT NULL,
  `status` enum('0','1') DEFAULT NULL,
  `kelas` enum('-','Rawat Jalan','Kelas 1','Kelas 2','Kelas 3','Kelas Utama','Kelas VIP','Kelas VVIP') DEFAULT NULL,
  PRIMARY KEY (`kode_paket`),
  KEY `nm_perawatan` (`nm_perawatan`),
  KEY `operator1` (`operator1`),
  KEY `operator2` (`operator2`),
  KEY `operator3` (`operator3`),
  KEY `asisten_operator1` (`asisten_operator1`),
  KEY `asisten_operator2` (`asisten_operator2`),
  KEY `asisten_operator3` (`instrumen`),
  KEY `dokter_anak` (`dokter_anak`),
  KEY `perawat_resusitas` (`perawaat_resusitas`),
  KEY `dokter_anestasi` (`dokter_anestesi`),
  KEY `asisten_anastesi` (`asisten_anestesi`),
  KEY `bidan` (`bidan`),
  KEY `perawat_luar` (`perawat_luar`),
  KEY `sewa_ok` (`sewa_ok`),
  KEY `alat` (`alat`),
  KEY `sewa_vk` (`akomodasi`),
  KEY `bagian_rs` (`bagian_rs`),
  KEY `omloop` (`omloop`),
  KEY `kd_pj` (`kd_pj`),
  KEY `asisten_anestesi2` (`asisten_anestesi2`),
  KEY `omloop2` (`omloop2`),
  KEY `omloop3` (`omloop3`),
  KEY `omloop4` (`omloop4`),
  KEY `omloop5` (`omloop5`),
  KEY `status` (`status`),
  KEY `kategori` (`kategori`),
  KEY `bidan2` (`bidan2`),
  KEY `bidan3` (`bidan3`),
  KEY `asisten_operator3_2` (`asisten_operator3`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `pasien`
--

DROP TABLE IF EXISTS `pasien`;
CREATE TABLE IF NOT EXISTS `pasien` (
  `no_rkm_medis` varchar(15) NOT NULL,
  `nm_pasien` varchar(40) DEFAULT NULL,
  `no_ktp` varchar(20) DEFAULT NULL,
  `jk` enum('L','P') DEFAULT NULL,
  `tmp_lahir` varchar(15) DEFAULT NULL,
  `tgl_lahir` date DEFAULT NULL,
  `nm_ibu` varchar(40) NOT NULL,
  `alamat` varchar(200) DEFAULT NULL,
  `gol_darah` enum('A','B','O','AB','-') DEFAULT NULL,
  `pekerjaan` varchar(60) DEFAULT NULL,
  `stts_nikah` enum('BELUM MENIKAH','MENIKAH','JANDA','DUDHA','JOMBLO') DEFAULT NULL,
  `agama` varchar(12) DEFAULT NULL,
  `tgl_daftar` date DEFAULT NULL,
  `no_tlp` varchar(40) DEFAULT NULL,
  `umur` varchar(30) NOT NULL,
  `pnd` enum('TS','TK','SD','SMP','SMA','SLTA/SEDERAJAT','D1','D2','D3','D4','S1','S2','S3','-') NOT NULL,
  `keluarga` enum('AYAH','IBU','ISTRI','SUAMI','SAUDARA','ANAK') DEFAULT NULL,
  `namakeluarga` varchar(50) NOT NULL,
  `kd_pj` char(3) NOT NULL,
  `no_peserta` varchar(25) DEFAULT NULL,
  `kd_kel` varchar(100) NOT NULL,
  `kd_kec` int NOT NULL,
  `kd_kab` int NOT NULL,
  `pekerjaanpj` varchar(35) NOT NULL,
  `alamatpj` varchar(100) NOT NULL,
  `kelurahanpj` varchar(60) NOT NULL,
  `kecamatanpj` varchar(60) NOT NULL,
  `kabupatenpj` varchar(60) NOT NULL,
  `perusahaan_pasien` varchar(8) NOT NULL,
  `suku_bangsa` int NOT NULL,
  `bahasa_pasien` int NOT NULL,
  `cacat_fisik` int NOT NULL,
  `email` varchar(50) NOT NULL,
  `nip` varchar(30) NOT NULL,
  `kd_prop` int NOT NULL,
  `propinsipj` varchar(30) NOT NULL,
  PRIMARY KEY (`no_rkm_medis`),
  KEY `kd_pj` (`kd_pj`),
  KEY `kd_kec` (`kd_kec`),
  KEY `kd_kab` (`kd_kab`),
  KEY `nm_pasien` (`nm_pasien`),
  KEY `alamat` (`alamat`),
  KEY `kd_kel_2` (`kd_kel`),
  KEY `no_ktp` (`no_ktp`),
  KEY `no_peserta` (`no_peserta`),
  KEY `perusahaan_pasien` (`perusahaan_pasien`) USING BTREE,
  KEY `suku_bangsa` (`suku_bangsa`) USING BTREE,
  KEY `bahasa_pasien` (`bahasa_pasien`) USING BTREE,
  KEY `cacat_fisik` (`cacat_fisik`) USING BTREE,
  KEY `kd_prop` (`kd_prop`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `pasien`
--

INSERT INTO `pasien` (`no_rkm_medis`, `nm_pasien`, `no_ktp`, `jk`, `tmp_lahir`, `tgl_lahir`, `nm_ibu`, `alamat`, `gol_darah`, `pekerjaan`, `stts_nikah`, `agama`, `tgl_daftar`, `no_tlp`, `umur`, `pnd`, `keluarga`, `namakeluarga`, `kd_pj`, `no_peserta`, `kd_kel`, `kd_kec`, `kd_kab`, `pekerjaanpj`, `alamatpj`, `kelurahanpj`, `kecamatanpj`, `kabupatenpj`, `perusahaan_pasien`, `suku_bangsa`, `bahasa_pasien`, `cacat_fisik`, `email`, `nip`, `kd_prop`, `propinsipj`) VALUES
('000001', 'BAMBANG HERMAWAN', '3171011508800001', 'L', 'JAKARTA', '1980-08-15', 'SITI AISYAH', 'JL. KEBON SIRIH NO. 12', 'O', 'PNS / ASN', 'MENIKAH', 'ISLAM', '2026-01-10', '081298760001', '46 Th 0 Bl 24 Hr', 'S1', 'AYAH', 'SITI AISYAH', 'BPJ', '0001234567891', '1', 1, 1, 'IBU RUMAH TANGGA', 'JL. KEBON SIRIH NO. 12', 'JAKARTA', 'JAKARTA', '081298760001', 'CORP01', 1, 1, 1, 'pasien000001@gmail.com', '-', 31, '31'),
('000002', 'RATNA DEWI KARTIKA', '3171025204850002', 'P', 'BANDUNG', '1985-04-12', 'ENDAH RETNO', 'JL. TEBET TIMUR DALAM NO. 25', 'A', 'KARYAWAN SWASTA', 'MENIKAH', 'ISLAM', '2026-01-15', '081298760002', '41 Th 4 Bl 26 Hr', 'S1', 'SUAMI', 'ANDI WIJAYA', 'BPJ', '0001234567892', '2', 2, 2, 'WIRASWASTA', 'JL. TEBET TIMUR DALAM NO. 25', 'JAKARTA', 'JAKARTA', '081298760002', 'CORP02', 1, 1, 1, 'pasien000002@gmail.com', '-', 31, '31'),
('000003', 'HENDRO KURNIAWAN', '3275031011920003', 'L', 'BEKASI', '1992-11-10', 'SRI WAHYUNI', 'JL. AHMAD YANI NO. 88', 'B', 'WIRASWASTA', 'BELUM MENIKAH', 'ISLAM', '2026-02-01', '081298760003', '33 Th 9 Bl 28 Hr', 'SMA', 'IBU', 'SRI WAHYUNI', 'UMU', '-', '1', 1, 1, 'IBU RUMAH TANGGA', 'JL. AHMAD YANI NO. 88', 'BEKASI', 'BEKASI', '081298760003', '-', 1, 1, 1, 'pasien000003@gmail.com', '-', 1, 'DKI JAKARTA'),
('000004', 'DEWI ANGGRAENI', '3271044509950004', 'P', 'BOGOR', '1995-09-05', 'NURJANAH', 'JL. PAJAJARAN NO. 45', 'AB', 'GURU / PENGAJAR', 'MENIKAH', 'ISLAM', '2026-02-10', '081298760004', '31 Th 0 Bl 3 Hr', 'S1', 'SUAMI', 'RIZKY FAUZI', 'BPJ', '0001234567894', '2', 2, 2, 'KARYAWAN SWASTA', 'JL. PAJAJARAN NO. 45', 'BOGOR', 'BOGOR', '081298760004', 'CORP03', 1, 1, 1, 'pasien000004@gmail.com', '-', 2, 'DKI JAKARTA'),
('000005', 'AGUS SETIAWAN', '3171052203780005', 'L', 'JAKARTA', '1978-03-22', 'RUKMINI', 'JL. MANGGA BESAR NO. 101', 'O', 'PEDAGANG', 'MENIKAH', 'ISLAM', '2026-03-01', '081298760005', '48 Th 5 Bl 17 Hr', 'SMA', 'ISTRI', 'MARLINA', 'UMU', '-', '1', 1, 1, 'PEDAGANG', 'JL. MANGGA BESAR NO. 101', 'JAKARTA', 'JAKARTA', '081298760005', '-', 1, 1, 1, 'pasien000005@gmail.com', '-', 31, '31'),
('000006', 'SRI MULYANI', '3171066807820006', 'P', 'SURABAYA', '1982-07-28', 'KARTINI', 'JL. FATMAWATI RAYA NO. 30', 'A', 'KARYAWAN BUMN', 'MENIKAH', 'ISLAM', '2026-03-15', '081298760006', '44 Th 1 Bl 11 Hr', 'S1', 'SUAMI', 'SUGENG RIYADI', 'ASR', '0001234567896', '2', 2, 2, 'KARYAWAN BUMN', 'JL. FATMAWATI RAYA NO. 30', 'JAKARTA', 'JAKARTA', '081298760006', 'CORP01', 1, 1, 1, 'pasien000006@gmail.com', '-', 31, '31'),
('000007', 'MUHAMMAD RIZKY (ANAK)', '3171011506180007', 'L', 'JAKARTA', '2018-06-15', 'RATNA DEWI', 'JL. CEMPAKA PUTIH NO. 5', 'B', 'BELUM BEKERJA', 'BELUM MENIKAH', 'ISLAM', '2026-04-01', '081298760007', '8 Th 2 Bl 23 Hr', '-', 'AYAH', 'ANDI WIJAYA', 'BPJ', '0001234567897', '1', 1, 1, 'WIRASWASTA', 'JL. CEMPAKA PUTIH NO. 5', 'JAKARTA', 'JAKARTA', '081298760007', 'CORP02', 1, 1, 1, 'pasien000007@gmail.com', '-', 31, '31'),
('000008', 'NURUL AINI', '3275085112900008', 'P', 'BEKASI', '1990-12-11', 'HALIMAH', 'JL. IR. JUANDA NO. 14', 'O', 'KARYAWAN SWASTA', 'MENIKAH', 'ISLAM', '2026-04-10', '081298760008', '35 Th 8 Bl 28 Hr', 'D3', 'SUAMI', 'EKO PRASETYO', 'PRH', '0001234567898', '2', 2, 2, 'KARYAWAN SWASTA', 'JL. IR. JUANDA NO. 14', 'BEKASI', 'BEKASI', '081298760008', 'CORP01', 1, 1, 1, 'pasien000008@gmail.com', '-', 2, 'DKI JAKARTA'),
('000009', 'EKO PRASETYO', '3275091402880009', 'L', 'SEMARANG', '1988-02-14', 'SUMIATI', 'JL. IR. JUANDA NO. 14', 'A', 'WIRASWASTA', 'MENIKAH', 'ISLAM', '2026-05-01', '081298760009', '38 Th 6 Bl 23 Hr', 'S1', 'ISTRI', 'NURUL AINI', 'UMU', '-', '1', 1, 1, 'KARYAWAN SWASTA', 'JL. IR. JUANDA NO. 14', 'BEKASI', 'BEKASI', '081298760009', '-', 1, 1, 1, 'pasien000009@gmail.com', '-', 1, 'DKI JAKARTA'),
('000010', 'NURHALIZA', '3171104808980010', 'P', 'JAKARTA', '1998-08-08', 'AMINA', 'JL. KRAMAT JATI NO. 7', 'B', 'MAHASISWA', 'BELUM MENIKAH', 'ISLAM', '2026-05-15', '081298760010', '28 Th 1 Bl 0 Hr', 'SMA', 'IBU', 'AMINA', 'BPJ', '0001234567810', '1', 1, 1, 'IBU RUMAH TANGGA', 'JL. KRAMAT JATI NO. 7', 'JAKARTA', 'JAKARTA', '081298760010', '-', 1, 1, 1, 'pasien000010@gmail.com', '-', 31, '31');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pegawai`
--

DROP TABLE IF EXISTS `pegawai`;
CREATE TABLE IF NOT EXISTS `pegawai` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nik` varchar(20) NOT NULL,
  `nama` varchar(50) NOT NULL,
  `jk` enum('Pria','Wanita') NOT NULL,
  `jbtn` varchar(25) NOT NULL,
  `jnj_jabatan` varchar(5) NOT NULL,
  `kode_kelompok` varchar(3) NOT NULL,
  `kode_resiko` varchar(3) NOT NULL,
  `kode_emergency` varchar(3) NOT NULL,
  `departemen` char(4) NOT NULL,
  `bidang` varchar(15) NOT NULL,
  `stts_wp` char(5) NOT NULL,
  `stts_kerja` char(3) NOT NULL,
  `npwp` varchar(15) NOT NULL,
  `pendidikan` varchar(80) NOT NULL,
  `gapok` double NOT NULL,
  `tmp_lahir` varchar(20) NOT NULL,
  `tgl_lahir` date NOT NULL,
  `alamat` varchar(60) NOT NULL,
  `kota` varchar(20) NOT NULL,
  `mulai_kerja` date NOT NULL,
  `ms_kerja` enum('<1','PT','FT>1') NOT NULL,
  `indexins` char(4) NOT NULL,
  `bpd` varchar(50) NOT NULL,
  `rekening` varchar(25) NOT NULL,
  `stts_aktif` enum('AKTIF','CUTI','KELUAR','TENAGA LUAR') NOT NULL,
  `wajibmasuk` tinyint NOT NULL,
  `pengurang` double NOT NULL,
  `indek` tinyint NOT NULL,
  `mulai_kontrak` date DEFAULT NULL,
  `cuti_diambil` int NOT NULL,
  `dankes` double NOT NULL,
  `photo` text,
  `no_ktp` varchar(20) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nik_2` (`nik`),
  KEY `departemen` (`departemen`),
  KEY `bidang` (`bidang`),
  KEY `stts_wp` (`stts_wp`),
  KEY `stts_kerja` (`stts_kerja`),
  KEY `pendidikan` (`pendidikan`),
  KEY `indexins` (`indexins`),
  KEY `jnj_jabatan` (`jnj_jabatan`),
  KEY `bpd` (`bpd`),
  KEY `nama` (`nama`),
  KEY `jbtn` (`jbtn`),
  KEY `npwp` (`npwp`),
  KEY `dankes` (`dankes`),
  KEY `cuti_diambil` (`cuti_diambil`),
  KEY `mulai_kontrak` (`mulai_kontrak`),
  KEY `stts_aktif` (`stts_aktif`),
  KEY `tmp_lahir` (`tmp_lahir`),
  KEY `alamat` (`alamat`),
  KEY `mulai_kerja` (`mulai_kerja`),
  KEY `gapok` (`gapok`),
  KEY `kota` (`kota`),
  KEY `pengurang` (`pengurang`),
  KEY `indek` (`indek`),
  KEY `jk` (`jk`),
  KEY `ms_kerja` (`ms_kerja`),
  KEY `tgl_lahir` (`tgl_lahir`),
  KEY `rekening` (`rekening`),
  KEY `wajibmasuk` (`wajibmasuk`),
  KEY `kode_emergency` (`kode_emergency`) USING BTREE,
  KEY `kode_kelompok` (`kode_kelompok`) USING BTREE,
  KEY `kode_resiko` (`kode_resiko`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=76 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `pegawai`
--

INSERT INTO `pegawai` (`id`, `nik`, `nama`, `jk`, `jbtn`, `jnj_jabatan`, `kode_kelompok`, `kode_resiko`, `kode_emergency`, `departemen`, `bidang`, `stts_wp`, `stts_kerja`, `npwp`, `pendidikan`, `gapok`, `tmp_lahir`, `tgl_lahir`, `alamat`, `kota`, `mulai_kerja`, `ms_kerja`, `indexins`, `bpd`, `rekening`, `stts_aktif`, `wajibmasuk`, `pengurang`, `indek`, `mulai_kontrak`, `cuti_diambil`, `dankes`, `photo`, `no_ktp`) VALUES
(1, 'DR001', 'dr. Ataaka Muhammad, Sp.PD', 'Pria', 'Dokter Spesialis', '-', '-', '-', '-', 'DP01', '-', '-', '-', '-', '-', 12000000, 'Barabai', '2016-06-10', '-', 'Barabai', '2019-09-18', '<1', '-', '-', '-', 'AKTIF', 0, 0, 0, '2019-09-18', 1, 0, '-', '0'),
(3, 'DR002', 'dr. Siti Nurhaliza', 'Wanita', 'Dokter Umum', 'JJ03', 'KJ0', '-', '-', 'DP01', '-', 'K/1', 'TTP', '00.000.000.0-00', 'Profesi Dokter', 7500000, 'Barabai', '1990-08-21', 'Jl. Pahlawan No. 45', 'Barabai', '2020-01-01', '', '-', '-', '-', 'AKTIF', 26, 0, 0, '2020-01-01', 0, 0, '-', '6307000000000001'),
(4, 'DR003', 'dr. Budi Santoso, Sp.A', 'Pria', 'Dokter Spesialis Anak', 'JJ03', 'KJ0', '-', '-', 'DP01', '-', 'K/2', 'MTR', '00.000.000.0-00', 'Spesialis', 11500000, 'Barabai', '1985-03-10', 'Jl. Hasan Basri No. 8', 'Barabai', '2020-01-01', '', '-', '-', '-', 'AKTIF', 26, 0, 0, '2020-01-01', 0, 0, '-', '6307000000000001'),
(5, 'DR004', 'drg. Amanda Putri', 'Wanita', 'Dokter Gigi', 'JJ03', 'KJ0', '-', '-', 'DP01', '-', 'TK/0', 'TTP', '00.000.000.0-00', 'Profesi Dokter', 6800000, 'Barabai', '1993-11-25', 'Jl. Merdeka No. 17', 'Barabai', '2020-01-01', '', '-', '-', '-', 'AKTIF', 26, 0, 0, '2020-01-01', 0, 0, '-', '6307000000000001'),
(6, 'DR005', 'dr. Hendra Wijaya, Sp.OG', 'Pria', 'Dokter Spesialis Kandunga', 'JJ03', 'KJ0', '-', '-', 'DP01', '-', 'K/3', 'MTR', '00.000.000.0-00', 'Spesialis', 13000000, 'Barabai', '1980-01-19', 'Jl. Sudirman No. 99', 'Barabai', '2020-01-01', '', '-', '-', '-', 'AKTIF', 26, 0, 0, '2020-01-01', 0, 0, '-', '6307000000000001'),
(7, 'DR006', 'dr. Ratna Sari', 'Wanita', 'Dokter IGD', 'JJ03', 'KJ0', '-', '-', 'DP01', '-', 'TK/0', 'TTP', '00.000.000.0-00', 'Profesi Dokter', 7000000, 'Barabai', '1992-06-15', 'Jl. Veteran No. 33', 'Barabai', '2020-01-01', '', '-', '-', '-', 'AKTIF', 26, 0, 0, '2020-01-01', 0, 0, '-', '6307000000000001'),
(8, 'PT001', 'Ns. Rina Kartika, S.Kep', 'Wanita', 'Perawat Rawat Jalan', 'JJ04', 'KJ0', '-', '-', 'DP02', '-', 'K/1', 'TTP', '00.000.000.0-00', 'S1 Keperawatan / Ners', 4500000, 'Barabai', '1994-04-12', 'Jl. Diponegoro No. 22', 'Barabai', '2020-01-01', '', '-', '-', '-', 'AKTIF', 26, 0, 0, '2020-01-01', 0, 0, '-', '6307000000000001'),
(9, 'PT002', 'Ns. Dimas Pratama, S.Kep', 'Pria', 'Perawat IGD', 'JJ04', 'KJ0', '-', '-', 'DP02', '-', 'TK/0', 'TTP', '00.000.000.0-00', 'S1 Keperawatan / Ners', 4300000, 'Barabai', '1996-09-08', 'Jl. Pemuda No. 14', 'Barabai', '2020-01-01', '', '-', '-', '-', 'AKTIF', 26, 0, 0, '2020-01-01', 0, 0, '-', '6307000000000001'),
(10, 'PT003', 'Apt. Farhan Hidayat, S.Farm', 'Pria', 'Apoteker Penanggung Jawab', 'JJ02', 'KJ0', '-', '-', 'DP03', '-', 'K/1', 'TTP', '00.000.000.0-00', 'S1 Farmasi / Apt', 5500000, 'Barabai', '1989-12-03', 'Jl. Manggis No. 5', 'Barabai', '2020-01-01', '', '-', '-', '-', 'AKTIF', 26, 0, 0, '2020-01-01', 0, 0, '-', '6307000000000001'),
(11, 'PT004', 'Siti Rahmawati, A.Md.RMIK', 'Wanita', 'Perekam Medis', 'JJ04', 'KJ0', '-', '-', 'DP04', '-', 'TK/0', 'TTP', '00.000.000.0-00', 'D3 Rekam Medis', 3800000, 'Barabai', '1997-02-18', 'Jl. Melati No. 88', 'Barabai', '2020-01-01', '', '-', '-', '-', 'AKTIF', 26, 0, 0, '2020-01-01', 0, 0, '-', '6307000000000001'),
(12, 'PT005', 'Maya Anggraini, S.E', 'Wanita', 'Staf Kasir & Keuangan', 'JJ04', 'KJ0', '-', '-', 'DP05', '-', 'TK/0', 'TTP', '00.000.000.0-00', 'S1 Ekonomi / Kasir', 3900000, 'Barabai', '1995-07-22', 'Jl. Kenanga No. 11', 'Barabai', '2020-01-01', '', '-', '-', '-', 'AKTIF', 26, 0, 0, '2020-01-01', 0, 0, '-', '6307000000000001');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pemeliharaan_inventaris`
--

DROP TABLE IF EXISTS `pemeliharaan_inventaris`;
CREATE TABLE IF NOT EXISTS `pemeliharaan_inventaris` (
  `no_inventaris` varchar(30) NOT NULL,
  `tanggal` date NOT NULL,
  `uraian_kegiatan` varchar(255) NOT NULL,
  `nip` varchar(20) NOT NULL,
  `pelaksana` enum('Teknisi Rumah Sakit','Teknisi Rujukan','Pihak ke III') NOT NULL,
  `biaya` double NOT NULL,
  `jenis_pemeliharaan` enum('Running Maintenance','Shut Down Maintenance','Emergency Maintenance') NOT NULL,
  PRIMARY KEY (`no_inventaris`,`tanggal`),
  KEY `nip` (`nip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `pemeriksaan_ralan`
--

DROP TABLE IF EXISTS `pemeriksaan_ralan`;
CREATE TABLE IF NOT EXISTS `pemeriksaan_ralan` (
  `no_rawat` varchar(17) NOT NULL,
  `tgl_perawatan` date NOT NULL,
  `jam_rawat` time NOT NULL,
  `suhu_tubuh` varchar(5) DEFAULT NULL,
  `tensi` varchar(8) NOT NULL,
  `nadi` varchar(3) DEFAULT NULL,
  `respirasi` varchar(3) DEFAULT NULL,
  `tinggi` varchar(5) DEFAULT NULL,
  `berat` varchar(5) DEFAULT NULL,
  `spo2` varchar(3) NOT NULL,
  `gcs` varchar(10) DEFAULT NULL,
  `kesadaran` enum('Compos Mentis','Somnolence','Sopor','Coma') NOT NULL,
  `keluhan` text,
  `pemeriksaan` text,
  `alergi` varchar(50) DEFAULT NULL,
  `lingkar_perut` varchar(5) DEFAULT NULL,
  `rtl` text NOT NULL,
  `penilaian` text NOT NULL,
  `instruksi` text NOT NULL,
  `evaluasi` text NOT NULL,
  `nip` varchar(20) NOT NULL,
  PRIMARY KEY (`no_rawat`,`tgl_perawatan`,`jam_rawat`) USING BTREE,
  KEY `no_rawat` (`no_rawat`),
  KEY `nip` (`nip`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `pemeriksaan_ralan`
--

INSERT INTO `pemeriksaan_ralan` (`no_rawat`, `tgl_perawatan`, `jam_rawat`, `suhu_tubuh`, `tensi`, `nadi`, `respirasi`, `tinggi`, `berat`, `spo2`, `gcs`, `kesadaran`, `keluhan`, `pemeriksaan`, `alergi`, `lingkar_perut`, `rtl`, `penilaian`, `instruksi`, `evaluasi`, `nip`) VALUES
('2026/09/08/0001', '2026-09-08', '08:15:00', '36.8', '120/80', '80', '20', '165', '60', '98', '15', 'Compos Mentis', 'Keluhan pusing', 'Pemeriksaan dbn', 'Tidak Ada', '80', 'Istirahat', 'Cephalea', 'Edukasi', 'Membaik', 'PT001'),
('2026/09/08/0002', '2026-09-08', '08:30:00', '36.8', '120/80', '80', '20', '165', '60', '98', '15', 'Compos Mentis', 'Batuk berdahak sudah 4 hari, pilek dan tenggorokan terasa gatal agak demam', 'TD 110/75 mmHg, Nadi 80x/m, RR 20x/m, Suhu 37.8 C, BB 58 kg, TB 155 cm. Faring hiperemis (+).', 'Tidak Ada', '80', 'Istirahat cukup dan minum obat sesuai petunjuk', 'Acute Upper Respiratory Infection', 'Edukasi pola hidup sehat dan kontrol bila belum membaik', 'Pasien kooperatif dan memahami instruksi', 'PT001'),
('2026/09/08/0003', '2026-09-08', '09:00:00', '36.8', '120/80', '80', '20', '165', '60', '98', '15', 'Compos Mentis', 'Gigi geraham kanan bawah ngilu hebat saat minum dingin dan ada karang gigi tebal', 'Gigi 46 karies media disto-oklusal, kalkulus supra & subgingival regio anterior rahang bawah.', 'Tidak Ada', '80', 'Istirahat cukup dan minum obat sesuai petunjuk', 'Karies Gigi 46 & Calculus Dental', 'Edukasi pola hidup sehat dan kontrol bila belum membaik', 'Pasien kooperatif dan memahami instruksi', 'PT001'),
('2026/09/08/0004', '2026-09-08', '09:30:00', '36.8', '120/80', '80', '20', '165', '60', '98', '15', 'Compos Mentis', 'Kontrol kehamilan trimester 2, usia gestasi 24 minggu. Kadang merasa pegal di pinggang', 'TD 115/70 mmHg, Nadi 82x/m, BB 64 kg, TFU 20 cm, DJJ (+) 142 dpm reguler. Gerakan janin aktif.', 'Tidak Ada', '80', 'Istirahat cukup dan minum obat sesuai petunjuk', 'G1P0A0 Hamil 24 Minggu Single Live Intrauterine', 'Edukasi pola hidup sehat dan kontrol bila belum membaik', 'Pasien kooperatif dan memahami instruksi', 'PT001'),
('2026/09/08/0005', '2026-09-08', '10:00:00', '36.8', '120/80', '80', '20', '165', '60', '98', '15', 'Compos Mentis', 'Nyeri ulu hati seperti terbakar, mual, perut kembung dan sering sendawa asam', 'TD 125/80 mmHg, Nadi 76x/m, Nyeri tekan epigastrium (+), bising usus (+) normal.', 'Tidak Ada', '80', 'Istirahat cukup dan minum obat sesuai petunjuk', 'Dyspepsia Syndrome ec Suspek Gastritis Kronis', 'Edukasi pola hidup sehat dan kontrol bila belum membaik', 'Pasien kooperatif dan memahami instruksi', 'PT001'),
('2026/09/08/0006', '2026-09-08', '10:30:00', '36.8', '120/80', '80', '20', '165', '60', '98', '15', 'Compos Mentis', 'Kontrol rutin Diabetes Melitus, merasa cepat haus dan sering kencing saat malam hari', 'TD 130/80 mmHg, Nadi 78x/m, BB 66 kg, GDS sewaktu 210 mg/dL, HbA1c 7.4%.', 'Tidak Ada', '80', 'Istirahat cukup dan minum obat sesuai petunjuk', 'Type 2 Diabetes Mellitus Non Insulin Dependent', 'Edukasi pola hidup sehat dan kontrol bila belum membaik', 'Pasien kooperatif dan memahami instruksi', 'PT001'),
('2026/09/08/0007', '2026-09-08', '11:00:00', '36.8', '120/80', '80', '20', '165', '60', '98', '15', 'Compos Mentis', 'Anak demam sumeng-sumeng sejak 2 hari, batuk berdahak dan pilek, nafsu makan agak turun', 'Nadi 100x/m, RR 24x/m, Suhu 38.2 C, BB 24 kg, Tonsil T1-T1 tidak hiperemis, ronkhi (-/-).', 'Tidak Ada', '80', 'Istirahat cukup dan minum obat sesuai petunjuk', 'Common Cold pada Anak', 'Edukasi pola hidup sehat dan kontrol bila belum membaik', 'Pasien kooperatif dan memahami instruksi', 'PT001'),
('2026/09/08/0008', '2026-09-08', '11:30:00', '36.8', '120/80', '80', '20', '165', '60', '98', '15', 'Compos Mentis', 'BAB cair sudah 4 kali dari pagi, perut mulas melilit dan badan terasa lemas', 'TD 105/70 mmHg, Nadi 88x/m, Suhu 37.2 C, Turgor kulit baik, mata tidak cekung, BU (+) meningkat.', 'Tidak Ada', '80', 'Istirahat cukup dan minum obat sesuai petunjuk', 'Gastroenteritis Akut Dehidrasi Ringan-Sedang', 'Edukasi pola hidup sehat dan kontrol bila belum membaik', 'Pasien kooperatif dan memahami instruksi', 'PT001'),
('2026/09/08/0009', '2026-09-08', '12:00:00', '36.8', '120/80', '80', '20', '165', '60', '98', '15', 'Compos Mentis', 'Jempol kaki kanan bengkak merah dan sangat nyeri sejak tadi pagi bangun tidur setelah makan emping/seafood', 'MTP-1 Dextra: eritema (+), edema (+), kalor (+), nyeri tekan (+), ROM terbatas.', 'Tidak Ada', '80', 'Istirahat cukup dan minum obat sesuai petunjuk', 'Gout Arthritis Akut Serangan Pertama', 'Edukasi pola hidup sehat dan kontrol bila belum membaik', 'Pasien kooperatif dan memahami instruksi', 'PT001'),
('2026/09/08/0010', '2026-09-08', '12:30:00', '36.8', '120/80', '80', '20', '165', '60', '98', '15', 'Compos Mentis', 'Lecet dan luka robek dangkal di lutut kanan akibat terserempet motor saat menyebrang', 'Luka ekskoriasi dan laserasi superficial lutut kanan uk 3x2 cm, kotor (+), perdarahan aktif (-).', 'Tidak Ada', '80', 'Istirahat cukup dan minum obat sesuai petunjuk', 'Vulnus Laceratum Regio Genu Dextra', 'Edukasi pola hidup sehat dan kontrol bila belum membaik', 'Pasien kooperatif dan memahami instruksi', 'PT001');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pemeriksaan_ranap`
--

DROP TABLE IF EXISTS `pemeriksaan_ranap`;
CREATE TABLE IF NOT EXISTS `pemeriksaan_ranap` (
  `no_rawat` varchar(17) NOT NULL,
  `tgl_perawatan` date NOT NULL,
  `jam_rawat` time NOT NULL,
  `suhu_tubuh` varchar(5) DEFAULT NULL,
  `tensi` varchar(8) NOT NULL,
  `nadi` varchar(3) DEFAULT NULL,
  `respirasi` varchar(3) DEFAULT NULL,
  `tinggi` varchar(5) DEFAULT NULL,
  `berat` varchar(5) DEFAULT NULL,
  `spo2` varchar(3) NOT NULL,
  `gcs` varchar(10) DEFAULT NULL,
  `kesadaran` enum('Compos Mentis','Somnolence','Sopor','Coma') NOT NULL,
  `keluhan` text,
  `pemeriksaan` text,
  `alergi` varchar(50) DEFAULT NULL,
  `penilaian` text NOT NULL,
  `rtl` text NOT NULL,
  `instruksi` text NOT NULL,
  `evaluasi` text NOT NULL,
  `nip` varchar(20) NOT NULL,
  PRIMARY KEY (`no_rawat`,`tgl_perawatan`,`jam_rawat`),
  KEY `no_rawat` (`no_rawat`),
  KEY `nip` (`nip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `pendidikan`
--

DROP TABLE IF EXISTS `pendidikan`;
CREATE TABLE IF NOT EXISTS `pendidikan` (
  `tingkat` varchar(80) NOT NULL,
  `indek` tinyint NOT NULL,
  `gapok1` double NOT NULL,
  `kenaikan` double NOT NULL,
  `maksimal` int NOT NULL,
  PRIMARY KEY (`tingkat`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `pendidikan`
--

INSERT INTO `pendidikan` (`tingkat`, `indek`, `gapok1`, `kenaikan`, `maksimal`) VALUES
('-', 1, 0, 0, 1),
('D3', 3, 2500000, 150000, 5000000),
('D3 Rekam Medis', 5, 3800000, 150000, 8000000),
('Profesi Dokter', 2, 7000000, 350000, 15000000),
('S1', 2, 4000000, 200000, 8000000),
('S1 / Profesi Dokter', 2, 4000000, 200000, 8000000),
('S1 Ekonomi / Kasir', 6, 3800000, 150000, 8000000),
('S1 Farmasi / Apt', 4, 5000000, 250000, 12000000),
('S1 Keperawatan / Ners', 3, 4500000, 200000, 10000000),
('S2', 1, 5000000, 250000, 10000000),
('SMA', 4, 2000000, 100000, 4000000),
('SMA/SMK', 4, 2000000, 100000, 4000000),
('Spesialis', 1, 10000000, 500000, 20000000);

-- --------------------------------------------------------

--
-- Struktur dari tabel `penilaian_awal_keperawatan_igd`
--

DROP TABLE IF EXISTS `penilaian_awal_keperawatan_igd`;
CREATE TABLE IF NOT EXISTS `penilaian_awal_keperawatan_igd` (
  `no_rawat` varchar(17) NOT NULL,
  `tanggal` datetime NOT NULL,
  `informasi` enum('Autoanamnesis','Alloanamnesis') NOT NULL,
  `keluhan_utama` text NOT NULL,
  `rpd` text NOT NULL,
  `rpo` text NOT NULL,
  `status_kehamilan` enum('Tidak Hamil','Hamil') NOT NULL,
  `gravida` varchar(20) DEFAULT NULL,
  `para` varchar(20) DEFAULT NULL,
  `abortus` varchar(20) DEFAULT NULL,
  `hpht` varchar(20) DEFAULT NULL,
  `tekanan` enum('TAK','Sakit Kepala','Muntah','Pusing','Bingung') NOT NULL,
  `pupil` enum('Normal','Miosis','Isokor','Anisokor') NOT NULL,
  `neurosensorik` enum('TAK','Spasme Otot','Perubahan Sensorik','Perubahan Motorik','Perubahan Bentuk Ekstremitas','Penurunan Tingkat Kesadaran','Fraktur/Dislokasi','Luksasio','Kerusakan Jaringan/Luka') NOT NULL,
  `integumen` enum('TAK','Luka Bakar','Luka Robek','Lecet','Luka Decubitus','Luka Gangren') NOT NULL,
  `turgor` enum('Baik','Menurun') NOT NULL,
  `edema` enum('Tidak Ada','Ekstremitas','Seluruh Tubuh','Asites','Palpebrae') NOT NULL,
  `mukosa` enum('Lembab','Kering') NOT NULL,
  `perdarahan` enum('Tidak Ada','Ada') NOT NULL,
  `jumlah_perdarahan` char(5) DEFAULT NULL,
  `warna_perdarahan` varchar(40) DEFAULT '',
  `intoksikasi` enum('Tidak Ada','Ada','Gigitan Binatang','Zat Kimia','Gas','Obat') NOT NULL,
  `bab` char(2) DEFAULT NULL,
  `xbab` varchar(10) DEFAULT NULL,
  `kbab` varchar(40) DEFAULT NULL,
  `wbab` varchar(40) DEFAULT NULL,
  `bak` char(2) DEFAULT NULL,
  `xbak` varchar(10) DEFAULT NULL,
  `wbak` varchar(40) DEFAULT '',
  `lbak` varchar(40) DEFAULT '',
  `psikologis` enum('Tidak Ada Masalah','Marah','Takut','Depresi','Cepat Lelah','Cemas','Gelisah','Lain-lain') NOT NULL,
  `jiwa` enum('Ya','Tidak') NOT NULL,
  `perilaku` enum('Perilaku Kekerasan','Gangguan Efek','Gangguan Memori','Halusinasi','Kecenderungan Percobaan Bunuh Diri','Lainnya','-') NOT NULL,
  `dilaporkan` varchar(50) DEFAULT NULL,
  `sebutkan` varchar(50) DEFAULT NULL,
  `hubungan` enum('Harmonis','Kurang Harmonis','Tidak Harmonis','Konflik Besar') NOT NULL,
  `tinggal_dengan` enum('Sendiri','Orang Tua','Suami / Istri','Lainnya') NOT NULL,
  `ket_tinggal` varchar(50) DEFAULT '',
  `budaya` enum('Tidak Ada','Ada') NOT NULL,
  `ket_budaya` varchar(50) NOT NULL,
  `pendidikan_pj` enum('-','TS','TK','SD','SMP','SMA','SLTA/SEDERAJAT','D1','D2','D3','D4','S1','S2','S3') NOT NULL,
  `ket_pendidikan_pj` varchar(50) DEFAULT NULL,
  `edukasi` enum('Pasien','Keluarga') NOT NULL,
  `ket_edukasi` varchar(50) NOT NULL,
  `kemampuan` enum('Mandiri','Bantuan Minimal','Bantuan Sebagian','Ketergantungan Total') NOT NULL,
  `aktifitas` enum('Tirah Baring','Duduk','Berjalan') NOT NULL,
  `alat_bantu` enum('Tidak','Ya') NOT NULL,
  `ket_bantu` varchar(50) DEFAULT '',
  `nyeri` enum('Tidak Ada Nyeri','Nyeri Akut','Nyeri Kronis') NOT NULL,
  `provokes` enum('Proses Penyakit','Benturan','Lain-lain') NOT NULL,
  `ket_provokes` varchar(40) NOT NULL,
  `quality` enum('Seperti Tertusuk','Berdenyut','Teriris','Tertindih','Tertiban','Lain-lain') NOT NULL,
  `ket_quality` varchar(50) NOT NULL,
  `lokasi` varchar(50) NOT NULL,
  `menyebar` enum('Tidak','Ya') NOT NULL,
  `skala_nyeri` enum('0','1','2','3','4','5','6','7','8','9','10') NOT NULL,
  `durasi` varchar(25) NOT NULL,
  `nyeri_hilang` enum('Istirahat','Medengar Musik','Minum Obat') NOT NULL,
  `ket_nyeri` varchar(40) DEFAULT NULL,
  `pada_dokter` enum('Tidak','Ya') NOT NULL,
  `ket_dokter` varchar(15) DEFAULT NULL,
  `berjalan_a` enum('Ya','Tidak') NOT NULL,
  `berjalan_b` enum('Ya','Tidak') NOT NULL,
  `berjalan_c` enum('Ya','Tidak') NOT NULL,
  `hasil` enum('Tidak beresiko (tidak ditemukan a dan b)','Resiko rendah (ditemukan a/b)','Resiko tinggi (ditemukan a dan b)') NOT NULL,
  `lapor` enum('Ya','Tidak') NOT NULL,
  `ket_lapor` varchar(15) DEFAULT NULL,
  `rencana` text NOT NULL,
  `nip` varchar(20) NOT NULL,
  PRIMARY KEY (`no_rawat`) USING BTREE,
  KEY `nip` (`nip`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `penilaian_awal_keperawatan_ralan`
--

DROP TABLE IF EXISTS `penilaian_awal_keperawatan_ralan`;
CREATE TABLE IF NOT EXISTS `penilaian_awal_keperawatan_ralan` (
  `no_rawat` varchar(17) NOT NULL,
  `tanggal` datetime NOT NULL,
  `informasi` enum('Autoanamnesis','Alloanamnesis') NOT NULL,
  `td` varchar(8) NOT NULL DEFAULT '',
  `nadi` varchar(5) NOT NULL DEFAULT '',
  `rr` varchar(5) NOT NULL,
  `suhu` varchar(5) NOT NULL DEFAULT '',
  `gcs` varchar(5) NOT NULL,
  `bb` varchar(5) NOT NULL DEFAULT '',
  `tb` varchar(5) NOT NULL DEFAULT '',
  `bmi` varchar(10) NOT NULL,
  `keluhan_utama` varchar(150) NOT NULL DEFAULT '',
  `rpd` varchar(100) NOT NULL DEFAULT '',
  `rpk` varchar(100) NOT NULL,
  `rpo` varchar(100) NOT NULL,
  `alergi` varchar(25) NOT NULL DEFAULT '',
  `alat_bantu` enum('Tidak','Ya') NOT NULL,
  `ket_bantu` varchar(50) NOT NULL DEFAULT '',
  `prothesa` enum('Tidak','Ya') NOT NULL,
  `ket_pro` varchar(50) NOT NULL,
  `adl` enum('Mandiri','Dibantu') NOT NULL,
  `status_psiko` enum('Tenang','Takut','Cemas','Depresi','Lain-lain') NOT NULL,
  `ket_psiko` varchar(70) NOT NULL,
  `hub_keluarga` enum('Baik','Tidak Baik') NOT NULL,
  `tinggal_dengan` enum('Sendiri','Orang Tua','Suami / Istri','Lainnya') NOT NULL,
  `ket_tinggal` varchar(40) NOT NULL,
  `ekonomi` enum('Baik','Cukup','Kurang') NOT NULL,
  `budaya` enum('Tidak Ada','Ada') NOT NULL,
  `ket_budaya` varchar(50) NOT NULL,
  `edukasi` enum('Pasien','Keluarga') NOT NULL,
  `ket_edukasi` varchar(50) NOT NULL,
  `berjalan_a` enum('Ya','Tidak') NOT NULL,
  `berjalan_b` enum('Ya','Tidak') NOT NULL,
  `berjalan_c` enum('Ya','Tidak') NOT NULL,
  `hasil` enum('Tidak beresiko (tidak ditemukan a dan b)','Resiko rendah (ditemukan a/b)','Resiko tinggi (ditemukan a dan b)') NOT NULL,
  `lapor` enum('Ya','Tidak') NOT NULL,
  `ket_lapor` varchar(15) NOT NULL,
  `sg1` enum('Tidak','Tidak Yakin','Ya, 1-5 Kg','Ya, 6-10 Kg','Ya, 11-15 Kg','Ya, >15 Kg') NOT NULL,
  `nilai1` enum('0','1','2','3','4') NOT NULL,
  `sg2` enum('Ya','Tidak') NOT NULL,
  `nilai2` enum('0','1') NOT NULL,
  `total_hasil` tinyint NOT NULL,
  `nyeri` enum('Tidak Ada Nyeri','Nyeri Akut','Nyeri Kronis') NOT NULL,
  `provokes` enum('Proses Penyakit','Benturan','Lain-lain') NOT NULL,
  `ket_provokes` varchar(40) NOT NULL,
  `quality` enum('Seperti Tertusuk','Berdenyut','Teriris','Tertindih','Tertiban','Lain-lain') NOT NULL,
  `ket_quality` varchar(50) NOT NULL,
  `lokasi` varchar(50) NOT NULL,
  `menyebar` enum('Tidak','Ya') NOT NULL,
  `skala_nyeri` enum('0','1','2','3','4','5','6','7','8','9','10') NOT NULL,
  `durasi` varchar(25) NOT NULL,
  `nyeri_hilang` enum('Istirahat','Medengar Musik','Minum Obat') NOT NULL,
  `ket_nyeri` varchar(40) NOT NULL,
  `pada_dokter` enum('Tidak','Ya') NOT NULL,
  `ket_dokter` varchar(15) NOT NULL,
  `rencana` varchar(200) NOT NULL,
  `nip` varchar(20) NOT NULL,
  PRIMARY KEY (`no_rawat`) USING BTREE,
  KEY `nip` (`nip`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `penilaian_awal_keperawatan_ranap`
--

DROP TABLE IF EXISTS `penilaian_awal_keperawatan_ranap`;
CREATE TABLE IF NOT EXISTS `penilaian_awal_keperawatan_ranap` (
  `no_rawat` varchar(17) NOT NULL,
  `tanggal` datetime NOT NULL,
  `informasi` enum('Autoanamnesis','Alloanamnesis') NOT NULL,
  `ket_informasi` varchar(30) NOT NULL,
  `tiba_diruang_rawat` enum('Jalan Tanpa Bantuan','Kursi Roda','Brankar') NOT NULL,
  `kasus_trauma` enum('Trauma','Non Trauma') DEFAULT NULL,
  `cara_masuk` enum('Poli','IGD','Lain-lain') NOT NULL,
  `rps` text NOT NULL,
  `rpd` text NOT NULL,
  `rpk` text NOT NULL,
  `rpo` text NOT NULL,
  `riwayat_pembedahan` text NOT NULL,
  `riwayat_dirawat_dirs` text NOT NULL,
  `alat_bantu_dipakai` enum('Kacamata','Prothesa','Alat Bantu Dengar','Lain-lain') NOT NULL,
  `riwayat_kehamilan` enum('Tidak','Ya') NOT NULL,
  `riwayat_kehamilan_perkiraan` text NOT NULL,
  `riwayat_tranfusi` text NOT NULL,
  `riwayat_alergi` text NOT NULL,
  `riwayat_merokok` enum('Tidak','Ya') NOT NULL,
  `riwayat_merokok_jumlah` varchar(5) NOT NULL,
  `riwayat_alkohol` enum('Tidak','Ya') NOT NULL,
  `riwayat_alkohol_jumlah` varchar(5) NOT NULL,
  `riwayat_narkoba` enum('Tidak','Ya') NOT NULL,
  `riwayat_olahraga` enum('Tidak','Ya') NOT NULL,
  `pemeriksaan_mental` text NOT NULL,
  `pemeriksaan_keadaan_umum` enum('Baik','Sedang','Buruk') NOT NULL,
  `pemeriksaan_gcs` varchar(10) NOT NULL,
  `pemeriksaan_td` varchar(8) NOT NULL,
  `pemeriksaan_nadi` varchar(5) NOT NULL,
  `pemeriksaan_rr` varchar(5) NOT NULL,
  `pemeriksaan_suhu` varchar(5) NOT NULL,
  `pemeriksaan_spo2` varchar(5) NOT NULL,
  `pemeriksaan_bb` varchar(5) NOT NULL,
  `pemeriksaan_tb` varchar(5) NOT NULL,
  `pemeriksaan_susunan_kepala` enum('TAK','Hydrocephalus','Hematoma','Lain-lain') NOT NULL,
  `pemeriksaan_susunan_kepala_keterangan` text NOT NULL,
  `pemeriksaan_susunan_wajah` enum('TAK','Asimetris','Kelainan Kongenital') NOT NULL,
  `pemeriksaan_susunan_wajah_keterangan` text NOT NULL,
  `pemeriksaan_susunan_leher` enum('TAK','Kaku Kuduk','Pembesaran Thyroid','Pembesaran KGB') NOT NULL,
  `pemeriksaan_susunan_kejang` enum('TAK','Kuat','Ada') NOT NULL,
  `pemeriksaan_susunan_kejang_keterangan` text NOT NULL,
  `pemeriksaan_susunan_sensorik` enum('TAK','Sakit Nyeri','Rasa kebas') NOT NULL,
  `pemeriksaan_kardiovaskuler_denyut_nadi` enum('Teratur','Tidak Teratur') NOT NULL,
  `pemeriksaan_kardiovaskuler_sirkulasi` enum('Akral Hangat','Akral Dingin','Edema') NOT NULL,
  `pemeriksaan_kardiovaskuler_sirkulasi_keterangan` text NOT NULL,
  `pemeriksaan_kardiovaskuler_pulsasi` enum('Kuat','Lemah','Lain-lain') NOT NULL,
  `pemeriksaan_respirasi_pola_nafas` enum('Normal','Bradipnea','Tachipnea') NOT NULL,
  `pemeriksaan_respirasi_retraksi` enum('Tidak Ada','Ringan','Berat') NOT NULL,
  `pemeriksaan_respirasi_suara_nafas` enum('Vesikuler','Wheezing','Rhonki') NOT NULL,
  `pemeriksaan_respirasi_volume_pernafasan` enum('Normal','Hiperventilasi','Hipoventilasi') NOT NULL,
  `pemeriksaan_respirasi_jenis_pernafasan` enum('Pernafasan Dada','Alat Bantu Pernafasaan') NOT NULL,
  `pemeriksaan_respirasi_jenis_pernafasan_keterangan` text NOT NULL,
  `pemeriksaan_respirasi_irama_nafas` enum('Teratur','Tidak Teratur') NOT NULL,
  `pemeriksaan_respirasi_batuk` enum('Tidak','Ya : Produktif','Ya : Non Produktif') NOT NULL,
  `pemeriksaan_gastrointestinal_mulut` enum('TAK','Stomatitis','Mukosa Kering','Bibir Pucat','Lain-lain') NOT NULL,
  `pemeriksaan_gastrointestinal_mulut_keterangan` text NOT NULL,
  `pemeriksaan_gastrointestinal_gigi` enum('TAK','Karies','Goyang','Lain-lain') NOT NULL,
  `pemeriksaan_gastrointestinal_gigi_keterangan` text NOT NULL,
  `pemeriksaan_gastrointestinal_lidah` enum('TAK','Kotor','Gerak Asimetris','Lain-lain') NOT NULL,
  `pemeriksaan_gastrointestinal_lidah_keterangan` text NOT NULL,
  `pemeriksaan_gastrointestinal_tenggorokan` enum('TAK','Gangguan Menelan','Sakit Menelan','Lain-lain') NOT NULL,
  `pemeriksaan_gastrointestinal_tenggorokan_keterangan` text NOT NULL,
  `pemeriksaan_gastrointestinal_abdomen` enum('Supel','Asictes',' Tegang','Nyeri Tekan/Lepas','Lain-lain') NOT NULL,
  `pemeriksaan_gastrointestinal_abdomen_keterangan` text NOT NULL,
  `pemeriksaan_gastrointestinal_peistatik_usus` enum('TAK','Tidak Ada Bising Usus','Hiperistaltik') NOT NULL,
  `pemeriksaan_gastrointestinal_anus` enum('TAK','Atresia Ani') NOT NULL,
  `pemeriksaan_neurologi_pengelihatan` enum('TAK','Ada Kelainan') NOT NULL,
  `pemeriksaan_neurologi_pengelihatan_keterangan` text NOT NULL,
  `pemeriksaan_neurologi_alat_bantu_penglihatan` enum('Tidak','Kacamata','Lensa Kontak') NOT NULL,
  `pemeriksaan_neurologi_pendengaran` enum('TAK','Berdengung','Nyeri','Tuli','Keluar Cairan','Lain-lain') NOT NULL,
  `pemeriksaan_neurologi_bicara` enum('Jelas','Tidak Jelas') NOT NULL,
  `pemeriksaan_neurologi_bicara_keterangan` text NOT NULL,
  `pemeriksaan_neurologi_sensorik` enum('TAK','Sakit Nyeri','Rasa Kebas','Lain-lain') NOT NULL,
  `pemeriksaan_neurologi_motorik` enum('TAK','Hemiparese','Tetraparese','Tremor','Lain-lain') NOT NULL,
  `pemeriksaan_neurologi_kekuatan_otot` enum('Kuat','Lemah') NOT NULL,
  `pemeriksaan_integument_warnakulit` enum('Pucat','Sianosis','Normal','Lain-lain') NOT NULL,
  `pemeriksaan_integument_turgor` enum('Baik','Sedang','Buruk') NOT NULL,
  `pemeriksaan_integument_kulit` enum('Normal','Rash/Kemerahan','Luka','Memar','Ptekie','Bula') NOT NULL,
  `pemeriksaan_integument_dekubitas` enum('Tidak Ada','Usia > 65 tahun','Obesitas','Imobilisasi','Paraplegi/Vegetative State','Dirawat Di HCU','Penyakit Kronis (DM, CHF, CKD)','Inkontinentia Uri/Alvi') NOT NULL,
  `pemeriksaan_muskuloskletal_pergerakan_sendi` enum('Bebas','Terbatas') NOT NULL,
  `pemeriksaan_muskuloskletal_kekauatan_otot` enum('Baik','Lemah','Tremor') NOT NULL,
  `pemeriksaan_muskuloskletal_nyeri_sendi` enum('Tidak Ada','Ada') NOT NULL,
  `pemeriksaan_muskuloskletal_nyeri_sendi_keterangan` text NOT NULL,
  `pemeriksaan_muskuloskletal_oedema` enum('Tidak Ada','Ada') NOT NULL,
  `pemeriksaan_muskuloskletal_oedema_keterangan` text NOT NULL,
  `pemeriksaan_muskuloskletal_fraktur` enum('Tidak Ada','Ada') NOT NULL,
  `pemeriksaan_muskuloskletal_fraktur_keterangan` text NOT NULL,
  `pemeriksaan_eliminasi_bab_frekuensi_jumlah` varchar(5) NOT NULL,
  `pemeriksaan_eliminasi_bab_frekuensi_durasi` varchar(10) NOT NULL,
  `pemeriksaan_eliminasi_bab_konsistensi` varchar(30) NOT NULL,
  `pemeriksaan_eliminasi_bab_warna` varchar(30) NOT NULL,
  `pemeriksaan_eliminasi_bak_frekuensi_jumlah` varchar(5) NOT NULL,
  `pemeriksaan_eliminasi_bak_frekuensi_durasi` varchar(10) NOT NULL,
  `pemeriksaan_eliminasi_bak_warna` varchar(30) NOT NULL,
  `pemeriksaan_eliminasi_bak_lainlain` varchar(30) NOT NULL,
  `pola_aktifitas_makanminum` enum('Mandiri','Bantuan Orang Lain') NOT NULL,
  `pola_aktifitas_mandi` enum('Mandiri','Bantuan Orang Lain') NOT NULL,
  `pola_aktifitas_eliminasi` enum('Mandiri','Bantuan Orang Lain') NOT NULL,
  `pola_aktifitas_berpakaian` enum('Mandiri','Bantuan Orang Lain') NOT NULL,
  `pola_aktifitas_berpindah` enum('Mandiri','Bantuan Orang Lain') NOT NULL,
  `pola_nutrisi_frekuesi_makan` varchar(3) NOT NULL,
  `pola_nutrisi_jenis_makanan` varchar(20) NOT NULL,
  `pola_nutrisi_porsi_makan` varchar(3) NOT NULL,
  `pola_tidur_lama_tidur` varchar(3) NOT NULL,
  `pola_tidur_gangguan` enum('Tidak Ada Gangguan','Insomnia') NOT NULL,
  `pengkajian_fungsi_kemampuan_sehari` enum('Mandiri','Bantuan Minimal','Bantuan Sebagian','Ketergantungan Total') NOT NULL,
  `pengkajian_fungsi_aktifitas` enum('Tirah Baring','Duduk','Berjalan') NOT NULL,
  `pengkajian_fungsi_berjalan` enum('TAK','Penurunan Kekuatan/ROM','Paralisis','Sering Jatuh','Deformitas','Hilang Keseimbangan','Riwayat Patah Tulang','Lain-lain') NOT NULL,
  `pengkajian_fungsi_berjalan_keterangan` text NOT NULL,
  `pengkajian_fungsi_ambulasi` enum('Walker','Tongkat','Kursi Roda','Tidak Menggunakan') NOT NULL,
  `pengkajian_fungsi_ekstrimitas_atas` enum('TAK','Lemah','Oedema','Tidak Simetris','Lain-lain') NOT NULL,
  `pengkajian_fungsi_ekstrimitas_atas_keterangan` text NOT NULL,
  `pengkajian_fungsi_ekstrimitas_bawah` enum('TAK','Varises','Oedema','Tidak Simetris','Lain-lain') NOT NULL,
  `pengkajian_fungsi_ekstrimitas_bawah_keterangan` text NOT NULL,
  `pengkajian_fungsi_menggenggam` enum('Tidak Ada Kesulitan','Terakhir','Lain-lain') NOT NULL,
  `pengkajian_fungsi_menggenggam_keterangan` text NOT NULL,
  `pengkajian_fungsi_koordinasi` enum('Tidak Ada Kesulitan','Ada Masalah') NOT NULL,
  `pengkajian_fungsi_koordinasi_keterangan` text NOT NULL,
  `pengkajian_fungsi_kesimpulan` enum('Ya (Co DPJP)','Tidak (Tidak Perlu Co DPJP)') NOT NULL,
  `riwayat_psiko_kondisi_psiko` enum('Tidak Ada Masalah','Marah','Takut','Depresi','Cepat Lelah','Cemas','Gelisah','Sulit Tidur','Lain-lain') NOT NULL,
  `riwayat_psiko_gangguan_jiwa` enum('Ya','Tidak') NOT NULL,
  `riwayat_psiko_perilaku` enum('Tidak Ada Masalah','Perilaku Kekerasan','Gangguan Efek','Gangguan Memori','Halusinasi','Kecenderungan Percobaan Bunuh Diri','Lain-lain') NOT NULL,
  `riwayat_psiko_perilaku_keterangan` text NOT NULL,
  `riwayat_psiko_hubungan_keluarga` enum('Harmonis','Kurang Harmonis','Tidak Harmonis','Konflik Besar') NOT NULL,
  `riwayat_psiko_tinggal` enum('Sendiri','Orang Tua','Suami/Istri','Keluarga','Lain-lain') NOT NULL,
  `riwayat_psiko_tinggal_keterangan` text NOT NULL,
  `riwayat_psiko_nilai_kepercayaan` enum('Tidak Ada','Ada') NOT NULL,
  `riwayat_psiko_nilai_kepercayaan_keterangan` text NOT NULL,
  `riwayat_psiko_pendidikan_pj` enum('-','TS','TK','SD','SMP','SMA','SLTA/SEDERAJAT','D1','D2','D3','D4','S1','S2','S3') NOT NULL,
  `riwayat_psiko_edukasi_diberikan` enum('Pasien','Keluarga') NOT NULL,
  `riwayat_psiko_edukasi_diberikan_keterangan` text NOT NULL,
  `penilaian_nyeri` enum('Tidak Ada Nyeri','Nyeri Akut','Nyeri Kronis') NOT NULL,
  `penilaian_nyeri_penyebab` enum('Proses Penyakit','Benturan','Lain-lain') NOT NULL,
  `penilaian_nyeri_ket_penyebab` text NOT NULL,
  `penilaian_nyeri_kualitas` enum('Seperti Tertusuk','Berdenyut','Teriris','Tertindih','Tertiban','Lain-lain') NOT NULL,
  `penilaian_nyeri_ket_kualitas` text NOT NULL,
  `penilaian_nyeri_lokasi` text NOT NULL,
  `penilaian_nyeri_menyebar` enum('Tidak','Ya') NOT NULL,
  `penilaian_nyeri_skala` enum('0','1','2','3','4','5','6','7','8','9','10') NOT NULL,
  `penilaian_nyeri_waktu` varchar(5) NOT NULL,
  `penilaian_nyeri_hilang` enum('Istirahat','Medengar Musik','Minum Obat') NOT NULL,
  `penilaian_nyeri_ket_hilang` text NOT NULL,
  `penilaian_nyeri_diberitahukan_dokter` enum('Tidak','Ya') NOT NULL,
  `penilaian_nyeri_jam_diberitahukan_dokter` varchar(10) NOT NULL,
  `penilaian_jatuhmorse_skala1` enum('Tidak','Ya') DEFAULT NULL,
  `penilaian_jatuhmorse_nilai1` tinyint DEFAULT NULL,
  `penilaian_jatuhmorse_skala2` enum('Tidak','Ya') DEFAULT NULL,
  `penilaian_jatuhmorse_nilai2` tinyint DEFAULT NULL,
  `penilaian_jatuhmorse_skala3` enum('Tidak Ada/Kursi Roda/Perawat/Tirah Baring','Tongkat/Alat Penopang','Berpegangan Pada Perabot') DEFAULT NULL,
  `penilaian_jatuhmorse_nilai3` tinyint DEFAULT NULL,
  `penilaian_jatuhmorse_skala4` enum('Tidak','Ya') DEFAULT NULL,
  `penilaian_jatuhmorse_nilai4` tinyint DEFAULT NULL,
  `penilaian_jatuhmorse_skala5` enum('Normal/Tirah Baring/Imobilisasi','Lemah','Terganggu') DEFAULT NULL,
  `penilaian_jatuhmorse_nilai5` tinyint DEFAULT NULL,
  `penilaian_jatuhmorse_skala6` enum('Sadar Akan Kemampuan Diri Sendiri','Sering Lupa Akan Keterbatasan Yang Dimiliki') DEFAULT NULL,
  `penilaian_jatuhmorse_nilai6` tinyint DEFAULT NULL,
  `penilaian_jatuhmorse_totalnilai` tinyint DEFAULT NULL,
  `penilaian_jatuhsydney_skala1` enum('Tidak','Ya') DEFAULT NULL,
  `penilaian_jatuhsydney_nilai1` tinyint DEFAULT NULL,
  `penilaian_jatuhsydney_skala2` enum('Tidak','Ya') DEFAULT NULL,
  `penilaian_jatuhsydney_nilai2` tinyint DEFAULT NULL,
  `penilaian_jatuhsydney_skala3` enum('Tidak','Ya') DEFAULT NULL,
  `penilaian_jatuhsydney_nilai3` tinyint DEFAULT NULL,
  `penilaian_jatuhsydney_skala4` enum('Tidak','Ya') DEFAULT NULL,
  `penilaian_jatuhsydney_nilai4` tinyint DEFAULT NULL,
  `penilaian_jatuhsydney_skala5` enum('Tidak','Ya') DEFAULT NULL,
  `penilaian_jatuhsydney_nilai5` tinyint DEFAULT NULL,
  `penilaian_jatuhsydney_skala6` enum('Tidak','Ya') DEFAULT NULL,
  `penilaian_jatuhsydney_nilai6` tinyint DEFAULT NULL,
  `penilaian_jatuhsydney_skala7` enum('Tidak','Ya') DEFAULT NULL,
  `penilaian_jatuhsydney_nilai7` tinyint DEFAULT NULL,
  `penilaian_jatuhsydney_skala8` enum('Tidak','Ya') DEFAULT NULL,
  `penilaian_jatuhsydney_nilai8` tinyint DEFAULT NULL,
  `penilaian_jatuhsydney_skala9` enum('Tidak','Ya') DEFAULT NULL,
  `penilaian_jatuhsydney_nilai9` tinyint DEFAULT NULL,
  `penilaian_jatuhsydney_skala10` enum('Tidak','Ya') DEFAULT NULL,
  `penilaian_jatuhsydney_nilai10` tinyint DEFAULT NULL,
  `penilaian_jatuhsydney_skala11` enum('Tidak','Ya') DEFAULT NULL,
  `penilaian_jatuhsydney_nilai11` tinyint DEFAULT NULL,
  `penilaian_jatuhsydney_totalnilai` tinyint DEFAULT NULL,
  `skrining_gizi1` enum('Tidak ada penurunan berat badan','Tidak yakin/ tidak tahu/ terasa baju lebih longgar','Ya 1-5 kg','Ya 6-10 kg','Ya 11-15 kg','Ya > 15 kg') DEFAULT NULL,
  `nilai_gizi1` int DEFAULT NULL,
  `skrining_gizi2` enum('Tidak','Ya') DEFAULT NULL,
  `nilai_gizi2` int DEFAULT NULL,
  `nilai_total_gizi` double DEFAULT NULL,
  `skrining_gizi_diagnosa_khusus` enum('Tidak','Ya') DEFAULT NULL,
  `skrining_gizi_ket_diagnosa_khusus` text,
  `skrining_gizi_diketahui_dietisen` enum('Tidak','Ya') DEFAULT NULL,
  `skrining_gizi_jam_diketahui_dietisen` varchar(10) DEFAULT NULL,
  `rencana` text,
  `nip1` varchar(20) NOT NULL,
  `nip2` varchar(20) NOT NULL,
  `kd_dokter` varchar(20) NOT NULL,
  PRIMARY KEY (`no_rawat`) USING BTREE,
  KEY `nip1` (`nip1`) USING BTREE,
  KEY `nip2` (`nip2`) USING BTREE,
  KEY `kd_dokter` (`kd_dokter`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `penilaian_medis_igd`
--

DROP TABLE IF EXISTS `penilaian_medis_igd`;
CREATE TABLE IF NOT EXISTS `penilaian_medis_igd` (
  `no_rawat` varchar(17) NOT NULL,
  `tanggal` datetime NOT NULL,
  `kd_dokter` varchar(20) NOT NULL,
  `anamnesis` enum('Autoanamnesis','Alloanamnesis') NOT NULL,
  `hubungan` varchar(100) NOT NULL,
  `keluhan_utama` text NOT NULL,
  `rps` text NOT NULL,
  `rpd` text NOT NULL,
  `rpk` text NOT NULL,
  `rpo` text NOT NULL,
  `alergi` varchar(100) NOT NULL DEFAULT '',
  `keadaan` enum('Sehat','Sakit Ringan','Sakit Sedang','Sakit Berat') NOT NULL,
  `gcs` varchar(10) NOT NULL,
  `kesadaran` enum('Compos Mentis','Apatis','Somnolen','Sopor','Koma') NOT NULL,
  `td` varchar(8) NOT NULL DEFAULT '',
  `nadi` varchar(5) NOT NULL DEFAULT '',
  `rr` varchar(5) NOT NULL,
  `suhu` varchar(5) NOT NULL DEFAULT '',
  `spo` varchar(5) NOT NULL,
  `bb` varchar(5) NOT NULL DEFAULT '',
  `tb` varchar(5) NOT NULL DEFAULT '',
  `kepala` enum('Normal','Abnormal','Tidak Diperiksa') NOT NULL,
  `mata` enum('Normal','Abnormal','Tidak Diperiksa') NOT NULL,
  `gigi` enum('Normal','Abnormal','Tidak Diperiksa') NOT NULL,
  `leher` enum('Normal','Abnormal','Tidak Diperiksa') NOT NULL,
  `thoraks` enum('Normal','Abnormal','Tidak Diperiksa') NOT NULL,
  `abdomen` enum('Normal','Abnormal','Tidak Diperiksa') NOT NULL,
  `genital` enum('Normal','Abnormal','Tidak Diperiksa') NOT NULL,
  `ekstremitas` enum('Normal','Abnormal','Tidak Diperiksa') NOT NULL,
  `ket_fisik` text NOT NULL,
  `ket_lokalis` text NOT NULL,
  `ekg` text NOT NULL,
  `rad` text NOT NULL,
  `lab` text NOT NULL,
  `diagnosis` text NOT NULL,
  `tata` text NOT NULL,
  PRIMARY KEY (`no_rawat`) USING BTREE,
  KEY `kd_dokter` (`kd_dokter`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `penilaian_medis_ralan`
--

DROP TABLE IF EXISTS `penilaian_medis_ralan`;
CREATE TABLE IF NOT EXISTS `penilaian_medis_ralan` (
  `no_rawat` varchar(17) NOT NULL,
  `tanggal` datetime NOT NULL,
  `kd_dokter` varchar(20) NOT NULL,
  `anamnesis` enum('Autoanamnesis','Alloanamnesis') NOT NULL,
  `hubungan` varchar(30) NOT NULL,
  `keluhan_utama` text NOT NULL,
  `rps` text NOT NULL,
  `rpd` text NOT NULL,
  `rpk` text NOT NULL,
  `rpo` text NOT NULL,
  `alergi` varchar(50) NOT NULL DEFAULT '',
  `keadaan` enum('Sehat','Sakit Ringan','Sakit Sedang','Sakit Berat') NOT NULL,
  `gcs` varchar(10) NOT NULL,
  `kesadaran` enum('Compos Mentis','Apatis','Somnolen','Sopor','Koma') NOT NULL,
  `td` varchar(8) NOT NULL DEFAULT '',
  `nadi` varchar(5) NOT NULL DEFAULT '',
  `rr` varchar(5) NOT NULL,
  `suhu` varchar(5) NOT NULL DEFAULT '',
  `spo` varchar(5) NOT NULL,
  `bb` varchar(5) NOT NULL DEFAULT '',
  `tb` varchar(5) NOT NULL DEFAULT '',
  `kepala` enum('Normal','Abnormal','Tidak Diperiksa') NOT NULL,
  `gigi` enum('Normal','Abnormal','Tidak Diperiksa') NOT NULL,
  `tht` enum('Normal','Abnormal','Tidak Diperiksa') NOT NULL,
  `thoraks` enum('Normal','Abnormal','Tidak Diperiksa') NOT NULL,
  `abdomen` enum('Normal','Abnormal','Tidak Diperiksa') NOT NULL,
  `genital` enum('Normal','Abnormal','Tidak Diperiksa') NOT NULL,
  `ekstremitas` enum('Normal','Abnormal','Tidak Diperiksa') NOT NULL,
  `kulit` enum('Normal','Abnormal','Tidak Diperiksa') NOT NULL,
  `ket_fisik` text NOT NULL,
  `ket_lokalis` text NOT NULL,
  `penunjang` text NOT NULL,
  `diagnosis` text NOT NULL,
  `tata` text NOT NULL,
  `konsulrujuk` text NOT NULL,
  PRIMARY KEY (`no_rawat`) USING BTREE,
  KEY `kd_dokter` (`kd_dokter`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `penilaian_medis_ralan`
--

INSERT INTO `penilaian_medis_ralan` (`no_rawat`, `tanggal`, `kd_dokter`, `anamnesis`, `hubungan`, `keluhan_utama`, `rps`, `rpd`, `rpk`, `rpo`, `alergi`, `keadaan`, `gcs`, `kesadaran`, `td`, `nadi`, `rr`, `suhu`, `spo`, `bb`, `tb`, `kepala`, `gigi`, `tht`, `thoraks`, `abdomen`, `genital`, `ekstremitas`, `kulit`, `ket_fisik`, `ket_lokalis`, `penunjang`, `diagnosis`, `tata`, `konsulrujuk`) VALUES
('2026/09/08/0001', '2026-09-08 08:15:00', 'DR001', 'Autoanamnesis', 'Pasien Sendiri', 'Kepala sering pusing dan tengkuk terasa berat sejak 3 hari yang lalu', 'Keluhan dirasakan sejak beberapa hari yang lalu dan mengganggu aktivitas harian', 'Disangkal', 'Disangkal', 'Belum minum obat', 'Tidak ada riwayat alergi', 'Sakit Sedang', '15', 'Compos Mentis', '120/80', '80', '20', '36.8', '98', '60', '165', 'Normal', 'Normal', 'Normal', 'Normal', 'Normal', 'Normal', 'Normal', 'Normal', 'Keadaan umum tampak sakit sedang, gizi cukup', '', '', 'Cephalea ec Suspek Hipertensi', 'Terapi medikamentosa dan edukasi pasien', ''),
('2026/09/08/0002', '2026-09-08 08:30:00', 'DR002', 'Autoanamnesis', 'Pasien Sendiri', 'Batuk berdahak sudah 4 hari, pilek dan tenggorokan terasa gatal agak demam', 'Keluhan dirasakan sejak beberapa hari yang lalu dan mengganggu aktivitas harian', 'Disangkal', 'Disangkal', 'Belum minum obat', 'Tidak ada riwayat alergi', 'Sakit Sedang', '15', 'Compos Mentis', '120/80', '80', '20', '36.8', '98', '60', '165', 'Normal', 'Normal', 'Normal', 'Normal', 'Normal', 'Normal', 'Normal', 'Normal', 'Keadaan umum tampak sakit sedang, gizi cukup', '', '', 'Acute Upper Respiratory Infection', 'Terapi medikamentosa dan edukasi pasien', ''),
('2026/09/08/0003', '2026-09-08 09:00:00', 'DR003', 'Autoanamnesis', 'Pasien Sendiri', 'Gigi geraham kanan bawah ngilu hebat saat minum dingin dan ada karang gigi tebal', 'Keluhan dirasakan sejak beberapa hari yang lalu dan mengganggu aktivitas harian', 'Disangkal', 'Disangkal', 'Belum minum obat', 'Tidak ada riwayat alergi', 'Sakit Sedang', '15', 'Compos Mentis', '120/80', '80', '20', '36.8', '98', '60', '165', 'Normal', 'Normal', 'Normal', 'Normal', 'Normal', 'Normal', 'Normal', 'Normal', 'Keadaan umum tampak sakit sedang, gizi cukup', '', '', 'Karies Gigi 46 & Calculus Dental', 'Terapi medikamentosa dan edukasi pasien', ''),
('2026/09/08/0004', '2026-09-08 09:30:00', 'DR005', 'Autoanamnesis', 'Pasien Sendiri', 'Kontrol kehamilan trimester 2, usia gestasi 24 minggu. Kadang merasa pegal di pinggang', 'Keluhan dirasakan sejak beberapa hari yang lalu dan mengganggu aktivitas harian', 'Disangkal', 'Disangkal', 'Belum minum obat', 'Tidak ada riwayat alergi', 'Sakit Sedang', '15', 'Compos Mentis', '120/80', '80', '20', '36.8', '98', '60', '165', 'Normal', 'Normal', 'Normal', 'Normal', 'Normal', 'Normal', 'Normal', 'Normal', 'Keadaan umum tampak sakit sedang, gizi cukup', '', '', 'G1P0A0 Hamil 24 Minggu Single Live Intrauterine', 'Terapi medikamentosa dan edukasi pasien', ''),
('2026/09/08/0005', '2026-09-08 10:00:00', 'DR001', 'Autoanamnesis', 'Pasien Sendiri', 'Nyeri ulu hati seperti terbakar, mual, perut kembung dan sering sendawa asam', 'Keluhan dirasakan sejak beberapa hari yang lalu dan mengganggu aktivitas harian', 'Disangkal', 'Disangkal', 'Belum minum obat', 'Tidak ada riwayat alergi', 'Sakit Sedang', '15', 'Compos Mentis', '120/80', '80', '20', '36.8', '98', '60', '165', 'Normal', 'Normal', 'Normal', 'Normal', 'Normal', 'Normal', 'Normal', 'Normal', 'Keadaan umum tampak sakit sedang, gizi cukup', '', '', 'Dyspepsia Syndrome ec Suspek Gastritis Kronis', 'Terapi medikamentosa dan edukasi pasien', ''),
('2026/09/08/0006', '2026-09-08 10:30:00', 'DR001', 'Autoanamnesis', 'Pasien Sendiri', 'Kontrol rutin Diabetes Melitus, merasa cepat haus dan sering kencing saat malam hari', 'Keluhan dirasakan sejak beberapa hari yang lalu dan mengganggu aktivitas harian', 'Disangkal', 'Disangkal', 'Belum minum obat', 'Tidak ada riwayat alergi', 'Sakit Sedang', '15', 'Compos Mentis', '120/80', '80', '20', '36.8', '98', '60', '165', 'Normal', 'Normal', 'Normal', 'Normal', 'Normal', 'Normal', 'Normal', 'Normal', 'Keadaan umum tampak sakit sedang, gizi cukup', '', '', 'Type 2 Diabetes Mellitus Non Insulin Dependent', 'Terapi medikamentosa dan edukasi pasien', ''),
('2026/09/08/0007', '2026-09-08 11:00:00', 'DR004', 'Autoanamnesis', 'Pasien Sendiri', 'Anak demam sumeng-sumeng sejak 2 hari, batuk berdahak dan pilek, nafsu makan agak turun', 'Keluhan dirasakan sejak beberapa hari yang lalu dan mengganggu aktivitas harian', 'Disangkal', 'Disangkal', 'Belum minum obat', 'Tidak ada riwayat alergi', 'Sakit Sedang', '15', 'Compos Mentis', '120/80', '80', '20', '36.8', '98', '60', '165', 'Normal', 'Normal', 'Normal', 'Normal', 'Normal', 'Normal', 'Normal', 'Normal', 'Keadaan umum tampak sakit sedang, gizi cukup', '', '', 'Common Cold pada Anak', 'Terapi medikamentosa dan edukasi pasien', ''),
('2026/09/08/0008', '2026-09-08 11:30:00', 'DR002', 'Autoanamnesis', 'Pasien Sendiri', 'BAB cair sudah 4 kali dari pagi, perut mulas melilit dan badan terasa lemas', 'Keluhan dirasakan sejak beberapa hari yang lalu dan mengganggu aktivitas harian', 'Disangkal', 'Disangkal', 'Belum minum obat', 'Tidak ada riwayat alergi', 'Sakit Sedang', '15', 'Compos Mentis', '120/80', '80', '20', '36.8', '98', '60', '165', 'Normal', 'Normal', 'Normal', 'Normal', 'Normal', 'Normal', 'Normal', 'Normal', 'Keadaan umum tampak sakit sedang, gizi cukup', '', '', 'Gastroenteritis Akut Dehidrasi Ringan-Sedang', 'Terapi medikamentosa dan edukasi pasien', ''),
('2026/09/08/0009', '2026-09-08 12:00:00', 'DR001', 'Autoanamnesis', 'Pasien Sendiri', 'Jempol kaki kanan bengkak merah dan sangat nyeri sejak tadi pagi bangun tidur setelah makan emping/seafood', 'Keluhan dirasakan sejak beberapa hari yang lalu dan mengganggu aktivitas harian', 'Disangkal', 'Disangkal', 'Belum minum obat', 'Tidak ada riwayat alergi', 'Sakit Sedang', '15', 'Compos Mentis', '120/80', '80', '20', '36.8', '98', '60', '165', 'Normal', 'Normal', 'Normal', 'Normal', 'Normal', 'Normal', 'Normal', 'Normal', 'Keadaan umum tampak sakit sedang, gizi cukup', '', '', 'Gout Arthritis Akut Serangan Pertama', 'Terapi medikamentosa dan edukasi pasien', ''),
('2026/09/08/0010', '2026-09-08 12:30:00', 'DR002', 'Autoanamnesis', 'Pasien Sendiri', 'Lecet dan luka robek dangkal di lutut kanan akibat terserempet motor saat menyebrang', 'Keluhan dirasakan sejak beberapa hari yang lalu dan mengganggu aktivitas harian', 'Disangkal', 'Disangkal', 'Belum minum obat', 'Tidak ada riwayat alergi', 'Sakit Sedang', '15', 'Compos Mentis', '120/80', '80', '20', '36.8', '98', '60', '165', 'Normal', 'Normal', 'Normal', 'Normal', 'Normal', 'Normal', 'Normal', 'Normal', 'Keadaan umum tampak sakit sedang, gizi cukup', '', '', 'Vulnus Laceratum Regio Genu Dextra', 'Terapi medikamentosa dan edukasi pasien', '');

-- --------------------------------------------------------

--
-- Struktur dari tabel `penilaian_medis_ranap`
--

DROP TABLE IF EXISTS `penilaian_medis_ranap`;
CREATE TABLE IF NOT EXISTS `penilaian_medis_ranap` (
  `no_rawat` varchar(17) NOT NULL,
  `tanggal` datetime NOT NULL,
  `kd_dokter` varchar(20) NOT NULL,
  `anamnesis` enum('Autoanamnesis','Alloanamnesis') NOT NULL,
  `hubungan` varchar(100) NOT NULL,
  `keluhan_utama` text NOT NULL,
  `rps` text NOT NULL,
  `rpd` text NOT NULL,
  `rpk` text NOT NULL,
  `rpo` text NOT NULL,
  `alergi` varchar(100) NOT NULL DEFAULT '',
  `keadaan` enum('Sehat','Sakit Ringan','Sakit Sedang','Sakit Berat') NOT NULL,
  `gcs` varchar(10) NOT NULL,
  `kesadaran` enum('Compos Mentis','Apatis','Somnolen','Sopor','Koma') NOT NULL,
  `td` varchar(8) NOT NULL DEFAULT '',
  `nadi` varchar(5) NOT NULL DEFAULT '',
  `rr` varchar(5) NOT NULL,
  `suhu` varchar(5) NOT NULL DEFAULT '',
  `spo` varchar(5) NOT NULL,
  `bb` varchar(5) NOT NULL DEFAULT '',
  `tb` varchar(5) NOT NULL DEFAULT '',
  `kepala` enum('Normal','Abnormal','Tidak Diperiksa') NOT NULL,
  `mata` enum('Normal','Abnormal','Tidak Diperiksa') NOT NULL,
  `gigi` enum('Normal','Abnormal','Tidak Diperiksa') NOT NULL,
  `tht` enum('Normal','Abnormal','Tidak Diperiksa') NOT NULL,
  `thoraks` enum('Normal','Abnormal','Tidak Diperiksa') NOT NULL,
  `jantung` enum('Normal','Abnormal','Tidak Diperiksa') NOT NULL,
  `paru` enum('Normal','Abnormal','Tidak Diperiksa') NOT NULL,
  `abdomen` enum('Normal','Abnormal','Tidak Diperiksa') NOT NULL,
  `genital` enum('Normal','Abnormal','Tidak Diperiksa') NOT NULL,
  `ekstremitas` enum('Normal','Abnormal','Tidak Diperiksa') NOT NULL,
  `kulit` enum('Normal','Abnormal','Tidak Diperiksa') NOT NULL,
  `ket_fisik` text NOT NULL,
  `ket_lokalis` text NOT NULL,
  `lab` text NOT NULL,
  `rad` text NOT NULL,
  `penunjang` text NOT NULL,
  `diagnosis` text NOT NULL,
  `tata` text NOT NULL,
  `edukasi` text NOT NULL,
  PRIMARY KEY (`no_rawat`) USING BTREE,
  KEY `kd_dokter` (`kd_dokter`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `penilaian_ulang_nyeri`
--

DROP TABLE IF EXISTS `penilaian_ulang_nyeri`;
CREATE TABLE IF NOT EXISTS `penilaian_ulang_nyeri` (
  `no_rawat` varchar(17) NOT NULL,
  `tanggal` datetime NOT NULL,
  `nyeri` enum('Tidak Ada Nyeri','Nyeri Akut','Nyeri Kronis') NOT NULL,
  `provokes` enum('Proses Penyakit','Benturan','Lain-lain','-') NOT NULL,
  `ket_provokes` varchar(40) NOT NULL,
  `quality` enum('Seperti Tertusuk','Berdenyut','Teriris','Tertindih','Tertiban','Lain-lain','-') NOT NULL,
  `ket_quality` varchar(50) NOT NULL,
  `lokasi` varchar(50) NOT NULL,
  `menyebar` enum('Tidak','Ya') NOT NULL,
  `skala_nyeri` enum('0','1','2','3','4','5','6','7','8','9','10') NOT NULL,
  `durasi` varchar(25) NOT NULL,
  `nyeri_hilang` enum('Istirahat','Medengar Musik','Minum Obat','-') NOT NULL,
  `ket_nyeri` varchar(40) NOT NULL,
  `nip` varchar(20) NOT NULL,
  PRIMARY KEY (`no_rawat`,`tanggal`) USING BTREE,
  KEY `nip` (`nip`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `penjab`
--

DROP TABLE IF EXISTS `penjab`;
CREATE TABLE IF NOT EXISTS `penjab` (
  `kd_pj` char(3) NOT NULL,
  `png_jawab` varchar(30) NOT NULL,
  `nama_perusahaan` varchar(60) NOT NULL,
  `alamat_asuransi` varchar(130) NOT NULL,
  `no_telp` varchar(40) NOT NULL,
  `attn` varchar(60) NOT NULL,
  `status` enum('0','1') NOT NULL,
  PRIMARY KEY (`kd_pj`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `penjab`
--

INSERT INTO `penjab` (`kd_pj`, `png_jawab`, `nama_perusahaan`, `alamat_asuransi`, `no_telp`, `attn`, `status`) VALUES
('-', '-', '-', '-', '0', '0', '1'),
('ASR', 'Asuransi Swasta (Prudential/In', 'PT Prudential Life Assurance', 'Prudential Tower Jakarta', '021-29958888', 'Ibu Cindy', '1'),
('BPJ', 'BPJS Kesehatan', '-', '-', '0', '0', '1'),
('COR', 'Perusahaan Rekanan (PT Mitra)', 'PT Pertamina Bina Medika', 'Jl. HR Rasuna Said Jakarta', '021-5250012', 'Bpk. Anton', '1'),
('PRH', 'PT. TELKOM MEDIKA', 'PT. TELKOM INDONESIA TBK', 'Bandung', '022-4521510', 'HRD & Medika', '1'),
('UMU', 'Pasien Umum / Mandiri', '-', '-', '0', '0', '1');

-- --------------------------------------------------------

--
-- Struktur dari tabel `penyakit`
--

DROP TABLE IF EXISTS `penyakit`;
CREATE TABLE IF NOT EXISTS `penyakit` (
  `kd_penyakit` varchar(10) NOT NULL,
  `nm_penyakit` varchar(250) DEFAULT NULL,
  `ciri_ciri` text,
  `keterangan` varchar(60) DEFAULT NULL,
  `kd_ktg` varchar(8) DEFAULT NULL,
  `status` enum('Menular','Tidak Menular') NOT NULL,
  PRIMARY KEY (`kd_penyakit`),
  KEY `kd_ktg` (`kd_ktg`),
  KEY `nm_penyakit` (`nm_penyakit`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `penyakit`
--

INSERT INTO `penyakit` (`kd_penyakit`, `nm_penyakit`, `ciri_ciri`, `keterangan`, `kd_ktg`, `status`) VALUES
('A09', 'Infectious gastroenteritis and colitis, unspecified (Diare Akut)', 'BAB cair > 3 kali sehari, perut melilit', 'Penyakit Infeksi Saluran Cerna', 'KTP04', 'Menular'),
('A09.9', 'Gastroenteritis and colitis of unspecified origin', 'BAB cair > 3x sehari, perut mulas, lemas', 'Diare / Gastroenteritis Akut', 'KP03', ''),
('E11.9', 'Type 2 diabetes mellitus without complications (DM Tipe 2)', 'Gula darah puasa >= 126 mg/dL atau 2 jam PP >= 200 mg/dL', 'Penyakit Endokrin & Metabolik', 'KTP03', 'Tidak Menular'),
('H10.9', 'Conjunctivitis, unspecified (Konjungtivitis / Sakit Mata Merah)', 'Mata merah, berair, ada sekret', 'Penyakit Mata', 'KTP01', 'Menular'),
('H66.9', 'Otitis media, unspecified (Infeksi Telinga Tengah)', 'Nyeri telinga, penurunan pendengaran', 'Penyakit THT', 'KTP01', 'Menular'),
('I10', 'Essential (primary) hypertension (Hipertensi Primer)', 'Tekanan darah sistolik >= 140 atau diastolik >= 90 mmHg', 'Penyakit Kardiovaskular', 'KTP02', 'Tidak Menular'),
('J00', 'Acute nasopharyngitis [common cold]', 'Pilek, bersin, hidung tersumbat', 'ISPA Ringan', 'KP01', ''),
('J02.9', 'Acute pharyngitis, unspecified (Faringitis Akut / Radang Tenggorokan)', 'Nyeri menelan, faring hiperemis', 'Penyakit Saluran Nafas Atas', 'KTP01', 'Menular'),
('J06.9', 'Acute upper respiratory infection, unspecified (ISPA Akut)', 'Demam, batuk, pilek, hidung tersumbat', 'Penyakit Saluran Nafas Atas', 'KTP01', 'Menular'),
('J45.9', 'Asthma, unspecified (Asma Bronkial)', 'Sesak nafas episodik, mengi (wheezing)', 'Penyakit Saluran Nafas', 'KTP01', 'Tidak Menular'),
('K02.9', 'Dental caries, unspecified (Karies Gigi / Gigi Berlubang)', 'Kavitas pada email dan dentin gigi, ngilu', 'Penyakit Rongga Mulut & Gigi', 'KTP05', 'Tidak Menular'),
('K04.0', 'Pulpitis (Radang Pulpa Gigi)', 'Nyeri spontan berdenyut pada gigi', 'Penyakit Rongga Mulut & Gigi', 'KTP05', 'Tidak Menular'),
('K05.3', 'Chronic periodontitis', 'Gusi berdarah, pembengkakan gusi, bau mulut', 'Radang Gusi / Periodontitis', 'KP04', ''),
('K29.7', 'Gastritis, unspecified (Gastritis / Dispepsia)', 'Nyeri ulu hati, rasa terbakar di epigastrium, mual', 'Penyakit Saluran Cerna', 'KTP04', 'Tidak Menular'),
('L30.9', 'Dermatitis, unspecified (Dermatitis / Alergi Kulit)', 'Ruam kemerahan, gatal pada kulit', 'Penyakit Kulit', 'KTP01', 'Tidak Menular'),
('M10.9', 'Gout, unspecified', 'Nyeri sendi hebat mendadak, bengkak kemerahan di jempol/kaki', 'Asam Urat / Gout Arthritis', 'KP05', ''),
('M79.1', 'Myalgia (Nyeri Otot / Pegal Linu)', 'Nyeri dan kekakuan pada otot dan persendian', 'Penyakit Muskuloskeletal', 'KTP06', 'Tidak Menular'),
('O80.0', 'Spontaneous vertex delivery', 'Persalinan normal pervaginam tanpa komplikasi', 'Persalinan Normal', 'KP02', ''),
('O80.9', 'Single spontaneous delivery, unspecified (Persalinan Normal)', 'Kehamilan matur aterm siap persalinan', 'Kebidanan & Kehamilan', 'KTP07', 'Tidak Menular'),
('R50.9', 'Fever, unspecified (Febris / Demam Tanpa Sebab Spesifik)', 'Suhu aksila >= 37.5 derajat celcius', 'Gejala & Tanda Klinis Umum', 'KTP01', 'Tidak Menular');

-- --------------------------------------------------------

--
-- Struktur dari tabel `perbaikan_inventaris`
--

DROP TABLE IF EXISTS `perbaikan_inventaris`;
CREATE TABLE IF NOT EXISTS `perbaikan_inventaris` (
  `no_permintaan` varchar(15) NOT NULL,
  `tanggal` date NOT NULL,
  `uraian_kegiatan` varchar(255) NOT NULL,
  `nip` varchar(20) NOT NULL,
  `pelaksana` enum('Teknisi Rumah Sakit','Teknisi Rujukan','Pihak ke III') NOT NULL,
  `biaya` double NOT NULL,
  `keterangan` varchar(255) NOT NULL,
  `status` enum('Bisa Diperbaiki','Tidak Bisa Diperbaiki') NOT NULL,
  PRIMARY KEY (`no_permintaan`),
  KEY `nip` (`nip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `periksa_lab`
--

DROP TABLE IF EXISTS `periksa_lab`;
CREATE TABLE IF NOT EXISTS `periksa_lab` (
  `no_rawat` varchar(17) NOT NULL,
  `nip` varchar(20) NOT NULL,
  `kd_jenis_prw` varchar(15) NOT NULL,
  `tgl_periksa` date NOT NULL,
  `jam` time NOT NULL,
  `dokter_perujuk` varchar(20) NOT NULL,
  `bagian_rs` double NOT NULL,
  `bhp` double NOT NULL,
  `tarif_perujuk` double NOT NULL,
  `tarif_tindakan_dokter` double NOT NULL,
  `tarif_tindakan_petugas` double NOT NULL,
  `kso` double DEFAULT NULL,
  `menejemen` double DEFAULT NULL,
  `biaya` double NOT NULL,
  `kd_dokter` varchar(20) NOT NULL,
  `status` enum('Ralan','Ranap') DEFAULT NULL,
  `kategori` enum('PA','PK','MB') NOT NULL,
  PRIMARY KEY (`no_rawat`,`kd_jenis_prw`,`tgl_periksa`,`jam`),
  KEY `nip` (`nip`),
  KEY `kd_jenis_prw` (`kd_jenis_prw`),
  KEY `kd_dokter` (`kd_dokter`),
  KEY `dokter_perujuk` (`dokter_perujuk`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `periksa_lab`
--

INSERT INTO `periksa_lab` (`no_rawat`, `nip`, `kd_jenis_prw`, `tgl_periksa`, `jam`, `dokter_perujuk`, `bagian_rs`, `bhp`, `tarif_perujuk`, `tarif_tindakan_dokter`, `tarif_tindakan_petugas`, `kso`, `menejemen`, `biaya`, `kd_dokter`, `status`, `kategori`) VALUES
('2026/09/08/0001', 'PT003', 'LAB006', '2026-09-08', '08:14:00', 'DR001', 10000, 25000, 5000, 10000, 10000, 0, 0, 60000, 'DR001', 'Ralan', 'PK'),
('2026/09/08/0009', 'PT003', 'LAB004', '2026-09-08', '08:09:00', 'DR001', 20000, 40000, 10000, 20000, 20000, 0, 10000, 120000, 'DR001', 'Ralan', 'PK'),
('2026/09/08/0009', 'PT003', 'LAB005', '2026-09-08', '08:09:00', 'DR001', 15000, 30000, 5000, 15000, 15000, 0, 5000, 95000, 'DR001', 'Ralan', 'PK'),
('2026/09/08/0010', 'PET001', 'LAB001', '2026-09-08', '09:30:00', 'DR002', 15000, 25000, 5000, 15000, 15000, 0, 5000, 80000, 'DR002', 'Ralan', 'PK'),
('2026/09/08/0010', 'PET001', 'LAB002', '2026-09-08', '09:30:00', 'DR002', 5000, 10000, 0, 5000, 5000, 0, 0, 25000, 'DR002', 'Ralan', 'PK');

-- --------------------------------------------------------

--
-- Struktur dari tabel `periksa_radiologi`
--

DROP TABLE IF EXISTS `periksa_radiologi`;
CREATE TABLE IF NOT EXISTS `periksa_radiologi` (
  `no_rawat` varchar(17) NOT NULL,
  `nip` varchar(20) NOT NULL,
  `kd_jenis_prw` varchar(15) NOT NULL,
  `tgl_periksa` date NOT NULL,
  `jam` time NOT NULL,
  `dokter_perujuk` varchar(20) NOT NULL,
  `bagian_rs` double NOT NULL,
  `bhp` double NOT NULL,
  `tarif_perujuk` double NOT NULL,
  `tarif_tindakan_dokter` double NOT NULL,
  `tarif_tindakan_petugas` double NOT NULL,
  `kso` double DEFAULT NULL,
  `menejemen` double DEFAULT NULL,
  `biaya` double NOT NULL,
  `kd_dokter` varchar(20) NOT NULL,
  `status` enum('Ranap','Ralan') DEFAULT NULL,
  `proyeksi` varchar(50) NOT NULL,
  `kV` varchar(10) NOT NULL,
  `mAS` varchar(10) NOT NULL,
  `FFD` varchar(10) NOT NULL,
  `BSF` varchar(10) NOT NULL,
  `inak` varchar(10) NOT NULL,
  `jml_penyinaran` varchar(10) NOT NULL,
  `dosis` varchar(20) NOT NULL,
  PRIMARY KEY (`no_rawat`,`kd_jenis_prw`,`tgl_periksa`,`jam`),
  KEY `nip` (`nip`),
  KEY `kd_jenis_prw` (`kd_jenis_prw`),
  KEY `kd_dokter` (`kd_dokter`),
  KEY `dokter_perujuk` (`dokter_perujuk`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `permintaan_detail_permintaan_lab`
--

DROP TABLE IF EXISTS `permintaan_detail_permintaan_lab`;
CREATE TABLE IF NOT EXISTS `permintaan_detail_permintaan_lab` (
  `noorder` varchar(15) NOT NULL,
  `kd_jenis_prw` varchar(15) NOT NULL,
  `id_template` int NOT NULL,
  `stts_bayar` enum('Sudah','Belum') DEFAULT NULL,
  PRIMARY KEY (`noorder`,`kd_jenis_prw`,`id_template`) USING BTREE,
  KEY `id_template` (`id_template`) USING BTREE,
  KEY `kd_jenis_prw` (`kd_jenis_prw`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `permintaan_detail_permintaan_lab`
--

INSERT INTO `permintaan_detail_permintaan_lab` (`noorder`, `kd_jenis_prw`, `id_template`, `stts_bayar`) VALUES
('PK202609080001', 'LAB001', 1, 'Belum'),
('PK202609080001', 'LAB001', 2, 'Belum'),
('PK202609080001', 'LAB001', 3, 'Belum'),
('PK202609080001', 'LAB001', 4, 'Belum'),
('PK202609080001', 'LAB001', 5, 'Belum'),
('PK202609080001', 'LAB001', 6, 'Belum'),
('PK202609080001', 'LAB001', 7, 'Belum'),
('PK202609080001', 'LAB001', 8, 'Belum'),
('PK202609080001', 'LAB001', 9, 'Belum'),
('PK202609080001', 'LAB001', 10, 'Belum'),
('PK202609080001', 'LAB001', 11, 'Belum'),
('PK202609080001', 'LAB001', 12, 'Belum'),
('PK202609080001', 'LAB002', 13, 'Belum'),
('PK202609080004', 'LAB006', 23, 'Belum'),
('PK202609080004', 'LAB006', 24, 'Belum');

-- --------------------------------------------------------

--
-- Struktur dari tabel `permintaan_lab`
--

DROP TABLE IF EXISTS `permintaan_lab`;
CREATE TABLE IF NOT EXISTS `permintaan_lab` (
  `noorder` varchar(15) NOT NULL,
  `no_rawat` varchar(17) NOT NULL,
  `tgl_permintaan` date NOT NULL,
  `jam_permintaan` time NOT NULL,
  `tgl_sampel` date NOT NULL,
  `jam_sampel` time NOT NULL,
  `tgl_hasil` date NOT NULL,
  `jam_hasil` time NOT NULL,
  `dokter_perujuk` varchar(20) NOT NULL,
  `status` enum('ralan','ranap') NOT NULL,
  `informasi_tambahan` varchar(60) NOT NULL,
  `diagnosa_klinis` varchar(80) NOT NULL,
  PRIMARY KEY (`noorder`),
  KEY `dokter_perujuk` (`dokter_perujuk`),
  KEY `no_rawat` (`no_rawat`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `permintaan_lab`
--

INSERT INTO `permintaan_lab` (`noorder`, `no_rawat`, `tgl_permintaan`, `jam_permintaan`, `tgl_sampel`, `jam_sampel`, `tgl_hasil`, `jam_hasil`, `dokter_perujuk`, `status`, `informasi_tambahan`, `diagnosa_klinis`) VALUES
('PK202609080001', '2026/09/08/0010', '2026-09-08', '08:30:00', '2026-09-08', '08:45:00', '2026-09-08', '09:30:00', 'DR002', 'ralan', 'Cek darah rutin & evaluasi infeksi', 'Febris Hari ke-3 suspek DHF'),
('PK202609080002', '2026/09/08/0009', '2026-09-08', '09:00:00', '2026-09-08', '09:15:00', '2026-09-08', '08:09:00', 'DR001', 'ralan', 'Medical checkup rutin & hipertensi', 'Hipertensi Primer & Dislipidemia'),
('PK202609080003', '2026/09/08/0008', '2026-09-08', '09:45:00', '2026-09-08', '08:09:03', '0000-00-00', '00:00:00', 'DR002', 'ralan', 'Demam naik turun > 5 hari, mual', 'Suspek Demam Tifoid'),
('PK202609080004', '2026/09/08/0001', '2026-09-08', '08:08:53', '2026-09-08', '08:14:49', '2026-09-08', '08:14:00', 'DR001', 'ralan', '', '');

-- --------------------------------------------------------

--
-- Struktur dari tabel `permintaan_pemeriksaan_lab`
--

DROP TABLE IF EXISTS `permintaan_pemeriksaan_lab`;
CREATE TABLE IF NOT EXISTS `permintaan_pemeriksaan_lab` (
  `noorder` varchar(15) NOT NULL,
  `kd_jenis_prw` varchar(15) NOT NULL,
  `stts_bayar` enum('Sudah','Belum') DEFAULT NULL,
  PRIMARY KEY (`noorder`,`kd_jenis_prw`),
  KEY `kd_jenis_prw` (`kd_jenis_prw`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `permintaan_pemeriksaan_lab`
--

INSERT INTO `permintaan_pemeriksaan_lab` (`noorder`, `kd_jenis_prw`, `stts_bayar`) VALUES
('PK202609080001', 'LAB001', 'Belum'),
('PK202609080001', 'LAB002', 'Belum'),
('PK202609080002', 'LAB004', 'Belum'),
('PK202609080002', 'LAB005', 'Belum'),
('PK202609080003', 'LAB007', 'Belum'),
('PK202609080003', 'LAB008', 'Belum'),
('PK202609080004', 'LAB006', 'Belum');

-- --------------------------------------------------------

--
-- Struktur dari tabel `permintaan_pemeriksaan_radiologi`
--

DROP TABLE IF EXISTS `permintaan_pemeriksaan_radiologi`;
CREATE TABLE IF NOT EXISTS `permintaan_pemeriksaan_radiologi` (
  `noorder` varchar(15) NOT NULL,
  `kd_jenis_prw` varchar(15) NOT NULL,
  `stts_bayar` enum('Sudah','Belum') DEFAULT NULL,
  PRIMARY KEY (`noorder`,`kd_jenis_prw`),
  KEY `kd_jenis_prw` (`kd_jenis_prw`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `permintaan_perbaikan_inventaris`
--

DROP TABLE IF EXISTS `permintaan_perbaikan_inventaris`;
CREATE TABLE IF NOT EXISTS `permintaan_perbaikan_inventaris` (
  `no_permintaan` varchar(15) NOT NULL,
  `no_inventaris` varchar(30) DEFAULT NULL,
  `nik` varchar(20) DEFAULT NULL,
  `tanggal` datetime DEFAULT NULL,
  `deskripsi_kerusakan` varchar(300) DEFAULT NULL,
  PRIMARY KEY (`no_permintaan`),
  KEY `no_inventaris` (`no_inventaris`),
  KEY `nik` (`nik`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `permintaan_radiologi`
--

DROP TABLE IF EXISTS `permintaan_radiologi`;
CREATE TABLE IF NOT EXISTS `permintaan_radiologi` (
  `noorder` varchar(15) NOT NULL,
  `no_rawat` varchar(17) NOT NULL,
  `tgl_permintaan` date NOT NULL,
  `jam_permintaan` time NOT NULL,
  `tgl_sampel` date NOT NULL,
  `jam_sampel` time NOT NULL,
  `tgl_hasil` date NOT NULL,
  `jam_hasil` time NOT NULL,
  `dokter_perujuk` varchar(20) NOT NULL,
  `status` enum('ralan','ranap') NOT NULL,
  `informasi_tambahan` varchar(60) NOT NULL,
  `diagnosa_klinis` varchar(80) NOT NULL,
  PRIMARY KEY (`noorder`),
  KEY `dokter_perujuk` (`dokter_perujuk`),
  KEY `no_rawat` (`no_rawat`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `personal_pasien`
--

DROP TABLE IF EXISTS `personal_pasien`;
CREATE TABLE IF NOT EXISTS `personal_pasien` (
  `no_rkm_medis` varchar(15) NOT NULL,
  `gambar` text,
  `password` text,
  PRIMARY KEY (`no_rkm_medis`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `perusahaan_pasien`
--

DROP TABLE IF EXISTS `perusahaan_pasien`;
CREATE TABLE IF NOT EXISTS `perusahaan_pasien` (
  `kode_perusahaan` varchar(8) NOT NULL,
  `nama_perusahaan` varchar(70) DEFAULT NULL,
  `alamat` varchar(100) DEFAULT NULL,
  `kota` varchar(40) DEFAULT NULL,
  `no_telp` varchar(27) DEFAULT NULL,
  PRIMARY KEY (`kode_perusahaan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `perusahaan_pasien`
--

INSERT INTO `perusahaan_pasien` (`kode_perusahaan`, `nama_perusahaan`, `alamat`, `kota`, `no_telp`) VALUES
('-', '-', '-', '-', '0'),
('COR01', 'PT Pertamina Bina Sejahtera', 'Jl. Sudirman No. 10', 'Jakarta', '021-5551234'),
('COR02', 'PT Astra International Tbk', 'Jl. Gaya Motor No. 8', 'Jakarta', '021-5558888'),
('COR03', 'PT Telkom Indonesia', 'Jl. Japati No. 1', 'Bandung', '022-4567890'),
('COR04', 'PT Bank Rakyat Indonesia', 'Jl. Jenderal Sudirman No. 44', 'Jakarta', '021-5751234'),
('CORP01', 'PT. TELKOM INDONESIA TBK', 'Jl. Japati No. 1', 'Bandung', '022-4521510'),
('CORP02', 'PT. BANK MANDIRI TBK', 'Jl. Gatot Subroto Kav. 36-38', 'Jakarta', '021-5265045'),
('CORP03', 'PT. ASTRA INTERNATIONAL TBK', 'Jl. Gaya Motor Raya No. 8', 'Jakarta', '021-6522555');

-- --------------------------------------------------------

--
-- Struktur dari tabel `petugas`
--

DROP TABLE IF EXISTS `petugas`;
CREATE TABLE IF NOT EXISTS `petugas` (
  `nip` varchar(20) NOT NULL,
  `nama` varchar(50) DEFAULT NULL,
  `jk` enum('L','P') DEFAULT NULL,
  `tmp_lahir` varchar(20) DEFAULT NULL,
  `tgl_lahir` date DEFAULT NULL,
  `gol_darah` enum('A','B','O','AB','-') DEFAULT NULL,
  `agama` varchar(12) DEFAULT NULL,
  `stts_nikah` enum('BELUM MENIKAH','MENIKAH','JANDA','DUDHA','JOMBLO') DEFAULT NULL,
  `alamat` varchar(60) DEFAULT NULL,
  `kd_jbtn` char(4) DEFAULT NULL,
  `no_telp` varchar(13) DEFAULT NULL,
  `status` enum('0','1') DEFAULT NULL,
  PRIMARY KEY (`nip`),
  KEY `kd_jbtn` (`kd_jbtn`),
  KEY `nama` (`nama`),
  KEY `nip` (`nip`),
  KEY `tmp_lahir` (`tmp_lahir`),
  KEY `tgl_lahir` (`tgl_lahir`),
  KEY `agama` (`agama`),
  KEY `stts_nikah` (`stts_nikah`),
  KEY `alamat` (`alamat`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `petugas`
--

INSERT INTO `petugas` (`nip`, `nama`, `jk`, `tmp_lahir`, `tgl_lahir`, `gol_darah`, `agama`, `stts_nikah`, `alamat`, `kd_jbtn`, `no_telp`, `status`) VALUES
('DR001', 'dr. Ataaka Muhammad', 'L', 'Barabai', '2020-12-01', 'A', 'Islam', 'MENIKAH', '-', '-', '0', '1'),
('PT001', 'Ns. Rina Kartika, S.Kep', 'P', 'Barabai', '1994-04-12', 'A', 'Islam', 'MENIKAH', 'Jl. Diponegoro No. 22', 'JB05', '08123456007', '1'),
('PT002', 'Ns. Dimas Pratama, S.Kep', 'L', 'Barabai', '1996-09-08', 'B', 'Islam', 'BELUM MENIKAH', 'Jl. Pemuda No. 14', 'JB05', '08123456008', '1'),
('PT003', 'Apt. Farhan Hidayat, S.Farm', 'L', 'Barabai', '1989-12-03', 'O', 'Islam', 'MENIKAH', 'Jl. Manggis No. 5', 'JB06', '08123456009', '1'),
('PT004', 'Siti Rahmawati, A.Md.RMIK', 'P', 'Barabai', '1997-02-18', 'AB', 'Islam', 'BELUM MENIKAH', 'Jl. Melati No. 88', 'JB08', '08123456010', '1'),
('PT005', 'Maya Anggraini, S.E', 'P', 'Barabai', '1995-07-22', 'O', 'Islam', 'BELUM MENIKAH', 'Jl. Kenanga No. 11', 'JB09', '08123456011', '1');

-- --------------------------------------------------------

--
-- Struktur dari tabel `poliklinik`
--

DROP TABLE IF EXISTS `poliklinik`;
CREATE TABLE IF NOT EXISTS `poliklinik` (
  `kd_poli` char(5) NOT NULL DEFAULT '',
  `nm_poli` varchar(50) DEFAULT NULL,
  `registrasi` double NOT NULL,
  `registrasilama` double NOT NULL,
  `status` enum('0','1') NOT NULL,
  PRIMARY KEY (`kd_poli`),
  KEY `nm_poli` (`nm_poli`),
  KEY `registrasi` (`registrasi`),
  KEY `registrasilama` (`registrasilama`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `poliklinik`
--

INSERT INTO `poliklinik` (`kd_poli`, `nm_poli`, `registrasi`, `registrasilama`, `status`) VALUES
('-', '-', 0, 0, '1'),
('ANA', 'Poli Anak', 25000, 20000, '1'),
('GIG', 'Poli Gigi & Mulut', 25000, 20000, '1'),
('IGD01', 'INSTALASI GAWAT DARURAT (IGD)', 35000, 30000, '1'),
('IGDK', 'Instalasi Gawat Darurat (IGD)', 35000, 30000, '1'),
('INT', 'Poli Penyakit Dalam', 30000, 25000, '1'),
('KIA', 'Poli KIA & Kebidanan', 20000, 15000, '1'),
('MAT', 'Poli Mata', 25000, 20000, '1'),
('POL01', 'POLI UMUM', 15000, 10000, '1'),
('POL02', 'POLI GIGI & MULUT', 20000, 15000, '1'),
('POL03', 'POLI PENYAKIT DALAM', 25000, 20000, '1'),
('POL04', 'POLI KANDUNGAN & KEBIDANAN (OBGYN)', 30000, 25000, '1'),
('POL05', 'POLI ANAK', 25000, 20000, '1'),
('POL06', 'POLI BEDAH', 30000, 25000, '1'),
('POL07', 'POLI MATA', 25000, 20000, '1'),
('POL08', 'POLI SYARAF', 25000, 20000, '1'),
('THT', 'Poli THT', 25000, 20000, '1'),
('UMU', 'Poli Umum', 15000, 10000, '1');

-- --------------------------------------------------------

--
-- Struktur dari tabel `propinsi`
--

DROP TABLE IF EXISTS `propinsi`;
CREATE TABLE IF NOT EXISTS `propinsi` (
  `kd_prop` int NOT NULL,
  `nm_prop` varchar(30) NOT NULL,
  PRIMARY KEY (`kd_prop`),
  UNIQUE KEY `nm_prop` (`nm_prop`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `propinsi`
--

INSERT INTO `propinsi` (`kd_prop`, `nm_prop`) VALUES
(1, '-'),
(31, 'DKI JAKARTA'),
(2, 'Jawa Barat'),
(3, 'Jawa Tengah'),
(4, 'Jawa Timur'),
(5, 'Kalimantan Selatan');

-- --------------------------------------------------------

--
-- Struktur dari tabel `prosedur_pasien`
--

DROP TABLE IF EXISTS `prosedur_pasien`;
CREATE TABLE IF NOT EXISTS `prosedur_pasien` (
  `no_rawat` varchar(17) NOT NULL,
  `kode` varchar(8) NOT NULL,
  `status` enum('Ralan','Ranap') NOT NULL,
  `prioritas` tinyint NOT NULL,
  PRIMARY KEY (`no_rawat`,`kode`,`status`),
  KEY `kode` (`kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `prosedur_pasien`
--

INSERT INTO `prosedur_pasien` (`no_rawat`, `kode`, `status`, `prioritas`) VALUES
('2026/09/08/0001', '89.07', 'Ralan', 1),
('2026/09/08/0002', '89.07', 'Ralan', 1),
('2026/09/08/0003', '96.54', 'Ralan', 1),
('2026/09/08/0004', '88.78', 'Ralan', 1),
('2026/09/08/0005', '89.07', 'Ralan', 1),
('2026/09/08/0006', '89.07', 'Ralan', 1),
('2026/09/08/0007', '89.07', 'Ralan', 1),
('2026/09/08/0008', '89.07', 'Ralan', 1),
('2026/09/08/0009', '89.07', 'Ralan', 1),
('2026/09/08/0010', '96.59', 'Ralan', 1);

-- --------------------------------------------------------

--
-- Struktur dari tabel `rawat_inap_dr`
--

DROP TABLE IF EXISTS `rawat_inap_dr`;
CREATE TABLE IF NOT EXISTS `rawat_inap_dr` (
  `no_rawat` varchar(17) NOT NULL DEFAULT '',
  `kd_jenis_prw` varchar(15) NOT NULL,
  `kd_dokter` varchar(20) NOT NULL,
  `tgl_perawatan` date NOT NULL DEFAULT '0000-00-00',
  `jam_rawat` time NOT NULL DEFAULT '00:00:00',
  `material` double NOT NULL,
  `bhp` double NOT NULL,
  `tarif_tindakandr` double NOT NULL,
  `kso` double DEFAULT NULL,
  `menejemen` double DEFAULT NULL,
  `biaya_rawat` double DEFAULT NULL,
  PRIMARY KEY (`no_rawat`,`kd_jenis_prw`,`kd_dokter`,`tgl_perawatan`,`jam_rawat`),
  KEY `no_rawat` (`no_rawat`),
  KEY `kd_jenis_prw` (`kd_jenis_prw`),
  KEY `kd_dokter` (`kd_dokter`),
  KEY `tgl_perawatan` (`tgl_perawatan`),
  KEY `biaya_rawat` (`biaya_rawat`),
  KEY `jam_rawat` (`jam_rawat`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `rawat_inap_drpr`
--

DROP TABLE IF EXISTS `rawat_inap_drpr`;
CREATE TABLE IF NOT EXISTS `rawat_inap_drpr` (
  `no_rawat` varchar(17) NOT NULL DEFAULT '',
  `kd_jenis_prw` varchar(15) NOT NULL,
  `kd_dokter` varchar(20) NOT NULL,
  `nip` varchar(20) NOT NULL DEFAULT '',
  `tgl_perawatan` date NOT NULL DEFAULT '0000-00-00',
  `jam_rawat` time NOT NULL DEFAULT '00:00:00',
  `material` double NOT NULL,
  `bhp` double NOT NULL,
  `tarif_tindakandr` double DEFAULT NULL,
  `tarif_tindakanpr` double DEFAULT NULL,
  `kso` double DEFAULT NULL,
  `menejemen` double DEFAULT NULL,
  `biaya_rawat` double DEFAULT NULL,
  PRIMARY KEY (`no_rawat`,`kd_jenis_prw`,`kd_dokter`,`nip`,`tgl_perawatan`,`jam_rawat`),
  KEY `rawat_inap_drpr_ibfk_2` (`kd_jenis_prw`),
  KEY `rawat_inap_drpr_ibfk_3` (`kd_dokter`),
  KEY `rawat_inap_drpr_ibfk_4` (`nip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `rawat_inap_pr`
--

DROP TABLE IF EXISTS `rawat_inap_pr`;
CREATE TABLE IF NOT EXISTS `rawat_inap_pr` (
  `no_rawat` varchar(17) NOT NULL DEFAULT '',
  `kd_jenis_prw` varchar(15) NOT NULL,
  `nip` varchar(20) NOT NULL DEFAULT '',
  `tgl_perawatan` date NOT NULL DEFAULT '0000-00-00',
  `jam_rawat` time NOT NULL DEFAULT '00:00:00',
  `material` double NOT NULL,
  `bhp` double NOT NULL,
  `tarif_tindakanpr` double NOT NULL,
  `kso` double DEFAULT NULL,
  `menejemen` double DEFAULT NULL,
  `biaya_rawat` double DEFAULT NULL,
  PRIMARY KEY (`no_rawat`,`kd_jenis_prw`,`nip`,`tgl_perawatan`,`jam_rawat`),
  KEY `no_rawat` (`no_rawat`),
  KEY `kd_jenis_prw` (`kd_jenis_prw`),
  KEY `nip` (`nip`),
  KEY `biaya_rawat` (`biaya_rawat`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `rawat_jl_dr`
--

DROP TABLE IF EXISTS `rawat_jl_dr`;
CREATE TABLE IF NOT EXISTS `rawat_jl_dr` (
  `no_rawat` varchar(17) NOT NULL DEFAULT '',
  `kd_jenis_prw` varchar(15) NOT NULL,
  `kd_dokter` varchar(20) NOT NULL,
  `tgl_perawatan` date NOT NULL,
  `jam_rawat` time NOT NULL,
  `material` double NOT NULL,
  `bhp` double NOT NULL,
  `tarif_tindakandr` double NOT NULL,
  `kso` double DEFAULT NULL,
  `menejemen` double DEFAULT NULL,
  `biaya_rawat` double DEFAULT NULL,
  `stts_bayar` enum('Sudah','Belum','Suspen') DEFAULT NULL,
  PRIMARY KEY (`no_rawat`,`kd_jenis_prw`,`kd_dokter`,`tgl_perawatan`,`jam_rawat`) USING BTREE,
  KEY `no_rawat` (`no_rawat`),
  KEY `kd_jenis_prw` (`kd_jenis_prw`),
  KEY `kd_dokter` (`kd_dokter`),
  KEY `biaya_rawat` (`biaya_rawat`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `rawat_jl_drpr`
--

DROP TABLE IF EXISTS `rawat_jl_drpr`;
CREATE TABLE IF NOT EXISTS `rawat_jl_drpr` (
  `no_rawat` varchar(17) NOT NULL DEFAULT '',
  `kd_jenis_prw` varchar(15) NOT NULL,
  `kd_dokter` varchar(20) NOT NULL,
  `nip` varchar(20) NOT NULL,
  `tgl_perawatan` date NOT NULL,
  `jam_rawat` time NOT NULL,
  `material` double DEFAULT NULL,
  `bhp` double NOT NULL,
  `tarif_tindakandr` double DEFAULT NULL,
  `tarif_tindakanpr` double DEFAULT NULL,
  `kso` double DEFAULT NULL,
  `menejemen` double DEFAULT NULL,
  `biaya_rawat` double DEFAULT NULL,
  `stts_bayar` enum('Sudah','Belum','Suspen') DEFAULT NULL,
  PRIMARY KEY (`no_rawat`,`kd_jenis_prw`,`kd_dokter`,`nip`,`tgl_perawatan`,`jam_rawat`) USING BTREE,
  KEY `rawat_jl_drpr_ibfk_2` (`kd_jenis_prw`),
  KEY `rawat_jl_drpr_ibfk_3` (`kd_dokter`),
  KEY `rawat_jl_drpr_ibfk_4` (`nip`),
  KEY `no_rawat` (`no_rawat`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `rawat_jl_drpr`
--

INSERT INTO `rawat_jl_drpr` (`no_rawat`, `kd_jenis_prw`, `kd_dokter`, `nip`, `tgl_perawatan`, `jam_rawat`, `material`, `bhp`, `tarif_tindakandr`, `tarif_tindakanpr`, `kso`, `menejemen`, `biaya_rawat`, `stts_bayar`) VALUES
('2026/09/08/0001', 'TND002', 'DR001', 'PT001', '2026-09-08', '08:15:00', 10000, 5000, 35000, 10000, 0, 5000, 65000, 'Sudah'),
('2026/09/08/0002', 'TND001', 'DR002', 'PT001', '2026-09-08', '08:30:00', 10000, 5000, 35000, 10000, 0, 5000, 65000, 'Sudah'),
('2026/09/08/0003', 'TND006', 'DR003', 'PT001', '2026-09-08', '09:00:00', 10000, 5000, 35000, 10000, 0, 5000, 65000, 'Sudah'),
('2026/09/08/0004', 'TND008', 'DR005', 'PT001', '2026-09-08', '09:30:00', 10000, 5000, 35000, 10000, 0, 5000, 65000, 'Sudah'),
('2026/09/08/0005', 'TND002', 'DR001', 'PT001', '2026-09-08', '10:00:00', 10000, 5000, 35000, 10000, 0, 5000, 65000, 'Sudah'),
('2026/09/08/0006', 'TND002', 'DR001', 'PT001', '2026-09-08', '10:30:00', 10000, 5000, 35000, 10000, 0, 5000, 65000, 'Sudah'),
('2026/09/08/0007', 'TND002', 'DR004', 'PT001', '2026-09-08', '11:00:00', 10000, 5000, 35000, 10000, 0, 5000, 65000, 'Sudah'),
('2026/09/08/0008', 'TND001', 'DR002', 'PT001', '2026-09-08', '11:30:00', 10000, 5000, 35000, 10000, 0, 5000, 65000, 'Sudah'),
('2026/09/08/0009', 'TND002', 'DR001', 'PT001', '2026-09-08', '12:00:00', 10000, 5000, 35000, 10000, 0, 5000, 65000, 'Sudah'),
('2026/09/08/0010', 'TND009', 'DR002', 'PT001', '2026-09-08', '12:30:00', 10000, 5000, 35000, 10000, 0, 5000, 65000, 'Sudah');

-- --------------------------------------------------------

--
-- Struktur dari tabel `rawat_jl_pr`
--

DROP TABLE IF EXISTS `rawat_jl_pr`;
CREATE TABLE IF NOT EXISTS `rawat_jl_pr` (
  `no_rawat` varchar(17) NOT NULL DEFAULT '',
  `kd_jenis_prw` varchar(15) NOT NULL,
  `nip` varchar(20) NOT NULL DEFAULT '',
  `tgl_perawatan` date NOT NULL,
  `jam_rawat` time NOT NULL,
  `material` double NOT NULL,
  `bhp` double NOT NULL,
  `tarif_tindakanpr` double NOT NULL,
  `kso` double DEFAULT NULL,
  `menejemen` double DEFAULT NULL,
  `biaya_rawat` double DEFAULT NULL,
  `stts_bayar` enum('Sudah','Belum','Suspen') DEFAULT NULL,
  PRIMARY KEY (`no_rawat`,`kd_jenis_prw`,`nip`,`tgl_perawatan`,`jam_rawat`) USING BTREE,
  KEY `no_rawat` (`no_rawat`),
  KEY `kd_jenis_prw` (`kd_jenis_prw`),
  KEY `nip` (`nip`),
  KEY `biaya_rawat` (`biaya_rawat`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `reg_periksa`
--

DROP TABLE IF EXISTS `reg_periksa`;
CREATE TABLE IF NOT EXISTS `reg_periksa` (
  `no_reg` varchar(8) DEFAULT NULL,
  `no_rawat` varchar(17) NOT NULL,
  `tgl_registrasi` date DEFAULT NULL,
  `jam_reg` time DEFAULT NULL,
  `kd_dokter` varchar(20) DEFAULT NULL,
  `no_rkm_medis` varchar(15) DEFAULT NULL,
  `kd_poli` char(5) DEFAULT NULL,
  `p_jawab` varchar(100) DEFAULT NULL,
  `almt_pj` varchar(200) DEFAULT NULL,
  `hubunganpj` varchar(20) DEFAULT NULL,
  `biaya_reg` double DEFAULT NULL,
  `stts` enum('Belum','Sudah','Batal','Berkas Diterima','Dirujuk','Meninggal','Dirawat','Pulang Paksa') DEFAULT NULL,
  `stts_daftar` enum('-','Lama','Baru') NOT NULL,
  `status_lanjut` enum('Ralan','Ranap') NOT NULL,
  `kd_pj` char(3) NOT NULL,
  `umurdaftar` int DEFAULT NULL,
  `sttsumur` enum('Th','Bl','Hr') DEFAULT NULL,
  `status_bayar` enum('Sudah Bayar','Belum Bayar') NOT NULL,
  `status_poli` enum('Lama','Baru') NOT NULL,
  PRIMARY KEY (`no_rawat`),
  KEY `no_rkm_medis` (`no_rkm_medis`),
  KEY `kd_poli` (`kd_poli`),
  KEY `kd_pj` (`kd_pj`),
  KEY `status_lanjut` (`status_lanjut`),
  KEY `kd_dokter` (`kd_dokter`),
  KEY `status_bayar` (`status_bayar`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `reg_periksa`
--

INSERT INTO `reg_periksa` (`no_reg`, `no_rawat`, `tgl_registrasi`, `jam_reg`, `kd_dokter`, `no_rkm_medis`, `kd_poli`, `p_jawab`, `almt_pj`, `hubunganpj`, `biaya_reg`, `stts`, `stts_daftar`, `status_lanjut`, `kd_pj`, `umurdaftar`, `sttsumur`, `status_bayar`, `status_poli`) VALUES
('001', '2026/09/08/0001', '2026-09-08', '08:15:00', 'DR001', '000001', 'POL03', 'SITI AISYAH', 'JL. KEBON SIRIH NO. 12', 'ISTRI', 25000, 'Sudah', 'Lama', 'Ralan', 'BPJ', 46, 'Th', 'Sudah Bayar', 'Lama'),
('002', '2026/09/08/0002', '2026-09-08', '08:30:00', 'DR002', '000002', 'POL01', 'ANDI WIJAYA', 'JL. TEBET TIMUR DALAM NO. 25', 'SUAMI', 15000, 'Sudah', 'Lama', 'Ralan', 'BPJ', 41, 'Th', 'Sudah Bayar', 'Lama'),
('003', '2026/09/08/0003', '2026-09-08', '09:00:00', 'DR003', '000003', 'POL02', 'HENDRO KURNIAWAN', 'JL. AHMAD YANI NO. 88', 'DIRI SENDIRI', 20000, 'Sudah', 'Lama', 'Ralan', 'UMU', 33, 'Th', 'Sudah Bayar', 'Lama'),
('004', '2026/09/08/0004', '2026-09-08', '09:30:00', 'DR005', '000004', 'POL04', 'RIZKY FAUZI', 'JL. PAJAJARAN NO. 45', 'SUAMI', 30000, 'Sudah', 'Lama', 'Ralan', 'BPJ', 31, 'Th', 'Sudah Bayar', 'Lama'),
('005', '2026/09/08/0005', '2026-09-08', '10:00:00', 'DR001', '000005', 'POL03', 'MARLINA', 'JL. MANGGA BESAR NO. 101', 'ISTRI', 25000, 'Sudah', 'Lama', 'Ralan', 'UMU', 48, 'Th', 'Sudah Bayar', 'Lama'),
('006', '2026/09/08/0006', '2026-09-08', '10:30:00', 'DR001', '000006', 'POL03', 'SUGENG RIYADI', 'JL. FATMAWATI RAYA NO. 30', 'SUAMI', 25000, 'Sudah', 'Lama', 'Ralan', 'ASR', 44, 'Th', 'Sudah Bayar', 'Lama'),
('007', '2026/09/08/0007', '2026-09-08', '11:00:00', 'DR004', '000007', 'POL05', 'ANDI WIJAYA', 'JL. CEMPAKA PUTIH NO. 5', 'AYAH', 25000, 'Sudah', 'Lama', 'Ralan', 'BPJ', 8, 'Th', 'Sudah Bayar', 'Lama'),
('008', '2026/09/08/0008', '2026-09-08', '11:30:00', 'DR002', '000008', 'POL01', 'EKO PRASETYO', 'JL. IR. JUANDA NO. 14', 'SUAMI', 15000, 'Sudah', 'Lama', 'Ralan', 'PRH', 35, 'Th', 'Sudah Bayar', 'Lama'),
('009', '2026/09/08/0009', '2026-09-08', '12:00:00', 'DR001', '000009', 'POL03', 'NURUL AINI', 'JL. IR. JUANDA NO. 14', 'ISTRI', 25000, 'Sudah', 'Lama', 'Ralan', 'UMU', 38, 'Th', 'Sudah Bayar', 'Lama'),
('010', '2026/09/08/0010', '2026-09-08', '12:30:00', 'DR002', '000010', 'POL01', 'AMINA', 'JL. KRAMAT JATI NO. 7', 'IBU', 15000, 'Sudah', 'Lama', 'Ralan', 'BPJ', 28, 'Th', 'Sudah Bayar', 'Lama');

-- --------------------------------------------------------

--
-- Struktur dari tabel `rekap_presensi`
--

DROP TABLE IF EXISTS `rekap_presensi`;
CREATE TABLE IF NOT EXISTS `rekap_presensi` (
  `id` int NOT NULL,
  `shift` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10') NOT NULL,
  `jam_datang` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `jam_pulang` datetime DEFAULT NULL,
  `status` enum('Tepat Waktu','Terlambat Toleransi','Terlambat I','Terlambat II','Tepat Waktu & PSW','Terlambat Toleransi & PSW','Terlambat I & PSW','Terlambat II & PSW') NOT NULL,
  `keterlambatan` varchar(20) NOT NULL,
  `durasi` varchar(20) DEFAULT NULL,
  `keterangan` varchar(100) NOT NULL,
  `photo` text NOT NULL,
  PRIMARY KEY (`id`,`jam_datang`),
  KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `resep_dokter`
--

DROP TABLE IF EXISTS `resep_dokter`;
CREATE TABLE IF NOT EXISTS `resep_dokter` (
  `no_resep` varchar(14) DEFAULT NULL,
  `kode_brng` varchar(15) DEFAULT NULL,
  `jml` double DEFAULT NULL,
  `aturan_pakai` varchar(150) DEFAULT NULL,
  KEY `no_resep` (`no_resep`),
  KEY `kode_brng` (`kode_brng`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `resep_dokter`
--

INSERT INTO `resep_dokter` (`no_resep`, `kode_brng`, `jml`, `aturan_pakai`) VALUES
('RSP0000001', 'OB003', 30, '1 x 1 Tablet Sehari Pagi Sesudah Makan'),
('RSP0000002', 'OB001', 10, '3 x 1 Tablet Sehari Sesudah Makan'),
('RSP0000002', 'OB002', 10, '3 x 1 Kaplet Sehari Sesudah Makan (Habiskan)'),
('RSP0000002', 'OB014', 10, '3 x 1 Tablet Sehari Sesudah Makan'),
('RSP0000005', 'OB007', 14, '2 x 1 Kapsul Sehari 30 Menit Sebelum Makan'),
('RSP0000005', 'OB008', 20, '3 x 1 Tablet Kunyah Sehari 1 Jam Sebelum Makan'),
('RSP0000006', 'OB005', 60, '2 x 1 Tablet Sehari Bersama Makan'),
('RSP0000006', 'OB006', 30, '1 x 1 Tablet Sehari Pagi Sebelum Makan'),
('RSP0000007', 'OB018', 1, '3 x 1 Sendok Takar (5 ml) Sehari Sesudah Makan Bila Demam'),
('RSP0000007', 'OB019', 1, '3 x 1 Sendok Takar (5 ml) Sehari Sesudah Makan (Habiskan)'),
('RSP0000010', 'OB002', 10, '3 x 1 Kaplet Sehari Sesudah Makan (Habiskan)'),
('RSP0000010', 'OB011', 10, '3 x 1 Kaplet Sehari Sesudah Makan Bila Sakit'),
('RSP0000001', 'OB003', 30, '1 x 1 Tablet Sehari Pagi Sesudah Makan'),
('RSP0000002', 'OB001', 10, '3 x 1 Tablet Sehari Sesudah Makan'),
('RSP0000002', 'OB002', 10, '3 x 1 Kaplet Sehari Sesudah Makan (Habiskan)'),
('RSP0000002', 'OB014', 10, '3 x 1 Tablet Sehari Sesudah Makan'),
('RSP0000003', 'OB011', 10, '3 x 1 Kaplet Sehari Sesudah Makan Bila Nyeri'),
('RSP0000003', 'OB002', 10, '3 x 1 Kaplet Sehari Sesudah Makan (Habiskan)'),
('RSP0000004', 'OB012', 30, '1 x 1 Tablet Sehari Sesudah Makan'),
('RSP0000004', 'OB013', 30, '1 x 1 Tablet Sehari Pagi Sesudah Makan'),
('RSP0000005', 'OB007', 14, '2 x 1 Kapsul Sehari 30 Menit Sebelum Makan'),
('RSP0000005', 'OB008', 20, '3 x 1 Tablet Kunyah Sehari 1 Jam Sebelum Makan'),
('RSP0000006', 'OB005', 60, '2 x 1 Tablet Sehari Bersama Makan'),
('RSP0000006', 'OB006', 30, '1 x 1 Tablet Sehari Pagi Sebelum Makan'),
('RSP0000007', 'OB018', 1, '3 x 1 Sendok Takar (5 ml) Sehari Sesudah Makan Bila Demam'),
('RSP0000007', 'OB019', 1, '3 x 1 Sendok Takar (5 ml) Sehari Sesudah Makan (Habiskan)'),
('RSP0000008', 'OB015', 10, '2 x 1 Tablet Sehari Sesudah Makan (Habiskan)'),
('RSP0000008', 'OB008', 10, '3 x 1 Tablet Kunyah Sehari'),
('RSP0000008', 'OB001', 10, '3 x 1 Tablet Sehari Sesudah Makan Bila Nyeri'),
('RSP0000009', 'OB010', 30, '1 x 1 Tablet Sehari Sesudah Makan'),
('RSP0000009', 'OB011', 10, '3 x 1 Kaplet Sehari Sesudah Makan Bila Nyeri'),
('RSP0000010', 'OB002', 10, '3 x 1 Kaplet Sehari Sesudah Makan (Habiskan)'),
('RSP0000010', 'OB011', 10, '3 x 1 Kaplet Sehari Sesudah Makan Bila Sakit'),
('RSP0000001', 'OB003', 30, '1 x 1 Tablet Sehari Pagi Sesudah Makan'),
('RSP0000002', 'OB001', 10, '3 x 1 Tablet Sehari Sesudah Makan'),
('RSP0000002', 'OB002', 10, '3 x 1 Kaplet Sehari Sesudah Makan (Habiskan)'),
('RSP0000002', 'OB014', 10, '3 x 1 Tablet Sehari Sesudah Makan'),
('RSP0000003', 'OB011', 10, '3 x 1 Kaplet Sehari Sesudah Makan Bila Nyeri'),
('RSP0000003', 'OB002', 10, '3 x 1 Kaplet Sehari Sesudah Makan (Habiskan)'),
('RSP0000004', 'OB012', 30, '1 x 1 Tablet Sehari Sesudah Makan'),
('RSP0000004', 'OB013', 30, '1 x 1 Tablet Sehari Pagi Sesudah Makan'),
('RSP0000005', 'OB007', 14, '2 x 1 Kapsul Sehari 30 Menit Sebelum Makan'),
('RSP0000005', 'OB008', 20, '3 x 1 Tablet Kunyah Sehari 1 Jam Sebelum Makan'),
('RSP0000006', 'OB005', 60, '2 x 1 Tablet Sehari Bersama Makan'),
('RSP0000006', 'OB006', 30, '1 x 1 Tablet Sehari Pagi Sebelum Makan'),
('RSP0000007', 'OB018', 1, '3 x 1 Sendok Takar (5 ml) Sehari Sesudah Makan Bila Demam'),
('RSP0000007', 'OB019', 1, '3 x 1 Sendok Takar (5 ml) Sehari Sesudah Makan (Habiskan)'),
('RSP0000008', 'OB015', 10, '2 x 1 Tablet Sehari Sesudah Makan (Habiskan)'),
('RSP0000008', 'OB008', 10, '3 x 1 Tablet Kunyah Sehari'),
('RSP0000008', 'OB001', 10, '3 x 1 Tablet Sehari Sesudah Makan Bila Nyeri'),
('RSP0000009', 'OB010', 30, '1 x 1 Tablet Sehari Sesudah Makan'),
('RSP0000009', 'OB011', 10, '3 x 1 Kaplet Sehari Sesudah Makan Bila Nyeri'),
('RSP0000010', 'OB002', 10, '3 x 1 Kaplet Sehari Sesudah Makan (Habiskan)'),
('RSP0000010', 'OB011', 10, '3 x 1 Kaplet Sehari Sesudah Makan Bila Sakit'),
('RSP0000001', 'OB003', 30, '1 x 1 Tablet Sehari Pagi Sesudah Makan'),
('RSP0000002', 'OB001', 10, '3 x 1 Tablet Sehari Sesudah Makan'),
('RSP0000002', 'OB002', 10, '3 x 1 Kaplet Sehari Sesudah Makan (Habiskan)'),
('RSP0000002', 'OB014', 10, '3 x 1 Tablet Sehari Sesudah Makan'),
('RSP0000003', 'OB011', 10, '3 x 1 Kaplet Sehari Sesudah Makan Bila Nyeri'),
('RSP0000003', 'OB002', 10, '3 x 1 Kaplet Sehari Sesudah Makan (Habiskan)'),
('RSP0000004', 'OB012', 30, '1 x 1 Tablet Sehari Sesudah Makan'),
('RSP0000004', 'OB013', 30, '1 x 1 Tablet Sehari Pagi Sesudah Makan'),
('RSP0000005', 'OB007', 14, '2 x 1 Kapsul Sehari 30 Menit Sebelum Makan'),
('RSP0000005', 'OB008', 20, '3 x 1 Tablet Kunyah Sehari 1 Jam Sebelum Makan'),
('RSP0000006', 'OB005', 60, '2 x 1 Tablet Sehari Bersama Makan'),
('RSP0000006', 'OB006', 30, '1 x 1 Tablet Sehari Pagi Sebelum Makan'),
('RSP0000007', 'OB018', 1, '3 x 1 Sendok Takar (5 ml) Sehari Sesudah Makan Bila Demam'),
('RSP0000007', 'OB019', 1, '3 x 1 Sendok Takar (5 ml) Sehari Sesudah Makan (Habiskan)'),
('RSP0000008', 'OB015', 10, '2 x 1 Tablet Sehari Sesudah Makan (Habiskan)'),
('RSP0000008', 'OB008', 10, '3 x 1 Tablet Kunyah Sehari'),
('RSP0000008', 'OB001', 10, '3 x 1 Tablet Sehari Sesudah Makan Bila Nyeri'),
('RSP0000009', 'OB010', 30, '1 x 1 Tablet Sehari Sesudah Makan'),
('RSP0000009', 'OB011', 10, '3 x 1 Kaplet Sehari Sesudah Makan Bila Nyeri'),
('RSP0000010', 'OB002', 10, '3 x 1 Kaplet Sehari Sesudah Makan (Habiskan)'),
('RSP0000010', 'OB011', 10, '3 x 1 Kaplet Sehari Sesudah Makan Bila Sakit');

-- --------------------------------------------------------

--
-- Struktur dari tabel `resep_dokter_racikan`
--

DROP TABLE IF EXISTS `resep_dokter_racikan`;
CREATE TABLE IF NOT EXISTS `resep_dokter_racikan` (
  `no_resep` varchar(14) NOT NULL,
  `no_racik` varchar(2) NOT NULL,
  `nama_racik` varchar(100) NOT NULL,
  `kd_racik` varchar(3) NOT NULL,
  `jml_dr` int NOT NULL,
  `aturan_pakai` varchar(150) NOT NULL,
  `keterangan` varchar(50) NOT NULL,
  PRIMARY KEY (`no_resep`,`no_racik`),
  KEY `kd_racik` (`kd_racik`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `resep_dokter_racikan_detail`
--

DROP TABLE IF EXISTS `resep_dokter_racikan_detail`;
CREATE TABLE IF NOT EXISTS `resep_dokter_racikan_detail` (
  `no_resep` varchar(14) NOT NULL,
  `no_racik` varchar(2) NOT NULL,
  `kode_brng` varchar(15) NOT NULL,
  `p1` double DEFAULT NULL,
  `p2` double DEFAULT NULL,
  `kandungan` varchar(10) DEFAULT NULL,
  `jml` double DEFAULT NULL,
  PRIMARY KEY (`no_resep`,`no_racik`,`kode_brng`),
  KEY `kode_brng` (`kode_brng`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `resep_obat`
--

DROP TABLE IF EXISTS `resep_obat`;
CREATE TABLE IF NOT EXISTS `resep_obat` (
  `no_resep` varchar(14) NOT NULL DEFAULT '',
  `tgl_perawatan` date DEFAULT NULL,
  `jam` time NOT NULL,
  `no_rawat` varchar(17) NOT NULL DEFAULT '',
  `kd_dokter` varchar(20) NOT NULL,
  `tgl_peresepan` date DEFAULT NULL,
  `jam_peresepan` time DEFAULT NULL,
  `status` enum('ralan','ranap') DEFAULT NULL,
  `tgl_penyerahan` date NOT NULL,
  `jam_penyerahan` time NOT NULL,
  PRIMARY KEY (`no_resep`),
  KEY `no_rawat` (`no_rawat`),
  KEY `kd_dokter` (`kd_dokter`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `resep_obat`
--

INSERT INTO `resep_obat` (`no_resep`, `tgl_perawatan`, `jam`, `no_rawat`, `kd_dokter`, `tgl_peresepan`, `jam_peresepan`, `status`, `tgl_penyerahan`, `jam_penyerahan`) VALUES
('RSP0000001', '2026-09-08', '08:15:00', '2026/09/08/0001', 'DR001', '2026-09-08', '08:15:00', 'ralan', '2026-09-08', '08:15:00'),
('RSP0000002', '2026-09-08', '08:30:00', '2026/09/08/0002', 'DR002', '2026-09-08', '08:30:00', 'ralan', '2026-09-08', '08:30:00'),
('RSP0000003', '2026-09-08', '09:00:00', '2026/09/08/0003', 'DR003', '2026-09-08', '09:00:00', 'ralan', '2026-09-08', '09:00:00'),
('RSP0000004', '2026-09-08', '09:30:00', '2026/09/08/0004', 'DR005', '2026-09-08', '09:30:00', 'ralan', '2026-09-08', '09:30:00'),
('RSP0000005', '2026-09-08', '10:00:00', '2026/09/08/0005', 'DR001', '2026-09-08', '10:00:00', 'ralan', '2026-09-08', '10:00:00'),
('RSP0000006', '2026-09-08', '10:30:00', '2026/09/08/0006', 'DR001', '2026-09-08', '10:30:00', 'ralan', '2026-09-08', '10:30:00'),
('RSP0000007', '2026-09-08', '11:00:00', '2026/09/08/0007', 'DR004', '2026-09-08', '11:00:00', 'ralan', '2026-09-08', '11:00:00'),
('RSP0000008', '2026-09-08', '11:30:00', '2026/09/08/0008', 'DR002', '2026-09-08', '11:30:00', 'ralan', '2026-09-08', '11:30:00'),
('RSP0000009', '2026-09-08', '12:00:00', '2026/09/08/0009', 'DR001', '2026-09-08', '12:00:00', 'ralan', '2026-09-08', '12:00:00'),
('RSP0000010', '2026-09-08', '12:30:00', '2026/09/08/0010', 'DR002', '2026-09-08', '12:30:00', 'ralan', '2026-09-08', '12:30:00');

-- --------------------------------------------------------

--
-- Struktur dari tabel `resep_pulang`
--

DROP TABLE IF EXISTS `resep_pulang`;
CREATE TABLE IF NOT EXISTS `resep_pulang` (
  `no_rawat` varchar(17) NOT NULL,
  `kode_brng` varchar(15) NOT NULL,
  `jml_barang` double NOT NULL,
  `harga` double NOT NULL,
  `total` double NOT NULL,
  `dosis` varchar(150) NOT NULL,
  `tanggal` date NOT NULL,
  `jam` time NOT NULL,
  `kd_bangsal` varchar(5) NOT NULL,
  `no_batch` varchar(20) NOT NULL,
  `no_faktur` varchar(20) NOT NULL,
  PRIMARY KEY (`no_rawat`,`kode_brng`,`tanggal`,`jam`,`no_batch`,`no_faktur`),
  KEY `kode_brng` (`kode_brng`),
  KEY `kd_bangsal` (`kd_bangsal`),
  KEY `no_rawat` (`no_rawat`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `resiko_kerja`
--

DROP TABLE IF EXISTS `resiko_kerja`;
CREATE TABLE IF NOT EXISTS `resiko_kerja` (
  `kode_resiko` varchar(3) NOT NULL,
  `nama_resiko` varchar(200) DEFAULT NULL,
  `indek` tinyint DEFAULT NULL,
  PRIMARY KEY (`kode_resiko`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `resiko_kerja`
--

INSERT INTO `resiko_kerja` (`kode_resiko`, `nama_resiko`, `indek`) VALUES
('-', '-', 1);

-- --------------------------------------------------------

--
-- Struktur dari tabel `resume_pasien`
--

DROP TABLE IF EXISTS `resume_pasien`;
CREATE TABLE IF NOT EXISTS `resume_pasien` (
  `no_rawat` varchar(17) NOT NULL,
  `kd_dokter` varchar(20) NOT NULL,
  `keluhan_utama` text NOT NULL,
  `jalannya_penyakit` text NOT NULL,
  `pemeriksaan_penunjang` text NOT NULL,
  `hasil_laborat` text NOT NULL,
  `diagnosa_utama` varchar(80) NOT NULL,
  `kd_diagnosa_utama` varchar(10) NOT NULL,
  `diagnosa_sekunder` varchar(80) NOT NULL,
  `kd_diagnosa_sekunder` varchar(10) NOT NULL,
  `diagnosa_sekunder2` varchar(80) NOT NULL,
  `kd_diagnosa_sekunder2` varchar(10) NOT NULL,
  `diagnosa_sekunder3` varchar(80) NOT NULL,
  `kd_diagnosa_sekunder3` varchar(10) NOT NULL,
  `diagnosa_sekunder4` varchar(80) NOT NULL,
  `kd_diagnosa_sekunder4` varchar(10) NOT NULL,
  `prosedur_utama` varchar(80) NOT NULL,
  `kd_prosedur_utama` varchar(8) NOT NULL,
  `prosedur_sekunder` varchar(80) NOT NULL,
  `kd_prosedur_sekunder` varchar(8) NOT NULL,
  `prosedur_sekunder2` varchar(80) NOT NULL,
  `kd_prosedur_sekunder2` varchar(8) NOT NULL,
  `prosedur_sekunder3` varchar(80) NOT NULL,
  `kd_prosedur_sekunder3` varchar(8) NOT NULL,
  `kondisi_pulang` enum('Hidup','Meninggal') NOT NULL,
  `obat_pulang` text NOT NULL,
  PRIMARY KEY (`no_rawat`),
  KEY `kd_dokter` (`kd_dokter`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `resume_pasien_ranap`
--

DROP TABLE IF EXISTS `resume_pasien_ranap`;
CREATE TABLE IF NOT EXISTS `resume_pasien_ranap` (
  `no_rawat` varchar(17) NOT NULL,
  `kd_dokter` varchar(20) NOT NULL,
  `diagnosa_awal` varchar(100) NOT NULL,
  `alasan` varchar(100) NOT NULL,
  `keluhan_utama` text NOT NULL,
  `pemeriksaan_fisik` text NOT NULL,
  `jalannya_penyakit` text NOT NULL,
  `pemeriksaan_penunjang` text NOT NULL,
  `hasil_laborat` text NOT NULL,
  `tindakan_dan_operasi` text NOT NULL,
  `obat_di_rs` text NOT NULL,
  `diagnosa_utama` varchar(80) NOT NULL,
  `kd_diagnosa_utama` varchar(10) NOT NULL,
  `diagnosa_sekunder` varchar(80) NOT NULL,
  `kd_diagnosa_sekunder` varchar(10) NOT NULL,
  `diagnosa_sekunder2` varchar(80) NOT NULL,
  `kd_diagnosa_sekunder2` varchar(10) NOT NULL,
  `diagnosa_sekunder3` varchar(80) NOT NULL,
  `kd_diagnosa_sekunder3` varchar(10) NOT NULL,
  `diagnosa_sekunder4` varchar(80) NOT NULL,
  `kd_diagnosa_sekunder4` varchar(10) NOT NULL,
  `prosedur_utama` varchar(80) NOT NULL,
  `kd_prosedur_utama` varchar(8) NOT NULL,
  `prosedur_sekunder` varchar(80) NOT NULL,
  `kd_prosedur_sekunder` varchar(8) NOT NULL,
  `prosedur_sekunder2` varchar(80) NOT NULL,
  `kd_prosedur_sekunder2` varchar(8) NOT NULL,
  `prosedur_sekunder3` varchar(80) NOT NULL,
  `kd_prosedur_sekunder3` varchar(8) NOT NULL,
  `alergi` varchar(100) NOT NULL,
  `diet` text NOT NULL,
  `lab_belum` text NOT NULL,
  `edukasi` text NOT NULL,
  `cara_keluar` enum('Atas Izin Dokter','Pindah RS','Pulang Atas Permintaan Sendiri','Lainnya') NOT NULL,
  `ket_keluar` varchar(50) DEFAULT NULL,
  `keadaan` enum('Membaik','Sembuh','Keadaan Khusus','Meninggal') NOT NULL,
  `ket_keadaan` varchar(50) DEFAULT NULL,
  `dilanjutkan` enum('Kembali Ke RS','RS Lain','Dokter Luar','Puskesmes','Lainnya') NOT NULL,
  `ket_dilanjutkan` varchar(50) DEFAULT NULL,
  `kontrol` datetime DEFAULT NULL,
  `obat_pulang` text NOT NULL,
  PRIMARY KEY (`no_rawat`),
  KEY `kd_dokter` (`kd_dokter`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `riwayat_barang_medis`
--

DROP TABLE IF EXISTS `riwayat_barang_medis`;
CREATE TABLE IF NOT EXISTS `riwayat_barang_medis` (
  `kode_brng` varchar(15) DEFAULT NULL,
  `stok_awal` double DEFAULT NULL,
  `masuk` double DEFAULT NULL,
  `keluar` double DEFAULT NULL,
  `stok_akhir` double NOT NULL,
  `posisi` enum('Pemberian Obat','Pengadaan','Penerimaan','Piutang','Retur Beli','Retur Jual','Retur Piutang','Mutasi','Opname','Resep Pulang','Retur Pasien','Stok Pasien Ranap','Pengambilan Medis','Penjualan','Stok Keluar','Hibah') DEFAULT NULL,
  `tanggal` date DEFAULT NULL,
  `jam` time DEFAULT NULL,
  `petugas` varchar(100) DEFAULT NULL,
  `kd_bangsal` char(5) DEFAULT NULL,
  `status` enum('Simpan','Hapus') DEFAULT NULL,
  `no_batch` varchar(20) NOT NULL,
  `no_faktur` varchar(20) NOT NULL,
  `keterangan` varchar(100) NOT NULL,
  KEY `riwayat_barang_medis_ibfk_1` (`kode_brng`) USING BTREE,
  KEY `kd_bangsal` (`kd_bangsal`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `ruang_ok`
--

DROP TABLE IF EXISTS `ruang_ok`;
CREATE TABLE IF NOT EXISTS `ruang_ok` (
  `kd_ruang_ok` varchar(3) NOT NULL,
  `nm_ruang_ok` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`kd_ruang_ok`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `set_keterlambatan`
--

DROP TABLE IF EXISTS `set_keterlambatan`;
CREATE TABLE IF NOT EXISTS `set_keterlambatan` (
  `toleransi` int DEFAULT NULL,
  `terlambat1` int DEFAULT NULL,
  `terlambat2` int DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `set_no_rkm_medis`
--

DROP TABLE IF EXISTS `set_no_rkm_medis`;
CREATE TABLE IF NOT EXISTS `set_no_rkm_medis` (
  `no_rkm_medis` varchar(15) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `set_no_rkm_medis`
--

INSERT INTO `set_no_rkm_medis` (`no_rkm_medis`) VALUES
('000000');

-- --------------------------------------------------------

--
-- Struktur dari tabel `skdp_bpjs`
--

DROP TABLE IF EXISTS `skdp_bpjs`;
CREATE TABLE IF NOT EXISTS `skdp_bpjs` (
  `tahun` year NOT NULL,
  `no_rkm_medis` varchar(15) DEFAULT NULL,
  `diagnosa` varchar(50) NOT NULL,
  `terapi` varchar(50) NOT NULL,
  `alasan1` varchar(50) DEFAULT NULL,
  `alasan2` varchar(50) DEFAULT NULL,
  `rtl1` varchar(50) DEFAULT NULL,
  `rtl2` varchar(50) DEFAULT NULL,
  `tanggal_datang` datetime DEFAULT NULL,
  `tanggal_rujukan` datetime NOT NULL,
  `no_antrian` varchar(6) NOT NULL,
  `kd_dokter` varchar(20) DEFAULT NULL,
  `status` enum('Menunggu','Sudah Periksa','Batal Periksa') NOT NULL,
  PRIMARY KEY (`tahun`,`no_antrian`) USING BTREE,
  KEY `no_rkm_medis` (`no_rkm_medis`) USING BTREE,
  KEY `kd_dokter` (`kd_dokter`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `spesialis`
--

DROP TABLE IF EXISTS `spesialis`;
CREATE TABLE IF NOT EXISTS `spesialis` (
  `kd_sps` char(5) NOT NULL DEFAULT '',
  `nm_sps` varchar(30) DEFAULT NULL,
  PRIMARY KEY (`kd_sps`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `spesialis`
--

INSERT INTO `spesialis` (`kd_sps`, `nm_sps`) VALUES
('S0001', 'Umum'),
('S0002', 'Spesialis Penyakit Dalam'),
('S0003', 'Spesialis Gigi dan Mulut'),
('S0004', 'Spesialis Kebidanan & Kandunga'),
('S0005', 'Spesialis Anak'),
('S0006', 'Spesialis Bedah Umum'),
('S0007', 'Spesialis Mata'),
('S0008', 'Spesialis Neurologi/Syaraf'),
('SPS-A', 'Spesialis Anak (Sp.A)'),
('SPS-G', 'Dokter Gigi (drg)'),
('SPS-O', 'Spesialis Obstetri & Ginekolog'),
('SPS-P', 'Spesialis Penyakit Dalam (Sp.P'),
('SPS-T', 'Spesialis THT-KL'),
('UMUM', 'Dokter Umum');

-- --------------------------------------------------------

--
-- Struktur dari tabel `stts_kerja`
--

DROP TABLE IF EXISTS `stts_kerja`;
CREATE TABLE IF NOT EXISTS `stts_kerja` (
  `stts` char(3) NOT NULL,
  `ktg` varchar(20) NOT NULL,
  `indek` tinyint NOT NULL,
  PRIMARY KEY (`stts`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `stts_kerja`
--

INSERT INTO `stts_kerja` (`stts`, `ktg`, `indek`) VALUES
('-', '-', 1),
('Kon', 'Karyawan Kontrak', 2),
('KTR', 'Karyawan Kontrak', 1),
('Mit', 'Mitra Medis', 3),
('MTR', 'Dokter Mitra Kerja', 1),
('Tet', 'Karyawan Tetap', 1),
('TTP', 'Karyawan Tetap', 1);

-- --------------------------------------------------------

--
-- Struktur dari tabel `stts_wp`
--

DROP TABLE IF EXISTS `stts_wp`;
CREATE TABLE IF NOT EXISTS `stts_wp` (
  `stts` char(5) NOT NULL,
  `ktg` varchar(50) NOT NULL,
  PRIMARY KEY (`stts`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `stts_wp`
--

INSERT INTO `stts_wp` (`stts`, `ktg`) VALUES
('-', '-'),
('K/0', 'Kawin 0 Tanggungan'),
('K/1', 'Kawin 1 Tanggungan'),
('K/2', 'Kawin 2 Tanggungan'),
('K/3', 'Kawin 3 Tanggungan'),
('TK/0', 'Tidak Kawin 0 Tanggungan');

-- --------------------------------------------------------

--
-- Struktur dari tabel `suku_bangsa`
--

DROP TABLE IF EXISTS `suku_bangsa`;
CREATE TABLE IF NOT EXISTS `suku_bangsa` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nama_suku_bangsa` varchar(30) DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `nama_suku_bangsa` (`nama_suku_bangsa`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `suku_bangsa`
--

INSERT INTO `suku_bangsa` (`id`, `nama_suku_bangsa`) VALUES
(1, '-'),
(3, 'Banjar'),
(4, 'Batak'),
(6, 'Betawi'),
(5, 'Madura'),
(2, 'Sunda');

-- --------------------------------------------------------

--
-- Struktur dari tabel `tambahan_biaya`
--

DROP TABLE IF EXISTS `tambahan_biaya`;
CREATE TABLE IF NOT EXISTS `tambahan_biaya` (
  `no_rawat` varchar(17) NOT NULL,
  `nama_biaya` varchar(60) NOT NULL,
  `besar_biaya` double NOT NULL,
  PRIMARY KEY (`no_rawat`,`nama_biaya`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `telaah_resep`
--

DROP TABLE IF EXISTS `telaah_resep`;
CREATE TABLE IF NOT EXISTS `telaah_resep` (
  `no_resep` varchar(14) NOT NULL,
  `tgl_telaah` date DEFAULT NULL,
  `jam_telaah` time DEFAULT NULL,
  `petugas` varchar(50) DEFAULT NULL,
  `tepat_identitas` enum('Ya','Tidak') DEFAULT 'Ya',
  `tepat_indikasi` enum('Ya','Tidak') DEFAULT 'Ya',
  `tepat_dosis` enum('Ya','Tidak') DEFAULT 'Ya',
  `tepat_waktu` enum('Ya','Tidak') DEFAULT 'Ya',
  `duplikasi` enum('Ya','Tidak') DEFAULT 'Tidak',
  `alergi` enum('Ya','Tidak') DEFAULT 'Tidak',
  `interaksi` enum('Ya','Tidak') DEFAULT 'Tidak',
  `status_telaah` varchar(20) DEFAULT 'Lolos',
  `catatan_telaah` text,
  PRIMARY KEY (`no_resep`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `telaah_resep`
--

INSERT INTO `telaah_resep` (`no_resep`, `tgl_telaah`, `jam_telaah`, `petugas`, `tepat_identitas`, `tepat_indikasi`, `tepat_dosis`, `tepat_waktu`, `duplikasi`, `alergi`, `interaksi`, `status_telaah`, `catatan_telaah`) VALUES
('RSP0000001', '2026-09-08', '08:15:00', 'PET003', 'Ya', 'Ya', 'Ya', 'Ya', 'Ya', 'Ya', 'Ya', 'Lengkap & Sesuai', 'Resep telah diverifikasi oleh Apoteker dan siap diserahkan'),
('RSP0000002', '2026-09-08', '08:30:00', 'PET003', 'Ya', 'Ya', 'Ya', 'Ya', 'Ya', 'Ya', 'Ya', 'Lengkap & Sesuai', 'Resep telah diverifikasi oleh Apoteker dan siap diserahkan'),
('RSP0000003', '2026-09-08', '09:00:00', 'PET003', 'Ya', 'Ya', 'Ya', 'Ya', 'Ya', 'Ya', 'Ya', 'Lengkap & Sesuai', 'Resep telah diverifikasi oleh Apoteker dan siap diserahkan'),
('RSP0000004', '2026-09-08', '09:30:00', 'PET003', 'Ya', 'Ya', 'Ya', 'Ya', 'Ya', 'Ya', 'Ya', 'Lengkap & Sesuai', 'Resep telah diverifikasi oleh Apoteker dan siap diserahkan'),
('RSP0000005', '2026-09-08', '10:00:00', 'PET003', 'Ya', 'Ya', 'Ya', 'Ya', 'Ya', 'Ya', 'Ya', 'Lengkap & Sesuai', 'Resep telah diverifikasi oleh Apoteker dan siap diserahkan'),
('RSP0000006', '2026-09-08', '10:30:00', 'PET003', 'Ya', 'Ya', 'Ya', 'Ya', 'Ya', 'Ya', 'Ya', 'Lengkap & Sesuai', 'Resep telah diverifikasi oleh Apoteker dan siap diserahkan'),
('RSP0000007', '2026-09-08', '11:00:00', 'PET003', 'Ya', 'Ya', 'Ya', 'Ya', 'Ya', 'Ya', 'Ya', 'Lengkap & Sesuai', 'Resep telah diverifikasi oleh Apoteker dan siap diserahkan'),
('RSP0000008', '2026-09-08', '11:30:00', 'PET003', 'Ya', 'Ya', 'Ya', 'Ya', 'Ya', 'Ya', 'Ya', 'Lengkap & Sesuai', 'Resep telah diverifikasi oleh Apoteker dan siap diserahkan'),
('RSP0000009', '2026-09-08', '12:00:00', 'PET003', 'Ya', 'Ya', 'Ya', 'Ya', 'Ya', 'Ya', 'Ya', 'Lengkap & Sesuai', 'Resep telah diverifikasi oleh Apoteker dan siap diserahkan'),
('RSP0000010', '2026-09-08', '12:30:00', 'PET003', 'Ya', 'Ya', 'Ya', 'Ya', 'Ya', 'Ya', 'Ya', 'Lengkap & Sesuai', 'Resep telah diverifikasi oleh Apoteker dan siap diserahkan');

-- --------------------------------------------------------

--
-- Struktur dari tabel `template_laboratorium`
--

DROP TABLE IF EXISTS `template_laboratorium`;
CREATE TABLE IF NOT EXISTS `template_laboratorium` (
  `kd_jenis_prw` varchar(15) NOT NULL,
  `id_template` int NOT NULL AUTO_INCREMENT,
  `Pemeriksaan` varchar(200) NOT NULL,
  `satuan` varchar(20) NOT NULL,
  `nilai_rujukan_ld` varchar(30) NOT NULL,
  `nilai_rujukan_la` varchar(30) NOT NULL,
  `nilai_rujukan_pd` varchar(30) NOT NULL,
  `nilai_rujukan_pa` varchar(30) NOT NULL,
  `bagian_rs` double NOT NULL,
  `bhp` double NOT NULL,
  `bagian_perujuk` double NOT NULL,
  `bagian_dokter` double NOT NULL,
  `bagian_laborat` double NOT NULL,
  `kso` double DEFAULT NULL,
  `menejemen` double DEFAULT NULL,
  `biaya_item` double NOT NULL,
  `urut` int DEFAULT NULL,
  PRIMARY KEY (`id_template`),
  KEY `kd_jenis_prw` (`kd_jenis_prw`),
  KEY `Pemeriksaan` (`Pemeriksaan`),
  KEY `satuan` (`satuan`),
  KEY `nilai_rujukan_ld` (`nilai_rujukan_ld`),
  KEY `nilai_rujukan_la` (`nilai_rujukan_la`),
  KEY `nilai_rujukan_pd` (`nilai_rujukan_pd`),
  KEY `nilai_rujukan_pa` (`nilai_rujukan_pa`),
  KEY `bagian_rs` (`bagian_rs`),
  KEY `bhp` (`bhp`),
  KEY `bagian_perujuk` (`bagian_perujuk`),
  KEY `bagian_dokter` (`bagian_dokter`),
  KEY `bagian_laborat` (`bagian_laborat`),
  KEY `kso` (`kso`),
  KEY `menejemen` (`menejemen`),
  KEY `biaya_item` (`biaya_item`),
  KEY `urut` (`urut`)
) ENGINE=InnoDB AUTO_INCREMENT=54 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `template_laboratorium`
--

INSERT INTO `template_laboratorium` (`kd_jenis_prw`, `id_template`, `Pemeriksaan`, `satuan`, `nilai_rujukan_ld`, `nilai_rujukan_la`, `nilai_rujukan_pd`, `nilai_rujukan_pa`, `bagian_rs`, `bhp`, `bagian_perujuk`, `bagian_dokter`, `bagian_laborat`, `kso`, `menejemen`, `biaya_item`, `urut`) VALUES
('LAB001', 1, 'Hemoglobin (Hb)', 'g/dL', '13.0 - 17.5', '11.5 - 15.5', '12.0 - 16.0', '11.5 - 15.5', 0, 0, 0, 0, 0, 0, 0, 0, 1),
('LAB001', 2, 'Leukosit (WBC)', '10^3/uL', '4.5 - 11.0', '5.0 - 13.0', '4.5 - 11.0', '5.0 - 13.0', 0, 0, 0, 0, 0, 0, 0, 0, 2),
('LAB001', 3, 'Eritrosit (RBC)', '10^6/uL', '4.50 - 5.90', '4.00 - 5.20', '4.00 - 5.20', '4.00 - 5.20', 0, 0, 0, 0, 0, 0, 0, 0, 3),
('LAB001', 4, 'Hematokrit (Ht)', '%', '40.0 - 52.0', '35.0 - 45.0', '36.0 - 46.0', '35.0 - 45.0', 0, 0, 0, 0, 0, 0, 0, 0, 4),
('LAB001', 5, 'Trombosit (PLT)', '10^3/uL', '150 - 450', '150 - 450', '150 - 450', '150 - 450', 0, 0, 0, 0, 0, 0, 0, 0, 5),
('LAB001', 6, 'Laju Endap Darah (LED)', 'mm/jam', '0 - 15', '0 - 10', '0 - 20', '0 - 10', 0, 0, 0, 0, 0, 0, 0, 0, 6),
('LAB001', 7, 'Basofil', '%', '0 - 1', '0 - 1', '0 - 1', '0 - 1', 0, 0, 0, 0, 0, 0, 0, 0, 7),
('LAB001', 8, 'Eosinofil', '%', '1 - 3', '1 - 3', '1 - 3', '1 - 3', 0, 0, 0, 0, 0, 0, 0, 0, 8),
('LAB001', 9, 'Batang (Band)', '%', '2 - 6', '2 - 6', '2 - 6', '2 - 6', 0, 0, 0, 0, 0, 0, 0, 0, 9),
('LAB001', 10, 'Segmen (Neutrofil)', '%', '50 - 70', '50 - 70', '50 - 70', '50 - 70', 0, 0, 0, 0, 0, 0, 0, 0, 10),
('LAB001', 11, 'Limfosit', '%', '20 - 40', '20 - 40', '20 - 40', '20 - 40', 0, 0, 0, 0, 0, 0, 0, 0, 11),
('LAB001', 12, 'Monosit', '%', '2 - 8', '2 - 8', '2 - 8', '2 - 8', 0, 0, 0, 0, 0, 0, 0, 0, 12),
('LAB002', 13, 'Glukosa Darah Sewaktu', 'mg/dL', '< 140', '< 140', '< 140', '< 140', 0, 0, 0, 0, 0, 0, 0, 0, 1),
('LAB003', 14, 'Glukosa Darah Puasa (GDP)', 'mg/dL', '70 - 100', '70 - 100', '70 - 100', '70 - 100', 0, 0, 0, 0, 0, 0, 0, 0, 1),
('LAB003', 15, 'Glukosa 2 Jam PP', 'mg/dL', '< 140', '< 140', '< 140', '< 140', 0, 0, 0, 0, 0, 0, 0, 0, 2),
('LAB004', 16, 'Kolesterol Total', 'mg/dL', '< 200', '< 200', '< 200', '< 200', 0, 0, 0, 0, 0, 0, 0, 0, 1),
('LAB004', 17, 'Trigliserida', 'mg/dL', '< 150', '< 150', '< 150', '< 150', 0, 0, 0, 0, 0, 0, 0, 0, 2),
('LAB004', 18, 'HDL Kolesterol', 'mg/dL', '> 40', '> 40', '> 50', '> 50', 0, 0, 0, 0, 0, 0, 0, 0, 3),
('LAB004', 19, 'LDL Kolesterol', 'mg/dL', '< 100', '< 100', '< 100', '< 100', 0, 0, 0, 0, 0, 0, 0, 0, 4),
('LAB005', 20, 'Ureum', 'mg/dL', '15.0 - 45.0', '15.0 - 40.0', '15.0 - 45.0', '15.0 - 40.0', 0, 0, 0, 0, 0, 0, 0, 0, 1),
('LAB005', 21, 'Kreatinin', 'mg/dL', '0.70 - 1.30', '0.40 - 0.90', '0.60 - 1.10', '0.40 - 0.90', 0, 0, 0, 0, 0, 0, 0, 0, 2),
('LAB005', 22, 'Asam Urat', 'mg/dL', '3.5 - 7.2', '2.0 - 5.5', '2.6 - 6.0', '2.0 - 5.5', 0, 0, 0, 0, 0, 0, 0, 0, 3),
('LAB006', 23, 'SGOT / AST', 'U/L', '0 - 37', '0 - 37', '0 - 31', '0 - 31', 0, 0, 0, 0, 0, 0, 0, 0, 1),
('LAB006', 24, 'SGPT / ALT', 'U/L', '0 - 42', '0 - 42', '0 - 32', '0 - 32', 0, 0, 0, 0, 0, 0, 0, 0, 2),
('LAB007', 25, 'Warna', '', 'Kuning Muda', 'Kuning Muda', 'Kuning Muda', 'Kuning Muda', 0, 0, 0, 0, 0, 0, 0, 0, 1),
('LAB007', 26, 'Kejernihan', '', 'Jernih', 'Jernih', 'Jernih', 'Jernih', 0, 0, 0, 0, 0, 0, 0, 0, 2),
('LAB007', 27, 'pH', '', '4.8 - 7.5', '4.8 - 7.5', '4.8 - 7.5', '4.8 - 7.5', 0, 0, 0, 0, 0, 0, 0, 0, 3),
('LAB007', 28, 'Berat Jenis (BJ)', '', '1.005 - 1.030', '1.005 - 1.030', '1.005 - 1.030', '1.005 - 1.030', 0, 0, 0, 0, 0, 0, 0, 0, 4),
('LAB007', 29, 'Protein / Albumin', '', 'Negatif (-)', 'Negatif (-)', 'Negatif (-)', 'Negatif (-)', 0, 0, 0, 0, 0, 0, 0, 0, 5),
('LAB007', 30, 'Glukosa / Reduksi', '', 'Negatif (-)', 'Negatif (-)', 'Negatif (-)', 'Negatif (-)', 0, 0, 0, 0, 0, 0, 0, 0, 6),
('LAB007', 31, 'Bilirubin', '', 'Negatif (-)', 'Negatif (-)', 'Negatif (-)', 'Negatif (-)', 0, 0, 0, 0, 0, 0, 0, 0, 7),
('LAB007', 32, 'Urobilinogen', 'mg/dL', '0.1 - 1.0', '0.1 - 1.0', '0.1 - 1.0', '0.1 - 1.0', 0, 0, 0, 0, 0, 0, 0, 0, 8),
('LAB007', 33, 'Nitrit', '', 'Negatif (-)', 'Negatif (-)', 'Negatif (-)', 'Negatif (-)', 0, 0, 0, 0, 0, 0, 0, 0, 9),
('LAB007', 34, 'Keton', '', 'Negatif (-)', 'Negatif (-)', 'Negatif (-)', 'Negatif (-)', 0, 0, 0, 0, 0, 0, 0, 0, 10),
('LAB007', 35, 'Sedimen: Leukosit', '/LPB', '0 - 4', '0 - 4', '0 - 5', '0 - 5', 0, 0, 0, 0, 0, 0, 0, 0, 11),
('LAB007', 36, 'Sedimen: Eritrosit', '/LPB', '0 - 2', '0 - 2', '0 - 2', '0 - 2', 0, 0, 0, 0, 0, 0, 0, 0, 12),
('LAB007', 37, 'Sedimen: Epitel', '/LPK', 'Positif 1 (+)', 'Positif 1 (+)', 'Positif 1 (+)', 'Positif 1 (+)', 0, 0, 0, 0, 0, 0, 0, 0, 13),
('LAB007', 38, 'Sedimen: Silinder', '/LPK', 'Negatif (-)', 'Negatif (-)', 'Negatif (-)', 'Negatif (-)', 0, 0, 0, 0, 0, 0, 0, 0, 14),
('LAB007', 39, 'Sedimen: Kristal', '/LPK', 'Negatif (-)', 'Negatif (-)', 'Negatif (-)', 'Negatif (-)', 0, 0, 0, 0, 0, 0, 0, 0, 15),
('LAB007', 40, 'Sedimen: Bakteri', '/LPK', 'Negatif (-)', 'Negatif (-)', 'Negatif (-)', 'Negatif (-)', 0, 0, 0, 0, 0, 0, 0, 0, 16),
('LAB008', 41, 'S. Typhi O', '', '< 1/80', '< 1/80', '< 1/80', '< 1/80', 0, 0, 0, 0, 0, 0, 0, 0, 1),
('LAB008', 42, 'S. Typhi H', '', '< 1/80', '< 1/80', '< 1/80', '< 1/80', 0, 0, 0, 0, 0, 0, 0, 0, 2),
('LAB008', 43, 'S. Paratyphi A-O', '', '< 1/80', '< 1/80', '< 1/80', '< 1/80', 0, 0, 0, 0, 0, 0, 0, 0, 3),
('LAB008', 44, 'S. Paratyphi A-H', '', '< 1/80', '< 1/80', '< 1/80', '< 1/80', 0, 0, 0, 0, 0, 0, 0, 0, 4),
('LAB008', 45, 'S. Paratyphi B-O', '', '< 1/80', '< 1/80', '< 1/80', '< 1/80', 0, 0, 0, 0, 0, 0, 0, 0, 5),
('LAB008', 46, 'S. Paratyphi B-H', '', '< 1/80', '< 1/80', '< 1/80', '< 1/80', 0, 0, 0, 0, 0, 0, 0, 0, 6),
('LAB009', 47, 'Golongan Darah ABO', '', '-', '-', '-', '-', 0, 0, 0, 0, 0, 0, 0, 0, 1),
('LAB009', 48, 'Faktor Rhesus (Rh)', '', 'Positif (+)', 'Positif (+)', 'Positif (+)', 'Positif (+)', 0, 0, 0, 0, 0, 0, 0, 0, 2),
('LAB010', 49, 'HBsAg Kualitatif', '', 'Non Reaktif (-)', 'Non Reaktif (-)', 'Non Reaktif (-)', 'Non Reaktif (-)', 0, 0, 0, 0, 0, 0, 0, 0, 1),
('LAB011', 50, 'HCG Urin (Tes Kehamilan)', '', 'Negatif (-)', 'Negatif (-)', 'Negatif (-)', 'Negatif (-)', 0, 0, 0, 0, 0, 0, 0, 0, 1),
('LAB012', 51, 'Natrium (Na)', 'mmol/L', '135 - 147', '135 - 145', '135 - 147', '135 - 145', 0, 0, 0, 0, 0, 0, 0, 0, 1),
('LAB012', 52, 'Kalium (K)', 'mmol/L', '3.5 - 5.1', '3.6 - 5.2', '3.5 - 5.1', '3.6 - 5.2', 0, 0, 0, 0, 0, 0, 0, 0, 2),
('LAB012', 53, 'Klorida (Cl)', 'mmol/L', '96 - 106', '96 - 106', '96 - 106', '96 - 106', 0, 0, 0, 0, 0, 0, 0, 0, 3);

-- --------------------------------------------------------

--
-- Struktur dari tabel `temporary_presensi`
--

DROP TABLE IF EXISTS `temporary_presensi`;
CREATE TABLE IF NOT EXISTS `temporary_presensi` (
  `id` int NOT NULL,
  `shift` enum('Pagi','Pagi2','Pagi3','Pagi4','Pagi5','Pagi6','Pagi7','Pagi8','Pagi9','Pagi10','Siang','Siang2','Siang3','Siang4','Siang5','Siang6','Siang7','Siang8','Siang9','Siang10','Malam','Malam2','Malam3','Malam4','Malam5','Malam6','Malam7','Malam8','Malam9','Malam10','Midle Pagi1','Midle Pagi2','Midle Pagi3','Midle Pagi4','Midle Pagi5','Midle Pagi6','Midle Pagi7','Midle Pagi8','Midle Pagi9','Midle Pagi10','Midle Siang1','Midle Siang2','Midle Siang3','Midle Siang4','Midle Siang5','Midle Siang6','Midle Siang7','Midle Siang8','Midle Siang9','Midle Siang10','Midle Malam1','Midle Malam2','Midle Malam3','Midle Malam4','Midle Malam5','Midle Malam6','Midle Malam7','Midle Malam8','Midle Malam9','Midle Malam10') NOT NULL,
  `jam_datang` datetime DEFAULT NULL,
  `jam_pulang` datetime DEFAULT NULL,
  `status` enum('Tepat Waktu','Terlambat Toleransi','Terlambat I','Terlambat II','Tepat Waktu & PSW','Terlambat Toleransi & PSW','Terlambat I & PSW','Terlambat II & PSW') NOT NULL,
  `keterlambatan` varchar(20) NOT NULL,
  `durasi` varchar(20) DEFAULT NULL,
  `photo` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `utd_donor`
--

DROP TABLE IF EXISTS `utd_donor`;
CREATE TABLE IF NOT EXISTS `utd_donor` (
  `no_donor` varchar(15) NOT NULL,
  `no_pendonor` varchar(15) NOT NULL,
  `tanggal` date DEFAULT NULL,
  `dinas` enum('Pagi','Siang','Sore','Malam') DEFAULT NULL,
  `tensi` varchar(7) DEFAULT NULL,
  `no_bag` int DEFAULT NULL,
  `jenis_bag` enum('SB','DB','TB','QB') DEFAULT NULL,
  `jenis_donor` enum('DB','DP','DS') DEFAULT NULL,
  `tempat_aftap` enum('Dalam Gedung','Luar Gedung') DEFAULT NULL,
  `petugas_aftap` varchar(20) DEFAULT NULL,
  `hbsag` enum('Negatif','Positif') DEFAULT NULL,
  `hcv` enum('Negatif','Positif') DEFAULT NULL,
  `hiv` enum('Negatif','Positif') DEFAULT NULL,
  `spilis` enum('Negatif','Positif') DEFAULT NULL,
  `malaria` enum('Negatif','Positif') DEFAULT NULL,
  `petugas_u_saring` varchar(20) DEFAULT NULL,
  `status` enum('Aman','Cekal') DEFAULT NULL,
  PRIMARY KEY (`no_donor`),
  KEY `petugas_aftap` (`petugas_aftap`),
  KEY `petugas_u_saring` (`petugas_u_saring`),
  KEY `no_pendonor` (`no_pendonor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `utd_komponen_darah`
--

DROP TABLE IF EXISTS `utd_komponen_darah`;
CREATE TABLE IF NOT EXISTS `utd_komponen_darah` (
  `kode` varchar(5) NOT NULL,
  `nama` varchar(70) DEFAULT NULL,
  `lama` smallint DEFAULT NULL,
  `jasa_sarana` double DEFAULT NULL,
  `paket_bhp` double DEFAULT NULL,
  `kso` double DEFAULT NULL,
  `manajemen` double DEFAULT NULL,
  `total` double DEFAULT NULL,
  `pembatalan` double DEFAULT NULL,
  PRIMARY KEY (`kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `utd_pendonor`
--

DROP TABLE IF EXISTS `utd_pendonor`;
CREATE TABLE IF NOT EXISTS `utd_pendonor` (
  `no_pendonor` varchar(15) NOT NULL,
  `nama` varchar(40) NOT NULL,
  `no_ktp` varchar(20) NOT NULL,
  `jk` enum('L','P') NOT NULL,
  `tmp_lahir` varchar(15) NOT NULL,
  `tgl_lahir` date NOT NULL,
  `alamat` varchar(100) NOT NULL,
  `kd_kel` int NOT NULL,
  `kd_kec` int NOT NULL,
  `kd_kab` int NOT NULL,
  `kd_prop` int NOT NULL,
  `golongan_darah` enum('A','AB','B','O') NOT NULL,
  `resus` enum('(-)','(+)') NOT NULL,
  `no_telp` varchar(40) NOT NULL,
  PRIMARY KEY (`no_pendonor`),
  KEY `kd_kec` (`kd_kec`),
  KEY `kd_kab` (`kd_kab`),
  KEY `kd_prop` (`kd_prop`),
  KEY `kd_kel` (`kd_kel`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `utd_stok_darah`
--

DROP TABLE IF EXISTS `utd_stok_darah`;
CREATE TABLE IF NOT EXISTS `utd_stok_darah` (
  `no_kantong` varchar(20) NOT NULL DEFAULT '',
  `kode_komponen` varchar(5) DEFAULT NULL,
  `golongan_darah` enum('A','AB','B','O') DEFAULT NULL,
  `resus` enum('(-)','(+)') DEFAULT NULL,
  `tanggal_aftap` date DEFAULT NULL,
  `tanggal_kadaluarsa` date DEFAULT NULL,
  `asal_darah` enum('Hibah','Beli','Produksi Sendiri') DEFAULT NULL,
  `status` enum('Ada','Diambil','Dimusnahkan') DEFAULT NULL,
  PRIMARY KEY (`no_kantong`),
  KEY `kode_komponen` (`kode_komponen`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `aturan_pakai`
--
ALTER TABLE `aturan_pakai`
  ADD CONSTRAINT `aturan_pakai_ibfk_1` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `aturan_pakai_ibfk_2` FOREIGN KEY (`kode_brng`) REFERENCES `databarang` (`kode_brng`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `barcode`
--
ALTER TABLE `barcode`
  ADD CONSTRAINT `barcode_ibfk_1` FOREIGN KEY (`id`) REFERENCES `pegawai` (`id`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `beri_obat_operasi`
--
ALTER TABLE `beri_obat_operasi`
  ADD CONSTRAINT `beri_obat_operasi_ibfk_2` FOREIGN KEY (`kd_obat`) REFERENCES `obatbhp_ok` (`kd_obat`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `beri_obat_operasi_ibfk_3` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `berkas_digital_perawatan`
--
ALTER TABLE `berkas_digital_perawatan`
  ADD CONSTRAINT `berkas_digital_perawatan_ibfk_1` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `berkas_digital_perawatan_ibfk_2` FOREIGN KEY (`kode`) REFERENCES `master_berkas_digital` (`kode`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `booking_operasi`
--
ALTER TABLE `booking_operasi`
  ADD CONSTRAINT `booking_operasi_ibfk_1` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON UPDATE CASCADE,
  ADD CONSTRAINT `booking_operasi_ibfk_2` FOREIGN KEY (`kode_paket`) REFERENCES `paket_operasi` (`kode_paket`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `booking_operasi_ibfk_3` FOREIGN KEY (`kd_dokter`) REFERENCES `dokter` (`kd_dokter`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `booking_operasi_ibfk_4` FOREIGN KEY (`kd_ruang_ok`) REFERENCES `ruang_ok` (`kd_ruang_ok`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `booking_periksa`
--
ALTER TABLE `booking_periksa`
  ADD CONSTRAINT `booking_periksa_ibfk_1` FOREIGN KEY (`kd_poli`) REFERENCES `poliklinik` (`kd_poli`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `booking_periksa_balasan`
--
ALTER TABLE `booking_periksa_balasan`
  ADD CONSTRAINT `booking_periksa_balasan_ibfk_1` FOREIGN KEY (`no_booking`) REFERENCES `booking_periksa` (`no_booking`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `booking_periksa_diterima`
--
ALTER TABLE `booking_periksa_diterima`
  ADD CONSTRAINT `booking_periksa_diterima_ibfk_1` FOREIGN KEY (`no_booking`) REFERENCES `booking_periksa` (`no_booking`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `booking_periksa_diterima_ibfk_2` FOREIGN KEY (`no_rkm_medis`) REFERENCES `pasien` (`no_rkm_medis`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `booking_registrasi`
--
ALTER TABLE `booking_registrasi`
  ADD CONSTRAINT `booking_registrasi_ibfk_1` FOREIGN KEY (`kd_dokter`) REFERENCES `dokter` (`kd_dokter`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `booking_registrasi_ibfk_2` FOREIGN KEY (`kd_poli`) REFERENCES `poliklinik` (`kd_poli`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `booking_registrasi_ibfk_3` FOREIGN KEY (`kd_pj`) REFERENCES `penjab` (`kd_pj`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `booking_registrasi_ibfk_4` FOREIGN KEY (`no_rkm_medis`) REFERENCES `pasien` (`no_rkm_medis`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `bpjs_prb`
--
ALTER TABLE `bpjs_prb`
  ADD CONSTRAINT `bpjs_prb_ibfk_1` FOREIGN KEY (`no_sep`) REFERENCES `bridging_sep` (`no_sep`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `bridging_rujukan_bpjs`
--
ALTER TABLE `bridging_rujukan_bpjs`
  ADD CONSTRAINT `bridging_rujukan_bpjs_ibfk_1` FOREIGN KEY (`no_sep`) REFERENCES `bridging_sep` (`no_sep`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `bridging_sep`
--
ALTER TABLE `bridging_sep`
  ADD CONSTRAINT `bridging_sep_ibfk_1` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `bridging_sep_internal`
--
ALTER TABLE `bridging_sep_internal`
  ADD CONSTRAINT `bridging_sep_internal_ibfk_1` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `bridging_sep_internal_ibfk_2` FOREIGN KEY (`no_sep`) REFERENCES `bridging_sep` (`no_sep`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `bridging_srb_bpjs`
--
ALTER TABLE `bridging_srb_bpjs`
  ADD CONSTRAINT `bridging_srb_bpjs_ibfk_1` FOREIGN KEY (`no_sep`) REFERENCES `bridging_sep` (`no_sep`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `bridging_surat_kontrol_bpjs`
--
ALTER TABLE `bridging_surat_kontrol_bpjs`
  ADD CONSTRAINT `bridging_surat_kontrol_bpjs_ibfk_1` FOREIGN KEY (`no_sep`) REFERENCES `bridging_sep` (`no_sep`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `bridging_surat_pri_bpjs`
--
ALTER TABLE `bridging_surat_pri_bpjs`
  ADD CONSTRAINT `bridging_surat_pri_bpjs_ibfk_1` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `catatan_adime_gizi`
--
ALTER TABLE `catatan_adime_gizi`
  ADD CONSTRAINT `catatan_adime_gizi_ibfk_1` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `catatan_adime_gizi_ibfk_2` FOREIGN KEY (`nip`) REFERENCES `petugas` (`nip`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `catatan_perawatan`
--
ALTER TABLE `catatan_perawatan`
  ADD CONSTRAINT `catatan_perawatan_ibfk_1` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `catatan_perawatan_ibfk_2` FOREIGN KEY (`kd_dokter`) REFERENCES `dokter` (`kd_dokter`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `databarang`
--
ALTER TABLE `databarang`
  ADD CONSTRAINT `databarang_ibfk_2` FOREIGN KEY (`kdjns`) REFERENCES `jenis` (`kdjns`) ON UPDATE CASCADE,
  ADD CONSTRAINT `databarang_ibfk_3` FOREIGN KEY (`kode_sat`) REFERENCES `kodesatuan` (`kode_sat`) ON UPDATE CASCADE,
  ADD CONSTRAINT `databarang_ibfk_4` FOREIGN KEY (`kode_industri`) REFERENCES `industrifarmasi` (`kode_industri`) ON UPDATE CASCADE,
  ADD CONSTRAINT `databarang_ibfk_5` FOREIGN KEY (`kode_kategori`) REFERENCES `kategori_barang` (`kode`) ON UPDATE CASCADE,
  ADD CONSTRAINT `databarang_ibfk_6` FOREIGN KEY (`kode_golongan`) REFERENCES `golongan_barang` (`kode`) ON UPDATE CASCADE,
  ADD CONSTRAINT `databarang_ibfk_7` FOREIGN KEY (`kode_satbesar`) REFERENCES `kodesatuan` (`kode_sat`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `data_tb`
--
ALTER TABLE `data_tb`
  ADD CONSTRAINT `data_tb_ibfk_1` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `data_tb_ibfk_2` FOREIGN KEY (`kode_icd_x`) REFERENCES `penyakit` (`kd_penyakit`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `detail_obat_racikan`
--
ALTER TABLE `detail_obat_racikan`
  ADD CONSTRAINT `detail_obat_racikan_ibfk_1` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON UPDATE CASCADE,
  ADD CONSTRAINT `detail_obat_racikan_ibfk_2` FOREIGN KEY (`kode_brng`) REFERENCES `databarang` (`kode_brng`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `detail_pemberian_obat`
--
ALTER TABLE `detail_pemberian_obat`
  ADD CONSTRAINT `detail_pemberian_obat_ibfk_3` FOREIGN KEY (`kode_brng`) REFERENCES `databarang` (`kode_brng`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `detail_pemberian_obat_ibfk_4` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `detail_pemberian_obat_ibfk_5` FOREIGN KEY (`kd_bangsal`) REFERENCES `bangsal` (`kd_bangsal`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `detail_periksa_lab`
--
ALTER TABLE `detail_periksa_lab`
  ADD CONSTRAINT `detail_periksa_lab_ibfk_10` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON UPDATE CASCADE,
  ADD CONSTRAINT `detail_periksa_lab_ibfk_11` FOREIGN KEY (`kd_jenis_prw`) REFERENCES `jns_perawatan_lab` (`kd_jenis_prw`) ON UPDATE CASCADE,
  ADD CONSTRAINT `detail_periksa_lab_ibfk_12` FOREIGN KEY (`id_template`) REFERENCES `template_laboratorium` (`id_template`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `diagnosa_pasien`
--
ALTER TABLE `diagnosa_pasien`
  ADD CONSTRAINT `diagnosa_pasien_ibfk_1` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON UPDATE CASCADE,
  ADD CONSTRAINT `diagnosa_pasien_ibfk_2` FOREIGN KEY (`kd_penyakit`) REFERENCES `penyakit` (`kd_penyakit`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `dokter`
--
ALTER TABLE `dokter`
  ADD CONSTRAINT `dokter_ibfk_2` FOREIGN KEY (`kd_sps`) REFERENCES `spesialis` (`kd_sps`) ON UPDATE CASCADE,
  ADD CONSTRAINT `dokter_ibfk_3` FOREIGN KEY (`kd_dokter`) REFERENCES `pegawai` (`nik`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `dpjp_ranap`
--
ALTER TABLE `dpjp_ranap`
  ADD CONSTRAINT `dpjp_ranap_ibfk_1` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON UPDATE CASCADE,
  ADD CONSTRAINT `dpjp_ranap_ibfk_2` FOREIGN KEY (`kd_dokter`) REFERENCES `dokter` (`kd_dokter`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `gambar_radiologi`
--
ALTER TABLE `gambar_radiologi`
  ADD CONSTRAINT `gambar_radiologi_ibfk_1` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `gudangbarang`
--
ALTER TABLE `gudangbarang`
  ADD CONSTRAINT `gudangbarang_ibfk_1` FOREIGN KEY (`kd_bangsal`) REFERENCES `bangsal` (`kd_bangsal`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `gudangbarang_ibfk_2` FOREIGN KEY (`kode_brng`) REFERENCES `databarang` (`kode_brng`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `hasil_radiologi`
--
ALTER TABLE `hasil_radiologi`
  ADD CONSTRAINT `hasil_radiologi_ibfk_1` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `inventaris`
--
ALTER TABLE `inventaris`
  ADD CONSTRAINT `inventaris_ibfk_1` FOREIGN KEY (`kode_barang`) REFERENCES `inventaris_barang` (`kode_barang`) ON UPDATE CASCADE,
  ADD CONSTRAINT `inventaris_ibfk_2` FOREIGN KEY (`id_ruang`) REFERENCES `inventaris_ruang` (`id_ruang`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `inventaris_barang`
--
ALTER TABLE `inventaris_barang`
  ADD CONSTRAINT `inventaris_barang_ibfk_5` FOREIGN KEY (`kode_produsen`) REFERENCES `inventaris_produsen` (`kode_produsen`) ON UPDATE CASCADE,
  ADD CONSTRAINT `inventaris_barang_ibfk_6` FOREIGN KEY (`id_merk`) REFERENCES `inventaris_merk` (`id_merk`) ON UPDATE CASCADE,
  ADD CONSTRAINT `inventaris_barang_ibfk_7` FOREIGN KEY (`id_kategori`) REFERENCES `inventaris_kategori` (`id_kategori`) ON UPDATE CASCADE,
  ADD CONSTRAINT `inventaris_barang_ibfk_8` FOREIGN KEY (`id_jenis`) REFERENCES `inventaris_jenis` (`id_jenis`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `inventaris_peminjaman`
--
ALTER TABLE `inventaris_peminjaman`
  ADD CONSTRAINT `inventaris_peminjaman_ibfk_1` FOREIGN KEY (`no_inventaris`) REFERENCES `inventaris` (`no_inventaris`) ON UPDATE CASCADE,
  ADD CONSTRAINT `inventaris_peminjaman_ibfk_2` FOREIGN KEY (`nip`) REFERENCES `petugas` (`nip`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `jadwal`
--
ALTER TABLE `jadwal`
  ADD CONSTRAINT `jadwal_ibfk_1` FOREIGN KEY (`kd_dokter`) REFERENCES `dokter` (`kd_dokter`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `jadwal_ibfk_2` FOREIGN KEY (`kd_poli`) REFERENCES `poliklinik` (`kd_poli`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `jadwal_pegawai`
--
ALTER TABLE `jadwal_pegawai`
  ADD CONSTRAINT `jadwal_pegawai_ibfk_1` FOREIGN KEY (`id`) REFERENCES `pegawai` (`id`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `jadwal_tambahan`
--
ALTER TABLE `jadwal_tambahan`
  ADD CONSTRAINT `jadwal_tambahan_ibfk_1` FOREIGN KEY (`id`) REFERENCES `pegawai` (`id`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `jam_jaga`
--
ALTER TABLE `jam_jaga`
  ADD CONSTRAINT `jam_jaga_ibfk_1` FOREIGN KEY (`dep_id`) REFERENCES `departemen` (`dep_id`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `jns_perawatan`
--
ALTER TABLE `jns_perawatan`
  ADD CONSTRAINT `jns_perawatan_ibfk_1` FOREIGN KEY (`kd_kategori`) REFERENCES `kategori_perawatan` (`kd_kategori`) ON UPDATE CASCADE,
  ADD CONSTRAINT `jns_perawatan_ibfk_2` FOREIGN KEY (`kd_pj`) REFERENCES `penjab` (`kd_pj`) ON UPDATE CASCADE,
  ADD CONSTRAINT `jns_perawatan_ibfk_3` FOREIGN KEY (`kd_poli`) REFERENCES `poliklinik` (`kd_poli`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `jns_perawatan_inap`
--
ALTER TABLE `jns_perawatan_inap`
  ADD CONSTRAINT `jns_perawatan_inap_ibfk_7` FOREIGN KEY (`kd_kategori`) REFERENCES `kategori_perawatan` (`kd_kategori`) ON UPDATE CASCADE,
  ADD CONSTRAINT `jns_perawatan_inap_ibfk_8` FOREIGN KEY (`kd_pj`) REFERENCES `penjab` (`kd_pj`) ON UPDATE CASCADE,
  ADD CONSTRAINT `jns_perawatan_inap_ibfk_9` FOREIGN KEY (`kd_bangsal`) REFERENCES `bangsal` (`kd_bangsal`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `jns_perawatan_lab`
--
ALTER TABLE `jns_perawatan_lab`
  ADD CONSTRAINT `jns_perawatan_lab_ibfk_1` FOREIGN KEY (`kd_pj`) REFERENCES `penjab` (`kd_pj`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `jns_perawatan_radiologi`
--
ALTER TABLE `jns_perawatan_radiologi`
  ADD CONSTRAINT `jns_perawatan_radiologi_ibfk_1` FOREIGN KEY (`kd_pj`) REFERENCES `penjab` (`kd_pj`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `kamar`
--
ALTER TABLE `kamar`
  ADD CONSTRAINT `kamar_ibfk_1` FOREIGN KEY (`kd_bangsal`) REFERENCES `bangsal` (`kd_bangsal`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `kamar_inap`
--
ALTER TABLE `kamar_inap`
  ADD CONSTRAINT `kamar_inap_ibfk_2` FOREIGN KEY (`kd_kamar`) REFERENCES `kamar` (`kd_kamar`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `kamar_inap_ibfk_3` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `laporan_operasi`
--
ALTER TABLE `laporan_operasi`
  ADD CONSTRAINT `laporan_operasi_ibfk_1` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `maping_dokter_dpjpvclaim`
--
ALTER TABLE `maping_dokter_dpjpvclaim`
  ADD CONSTRAINT `maping_dokter_dpjpvclaim_ibfk_1` FOREIGN KEY (`kd_dokter`) REFERENCES `dokter` (`kd_dokter`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `maping_dokter_pcare`
--
ALTER TABLE `maping_dokter_pcare`
  ADD CONSTRAINT `maping_dokter_pcare_ibfk_1` FOREIGN KEY (`kd_dokter`) REFERENCES `dokter` (`kd_dokter`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `maping_poliklinik_pcare`
--
ALTER TABLE `maping_poliklinik_pcare`
  ADD CONSTRAINT `maping_poliklinik_pcare_ibfk_1` FOREIGN KEY (`kd_poli_rs`) REFERENCES `poliklinik` (`kd_poli`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `maping_poli_bpjs`
--
ALTER TABLE `maping_poli_bpjs`
  ADD CONSTRAINT `maping_poli_bpjs_ibfk_1` FOREIGN KEY (`kd_poli_rs`) REFERENCES `poliklinik` (`kd_poli`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `mlite_billing_pembayaran_detail`
--
ALTER TABLE `mlite_billing_pembayaran_detail`
  ADD CONSTRAINT `fk_billing_pembayaran_detail_header` FOREIGN KEY (`pembayaran_id`) REFERENCES `mlite_billing_pembayaran` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `mlite_clinical_pathway_activity`
--
ALTER TABLE `mlite_clinical_pathway_activity`
  ADD CONSTRAINT `fk_cp_activity_day` FOREIGN KEY (`clinical_pathway_day_id`) REFERENCES `mlite_clinical_pathway_day` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `mlite_clinical_pathway_audit`
--
ALTER TABLE `mlite_clinical_pathway_audit`
  ADD CONSTRAINT `fk_cp_audit_cp` FOREIGN KEY (`clinical_pathway_id`) REFERENCES `mlite_clinical_pathway` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cp_audit_patient` FOREIGN KEY (`clinical_pathway_patient_id`) REFERENCES `mlite_clinical_pathway_patient` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `mlite_clinical_pathway_compliance`
--
ALTER TABLE `mlite_clinical_pathway_compliance`
  ADD CONSTRAINT `fk_cp_compliance_patient` FOREIGN KEY (`clinical_pathway_patient_id`) REFERENCES `mlite_clinical_pathway_patient` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `mlite_clinical_pathway_cppt_template`
--
ALTER TABLE `mlite_clinical_pathway_cppt_template`
  ADD CONSTRAINT `fk_cppt_template_penyakit` FOREIGN KEY (`kd_penyakit`) REFERENCES `penyakit` (`kd_penyakit`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `mlite_clinical_pathway_day`
--
ALTER TABLE `mlite_clinical_pathway_day`
  ADD CONSTRAINT `fk_cp_day_cp` FOREIGN KEY (`clinical_pathway_id`) REFERENCES `mlite_clinical_pathway` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `mlite_clinical_pathway_diagnosis`
--
ALTER TABLE `mlite_clinical_pathway_diagnosis`
  ADD CONSTRAINT `fk_cp_diagnosis_cp` FOREIGN KEY (`clinical_pathway_id`) REFERENCES `mlite_clinical_pathway` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cp_diagnosis_penyakit` FOREIGN KEY (`kd_penyakit`) REFERENCES `penyakit` (`kd_penyakit`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `mlite_clinical_pathway_execution`
--
ALTER TABLE `mlite_clinical_pathway_execution`
  ADD CONSTRAINT `fk_cp_execution_activity` FOREIGN KEY (`clinical_pathway_activity_id`) REFERENCES `mlite_clinical_pathway_activity` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cp_execution_patient` FOREIGN KEY (`clinical_pathway_patient_id`) REFERENCES `mlite_clinical_pathway_patient` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `mlite_clinical_pathway_patient`
--
ALTER TABLE `mlite_clinical_pathway_patient`
  ADD CONSTRAINT `fk_cp_patient_cp` FOREIGN KEY (`clinical_pathway_id`) REFERENCES `mlite_clinical_pathway` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cp_patient_penyakit` FOREIGN KEY (`kd_penyakit`) REFERENCES `penyakit` (`kd_penyakit`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cp_patient_reg` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `mlite_clinical_pathway_variance`
--
ALTER TABLE `mlite_clinical_pathway_variance`
  ADD CONSTRAINT `fk_cp_variance_execution` FOREIGN KEY (`clinical_pathway_execution_id`) REFERENCES `mlite_clinical_pathway_execution` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cp_variance_patient` FOREIGN KEY (`clinical_pathway_patient_id`) REFERENCES `mlite_clinical_pathway_patient` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `mlite_detailjurnal`
--
ALTER TABLE `mlite_detailjurnal`
  ADD CONSTRAINT `mlite_detailjurnal_ibfk_1` FOREIGN KEY (`no_jurnal`) REFERENCES `mlite_jurnal` (`no_jurnal`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `mlite_detailjurnal_ibfk_2` FOREIGN KEY (`kd_rek`) REFERENCES `mlite_rekening` (`kd_rek`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `mlite_duitku`
--
ALTER TABLE `mlite_duitku`
  ADD CONSTRAINT `mlite_duitku_ibfk_1` FOREIGN KEY (`no_rkm_medis`) REFERENCES `pasien` (`no_rkm_medis`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `mlite_geolocation_presensi`
--
ALTER TABLE `mlite_geolocation_presensi`
  ADD CONSTRAINT `mlite_geolocation_presensi_ibfk_1` FOREIGN KEY (`id`) REFERENCES `pegawai` (`id`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `mlite_mini_pacs_instance`
--
ALTER TABLE `mlite_mini_pacs_instance`
  ADD CONSTRAINT `fk_mlite_mini_pacs_instance_series` FOREIGN KEY (`series_id`) REFERENCES `mlite_mini_pacs_series` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `mlite_mini_pacs_instance_metadata`
--
ALTER TABLE `mlite_mini_pacs_instance_metadata`
  ADD CONSTRAINT `fk_pacs_instance_metadata` FOREIGN KEY (`instance_id`) REFERENCES `mlite_mini_pacs_instance` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `mlite_mini_pacs_series`
--
ALTER TABLE `mlite_mini_pacs_series`
  ADD CONSTRAINT `fk_mlite_mini_pacs_series_study` FOREIGN KEY (`study_id`) REFERENCES `mlite_mini_pacs_study` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `mlite_mini_pacs_study`
--
ALTER TABLE `mlite_mini_pacs_study`
  ADD CONSTRAINT `fk_mlite_mini_pacs_study_no_rawat` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `mlite_news_tags_relationship`
--
ALTER TABLE `mlite_news_tags_relationship`
  ADD CONSTRAINT `mlite_news_tags_relationship_ibfk_1` FOREIGN KEY (`news_id`) REFERENCES `mlite_news` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  ADD CONSTRAINT `mlite_news_tags_relationship_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `mlite_news_tags` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT;

--
-- Ketidakleluasaan untuk tabel `mlite_pendaftaran_oral_diagnostic`
--
ALTER TABLE `mlite_pendaftaran_oral_diagnostic`
  ADD CONSTRAINT `mlite_pendaftaran_oral_diagnostic_ibfk_3` FOREIGN KEY (`kd_poli`) REFERENCES `poliklinik` (`kd_poli`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `mlite_pendaftaran_oral_diagnostic_ibfk_4` FOREIGN KEY (`kd_dokter`) REFERENCES `dokter` (`kd_dokter`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `mlite_pendaftaran_oral_diagnostic_ibfk_6` FOREIGN KEY (`kd_pj`) REFERENCES `penjab` (`kd_pj`) ON UPDATE CASCADE,
  ADD CONSTRAINT `mlite_pendaftaran_oral_diagnostic_ibfk_7` FOREIGN KEY (`no_rkm_medis`) REFERENCES `pasien` (`no_rkm_medis`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `mlite_pengaduan`
--
ALTER TABLE `mlite_pengaduan`
  ADD CONSTRAINT `mlite_pengaduan_ibfk_1` FOREIGN KEY (`no_rkm_medis`) REFERENCES `pasien` (`no_rkm_medis`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `mlite_pengaduan_detail`
--
ALTER TABLE `mlite_pengaduan_detail`
  ADD CONSTRAINT `mlite_pengaduan_detail_ibfk_1` FOREIGN KEY (`pengaduan_id`) REFERENCES `mlite_pengaduan` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `mlite_rekeningtahun`
--
ALTER TABLE `mlite_rekeningtahun`
  ADD CONSTRAINT `mlite_rekeningtahun_ibfk_1` FOREIGN KEY (`kd_rek`) REFERENCES `mlite_rekening` (`kd_rek`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `mlite_rujukan_internal_poli`
--
ALTER TABLE `mlite_rujukan_internal_poli`
  ADD CONSTRAINT `mlite_rujukan_internal_poli_ibfk_1` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `mlite_rujukan_internal_poli_ibfk_2` FOREIGN KEY (`kd_dokter`) REFERENCES `dokter` (`kd_dokter`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `mlite_rujukan_internal_poli_ibfk_3` FOREIGN KEY (`kd_poli`) REFERENCES `poliklinik` (`kd_poli`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `mlite_satu_sehat_departemen`
--
ALTER TABLE `mlite_satu_sehat_departemen`
  ADD CONSTRAINT `mlite_satu_sehat_departemen_ibfk_1` FOREIGN KEY (`dep_id`) REFERENCES `departemen` (`dep_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `mlite_satu_sehat_lokasi`
--
ALTER TABLE `mlite_satu_sehat_lokasi`
  ADD CONSTRAINT `mlite_satu_sehat_lokasi_ibfk_2` FOREIGN KEY (`id_organisasi_satusehat`) REFERENCES `mlite_satu_sehat_departemen` (`id_organisasi_satusehat`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `mlite_satu_sehat_mapping_lab`
--
ALTER TABLE `mlite_satu_sehat_mapping_lab`
  ADD CONSTRAINT `mlite_satu_sehat_mapping_lab_ibfk_1` FOREIGN KEY (`id_template`) REFERENCES `template_laboratorium` (`id_template`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `mlite_satu_sehat_mapping_obat`
--
ALTER TABLE `mlite_satu_sehat_mapping_obat`
  ADD CONSTRAINT `mlite_satu_sehat_mapping_obat_ibfk_1` FOREIGN KEY (`kode_brng`) REFERENCES `databarang` (`kode_brng`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `mlite_satu_sehat_mapping_praktisi`
--
ALTER TABLE `mlite_satu_sehat_mapping_praktisi`
  ADD CONSTRAINT `mlite_satu_sehat_mapping_praktisi_ibfk_1` FOREIGN KEY (`kd_dokter`) REFERENCES `dokter` (`kd_dokter`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `mlite_satu_sehat_mapping_rad`
--
ALTER TABLE `mlite_satu_sehat_mapping_rad`
  ADD CONSTRAINT `mlite_satu_sehat_mapping_rad_ibfk_1` FOREIGN KEY (`kd_jenis_prw`) REFERENCES `jns_perawatan_radiologi` (`kd_jenis_prw`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `mlite_satu_sehat_response`
--
ALTER TABLE `mlite_satu_sehat_response`
  ADD CONSTRAINT `mlite_satu_sehat_response_ibfk_1` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `mlite_subrekening`
--
ALTER TABLE `mlite_subrekening`
  ADD CONSTRAINT `mlite_subrekening_ibfk_1` FOREIGN KEY (`kd_rek`) REFERENCES `mlite_rekening` (`kd_rek`) ON UPDATE CASCADE,
  ADD CONSTRAINT `mlite_subrekening_ibfk_2` FOREIGN KEY (`kd_rek2`) REFERENCES `mlite_rekening` (`kd_rek`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `mlite_triase_igd`
--
ALTER TABLE `mlite_triase_igd`
  ADD CONSTRAINT `fk_triase_reg_periksa` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `mutasibarang`
--
ALTER TABLE `mutasibarang`
  ADD CONSTRAINT `mutasibarang_ibfk_1` FOREIGN KEY (`kode_brng`) REFERENCES `databarang` (`kode_brng`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `mutasibarang_ibfk_2` FOREIGN KEY (`kd_bangsaldari`) REFERENCES `bangsal` (`kd_bangsal`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `mutasibarang_ibfk_3` FOREIGN KEY (`kd_bangsalke`) REFERENCES `bangsal` (`kd_bangsal`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `mutasi_berkas`
--
ALTER TABLE `mutasi_berkas`
  ADD CONSTRAINT `mutasi_berkas_ibfk_1` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `obatbhp_ok`
--
ALTER TABLE `obatbhp_ok`
  ADD CONSTRAINT `obatbhp_ok_ibfk_1` FOREIGN KEY (`kode_sat`) REFERENCES `kodesatuan` (`kode_sat`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `obat_racikan`
--
ALTER TABLE `obat_racikan`
  ADD CONSTRAINT `obat_racikan_ibfk_1` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON UPDATE CASCADE,
  ADD CONSTRAINT `obat_racikan_ibfk_2` FOREIGN KEY (`kd_racik`) REFERENCES `metode_racik` (`kd_racik`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `operasi`
--
ALTER TABLE `operasi`
  ADD CONSTRAINT `operasi_ibfk_31` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON UPDATE CASCADE,
  ADD CONSTRAINT `operasi_ibfk_32` FOREIGN KEY (`operator1`) REFERENCES `dokter` (`kd_dokter`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `operasi_ibfk_33` FOREIGN KEY (`operator2`) REFERENCES `dokter` (`kd_dokter`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `operasi_ibfk_34` FOREIGN KEY (`operator3`) REFERENCES `dokter` (`kd_dokter`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `operasi_ibfk_35` FOREIGN KEY (`asisten_operator1`) REFERENCES `petugas` (`nip`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `operasi_ibfk_36` FOREIGN KEY (`asisten_operator2`) REFERENCES `petugas` (`nip`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `operasi_ibfk_38` FOREIGN KEY (`dokter_anak`) REFERENCES `dokter` (`kd_dokter`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `operasi_ibfk_39` FOREIGN KEY (`perawaat_resusitas`) REFERENCES `petugas` (`nip`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `operasi_ibfk_40` FOREIGN KEY (`dokter_anestesi`) REFERENCES `dokter` (`kd_dokter`) ON UPDATE CASCADE,
  ADD CONSTRAINT `operasi_ibfk_41` FOREIGN KEY (`asisten_anestesi`) REFERENCES `petugas` (`nip`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `operasi_ibfk_42` FOREIGN KEY (`bidan`) REFERENCES `petugas` (`nip`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `operasi_ibfk_43` FOREIGN KEY (`perawat_luar`) REFERENCES `petugas` (`nip`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `operasi_ibfk_44` FOREIGN KEY (`kode_paket`) REFERENCES `paket_operasi` (`kode_paket`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `opname`
--
ALTER TABLE `opname`
  ADD CONSTRAINT `opname_ibfk_1` FOREIGN KEY (`kode_brng`) REFERENCES `databarang` (`kode_brng`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `opname_ibfk_2` FOREIGN KEY (`kd_bangsal`) REFERENCES `bangsal` (`kd_bangsal`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `paket_operasi`
--
ALTER TABLE `paket_operasi`
  ADD CONSTRAINT `paket_operasi_ibfk_1` FOREIGN KEY (`kd_pj`) REFERENCES `penjab` (`kd_pj`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `pasien`
--
ALTER TABLE `pasien`
  ADD CONSTRAINT `pasien_ibfk_1` FOREIGN KEY (`kd_pj`) REFERENCES `penjab` (`kd_pj`) ON UPDATE CASCADE,
  ADD CONSTRAINT `pasien_ibfk_3` FOREIGN KEY (`kd_kec`) REFERENCES `kecamatan` (`kd_kec`) ON UPDATE CASCADE,
  ADD CONSTRAINT `pasien_ibfk_4` FOREIGN KEY (`kd_kab`) REFERENCES `kabupaten` (`kd_kab`) ON UPDATE CASCADE,
  ADD CONSTRAINT `pasien_ibfk_5` FOREIGN KEY (`perusahaan_pasien`) REFERENCES `perusahaan_pasien` (`kode_perusahaan`) ON UPDATE CASCADE,
  ADD CONSTRAINT `pasien_ibfk_6` FOREIGN KEY (`suku_bangsa`) REFERENCES `suku_bangsa` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `pasien_ibfk_7` FOREIGN KEY (`bahasa_pasien`) REFERENCES `bahasa_pasien` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `pasien_ibfk_8` FOREIGN KEY (`cacat_fisik`) REFERENCES `cacat_fisik` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `pasien_ibfk_9` FOREIGN KEY (`kd_prop`) REFERENCES `propinsi` (`kd_prop`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `pegawai`
--
ALTER TABLE `pegawai`
  ADD CONSTRAINT `pegawai_ibfk_1` FOREIGN KEY (`jnj_jabatan`) REFERENCES `jnj_jabatan` (`kode`) ON UPDATE CASCADE,
  ADD CONSTRAINT `pegawai_ibfk_10` FOREIGN KEY (`kode_kelompok`) REFERENCES `kelompok_jabatan` (`kode_kelompok`) ON UPDATE CASCADE,
  ADD CONSTRAINT `pegawai_ibfk_11` FOREIGN KEY (`kode_resiko`) REFERENCES `resiko_kerja` (`kode_resiko`) ON UPDATE CASCADE,
  ADD CONSTRAINT `pegawai_ibfk_2` FOREIGN KEY (`departemen`) REFERENCES `departemen` (`dep_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `pegawai_ibfk_3` FOREIGN KEY (`bidang`) REFERENCES `bidang` (`nama`) ON UPDATE CASCADE,
  ADD CONSTRAINT `pegawai_ibfk_4` FOREIGN KEY (`stts_wp`) REFERENCES `stts_wp` (`stts`) ON UPDATE CASCADE,
  ADD CONSTRAINT `pegawai_ibfk_5` FOREIGN KEY (`stts_kerja`) REFERENCES `stts_kerja` (`stts`) ON UPDATE CASCADE,
  ADD CONSTRAINT `pegawai_ibfk_6` FOREIGN KEY (`pendidikan`) REFERENCES `pendidikan` (`tingkat`) ON UPDATE CASCADE,
  ADD CONSTRAINT `pegawai_ibfk_7` FOREIGN KEY (`indexins`) REFERENCES `departemen` (`dep_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `pegawai_ibfk_8` FOREIGN KEY (`bpd`) REFERENCES `bank` (`namabank`) ON UPDATE CASCADE,
  ADD CONSTRAINT `pegawai_ibfk_9` FOREIGN KEY (`kode_emergency`) REFERENCES `emergency_index` (`kode_emergency`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `pemeliharaan_inventaris`
--
ALTER TABLE `pemeliharaan_inventaris`
  ADD CONSTRAINT `pemeliharaan_inventaris_ibfk_1` FOREIGN KEY (`no_inventaris`) REFERENCES `inventaris` (`no_inventaris`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `pemeliharaan_inventaris_ibfk_2` FOREIGN KEY (`nip`) REFERENCES `petugas` (`nip`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `pemeriksaan_ralan`
--
ALTER TABLE `pemeriksaan_ralan`
  ADD CONSTRAINT `pemeriksaan_ralan_ibfk_1` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `pemeriksaan_ralan_ibfk_2` FOREIGN KEY (`nip`) REFERENCES `pegawai` (`nik`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `pemeriksaan_ranap`
--
ALTER TABLE `pemeriksaan_ranap`
  ADD CONSTRAINT `pemeriksaan_ranap_ibfk_1` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `pemeriksaan_ranap_ibfk_2` FOREIGN KEY (`nip`) REFERENCES `pegawai` (`nik`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `penilaian_awal_keperawatan_igd`
--
ALTER TABLE `penilaian_awal_keperawatan_igd`
  ADD CONSTRAINT `penilaian_awal_keperawatan_igd_ibfk_1` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `penilaian_awal_keperawatan_igd_ibfk_2` FOREIGN KEY (`nip`) REFERENCES `petugas` (`nip`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `penilaian_awal_keperawatan_ralan`
--
ALTER TABLE `penilaian_awal_keperawatan_ralan`
  ADD CONSTRAINT `penilaian_awal_keperawatan_ralan_ibfk_1` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `penilaian_awal_keperawatan_ralan_ibfk_2` FOREIGN KEY (`nip`) REFERENCES `petugas` (`nip`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `penilaian_awal_keperawatan_ranap`
--
ALTER TABLE `penilaian_awal_keperawatan_ranap`
  ADD CONSTRAINT `penilaian_awal_keperawatan_ranap_ibfk_1` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `penilaian_awal_keperawatan_ranap_ibfk_2` FOREIGN KEY (`nip1`) REFERENCES `petugas` (`nip`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `penilaian_awal_keperawatan_ranap_ibfk_3` FOREIGN KEY (`nip2`) REFERENCES `petugas` (`nip`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `penilaian_awal_keperawatan_ranap_ibfk_4` FOREIGN KEY (`kd_dokter`) REFERENCES `dokter` (`kd_dokter`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `penilaian_medis_igd`
--
ALTER TABLE `penilaian_medis_igd`
  ADD CONSTRAINT `penilaian_medis_igd_ibfk_1` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `penilaian_medis_igd_ibfk_2` FOREIGN KEY (`kd_dokter`) REFERENCES `dokter` (`kd_dokter`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `penilaian_medis_ralan`
--
ALTER TABLE `penilaian_medis_ralan`
  ADD CONSTRAINT `penilaian_medis_ralan_ibfk_1` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `penilaian_medis_ralan_ibfk_2` FOREIGN KEY (`kd_dokter`) REFERENCES `dokter` (`kd_dokter`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `penilaian_medis_ranap`
--
ALTER TABLE `penilaian_medis_ranap`
  ADD CONSTRAINT `penilaian_medis_ranap_ibfk_1` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `penilaian_medis_ranap_ibfk_2` FOREIGN KEY (`kd_dokter`) REFERENCES `dokter` (`kd_dokter`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `penilaian_ulang_nyeri`
--
ALTER TABLE `penilaian_ulang_nyeri`
  ADD CONSTRAINT `penilaian_ulang_nyeri_ibfk_1` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `penilaian_ulang_nyeri_ibfk_2` FOREIGN KEY (`nip`) REFERENCES `petugas` (`nip`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `penyakit`
--
ALTER TABLE `penyakit`
  ADD CONSTRAINT `penyakit_ibfk_1` FOREIGN KEY (`kd_ktg`) REFERENCES `kategori_penyakit` (`kd_ktg`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `perbaikan_inventaris`
--
ALTER TABLE `perbaikan_inventaris`
  ADD CONSTRAINT `perbaikan_inventaris_ibfk_1` FOREIGN KEY (`no_permintaan`) REFERENCES `permintaan_perbaikan_inventaris` (`no_permintaan`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `perbaikan_inventaris_ibfk_2` FOREIGN KEY (`nip`) REFERENCES `petugas` (`nip`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `periksa_lab`
--
ALTER TABLE `periksa_lab`
  ADD CONSTRAINT `periksa_lab_ibfk_10` FOREIGN KEY (`nip`) REFERENCES `petugas` (`nip`) ON UPDATE CASCADE,
  ADD CONSTRAINT `periksa_lab_ibfk_11` FOREIGN KEY (`kd_jenis_prw`) REFERENCES `jns_perawatan_lab` (`kd_jenis_prw`) ON UPDATE CASCADE,
  ADD CONSTRAINT `periksa_lab_ibfk_12` FOREIGN KEY (`dokter_perujuk`) REFERENCES `dokter` (`kd_dokter`) ON UPDATE CASCADE,
  ADD CONSTRAINT `periksa_lab_ibfk_13` FOREIGN KEY (`kd_dokter`) REFERENCES `dokter` (`kd_dokter`) ON UPDATE CASCADE,
  ADD CONSTRAINT `periksa_lab_ibfk_9` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `periksa_radiologi`
--
ALTER TABLE `periksa_radiologi`
  ADD CONSTRAINT `periksa_radiologi_ibfk_4` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON UPDATE CASCADE,
  ADD CONSTRAINT `periksa_radiologi_ibfk_5` FOREIGN KEY (`nip`) REFERENCES `petugas` (`nip`) ON UPDATE CASCADE,
  ADD CONSTRAINT `periksa_radiologi_ibfk_6` FOREIGN KEY (`kd_jenis_prw`) REFERENCES `jns_perawatan_radiologi` (`kd_jenis_prw`) ON UPDATE CASCADE,
  ADD CONSTRAINT `periksa_radiologi_ibfk_7` FOREIGN KEY (`kd_dokter`) REFERENCES `dokter` (`kd_dokter`) ON UPDATE CASCADE,
  ADD CONSTRAINT `periksa_radiologi_ibfk_8` FOREIGN KEY (`dokter_perujuk`) REFERENCES `dokter` (`kd_dokter`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `permintaan_detail_permintaan_lab`
--
ALTER TABLE `permintaan_detail_permintaan_lab`
  ADD CONSTRAINT `permintaan_detail_permintaan_lab_ibfk_2` FOREIGN KEY (`kd_jenis_prw`) REFERENCES `jns_perawatan_lab` (`kd_jenis_prw`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `permintaan_detail_permintaan_lab_ibfk_3` FOREIGN KEY (`id_template`) REFERENCES `template_laboratorium` (`id_template`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `permintaan_detail_permintaan_lab_ibfk_4` FOREIGN KEY (`noorder`) REFERENCES `permintaan_lab` (`noorder`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `permintaan_lab`
--
ALTER TABLE `permintaan_lab`
  ADD CONSTRAINT `permintaan_lab_ibfk_2` FOREIGN KEY (`dokter_perujuk`) REFERENCES `dokter` (`kd_dokter`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `permintaan_lab_ibfk_3` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `permintaan_pemeriksaan_lab`
--
ALTER TABLE `permintaan_pemeriksaan_lab`
  ADD CONSTRAINT `permintaan_pemeriksaan_lab_ibfk_1` FOREIGN KEY (`noorder`) REFERENCES `permintaan_lab` (`noorder`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `permintaan_pemeriksaan_lab_ibfk_2` FOREIGN KEY (`kd_jenis_prw`) REFERENCES `jns_perawatan_lab` (`kd_jenis_prw`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `permintaan_pemeriksaan_radiologi`
--
ALTER TABLE `permintaan_pemeriksaan_radiologi`
  ADD CONSTRAINT `permintaan_pemeriksaan_radiologi_ibfk_1` FOREIGN KEY (`noorder`) REFERENCES `permintaan_radiologi` (`noorder`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `permintaan_pemeriksaan_radiologi_ibfk_2` FOREIGN KEY (`kd_jenis_prw`) REFERENCES `jns_perawatan_radiologi` (`kd_jenis_prw`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `permintaan_perbaikan_inventaris`
--
ALTER TABLE `permintaan_perbaikan_inventaris`
  ADD CONSTRAINT `permintaan_perbaikan_inventaris_ibfk_1` FOREIGN KEY (`no_inventaris`) REFERENCES `inventaris` (`no_inventaris`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `permintaan_perbaikan_inventaris_ibfk_2` FOREIGN KEY (`nik`) REFERENCES `pegawai` (`nik`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `permintaan_radiologi`
--
ALTER TABLE `permintaan_radiologi`
  ADD CONSTRAINT `permintaan_radiologi_ibfk_1` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `permintaan_radiologi_ibfk_3` FOREIGN KEY (`dokter_perujuk`) REFERENCES `dokter` (`kd_dokter`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `personal_pasien`
--
ALTER TABLE `personal_pasien`
  ADD CONSTRAINT `personal_pasien_ibfk_1` FOREIGN KEY (`no_rkm_medis`) REFERENCES `pasien` (`no_rkm_medis`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `petugas`
--
ALTER TABLE `petugas`
  ADD CONSTRAINT `petugas_ibfk_4` FOREIGN KEY (`nip`) REFERENCES `pegawai` (`nik`) ON UPDATE CASCADE,
  ADD CONSTRAINT `petugas_ibfk_5` FOREIGN KEY (`kd_jbtn`) REFERENCES `jabatan` (`kd_jbtn`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `prosedur_pasien`
--
ALTER TABLE `prosedur_pasien`
  ADD CONSTRAINT `prosedur_pasien_ibfk_1` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `prosedur_pasien_ibfk_2` FOREIGN KEY (`kode`) REFERENCES `icd9` (`kode`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `rawat_inap_dr`
--
ALTER TABLE `rawat_inap_dr`
  ADD CONSTRAINT `rawat_inap_dr_ibfk_3` FOREIGN KEY (`kd_dokter`) REFERENCES `dokter` (`kd_dokter`) ON UPDATE CASCADE,
  ADD CONSTRAINT `rawat_inap_dr_ibfk_6` FOREIGN KEY (`kd_jenis_prw`) REFERENCES `jns_perawatan_inap` (`kd_jenis_prw`) ON UPDATE CASCADE,
  ADD CONSTRAINT `rawat_inap_dr_ibfk_7` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `rawat_inap_drpr`
--
ALTER TABLE `rawat_inap_drpr`
  ADD CONSTRAINT `rawat_inap_drpr_ibfk_1` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON UPDATE CASCADE,
  ADD CONSTRAINT `rawat_inap_drpr_ibfk_2` FOREIGN KEY (`kd_jenis_prw`) REFERENCES `jns_perawatan_inap` (`kd_jenis_prw`) ON UPDATE CASCADE,
  ADD CONSTRAINT `rawat_inap_drpr_ibfk_3` FOREIGN KEY (`kd_dokter`) REFERENCES `dokter` (`kd_dokter`) ON UPDATE CASCADE,
  ADD CONSTRAINT `rawat_inap_drpr_ibfk_4` FOREIGN KEY (`nip`) REFERENCES `petugas` (`nip`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `rawat_inap_pr`
--
ALTER TABLE `rawat_inap_pr`
  ADD CONSTRAINT `rawat_inap_pr_ibfk_3` FOREIGN KEY (`nip`) REFERENCES `petugas` (`nip`) ON UPDATE CASCADE,
  ADD CONSTRAINT `rawat_inap_pr_ibfk_6` FOREIGN KEY (`kd_jenis_prw`) REFERENCES `jns_perawatan_inap` (`kd_jenis_prw`) ON UPDATE CASCADE,
  ADD CONSTRAINT `rawat_inap_pr_ibfk_7` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `rawat_jl_dr`
--
ALTER TABLE `rawat_jl_dr`
  ADD CONSTRAINT `rawat_jl_dr_ibfk_2` FOREIGN KEY (`kd_jenis_prw`) REFERENCES `jns_perawatan` (`kd_jenis_prw`) ON UPDATE CASCADE,
  ADD CONSTRAINT `rawat_jl_dr_ibfk_3` FOREIGN KEY (`kd_dokter`) REFERENCES `dokter` (`kd_dokter`) ON UPDATE CASCADE,
  ADD CONSTRAINT `rawat_jl_dr_ibfk_5` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `rawat_jl_drpr`
--
ALTER TABLE `rawat_jl_drpr`
  ADD CONSTRAINT `rawat_jl_drpr_ibfk_1` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON UPDATE CASCADE,
  ADD CONSTRAINT `rawat_jl_drpr_ibfk_2` FOREIGN KEY (`kd_jenis_prw`) REFERENCES `jns_perawatan` (`kd_jenis_prw`) ON UPDATE CASCADE,
  ADD CONSTRAINT `rawat_jl_drpr_ibfk_3` FOREIGN KEY (`kd_dokter`) REFERENCES `dokter` (`kd_dokter`) ON UPDATE CASCADE,
  ADD CONSTRAINT `rawat_jl_drpr_ibfk_4` FOREIGN KEY (`nip`) REFERENCES `petugas` (`nip`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `rawat_jl_pr`
--
ALTER TABLE `rawat_jl_pr`
  ADD CONSTRAINT `rawat_jl_pr_ibfk_10` FOREIGN KEY (`nip`) REFERENCES `petugas` (`nip`) ON UPDATE CASCADE,
  ADD CONSTRAINT `rawat_jl_pr_ibfk_8` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON UPDATE CASCADE,
  ADD CONSTRAINT `rawat_jl_pr_ibfk_9` FOREIGN KEY (`kd_jenis_prw`) REFERENCES `jns_perawatan` (`kd_jenis_prw`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `reg_periksa`
--
ALTER TABLE `reg_periksa`
  ADD CONSTRAINT `reg_periksa_ibfk_3` FOREIGN KEY (`kd_poli`) REFERENCES `poliklinik` (`kd_poli`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `reg_periksa_ibfk_4` FOREIGN KEY (`kd_dokter`) REFERENCES `dokter` (`kd_dokter`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `reg_periksa_ibfk_6` FOREIGN KEY (`kd_pj`) REFERENCES `penjab` (`kd_pj`) ON UPDATE CASCADE,
  ADD CONSTRAINT `reg_periksa_ibfk_7` FOREIGN KEY (`no_rkm_medis`) REFERENCES `pasien` (`no_rkm_medis`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `rekap_presensi`
--
ALTER TABLE `rekap_presensi`
  ADD CONSTRAINT `rekap_presensi_ibfk_1` FOREIGN KEY (`id`) REFERENCES `pegawai` (`id`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `resep_dokter`
--
ALTER TABLE `resep_dokter`
  ADD CONSTRAINT `resep_dokter_ibfk_1` FOREIGN KEY (`no_resep`) REFERENCES `resep_obat` (`no_resep`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `resep_dokter_ibfk_2` FOREIGN KEY (`kode_brng`) REFERENCES `databarang` (`kode_brng`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `resep_dokter_racikan`
--
ALTER TABLE `resep_dokter_racikan`
  ADD CONSTRAINT `resep_dokter_racikan_ibfk_1` FOREIGN KEY (`no_resep`) REFERENCES `resep_obat` (`no_resep`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `resep_dokter_racikan_ibfk_2` FOREIGN KEY (`kd_racik`) REFERENCES `metode_racik` (`kd_racik`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `resep_dokter_racikan_detail`
--
ALTER TABLE `resep_dokter_racikan_detail`
  ADD CONSTRAINT `resep_dokter_racikan_detail_ibfk_1` FOREIGN KEY (`no_resep`) REFERENCES `resep_obat` (`no_resep`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `resep_dokter_racikan_detail_ibfk_2` FOREIGN KEY (`kode_brng`) REFERENCES `databarang` (`kode_brng`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `resep_obat`
--
ALTER TABLE `resep_obat`
  ADD CONSTRAINT `resep_obat_ibfk_3` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON UPDATE CASCADE,
  ADD CONSTRAINT `resep_obat_ibfk_4` FOREIGN KEY (`kd_dokter`) REFERENCES `dokter` (`kd_dokter`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `resep_pulang`
--
ALTER TABLE `resep_pulang`
  ADD CONSTRAINT `resep_pulang_ibfk_2` FOREIGN KEY (`kode_brng`) REFERENCES `databarang` (`kode_brng`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `resep_pulang_ibfk_3` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON UPDATE CASCADE,
  ADD CONSTRAINT `resep_pulang_ibfk_4` FOREIGN KEY (`kd_bangsal`) REFERENCES `bangsal` (`kd_bangsal`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `resume_pasien`
--
ALTER TABLE `resume_pasien`
  ADD CONSTRAINT `resume_pasien_ibfk_1` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `resume_pasien_ibfk_2` FOREIGN KEY (`kd_dokter`) REFERENCES `dokter` (`kd_dokter`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `resume_pasien_ranap`
--
ALTER TABLE `resume_pasien_ranap`
  ADD CONSTRAINT `resume_pasien_ranap_ibfk_1` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `resume_pasien_ranap_ibfk_2` FOREIGN KEY (`kd_dokter`) REFERENCES `dokter` (`kd_dokter`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `riwayat_barang_medis`
--
ALTER TABLE `riwayat_barang_medis`
  ADD CONSTRAINT `riwayat_barang_medis_ibfk_1` FOREIGN KEY (`kode_brng`) REFERENCES `databarang` (`kode_brng`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `riwayat_barang_medis_ibfk_2` FOREIGN KEY (`kd_bangsal`) REFERENCES `bangsal` (`kd_bangsal`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `skdp_bpjs`
--
ALTER TABLE `skdp_bpjs`
  ADD CONSTRAINT `skdp_bpjs_ibfk_1` FOREIGN KEY (`no_rkm_medis`) REFERENCES `pasien` (`no_rkm_medis`) ON UPDATE CASCADE,
  ADD CONSTRAINT `skdp_bpjs_ibfk_2` FOREIGN KEY (`kd_dokter`) REFERENCES `dokter` (`kd_dokter`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `tambahan_biaya`
--
ALTER TABLE `tambahan_biaya`
  ADD CONSTRAINT `tambahan_biaya_ibfk_1` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `template_laboratorium`
--
ALTER TABLE `template_laboratorium`
  ADD CONSTRAINT `template_laboratorium_ibfk_1` FOREIGN KEY (`kd_jenis_prw`) REFERENCES `jns_perawatan_lab` (`kd_jenis_prw`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `temporary_presensi`
--
ALTER TABLE `temporary_presensi`
  ADD CONSTRAINT `temporary_presensi_ibfk_1` FOREIGN KEY (`id`) REFERENCES `pegawai` (`id`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `utd_donor`
--
ALTER TABLE `utd_donor`
  ADD CONSTRAINT `utd_donor_ibfk_1` FOREIGN KEY (`petugas_aftap`) REFERENCES `petugas` (`nip`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `utd_donor_ibfk_2` FOREIGN KEY (`petugas_u_saring`) REFERENCES `petugas` (`nip`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `utd_donor_ibfk_3` FOREIGN KEY (`no_pendonor`) REFERENCES `utd_pendonor` (`no_pendonor`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `utd_pendonor`
--
ALTER TABLE `utd_pendonor`
  ADD CONSTRAINT `utd_pendonor_ibfk_1` FOREIGN KEY (`kd_kec`) REFERENCES `kecamatan` (`kd_kec`) ON UPDATE CASCADE,
  ADD CONSTRAINT `utd_pendonor_ibfk_2` FOREIGN KEY (`kd_kab`) REFERENCES `kabupaten` (`kd_kab`) ON UPDATE CASCADE,
  ADD CONSTRAINT `utd_pendonor_ibfk_3` FOREIGN KEY (`kd_prop`) REFERENCES `propinsi` (`kd_prop`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `utd_stok_darah`
--
ALTER TABLE `utd_stok_darah`
  ADD CONSTRAINT `utd_stok_darah_ibfk_1` FOREIGN KEY (`kode_komponen`) REFERENCES `utd_komponen_darah` (`kode`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
