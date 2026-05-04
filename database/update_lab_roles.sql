-- Update lab roles: Replace "Laboratory Technician" with two new roles
-- Run this SQL to update the database

-- Note: This script will:
-- 1. Add new roles: lab_technologist and lab_scientist
-- 2. Users with existing lab_technician role can be reassigned to one of the new roles

-- Example: Update existing users to new roles (uncomment and modify as needed)
-- UPDATE users SET role = 'lab_technologist' WHERE role = 'lab_technician';
-- UPDATE users SET role = 'lab_scientist' WHERE role = 'lab_technician';

-- The new roles will work with the existing role-based access control
-- Both roles will have access to laboratory functions
