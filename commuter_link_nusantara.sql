-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Mar 07, 2026 at 12:27 PM
-- Server version: 8.0.44
-- PHP Version: 8.3.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `commuter_link_nusantara`
--

-- --------------------------------------------------------

--
-- Table structure for table `barang_temuan`
--

CREATE TABLE `barang_temuan` (
  `id` bigint NOT NULL,
  `kode_barang` varchar(30) COLLATE utf8mb4_general_ci NOT NULL,
  `petugas_id` bigint NOT NULL,
  `nama_barang` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `kategori` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `deskripsi` text COLLATE utf8mb4_general_ci,
  `warna` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `merek` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `lokasi_ditemukan` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `no_krl` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `waktu_ditemukan` datetime NOT NULL,
  `foto_barang` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `catatan` text COLLATE utf8mb4_general_ci,
  `status` enum('tersimpan','dicocokkan','diklaim','diserahkan') COLLATE utf8mb4_general_ci DEFAULT 'tersimpan',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_by` bigint DEFAULT NULL,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` bigint DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` bigint DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `barang_temuan`
--

INSERT INTO `barang_temuan` (`id`, `kode_barang`, `petugas_id`, `nama_barang`, `kategori`, `deskripsi`, `warna`, `merek`, `lokasi_ditemukan`, `no_krl`, `waktu_ditemukan`, `foto_barang`, `catatan`, `status`, `created_at`, `created_by`, `updated_at`, `updated_by`, `deleted_at`, `deleted_by`) VALUES
(1, 'BT-20260307-942B84', 1, 'Pouch Makeup', 'Dompet', 'biru dan rellsletingnya pink agak besar', 'Biru', 'MAD', 'Stasiun Ancol', 'KFW3034', '2026-02-10 07:28:00', 'uploads/barang/BT_20260307081141_5651eb.jpeg', 'Petugas Seaa', 'tersimpan', '2026-03-07 15:11:41', 1, '2026-03-07 15:11:41', NULL, NULL, NULL),
(2, 'BT-20260307-B5012E', 1, 'Macbook', 'Laptop', 'dengan tas laptop motif batik', 'silver', 'Macbook Air Retina 2019 i5 8GB 256GB 128GB', 'Stasiun Duri', 'KFW8083', '2026-02-07 15:13:00', 'uploads/barang/BT_20260307081457_55b9eb.jpeg', 'Petugas Seaa', 'dicocokkan', '2026-03-07 15:14:57', 1, '2026-03-07 15:15:21', 1, NULL, NULL),
(3, 'BT-20260307-D7972D', 1, 'iPhone 18', 'Ponsel', 'case shincan', 'Silver', 'iPhone', 'Stasiun Manggarai', 'KFW8084', '2026-01-07 15:16:00', 'uploads/barang/BT_20260307081702_5a74e6.jpeg', 'Petugas seaa', 'tersimpan', '2026-03-07 15:17:02', 1, '2026-03-07 15:18:03', 1, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `bukti_kepemilikan`
--

CREATE TABLE `bukti_kepemilikan` (
  `id` bigint NOT NULL,
  `pencocokan_id` bigint NOT NULL,
  `user_id` bigint NOT NULL,
  `file_bukti` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `status_verifikasi` enum('menunggu','valid','tidak_valid') COLLATE utf8mb4_general_ci DEFAULT 'menunggu',
  `catatan_petugas` text COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_by` bigint DEFAULT NULL,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` bigint DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` bigint DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `laporan_kehilangan`
--

CREATE TABLE `laporan_kehilangan` (
  `id` bigint NOT NULL,
  `no_laporan` varchar(30) COLLATE utf8mb4_general_ci NOT NULL,
  `user_id` bigint NOT NULL,
  `nama_barang` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `kategori` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `deskripsi` text COLLATE utf8mb4_general_ci,
  `warna` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `merek` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `lokasi_hilang` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `no_krl` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `waktu_hilang` datetime NOT NULL,
  `foto_barang` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `catatan` text COLLATE utf8mb4_general_ci,
  `ditangani_oleh` bigint DEFAULT NULL,
  `id_pelapor_jenis` varchar(30) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `id_pelapor_no` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` enum('diproses','ditemukan','selesai','ditutup') COLLATE utf8mb4_general_ci DEFAULT 'diproses',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_by` bigint DEFAULT NULL,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` bigint DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` bigint DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `laporan_kehilangan`
--

INSERT INTO `laporan_kehilangan` (`id`, `no_laporan`, `user_id`, `nama_barang`, `kategori`, `deskripsi`, `warna`, `merek`, `lokasi_hilang`, `no_krl`, `waktu_hilang`, `foto_barang`, `catatan`, `ditangani_oleh`, `id_pelapor_jenis`, `id_pelapor_no`, `status`, `created_at`, `created_by`, `updated_at`, `updated_by`, `deleted_at`, `deleted_by`) VALUES
(1, 'LPR-20260307-809DC7', 1, 'iPhone 18', 'Ponsel', 'case bening dengan stiker shincan', 'Silver', 'iPhone', 'Gerbong 5 Stasiun Manggarai', 'KFW8083', '2026-01-08 02:45:00', NULL, 'Mohon cepat diproses dengan nama pelapor keonho', 1, 'KTP', '3718080384682632', 'diproses', '2026-03-07 14:52:41', 1, '2026-03-07 15:08:25', 1, NULL, NULL),
(2, 'LPR-20260307-C8F464', 1, 'Blindbox SkullPanda', 'Aksesoris', 'series L\'impressionnisme dan agak butek warnanya karna kotor sudah lama beli', 'Warna Ungu', 'POPMART', 'Gerbong 2 arah Bogor', 'KFW9092', '2026-02-12 22:15:00', NULL, 'Atas nama Rora', 1, 'KTP', '31740358928493', 'ditemukan', '2026-03-07 14:58:06', 1, '2026-03-07 14:58:06', NULL, NULL, NULL),
(3, 'LPR-20260307-26557E', 1, 'Kipas portable', 'Lainnya', 'ada stiker shinchan', 'hitam', 'M11', 'Stasiun Bekasi Peron 2', 'KFW8083', '2026-02-20 12:15:00', NULL, 'Atas nama Chris', 1, 'Kartu Mahasiswa', '32230163', 'selesai', '2026-03-07 15:00:21', 1, '2026-03-07 15:01:09', 1, NULL, NULL),
(4, 'LPR-20260307-D9AFC3', 1, 'Macbook', 'Laptop', 'bareng tas laptop motif batik', 'silver', 'Macbook Air Retina 2019 i5 8GB 256GB 128GB', 'Stasiun Duri', 'KFW9097', '2026-02-18 23:10:00', NULL, 'Atas nama owen', 1, 'KTP', '3174935782975438', 'ditutup', '2026-03-07 15:08:12', 1, '2026-03-07 15:08:12', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `log_aktivitas`
--

CREATE TABLE `log_aktivitas` (
  `id` bigint NOT NULL,
  `user_id` bigint NOT NULL,
  `aktivitas` text COLLATE utf8mb4_general_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pencocokan`
--

CREATE TABLE `pencocokan` (
  `id` bigint NOT NULL,
  `laporan_id` bigint NOT NULL,
  `barang_id` bigint NOT NULL,
  `petugas_id` bigint NOT NULL,
  `status` enum('menunggu_verifikasi','diverifikasi','ditolak') COLLATE utf8mb4_general_ci DEFAULT 'menunggu_verifikasi',
  `catatan` text COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_by` bigint DEFAULT NULL,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` bigint DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` bigint DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `serah_terima`
--

CREATE TABLE `serah_terima` (
  `id` bigint NOT NULL,
  `pencocokan_id` bigint NOT NULL,
  `petugas_id` bigint NOT NULL,
  `pelapor_id` bigint NOT NULL,
  `tanggal_serah_terima` datetime NOT NULL,
  `catatan` text COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_by` bigint DEFAULT NULL,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` bigint DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` bigint DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint NOT NULL,
  `nama` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `no_telepon` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `telp` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `role` enum('pelapor','petugas') COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_by` bigint DEFAULT NULL,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` bigint DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` bigint DEFAULT NULL,
  `bio` text COLLATE utf8mb4_general_ci,
  `stasiun` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `avatar` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `nama`, `email`, `no_telepon`, `telp`, `password`, `role`, `created_at`, `created_by`, `updated_at`, `updated_by`, `deleted_at`, `deleted_by`, `bio`, `stasiun`, `avatar`) VALUES
(1, 'seaa', 'saitouayummu@gmail.com', NULL, NULL, '$2y$10$vVQm4BQ2PemCYdCqPsz/m.yXRdEZJrPJNDqLI.gj0yE0pR75MkoW6', 'petugas', '2026-02-27 23:19:06', NULL, '2026-03-05 23:12:37', NULL, NULL, NULL, 'workahoolic', 'Tangerang Line', 'uploads/avatars/avatar_1_1772726508.jpg'),
(2, 'sean', 'sean@gmail.com', NULL, NULL, '$2y$10$dDaoj17VYnVETSveegW8tO8PvIxG2fOo2lx6AFo82QFy3uCjxIu2q', 'pelapor', '2026-03-01 12:39:59', NULL, '2026-03-01 12:39:59', NULL, NULL, NULL, NULL, NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `barang_temuan`
--
ALTER TABLE `barang_temuan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_barang` (`kode_barang`),
  ADD KEY `fk_barang_petugas` (`petugas_id`),
  ADD KEY `fk_barang_created_by` (`created_by`),
  ADD KEY `fk_barang_updated_by` (`updated_by`),
  ADD KEY `idx_barang_status` (`status`),
  ADD KEY `idx_barang_waktu` (`waktu_ditemukan`),
  ADD KEY `fk_barang_deleted_by` (`deleted_by`);

--
-- Indexes for table `bukti_kepemilikan`
--
ALTER TABLE `bukti_kepemilikan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_bukti_pencocokan` (`pencocokan_id`),
  ADD KEY `fk_bukti_user` (`user_id`),
  ADD KEY `fk_bukti_created_by` (`created_by`),
  ADD KEY `fk_bukti_updated_by` (`updated_by`),
  ADD KEY `fk_bukti_deleted_by` (`deleted_by`);

--
-- Indexes for table `laporan_kehilangan`
--
ALTER TABLE `laporan_kehilangan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `no_laporan` (`no_laporan`),
  ADD KEY `fk_laporan_user` (`user_id`),
  ADD KEY `fk_laporan_created_by` (`created_by`),
  ADD KEY `fk_laporan_updated_by` (`updated_by`),
  ADD KEY `idx_laporan_status` (`status`),
  ADD KEY `idx_laporan_waktu` (`waktu_hilang`),
  ADD KEY `fk_laporan_deleted_by` (`deleted_by`),
  ADD KEY `fk_laporan_ditangani_oleh` (`ditangani_oleh`);

--
-- Indexes for table `log_aktivitas`
--
ALTER TABLE `log_aktivitas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_log_user` (`user_id`);

--
-- Indexes for table `pencocokan`
--
ALTER TABLE `pencocokan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_match` (`laporan_id`,`barang_id`),
  ADD KEY `fk_pencocokan_barang` (`barang_id`),
  ADD KEY `fk_pencocokan_petugas` (`petugas_id`),
  ADD KEY `fk_pencocokan_created_by` (`created_by`),
  ADD KEY `fk_pencocokan_updated_by` (`updated_by`),
  ADD KEY `fk_pencocokan_deleted_by` (`deleted_by`);

--
-- Indexes for table `serah_terima`
--
ALTER TABLE `serah_terima`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_serah_pencocokan` (`pencocokan_id`),
  ADD KEY `fk_serah_petugas` (`petugas_id`),
  ADD KEY `fk_serah_pelapor` (`pelapor_id`),
  ADD KEY `fk_serah_created_by` (`created_by`),
  ADD KEY `fk_serah_updated_by` (`updated_by`),
  ADD KEY `fk_serah_deleted_by` (`deleted_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_users_created_by` (`created_by`),
  ADD KEY `fk_users_updated_by` (`updated_by`),
  ADD KEY `fk_users_deleted_by` (`deleted_by`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `barang_temuan`
--
ALTER TABLE `barang_temuan`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `bukti_kepemilikan`
--
ALTER TABLE `bukti_kepemilikan`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `laporan_kehilangan`
--
ALTER TABLE `laporan_kehilangan`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `log_aktivitas`
--
ALTER TABLE `log_aktivitas`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pencocokan`
--
ALTER TABLE `pencocokan`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `serah_terima`
--
ALTER TABLE `serah_terima`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `barang_temuan`
--
ALTER TABLE `barang_temuan`
  ADD CONSTRAINT `fk_barang_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_barang_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_barang_petugas` FOREIGN KEY (`petugas_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_barang_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `bukti_kepemilikan`
--
ALTER TABLE `bukti_kepemilikan`
  ADD CONSTRAINT `fk_bukti_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_bukti_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_bukti_pencocokan` FOREIGN KEY (`pencocokan_id`) REFERENCES `pencocokan` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_bukti_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_bukti_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `laporan_kehilangan`
--
ALTER TABLE `laporan_kehilangan`
  ADD CONSTRAINT `fk_laporan_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_laporan_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_laporan_ditangani_oleh` FOREIGN KEY (`ditangani_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_laporan_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_laporan_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `log_aktivitas`
--
ALTER TABLE `log_aktivitas`
  ADD CONSTRAINT `fk_log_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pencocokan`
--
ALTER TABLE `pencocokan`
  ADD CONSTRAINT `fk_pencocokan_barang` FOREIGN KEY (`barang_id`) REFERENCES `barang_temuan` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pencocokan_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_pencocokan_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_pencocokan_laporan` FOREIGN KEY (`laporan_id`) REFERENCES `laporan_kehilangan` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pencocokan_petugas` FOREIGN KEY (`petugas_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pencocokan_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `serah_terima`
--
ALTER TABLE `serah_terima`
  ADD CONSTRAINT `fk_serah_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_serah_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_serah_pelapor` FOREIGN KEY (`pelapor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_serah_pencocokan` FOREIGN KEY (`pencocokan_id`) REFERENCES `pencocokan` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_serah_petugas` FOREIGN KEY (`petugas_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_serah_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_users_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_users_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
