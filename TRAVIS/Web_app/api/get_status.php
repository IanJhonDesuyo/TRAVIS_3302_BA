<?php

header("Content-Type: application/json");

require_once "../db_connect.php";

$sql = "
SELECT *
FROM camera_monitoring_logs
ORDER BY recorded_at DESC
LIMIT 1
";

$result = $conn->query($sql);

if($result->num_rows==0){

    echo json_encode([
        "vehicle_count"=>0,
        "inbound_count"=>0,
        "outbound_count"=>0,
        "congestion_level"=>"Unknown",
        "officer_presence"=>"Unknown",
        "potential_collision"=>"None",
        "recorded_at"=>"No Data"
    ]);

    exit;

}

echo json_encode($result->fetch_assoc());

$conn->close();