-- Add is_read column to activity_logs table
ALTER TABLE activity_logs ADD COLUMN is_read TINYINT(1) DEFAULT 0 AFTER created_at;

-- Add index for faster queries
ALTER TABLE activity_logs ADD INDEX idx_is_read (is_read);
