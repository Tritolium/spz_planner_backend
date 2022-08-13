<?php
include_once './config/database.php';

// checks if authorization is given, no validation
if(!isset($_GET['api_token'])){
    http_response_code(401);
    exit();
}

switch($_SERVER['REQUEST_METHOD']){
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
case 'PUT':
    // Update resource, get request body for data
    $data = json_decode(file_get_contents("php://input"));
    if(updateAbsence($_GET['api_token'], $data)){
        http_response_code(200);
    } else {
        http_response_code(500);
    }
    exit();
default:
    // exit with code 501 - not implemented, if none of the given Methods above is requested
    http_response_code(501);
    exit();
}

function newAbsence($api_token, $data)
{
    $database = new Database();
    $db_conn = $database->getConnection();

    // get member_id from api_token
    $query = "SELECT member_id FROM tblMembers WHERE api_token=:api_token";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":api_token", $api_token);
    if(!$statement->execute() || $statement->rowCount() < 1){
        return false;
    }
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    $member_id = $row['member_id'];

    $query = "INSERT INTO tblAbsence (member_id, from_date, until_date, info) VALUES (:member_id, :from_date, :until_date, :info)";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":member_id", $member_id);
    $statement->bindParam(":from_date", $data->From);
    $statement->bindParam(":until_date", $data->Until);
    $statement->bindParam(":info", $data->Info);
    if($statement->execute()){
        return true;
    }
    return false;
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
        http_resonse_code(204);
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
    
    if($auth_level < 3){
        http_response_code(401);
        return true;
    }

    switch($filter){
    case 'all':
        $query = "SELECT * FROM tblAbsence";
        $statement = $db_conn->prepare($query);
        break;
    case 'current':
        $query = "SELECT * FROM tblAbsence WHERE (from_date >= :today OR until_date >= :today) AND member_id=:member_id";
        $statement = $db_conn->prepare($query);
        $statement->bindValue(":today", date("Y-m-d"));
        $statement->bindValue(":member_id", $member_id);
        break;
    case 'past':
        $query = "SELECT * FROM tblAbsence WHERE (from_date < :today AND  until_date < :today) AND member_id=:member_id";
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
        $query = "SELECT * FROM tblAbsence";
        $statement = $db_conn->prepare($query);
        break;
    case 'current':
        $query = "SELECT * FROM tblAbsence WHERE (from_date >= :today OR until_date >= :today)";
        $statement = $db_conn->prepare($query);
        $statement->bindValue(":today", date("Y-m-d"));
        break;
    case 'past':
        $query = "SELECT * FROM tblAbsence WHERE (from_date < :today AND  until_date < :today)";
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

function updateAbsence($api_token, $data)
{
    if(!authorizeAlterAbsence($api_token, $data->Absence_ID)){
        http_response_code(401);
        exit();
    }
    $database = new Database();
    $db_conn = $database->getConnection();
    $query = "UPDATE tblAbsence SET from_date=:from_date, until_date=:until_date WHERE absence_id=:id";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":id", $data->Absence_ID);
    $statement->bindParam(":from_date", $data->From);
    $statement->bindParam(":until_date", $data->Until);
    if($statement->execute()){
        return true;
    } else {
        return false;
    }
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