-- lernginx Database Schema
-- Database: lernginx
-- Charset: utf8mb4

CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('student', 'teacher', 'admin') NOT NULL DEFAULT 'student',
    `display_name` VARCHAR(100) DEFAULT NULL,
    `foto_profil` VARCHAR(512) DEFAULT NULL,
    `nomor_telpon` VARCHAR(20) DEFAULT NULL,
    `alamat_rumah` TEXT DEFAULT NULL,
    `tempat_lahir` VARCHAR(100) DEFAULT NULL,
    `tanggal_lahir` DATE DEFAULT NULL,
    `asal_sekolah` VARCHAR(255) DEFAULT NULL,
    `tahun_masuk` VARCHAR(10) DEFAULT NULL,
    `tingkat_kelas` VARCHAR(50) DEFAULT NULL,
    `jurusan` VARCHAR(100) DEFAULT NULL,
    `nisn` VARCHAR(20) DEFAULT NULL,
    `must_change_password` TINYINT(1) DEFAULT 0,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `deleted_by` INT UNSIGNED DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_users_email` (`email`),
    INDEX `idx_users_role` (`role`),
    INDEX `idx_users_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sessions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `session_token` VARCHAR(64) NOT NULL UNIQUE,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` TEXT DEFAULT NULL,
    `expires_at` DATETIME NOT NULL,
    `last_activity` DATETIME DEFAULT NULL,
    `is_valid` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_sessions_token` (`session_token`),
    INDEX `idx_sessions_user` (`user_id`),
    INDEX `idx_sessions_expires` (`expires_at`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `parent_id` INT UNSIGNED DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `info` TEXT DEFAULT NULL,
    `show_posts` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_categories_slug` (`slug`),
    INDEX `idx_categories_parent` (`parent_id`),
    FOREIGN KEY (`parent_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `categories_closure` (
    `ancestor_id` INT UNSIGNED NOT NULL,
    `descendant_id` INT UNSIGNED NOT NULL,
    `depth` INT DEFAULT 0,
    PRIMARY KEY (`ancestor_id`, `descendant_id`),
    INDEX `idx_closure_descendant` (`descendant_id`),
    FOREIGN KEY (`ancestor_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`descendant_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `posts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) NOT NULL UNIQUE,
    `content` LONGTEXT NOT NULL,
    `excerpt` TEXT DEFAULT NULL,
    `thumbnail` VARCHAR(255) DEFAULT NULL,
    `featured_image` VARCHAR(255) DEFAULT NULL,
    `youtube_url` VARCHAR(255) DEFAULT NULL,
    `category_id` INT UNSIGNED DEFAULT NULL,
    `author_id` INT UNSIGNED DEFAULT NULL,
    `status` ENUM('published', 'draft', 'private') DEFAULT 'draft',
    `is_auto_slug` TINYINT(1) DEFAULT 1,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_posts_slug` (`slug`),
    INDEX `idx_posts_category` (`category_id`),
    INDEX `idx_posts_author` (`author_id`),
    INDEX `idx_posts_status` (`status`),
    INDEX `idx_posts_deleted` (`is_deleted`),
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`author_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pages` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) NOT NULL UNIQUE,
    `content` LONGTEXT NOT NULL,
    `excerpt` TEXT DEFAULT NULL,
    `thumbnail` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('draft', 'published', 'private') DEFAULT 'draft',
    `created_by` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    `is_deleted` TINYINT(1) DEFAULT 0,
    INDEX `idx_pages_slug` (`slug`),
    INDEX `idx_pages_status` (`status`),
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tags` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `description` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_tags_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `page_tag` (
    `page_id` INT UNSIGNED NOT NULL,
    `tag_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`page_id`, `tag_id`),
    FOREIGN KEY (`page_id`) REFERENCES `pages`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`tag_id`) REFERENCES `tags`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `modules` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `category_id` INT UNSIGNED NOT NULL,
    `status` TINYINT(1) DEFAULT 0 COMMENT '0=active, 1=pending, 2=cancelled',
    `is_reviewed` TINYINT(1) DEFAULT 0,
    `reviewed_by` INT UNSIGNED DEFAULT NULL,
    `reviewed_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_modules_user` (`user_id`),
    INDEX `idx_modules_category` (`category_id`),
    INDEX `idx_modules_status` (`status`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `media` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `url` VARCHAR(512) NOT NULL UNIQUE,
    `path` VARCHAR(1024) NOT NULL,
    `filename` VARCHAR(255) NOT NULL,
    `ext` VARCHAR(10) DEFAULT NULL,
    `mime` VARCHAR(64) DEFAULT NULL,
    `size` INT DEFAULT 0,
    `width` INT DEFAULT NULL,
    `height` INT DEFAULT NULL,
    `uploaded_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `uploaded_by` INT UNSIGNED DEFAULT NULL,
    `note` VARCHAR(255) DEFAULT NULL,
    INDEX `idx_media_uploaded` (`uploaded_at`),
    FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `registration_policies` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key_name` VARCHAR(100) NOT NULL UNIQUE,
    `value` TEXT DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    `updated_by` INT UNSIGNED DEFAULT NULL,
    FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `menu` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `label` VARCHAR(100) NOT NULL,
    `link` VARCHAR(255) DEFAULT NULL,
    `parent_id` INT UNSIGNED DEFAULT NULL,
    `sort_order` INT DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_menu_parent` (`parent_id`),
    FOREIGN KEY (`parent_id`) REFERENCES `menu`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_resets` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `token` VARCHAR(64) NOT NULL UNIQUE,
    `expires_at` DATETIME NOT NULL,
    `used_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_resets_token` (`token`),
    INDEX `idx_resets_user` (`user_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
