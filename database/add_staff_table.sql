-- Staff Management Table
-- Run this SQL to create the necessary table

CREATE TABLE IF NOT EXISTS staff (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_number VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    role VARCHAR(100) NOT NULL,
    department VARCHAR(100),
    phone VARCHAR(50),
    email VARCHAR(255),
    date_joined DATE NOT NULL,
    status ENUM('active', 'inactive', 'on_leave', 'suspended') DEFAULT 'active',
    user_account_id INT,
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_staff_number (staff_number),
    INDEX idx_role (role),
    INDEX idx_department (department),
    INDEX idx_status (status)
);
