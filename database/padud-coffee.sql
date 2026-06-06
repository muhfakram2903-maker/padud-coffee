SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+07:00";

CREATE TABLE `users` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `username`   VARCHAR(50)  NOT NULL,
  `password`   VARCHAR(255) NOT NULL,
  `name`       VARCHAR(100) NOT NULL,
  `email`      VARCHAR(100) DEFAULT NULL,
  `phone`      VARCHAR(20)  DEFAULT NULL,
  `role`       ENUM('admin','kasir','member') NOT NULL DEFAULT 'member',
  `points`     INT(11)      NOT NULL DEFAULT 0,
  `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_username` (`username`),
  UNIQUE KEY `uq_email`    (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tables` (
  `id`           INT(11)      NOT NULL AUTO_INCREMENT,
  `table_number` VARCHAR(10)  NOT NULL,
  `qr_code_path` VARCHAR(255) DEFAULT NULL,
  `status`       ENUM('available','occupied') NOT NULL DEFAULT 'available',
  `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_table_number` (`table_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `qr_codes` (
  `id`           INT(11)      NOT NULL AUTO_INCREMENT,
  `table_id`     INT(11)      NOT NULL,
  `token`        VARCHAR(64)  NOT NULL,
  `file_path`    VARCHAR(255) NOT NULL,
  `generated_by` INT(11)      NOT NULL,
  `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_token` (`token`),
  KEY `fk_qr_table` (`table_id`),
  KEY `fk_qr_admin` (`generated_by`),
  CONSTRAINT `fk_qr_table` FOREIGN KEY (`table_id`)     REFERENCES `tables` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_qr_admin` FOREIGN KEY (`generated_by`) REFERENCES `users`  (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `categories` (
  `id`         INT(11)     NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(50) NOT NULL,
  `created_at` TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `menus` (
  `id`           INT(11)       NOT NULL AUTO_INCREMENT,
  `category_id`  INT(11)       NOT NULL,
  `name`         VARCHAR(100)  NOT NULL,
  `description`  TEXT          DEFAULT NULL,
  `price`        DECIMAL(10,2) NOT NULL,
  `image`        VARCHAR(255)  DEFAULT NULL,
  `is_available` TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_menu_category` (`category_id`),
  CONSTRAINT `fk_menu_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `menu_reviews` (
  `id`         INT(11)    NOT NULL AUTO_INCREMENT,
  `menu_id`    INT(11)    NOT NULL,
  `user_id`    INT(11)    NOT NULL,
  `rating`     TINYINT(1) NOT NULL CHECK (`rating` BETWEEN 1 AND 5),
  `comment`    TEXT       DEFAULT NULL,
  `created_at` TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_review_per_menu` (`menu_id`, `user_id`),
  KEY `fk_review_menu` (`menu_id`),
  KEY `fk_review_user` (`user_id`),
  CONSTRAINT `fk_review_menu` FOREIGN KEY (`menu_id`) REFERENCES `menus` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_review_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `vouchers` (
  `id`              INT(11)       NOT NULL AUTO_INCREMENT,
  `code`            VARCHAR(20)   NOT NULL,
  `name`            VARCHAR(100)  NOT NULL,
  `points_required` INT(11)       NOT NULL DEFAULT 0,
  `discount_amount` DECIMAL(10,2) NOT NULL,
  `discount_type`   ENUM('fixed','percent') NOT NULL DEFAULT 'fixed',
  `min_order`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `is_active`       TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_voucher_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_vouchers` (
  `id`         INT(11)    NOT NULL AUTO_INCREMENT,
  `user_id`    INT(11)    NOT NULL,
  `voucher_id` INT(11)    NOT NULL,
  `is_used`    TINYINT(1) NOT NULL DEFAULT 0,
  `used_at`    TIMESTAMP  NULL DEFAULT NULL,
  `created_at` TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_uv_user`    (`user_id`),
  KEY `fk_uv_voucher` (`voucher_id`),
  CONSTRAINT `fk_uv_user`    FOREIGN KEY (`user_id`)    REFERENCES `users`    (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_uv_voucher` FOREIGN KEY (`voucher_id`) REFERENCES `vouchers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `orders` (
  `id`              INT(11)       NOT NULL AUTO_INCREMENT,
  `order_number`    VARCHAR(20)   NOT NULL,
  `table_id`        INT(11)       DEFAULT NULL,
  `user_id`         INT(11)       DEFAULT NULL,
  `customer_name`   VARCHAR(100)  DEFAULT NULL,
  `user_voucher_id` INT(11)       DEFAULT NULL,
  `subtotal`        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_amount`    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `status`          ENUM('pending','processing','ready','completed','cancelled') NOT NULL DEFAULT 'pending',
  `notes`           TEXT          DEFAULT NULL,
  `created_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_order_number` (`order_number`),
  KEY `fk_order_table`   (`table_id`),
  KEY `fk_order_user`    (`user_id`),
  KEY `fk_order_voucher` (`user_voucher_id`),
  CONSTRAINT `fk_order_table`   FOREIGN KEY (`table_id`)        REFERENCES `tables`        (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_order_user`    FOREIGN KEY (`user_id`)         REFERENCES `users`         (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_order_voucher` FOREIGN KEY (`user_voucher_id`) REFERENCES `user_vouchers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `order_items` (
  `id`         INT(11)       NOT NULL AUTO_INCREMENT,
  `order_id`   INT(11)       NOT NULL,
  `menu_id`    INT(11)       NOT NULL,
  `quantity`   INT(11)       NOT NULL DEFAULT 1,
  `price`      DECIMAL(10,2) NOT NULL,
  `notes`      VARCHAR(255)  DEFAULT NULL,
  `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_item_order` (`order_id`),
  KEY `fk_item_menu`  (`menu_id`),
  CONSTRAINT `fk_item_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_item_menu`  FOREIGN KEY (`menu_id`)  REFERENCES `menus`  (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `transactions` (
  `id`                INT(11)       NOT NULL AUTO_INCREMENT,
  `order_id`          INT(11)       NOT NULL,
  `transaction_code`  VARCHAR(50)   NOT NULL,
  `payment_method`    VARCHAR(50)   NOT NULL,
  `payment_type`      ENUM('midtrans','manual') NOT NULL DEFAULT 'manual',
  `amount`            DECIMAL(10,2) NOT NULL,
  `payment_proof`     VARCHAR(255)  DEFAULT NULL,
  `midtrans_order_id` VARCHAR(100)  DEFAULT NULL,
  `midtrans_token`    TEXT          DEFAULT NULL,
  `midtrans_response` LONGTEXT      DEFAULT NULL,
  `status`            ENUM('pending','paid','failed','refunded','expired') NOT NULL DEFAULT 'pending',
  `processed_by`      INT(11)       DEFAULT NULL,
  `processed_at`      TIMESTAMP     NULL DEFAULT NULL,
  `created_at`        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_transaction_code`  (`transaction_code`),
  UNIQUE KEY `uq_midtrans_order_id` (`midtrans_order_id`),
  KEY `fk_trx_order` (`order_id`),
  KEY `fk_trx_kasir` (`processed_by`),
  CONSTRAINT `fk_trx_order` FOREIGN KEY (`order_id`)     REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_trx_kasir` FOREIGN KEY (`processed_by`) REFERENCES `users`  (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `payment_logs` (
  `id`               INT(11)      NOT NULL AUTO_INCREMENT,
  `transaction_id`   INT(11)      DEFAULT NULL,
  `midtrans_order_id` VARCHAR(100) DEFAULT NULL,
  `event_type`       VARCHAR(50)  DEFAULT NULL,
  `payload`          LONGTEXT     DEFAULT NULL,
  `signature_valid`  TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_plog_trx` (`transaction_id`),
  CONSTRAINT `fk_plog_trx` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notifications` (
  `id`          INT(11)      NOT NULL AUTO_INCREMENT,
  `user_id`     INT(11)      DEFAULT NULL,
  `target_role` ENUM('admin','kasir','member') DEFAULT NULL,
  `order_id`    INT(11)      DEFAULT NULL,
  `type`        VARCHAR(50)  NOT NULL DEFAULT 'info',
  `title`       VARCHAR(100) NOT NULL,
  `message`     TEXT         NOT NULL,
  `is_read`     TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_notif_user`  (`user_id`),
  KEY `fk_notif_order` (`order_id`),
  CONSTRAINT `fk_notif_user`  FOREIGN KEY (`user_id`)  REFERENCES `users`  (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notif_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `reward_history` (
  `id`          INT(11)      NOT NULL AUTO_INCREMENT,
  `user_id`     INT(11)      NOT NULL,
  `order_id`    INT(11)      DEFAULT NULL,
  `voucher_id`  INT(11)      DEFAULT NULL,
  `points`      INT(11)      NOT NULL,
  `type`        ENUM('earn','redeem') NOT NULL DEFAULT 'earn',
  `description` VARCHAR(255) NOT NULL,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_rh_user`    (`user_id`),
  KEY `fk_rh_order`   (`order_id`),
  KEY `fk_rh_voucher` (`voucher_id`),
  CONSTRAINT `fk_rh_user`    FOREIGN KEY (`user_id`)   REFERENCES `users`    (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rh_order`   FOREIGN KEY (`order_id`)  REFERENCES `orders`   (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_rh_voucher` FOREIGN KEY (`voucher_id`) REFERENCES `vouchers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `websocket_connections` (
  `id`           INT(11)      NOT NULL AUTO_INCREMENT,
  `conn_id`      VARCHAR(64)  NOT NULL,
  `user_id`      INT(11)      DEFAULT NULL,
  `role`         VARCHAR(20)  DEFAULT NULL,
  `session_key`  VARCHAR(128) DEFAULT NULL,
  `connected_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_ping`    TIMESTAMP    NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_conn_id` (`conn_id`),
  KEY `fk_wsc_user` (`user_id`),
  CONSTRAINT `fk_wsc_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── SEED DATA (Kategori, Menu, Meja) ───────────────────────────────────

-- Insert Categories
INSERT INTO `categories` (`id`, `name`) VALUES
(1, 'Signature Coffee'),
(2, 'Classic Espresso'),
(3, 'Non-Coffee'),
(4, 'Mocktails'),
(5, 'Pastry & Snacks');

-- Insert Menus
INSERT INTO `menus` (`id`, `category_id`, `name`, `description`, `price`, `image`, `is_available`) VALUES
(1, 1, 'Padud Aren Latte', 'Kopi susu gula aren khas Padud dengan double espresso.', 25000.00, 'aren-latte.jpg', 1),
(2, 1, 'Creamy Caramel Macchiato', 'Paduan espresso, susu krim, dan saus karamel lezat.', 28000.00, 'caramel-macchiato.jpg', 1),
(3, 2, 'Caffe Americano', 'Espresso double shot dengan air murni, panas atau dingin.', 18000.00, 'americano.jpg', 1),
(4, 2, 'Cafe Latte', 'Espresso dengan steamed milk yang lembut dan foamy.', 22000.00, 'cafe-latte.jpg', 1),
(5, 3, 'Matcha Latte', 'Serbuk matcha premium jepang dengan susu segar.', 25000.00, 'matcha-latte.jpg', 1),
(6, 3, 'Red Velvet Latte', 'Minuman red velvet manis dan creamy.', 25000.00, 'red-velvet.jpg', 1),
(7, 4, 'Sunset Mocktail', 'Campuran sirup peach, jeruk nipis, dan soda.', 24000.00, 'sunset-mocktail.jpg', 1),
(8, 5, 'Butter Croissant', 'Croissant mentega klasik yang renyah di luar lembut di dalam.', 18000.00, 'croissant.jpg', 1),
(9, 5, 'French Fries', 'Kentang goreng renyah dengan taburan bumbu gurih.', 20000.00, 'french-fries.jpg', 1),
(10, 5, 'Mix Platter', 'Sosis, nugget, dan kentang goreng cocok untuk sharing.', 35000.00, 'mix-platter.jpg', 1);

-- Insert Tables
INSERT INTO `tables` (`id`, `table_number`, `status`) VALUES
(1, '01', 'available'),
(2, '02', 'available'),
(3, '03', 'available'),
(4, '04', 'available'),
(5, '05', 'available'),
(6, 'VIP-A', 'available'),
(7, 'VIP-B', 'available');

COMMIT;
