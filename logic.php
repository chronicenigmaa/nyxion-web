<?php
// Nyxion Labs — Single-file PHP site (index.php)
// Drop this at your web root. Put your logo as ./logo.jpg
// This build is hardened for shared hosts (older PHP, write perms, mail quirks).

/* ================= Settings ================= */
$brand_name   = 'Nyxion Labs';
$contact_to   = 'info@nyxionlabs.com';    // <-- change to your inbox
$contact_from = 'no-reply@nyxionlabs.com'; // domain you can send from (ideally same domain as the site)

/* ================= Safe bootstrap & logging ================= */
@ini_set('log_errors', '1');
@ini_set('display_errors', '0'); // do NOT show errors to visitors
@ini_set('error_log', __DIR__ . '/php_errors.log');
error_reporting(E_ALL);

if (function_exists('mb_internal_encoding')) { @mb_internal_encoding('UTF-8'); }

/* Start session safely (some hosts misconfigure session.save_path) */
if (session_status() === PHP_SESSION_NONE) {
  @session_start();
}

/* CSRF helpers (backwards compatible) */
if (!function_exists('nyx_randbytes')) {
  function nyx_randbytes($len = 16) {
    if (function_exists('random_bytes')) { return random_bytes($len); }
    if (function_exists('openssl_random_pseudo_bytes')) {
      $strong = false; $b = openssl_random_pseudo_bytes($len, $strong);
      if ($b !== false) { return $b; }
    }
    // Fallback (not cryptographic, but fine for CSRF token on small site)
    $out = '';
    for ($i=0; $i<$len; $i++) { $out .= chr(mt_rand(0, 255)); }
    return $out;
  }
}
if (!function_exists('nyx_timing_safe_equals')) {
  function nyx_timing_safe_equals($a, $b) {
    if (function_exists('hash_equals')) return hash_equals($a, $b);
    if (strlen($a) !== strlen($b)) return false;
    $res = 0;
    for ($i=0; $i<strlen($a); $i++) { $res |= ord($a[$i]) ^ ord($b[$i]); }
    return $res === 0;
  }
}

/* Generate CSRF token if missing */
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(nyx_randbytes(16));
}

/* Helpers */
function h($v){ return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
function safe_ip() {
  foreach (array('HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR') as $k) {
    if (!empty($_SERVER[$k])) return substr((string)$_SERVER[$k],0,45);
  }
  return 'unknown';
}

/* ================= Assets (logo autodetect) ================= */
$logo_src = null;
$logo_path = __DIR__ . '/logo.png';
if (file_exists($logo_path)) {
  $mime = (function_exists('mime_content_type') ? @mime_content_type($logo_path) : 'image/png');
  if (!$mime) $mime = 'image/jpeg';
  $data = @file_get_contents($logo_path);
  if ($data !== false) {
    $logo_src = "data:$mime;base64," . base64_encode($data); // inline for <img>, favicon, OG
  }
}
if (!$logo_src) {
  // Fallback SVG if logo.jpg missing/unreadable
  $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" role="img" aria-label="Nyxion Labs"><defs><linearGradient id="g" x1="0" x2="1" y1="0" y2="1"><stop stop-color="#6ee7ff"/><stop offset="1" stop-color="#8b5cf6"/></linearGradient></defs><rect rx="12" width="64" height="64" fill="url(#g)"/></svg>';
  $logo_src = 'data:image/svg+xml;base64,' . base64_encode($svg);
}

/* ================= Contact Form (PRG pattern) ================= */
$errors = array(); $name=''; $email=''; $msg='';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['formid']) && $_POST['formid'] === 'contact')) {
  // Basic anti-bot checks
  $csrf_ok = isset($_POST['csrf']) && nyx_timing_safe_equals($_SESSION['csrf'], $_POST['csrf']);
  if (!$csrf_ok) { $errors[] = 'Session expired. Please try again.'; }
  $hp = trim(isset($_POST['hp']) ? $_POST['hp'] : ''); // honeypot
  if ($hp !== '') { $errors[] = 'Spam detected.'; }

  // Fields
  $name  = trim(isset($_POST['name']) ? $_POST['name'] : '');
  $email = trim(isset($_POST['email']) ? $_POST['email'] : '');
  $msg   = trim(isset($_POST['message']) ? $_POST['message'] : '');

  if ($name === '') { $errors[] = 'Please enter your name.'; }
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = 'Please enter a valid email.'; }
  if ($msg === '') { $errors[] = 'Please write a short message.'; }

  if (!$errors) {
    $subject = "New website inquiry — $brand_name";
    $body = "Name: $name\nEmail: $email\nIP: ".safe_ip()."\n\nMessage:\n$msg\n\n— Sent from nyxionlabs.com";
    $headers = array(
      'From' => $contact_from,
      'Reply-To' => $email,
      'MIME-Version' => '1.0',
      'Content-Type' => 'text/plain; charset=UTF-8'
    );
    $header_str = '';
    foreach ($headers as $k=>$v) { $header_str .= "$k: $v\r\n"; }

    // Many shared hosts reject the 5th param or -f; send without extras
    $sent = @mail($contact_to, $subject, $body, $header_str);

    if (!$sent) {
      // Fallback: log to file so the inquiry is not lost
      $log = __DIR__ . '/inquiries.log';
      $line = "==== ".date('c')." ====\n$subject\n$body\n\n";
      @file_put_contents($log, $line, FILE_APPEND | LOCK_EX);
      $sent = true; // treat as success for UX; you’ll still get it in the log
    }

    if ($sent) {
      // Post/Redirect/Get with thank-you flag
      $base = strtok($_SERVER['REQUEST_URI'], '?');
      @header("Location: {$base}?thanks=1#contact");
      exit;
    } else {
      $errors[] = 'We could not send your message right now. Please email us directly.';
    }
  }
}

$show_thanks = isset($_GET['thanks']);
?>

<?php if ($show_thanks): ?>

<?php elseif (!empty($errors)): ?>

<?php endif; ?>

<?php if(!empty($errors)) echo 'aria-invalid="true"';?>

<?php if(!empty($errors)) echo 'aria-invalid="true"';?>