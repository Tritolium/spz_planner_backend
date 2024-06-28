<?php
require_once __DIR__ . '/../config/database.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PATCH, OPTIONS');
header('Content-Type: application/json; charset=UTF-8');

$request = $_SERVER['REQUEST_URI'];
// remove the /api/v0 part of the request
$request = str_replace('/api/v0', '', $request);
// remove the query string
$request = explode('?', $request)[0];
// split the request to get the id
$request_exploded = explode('/', $request);

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] != 'PATCH') {
    http_response_code(405);
    exit();
}

$member_id = null;

if (isset($request_exploded[2]) && $request_exploded[2] != '') {
    $member_id = intval($request_exploded[2]);
} else {
    http_response_code(400);
    exit();
}

$data = json_decode(file_get_contents('php://input'));

if (!isset($data->association_id) || !isset($data->role_ids)) {
    http_response_code(400);
    exit();
}

$database = new Database();
$db_conn = $database->getConnection();

$query = "DELETE FROM tblUserRoles WHERE member_id = :member_id AND association_id = :association_id";
$statement = $db_conn->prepare($query);
$statement->bindParam(':member_id', $member_id);
$statement->bindParam(':association_id', $data->association_id);

if (!$statement->execute()) {
    http_response_code(500);
    exit();
}

foreach ($data->role_ids as $role_id) {
    $query = "INSERT INTO tblUserRoles (member_id, association_id, role_id) VALUES (:member_id, :association_id, :role_id)";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(':member_id', $member_id);
    $statement->bindParam(':association_id', $data->association_id);
    $statement->bindParam(':role_id', $role_id);

    if (!$statement->execute()) {
        http_response_code(500);
        exit();
    }
}

http_response_code(200);

?>