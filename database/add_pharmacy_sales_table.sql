-- Walk-in Pharmacy Sales Table
-- Run this SQL to create the necessary table

CREATE TABLE IF NOT EXISTS pharmacy_sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(255),
    customer_phone VARCHAR(50),
    drug_name VARCHAR(255) NOT NULL,
    drug_id INT,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    sold_by INT NOT NULL,
    sale_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    payment_method VARCHAR(50) DEFAULT 'Cash',
    notes TEXT,
    INDEX idx_sale_date (sale_date),
    INDEX idx_sold_by (sold_by)
);
