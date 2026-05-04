-- Add missing 'status' column to users table
-- Run this in phpMyAdmin

ALTER TABLE users ADD COLUMN status ENUM('active', 'inactive') DEFAULT 'active' AFTER is_active;

-- Update existing users to have status = 'active' where is_active = 1
UPDATE users SET status = 'active' WHERE is_active = 1;

-- Update existing users to have status = 'inactive' where is_active = 0
UPDATE users SET status = 'inactive' WHERE is_active = 0;
