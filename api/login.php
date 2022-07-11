<?php
include_once './config/database.php';

if($_SERVER['REQUEST_METHOD'] != 'GET'){
    http_response_code(501);
    exit();
}

$database = new Database();
$db_conn = $database->getConnection();

if(!isset($_GET['mode'])){
    http_response_code(400);
    exit();
}

switch($_GET['mode']){
case 'login':
    if(!isset($_GET['name'])){
        http_response_code(400);
        exit();
    }
    $name = '%' . $_GET['name'] . '%';

    $statement = $db_conn->prepare('SELECT forename, surname, auth_level, api_token FROM tblMembers WHERE CONCAT(forename, \' \', surname, \' \', nicknames) LIKE :full_name');
    $statement->bindParam(":full_name", $name);

    if($statement->execute()){
        if($statement->rowCount() == 1){
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            extract($row);
            $response_body = array(
                "Forename" => $forename,
                "Surname" => $surname,
                "API_token" => $api_token,
                "Auth_level" => $auth_level
            );

            response_with_data(200, $response_body);
        } else {
            http_response_code(404);
        }
    }
    exit();
case 'update':
    if(!isset($_GET['api_token'])){
        http_response_code(400);
        exit();
    }
    $api_token = $_GET['api_token'];

    $statement = $db_conn->prepare('SELECT forename, surname, auth_level FROM tblMembers WHERE api_token = :token');
    $statement->bindParam(":token", $api_token);

    if($statement->execute()){
        if($statement->rowCount() == 1){
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            extract($row);
            $response_body = array(
                "Forename" => $forename,
                "Surname" => $surname,
                "Auth_level" => $auth_level
            );
            response_with_data(200, $response_body);
        } else {
            http_response_code(204);
        }
    }
    exit();
}

?>