-- =====================================================
-- MIGRATION TAHAP 1: DEDUPLIKASI LOKASI & COMPOSITE INDEXES
-- Aman dijalankan di MySQL 8.x (Lokal) maupun MariaDB 11.x (Production)
-- =====================================================

-- 1. Bersihkan duplikat cart_id di cart_locations (simpan baris terbaru per cart_id)
DELETE cl1 FROM cart_locations cl1
INNER JOIN cart_locations cl2
    ON cl1.cart_id = cl2.cart_id AND cl1.id < cl2.id;

-- 2. Tambahkan UNIQUE KEY pada cart_locations(cart_id) agar ON DUPLICATE KEY UPDATE berfungsi
ALTER TABLE `cart_locations`
    ADD UNIQUE KEY `cart_locations_cart_id_unique` (`cart_id`);

-- 3. Composite Indexes untuk query laporan, dashboard, jurnal, dan transaksi
ALTER TABLE `rider_daily_sales`
    ADD INDEX `idx_rds_branch_date` (`branch_id`, `date`);

ALTER TABLE `transactions`
    ADD INDEX `idx_tx_branch_created` (`branch_id`, `created_at`),
    ADD INDEX `idx_tx_user_created` (`user_id`, `created_at`),
    ADD INDEX `idx_tx_cart_created` (`cart_id`, `created_at`);

ALTER TABLE `journals`
    ADD INDEX `idx_journals_branch_type_date` (`branch_id`, `type`, `date`),
    ADD INDEX `idx_journals_branch_date` (`branch_id`, `date`);

ALTER TABLE `rider_finances`
    ADD INDEX `idx_rf_branch_type_date` (`branch_id`, `type`, `date`);

