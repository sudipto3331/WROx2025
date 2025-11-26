<?php
// zone_dashboard_leaflet.php — Leaflet + OSM • Glassmorphism Theme • Bootstrap • MySQL

// ---------------------------------
// AUTH
// ---------------------------------
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$isLoggedIn = !empty($_SESSION['user_id']);
$userId     = $isLoggedIn ? intval($_SESSION['user_id']) : null;
$userEmail  = $isLoggedIn ? ($_SESSION['user_email'] ?? null) : null;

// ---------------------------------
// DB CONFIG
// ---------------------------------
$DB_HOST = '194.233.77.177';
$DB_NAME = 'soralabs_masterdb';
$DB_USER = 'soralabs_masterdb';
$DB_PASS = ']Pi{5,^)G}]D';

// ---------------------------------
// CREDIT + VERIFICATION CONFIG (EDIT THESE)
// ---------------------------------
$CREDIT_THRESHOLD_PERCENT = 5; // Award credit only if confidence > this value (in %)
$CREDIT_FOR_RED    = 2;
$CREDIT_FOR_YELLOW = 1;
$CREDIT_FOR_GREEN  = 0;

$VERIFY_WITH_PLANTNET = true; // set false to disable API verification
$PLANTNET_API_KEY = '2b1099DMcB5ZqTwSqirdJUUj3e'; // https://my.plantnet.org/
$PLANTNET_API_URL = 'https://my-api.plantnet.org/v2/identify/all';

// ---------------------------------
// Helpers
// ---------------------------------
function pdo_conn($DB_HOST,$DB_NAME,$DB_USER,$DB_PASS){
  return new PDO(
    "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
    $DB_USER,$DB_PASS,
    [ PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC ]
  );
}
function detect_mime_safe($path, $fallbackExt='jpg') {
  if (function_exists('mime_content_type')) {
    $m = @mime_content_type($path);
    if ($m) return $m;
  } elseif (function_exists('finfo_open')) {
    $f = @finfo_open(FILEINFO_MIME_TYPE);
    $m = $f ? @finfo_file($f, $path) : null;
    if ($f) @finfo_close($f);
    if ($m) return $m;
  }
  return ($fallbackExt === 'png') ? 'image/png' : 'image/jpeg';
}
function verify_tree_with_plantnet($apiUrl, $apiKey, $imgLocalPath) {
  if (empty($apiKey) || !is_readable($imgLocalPath)) {
    return ['ok'=>false,'confidence'=>null,'verifier'=>'plantnet'];
  }
  if (!extension_loaded('curl')) {
    return ['ok'=>false,'confidence'=>null,'verifier'=>'plantnet'];
  }
  $ext  = strtolower(pathinfo($imgLocalPath, PATHINFO_EXTENSION));
  $mime = detect_mime_safe($imgLocalPath, $ext ?: 'jpg');

  $form = [
    'organs' => 'leaf', // single value to avoid API 400
    'images' => new CURLFile($imgLocalPath, $mime, basename($imgLocalPath)),
  ];

  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL            => $apiUrl.'?api-key='.urlencode($apiKey),
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $form,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => true,
  ]);
  $resp = curl_exec($ch);
  if ($resp === false) { curl_close($ch); return ['ok'=>false,'confidence'=>null,'verifier'=>'plantnet']; }
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($code !== 200) { return ['ok'=>false,'confidence'=>null,'verifier'=>'plantnet']; }

  $json = json_decode($resp, true);
  $prob = isset($json['results'][0]['score']) ? (float)$json['results'][0]['score'] : null;

  return [
    'ok'         => true,
    'confidence' => $prob !== null ? round($prob * 100, 2) : null, // %
    'verifier'   => 'plantnet'
  ];
}

// ---------------------------------
// Ensure tables exist for credits/logs (safe no-ops if already there)
// ---------------------------------
try { pdo_conn($DB_HOST,$DB_NAME,$DB_USER,$DB_PASS)->exec("ALTER TABLE users ADD COLUMN green_credit INT NOT NULL DEFAULT 0"); } catch(Throwable $e){}
try {
  pdo_conn($DB_HOST,$DB_NAME,$DB_USER,$DB_PASS)->exec("
    CREATE TABLE IF NOT EXISTS tree_submissions (
      id INT AUTO_INCREMENT PRIMARY KEY,
      user_id INT NOT NULL,
      user_email VARCHAR(190) NULL,
      task_level ENUM('green','yellow','red') NOT NULL,
      required_trees INT NOT NULL DEFAULT 0,
      img_path VARCHAR(255) NOT NULL,
      gps_lat DECIMAL(10,7) NOT NULL,
      gps_lng DECIMAL(10,7) NOT NULL,
      verified TINYINT(1) NOT NULL DEFAULT 0,
      verifier VARCHAR(50) DEFAULT NULL,
      confidence DECIMAL(5,2) DEFAULT NULL,
      credit_awarded INT NOT NULL DEFAULT 0,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      INDEX(user_id), INDEX(task_level), INDEX(verified)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");
} catch(Throwable $e){}
try {
  pdo_conn($DB_HOST,$DB_NAME,$DB_USER,$DB_PASS)->exec("
    CREATE TABLE IF NOT EXISTS green_credit_log (
      id INT AUTO_INCREMENT PRIMARY KEY,
      user_id INT NOT NULL,
      delta INT NOT NULL,
      reason VARCHAR(255),
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      INDEX(user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");
} catch(Throwable $e){}

// ---------------------------------
// DATA ENDPOINT (AJAX)
// ---------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'data') {
  header('Content-Type: application/json; charset=utf-8');

  $source = $_GET['source'] ?? 'demo'; // 'demo' (dataset mode) or 'real'
  $metric = $_GET['metric'] ?? 'co2';
  $range  = $_GET['range']  ?? 'past_15_days';
  $from   = $_GET['from']   ?? null;
  $to     = $_GET['to']     ?? null;

  // dataset id (1..5) only matters when source==='demo'
  $datasetId = isset($_GET['dataset_id']) ? intval($_GET['dataset_id']) : 1;
  if ($datasetId < 1 || $datasetId > 5) { $datasetId = 1; }

  // Table decision:
  // - demo/dataset mode -> sensor_ingest_{id}
  // - real mode         -> real_sensor_ingest
  $table = ($source === 'real') ? 'real_sensor_ingest' : ('sensor_ingest_'.$datasetId);

  date_default_timezone_set('Asia/Dhaka');
  $now = new DateTime('now');

  switch ($range) {
    case 'yesterday':    $start = (new DateTime('yesterday'))->setTime(0,0,0); $end = (new DateTime('yesterday'))->setTime(23,59,59); break;
    case 'last_week':    $start = (clone $now)->modify('-7 days')->setTime(0,0,0); $end = (clone $now)->setTime(23,59,59); break;
    case 'past_15_days': $start = (clone $now)->modify('-15 days')->setTime(0,0,0); $end = (clone $now)->setTime(23,59,59); break;
    case 'last_month':   $start = (new DateTime('first day of last month'))->setTime(0,0,0); $end = (new DateTime('last day of last month'))->setTime(23,59,59); break;
    case 'custom':       $start = $from ? new DateTime($from) : (clone $now)->modify('-1 day'); $end = $to ? new DateTime($to) : (clone $now); break;
    default:             $start = (clone $now)->modify('-15 days')->setTime(0,0,0); $end = (clone $now)->setTime(23,59,59); break;
  }

  try {
    $pdo = pdo_conn($DB_HOST,$DB_NAME,$DB_USER,$DB_PASS);

    // Check table exists to avoid SQL error
    $chk = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = :db AND table_name = :tbl");
    $chk->execute([':db'=>$DB_NAME, ':tbl'=>$table]);
    if (!$chk->fetchColumn()) {
      http_response_code(404);
      echo json_encode(['status'=>'error','message'=>"Dataset table '{$table}' not found",'source'=>$source,'dataset_id'=>$datasetId]);
      exit;
    }

    $sql = "SELECT id, co2_level, voc, nox, particulate_matter,
                   humidity_scd30, humidity_sen55,
                   temperature_scd30, temperature_sen55,
                   gps_lat, gps_long, `time`, create_time
            FROM `{$table}`
            WHERE COALESCE(`time`, `create_time`) BETWEEN :start AND :end
              AND gps_lat IS NOT NULL AND gps_long IS NOT NULL";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':start'=>$start->format('Y-m-d H:i:s'), ':end'=>$end->format('Y-m-d H:i:s')]);

    $rows = [];
    while ($r = $stmt->fetch()) {
      $co2 = isset($r['co2_level']) ? floatval($r['co2_level']) : null;
      $voc = isset($r['voc'])       ? floatval($r['voc'])       : null;
      $nox = array_key_exists('nox', $r) && $r['nox'] !== null ? floatval($r['nox']) : null;

      $pm25 = null; $pm10 = null;
      if (!empty($r['particulate_matter'])) {
        $pmStr = (string)$r['particulate_matter'];
        if (preg_match_all('/\d+(?:\.\d+)?/', $pmStr, $m)) {
          if (isset($m[0][0])) $pm25 = floatval($m[0][0]);
          if (isset($m[0][1])) $pm10 = floatval($m[0][1]);
        }
      }

      $h1 = isset($r['humidity_scd30']) ? floatval($r['humidity_scd30']) : null;
      $h2 = isset($r['humidity_sen55']) ? floatval($r['humidity_sen55']) : null;
      $humidity = (!is_null($h1) && !is_null($h2)) ? ($h1 + $h2)/2.0 : (!is_null($h1) ? $h1 : $h2);

      $t1 = isset($r['temperature_scd30']) ? floatval($r['temperature_scd30']) : null;
      $t2 = isset($r['temperature_sen55']) ? floatval($r['temperature_sen55']) : null;
      $temperature = (!is_null($t1) && !is_null($t2)) ? ($t1 + $t2)/2.0 : (!is_null($t1) ? $t1 : $t2);

      $lat = floatval($r['gps_lat']); $lng = floatval($r['gps_long']);
      if (!is_finite($lat) || !is_finite($lng)) continue;

      $rows[] = [
        'id'=>intval($r['id']),
        'lat'=>$lat, 'lng'=>$lng,
        'co2'=>$co2, 'voc'=>$voc, 'nox'=>$nox,
        'pm25'=>$pm25, 'pm10'=>$pm10,
        'humidity'=>is_null($humidity)?null:round($humidity,2),
        'temperature'=>is_null($temperature)?null:round($temperature,2),
        'ts'=>$r['time'] ?? $r['create_time']
      ];
    }

    echo json_encode([
      'status'=>'ok',
      'source'=>$source,
      'dataset_id'=>$datasetId,
      'table'=>$table,
      'metric'=>$metric,
      'range'=>$range,
      'from'=>$start->format('Y-m-d H:i:s'),
      'to'=>$end->format('Y-m-d H:i:s'),
      'count'=>count($rows),
      'data'=>$rows
    ]);
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
  }
  exit;
}

// ---------------------------------
// Handle tree submission (AJAX)
// ---------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'submit_tree') {
  header('Content-Type: application/json; charset=utf-8');
  if (!$isLoggedIn) { http_response_code(401); echo json_encode(['status'=>'error','message'=>'Login required']); exit; }

  try {
    if (!isset($_POST['task_level'], $_POST['required_trees'], $_POST['gps_lat'], $_POST['gps_lng'])) {
      throw new Exception('Missing fields');
    }
    $task_level = $_POST['task_level']; // 'red'|'yellow'|'green'
    $required   = max(0, intval($_POST['required_trees']));
    $gps_lat    = floatval($_POST['gps_lat']);
    $gps_lng    = floatval($_POST['gps_lng']);

    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
      throw new Exception('Image upload failed');
    }

    // Save image
    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp'])) $ext = 'jpg';
    $dir = __DIR__.'/uploads/trees';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    if (!is_writable($dir)) { throw new Exception('Upload directory not writable'); }
    $filename = 'tree_'.time().'_u'.$userId.'.'.$ext;
    $dest = $dir.'/'.$filename;
    if (!move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
      throw new Exception('Failed to move uploaded file');
    }

    // Verification (confidence in %)
    $confidence = null; $verifier = 'manual'; $ok = false;
    if ($VERIFY_WITH_PLANTNET && !empty($PLANTNET_API_KEY)) {
      $vr = verify_tree_with_plantnet($PLANTNET_API_URL, $PLANTNET_API_KEY, $dest);
      $ok         = $vr['ok'];
      $confidence = $vr['confidence']; // %
      $verifier   = $vr['verifier'];
    }

    // Credit decision: only if confidence > threshold
    $credit = 0; $verified = 0;
    if ($ok && $confidence !== null && $confidence > $CREDIT_THRESHOLD_PERCENT) {
      $verified = 1;
      if     ($task_level === 'red')    $credit = $CREDIT_FOR_RED;
      elseif ($task_level === 'yellow') $credit = $CREDIT_FOR_YELLOW;
      else                              $credit = $CREDIT_FOR_GREEN;
    }

    // Error status for frontend (when not verified)
    $error_status = null;
    if (!$verified) {
      if (!$ok)                         $error_status = 'verification_unavailable';
      elseif ($confidence === null)     $error_status = 'no_confidence';
      elseif ($confidence <= $CREDIT_THRESHOLD_PERCENT) $error_status = 'low_confidence';
      else                              $error_status = 'unknown';
    }

    $pdo = pdo_conn($DB_HOST,$DB_NAME,$DB_USER,$DB_PASS);

    // Insert submission
    $ins = $pdo->prepare("INSERT INTO tree_submissions
      (user_id,user_email,task_level,required_trees,img_path,gps_lat,gps_lng,verified,verifier,confidence,credit_awarded)
      VALUES (:uid,:email,:lvl,:req,:img,:lat,:lng,:ver,:vrf,:conf,:cred)");
    $ins->execute([
      ':uid'=>$userId, ':email'=>$userEmail, ':lvl'=>$task_level, ':req'=>$required,
      ':img'=>'uploads/trees/'.$filename, ':lat'=>$gps_lat, ':lng'=>$gps_lng,
      ':ver'=>$verified, ':vrf'=>$verifier, ':conf'=>$confidence, ':cred'=>$credit
    ]);

    if ($verified && $credit>0) {
      $pdo->beginTransaction();
      $pdo->prepare("UPDATE users SET green_credit = green_credit + :c WHERE id=:uid")->execute([':c'=>$credit,':uid'=>$userId]);
      $pdo->prepare("INSERT INTO green_credit_log (user_id, delta, reason) VALUES (:uid,:delta,:reason)")
          ->execute([':uid'=>$userId, ':delta'=>$credit, ':reason'=>"Tree verified ({$task_level}) @ {$confidence}%"]);
      $pdo->commit();
    }

    echo json_encode([
      'status'=>'ok',
      'verified'=> (bool)$verified,
      'confidence'=>$confidence,
      'threshold'=>$CREDIT_THRESHOLD_PERCENT,
      'credit_added'=>$credit,
      'error_status'=>$error_status,
      'img'=>'uploads/trees/'.$filename
    ]);
  } catch(Throwable $e){
    http_response_code(400);
    echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
  }
  exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>SORA Labs — Zone Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="robots" content="noindex, nofollow">

  <!-- Favicon -->
  <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🗺️</text></svg>">

  <!-- Bootstrap / Icons / Font -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">

  <!-- Leaflet -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

  <style>
    :root { --glass: rgba(255,255,255,0.08); --glass-border: rgba(255,255,255,0.18); }
    html, body { height: 100%; }
    body {
      font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, 'Helvetica Neue', Arial, 'Noto Sans', sans-serif;
      background: radial-gradient(1200px 800px at 20% 10%, #6c63ff33, transparent 60%),
                  radial-gradient(1000px 700px at 80% 30%, #00e7ff33, transparent 60%),
                  radial-gradient(900px 700px at 50% 90%, #ff6ec733, transparent 60%),
                  #0b0f14;
      color: #e9eef5;
      overflow: hidden;
      padding-top: 72px;
    }
    .noise:before {
      content: "";
      position: fixed; inset: 0; pointer-events: none; mix-blend-mode: soft-light; z-index: 1000;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='160' height='160' viewBox='0 0 160 160'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='2' stitchTiles='stitch'/%3E%3CfeColorMatrix type='saturate' values='0'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='.025'/%3E%3C/svg%3E");
    }
    .glass {
      background: var(--glass);
      border: 1px solid var(--glass-border);
      backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px);
      border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.35);
    }
    .brand { letter-spacing: .6px; background: linear-gradient(90deg, #fff, #b6d7ff 60%, #c7fff6); -webkit-background-clip: text; background-clip: text; color: transparent; }
    .dot { width: 10px; height: 10px; border-radius: 50%; background: #15ff99; box-shadow: 0 0 12px #15ff99aa; display: inline-block; margin-right: 8px; animation: pulse 1.8s ease-in-out infinite; }
    @keyframes pulse { 0%, 100% { transform: scale(1); opacity: 0.9; } 50% { transform: scale(1.25); opacity: 0.6; } }

    .topbar { background: var(--glass); border: 1px solid var(--glass-border); }
    .topbar.navbar { backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px); margin: 8px 12px; border-radius: 14px; }

    .sidebar { width: 380px; max-width: 92vw; height: calc(100vh - 88px); overflow-y: auto; padding: 18px; position: relative; z-index: 1100; }
    .sidebar .form-label { color: #d9e3ee; }
    .sidebar .form-select, .sidebar input[type="datetime-local"], .sidebar .form-range {
      background-color: rgba(255,255,255,0.06); color: #e9eef5; border-color: rgba(255,255,255,0.2);
    }
    .sidebar .form-select:focus, .sidebar input[type="datetime-local"]:focus {
      box-shadow: 0 0 0 .25rem rgba(99, 179, 237, 0.25); border-color: #84c5ff;
    }
    .btn-ghost { background: rgba(255,255,255,0.06); color: #e9eef5; border: 1px solid rgba(255,255,255,0.2); }
    .btn-ghost:hover { background: rgba(255,255,255,0.12); }
    .btn-toggle.active { border-color: #fff !important; box-shadow: 0 0 0 .15rem rgba(255,255,255,.25) inset; }

    #map { height: calc(100vh - 88px); flex: 1; }
    .legend { position: absolute; bottom: 16px; left: 400px; z-index: 900; } /* behind sidebar */
    .legend .card { background: var(--glass); border: 1px solid var(--glass-border); }
    .badge-dot { display:inline-block; width:10px; height:10px; border-radius:50%; margin-right:6px; }
    .leaflet-container { background: transparent; }
    .leaflet-popup-content { color: #0b0f14; }

    .sidebar.collapsed { display: none !important; }

    @media (max-width: 991.98px) {
      body { padding-top: 64px; }
      .sidebar { display: none; }
      .sidebar.show { display: block; position: fixed; top: 72px; left: 12px; z-index: 1300; width: calc(100vw - 24px); max-width: 420px; height: calc(100vh - 84px); }
      .legend { left: 16px; } /* still behind because z-index=900 */
      #mobileFiltersBtn { display: inline-flex; }
    }
    @media (min-width: 992px) { #mobileFiltersBtn { display: none; } }

    .backdrop { display: none; position: fixed; inset: 0; z-index: 1035; background: rgba(0,0,0,0.45); }
    .backdrop.active { display: block; }

    #mobileFiltersBtn { position: fixed; bottom: 18px; right: 18px; z-index: 1500; border-radius: 999px; }

    /* Colorful modal glass for readability */
    .modal.glass-skin .modal-content{
      background:
        radial-gradient(1200px 800px at 15% 5%, rgba(108,99,255,.36), transparent 60%),
        radial-gradient(1000px 700px at 85% 25%, rgba(0,231,255,.34), transparent 60%),
        radial-gradient(900px 700px at 40% 95%, rgba(255,110,199,.32), transparent 60%),
        rgba(32,38,48,.68);
      border: 1px solid rgba(255,255,255,0.30);
      color: #f5f8ff;
      backdrop-filter: blur(14px);
      -webkit-backdrop-filter: blur(14px);
      border-radius: 16px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.40), inset 0 0 0 1px rgba(255,255,255,0.06);
    }
    .modal.glass-skin .modal-header,
    .modal.glass-skin .modal-footer { border-color: rgba(255,255,255,0.18); }
    .modal.glass-skin .modal-title { color: #ffffff; text-shadow: 0 1px 10px rgba(0,0,0,.25); }
    .modal.glass-skin .btn-close { filter: invert(1) grayscale(1) brightness(1.2); }
    .modal.glass-skin .form-label { color: #ffffff; }
    .modal.glass-skin .form-text { color: #eaf2ff; }
    .modal.glass-skin .form-control, .modal.glass-skin .form-select {
      background-color: rgba(255,255,255,0.12);
      color: #ffffff;
      border-color: rgba(255,255,255,0.34);
    }
    .modal.glass-skin .form-control::placeholder { color: #e2ebff; opacity: .85; }
    .modal.glass-skin .form-control:focus, .modal.glass-skin .form-select:focus {
      box-shadow: 0 0 0 .25rem rgba(99,179,237,0.38);
      border-color: #84c5ff;
    }

    /* Mobile contrast fix */
    @media (max-width: 991.98px) {
      .topbar,
      .topbar.navbar,
      .sidebar,
      .legend .card,
      .glass {
        background:
          radial-gradient(800px 500px at 15% 0%, rgba(108,99,255,.28), transparent 60%),
          radial-gradient(600px 400px at 100% 20%, rgba(0,231,255,.22), transparent 60%),
          rgba(18,22,30,.92) !important;
        border-color: rgba(255,255,255,.16) !important;
        color: #f4f8ff !important;
      }
      .sidebar .form-label,
      .sidebar .form-text,
      .sidebar .text-secondary,
      .sidebar .brand,
      .legend .card small,
      .legend .card strong {
        color: #f4f8ff !important;
      }
      .sidebar .form-select,
      .sidebar input[type="datetime-local"],
      .sidebar .form-range {
        background-color: rgba(255,255,255,0.08) !important;
        color: #f4f8ff !important;
        border-color: rgba(255,255,255,0.22) !important;
      }
      .btn-ghost {
        background: rgba(255,255,255,0.06) !important;
        color: #f4f8ff !important;
        border-color: rgba(255,255,255,0.28) !important;
      }
      .btn-ghost:hover { background: rgba(255,255,255,0.12) !important; }
      .badge.text-bg-dark {
        background-color: rgba(255,255,255,0.10) !important;
        color: #fff !important;
        border-color: rgba(255,255,255,0.22) !important;
      }
      #taskList .small,
      #taskList .task-pill,
      #taskList span {
        color: #f4f8ff !important;
      }
    }

    .task-pill { display:inline-flex; align-items:center; gap:8px; padding:6px 10px; border-radius:999px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.18); }
    .badge-dot { display:inline-block; width:10px; height:10px; border-radius:50%; margin-right:6px; }

    /* --- FIX: keep modals above the sidebar --- */
    .modal { z-index: 2000 !important; }
    .modal-backdrop { z-index: 1990 !important; }

    /* Optional: hide the floating Filters button when a modal is open (mobile UX) */
    .modal-open #mobileFiltersBtn { display: none !important; }

  </style>
</head>
<body class="noise">

  <!-- TOP BAR -->
  <nav class="navbar navbar-dark fixed-top topbar glass">
    <div class="container-fluid">
      <div class="d-flex align-items-center gap-2">
        <button id="toggleSidebar" class="btn btn-ghost btn-sm" type="button" aria-label="Toggle filters">
          <i class="bi bi-sliders"></i>
        </button>
        <a class="navbar-brand d-flex align-items-center gap-2" href="#">
          <span class="dot" aria-hidden="true"></span>
          <span class="brand fw-bold">SORA Labs</span>
        </a>
      </div>

      <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
        <a href="/leaderboard.php" class="btn btn-ghost btn-sm"><i class="bi bi-trophy me-1"></i>Leaderboard</a>
        <a href="/status.php" class="btn btn-ghost btn-sm"><i class="bi bi-speedometer2 me-1"></i>Overall Status</a>
        <a href="/faq.php" class="btn btn-ghost btn-sm"><i class="bi bi-question-circle me-1"></i>FAQ</a>
        <a href="/contact.php" class="btn btn-ghost btn-sm"><i class="bi bi-envelope me-1"></i>Contact</a>
        <a href="/claim.php" class="btn btn-ghost btn-sm"><i class="bi bi-patch-check-fill me-1"></i>Claim</a>

        <?php if ($isLoggedIn): ?>
          <a href="/dashboard.php" class="btn btn-light btn-sm">
            <i class="bi bi-speedometer2 me-1"></i>Dashboard
          </a>
        <?php else: ?>
          <a href="/login.php" class="btn btn-light btn-sm">
            <i class="bi bi-person me-1"></i>Login / Registration
          </a>
        <?php endif; ?>
      </div>
    </div>
  </nav>

  <div id="backdrop" class="backdrop" aria-hidden="true"></div>

  <div class="d-flex">
    <!-- Sidebar -->
    <aside id="sidebar" class="sidebar glass">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <div class="d-flex align-items-center gap-3">
          <span class="dot" aria-hidden="true"></span>
          <h1 class="h5 mb-0 brand">Zone Dashboard</h1>
        </div>
        <span class="badge text-bg-dark border border-1 border-light-subtle">Live</span>
      </div>

      <!-- Dataset/Mode Section -->
      <div class="glass p-3 mb-3">
        <label class="form-label"><i class="bi bi-database me-2"></i>Data Source</label>
        <div class="d-flex gap-2 mb-2">
          <!-- Removed Demo button by request -->
          <button id="btnReal" type="button" class="btn btn-ghost btn-toggle flex-fill">Real Data</button>
        </div>
        <!-- DataSet selector (used when NOT in Real mode) -->
        <div class="mt-2">
          <label class="form-label small mb-1">Choose DataSet</label>
          <select id="datasetId" class="form-select">
            <option value="1">ALL-BD</option>
            <option value="2">DHK-GRN-RD-1</option>
            <option value="3">DHK-GRN-RD-2</option>
            <option value="4">DataSet 4</option>
            <option value="5">DataSet 5</option>
          </select>
          <div id="datasetHelp" class="form-text"></div>
          <div id="realHelp" class="form-text" style="display:none;">Using table: <code>real_sensor_ingest</code></div>
        </div>
      </div>

      <!-- Tasks panel -->
      <div class="glass p-3 mb-3">
        <label class="form-label mb-2"><i class="bi bi-list-check me-2"></i>Tasks</label>
        <div id="taskList" class="d-grid gap-2">
          <div class="text-secondary small">Click a map circle to load a task.</div>
        </div>
      </div>

      <!-- Metric + Circle Size + Refresh -->
      <div class="glass p-3 mb-3">
        <label class="form-label"><i class="bi bi-activity me-2"></i>Metric</label>
        <select id="metric" class="form-select">
          <option value="co2">CO₂</option>
          <option value="voc">VOC</option>
          <option value="pm">Particulate Matter (PM)</option>
          <option value="nox">NOX</option>
          <option value="humidity">Humidity</option>
          <option value="temperature">Temperature</option>
        </select>

        <div class="mt-3">
          <label class="form-label"><i class="bi bi-circle-half me-2"></i>Circle Size</label>
          <input type="range" id="radius" class="form-range" min="50" max="500" step="10" value="200">
          <div class="d-flex justify-content-between small"><span>50 m</span><span>500 m</span></div>
        </div>

        <button id="refresh" class="btn btn-ghost w-100 mt-3">
          <i class="bi bi-arrow-clockwise me-1"></i>Refresh
        </button>
      </div>

      <!-- Time Range -->
      <div class="glass p-3 mb-3">
        <label class="form-label"><i class="bi bi-clock-history me-2"></i>Time Range</label>
        <select id="range" class="form-select mb-2">
          <option value="yesterday">Yesterday</option>
          <option value="last_week">Last week</option>
          <option value="past_15_days" selected>Past 15 days</option>
          <option value="last_month">Last month</option>
          <option value="custom">Custom</option>
        </select>
        <div id="customRange" class="row g-2" style="display:none;">
          <div class="col-12">
            <label class="form-label">From</label>
            <input type="datetime-local" id="from" class="form-control">
          </div>
          <div class="col-12">
            <label class="form-label">To</label>
            <input type="datetime-local" id="to" class="form-control">
          </div>
        </div>
        <button id="apply" class="btn btn-ghost w-100 mt-3">
          <i class="bi bi-filter-circle me-1"></i>Apply
        </button>
      </div>

      <!-- Legend -->
      <div class="glass p-3">
        <div class="d-flex align-items-center mb-2 flex-wrap gap-2">
          <span class="badge-dot" style="background:#22c55e"></span><small>Safe</small>
          <span class="badge-dot" style="background:#eab308"></span><small>Warning</small>
          <span class="badge-dot" style="background:#ef4444"></span><small>Critical</small>
        </div>
        <small class="text-secondary">Colors adapt per metric thresholds.</small>
      </div>
    </aside>

    <!-- Map -->
    <main id="map"></main>
  </div>

  <!-- Floating Legend -->
  <div class="legend">
    <div class="card glass p-2">
      <div class="d-flex align-items-center">
        <strong class="me-2">Legend</strong>
        <span id="legendMetric" class="text-muted small">CO₂</span>
      </div>
      <div class="mt-1"><span class="badge-dot" style="background:#22c55e"></span><small>Safe</small></div>
      <div><span class="badge-dot" style="background:#eab308"></span><small>Warning</small></div>
      <div><span class="badge-dot" style="background:#ef4444"></span><small>Critical</small></div>
    </div>
  </div>

  <!-- Mobile floating Filters button -->
  <button id="mobileFiltersBtn" class="btn btn-light btn-lg">
    <i class="bi bi-sliders"></i> Filters
  </button>

  <!-- Upload Proof Modal -->
  <div class="modal fade glass-skin" id="proofModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <form id="proofForm" class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-tree me-2"></i>Upload Tree Photo</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <?php if (!$isLoggedIn): ?>
            <div class="alert alert-warning mb-0">Please log in to submit proof.</div>
          <?php else: ?>
            <div class="mb-2">
              <div class="small text-white">Task</div>
              <div id="proofTaskText" class="fw-semibold">—</div>
            </div>

            <div class="mb-3">
              <label class="form-label">Photo of newly planted tree</label>
              <input type="file" class="form-control" name="image" id="image" accept="image/*" required>
              <div class="form-text">JPG/PNG/WEBP • Make sure the tree is fully visible and in focus.</div>
            </div>

            <input type="hidden" name="task_level" id="task_level">
            <input type="hidden" name="required_trees" id="required_trees">
            <input type="hidden" name="gps_lat" id="gps_lat">
            <input type="hidden" name="gps_lng" id="gps_lng">

            <div id="geoStatus" class="small text-white">Grabbing your GPS location…</div>
            <div id="geoCoords" class="small mt-1 text-white">Current GPS: —, —</div>
            <div class="small mt-2 text-white" id="creditHint">
              Credit is granted only if confidence is greater than <strong><?=htmlspecialchars((string)$CREDIT_THRESHOLD_PERCENT)?></strong>%.
            </div>
          <?php endif; ?>
        </div>
        <div class="modal-footer">
          <?php if ($isLoggedIn): ?>
            <button class="btn btn-ghost" data-bs-dismiss="modal" type="button">Cancel</button>
            <button class="btn btn-light" type="submit"><i class="bi bi-cloud-arrow-up me-1"></i>Submit</button>
          <?php else: ?>
            <a href="/login.php" class="btn btn-light">Login</a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <!-- Success Modal -->
  <div class="modal fade glass-skin" id="successModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header border-0">
          <h5 class="modal-title"><i class="bi bi-patch-check-fill me-2"></i>Tree Verified</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body pt-0">
          <p class="mb-2">Your photo passed verification 🎉</p>
          <p class="mb-1">Confidence: <span id="succConf">—</span>% (Threshold: <span id="succThresh">—</span>%)</p>
          <p class="mb-0">Green Credit added: <strong id="succCredit">0</strong></p>
        </div>
        <div class="modal-footer border-0">
          <a href="/dashboard.php" class="btn btn-light"><i class="bi bi-speedometer2 me-1"></i>Go to Dashboard</a>
          <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Fail/Low-Confidence/Null Modal -->
  <div class="modal fade glass-skin" id="failModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header border-0">
          <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill me-2"></i>Verification Issue</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body pt-0">
          <p class="mb-2">Status: <strong id="failStatus">—</strong></p>
          <p class="mb-1">Confidence: <span id="failConf">—</span>% (Threshold: <span id="failThresh">—</span>%)</p>
          <p class="mb-0">Please <strong>plant a real tree</strong> 🌱, take a clear photo (good lighting, leaves/trunk visible, minimal background clutter), and try again.</p>
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="btn btn-light" id="btnFailRetry"><i class="bi bi-arrow-counterclockwise me-1"></i>Try Again</button>
          <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Login Required Modal (floating dialogue) -->
  <div class="modal fade glass-skin" id="loginRequiredModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header border-0">
          <h5 class="modal-title"><i class="bi bi-shield-lock-fill me-2"></i>Login Required</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body pt-0">
          <p class="mb-2">You need to sign in to upload proof and earn <strong>Green Credit</strong>.</p>
          <ul class="small mb-0">
            <li>Track your verified tree plantings</li>
            <li>Appear on the Leaderboard</li>
            <li>Redeem Green Credit in partner stores</li>
          </ul>
        </div>
        <div class="modal-footer border-0">
          <a href="/login.php" class="btn btn-light"><i class="bi bi-box-arrow-in-right me-1"></i>Login</a>
          <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Maybe later</button>
        </div>
      </div>
    </div>
  </div>

  <!-- JS -->
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    // PHP auth state -> JS
    const IS_LOGGED_IN = <?= $isLoggedIn ? 'true' : 'false' ?>;

    // State
    const STORAGE_SOURCE  = 'soralabs_source';      // 'demo' (dataset mode) OR 'real'
    const STORAGE_DATASET = 'soralabs_dataset_id';  // 1..5 for dataset mode

    // Default to dataset mode ('demo'); user can toggle Real with the button
    let source = localStorage.getItem(STORAGE_SOURCE) || 'demo';
    let datasetId = parseInt(localStorage.getItem(STORAGE_DATASET) || '1', 10);
    if (isNaN(datasetId) || datasetId < 1 || datasetId > 5) datasetId = 1;

    let map; let circles = []; let popup = L.popup();

    const TASKS = {
      green: { label: 'Area OK', required: 0, color:'#22c55e', desc:'No action needed' },
      yellow:{ label: 'Plant 1 tree', required: 1, color:'#eab308', desc:'Help improve air by planting 1 tree' },
      red:   { label: 'Plant 2 trees', required: 2, color:'#ef4444', desc:'High pollution. Plant 2 trees' }
    };
    let currentTask = null;

    // DOM
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('backdrop');
    const toggleSidebarBtn = document.getElementById('toggleSidebar');
    const mobileFiltersBtn = document.getElementById('mobileFiltersBtn');
    const btnReal = document.getElementById('btnReal');
    const datasetSel = document.getElementById('datasetId');
    const datasetHelp = document.getElementById('datasetHelp');
    const realHelp = document.getElementById('realHelp');
    const metricSel = document.getElementById('metric');
    const rangeSel  = document.getElementById('range');
    const radiusSel = document.getElementById('radius');
    const customBox = document.getElementById('customRange');
    const fromInp   = document.getElementById('from');
    const toInp     = document.getElementById('to');
    const legendMetric = document.getElementById('legendMetric');
    const taskList = document.getElementById('taskList');

    const proofModalEl = document.getElementById('proofModal');
    const proofModal = new bootstrap.Modal(proofModalEl);
    const successModalEl = document.getElementById('successModal');
    const successModal = new bootstrap.Modal(successModalEl);
    const failModalEl = document.getElementById('failModal');
    const failModal = new bootstrap.Modal(failModalEl);
    const loginRequiredModalEl = document.getElementById('loginRequiredModal');
    const loginRequiredModal = new bootstrap.Modal(loginRequiredModalEl);

    document.getElementById('btnFailRetry').addEventListener('click', () => {
      failModal.hide();
      setTimeout(() => proofModal.show(), 180);
    });

    // Map
    function initMap() {
      map = L.map('map', { zoomControl: true }).setView([23.7808875, 90.2792371], 11);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 20,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a>'
      }).addTo(map);

      if (window.innerWidth < 992) hideSidebar(true);
      datasetSel.value = String(datasetId);
      syncModeUI();
      loadAndRender();
    }
    document.addEventListener('DOMContentLoaded', initMap);

    // Sidebar show/hide
    function showSidebar(isInit=false) {
      if (window.innerWidth < 992) { sidebar.classList.add('show'); backdrop.classList.add('active'); }
      else { sidebar.classList.remove('collapsed'); }
      if (!isInit) setTimeout(() => map.invalidateSize(true), 250);
    }
    function hideSidebar(isInit=false) {
      if (window.innerWidth < 992) { sidebar.classList.remove('show'); backdrop.classList.remove('active'); }
      else { sidebar.classList.add('collapsed'); }
      if (!isInit) setTimeout(() => map.invalidateSize(true), 250);
    }
    function toggleSidebar() {
      if (window.innerWidth < 992) {
        const visible = sidebar.classList.contains('show');
        visible ? hideSidebar() : showSidebar();
      } else {
        const hidden = sidebar.classList.contains('collapsed');
        hidden ? showSidebar() : hideSidebar();
      }
    }
    toggleSidebarBtn.addEventListener('click', toggleSidebar);
    mobileFiltersBtn.addEventListener('click', toggleSidebar);
    backdrop.addEventListener('click', hideSidebar);

    // Real button toggles between dataset mode (demo) and real mode
    btnReal.addEventListener('click', () => {
      source = (source === 'real') ? 'demo' : 'real';
      localStorage.setItem(STORAGE_SOURCE, source);
      syncModeUI();
      loadAndRender();
    });

    // DataSet dropdown (only active when source !== 'real')
    datasetSel.addEventListener('change', () => {
      const v = parseInt(datasetSel.value, 10);
      datasetId = (isNaN(v) || v < 1 || v > 5) ? 1 : v;
      localStorage.setItem(STORAGE_DATASET, String(datasetId));
      if (source !== 'real') loadAndRender();
    });

    // Mode UI sync
    function syncModeUI() {
      // Toggle button active state
      btnReal.classList.toggle('active', source === 'real');

      // Enable/disable dataset picker depending on mode
      const dsDisabled = (source === 'real');
      datasetSel.disabled = dsDisabled;
      datasetHelp.style.display = dsDisabled ? 'none' : 'block';
      realHelp.style.display = dsDisabled ? 'block' : 'none';

      // Optional: change button text to indicate toggle state
      btnReal.textContent = (source === 'real') ? 'Real Data (ON)' : 'Real Data';
      btnReal.title = (source === 'real') ? 'Click to switch back to DataSet tables' : 'Click to use real_sensor_ingest';
    }

    // Range + controls
    rangeSel.addEventListener('change', () => {
      customBox.style.display = (rangeSel.value === 'custom') ? 'block' : 'none';
    });
    document.getElementById('apply').addEventListener('click', () => { loadAndRender(); if (window.innerWidth < 992) hideSidebar(); });
    document.getElementById('refresh').addEventListener('click', loadAndRender);

    // Fetch + render
    function loadAndRender() {
      clearCircles();
      const base = window.location.href.split('?')[0];
      const params = new URLSearchParams({
        action: 'data',
        source: source,                          // 'demo' (dataset mode) or 'real'
        dataset_id: datasetId,                   // used only when demo/dataset mode
        metric: metricSel.value,
        range: rangeSel.value
      });
      if (rangeSel.value === 'custom') {
        if (fromInp.value) params.set('from', fromInp.value.replace('T', ' ') + ':00');
        if (toInp.value)   params.set('to',   toInp.value.replace('T', ' ')   + ':00');
      }
      legendMetric.textContent = metricSel.options[metricSel.selectedIndex].text;
      fetch(base + '?' + params.toString())
        .then(r => r.json())
        .then(payload => { if (payload.status === 'ok') plotPoints(payload.data); else showToast('Dataset error: '+(payload.message||'Unknown')); })
        .catch(err => { console.error(err); showToast('Failed to load data'); });
    }
    function clearCircles() { circles.forEach(c => map.removeLayer(c)); circles = []; }

    // Colors & metrics
    function colorFor(metric, value) {
      const SAFE = '#22c55e', WARN = '#eab308', CRIT = '#ef4444', NA = '#64748b';
      if (value == null || isNaN(value)) return NA;
      switch (metric) {
        case 'co2':        if (value <= 800)  return SAFE; if (value <= 1200) return WARN; return CRIT;
        case 'voc':        if (value <= 220)  return SAFE; if (value <= 660)  return WARN; return CRIT;
        case 'nox':        if (value <= 100)  return SAFE; if (value <= 200)  return WARN; return CRIT;
        case 'humidity':   if (value >= 30 && value <= 60) return SAFE;
                           if ((value >= 20 && value < 30) || (value > 60 && value <= 70)) return WARN;
                           return CRIT;
        case 'temperature':if (value >= 20 && value <= 30) return SAFE;
                           if ((value > 30 && value <= 35) || (value >= 18 && value < 20)) return WARN;
                           return CRIT;
        default: return NA;
      }
    }
    function colorForPM(pm25, pm10) {
      const SAFE = '#22c55e', WARN = '#eab308', CRIT = '#ef4444', NA = '#64748b';
      function level(val, safeMax, warnMax) {
        if (val == null || isNaN(val)) return 0;
        if (val <= safeMax) return 1;
        if (val <= warnMax) return 2;
        return 3;
      }
      const l25 = level(pm25, 35, 55);
      const l10 = level(pm10, 150, 250);
      const worst = Math.max(l25, l10);
      if (worst === 1) return SAFE;
      if (worst === 2) return WARN;
      if (worst === 3) return CRIT;
      return NA;
    }
    function valueByMetric(point, metric) {
      switch (metric) {
        case 'co2': return point.co2;
        case 'voc': return point.voc;
        case 'nox': return point.nox;
        case 'pm':  return { pm25: point.pm25, pm10: point.pm10 };
        case 'humidity': return point.humidity;
        case 'temperature': return point.temperature;
        default: return null;
      }
    }

    // Plot points + attach tasks
    function plotPoints(points) {
      const radius = Number(radiusSel.value);
      const bounds = [];
      points.forEach(p => {
        const v = valueByMetric(p, metricSel.value);
        const fill = (metricSel.value === 'pm')
          ? colorForPM(v?.pm25 ?? null, v?.pm10 ?? null)
          : colorFor(metricSel.value, v);

        const circle = L.circle([p.lat, p.lng], {
          radius: radius, color: fill, weight: 1, opacity: 0.8,
          fillColor: fill, fillOpacity: 0.26
        }).addTo(map);

        // Decide task level from color
        let level = 'green';
        if (fill === '#eab308') level = 'yellow';
        else if (fill === '#ef4444') level = 'red';

        circle.on('click', () => {
          const html = `
            <div style="min-width:240px">
              <div class="fw-bold mb-1">Sensor Point #${p.id}</div>
              <div class="small text-muted mb-2">${p.ts ?? ''}</div>
              <div><strong>CO₂:</strong> ${p.co2 ?? '—'}</div>
              <div><strong>VOC:</strong> ${p.voc ?? '—'}</div>
              <div><strong>NOX:</strong> ${p.nox ?? '—'}</div>
              <div><strong>PM2.5:</strong> ${p.pm25 ?? '—'}</div>
              <div><strong>PM10:</strong> ${p.pm10 ?? '—'}</div>
              <div><strong>Humidity:</strong> ${p.humidity ?? '—'}%</div>
              <div><strong>Temperature:</strong> ${p.temperature ?? '—'}°C</div>
            </div>`;
          popup.setLatLng([p.lat, p.lng]).setContent(html).openOn(map);

          const task = (level==='red') ? TASKS.red : (level==='yellow' ? TASKS.yellow : TASKS.green);
          currentTask = { level, required: task.required, lat: p.lat, lng: p.lng };
          renderTaskList();
          if (window.innerWidth < 992) showSidebar();
        });

        circles.push(circle);
        bounds.push([p.lat, p.lng]);
      });

      if (bounds.length > 0) { try { map.fitBounds(bounds, { padding: [40, 40] }); } catch (e) {} }
    }

    function renderTaskList() {
      if (!currentTask) {
        taskList.innerHTML = '<div class="text-secondary small">Click a map circle to load a task.</div>';
        return;
      }
      const t = TASKS[currentTask.level];
      const disabled = (t.required === 0);
      taskList.innerHTML = `
        <div class="d-flex align-items-center justify-content-between">
          <span class="task-pill" style="border-color:${t.color};">
            <span class="badge-dot" style="background:${t.color}"></span>
            <span>${t.label}</span>
          </span>
          <span class="small text-secondary ms-2">at ${currentTask.lat.toFixed(4)}, ${currentTask.lng.toFixed(4)}</span>
        </div>
        <div class="small text-secondary mt-2">${t.desc}</div>
        <button class="btn btn-ghost mt-3" id="btnUploadProof" ${disabled?'disabled':''}>
          <i class="bi bi-cloud-arrow-up me-1"></i>Upload Proof
        </button>
      `;
      const btn = document.getElementById('btnUploadProof');
      if (btn) btn.addEventListener('click', openProofModal);
    }

    function openProofModal() {
      // If not logged in, show themed floating login dialog instead of the proof form
      if (!IS_LOGGED_IN) {
        loginRequiredModal.show();
        return;
      }

      if (!currentTask) return;
      const t = TASKS[currentTask.level];
      document.getElementById('proofTaskText').textContent = t.label + ` (Required: ${t.required})`;
      document.getElementById('task_level').value = currentTask.level;
      document.getElementById('required_trees').value = t.required;

      const geoStatus = document.getElementById('geoStatus');
      const geoCoords = document.getElementById('geoCoords');

      geoStatus.classList.add('text-white');
      geoCoords.classList.add('text-white');

      geoStatus.textContent = 'Grabbing your GPS location…';
      geoCoords.textContent = 'Current GPS: —, —';

      navigator.geolocation.getCurrentPosition(
        (pos) => {
          const lat = parseFloat(pos.coords.latitude).toFixed(7);
          const lng = parseFloat(pos.coords.longitude).toFixed(7);
          document.getElementById('gps_lat').value = lat;
          document.getElementById('gps_lng').value = lng;
          geoStatus.textContent = `Location captured: ${parseFloat(lat).toFixed(5)}, ${parseFloat(lng).toFixed(5)}`;
          geoCoords.textContent = `Current GPS: ${parseFloat(lat).toFixed(5)}, ${parseFloat(lng).toFixed(5)}`;
        },
        () => {
          // Fallback to sensor point
          const lat = currentTask.lat.toFixed(7);
          const lng = currentTask.lng.toFixed(7);
          document.getElementById('gps_lat').value = lat;
          document.getElementById('gps_lng').value = lng;
          geoStatus.textContent = 'Using sensor point location (browser GPS unavailable).';
          geoCoords.textContent = `Current GPS: ${parseFloat(lat).toFixed(5)}, ${parseFloat(lng).toFixed(5)}`;
        },
        { enableHighAccuracy:true, timeout:7000, maximumAge:0 }
      );

      proofModal.show();
    }

    // Submit handler
    const proofForm = document.getElementById('proofForm');
    if (proofForm) {
      proofForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const fd = new FormData();
        const imgInput = document.getElementById('image');
        if (!imgInput.files || imgInput.files.length === 0) { showToast('Please select an image.'); return; }
        fd.append('image', imgInput.files[0]);
        fd.append('task_level', document.getElementById('task_level').value);
        fd.append('required_trees', document.getElementById('required_trees').value);
        fd.append('gps_lat', document.getElementById('gps_lat').value);
        fd.append('gps_lng', document.getElementById('gps_lng').value);

        const submitBtn = proofForm.querySelector('button[type="submit"]');
        submitBtn.disabled = true; submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';

        try {
          const resp = await fetch(window.location.pathname + '?action=submit_tree', { method:'POST', body: fd });
          const json = await resp.json();
          if (!resp.ok || json.status!=='ok') throw new Error(json.message || 'Failed');

          setTimeout(() => proofModal.hide(), 200);

          if (json.verified) {
            document.getElementById('succConf').textContent   = json.confidence ?? '—';
            document.getElementById('succThresh').textContent = json.threshold ?? '—';
            document.getElementById('succCredit').textContent = json.credit_added ?? 0;
            successModal.show();
          } else {
            const statusMap = {
              verification_unavailable: 'Verification unavailable',
              no_confidence: 'No confidence returned',
              low_confidence: 'Confidence too low',
              unknown: 'Unknown error',
              pending: 'Pending'
            };
            const label = statusMap[json.error_status] || 'Verification failed';
            document.getElementById('failStatus').textContent = label;
            document.getElementById('failConf').textContent   = (json.confidence != null) ? json.confidence : '—';
            document.getElementById('failThresh').textContent = json.threshold ?? '—';
            failModal.show();
          }

        } catch(err) {
          showToast('❌ '+(err.message||'Submission failed'));
        } finally {
          submitBtn.disabled = false;
          submitBtn.innerHTML = '<i class="bi bi-cloud-arrow-up me-1"></i>Submit';
          proofForm.reset();
        }
      });
    }

    // Simple toast
    function showToast(msg) {
      const el = document.createElement('div');
      el.className = 'toast align-items-center text-bg-dark border-0 position-fixed bottom-0 end-0 m-3';
      el.role = 'alert'; el.ariaLive='assertive'; el.ariaAtomic='true';
      el.innerHTML = `
        <div class="d-flex">
          <div class="toast-body">${msg}</div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>`;
      document.body.appendChild(el);
      const t = new bootstrap.Toast(el, { delay: 4000 }); t.show();
      el.addEventListener('hidden.bs.toast', ()=> el.remove());
    }

    // Range sidebar auto-behavior
    window.addEventListener('resize', () => {
      if (window.innerWidth < 992) { sidebar.classList.remove('collapsed'); hideSidebar(true); }
      else { backdrop.classList.remove('active'); sidebar.classList.remove('show'); showSidebar(true); }
      setTimeout(() => map.invalidateSize(true), 250);
    });
  </script>
</body>
</html>
