<?php

require __DIR__ . '/config/database.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
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
            getRoles($id);
            break;
        case 'PUT':
            updateRole($id);
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
            getRoles();
            break;
        case 'POST':
            createRole();
            break;
        default:
            http_response_code(405);
            break;
    }
}

function getRoles($role_id = null) {
    $database = new Database();
    $db_conn = $database->getConnection();

    if ($role_id != null) {
        $query = "SELECT * FROM tblRoles WHERE role_id = :role_id";
        $stmt = $db_conn->prepare($query);
        $stmt->bindParam(':role_id', $role_id);
    } else {
        $query = "SELECT * FROM tblRoles";
        $stmt = $db_conn->prepare($query);
    }

    if (!$stmt->execute()) {
        http_response_code(500);
        exit();
    }

    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($roles as $index => $role) {
        $query = "SELECT permission_id FROM tblRolePermissions WHERE role_id = :role_id";
        $stmt = $db_conn->prepare($query);
        $stmt->bindParam(':role_id', $role['role_id']);

        if (!$stmt->execute()) {
            http_response_code(500);
            exit();
        }

        $roles[$index]['permissions'] = [];

        $role_permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($role_permissions as $permission) {
            $roles[$index]['permissions'][] = $permission['permission_id'];
        }
    }

    echo json_encode($roles);
}

function updateRole($role_id) {
    // TODO check if the user is authorized to update a role
    $database = new Database();
    $db_conn = $database->getConnection();

    $data = json_decode(file_get_contents('php://input'), true);

    $query = "UPDATE tblRoles SET role_name = :role_name, description = :description WHERE role_id = :role_id";
    $stmt = $db_conn->prepare($query);
    $stmt->bindParam(':role_name', $data['role_name']);
    $stmt->bindParam(':description', $data['description']);
    $stmt->bindParam(':role_id', $role_id);

    if (!$stmt->execute()) {
        http_response_code(500);
        exit();
    }

    $query = "DELETE FROM tblRolePermissions WHERE role_id = :role_id";
    $stmt = $db_conn->prepare($query);
    $stmt->bindParam(':role_id', $role_id);
    
    if (!$stmt->execute()) {
        http_response_code(500);
        exit();
    }

    foreach ($data['permissions'] as $permission_id) {
        $query = "INSERT INTO tblRolePermissions (role_id, permission_id) VALUES (:role_id, :permission_id)";
        $stmt = $db_conn->prepare($query);
        $stmt->bindParam(':role_id', $role_id);
        $stmt->bindParam(':permission_id', $permission_id);

        if (!$stmt->execute()) {
            http_response_code(500);
            exit();
        }
    }
}

function createRole() {
    // TODO check if the user is authorized to create a role
    $database = new Database();
    $db_conn = $database->getConnection();

    $data = json_decode(file_get_contents('php://input'), true);

    $query = "INSERT INTO tblRoles (role_name, description) VALUES (:role_name, :description)";
    $stmt = $db_conn->prepare($query);
    $stmt->bindParam(':role_name', $data['role_name']);
    $stmt->bindParam(':description', $data['role_description']);

    if (!$stmt->execute()) {
        http_response_code(500);
        exit();
    }

    $role_id = $db_conn->lastInsertId();

    foreach ($data['permissions'] as $permission_id) {
        $query = "INSERT INTO tblRolePermissions (role_id, permission_id) VALUES (:role_id, :permission_id)";
        $stmt = $db_conn->prepare($query);
        $stmt->bindParam(':role_id', $role_id);
        $stmt->bindParam(':permission_id', $permission_id);

        if (!$stmt->execute()) {
            http_response_code(500);
            exit();
        }
    }
}

?>