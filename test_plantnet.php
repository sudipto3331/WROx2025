<?php
// test_plantnet.php — Pl@ntNet API test (fixed: use "organs", not "organs[]")
// Requires: PHP cURL

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

@ini_set('upload_max_filesize', '10M');
@ini_set('post_max_size', '16M');
@ini_set('max_execution_time', '60');

$PLANTNET_API_KEY = '2b1099DMcB5ZqTwSqirdJUUj3e';
$PLANTNET_API_URL = 'https://my-api.plantnet.org/v2/identify/all';

function detect_mime($path, $fallbackExt) {
  if (function_exists('mime_content_type')) {
    return mime_content_type($path) ?: ($fallbackExt === 'png' ? 'image/png' : 'image/jpeg');
  } elseif (function_exists('finfo_open')) {
    $f = finfo_open(FILEINFO_MIME_TYPE);
    $m = finfo_file($f, $path);
    finfo_close($f);
    return $m ?: ($fallbackExt === 'png' ? 'image/png' : 'image/jpeg');
  } else {
    return ($fallbackExt === 'png') ? 'image/png' : 'image/jpeg';
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: text/plain; charset=utf-8');

  if (empty($PLANTNET_API_KEY)) { http_response_code(400); echo "ERROR: Missing API key.\n"; exit; }
  if (!extension_loaded('curl')) { echo "ERROR: PHP cURL extension not enabled.\n"; exit; }
  if (!isset($_FILES['img']) || $_FILES['img']['error'] !== UPLOAD_ERR_OK) {
    $err = $_FILES['img']['error'] ?? 'unknown';
    echo "ERROR: Image upload failed (code: {$err}).\n"; exit;
  }

  $imgPath = $_FILES['img']['tmp_name'];
  $ext = strtolower(pathinfo($_FILES['img']['name'], PATHINFO_EXTENSION));
  $mime = detect_mime($imgPath, $ext);

  // ✅ Use the correct field name: "organs"
  // (If you ever need multiple, you'd need to build a manual multipart body to repeat "organs" keys.)
  $form = [
    'organs' => 'leaf',
    'images' => new CURLFile($imgPath, $mime, $_FILES['img']['name'])
  ];

  // Optional GPS improves results
  if (!empty($_POST['lat']) && !empty($_POST['lng'])) {
    $form['lat'] = trim($_POST['lat']);
    $form['lng'] = trim($_POST['lng']);
  }

  $url = $PLANTNET_API_URL . '?api-key=' . urlencode($PLANTNET_API_KEY);

  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL            => $url,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $form,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER         => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => true,
  ]);

  $resp = curl_exec($ch);
  if ($resp === false) {
    echo "cURL error: " . curl_error($ch) . "\n";
    curl_close($ch); exit;
  }

  $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
  $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $respHeaders = substr($resp, 0, $headerSize);
  $respBody    = substr($resp, $headerSize);
  curl_close($ch);

  echo "=== REQUEST ===\n";
  echo "Endpoint: $url\n";
  echo "Organs: leaf\n";
  if (isset($form['lat'], $form['lng'])) echo "GPS: {$form['lat']}, {$form['lng']}\n";
  echo "File: " . ($_FILES['img']['name'] ?? 'unknown') . " ($mime)\n\n";

  echo "=== HTTP STATUS: $statusCode ===\n\n";
  echo "=== RESPONSE HEADERS ===\n$respHeaders\n";
  echo "=== RAW BODY (first 2000 chars) ===\n";
  echo substr($respBody, 0, 2000) . (strlen($respBody) > 2000 ? "\n...[truncated]...\n" : "\n");
  echo "\n";

  $json = json_decode($respBody, true);
  if (json_last_error() !== JSON_ERROR_NONE) {
    echo "JSON decode error: " . json_last_error_msg() . "\n"; exit;
  }

  echo "=== PARSED SUMMARY ===\n";
  if (empty($json['results'])) { echo "No results array in response.\n"; exit; }

  $top = array_slice($json['results'], 0, 3);
  foreach ($top as $i => $r) {
    $rank = $i + 1;
    $score = isset($r['score']) ? round($r['score'] * 100, 2) . '%' : '—';
    $sci = $r['species']['scientificNameWithoutAuthor'] ?? ($r['species']['scientificName'] ?? '—');
    $common = (!empty($r['species']['commonNames'])) ? implode(', ', array_slice($r['species']['commonNames'], 0, 3)) : '—';
    echo "#$rank: $sci | Common: $common | Confidence: $score\n";
  }

  $prob = isset($json['results'][0]['score']) ? (float)$json['results'][0]['score'] : null;
  $is_tree = ($prob !== null && $prob >= 0.35);
  echo "\nVERDICT: " . ($is_tree ? "✅ Treat as valid tree proof (>=35%)" : "❌ Below threshold or no score") . "\n";

  if ($statusCode === 401 || $statusCode === 403) {
    echo "\nHint: 401/403 means key invalid/expired or quota issue.\n";
  } elseif ($statusCode === 413) {
    echo "\nHint: 413 = file too large; try a smaller image.\n";
  }
  exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Pl@ntNet API Tester (Fixed)</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
  <div class="container py-5" style="max-width: 720px;">
    <div class="mb-4">
      <h1 class="h3 mb-1">Pl@ntNet API Test</h1>
      <p class="text-muted mb-0">Upload a clear photo of a leaf/tree. This uses your key and prints raw + parsed results.</p>
    </div>

    <div class="card shadow-sm">
      <div class="card-body">
        <form method="post" enctype="multipart/form-data">
          <div class="mb-3">
            <label class="form-label">Image (leaf/tree)</label>
            <input type="hidden" name="MAX_FILE_SIZE" value="10485760">
            <input type="file" name="img" accept="image/*" class="form-control" required>
            <div class="form-text">Max ~10 MB. Close-up leaf works best.</div>
          </div>
          <div class="row g-2">
            <div class="col">
              <label class="form-label">Latitude (optional)</label>
              <input type="text" class="form-control" name="lat" placeholder="23.7809">
            </div>
            <div class="col">
              <label class="form-label">Longitude (optional)</label>
              <input type="text" class="form-control" name="lng" placeholder="90.2792">
            </div>
          </div>
          <button class="btn btn-primary mt-3" type="submit">
            <i class="bi bi-cloud-arrow-up me-1"></i> Test API
          </button>
        </form>
      </div>
    </div>

    <div class="small text-muted mt-3">
      If you still get 400, paste the raw body here. For multiple organs, we can switch to a manual multipart body to repeat the <code>organs</code> key.
    </div>

    <div class="mt-4">
      <details>
        <summary class="text-muted">Terminal test (curl)</summary>
        <pre class="mt-2 bg-white p-3 border rounded small">curl -i -X POST \
  -F "organs=leaf" \
  -F "images=@/path/to/your.jpg" \
  "https://my-api.plantnet.org/v2/identify/all?api-key=<?=htmlspecialchars($PLANTNET_API_KEY, ENT_QUOTES)?>"</pre>
      </details>
    </div>
  </div>
</body>
</html>
