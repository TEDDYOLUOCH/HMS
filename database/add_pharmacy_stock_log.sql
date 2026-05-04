-- Add missing pharmacy_stock_log table
-- Run this in phpMyAdmin

CREATE TABLE IF NOT EXISTS pharmacy_stock_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    drug_id INT NOT NULL,
    adjustment INT NOT NULL COMMENT 'Positive for additions, negative for deductions',
    reason VARCHAR(255),
    adjusted_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (drug_id) REFERENCES drugs(id) ON DELETE CASCADE,
    FOREIGN KEY (adjusted_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_drug_id (drug_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
