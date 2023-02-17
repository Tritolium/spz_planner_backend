<?php
include_once './config/database.php';

header('content-type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

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
        if(newFeedback($_GET['api_token'], $data)){
            http_response_code(201);
        } else {
            http_response_code(500);
        }
        break;
    case 'GET':
        if(!getFeedbacks($_GET['api_token'])){
            http_response_code(500);
        }
        break;
}

function newFeedback($token, $data)
{
    if(authorize($token) <= 0){
        http_response_code(403);
        exit();
    }

    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "INSERT INTO tblFeedback (content) VALUES (:content)";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":content", $data->Content);
    
    return $statement->execute();
}

function getFeedbacks($token)
{
    if(!isAdmin($token)){
        http_response_code(403);
        exit();
    }

    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "SELECT * FROM tblFeedback";
    $statement = $db_conn->prepare();

    if(!$statement->execute()){
        return false;
    }

    if($statement->rowCount() == 0){
        http_response_code(204);
        exit();
    }

    $feedback_array = array();
    
    while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        extract($row);
        array_push($feedback_array, $content);
    }
    response_with_data(200, $feedback_array);
    return true;
}
?>