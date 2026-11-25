<?php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/permission-helper.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
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

// resolve headevent_id from request URI
$request = $_SERVER['REQUEST_URI'];
$request = str_replace('/api/v0/heatevent/', '', $request);
$heatevent_id = explode('?', $request)[0];

if (isset($heatevent_id ) && is_numeric($heatevent_id)) {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'OPTIONS':
            http_response_code(200);
            exit();
        case 'GET':
            getHeatEvent($heatevent_id, $api_token);
            break;
        case 'POST':
            updateHeatEvent($heatevent_id, $api_token);
            break;
        case 'DELETE':
            deleteHeatEvent($heatevent_id, $api_token);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} else {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'OPTIONS':
            http_response_code(200);
            exit();
        case 'GET':
            if (isset($_GET['current'])) {
                getCurrentHeatEvents($api_token);
            } else {
                getAllHeatEvents($api_token);
            }
            break;
        case 'POST':
            createHeatEvent($api_token);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
}

function getAllHeatEvents($api_token) {
    // Implementation for retrieving all heat events
    $query = "SELECT * FROM tblHeatEvents";
    $db = new Database();
    $conn = $db->getConnection();
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $heatEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($heatEvents);
}

function getHeatEvent($heatevent_id, $api_token) {
    // Implementation for retrieving a specific heat event
    $query = "SELECT * FROM tblHeatEvents WHERE id = :id";
    $db = new Database();
    $conn = $db->getConnection();
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $heatevent_id, PDO::PARAM_INT);
    $stmt->execute();
    $heatEvent = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode($heatEvent);
}

function getCurrentHeatEvents($api_token) {
    // get current heat events where begin <= now and end >= now
    $query = "SELECT * FROM tblHeatEvents WHERE begin <= CONVERT_TZ(NOW(), @@session.time_zone, 'Europe/Berlin') AND end >= CONVERT_TZ(NOW(), @@session.time_zone, 'Europe/Berlin')";
    $db = new Database();
    $conn = $db->getConnection();
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $heatEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // get upcoming heat events where begin is in the next 6 hours
    $query = "SELECT * FROM tblHeatEvents WHERE begin > CONVERT_TZ(NOW(), @@session.time_zone, 'Europe/Berlin') AND begin <= DATE_ADD(CONVERT_TZ(NOW(), @@session.time_zone, 'Europe/Berlin'), INTERVAL 6 HOUR)";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $upcomingHeatEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'current' => $heatEvents,
        'upcoming' => $upcomingHeatEvents
    ]);
}

function createHeatEvent($api_token) {
    // Implementation for creating a new heat event
    $data = json_decode(file_get_contents("php://input"), true);
    $query = "INSERT INTO tblHeatEvents (title, begin, end, room_id) VALUES (:title, :begin, :end, :room_id)";
    $db = new Database();
    $conn = $db->getConnection();
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':title', $data['title']);
    $stmt->bindParam(':begin', $data['begin']);
    $stmt->bindParam(':end', $data['end']);
    $stmt->bindParam(':room_id', $data['room_id']);
    if ($stmt->execute()) {
        http_response_code(201);
        echo json_encode([
            'message' => 'Heat event created successfully',
            'heatevent_id' => $conn->lastInsertId(),
            'title' => $data['title'],
            'begin' => $data['begin'],
            'end' => $data['end'],
            'room_id' => $data['room_id']
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create heat event']);
    }
}

function updateHeatEvent($heatevent_id, $api_token) {
    // Implementation for updating a specific heat event
    $data = json_decode(file_get_contents("php://input"), true);
    $query = "UPDATE tblHeatEvents SET title = :title, begin = :begin, end = :end, room_id = :room_id WHERE heatevent_id = :id";
    $db = new Database();
    $conn = $db->getConnection();
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':title', $data['title']);
    $stmt->bindParam(':begin', $data['begin']);
    $stmt->bindParam(':end', $data['end']);
    $stmt->bindParam(':room_id', $data['room_id']);
    $stmt->bindParam(':id', $heatevent_id, PDO::PARAM_INT);
    if ($stmt->execute()) {
        echo json_encode(['message' => 'Heat event updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update heat event']);
    }
}

function deleteHeatEvent($heatevent_id, $api_token) {
    // Implementation for deleting a specific heat event
    $query = "DELETE FROM tblHeatEvents WHERE heatevent_id = :id";
    $db = new Database();
    $conn = $db->getConnection();
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $heatevent_id, PDO::PARAM_INT);
    if ($stmt->execute()) {
        echo json_encode(['message' => 'Heat event deleted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete heat event']);
    }
}
