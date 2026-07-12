<?php

header("Content-Type: application/json");

// Allow testing from browser
if ($_SERVER["REQUEST_METHOD"] === "GET") {

    $input = [
        "year" => date("Y"),
        "month" => date("n")
    ];

} else {

    $input = json_decode(file_get_contents("php://input"), true);

    if (!$input) {
        echo json_encode([
            "success" => false,
            "message" => "Invalid JSON input."
        ]);
        exit;
    }

}

$flask_api = "http://127.0.0.1:5001/predict/monthly";

$ch = curl_init($flask_api);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json"
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($input));

$response = curl_exec($ch);

if (curl_errno($ch)) {

    echo json_encode([
        "success" => false,
        "message" => curl_error($ch)
    ]);
    exit;
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

http_response_code($httpCode);

echo $response;