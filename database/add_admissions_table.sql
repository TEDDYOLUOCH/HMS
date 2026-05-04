-- Admissions table for inpatient management
CREATE TABLE IF NOT EXISTS admissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admission_number VARCHAR(20) NOT NULL UNIQUE,
    patient_id INT NOT NULL,
    ward VARCHAR(50) NOT NULL,
    bed_number VARCHAR(20),
    admission_reason TEXT NOT NULL,
    admission_notes TEXT,
    expected_stay_days INT DEFAULT 1,
    special_requirements TEXT,
    admitted_by INT,
    admission_date DATETIME NOT NULL,
    discharge_date DATETIME,
    status ENUM('Admitted', 'Discharged', 'Transferred', 'Deceased') DEFAULT 'Admitted',
    discharge_summary TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (admitted_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_patient_id (patient_id),
    INDEX idx_admission_number (admission_number),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
