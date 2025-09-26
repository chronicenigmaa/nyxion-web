<?php
/**
 * Nyxion Labs - Secure Contact Form Handler
 * Enhanced security, validation, and error handling
 */

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Configuration
$config = [
    'brand_name' => 'Nyxion Labs',
    'contact_to' => 'info@nyxionlabs.com',
    'contact_from' => 'no-reply@nyxionlabs.com',
    'rate_limit' => 5, // submissions per hour per IP
    'max_message_length' => 2000,
    'allowed_origins' => ['nyxionlabs.com', 'www.nyxionlabs.com'],
    'honeypot_field' => 'hp',
    'debug_mode' => false // Set to true for development
];

// Error reporting
if ($config['debug_mode']) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/error.log');
    error_reporting(E_ALL);
}

// Start session securely
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 0,
        'cookie_secure' => isset($_SERVER['HTTPS']),
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict'
    ]);
}

/**
 * Security Functions
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function sanitizeInput($input, $maxLength = null) {
    $cleaned = trim(strip_tags($input));
    if ($maxLength && strlen($cleaned) > $maxLength) {
        $cleaned = substr($cleaned, 0, $maxLength);
    }
    return $cleaned;
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) && 
           strlen($email) <= 254 && 
           !preg_match('/[<>]/', $email);
}

function getClientIP() {
    $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    
    foreach ($ipKeys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = trim(explode(',', $_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function checkRateLimit($ip, $limit = 5) {
    $logFile = __DIR__ . '/rate_limit.log';
    $now = time();
    $hourAgo = $now - 3600;
    
    // Read existing logs
    $logs = [];
    if (file_exists($logFile)) {
        $content = file_get_contents($logFile);
        if ($content) {
            $logs = array_filter(explode("\n", $content));
        }
    }
    
    // Filter recent submissions for this IP
    $recentSubmissions = 0;
    $validLogs = [];
    
    foreach ($logs as $log) {
        $parts = explode('|', $log);
        if (count($parts) >= 2) {
            $timestamp = intval($parts[0]);
            $logIp = $parts[1];
            
            // Keep logs from the last hour
            if ($timestamp > $hourAgo) {
                $validLogs[] = $log;
                if ($logIp === $ip) {
                    $recentSubmissions++;
                }
            }
        }
    }
    
    // Check rate limit
    if ($recentSubmissions >= $limit) {
        return false;
    }
    
    // Add current submission to log
    $validLogs[] = $now . '|' . $ip;
    file_put_contents($logFile, implode("\n", $validLogs) . "\n", LOCK_EX);
    
    return true;
}

function logSubmission($data) {
    $logEntry = [
        'timestamp' => date('c'),
        'ip' => getClientIP(),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'name' => $data['name'] ?? '',
        'email' => $data['email'] ?? '',
        'form_type' => $data['form_type'] ?? 'contact',
        'message_length' => strlen($data['message'] ?? ''),
        'success' => $data['success'] ?? false
    ];
    
    $logFile = __DIR__ . '/submissions.log';
    file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);
}

function sendEmail($to, $subject, $body, $fromEmail, $replyToEmail = null) {
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'From: ' . $fromEmail,
        'X-Mailer: PHP/' . phpversion(),
        'X-Priority: 3',
        'X-MSMail-Priority: Normal'
    ];
    
    if ($replyToEmail && validateEmail($replyToEmail)) {
        $headers[] = 'Reply-To: ' . $replyToEmail;
    }
    
    // Additional security headers
    $headers[] = 'X-Spam-Score: 0';
    $headers[] = 'X-Source-IP: ' . getClientIP();
    
    return mail($to, $subject, $body, implode("\r\n", $headers));
}

/**
 * Main Processing
 */
$response = ['success' => false, 'message' => '', 'errors' => []];

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Rate limiting
$clientIP = getClientIP();
if (!checkRateLimit($clientIP, $config['rate_limit'])) {
    http_response_code(429);
    $response['message'] = 'Too many requests. Please try again later.';
    echo json_encode($response);
    exit;
}

// CSRF validation
$csrfToken = $_POST['csrf_token'] ?? $_POST['csrf'] ?? '';
if (!validateCSRFToken($csrfToken)) {
    http_response_code(403);
    $response['message'] = 'Invalid security token. Please refresh and try again.';
    echo json_encode($response);
    exit;
}

// Honeypot check
if (!empty($_POST[$config['honeypot_field']])) {
    http_response_code(400);
    $response['message'] = 'Spam detected.';
    logSubmission(['success' => false, 'ip' => $clientIP, 'reason' => 'honeypot']);
    echo json_encode($response);
    exit;
}

// Validate required fields
$requiredFields = ['name', 'email', 'message'];
$data = [];

foreach ($requiredFields as $field) {
    $value = sanitizeInput($_POST[$field] ?? '');
    
    if (empty($value)) {
        $response['errors'][$field] = ucfirst($field) . ' is required.';
        continue;
    }
    
    // Field-specific validation
    switch ($field) {
        case 'email':
            if (!validateEmail($value)) {
                $response['errors'][$field] = 'Please enter a valid email address.';
            }
            break;
        case 'name':
            if (strlen($value) < 2) {
                $response['errors'][$field] = 'Name must be at least 2 characters.';
            }
            break;
        case 'message':
            if (strlen($value) < 10) {
                $response['errors'][$field] = 'Message must be at least 10 characters.';
            } elseif (strlen($value) > $config['max_message_length']) {
                $response['errors'][$field] = 'Message is too long.';
            }
            break;
    }
    
    $data[$field] = $value;
}

// Optional fields
$optionalFields = ['company', 'role', 'phone', 'form_type', 'company_size', 'primary_interest', 'challenge', 'package'];
foreach ($optionalFields as $field) {
    if (isset($_POST[$field])) {
        $data[$field] = sanitizeInput($_POST[$field], 500);
    }
}

// If there are validation errors, return them
if (!empty($response['errors'])) {
    http_response_code(400);
    $response['message'] = 'Please correct the errors below.';
    echo json_encode($response);
    exit;
}

// Build email content
$formType = $data['form_type'] ?? 'contact';
$subject = "New " . ucwords(str_replace('_', ' ', $formType)) . " Inquiry — " . $config['brand_name'];

$emailBody = "New inquiry from " . $config['brand_name'] . " website\n";
$emailBody .= "==========================================\n\n";
$emailBody .= "Name: " . $data['name'] . "\n";
$emailBody .= "Email: " . $data['email'] . "\n";

if (!empty($data['company'])) {
    $emailBody .= "Company: " . $data['company'] . "\n";
}

if (!empty($data['role'])) {
    $emailBody .= "Role: " . $data['role'] . "\n";
}

if (!empty($data['phone'])) {
    $emailBody .= "Phone: " . $data['phone'] . "\n";
}

if (!empty($data['company_size'])) {
    $emailBody .= "Company Size: " . $data['company_size'] . "\n";
}

if (!empty($data['primary_interest'])) {
    $emailBody .= "Primary Interest: " . ucwords(str_replace('_', ' ', $data['primary_interest'])) . "\n";
}

if (!empty($data['package'])) {
    $emailBody .= "Package Interest: " . ucwords($data['package']) . "\n";
}

$emailBody .= "\nMessage:\n" . $data['message'] . "\n\n";

$emailBody .= "---\n";
$emailBody .= "Form Type: " . $formType . "\n";
$emailBody .= "IP Address: " . $clientIP . "\n";
$emailBody .= "User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown') . "\n";
$emailBody .= "Timestamp: " . date('c') . "\n";
$emailBody .= "Submitted from: " . ($_SERVER['HTTP_REFERER'] ?? 'unknown') . "\n";

// Send email
try {
    $emailSent = sendEmail(
        $config['contact_to'],
        $subject,
        $emailBody,
        $config['contact_from'],
        $data['email']
    );
    
    if ($emailSent) {
        $response['success'] = true;
        $response['message'] = 'Thank you! Your message has been sent successfully.';
        
        // Log successful submission
        $data['success'] = true;
        logSubmission($data);
        
        // Send auto-response (optional)
        $autoResponseSubject = "Thank you for contacting " . $config['brand_name'];
        $autoResponseBody = "Hi " . $data['name'] . ",\n\n";
        $autoResponseBody .= "Thank you for your interest in " . $config['brand_name'] . ". We've received your message and will get back to you within 24 hours.\n\n";
        $autoResponseBody .= "Best regards,\n";
        $autoResponseBody .= "The " . $config['brand_name'] . " Team\n\n";
        $autoResponseBody .= "---\n";
        $autoResponseBody .= "This is an automated response. Please do not reply to this email.";
        
        sendEmail(
            $data['email'],
            $autoResponseSubject,
            $autoResponseBody,
            $config['contact_from']
        );
        
    } else {
        throw new Exception('Failed to send email');
    }
    
} catch (Exception $e) {
    error_log('Email sending failed: ' . $e->getMessage());
    
    // Fallback: save to file
    $fallbackFile = __DIR__ . '/inquiries_fallback.log';
    $fallbackEntry = date('c') . " | " . json_encode($data) . "\n";
    file_put_contents($fallbackFile, $fallbackEntry, FILE_APPEND | LOCK_EX);
    
    $response['success'] = false;
    $response['message'] = 'Sorry, there was an issue sending your message. Please try again or email us directly.';
    
    // Log failed submission
    $data['success'] = false;
    $data['error'] = $e->getMessage();
    logSubmission($data);
}

// Handle AJAX vs form submission
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    
    // AJAX request
    header('Content-Type: application/json');
    echo json_encode($response);
} else {
    // Regular form submission - redirect
    $redirectUrl = $response['success'] ? 'thank-you.html' : 'error.html';
    
    // Check for custom redirect URLs
    if ($response['success'] && !empty($_POST['redirect_success'])) {
        $redirectUrl = $_POST['redirect_success'];
    } elseif (!$response['success'] && !empty($_POST['redirect_error'])) {
        $redirectUrl = $_POST['redirect_error'];
    }
    
    header('Location: ' . $redirectUrl);
    exit;
}

// Generate new CSRF token for next request
generateCSRFToken();
?>