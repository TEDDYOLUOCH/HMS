<?php
/**
 * Hospital Management System - Simple SMTP Mailer
 * A lightweight SMTP mailer without external dependencies
 */

class SmtpMailer {
    private $host;
    private $port;
    private $username;
    private $password;
    private $encryption;
    private $socket;
    private $debug = false;
    private $lastError = '';
    
    /**
     * Constructor
     */
    public function __construct($config = []) {
        $this->host = $config['host'] ?? 'smtp.gmail.com';
        $this->port = $config['port'] ?? 587;
        $this->username = $config['username'] ?? '';
        $this->password = $config['password'] ?? '';
        $this->encryption = $config['encryption'] ?? 'tls';
    }
    
    /**
     * Get last error message
     */
    public function getLastError() {
        return $this->lastError;
    }
    
    /**
     * Send email
     */
    public function send($to, $subject, $body, $headers = []) {
        $from = $headers['From'] ?? 'lab@siwothospital.org';
        $fromName = $headers['FromName'] ?? 'SIWOT Hospital';
        $replyTo = $headers['Reply-To'] ?? $from;
        
        // Connect to SMTP server
        if (!$this->connect()) {
            $this->lastError = 'Failed to connect to SMTP server: ' . $this->lastError;
            error_log($this->lastError);
            return false;
        }
        
        // SMTP commands - EHLO already sent in connect()
        $response = $this->sendCommand("AUTH LOGIN");
        if (!$this->isSuccessResponse($response) && substr($response, 0, 3) !== '334') {
            $this->lastError = 'AUTH LOGIN failed: ' . $response;
            error_log($this->lastError);
            $this->disconnect();
            return false;
        }
        
        $response = $this->sendCommand(base64_encode($this->username));
        if (!$this->isSuccessResponse($response) && substr($response, 0, 3) !== '334') {
            $this->lastError = 'Username authentication failed: ' . $response;
            error_log($this->lastError);
            $this->disconnect();
            return false;
        }
        
        $response = $this->sendCommand(base64_encode($this->password));
        if (!$this->isSuccessResponse($response) && substr($response, 0, 3) !== '235') {
            $this->lastError = 'Password authentication failed. Please check SMTP credentials in email configuration.';
            error_log($this->lastError . ' | Response: ' . $response);
            $this->disconnect();
            return false;
        }
        
        $response = $this->sendCommand("MAIL FROM:<$from>");
        if (!$this->isSuccessResponse($response)) {
            $this->lastError = 'MAIL FROM failed: ' . $response;
            error_log($this->lastError);
            $this->disconnect();
            return false;
        }
        
        $response = $this->sendCommand("RCPT TO:<$to>");
        if (!$this->isSuccessResponse($response)) {
            $this->lastError = 'RCPT TO failed: ' . $response;
            error_log($this->lastError);
            $this->disconnect();
            return false;
        }
        
        $response = $this->sendCommand("DATA");
        if (!$this->isSuccessResponse($response)) {
            $this->lastError = 'DATA command failed: ' . $response;
            error_log($this->lastError);
            $this->disconnect();
            return false;
        }
        
        // Build email message
        $message = "From: $fromName <$from>\r\n";
        $message .= "To: $to\r\n";
        $message .= "Subject: $subject\r\n";
        $message .= "MIME-Version: 1.0\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Reply-To: $replyTo\r\n";
        $message .= "\r\n";
        $message .= $body;
        $message .= "\r\n.";
        
        $response = $this->sendCommand($message);
        if (!$this->isSuccessResponse($response)) {
            $this->lastError = 'Message send failed: ' . $response;
            error_log($this->lastError);
            $this->disconnect();
            return false;
        }
        
        $this->sendCommand("QUIT");
        $this->disconnect();
        
        return true;
    }
    
    /**
     * Check if SMTP response is successful (2xx or 3xx)
     */
    private function isSuccessResponse($response) {
        if (empty($response)) {
            return false;
        }
        $code = substr(trim($response), 0, 3);
        return ($code[0] === '2' || $code[0] === '3');
    }
    
    /**
     * Connect to SMTP server
     */
    private function connect() {
        $host = $this->host;
        
        // Use SSL/TLS
        if ($this->encryption === 'ssl') {
            $host = 'ssl://' . $host;
        }
        
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);
        
        $errno = 0;
        $errstr = '';
        
        if ($this->encryption === 'tls') {
            $this->socket = @fsockopen($host, $this->port, $errno, $errstr, 30);
        } else {
            $this->socket = @fsockopen($host, $this->port, $errno, $errstr, 30);
        }
        
        if (!$this->socket) {
            $this->lastError = "SMTP Connection failed: $errstr ($errno)";
            return false;
        }
        
        // Read welcome message
        $response = fgets($this->socket, 515);
        
        if (!$this->isSuccessResponse($response)) {
            $this->lastError = 'SMTP welcome failed: ' . $response;
            return false;
        }
        
        // Send EHLO first
        $response = $this->sendCommand("EHLO " . $this->getHelo());
        if (!$this->isSuccessResponse($response)) {
            $this->lastError = 'Initial EHLO failed: ' . $response;
            return false;
        }
        
        // Start TLS if needed
        if ($this->encryption === 'tls') {
            // Check if STARTTLS is supported
            if (strpos($response, 'STARTTLS') === false) {
                $this->lastError = 'STARTTLS not supported by server';
                return false;
            }
            
            $response = $this->sendCommand("STARTTLS");
            // 220 is the success code for STARTTLS
            if (substr(trim($response), 0, 3) !== '220') {
                $this->lastError = 'STARTTLS failed: ' . $response;
                return false;
            }
            
            $crypto = stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if (!$crypto) {
                $this->lastError = 'TLS encryption upgrade failed';
                return false;
            }
            
            // Re-issue EHLO after TLS
            $response = $this->sendCommand("EHLO " . $this->getHelo());
            if (!$this->isSuccessResponse($response)) {
                $this->lastError = 'EHLO after TLS failed: ' . $response;
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Disconnect from SMTP server
     */
    private function disconnect() {
        if ($this->socket) {
            fclose($this->socket);
            $this->socket = null;
        }
    }
    
    /**
     * Send SMTP command
     */
    private function sendCommand($command) {
        fputs($this->socket, $command . "\r\n");
        
        if ($this->debug) {
            echo ">>> $command\n";
        }
        
        // Read response
        $response = '';
        while ($line = fgets($this->socket, 515)) {
            $response .= $line;
            
            if (substr($line, 3, 1) === ' ') {
                break;
            }
        }
        
        if ($this->debug) {
            echo "<<< $response\n";
        }
        
        return $response;
    }
    
    /**
     * Get HELO string
     */
    private function getHelo() {
        return isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    }
    
    /**
     * Enable debug mode
     */
    public function setDebug($debug) {
        $this->debug = $debug;
    }
}

/**
 * Send email using SMTP
 */
function sendEmail($to, $subject, $body, $from = null, $fromName = null) {
    // Load email configuration
    require_once dirname(__DIR__) . '/config/email.php';
    
    $config = getEmailConfig();
    
    // Check if email is enabled
    if (empty($config['enabled'])) {
        error_log("Email disabled in configuration");
        return false;
    }
    
    // Check SMTP credentials
    if (empty($config['smtp']['host']) || empty($config['smtp']['username'])) {
        error_log("SMTP not configured");
        return false;
    }
    
    $from = $from ?? $config['from']['email'];
    $fromName = $fromName ?? $config['from']['name'];
    
    // Create mailer with config
    $mailer = new SmtpMailer($config['smtp']);
    
    $headers = [
        'From' => $from,
        'FromName' => $fromName,
        'Reply-To' => $config['reply_to']['email']
    ];
    
    try {
        $result = $mailer->send($to, $subject, $body, $headers);
        
        if (!$result) {
            $error = $mailer->getLastError();
            error_log("Email send failed: " . $error);
        }
        
        return $result;
    } catch (Exception $e) {
        error_log("Email send exception: " . $e->getMessage());
        return false;
    }
}
