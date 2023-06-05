<?php
include_once './config/database.php';

$data = json_decode(file_get_contents("php://input"));

header('content-type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: content-type');

if(isset($_GET['api_token'])){
    $auth_level = authorize($_GET['api_token']);
} else {
    http_response_code(403);
    exit();
}

switch($_SERVER['REQUEST_METHOD'])
{
    case 'OPTIONS':
        http_response_code(200);
        break;
    case 'GET':
        getUserSettings($_GET['api_token']);
        break;
    case 'PUT':
        setUserSettings($_GET['api_token'], $data);
        break;
    default:
        http_response_code(501);
}

function getUserSettings($api_token)
{
    $database = new Database();
    $db_conn = $database->getConnection();
    $query = "SELECT forename, surname, birthdate FROM tblMembers WHERE api_token=:api_token";

    $statement = $db_conn->prepare($query);
    $statement->bindParam(":api_token", $api_token);

    if(!$statement->execute()){
        print_r($statement->errorInfo());
        http_response_code(500);
        exit();
    }

    $row = $statement->fetch(PDO::FETCH_ASSOC);
    extract($row);

    $response = array(
        "Fullname"  => $forename . " " . $surname,
        "Birthdate" => $birthdate
    );

    response_with_data(200, $response);
}

function setUserSettings($api_token, $data)
{
    $database = new Database();
    $db_conn = $database->getConnection();
    $query = "SELECT pwhash FROM tblMembers WHERE api_token=:api_token";
    
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":api_token", $api_token);
    
    if(!$statement->execute()){
        print_r($statement->errorInfo());
        http_response_code(500);
        exit();
    }
    
    $row = $statement->fetch(PDO::FETCH_ASSOC);

    if($data->oldPassword != $row['pwhash']) {
        http_response_code(409);
        exit();
    }
    
    $query = "UPDATE tblMembers SET pwhash=:pwhash WHERE api_token=:api_token";

    $statement = $db_conn->prepare($query);
    $statement->bindParam(":api_token", $api_token);
    $statement->bindParam(":pwhash", $data->newPassword);

    if(!$statement->execute()){
        http_response_code(501);
        exit();
    } else {
        http_response_code(200);
    }
}