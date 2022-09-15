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
        readAllAttendences($_GET['api_token'], $_GET['usergroup']);
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

    $query = "SELECT events.event_id, type, location, date, attendence FROM (SELECT event_id, t4.member_id, type, location, date, begin, accepted FROM tblEvents t 
    LEFT JOIN tblUsergroupAssignments t2 
    ON t.usergroup_id = t2.usergroup_id
    LEFT JOIN tblMembers t4 
    ON t2.member_id = t4.member_id 
    WHERE api_token = :api_token AND accepted=1) 
    AS events
    LEFT JOIN tblAttendence t3
    ON events.event_id = t3.event_id AND events.member_id = t3.member_id 
    WHERE date >= curdate()
    ORDER BY date, begin";

    $statement = $db_conn->prepare($query);
    $statement->bindParam(":api_token", $api_token);

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
        echo json_encode($statement->errorInfo());
        http_response_code(500);
        exit();
    }
}

function readAllAttendences($api_token, $usergroup_id)
{
    $database = new Database();
    $db_conn = $database->getConnection();
    /*
    $query = "SELECT viewCrossMemberEvents.event_id, viewCrossMemberEvents.type, viewCrossMemberEvents.location, forename, surname, attendence, date FROM viewCrossMemberEvents LEFT JOIN tblAttendence ON viewCrossMemberEvents.member_id=tblAttendence.member_id AND viewCrossMemberEvents.event_id=tblAttendence.event_id WHERE date >= :_now";
    */
    $query = "SELECT users.usergroup_id, users.member_id, forename, surname, 
    t3.event_id, type, location, date, attendence
    FROM
    (SELECT usergroup_id, t.member_id, forename, surname 
    FROM tblMembers t 
    LEFT JOIN tblUsergroupAssignments t2 
    ON t.member_id = t2.member_id 
    WHERE usergroup_id = :usergroup_id)
    AS users
    LEFT JOIN tblEvents t3 
    ON users.usergroup_id = t3.usergroup_id
    LEFT JOIN tblAttendence t4 
    ON users.member_id = t4.member_id 
    AND t4.event_id = t3.event_id
    WHERE date >= curdate() AND accepted=1
    ORDER BY date, begin, surname, forename";

    $statement = $db_conn->prepare($query);
    $statement->bindValue(':usergroup_id', $usergroup_id);
    
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
            http_response_code(204);
        }
        exit();
    }
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