@echo off
title AlmaTech Bulk SMS SaaS Structure Generator
echo Creating AlmaTech Bulk SMS SaaS Structure...
echo.

REM ROOT
mkdir almatech_sms
cd almatech_sms

REM CORE FILES
type nul > index.php
type nul > login.php
type nul > logout.php
type nul > dashboard.php
type nul > maintenance.php
type nul > config.php

REM DATABASE
mkdir database
type nul > database/schema.sql
type nul > database/seed.sql

REM INCLUDES
mkdir includes
type nul > includes/bootstrap.php
type nul > includes/db.php
type nul > includes/auth.php
type nul > includes/rbac.php
type nul > includes/helpers.php
type nul > includes/csrf.php
type nul > includes/maintenance.php
type nul > includes/notifications.php
type nul > includes/push.php

REM TEMPLATES
mkdir templates
type nul > templates/header.php
type nul > templates/sidebar.php
type nul > templates/footer.php
type nul > templates/navbar.php

REM ASSETS
mkdir assets
mkdir assets\css
mkdir assets\css\themes
mkdir assets\js
mkdir assets\images
mkdir assets\uploads
mkdir assets\uploads\logos

type nul > assets\css\app.css
type nul > assets\js\app.js

REM THEMES (10 THEMES)
type nul > assets\css\themes\theme_blue.css
type nul > assets\css\themes\theme_green.css
type nul > assets\css\themes\theme_red.css
type nul > assets\css\themes\theme_orange.css
type nul > assets\css\themes\theme_purple.css
type nul > assets\css\themes\theme_teal.css
type nul > assets\css\themes\theme_maroon.css
type nul > assets\css\themes\theme_indigo.css
type nul > assets\css\themes\theme_light.css
type nul > assets\css\themes\theme_dark.css

REM SERVICE WORKER (PUSH)
type nul > sw.js

REM MODULES ROOT
mkdir modules

REM SMS MODULE
mkdir modules\sms
type nul > modules\sms\compose.php
type nul > modules\sms\customized.php
type nul > modules\sms\sent.php
type nul > modules\sms\queue.php
type nul > modules\sms\api.php

REM PHONE BOOK MODULE
mkdir modules\phonebook
type nul > modules\phonebook\groups.php
type nul > modules\phonebook\contacts.php
type nul > modules\phonebook\api.php

REM WALLET / TOPUP MODULE
mkdir modules\wallet
type nul > modules\wallet\buy.php
type nul > modules\wallet\transactions.php
type nul > modules\wallet\transfer.php
type nul > modules\wallet\vouchers.php
type nul > modules\wallet\api.php

REM MESSAGING MODULE
mkdir modules\messaging
type nul > modules\messaging\inbox.php
type nul > modules\messaging\compose.php
type nul > modules\messaging\thread.php
type nul > modules\messaging\api.php

REM NOTIFICATIONS MODULE
mkdir modules\notifications
type nul > modules\notifications\index.php
type nul > modules\notifications\api.php

REM SETTINGS MODULE
mkdir modules\settings
type nul > modules\settings\profile.php
type nul > modules\settings\theme.php
type nul > modules\settings\branding.php
type nul > modules\settings\password.php

REM ADMIN ROOT
mkdir modules\admin

REM ADMIN USERS
mkdir modules\admin\users
type nul > modules\admin\users\index.php
type nul > modules\admin\users\activate.php
type nul > modules\admin\users\deactivate.php
type nul > modules\admin\users\edit.php

REM ADMIN PAYMENTS
mkdir modules\admin\payments
type nul > modules\admin\payments\index.php
type nul > modules\admin\payments\approve.php
type nul > modules\admin\payments\reject.php

REM ADMIN SMS LOGS
mkdir modules\admin\sms_logs
type nul > modules\admin\sms_logs\index.php

REM ADMIN MAINTENANCE
mkdir modules\admin\maintenance
type nul > modules\admin\maintenance\index.php
type nul > modules\admin\maintenance\toggle.php

REM ADMIN BROADCAST
mkdir modules\admin\broadcast
type nul > modules\admin\broadcast\index.php
type nul > modules\admin\broadcast\send.php

echo.
echo AlmaTech Bulk SMS SaaS Structure Created Successfully!
echo.
pause
