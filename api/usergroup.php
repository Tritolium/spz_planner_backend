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

$data = json_decode(file_get_contents('php://input'));

switch($_SERVER['REQUEST_METHOD'])
{
    case 'POST':        
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
            if(!getUsergroupById($_GET['api_token'], $_GET['id'])){
                http_response_code(500);
            }
            break;
        }

        if(isset($_GET['search'])){
            if(!getUsergroupBySearch($_GET['api_token'], $_GET['search'])){
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
function updateUsergroup($api_token, $id, $data){
    
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
    return $statement->execute();
}

function deleteUsergroup($api_token, $id){
    if(authorize($api_token) < 3){
        http_response_code(403);
        exit();
    }
    
    $database = new Database();
    $db_conn = $database->getConnection();

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
    $db_conn = $database->getConnection();

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

function getUsergroupBySearch($api_token, $searchterm){
    if(authorize($api_token) < 2){
        http_response_code(403);
        exit();
    }
    
    $title = '%' . $searchterm . '%';
    
    $database = new Database();
    $db_conn = $database->getConnection();
    
    $query = "SELECT * FROM tblUsergroups WHERE title LIKE :title";
    
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":title", $title);
    
    if(!$statement->execute()){
        return false;
    }
    
    if($statement->rowCount() < 1){
        http_response_code(204);
        return true;
    }

    $group_array = array();

    while($row = $statement->fetch(PDO::FETCH_ASSOC)){
        extract($row);
        $group = array(
            "Usergroup_ID"  => $usergroup_id,
            "Title"         => $title,
            "Admin"         => $is_admin,
            "Moderator"     => $is_moderator,
            "Info"          => $info
        );

        array_push($group_array, $group);
    }

    response_with_data(200, $group_array);
    return true;
}
?>