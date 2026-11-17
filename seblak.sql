-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Nov 13, 2026 at 11:43 PM
-- Server version: 8.4.3
-- PHP Version: 8.4.26

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `seblak`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int NOT NULL,
  `nama_kategori` varchar(100) NOT NULL,
  `deskripsi` text,
  `status` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `finance_categories`
--

CREATE TABLE `finance_categories` (
  `id` int NOT NULL,
  `nama_kategori` varchar(100) NOT NULL,
  `jenis` enum('pemasukan','pengeluaran') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `finance_categories`
--

INSERT INTO `finance_categories` (`id`, `nama_kategori`, `jenis`) VALUES
(1, 'Pemasukan lainnya', 'pemasukan'),
(2, 'Pembelian bahan baku', 'pengeluaran'),
(3, 'Pembelian gas', 'pengeluaran'),
(4, 'Kemasan', 'pengeluaran'),
(5, 'Listrik dan air', 'pengeluaran'),
(6, 'Biaya operasional lainnya', 'pengeluaran');

-- --------------------------------------------------------

--
-- Table structure for table `finance_entries`
--

CREATE TABLE `finance_entries` (
  `id` int NOT NULL,
  `finance_category_id` int DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `jenis` enum('pemasukan','pengeluaran') NOT NULL,
  `jumlah` decimal(12,2) NOT NULL,
  `tanggal` date NOT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int NOT NULL,
  `kode_pesanan` varchar(40) NOT NULL,
  `sumber` enum('kasir','online') NOT NULL,
  `nama_pelanggan` varchar(120) DEFAULT NULL,
  `no_hp` varchar(25) DEFAULT NULL,
  `jenis_pesanan` enum('dine_in','takeaway') DEFAULT 'takeaway',
  `whatsapp_number_id` int DEFAULT NULL,
  `status_pesanan` enum('menunggu','dikonfirmasi','diproses','selesai','ditolak','dibatalkan') DEFAULT 'dikonfirmasi',
  `status_pembayaran` enum('belum_bayar','sebagian','lunas','dikembalikan') DEFAULT 'belum_bayar',
  `subtotal` decimal(12,2) DEFAULT '0.00',
  `diskon` decimal(12,2) DEFAULT '0.00',
  `total` decimal(12,2) DEFAULT '0.00',
  `catatan` text,
  `user_id` int DEFAULT NULL,
  `metode_pembayaran` varchar(100) DEFAULT NULL,
  `bukti_pembayaran` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_code_sequences`
--

CREATE TABLE `order_code_sequences` (
  `order_date` date NOT NULL,
  `last_number` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`order_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int NOT NULL,
  `order_id` int NOT NULL,
  `product_id` int DEFAULT NULL,
  `nama_produk` varchar(150) NOT NULL,
  `jumlah` int DEFAULT '1',
  `harga` decimal(12,2) DEFAULT '0.00',
  `subtotal` decimal(12,2) DEFAULT '0.00',
  `catatan` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_item_toppings`
--

CREATE TABLE `order_item_toppings` (
  `id` int NOT NULL,
  `order_item_id` int NOT NULL,
  `topping_id` int DEFAULT NULL,
  `nama_topping` varchar(100) NOT NULL,
  `jumlah` int DEFAULT '1',
  `pcs_per_paket` int NOT NULL DEFAULT '1',
  `harga_satuan` decimal(12,2) DEFAULT '0.00',
  `subtotal` decimal(12,2) DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_item_variants`
--

CREATE TABLE `order_item_variants` (
  `id` int NOT NULL,
  `order_item_id` int NOT NULL,
  `variant_group_id` int DEFAULT NULL,
  `variant_option_id` int DEFAULT NULL,
  `nama_grup` varchar(100) NOT NULL,
  `nama_pilihan` varchar(100) NOT NULL,
  `harga_tambahan` decimal(12,2) DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int NOT NULL,
  `order_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `jumlah_bayar` decimal(12,2) NOT NULL,
  `metode` varchar(100) NOT NULL DEFAULT 'tunai',
  `tanggal_bayar` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_methods`
--

CREATE TABLE `payment_methods` (
  `id` int NOT NULL,
  `nama` varchar(100) NOT NULL,
  `tipe` enum('rekening','gambar','lainnya') NOT NULL DEFAULT 'lainnya',
  `nomor_rekening` varchar(100) DEFAULT NULL,
  `nama_penerima` varchar(100) DEFAULT NULL,
  `gambar` varchar(255) DEFAULT NULL,
  `detail` text,
  `status` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `store_settings`
--

CREATE TABLE `store_settings` (
  `id` tinyint NOT NULL,
  `status` enum('buka','tutup') NOT NULL DEFAULT 'buka',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `store_settings` (`id`, `status`) VALUES (1, 'buka');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int NOT NULL,
  `category_id` int DEFAULT NULL,
  `nama_produk` varchar(150) NOT NULL,
  `deskripsi` text,
  `harga` decimal(12,2) DEFAULT '0.00',
  `gambar` varchar(255) DEFAULT NULL,
  `status` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_toppings`
--

CREATE TABLE `product_toppings` (
  `product_id` int NOT NULL,
  `topping_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_variant_groups`
--

CREATE TABLE `product_variant_groups` (
  `product_id` int NOT NULL,
  `variant_group_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `toppings`
--

CREATE TABLE `toppings` (
  `id` int NOT NULL,
  `nama_topping` varchar(100) NOT NULL,
  `harga` decimal(12,2) NOT NULL DEFAULT '0.00',
  `pcs_per_paket` int NOT NULL DEFAULT '1',
  `gambar` varchar(255) DEFAULT NULL,
  `status` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `nama` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `no_hp` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','karyawan') DEFAULT 'karyawan',
  `foto` varchar(255) DEFAULT NULL,
  `status` enum('aktif','nonaktif') DEFAULT 'aktif',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `variant_groups`
--

CREATE TABLE `variant_groups` (
  `id` int NOT NULL,
  `nama_varian` varchar(100) NOT NULL,
  `tipe_pilihan` enum('satu','banyak') DEFAULT 'satu',
  `wajib_dipilih` tinyint(1) DEFAULT '0',
  `status` tinyint(1) DEFAULT '1',
  `urutan` int DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `variant_options`
--

CREATE TABLE `variant_options` (
  `id` int NOT NULL,
  `variant_group_id` int NOT NULL,
  `nama_pilihan` varchar(100) NOT NULL,
  `harga_tambahan` decimal(12,2) DEFAULT '0.00',
  `status` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `whatsapp_numbers`
--

CREATE TABLE `whatsapp_numbers` (
  `id` int NOT NULL,
  `nama` varchar(100) NOT NULL,
  `nomor` varchar(25) NOT NULL,
  `status` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `finance_categories`
--
ALTER TABLE `finance_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `finance_entries`
--
ALTER TABLE `finance_entries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `finance_category_id` (`finance_category_id`),
  ADD KEY `user_id` (`user_id`);

-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_pesanan` (`kode_pesanan`),
  ADD KEY `whatsapp_number_id` (`whatsapp_number_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `order_item_toppings`
--
ALTER TABLE `order_item_toppings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_item_id` (`order_item_id`),
  ADD KEY `topping_id` (`topping_id`);

--
-- Indexes for table `order_item_variants`
--
ALTER TABLE `order_item_variants`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_item_id` (`order_item_id`),
  ADD KEY `variant_group_id` (`variant_group_id`),
  ADD KEY `variant_option_id` (`variant_option_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `payment_methods`
--
ALTER TABLE `payment_methods`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nama` (`nama`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `product_toppings`
--
ALTER TABLE `product_toppings`
  ADD PRIMARY KEY (`product_id`,`topping_id`),
  ADD KEY `topping_id` (`topping_id`);

--
-- Indexes for table `product_variant_groups`
--
ALTER TABLE `product_variant_groups`
  ADD PRIMARY KEY (`product_id`,`variant_group_id`),
  ADD KEY `variant_group_id` (`variant_group_id`);

--
-- Indexes for table `toppings`
--
ALTER TABLE `toppings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `variant_groups`
--
ALTER TABLE `variant_groups`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `variant_options`
--
ALTER TABLE `variant_options`
  ADD PRIMARY KEY (`id`),
  ADD KEY `variant_group_id` (`variant_group_id`);

--
-- Indexes for table `whatsapp_numbers`
--
ALTER TABLE `whatsapp_numbers`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `finance_categories`
--
ALTER TABLE `finance_categories`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `finance_entries`
--
ALTER TABLE `finance_entries`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_item_toppings`
--
ALTER TABLE `order_item_toppings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_item_variants`
--
ALTER TABLE `order_item_variants`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_methods`
--
ALTER TABLE `payment_methods`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `toppings`
--
ALTER TABLE `toppings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `variant_groups`
--
ALTER TABLE `variant_groups`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `variant_options`
--
ALTER TABLE `variant_options`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `whatsapp_numbers`
--
ALTER TABLE `whatsapp_numbers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `finance_entries`
--
ALTER TABLE `finance_entries`
  ADD CONSTRAINT `finance_entries_ibfk_1` FOREIGN KEY (`finance_category_id`) REFERENCES `finance_categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `finance_entries_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`whatsapp_number_id`) REFERENCES `whatsapp_numbers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `order_item_toppings`
--
ALTER TABLE `order_item_toppings`
  ADD CONSTRAINT `order_item_toppings_ibfk_1` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_item_toppings_ibfk_2` FOREIGN KEY (`topping_id`) REFERENCES `toppings` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `order_item_variants`
--
ALTER TABLE `order_item_variants`
  ADD CONSTRAINT `order_item_variants_ibfk_1` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_item_variants_ibfk_2` FOREIGN KEY (`variant_group_id`) REFERENCES `variant_groups` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `order_item_variants_ibfk_3` FOREIGN KEY (`variant_option_id`) REFERENCES `variant_options` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `product_toppings`
--
ALTER TABLE `product_toppings`
  ADD CONSTRAINT `product_toppings_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_toppings_ibfk_2` FOREIGN KEY (`topping_id`) REFERENCES `toppings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_variant_groups`
--
ALTER TABLE `product_variant_groups`
  ADD CONSTRAINT `product_variant_groups_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_variant_groups_ibfk_2` FOREIGN KEY (`variant_group_id`) REFERENCES `variant_groups` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `variant_options`
--
ALTER TABLE `variant_options`
  ADD CONSTRAINT `variant_options_ibfk_1` FOREIGN KEY (`variant_group_id`) REFERENCES `variant_groups` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
