-- ---------------------------------------------------------------------------
-- 001_create_users_table.sql
--
-- Project  : PHP REST API (native PHP + PDO)
-- Database : javascript
-- Server   : MySQL 8 / MariaDB 10+ , utf8mb4
--
-- Manual execution:
--   mysql -u root javascript < database/migrations/001_create_users_table.sql
--
-- This file is idempotent: the table is created only when it does not exist.
-- It never drops or alters an existing table and never touches existing data.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `users` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(100)    NOT NULL,
    `email`      VARCHAR(255)    NOT NULL COMMENT 'unique, stored lower case',
    `password`   VARCHAR(255)    NOT NULL COMMENT 'password_hash() output - never plaintext',
    `role`       ENUM('admin', 'user')      NOT NULL DEFAULT 'user',
    `status`     ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `users_email_unique` (`email`),
    KEY `users_role_status_index` (`role`, `status`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;