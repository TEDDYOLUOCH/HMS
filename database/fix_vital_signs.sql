-- Fix vital_signs table columns (safe to run multiple times)
-- This handles columns that may already exist

-- Check and add blood_pressure column (run individually in phpMyAdmin)
-- Note: Run each ALTER TABLE separately

-- 1. Add blood_pressure if not exists
-- ALTER TABLE vital_signs ADD COLUMN blood_pressure VARCHAR(20) AFTER temperature;

-- 2. Add pulse if not exists  
-- ALTER TABLE vital_signs ADD COLUMN pulse INT AFTER blood_pressure;

-- 3. Add spo2 if not exists
-- ALTER TABLE vital_signs ADD COLUMN spo2 DECIMAL(5,2) AFTER respiratory_rate;

-- 4. Add consciousness if not exists
-- ALTER TABLE vital_signs ADD COLUMN consciousness VARCHAR(20) AFTER pain_score;

-- 5. Add is_critical if not exists
-- ALTER TABLE vital_signs ADD COLUMN is_critical TINYINT(1) DEFAULT 0 AFTER notes;

-- ========================================================
-- SIMPLE FIX - Just run this single query to check structure
-- ========================================================
DESCRIBE vital_signs;
