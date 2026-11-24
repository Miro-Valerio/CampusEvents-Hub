-- CampusEvents Hub / SPC SQL schema for MySQL 8+ and phpMyAdmin
-- Database name is campusevents-hubspc; surround it with backticks in SQL.
-- Import this file OR run `php artisan migrate` (do not do both on the same fresh database).
CREATE DATABASE IF NOT EXISTS `campusevents-hubspc` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `campusevents-hubspc`;
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `departments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` varchar(255) NOT NULL,
  `code` varchar(20) NOT NULL,
  `description` text NULL,
  `email` varchar(255) NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  UNIQUE KEY `departments_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `department_id` bigint unsigned NULL DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `course` varchar(255) NULL,
  `role` varchar(24) NOT NULL DEFAULT 'student',
  `officer_permissions` json NULL,
  `preferences` json NULL,
  `photo_path` varchar(255) NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_role_index` (`role`),
  KEY `users_department_id_index` (`department_id`),
  CONSTRAINT `fk_users_department_id` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `email` varchar(255) NOT NULL PRIMARY KEY,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sessions` (
  `id` varchar(255) NOT NULL PRIMARY KEY,
  `user_id` bigint unsigned NULL DEFAULT NULL,
  `ip_address` varchar(45) NULL,
  `user_agent` text NULL,
  `payload` longtext NOT NULL,
  `last_activity` int NOT NULL,
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  UNIQUE KEY `pat_token_unique` (`token`),
  KEY `pat_tokenable_index` (`tokenable_type`,`tokenable_id`),
  KEY `pat_expires_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `department_id` bigint unsigned NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `venue` varchar(255) NOT NULL,
  `starts_at` datetime NOT NULL,
  `ends_at` datetime NOT NULL,
  `category` varchar(60) NOT NULL DEFAULT 'Campus',
  `seat_limit` int unsigned NOT NULL DEFAULT 50,
  `poster_path` varchar(255) NULL,
  `rules` text NULL,
  `status` varchar(20) NOT NULL DEFAULT 'published',
  `check_in_code` varchar(12) NULL,
  `views` bigint unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  KEY `events_department_id_index` (`department_id`),
  KEY `events_created_by_index` (`created_by`),
  KEY `events_starts_at_index` (`starts_at`),
  KEY `events_status_index` (`status`),
  CONSTRAINT `fk_events_department_id` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_events_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rsvps` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `event_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `seat_no` int unsigned NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `reminder_sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  UNIQUE KEY `rsvps_event_user_unique` (`event_id`,`user_id`),
  UNIQUE KEY `rsvps_event_seat_unique` (`event_id`,`seat_no`),
  KEY `rsvps_event_status_index` (`event_id`,`status`),
  KEY `rsvps_user_id_index` (`user_id`),
  CONSTRAINT `fk_rsvps_event_id` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rsvps_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bookmarks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `event_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  UNIQUE KEY `bookmarks_event_user_unique` (`event_id`,`user_id`),
  KEY `bookmarks_user_id_index` (`user_id`),
  CONSTRAINT `fk_bookmarks_event_id` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bookmarks_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `calendar_entries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `event_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  UNIQUE KEY `calendar_entries_event_user_unique` (`event_id`,`user_id`),
  KEY `calendar_entries_user_id_index` (`user_id`),
  CONSTRAINT `fk_calendar_entries_event_id` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_calendar_entries_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `event_check_ins` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `rsvp_id` bigint unsigned NOT NULL,
  `verified_by` bigint unsigned NULL DEFAULT NULL,
  `checked_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  UNIQUE KEY `event_check_ins_rsvp_unique` (`rsvp_id`),
  KEY `event_check_ins_verified_by_index` (`verified_by`),
  CONSTRAINT `fk_event_check_ins_rsvp_id` FOREIGN KEY (`rsvp_id`) REFERENCES `rsvps` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_event_check_ins_verified_by` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `announcements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `department_id` bigint unsigned NULL DEFAULT NULL,
  `created_by` bigint unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `target_course` varchar(255) NULL,
  `is_pinned` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  KEY `announcements_department_id_index` (`department_id`),
  KEY `announcements_created_by_index` (`created_by`),
  CONSTRAINT `fk_announcements_department_id` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_announcements_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `announcement_reads` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `announcement_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `read_at` timestamp NOT NULL,
  UNIQUE KEY `announcement_reads_unique` (`announcement_id`,`user_id`),
  KEY `announcement_reads_user_id_index` (`user_id`),
  CONSTRAINT `fk_announcement_reads_announcement_id` FOREIGN KEY (`announcement_id`) REFERENCES `announcements` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_announcement_reads_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `announcement_reactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `announcement_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `emoji` varchar(10) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  UNIQUE KEY `announcement_reactions_unique` (`announcement_id`,`user_id`),
  KEY `announcement_reactions_user_id_index` (`user_id`),
  CONSTRAINT `fk_announcement_reactions_announcement_id` FOREIGN KEY (`announcement_id`) REFERENCES `announcements` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_announcement_reactions_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_notifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id` bigint unsigned NOT NULL,
  `dedupe_key` varchar(191) NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(255) NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  KEY `user_notifications_user_unread_index` (`user_id`,`read_at`),
  UNIQUE KEY `user_notifications_user_dedupe_unique` (`user_id`,`dedupe_key`),
  CONSTRAINT `fk_user_notifications_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `memberships` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `department_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'pending',
  `reviewed_by` bigint unsigned NULL DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  UNIQUE KEY `memberships_dept_user_unique` (`department_id`,`user_id`),
  KEY `memberships_user_id_index` (`user_id`),
  KEY `memberships_reviewed_by_index` (`reviewed_by`),
  CONSTRAINT `fk_memberships_department_id` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_memberships_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_memberships_reviewed_by` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `event_reports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `event_id` bigint unsigned NOT NULL,
  `category` varchar(40) NOT NULL DEFAULT 'other',
  `user_id` bigint unsigned NOT NULL,
  `reason` text NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `resolution` text NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  KEY `event_reports_event_id_index` (`event_id`),
  KEY `event_reports_user_id_index` (`user_id`),
  CONSTRAINT `fk_event_reports_event_id` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_event_reports_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `actor_id` bigint unsigned NULL DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `subject_type` varchar(80) NULL,
  `subject_id` bigint unsigned NULL,
  `details` json NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  KEY `audit_logs_actor_id_index` (`actor_id`),
  CONSTRAINT `fk_audit_logs_actor_id` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `system_settings` (
  `key` varchar(255) NOT NULL PRIMARY KEY,
  `value` text NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `migration` varchar(255) NOT NULL,
  `batch` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- The schema has already been applied by importing this file, so record the Laravel migration.
INSERT INTO `migrations` (`migration`,`batch`)
SELECT '2026_01_01_000001_create_core_tables', 1
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration`='2026_01_01_000001_create_core_tables');
INSERT INTO `migrations` (`migration`,`batch`)
SELECT '2026_11_17_000001_add_notification_metadata', 2
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration`='2026_11_17_000001_add_notification_metadata');

-- To add starter departments, accounts and an event, run: php artisan db:seed
-- Initial demo account passwords MUST be changed before real student use.
