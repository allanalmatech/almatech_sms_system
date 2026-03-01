-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 01, 2026 at 02:03 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `almatech_sms`
--

-- --------------------------------------------------------

--
-- Table structure for table `api_keys`
--

CREATE TABLE `api_keys` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `api_key` varchar(80) NOT NULL,
  `label` varchar(80) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_used_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `app_settings`
--

CREATE TABLE `app_settings` (
  `key` varchar(100) NOT NULL,
  `value` longtext DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `app_settings`
--

INSERT INTO `app_settings` (`key`, `value`, `updated_at`) VALUES
('default_theme', 'theme_blue', '2026-02-17 17:11:49'),
('maintenance_allow_admin', '1', '2026-02-17 17:11:49'),
('maintenance_enabled', '0', '2026-02-17 17:11:49'),
('maintenance_message', 'We are performing scheduled maintenance. Please try again later.', '2026-02-17 17:11:49'),
('min_topup_amount', '15000', '2026-02-17 17:11:49');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` varchar(120) NOT NULL,
  `entity` varchar(60) DEFAULT NULL,
  `entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `details_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details_json`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity`, `entity_id`, `ip`, `user_agent`, `details_json`, `created_at`) VALUES
(1, 1, 'settings.branding.update', 'users', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:149.0) Gecko/20100101 Firefox/149.0', '{\"logo_updated\":false}', '2026-02-28 19:36:42');

-- --------------------------------------------------------

--
-- Table structure for table `contacts`
--

CREATE TABLE `contacts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `phone_e164` varchar(20) NOT NULL,
  `phone_raw` varchar(40) DEFAULT NULL,
  `name` varchar(120) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contact_groups`
--

CREATE TABLE `contact_groups` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(80) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `contact_groups`
--

INSERT INTO `contact_groups` (`id`, `user_id`, `name`, `created_at`) VALUES
(1, 2, 'All', '2026-02-17 17:11:58'),
(3, 1, 'All', '2026-02-28 19:34:57');

-- --------------------------------------------------------

--
-- Table structure for table `credit_transfers`
--

CREATE TABLE `credit_transfers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `from_user_id` bigint(20) UNSIGNED NOT NULL,
  `to_user_id` bigint(20) UNSIGNED NOT NULL,
  `sms_units` int(11) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `status` enum('completed','failed') NOT NULL DEFAULT 'completed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `group_contacts`
--

CREATE TABLE `group_contacts` (
  `group_id` bigint(20) UNSIGNED NOT NULL,
  `contact_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `message_reads`
--

CREATE TABLE `message_reads` (
  `message_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `read_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `message_threads`
--

CREATE TABLE `message_threads` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `subject` varchar(160) NOT NULL,
  `status` enum('open','pending','resolved','closed') NOT NULL DEFAULT 'open',
  `category` enum('billing','sms','sender_id','technical','general') NOT NULL DEFAULT 'general',
  `priority` enum('low','medium','high') NOT NULL DEFAULT 'medium',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `message_threads`
--

INSERT INTO `message_threads` (`id`, `created_by`, `subject`, `status`, `category`, `priority`, `created_at`, `updated_at`) VALUES
(1, 2, 'Welcome / Support', 'open', 'general', 'low', '2026-02-17 17:11:58', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `networks`
--

CREATE TABLE `networks` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(50) NOT NULL,
  `code` varchar(20) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `networks`
--

INSERT INTO `networks` (`id`, `name`, `code`, `is_active`, `created_at`) VALUES
(1, 'MTN', 'MTN', 1, '2026-02-17 17:11:49'),
(2, 'Airtel', 'AIRTEL', 1, '2026-02-17 17:11:49'),
(3, 'Warid', 'WARID', 1, '2026-02-17 17:11:49'),
(4, 'UTL', 'UTL', 1, '2026-02-17 17:11:49'),
(5, 'Africell', 'AFRICELL', 1, '2026-02-17 17:11:49'),
(6, 'Others', 'OTHERS', 1, '2026-02-17 17:11:49');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(120) NOT NULL,
  `body` varchar(255) NOT NULL,
  `url` varchar(255) DEFAULT NULL,
  `type` varchar(60) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `body`, `url`, `type`, `is_read`, `created_at`, `read_at`) VALUES
(1, 2, 'Welcome to AlmaTech SMS', 'Your account is ready. You can now add contacts and send SMS.', 'dashboard.php', 'welcome', 0, '2026-02-17 17:11:58', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `perm_key` varchar(80) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `perm_key`, `description`, `created_at`) VALUES
(1, 'dashboard.view', 'View dashboard', '2026-02-17 17:11:58'),
(2, 'users.view', 'View users', '2026-02-17 17:11:58'),
(3, 'users.create', 'Create users', '2026-02-17 17:11:58'),
(4, 'users.update', 'Update users', '2026-02-17 17:11:58'),
(5, 'users.activate', 'Activate users', '2026-02-17 17:11:58'),
(6, 'users.deactivate', 'Deactivate users', '2026-02-17 17:11:58'),
(7, 'maintenance.view', 'View maintenance settings', '2026-02-17 17:11:58'),
(8, 'maintenance.update', 'Update maintenance settings', '2026-02-17 17:11:58'),
(9, 'sms.compose', 'Compose SMS', '2026-02-17 17:11:58'),
(10, 'sms.send', 'Send SMS now', '2026-02-17 17:11:58'),
(11, 'sms.schedule', 'Schedule SMS', '2026-02-17 17:11:58'),
(12, 'sms.sent.view', 'View sent messages', '2026-02-17 17:11:58'),
(13, 'sms.export', 'Export SMS logs', '2026-02-17 17:11:58'),
(14, 'phonebook.view', 'View phonebook', '2026-02-17 17:11:58'),
(15, 'phonebook.manage', 'Manage groups and contacts', '2026-02-17 17:11:58'),
(16, 'wallet.view', 'View wallet/transactions', '2026-02-17 17:11:58'),
(17, 'wallet.topup', 'Request topup', '2026-02-17 17:11:58'),
(18, 'wallet.approve', 'Approve topups', '2026-02-17 17:11:58'),
(19, 'wallet.voucher', 'Load voucher', '2026-02-17 17:11:58'),
(20, 'wallet.transfer', 'Transfer credits to another user', '2026-02-17 17:11:58'),
(21, 'messaging.view', 'View internal messages', '2026-02-17 17:11:58'),
(22, 'messaging.send', 'Send internal messages', '2026-02-17 17:11:58'),
(23, 'messaging.broadcast', 'Broadcast messages', '2026-02-17 17:11:58'),
(24, 'notifications.view', 'View notifications', '2026-02-17 17:11:58'),
(25, 'push.subscribe', 'Subscribe to push notifications', '2026-02-17 17:11:58'),
(26, 'settings.profile', 'Edit profile & branding', '2026-02-17 17:11:58'),
(27, 'settings.theme', 'Change theme', '2026-02-17 17:11:58'),
(28, 'audit.view', 'View audit logs', '2026-02-17 17:11:58');

-- --------------------------------------------------------

--
-- Table structure for table `push_subscriptions`
--

CREATE TABLE `push_subscriptions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `endpoint` varchar(500) NOT NULL,
  `p256dh` varchar(255) NOT NULL,
  `auth` varchar(255) NOT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_seen_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `description`, `created_at`) VALUES
(1, 'admin', 'System Administrator', '2026-02-17 17:11:58'),
(2, 'client', 'Client Account', '2026-02-17 17:11:58');

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `role_id` bigint(20) UNSIGNED NOT NULL,
  `permission_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `role_permissions`
--

INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(1, 1),
(1, 2),
(1, 3),
(1, 4),
(1, 5),
(1, 6),
(1, 7),
(1, 8),
(1, 9),
(1, 10),
(1, 11),
(1, 12),
(1, 13),
(1, 14),
(1, 15),
(1, 16),
(1, 17),
(1, 18),
(1, 19),
(1, 20),
(1, 21),
(1, 22),
(1, 23),
(1, 24),
(1, 25),
(1, 26),
(1, 27),
(1, 28),
(2, 1),
(2, 9),
(2, 10),
(2, 11),
(2, 12),
(2, 13),
(2, 14),
(2, 15),
(2, 16),
(2, 17),
(2, 19),
(2, 20),
(2, 21),
(2, 22),
(2, 24),
(2, 25),
(2, 26),
(2, 27);

-- --------------------------------------------------------

--
-- Table structure for table `sender_ids`
--

CREATE TABLE `sender_ids` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `sender_name` varchar(20) NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'approved',
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sms_campaigns`
--

CREATE TABLE `sms_campaigns` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `type` enum('bulk','personalized') NOT NULL DEFAULT 'bulk',
  `sender_id_text` varchar(20) NOT NULL,
  `message` longtext NOT NULL,
  `is_scheduled` tinyint(1) NOT NULL DEFAULT 0,
  `scheduled_at` datetime DEFAULT NULL,
  `total_recipients` int(11) NOT NULL DEFAULT 0,
  `valid_recipients` int(11) NOT NULL DEFAULT 0,
  `invalid_recipients` int(11) NOT NULL DEFAULT 0,
  `duplicate_recipients` int(11) NOT NULL DEFAULT 0,
  `segments_per_sms` int(11) NOT NULL DEFAULT 1,
  `total_sms_units` int(11) NOT NULL DEFAULT 0,
  `cost_units` int(11) NOT NULL DEFAULT 0,
  `cost_currency` varchar(10) NOT NULL DEFAULT 'UGX',
  `status` enum('draft','queued','processing','sent','failed','cancelled') NOT NULL DEFAULT 'queued',
  `gateway_batch_id` varchar(80) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sms_logs`
--

CREATE TABLE `sms_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `campaign_id` bigint(20) UNSIGNED DEFAULT NULL,
  `sender_id_text` varchar(20) NOT NULL,
  `recipients_preview` varchar(255) DEFAULT NULL,
  `message_preview` varchar(255) DEFAULT NULL,
  `sms_units` int(11) NOT NULL DEFAULT 0,
  `status_label` varchar(40) NOT NULL DEFAULT 'Sent',
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sms_recipients`
--

CREATE TABLE `sms_recipients` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `campaign_id` bigint(20) UNSIGNED NOT NULL,
  `phone_e164` varchar(20) NOT NULL,
  `variables_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`variables_json`)),
  `parts` int(11) NOT NULL DEFAULT 1,
  `status` enum('queued','sent','failed') NOT NULL DEFAULT 'queued',
  `gateway_message_id` varchar(120) DEFAULT NULL,
  `gateway_status` varchar(60) DEFAULT NULL,
  `error_message` varchar(255) DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `themes`
--

CREATE TABLE `themes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `theme_key` varchar(50) NOT NULL,
  `label` varchar(80) NOT NULL,
  `css_file` varchar(255) NOT NULL,
  `primary_color` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `themes`
--

INSERT INTO `themes` (`id`, `theme_key`, `label`, `css_file`, `primary_color`, `is_active`, `sort_order`) VALUES
(1, 'theme_blue', 'Blue', 'assets/css/themes/theme_blue.css', '#2563eb', 1, 1),
(2, 'theme_green', 'Green', 'assets/css/themes/theme_green.css', '#16a34a', 1, 2),
(3, 'theme_red', 'Red', 'assets/css/themes/theme_red.css', '#dc2626', 1, 3),
(4, 'theme_orange', 'Orange', 'assets/css/themes/theme_orange.css', '#f59e0b', 1, 4),
(5, 'theme_purple', 'Purple', 'assets/css/themes/theme_purple.css', '#7c3aed', 1, 5),
(6, 'theme_teal', 'Teal', 'assets/css/themes/theme_teal.css', '#14b8a6', 1, 6),
(7, 'theme_maroon', 'Maroon', 'assets/css/themes/theme_maroon.css', '#ea580c', 1, 7),
(8, 'theme_indigo', 'Indigo', 'assets/css/themes/theme_indigo.css', '#0ea5e9', 1, 8),
(9, 'theme_light', 'Light', 'assets/css/themes/theme_light.css', '#334155', 1, 9),
(10, 'theme_dark', 'Dark', 'assets/css/themes/theme_dark.css', '#111827', 1, 10);

-- --------------------------------------------------------

--
-- Table structure for table `thread_messages`
--

CREATE TABLE `thread_messages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `thread_id` bigint(20) UNSIGNED NOT NULL,
  `from_user_id` bigint(20) UNSIGNED NOT NULL,
  `body` longtext NOT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `thread_messages`
--

INSERT INTO `thread_messages` (`id`, `thread_id`, `from_user_id`, `body`, `attachment_path`, `created_at`) VALUES
(1, 1, 1, 'Welcome! If you need help setting up Sender IDs, contacts, or payments, message here.', NULL, '2026-02-17 17:11:58');

-- --------------------------------------------------------

--
-- Table structure for table `thread_participants`
--

CREATE TABLE `thread_participants` (
  `thread_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `role` enum('owner','member','support') NOT NULL DEFAULT 'member',
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `thread_participants`
--

INSERT INTO `thread_participants` (`thread_id`, `user_id`, `role`, `joined_at`) VALUES
(1, 1, 'support', '2026-02-17 17:11:58'),
(1, 2, 'owner', '2026-02-17 17:11:58');

-- --------------------------------------------------------

--
-- Table structure for table `topups`
--

CREATE TABLE `topups` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `phone_to_charge` varchar(30) NOT NULL,
  `amount` int(11) NOT NULL,
  `sms_units` int(11) NOT NULL,
  `provider` enum('mtn','airtel','manual') NOT NULL DEFAULT 'manual',
  `payment_ref` varchar(120) DEFAULT NULL,
  `payment_raw` longtext DEFAULT NULL,
  `status` enum('pending','approved','failed') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `approved_at` datetime DEFAULT NULL,
  `approved_by` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `username` varchar(60) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role_id` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `disabled_reason` varchar(255) DEFAULT NULL,
  `disabled_at` datetime DEFAULT NULL,
  `full_name` varchar(120) DEFAULT NULL,
  `business_name` varchar(120) DEFAULT NULL,
  `business_logo` varchar(255) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `address` varchar(160) DEFAULT NULL,
  `theme` varchar(50) NOT NULL DEFAULT 'theme_blue',
  `sms_balance` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `last_login_at` datetime DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `role_id`, `status`, `disabled_reason`, `disabled_at`, `full_name`, `business_name`, `business_logo`, `phone`, `email`, `address`, `theme`, `sms_balance`, `created_at`, `updated_at`, `last_login_at`, `last_login_ip`) VALUES
(1, 'admin', '$2y$12$Vya0NLsmZtSI9.P7Yopwl.mGttaHAUCmAfMYrvbmC8lrtQNaoLBgu', 1, 'active', NULL, NULL, 'System Admin', 'Alma Tech SMS', NULL, '256700000000', 'admin@almatech.local', 'Kampala', 'theme_blue', 0, '2026-02-17 17:11:58', '2026-02-28 19:36:42', '2026-02-28 22:03:15', '127.0.0.1'),
(2, 'almatech', '$2y$12$m8ry7.9fzR19Uj/fAHu7Xu6Zt0LTz4p/GHtmYssKW9VBRvhV/gO1y', 2, 'active', NULL, NULL, 'Tech', 'almatech', NULL, '256700868939', 'allanomwesi70@gmail.com', 'Mbarara', 'theme_blue', 5796, '2026-02-17 17:11:58', '2026-03-01 11:48:27', '2026-03-01 14:48:27', '127.0.0.1');

-- --------------------------------------------------------

--
-- Table structure for table `user_network_pricing`
--

CREATE TABLE `user_network_pricing` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `network_id` bigint(20) UNSIGNED NOT NULL,
  `price_per_sms` int(11) NOT NULL DEFAULT 17,
  `currency` varchar(10) NOT NULL DEFAULT 'UGX',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_network_pricing`
--

INSERT INTO `user_network_pricing` (`id`, `user_id`, `network_id`, `price_per_sms`, `currency`, `created_at`, `updated_at`) VALUES
(1, 2, 1, 17, 'UGX', '2026-02-17 17:11:58', NULL),
(2, 2, 2, 17, 'UGX', '2026-02-17 17:11:58', NULL),
(3, 2, 3, 17, 'UGX', '2026-02-17 17:11:58', NULL),
(4, 2, 4, 17, 'UGX', '2026-02-17 17:11:58', NULL),
(5, 2, 5, 17, 'UGX', '2026-02-17 17:11:58', NULL),
(6, 2, 6, 17, 'UGX', '2026-02-17 17:11:58', NULL),
(7, 1, 1, 17, 'UGX', '2026-02-17 17:11:58', NULL),
(8, 1, 2, 17, 'UGX', '2026-02-17 17:11:58', NULL),
(9, 1, 3, 17, 'UGX', '2026-02-17 17:11:58', NULL),
(10, 1, 4, 17, 'UGX', '2026-02-17 17:11:58', NULL),
(11, 1, 5, 17, 'UGX', '2026-02-17 17:11:58', NULL),
(12, 1, 6, 17, 'UGX', '2026-02-17 17:11:58', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `vouchers`
--

CREATE TABLE `vouchers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(80) NOT NULL,
  `sms_units` int(11) NOT NULL,
  `amount` int(11) NOT NULL DEFAULT 0,
  `currency` varchar(10) NOT NULL DEFAULT 'UGX',
  `status` enum('unused','used','revoked','expired') NOT NULL DEFAULT 'unused',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL,
  `used_at` datetime DEFAULT NULL,
  `used_by` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vouchers`
--

INSERT INTO `vouchers` (`id`, `code`, `sms_units`, `amount`, `currency`, `status`, `created_at`, `expires_at`, `used_at`, `used_by`) VALUES
(1, 'ALMA-TEST-15000', 15000, 15000, 'UGX', 'unused', '2026-02-17 17:11:58', '2026-05-18 20:11:58', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `wallet_transactions`
--

CREATE TABLE `wallet_transactions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `type` enum('topup','debit_sms','refund','adjustment','transfer_in','transfer_out','voucher') NOT NULL,
  `sms_units` int(11) NOT NULL DEFAULT 0,
  `amount` int(11) NOT NULL DEFAULT 0,
  `currency` varchar(10) NOT NULL DEFAULT 'UGX',
  `reference` varchar(120) DEFAULT NULL,
  `meta_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta_json`)),
  `status` enum('pending','approved','failed','reversed') NOT NULL DEFAULT 'approved',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `worker_locks`
--

CREATE TABLE `worker_locks` (
  `lock_key` varchar(80) NOT NULL,
  `locked_by` varchar(80) DEFAULT NULL,
  `locked_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `api_keys`
--
ALTER TABLE `api_keys`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_api_key` (`api_key`),
  ADD KEY `idx_api_user` (`user_id`);

--
-- Indexes for table `app_settings`
--
ALTER TABLE `app_settings`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_user` (`user_id`),
  ADD KEY `idx_audit_action` (`action`),
  ADD KEY `idx_audit_created` (`created_at`);

--
-- Indexes for table `contacts`
--
ALTER TABLE `contacts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_contact_user_phone` (`user_id`,`phone_e164`),
  ADD KEY `idx_contacts_user` (`user_id`),
  ADD KEY `idx_contacts_phone` (`phone_e164`);

--
-- Indexes for table `contact_groups`
--
ALTER TABLE `contact_groups`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_group_user_name` (`user_id`,`name`),
  ADD KEY `idx_groups_user` (`user_id`);

--
-- Indexes for table `credit_transfers`
--
ALTER TABLE `credit_transfers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ct_from` (`from_user_id`),
  ADD KEY `idx_ct_to` (`to_user_id`),
  ADD KEY `idx_ct_created` (`created_at`);

--
-- Indexes for table `group_contacts`
--
ALTER TABLE `group_contacts`
  ADD PRIMARY KEY (`group_id`,`contact_id`),
  ADD KEY `idx_gc_contact` (`contact_id`);

--
-- Indexes for table `message_reads`
--
ALTER TABLE `message_reads`
  ADD PRIMARY KEY (`message_id`,`user_id`),
  ADD KEY `fk_mr_user` (`user_id`);

--
-- Indexes for table `message_threads`
--
ALTER TABLE `message_threads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_threads_status` (`status`),
  ADD KEY `idx_threads_created_by` (`created_by`);

--
-- Indexes for table `networks`
--
ALTER TABLE `networks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_networks_code` (`code`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notif_user` (`user_id`),
  ADD KEY `idx_notif_read` (`is_read`),
  ADD KEY `idx_notif_created` (`created_at`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_permissions_key` (`perm_key`);

--
-- Indexes for table `push_subscriptions`
--
ALTER TABLE `push_subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ps_user` (`user_id`),
  ADD KEY `idx_ps_active` (`is_active`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_roles_name` (`name`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`role_id`,`permission_id`),
  ADD KEY `fk_rp_perm` (`permission_id`);

--
-- Indexes for table `sender_ids`
--
ALTER TABLE `sender_ids`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_sender_user_name` (`user_id`,`sender_name`),
  ADD KEY `idx_sender_user` (`user_id`);

--
-- Indexes for table `sms_campaigns`
--
ALTER TABLE `sms_campaigns`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_campaign_user` (`user_id`),
  ADD KEY `idx_campaign_status` (`status`),
  ADD KEY `idx_campaign_created` (`created_at`),
  ADD KEY `idx_campaign_scheduled` (`scheduled_at`);

--
-- Indexes for table `sms_logs`
--
ALTER TABLE `sms_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_logs_user` (`user_id`),
  ADD KEY `idx_logs_campaign` (`campaign_id`),
  ADD KEY `idx_logs_created` (`created_at`);

--
-- Indexes for table `sms_recipients`
--
ALTER TABLE `sms_recipients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_rec_campaign` (`campaign_id`),
  ADD KEY `idx_rec_phone` (`phone_e164`),
  ADD KEY `idx_rec_status` (`status`);

--
-- Indexes for table `themes`
--
ALTER TABLE `themes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_themes_key` (`theme_key`),
  ADD KEY `idx_themes_active` (`is_active`);

--
-- Indexes for table `thread_messages`
--
ALTER TABLE `thread_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tm_thread` (`thread_id`),
  ADD KEY `idx_tm_from` (`from_user_id`),
  ADD KEY `idx_tm_created` (`created_at`);

--
-- Indexes for table `thread_participants`
--
ALTER TABLE `thread_participants`
  ADD PRIMARY KEY (`thread_id`,`user_id`),
  ADD KEY `idx_tp_user` (`user_id`);

--
-- Indexes for table `topups`
--
ALTER TABLE `topups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_top_user` (`user_id`),
  ADD KEY `idx_top_status` (`status`),
  ADD KEY `idx_top_created` (`created_at`),
  ADD KEY `fk_top_approver` (`approved_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_username` (`username`),
  ADD KEY `idx_users_status` (`status`),
  ADD KEY `idx_users_role` (`role_id`),
  ADD KEY `idx_users_phone` (`phone`),
  ADD KEY `idx_users_email` (`email`);

--
-- Indexes for table `user_network_pricing`
--
ALTER TABLE `user_network_pricing`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_network` (`user_id`,`network_id`),
  ADD KEY `idx_pricing_user` (`user_id`),
  ADD KEY `idx_pricing_network` (`network_id`);

--
-- Indexes for table `vouchers`
--
ALTER TABLE `vouchers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_vouchers_code` (`code`),
  ADD KEY `idx_vouchers_status` (`status`),
  ADD KEY `idx_vouchers_used_by` (`used_by`);

--
-- Indexes for table `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_wt_user` (`user_id`),
  ADD KEY `idx_wt_type` (`type`),
  ADD KEY `idx_wt_status` (`status`),
  ADD KEY `idx_wt_created` (`created_at`);

--
-- Indexes for table `worker_locks`
--
ALTER TABLE `worker_locks`
  ADD PRIMARY KEY (`lock_key`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `api_keys`
--
ALTER TABLE `api_keys`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `contacts`
--
ALTER TABLE `contacts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `contact_groups`
--
ALTER TABLE `contact_groups`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `credit_transfers`
--
ALTER TABLE `credit_transfers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `message_threads`
--
ALTER TABLE `message_threads`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `networks`
--
ALTER TABLE `networks`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `push_subscriptions`
--
ALTER TABLE `push_subscriptions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `sender_ids`
--
ALTER TABLE `sender_ids`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sms_campaigns`
--
ALTER TABLE `sms_campaigns`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sms_logs`
--
ALTER TABLE `sms_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sms_recipients`
--
ALTER TABLE `sms_recipients`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `themes`
--
ALTER TABLE `themes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `thread_messages`
--
ALTER TABLE `thread_messages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `topups`
--
ALTER TABLE `topups`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `user_network_pricing`
--
ALTER TABLE `user_network_pricing`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `vouchers`
--
ALTER TABLE `vouchers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `api_keys`
--
ALTER TABLE `api_keys`
  ADD CONSTRAINT `fk_api_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `contacts`
--
ALTER TABLE `contacts`
  ADD CONSTRAINT `fk_contacts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `contact_groups`
--
ALTER TABLE `contact_groups`
  ADD CONSTRAINT `fk_groups_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `credit_transfers`
--
ALTER TABLE `credit_transfers`
  ADD CONSTRAINT `fk_ct_from` FOREIGN KEY (`from_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ct_to` FOREIGN KEY (`to_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `group_contacts`
--
ALTER TABLE `group_contacts`
  ADD CONSTRAINT `fk_gc_contact` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_gc_group` FOREIGN KEY (`group_id`) REFERENCES `contact_groups` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `message_reads`
--
ALTER TABLE `message_reads`
  ADD CONSTRAINT `fk_mr_message` FOREIGN KEY (`message_id`) REFERENCES `thread_messages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_mr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `message_threads`
--
ALTER TABLE `message_threads`
  ADD CONSTRAINT `fk_threads_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `push_subscriptions`
--
ALTER TABLE `push_subscriptions`
  ADD CONSTRAINT `fk_ps_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `fk_rp_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sender_ids`
--
ALTER TABLE `sender_ids`
  ADD CONSTRAINT `fk_sender_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sms_campaigns`
--
ALTER TABLE `sms_campaigns`
  ADD CONSTRAINT `fk_campaign_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sms_logs`
--
ALTER TABLE `sms_logs`
  ADD CONSTRAINT `fk_logs_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `sms_campaigns` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sms_recipients`
--
ALTER TABLE `sms_recipients`
  ADD CONSTRAINT `fk_rec_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `sms_campaigns` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `thread_messages`
--
ALTER TABLE `thread_messages`
  ADD CONSTRAINT `fk_tm_from` FOREIGN KEY (`from_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_tm_thread` FOREIGN KEY (`thread_id`) REFERENCES `message_threads` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `thread_participants`
--
ALTER TABLE `thread_participants`
  ADD CONSTRAINT `fk_tp_thread` FOREIGN KEY (`thread_id`) REFERENCES `message_threads` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_tp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `topups`
--
ALTER TABLE `topups`
  ADD CONSTRAINT `fk_top_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_top_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_network_pricing`
--
ALTER TABLE `user_network_pricing`
  ADD CONSTRAINT `fk_unp_network` FOREIGN KEY (`network_id`) REFERENCES `networks` (`id`),
  ADD CONSTRAINT `fk_unp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vouchers`
--
ALTER TABLE `vouchers`
  ADD CONSTRAINT `fk_voucher_used_by` FOREIGN KEY (`used_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  ADD CONSTRAINT `fk_wt_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
