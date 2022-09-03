<?php
include_once './config/database.php';

if(!isset($_GET['api_token'])){
    http_response_code(401);
    exit();
}

header("Content-Type: application/json");
header('Access-Control-Allow-Origin: *');

$data = json_decode(file_get_contents("php://input"));

switch($_SERVER['REQUEST_METHOD']){
case 'GET':
    if(isset($_GET['all'])){
        readAllAttendences($_GET['api_token']);
    } else {
        if (isset($_GET['missing'])){
            readMissingAttendences();
        } else {
            readAttendence($_GET['api_token']);
        }
    }    
    break;
case 'PUT':
    if(isset($_GET['single'])){
        updateSingleAttendence($data->Member_ID, $data->Event_ID, $data->Attendence);
        exit();
    }
    updateAttendence($_GET['api_token'], $data);
    break;
}

function readAttendence($api_token)
{
    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "SELECT attendence, tblEvents.event_id, type, location, date FROM (SELECT * FROM `viewAttendence` WHERE `api_token` = :api_token) AS Att RIGHT JOIN tblEvents ON Att.event_id = tblEvents.event_id WHERE accepted = 1 AND date >= :_now ORDER BY date";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(':api_token', $api_token);
    $statement->bindValue(":_now", date("Y-m-d"));

    if($statement->execute()){
        $attendence_arr = array();
        while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
           extract($row);
           $attendence_item = array(
               "Event_ID"   => intval($event_id),
               "Attendence" => (is_null($attendence)) ? -1 : intval($attendence),
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

    $query = "SELECT viewCrossMemberEvents.event_id, viewCrossMemberEvents.type, viewCrossMemberEvents.location, forename, surname, attendence, date FROM viewCrossMemberEvents LEFT JOIN tblAttendence ON viewCrossMemberEvents.member_id=tblAttendence.member_id AND viewCrossMemberEvents.event_id=tblAttendence.event_id WHERE date >= :_now";
    $statement = $db_conn->prepare($query);
    $statement->bindValue(':_now', date('Y-m-d'));
    
    if($statement->execute()){
        $attendence_arr = array();
        if($row = $statement->fetch(PDO::FETCH_ASSOC)){
            extract($row);
            $curr_event_id = intval($event_id);
            $event_arr = array();
            $att_item = array(
                "Fullname" => $forename . " " . $surname,
                "Attendence" => (is_null($attendence)) ? -1 : intval($attendence)
            );
            array_push($event_arr, $att_item);
            while($row = $statement->fetch(PDO::FETCH_ASSOC)){
                if($curr_event_id == $row['event_id']){
                    extract($row);
                    $att_item = array(
                        "Fullname" => $forename . " " . $surname,
                        "Attendence" => (is_null($attendence)) ? -1 : intval($attendence)
                    );
                    array_push($event_arr, $att_item);
                } else {
                    $ev = array(
                        "Type" => $type,
                        "Location" => $location,
                        "Date" => $date,
                        "Attendences" => $event_arr
                    );
                    array_push($attendence_arr, $ev);
                    extract($row);
                    $curr_event_id = $event_id;
                    $event_arr = array();
                    $att_item = array(
                        "Fullname" => $forename . " " . $surname,
                        "Attendence" => (is_null($attendence)) ? -1 : intval($attendence)
                    );
                    array_push($event_arr, $att_item);
                }
            }

            $ev = array(
                "Type" => $type,
                "Location" => $location,
                "Date" => $date,
                "Attendences" => $event_arr
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
               "Attendence" => (is_null($attendence)) ? -1 : intval($attendence),
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
            updateSingleAttendence($member_id, $event_id, $attendence);
        }
        http_response_code(200);
    } else {
        http_response_code(500);
        exit();
    }
}

function updateSingleAttendence($member_id, $event_id, $attendence)
{
    $database = new Database();
    $db_conn = $database->getConnection();
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

function readMissingAttendences()
{
    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "SELECT * FROM viewMissingAttendence";

    $statement = $db_conn->prepare($query);

    $statement->execute();
    if($statement->rowCount() < 1){
        http_response_code(204);
    } else {
        $attendences = array();
        while($row = $statement->fetch(PDO::FETCH_ASSOC)){
            extract($row);
            $missing = array(
                "Forename" => $forename,
                "Surname"  => $surname,
                "FirstMissing" => $type . " " . $location
            );
            array_push($attendences, $missing);
        }
        response_with_data(200, $attendences);
    }
    exit();
}