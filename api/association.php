<?php
include_once './config/database.php';
include_once './util/caching.php';

header('content-type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: if-modified-since');

if(!isset($_GET['api_token'])){
    http_response_code(403);
    exit();
}

$data = json_decode(file_get_contents('php://input'));

switch([$_SERVER['REQUEST_METHOD']])
{
    case 'OPTIONS':
        http_response_code(200);
        break;
    case 'POST':
        if(newAssociation($_GET['api_token'], $data)){
            http_response_code(201);
        } else {
            http_response_code(500);
        }
        break;
    case 'PUT':
        if(updateAssociation($_GET['api_token'], $_GET['id'], $data)){
            http_response_code(200);
        } else {
            http_response_code(500);
        }
        break;
    case 'GET':
        if(!getAssociations($_GET['api_token'])){
            http_response_code(500);
        }
        break;
}

function newAssociation($api_token, $data){
    if(!isAdmin($api_token)){
        http_response_code(403);
        exit();
    }

    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "INSERT INTO tblAssociations (title, firstchair, treasurer, clerk) VALUES (:title, :firstchair, :treasurer, :clerk)";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":title", $data->Title);
    $statement->bindParam(":firstchair", $data->Firstchair);
    $statement->bindParam(":treasurer", $data->Treasurer);
    $statement->bindParam(":clerk", $data->Clerk);
    
    if($statement->execute()){
        return true;
    }

    return false;
}

function updateAssociation($api_token, $id, $data){
    if(!isAdmin($api_token)){
        http_response_code(403);
        exit();
    }

    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "UPDATE tblAssociations SET title=:title, firstchair=:firstchair, treasurer=:treasurer, clerk=:clerk WHERE association_id = :association_id";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":title", $data->Title);
    $statement->bindParam(":firstchair", $data->Firstchair);
    $statement->bindParam(":treasurer", $data->Treasurer);
    $statement->bindParam(":clerk", $data->Clerk);
    $statement->bindParam(":association_id", $id);
    return $statement->execute();
}

function getAssociations($api_token){
    if(!isAdmin($api_token)){
        http_response_code(403);
        exit();
    }

    checkIfModified(['tblAssociations']);

    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "SELECT * FROM tblAssociations";

    $statement = $db_conn->prepare($query);

    if(!$statement->execute()){
        return false;
    }

    if($statement->rowCount() < 1){
        http_response_code(204);
        exit();
    }

    $associations_array = array();

    while($row = $statement->fetch(PDO::FETCH_ASSOC)){
        extract($row);
        $association = array(
            "Association_ID"    => $association_id,
            "Title"             => $title,
            "FirstChair"        => $firstchair,
            "Clerk"             => $clerk,
            "Treasurer"         => $treasurer
        );

        array_push($associations_array, $association);
    }

    response_with_data(200, $associations_array);
    return true;
}
?>