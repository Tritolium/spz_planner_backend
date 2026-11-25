<?php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/permission-helper.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (isset($_GET['api_token'])) {
    $api_token = $_GET['api_token'];
} else {
    http_response_code(403);
    exit();
}

if (!hasPermission($api_token, 501)) { // permission_id 501 for heating_read_write
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden: insufficient permissions']);
    exit();
}

switch ($_SERVER['REQUEST_METHOD']) {
    case 'OPTIONS':
        http_response_code(200);
        exit();
    case 'GET':
        getHeatWeek($api_token);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

function getHeatWeek($api_token) {
    $database = new Database();
    $db_conn = $database->getConnection();

    // Get start and end of the current week (Monday to Sunday)
    $start_of_week = date('Y-m-d', strtotime('monday this week'));
    $end_of_week = date('Y-m-d', strtotime('sunday this week'));

    $query = "SELECT * FROM tblHeatEvents
        WHERE begin >= :start_of_week
        AND end <= :end_of_week
        ORDER BY begin ASC";

    $stmt = $db_conn->prepare($query);
    $stmt->bindParam(':start_of_week', $start_of_week);
    $stmt->bindParam(':end_of_week', $end_of_week);

    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to retrieve heat events']);
        exit();
    }

    $heat_events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response = [
        'start_of_week' => $start_of_week,
        'end_of_week' => $end_of_week,
        'monday' => [],
        'tuesday' => [],
        'wednesday' => [],
        'thursday' => [],
        'friday' => [],
        'saturday' => [],
        'sunday' => []
    ];

    foreach ($heat_events as $event) {
        $day_of_week = date('l', strtotime($event['begin'])); // Get day of week as string
        $response[strtolower($day_of_week)][] = $event; // Add event to corresponding day
    }

    $json = json_encode($response);
    $etag = '"' . md5($json) . '"';

    // ETag handling
    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
        http_response_code(304);
        header('ETag: ' . $etag);
        exit();
    }

    header('ETag: ' . $etag);
    echo $json;
}