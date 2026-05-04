-- Create departments table
CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default departments
INSERT INTO departments (name, description, status) VALUES 
('Administration', 'Administrative department', 'active'),
('Emergency', 'Emergency and casualty department', 'active'),
('Outpatient', 'Outpatient department (OPD)', 'active'),
('Inpatient', 'Inpatient department (IPD)', 'active'),
('Pharmacy', 'Pharmacy and dispensing', 'active'),
('Laboratory', 'Laboratory services', 'active'),
('Radiology', 'Radiology and imaging', 'active'),
('Theatre', 'Surgical theatre', 'active'),
('Nursing', 'Nursing services', 'active')
ON DUPLICATE KEY UPDATE name = VALUES(name);
