-- Import this file once into the existing `seblak` database.
-- Adds customer accounts, configurable order service variants, and delivery addresses.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `customers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nama` varchar(100) NOT NULL,
  `no_hp` varchar(25) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `no_hp` (`no_hp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `order_type_options` (
  `id` int NOT NULL AUTO_INCREMENT,
  `kode` varchar(40) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `perlu_alamat` tinyint(1) NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `urutan` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode` (`kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `order_type_options` (`kode`, `nama`, `perlu_alamat`, `status`, `urutan`) VALUES
  ('dine_in', 'Makan di tempat', 0, 1, 1),
  ('takeaway', 'Dibawa pulang', 0, 1, 2),
  ('delivery', 'Dianter', 1, 1, 3)
ON DUPLICATE KEY UPDATE
  `nama` = VALUES(`nama`),
  `perlu_alamat` = VALUES(`perlu_alamat`),
  `status` = VALUES(`status`),
  `urutan` = VALUES(`urutan`);

ALTER TABLE `orders`
  MODIFY `jenis_pesanan` varchar(100) DEFAULT 'takeaway',
  ADD COLUMN `customer_id` int DEFAULT NULL AFTER `user_id`,
  ADD COLUMN `alamat_pengantaran` varchar(500) DEFAULT NULL AFTER `jenis_pesanan`,
  ADD KEY `customer_id` (`customer_id`),
  ADD CONSTRAINT `orders_ibfk_3` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL;
