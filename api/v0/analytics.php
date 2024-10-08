<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');

$request = $_SERVER['REQUEST_URI'];
$request = str_replace('/api/v0', '', $request);
$request = explode('?', $request)[0];
$request_exploded = explode('/', $request);

$device_uuid = null;

if ($_SERVER['HTTP_REFERER'] === "https://spzroenkhausen.bplaced.net/alpha/index.html") {
    http_response_code(200);
    exit();
}

if (isset($request_exploded[2])) {
    $device_uuid = $request_exploded[2];
}

switch ($_SERVER['REQUEST_METHOD']) {
    case 'POST':
        insertDeviceAnalytics($device_uuid);
        break;
    case 'OPTIONS':
        http_response_code(200);
        break;
    default:
        http_response_code(405);
        break;
}

function insertDeviceAnalytics($device_uuid) {
    require_once __DIR__ . '/../config/database.php';

    $database = new Database();
    $conn = $database->getConnection();

    $data = json_decode(file_get_contents("php://input"));

    if (!isset($data->analytics)) {
        http_response_code(400);
        exit();
    }

    $query = "SELECT device_id FROM tblDevices WHERE device_uuid = :device_uuid";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':device_uuid', $device_uuid);

    if (!$stmt->execute()) {
        http_response_code(500);
        exit();
    }

    $device_id = $stmt->fetch(PDO::FETCH_ASSOC)['device_id'];

    foreach($data->analytics as $analytic => $count) {
        updateAnalytic($device_id, [
            'analytic_desc' => $analytic,
            'count' => $count
        ]);
    }

    http_response_code(200);
}

function updateAnalytic($device_id, $analytic) {
    require_once __DIR__ . '/../config/database.php';

    $database = new Database();
    $conn = $database->getConnection();

    // check if the analytic already exists
    $query = "SELECT analytic_id FROM tblAnalytics WHERE analytic_desc = :analytic_desc";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':analytic_desc', $analytic['analytic_desc']);

    if (!$stmt->execute()) {
        http_response_code(500);
        exit();
    }

    if ($stmt->rowCount() < 1) {
        // insert new analytic
        $query = "INSERT INTO tblAnalytics (analytic_desc) VALUES (:analytic_desc)";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':analytic_desc', $analytic['analytic_desc']);

        if (!$stmt->execute()) {
            http_response_code(500);
            exit();
        }

        $analytic_id = $conn->lastInsertId();
    } else {
        $analytic_id = $stmt->fetch(PDO::FETCH_ASSOC)['analytic_id'];
    }

    // update analytic count
    $query = "INSERT INTO tblDeviceAnalytics (device_id, analytic_id, count) VALUES (:device_id, :analytic_id, :count)
        ON DUPLICATE KEY UPDATE count = :count";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':device_id', $device_id);
    $stmt->bindParam(':analytic_id', $analytic_id);
    $stmt->bindParam(':count', $analytic['count']);

    if (!$stmt->execute()) {
        http_response_code(500);
        exit();
    }
}

?>