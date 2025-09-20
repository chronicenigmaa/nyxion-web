<?php
// --- CONFIG ---
$to = "info@nyxionlabs.com";
$from_domain = $_SERVER['SERVER_NAME'] ?? 'nyxionlabs.com';
$redirect_ok = "thank-you.html";
$redirect_err = "error.html";

function clean($v){ return trim(filter_var($v, FILTER_SANITIZE_FULL_SPECIAL_CHARS)); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header("Location: $redirect_err"); exit; }

// Honeypot
if (!empty($_POST['website'])) { header("Location: $redirect_ok"); exit; }

$form_name = isset($_POST['form_name']) ? clean($_POST['form_name']) : 'Website Form';
$name = clean($_POST['name'] ?? (($_POST['first_name'] ?? '').' '.($_POST['last_name'] ?? '')));
$email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
$subject_in = clean($_POST['subject'] ?? $form_name);
$message = clean($_POST['message'] ?? '');

if (!$email || empty($message)) { header("Location: $redirect_err"); exit; }

$subject = "Nyxion Labs — {$form_name}: {$subject_in}";
$body = "Form: {$form_name}\n"
      . "Name: {$name}\n"
      . "Email: {$email}\n"
      . "IP: " . ($_SERVER['REMOTE_ADDR'] ?? '') . "\n"
      . "User-Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? '') . "\n\n"
      . "Message:\n{$message}\n";

$headers = [];
$headers[] = "From: Nyxion Labs <no-reply@{$from_domain}>";
$headers[] = "Reply-To: {$email}";
$headers[] = "MIME-Version: 1.0";
$headers[] = "Content-Type: text/plain; charset=UTF-8";

$sent = @mail($to, $subject, $body, implode("\r\n", $headers));
header("Location: " . ($sent ? $redirect_ok : $redirect_err));
exit;
