<?php
include_once './config/database.php';
include_once './model/member.php';

$database = new Database();

$db_conn = $database->getConnection();

$member = new Member($db_conn);

$data = json_decode(file_get_contents("php://input"));

header('content-type: application/json');

if(isset($_GET['api_token'])){
    $auth_level = authorize($_GET['api_token']);
} else {
    http_response_code(403);
    exit();
}

if($auth_level < 2){
    http_response_code(403);
    exit();
}

switch($_SERVER['REQUEST_METHOD'])
{
    case 'POST':
        // INSERT
        if(!empty($data))
        {
            if($member->create($data))
            {
                response(201, "");
            } else {
                response(500, "");
            }
        } else {
            response(400, $data);
        }
        break;
    case 'GET':
        // SELECT
        if(isset($_GET['id'])){
            $id = $_GET['id'];
            $stmt = $member->readSingle($id);
            $num = $stmt->rowCount();

            switch($num) {
            case 0:
                http_response_code(404);
                break;
            case 1:
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                extract($row);
                $member = array(
                    "Member_ID" => $member_id,
                    "Forename"  => $forename,
                    "Surname"   => $surname,
                    "Nicknames" => $nicknames,
                    "Auth_level" => $auth_level,
                    "Instrument" => $instrument
                );
                response_with_data(200, $member);
                break;
            default:
                http_response_code(500);
                break;
            }

        } else {
            $stmt = $member->read();
            $num = $stmt->rowCount();

            if($num > 0) {
                $member_arr = array();
                while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    extract($row);
                    $member_item = array(
                        "Member_ID"        => $member_id,
                        "Forename"  => $forename,
                        "Surname"   => $surname,
                        "Auth_level" => $auth_level,
                        "Instrument" => $instrument
                    );
                    array_push($member_arr, $member_item);
                }

                response_with_data(200, $member_arr);
            } else {
                http_response_code(204);
            }
        }

        
        break;
    case 'PUT':
        if($member->update($data)){
            http_response_code(200);
        } else {
            http_response_code(400);
        }
        break;
    default:
        http_response_code(501);
}
?>