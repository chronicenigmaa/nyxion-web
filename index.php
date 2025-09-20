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
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Nyxion Labs — Applied AI for Ambitious Teams</title>
  <meta name="description" content="Nyxion Labs helps companies design, build, and operate reliable AI systems—automation, assistants, analytics, integrations, and Azure ecosystems—delivered with strong security and governance." />
  <meta name="theme-color" content="#0b1220" />

  <!-- Favicon & social (inline so it always shows) -->
  <link rel="icon" href="<?=$logo_src?>" sizes="48x48" />
  <link rel="apple-touch-icon" href="<?=$logo_src?>" />
  <meta property="og:title" content="Nyxion Labs" />
  <meta property="og:description" content="Applied AI for ambitious teams." />
  <meta property="og:image" content="<?=$logo_src?>" />

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Space+Grotesk:wght@400;600;700&display=swap" rel="stylesheet">

  <style>
    :root{
      --bg:#0b1020;--bg-soft:#0f1530;--card:#111735;--muted:#8ea0c6;--text:#e8eeff;
      --brand:#6ee7ff;--brand-2:#8b5cf6;--accent:#00f5a0;--focus:#9bdcff;--ring:0 0 0 3px rgba(110,231,255,.35)
    }
    html,body{
      background:linear-gradient(180deg,#070a16 0%,#0b1020 30%,#0b1020 100%);
      color:var(--text);font-family:Inter,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif;scroll-behavior:smooth
    }
    *{box-sizing:border-box}
    img{max-width:100%;height:auto}
    a{color:inherit;text-decoration:none}
    a:focus-visible, button:focus-visible, input:focus-visible, textarea:focus-visible {outline:none;box-shadow:var(--ring);border-radius:10px}
    .skip{position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden}
    .skip:focus{left:12px;top:12px;width:auto;height:auto;background:#09102a;color:#fff;padding:10px 12px;border-radius:10px;z-index:9999}

    .container{max-width:1200px;margin:0 auto;padding:0 20px}
    .nav{position:sticky;top:0;z-index:50;background:rgba(8,12,28,.7);backdrop-filter: blur(10px);
         border-bottom:1px solid rgba(255,255,255,.06)}
    .nav-inner{display:flex;align-items:center;justify-content:space-between;gap:12px;min-height:70px}
    .brand{display:flex;align-items:center;gap:12px;font-weight:800;letter-spacing:.3px}
    .logo-img{height:42px;width:auto;border-radius:10px;box-shadow:0 6px 18px rgba(139,92,246,.25)}
    .btn{display:inline-flex;align-items:center;gap:10px;padding:12px 16px;border-radius:14px;background:linear-gradient(135deg,var(--brand) 0%,var(--brand-2) 60%);color:#04101f;font-weight:800;border:1px solid rgba(255,255,255,.15);box-shadow:0 6px 20px rgba(110,231,255,.15);transition:transform .2s}
    .btn:hover{transform:translateY(-2px)}
    .ghost{background:transparent;color:var(--text);border:1px solid rgba(255,255,255,.18)}
    .menu-toggle{display:none;border:1px solid rgba(255,255,255,.18);background:#0d1430;color:#fff;border-radius:12px;padding:10px 12px}
    .menu-toggle[aria-expanded="true"]{box-shadow:var(--ring)}

    /* Hero */
    .hero{position:relative;overflow:hidden}
    .hero .grid{display:grid;grid-template-columns:1fr;gap:22px;align-items:center;padding:56px 0 24px}
    .eyebrow{display:inline-flex;gap:10px;align-items:center;padding:6px 10px;border-radius:999px;border:1px solid rgba(255,255,255,.15);font-size:12px;letter-spacing:.4px;color:var(--muted)}
    h1{font-family:"Space Grotesk",Inter,system-ui,sans-serif;font-size:42px;line-height:1.15;margin:14px 0}
    p.lead{font-size:18px;color:#c7d4ff;opacity:.9}
    .hero-cta{display:flex;gap:12px;flex-wrap:wrap;margin-top:20px}
    .badge-row{display:flex;gap:12px;flex-wrap:wrap;margin-top:18px;color:#a9bbec}
    .badge{display:flex;gap:8px;align-items:center;padding:8px 12px;border-radius:999px;border:1px dashed rgba(255,255,255,.16);font-size:12px}

    /* Sections */
    .section{padding:60px 0;border-top:1px solid rgba(255,255,255,.06)}
    .grid-3{display:grid;grid-template-columns:1fr;gap:16px}
    .grid-2{display:grid;grid-template-columns:1fr;gap:16px}
    .card{background:linear-gradient(180deg,rgba(255,255,255,.04),rgba(255,255,255,.02));border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:20px;transition:transform .2s, box-shadow .35s ease, border-color .35s ease}
    .card h3{margin:6px 0 8px 0}
    .card:hover, .card:focus-within{transform:translateY(-3px);border-color:rgba(110,231,255,.5);box-shadow:0 0 0 2px rgba(110,231,255,.15), 0 12px 48px rgba(139,92,246,.25), inset 0 0 40px rgba(110,231,255,.05)}
    .muted{color:#a8b4d9}
    .kpis{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin-top:18px}
    .kpi{background:rgba(255,255,255,.02);border:1px solid rgba(255,255,255,.08);padding:14px;border-radius:14px;text-align:center}
    .mono{font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace}
    .pill{display:inline-block;padding:6px 10px;border:1px solid rgba(255,255,255,.16);border-radius:999px;font-size:12px;color:#9fb2df}
    .cta{display:flex;flex-direction:column;gap:14px;background:linear-gradient(135deg,#0f1736,#0d1430);border:1px solid rgba(255,255,255,.08);border-radius:18px;padding:18px}
    footer{padding:36px 0;color:#9fb2df}
    .footer-grid{display:grid;grid-template-columns:1fr;gap:16px}
    .input, textarea{width:100%;padding:12px 14px;background:#0c1330;border:1px solid rgba(255,255,255,.12);border-radius:12px;color:var(--text)}
    .input:focus, textarea:focus{outline:none;box-shadow:var(--ring)}
    .form-row{display:grid;grid-template-columns:1fr;gap:12px}
    .alert{padding:12px 14px;border-radius:12px;margin:10px 0;font-weight:600}
    .alert.ok{background:rgba(0,245,160,.08);border:1px solid rgba(0,245,160,.25);color:#baffdf}
    .alert.err{background:rgba(255,107,107,.08);border:1px solid rgba(255,255,255,.25);color:#ffd3d3}

    /* Desktop enhancements */
    @media (min-width: 760px){
      .hero .grid{grid-template-columns:1.1fr .9fr;gap:40px;padding:72px 0}
      h1{font-size:64px;line-height:1.05}
      .grid-3{grid-template-columns:repeat(3,1fr);gap:20px}
      .grid-2{grid-template-columns:repeat(2,1fr);gap:24px}
      .kpis{grid-template-columns:repeat(4,1fr);gap:16px}
      .cta{flex-direction:row;align-items:center;justify-content:space-between}
      .footer-grid{grid-template-columns:2fr 1fr 1fr}
      .form-row{grid-template-columns:1fr 1fr}
    }

    /* Mobile nav */
    #primary-nav{display:flex;gap:18px;align-items:center}
    @media (max-width: 900px){
      .menu-toggle{display:inline-flex}
      #primary-nav{
        position:fixed;inset:70px 0 auto 0;display:none;flex-direction:column;gap:16px;padding:16px 20px;
        background:#0a1026;border-top:1px solid rgba(255,255,255,.06)
      }
      #primary-nav.open{display:flex}
      .nav-inner{min-height:70px}
    }

    /* Reduced motion */
    @media (prefers-reduced-motion: reduce){
      *{animation:none!important;transition:none!important;scroll-behavior:auto!important}
    }
  </style>
</head>
<body>
  <a class="skip" href="#main">Skip to content</a>

  <header class="nav" role="banner">
    <div class="container nav-inner">
      <a href="#top" class="brand" aria-label="<?=$brand_name?> home">
        <img src="<?=$logo_src?>" alt="<?=$brand_name?> logo" class="logo-img" />
        <span><?=$brand_name?></span>
      </a>

      <button class="menu-toggle" id="menu-toggle" aria-label="Toggle navigation" aria-controls="primary-nav" aria-expanded="false">
        Menu
      </button>

      <nav aria-label="Primary" id="primary-nav">
        <a href="#solutions">Solutions</a>
        <a href="#capabilities">Capabilities</a>
        <a href="#cases">Case Studies</a>
        <a href="#standards">Standards</a>
        <a href="#contact" class="btn ghost">Contact</a>
      </nav>
    </div>
  </header>

  <main id="main" class="hero" tabindex="-1">
    <div class="container grid">
      <div>
        <span class="eyebrow">Secure • Reliable • Deployable</span>
        <h1>Applied AI for ambitious teams</h1>
        <p class="lead">
          We help organizations design, build, and operate AI systems that actually ship—assistants, automation,
          analytics, computer vision, and cloud ecosystems—delivered with strong security and governance.
        </p>
        <div class="hero-cta">
          <a class="btn" href="#contact">Book a consultation</a>
          <a class="btn ghost" href="#solutions">Explore solutions</a>
        </div>
        <div class="badge-row" aria-label="Highlights">
          <span class="badge">◆ On-prem &amp; cloud</span>
          <span class="badge">◆ Measurement &amp; evals</span>
          <span class="badge">◆ Compliance-friendly workflows</span>
        </div>
      </div>
      <!-- No decorative box -->
      <div aria-hidden="true" class="desk"></div>
    </div>
  </main>

  <section id="solutions" class="section" aria-labelledby="solutions-title">
    <div class="container">
      <h2 id="solutions-title">Solutions</h2>
      <p class="muted">Modular offerings that meet you where you are—each delivered with security, observability, and governance.</p>

      <div class="grid-3" style="margin-top:16px">
        <article class="card" tabindex="0">
          <div class="pill">Automation</div>
          <h3>Process &amp; workflow automation</h3>
          <p class="muted">Automate repetitive tasks across tools with human-in-the-loop guardrails and traceability.</p>
        </article>

        <article class="card" tabindex="0">
          <div class="pill">Assistants</div>
          <h3>Knowledge assistants &amp; agents</h3>
          <p class="muted">RAG-backed assistants that answer with citations from your docs, tickets, and data sources.</p>
        </article>

        <article class="card" tabindex="0">
          <div class="pill">Vision &amp; Docs</div>
          <h3>Document &amp; image understanding</h3>
          <p class="muted">Ingest, classify, and extract structured data from PDFs, forms, images, and video.</p>
        </article>

        <article class="card" tabindex="0">
          <div class="pill">Analytics</div>
          <h3>Predictive &amp; decision support</h3>
          <p class="muted">Forecasting and risk models with dashboards, alerts, and explainability baked in.</p>
        </article>

        <article class="card" tabindex="0">
          <div class="pill">Integrations</div>
          <h3>Platform &amp; data integration</h3>
          <p class="muted">APIs, connectors, and event buses to tie your stack together without vendor lock-in.</p>
        </article>

        <article class="card" tabindex="0">
          <div class="pill">Enablement</div>
          <h3>Training &amp; change management</h3>
          <p class="muted">Playbooks, SOPs, and targeted upskilling so teams adopt AI confidently and safely.</p>
        </article>

        <article class="card" tabindex="0">
          <div class="pill">Azure</div>
          <h3>Azure ecosystem setup</h3>
          <p class="muted">Azure OpenAI, AKS, AAD, Key Vault, Private Link, Monitor, Policies, and Landing Zone—secure by default, production-ready.</p>
        </article>
      </div>

      <div class="kpis" role="list">
        <div class="kpi" role="listitem"><div class="mono" style="font-size:28px">10x</div><div class="muted">Ops throughput</div></div>
        <div class="kpi" role="listitem"><div class="mono" style="font-size:28px">&lt; 80ms</div><div class="muted">Edge latency</div></div>
        <div class="kpi" role="listitem"><div class="mono" style="font-size:28px">99.95%</div><div class="muted">Uptime SLO</div></div>
        <div class="kpi" role="listitem"><div class="mono" style="font-size:28px">Zero</div><div class="muted">Data exfiltration</div></div>
      </div>
    </div>
  </section>

  <section id="capabilities" class="section" aria-labelledby="capabilities-title">
    <div class="container">
      <h2 id="capabilities-title">Core capabilities</h2>
      <div class="grid-2" style="margin-top:12px">
        <article class="card" tabindex="0">
          <h3>Data &amp; platforms</h3>
          <ul class="muted">
            <li>Streaming pipelines • Kafka/NATS</li>
            <li>Vector stores • PgVector/FAISS</li>
            <li>Feature stores • Feast</li>
            <li>Observability • OpenTelemetry</li>
          </ul>
        </article>

        <article class="card" tabindex="0">
          <h3>Model engineering</h3>
          <ul class="muted">
            <li>RAG &amp; tool-use agents</li>
            <li>Fine-tuning &amp; distillation</li>
            <li>Edge/Jetson acceleration</li>
            <li>Evaluation &amp; alignment</li>
          </ul>
        </article>

        <article class="card" tabindex="0">
          <h3>Security &amp; reliability</h3>
          <ul class="muted">
            <li>SBOMs • SLSA • Sigstore</li>
            <li>Policy as code • OPA/Conftest</li>
            <li>Access segregation • ABAC/RBAC</li>
            <li>Air-gap &amp; zero-trust ops</li>
          </ul>
        </article>

        <article class="card" tabindex="0">
          <h3>Azure ecosystem</h3>
          <ul class="muted">
            <li>Azure OpenAI &amp; content filters</li>
            <li>AKS, Functions, APIM, Event Grid</li>
            <li>Key Vault, Private Link, Defender</li>
            <li>Landing Zone, Bicep/Terraform, Policy</li>
          </ul>
        </article>
      </div>
    </div>
  </section>

  <section id="cases" class="section" aria-labelledby="cases-title">
    <div class="container">
      <h2 id="cases-title">Selected case studies</h2>
      <div class="grid-3" style="margin-top:12px">
        <article class="card" tabindex="0">
          <h3>Customer support assistant</h3>
          <p class="muted">Deflected 42% of tickets with a knowledge-grounded assistant integrated with CRM and internal docs.</p>
        </article>
        <article class="card" tabindex="0">
          <h3>Invoice &amp; forms extraction</h3>
          <p class="muted">Automated intake of multi-layout PDFs with human review queues and end-to-end observability.</p>
        </article>
        <article class="card" tabindex="0">
          <h3>Predictive supply planning</h3>
          <p class="muted">Time-series forecasting improved inventory turns by 18% with clear explanations for operators.</p>
        </article>
      </div>
    </div>
  </section>

  <section id="standards" class="section" aria-labelledby="standards-title">
    <div class="container">
      <h2 id="standards-title">Standards &amp; assurances</h2>
    <div class="grid-3" style="margin-top:12px">
        <article class="card" tabindex="0"><h3>Security</h3><p class="muted">Zero-trust defaults, signed artifacts, key management, and privacy-first data design.</p></article>
        <article class="card" tabindex="0"><h3>Reliability</h3><p class="muted">SLOs, incident response, blue/green deploys, and graceful degradation by design.</p></article>
        <article class="card" tabindex="0"><h3>Compliance</h3><p class="muted">ISO 27001 practices, SOC2-friendly controls, and data residency options.</p></article>
      </div>

      <div class="cta" style="margin-top:22px">
        <div>
          <h3 style="margin:6px 0">Ready to ship trustworthy AI?</h3>
          <p class="muted">We’ll assess, prototype, and deploy on premises or in your cloud—safely and fast.</p>
        </div>
        <a class="btn" href="#contact">Start your project</a>
      </div>
    </div>
  </section>

  <section id="contact" class="section" aria-labelledby="contact-title">
    <div class="container">
      <h2 id="contact-title">Contact</h2>
      <p class="muted">Tell us about your use case. We’ll reply within one business day.</p>

      <?php if ($show_thanks): ?>
        <div class="alert ok" role="status">Thank you — your message has been received. We’ll be in touch shortly.</div>
      <?php elseif (!empty($errors)): ?>
        <div class="alert err" role="alert"><?=h(implode(' ', $errors))?></div>
      <?php endif; ?>

      <form method="post" style="margin-top:12px" novalidate>
        <input type="hidden" name="formid" value="contact" />
        <input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>" />
        <input type="text" name="hp" value="" class="skip" tabindex="-1" autocomplete="off" aria-hidden="true" />

        <div class="form-row">
          <div>
            <label for="name">Your name</label>
            <input class="input" id="name" type="text" name="name" value="<?=h($name)?>" placeholder="Jane Doe" required <?php if(!empty($errors)) echo 'aria-invalid="true"';?> />
          </div>
          <div>
            <label for="email">Email</label>
            <input class="input" id="email" type="email" name="email" value="<?=h($email)?>" placeholder="jane@company.com" required <?php if(!empty($errors)) echo 'aria-invalid="true"';?> />
          </div>
        </div>

        <div style="margin-top:12px">
          <label for="message">How can we help?</label>
          <textarea class="input" id="message" name="message" rows="6" placeholder="Briefly describe your problem space, environment (on-prem or cloud), and timeline."><?=h($msg)?></textarea>
        </div>

        <div style="margin-top:14px;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
          <button class="btn" type="submit">Send message</button>
          <span class="muted">or email <span class="mono"><?=h($contact_to)?></span></span>
        </div>
      </form>
    </div>
  </section>

  <footer role="contentinfo">
    <div class="container footer-grid">
      <div>
        <div class="brand" style="margin-bottom:10px">
          <img src="<?=$logo_src?>" alt="<?=$brand_name?> logo" class="logo-img" />
          <span><?=$brand_name?></span>
        </div>
        <p class="muted">Applied AI for automation, assistants, analytics, integrations, and Azure ecosystems. Built with security and operators in mind.</p>
      </div>
      <div>
        <h4>Company</h4>
        <ul class="muted" style="list-style:none;padding:0;margin:6px 0 0 0">
          <li><a href="#solutions">Solutions</a></li>
          <li><a href="#capabilities">Capabilities</a></li>
          <li><a href="#cases">Case Studies</a></li>
          <li><a href="#standards">Standards</a></li>
        </ul>
      </div>
      <div>
        <h4>Contact</h4>
        <ul class="muted" style="list-style:none;padding:0;margin:6px 0 0 0">
          <li><a href="#contact">Work with us</a></li>
          <li><a href="mailto:<?=h($contact_to)?>"><?=h($contact_to)?></a></li>
        </ul>
      </div>
    </div>
    <div class="container" style="margin-top:16px;color:#6d7fb1;font-size:12px">
      © <?= date('Y') ?> <?=h($brand_name)?>. All rights reserved.
    </div>
  </footer>

  <script>
    // Smooth scroll for anchors
    document.querySelectorAll('a[href^="#"]').forEach(function(a){
      a.addEventListener('click', function(e){
        var id = a.getAttribute('href').slice(1);
        var el = document.getElementById(id);
        if (el) { e.preventDefault(); el.scrollIntoView({behavior:'smooth'}); }
      });
    });

    // Mobile menu toggle (accessible)
    var toggle = document.getElementById('menu-toggle');
    var nav = document.getElementById('primary-nav');
    if (toggle && nav) {
      var openMenu = function(){ nav.classList.add('open'); toggle.setAttribute('aria-expanded','true'); };
      var closeMenu = function(){ nav.classList.remove('open'); toggle.setAttribute('aria-expanded','false'); };
      toggle.addEventListener('click', function(){
        if (nav.classList.contains('open')) closeMenu(); else openMenu();
      });
      window.addEventListener('keydown', function(e){ if(e.key==='Escape') closeMenu(); });
      Array.prototype.forEach.call(nav.querySelectorAll('a'), function(link){
        link.addEventListener('click', closeMenu);
      });
    }
  </script>
</body>
</html>
