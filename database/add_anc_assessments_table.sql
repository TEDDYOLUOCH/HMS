-- ANC Assessments Table
-- Run this SQL to create the necessary table

CREATE TABLE IF NOT EXISTS anc_assessments (
    assessment_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    visit_id INT,
    blood_pressure VARCHAR(20),
    weight DECIMAL(5,2),
    urine_test VARCHAR(50),
    hemoglobin DECIMAL(4,1),
    scan_results TEXT,
    ve_results TEXT,
    fetal_heartbeat VARCHAR(50),
    gestation_weeks INT,
    risk_notes TEXT,
    notes TEXT,
    recorded_by INT,
    date_recorded DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_patient_id (patient_id),
    INDEX idx_visit_id (visit_id),
    INDEX idx_recorded_by (recorded_by),
    INDEX idx_date_recorded (date_recorded)
);
