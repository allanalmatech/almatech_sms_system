-- AlmaTech Bulk SMS SaaS - SEED DATA
-- Date: 2026-02-17
-- NOTE: password_hash values here are PLACEHOLDERS.
-- After importing, update passwords using PHP password_hash() and update the rows.

SET NAMES utf8mb4;
SET time_zone = '+03:00';
SET FOREIGN_KEY_CHECKS = 0;

-- =========================================================
-- 1) ROLES
-- =========================================================
INSERT IGNORE INTO roles (id, name, description) VALUES
(1, 'admin',  'System Administrator'),
(2, 'client', 'Client Account');

-- =========================================================
-- 2) PERMISSIONS (core permissions you'll need)
-- =========================================================
INSERT IGNORE INTO permissions (perm_key, description) VALUES
('dashboard.view', 'View dashboard'),

('users.view', 'View users'),
('users.create', 'Create users'),
('users.update', 'Update users'),
('users.activate', 'Activate users'),
('users.deactivate', 'Deactivate users'),

('maintenance.view', 'View maintenance settings'),
('maintenance.update', 'Update maintenance settings'),

('sms.compose', 'Compose SMS'),
('sms.send', 'Send SMS now'),
('sms.schedule', 'Schedule SMS'),
('sms.sent.view', 'View sent messages'),
('sms.export', 'Export SMS logs'),

('phonebook.view', 'View phonebook'),
('phonebook.manage', 'Manage groups and contacts'),

('wallet.view', 'View wallet/transactions'),
('wallet.topup', 'Request topup'),
('wallet.approve', 'Approve topups'),
('wallet.voucher', 'Load voucher'),
('wallet.transfer', 'Transfer credits to another user'),

('messaging.view', 'View internal messages'),
('messaging.send', 'Send internal messages'),
('messaging.broadcast', 'Broadcast messages'),

('notifications.view', 'View notifications'),
('push.subscribe', 'Subscribe to push notifications'),

('settings.profile', 'Edit profile & branding'),
('settings.theme', 'Change theme'),

('audit.view', 'View audit logs');

-- =========================================================
-- 3) ROLE -> PERMISSIONS
--    Admin gets everything
-- =========================================================
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 1 AS role_id, p.id
FROM permissions p;

-- Client permissions (safe subset)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 2 AS role_id, p.id
FROM permissions p
WHERE p.perm_key IN (
  'dashboard.view',
  'sms.compose','sms.send','sms.schedule','sms.sent.view','sms.export',
  'phonebook.view','phonebook.manage',
  'wallet.view','wallet.topup','wallet.voucher','wallet.transfer',
  'messaging.view','messaging.send',
  'notifications.view','push.subscribe',
  'settings.profile','settings.theme'
);

-- =========================================================
-- 4) NETWORKS (if not already inserted)
-- =========================================================
INSERT IGNORE INTO networks (id, name, code, is_active) VALUES
(1,'MTN','MTN',1),
(2,'Airtel','AIRTEL',1),
(3,'Warid','WARID',1),
(4,'UTL','UTL',1),
(5,'Africell','AFRICELL',1),
(6,'Others','OTHERS',1);

-- =========================================================
-- 5) THEMES (10 themes)
-- =========================================================
INSERT IGNORE INTO themes (theme_key, label, css_file, is_active, sort_order) VALUES
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
-- 6) APP SETTINGS DEFAULTS (if not already inserted)
-- =========================================================
INSERT IGNORE INTO app_settings(`key`,`value`) VALUES
('maintenance_enabled','0'),
('maintenance_message','We are performing scheduled maintenance. Please try again later.'),
('maintenance_allow_admin','1'),
('default_theme','theme_blue'),
('min_topup_amount','15000');

-- =========================================================
-- 7) DEMO USERS
--    IMPORTANT: Replace password hashes after import!
-- =========================================================

-- Admin user: username=admin / password=Admin@123 (placeholder hash)
INSERT IGNORE INTO users (
  id, username, password_hash, role_id, status,
  full_name, business_name, phone, email, address,
  theme, sms_balance, created_at
) VALUES (
  1, 'admin',
  '$2y$10$REPLACE_THIS_HASH_WITH_REAL_HASH_ADMIN',
  1, 'active',
  'System Admin', 'AlmaTech', '256700000000', 'admin@almatech.local', 'Kampala',
  'theme_dark', 0, NOW()
);

-- Client user: username=almatech / password=Alma@123 (placeholder hash)
INSERT IGNORE INTO users (
  id, username, password_hash, role_id, status,
  full_name, business_name, phone, email, address,
  theme, sms_balance, created_at
) VALUES (
  2, 'almatech',
  '$2y$10$REPLACE_THIS_HASH_WITH_REAL_HASH_CLIENT',
  2, 'active',
  'Tech', 'almatech', '256700868939', 'allanomwesi70@gmail.com', 'Mbarara',
  'theme_blue', 5796, NOW()
);

-- =========================================================
-- 8) DEFAULT PRICING FOR DEMO CLIENT (17 across networks)
-- =========================================================
INSERT IGNORE INTO user_network_pricing (user_id, network_id, price_per_sms, currency)
VALUES
(2,1,17,'UGX'),
(2,2,17,'UGX'),
(2,3,17,'UGX'),
(2,4,17,'UGX'),
(2,5,17,'UGX'),
(2,6,17,'UGX');

-- (Optional) Admin pricing not needed, but harmless:
INSERT IGNORE INTO user_network_pricing (user_id, network_id, price_per_sms, currency)
VALUES
(1,1,17,'UGX'),
(1,2,17,'UGX'),
(1,3,17,'UGX'),
(1,4,17,'UGX'),
(1,5,17,'UGX'),
(1,6,17,'UGX');

-- =========================================================
-- 9) DEFAULT CONTACT GROUP "All" FOR DEMO CLIENT
-- =========================================================
INSERT IGNORE INTO contact_groups (id, user_id, name, created_at)
VALUES (1, 2, 'All', NOW());

-- =========================================================
-- 10) OPTIONAL: Seed a welcome notification + message thread
-- =========================================================
INSERT IGNORE INTO notifications (user_id, title, body, url, type, is_read, created_at)
VALUES
(2, 'Welcome to AlmaTech SMS', 'Your account is ready. You can now add contacts and send SMS.', 'dashboard.php', 'welcome', 0, NOW());

-- Create support thread
INSERT IGNORE INTO message_threads (id, created_by, subject, status, category, priority, created_at)
VALUES (1, 2, 'Welcome / Support', 'open', 'general', 'low', NOW());

INSERT IGNORE INTO thread_participants (thread_id, user_id, role, joined_at)
VALUES
(1, 2, 'owner', NOW()),
(1, 1, 'support', NOW());

INSERT IGNORE INTO thread_messages (thread_id, from_user_id, body, created_at)
VALUES
(1, 1, 'Welcome! If you need help setting up Sender IDs, contacts, or payments, message here.', NOW());

-- Mark admin’s message as unread for client (no read record inserted)

-- =========================================================
-- 11) OPTIONAL: Seed sample voucher
-- =========================================================
INSERT IGNORE INTO vouchers (code, sms_units, amount, currency, status, created_at, expires_at)
VALUES
('ALMA-TEST-15000', 15000, 15000, 'UGX', 'unused', NOW(), DATE_ADD(NOW(), INTERVAL 90 DAY));

SET FOREIGN_KEY_CHECKS = 1;

-- =========================================================
-- IMPORTANT NEXT STEP:
-- Update password hashes using PHP:
--   UPDATE users SET password_hash = password_hash('Admin@123', PASSWORD_DEFAULT) ... (NO)
-- Use PHP's password_hash() function in a script, example:
--   $hash = password_hash('Admin@123', PASSWORD_DEFAULT);
-- Then run:
--   UPDATE users SET password_hash='<HASH>' WHERE username='admin';
-- =========================================================
