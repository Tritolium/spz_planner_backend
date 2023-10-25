<?php
include_once './config/database.php';

header('Access-Control-Allow-Origin: *');

switch($_SERVER['REQUEST_METHOD']){
    case 'OPTIONS':
        http_response_code(200);
        break;
    case 'DELETE':
        if(deleteTestLoginLog()){
            http_response_code(204);
        } else {
            http_response_code(500);
        }
        break;
}

function deleteTestLoginLog()
{
    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "DELETE from tblLogin WHERE member_id = 3 AND login_update = 0 AND timestamp > date_sub(curdate(), INTERVAL 15 MINUTE)";

    $statement = $db_conn->prepare($query);

    if($statement->execute()){
        return true;
    }

    return false;
}
?>