-- Add missing columns to users table for password change tracking
-- Run this SQL to fix the missing column error

ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) DEFAULT 0;
ALTER TABLE users ADD COLUMN last_password_change DATETIME DEFAULT NULL;
