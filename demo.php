<?php
// bulk_insert.php — generate 100 random datapoints and insert via API

$endpoint = "https://soralabs.cc/api/sensor.php";

for ($i = 1; $i <= 100; $i++) {
    // Random values in given ranges
    $co2       = rand(400, 10000);           // ppm
    $humidity1 = rand(0, 100);              // %
    $humidity2 = rand(0, 100);              // %
    $temp1     = rand(200, 550) / 10;       // 20.0–55.0 °C
    $temp2     = rand(200, 550) / 10;
    $voc       = rand(0, 500);              // ppb
    $pm25      = rand(0, 200);              // PM2.5
    $pm10      = rand(0, 200);              // PM10
    $gps_lat   = 23.70 + (mt_rand() / mt_getrandmax()) * 0.2;  // random near Dhaka
    $gps_long  = 90.35 + (mt_rand() / mt_getrandmax()) * 0.2;

    // Build query string
    $params = http_build_query([
        "CO2_level"         => $co2,
        "Humidity_SCD30"    => $humidity1,
        "Temperature_SCD30" => $temp1,
        "Particulate_Matter"=> "$pm25,$pm10",
        "Humidity_SEN55"    => $humidity2,
        "Temperature_SEN55" => $temp2,
        "VOC"               => $voc,
        "Camera_Data"       => "TestData$i",
        "Gps_lat"           => $gps_lat,
        "GPS_long"          => $gps_long,
        "extra_field_1"     => "extra1_$i",
        "extra_field_2"     => "extra2_$i",
        "extra_field_3"     => "medium text sample $i",
        "extra_field_4"     => "long text data sample $i",
        "time"              => date("Y-m-d H:i:s"),
        "api_version"       => "v1.0"
    ]);

    $url = $endpoint . "?" . $params;

    // Send GET request
    $response = file_get_contents($url);

    echo "[$i] Sent -> CO2=$co2, Temp=$temp1, Humidity=$humidity1 | Response: $response\n";

    // Optional: delay to avoid flooding server
    usleep(100000); // 0.1 sec
}
