<?php
/**
 * Hospital Management System - Index
 * Main entry point - redirects to login or dashboard
 */

// Start session
session_start();

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    // Redirect to dashboard
    header('Location: dashboard');
    exit;
} else {
    // Redirect to login
    header('Location: auth/login');
    exit;
}
