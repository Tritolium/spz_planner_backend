<?php
include_once './config/database.php';

if(!isset($_GET['api_token'])){
    http_response_code(401);
    exit();
}

header("Content-Type: application/json");

switch($_SERVER['REQUEST_METHOD']){
case 'GET':
    if(isset($_GET['events'])){
        getEventEval();
    }
    exit();
default:
    http_response_code(501);
    exit();
}

function getEventEval()
{
    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "SELECT event_id, type, location, date FROM tblEvents WHERE date >= curdate() AND accepted=1 ORDER BY date";
    $statement = $db_conn->prepare($query);
    $statement->execute();

    $events = array();
    $eval = array();

    while($row = $statement->fetch(PDO::FETCH_ASSOC)){
        extract($row);
        array_push($events, array(intval($event_id), $type, $location, $date));
    }

    foreach($events as $event_item){
        $consent = getEventConsentCount($event_item[0]);
        $refusal = getEventRefusalCount($event_item[0]);
        $maybe = getEventMaybeCount($event_item[0]);
        $missing = getEventMissingCount($event_item[0]);

        $event = array(
            "Event_ID" => intval($event_item[0]),
            "Type"     => $event_item[1],
            "Location" => $event_item[2],
            "Date"     => $event_item[3],
            "Consent"  => $consent,
            "Refusal"  => $refusal,
            "Maybe"    => $maybe,
            "Missing"  => $missing
        );

        array_push($eval, $event);
    }

    response_with_data(200, $eval);
}

function getEventConsentCount($event_id)
{
    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "SELECT COUNT(attendence) AS consent FROM tblMembers LEFT JOIN (SELECT * FROM tblAttendence WHERE event_id=:event_id) AS a ON tblMembers.member_id=a.member_id WHERE attendence=1";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":event_id", $event_id);
    $statement->execute();
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    return $row["consent"];
}

function getEventRefusalCount($event_id)
{
    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "SELECT COUNT(attendence) AS refusal FROM tblMembers LEFT JOIN (SELECT * FROM tblAttendence WHERE event_id=:event_id) AS a ON tblMembers.member_id=a.member_id WHERE attendence=0";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":event_id", $event_id);
    $statement->execute();
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    return $row["refusal"];
}

function getEventMaybeCount($event_id)
{
    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "SELECT COUNT(attendence) AS maybe FROM tblMembers LEFT JOIN (SELECT * FROM tblAttendence WHERE event_id=:event_id) AS a ON tblMembers.member_id=a.member_id WHERE attendence=2";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":event_id", $event_id);
    $statement->execute();
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    return $row["maybe"];
}

function getEventmissingCount($event_id)
{
    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "SELECT SUM(CASE WHEN attendence IS NULL THEN 1 ELSE 0 END) AS missing FROM tblMembers LEFT JOIN (SELECT * FROM tblAttendence WHERE event_id=:event_id) AS a ON tblMembers.member_id=a.member_id";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":event_id", $event_id);
    $statement->execute();
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    return $row["missing"];
}

?>