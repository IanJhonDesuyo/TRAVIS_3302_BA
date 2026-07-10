<?php

header("Content-Type: application/json");

require_once __DIR__ . "/../Admin/db_connect.php";

$sql = "
SELECT
recorded_at,
vehicle_count,
inbound_count,
outbound_count,
congestion_level,
alert_generated
FROM camera_monitoring_logs
ORDER BY recorded_at DESC
LIMIT 10
";

$result = $conn->query($sql);
$logs = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $logs[] = [
            "recorded_at" => $row["recorded_at"],
            "vehicle_count" => intval($row["vehicle_count"]),
            "inbound_count" => intval($row["inbound_count"]),
            "outbound_count" => intval($row["outbound_count"]),
            "congestion_level" => $row["congestion_level"],
            "alert_generated" => intval($row["alert_generated"])
        ];
    }
}

echo json_encode([
    "success" => true,
    "logs" => $logs
]);

$conn->close();
