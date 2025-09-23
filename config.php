<?php
// config.php — shared config, DB, helpers, security, email (SMTP via PHPMailer)

// ---------- Security headers ----------
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

// ---------- Sessions (secure cookies) ----------
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

// ---------- App config ----------
date_default_timezone_set('Asia/Dhaka');

// ---------- Database credentials ----------
define('DB_HOST', '194.233.77.177');
define('DB_NAME', 'soralabs_masterdb');
define('DB_USER', 'soralabs_masterdb');
define('DB_PASS', ']Pi{5,^)G}]D');

// ---------- Mail / SMTP settings ----------
define('MAIL_FROM', 'info@soralabs.cc');   // your cPanel mailbox
define('MAIL_FROM_NAME', 'SORA Labs');

// Prefer .env or server env var for the password in production
// e.g., put:  SMTP_PASS=your_secret  in environment and read via getenv('SMTP_PASS')
define('SMTP_HOST', 'mail.soralabs.cc');   // from cPanel "Connect Devices"
define('SMTP_PORT', 587);                  // 587 (TLS) or 465 (SSL)
define('SMTP_USER', 'info@soralabs.cc');   // full email as username
define('SMTP_PASS', '*@.9j}#N2MT+');
define('SMTP_ENCRYPTION', 'tls');          // 'tls' for 587, 'ssl' for 465

// ---------- Paths ----------
define('UPLOAD_DIR', __DIR__ . '/uploads');

// ---------- Utilities ----------
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

// ---------- CSRF ----------
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

// ---------- Flash ----------
function flash(string $key, ?string $val=null) {
  if ($val === null) {
    $msg = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $msg;
  }
  $_SESSION['flash'][$key] = $val;
}

// ---------- PHPMailer (Composer or manual include) ----------
$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
  require_once $autoload; // Composer install: composer require phpmailer/phpmailer
} else {
  // Manual includes (upload PHPMailer src/ to lib/phpmailer/src)
  require_once __DIR__.'/lib/phpmailer/src/PHPMailer.php';
  require_once __DIR__.'/lib/phpmailer/src/SMTP.php';
  require_once __DIR__.'/lib/phpmailer/src/Exception.php';
}
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ---------- Email sender (HTML) via SMTP ----------
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

    $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
    $mail->addReplyTo(MAIL_FROM, MAIL_FROM_NAME);
    $mail->addAddress($to);

    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body    = $html;
    // Plain text alternative improves deliverability
    $mail->AltBody = strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $html));
    $mail->CharSet = 'UTF-8';

    return $mail->send();
  } catch (Exception $e) {
    // Optionally log: error_log('Mailer Error: '.$e->getMessage());
    return false;
  }
}

// ---------- Tokens / Auth guards / Rate limit ----------
function token64(): string { return bin2hex(random_bytes(32)); }

function require_login(): void {
  if (empty($_SESSION['user_id'])) {
    header('Location: '.base_url().'/login.php');
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

// ---------- Theme helpers ----------
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
  </style></head><body class="noise">';
}

function topbar(string $active=''): void {
  echo '<nav class="navbar navbar-dark topbar">
    <div class="container-fluid">
      <a class="navbar-brand d-flex align-items-center gap-2" href="#">
        <span class="dot"></span><span class="brand fw-bold">SORA Labs</span>
      </a>
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <a class="btn btn-ghost btn-sm'.($active==='leaderboard'?' active':'').'" href="#"><i class="bi bi-trophy me-1"></i>Leaderboard</a>
        <a class="btn btn-ghost btn-sm'.($active==='status'?' active':'').'" href="#"><i class="bi bi-speedometer2 me-1"></i>Overall Status</a>
        <a class="btn btn-ghost btn-sm'.($active==='faq'?' active':'').'" href="#"><i class="bi bi-question-circle me-1"></i>FAQ</a>
        <a class="btn btn-ghost btn-sm'.($active==='contact'?' active':'').'" href="#"><i class="bi bi-envelope me-1"></i>Contact</a>
        <a class="btn btn-ghost btn-sm'.($active==='claim'?' active':'').'" href="#"><i class="bi bi-patch-check-fill me-1"></i>Claim</a>
        <a class="btn btn-light btn-sm" href="login.php"><i class="bi bi-person me-1"></i>Login / Registration</a>
      </div>
    </div>
  </nav>';
}

function theme_foot(): void {
  echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script></body></html>';
}
