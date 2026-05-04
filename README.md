# Hospital Management System (HMS)

A comprehensive PHP-based hospital management system for healthcare facilities.

## Overview

This Hospital Management System provides a complete solution for managing:
- Patient records and registration
- OPD (Outpatient Department) consultations
- Laboratory services and test management
- Pharmacy inventory and prescriptions
- Nursing services (vitals, ANC, postnatal care)
- Theatre/surgical procedures
- Reports and analytics
- User and system administration

## Technology Stack

- **Backend**: PHP 7.4+ with PDO
- **Database**: MySQL/MariaDB
- **Frontend**: HTML5, Tailwind CSS, JavaScript
- **Server**: XAMPP (Apache, MySQL, PHP)

## Installation

### 1. XAMPP Setup

1. Download and install XAMPP from https://www.apachefriends.org/
2. Start Apache and MySQL services
3. Place the project files in `htdocs/hospital-management-system/`

### 2. Database Setup

1. Open phpMyAdmin (http://localhost/phpmyadmin)
2. Create a new database named `siwot_hms`
3. Import the database schema:
   ```bash
   mysql -u root -p siwot_hms < database/hms.sql
   ```

### 3. Configuration

Edit `config/database.php` if needed:
```php
private const DB_HOST = 'localhost';
private const DB_NAME = 'siwot_hms';
private const DB_USER = 'root';
private const DB_PASS = ''; // Your MySQL password
```

### 4. Default Login

- **Username**: admin
- **Password**: admin123

**Change the default password immediately after first login!**

---

## User Roles & Permissions

| Role | Permissions |
|------|-------------|
| Admin | Full system access, user management, reports |
| Doctor | Patient records, consultations, prescriptions, theatre |
| Nurse | Vitals, nursing care, ANC/postnatal |
| Pharmacist | Dispensing, inventory management |
| Lab Tech | Laboratory requests and results |
| Theatre | Surgical procedures management |

---

## Module Documentation

### Patients Module
- Add new patients with comprehensive demographics
- Edit and update patient information
- View patient history
- Delete (archive) patient records

### OPD Module
- Record consultations with diagnoses
- Create prescriptions linked to consultations
- View consultation history

### Laboratory Module
- Create lab test requests
- Record test results
- Generate lab reports

### Pharmacy Module
- View and dispense prescriptions
- Manage drug inventory
- Track stock levels and expiry

### Nursing Module
- Record patient vitals
- ANC (Antenatal Care) visits tracking
- Postnatal care follow-ups

### Theatre Module
- Schedule surgical procedures
- WHO Surgical Safety Checklist
- Operative reports generation

### Reports Module
- Daily statistics and comparisons
- Monthly trends with charts
- Laboratory analytics
- Pharmacy consumption reports

### Admin Module
- User account management
- Activity audit logs
- System settings
- Database backup/restore

---

## Security Features

- CSRF protection on all forms
- Session management with timeout
- SQL injection prevention (prepared statements)
- XSS protection (output encoding)
- Security headers (CSP, HSTS, etc.)
- Role-based access control
- Audit logging for data changes
- Rate limiting for failed attempts

---

## Testing Guide

### 1. Unit Testing - Individual Functions

Test core functions:
```php
// Patient ID generation
// Age calculation from DOB
// Date calculations (EDD, gestational age)
// Password hashing/verification
```

### 2. Integration Testing - Workflows

**OPD → Lab → Pharmacy Flow:**
1. Register new patient
2. Create OPD consultation with diagnosis
3. Request lab tests from consultation
4. Record lab results
5. Create prescription
6. Dispense medication from pharmacy

### 3. Security Testing

**SQL Injection Test:**
```
Username: admin' OR '1'='1
Password: anything
```

**XSS Test:**
```html
<script>alert('XSS')</script>
```

**Privilege Escalation:**
- Try accessing admin pages with non-admin account

### 4. User Acceptance Testing Scenarios

| Role | Test Scenario |
|------|----------------|
| Admin | Create user, view all reports, backup database |
| Doctor | Add patient, create consultation, prescribe medication |
| Nurse | Record vitals, ANC visit, view patient |
| Pharmacist | View prescription, dispense drug, check stock |
| Lab Tech | View requests, record results |
| Theatre | Schedule procedure, record intra-op details |

### 5. Performance Testing

- Test with 20+ concurrent users
- Monitor page load times
- Check database query performance

---

## Troubleshooting

### Common Issues

**"Database connection failed"**
- Check MySQL is running
- Verify database credentials in `config/database.php`
- Ensure database exists in phpMyAdmin

**"Session expired"**
- Check browser cookies are enabled
- Verify session.save_path is writable

**"Access denied"**
- User role doesn't have permission
- Account may be inactive

**"File upload not working"**
- Check PHP upload limits in php.ini
- Verify assets folder is writable

### Error Logs

Check these log files:
- `logs/errors.log` - Application errors
- `logs/security.log` - Security events

---

## Backup & Restore

### Manual Backup
```bash
mysqldump -u root -p siwot_hms > backup_$(date +%Y%m%d).sql
```

### Restore
```bash
mysql -u root -p siwot_hms < backup_file.sql
```

### In-System Backup
Use Admin → System Settings → Backup & Restore

---

## Keyboard Shortcuts

| Shortcut | Action |
|----------|--------|
| Ctrl+S | Save form |
| Ctrl+N | New record |
| Ctrl+F | Search |
| Ctrl+H | Dashboard |
| Ctrl+L | Logout |
| ? | Show help |

---

## Accessibility Features

- High contrast mode (toggle button)
- Font size adjustment
- Keyboard navigation
- Screen reader support
- WCAG 2.1 AA compliant

---

## Support

For issues or questions, contact your system administrator.

---

*Version 1.0 | SIWOT Hospital Management System*
