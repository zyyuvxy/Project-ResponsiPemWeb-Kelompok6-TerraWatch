-- ============================================================
-- TerraWatch Environmental Reporting Platform
-- Database Schema & Seed Data (SDG 15 - Life on Land)
-- ============================================================

CREATE DATABASE IF NOT EXISTS `terrawatch_db`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_general_ci;

USE `terrawatch_db`;

-- ------------------------------------------------------------
-- Drop tables in reverse foreign key order
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `activity_logs`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `report_updates`;
DROP TABLE IF EXISTS `report_images`;
DROP TABLE IF EXISTS `reports`;
DROP TABLE IF EXISTS `articles`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `users`;

-- ------------------------------------------------------------
-- 1. users
-- ------------------------------------------------------------
CREATE TABLE `users` (
  `user_id`       INT                  NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(100)         NOT NULL,
  `email`         VARCHAR(150)         NOT NULL,
  `password`      VARCHAR(255)         NOT NULL,
  `role`          ENUM('user','admin') NOT NULL DEFAULT 'user',
  `is_active`     TINYINT(1)           NOT NULL DEFAULT 1,
  `profile_photo` VARCHAR(255)         NULL DEFAULT NULL,
  `phone`         VARCHAR(20)          NULL DEFAULT NULL,
  `address`       TEXT                 NULL,
  `created_at`    TIMESTAMP            NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE INDEX `idx_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- 2. categories
-- ------------------------------------------------------------
CREATE TABLE `categories` (
  `category_id`   INT          NOT NULL AUTO_INCREMENT,
  `category_name` VARCHAR(100) NOT NULL,
  `description`   TEXT         NULL,
  `icon`          VARCHAR(50)  NULL DEFAULT NULL,
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- 3. articles
-- ------------------------------------------------------------
CREATE TABLE `articles` (
  `article_id` INT                       NOT NULL AUTO_INCREMENT,
  `admin_id`   INT                       NOT NULL,
  `title`      VARCHAR(255)              NOT NULL,
  `content`    TEXT                      NOT NULL,
  `image`      VARCHAR(255)              NULL DEFAULT NULL,
  `status`     ENUM('Draft','Published') NOT NULL DEFAULT 'Draft',
  `created_at` TIMESTAMP                 NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP                 NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`article_id`),
  INDEX `idx_articles_created_at` (`created_at`),
  CONSTRAINT `fk_articles_admin` FOREIGN KEY (`admin_id`) REFERENCES `users`(`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- 4. reports
-- ------------------------------------------------------------
CREATE TABLE `reports` (
  `report_id`        INT           NOT NULL AUTO_INCREMENT,
  `user_id`          INT           NOT NULL,
  `category_id`      INT           NOT NULL,
  `title`            VARCHAR(255)  NOT NULL,
  `incident_date`    DATE          NOT NULL,
  `location`         VARCHAR(500)  NOT NULL,
  `latitude`         DECIMAL(10,8) NULL DEFAULT NULL,
  `longitude`        DECIMAL(11,8) NULL DEFAULT NULL,
  `description`      TEXT          NOT NULL,
  `status`           ENUM('Pending','Revision','Verified','In Progress','Completed','Rejected') NOT NULL DEFAULT 'Pending',
  `rejection_reason` TEXT          NULL,
  `created_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`report_id`),
  INDEX `idx_reports_status` (`status`),
  INDEX `idx_reports_created_at` (`created_at`),
  INDEX `idx_reports_user_id` (`user_id`),
  INDEX `idx_reports_category_id` (`category_id`),
  CONSTRAINT `fk_reports_user`     FOREIGN KEY (`user_id`)     REFERENCES `users`(`user_id`),
  CONSTRAINT `fk_reports_category` FOREIGN KEY (`category_id`) REFERENCES `categories`(`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- 5. report_images
-- ------------------------------------------------------------
CREATE TABLE `report_images` (
  `image_id`    INT          NOT NULL AUTO_INCREMENT,
  `report_id`   INT          NOT NULL,
  `image_path`  VARCHAR(255) NOT NULL,
  `uploaded_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`image_id`),
  INDEX `idx_report_images_report_id` (`report_id`),
  CONSTRAINT `fk_report_images_report` FOREIGN KEY (`report_id`) REFERENCES `reports`(`report_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- 6. report_updates
-- ------------------------------------------------------------
CREATE TABLE `report_updates` (
  `update_id`  INT         NOT NULL AUTO_INCREMENT,
  `report_id`  INT         NOT NULL,
  `changed_by` INT         NULL DEFAULT NULL,
  `old_status` VARCHAR(50) NULL DEFAULT NULL,
  `new_status` VARCHAR(50) NOT NULL,
  `note`       TEXT        NULL,
  `created_at` TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`update_id`),
  INDEX `idx_report_updates_report_id` (`report_id`),
  CONSTRAINT `fk_report_updates_report`     FOREIGN KEY (`report_id`)  REFERENCES `reports`(`report_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_report_updates_changed_by` FOREIGN KEY (`changed_by`) REFERENCES `users`(`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- 7. notifications
-- ------------------------------------------------------------
CREATE TABLE `notifications` (
  `notification_id` INT       NOT NULL AUTO_INCREMENT,
  `user_id`         INT       NOT NULL,
  `report_id`       INT       NULL DEFAULT NULL,
  `message`         TEXT      NOT NULL,
  `is_read`         TINYINT   NOT NULL DEFAULT 0,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`notification_id`),
  INDEX `idx_notifications_user_id` (`user_id`),
  INDEX `idx_notifications_created_at` (`created_at`),
  CONSTRAINT `fk_notifications_user`   FOREIGN KEY (`user_id`)   REFERENCES `users`(`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notifications_report` FOREIGN KEY (`report_id`) REFERENCES `reports`(`report_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- 8. activity_logs
-- ------------------------------------------------------------
CREATE TABLE `activity_logs` (
  `log_id`     INT         NOT NULL AUTO_INCREMENT,
  `user_id`    INT         NULL DEFAULT NULL,
  `activity`   TEXT        NOT NULL,
  `ip_address` VARCHAR(45) NULL DEFAULT NULL,
  `created_at` TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  INDEX `idx_activity_logs_user_id` (`user_id`),
  INDEX `idx_activity_logs_created_at` (`created_at`),
  CONSTRAINT `fk_activity_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ============================================================
-- SEED DATA
-- ============================================================

-- Users (admin@terrawatch.id / admin123; budi@terrawatch.id / user123)
INSERT INTO `users` (`user_id`, `name`, `email`, `password`, `role`, `is_active`, `phone`, `address`, `created_at`) VALUES
(1, 'Administrator', 'admin@terrawatch.id', '$2y$10$7hGx1CvWRpFmkoi.VY00Ge89QSZ8Bua2qnO4yci6wnLT/r2hBZouK', 'admin', 1, '081234567890', 'Gedung Pusat Konservasi Terestrial, Jakarta', '2024-01-01 08:00:00'),
(2, 'Budi Wicaksono', 'budi@terrawatch.id', '$2y$10$LvYyyqhMUPB8i7w36Nny5eifkzZbD2uo0IBpX3bxcUl0kluIGNxDW', 'user', 1, '082345678901', 'Jl. Merdeka No. 45, Bandung', '2024-01-15 08:30:00');

-- Categories (SDG 15 Relevant)
INSERT INTO `categories` (`category_id`, `category_name`, `description`, `icon`, `created_at`) VALUES
(1, 'Penebangan Liar',       'Aktivitas penebangan kayu ilegal atau perusakan kawasan tegakan hutan.',           'fas fa-tree',          '2024-01-01 00:00:00'),
(2, 'Kebakaran Hutan/Lahan', 'Insiden kebakaran vegetasi hutan dan lahan gambut yang mengancam ekosistem.',    'fas fa-fire',          '2024-01-01 00:00:00'),
(3, 'Pembuangan Sampah Ilegal', 'Pembuangan limbah padat dan sampah liar di kawasan konservasi darat.',          'fas fa-trash',         '2024-01-01 00:00:00'),
(4, 'Kerusakan Habitat Satwa',  'Gangguan habitat flora/fauna endemik terancam punah dan jerat satwa.',          'fas fa-paw',           '2024-01-01 00:00:00'),
(5, 'Degradasi Lahan',       'Erosi tanah, pembukaan lereng curam, dan penurunan fungsi daya dukung tanah.',     'fas fa-mountain',      '2024-01-01 00:00:00'),
(6, 'Pencemaran Tanah',      'Kontaminasi zat kimia berbahaya atau limbah cair industri pada tanah darat.',     'fas fa-exclamation-triangle', '2024-01-01 00:00:00');

-- Reports
INSERT INTO `reports` (`report_id`, `user_id`, `category_id`, `title`, `incident_date`, `location`, `latitude`, `longitude`, `description`, `status`, `rejection_reason`, `created_at`, `updated_at`) VALUES
(1, 2, 1, 'Penebangan Liar di Blok Hutan Lindung Gunung Manglayang', '2024-03-10', 'Hutan Lindung Gunung Manglayang, Kab. Bandung, Jawa Barat', -6.85230000, 107.72340000,
 'Ditemukan bekas pemotongan pohon kayu keras di zona lindung lereng timur Manglayang seluas kurleb 0.5 hektar. Bekas serutan gergaji mesin dan jejak ban truk pengangkut kayu terlihat jelas.',
 'Verified', NULL, '2024-03-11 09:15:00', '2024-03-15 14:30:00'),

(2, 2, 6, 'Pencemaran Limbah Cair Kimia di Bantaran Sungai Citarum', '2024-04-05', 'Bantaran Sungai Citarum Sektor 7, Kec. Majalaya, Kab. Bandung', -7.04560000, 107.75890000,
 'Saluran pembuangan pipa tak berizin mengalirkan residu pekat ke tanah bantaran sungai hingga vegetasi rumput mengering dan menimbulkan bau belerang yang menyengat.',
 'In Progress', NULL, '2024-04-06 10:20:00', '2024-04-10 11:00:00'),

(3, 2, 2, 'Kebakaran Lahan Terbuka Bekas Semak Belukar di Subang', '2024-05-20', 'Kec. Compreng, Kab. Subang, Jawa Barat', -6.55000000, 107.75000000,
 'Kebakaran semak belukar kering seluas kurang lebih 2 hektar di perbatasan perkebunan dan permukiman warga. Titik api berhasil dipadamkan bersama warga dan relawan setempat.',
 'Completed', NULL, '2024-05-21 07:45:00', '2024-05-25 16:00:00'),

(4, 2, 3, 'Tumpukan Sampah Plastik Liar di Tepi Kawasan Cagar Alam', '2024-06-01', 'Jalur Lingkar Luar Hutan Lindung Kamojang, Kab. Garut', -7.14000000, 107.79000000,
 'Tumpukan kantong sampah plastik dan limbah rumah tangga dibuang di tepi jalan perbatasan cagar alam dan mencemari badan lereng tanah.',
 'Pending', NULL, '2024-06-02 08:30:00', '2024-06-02 08:30:00'),

(5, 2, 4, 'Temuan Jerat Kawat Satwa Liar di Jalur Pendakian', '2024-06-10', 'Lereng Selatan Taman Nasional Gunung Gede Pangrango', -6.78000000, 106.98000000,
 'Ditemukan dua utas jerat kawat baja di dahan rendah jalur satwa mamalia. Tidak ada hewan yang terjerat saat ditemukan.',
 'Revision', 'Mohon perjelas patokan pos terdekat dan sertakan foto koordinat GPS yang lebih jelas.', '2024-06-11 11:00:00', '2024-06-12 13:00:00'),

(6, 2, 5, 'Klaim Pengikisan Tebing di Tanah Milik Pribadi Berizin', '2024-06-15', 'Desa Sukamaju, Kab. Sumedang', -6.86000000, 107.92000000,
 'Diduga terjadi pemotongan tebing tanpa izin di perbatasan kebun.',
 'Rejected', 'Lokasi setelah diverifikasi berada di tanah milik pribadi dengan izin perataan tanah yang sah dari instansi setempat.', '2024-06-16 09:00:00', '2024-06-16 15:30:00');

-- Report Images (Semua diawali folder assets/img/seed/...)
INSERT INTO `report_images` (`image_id`, `report_id`, `image_path`, `uploaded_at`) VALUES
(1, 1, 'assets/img/seed/report_1_img1.jpg', '2024-03-11 09:15:00'),
(2, 1, 'assets/img/seed/report_1_img2.jpg', '2024-03-11 09:15:00'),
(3, 2, 'assets/img/seed/report_2_img1.jpg', '2024-04-06 10:20:00'),
(4, 3, 'assets/img/seed/report_3_img1.jpg', '2024-05-21 07:45:00'),
(5, 3, 'assets/img/seed/report_3_img2.jpg', '2024-05-21 07:45:00'),
(6, 4, 'assets/img/seed/report_4_img1.jpg', '2024-06-02 08:30:00'),
(7, 5, 'assets/img/seed/report_5_img1.jpg', '2024-06-11 11:00:00'),
(8, 6, 'assets/img/seed/report_6_img1.jpg', '2024-06-16 09:00:00');

-- Report Updates (Status History Lengkap Berurutan)
INSERT INTO `report_updates` (`update_id`, `report_id`, `changed_by`, `old_status`, `new_status`, `note`, `created_at`) VALUES
-- Report 1 (Pending -> Verified)
(1, 1, 2, NULL,       'Pending',     'Laporan dikirim',                                                                        '2024-03-11 09:15:00'),
(2, 1, 1, 'Pending',  'Verified',    'Laporan diverifikasi. Bukti foto kerusakan tegakan pohon valid dan lokasi terkonfirmasi.','2024-03-15 14:30:00'),

-- Report 2 (Pending -> Verified -> In Progress)
(3, 2, 2, NULL,       'Pending',     'Laporan dikirim',                                                                        '2024-04-06 10:20:00'),
(4, 2, 1, 'Pending',  'Verified',    'Laporan diverifikasi. Tingkat bahaya kontaminasi tanah terestrial tercatat tinggi.',     '2024-04-08 09:00:00'),
(5, 2, 1, 'Verified', 'In Progress', 'Petugas verifikator mulai menindaklanjuti lokasi untuk sampling tanah tercemar.',         '2024-04-10 11:00:00'),

-- Report 3 (Pending -> Verified -> In Progress -> Completed)
(6, 3, 2, NULL,       'Pending',     'Laporan dikirim',                                                                        '2024-05-21 07:45:00'),
(7, 3, 1, 'Pending',  'Verified',    'Laporan diverifikasi.',                                                                  '2024-05-22 10:00:00'),
(8, 3, 1, 'Verified', 'In Progress', 'Pemadaman bara sisa dan pendinginan lahan gambut sedang dilakukan.',                     '2024-05-23 08:00:00'),
(9, 3, 1, 'In Progress', 'Completed', 'Area semak yang terbakar telah sepenuhnya padam dan direhabilitasi oleh tim lapangan.', '2024-05-25 16:00:00'),

-- Report 4 (Pending)
(10, 4, 2, NULL,      'Pending',     'Laporan dikirim',                                                                        '2024-06-02 08:30:00'),

-- Report 5 (Pending -> Revision)
(11, 5, 2, NULL,      'Pending',     'Laporan dikirim',                                                                        '2024-06-11 11:00:00'),
(12, 5, 1, 'Pending', 'Revision',    'Mohon perjelas patokan pos terdekat dan sertakan foto koordinat GPS yang lebih jelas.',   '2024-06-12 13:00:00'),

-- Report 6 (Pending -> Rejected)
(13, 6, 2, NULL,      'Pending',     'Laporan dikirim',                                                                        '2024-06-16 09:00:00'),
(14, 6, 1, 'Pending', 'Rejected',    'Lokasi setelah diverifikasi berada di tanah milik pribadi dengan izin perataan yang sah.','2024-06-16 15:30:00');

-- Articles
INSERT INTO `articles` (`article_id`, `admin_id`, `title`, `content`, `image`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 'Pentingnya Menjaga Kelestarian Hutan untuk Generasi Mendatang',
 'Hutan merupakan paru-paru dunia yang memiliki peran vital dalam menjaga keseimbangan ekosistem terestrial. Kawasan hutan tropis menyimpan keanekaragaman hayati terkaya di bumi dan menjadi penyangga utama siklus air tanah serta pencegah bencana longsor.\n\nDalam rangka mendukung tercapainya Sustainable Development Goals (SDG 15 - Life on Land), setiap elemen masyarakat dapat turut serta menjaga kelestarian ekosistem darat dengan:\n1. Tidak melakukan penebangan liar pohon di kawasan hutan lindung.\n2. Mencegah praktik pembukaan lahan dengan cara membakar.\n3. Melaporkan setiap indikasi perusakan lingkungan melalui platform partisipatif TerraWatch.',
 'assets/img/seed/article_1.jpg', 'Published', '2024-02-15 10:00:00', '2024-02-15 10:00:00'),

(2, 1, 'Mengenal Erosi dan Upaya Konservasi Lahan Terestrial',
 'Degradasi lahan akibat erosi lereng dan hilangnya tutupan kanopi pohon mempercepat kehilangan lapisan tanah humus yang subur. Upaya terasering, penanaman vegetasi penahan akar, dan perlindungan DAS menjadi kunci pemulihan kualitas lahan darat.',
 'assets/img/seed/article_2.jpg', 'Draft', '2024-03-20 14:30:00', '2024-03-20 14:30:00');

-- Notifications
INSERT INTO `notifications` (`notification_id`, `user_id`, `report_id`, `message`, `is_read`, `created_at`) VALUES
(1, 2, 1, 'Laporan "Penebangan Liar di Blok Hutan Lindung Gunung Manglayang" kini berstatus Terverifikasi.', 1, '2024-03-15 14:30:00'),
(2, 2, 2, 'Laporan "Pencemaran Limbah Cair Kimia di Bantaran Sungai Citarum" kini berstatus Sedang Diproses.', 1, '2024-04-10 11:00:00'),
(3, 2, 3, 'Laporan "Kebakaran Lahan Terbuka Bekas Semak Belukar di Subang" kini berstatus Selesai. Catatan: Area semak yang terbakar telah sepenuhnya padam.', 0, '2024-05-25 16:00:00'),
(4, 2, 5, 'Laporan "Temuan Jerat Kawat Satwa Liar di Jalur Pendakian" kini berstatus Perlu Perbaikan. Catatan: Mohon perjelas patokan pos terdekat.', 0, '2024-06-12 13:00:00'),
(5, 1, 4, 'Laporan baru dari Budi Wicaksono: "Tumpukan Sampah Plastik Liar di Tepi Kawasan Cagar Alam".', 0, '2024-06-02 08:30:00');

-- Activity logs
INSERT INTO `activity_logs` (`log_id`, `user_id`, `activity`, `ip_address`, `created_at`) VALUES
(1, 1, 'Login ke sistem administrator', '127.0.0.1', '2024-03-15 14:00:00'),
(2, 1, 'Mengubah status laporan #1 menjadi Terverifikasi', '127.0.0.1', '2024-03-15 14:30:00'),
(3, 2, 'Login ke sistem pelapor warga', '127.0.0.1', '2024-04-06 10:00:00'),
(4, 2, 'Mengirim laporan kerusakan lingkungan: Pencemaran Limbah Cair Kimia di Bantaran Sungai Citarum', '127.0.0.1', '2024-04-06 10:20:00'),
(5, 1, 'Mengubah status laporan #2 menjadi Sedang Diproses', '127.0.0.1', '2024-04-10 11:00:00'),
(6, 2, 'Mengirim laporan kerusakan lingkungan: Kebakaran Lahan Terbuka Bekas Semak Belukar di Subang', '127.0.0.1', '2024-05-21 07:45:00'),
(7, 1, 'Mengubah status laporan #3 menjadi Selesai', '127.0.0.1', '2024-05-25 16:00:00');
