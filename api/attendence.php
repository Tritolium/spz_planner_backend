<?php
include_once './config/database.php';

if(!isset($_GET['api_token'])){
    http_response_code(401);
    exit();
}

$data = json_decode(file_get_contents("php://input"));

switch($_SERVER['REQUEST_METHOD']){
case 'GET':
    if(isset($_GET['all'])){
        readAllAttendences($_GET['api_token']);
    } else {
        readAttendence($_GET['api_token']);
    }    
    break;
case 'PUT':
    updateAttendence($_GET['api_token'], $data);
    break;
}

function readAttendence($api_token)
{
    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "SELECT attendence, tblEvents.event_id, type, location, date FROM (SELECT * FROM `viewAttendence` WHERE `api_token` = :api_token) AS Att RIGHT JOIN tblEvents ON Att.event_id = tblEvents.event_id WHERE accepted = 1 AND date > :_now ORDER BY date";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(':api_token', $api_token);
    $statement->bindValue(":_now", date("Y-m-d"));

    if($statement->execute()){
        $attendence_arr = array();
        while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
           extract($row);
           $attendence_item = array(
               "Event_ID"   => intval($event_id),
               "Attendence" => ($attendence == NULL) ? -1 : intval($attendence),
               "Type"       => $type,
               "Location"   => $location,
               "Date"       => $date
           );
           array_push($attendence_arr, $attendence_item);
       }
       response_with_data(200, $attendence_arr);
       exit();
    } else {
        http_response_code(500);
        exit();
    }
}

function readAllAttendences($api_token)
{
    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "SELECT viewCrossMemberEvents.member_id, viewCrossMemberEvents.event_id, attendence FROM viewCrossMemberEvents LEFT JOIN tblAttendence ON viewCrossMemberEvents.member_id=tblAttendence.member_id AND viewCrossMemberEvents.event_id=tblAttendence.event_id WHERE viewCrossMemberEvents.date > :_now ORDER BY viewCrossMemberEvents.event_id, viewCrossMemberEvents.member_id";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(':_now', date('Y-m-d'));
    
    if($statement->execute()){
        $attendence_arr = array();
        if($row = $statement->fetch(PDO::FETCH_ASSOC)){
            extract($row);
            $curr_event_id = intval($event_id);
            $event_arr = array();
            $att_item = array(
                "Member_ID"  => $member_id,
                "Attendence" => ($attendence == NULL) ? -1 : intval($attendence)
            );
            array_push($event_arr, $att_item);
            while($row = $statement->fetch(PDO::FETCH_ASSOC)){
                extract($row);
                if($curr_event_id == $event_id){
                    $att_item = array(
                        "Member_ID"  => $member_id,
                        "Attendence" => ($attendence == NULL) ? -1 : intval($attendence)
                    );
                    array_push($event_arr, $att_item);
                } else {
                    $ev = array(
                        "Event_ID" => intval($curr_event_id),
                        "Attendences" => $event_arr
                    );
                    array_push($attendence_arr, $ev);
                    $curr_event_id = $event_id;
                    $event_arr = array();
                    $att_item = array(
                        "Member_ID"  => $member_id,
                        "Attendence" => ($attendence == NULL) ? -1 : intval($attendence)
                    );
                    array_push($event_arr, $att_item);
                }
            }

            $ev = array(
                intval($curr_event_id) => $event_arr
            );
            array_push($attendence_arr, $ev);

            response_with_data(200, $attendence_arr);
        } else {
            http_response_code(404);
        }
        exit();
    }
    /*
    if($statement->execute()){
        $attendence_arr = array();
        while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
           extract($row);
           $attendence_item = array(
               "Event_ID"   => intval($event_id),
               "Attendence" => ($attendence == NULL) ? -1 : intval($attendence),
               "Member_ID"  => $member_id
           );
           array_push($attendence_arr, $attendence_item);
       }
       response_with_data(200, $attendence_arr);
       exit();
    } else {
        http_response_code(500);
        exit();
    }*/
}

function updateAttendence($api_token, $changes)
{
    $database = new Database();
    $db_conn = $database->getConnection();
    $query = "SELECT member_id FROM tblMembers WHERE api_token = :api_token";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(':api_token', $api_token);
    if($statement->execute()){
        if($statement->rowCount() < 1){
            http_response_code(401);
            exit();
        }
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        extract($row);
        foreach($changes as $event_id => $attendence){
            updateSingleAttendence($db_conn, $member_id, $event_id, $attendence);
        }
        http_response_code(200);
    } else {
        http_response_code(500);
        exit();
    }
}

function updateSingleAttendence($db_conn, $member_id, $event_id, $attendence)
{
    $query = "SELECT * FROM tblAttendence WHERE member_id=:member_id AND event_id=:event_id";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":member_id", $member_id);
    $statement->bindParam(":event_id", $event_id);
    $statement->execute();
    if($statement->rowCount() < 1){
        $query = "INSERT INTO tblAttendence (attendence, member_id, event_id) VALUES (:attendence, :member_id, :event_id)";
        $statement = $db_conn->prepare($query);
        $statement->bindParam(":attendence", $attendence);
        $statement->bindParam(":member_id", $member_id);
        $statement->bindParam(":event_id", $event_id);
    } else {
        $query = "UPDATE tblAttendence SET attendence=:attendence WHERE member_id=:member_id AND event_id=:event_id";
        $statement = $db_conn->prepare($query);
        $statement->bindParam(":attendence", $attendence);
        $statement->bindParam(":member_id", $member_id);
        $statement->bindParam(":event_id", $event_id);
    }
    
    $statement->execute();
}