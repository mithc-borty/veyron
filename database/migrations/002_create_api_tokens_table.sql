-- ---------------------------------------------------------------------------
-- 002_create_api_tokens_table.sql
--
-- Project  : PHP REST API (native PHP + PDO)
-- Database : javascript
-- Server   : MySQL 8 / MariaDB 10+ , utf8mb4
--
-- Manual execution (run 001 first):
--   mysql -u root javascript < database/migrations/002_create_api_tokens_table.sql
--
-- Stores a SHA-256 hash of each bearer token - never the plaintext token.
-- Idempotent: the table is created only when it does not exist.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `api_tokens` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`      BIGINT UNSIGNED NOT NULL,
    `token_hash`   CHAR(64)        NOT NULL COMMENT 'sha256 hex of the plaintext token',
    `expires_at`   DATETIME        NOT NULL,
    `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_used_at` DATETIME        NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `api_tokens_token_hash_unique` (`token_hash`),
    KEY `api_tokens_user_id_index` (`user_id`),
    KEY `api_tokens_expires_at_index` (`expires_at`),
    CONSTRAINT `api_tokens_user_id_foreign`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;