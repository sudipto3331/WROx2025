<?php
/**
 * seed_green_road.php
 * Push 50 randomized sensor datapoints around Green Road, Dhaka to your API.
 *
 * Works via GET requests like:
 * https://soralabs.cc/api/sensor.php?...params...
 */

if (php_sapi_name() !== 'cli') {
  header('Content-Type: text/plain; charset=utf-8');
}
date_default_timezone_set('Asia/Dhaka');

/* =========================
   CONFIG — EDIT IF NEEDED
   ========================= */
$API_BASE = 'https://soralabs.cc/api/sensor.php';

// Center on Green Road, Dhaka (approx)
$CENTER_LAT = 23.7466;
$CENTER_LNG = 90.3839;
$RADIUS_METERS = 600; // datapoints within ~600 m radius

$COUNT = 50;
$API_VERSION = 'v1.0';

// Slow down between requests (seconds)
$SLEEP_SEC = 0.15;

// Fix random seed (set to null to make it truly random each run)
$SEED = 20250923;

/* =========================
   HELPERS
   ========================= */
if ($SEED !== null) srand($SEED);

function randf(float $min, float $max, int $decimals = 1): float {
  $v = $min + (lcg_value() * ($max - $min));
  return round($v, $decimals);
}
function randi(int $min, int $max): int {
  return $min + (int)floor(lcg_value() * (($max - $min) + 1));
}
/** Random point within a circle (meters) around a lat/lng */
function random_point_near(float $lat0, float $lng0, float $radiusMeters): array {
  // Random bearing + distance
  $bearing = lcg_value() * 2 * M_PI;
  $dist = sqrt(lcg_value()) * $radiusMeters; // sqrt for uniform within circle

  // Degrees per meter
  $degLat = $dist / 111320.0; // ~111.32 km per degree latitude
  $degLng = $dist / (111320.0 * cos(deg2rad($lat0)));

  $lat = $lat0 + ($degLat * cos($bearing));
  $lng = $lng0 + ($degLng * sin($bearing));
  return [round($lat, 7), round($lng, 7)];
}

/** Simple GET via cURL returning [status, body] */
function http_get(string $url): array {
  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_USERAGENT => 'SORA Seeder/1.0',
  ]);
  $body = curl_exec($ch);
  $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err = curl_error($ch);
  curl_close($ch);
  if ($body === false) {
    return [$status ?: 0, "cURL error: $err"];
  }
  return [$status, $body];
}

/* =========================
   MAIN
   ========================= */
$ok = 0; $fail = 0;
echo "Seeding $COUNT datapoints near Green Road, Dhaka…\n\n";

for ($i = 1; $i <= $COUNT; $i++) {
  // Random location near center
  [$lat, $lng] = random_point_near($CENTER_LAT, $CENTER_LNG, $RADIUS_METERS);

  // Random realistic measurements
  $co2 = randi(420, 1100);                         // ppm
  $voc = randi(80, 380);                           // arbitrary index
  $nox = randi(10, 80);                            // arbitrary index
  $hum_scd30 = randf(40, 65, 1);                   // %
  $hum_sen55 = randf(40, 65, 1);                   // %
  $temp_scd30 = randf(24.0, 34.5, 1);              // °C
  $temp_sen55 = randf(24.0, 34.5, 1);              // °C

  // PM2.5 / PM10
  $pm25 = randi(10, 80);
  $pm10 = max($pm25 + randi(5, 100), $pm25 + 10);  // ensure PM10 ≥ PM2.5+10

  // Labels/extra info
  $sensorId = sprintf('sensor-%02d', $i);
  $qual = ['low','med','high'][randi(0, 2)];
  $longtxt = ['short','medium','long'][randi(0, 2)];

  // Time: spread over the last ~2 hours
  $ts = time() - (($COUNT - $i) * randi(60, 180)); // seconds back
  $timeStr = date('Y-m-d H:i:s', $ts);

  // Build query (keep exact casing from your working API)
  $params = [
    'CO2_level'         => $co2,
    'Humidity_SCD30'    => $hum_scd30,
    'Temperature_SCD30' => $temp_scd30,
    'Particulate_Matter'=> $pm25 . ',' . $pm10,
    'Humidity_SEN55'    => $hum_sen55,
    'Temperature_SEN55' => $temp_sen55,
    'VOC'               => $voc,
    'NOX'               => $nox,
    'Camera_Data'       => $sensorId,
    'Gps_lat'           => $lat,
    'GPS_long'          => $lng,
    'extra_field_1'     => $sensorId,
    'extra_field_2'     => 'green-road',
    'extra_field_3'     => $qual,
    'extra_field_4'     => $longtxt,
    'time'              => $timeStr,
    'api_version'       => $API_VERSION,
  ];

  // Construct URL manually to keep comma in PM string readable (encoding is fine too)
  $qs = http_build_query($params);
  $url = $API_BASE . '?' . $qs;

  [$status, $body] = http_get($url);

  if ($status >= 200 && $status < 300) {
    $ok++;
    echo sprintf(
      "OK  #%02d  %s  CO2=%d  PM=%s  @ (%.5f, %.5f)\n",
      $i, $sensorId, $co2, $params['Particulate_Matter'], $lat, $lng
    );
  } else {
    $fail++;
    echo sprintf(
      "ERR #%02d  HTTP %d  %s\n",
      $i, $status, is_string($body) ? substr($body, 0, 200) : ''
    );
  }

  usleep((int)round($SLEEP_SEC * 1e6));
}

echo "\nDone. Success: $ok, Failed: $fail\n";
