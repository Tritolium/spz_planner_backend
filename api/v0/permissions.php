<?php

require __DIR__ . '/config/database.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header("Content-Type: application/json; charset=UTF-8");

$permissions = [
    [
        "permission_id" => 1,
        "permission_name" => "member_read",
        "description" => "Nutzer lesen"
    ],
    [
        "permission_id" => 2,
        "permission_name" => "member_write",
        "description" => "Nutzer erstellen, bearbeiten und löschen"
    ],
    [
        "permission_id" => 3,
        "permission_name" => "role_read",
        "description" => "Rolle lesen"
    ],
    [
        "permission_id" => 4,
        "permission_name" => "role_write",
        "description" => "Rolle erstellen, bearbeiten und löschen"
    ],
    [
        "permission_id" => 5,
        "permission_name" => "role_assign",
        "description" => "Rolle zuweisen"
    ]
];

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

    global $permissions;

    if ($permission_id != null) {
        foreach ($permissions as $permission) {
            if ($permission['permission_id'] == $permission_id) {
                $result = $permission;
            }
        }
    } else {
        $result = $permissions;
    }

    echo json_encode($result);
}

?>
