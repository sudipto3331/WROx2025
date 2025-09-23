<?php
// status.php — Overall Status (all users’ contributions on a map)
// Requires: config.php (theme_head, topbar, theme_foot, db())

require_once __DIR__.'/config.php';

/* ------------------------ AJAX: all tree submissions ------------------------ */
if (isset($_GET['action']) && $_GET['action'] === 'trees') {
  header('Content-Type: application/json; charset=utf-8');
  try {
    $pdo = db();
    $sql = "SELECT ts.id, ts.user_id, ts.user_email, ts.task_level, ts.required_trees,
                   ts.img_path, ts.gps_lat, ts.gps_lng, ts.verified, ts.verifier,
                   ts.confidence, ts.credit_awarded, ts.created_at,
                   u.username, u.first_name, u.last_name
            FROM tree_submissions ts
            LEFT JOIN users u ON u.id = ts.user_id
            ORDER BY ts.created_at DESC";
    $rows = $pdo->query($sql)->fetchAll();

    $out = [];
    $verifiedCount = 0;
    foreach ($rows as $r) {
      $lat = isset($r['gps_lat']) ? (float)$r['gps_lat'] : null;
      $lng = isset($r['gps_lng']) ? (float)$r['gps_lng'] : null;
      if (!is_finite($lat) || !is_finite($lng)) continue;

      $name = trim(($r['first_name'] ?? '').' '.($r['last_name'] ?? ''));
      if ($name === '') $name = $r['username'] ?: ($r['user_email'] ?: 'User #'.$r['user_id']);

      $verified = (int)$r['verified'] === 1;
      if ($verified) $verifiedCount++;

      $out[] = [
        'id'         => (int)$r['id'],
        'user_id'    => (int)$r['user_id'],
        'name'       => $name,
        'email'      => $r['user_email'],
        'task_level' => $r['task_level'],
        'required'   => (int)$r['required_trees'],
        'img'        => $r['img_path'],
        'lat'        => $lat,
        'lng'        => $lng,
        'verified'   => $verified,
        'verifier'   => $r['verifier'],
        'confidence' => $r['confidence'] !== null ? (float)$r['confidence'] : null,
        'credit'     => (int)$r['credit_awarded'],
        'created_at' => $r['created_at'],
      ];
    }

    echo json_encode([
      'status' => 'ok',
      'count'  => count($out),
      'verified_count' => $verifiedCount,
      'data'   => $out
    ]);
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
  }
  exit;
}

/* ------------------------ AJAX: leaderboard (for sidebar) ------------------------ */
if (isset($_GET['action']) && $_GET['action'] === 'leaders') {
  header('Content-Type: application/json; charset=utf-8');
  try {
    $pdo = db();
    $limit = isset($_GET['limit']) ? max(5, min(100, (int)$_GET['limit'])) : 30;
    $sql = "SELECT u.id, u.username, u.first_name, u.last_name, u.email, u.green_credit, u.created_at,
              (SELECT COUNT(*) FROM tree_submissions ts WHERE ts.user_id=u.id AND ts.verified=1) AS verified_count,
              (SELECT MAX(ts2.created_at) FROM tree_submissions ts2 WHERE ts2.user_id=u.id AND ts2.verified=1) AS last_verified
            FROM users u
            ORDER BY u.green_credit DESC, verified_count DESC, u.created_at ASC
            LIMIT :lim";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $out  = [];
    foreach ($rows as $r) {
      $name = trim(($r['first_name'] ?? '').' '.($r['last_name'] ?? ''));
      if ($name === '') $name = $r['username'] ?: ($r['email'] ?: 'User #'.$r['id']);
      $out[] = [
        'id' => (int)$r['id'],
        'name' => $name,
        'email' => $r['email'],
        'green_credit' => (int)$r['green_credit'],
        'verified_count' => (int)$r['verified_count'],
        'last_verified' => $r['last_verified'],
      ];
    }
    echo json_encode(['status'=>'ok','data'=>$out]);
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
  }
  exit;
}

theme_head('Overall Status — SORA Labs');
topbar('status'); ?>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<style>
  /* Space under fixed topbar */
  body { overflow: hidden; padding-top: 10px; } /* desktop */
  @media (max-width: 991.98px) { body { padding-top: 30px; } } /* mobile (reduced from earlier) */

  /* Glass look matching your theme */
  .glass {
    background: var(--glass);
    border: 1px solid var(--glass-border);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0,0,0,.35);
    color:#e9eef5;
  }
  .btn-ghost{ background:rgba(255,255,255,.06); color:#e9eef5; border:1px solid rgba(255,255,255,.2); }
  .btn-ghost:hover{ background:rgba(255,255,255,.12); }

  /* Sidebar + Map */
  .sidebar { width: 380px; max-width: 92vw; height: calc(100vh - 88px); overflow-y: auto; padding: 18px; position: relative; z-index: 1100; }
  #map { height: calc(100vh - 88px); flex: 1; }

  /* Mobile sidebar behavior */
  .sidebar.collapsed { display: none !important; }
  #backdrop { display:none; position:fixed; inset:0; z-index:1035; background:rgba(0,0,0,.45); }
  #backdrop.active { display:block; }
  #mobileFiltersBtn { position: fixed; bottom: 18px; right: 18px; z-index: 1500; border-radius: 999px; }
  @media (max-width: 991.98px) {
    .sidebar { display:none; }
    .sidebar.show { display:block; position:fixed; top:72px; left:12px; z-index:1300; width:calc(100vw - 24px); height:calc(100vh - 84px); }
    #mobileFiltersBtn { display: inline-flex; }
  }
  @media (min-width: 992px) { #mobileFiltersBtn { display:none; } }

  /* Colorful/contrasty panels for readability (mobile & desktop) */
  .sidebar, .legend .card.glass {
    background:
      radial-gradient(800px 500px at 15% 0%, rgba(108,99,255,.22), transparent 60%),
      radial-gradient(600px 400px at 100% 20%, rgba(0,231,255,.18), transparent 60%),
      rgba(18,22,30,.90) !important;
    border-color: rgba(255,255,255,.16) !important;
    color:#f4f8ff !important;
  }
  .sidebar .form-label, .sidebar .form-text, .sidebar .text-secondary { color:#f4f8ff !important; opacity:.92; }

  /* Leaderboard list */
  .avatar-blob {
    width:36px; height:36px; border-radius:50%;
    display:flex; align-items:center; justify-content:center;
    background: rgba(255,255,255,.12);
    border:1px solid rgba(255,255,255,.28);
    color:#fff; font-weight:700;
    box-shadow: inset 0 0 12px rgba(255,255,255,.06);
  }
  .lb-item { border:1px solid rgba(255,255,255,.16); border-radius:12px; padding:10px; background: rgba(255,255,255,.06); color:#f4f8ff; text-decoration: none; }
  .lb-item:hover { background: rgba(255,255,255,.12); }
  .lb-item.active { outline: 2px solid #84c5ff; }
  .badge-gc { background:#1f7a4a; color:#d7ffe9; border:1px solid rgba(255,255,255,.18); }

  /* Legend (behind sidebar) */
  .legend { position:absolute; bottom:16px; left:400px; z-index:900; }
  @media (max-width: 991.98px) {
    .legend { left: 12px; right: 12px; bottom: 12px; }           /* keep fully on-screen */
    .legend .card { max-width: calc(100vw - 24px); }
  }
  .badge-dot { display:inline-block; width:10px; height:10px; border-radius:50%; margin-right:6px; }

  /* Popup legibility */
  .leaflet-popup-content { color: #0b0f14; }

  /* Tree icon (verified) */
  .tree-icon {
    font-size: 22px; line-height: 22px;
    width: 26px; height: 26px; border-radius: 50%;
    background: rgba(34,197,94,0.18);
    border: 2px solid #22c55e;
    box-shadow: 0 0 12px rgba(34,197,94,.55), inset 0 0 6px rgba(34,197,94,.25);
    display:flex; align-items:center; justify-content:center;
    transform: translate(-13px, -13px);
    text-shadow: 0 1px 2px rgba(0,0,0,.5);
  }
  .tree-icon span { filter: drop-shadow(0 1px 1px rgba(0,0,0,.35)); }
</style>

<div id="backdrop"></div>

<div class="d-flex">
  <!-- Sidebar -->
  <aside id="sidebar" class="sidebar glass">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
      <div class="d-flex align-items-center gap-3">
        <span class="dot" aria-hidden="true"></span>
        <h1 class="h5 mb-0 brand">Overall Status</h1>
      </div>
      <span class="badge text-bg-dark border border-1 border-light-subtle">Live</span>
    </div>

    <div class="glass p-3 mb-3">
      <div class="d-flex align-items-center justify-content-between">
        <div>
          <div class="fw-semibold">Contributions</div>
          <div class="small text-secondary"><span id="countAll">—</span> total · <span id="countVerified">—</span> verified</div>
        </div>
        <button id="btnRecenter" class="btn btn-ghost btn-sm"><i class="bi bi-crosshair2 me-1"></i>Recenter</button>
      </div>
      <div class="mt-3 d-flex gap-3">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="chkVerified" checked>
          <label class="form-check-label" for="chkVerified">Verified</label>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="chkOthers" checked>
          <label class="form-check-label" for="chkOthers">Other submissions</label>
        </div>
      </div>
    </div>

    <div class="glass p-3">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <label class="form-label mb-0"><i class="bi bi-trophy me-2"></i>Leaderboard</label>
        <button id="btnClearUser" class="btn btn-ghost btn-sm" disabled><i class="bi bi-ui-checks me-1"></i>Show All</button>
      </div>
      <div id="leaderList" class="d-grid gap-2">
        <div class="text-secondary small">Loading top contributors…</div>
      </div>
    </div>
  </aside>

  <!-- Map -->
  <main id="map"></main>
</div>

<!-- Floating Legend -->
<div class="legend">
  <div class="card glass p-2">
    <div class="d-flex align-items-center"><strong class="me-2">Legend</strong></div>
    <div class="mt-1"><span class="badge-dot" style="background:#22c55e"></span><small>Verified (Green Credit)</small></div>
    <div><span class="badge-dot" style="background:#ef4444"></span><small>Other submissions</small></div>
  </div>
</div>

<!-- Mobile floating Filters button -->
<button id="mobileFiltersBtn" class="btn btn-light btn-lg">
  <i class="bi bi-sliders"></i> Filters
</button>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
  // Map state
  let map;
  let allRows = [];
  let selectedUserId = null;

  // Marker groups
  let verifiedGroup = L.layerGroup();
  let otherGroup    = L.layerGroup();

  // DOM
  const sidebar = document.getElementById('sidebar');
  const backdrop = document.getElementById('backdrop');
  const btnRecenter = document.getElementById('btnRecenter');
  const chkVerified = document.getElementById('chkVerified');
  const chkOthers   = document.getElementById('chkOthers');
  const leaderList  = document.getElementById('leaderList');
  const btnClearUser= document.getElementById('btnClearUser');
  const mobileFiltersBtn = document.getElementById('mobileFiltersBtn');

  // Sidebar show/hide
  function showSidebar(isInit=false) {
    if (window.innerWidth < 992) { sidebar.classList.add('show'); backdrop.classList.add('active'); }
    else { sidebar.classList.remove('collapsed'); }
    if (!isInit) setTimeout(() => { map.invalidateSize(true); autoZoom(); }, 250);
  }
  function hideSidebar(isInit=false) {
    if (window.innerWidth < 992) { sidebar.classList.remove('show'); backdrop.classList.remove('active'); }
    else { sidebar.classList.add('collapsed'); }
    if (!isInit) setTimeout(() => { map.invalidateSize(true); autoZoom(); }, 250);
  }
  mobileFiltersBtn.addEventListener('click', showSidebar);
  backdrop.addEventListener('click', hideSidebar);

  // Custom DivIcon for the green tree
  const TreeIcon = L.DivIcon.extend({
    options: {
      className: '',
      html: '<div class="tree-icon"><span>🌳</span></div>',
      iconSize: [26, 26],
      iconAnchor: [13, 13]
    }
  });
  const treeIcon = new TreeIcon();

  function initMap() {
    map = L.map('map', { zoomControl: true }).setView([23.7808875, 90.2792371], 11);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 20,
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a>'
    }).addTo(map);

    verifiedGroup.addTo(map);
    otherGroup.addTo(map);

    fetchLeaders();
    fetchTrees();

    btnRecenter.addEventListener('click', autoZoom);
    chkVerified.addEventListener('change', applyFilters);
    chkOthers.addEventListener('change', applyFilters);
    btnClearUser.addEventListener('click', () => { selectedUserId = null; btnClearUser.disabled = true; applyFilters(true); });

    window.addEventListener('resize', () => {
      if (window.innerWidth < 992) { sidebar.classList.remove('collapsed'); hideSidebar(true); }
      else { backdrop.classList.remove('active'); sidebar.classList.remove('show'); showSidebar(true); }
      setTimeout(() => { map.invalidateSize(true); autoZoom(); }, 250);
    });
  }
  document.addEventListener('DOMContentLoaded', initMap);

  async function fetchTrees() {
    const resp = await fetch('status.php?action=trees');
    const json = await resp.json();
    if (!resp.ok || json.status !== 'ok') {
      console.error(json.message || 'Failed to load contributions');
      return;
    }
    document.getElementById('countAll').textContent = json.count ?? '0';
    document.getElementById('countVerified').textContent = json.verified_count ?? '0';

    allRows = json.data || [];
    applyFilters(true);
  }

  async function fetchLeaders() {
    const resp = await fetch('status.php?action=leaders&limit=30');
    const json = await resp.json();
    if (!resp.ok || json.status !== 'ok') {
      leaderList.innerHTML = '<div class="text-secondary small">Failed to load leaderboard.</div>';
      return;
    }
    renderLeaderList(json.data || []);
  }

  function renderLeaderList(rows) {
    if (!rows.length) {
      leaderList.innerHTML = '<div class="text-secondary small">No leaders yet.</div>';
      return;
    }
    leaderList.innerHTML = '';
    rows.forEach((r, idx) => {
      const initials = (r.name || '?').trim().slice(0,1).toUpperCase();

      // Row
      const wrap = document.createElement('div');
      wrap.className = 'd-grid gap-2';

      // Clickable user (go to contributions page)
      const link = document.createElement('a');
      link.href = 'leaderboard.php?user=' + encodeURIComponent(r.id);
      link.className = 'lb-item';
      link.innerHTML = `
        <div class="d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center gap-3">
            <div class="avatar-blob">${initials}</div>
            <div>
              <div class="fw-semibold">${escapeHtml(r.name)}</div>
              <div class="small" style="color:#e6f0ff;opacity:.9;">${escapeHtml(r.email || '')}</div>
              <div class="small text-secondary">#${idx+1} • ${r.verified_count} verified</div>
            </div>
          </div>
          <span class="badge badge-gc">${r.green_credit}</span>
        </div>`;

      // Pin to map button (keeps map filter feature)
      const pinBtn = document.createElement('button');
      pinBtn.type = 'button';
      pinBtn.className = 'btn btn-ghost btn-sm mt-1';
      pinBtn.innerHTML = '<i class="bi bi-geo-alt me-1"></i>Show on Map';

      pinBtn.addEventListener('click', (ev) => {
        ev.preventDefault(); // prevent link navigation
        selectedUserId = r.id;
        btnClearUser.disabled = false;
        document.querySelectorAll('.lb-item').forEach(el => el.classList.remove('active'));
        link.classList.add('active');
        applyFilters(true);
        if (window.innerWidth < 992) hideSidebar();
      });

      wrap.appendChild(link);
      wrap.appendChild(pinBtn);
      leaderList.appendChild(wrap);
    });
  }

  function applyFilters(zoomAfter = false) {
    verifiedGroup.clearLayers();
    otherGroup.clearLayers();

    const showVerified = chkVerified.checked;
    const showOthers   = chkOthers.checked;

    const rows = allRows.filter(r => {
      if (selectedUserId && r.user_id !== selectedUserId) return false;
      if (r.verified && !showVerified) return false;
      if (!r.verified && !showOthers)  return false;
      return true;
    });

    rows.forEach(r => {
      const latlng = [r.lat, r.lng];
      const imgHtml = r.img ? `<div class="mt-2"><img src="${escapeHtml(r.img)}" alt="Tree photo" class="img-fluid rounded" style="max-width:220px"></div>
                               <div class="small mt-1"><a href="${escapeHtml(r.img)}" target="_blank" rel="noopener">Open full image</a></div>` : '';
      const confTxt = (r.confidence != null) ? `${r.confidence}%` : '—';
      const popupHtml = `
        <div style="min-width:240px">
          <div class="fw-bold mb-1">Contribution #${r.id}</div>
          <div class="small text-muted mb-2">${escapeHtml(r.created_at || '')}</div>
          <div><strong>By:</strong> ${escapeHtml(r.name || '')}</div>
          <div class="small text-muted">${escapeHtml(r.email || '')}</div>
          <div class="mt-2"><strong>Status:</strong> ${r.verified ? 'Verified ✅' : 'Other submission'}</div>
          <div><strong>Confidence:</strong> ${confTxt}</div>
          <div><strong>Credit:</strong> ${r.credit ?? 0}</div>
          <div><strong>Task:</strong> ${escapeHtml(r.task_level || '—')} (req ${r.required ?? 0})</div>
          <div><strong>GPS:</strong> ${Number(r.lat).toFixed(5)}, ${Number(r.lng).toFixed(5)}</div>
          ${imgHtml}
        </div>`;

      if (r.verified) {
        L.marker(latlng, { icon: treeIcon }).bindPopup(popupHtml).addTo(verifiedGroup);
      } else {
        L.circleMarker(latlng, {
          radius: 7,
          color: '#ef4444', weight: 2, opacity: 0.95,
          fillColor: '#ef4444', fillOpacity: 0.35
        }).bindPopup(popupHtml).addTo(otherGroup);
      }
    });

    if (zoomAfter) autoZoom();
  }

  function autoZoom() {
    const latlngs = [];
    verifiedGroup.eachLayer(l => { if (l.getLatLng) latlngs.push(l.getLatLng()); });
    otherGroup.eachLayer(l => { if (l.getLatLng) latlngs.push(l.getLatLng()); });

    if (!latlngs.length) return;
    if (latlngs.length === 1) { map.setView(latlngs[0], 16); return; }

    const padLeft = computeSidebarLeftPadding();
    try {
      map.fitBounds(L.latLngBounds(latlngs), {
        paddingTopLeft: [padLeft, 72],
        paddingBottomRight: [20, 20],
        maxZoom: 18
      });
    } catch (e) {}
  }

  function computeSidebarLeftPadding() {
    const isDesktop = window.innerWidth >= 992;
    const isCollapsed = sidebar.classList.contains('collapsed');
    if (isDesktop && !isCollapsed) {
      const w = sidebar.getBoundingClientRect().width || 360;
      return Math.round(w + 28);
    }
    if (!isDesktop && sidebar.classList.contains('show')) {
      const w = sidebar.getBoundingClientRect().width || (window.innerWidth - 24);
      return Math.round(w + 16);
    }
    return 24;
  }

  function escapeHtml(s) {
    if (s == null) return '';
    return String(s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }
</script>

<?php theme_foot();
