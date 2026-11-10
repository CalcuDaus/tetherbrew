-- =====================================================
-- TETHER BREW DATABASE SCHEMA
-- Migrasi dari Laravel 13 ke PHP Native
-- =====================================================

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

CREATE DATABASE IF NOT EXISTS `coffee_gerobak`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `coffee_gerobak`;

-- ==========================================
-- 1. branches (harus pertama karena direferensi banyak tabel)
-- ==========================================
CREATE TABLE IF NOT EXISTS `branches` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `code` VARCHAR(255) NOT NULL UNIQUE,
  `address` TEXT NULL,
  `phone` VARCHAR(255) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL
) ENGINE=InnoDB;

INSERT INTO `branches` (`name`, `code`, `is_active`, `created_at`, `updated_at`)
VALUES ('Cabang Utama', 'utama', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE `name` = `name`;

-- ==========================================
-- 2. users
-- ==========================================
CREATE TABLE IF NOT EXISTS `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `branch_id` BIGINT UNSIGNED NULL,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `whatsapp` VARCHAR(255) NULL,
  `phone` VARCHAR(20) NULL UNIQUE,
  `email_verified_at` TIMESTAMP NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('owner','admin','bar','rider','customer') NOT NULL DEFAULT 'rider',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `remember_token` VARCHAR(100) NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ==========================================
-- 3. password_reset_tokens
-- ==========================================
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `email` VARCHAR(255) NOT NULL PRIMARY KEY,
  `token` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NULL
) ENGINE=InnoDB;

-- ==========================================
-- 4. carts (gerobak)
-- ==========================================
CREATE TABLE IF NOT EXISTS `carts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `branch_id` BIGINT UNSIGNED NULL,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `user_id` BIGINT UNSIGNED NULL,
  `status` ENUM('active','inactive','closed') NOT NULL DEFAULT 'inactive',
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ==========================================
-- 5. cart_locations
-- ==========================================
CREATE TABLE IF NOT EXISTS `cart_locations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `cart_id` BIGINT UNSIGNED NOT NULL,
  `latitude` DECIMAL(10,7) NOT NULL,
  `longitude` DECIMAL(10,7) NOT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  FOREIGN KEY (`cart_id`) REFERENCES `carts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ==========================================
-- 6. products
-- ==========================================
CREATE TABLE IF NOT EXISTS `products` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `price` DECIMAL(12,2) NOT NULL,
  `image` VARCHAR(255) NULL,
  `category` VARCHAR(255) NOT NULL DEFAULT 'kopi',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL
) ENGINE=InnoDB;

-- ==========================================
-- 7. inventories
-- ==========================================
CREATE TABLE IF NOT EXISTS `inventories` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `branch_id` BIGINT UNSIGNED NULL,
  `cart_id` BIGINT UNSIGNED NOT NULL,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `stock` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  UNIQUE KEY `inventories_cart_product_unique` (`cart_id`, `product_id`),
  FOREIGN KEY (`cart_id`) REFERENCES `carts`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ==========================================
-- 8. transactions
-- ==========================================
CREATE TABLE IF NOT EXISTS `transactions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `branch_id` BIGINT UNSIGNED NULL,
  `cart_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `total_price` DECIMAL(12,2) NOT NULL,
  `payment_method` ENUM('cash','qris') NOT NULL DEFAULT 'cash',
  `notes` TEXT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  FOREIGN KEY (`cart_id`) REFERENCES `carts`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ==========================================
-- 9. transaction_items
-- ==========================================
CREATE TABLE IF NOT EXISTS `transaction_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `transaction_id` BIGINT UNSIGNED NOT NULL,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `qty` INT NOT NULL,
  `price` DECIMAL(12,2) NOT NULL,
  `subtotal` DECIMAL(12,2) NOT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  FOREIGN KEY (`transaction_id`) REFERENCES `transactions`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ==========================================
-- 10. journal_categories
-- ==========================================
CREATE TABLE IF NOT EXISTS `journal_categories` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `branch_id` BIGINT UNSIGNED NULL,
  `name` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ==========================================
-- 11. journals
-- ==========================================
CREATE TABLE IF NOT EXISTS `journals` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `branch_id` BIGINT UNSIGNED NULL,
  `date` DATE NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  `type` ENUM('debit','credit') NOT NULL,
  `amount` DECIMAL(15,2) NOT NULL,
  `journal_category_id` BIGINT UNSIGNED NULL,
  `created_by` BIGINT UNSIGNED NOT NULL,
  `reference_type` VARCHAR(255) NULL,
  `reference_id` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`),
  FOREIGN KEY (`journal_category_id`) REFERENCES `journal_categories`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ==========================================
-- 12. rider_finances
-- ==========================================
CREATE TABLE IF NOT EXISTS `rider_finances` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `branch_id` BIGINT UNSIGNED NULL,
  `rider_id` BIGINT UNSIGNED NOT NULL,
  `admin_id` BIGINT UNSIGNED NOT NULL,
  `date` DATE NOT NULL,
  `type` ENUM('kasbon','uang_makan') NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `reference_cups` INT NULL,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  FOREIGN KEY (`rider_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`admin_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ==========================================
-- 13. payroll_records (HARUS sebelum rider_daily_sales karena FK)
-- ==========================================
CREATE TABLE IF NOT EXISTS `payroll_records` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `branch_id` BIGINT UNSIGNED NULL,
  `rider_id` BIGINT UNSIGNED NOT NULL,
  `admin_id` BIGINT UNSIGNED NOT NULL,
  `type` ENUM('weekly','monthly','custom') NOT NULL,
  `period_start` DATE NOT NULL,
  `period_end` DATE NOT NULL,
  `total_cups` INT NOT NULL DEFAULT 0,
  `gross_income` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `kasbon_outstanding` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `kasbon_deducted` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `kasbon_paid_cash` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `minus_outstanding` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `minus_deducted` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `minus_paid_cash` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `uang_makan_adjustment` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `net_income` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `status` ENUM('draft','confirmed') NOT NULL DEFAULT 'draft',
  `confirmed_at` TIMESTAMP NULL,
  `confirmed_by` BIGINT UNSIGNED NULL,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  FOREIGN KEY (`rider_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`admin_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`confirmed_by`) REFERENCES `users`(`id`),
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ==========================================
-- 14. rider_daily_sales
-- ==========================================
CREATE TABLE IF NOT EXISTS `rider_daily_sales` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `branch_id` BIGINT UNSIGNED NULL,
  `rider_id` BIGINT UNSIGNED NOT NULL,
  `date` DATE NOT NULL,
  `cash_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `actual_setor` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `minus_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `minus_paid` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `minus_status` ENUM('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
  `minus_source` ENUM('penjualan','carry_over') NOT NULL DEFAULT 'penjualan',
  `minus_notes` TEXT NULL,
  `carry_over_from_payroll_id` BIGINT UNSIGNED NULL,
  `qris_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `total_setoran` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `total_gross_income` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `admin_pemeriksa` VARCHAR(255) NULL,
  `admin_id` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  UNIQUE KEY `rider_daily_sales_rider_date_unique` (`rider_id`, `date`),
  FOREIGN KEY (`rider_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`admin_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`carry_over_from_payroll_id`) REFERENCES `payroll_records`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ==========================================
-- 15. rider_daily_sale_items
-- ==========================================
CREATE TABLE IF NOT EXISTS `rider_daily_sale_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `branch_id` BIGINT UNSIGNED NULL,
  `rider_daily_sale_id` BIGINT UNSIGNED NOT NULL,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `stock_out` INT NOT NULL DEFAULT 0,
  `stock_added` INT NOT NULL DEFAULT 0,
  `stock_return` INT NOT NULL DEFAULT 0,
  `stock_sold` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  FOREIGN KEY (`rider_daily_sale_id`) REFERENCES `rider_daily_sales`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`),
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ==========================================
-- 16. app_settings
-- ==========================================
CREATE TABLE IF NOT EXISTS `app_settings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `branch_id` BIGINT UNSIGNED NULL,
  `key` VARCHAR(255) NOT NULL,
  `value` TEXT NULL,
  `label` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  UNIQUE KEY `app_settings_branch_key_unique` (`branch_id`, `key`),
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO `app_settings` (`key`, `value`, `label`, `created_at`, `updated_at`) VALUES
('uang_makan_target_cup', '1040', 'Target Cup Uang Makan (per bulan)', NOW(), NOW()),
('uang_makan_base_amount', '650000', 'Base Uang Makan (Rp)', NOW(), NOW()),
('bonus_per_cup', '2000', 'Bonus per Cup (Rp)', NOW(), NOW())
ON DUPLICATE KEY UPDATE `value` = `value`;

-- ==========================================
-- 17. conversations
-- ==========================================
CREATE TABLE IF NOT EXISTS `conversations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `rider_id` BIGINT UNSIGNED NOT NULL,
  `cart_id` BIGINT UNSIGNED NULL,
  `status` ENUM('active','closed') NOT NULL DEFAULT 'active',
  `last_message_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  UNIQUE KEY `conversations_customer_rider_cart_unique` (`customer_id`, `rider_id`, `cart_id`),
  FOREIGN KEY (`customer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`rider_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`cart_id`) REFERENCES `carts`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ==========================================
-- 18. messages
-- ==========================================
CREATE TABLE IF NOT EXISTS `messages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `conversation_id` BIGINT UNSIGNED NOT NULL,
  `sender_id` BIGINT UNSIGNED NOT NULL,
  `body` TEXT NOT NULL,
  `attachment_path` VARCHAR(255) NULL,
  `attachment_type` VARCHAR(255) NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  INDEX `messages_conversation_created_index` (`conversation_id`, `created_at`),
  FOREIGN KEY (`conversation_id`) REFERENCES `conversations`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ==========================================
-- 19. artikels
-- ==========================================
CREATE TABLE IF NOT EXISTS `artikels` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL UNIQUE,
  `excerpt` TEXT NOT NULL,
  `content` LONGTEXT NOT NULL,
  `category` VARCHAR(255) NOT NULL DEFAULT 'Cerita',
  `cover_image` VARCHAR(255) NULL,
  `is_published` TINYINT(1) NOT NULL DEFAULT 1,
  `published_at` TIMESTAMP NULL,
  `read_time` INT UNSIGNED NOT NULL DEFAULT 5,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ==========================================
-- 20. rider_sales_journal_confirmations
-- ==========================================
CREATE TABLE IF NOT EXISTS `rider_sales_journal_confirmations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `date` DATE NOT NULL,
  `branch_id` BIGINT UNSIGNED NOT NULL,
  `total_cash` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `total_qris` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `total_minus` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `total_omset` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `rider_count` INT NOT NULL DEFAULT 0,
  `confirmed_by` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  UNIQUE KEY `rsjc_date_branch_unique` (`date`, `branch_id`),
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`confirmed_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ==========================================
-- 21. office_kasbons
-- ==========================================
CREATE TABLE IF NOT EXISTS `office_kasbons` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `branch_id` BIGINT UNSIGNED NOT NULL,
  `admin_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `date` DATE NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `paid_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `status` ENUM('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
  `notes` TEXT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`admin_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB;

-- ==========================================
-- 22. office_kasbon_payments
-- ==========================================
CREATE TABLE IF NOT EXISTS `office_kasbon_payments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `office_kasbon_id` BIGINT UNSIGNED NOT NULL,
  `admin_id` BIGINT UNSIGNED NOT NULL,
  `date` DATE NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  FOREIGN KEY (`office_kasbon_id`) REFERENCES `office_kasbons`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`admin_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB;

-- ==========================================
-- 23. daily_productions
-- ==========================================
CREATE TABLE IF NOT EXISTS `daily_productions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `date` DATE NOT NULL,
  `branch_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ==========================================
-- 24. daily_production_items
-- ==========================================
CREATE TABLE IF NOT EXISTS `daily_production_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `daily_production_id` BIGINT UNSIGNED NOT NULL,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `quantity_produced` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  FOREIGN KEY (`daily_production_id`) REFERENCES `daily_productions`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ==========================================
-- 25. spoiled_products
-- ==========================================
CREATE TABLE IF NOT EXISTS `spoiled_products` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `date` DATE NOT NULL,
  `branch_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ==========================================
-- 26. spoiled_product_items
-- ==========================================
CREATE TABLE IF NOT EXISTS `spoiled_product_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `spoiled_product_id` BIGINT UNSIGNED NOT NULL,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `quantity` INT NOT NULL DEFAULT 0,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  FOREIGN KEY (`spoiled_product_id`) REFERENCES `spoiled_products`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;
