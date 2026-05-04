-- Laboratory Stock Management Tables
-- Run this SQL to create the necessary tables

-- Lab Stock table for reagents and supplies
CREATE TABLE IF NOT EXISTS lab_stock (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_name VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    unit VARCHAR(50) NOT NULL,
    expiry_date DATE,
    supplier VARCHAR(255),
    added_by INT,
    date_added DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    low_stock_threshold INT DEFAULT 10,
    INDEX idx_item_name (item_name),
    INDEX idx_category (category),
    INDEX idx_expiry_date (expiry_date)
);

-- Lab Stock Usage table for tracking consumption
CREATE TABLE IF NOT EXISTS lab_stock_usage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    stock_id INT NOT NULL,
    quantity_used INT NOT NULL,
    lab_request_id INT,
    test_type VARCHAR(255),
    used_by INT NOT NULL,
    usage_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    FOREIGN KEY (stock_id) REFERENCES lab_stock(id) ON DELETE CASCADE,
    INDEX idx_stock_id (stock_id),
    INDEX idx_usage_date (usage_date)
);

-- Insert sample lab stock items
INSERT INTO lab_stock (item_name, category, quantity, unit, expiry_date, supplier, low_stock_threshold) VALUES
('Reagent A - CBC', 'Reagents', 50, 'tests', '2026-12-31', 'MedSupply Co', 10),
('Reagent B - Chemistry', 'Reagents', 30, 'tests', '2026-06-30', 'LabTech Solutions', 10),
('Blood Collection Tubes', 'Consumables', 200, 'pieces', '2027-06-30', 'Medical Supplies Ltd', 50),
('Glucose Strips', 'Test Strips', 100, 'strips', '2026-09-30', 'Diagnostics Inc', 20),
('Urine Containers', 'Consumables', 150, 'pieces', '2027-12-31', 'MedSupply Co', 30),
('Centrifuge Cups', 'Consumables', 40, 'pieces', '2027-06-30', 'LabTech Solutions', 10),
('Microscope Slides', 'Consumables', 500, 'pieces', '2027-12-31', 'Medical Supplies Ltd', 100),
('Staining Solutions', 'Reagents', 25, 'ml', '2026-08-31', 'Diagnostics Inc', 5);
