-- ========================================================
-- SAMPLE WORKFLOW DATA
-- Run this to test the complete patient flow
-- Use REPLACE or DELETE existing data first if needed
-- ========================================================

USE siwot_hms;

-- ========================================================
-- CLEAR EXISTING SAMPLE DATA (run these first if you want fresh data)
-- ========================================================
-- DELETE FROM dispensing;
-- DELETE FROM prescriptions;
-- DELETE FROM lab_requests;
-- DELETE FROM consultations;
-- DELETE FROM visits WHERE patient_id > 0;
-- DELETE FROM anc_visits;
-- DELETE FROM anc_profiles;
-- DELETE FROM postnatal_care;
-- DELETE FROM vital_signs;
-- DELETE FROM patients WHERE patient_id LIKE 'SIWOT-2026-%';

-- ========================================================
-- SAMPLE USERS (if not exists)
-- ========================================================

INSERT IGNORE INTO users (id, username, password_hash, role, full_name, initials, email, phone, department, is_active, created_by)
VALUES 
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'System Administrator', 'ADM', 'admin@siwot.medical', '0700000000', 'Administration', 1, NULL),
(2, 'nurse1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'nurse', 'Nurse Joy', 'NJ', 'nurse@siwot.medical', '0711111111', 'Nursing', 1, 1),
(3, 'doctor1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', 'Dr. Smith', 'DS', 'doctor@siwot.medical', '0722222222', 'OPD', 1, 1),
(4, 'labtech1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'lab_technician', 'Lab Tech Tom', 'LT', 'lab@siwot.medical', '0733333333', 'Laboratory', 1, 1),
(5, 'pharmacist1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'pharmacist', 'Pharmacist Paul', 'PP', 'pharmacy@siwot.medical', '0744444444', 'Pharmacy', 1, 1);

-- ========================================================
-- SAMPLE DRUGS (if not exists)
-- ========================================================

INSERT IGNORE INTO drug_categories (id, category_name) VALUES
(1, 'Analgesics'), (2, 'Antibiotics'), (3, 'Antimalarials');

INSERT IGNORE INTO drugs (id, drug_code, brand_name, generic_name, category_id, dosage_form, strength, stock_quantity, unit_price, is_active)
VALUES 
(1, 'DRG001', 'Panadol', 'Paracetamol', 1, 'Tablet', '500mg', 1000, 10.00, 1),
(2, 'DRG002', 'Amoxil', 'Amoxicillin', 2, 'Capsule', '250mg', 500, 25.00, 1),
(3, 'DRG003', 'Coartem', 'Artemether/Lumefantrine', 3, 'Tablet', '20/120mg', 200, 50.00, 1),
(4, 'DRG004', 'Brufen', 'Ibuprofen', 1, 'Tablet', '400mg', 300, 15.00, 1),
(5, 'DRG005', 'Augmentin', 'Amoxicillin/Clavulanic', 2, 'Syrup', '125mg/31.25mg', 100, 80.00, 1);

-- ========================================================
-- SAMPLE LAB TEST TYPES (if not exists)
-- ========================================================

INSERT IGNORE INTO lab_test_types (id, test_code, test_name, category, cost, is_active) VALUES
(1, 'CBC', 'Complete Blood Count', 'Hematology', 300.00, 1),
(2, 'URINE', 'Urinalysis', 'Biochemistry', 200.00, 1),
(3, 'BS', 'Blood Sugar', 'Biochemistry', 150.00, 1),
(4, 'Malaria', 'Malaria Rapid Test', 'Parasitology', 200.00, 1);

-- ========================================================
-- CHECK IF PATIENTS ALREADY EXIST
-- ========================================================

-- If patients exist, skip creating new ones
-- Otherwise create sample patients

-- Patient 1: John Doe - Complete OPD Flow
INSERT IGNORE INTO patients (patient_id, first_name, last_name, gender, date_of_birth, phone_primary, address, blood_group, is_active, created_by)
VALUES ('SIWOT-2026-00001', 'John', 'Doe', 'Male', '1985-03-15', '0755123456', 'Nairobi, Kenya', 'O+', 1, 1);

SET @patient1_id = (SELECT id FROM patients WHERE patient_id = 'SIWOT-2026-00001' LIMIT 1);

-- Only create visit if patient exists and no visit today
INSERT IGNORE INTO visits (patient_id, visit_date, visit_time, department, visit_type, status, priority, created_by)
SELECT @patient1_id, CURDATE(), CURTIME(), 'OPD', 'New', 'Waiting', 'Normal', 1
FROM DUAL WHERE @patient1_id IS NOT NULL AND NOT EXISTS (
    SELECT 1 FROM visits WHERE patient_id = @patient1_id AND visit_date = CURDATE()
);

SET @visit1_id = (SELECT id FROM visits WHERE patient_id = @patient1_id AND visit_date = CURDATE() LIMIT 1);

-- Only insert vitals if not exists
INSERT IGNORE INTO vital_signs (patient_id, visit_id, temperature, blood_pressure, pulse, respiratory_rate, weight, recorded_by, recorded_at)
SELECT @patient1_id, @visit1_id, 37.0, '120/80', 72, 16, 70, 2, NOW()
FROM DUAL WHERE @visit1_id IS NOT NULL AND NOT EXISTS (
    SELECT 1 FROM vital_signs WHERE visit_id = @visit1_id
);

-- Update visit status
UPDATE visits SET status = 'In Progress' WHERE id = @visit1_id AND status = 'Waiting';

-- Insert consultation if not exists
INSERT IGNORE INTO consultations (visit_id, doctor_id, chief_complaint, history_of_illness, examination_notes, diagnosis_primary, treatment_plan, created_at)
SELECT @visit1_id, 3, 'Fever and headache for 2 days', 'Patient reports chills and body aches', 'Temperature elevated, throat inflamed', 'Malaria', 'Order malaria test and prescribe antimalarial', NOW()
FROM DUAL WHERE @visit1_id IS NOT NULL AND NOT EXISTS (
    SELECT 1 FROM consultations WHERE visit_id = @visit1_id
);

-- Update visit to Pharmacy
UPDATE visits SET department = 'Pharmacy' WHERE id = @visit1_id;

-- ========================================================
-- SAMPLE PENDING PATIENTS (for testing workflow)
-- ========================================================

-- Patient 4: Peter Kiprotich - Waiting for Vitals
INSERT IGNORE INTO patients (patient_id, first_name, last_name, gender, date_of_birth, phone_primary, address, is_active, created_by)
VALUES ('SIWOT-2026-00004', 'Peter', 'Kiprotich', 'Male', '1978-08-25', '0755333444', 'Eldoret, Kenya', 1, 1);

SET @patient4_id = (SELECT id FROM patients WHERE patient_id = 'SIWOT-2026-00004' LIMIT 1);

INSERT IGNORE INTO visits (patient_id, visit_date, visit_time, department, visit_type, status, priority, created_by)
SELECT @patient4_id, CURDATE(), CURTIME(), 'OPD', 'New', 'Waiting', 'Normal', 1
FROM DUAL WHERE @patient4_id IS NOT NULL;

-- ========================================================
-- VERIFICATION
-- ========================================================

-- Check patients
SELECT patient_id, first_name, last_name FROM patients WHERE patient_id LIKE 'SIWOT-2026-%' ORDER BY patient_id;

-- Check visits
SELECT v.id, p.patient_id, p.first_name, v.department, v.status, v.visit_date
FROM visits v
JOIN patients p ON v.patient_id = p.id
WHERE p.patient_id LIKE 'SIWOT-2026-%'
ORDER BY v.id;
