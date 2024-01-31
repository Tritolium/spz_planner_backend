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
}

function getAttendence($event_id) {
    $database = new Database();
    $db_conn = $database->getConnection();

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

    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        switch ($row['attendence']) {
            case null:
                $missing = intval($row['COUNT(*)']);
                break;
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

    $attendence = array(
        "Event_ID" => $event_id,
        "Consent" => $consent,
        "Refusal" => $refusal,
        "Maybe" => $maybe,
        "Missing" => $missing,
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

    $event['Attendence'] = intval($attendence_user['attendence']);
    $event['PlusOne'] = intval($attendence_user['plusone']);

    response_with_data(200, array(
        'Event' => $event,
        'Attendence' => $attendence
    ));
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
