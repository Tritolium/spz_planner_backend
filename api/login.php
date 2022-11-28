<?php
include_once './config/database.php';

if($_SERVER['REQUEST_METHOD'] != 'GET'){
    http_response_code(501);
    exit();
}

header('Access-Control-Allow-Origin: *');

$database = new Database();
$db_conn = $database->getConnection();

if(!isset($_GET['mode'])){
    http_response_code(400);
    echo('<h>No Mode</h>');
    exit();
}

switch($_GET['mode']){
case 'login':
    if(!isset($_GET['name'])){
        http_response_code(400);
        echo('<h>No Name</h>');
        exit();
    }

    $name = '%' . $_GET['name'] . '%';

    $statement = $db_conn->prepare('SELECT forename, surname, auth_level, api_token FROM tblMembers WHERE Nicknames LIKE :name');
    $statement->bindParam(":name", $name);

    if($statement->execute()){
        if($statement->rowCount() == 1){
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } else {
            $statement = $db_conn->prepare('SELECT forename, surname, auth_level, api_token FROM tblMembers WHERE CONCAT(forename, \' \', surname, \' \', nicknames) LIKE :full_name');
            $statement->bindParam(":full_name", $name);
            
            if($statement->execute()){
                if($statement->rowCount() == 1){
                    $row = $statement->fetch(PDO::FETCH_ASSOC);
                }
            }
        }
    }

    if($row !== NULL){
        extract($row);
        $response_body = array(
            "Forename" => $forename,
            "Surname" => $surname,
            "API_token" => $api_token,
            "Auth_level" => $auth_level
        );

        lastLogin($api_token);

        response_with_data(200, $response_body);
    } else {
        http_response_code(404);
    }

    exit();
case 'update':
    if(!isset($_GET['api_token'])){
        http_response_code(400);
        echo('<h>No Token</h>');
        exit();
    }
    $api_token = $_GET['api_token'];

    lastLogin($api_token);

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

function lastLogin($api_token)
{
    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "UPDATE tblMembers SET last_login=CURRENT_TIMESTAMP WHERE api_token=:api_token";
    $statement = $db_conn->prepare($query);
    
    $statement->bindParam(":api_token", $api_token);

    $statement->execute();
}

?>