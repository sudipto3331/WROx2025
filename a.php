<?php
// zone_dashboard_leaflet.php — Leaflet + OSM • Glassmorphism Theme • Bootstrap • MySQL
// Mobile-friendly: collapsible sidebar, top-bar toggle + mobile FAB
// Dataset toggle: Demo (sensor_ingest) / Real (rea_sensor_ingest)

$DB_HOST = '194.233.77.177';
$DB_NAME = 'soralabs_masterdb';
$DB_USER = 'soralabs_masterdb';
$DB_PASS = ']Pi{5,^)G}]D';

// ========================
// DATA ENDPOINT (AJAX)
// ========================
if (isset($_GET['action']) && $_GET['action'] === 'data') {
    header('Content-Type: application/json; charset=utf-8');

    $source = $_GET['source'] ?? 'demo'; // demo|real
    $allowedTables = ['demo' => 'sensor_ingest', 'real' => 'rea_sensor_ingest'];
    $table = $allowedTables[$source] ?? $allowedTables['demo'];

    $metric = $_GET['metric'] ?? 'co2'; // co2|voc|pm|nox|humidity|temperature
    $range  = $_GET['range']  ?? 'past_15_days'; // yesterday|last_week|past_15_days|last_month|custom
    $from   = $_GET['from']   ?? null;  // 'YYYY-MM-DD HH:MM:SS'
    $to     = $_GET['to']     ?? null;

    date_default_timezone_set('Asia/Dhaka');
    $now = new DateTime('now');

    switch ($range) {
        case 'yesterday':
            $start = (new DateTime('yesterday'))->setTime(0,0,0);
            $end   = (new DateTime('yesterday'))->setTime(23,59,59);
            break;
        case 'last_week':
            $start = (clone $now)->modify('-7 days')->setTime(0,0,0);
            $end   = (clone $now)->setTime(23,59,59);
            break;
        case 'past_15_days':
            $start = (clone $now)->modify('-15 days')->setTime(0,0,0);
            $end   = (clone $now)->setTime(23,59,59);
            break;
        case 'last_month':
            $start = (new DateTime('first day of last month'))->setTime(0,0,0);
            $end   = (new DateTime('last day of last month'))->setTime(23,59,59);
            break;
        case 'custom':
            $start = $from ? new DateTime($from) : (clone $now)->modify('-1 day');
            $end   = $to   ? new DateTime($to)   : (clone $now);
            break;
        default:
            $start = (clone $now)->modify('-15 days')->setTime(0,0,0);
            $end   = (clone $now)->setTime(23,59,59);
            break;
    }

    try {
        $pdo = new PDO(
            "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
            $DB_USER,
            $DB_PASS,
            [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC ]
        );

        $sql = "SELECT id, co2_level, voc, nox, particulate_matter,
                       humidity_scd30, humidity_sen55,
                       temperature_scd30, temperature_sen55,
                       gps_lat, gps_long, `time`, create_time
                FROM `{$table}`
                WHERE COALESCE(`time`, `create_time`) BETWEEN :start AND :end
                  AND gps_lat IS NOT NULL AND gps_long IS NOT NULL";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':start' => $start->format('Y-m-d H:i:s'),
            ':end'   => $end->format('Y-m-d H:i:s'),
        ]);

        $rows = [];
        while ($r = $stmt->fetch()) {
            $co2 = isset($r['co2_level']) ? floatval($r['co2_level']) : null;
            $voc = isset($r['voc'])       ? floatval($r['voc'])       : null;
            $nox = array_key_exists('nox', $r) && $r['nox'] !== null ? floatval($r['nox']) : null;

            // Parse PM string to PM2.5 / PM10 (supports "32,80" or "PM2.5:32;PM10:80")
            $pm25 = null; $pm10 = null;
            if (!empty($r['particulate_matter'])) {
                $pmStr = (string)$r['particulate_matter'];
                if (preg_match_all('/\d+(?:\.\d+)?/', $pmStr, $m)) {
                    if (isset($m[0][0])) $pm25 = floatval($m[0][0]);
                    if (isset($m[0][1])) $pm10 = floatval($m[0][1]);
                }
            }

            // Merge humidity & temperature
            $h1 = isset($r['humidity_scd30']) ? floatval($r['humidity_scd30']) : null;
            $h2 = isset($r['humidity_sen55']) ? floatval($r['humidity_sen55']) : null;
            $humidity = (!is_null($h1) && !is_null($h2)) ? ($h1 + $h2)/2.0 : (!is_null($h1) ? $h1 : $h2);

            $t1 = isset($r['temperature_scd30']) ? floatval($r['temperature_scd30']) : null;
            $t2 = isset($r['temperature_sen55']) ? floatval($r['temperature_sen55']) : null;
            $temperature = (!is_null($t1) && !is_null($t2)) ? ($t1 + $t2)/2.0 : (!is_null($t1) ? $t1 : $t2);

            $lat = floatval($r['gps_lat']);
            $lng = floatval($r['gps_long']);
            if (!is_finite($lat) || !is_finite($lng)) continue;

            $timestamp = $r['time'] ?? $r['create_time'];
            $rows[] = [
                'id'          => intval($r['id']),
                'lat'         => $lat,
                'lng'         => $lng,
                'co2'         => $co2,
                'voc'         => $voc,
                'nox'         => $nox,
                'pm25'        => $pm25,
                'pm10'        => $pm10,
                'humidity'    => is_null($humidity) ? null : round($humidity, 2),
                'temperature' => is_null($temperature) ? null : round($temperature, 2),
                'ts'          => $timestamp,
            ];
        }

        echo json_encode([
            'status' => 'ok',
            'source' => $source,
            'table'  => $table,
            'metric' => $metric,
            'range'  => $range,
            'from'   => $start->format('Y-m-d H:i:s'),
            'to'     => $end->format('Y-m-d H:i:s'),
            'count'  => count($rows),
            'data'   => $rows
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
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
      padding-top: 72px; /* space for fixed top bar */
    }
    .noise:before {
      content: "";
      position: fixed; inset: 0;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='160' height='160' viewBox='0 0 160 160'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='2' stitchTiles='stitch'/%3E%3CfeColorMatrix type='saturate' values='0'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='.025'/%3E%3C/svg%3E");
      pointer-events: none; mix-blend-mode: soft-light; z-index: 1000;
    }
    .glass {
      background: var(--glass);
      border: 1px solid var(--glass-border);
      backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px);
      border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.35);
    }
    .brand {
      letter-spacing: 0.6px;
      background: linear-gradient(90deg, #ffffff, #b6d7ff 60%, #c7fff6);
      -webkit-background-clip: text; background-clip: text; color: transparent;
    }
    .dot { width: 10px; height: 10px; border-radius: 50%; background: #15ff99; box-shadow: 0 0 12px #15ff99aa; display: inline-block; margin-right: 8px; animation: pulse 1.8s ease-in-out infinite; }
    @keyframes pulse { 0%, 100% { transform: scale(1); opacity: 0.9; } 50% { transform: scale(1.25); opacity: 0.6; } }

    /* Top bar */
    .topbar { background: var(--glass); border: 1px solid var(--glass-border); }
    .topbar.navbar { backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px); margin: 8px 12px; border-radius: 14px; }

    /* Layout */
    .sidebar { width: 380px; max-width: 92vw; height: calc(100vh - 88px); overflow-y: auto; padding: 18px; }
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

    /* Map area */
    #map { height: calc(100vh - 88px); flex: 1; }
    .legend { position: absolute; bottom: 16px; left: 400px; z-index: 1200; }
    .legend .card { background: var(--glass); border: 1px solid var(--glass-border); }
    .badge-dot { display:inline-block; width:10px; height:10px; border-radius:50%; margin-right:6px; }
    .leaflet-container { background: transparent; }
    .leaflet-popup-content { color: #0b0f14; }

    /* Sidebar collapse behavior (desktop & mobile) */
    .sidebar.collapsed { display: none !important; }

    /* Mobile overlay sidebar */
    @media (max-width: 991.98px) {
      body { padding-top: 64px; }
      .sidebar { display: none; }
      .sidebar.show {
        display: block;
        position: fixed;
        top: 72px;
        left: 12px;
        z-index: 1040;
        width: calc(100vw - 24px);
        max-width: 420px;
        height: calc(100vh - 84px);
      }
      .legend { left: 16px; }
      #mobileFiltersBtn { display: inline-flex; }
    }
    @media (min-width: 992px) {
      #mobileFiltersBtn { display: none; }
    }

    /* Backdrop for mobile sidebar */
    .backdrop {
      display: none;
      position: fixed; inset: 0; z-index: 1035;
      background: rgba(0,0,0,0.45);
    }
    .backdrop.active { display: block; }

    /* Floating mobile Filters button */
    #mobileFiltersBtn {
      position: fixed; bottom: 18px; right: 18px; z-index: 1500;
      border-radius: 999px;
    }
  </style>
</head>
<body class="noise">

  <!-- TOP BAR -->
  <nav class="navbar navbar-dark fixed-top topbar glass">
    <div class="container-fluid">
      <!-- Left: brand + toggle -->
      <div class="d-flex align-items-center gap-2">
        <button id="toggleSidebar" class="btn btn-ghost btn-sm" type="button" aria-label="Toggle filters">
          <i class="bi bi-sliders"></i>
        </button>
        <a class="navbar-brand d-flex align-items-center gap-2" href="#">
          <span class="dot" aria-hidden="true"></span>
          <span class="brand fw-bold">SORA Labs</span>
        </a>
      </div>

      <!-- Right: menu buttons (all to the LEFT of Login/Registration) -->
      <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
        <a href="#" class="btn btn-ghost btn-sm"><i class="bi bi-trophy me-1"></i>Leaderboard</a>
        <a href="#" class="btn btn-ghost btn-sm"><i class="bi bi-speedometer2 me-1"></i>Overall Status</a>
        <a href="#" class="btn btn-ghost btn-sm"><i class="bi bi-question-circle me-1"></i>FAQ</a>
        <a href="#" class="btn btn-ghost btn-sm"><i class="bi bi-envelope me-1"></i>Contact</a>
        <a href="#" class="btn btn-ghost btn-sm"><i class="bi bi-patch-check-fill me-1"></i>Claim</a>
        <a href="#" class="btn btn-light btn-sm"><i class="bi bi-person me-1"></i>Login / Registration</a>
      </div>
    </div>
  </nav>

  <!-- Mobile overlay backdrop -->
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

      <!-- Dataset Toggle -->
      <div class="glass p-3 mb-3">
        <label class="form-label"><i class="bi bi-database me-2"></i>Dataset</label>
        <div class="d-flex gap-2">
          <button id="btnDemo" type="button" class="btn btn-ghost btn-toggle flex-fill">Demo Data</button>
          <button id="btnReal" type="button" class="btn btn-ghost btn-toggle flex-fill">Real Data</button>
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
          <span class="badge-dot" style="background:#22c55e"></span><small class="me-3">Safe</small>
          <span class="badge-dot" style="background:#eab308"></span><small class="me-3">Warning</small>
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

  <!-- JS -->
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    // ========================
    // State: dataset source (demo|real) persisted in localStorage
    // ========================
    const STORAGE_SOURCE = 'soralabs_source';
    let source = localStorage.getItem(STORAGE_SOURCE) || 'demo';

    // ========================
    // Leaflet Map
    // ========================
    let map; let circles = []; let popup = L.popup();

    function initMap() {
      map = L.map('map', { zoomControl: true }).setView([23.7808875, 90.2792371], 11);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 20,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a>'
      }).addTo(map);

      // Start with sidebar hidden on mobile
      if (window.innerWidth < 992) hideSidebar(true);
      syncDatasetButtons();
      loadAndRender();
    }
    document.addEventListener('DOMContentLoaded', initMap);

    // ========================
    // UI Elements
    // ========================
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('backdrop');
    const toggleSidebarBtn = document.getElementById('toggleSidebar');
    const mobileFiltersBtn = document.getElementById('mobileFiltersBtn');

    const btnDemo = document.getElementById('btnDemo');
    const btnReal = document.getElementById('btnReal');
    const metricSel = document.getElementById('metric');
    const rangeSel  = document.getElementById('range');
    const radiusSel = document.getElementById('radius');
    const customBox = document.getElementById('customRange');
    const fromInp   = document.getElementById('from');
    const toInp     = document.getElementById('to');
    const legendMetric = document.getElementById('legendMetric');

    // Sidebar show/hide handlers
    function showSidebar(isInit=false) {
      if (window.innerWidth < 992) {
        sidebar.classList.add('show');
        backdrop.classList.add('active');
      } else {
        sidebar.classList.remove('collapsed');
      }
      if (!isInit) setTimeout(() => map.invalidateSize(true), 250);
    }
    function hideSidebar(isInit=false) {
      if (window.innerWidth < 992) {
        sidebar.classList.remove('show');
        backdrop.classList.remove('active');
      } else {
        sidebar.classList.add('collapsed');
      }
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

    // Auto-hide on resize to smaller screens
    window.addEventListener('resize', () => {
      if (window.innerWidth < 992) {
        // move to overlay mode
        sidebar.classList.remove('collapsed');
        hideSidebar(true);
      } else {
        // desktop mode
        backdrop.classList.remove('active');
        sidebar.classList.remove('show');
        showSidebar(true);
      }
      setTimeout(() => map.invalidateSize(true), 250);
    });

    // Dataset
    btnDemo.addEventListener('click', () => { source = 'demo'; localStorage.setItem(STORAGE_SOURCE, source); syncDatasetButtons(); loadAndRender(); });
    btnReal.addEventListener('click', () => { source = 'real'; localStorage.setItem(STORAGE_SOURCE, source); syncDatasetButtons(); loadAndRender(); });

    function syncDatasetButtons() {
      btnDemo.classList.toggle('active', source === 'demo');
      btnReal.classList.toggle('active', source === 'real');
    }

    rangeSel.addEventListener('change', () => {
      customBox.style.display = (rangeSel.value === 'custom') ? 'block' : 'none';
    });
    document.getElementById('apply').addEventListener('click', () => { loadAndRender(); if (window.innerWidth < 992) hideSidebar(); });
    document.getElementById('refresh').addEventListener('click', loadAndRender);

    // ========================
    // Fetch + Render
    // ========================
    function loadAndRender() {
      clearCircles();
      const base = window.location.href.split('?')[0];
      const params = new URLSearchParams({
        action: 'data',
        source: source,
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
        .then(payload => { if (payload.status === 'ok') plotPoints(payload.data); })
        .catch(console.error);
    }

    function clearCircles() { circles.forEach(c => map.removeLayer(c)); circles = []; }

    // ========================
    // Threshold Colors
    // ========================
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

    // PM worst-of
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

    // ========================
    // Plotting
    // ========================
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
        });

        circles.push(circle);
        bounds.push([p.lat, p.lng]);
      });

      if (bounds.length > 0) {
        try { map.fitBounds(bounds, { padding: [40, 40] }); } catch (e) {}
      }
    }
  </script>
</body>
</html>
