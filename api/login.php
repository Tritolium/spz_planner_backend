<?php
include_once './config/database.php';

header('Access-Control-Allow-Origin: *');

$data = json_decode(file_get_contents("php://input"));

$database = new Database();
$db_conn = $database->getConnection();

if(!isset($_GET['mode'])){
    http_response_code(400);
    echo('<h>No Mode</h>');
    exit();
}

switch($_GET['mode']){
case 'login':
    if($_SERVER['REQUEST_METHOD'] !== 'POST'){
        http_response_code(405);
        exit();
    }

    if(!isset($data->Name)){
        http_response_code(400);
        echo('<h>No Name</h>');
        exit();
    }

    $name = '%' . $data->Name . '%';

    $statement = $db_conn->prepare('SELECT member_id, forename, surname, auth_level, api_token, theme FROM tblMembers WHERE Nicknames LIKE :name');
    $statement->bindParam(":name", $name);

    if($statement->execute()){
        if($statement->rowCount() == 1){
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } else {
            $statement = $db_conn->prepare('SELECT member_id, forename, surname, auth_level, api_token, pwhash, theme FROM tblMembers WHERE CONCAT(forename, \' \', surname, \' \', nicknames) LIKE :full_name');
            $statement->bindParam(":full_name", $name);
            
            if($statement->execute()){
                if($statement->rowCount() == 1){
                    $row = $statement->fetch(PDO::FETCH_ASSOC);
                } else {
                    http_response_code(406);
                    exit();
                }
            } else {
                http_response_code(500);
                exit();
            }
        }
    } else {
        http_response_code(500);
        exit();
    }

    if($row !== NULL){
        extract($row);

        $err_code = 0;

        if($pwhash == "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855") {
            $err_code = 4;
        }

        $permissions = array();

        $query = "SELECT association_id, permission_id 
            FROM `tblUserRoles` 
            LEFT JOIN tblRolePermissions 
            ON tblUserRoles.role_id=tblRolePermissions.role_id 
            WHERE member_id=:member_id 
            ORDER BY association_id";

        $statement = $db_conn->prepare($query);
        $statement->bindParam(":member_id", $member_id);

        if(!$statement->execute()){
            http_response_code(500);
            exit();
        }

        while($row = $statement->fetch(PDO::FETCH_ASSOC)){
            extract($row);

            if($permission_id !== NULL)
                $permissions[$association_id][] = $permission_id;

        }

        // check the password
        if($data->PWHash == $pwhash){
            $response_body = array(
                "Forename" => $forename,
                "Surname" => $surname,
                "API_token" => $api_token,
                "Auth_level" => $auth_level,
                "Theme" => intval($theme),
                "Err" => $err_code,
                "Permissions" => $permissions
            );

            response_with_data(200, $response_body);
            lastLogin($api_token, $data, 0);
        } else if ($data->PWHash !== $pwhash) { // password not matching
            http_response_code(403);
            echo('<h>Falscher Nutzer oder falsches Passwort</h>');
        } else {
            // code not reachable at the moment.
            http_response_code(403);
        }
    } else {
        http_response_code(404);
    }

    exit();
case 'update':
    if($_SERVER['REQUEST_METHOD'] === 'GET'){
        $data = json_decode($_GET['body']);
    }
    if(!isset($data->Token)){
        http_response_code(400);
        echo('<h>No Token</h>');
        exit();
    }
    $api_token = $data->Token;

    lastLogin($api_token, $data, 1);

    $statement = $db_conn->prepare('SELECT member_id, forename, surname, auth_level, theme, pwhash FROM tblMembers WHERE api_token = :token');
    $statement->bindParam(":token", $api_token);

    $err_code = 0;

    if($statement->execute()){
        if($statement->rowCount() == 1){
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            extract($row);

            if ($pwhash == "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855") {
                $err_code = 4;
            }

            $permissions = array();

            $query = "SELECT association_id, permission_id 
                FROM `tblUserRoles` 
                LEFT JOIN tblRolePermissions 
                ON tblUserRoles.role_id=tblRolePermissions.role_id 
                WHERE member_id=:member_id 
                ORDER BY association_id";

            $statement = $db_conn->prepare($query);
            $statement->bindParam(":member_id", $member_id);

            if(!$statement->execute()){
                http_response_code(500);
                exit();
            }

            while($row = $statement->fetch(PDO::FETCH_ASSOC)){
                extract($row);

                if($permission_id !== NULL)
                    $permissions[$association_id][] = $permission_id;

            }

            $response_body = array(
                "Forename" => $forename,
                "Surname" => $surname,
                "Auth_level" => $auth_level,
                "Theme" => intval($theme),
                "Err" => $err_code,
                "Permissions" => $permissions
            );
            response_with_data(200, $response_body);
        } else {
            http_response_code(204);
        }
    }
    exit();
}

function lastLogin($api_token, $data, $update)
{
    // ignore the login if the referrer is alpha/index.html
    if ($_SERVER['HTTP_REFERER'] === "https://spzroenkhausen.bplaced.net/alpha/index.html") {
        return;
    }

    $database = new Database();
    $db_conn = $database->getConnection();

    $displayMode = $data->DisplayMode;
    $version = $data->Version;
    $engine = $data->Engine;
    $device = $data->Device;
    $dimension = NULL;
    if(isset($data->Dimension)){
        $dimension = $data->Dimension;
    }
    $device_uuid = NULL;
    if(isset($data->DeviceUUID)){
        $device_uuid = $data->DeviceUUID;
    }
    $notifications = NULL;
    if(isset($data->Notification)){
        $notifications = $data->Notification;
    }
    $darkmode = NULL;
    $lightmode = NULL;
    $forced_colors = NULL;
    if(isset($data->Preferences)){
        $darkmode = $data->Preferences->darkmode ?? NULL;
        $lightmode = $data->Preferences->lightmode;
        $forced_colors = $data->Preferences->forcedcolors;
    }

    $query = "UPDATE tblMembers SET last_login=CURRENT_TIMESTAMP, last_display=:displaymode, last_version=:version, u_agent=:u_agent WHERE api_token=:api_token";
    $statement = $db_conn->prepare($query);
    
    $statement->bindParam(":displaymode", $displayMode);
    $statement->bindParam(":version", $version);
    $statement->bindValue(":u_agent", htmlspecialchars($engine . " " . $device, ENT_QUOTES));
    $statement->bindParam(":api_token", $api_token);

    $statement->execute();

    $query = "SELECT member_id FROM tblMembers WHERE api_token=:api_token";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":api_token", $api_token);
    $statement->execute();
    $row = $statement->fetch(PDO::FETCH_ASSOC);

    // for some reason, this code can be reached without a valid token. Nullify the member_id in this case.
    $row['member_id'] = $row['member_id'] ?? NULL;

    $member_id = $row['member_id'];

    // if the member_id is NULL, the token is invalid. Do not log the login, send a mail to the admin.
    if($member_id === NULL){
        // get mail address from .env
        $env = parse_ini_file("./.env");
        $recipient = $env['ADMIN_MAIL'];
        $subject = "Invalid API Token";
        
        $token = "<p>API Token: " . $api_token . "</p>";
        $display = "<p>Display Mode: " . $displayMode . "</p>";
        $ver = "<p>Version: " . $version . "</p>";
        $ua = "<p>User Agent: " . htmlspecialchars($engine . " " . $device, ENT_QUOTES) . "</p>";
        $dim = "<p>Dimension: " . $dimension . "</p>";
        $name = isset($data->Name) ? "<p>Name: " . $data->Name . "</p>" : "<p>Name: not set</p>";
        $uuid = isset($data->DeviceID) ? "<p>Device ID: " . $device_uuid . "</p>" : "<p>Device ID: not set</p>";

        $message = "<html><body>" . $token . $display . $ver . $ua . $dim . $name . $uuid . "</body></html>";

        $headers = "From: <" . $env['ADMIN_MAIL'] . ">";

        mail($recipient, $subject, $message, $headers);

        return;
    }

    // ignore the login if the referrer is alpha/index.html
    if ($_SERVER['HTTP_REFERER'] === "https://spzroenkhausen.bplaced.net/alpha/index.html") {
        return;
    }

    // get device id for device uuid
    $query = "SELECT device_id FROM tblDevices WHERE device_uuid=:device_uuid";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":device_uuid", $device_uuid);
    $statement->execute();

    if($statement->rowCount() == 0 && $device_uuid !== NULL){
        $query = "INSERT INTO tblDevices (device_uuid, darkmode, lightmode, forced_colors, notifications) VALUES (:device_uuid, :darkmode, :lightmode, :forced_colors, :notifications)";
        $statement = $db_conn->prepare($query);
        $statement->bindParam(":device_uuid", $device_uuid);
        $statement->bindValue(":darkmode", $darkmode ? 1 : 0);
        $statement->bindValue(":lightmode", $lightmode ? 1 : 0);
        $statement->bindValue(":forced_colors", $forced_colors ? 1 : 0);
        $statement->bindParam(":notifications", $notifications);
        $statement->execute();

        $device_id = $db_conn->lastInsertId();
    } else if ($statement->rowCount() == 1) {
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        $device_id = $row['device_id'];

        $query = "UPDATE tblDevices SET darkmode=:darkmode, lightmode=:lightmode, forced_colors=:forced_colors, notifications=:notifications WHERE device_id=:device_id";
        $statement = $db_conn->prepare($query);
        $statement->bindValue(":darkmode", $darkmode ? 1 : 0);
        $statement->bindValue(":lightmode", $lightmode ? 1 : 0);
        $statement->bindValue(":forced_colors", $forced_colors ? 1 : 0);
        $statement->bindParam(":notifications", $notifications);
        $statement->bindParam(":device_id", $device_id);
        $statement->execute();
    } else {
        $device_id = NULL;
    }

    $query = "INSERT INTO tblLogin (member_id, login_update, version, display, dimension, u_agent, device_id) VALUES (:member_id, :login_update, :version, :display, :dimension, :u_agent, :device_id)";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":member_id", $member_id);
    $statement->bindParam(":login_update", $update);
    $statement->bindParam(":version", $version);
    $statement->bindParam(":display", $displayMode);
    $statement->bindParam(":dimension", $dimension);
    $statement->bindValue(":u_agent", htmlspecialchars($engine . " " . $device, ENT_QUOTES));
    $statement->bindParam(":device_id", $device_id);
    $statement->execute();
}

?>