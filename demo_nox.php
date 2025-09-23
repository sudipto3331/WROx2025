<?php
// seed_nox_1_to_200.php — insert 200 NOX datapoints (NOX = 1..200) via GET

$endpoint = "https://soralabs.cc/api/sensor.php"; // change if your API lives elsewhere

// Simple GET helper (cURL if available, fallback to file_get_contents)
function http_get($url) {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => 'Soralabs NOX Seeder/1.0',
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);
        return $resp;
    }
    return @file_get_contents($url);
}

$total = 200;
for ($i = 1; $i <= $total; $i++) {
    // NOX value = loop index (1..200)
    $params = [
        'NOX'         => $i,
        // omit 'time' to let server/DB timestamp it (create_time)
        'api_version' => 'v1.2-seed-nox-1to200'
    ];

    $url = $endpoint . '?' . http_build_query($params);
    $resp = http_get($url);

    echo "[{$i}/{$total}] Sent NOX={$i} -> " . ($resp ?: 'no response') . PHP_EOL;

    // small delay to avoid flooding
    usleep(100000); // 0.1 sec
}
