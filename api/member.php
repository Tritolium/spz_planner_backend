<?php
// TODO: remove file when no user uses v0.15 of the app anymore
include_once './config/database.php';
include_once './model/member.php';

$database = new Database();

$db_conn = $database->getConnection();

$member = new Member($db_conn);

$data = json_decode(file_get_contents("php://input"));

header('content-type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: content-type, if-modified-since');

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
    case 'POST':
        // INSERT
        if(!empty($data))
        {
            if($member->create($data))
            {
                response(201, "");
            } else {
                response(500, "");
            }
        } else {
            response(400, $data);
        }
        break;
    case 'GET':
        // SELECT
        if(isset($_GET['birthdate'])){
            getBirthdays($_GET['api_token']);
        } else {
            if(isset($_GET['id'])){
                getMemberById($_GET['id']);
            } else {
                getAllMembers($_GET['api_token']);
            }
        }
        break;
    case 'PUT':
        if($member->update($data)){
            processUsergroupAssignments($data->UsergroupChanges, $data->Member_ID);
            http_response_code(200);
        } else {
            http_response_code(400);
        }
        break;
    default:
        http_response_code(501);
}

function getAllMembers($api_token)
{
    $database = new Database();
    $db_conn = $database->getConnection();
    if(isAdmin($api_token)) {
        $query = "SELECT * FROM tblMembers ORDER BY surname, forename";

        $statement = $db_conn->prepare($query);
    } else {
        $query = "SELECT tm2.member_id, forename, surname, auth_level, instrument, nicknames, birthdate FROM
        (SELECT member_id FROM
        (SELECT usergroup_id FROM tblUsergroupAssignments tua 
        LEFT JOIN tblMembers tm
        ON tua.member_id = tm.member_id
        WHERE api_token=:api_token) as ugroups
        LEFT JOIN tblUsergroupAssignments tua2 
        ON ugroups.usergroup_id = tua2.usergroup_id
        GROUP BY member_id) AS members
        LEFT JOIN tblMembers tm2 
        ON members.member_id = tm2.member_id 
        ORDER BY surname, forename";

        $statement = $db_conn->prepare($query);
        $statement->bindParam(":api_token", $api_token);
    }  
    
    if(!$statement->execute()){
        http_response_code(500);
        exit();
    }

    if($statement->rowCount() < 1){
        http_response_code(204);
        exit();
    }
    
    $member_arr = array();

    while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        extract($row);
        $member_item = array(
            "Member_ID"     => $member_id,
            "Forename"      => $forename,
            "Surname"       => $surname,
            "Auth_level"    => $auth_level,
            "Instrument"    => $instrument,
            "Nicknames"     => $nicknames,
            "Birthdate"     => $birthdate,
            "Usergroups"    => getUsergroupAssignments($member_id)
        );
        array_push($member_arr, $member_item);
    }

    response_with_data(200, $member_arr);
}

function getMemberById($id)
{
    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "SELECT * FROM tblMembers WHERE member_id = :id";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":id", $id);
    $statement->execute();

    if(!$statement->execute()){
        http_response_code(500);
        exit();
    }

    if($statement->rowCount() < 1){
        http_response_code(404);
        exit();
    }

    $row = $statement->fetch(PDO::FETCH_ASSOC);
    extract($row);

    $member = array(
        "Member_ID" => $member_id,
        "Forename"  => $forename,
        "Surname"   => $surname,
        "Nicknames" => $nicknames,
        "Auth_level" => $auth_level,
        "Instrument" => $instrument,
        "Birthdate"     => $birthdate,
        "Usergroups" => getUsergroupAssignments($id)
    );

    response_with_data(200, $member);
}

function getUsergroupAssignments($id)
{
    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "SELECT tblUsergroups.usergroup_id, title, member_id FROM (SELECT * FROM tblUsergroupAssignments WHERE member_id=:member_id) AS ass RIGHT JOIN tblUsergroups ON tblUsergroups.usergroup_id=ass.usergroup_id";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":member_id", $id);

    if(!$statement->execute()){
        http_response_code(500);
        exit();
    }

    $usergroups = array();

    while($row = $statement->fetch(PDO::FETCH_ASSOC)){
        extract($row);
        $usergroup = array(
            "Usergroup_ID"  => $usergroup_id,
            "Title"         => $title,
            "Assigned"      => is_null($member_id) ? false : true
        );
        array_push($usergroups, $usergroup);
    }

    return $usergroups;
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

function getBirthdays($api_token)
{
    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "SELECT forename, surname, birthdate FROM (SELECT member_id FROM (SELECT association_id FROM tblMembers JOIN tblAssociationAssignments ON tblMembers.member_id=tblAssociationAssignments.member_id WHERE api_token=:token) AS assoc JOIN tblAssociationAssignments on assoc.association_id=tblAssociationAssignments.association_id GROUP BY member_id) AS mem JOIN tblMembers ON mem.member_id=tblMembers.member_id";
    $statement = $db_conn->prepare($query);
    $statement->bindParam("token", $api_token);

    $statement->execute();

    $member_list = array();

    while($row = $statement->fetch(PDO::FETCH_ASSOC)){
        extract($row);
        if(!is_null($birthdate)){
            $member = array(
                "Fullname"  => $forename . " " . $surname,
                "Birthday"  => $birthdate
            );
    
            array_push($member_list, $member);
        }
        
    }
    if(count($member_list)){
        response_with_data(200, $member_list);
    } else {
        http_response_code(204);
    }
}
?>