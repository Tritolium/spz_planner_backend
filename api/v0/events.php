<?php

require __DIR__ . '/config/database.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, POST, OPTIONS');
header("Content-Type: application/json; charset=UTF-8");

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
            getEvents($id);
            break;
        case 'PUT':
            updateEvent($id);
            break;
        default:
            http_response_code(405);
            break;
    }
} else {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'OPTIONS':
            http_response_code(200);
            break;
        case 'GET':
            if (isset($_GET['next'])) {
                getNextEvents();
            } else if (isset($_GET['fixed'])) {
                getFixedEvents();
            } else {
                getEvents();
            }
            break;
        case 'POST':
            createEvent();
            break;
        default:
            http_response_code(405);
            break;
    }
}

function getEvents($id = null) {
    $database = new Database();
    $db_conn = $database->getConnection();

    // id is set, get the event with the id if the requesting user is allowed to see it. Else return 204
    if ($id != null) {
        $query = "SELECT * FROM tblEvents 
            LEFT JOIN tblUsergroupAssignments 
            ON tblEvents.usergroup_id=tblUsergroupAssignments.usergroup_id 
            LEFT JOIN tblMembers 
            ON tblUsergroupAssignments.member_id=tblMembers.member_id 
            WHERE tblEvents.event_id=:event_id 
            AND tblMembers.api_token=:token";
        
        $statement = $db_conn->prepare($query);
        $statement->bindParam(":event_id", $id);
        $statement->bindParam(":token", $_GET['api_token']);

        if (!$statement->execute()) {
            http_response_code(500);
            exit();
        }

        if ($statement->rowCount() < 1) {
            http_response_code(204);
            exit();
        }

        $event = array();

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            extract($row);
            $event = array(
                "Event_ID" => intval($event_id),
                "Type" => $type,
                "Location" => $location,
                "Address" => $address,
                "Category" => $category,
                "Date" => $date,
                "Begin" => ($begin == "12:34:56") ? null : $begin,
                "Departure" => ($departure == "12:34:56") ? null : $departure,
                "Leave_Dep" => ($leave_dep == "12:34:56") ? null : $leave_dep,
                "Accepted" => boolval($accepted),
                "PlusOne" => boolval($plusone),
                "Clothing" => intval($clothing),
                "Usergroup_ID" => intval($usergroup_id),
                "Evaluated" => boolval($evaluated),
                "Fixed" => boolval($fixed),
                "Push" => boolval($push)
            );
        }

        response_with_data(200, $event);
    } else {
        // get the scope of the request, defaults to association
        if (isset($_GET['usergroup'])) {
            $scope = 'usergroup';
            $usergroup_id = $_GET['usergroup'];
        } else if (isset($_GET['association'])) {
            $scope = 'association';
        } else {
            $scope = 'all';
        }

        switch ($scope) {
            case 'usergroup':
                $query = "SELECT * FROM tblEvents WHERE usergroup_id = :usergroup_id";
                if (isset($_GET['past'])) {
                    $query .= " AND date < CURDATE()";
                } else if (isset($_GET['current'])) {
                    $query .= " AND date >= CURDATE()";
                }
                $query .= " ORDER BY date ASC";
                $statement = $db_conn->prepare($query);
                $statement->bindParam(":usergroup_id", $usergroup_id);
                break;
            case 'association':
                $query = "SELECT * FROM tblEvents WHERE usergroup_id IN (SELECT usergroup_id FROM tblUsergroups WHERE association_id = :association_id)";
                if (isset($_GET['past'])) {
                    $query .= " AND date < CURDATE()";
                } else if (isset($_GET['current'])) {
                    $query .= " AND date >= CURDATE()";
                }
                $query .= " ORDER BY date ASC";
                $statement = $db_conn->prepare($query);
                $statement->bindParam(":association_id", $_GET['association']);
                break;
            default:
                $query = "SELECT * FROM tblEvents LEFT JOIN tblUsergroups ON tblEvents.usergroup_id=tblUsergroups.usergroup_id WHERE tblEvents.usergroup_id IN (SELECT usergroup_id FROM tblUsergroupAssignments WHERE member_id IN (SELECT member_id FROM tblMembers WHERE api_token = :api_token))";
                if (isset($_GET['past'])) {
                    $query .= " AND date < CURDATE()";
                } else if (isset($_GET['current'])) {
                    $query .= " AND date >= CURDATE()";
                }
                $query .= " ORDER BY date ASC";
                $statement = $db_conn->prepare($query);
                $statement->bindParam(":api_token", $_GET['api_token']);
                break;
        }

        if (!$statement->execute()) {
            http_response_code(500);
            exit();
        }

        if ($statement->rowCount() < 1) {
            http_response_code(204);
            exit();
        }

        $events = array();

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            extract($row);
            $event = array(
                "Event_ID" => intval($event_id),
                "Type" => $type,
                "Location" => $location,
                "Address" => $address,
                "Category" => $category,
                "Date" => $date,
                "Begin" => ($begin == "12:34:56") ? null : $begin,
                "Departure" => ($departure == "12:34:56") ? null : $departure,
                "Leave_Dep" => ($leave_dep == "12:34:56") ? null : $leave_dep,
                "Accepted" => boolval($accepted),
                "PlusOne" => boolval($plusone),
                "Clothing" => intval($clothing),
                "Usergroup_ID" => intval($usergroup_id),
                "Association_ID" => intval($association_id),
                "Evaluated" => boolval($evaluated),
                "Fixed" => boolval($fixed),
                "Push" => boolval($push)
            );
            array_push($events, $event);
        }

        response_with_data(200, $events);
    }
}

function getNextEvents() {
    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "SELECT * FROM tblEvents
        WHERE usergroup_id IN 
            (SELECT usergroup_id FROM tblUsergroupAssignments 
            WHERE member_id IN
                (SELECT member_id FROM tblMembers 
                WHERE api_token = :api_token))
        AND date >= CURDATE()
        AND accepted = 1
        AND category = :category
        AND evaluated = 0
        ORDER BY date ASC";

    $statement = $db_conn->prepare($query);
    $statement->bindParam(":api_token", $_GET['api_token']);
    $statement->bindParam(":category", $_GET['next']);

    if (!$statement->execute()) {
        http_response_code(500);
        return;
    }
    
    if ($statement->rowCount() < 1) {
        http_response_code(204);
        return;
    }

    $events = array();

    $row = $statement->fetch(PDO::FETCH_ASSOC);
    extract($row);
    array_push($events, intval($event_id));
    $head_date = new DateTime($date);

    // if the type contains "Abgesagt", get the next event
    while (strpos($type, 'Abgesagt') !== false) {
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        extract($row);
        array_push($events, intval($event_id));
        $head_date = new DateTime($date);
    }

    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        extract($row);
        $cur_date = new DateTime($date);
        $cur_date->modify('-3 day');
        if ($cur_date <= $head_date) {
            array_push($events, intval($event_id));
            $head_date = new DateTime($date);
        }
    }

    response_with_data(200, $events);
}

function getFixedEvents() {
    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "SELECT * FROM tblEvents
        WHERE usergroup_id IN 
            (SELECT usergroup_id FROM tblUsergroupAssignments 
            WHERE member_id IN
                (SELECT member_id FROM tblMembers 
                WHERE api_token = :api_token))
        AND date >= CURDATE()
        AND accepted = 1
        AND fixed = 1
        AND evaluated = 0
        ORDER BY date ASC";

    $statement = $db_conn->prepare($query);
    $statement->bindParam(":api_token", $_GET['api_token']);
    
    if (!$statement->execute()) {
        http_response_code(500);
        return;
    }

    if ($statement->rowCount() < 1) {
        http_response_code(204);
        return;
    }

    $events = array();

    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        extract($row);
        array_push($events, intval($event_id));
    }

    response_with_data(200, $events);
}

function updateEvent($id) {
    $database = new Database();
    $db_conn = $database->getConnection();

    $data = json_decode(file_get_contents("php://input"));

    $query = "UPDATE tblEvents SET type = :type, location = :location, address = :address, category = :category, date = :date, begin = :begin, departure = :departure, leave_dep = :leave_dep, accepted = :accepted, plusone = :plusone, clothing = :clothing, usergroup_id = :usergroup_id, fixed = :fixed, push = :push WHERE event_id = :event_id";
    $statement = $db_conn->prepare($query);

    $statement->bindParam(":type", $data->Type);
    $statement->bindParam(":location", $data->Location);
    $statement->bindParam(":address", $data->Address);
    $statement->bindParam(":category", $data->Category);
    $statement->bindParam(":date", $data->Date);
    $statement->bindParam(":begin", $data->Begin);
    $statement->bindParam(":departure", $data->Departure);
    $statement->bindParam(":leave_dep", $data->Leave_Dep);
    $statement->bindValue(":accepted", $data->Accepted ? 1 : 0);
    $statement->bindValue(":plusone", $data->PlusOne ? 1 : 0);
    $statement->bindParam(":clothing", $data->Clothing);
    $statement->bindParam(":usergroup_id", $data->Usergroup_ID);
    $statement->bindParam(":event_id", $id);
    $statement->bindValue(":fixed", $data->Fixed ? 1 : 0);
    $statement->bindValue(":push", $data->Push ? 1 : 0);

    if (!$statement->execute()) {
        http_response_code(500);
        exit();
    }

    response_with_data(200, $data);
}

function createEvent() {
    $database = new Database();
    $db_conn = $database->getConnection();

    $data = json_decode(file_get_contents("php://input"));

    $query = "INSERT INTO tblEvents (type, location, address, category, date, begin, departure, leave_dep, accepted, plusone, clothing, usergroup_id, fixed, push) VALUES (:type, :location, :address, :category, :date, :begin, :departure, :leave_dep, :accepted, :plusone, :clothing, :usergroup_id, :fixed, :push); SELECT LAST_INSERT_ID()";
    $statement = $db_conn->prepare($query);

    $statement->bindParam(":type", $data->Type);
    $statement->bindParam(":location", $data->Location);
    $statement->bindParam(":address", $data->Address);
    $statement->bindParam(":category", $data->Category);
    $statement->bindParam(":date", $data->Date);
    $statement->bindParam(":begin", $data->Begin);
    $statement->bindParam(":departure", $data->Departure);
    $statement->bindParam(":leave_dep", $data->Leave_Dep);
    $statement->bindParam(":accepted", $data->Accepted);
    $statement->bindValue(":plusone", $data->PlusOne ? 1 : 0);
    $statement->bindParam(":clothing", $data->Clothing);
    $statement->bindParam(":usergroup_id", $data->Usergroup_ID);
    $statement->bindValue(":fixed", $data->Fixed ? 1 : 0);
    $statement->bindValue(":push", $data->Push ? 1 : 0);

    if (!$statement->execute()) {
        http_response_code(500);
        exit();
    }

    $data->Event_ID = intval($db_conn->lastInsertId());

    // get all members that are absent on the event date and are in the usergroup
    $query = "SELECT member_id FROM tblAbsence WHERE from_date <= :event_date AND until_date >= :event_date AND member_id IN (SELECT member_id FROM tblUsergroupAssignments WHERE usergroup_id = :usergroup_id)";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":event_date", $data->Date);
    $statement->bindParam(":usergroup_id", $data->Usergroup_ID);

    if (!$statement->execute()) {
        http_response_code(500);
        exit();
    }

    $members = array();

    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        extract($row);
        array_push($members, intval($member_id));
    }

    // update the attendence of the absent members
    foreach ($members as $member_id) {
        $query = "INSERT INTO tblAttendence (member_id, event_id, attendence) VALUES (:member_id, :event_id, 0) ON DUPLICATE KEY UPDATE attendence = 0";
        $statement = $db_conn->prepare($query);
        $statement->bindParam(":member_id", $member_id);
        $statement->bindParam(":event_id", $data->Event_ID);

        if (!$statement->execute()) {
            http_response_code(500);
            exit();
        }
    }


    response_with_data(201, $data);
}

?>