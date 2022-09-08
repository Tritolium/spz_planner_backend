<?php
include_once './config/database.php';
include_once './util/authorization.php';

$database = new Database();

$db_conn = $database->getConnection();

header('content-type: application/json');
header('Access-Control-Allow-Origin: *');

if(!isset($_GET['api_token'])){
    http_response_code(403);
    exit();
}

switch($_SERVER['REQUEST_METHOD'])
{
    case 'POST':
        $data = json_decode(file_get_contents("php://input"));
        if(newUsergroup($_GET['api_token'], $data)){
            http_response_code(201);
        } else {
            http_response_code(500);
        }
        break;
    case 'PUT':
        if(!isset($_GET['id'])){
            http_response_code(400);
            break;
        }
        $data = json_decode(file_get_contents("php://input"));
        if(updateUsergroup($_GET['api_token'], $_GET['id'], $data)){
            http_response_code(200);
        } else {
            http_response_code(500);
        }
        break;
    case 'DELETE':
        if(!isset($_GET['id'])){
            http_response_code(400);
            break;
        }
        if(deleteUsergroup($_GET['api_token'], $_GET['id'])){
            http_response_code(204);
        } else {
            http_response_code(500);
        }
        break;
    case 'GET':
        if(isset($_GET['id'])){
            if(!getUsergroupById($_GET['id'])){
                http_response_code(500);
            }
            break;
        }

        if(isset($_GET['search'])){
            if(!getUsergroupBySearch($_GET['search'])){
                http_response_code(500);
            }
            break;
        }

        http_response_code(400);
        break;
    default:
        http_response_code(501);
        break;
}

function newUsergroup($api_token, $data){
    if(authorize($api_token) < 3){
        http_response_code(403);
        exit();
    }

    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "INSERT INTO tblUsergroups (title, is_admin, is_moderator, info) VALUES (:title, :is_admin, :is_moderator, :info)";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":title", $data->Title);
    $statement->bindParam(":is_admin", $data->Admin);
    $statement->bindParam(":is_moderator", $data->Moderator);
    $statement->bindParam(":info", $data->Info);
    if($statement->execute()){
        return true;
    }

    return false;
}

/**
 * @return boolval success
 */
function updateUsergroup($api_token, $id, $data) : boolval{
    if(authorize($api_token) < 3){
        http_response_code(403);
        exit();
    }

    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "UPDATE tblUsergroups SET title=:title, is_admin=:is_admin, is_moderator=:is_moderator, info=:info WHERE usergroup_id=:usergroup_id";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":usergroup_id", $id);
    $statement->bindParam(":title", $data->Title);
    $statement->bindParam(":is_admin", $data->Admin);
    $statement->bindParam(":is_moderator", $data->Moderator);
    $statement->bindParam(":info", $data->Info);

    if($statement->execute()){
        return true;
    }

    return false;
}

function deleteUsergroup($api_token, $id){
    if(authorize($api_token) < 3){
        http_response_code(403);
        exit();
    }
    
    $database = new Database();
    $db_conn = $database->createConnection();

    $query = "DELETE FROM tblUsergroups WHERE usergroup_id=:usergroup_id";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":usergroup_id", $id);

    if($statement->execute()){
        return true;
    }

    return false;
}

function getUsergroupById($api_token, $id){
    if(authorize($api_token) < 2){
        http_response_code(403);
        exit();
    }

    $database = new Database;
    $db_conn = $database->createConnection();

    $query = "SELECT * FROM tblUsergroups WHERE usergroup_id=:usergroup_id";

    $statement = $db_conn->prepare($query);
    $statement->bindParam(":usergroup_id", $id);

    if(!$statement->execute()){
        return false;
    }

    if($statement->rowCount() < 1){
        http_response_code(404);
        return true;
    }

    $row = $statement->fetch(PDO::FETCH_ASSOC);
    extract($row);
    $usergroup = array(
        "Usergroup_ID"  => intval($usergroup_id),
        "Admin"         => boolval($is_admin),
        "Moderator"     => boolval($is_moderator),
        "Info"          => $info
    );

    response_with_data(200, $usergroup);
    return true;
}

function getUsergroupBySearch($api_token, $serchterm){
    if(authorize($api_token) < 2){
        http_response_code(403);
        exit();
    }
}
?>