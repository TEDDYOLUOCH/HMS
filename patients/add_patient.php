<?php
/**
 * Add Patient - Redirects to Patient Records page with Add modal.
 * Add/View/Delete are now on one page (view_patients.php) via modals.
 */
session_start();
require_once '../includes/auth_check.php';
requireRole(['admin', 'doctor', 'nurse'], '../dashboard');
header('Location: view_patients#add');
exit;
