-- Simple SQL to add sample lab request data
-- Run this in phpMyAdmin

-- First check your existing IDs:
-- SELECT id FROM patients LIMIT 1;
-- SELECT id FROM lab_test_types LIMIT 1;  
-- SELECT id FROM users LIMIT 1;

-- Then run this INSERT with NULL for visit_id:
INSERT INTO lab_requests (visit_id, patient_id, test_type_id, requested_by, request_date, priority, clinical_notes, status, specimen_collected, completed_at) VALUES
(NULL, 1, 1, 1, NOW() - INTERVAL 1 DAY, 'Normal', 'Routine blood count', 'Completed', 1, NOW() - INTERVAL 1 DAY + INTERVAL 2 HOUR),
(NULL, 1, 6, 1, NOW() - INTERVAL 2 DAY, 'Normal', 'Fasting glucose check', 'Completed', 1, NOW() - INTERVAL 2 DAY + INTERVAL 4 HOUR),
(NULL, 1, 11, 1, NOW() - INTERVAL 3 DAY, 'Urgent', 'Liver function evaluation', 'Completed', 1, NOW() - INTERVAL 3 DAY + INTERVAL 6 HOUR),
(NULL, 1, 2, 1, NOW() - INTERVAL 4 DAY, 'Normal', 'Post-surgery monitoring', 'Completed', 1, NOW() - INTERVAL 4 DAY + INTERVAL 3 HOUR),
(NULL, 1, 7, 1, NOW() - INTERVAL 5 DAY, 'Normal', 'Kidney function test', 'Completed', 1, NOW() - INTERVAL 5 DAY + INTERVAL 5 HOUR),
(NULL, 1, 12, 1, NOW() - INTERVAL 6 DAY, 'STAT', 'Emergency malaria test', 'Completed', 1, NOW() - INTERVAL 6 DAY + INTERVAL 1 HOUR),
(NULL, 1, 3, 1, NOW() - INTERVAL 7 DAY, 'Normal', 'Pre-surgery platlet count', 'Completed', 1, NOW() - INTERVAL 7 DAY + INTERVAL 2 HOUR),
(NULL, 1, 15, 1, NOW() - INTERVAL 8 DAY, 'Normal', 'Routine pregnancy test', 'Completed', 1, NOW() - INTERVAL 8 DAY + INTERVAL 1 HOUR),
(NULL, 1, 8, 1, NOW() - INTERVAL 9 DAY, 'Normal', 'Urea nitrogen test', 'Completed', 1, NOW() - INTERVAL 9 DAY + INTERVAL 4 HOUR),
(NULL, 1, 4, 1, NOW() - INTERVAL 10 DAY, 'Normal', 'Anemia screening', 'Completed', 1, NOW() - INTERVAL 10 DAY + INTERVAL 3 HOUR),
(NULL, 1, 9, 1, NOW() - INTERVAL 11 DAY, 'Urgent', 'Jaundice workup', 'Completed', 1, NOW() - INTERVAL 11 DAY + INTERVAL 5 HOUR),
(NULL, 1, 10, 1, NOW() - INTERVAL 12 DAY, 'Normal', 'Routine ALT test', 'Completed', 1, NOW() - INTERVAL 12 DAY + INTERVAL 4 HOUR),
(NULL, 1, 5, 1, NOW() - INTERVAL 13 DAY, 'Normal', 'Hematocrit levels', 'Completed', 1, NOW() - INTERVAL 13 DAY + INTERVAL 2 HOUR),
(NULL, 1, 13, 1, NOW() - INTERVAL 14 DAY, 'Normal', 'HIV screening', 'Completed', 1, NOW() - INTERVAL 14 DAY + INTERVAL 24 HOUR),
(NULL, 1, 14, 1, NOW() - INTERVAL 15 DAY, 'Normal', 'Hepatitis B check', 'Completed', 1, NOW() - INTERVAL 15 DAY + INTERVAL 24 HOUR),
(NULL, 1, 16, 1, NOW() - INTERVAL 16 DAY, 'Normal', 'Syphilis screening', 'Completed', 1, NOW() - INTERVAL 16 DAY + INTERVAL 4 HOUR),
(NULL, 1, 17, 1, NOW() - INTERVAL 17 DAY, 'Normal', 'Urine analysis', 'Completed', 1, NOW() - INTERVAL 17 DAY + INTERVAL 2 HOUR),
(NULL, 1, 18, 1, NOW() - INTERVAL 18 DAY, 'STAT', 'Emergency urine culture', 'Completed', 1, NOW() - INTERVAL 18 DAY + INTERVAL 48 HOUR),
(NULL, 1, 1, 1, NOW() - INTERVAL 19 DAY, 'Normal', 'Follow-up hemoglobin', 'Pending', 1, NULL),
(NULL, 1, 6, 1, NOW() - INTERVAL 20 DAY, 'Normal', 'Diabetes monitoring', 'Pending', 1, NULL),
(NULL, 1, 2, 1, NOW() - INTERVAL 21 DAY, 'Normal', 'WBC count', 'In Progress', 1, NULL),
(NULL, 1, 11, 1, NOW() - INTERVAL 22 DAY, 'Normal', 'Bilirubin test', 'In Progress', 1, NULL),
(NULL, 1, 7, 1, NOW() - INTERVAL 23 DAY, 'Normal', 'Creatinine test', 'Rejected', 0, NULL);
