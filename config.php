<?php
// config.php — shared config, DB, helpers, security, email (SMTP via PHPMailer)

/* ------------------------ Security headers ------------------------ */
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

/* ------------------------ Sessions (secure cookies) ------------------------ */
$cookieParams = session_get_cookie_params();
session_set_cookie_params([
  'lifetime' => 0,
  'path' => $cookieParams['path'],
  'domain' => $cookieParams['domain'],
  'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
  'httponly' => true,
  'samesite' => 'Lax'
]);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

/* ------------------------ App config ------------------------ */
date_default_timezone_set('Asia/Dhaka');

/* ------------------------ Centralized routes (edit once here) ------------------------ */
$APP_ROUTES = [
  'home'        => './',
  'dashboard'   => '/dashboard.php',
  'leaderboard' => '/leaderboard.php',
  'status'      => '/status.php',
  'faq'         => '/faq.php',
  'contact'     => '/contact.php',
  'claim'       => '/claim.php',
  'settings'    => '/settings.php',
  'login'       => '/login.php',
  'logout'      => '/logout.php',
];

/** Get a route by key (with fallback). */
function route(string $key, string $fallback = '#'): string {
  global $APP_ROUTES;
  return $APP_ROUTES[$key] ?? $fallback;
}
/** Tiny alias. */
function r(string $key, string $fallback = '#'): string { return route($key, $fallback); }

/* ------------------------ Database credentials ------------------------ */
define('DB_HOST', '194.233.77.177');
define('DB_NAME', 'soralabs_masterdb');
define('DB_USER', 'soralabs_masterdb');
define('DB_PASS', ']Pi{5,^)G}]D');

/* ------------------------ Mail / SMTP settings ------------------------ */
define('MAIL_FROM', 'info@soralabs.cc');
define('MAIL_FROM_NAME', 'SORA Labs');

define('SMTP_HOST', 'server16.serverastro.com');
define('SMTP_PORT', 465);                        // 587 (TLS) or 465 (SSL)
define('SMTP_USER', 'info@soralabs.cc');
define('SMTP_PASS', '*@.9j}#N2MT+');
define('SMTP_ENCRYPTION', 'ssl');                // 'tls' or 'ssl'

/* ------------------------ Paths ------------------------ */
define('UPLOAD_DIR', __DIR__ . '/uploads');

/* ------------------------ Utilities ------------------------ */
function base_url(): string {
  $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $dir   = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
  return $https . '://' . $host . ($dir ? $dir : '');
}

function db(): PDO {
  static $pdo;
  if (!$pdo) {
    $pdo = new PDO(
      'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',
      DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      ]
    );
  }
  return $pdo;
}

/* ------------------------ CSRF ------------------------ */
function csrf_token(): string {
  if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
  return $_SESSION['csrf'];
}
function csrf_field(): string {
  return '<input type="hidden" name="csrf" value="'.htmlspecialchars(csrf_token(), ENT_QUOTES).'">';
}
function csrf_check(): void {
  if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) {
    http_response_code(400);
    exit('Invalid CSRF token.');
  }
}

/* ------------------------ Flash ------------------------ */
function flash(string $key, ?string $val=null) {
  if ($val === null) {
    $msg = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $msg;
  }
  $_SESSION['flash'][$key] = $val;
}

/* ------------------------ PHPMailer (Composer or manual include) ------------------------ */
$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
  require_once $autoload; // composer require phpmailer/phpmailer
} else {
  require_once __DIR__.'/lib/phpmailer/src/PHPMailer.php';
  require_once __DIR__.'/lib/phpmailer/src/SMTP.php';
  require_once __DIR__.'/lib/phpmailer/src/Exception.php';
}
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/* ------------------------ Email sender (HTML) via SMTP ------------------------ */
function send_mail_html(string $to, string $subject, string $html): bool {
  $mail = new PHPMailer(true);
  try {
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;

    if (strtolower(SMTP_ENCRYPTION) === 'ssl') {
      $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
      $mail->Port       = 465;
    } else {
      $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
      $mail->Port       = SMTP_PORT ?: 587;
    }

    // Identity — align SPF/DMARC
    $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME, true);
    $mail->Sender = MAIL_FROM; // Return-Path/envelope sender
    $mail->addReplyTo(MAIL_FROM, MAIL_FROM_NAME);

    // Headers
    $mail->MessageID = sprintf('<%s@soralabs.cc>', bin2hex(random_bytes(12)));
    $mail->addCustomHeader('List-Unsubscribe', '<mailto:'.MAIL_FROM.'>');

    // Recipient
    $mail->addAddress($to);

    // Content
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body    = $html;
    $mail->AltBody = strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $html));
    $mail->CharSet = 'UTF-8';

    return $mail->send();
  } catch (Exception $e) {
    return false;
  }
}

/* ------------------------ Email templates ------------------------ */
function email_template(string $title, string $preheader, string $contentHtml, ?string $ctaLabel=null, ?string $ctaUrl=null): string {
  $year = date('Y'); $brand = 'SORA Labs'; $site  = 'https://soralabs.cc';
  $btn = '';
  if ($ctaLabel && $ctaUrl) {
    $btn = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:24px 0 6px 0;">
      <tr><td align="center" bgcolor="#4f46e5" style="border-radius:10px;">
        <a href="'.htmlspecialchars($ctaUrl).'" style="display:inline-block;padding:12px 18px;color:#ffffff;text-decoration:none;font-weight:600;border-radius:10px;">'
        .htmlspecialchars($ctaLabel).'</a>
      </td></tr>
    </table>
    <div style="font-size:12px;color:#9fb6c9;line-height:18px;">If the button doesn’t work, copy &amp; paste this link:<br>
      <a href="'.htmlspecialchars($ctaUrl).'" style="color:#c7fff6;text-decoration:underline;">'.htmlspecialchars($ctaUrl).'</a>
    </div>';
  }
  $pre = $preheader ? '<div style="display:none!important;visibility:hidden;opacity:0;color:transparent;height:0;width:0;overflow:hidden;">'
        .htmlspecialchars($preheader).'</div>' : '';

  return '<!doctype html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#0b0f14;color:#e9eef5;">
  '.$pre.'
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#0b0f14;">
    <tr><td align="center" style="padding:32px 16px;">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:100%;">
        <tr><td style="padding:0 0 14px 0;text-align:center;">
          <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#15ff99;box-shadow:0 0 10px #15ff99a6;margin-right:8px;"></span>
          <span style="font-family:Inter,Arial,Helvetica,sans-serif;font-size:18px;font-weight:700;color:#e9eef5;letter-spacing:.4px;">SORA Labs</span>
        </td></tr>
        <tr><td>
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                 style="border-radius:18px;border:1px solid #253141;background:#0f1520;">
            <tr><td style="height:4px;background:linear-gradient(90deg,#6c63ff,#00e7ff,#ff6ec7);border-radius:18px 18px 0 0;"></td></tr>
            <tr><td style="padding:24px 24px 8px 24px;">
              <h1 style="margin:0;font-family:Inter,Arial,Helvetica,sans-serif;font-size:22px;line-height:28px;font-weight:800;color:#e9eef5;">'
                .htmlspecialchars($title).'</h1>
            </td></tr>
            <tr><td style="padding:8px 24px 4px 24px;font-family:Inter,Arial,Helvetica,sans-serif;font-size:15px;line-height:22px;color:#d7e2ee;">
              '.$contentHtml.$btn.'
            </td></tr>
            <tr><td style="padding:18px 24px 22px 24px;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-top:1px solid #243041;">
                <tr><td style="padding-top:12px;font-family:Inter,Arial,Helvetica,sans-serif;font-size:12px;line-height:18px;color:#9fb6c9;">
                  '.$brand.' • <a href="'.$site.'" style="color:#c7fff6;text-decoration:none;">soralabs.cc</a><br>
                  © '.$year.' '.$brand.'. All rights reserved.
                </td></tr>
              </table>
            </td></tr>
          </table>
        </td></tr>
        <tr><td style="height:10px;"></td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>';
}

function send_templated_mail(string $to, string $subject, string $title, string $preheader, string $contentHtml, ?string $ctaLabel=null, ?string $ctaUrl=null): bool {
  $html = email_template($title, $preheader, $contentHtml, $ctaLabel, $ctaUrl);
  return send_mail_html($to, $subject, $html);
}

function email_verify_template(string $nameOrUser, string $verifyUrl): string {
  $safeName = htmlspecialchars($nameOrUser ?: 'there');
  $content = '<p style="margin:0 0 14px 0;">Hi '.$safeName.',</p>
    <p style="margin:0 0 10px 0;">Thanks for signing up at <strong>SORA Labs</strong>. Please verify your email to activate your account.</p>';
  return email_template('Verify your email', 'Verify your email for SORA Labs', $content, 'Verify Email', $verifyUrl);
}
function email_reset_template(string $nameOrUser, string $resetUrl): string {
  $safeName = htmlspecialchars($nameOrUser ?: 'there');
  $content = '<p style="margin:0 0 14px 0;">Hi '.$safeName.',</p>
    <p style="margin:0 0 10px 0;">We received a request to reset your password. Click the button below to set a new one.</p>
    <p style="margin:10px 0 0 0;color:#9fb6c9;font-size:13px;">If you didn’t request this, you can safely ignore this email.</p>';
  return email_template('Reset your password', 'Reset your SORA Labs password', $content, 'Reset Password', $resetUrl);
}

/* ------------------------ Tokens / Auth guards / Rate limit ------------------------ */
function token64(): string { return bin2hex(random_bytes(32)); }

function require_login(): void {
  if (empty($_SESSION['user_id'])) {
    header('Location: '.route('login'));
    exit;
  }
}

function rate_limit(string $bucket, int $limit, int $windowSec): bool {
  $now = time();
  if (!isset($_SESSION['rate'][$bucket])) $_SESSION['rate'][$bucket] = [];
  $_SESSION['rate'][$bucket] = array_filter($_SESSION['rate'][$bucket], fn($t) => ($now - $t) < $windowSec);
  if (count($_SESSION['rate'][$bucket]) >= $limit) return false;
  $_SESSION['rate'][$bucket][] = $now;
  return true;
}

/* ------------------------ Theme helpers (site UI) ------------------------ */
function theme_head(string $title='SORA Labs'): void {
  echo '<!doctype html><html lang="en"><head><meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="robots" content="noindex, nofollow">
  <title>'.htmlspecialchars($title).'</title>
  <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>⚙️</text></svg>">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
  <style>
    :root { --glass: rgba(255,255,255,0.08); --glass-border: rgba(255,255,255,0.18); }
    html, body { height: 100%; }
    body {
      font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Helvetica Neue, Arial, Noto Sans, Liberation Sans, sans-serif;
      background: radial-gradient(1200px 800px at 20% 10%, #6c63ff33, transparent 60%),
                  radial-gradient(1000px 700px at 80% 30%, #00e7ff33, transparent 60%),
                  radial-gradient(900px 700px at 50% 90%, #ff6ec733, transparent 60%),
                  #0b0f14;
      color: #e9eef5;
    }
    .noise:before {
      content: ""; position: fixed; inset: 0; pointer-events:none; mix-blend-mode:soft-light; z-index:0;
      background-image:url("data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 width=%27160%27 height=%27160%27 viewBox=%270 0 160 160%27%3E%3Cfilter id=%27n%27%3E%3CfeTurbulence type=%27fractalNoise%27 baseFrequency=%270.9%27 numOctaves=%272%27 stitchTiles=%27stitch%27/%3E%3CfeColorMatrix type=%27saturate%27 values=%270%27/%3E%3C/filter%3E%3Crect width=%27100%25%27 height=%27100%25%27 filter=%27url(%23n)%27 opacity=%27.025%27/%3E%3C/svg%3E");
    }
    .glass { background: var(--glass); border: 1px solid var(--glass-border); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px); border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,.35); }
    .brand { letter-spacing: .6px; background: linear-gradient(90deg,#fff,#b6d7ff 60%,#c7fff6); -webkit-background-clip:text; background-clip:text; color:transparent; }
    .dot{ width:10px;height:10px;border-radius:50%;background:#15ff99;box-shadow:0 0 12px #15ff99aa;display:inline-block;margin-right:8px;animation:pulse 1.8s ease-in-out infinite;}
    @keyframes pulse{0%,100%{transform:scale(1);opacity:.9;}50%{transform:scale(1.25);opacity:.6;}}
    .topbar.navbar{ background:var(--glass); border:1px solid var(--glass-border); backdrop-filter:blur(14px); -webkit-backdrop-filter:blur(14px); margin:8px 12px; border-radius:14px;}
    .btn-ghost{ background:rgba(255,255,255,.06); color:#e9eef5; border:1px solid rgba(255,255,255,.2); }
    .btn-ghost:hover{ background:rgba(255,255,255,.12); }
    .form-control, .form-select { background-color: rgba(255,255,255,.06); color: #e9eef5; border-color: rgba(255,255,255,.2); }
    .form-control:focus, .form-select:focus { box-shadow: 0 0 0 .25rem rgba(99,179,237,.25); border-color:#84c5ff; }
    a { color:#b6d7ff; }

    /* ---- Spacer under the fixed top bar ----
       Increased for mobile to prevent overlap; includes safe-area for iOS. */
    .app-topbar-spacer { height: calc(136px + env(safe-area-inset-top, 0px)); }
    @media (min-width: 992px) {
      .app-topbar-spacer { height: 88px; }
    }
  </style></head><body class="noise">';
}

/**
 * Top bar with centralized links.
 * $active can be 'leaderboard'|'status'|'faq'|'contact'|'claim' to highlight.
 */
function topbar(string $active = ''): void {
  $isLoggedIn = !empty($_SESSION['user_id']); ?>
  <nav class="navbar navbar-dark fixed-top topbar glass" role="navigation">
    <div class="container-fluid">
      <div class="d-flex align-items-center gap-2">
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= r('home') ?>">
          <span class="dot" aria-hidden="true"></span>
          <span class="brand fw-bold">SORA Labs</span>
        </a>
      </div>

      <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
        <a href="<?= r('leaderboard') ?>" class="btn btn-ghost btn-sm<?= $active==='leaderboard'?' active':'' ?>">
          <i class="bi bi-trophy me-1"></i>Leaderboard
        </a>
        <a href="<?= r('status') ?>" class="btn btn-ghost btn-sm<?= $active==='status'?' active':'' ?>">
          <i class="bi bi-speedometer2 me-1"></i>Overall Status
        </a>
        <a href="<?= r('faq') ?>" class="btn btn-ghost btn-sm<?= $active==='faq'?' active':'' ?>">
          <i class="bi bi-question-circle me-1"></i>FAQ
        </a>
        <a href="<?= r('contact') ?>" class="btn btn-ghost btn-sm<?= $active==='contact'?' active':'' ?>">
          <i class="bi bi-envelope me-1"></i>Contact
        </a>
        <a href="<?= r('claim') ?>" class="btn btn-ghost btn-sm<?= $active==='claim'?' active':'' ?>">
          <i class="bi bi-patch-check-fill me-1"></i>Claim
        </a>

        <?php if ($isLoggedIn): ?>
          <a href="<?= r('dashboard') ?>" class="btn btn-light btn-sm">
            <i class="bi bi-speedometer2 me-1"></i>Dashboard
          </a>
        <?php else: ?>
          <a href="<?= r('login') ?>" class="btn btn-light btn-sm">
            <i class="bi bi-person me-1"></i>Login / Registration
          </a>
        <?php endif; ?>
      </div>
    </div>
  </nav>
  <!-- Spacer to push page content below the fixed nav (works on mobile & desktop) -->
  <div class="app-topbar-spacer" aria-hidden="true"></div>
<?php }

function theme_foot(): void {
  echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script></body></html>';
}
