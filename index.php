<?php
// Simple AUTH for header buttons
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$isLoggedIn = !empty($_SESSION['user_id']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>SORA Labs — Autonomous Drone Air-Quality & Green Credit Platform</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="description" content="SORA Labs — Autonomous drone-based air quality mapping, green credit rewards, and community tree plantation." />

  <!-- Favicon -->
  <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.8em%22 font-size=%2290%22>🌱</text></svg>">

  <!-- Bootstrap / Icons / Font -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">

  <style>
    :root {
      --glass: rgba(255,255,255,0.08);
      --glass-border: rgba(255,255,255,0.18);
    }
    html, body {
      height: 100%;
    }
    body {
      font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, 'Helvetica Neue', Arial, 'Noto Sans', sans-serif;
      background:
        radial-gradient(1200px 800px at 20% 10%, #6c63ff33, transparent 60%),
        radial-gradient(1000px 700px at 80% 30%, #00e7ff33, transparent 60%),
        radial-gradient(900px 700px at 50% 90%, #ff6ec733, transparent 60%),
        #0b0f14;
      color: #e9eef5;
      padding-top: 72px; /* match dashboard padding */
      overflow-x: hidden;
    }
    .noise:before {
      content: "";
      position: fixed;
      inset: 0;
      pointer-events: none;
      mix-blend-mode: soft-light;
      z-index: 1000;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='160' height='160' viewBox='0 0 160 160'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='2' stitchTiles='stitch'/%3E%3CfeColorMatrix type='saturate' values='0'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='.025'/%3E%3C/svg%3E");
    }
    .glass {
      background: var(--glass);
      border: 1px solid var(--glass-border);
      backdrop-filter: blur(14px);
      -webkit-backdrop-filter: blur(14px);
      border-radius: 20px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.35);
    }
    .brand {
      letter-spacing: .6px;
      background: linear-gradient(90deg, #fff, #b6d7ff 60%, #c7fff6);
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
    }
    .dot {
      width: 10px;
      height: 10px;
      border-radius: 50%;
      background: #15ff99;
      box-shadow: 0 0 12px #15ff99aa;
      display: inline-block;
      margin-right: 8px;
      animation: pulse 1.8s ease-in-out infinite;
    }
    @keyframes pulse {
      0%, 100% { transform: scale(1); opacity: 0.9; }
      50% { transform: scale(1.25); opacity: 0.6; }
    }

    .topbar {
      background: var(--glass);
      border: 1px solid var(--glass-border);
    }
    .topbar.navbar {
      backdrop-filter: blur(14px);
      -webkit-backdrop-filter: blur(14px);
      margin: 8px 12px;
      border-radius: 14px;
    }

    .btn-ghost {
      background: rgba(255,255,255,0.06);
      color: #e9eef5;
      border: 1px solid rgba(255,255,255,0.2);
    }
    .btn-ghost:hover {
      background: rgba(255,255,255,0.12);
      color: #ffffff;
    }

    .section-title {
      font-weight: 800;
      letter-spacing: .04em;
      text-transform: uppercase;
      font-size: .8rem;
      color: #9ca3af;
    }
    .hero-title {
      font-size: clamp(2.1rem, 2.6vw + 1.5rem, 3.4rem);
      font-weight: 800;
      line-height: 1.1;
    }
    .hero-highlight {
      background: linear-gradient(120deg, #6ee7b7, #22d3ee, #a855f7);
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
    }
    .badge-soft {
      border-radius: 999px;
      padding: .35rem .8rem;
      font-size: .7rem;
      text-transform: uppercase;
      letter-spacing: .12em;
      background: rgba(15,23,42,.7);
      border: 1px solid rgba(148,163,184,.5);
      color: #e5e7eb;
    }

    .pill-stat {
      border-radius: 999px;
      padding: .6rem 1rem;
      display: inline-flex;
      align-items: center;
      gap: .4rem;
      background: rgba(15,23,42,.85);
      border: 1px solid rgba(148,163,184,.4);
      font-size: .8rem;
      color: #e5e7eb;
    }
    .pill-stat i {
      font-size: 1rem;
    }

    .icon-circle {
      width: 40px;
      height: 40px;
      border-radius: 999px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: rgba(15,23,42,.8);
      border: 1px solid rgba(148,163,184,.5);
      margin-bottom: .6rem;
    }

    .sensor-tag {
      border-radius: 999px;
      padding: .25rem .75rem;
      font-size: .75rem;
      background: rgba(15,23,42,.9);
      border: 1px solid rgba(148,163,184,.4);
      color: #e5e7eb;
      display: inline-flex;
      align-items: center;
      gap: .35rem;
    }
    .sensor-dot {
      width: 9px;
      height: 9px;
      border-radius: 50%;
      background: #22c55e;
    }

    .timeline-step {
      position: relative;
      padding-left: 1.8rem;
      margin-bottom: 1.2rem;
    }
    .timeline-step::before {
      content: "";
      width: 10px;
      height: 10px;
      border-radius: 999px;
      background: #22c55e;
      position: absolute;
      left: 0.3rem;
      top: 0.4rem;
      box-shadow: 0 0 0 4px rgba(34,197,94,0.18);
    }
    .timeline-step::after {
      content: "";
      position: absolute;
      left: 0.69rem;
      top: 1.2rem;
      bottom: -0.2rem;
      width: 1px;
      background: linear-gradient(to bottom, rgba(148,163,184,.7), transparent);
    }
    .timeline-step:last-child::after {
      display: none;
    }

    .badge-dot {
      display:inline-block;
      width:10px;
      height:10px;
      border-radius:50%;
      margin-right:6px;
    }

    footer {
      border-top: 1px solid rgba(148,163,184,0.4);
      padding: 1.5rem 0;
      margin-top: 3rem;
      font-size: .8rem;
      color: #9ca3af;
    }

    /* Mobile tweaks */
    @media (max-width: 767.98px) {
      .hero-title {
        font-size: 2.05rem;
      }
      .glass {
        border-radius: 16px;
      }
    }

    /* Mobile contrast fix similar to dashboard */
    @media (max-width: 991.98px) {
      body { padding-top: 64px; }

      .topbar,
      .topbar.navbar,
      .glass {
        background:
          radial-gradient(800px 500px at 15% 0%, rgba(108,99,255,.28), transparent 60%),
          radial-gradient(600px 400px at 100% 20%, rgba(0,231,255,.22), transparent 60%),
          rgba(18,22,30,.92) !important;
        border-color: rgba(255,255,255,.16) !important;
        color: #f4f8ff !important;
      }
    }
  </style>
</head>
<body class="noise">

  <!-- TOP BAR (copied from zone_dashboard_leaflet.php) -->
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
            <i class="bi bi-person me-1"></i>Login / Registration</a>
        <?php endif; ?>
      </div>
    </div>
  </nav>

  <!-- MAIN CONTENT -->
  <main>
    <!-- HERO -->
    <section id="hero" class="py-4 py-lg-5">
      <div class="container-xxl">
        <div class="row g-4 align-items-center">
          <div class="col-lg-7">
            <div class="glass p-4 p-lg-5 h-100">
              <span class="badge-soft mb-3">
                <i class="bi bi-cloud-fog2-fill me-2"></i>
                Autonomous Air-Quality & Green Credit Platform
              </span>
              <h1 class="hero-title mb-3">
                Mapping polluted <span class="hero-highlight">air</span>.
                Turning action into <span class="hero-highlight">green credit</span>.
              </h1>
              <p class="mb-4" style="max-width: 640px;">
                SORA Labs deploys an autonomous drone with onboard CO₂, VOC, particulate and climate sensors.
                We build live pollution maps and reward real tree plantation with redeemable credits.
              </p>

              <div class="d-flex flex-wrap gap-2 mb-4">
                <a href="/index_plant.php" class="btn btn-light btn-lg d-flex align-items-center gap-2">
                  <i class="bi bi-radar"></i>
                  <span>View Live Zone Map</span>
                </a>
                <a href="#video" class="btn btn-ghost btn-lg d-flex align-items-center gap-2">
                  <i class="bi bi-play-circle"></i>
                  <span>Watch Autonomous Flight</span>
                </a>
              </div>

              <div class="d-flex flex-wrap gap-2">
                <div class="pill-stat">
                  <i class="bi bi-cpu"></i>
                  <span>Onboard SCD30, SEN55, ENS160 payload</span>
                </div>
                <div class="pill-stat">
                  <i class="bi bi-shield-lock"></i>
                  <span>ESP32 HTTPS → PHP API → MySQL</span>
                </div>
                <div class="pill-stat">
                  <i class="bi bi-award"></i>
                  <span>Tree-verified Green Credit & Store</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Hero side card: drone + pipeline overview -->
          <div class="col-lg-5">
            <div class="glass p-4 mb-3">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <div>
                  <div class="section-title mb-1">Mission Snapshot</div>
                  <h6 class="mb-0">Autonomous Drone Loop</h6>
                </div>
                <div class="icon-circle">
                  <i class="bi bi-drone-front"></i>
                </div>
              </div>
              <div class="timeline-step">
                <h6 class="mb-1">1. Pre-routed Autonomous Flight</h6>
                <p class="mb-0 small">
                  Drone follows a predefined mission path using GPS waypoints,
                  without manual piloting.
                </p>
              </div>
              <div class="timeline-step">
                <h6 class="mb-1">2. Modular Air Sampling</h6>
                <p class="mb-0 small">
                  SCD30, SEN55, ENS160 plus temperature, humidity and a camera
                  continuously sample CO₂, VOC, PM, AQI and climate data.
                </p>
              </div>
              <div class="timeline-step">
                <h6 class="mb-1">3. Secure Edge-to-Cloud Upload</h6>
                <p class="mb-0 small">
                  ESP32 pushes encrypted readings over HTTPS REST APIs to our PHP
                  backend, storing in a MySQL database.
                </p>
              </div>
              <div class="timeline-step">
                <h6 class="mb-1">4. Zone Map & Green Tasks</h6>
                <p class="mb-0 small">
                  Frontend (Leaflet + Bootstrap) renders zones.
                  Red zones trigger tree-plantation tasks and Green Credit rewards.
                </p>
              </div>
            </div>

            <div class="glass p-3 small">
              <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="section-title mb-0">Key Metrics Tracked</span>
              </div>
              <div class="d-flex flex-wrap gap-2">
                <span class="sensor-tag"><span class="sensor-dot"></span>CO₂ (ppm)</span>
                <span class="sensor-tag"><span class="sensor-dot"></span>VOC / NOx</span>
                <span class="sensor-tag"><span class="sensor-dot"></span>PM₂.₅ / PM₁₀</span>
                <span class="sensor-tag"><span class="sensor-dot"></span>AQI</span>
                <span class="sensor-tag"><span class="sensor-dot"></span>Temperature</span>
                <span class="sensor-tag"><span class="sensor-dot"></span>Humidity</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- HOW IT WORKS -->
    <section id="how" class="py-4 py-lg-5">
      <div class="container-xxl">
        <div class="row mb-4 align-items-end">
          <div class="col-lg-7">
            <div class="section-title">Platform Overview</div>
            <h2 class="h3 fw-bold mb-2">How SORA Labs connects drones, data and trees</h2>
            <p class="mb-0">
              This section explains the full end-to-end flow:
              from autonomous flight to map-based tasks and reward redemption.
            </p>
          </div>
        </div>

        <div class="row g-4">
          <!-- 1. Autonomous Drone -->
          <div class="col-md-6 col-lg-3">
            <div class="glass p-3 h-100">
              <div class="icon-circle"><i class="bi bi-drone"></i></div>
              <h5 class="mb-1">Autonomous Drone</h5>
              <p class="small mb-2">
                Custom drone flies fully autonomously on a pre-planned route using
                GPS waypoints and flight controller logic.
              </p>
              <ul class="small ps-3 mb-0">
                <li>Hands-off mission execution</li>
                <li>Repeatable survey paths</li>
                <li>Camera for visual context</li>
              </ul>
            </div>
          </div>

          <!-- 2. Multi-Sensor Payload -->
          <div class="col-md-6 col-lg-3">
            <div class="glass p-3 h-100">
              <div class="icon-circle"><i class="bi bi-graph-up"></i></div>
              <h5 class="mb-1">Multi-Sensor Payload</h5>
              <p class="small mb-2">
                Three key air-quality sensors plus climate sensing:
              </p>
              <ul class="small ps-3 mb-0">
                <li><strong>SCD30</strong> — CO₂, temperature, humidity</li>
                <li><strong>SEN55</strong> — PM₂.₅, PM₁₀ & particles</li>
                <li><strong>ENS160</strong> — VOC, AQI estimation</li>
              </ul>
            </div>
          </div>

          <!-- 3. Secure Data Pipeline -->
          <div class="col-md-6 col-lg-3">
            <div class="glass p-3 h-100">
              <div class="icon-circle"><i class="bi bi-hdd-network"></i></div>
              <h5 class="mb-1">Secure Data Pipeline</h5>
              <p class="small mb-2">
                Onboard ESP32 microcontroller pushes measurements to the backend:
              </p>
              <ul class="small ps-3 mb-0">
                <li>REST APIs over <strong>HTTPS</strong></li>
                <li>Server in PHP / MySQL</li>
                <li>Time-stamped with GPS coordinates</li>
              </ul>
            </div>
          </div>

          <!-- 4. Zone Map & Tasks -->
          <div class="col-md-6 col-lg-3">
            <div class="glass p-3 h-100">
              <div class="icon-circle"><i class="bi bi-tree"></i></div>
              <h5 class="mb-1">Zone Map & Green Tasks</h5>
              <p class="small mb-2">
                Frontend (your existing <strong>Zone Dashboard</strong>) visualizes:
              </p>
              <ul class="small ps-3 mb-0">
                <li>Leaflet + OSM glassmorphism UI</li>
                <li>Colored circles (green / yellow / red)</li>
                <li>Red zones assign tree-plant tasks</li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- SENSORS & DATA -->
    <section id="sensors" class="py-4 py-lg-5">
      <div class="container-xxl">
        <div class="row mb-4 align-items-end">
          <div class="col-lg-7">
            <div class="section-title">Sensors & Metrics</div>
            <h2 class="h3 fw-bold mb-2">What we measure in each flight</h2>
            <p class="mb-0">
              The drone turns the sky into a mobile air-quality lab,
              combining readings from multiple professional sensors.
            </p>
          </div>
        </div>

        <div class="row g-4">
          <div class="col-md-6 col-lg-4">
            <div class="glass p-3 h-100">
              <h6 class="mb-2">SCD30 — CO₂ climate module</h6>
              <p class="small mb-2">
                Measures CO₂, temperature and relative humidity.
              </p>
              <ul class="small ps-3 mb-0">
                <li>CO₂ concentration (ppm)</li>
                <li>Ambient temperature (°C)</li>
                <li>Relative humidity (%)</li>
              </ul>
            </div>
          </div>

          <div class="col-md-6 col-lg-4">
            <div class="glass p-3 h-100">
              <h6 class="mb-2">SEN55 — Particulate matter</h6>
              <p class="small mb-2">
                Captures particle-based pollution for health-critical metrics.
              </p>
              <ul class="small ps-3 mb-0">
                <li>PM₂.₅ and PM₁₀ mass concentration</li>
                <li>Dust and smoke-related pollution</li>
                <li>Helps classify AQI zones</li>
              </ul>
            </div>
          </div>

          <div class="col-md-6 col-lg-4">
            <div class="glass p-3 h-100">
              <h6 class="mb-2">ENS160 — VOC & AQI</h6>
              <p class="small mb-2">
                Tracks volatile organic compounds and gas-based pollution.
              </p>
              <ul class="small ps-3 mb-0">
                <li>VOC index</li>
                <li>NOx / gas mixture behaviour</li>
                <li>Supports AQI severity logic</li>
              </ul>
            </div>
          </div>
        </div>

        <div class="row g-4 mt-1">
          <div class="col-lg-7">
            <div class="glass p-3 h-100">
              <div class="d-flex align-items-center justify-content-between mb-2">
                <h6 class="mb-0">Zone color logic (simplified)</h6>
                <i class="bi bi-traffic-lights"></i>
              </div>
              <p class="small mb-2">
                Each point from the drone path becomes a circle on the map.
                Its color is derived from thresholds on CO₂, PM, VOC, humidity and temperature.
              </p>
              <ul class="small ps-3 mb-0">
                <li><span class="badge-dot" style="background:#22c55e"></span><strong>Green:</strong> safe levels, no action required.</li>
                <li><span class="badge-dot" style="background:#eab308"></span><strong>Yellow:</strong> moderate pollution, suggests planting 1 tree.</li>
                <li><span class="badge-dot" style="background:#ef4444"></span><strong>Red:</strong> critical levels, task to plant 2 trees.</li>
              </ul>
            </div>
          </div>

          <div class="col-lg-5">
            <div class="glass p-3 h-100">
              <h6 class="mb-2">What judges should notice here</h6>
              <ul class="small ps-3 mb-0">
                <li>End-to-end integration: <strong>Drone → Sensors → ESP32 → HTTPS → PHP → MySQL → Leaflet UI</strong>.</li>
                <li>All metrics stored with <strong>timestamp + GPS</strong> in a normalized database.</li>
                
              </ul>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- GREEN CREDIT LOOP -->
    <section id="credit" class="py-4 py-lg-5">
      <div class="container-xxl">
        <div class="row mb-4 align-items-end">
          <div class="col-lg-7">
            <div class="section-title">Incentive Mechanism</div>
            <h2 class="h3 fw-bold mb-2">From red zones to real trees and Green Credit</h2>
            <p class="mb-0">
              The platform converts polluted zones into concrete actions and
              tracks verified impact through user profiles and a contribution map.
            </p>
          </div>
        </div>

        <div class="row g-4">
          <div class="col-md-6 col-lg-3">
            <div class="glass p-3 h-100">
              <div class="icon-circle"><i class="bi bi-geo-alt"></i></div>
              <h6 class="mb-1">1. Click a red zone</h6>
              <p class="small mb-0">
                On the Zone Dashboard, users tap a red circle to view details:
                CO₂, VOC, PM, humidity, temperature and severity level.
              </p>
            </div>
          </div>

          <div class="col-md-6 col-lg-3">
            <div class="glass p-3 h-100">
              <div class="icon-circle"><i class="bi bi-list-check"></i></div>
              <h6 class="mb-1">2. Receive tree-plant task</h6>
              <p class="small mb-0">
                SORA creates a task:
                typically <strong>Plant 1 tree</strong> for yellow,
                <strong>Plant 2 trees</strong> for red zones.
              </p>
            </div>
          </div>

          <div class="col-md-6 col-lg-3">
            <div class="glass p-3 h-100">
              <div class="icon-circle"><i class="bi bi-camera"></i></div>
              <h6 class="mb-1">3. Upload planting proof</h6>
              <p class="small mb-0">
                The user plants a tree, takes a photo and submits it from their
                profile. GPS is captured and stored with the image.
              </p>
            </div>
          </div>

          <div class="col-md-6 col-lg-3">
            <div class="glass p-3 h-100">
              <div class="icon-circle"><i class="bi bi-patch-check-fill"></i></div>
              <h6 class="mb-1">4. Verification & Green Credit</h6>
              <p class="small mb-2">
                Backend verifies the photo using an API (e.g., PlantNet) and logs:
              </p>
              <ul class="small ps-3 mb-0">
                <li>Verification status and confidence</li>
                <li>Green Credit added to credit></li>
                <li>Entry in dashboard</li>
              </ul>
            </div>
          </div>
        </div>

        <div class="row g-4 mt-1">
          <div class="col-lg-6">
            <div class="glass p-3 h-100">
              <h6 class="mb-2">User profile & dashboard</h6>
              <p class="small mb-2">
                Inside the dashboard, each user can see:
              </p>
              <ul class="small ps-3 mb-0">
                <li>Total verified trees and accumulated Green Credit.</li>
                <li>History of tasks and verification confidence.</li>
                <li>Position on the public <strong>Leaderboard</strong>.</li>
              </ul>
            </div>
          </div>
          <div class="col-lg-6">
            <div class="glass p-3 h-100">
              <h6 class="mb-2">Store & contribution map</h6>
              <p class="small mb-2">
                The website also includes:
              </p>
              <ul class="small ps-3 mb-0">
                <li>A merchandise store where users redeem Green Credit for items.</li>
                <li>A contribution map that plots all tree plantings spatially.</li>
                <li>Clear link between air-quality improvement and community action.</li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- AUTONOMOUS FLIGHT VIDEO -->
    <section id="video" class="py-4 py-lg-5">
    <div class="container-xxl">
        <div class="row mb-4 align-items-end">
        <div class="col-lg-7">
            <div class="section-title">Autonomous Flight Demo</div>
            <h2 class="h3 fw-bold mb-2">See the SORA drone fly on its own</h2>
            <p class="mb-0">
            This is a pre-recorded mission where the drone follows a pre-routed
            path, collects live sensor data and streams it into the SORA backend.
            </p>
        </div>
        </div>

        <div class="row g-4">
        <div class="col-lg-8">
            <div class="glass p-3">
            <div class="ratio ratio-16x9 mb-2">
                <!-- IMPORTANT:
                    - src WITHOUT leading slash (same directory)
                    - muted + playsinline + loop for autoplay
                    - no autoplay attribute; we control it with JS when in view
                -->
                <video
                id="soraVideo"
                muted
                loop
                playsinline
                preload="metadata"
                controls
                >
                <source src="sora_autonomous_flight.mp4" type="video/mp4">
                Your browser does not support the video tag.
                </video>
            </div>
            <p class="small mb-0">
                During this flight, sensor data is pushed in real time through
                HTTPS APIs and visualized on the Zone Dashboard, using the same
                glassmorphism theme you can see on this page.
            </p>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="glass p-3 h-100">
            <h6 class="mb-2">What to look for in the video</h6>
            <ul class="small ps-3 mb-2">
                <li>Autonomous take-off and waypoint navigation.</li>
                <li>Stable flight behaviour while sampling air.</li>
                <li>Onboard payload: SCD30, SEN55, ENS160, GPS and camera.</li>
                <li>Sync between mission timeline and sensor data timestamps.</li>
            </ul>
            <p class="small mb-0">
                This demo shows that SORA is not a mock-up:
                it is a working end-to-end system combining hardware, firmware,
                secure networking and a full-stack web platform.
            </p>
            </div>
        </div>
        </div>
    </div>
    </section>


    <!-- TECH STACK -->
    <section id="tech" class="py-4 py-lg-5">
      <div class="container-xxl">
        <div class="row mb-4 align-items-end">
          <div class="col-lg-7">
            <div class="section-title">Implementation</div>
            <h2 class="h3 fw-bold mb-2">Hardware, firmware and web technologies</h2>
            <p class="mb-0">
              This summary helps judges quickly understand how soralabs.cc
              is built from bottom to top.
            </p>
          </div>
        </div>

        <div class="row g-4">
          <div class="col-md-6 col-lg-4">
            <div class="glass p-3 h-100">
              <h6 class="mb-2">Hardware & embedded</h6>
              <ul class="small ps-3 mb-0">
                <li>Custom multirotor drone frame and propulsion system.</li>
                <li>Flight controller for autonomous waypoint missions.</li>
                <li>ESP32 microcontroller handling sensor readouts and HTTPS.</li>
                <li>SCD30, SEN55, ENS160 sensor modules.</li>
                <li>GPS module for latitude / longitude tagging.</li>
                <li>Onboard camera for visual evidence and context.</li>
              </ul>
            </div>
          </div>

          <div class="col-md-6 col-lg-4">
            <div class="glass p-3 h-100">
              <h6 class="mb-2">Backend & database</h6>
              <ul class="small ps-3 mb-0">
                <li>REST API endpoints implemented in <strong>PHP</strong>.</li>
                <li>Secure communication using <strong>ESP32 HTTPS</strong> client.</li>
                <li>MySQL database for sensor ingest tables and user data.</li>
                <li>Separate tables for:
                  <ul class="ps-3">
                    <li>Sensor readings (real & demo datasets).</li>
                    <li>Tree submissions and verification logs.</li>
                    <li>Green Credit and transaction history.</li>
                  </ul>
                </li>
              </ul>
            </div>
          </div>

          <div class="col-md-12 col-lg-4">
            <div class="glass p-3 h-100">
              <h6 class="mb-2">Frontend & UX</h6>
              <ul class="small ps-3 mb-0">
                <li>Landing page and dashboard made with <strong>HTML, CSS, Bootstrap 5</strong>.</li>
                <li>Interactive map using <strong>Leaflet + OpenStreetMap</strong>.</li>
                <li>Modals for tree proof upload and verification feedback.</li>
                <li>Leaderboard, FAQ, Contact, Status and Claim pages.</li>
                <li>Responsive layout optimized for both desktop and mobile judges.</li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </section>
  </main>

  <!-- FOOTER -->
  <footer>
    <div class="container-xxl d-flex flex-wrap justify-content-between align-items-center gap-2">
      <span>© <?php echo date('Y'); ?> SORA Labs · soralabs.cc</span>
      <span class="small">
        Built by Team Soralabs..!
      </span>
    </div>
  </footer>

  <!-- JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
