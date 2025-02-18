<?php

require_once './config/database.php';
require_once './config/permission-helper.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, OPTIONS');
header("Content-Type: application/json; charset=UTF-8");

$request = $_SERVER['REQUEST_URI'];
// remove the /api/v0 part of the request
$request = str_replace('/api/v0', '', $request);
// remove the query string
$request = explode('?', $request)[0];
// split the request to get the id
$request_exploded = explode('/', $request);

if (isset($request_exploded[2]) && $request_exploded[2] != '') {
    // the request has an id
    $id = $request_exploded[2];
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'OPTIONS':
            http_response_code(200);
            break;
        case 'GET':
            getAttendenceEval($id);
            break;
        case 'PUT':
            updateAttendenceEval($id);
            break;
        default:
            http_response_code(405);
            break;
    }
} else {
    // the request does not have an id
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'OPTIONS':
            http_response_code(200);
            break;
        case 'GET':
            getAttendenceEval();
            break;
        default:
            http_response_code(405);
            break;
    }
}

function getAttendenceEval($event_id = null) {
    $database = new Database();
    $db_conn = $database->getConnection();

    require_once './predictionlog.php';

    if ($event_id != null) {
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
            return;
        }

        $attendence_arr = array();
        while($row = $statement->fetch(PDO::FETCH_ASSOC)){
            extract($row);

            [$_att, $_miss, $_sign] = predictAttendencePerMember($member_id, $event_id);

            $prediction = 0*$_att + 1*$_miss + 2*$_sign;

            $attendence_item = array(
                "Member_ID" => intval($member_id),
                "Attendence" => (is_null($attendence)) ? -1 : intval($attendence),
                "Prediction" => $prediction,
                "Timestamp" => $timestamp,
                "Fullname" => $forename . " " . $surname
            );
            array_push($attendence_arr, $attendence_item);
        }
        response_with_data(200, $attendence_arr);
    } else {
        if(!isset($_GET['usergroup_id'])){
            http_response_code(400);
            return;
        }

        $usergroup_id = $_GET['usergroup_id'];

        // check if the user has the permission to read attendenceeval
        $query = "SELECT association_id FROM tblUsergroups WHERE usergroup_id=:usergroup_id";
        $statement = $db_conn->prepare($query);
        $statement->bindParam(':usergroup_id', $usergroup_id);
        if(!$statement->execute()){
            http_response_code(500);
            return;
        }

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if(!hasPermission($_GET['api_token'], 8, $row['association_id'])){
            http_response_code(403);
            return;
        }

        $query = "SELECT users.usergroup_id, users.member_id, forename, surname, 
            t3.event_id, type, location, date, attendence, evaluation, category, prediction
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
            WHERE evaluated=1 
            AND state = 1
            AND date >= '2023-01-01'
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
                            "Event_ID" => $curr_event_id,
                            "Type" => $type,
                            "Location" => $location,
                            "Date" => $date,
                            "Category" => $category,
                            "Prediction" => $prediction,
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
                    "Category" => $category,
                    "Prediction" => $prediction,
                    "Evaluations" => $event_arr
                );
                array_push($attendence_arr, $ev);

                // check if there are any events following a practice event
                $attendence_arr = calcNextEvent($attendence_arr);

                response_with_data(200, $attendence_arr);
            } else {
                http_response_code(204);
            }
            exit();
        }
    }
}

function calcNextEvent($predictions) {

    $database = new Database();
    $db_conn = $database->getConnection();

    for($i = 0; $i < count($predictions); $i++){
        if($predictions[$i]['Category'] != 'practice'){
            $predictions[$i]['NextEvent'] = -1;
            continue;
        }

        $next_event = -1;

        $date = $predictions[$i]['Date'];

        $query = "SELECT event_id, date FROM tblEvents WHERE usergroup_id=:usergroup_id AND date > :date AND category='event' AND state = 1 ORDER BY date LIMIT 1";
        $statement = $db_conn->prepare($query);
        $statement->bindParam(':usergroup_id', $_GET['usergroup_id']);
        $statement->bindParam(':date', $date);
        if($statement->execute()){
            if($row = $statement->fetch(PDO::FETCH_ASSOC)){
                // calculate the difference in days
                $diff_days = (strtotime($row['date']) - strtotime($date)) / (60 * 60 * 24);
                $next_event = intdiv($diff_days, 7);
            }
        }

        $predictions[$i]['NextEvent'] = $next_event;
    }

    return $predictions;
}

?>