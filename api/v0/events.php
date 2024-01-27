<?php

require __DIR__ . '/config/database.php';

header("Content-Type: application/json; charset=UTF-8");

$request = $_SERVER['REQUEST_URI'];
$request = str_replace('/api/v0', '', $request);
$request_exploded = explode('/', $request);

if (isset($request_exploded[2]) && $request_exploded[2] != '') {
    $id = $request_exploded[2];
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            getEvents($id);
            break;
        default:
            http_response_code(404);
            require __DIR__ . '/views/404.php';
            break;
    }
} else {
    getEvents();
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
                "Evaluated" => boolval($evaluated)
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
                "Evaluated" => boolval($evaluated)
            );
            array_push($events, $event);
        }

        response_with_data(200, $events);
    }
}



?>