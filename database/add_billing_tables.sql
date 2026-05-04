-- Billing and Payment Tables
-- Run this SQL to create the necessary tables

-- Invoices table
CREATE TABLE IF NOT EXISTS invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(50) UNIQUE NOT NULL,
    patient_id INT NOT NULL,
    patient_name VARCHAR(255),
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0,
    status ENUM('pending', 'partial', 'paid', 'cancelled') DEFAULT 'pending',
    notes TEXT,
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_patient_id (patient_id),
    INDEX idx_invoice_number (invoice_number),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- Invoice Items table
CREATE TABLE IF NOT EXISTS invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    service_name VARCHAR(255) NOT NULL,
    service_type ENUM('consultation', 'lab', 'pharmacy', 'procedure', 'other') DEFAULT 'other',
    quantity INT DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    INDEX idx_invoice_id (invoice_id),
    INDEX idx_service_type (service_type)
);

-- Payments table
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cash', 'mpesa', 'insurance', 'card', 'bank') DEFAULT 'cash',
    reference_number VARCHAR(100),
    paid_by VARCHAR(255),
    notes TEXT,
    recorded_by INT,
    payment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    INDEX idx_invoice_id (invoice_id),
    INDEX idx_payment_method (payment_method),
    INDEX idx_payment_date (payment_date)
);

-- Trigger to generate invoice number
DELIMITER //
CREATE TRIGGER before_invoices_insert
BEFORE INSERT ON invoices
FOR EACH ROW
BEGIN
    IF NEW.invoice_number IS NULL OR NEW.invoice_number = '' THEN
        SET NEW.invoice_number = CONCAT('INV-', DATE_FORMAT(NOW(), '%Y%m%d'), '-', LPAD(NEW.id, 4, '0'));
    END IF;
END //
DELIMITER ;
