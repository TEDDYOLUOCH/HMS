<?php
/**
 * Hospital Management System - Send Lab Report via Email
 */

// Start session
session_start();

// Check authentication
require_once '../includes/auth_check.php';

// Check role permission
requireRole(['admin', 'lab_technologist', 'lab_scientist', 'doctor'], '../dashboard');

// Handle AJAX email request
header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCsrf($_POST['csrf_token'])) {
        $response['message'] = 'Invalid request';
        echo json_encode($response);
        exit;
    }
    
    $request_id = $_POST['request_id'] ?? 0;
    $recipient_email = trim($_POST['email'] ?? '');
    
    if (empty($request_id)) {
        $response['message'] = 'Request ID is required';
        echo json_encode($response);
        exit;
    }
    
    if (empty($recipient_email) || !filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'Valid email address is required';
        echo json_encode($response);
        exit;
    }
    
    try {
        // Get lab request details
        require_once '../config/database.php';
        $db = Database::getInstance();
        
        $stmt = $db->prepare("SELECT lr.*, p.first_name, p.last_name, p.patient_id, p.gender, p.date_of_birth, p.email as patient_email,
                             u.username as requested_by_name, u2.username as result_by_name,
                             lt.test_name as test_type, lt.unit as test_unit, lt.reference_range as test_ref
                             FROM lab_requests lr
                             JOIN patients p ON lr.patient_id = p.id
                             LEFT JOIN users u ON lr.requested_by = u.id
                             LEFT JOIN users u2 ON lr.result_entered_by = u2.id
                             LEFT JOIN lab_test_types lt ON lr.test_type_id = lt.id
                             WHERE lr.id = ?");
        $stmt->execute([$request_id]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$request) {
            $response['message'] = 'Lab request not found';
            echo json_encode($response);
            exit;
        }
        
        // Build email content
        $patient_name = $request['first_name'] . ' ' . $request['last_name'];
        $test_type = $request['test_type'] ?? 'Laboratory Test';
        $report_date = date('F j, Y g:i A', strtotime($request['completed_at'] ?? $request['created_at']));
        
        // Get default unit and reference range from test type
        $default_unit = $request['test_unit'] ?? '';
        $default_ref = $request['test_ref'] ?? '';
        
        // Decode results if available
        $results_html = '';
        if (!empty($request['results'])) {
            $results = json_decode($request['results'], true);
            if (!empty($results)) {
                $results_html = '<table style="width:100%; border-collapse: collapse; margin-top:10px;">';
                $results_html .= '<tr style="background:#f3f4f6;"><th style="padding:8px; border:1px solid #ddd; text-align:left;">Test</th><th style="padding:8px; border:1px solid #ddd; text-align:left;">Result</th><th style="padding:8px; border:1px solid #ddd; text-align:left;">Unit</th><th style="padding:8px; border:1px solid #ddd; text-align:left;">Reference Range</th></tr>';
                
                foreach ($results as $result) {
                    $test_name = $result['test'] ?? '';
                    $value = $result['value'] ?? '';
                    $unit = $result['unit'] ?? $default_unit;
                    $ref = $result['reference_range'] ?? $default_ref;
                    // Use '-' if still empty
                    if (empty($unit)) $unit = '-';
                    if (empty($ref)) $ref = '-';
                    $results_html .= '<tr>';
                    $results_html .= '<td style="padding:8px; border:1px solid #ddd;">' . htmlspecialchars($test_name) . '</td>';
                    $results_html .= '<td style="padding:8px; border:1px solid #ddd;">' . htmlspecialchars($value) . '</td>';
                    $results_html .= '<td style="padding:8px; border:1px solid #ddd;">' . htmlspecialchars($unit) . '</td>';
                    $results_html .= '<td style="padding:8px; border:1px solid #ddd;">' . htmlspecialchars($ref) . '</td>';
                    $results_html .= '</tr>';
                }
                $results_html .= '</table>';
            }
        }
        
        $subject = "Lab Report - $test_type - $patient_name";
        
        $email_body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #2563eb; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9fafb; }
                .info-table { width: 100%; margin-bottom: 20px; }
                .info-table td { padding: 8px; }
                .label { font-weight: bold; width: 120px; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>SIWOT HOSPITAL</h1>
                    <p>Laboratory Department</p>
                </div>
                <div class='content'>
                    <h2>Laboratory Test Report</h2>
                    <table class='info-table'>
                        <tr>
                            <td class='label'>Patient Name:</td>
                            <td>" . htmlspecialchars($patient_name) . "</td>
                        </tr>
                        <tr>
                            <td class='label'>Patient ID:</td>
                            <td>" . htmlspecialchars($request['patient_id']) . "</td>
                        </tr>
                        <tr>
                            <td class='label'>Test Type:</td>
                            <td>" . htmlspecialchars($test_type) . "</td>
                        </tr>
                        <tr>
                            <td class='label'>Report Date:</td>
                            <td>$report_date</td>
                        </tr>
                        <tr>
                            <td class='label'>Requested By:</td>
                            <td>" . htmlspecialchars($request['requested_by_name'] ?? 'N/A') . "</td>
                        </tr>
                    </table>
                    
                    $results_html
                    
                    " . (!empty($request['notes']) ? '<p><strong>Lab Notes:</strong> ' . htmlspecialchars($request['notes']) . '</p>' : '') . "
                </div>
                <div class='footer'>
                    <p>This is a computer-generated report. No signature required.</p>
                    <p>SIWOT Hospital | P.O. Box 12345, Nairobi, Kenya</p>
                    <p>Tel: +254 700 000 000 | Email: lab@siwothospital.org</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        // Include SMTP mailer
        require_once '../includes/SmtpMailer.php';
        
        // Load email configuration
        require_once '../config/email.php';
        $config = getEmailConfig();
        
        // Send email using SMTP
        $mailer = new SmtpMailer($config['smtp']);
        
        $from = $config['from']['email'];
        $fromName = $config['from']['name'];
        $replyTo = $config['reply_to']['email'];
        
        $headers = [
            'From' => $from,
            'FromName' => $fromName,
            'Reply-To' => $replyTo
        ];
        
        $mailSent = $mailer->send($recipient_email, $subject, $email_body, $headers);
        
        if ($mailSent) {
            $response['success'] = true;
            $response['message'] = 'Lab report sent successfully to ' . htmlspecialchars($recipient_email);
            
            // Log the activity
            try {
                $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, 'Email Lab Report', ?, NOW())");
                $stmt->execute([$_SESSION['user_id'], "Lab report #$request_id sent to $recipient_email"]);
            } catch (Exception $e) {}
        } else {
            $error = $mailer->getLastError();
            $response['message'] = !empty($error) ? $error : 'Failed to send email. Please try again.';
        }
        
    } catch (Exception $e) {
        $response['message'] = 'Error: ' . $e->getMessage();
    }
} else {
    $response['message'] = 'Invalid request method';
}

echo json_encode($response);