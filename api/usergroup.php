<?php
include_once './config/database.php';
include_once './util/authorization.php';

header('content-type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, POST, GET, OPTIONS, DELETE');

$database = new Database();

$db_conn = $database->getConnection();

header('content-type: application/json');

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
        if(newUsergroup($_GET['api_token'], $data)){
            http_response_code(201);
        } else {
            http_response_code(500);
        }
        break;
    case 'PUT':
        if(isset($_GET['assign'])){
            if(updateAssignments($_GET['api_token'], $data)){
                http_response_code(200);
            } else {
                http_response_code(500);
            }

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

        if(isset($_GET['own'])){
            if(!getOwnUsergroups($_GET['api_token'])){
                http_response_code(500);
            }
            break;
        }

        if(isset($_GET['array'])){
            if(!getComplUsergroupAssignment($_GET['api_token'])){
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
    if(!isAdmin($api_token)){
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
    
    if(!isAdmin($api_token)){
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
    if(!isAdmin($api_token)){
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

    $database = new Database();
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

function getOwnUsergroups($api_token)
{
    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "SELECT t.usergroup_id, title from tblUsergroupAssignments t 
    left join tblUsergroups t3 
    on t.usergroup_id = t3.usergroup_id 
    left join tblMembers t2 
    on t.member_id = t2.member_id 
    where api_token = :api_token";

    $statement = $db_conn->prepare($query);
    $statement->bindParam(":api_token", $api_token);

    $statement->execute();

    $usergroups = array();

    while($row = $statement->fetch(PDO::FETCH_ASSOC)){
        extract($row);
        $usergroup = array(
            "Usergroup_ID"  => $usergroup_id,
            "Title"         => $title
        );

        array_push($usergroups, $usergroup);
    }

    response_with_data(200, $usergroups);
    return true;
}

function getComplUsergroupAssignment($api_token)
{
    if(!isAdmin($api_token)){
        http_response_code(403);
        exit();
    }

    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "SELECT assign.usergroup_id, title, assign.member_id, tua.member_id AS assigned, assign.forename, assign.surname FROM 
    (SELECT tu.usergroup_id, tu.title, tm.member_id, tm.forename, tm.surname  
    FROM tblUsergroups tu 
    JOIN tblMembers tm) AS assign
    LEFT JOIN tblUsergroupAssignments tua 
    ON assign.usergroup_id = tua.usergroup_id 
    AND assign.member_id = tua.member_id
    ORDER BY surname, forename, usergroup_id";

    $statement = $db_conn->prepare($query);

    if(!$statement->execute()){
        return false;
    }

    // wenn statement erfolgreich:
    if($statement->execute()){
        $assignments = array();
        $usergroups = array();
        // zeile holen. wenn zeile gefunden:
        if($row = $statement->fetch(PDO::FETCH_ASSOC)){
            // erste Zeile setzt curr_member_id
            $curr_member_id = intval($row['member_id']);
            // extract, ersten gruppeneintrag füllen
            extract($row);
            $usergroup = array(
                "Usergroup_ID"  => $usergroup_id,
                "Title"         => $title,
                "Assigned"      => (is_null($assigned)) ? false : true
            );
            // in gruppenliste pushen
            array_push($usergroups, $usergroup);
            // while-schleife über die nächsten zeilen
            while($row = $statement->fetch(PDO::FETCH_ASSOC)){
                // wenn curr_member_id = row['member_id']:
                if($curr_member_id == $row['member_id']){
                    // extract, nächsten gruppeneintrag füllen, in liste pushen
                    extract($row);
                    $usergroup = array(
                        "Usergroup_ID"  => $usergroup_id,
                        "Title"         => $title,
                        //"Test"          => $assigned,
                        "Assigned"      => (is_null($assigned)) ? false : true
                    );
                    array_push($usergroups, $usergroup);
                } else {
                    // member-eintrag füllen, gruppenliste als attribut
                    $member = array(
                        "Member_ID"     => $member_id,
                        "Fullname"      => $forename . " " . $surname,
                        "Usergroups"    => $usergroups
                    );
                    // eintrag in assignments pushen
                    array_push($assignments, $member);
                    // extract, curr auf neue member_id, nächsten gruppeneintrag füllen, in liste pushen
                    extract($row);
                    $curr_member_id = $member_id;
                    $usergroups = array();
                    $usergroup = array(
                        "Usergroup_ID"  => $usergroup_id,
                        "Title"         => $title,
                        "Assigned"      => (is_null($assigned)) ? false : true
                    );
                    // in gruppenliste pushen
                    array_push($usergroups, $usergroup);
                }
            }

            // nach letzter Zeile:
            // member-eintrag füllen, gruppenliste als attribut
            $member = array(
                "Member_ID"     => $member_id,
                "Fullname"      => $forename . " " . $surname,
                "Usergroups"    => $usergroups
            );
            // eintrag in assignments pushen
            array_push($assignments, $member);

            response_with_data(200, $assignments);

            return true;
        }

        return false;
    }
}

function updateAssignments($api_token, $assignments)
{
    if(!isAdmin($api_token)){
        http_response_code(403);
        exit();
    }

    foreach($assignments as $member => $changes){
        processUsergroupAssignments($changes, $member);
    }

    return true;
}

function processUsergroupAssignments($changes, $member_id)
{
    foreach($changes as $usergroup_id => $assignment){
        $assignment ? setUsergroupAssignment($usergroup_id, $member_id) : deleteUsergroupAssignment($usergroup_id, $member_id);
    }
}

function setUsergroupAssignment($usergroup_id, $member_id)
{
    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "INSERT INTO tblUsergroupAssignments (usergroup_id, member_id) VALUES (:usergroup_id, :member_id)";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":usergroup_id", $usergroup_id);
    $statement->bindParam(":member_id", $member_id);

    $statement->execute();
}

function deleteUsergroupAssignment($usergroup_id, $member_id)
{
    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "DELETE FROM tblUsergroupAssignments WHERE usergroup_id=:usergroup_id AND member_id=:member_id";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":usergroup_id", $usergroup_id);
    $statement->bindParam(":member_id", $member_id);

    $statement->execute();
}
?>