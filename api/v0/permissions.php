<?php

require __DIR__ . '/config/database.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header("Content-Type: application/json; charset=UTF-8");

$request = $_SERVER['REQUEST_URI'];
// remove the /api/v0 part of the request
$request = str_replace('/api/v0', '', $request);
// remove the query string
$request = explode('?', $request)[0];
// split the request to get the id
$request_exploded = explode('/', $request);

if (isset($request_exploded[2]) && $request_exploded[2] != '') {
    // the request has an id
    $id = $request_exploded[2];
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'OPTIONS':
            http_response_code(200);
            break;
        case 'GET':
            getPermissions($id);
            break;
        default:
            http_response_code(405);
            break;
    }
} else {
    // the request does not have an id
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'OPTIONS':
            http_response_code(200);
            break;
        case 'GET':
            getPermissions();
            break;
        default:
            http_response_code(405);
            break;
    }
}

function getPermissions($permission_id = null) {
    $database = new Database();
    $db_conn = $database->getConnection();

    if ($permission_id != null) {
        $query = "SELECT * FROM tblPermissions WHERE permission_id = :permission_id";
        $stmt = $db_conn->prepare($query);
        $stmt->bindParam(':permission_id', $permission_id);
    } else {
        $query = "SELECT * FROM tblPermissions";
        $stmt = $db_conn->prepare($query);
    }

    if (!$stmt->execute()) {
        http_response_code(500);
        exit();
    }

    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($result);
}

?>
