<?php

require_once './config/database.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PATCH, PUT, POST, OPTIONS');
header("Content-Type: application/json; charset=UTF-8");
// header("Cache-Control: max-age=30, stale-while-revalidate=600");

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
            getAttendence($id);
            break;
        case 'PATCH':
            updateAttendence($id);
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
            getAttendence();
            break;
        default:
            http_response_code(405);
            break;
    }
}

function getAttendence($event_id = null) {
    $database = new Database();
    $db_conn = $database->getConnection();

    if ($event_id != null) {
        $query = "SELECT * FROM tblEvents WHERE event_id=:event_id";

        $statement = $db_conn->prepare($query);
        $statement->bindParam(":event_id", $event_id);
        
        if ($statement->execute()) {
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            extract($row);
            $event = array(
                "Event_ID" => $event_id,
                "Type" => $type,
                "Location" => $location,
                "Address" => $address,
                "Category" => $category,
                "Date" => $date,
                "Begin" => $begin,
                "Departure" => $departure,
                "Leave_dep" => $leave_dep,
                "Ev_PlusOne" => boolval($plusone),
                "Clothing" => $clothing,
                "Usergroup_ID" => $usergroup_id,
            );
        } else {
            http_response_code(500);
            return;
        }

        $query = "SELECT attendence, tblAttendence.plusone, COUNT(*) FROM `tblEvents` 
            LEFT JOIN tblUsergroupAssignments 
            ON tblEvents.usergroup_id=tblUsergroupAssignments.usergroup_id 
            LEFT JOIN tblAttendence 
            ON tblEvents.event_id=tblAttendence.event_id 
            AND tblUsergroupAssignments.member_id=tblAttendence.member_id 
            WHERE tblEvents.event_id=:event_id 
            GROUP BY attendence, tblAttendence.plusone";

        $statement = $db_conn->prepare($query);
        $statement->bindParam(":event_id", $event_id);

        if (!$statement->execute()) {
            http_response_code(500);
            return;
        }

        $consent = 0;
        $refusal = 0;
        $maybe = 0;
        $missing = 0;
        $plusone = 0;
        $prob_attending = 0;
        $prob_missing = 0;

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            if(!isset($row['attendence'])){
                $missing = intval($row['COUNT(*)']);
                continue;
            }
            switch ($row['attendence']) {
                case 0:
                    $refusal = intval($row['COUNT(*)']);
                    break;
                case 1:
                    if($row['plusone'] == 1)
                        $plusone = intval($row['COUNT(*)']);
                    else
                        $consent = intval($row['COUNT(*)']);
                    break;
                case 2:
                    $maybe = intval($row['COUNT(*)']);
                    break;
            }
        }

        [$prob_attending, $prob_missing] = predictAttendence($event_id);

        $attendence = array(
            "Event_ID" => $event_id,
            "Consent" => $consent,
            "Refusal" => $refusal,
            "Maybe" => $maybe,
            "Missing" => $missing,
            "ProbAttending" => $prob_attending,
            "ProbMissing" => $prob_missing,
            "PlusOne" => $plusone
        );

        $query = "SELECT COUNT(*) FROM tblAttendence WHERE event_id=:event_id AND plusone=1";

        $query = "SELECT * FROM tblAttendence
            LEFT JOIN tblMembers
            ON tblAttendence.member_id=tblMembers.member_id
            WHERE tblAttendence.event_id=:event_id
            AND tblMembers.api_token=:token";

        $statement = $db_conn->prepare($query);
        $statement->bindParam(":event_id", $event_id);
        $statement->bindParam(":token", $_GET['api_token']);

        if ($statement->execute()) {
            $attendence_user = $statement->fetch(PDO::FETCH_ASSOC);
        } else {
            http_response_code(500);
            return;
        }
        error_reporting(0);
        $event['Attendence'] = is_null($attendence_user['attendence']) ? -1 : intval($attendence_user['attendence']);
        $event['PlusOne'] = is_null($attendence_user['plusone']) ? 0 : boolval($attendence_user['plusone']);
        error_reporting(E_ALL);

        response_with_data(200, array(
            'Event' => $event,
            'Attendence' => $attendence
        ));
    } else {
        if (!isset($_GET['usergroup_id'])) {
            // get attendence for all events for the user
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
            $statement->bindParam(":api_token", $_GET['api_token']);

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
            } else {
                echo json_encode($statement->errorInfo());
                http_response_code(500);
            }
        } else {
            // get attendence for all members of the usergroup
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
            $statement->bindValue(':usergroup_id', $_GET['usergroup_id']);
            
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
    }
}

function updateAttendence($event_id) {
    $database = new Database();
    $db_conn = $database->getConnection();

    $data = json_decode(file_get_contents("php://input"));

    if (!isset($data->Member_ID)) {
        $query = "SELECT member_id FROM tblMembers WHERE api_token=:api_token";
        $statement = $db_conn->prepare($query);
        $statement->bindParam(":api_token", $_GET['api_token']);
        $statement->execute();
        $data->Member_ID = $statement->fetch(PDO::FETCH_ASSOC)['member_id'];
    } else {
        // TODO check if the requesting user is allowed to update the attendence of the member
    }

    $query = "INSERT INTO tblAttendence (event_id, member_id, attendence, plusone) VALUES (:event_id, :member_id, :attendence, :plusone) ON DUPLICATE KEY UPDATE attendence=:attendence, plusone=:plusone";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":event_id", $event_id);
    $statement->bindParam(":member_id", $data->Member_ID);
    $statement->bindParam(":attendence", $data->Attendence);
    $statement->bindValue(":plusone", $data->PlusOne ? 1 : 0);

    if ($statement->execute()) {
        http_response_code(200);
    } else {
        http_response_code(500);
    }
}

function predictAttendence($event_id) {
    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "SELECT tblUsergroupAssignments.member_id FROM `tblEvents` 
        LEFT JOIN tblUsergroupAssignments 
        ON tblEvents.usergroup_id=tblUsergroupAssignments.usergroup_id 
        LEFT JOIN tblAttendence 
        ON tblEvents.event_id=tblAttendence.event_id 
        AND tblUsergroupAssignments.member_id=tblAttendence.member_id 
        WHERE tblEvents.event_id=:event_id 
        AND attendence IS NULL";

    $statement = $db_conn->prepare($query);
    $statement->bindParam(":event_id", $event_id);

    if (!$statement->execute()) {
        http_response_code(500);
        return;
    }

    $prob_missing = 0;
    $prob_attending = 0;

    $members = array();

    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        array_push($members, $row['member_id']);
    }

    // iterate over members
    foreach ($members as $member_id) {
        /*
        * Get the last 10 attendences of the member for events of the same category
        * and calculate the probability of the member attending the event, based on the
        * evaluation of the attendences.
        * Limit the attendences to the last 10 to prevent the model from being biased.
        *
        * Adjust the limit over time to get a better prediction.
        */
        $query = "SELECT evaluation, COUNT(*) FROM 
            (SELECT evaluation FROM tblAttendence 
            LEFT JOIN tblEvents 
            ON tblAttendence.event_id=tblEvents.event_id 
            WHERE member_id=:member_id 
            AND evaluation IS NOT NULL 
            AND category=(SELECT category FROM tblEvents WHERE event_id=:event_id)
            ORDER BY date 
            LIMIT 10) att 
            GROUP BY evaluation";
        $statement = $db_conn->prepare($query);
        $statement->bindParam(":member_id", $member_id);
        $statement->bindParam(":event_id", $event_id);

        if (!$statement->execute()) {
            http_response_code(500);
            return;
        }

        $okay = 0;
        $not_okay = 0;

        if ($statement->rowCount() < 5) {
            // no or not enough evaluated attendences in the category, use all categories
            $query = "SELECT evaluation, COUNT(*) FROM 
                (SELECT evaluation FROM tblAttendence 
                LEFT JOIN tblEvents 
                ON tblAttendence.event_id=tblEvents.event_id 
                WHERE member_id=:member_id 
                AND evaluation IS NOT NULL 
                ORDER BY date 
                LIMIT 10) att 
                GROUP BY evaluation";
            $statement = $db_conn->prepare($query);
            $statement->bindParam(":member_id", $member_id);

            if (!$statement->execute()) {
                http_response_code(500);
                return;
            }
        }

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            switch ($row['evaluation']) {
            case 0:
            case 1:
                $not_okay += intval($row['COUNT(*)']);
                break;
            default:
                $okay += intval($row['COUNT(*)']);
                break;
            }
        }

        if ($okay + $not_okay == 0) {
            // no evaluated attendences
            $prob_missing += 1;
            continue;
        }

        if ($not_okay / ($not_okay + $okay) >= 0.1) {
            $prob_missing += 1;
        } else {
            $prob_attending += 1;
        }
    }

    return [$prob_attending, $prob_missing];
}
