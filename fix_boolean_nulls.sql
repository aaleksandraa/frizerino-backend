-- Fix Boolean NULL Values and Set Defaults
-- Run this script to fix existing NULL values in boolean columns
-- Date: 2025-12-27

-- Users table
UPDATE users SET is_guest = false WHERE is_guest IS NULL;

-- Appointments table
UPDATE appointments SET is_guest = false WHERE is_guest IS NULL;

-- Salon Settings table
UPDATE salon_settings
SET daily_report_enabled = false
WHERE daily_report_enabled IS NULL;

UPDATE salon_settings
SET email_notifications_enabled = true
WHERE email_notifications_enabled IS NULL;

UPDATE salon_settings
SET daily_report_include_staff = true
WHERE daily_report_include_staff IS NULL;

UPDATE salon_settings
SET daily_report_include_services = true
WHERE daily_report_include_services IS NULL;

UPDATE salon_settings
SET daily_report_include_capacity = true
WHERE daily_report_include_capacity IS NULL;

UPDATE salon_settings
SET daily_report_include_cancellations = true
WHERE daily_report_include_cancellations IS NULL;

-- Widget Settings table
UPDATE widget_settings
SET is_active = true
WHERE is_active IS NULL;

-- Verify changes
SELECT 'Users with NULL is_guest:' as check_name, COUNT(*) as count FROM users WHERE is_guest IS NULL
UNION ALL
SELECT 'Appointments with NULL is_guest:', COUNT(*) FROM appointments WHERE is_guest IS NULL
UNION ALL
SELECT 'Salon settings with NULL daily_report_enabled:', COUNT(*) FROM salon_settings WHERE daily_report_enabled IS NULL
UNION ALL
SELECT 'Widget settings with NULL is_active:', COUNT(*) FROM widget_settings WHERE is_active IS NULL;
