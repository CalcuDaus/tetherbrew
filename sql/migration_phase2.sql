-- =====================================================
-- MIGRATION TAHAP 2: IDEMPOTENCY KEY & INFO KEMBALIAN POS
-- Aman untuk MySQL 8.x (Lokal) & MariaDB 11.x (Production)
-- Seluruh kolom baru bersifat NULLABLE (100% kompatibel data lama)
-- =====================================================

ALTER TABLE `transactions`
    ADD COLUMN `client_tx_id` VARCHAR(64) NULL DEFAULT NULL AFTER `notes`,
    ADD COLUMN `cash_received` DECIMAL(12,2) NULL DEFAULT NULL AFTER `client_tx_id`,
    ADD COLUMN `change_amount` DECIMAL(12,2) NULL DEFAULT NULL AFTER `cash_received`,
    ADD UNIQUE KEY `transactions_client_tx_id_unique` (`client_tx_id`);

