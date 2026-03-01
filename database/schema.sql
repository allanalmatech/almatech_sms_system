-- AlmaTech Bulk SMS SaaS (obubaka-like) - FULL MySQL SCHEMA
-- Engine: InnoDB | Charset: utf8mb4
-- Date: 2026-02-17

SET NAMES utf8mb4;
SET time_zone = '+03:00';
SET FOREIGN_KEY_CHECKS = 0;

-- =========================================================
-- 1) APP SETTINGS (Maintenance mode + system config)
-- =========================================================
CREATE TABLE IF NOT EXISTS app_settings (
  `key` VARCHAR(100) NOT NULL,
  `value` LONGTEXT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed defaults (safe to re-run with INSERT IGNORE)
INSERT IGNORE INTO app_settings(`key`,`value`) VALUES
('maintenance_enabled','0'),
('maintenance_message','We are performing scheduled maintenance. Please try again later.'),
('maintenance_allow_admin','1'),
('default_theme','theme_blue'),
('min_topup_amount','15000');

-- =========================================================
-- 2) RBAC (Optional but recommended)
-- =========================================================
CREATE TABLE IF NOT EXISTS roles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(50) NOT NULL,
  description VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_roles_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS permissions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  perm_key VARCHAR(80) NOT NULL,  -- e.g. sms.send, users.manage
  description VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_permissions_key (perm_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS role_permissions (
  role_id BIGINT UNSIGNED NOT NULL,
  permission_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  CONSTRAINT fk_rp_perm FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- 3) USERS + ACCOUNT BRANDING + THEMES + ACTIVATION
-- =========================================================
CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(60) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,

  role_id BIGINT UNSIGNED NULL,

  status ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
  disabled_reason VARCHAR(255) NULL,
  disabled_at DATETIME NULL,

  full_name VARCHAR(120) NULL,
  business_name VARCHAR(120) NULL,
  business_logo VARCHAR(255) NULL, -- path like assets/uploads/logos/123.png
  phone VARCHAR(30) NULL,
  email VARCHAR(120) NULL,
  address VARCHAR(160) NULL,

  theme VARCHAR(50) NOT NULL DEFAULT 'theme_blue',

  sms_balance INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  last_login_at DATETIME NULL,
  last_login_ip VARCHAR(45) NULL,

  PRIMARY KEY (id),
  UNIQUE KEY uq_users_username (username),
  KEY idx_users_status (status),
  KEY idx_users_role (role_id),
  KEY idx_users_phone (phone),
  KEY idx_users_email (email),
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Optional: predefined themes list (if you want to show names + preview)
CREATE TABLE IF NOT EXISTS themes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  theme_key VARCHAR(50) NOT NULL,  -- theme_blue, theme_dark...
  label VARCHAR(80) NOT NULL,
  css_file VARCHAR(255) NOT NULL,  -- assets/css/themes/theme_blue.css
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_themes_key (theme_key),
  KEY idx_themes_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed 10 themes (optional)
INSERT IGNORE INTO themes(theme_key,label,css_file,is_active,sort_order) VALUES
('theme_blue','Blue','assets/css/themes/theme_blue.css',1,1),
('theme_green','Green','assets/css/themes/theme_green.css',1,2),
('theme_red','Red','assets/css/themes/theme_red.css',1,3),
('theme_orange','Orange','assets/css/themes/theme_orange.css',1,4),
('theme_purple','Purple','assets/css/themes/theme_purple.css',1,5),
('theme_teal','Teal','assets/css/themes/theme_teal.css',1,6),
('theme_maroon','Maroon','assets/css/themes/theme_maroon.css',1,7),
('theme_indigo','Indigo','assets/css/themes/theme_indigo.css',1,8),
('theme_light','Light','assets/css/themes/theme_light.css',1,9),
('theme_dark','Dark','assets/css/themes/theme_dark.css',1,10);

-- =========================================================
-- 4) PRICING (Per user, per network) + NETWORKS
-- =========================================================
CREATE TABLE IF NOT EXISTS networks (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(50) NOT NULL,          -- MTN, Airtel, UTL, etc
  code VARCHAR(20) NOT NULL,          -- MTN, AIRTEL...
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_networks_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO networks(name,code,is_active) VALUES
('MTN','MTN',1),
('Airtel','AIRTEL',1),
('Warid','WARID',1),
('UTL','UTL',1),
('Africell','AFRICELL',1),
('Others','OTHERS',1);

CREATE TABLE IF NOT EXISTS user_network_pricing (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  network_id BIGINT UNSIGNED NOT NULL,
  price_per_sms INT NOT NULL DEFAULT 17, -- UGX per SMS or points per SMS
  currency VARCHAR(10) NOT NULL DEFAULT 'UGX',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_network (user_id, network_id),
  KEY idx_pricing_user (user_id),
  KEY idx_pricing_network (network_id),
  CONSTRAINT fk_unp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_unp_network FOREIGN KEY (network_id) REFERENCES networks(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- 5) PHONEBOOK (Groups + Contacts + Membership)
-- =========================================================
CREATE TABLE IF NOT EXISTS contact_groups (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(80) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_group_user_name (user_id, name),
  KEY idx_groups_user (user_id),
  CONSTRAINT fk_groups_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contacts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  phone_e164 VARCHAR(20) NOT NULL,   -- normalized to +2567...
  phone_raw VARCHAR(40) NULL,        -- original input
  name VARCHAR(120) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_contact_user_phone (user_id, phone_e164),
  KEY idx_contacts_user (user_id),
  KEY idx_contacts_phone (phone_e164),
  CONSTRAINT fk_contacts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS group_contacts (
  group_id BIGINT UNSIGNED NOT NULL,
  contact_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (group_id, contact_id),
  KEY idx_gc_contact (contact_id),
  CONSTRAINT fk_gc_group FOREIGN KEY (group_id) REFERENCES contact_groups(id) ON DELETE CASCADE,
  CONSTRAINT fk_gc_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- 6) SMS SENDING (Campaigns + Recipients + Gateway responses)
-- =========================================================
CREATE TABLE IF NOT EXISTS sender_ids (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  sender_name VARCHAR(20) NOT NULL,     -- e.g. "NICK", "INFOO"
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved',
  reason VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sender_user_name (user_id, sender_name),
  KEY idx_sender_user (user_id),
  CONSTRAINT fk_sender_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sms_campaigns (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,

  type ENUM('bulk','personalized') NOT NULL DEFAULT 'bulk',
  sender_id_text VARCHAR(20) NOT NULL,

  message LONGTEXT NOT NULL,
  is_scheduled TINYINT(1) NOT NULL DEFAULT 0,
  scheduled_at DATETIME NULL,

  total_recipients INT NOT NULL DEFAULT 0,
  valid_recipients INT NOT NULL DEFAULT 0,
  invalid_recipients INT NOT NULL DEFAULT 0,
  duplicate_recipients INT NOT NULL DEFAULT 0,

  segments_per_sms INT NOT NULL DEFAULT 1,
  total_sms_units INT NOT NULL DEFAULT 0, -- billable units (recipients * segments)

  cost_units INT NOT NULL DEFAULT 0,       -- if you use "points" billing
  cost_currency VARCHAR(10) NOT NULL DEFAULT 'UGX',

  status ENUM('draft','queued','processing','sent','failed','cancelled') NOT NULL DEFAULT 'queued',
  gateway_batch_id VARCHAR(80) NULL,

  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  KEY idx_campaign_user (user_id),
  KEY idx_campaign_status (status),
  KEY idx_campaign_created (created_at),
  KEY idx_campaign_scheduled (scheduled_at),
  CONSTRAINT fk_campaign_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sms_recipients (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  campaign_id BIGINT UNSIGNED NOT NULL,
  phone_e164 VARCHAR(20) NOT NULL,
  variables_json JSON NULL,  -- {"name":"Ronald","amount":"35000","var1":"..."}
  parts INT NOT NULL DEFAULT 1, -- segments used for this recipient (if per-recipient differs)
  status ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
  gateway_message_id VARCHAR(120) NULL,
  gateway_status VARCHAR(60) NULL,
  error_message VARCHAR(255) NULL,
  sent_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_rec_campaign (campaign_id),
  KEY idx_rec_phone (phone_e164),
  KEY idx_rec_status (status),
  CONSTRAINT fk_rec_campaign FOREIGN KEY (campaign_id) REFERENCES sms_campaigns(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Quick "Sent Messages" view (optional denormalized log)
CREATE TABLE IF NOT EXISTS sms_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  campaign_id BIGINT UNSIGNED NULL,
  sender_id_text VARCHAR(20) NOT NULL,
  recipients_preview VARCHAR(255) NULL, -- e.g. "2567...,2567..."
  message_preview VARCHAR(255) NULL,
  sms_units INT NOT NULL DEFAULT 0,
  status_label VARCHAR(40) NOT NULL DEFAULT 'Sent', -- Sent/Failed/Low balance
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_logs_user (user_id),
  KEY idx_logs_campaign (campaign_id),
  KEY idx_logs_created (created_at),
  CONSTRAINT fk_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_logs_campaign FOREIGN KEY (campaign_id) REFERENCES sms_campaigns(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- 7) WALLET / TOPUPS / TRANSACTIONS / VOUCHERS / TRANSFERS
-- =========================================================
CREATE TABLE IF NOT EXISTS wallet_transactions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,

  type ENUM('topup','debit_sms','refund','adjustment','transfer_in','transfer_out','voucher') NOT NULL,
  sms_units INT NOT NULL DEFAULT 0,    -- credits +/- (like "No. of SMS")
  amount INT NOT NULL DEFAULT 0,       -- UGX amount (if any)
  currency VARCHAR(10) NOT NULL DEFAULT 'UGX',

  reference VARCHAR(120) NULL,         -- payment ref / gateway ref / voucher code
  meta_json JSON NULL,

  status ENUM('pending','approved','failed','reversed') NOT NULL DEFAULT 'approved',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  approved_at DATETIME NULL,

  PRIMARY KEY (id),
  KEY idx_wt_user (user_id),
  KEY idx_wt_type (type),
  KEY idx_wt_status (status),
  KEY idx_wt_created (created_at),
  CONSTRAINT fk_wt_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS topups (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,

  phone_to_charge VARCHAR(30) NOT NULL,  -- payer phone
  amount INT NOT NULL,                   -- UGX
  sms_units INT NOT NULL,                -- credits to award
  provider ENUM('mtn','airtel','manual') NOT NULL DEFAULT 'manual',

  payment_ref VARCHAR(120) NULL,
  payment_raw LONGTEXT NULL,

  status ENUM('pending','approved','failed') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  approved_at DATETIME NULL,
  approved_by BIGINT UNSIGNED NULL,

  PRIMARY KEY (id),
  KEY idx_top_user (user_id),
  KEY idx_top_status (status),
  KEY idx_top_created (created_at),
  CONSTRAINT fk_top_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_top_approver FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS vouchers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(80) NOT NULL,
  sms_units INT NOT NULL,
  amount INT NOT NULL DEFAULT 0,
  currency VARCHAR(10) NOT NULL DEFAULT 'UGX',
  status ENUM('unused','used','revoked','expired') NOT NULL DEFAULT 'unused',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NULL,
  used_at DATETIME NULL,
  used_by BIGINT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_vouchers_code (code),
  KEY idx_vouchers_status (status),
  KEY idx_vouchers_used_by (used_by),
  CONSTRAINT fk_voucher_used_by FOREIGN KEY (used_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS credit_transfers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  from_user_id BIGINT UNSIGNED NOT NULL,
  to_user_id BIGINT UNSIGNED NOT NULL,
  sms_units INT NOT NULL,
  note VARCHAR(255) NULL,
  status ENUM('completed','failed') NOT NULL DEFAULT 'completed',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ct_from (from_user_id),
  KEY idx_ct_to (to_user_id),
  KEY idx_ct_created (created_at),
  CONSTRAINT fk_ct_from FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_ct_to FOREIGN KEY (to_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- 8) INTERNAL MESSAGING (Inbox / Support / Broadcast)
-- =========================================================
CREATE TABLE IF NOT EXISTS message_threads (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  created_by BIGINT UNSIGNED NOT NULL,
  subject VARCHAR(160) NOT NULL,
  status ENUM('open','pending','resolved','closed') NOT NULL DEFAULT 'open',
  category ENUM('billing','sms','sender_id','technical','general') NOT NULL DEFAULT 'general',
  priority ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_threads_status (status),
  KEY idx_threads_created_by (created_by),
  CONSTRAINT fk_threads_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS thread_participants (
  thread_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  role ENUM('owner','member','support') NOT NULL DEFAULT 'member',
  joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (thread_id, user_id),
  KEY idx_tp_user (user_id),
  CONSTRAINT fk_tp_thread FOREIGN KEY (thread_id) REFERENCES message_threads(id) ON DELETE CASCADE,
  CONSTRAINT fk_tp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS thread_messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  thread_id BIGINT UNSIGNED NOT NULL,
  from_user_id BIGINT UNSIGNED NOT NULL,
  body LONGTEXT NOT NULL,
  attachment_path VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tm_thread (thread_id),
  KEY idx_tm_from (from_user_id),
  KEY idx_tm_created (created_at),
  CONSTRAINT fk_tm_thread FOREIGN KEY (thread_id) REFERENCES message_threads(id) ON DELETE CASCADE,
  CONSTRAINT fk_tm_from FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS message_reads (
  message_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  read_at DATETIME NOT NULL,
  PRIMARY KEY (message_id, user_id),
  CONSTRAINT fk_mr_message FOREIGN KEY (message_id) REFERENCES thread_messages(id) ON DELETE CASCADE,
  CONSTRAINT fk_mr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- 9) NOTIFICATIONS + PUSH SUBSCRIPTIONS (Web Push)
-- =========================================================
CREATE TABLE IF NOT EXISTS notifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(120) NOT NULL,
  body VARCHAR(255) NOT NULL,
  url VARCHAR(255) NULL,
  type VARCHAR(60) NULL, -- topup_approved, sms_failed, new_message, etc
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  read_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_notif_user (user_id),
  KEY idx_notif_read (is_read),
  KEY idx_notif_created (created_at),
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS push_subscriptions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  endpoint VARCHAR(500) NOT NULL,
  p256dh VARCHAR(255) NOT NULL,
  auth VARCHAR(255) NOT NULL,
  user_agent VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at DATETIME NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY idx_ps_user (user_id),
  KEY idx_ps_active (is_active),
  CONSTRAINT fk_ps_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- 10) AUDIT LOGS (recommended)
-- =========================================================
CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NULL,
  action VARCHAR(120) NOT NULL,         -- e.g. users.deactivate, sms.send
  entity VARCHAR(60) NULL,              -- users, sms_campaigns, topups
  entity_id BIGINT UNSIGNED NULL,
  ip VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  details_json JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_user (user_id),
  KEY idx_audit_action (action),
  KEY idx_audit_created (created_at),
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- 11) OPTIONAL: API KEYS (if clients integrate via API)
-- =========================================================
CREATE TABLE IF NOT EXISTS api_keys (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  api_key VARCHAR(80) NOT NULL,
  label VARCHAR(80) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_used_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_api_key (api_key),
  KEY idx_api_user (user_id),
  CONSTRAINT fk_api_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- 12) OPTIONAL: SIMPLE SMS QUEUE WORKER LOCKS
-- =========================================================
CREATE TABLE IF NOT EXISTS worker_locks (
  lock_key VARCHAR(80) NOT NULL,
  locked_by VARCHAR(80) NULL,
  locked_at DATETIME NULL,
  expires_at DATETIME NULL,
  PRIMARY KEY (lock_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
