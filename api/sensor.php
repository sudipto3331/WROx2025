<?php
// api.php — GET-only ingest endpoint (now includes NOX and returns server time)

header('Content-Type: application/json; charset=utf-8');
// (Optional) Allow CORS if needed
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

$DB_HOST = '194.233.77.177';
$DB_NAME = 'soralabs_masterdb';
$DB_USER = 'soralabs_masterdb';
$DB_PASS = ']Pi{5,^)G}]D';

try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    // Collect GET params. Missing keys become null.
    $data = [
        'co2_level'          => $_GET['CO2_level']            ?? $_GET['co2_level']          ?? null,
        'humidity_scd30'     => $_GET['Humidity_SCD30']       ?? $_GET['humidity_scd30']     ?? null,
        'temperature_scd30'  => $_GET['Temperature_SCD30']    ?? $_GET['temperature_scd30']  ?? null,
        'particulate_matter' => $_GET['Particulate_Matter']   ?? $_GET['particulate_matter'] ?? null,
        'humidity_sen55'     => $_GET['Humidity_SEN55']       ?? $_GET['humidity_sen55']     ?? null,
        'temperature_sen55'  => $_GET['Temperature_SEN55']    ?? $_GET['temperature_sen55']  ?? null,
        'voc'                => $_GET['VOC']                  ?? $_GET['voc']                ?? null,
        'nox'                => $_GET['NOX']                  ?? $_GET['nox']                ?? null,   // ← NEW
        'camera_data'        => $_GET['Camera_Data']          ?? $_GET['camera_data']        ?? null,
        'gps_lat'            => $_GET['Gps_lat']              ?? $_GET['gps_lat']            ?? null,
        'gps_long'           => $_GET['GPS_long']             ?? $_GET['gps_long']           ?? null,
        'extra_field_1'      => $_GET['extra_field_1']        ?? null,
        'extra_field_2'      => $_GET['extra_field_2']        ?? null,
        'extra_field_3'      => $_GET['extra_field_3']        ?? null,
        'extra_field_4'      => $_GET['extra_field_4']        ?? null,
        // Accept either 'time' as 'YYYY-MM-DD HH:MM:SS' or leave null
        'time'               => $_GET['time']                 ?? null,
        'api_version'        => $_GET['api_version']          ?? null,
    ];

    // Prepare INSERT (includes nox)
    $sql = "INSERT INTO real_sensor_ingest
            (co2_level, humidity_scd30, temperature_scd30, particulate_matter,
             humidity_sen55, temperature_sen55, voc, nox, camera_data,
             gps_lat, gps_long, extra_field_1, extra_field_2, extra_field_3,
             extra_field_4, `time`, api_version)
            VALUES
            (:co2_level, :humidity_scd30, :temperature_scd30, :particulate_matter,
             :humidity_sen55, :temperature_sen55, :voc, :nox, :camera_data,
             :gps_lat, :gps_long, :extra_field_1, :extra_field_2, :extra_field_3,
             :extra_field_4, :time, :api_version)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);
    $insertedId = $pdo->lastInsertId();

    // Times: PHP server local & UTC, plus DB server time
    $phpTimezone     = date_default_timezone_get();
    $serverTimeLocal = date('Y-m-d H:i:s');   // PHP server local time
    $serverTimeUtc   = gmdate('Y-m-d H:i:s'); // PHP server UTC
    $dbTime = null;
    try {
        $dbTime = $pdo->query("SELECT NOW()")->fetchColumn();
    } catch (Throwable $e) {
        // ignore
    }

    header('X-Server-Time: ' . $serverTimeLocal);

    echo json_encode([
        'status'      => 'ok',
        'message'     => 'Row inserted',
        'id'          => $insertedId,
        'server_time' => [
            'php_local' => $serverTimeLocal,
            'php_utc'   => $serverTimeUtc,
            'timezone'  => $phpTimezone,
            'db_time'   => $dbTime,
        ],
        'received'    => $data,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Database error',
        'error'   => $e->getMessage()
    ]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Server error',
        'error'   => $e->getMessage()
    ]);
    exit;
}
