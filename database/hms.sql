-- ========================================================-- SIWOT MEDICAL CENTRE - HOSPITAL MANAGEMENT SYSTEM-- Database Schema for phpMyAdmin-- Version: 1.0-- Date: February 2026-- ========================================================
-- STEP 1: Create Database (Run this first, or create via phpMyAdmin UI)-- CREATE DATABASE IF NOT EXISTS siwot_hms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;-- USE siwot_hms;

-- ========================================================-- 1. USERS & AUTHENTICATION-- ========================================================

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'doctor', 'lab_technician', 'pharmacist', 'nurse', 'theatre_officer') NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    initials VARCHAR(10) NOT NULL UNIQUE,
    email VARCHAR(100),
    phone VARCHAR(20),
    department VARCHAR(50),
    profile_image VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_role (role),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================-- 2. PATIENT MANAGEMENT-- ========================================================

CREATE TABLE patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id VARCHAR(20) NOT NULL UNIQUE,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    other_names VARCHAR(50),
    date_of_birth DATE NOT NULL,
    gender ENUM('Male', 'Female', 'Other') NOT NULL,
    blood_group ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Unknown') DEFAULT 'Unknown',
    marital_status ENUM('Single', 'Married', 'Divorced', 'Widowed', 'Other'),
    phone_primary VARCHAR(20) NOT NULL,
    phone_secondary VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    county VARCHAR(50),
    sub_county VARCHAR(50),
    ward VARCHAR(50),
    village VARCHAR(50),
    emergency_contact_name VARCHAR(100),
    emergency_contact_phone VARCHAR(20),
    emergency_contact_relationship VARCHAR(50),
    occupation VARCHAR(50),
    employer VARCHAR(100),
    insurance_provider VARCHAR(100),
    insurance_number VARCHAR(50),
    allergies TEXT,
    chronic_conditions TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_patient_id (patient_id),
    INDEX idx_name (last_name, first_name),
    INDEX idx_phone (phone_primary)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================-- 3. VISITS & CONSULTATIONS-- ========================================================

CREATE TABLE visits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    visit_date DATE NOT NULL,
    visit_time TIME NOT NULL,
    department ENUM('OPD', 'Laboratory', 'Pharmacy', 'Nursing', 'MCH', 'Theatre', 'Admin') NOT NULL,
    visit_type ENUM('New', 'Follow-up', 'Referral', 'Emergency') DEFAULT 'New',
    status ENUM('Waiting', 'In Progress', 'Completed', 'Cancelled') DEFAULT 'Waiting',
    priority ENUM('Normal', 'Urgent', 'Emergency') DEFAULT 'Normal',
    chief_complaint TEXT,
    assigned_to INT,
    closed_at DATETIME,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_patient_id (patient_id),
    INDEX idx_visit_date (visit_date),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE consultations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    visit_id INT NOT NULL,
    doctor_id INT NOT NULL,
    chief_complaint TEXT NOT NULL,
    history_of_illness TEXT,
    examination_notes TEXT,
    vital_signs_summary TEXT,
    diagnosis_primary VARCHAR(255),
    diagnosis_secondary TEXT,
    diagnosis_icd10 VARCHAR(20),
    treatment_plan TEXT,
    notes TEXT,
    follow_up_date DATE,
    follow_up_instructions TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_visit_id (visit_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================-- 4. LABORATORY MANAGEMENT-- ========================================================

CREATE TABLE lab_test_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    test_code VARCHAR(20) NOT NULL UNIQUE,
    test_name VARCHAR(100) NOT NULL,
    category ENUM('Hematology', 'Biochemistry', 'Microbiology', 'Immunology', 'Parasitology', 'Histopathology', 'Radiology', 'Other') NOT NULL,
    specimen_type VARCHAR(50),
    reference_range TEXT,
    turn_around_time INT COMMENT 'Hours',
    cost DECIMAL(10,2),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lab_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    visit_id INT NOT NULL,
    patient_id INT NOT NULL,
    test_type_id INT NOT NULL,
    requested_by INT NOT NULL,
    request_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    priority ENUM('Normal', 'Urgent', 'STAT') DEFAULT 'Normal',
    clinical_notes TEXT,
    status ENUM('Pending', 'In Progress', 'Completed', 'Cancelled') DEFAULT 'Pending',
    specimen_collected BOOLEAN DEFAULT FALSE,
    specimen_collected_at DATETIME,
    specimen_collected_by INT,
    completed_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (test_type_id) REFERENCES lab_test_types(id) ON DELETE RESTRICT,
    FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (specimen_collected_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_patient_id (patient_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lab_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    result_value TEXT NOT NULL,
    reference_range TEXT,
    unit VARCHAR(20),
    is_abnormal BOOLEAN DEFAULT FALSE,
    remarks TEXT,
    tested_by INT NOT NULL,
    verified_by INT,
    tested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    verified_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES lab_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (tested_by) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_request_id (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================-- 5. PHARMACY & INVENTORY-- ========================================================

CREATE TABLE drug_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE drugs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    drug_code VARCHAR(20) NOT NULL UNIQUE,
    brand_name VARCHAR(100) NOT NULL,
    generic_name VARCHAR(100) NOT NULL,
    category_id INT,
    dosage_form ENUM('Tablet', 'Capsule', 'Syrup', 'Injection', 'Cream', 'Ointment', 'Drops', 'Inhaler', 'Powder', 'Solution', 'Other') NOT NULL,
    strength VARCHAR(50),
    route_of_administration VARCHAR(50),
    indications TEXT,
    contraindications TEXT,
    side_effects TEXT,
    drug_interactions TEXT,
    stock_quantity INT DEFAULT 0,
    reorder_level INT DEFAULT 10,
    unit_of_measure VARCHAR(20) DEFAULT 'pieces',
    unit_price DECIMAL(10,2) DEFAULT 0.00,
    supplier_name VARCHAR(100),
    expiry_date DATE,
    batch_number VARCHAR(50),
    storage_conditions VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES drug_categories(id) ON DELETE SET NULL,
    INDEX idx_generic_name (generic_name),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE prescriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    visit_id INT NOT NULL,
    patient_id INT NOT NULL,
    prescribed_by INT NOT NULL,
    drug_id INT NOT NULL,
    dosage VARCHAR(50) NOT NULL,
    frequency VARCHAR(50) NOT NULL,
    duration VARCHAR(50) NOT NULL,
    quantity_prescribed INT NOT NULL,
    instructions TEXT,
    status ENUM('Pending', 'Dispensed', 'Partially Dispensed', 'Cancelled') DEFAULT 'Pending',
    prescribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (prescribed_by) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (drug_id) REFERENCES drugs(id) ON DELETE RESTRICT,
    INDEX idx_patient_id (patient_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE dispensing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prescription_id INT NOT NULL,
    dispensed_by INT NOT NULL,
    quantity_dispensed INT NOT NULL,
    batch_number VARCHAR(50),
    expiry_date DATE,
    dispensing_notes TEXT,
    dispensing_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (prescription_id) REFERENCES prescriptions(id) ON DELETE CASCADE,
    FOREIGN KEY (dispensed_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stock_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    drug_id INT NOT NULL,
    movement_type ENUM('Purchase', 'Dispensing', 'Return', 'Adjustment', 'Expired', 'Damaged') NOT NULL,
    quantity INT NOT NULL,
    batch_number VARCHAR(50),
    expiry_date DATE,
    reference_type VARCHAR(50),
    reference_id INT,
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (drug_id) REFERENCES drugs(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_drug_id (drug_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================-- 6. NURSING & VITAL SIGNS-- ========================================================

CREATE TABLE vital_signs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    visit_id INT,
    recorded_by INT NOT NULL,
    temperature DECIMAL(4,1) COMMENT 'Celsius',
    blood_pressure_systolic INT,
    blood_pressure_diastolic INT,
    pulse_rate INT COMMENT 'beats per minute',
    respiratory_rate INT COMMENT 'breaths per minute',
    oxygen_saturation DECIMAL(5,2) COMMENT 'SpO2 %',
    weight DECIMAL(5,2) COMMENT 'kg',
    height DECIMAL(5,2) COMMENT 'cm',
    bmi DECIMAL(4,2),
    pain_score INT COMMENT '0-10 scale',
    consciousness_level ENUM('Alert', 'Verbal', 'Pain', 'Unresponsive'),
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_patient_id (patient_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================-- 7. MATERNAL & CHILD HEALTH (MCH)-- ========================================================

CREATE TABLE anc_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    lmp DATE COMMENT 'Last Menstrual Period',
    edd DATE COMMENT 'Expected Date of Delivery',
    gravida INT DEFAULT 0,
    para INT DEFAULT 0,
    abortus INT DEFAULT 0,
    living_children INT DEFAULT 0,
    blood_group VARCHAR(5),
    rhesus_factor VARCHAR(10),
    hiv_status ENUM('Negative', 'Positive', 'Unknown') DEFAULT 'Unknown',
    syphilis_status ENUM('Negative', 'Positive', 'Unknown') DEFAULT 'Unknown',
    hb_level DECIMAL(4,1),
    risk_factors TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE anc_visits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    anc_profile_id INT NOT NULL,
    visit_number INT NOT NULL,
    gestational_age INT COMMENT 'weeks',
    fundal_height DECIMAL(4,1) COMMENT 'cm',
    fetal_heart_rate INT COMMENT 'bpm',
    fetal_movement BOOLEAN,
    presentation VARCHAR(50),
    blood_pressure_systolic INT,
    blood_pressure_diastolic INT,
    weight DECIMAL(5,2),
    urine_protein VARCHAR(20),
    urine_glucose VARCHAR(20),
    hb_level DECIMAL(4,1),
    pallor BOOLEAN,
    edema VARCHAR(20),
    tt_dose VARCHAR(20),
    ipt_given BOOLEAN,
    iron_folate_given BOOLEAN,
    mebendazole_given BOOLEAN,
    danger_signs TEXT,
    referral_needed BOOLEAN DEFAULT FALSE,
    next_visit_date DATE,
    notes TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (anc_profile_id) REFERENCES anc_profiles(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE postnatal_care (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    delivery_date DATE NOT NULL,
    delivery_place VARCHAR(100),
    delivery_type ENUM('Normal Vaginal', 'Assisted Vaginal', 'C-Section', 'Breech', 'Other'),
    attendant_type ENUM('Doctor', 'Nurse', 'Midwife', 'TBA', 'Other'),
    baby_weight DECIMAL(5,2) COMMENT 'kg',
    baby_gender ENUM('Male', 'Female'),
    apgar_score_1min INT,
    apgar_score_5min INT,
    baby_condition ENUM('Alive', 'Stillbirth', 'Neonatal Death'),
    mother_condition ENUM('Good', 'Fair', 'Poor', 'Critical'),
    complications TEXT,
    visit_day ENUM('Day 1', 'Day 7', 'Day 42', 'Other'),
    visit_date DATE,
    mother_assessment TEXT,
    baby_assessment TEXT,
    breastfeeding_status ENUM('Exclusive', 'Mixed', 'Formula', 'Not Breastfeeding'),
    family_planning_counseling BOOLEAN,
    family_planning_method VARCHAR(50),
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================-- 8. MINI THEATRE / PROCEDURES-- ========================================================

CREATE TABLE theatre_procedures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    visit_id INT,
    procedure_name VARCHAR(200) NOT NULL,
    procedure_category ENUM('Minor Surgery', 'Major Surgery', 'Endoscopy', 'Obstetric', 'Gynecological', 'Orthopedic', 'Other'),
    procedure_date DATE NOT NULL,
    start_time TIME,
    end_time TIME,
    duration_minutes INT,
    surgeon_id INT NOT NULL,
    assistant_surgeon_id INT,
    anesthetist_id INT,
    anesthesia_type ENUM('Local', 'Regional', 'General', 'Sedation', 'None'),
    scrub_nurse_id INT,
    circulating_nurse_id INT,
    pre_op_diagnosis TEXT,
    post_op_diagnosis TEXT,
    procedure_details TEXT,
    findings TEXT,
    complications TEXT,
    blood_loss_ml INT DEFAULT 0,
    fluids_given TEXT,
    specimens_collected TEXT,
    discharge_instructions TEXT,
    status ENUM('Scheduled', 'In Progress', 'Completed', 'Cancelled', 'Postponed') DEFAULT 'Scheduled',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE SET NULL,
    FOREIGN KEY (surgeon_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (assistant_surgeon_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (anesthetist_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (scrub_nurse_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (circulating_nurse_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE theatre_checklist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    procedure_id INT NOT NULL,
    checklist_type ENUM('Sign In', 'Time Out', 'Sign Out'),
    patient_identity_verified BOOLEAN DEFAULT FALSE,
    site_marked BOOLEAN DEFAULT FALSE,
    anesthesia_safety_check BOOLEAN DEFAULT FALSE,
    antibiotic_prophylaxis_given BOOLEAN DEFAULT FALSE,
    essential_imaging_displayed BOOLEAN DEFAULT FALSE,
    instrument_count_correct BOOLEAN DEFAULT FALSE,
    specimen_labeling_correct BOOLEAN DEFAULT FALSE,
    equipment_problems_addressed BOOLEAN DEFAULT FALSE,
    completed_by INT,
    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (procedure_id) REFERENCES theatre_procedures(id) ON DELETE CASCADE,
    FOREIGN KEY (completed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================-- 9. AUDIT & ACTIVITY LOGS-- ========================================================

CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    module VARCHAR(50),
    table_affected VARCHAR(50),
    record_id INT,
    old_values TEXT,
    new_values TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50),
    ip_address VARCHAR(45),
    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    success BOOLEAN DEFAULT FALSE,
    failure_reason VARCHAR(100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================-- 10. SYSTEM CONFIGURATION-- ========================================================

CREATE TABLE system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_group VARCHAR(50),
    description TEXT,
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================-- INSERT SAMPLE DATA-- ========================================================

-- Default Admin User (Password: admin123)
INSERT INTO users (username, password_hash, role, full_name, initials, email, phone, department, is_active, created_by) VALUES (
    'admin',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'admin',
    'System Administrator',
    'ADM',
    'admin@siwot.medical',
    '0700000000',
    'Administration',
    TRUE,
    NULL
);

-- Drug Categories
INSERT INTO drug_categories (category_name, description) VALUES
('Analgesics', 'Pain relief medications'),
('Antibiotics', 'Antibacterial medications'),
('Antimalarials', 'Malaria treatment drugs'),
('Antihistamines', 'Allergy medications'),
('Antacids', 'Gastric acid relief'),
('Vitamins & Supplements', 'Nutritional supplements'),
('Cardiovascular', 'Heart and blood pressure medications'),
('Respiratory', 'Asthma and respiratory medications'),
('Dermatological', 'Skin treatments'),
('Gynecological', 'Women''s health medications'),
('Pediatric', 'Children medications'),
('Emergency Drugs', 'Critical care medications');

-- Sample Drugs
INSERT INTO drugs (drug_code, brand_name, generic_name, category_id, dosage_form, strength, route_of_administration, stock_quantity, reorder_level, unit_price, supplier_name) VALUES
('DRG001', 'Panadol', 'Paracetamol', 1, 'Tablet', '500mg', 'Oral', 500, 50, 2.50, 'KEMSA'),
('DRG002', 'Brufen', 'Ibuprofen', 1, 'Tablet', '400mg', 'Oral', 300, 30, 3.00, 'KEMSA'),
('DRG003', 'Augmentin', 'Amoxicillin/Clavulanate', 2, 'Tablet', '625mg', 'Oral', 200, 20, 15.00, 'KEMSA'),
('DRG004', 'Amoxil', 'Amoxicillin', 2, 'Capsule', '500mg', 'Oral', 400, 40, 8.00, 'KEMSA'),
('DRG005', 'Coartem', 'Artemether/Lumefantrine', 3, 'Tablet', '20/120mg', 'Oral', 600, 60, 12.00, 'KEMSA'),
('DRG006', 'Fansidar', 'Sulfadoxine/Pyrimethamine', 3, 'Tablet', '500/25mg', 'Oral', 150, 15, 5.00, 'KEMSA'),
('DRG007', 'Zyrtec', 'Cetirizine', 4, 'Tablet', '10mg', 'Oral', 250, 25, 6.00, 'KEMSA'),
('DRG008', 'Gaviscon', 'Aluminum Hydroxide', 5, 'Suspension', '200ml', 'Oral', 100, 10, 12.00, 'KEMSA'),
('DRG009', 'Vitamin C', 'Ascorbic Acid', 6, 'Tablet', '500mg', 'Oral', 1000, 100, 1.50, 'KEMSA'),
('DRG010', 'Fersamal', 'Ferrous Sulfate', 6, 'Tablet', '200mg', 'Oral', 800, 80, 2.00, 'KEMSA'),
('DRG011', 'Amlodipine', 'Amlodipine Besylate', 7, 'Tablet', '5mg', 'Oral', 300, 30, 4.00, 'KEMSA'),
('DRG012', 'Nifedipine', 'Nifedipine', 7, 'Tablet', '20mg', 'Oral', 200, 20, 5.00, 'KEMSA'),
('DRG013', 'Salbutamol', 'Salbutamol', 8, 'Inhaler', '100mcg', 'Inhalation', 150, 15, 25.00, 'KEMSA'),
('DRG014', 'Hydrocortisone', 'Hydrocortisone', 9, 'Cream', '1%', 'Topical', 120, 12, 18.00, 'KEMSA'),
('DRG015', 'Gentamicin', 'Gentamicin', 9, 'Cream', '0.1%', 'Topical', 80, 8, 22.00, 'KEMSA'),
('DRG016', 'Folic Acid', 'Folic Acid', 10, 'Tablet', '5mg', 'Oral', 900, 90, 1.00, 'KEMSA'),
('DRG017', 'Paracetamol', 'Paracetamol', 11, 'Syrup', '120mg/5ml', 'Oral', 200, 20, 8.00, 'KEMSA'),
('DRG018', 'ORS', 'Oral Rehydration Salts', 11, 'Sachet', '4.2g', 'Oral', 500, 50, 3.00, 'KEMSA'),
('DRG019', 'Adrenaline', 'Epinephrine', 12, 'Injection', '1mg/ml', 'Injection', 50, 10, 45.00, 'KEMSA'),
('DRG020', 'Atropine', 'Atropine Sulfate', 12, 'Injection', '0.6mg/ml', 'Injection', 40, 8, 35.00, 'KEMSA');

-- Lab Test Types
INSERT INTO lab_test_types (test_code, test_name, category, specimen_type, reference_range, turn_around_time) VALUES
('HB', 'Hemoglobin', 'Hematology', 'Blood', 'Male: 13.5-17.5 g/dL, Female: 12.0-15.5 g/dL', 2),
('WBC', 'White Blood Cell Count', 'Hematology', 'Blood', '4,500-11,000 cells/mcL', 2),
('PLT', 'Platelet Count', 'Hematology', 'Blood', '150,000-450,000/mcL', 2),
('RBC', 'Red Blood Cell Count', 'Hematology', 'Blood', 'Male: 4.5-5.5 million/mcL, Female: 4.0-5.0 million/mcL', 2),
('HCT', 'Hematocrit', 'Hematology', 'Blood', 'Male: 38.8-50.0%, Female: 34.9-44.5%', 2),
('GLU', 'Glucose (Fasting)', 'Biochemistry', 'Blood', '70-100 mg/dL', 4),
('CRE', 'Creatinine', 'Biochemistry', 'Blood', 'Male: 0.74-1.35 mg/dL, Female: 0.59-1.04 mg/dL', 4),
('URE', 'Urea', 'Biochemistry', 'Blood', '7-20 mg/dL', 4),
('ALT', 'Alanine Aminotransferase', 'Biochemistry', 'Blood', '7-56 U/L', 4),
('AST', 'Aspartate Aminotransferase', 'Biochemistry', 'Blood', '10-40 U/L', 4),
('ALP', 'Alkaline Phosphatase', 'Biochemistry', 'Blood', '44-147 IU/L', 4),
('TBL', 'Total Bilirubin', 'Biochemistry', 'Blood', '0.1-1.2 mg/dL', 4),
('MAL', 'Malaria Parasite', 'Parasitology', 'Blood', 'Negative', 2),
('HIV', 'HIV Antibody Test', 'Immunology', 'Blood', 'Non-Reactive', 24),
('HBS', 'Hepatitis B Surface Antigen', 'Immunology', 'Blood', 'Negative', 24),
('HCV', 'Hepatitis C Antibody', 'Immunology', 'Blood', 'Negative', 24),
('VDRL', 'Syphilis Test (VDRL)', 'Immunology', 'Blood', 'Non-Reactive', 4),
('PREG', 'Pregnancy Test', 'Immunology', 'Urine', 'Negative', 1),
('URC', 'Urine Culture', 'Microbiology', 'Urine', 'No growth', 48),
('URS', 'Urine Analysis', 'Biochemistry', 'Urine', 'pH: 4.5-8.0, Specific Gravity: 1.005-1.030', 2);

-- System Settings
INSERT INTO system_settings (setting_key, setting_value, setting_group, description) VALUES
('hospital_name', 'SIWOT Medical Centre', 'general', 'Name of the hospital'),
('hospital_address', 'P.O. Box 123, Siwot Town', 'general', 'Hospital address'),
('hospital_phone', '0700123456', 'general', 'Main contact number'),
('hospital_email', 'info@siwot.medical', 'general', 'Contact email'),
('session_timeout', '30', 'security', 'Session timeout in minutes'),
('max_login_attempts', '3', 'security', 'Maximum failed login attempts'),
('lockout_duration', '15', 'security', 'Account lockout duration in minutes'),
('items_per_page', '25', 'system', 'Default pagination limit'),
('date_format', 'd/m/Y', 'system', 'Date display format'),
-- ========================================================
-- 15. SYSTEM SETTINGS TABLES
-- ========================================================

-- Settings table (key-value store)
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    category VARCHAR(50) DEFAULT 'general',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key),
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Departments table
CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Drug categories table
CREATE TABLE IF NOT EXISTS drug_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (category_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lab test types table
CREATE TABLE IF NOT EXISTS lab_test_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    test_name VARCHAR(100) NOT NULL,
    category VARCHAR(50) DEFAULT 'General',
    normal_range VARCHAR(100),
    unit VARCHAR(20),
    price DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_name (test_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default test types
INSERT INTO lab_test_types (test_name, category, normal_range, unit, price) VALUES
('Hemoglobin', 'Hematology', '12-17', 'g/dL', 150.00),
('Blood Glucose', 'Biochemistry', '70-100', 'mg/dL', 100.00),
('Malaria RDT', 'Parasitology', 'Negative', 'qualitative', 200.00),
('Urinalysis', 'General', 'Normal', 'qualitative', 150.00),
('CBC', 'Hematology', 'Various', '-', 500.00),
('Typhoid', 'Serology', 'Negative', 'qualitative', 350.00),
('Widal Test', 'Serology', '<1:80', 'titer', 300.00);

-- Insert default drug categories
INSERT INTO drug_categories (category_name, description) VALUES
('Analgesics', 'Pain relievers and fever reducers'),
('Antibiotics', 'Antimicrobial medications'),
('Antipyretics', 'Fever reducing medications'),
('Antihistamines', 'Allergy medications'),
('Antidiabetics', 'Diabetes medications'),
('Antihypertensives', 'Blood pressure medications'),
('Vitamins', 'Vitamin supplements'),
('IV Fluids', 'Intravenous solutions');

-- ========================================================
-- 16. SECURITY & AUDIT TABLES
-- ========================================================

-- Data audit log for tracking all modifications
CREATE TABLE IF NOT EXISTS data_audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    table_name VARCHAR(100) NOT NULL,
    record_id INT NOT NULL,
    action ENUM('INSERT', 'UPDATE', 'DELETE') NOT NULL,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_table (table_name),
    INDEX idx_record (record_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Remember me tokens for persistent login
CREATE TABLE IF NOT EXISTS remember_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_token (token),
    INDEX idx_expires (expires)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add user columns if not exist (for password change tracking)
-- ALTER TABLE users ADD COLUMN IF NOT EXISTS must_change_password TINYINT(1) DEFAULT 0;
-- ALTER TABLE users ADD COLUMN IF NOT EXISTS last_password_change DATETIME;



-- ========================================================-- END OF DATABASE SCRIPT-- ========================================================