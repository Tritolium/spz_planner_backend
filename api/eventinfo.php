<?php
include_once './config/database.php';

header("Content-Type: application/json");
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');

if(!isset($_GET['api_token'])){
    http_response_code(401);
    exit();
}

$data = json_decode(file_get_contents("php://input"));

switch($_SERVER['REQUEST_METHOD']){
case 'OPTIONS':
    http_response_code(200);
    break;
case 'GET':
    if(!getEntries($_GET['api_token'], $_GET['event_id'])){
        http_response_code(500);
    }
    break;
case 'POST':
    if(addEntry($_GET['api_token'], $_GET['event_id'], $data)){
        http_response_code(201);
    } else {
        http_response_code(500);
    }
    break;
}

function addEntry($api_token, $event_id, $entry_data)
{
    // get member_id
    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "SELECT member_id FROM tblMembers WHERE api_token = :api_token";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":api_token", $api_token);

    if(!$statement->execute()){
        return false;
    }

    $row = $statement->fetch(PDO::FETCH_ASSOC);

    $member_id = $row['member_id'];

    // is member in events group?
    $query = "SELECT * FROM (
        SELECT usergroup_id FROM tblEvents 
        WHERE event_id = :event_id) as event 
        LEFT JOIN tblUsergroupAssignments 
        ON event.usergroup_id=tblUsergroupAssignments.usergroup_id 
        WHERE member_id = :member_id";
    
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":member_id", $member_id);
    $statement->bindParam(":event_id", $event_id);

    if(!$statement->execute()){
        return false;
    }

    if($statement->rowCount() < 1){
        http_response_code(403);
        exit();
    }

    // add entry
    $query = "INSERT INTO tblEventInfo (member_id, event_id, timestamp, content) 
        VALUES (:member_id, :event_id, :timestamp, :content)";
    
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":member_id", $member_id);
    $statement->bindParam(":event_id", $event_id);
    $statement->bindParam(":timestamp", $entry_data->Timestamp);
    $statement->bindParam(":content", $entry_data->Content);

    if(!$statement->execute()){
        return false;
    }

    return true;
}

function getEntries($api_token, $event_id)
{
    // get member_id
    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "SELECT member_id FROM tblMembers WHERE api_token = :api_token";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":api_token", $api_token);

    if(!$statement->execute()){
        return false;
    }

    $row = $statement->fetch(PDO::FETCH_ASSOC);

    $member_id = $row['member_id'];

    // is member in events group?
    $query = "SELECT * FROM (
        SELECT usergroup_id FROM tblEvents 
        WHERE event_id = :event_id) as event 
        LEFT JOIN tblUsergroupAssignments 
        ON event.usergroup_id=tblUsergroupAssignments.usergroup_id 
        WHERE member_id = :member_id";
    
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":member_id", $member_id);
    $statement->bindParam(":event_id", $event_id);

    if(!$statement->execute()){
        return false;
    }

    if($statement->rowCount() < 1){
        http_response_code(403);
        exit();
    }

    // add entry
    $query = "SELECT forename, surname, timestamp, content FROM tblEventInfo 
        LEFT JOIN tblMembers 
        ON tblEventInfo.member_id = tblMembers.member_id
        WHERE event_id = :event_id 
        ORDER BY timestamp";
    
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":event_id", $event_id);

    if(!$statement->execute()){
        return false;
    }

    $entries = array();

    if($statement->rowCount() < 1){
        http_response_code(204);
        exit();
    }

    while($row = $statement->fetch(PDO::FETCH_ASSOC)){
        extract($row);
        $entry = array(
            "Fullname"  => $forename . " " . $surname,
            "Timestamp" => $timestamp,
            "Content"   => $content
        );
        array_push($entries, $entry);
    }

    response_with_data(200, $entries);

    return true;
}
?>