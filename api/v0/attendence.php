<?php

require_once './config/database.php';
require_once './predictionlog.php';
require_once './config/permission-helper.php';

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
        $query = "SELECT * FROM tblEvents 
            LEFT JOIN tblUsergroups 
            ON tblEvents.usergroup_id=tblUsergroups.usergroup_id 
            WHERE event_id=:event_id";

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
                "State" => $state,
                "Date" => $date,
                "Begin" => $begin,
                "Departure" => $departure,
                "Leave_dep" => $leave_dep,
                "Ev_PlusOne" => boolval($plusone),
                "Clothing" => $clothing,
                "Usergroup_ID" => $usergroup_id,
                "Association_ID" => $association_id
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
        $delayed = 0;
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
                    $refusal += intval($row['COUNT(*)']); //count refusal/plusone with 0/0 and 0/1
                    break;
                case 1:
                    if($row['plusone'] == 1)
                        $plusone = intval($row['COUNT(*)']);
                    else
                        $consent = intval($row['COUNT(*)']);
                    break;
                case 2:
                    $maybe += intval($row['COUNT(*)']);
                    break;
                case 3:
                    $delayed += intval($row['COUNT(*)']);
                    break;
            }
        }

        [$prob_attending, $prob_missing, $prob_signout] = predictAttendence($event_id);

        $attendence = array(
            "Event_ID" => $event_id,
            "Consent" => $consent,
            "Refusal" => $refusal,
            "Maybe" => $maybe,
            "Delayed" => $delayed,
            "Missing" => $missing,
            "ProbAttending" => $prob_attending,
            "ProbMissing" => $prob_missing,
            "ProbSignout" => $prob_signout,
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
        // check if the request comes from prediction server
        if (isset($_GET['xgboost'])) {
            $query = "SELECT * FROM tblAuth WHERE token=:token";
            $statement = $db_conn->prepare($query);
            $statement->bindParam(":token", $_GET['api_token']);

            if ($statement->execute()) {
                if ($statement->rowCount() == 1) {
                    // the request comes from the prediction server
                    $query = "SELECT member_id, attendence, evaluation, type, category, date 
                        FROM tblAttendence 
                        LEFT JOIN tblEvents 
                        ON tblAttendence.event_id=tblEvents.event_id 
                        WHERE evaluation IS NOT null";
                    
                    $statement = $db_conn->prepare($query);

                    if ($statement->execute()) {
                        $attendence_arr = array();
                        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
                            extract($row);
                            $attendence_item = array(
                                "member_id" => $member_id,
                                "attendence" => intval($attendence),
                                "evaluation" => intval($evaluation),
                                "type" => $type,
                                "category" => $category,
                                "date" => $date
                            );
                            array_push($attendence_arr, $attendence_item);
                        }
                        echo json_encode($attendence_arr);
                        return;
                    } else {
                        http_response_code(500);
                        return;
                    }
                } else {
                    http_response_code(401);
                    return;
                }
            } else {
                http_response_code(500);
                return;
            }
        }
        if (!isset($_GET['usergroup_id'])) {
            // get attendence for all events for the user
            $query = "SELECT events.event_id, category, state, type, location, address, date, ev_plusone, begin, departure, leave_dep, attendence, events.usergroup_id, association_id, clothing, plusone FROM (SELECT event_id, category, state, t4.member_id, type, location, address, date, plusone as ev_plusone, begin, departure, leave_dep, t2.usergroup_id, clothing FROM tblEvents t 
            LEFT JOIN tblUsergroupAssignments t2 
            ON t.usergroup_id = t2.usergroup_id
            LEFT JOIN tblMembers t4 
            ON t2.member_id = t4.member_id 
            WHERE api_token = :api_token
            AND state < 2) 
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
                        "State"          => $state,
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
            require_once __DIR__ . '/predictionlog.php';
            // get instruments for the members
            $instruments = getInstruments($_GET['usergroup_id']);

            // get attendence for all members of the usergroup
            $query = "SELECT users.usergroup_id, users.member_id, forename, surname, 
                t3.event_id, type, location, date, attendence, evaluation, t4.plusone,
                t3.category
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
                WHERE date >= curdate()
                AND state < 2
                ORDER BY date, begin, t3.event_id, surname, forename";

            $statement = $db_conn->prepare($query);
            $statement->bindValue(':usergroup_id', $_GET['usergroup_id']);
            
            if($statement->execute()){
                $attendence_arr = array();
                if($row = $statement->fetch(PDO::FETCH_ASSOC)){
                    extract($row);
                    $curr_event_id = intval($event_id);
                    $event_arr = array();
                    [$_att, $_miss, $_sign, $_miss_with_attendence] = predictAttendencePerMember($member_id, $event_id);
                    $prediction = 0*$_att + 1*$_miss + 2*$_sign;
                    $att_item = array(
                        "Member_ID" => $member_id,
                        "Fullname" => $forename . " " . $surname,
                        "Attendence" => (is_null($attendence)) ? -1 : intval($attendence),
                        "PlusOne" => (is_null($plusone)) ? 0 : intval($plusone),
                        "Prediction" => $prediction,
                        "Instrument" => $instruments[$member_id],
                        "Credible" => $_miss_with_attendence ? false : true
                    );
                    array_push($event_arr, $att_item);
                    while($row = $statement->fetch(PDO::FETCH_ASSOC)){
                        if($curr_event_id == $row['event_id']){
                            extract($row);
                            [$_att, $_miss, $_sign, $_miss_with_attendence] = predictAttendencePerMember($member_id, $event_id);
                            $prediction = 0*$_att + 1*$_miss + 2*$_sign;
                            $att_item = array(
                                "Member_ID" => $member_id,
                                "Fullname" => $forename . " " . $surname,
                                "Attendence" => (is_null($attendence)) ? -1 : intval($attendence),
                                "PlusOne" => (is_null($plusone)) ? 0 : intval($plusone),
                                "Prediction" => $prediction,
                                "Instrument" => $instruments[$member_id],
                                "Credible" => $_miss_with_attendence ? false : true
                            );
                            array_push($event_arr, $att_item);
                        } else {
                            $ev = array(
                                "Event_ID" => $event_id,
                                "Type" => $type,
                                "Location" => $location,
                                "Date" => $date,
                                "Category" => $category,
                                "Attendences" => $event_arr
                            );
                            array_push($attendence_arr, $ev);
                            extract($row);
                            $curr_event_id = $event_id;
                            $event_arr = array();
                            [$_att, $_miss, $_sign, $_miss_with_attendence] = predictAttendencePerMember($member_id, $event_id);
                            $prediction = 0*$_att + 1*$_miss + 2*$_sign;
                            $att_item = array(
                                "Member_ID" => $member_id,
                                "Fullname" => $forename . " " . $surname,
                                "Attendence" => (is_null($attendence)) ? -1 : intval($attendence),
                                "PlusOne" => (is_null($plusone)) ? 0 : intval($plusone),
                                "Prediction" => $prediction,
                                "Instrument" => $instruments[$member_id],
                                "Credible" => $_miss_with_attendence ? false : true
                            );
                            array_push($event_arr, $att_item);
                        }
                    }

                    $ev = array(
                        "Event_ID" => $event_id,
                        "Type" => $type,
                        "Location" => $location,
                        "Date" => $date,
                        "Category" => $category,
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

    if (!isset($data->PlusOne)) {
        $data->PlusOne = false;
    }

    $query = "SELECT member_id FROM tblMembers WHERE api_token=:api_token";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":api_token", $_GET['api_token']);
    $statement->execute();
    $requesting_member = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$requesting_member) {
        http_response_code(403);
        return;
    }

    $changed_by = $requesting_member['member_id'];

    if (!isset($data->Member_ID)) {
        $data->Member_ID = $changed_by;
    } else {
        // check if the requesting user is allowed to update the attendence of the member
        $query = "SELECT association_id
            FROM tblEvents
            LEFT JOIN tblUsergroups
            ON tblEvents.usergroup_id = tblUsergroups.usergroup_id
            WHERE event_id=:event_id;";
        $statement = $db_conn->prepare($query);
        $statement->bindParam(":event_id", $event_id);
        $statement->execute();
        $association_id = $statement->fetch(PDO::FETCH_ASSOC)['association_id'];

        if (!hasPermission($_GET['api_token'], 6, $association_id)) {
            http_response_code(403);
            return;
        }
    }

    $query = "SELECT attendence FROM tblAttendence WHERE event_id=:event_id AND member_id=:member_id";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":event_id", $event_id);
    $statement->bindParam(":member_id", $data->Member_ID);
    $statement->execute();
    if ($statement->rowCount() == 1) {
        $old_attendence = $statement->fetch(PDO::FETCH_ASSOC)['attendence'];
    } else {
        $old_attendence = -1;
    }

    $query = "INSERT INTO tblAttendence (event_id, member_id, attendence, plusone) VALUES (:event_id, :member_id, :attendence, :plusone) ON DUPLICATE KEY UPDATE attendence=:attendence, plusone=:plusone";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":event_id", $event_id);
    $statement->bindParam(":member_id", $data->Member_ID);
    $statement->bindParam(":attendence", $data->Attendence);
    $statement->bindValue(":plusone", $data->PlusOne ? 1 : 0);

    $query = "INSERT INTO tblAttendenceHistory (member_id, event_id, previous_attendence, new_attendence, changed_at, changed_by) VALUES (:member_id, :event_id, :previous_attendence, :new_attendence, NOW(), :changed_by)";
    $statement_history = $db_conn->prepare($query);
    $statement_history->bindParam(":member_id", $data->Member_ID);
    $statement_history->bindParam(":event_id", $event_id);
    $statement_history->bindParam(":previous_attendence", $old_attendence);
    $statement_history->bindParam(":new_attendence", $data->Attendence);
    $statement_history->bindParam(":changed_by", $changed_by);

    if (!$statement->execute()) {
        http_response_code(500);
        return;
    }

    if ($old_attendence != $data->Attendence) {
        if (!$statement_history->execute()) {
            http_response_code(500);
            return;
        }
    }

    http_response_code(200);
}

function getInstruments($usergroup_id) {
    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "SELECT member_id, instrument FROM tblUsergroups LEFT JOIN tblAssociationAssignments ON tblUsergroups.association_id=tblAssociationAssignments.association_id WHERE usergroup_id = :usergroup_id";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":usergroup_id", $usergroup_id);

    if ($statement->execute()) {
        $instruments = array();
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            extract($row);
            $instruments[$member_id] = is_null($instrument) ? "" : $instrument;
        }

        return $instruments;
    } else {
        http_response_code(500);
    }
}