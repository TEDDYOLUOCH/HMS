-- Referrals table for patient referrals
CREATE TABLE IF NOT EXISTS referrals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    consultation_id INT,
    refer_to_department VARCHAR(50),
    refer_to_facility VARCHAR(100),
    refer_reason TEXT NOT NULL,
    refer_notes TEXT,
    urgency ENUM('Normal', 'Urgent', 'Emergency') DEFAULT 'Normal',
    referred_by INT,
    referral_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('Pending', 'Completed', 'Cancelled') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (consultation_id) REFERENCES consultations(id) ON DELETE SET NULL,
    FOREIGN KEY (referred_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_patient_id (patient_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
