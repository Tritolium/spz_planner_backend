<?php
include_once './config/database.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, POST, GET, DELETE, OPTIONS');

// checks if authorization is given, no validation
if(!isset($_GET['api_token'])){
    http_response_code(401);
    exit();
}

switch($_SERVER['REQUEST_METHOD']){
case 'OPTIONS':
    http_response_code(200);
    break;
case 'GET':
    header("Content-Type: application/json");
    // if id is given in querry
    if(isset($_GET['id'])){
        // read single absence, api-token for validation
        readSingleAbsence($_GET['api_token'], $_GET['id']);
    } else {
        // checks for given filter in querry. if not set, exit with code 400
        if(!isset($_GET['filter'])){
            http_response_code(400);
            exit();
        }
        // read all absences for given filter. api-token for validation
        if(!readAbsences($_GET['api_token'], $_GET['filter'], isset($_GET['all']))){
            http_response_code(500);
            exit();
        }
        
    }
    break;
case 'POST':
    $data = json_decode(file_get_contents("php://input"));
    if(newAbsence($_GET['api_token'], $data)){
        http_response_code(201);
    } else {
        http_response_code(500);
    }
    exit();
    break;
case 'PUT':
    // Update resource, get request body for data
    $data = json_decode(file_get_contents("php://input"));
    if(updateAbsence($_GET['api_token'], $_GET['id'], $data)){
        http_response_code(200);
    } else {
        http_response_code(500);
    }
    exit();
    break;
case 'DELETE':
    if(!isset($_GET['id'])){
        http_response_code(400);
        exit();
    }
    if(deleteAbsence($_GET['api_token'], $_GET['id'])){
        http_response_code(204);
    } else {
        http_response_code(500);
    }
    break;
default:
    // exit with code 501 - not implemented, if none of the given Methods above is requested
    http_response_code(501);
    exit();
    break;
}

function newAbsence($api_token, $data)
{
    $database = new Database();
    $db_conn = $database->getConnection();

    if(isset($data->Member_ID)){
        $member_id = $data->Member_ID;
    } else {
        /**
         * member_id not given, so its own absence. Get own member_id via api_token
         */
        $query = "SELECT member_id FROM tblMembers WHERE api_token=:api_token";
        $statement = $db_conn->prepare($query);
        $statement->bindParam(":api_token", $api_token);
        if(!$statement->execute() || $statement->rowCount() < 1){
            return false;
        }
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        $member_id = $row['member_id'];
    }
    
    $query = "INSERT INTO tblAbsence (member_id, from_date, until_date, info) VALUES (:member_id, :from_date, :until_date, :info)";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":member_id", $member_id);
    $statement->bindParam(":from_date", $data->From);
    $statement->bindParam(":until_date", $data->Until);
    $statement->bindParam(":info", $data->Info);
    if(!$statement->execute()){
        return false;
    }

    // check for events in this period
    $query = "SELECT event_id FROM tblEvents WHERE date >= :from_date AND date <= :until_date";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":from_date", $data->From);
    $statement->bindParam(":until_date", $data->Until);
    $statement->execute();

    $events = array();

    while($row = $statement->fetch(PDO::FETCH_ASSOC)){
        array_push($events, $row['event_id']);
    }

    for($i = 0; $i < count($events); $i++){
        updateSingleAttendence($member_id, $events[$i], 0);
    }

    return true;
}

function readSingleAbsence($api_token, $id)
{
    if(!authorizeAlterAbsence($api_token, $id)){
        http_response_code(401);
        exit();
    }
    header("Content-Type: application/json");

    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "SELECT absence_id, tblMembers.member_id, from_date, until_date, forename, surname, info FROM tblAbsence LEFT JOIN tblMembers ON tblAbsence.member_id=tblMembers.member_id WHERE absence_id=:id";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":id", $id);

    if(!$statement->execute()){
        http_response_code(500);
        exit();
    }
    if($statement->rowCount() < 1){
        http_response_code(204);
        exit();
    }
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    extract($row);
    $absence = array(
        "Absence_ID"    => intval($absence_id),
        "Member_ID"     => intval($member_id),
        "From"          => $from_date,
        "Until"         => $until_date,
        "Info"          => $info,
        "Fullname"      => $forename . " " . $surname
    );
    response_with_data(200, $absence);
}

function readAbsences($api_token, $filter, $select_from_all){
    if($select_from_all){
        return readAllAbsences($api_token, $filter);
    } {
        return readOwnAbsences($api_token, $filter);
    }
}

function readOwnAbsences($api_token, $filter)
{
    $database = new Database();
    $db_conn = $database->getConnection();
    
    // GET meber_id, auth_level
    $query = "SELECT member_id, auth_level FROM tblMembers WHERE api_token=:api_token";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":api_token", $api_token);
    if(!$statement->execute()){
        http_response_code(401);
        exit();
    }
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    extract($row);

    switch($filter){
    case 'all':
        $query = "SELECT * FROM tblAbsence ORDER BY from_date, until_date";
        $statement = $db_conn->prepare($query);
        break;
    case 'current':
        $query = "SELECT * FROM tblAbsence WHERE (from_date >= :today OR until_date >= :today) AND member_id=:member_id ORDER BY from_date, until_date";
        $statement = $db_conn->prepare($query);
        $statement->bindValue(":today", date("Y-m-d"));
        $statement->bindValue(":member_id", $member_id);
        break;
    case 'past':
        $query = "SELECT * FROM tblAbsence WHERE (from_date < :today AND  until_date < :today) AND member_id=:member_id ORDER BY from_date, until_date";
        $statement = $db_conn->prepare($query);
        $statement->bindValue(":today", date("Y-m-d"));
        $statement->bindValue(":member_id", $member_id);
        break;
    }
    if(!$statement->execute()){
        return false;
    }

    if($statement->rowCount() < 1){
        http_response_code(204);
        return true;
    }

    $absence_array = array();
    while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        extract($row);
        $absence_item = array(
            "Absence_ID"    => intval($absence_id),
            "Member_ID"     => intval($member_id),
            "From"          => $from_date,
            "Until"         => $until_date,
            "Info"          => $info
        );
        array_push($absence_array, $absence_item);
    }
    response_with_data(200, $absence_array);
    return true;
}

function readAllAbsences($api_token, $filter)
{
    $database = new Database();
    $db_conn = $database->getConnection();

    switch($filter){
    case 'all':
        $query = "SELECT * FROM tblAbsence LEFT JOIN tblMembers ON tblAbsence.member_id=tblMembers.member_id ORDER BY from_date, surname";
        $statement = $db_conn->prepare($query);
        break;
    case 'current':
        $query = "SELECT * FROM tblAbsence LEFT JOIN tblMembers ON tblAbsence.member_id=tblMembers.member_id WHERE (from_date >= :today OR until_date >= :today) ORDER BY from_date, surname";
        $statement = $db_conn->prepare($query);
        $statement->bindValue(":today", date("Y-m-d"));
        break;
    case 'past':
        $query = "SELECT * FROM tblAbsence LEFT JOIN tblMembers ON tblAbsence.member_id=tblMembers.member_id WHERE (from_date < :today AND  until_date < :today) ORDER BY from_date, surname";
        $statement = $db_conn->prepare($query);
        $statement->bindValue(":today", date("Y-m-d"));
        break;
    }
    if(!$statement->execute()){
        return false;
    }

    if($statement->rowCount() < 1){
        http_response_code(204);
        return true;
    }

    $absence_array = array();
    while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        extract($row);
        $absence_item = array(
            "Absence_ID"    => intval($absence_id),
            "Fullname"      => $forename . " " . $surname,
            "From"          => $from_date,
            "Until"         => $until_date,
            "Info"          => $info
        );
        array_push($absence_array, $absence_item);
    }
    response_with_data(200, $absence_array);
    return true;
}

function updateAbsence($api_token, $absence_id, $data)
{
    echo "update " . $absence_id;
    if(!authorizeAlterAbsence($api_token, $absence_id)){
        http_response_code(401);
        exit();
    }
    $database = new Database();
    $db_conn = $database->getConnection();
    $query = "UPDATE tblAbsence SET from_date=:from_date, until_date=:until_date WHERE absence_id=:id";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":id", $absence_id);
    $statement->bindParam(":from_date", $data->From);
    $statement->bindParam(":until_date", $data->Until);
    if(!$statement->execute()){
        return false;
    }

    // check for events in this period
    $query = "SELECT event_id FROM tblEvents WHERE date >= :from_date AND date <= :until_date";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":from_date", $data->From);
    $statement->bindParam(":until_date", $data->Until);
    $statement->execute();

    $events = array();

    while($row = $statement->fetch(PDO::FETCH_ASSOC)){
        array_push($events, $row['event_id']);
    }

    for($i = 0; $i < count($events); $i++){
        updateSingleAttendence($data->Member_ID, $events[$i], 0);
    }

    return true;
}

function deleteAbsence($api_token, $absence_id)
{
    if(!authorizeAlterAbsence($api_token, $absence_id)){
        http_response_code(401);
        exit();
    }
    $database = new Database();
    $conn = $database->getConnection();
    $query = "DELETE FROM tblAbsence WHERE absence_id = :absence_id";
    $statement = $conn->prepare($query);
    $statement->bindParam(":absence_id", $absence_id);

    if(!$statement->execute()){
        return false;
    }

    return true;
}

function authorizeAlterAbsence($api_token, $id)
{
    //get auth_level and member_id
    $database = new Database();
    $db_conn = $database->getConnection();
    $query = "SELECT auth_level, member_id FROM tblMembers WHERE api_token = :token";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":token", $api_token);

    if(!$statement->execute()){
        return false;
    }
    if($statement->rowCount() < 1){
        return false;
    }

    $row = $statement->fetch(PDO::FETCH_ASSOC);
    extract($row);
    // check for admin status
    if($auth_level > 2){
        return true;
    }

    $query = "SELECT * FROM tblAbsence WHERE absence_id=:id AND member_id=:member_id";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":id", $id);
    $statement->bindParam(":member_id", $member_id);

    if(!$statement->execute()){
       return false;
    }
    if($statement->rowCount() == 1){
        return true;
    }

    //false
    return false;
}

function updateSingleAttendence($member_id, $event_id, $attendence)
{
    $database = new Database();
    $db_conn = $database->getConnection();
    $query = "SELECT * FROM tblAttendence WHERE member_id=:member_id AND event_id=:event_id";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":member_id", $member_id);
    $statement->bindParam(":event_id", $event_id);
    $statement->execute();
    if($statement->rowCount() < 1){
        $query = "INSERT INTO tblAttendence (attendence, member_id, event_id) VALUES (:attendence, :member_id, :event_id)";
        $statement = $db_conn->prepare($query);
        $statement->bindParam(":attendence", $attendence);
        $statement->bindParam(":member_id", $member_id);
        $statement->bindParam(":event_id", $event_id);
    } else {
        $query = "UPDATE tblAttendence SET attendence=:attendence WHERE member_id=:member_id AND event_id=:event_id";
        $statement = $db_conn->prepare($query);
        $statement->bindParam(":attendence", $attendence);
        $statement->bindParam(":member_id", $member_id);
        $statement->bindParam(":event_id", $event_id);
    }
    
    $statement->execute();
}