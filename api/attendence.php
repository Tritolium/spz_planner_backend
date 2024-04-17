<?php
include_once './config/database.php';

if(!isset($_GET['api_token'])){
    http_response_code(401);
    exit();
}

header("Content-Type: application/json");
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: if-modified-since');
header('Access-Control-Allow-Methods: PUT, POST, GET, OPTIONS');

$data = json_decode(file_get_contents("php://input"));

switch($_SERVER['REQUEST_METHOD']){
case 'GET':
    if(isset($_GET['all'])){
        if(isset($_GET['eval'])){
            readAllEvaluations($_GET['api_token'], $_GET['usergroup']);
        } else {
            readAllAttendences($_GET['api_token'], $_GET['usergroup']);
        }
    } else {
        if (isset($_GET['missing'])){
            $event_id = -1;
            if(isset($_GET['event_id'])){
                $event_id = $_GET['event_id'];
            }
            readMissingAttendences($event_id);
        } else {
            if(isset($_GET['event_id'])){
                readAttendence($_GET['api_token'], $_GET['event_id']);
            } else {
                readAttendence($_GET['api_token'], null);
            }
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

function readAttendence($api_token, $event_id)
{
    $database = new Database();
    $db_conn = $database->getConnection();

    if($event_id == null){
        $query = "SELECT events.event_id, category, type, location, address, date, ev_plusone, begin, departure, leave_dep, attendence, events.usergroup_id, association_id, clothing, plusone FROM (SELECT event_id, category, t4.member_id, type, location, address, date, plusone as ev_plusone, begin, departure, leave_dep, accepted, t2.usergroup_id, clothing FROM tblEvents t 
        LEFT JOIN tblUsergroupAssignments t2 
        ON t.usergroup_id = t2.usergroup_id
        LEFT JOIN tblMembers t4 
        ON t2.member_id = t4.member_id 
        WHERE api_token = :api_token AND accepted=1) 
        AS events
        LEFT JOIN tblAttendence t3
        ON events.event_id = t3.event_id AND events.member_id = t3.member_id 
        LEFT JOIN tblUsergroups t5
        ON events.usergroup_id = t5.usergroup_id
        WHERE date >= curdate()
        ORDER BY date, begin";

        $statement = $db_conn->prepare($query);
        $statement->bindParam(":api_token", $api_token);

        if($statement->execute()){
            $attendence_arr = array();
            while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            extract($row);
            $attendence_item = array(
                "Event_ID"       => intval($event_id),
                "Category"       => $category,
                "Attendence"     => (is_null($attendence)) ? -1 : intval($attendence),
                "Type"           => $type,
                "Location"       => $location,
                "Address"        => $address,
                "Ev_PlusOne"     => boolval($ev_plusone),
                "PlusOne"        => boolval($plusone),
                "Begin"          => $begin,
                "Departure"      => $departure,
                "Leave_dep"      => $leave_dep,
                "Date"           => $date,
                "Usergroup_ID"   => $usergroup_id,
                "Association_ID" => $association_id,
                "Clothing"       => $clothing
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
    } else {
        $query = "SELECT ug.member_id, attendence, timestamp, forename, surname 
            FROM (SELECT event_id, member_id 
            FROM tblEvents te 
            LEFT JOIN tblUsergroupAssignments tua 
            ON te.usergroup_id = tua.usergroup_id) AS ug 
            LEFT JOIN tblAttendence ta 
            ON ug.event_id = ta.event_id 
            AND ug.member_id = ta.member_id 
            LEFT JOIN tblMembers tm 
            ON ug.member_id = tm.member_id 
            WHERE ug.event_id = :event_id";
        
        $statement = $db_conn->prepare($query);
        $statement->bindParam(":event_id", $event_id);

        if(!$statement->execute()){
            http_response_code(500);
            exit();
        }

        $attendence_arr = array();
        while($row = $statement->fetch(PDO::FETCH_ASSOC)){
            extract($row);
            $attendence_item = array(
                "Member_ID" => intval($member_id),
                "Attendence" => (is_null($attendence)) ? -1 : intval($attendence),
                "Timestamp" => $timestamp,
                "Fullname" => $forename . " " . $surname
            );
            array_push($attendence_arr, $attendence_item);
        }
        response_with_data(200, $attendence_arr);
    }
}

function readAllAttendences($api_token, $usergroup_id)
{
    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "SELECT users.usergroup_id, users.member_id, forename, surname, 
    t3.event_id, type, location, date, attendence, evaluation, t4.plusone
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
                "Attendence" => (is_null($attendence)) ? -1 : intval($attendence),
                "PlusOne" => (is_null($plusone)) ? 0 : intval($plusone)
            );
            array_push($event_arr, $att_item);
            while($row = $statement->fetch(PDO::FETCH_ASSOC)){
                if($curr_event_id == $row['event_id']){
                    extract($row);
                    $att_item = array(
                        "Fullname" => $forename . " " . $surname,
                        "Attendence" => (is_null($attendence)) ? -1 : intval($attendence),
                        "PlusOne" => (is_null($plusone)) ? 0 : intval($plusone)
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
                        "Attendence" => (is_null($attendence)) ? -1 : intval($attendence),
                        "PlusOne" => (is_null($plusone)) ? 0 : intval($plusone)
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

function readAllEvaluations($api_token, $usergroup_id)
{
    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "SELECT users.usergroup_id, users.member_id, forename, surname, 
    t3.event_id, type, location, date, attendence, evaluation
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
    WHERE evaluated=1 AND accepted=1 AND date >= '2023-01-01'
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
                "Evaluation" => (is_null($evaluation)) ? -1 : intval($evaluation)
            );
            array_push($event_arr, $att_item);
            while($row = $statement->fetch(PDO::FETCH_ASSOC)){
                if($curr_event_id == $row['event_id']){
                    extract($row);
                    $att_item = array(
                        "Fullname" => $forename . " " . $surname,
                        "Evaluation" => (is_null($evaluation)) ? -1 : intval($evaluation)
                    );
                    array_push($event_arr, $att_item);
                } else {
                    $ev = array(
                        "Type" => $type,
                        "Location" => $location,
                        "Date" => $date,
                        "Evaluations" => $event_arr
                    );
                    array_push($attendence_arr, $ev);
                    extract($row);
                    $curr_event_id = $event_id;
                    $event_arr = array();
                    $att_item = array(
                        "Fullname" => $forename . " " . $surname,
                        "Evaluation" => (is_null($evaluation)) ? -1 : intval($evaluation)
                    );
                    array_push($event_arr, $att_item);
                }
            }

            $ev = array(
                "Type" => $type,
                "Location" => $location,
                "Date" => $date,
                "Evaluations" => $event_arr
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
            print_r($attendence);
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
    /* TODO: remove on 1/6/2024 */
    if (!is_array($attendence)){
        $attendence = array($attendence, false);
    }

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
        $statement->bindParam(":attendence", $attendence[0]);
        $statement->bindParam(":member_id", $member_id);
        $statement->bindParam(":event_id", $event_id);
    } else {
        $query = "UPDATE tblAttendence SET attendence=:attendence, plusone=:plusone, timestamp=CURRENT_TIMESTAMP() WHERE member_id=:member_id AND event_id=:event_id";
        $statement = $db_conn->prepare($query);
        $statement->bindParam(":attendence", $attendence[0]);
        $statement->bindValue(":plusone", ($attendence[1] == true) ? 1 : 0);
        $statement->bindParam(":member_id", $member_id);
        $statement->bindParam(":event_id", $event_id);
    }
    
    $statement->execute();

    if($attendence[0] == 0) {
        $query = "SELECT * FROM tblEvents WHERE event_id=:event_id AND date=curdate()";
        $statement = $db_conn->prepare($query);
        $statement->bindParam(":event_id", $event_id);
        $statement->execute();
        if($statement->rowCount() !== 0){
            $query = "SELECT forename, surname FROM tblMembers WHERE member_id=:member_id";
            $statement = $db_conn->prepare($query);
            $statement->bindParam(":member_id", $member_id);
            $statement->execute();
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            $fullname = $row['forename'] . " " . $row['surname'];
        }
    }
}

function readMissingAttendences($event_id)
{
    $database = new Database();
    $db_conn = $database->getConnection();

    if($event_id == -1){
        $query = "SELECT forename, surname, type, location, date FROM tblMembers t 
        JOIN tblUsergroupAssignments t2 
        ON t.member_id = t2.member_id 
        JOIN tblEvents t3 
        ON t2.usergroup_id = t3.usergroup_id 
        LEFT JOIN tblAttendence t4 
        ON t.member_id = t4.member_id 
        AND t3.event_id = t4.event_id 
        WHERE t3.`date` >= curdate() 
        AND t3.`date` < date_add(curdate(), interval 2 day)
        AND t4.attendence is null 
        GROUP BY t.member_id
        ORDER BY surname, forename";

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
                    "Type"     => $type,
                    "Location" => $location,
                    "Date"     => $date
                );
                array_push($attendences, $missing);
            }
            response_with_data(200, $attendences);
            $headers = array();
            $headers[] = "From: <podom@t-online.de>";
            $headers[] = "Content-type: text/html; charset=utf8";
            mail("podom@t-online.de", "Fehlende Rückmeldungen für heute", json_to_html($attendences), implode("\r\n", $headers));
        }
    } else {
        $missing_only = false;
        $query = "SELECT category FROM tblEvents WHERE event_id=:event_id";
        $statement = $db_conn->prepare($query);
        $statement->bindParam(":event_id", $event_id);

        if(!$statement->execute()){
            http_response_code(500);
            exit();
        }

        if($statement->rowCount() < 1){
            http_response_code(204);
            exit();
        }

        if(isset($_GET['monly'])) {
            $missing_only = true;
        }

        $query = "SELECT endpoint, authToken, publicKey FROM 
            (SELECT mem.member_id, attendence FROM 
            (SELECT tblMembers.member_id, event_id 
            FROM tblMembers JOIN tblUsergroupAssignments 
            ON tblMembers.member_id=tblUsergroupAssignments.member_id 
            JOIN tblEvents 
            ON tblEvents.usergroup_id=tblUsergroupAssignments.usergroup_id 
            WHERE event_id=:event_id
            AND type NOT LIKE '%Abgesagt%') AS mem LEFT JOIN tblAttendence 
            ON mem.member_id=tblAttendence.member_id 
            AND mem.event_id=tblAttendence.event_id 
            WHERE attendence IS null "
            . ($missing_only ? "" : "OR attendence = 2") . ") AS missing 
            JOIN tblSubscription 
            ON missing.member_id = tblSubscription.member_id 
            WHERE allowed=1";

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        switch($row['category']){
        case 'practice':
            $query = $query . " AND practice=1";
            break;
        case 'event':
            $query = $query . " AND event=1";
            break;
        case 'other':
            $query = $query . " AND other=1";
            break;
        default:
            break;
        }
        
        $statement = $db_conn->prepare($query);
        $statement->bindParam(":event_id", $event_id);

        if(!$statement->execute()){
            http_response_code(500);
            exit();
        }

        if($statement->rowCount() < 1){
            http_response_code(204);
            exit();
        }
        
        $subscriptions = array();
        
        while($row = $statement->fetch(PDO::FETCH_ASSOC)){
            extract($row);
            $sub = array(
                "endpoint"  => $endpoint,
                "authToken" => $authToken,
                "publicKey" => $publicKey
            );

            array_push($subscriptions, $sub);
        }

        response_with_data(200, $subscriptions);
    }
    
    exit();
}

function json_to_html($json)
{
    $html = "Fehlende Rückmeldungen:<br><ul>";
    echo print_r($json);
    foreach($json as $item){
        extract($item);

        $html .= "<li>" . $Forename . " " . $Surname . "</li>";
    }

    $html .= "</ul>";

    echo $html;
    return $html;
}