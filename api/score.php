<?php
include_once './config/database.php';

header('content-type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, POST, GET, OPTIONS, DELETE');

$database = new Database();

$db_conn = $database->getConnection();

if(!isset($_GET['api_token'])){
    http_response_code(403);
    exit();
}

$data = json_decode(file_get_contents('php://input'));

switch($_SERVER['REQUEST_METHOD'])
{
    case 'OPTIONS':
        http_response_code(200);
        break;
    case 'POST':        
        if(newScore($_GET['api_token'], $data)){
            http_response_code(201);
        } else {
            http_response_code(500);
        }
        break;
    case 'PUT':
        if(!isset($_GET['id'])){
            http_response_code(400);
            exit();
        }
        if(updateScore($_GET['api_token'], $_GET['id'], $data)){
            http_response_code(200);
        } else {
            http_response_code(500);
        }
        break;
    case 'DELETE':
        if(!isset($_GET['id'])){
            http_response_code(400);
        }
        if(deleteScore($_GET['api_token'], $_GET['id'])){
            http_response_code(204);
        } else {
            http_response_code(500);
        }
        break;
    case 'GET':
        if(!getScores($_GET['api_token'])){
            http_response_code(500);
        }
        break;
}

function newScore($token, $data) : boolval
{
    if(!isAdmin($token)){
        http_response_code(403);
        exit();
    }

    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "INSERT INTO tblScores (title, link) VALUES (:title, :link)";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":title", $data->Title);
    $statement->bindParam(":link", $data->Link);
    
    return $statement->execute();
}

function updateScore($token, $id, $data)
{
    if(!isAdmin($token)){
        http_response_code(403);
        exit();
    }

    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "UPDATE tblScores SET title=:title, link=:link WHERE score_id=:score_id";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":title", $data->Title);
    $statement->bindParam(":link", $data->Link);
    $statement->bindParam(":score_id", $id);
    
    return $statement->execute();
}

function deleteScore($token, $id)
{
    if(!isAdmin($token)){
        http_response_code(403);
        exit();
    }

    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "DELETE FROM tblScores WHERE score_id=:score_id";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":score_id", $id);
    
    return $statement->execute();
}

function getScores($token)
{
    if(authorize($token) <= 0){
        http_response_code(403);
        exit();
    }
    
    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "SELECT * FROM tblScores";
    $statement = $db_conn->prepare($query);
    if(!$statement->execute()){
        return false;
    }

    if($statement->rowCount() == 0){
        http_response_code(204);
        exit();
    }

    $scores_array = array();
    
    while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        extract($row);
        $score = array(
            "Score_ID"  => $score_id,
            "Title"     => $title,
            "Link"      => $link
        );
        array_push($scores_array, $score);
    }
    response_with_data(200, $scores_array);
    return true;
}
?>