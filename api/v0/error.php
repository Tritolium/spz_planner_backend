<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');

switch ($_SERVER['REQUEST_METHOD']) {
    case 'OPTIONS':
        http_response_code(200);
        break;
    case 'POST':
        logError();
        break;
    default:
        http_response_code(404);
        break;
}

function logError() {
    $data = json_decode(file_get_contents("php://input"));
    $error_msg = $data->Error_Msg;
    $engine = $data->Engine;
    $device = $data->Device;
    $dimension = $data->Dimension;
    $displaymode = $data->DisplayMode;
    $version = $data->Version;
    $token = $data->Token;
    
    require_once './config/database.php';

    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "SELECT member_id FROM tblMembers WHERE api_token=:token";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":token", $token);
    $statement->execute();
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    $member_id = $row['member_id'];

    $query = "INSERT INTO tblErrorLog (error, engine, device, dimension, displaymode, version, member_id)
        VALUES (:error, :engine, :device, :dimension, :displaymode, :version, :member_id)";

    $statement = $db_conn->prepare($query);
    $statement->bindParam(":error", $error_msg);
    $statement->bindParam(":engine", $engine);
    $statement->bindParam(":device", $device);
    $statement->bindParam(":dimension", $dimension);
    $statement->bindParam(":displaymode", $displaymode);
    $statement->bindParam(":version", $version);
    $statement->bindValue(":member_id", $member_id);

    if ($statement->execute()) {
        $data->Error_ID = $db_conn->lastInsertId();
        response_with_data(201, $data);
    } else {
        http_response_code(503);
    }
}

?>