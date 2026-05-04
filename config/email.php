<?php
/**
 * Hospital Management System - Email Configuration
 * Configure SMTP settings for sending emails
 */

// Email settings - Update these for your mail server
$email_config = [
    // SMTP Settings
    'smtp' => [
        'host' => 'smtp.gmail.com',        // SMTP server host
        'port' => 587,                     // SMTP port (587 for TLS, 465 for SSL)
        'username' => 'oluochteddyochieng@gmail.com',  // SMTP username (email address)
        'password' => 'giloaasnphkrozok', // SMTP password or app password
        'encryption' => 'tls',             // 'tls' or 'ssl'
    ],
    
    // From address (sender)
    'from' => [
        'email' => 'lab@siwothospital.org',
        'name' => 'SIWOT Hospital Laboratory'
    ],
    
    // Reply-to address
    'reply_to' => [
        'email' => 'lab@siwothospital.org',
        'name' => 'SIWOT Hospital Laboratory'
    ],
    
    // Enable/disable email (for testing)
    'enabled' => true
];

/**
 * Get email configuration
 */
function getEmailConfig() {
    global $email_config;
    return $email_config;
}
