<?php
include_once './config/database.php';
include_once './model/event.php';
include_once './util/caching.php';

$database = new Database();

$db_conn = $database->getConnection();

$event = new Event($db_conn);

$data = json_decode(file_get_contents("php://input"));

header('content-type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: if-modified-since');

if(isset($_GET['api_token'])){
    $auth_level = authorize($_GET['api_token']);
} else {
    http_response_code(403);
    exit();
}

if($auth_level < 1){
    http_response_code(403);
    exit();
}

switch($_SERVER['REQUEST_METHOD'])
{
    case 'OPTIONS':
        http_response_code(200);
        break;
    case 'GET':
        $id = -1;
        $filter = "all";
        if(isset($_GET['filter'])){
            $filter = $_GET['filter'];
        }
        if(isset($_GET['id'])){
            $id = $_GET['id'];
        }
        $stmt = $event->read($id, $filter, $_GET['api_token']);
        $num = $stmt->rowCount();
        if($num > 0){
            if($id < 0){
                $event_arr = array();
                while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    extract($row);
                    $event_item = array(
                        "Event_ID"      => intval($event_id),
                        "Type"          => $type,
                        "Location"      => $location,
                        "Date"          => $date,
                        "Begin"         => $begin,
                        "Departure"     => $departure,
                        "Leave_dep"     => $leave_dep,
                        "Accepted"      => boolval($accepted),
                        "Usergroup_ID"  => intval($usergroup_id),
                        "Clothing"      => intval($clothing)
                    );
                    array_push($event_arr, $event_item);
                }
                response_with_data(200, $event_arr);
                exit();
            } else {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                extract($row);
                $event = array(
                    "Event_ID"      => intval($event_id),
                    "Type"          => $type,
                    "Location"      => $location,
                    "Date"          => $date,
                    "Begin"         => $begin,
                    "Departure"     => $departure,
                    "Leave_dep"     => $leave_dep,
                    "Accepted"      => boolval($accepted),
                    "Usergroup_ID"  => intval($usergroup_id),
                    "Clothing"      => intval($clothing)
                );
                response_with_data(200, $event);
                exit();
            }
        } else {
            if($id < 0){
                http_response_code(204);
            } else {
                http_response_code(404);
            }
        }
        
        
        break;
    case 'PUT':
        if($event->update($data)){
            http_response_code(200);
        } else {
            http_response_code(400);
        }
        break;
    case 'POST':
        if($event->create($data)){
            http_response_code(201);
        } else {
            http_response_code(400);
        }
        break;
    default:
        http_response_code(501);
        exit();
}
?>