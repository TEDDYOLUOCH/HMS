-- Add unit column to lab_test_types table
ALTER TABLE lab_test_types ADD COLUMN unit VARCHAR(50) AFTER specimen_type;

-- Update existing test types with units
UPDATE lab_test_types SET unit = 'g/dL' WHERE test_name LIKE '%Hemoglobin%' OR test_name LIKE '%HB%';
UPDATE lab_test_types SET unit = 'cells/μL' WHERE test_name LIKE '%WBC%' OR test_name LIKE '%White Blood%';
UPDATE lab_test_types SET unit = 'cells/μL' WHERE test_name LIKE '%Platelets%';
UPDATE lab_test_types SET unit = 'cells/μL' WHERE test_name LIKE '%RBC%' OR test_name LIKE '%Red Blood%';
UPDATE lab_test_types SET unit = '%' WHERE test_name LIKE '%Hematocrit%' OR test_name LIKE '%HCT%';
UPDATE lab_test_types SET unit = 'fL' WHERE test_name LIKE '%MCV%';
UPDATE lab_test_types SET unit = 'pg' WHERE test_name LIKE '%MCH%';
UPDATE lab_test_types SET unit = '%' WHERE test_name LIKE '%Neutrophils%' OR test_name LIKE '%Lymphocytes%' OR test_name LIKE '%Eosinophils%';
UPDATE lab_test_types SET unit = 'mg/dL' WHERE test_name LIKE '%Glucose%' OR test_name LIKE '%Blood Sugar%';
UPDATE lab_test_types SET unit = 'mg/dL' WHERE test_name LIKE '%Cholesterol%';
UPDATE lab_test_types SET unit = 'g/L' WHERE test_name LIKE '%Protein%';
UPDATE lab_test_types SET unit = 'mg/dL' WHERE test_name LIKE '%Creatinine%';
UPDATE lab_test_types SET unit = 'mg/dL' WHERE test_name LIKE '%Urea%';

-- Verify
SELECT test_name, unit, reference_range FROM lab_test_types;
