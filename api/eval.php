<?php
include_once './config/database.php';

if(!isset($_GET['api_token'])){
    http_response_code(401);
    exit();
}

header("Content-Type: application/json");
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

$data = json_decode(file_get_contents("php://input"));

switch($_SERVER['REQUEST_METHOD']){
case 'OPTIONS':
    http_response_code(200);
    break;
case 'GET':
    if(isset($_GET['version'])){
        getVersionEval();
        exit();
    }
    if(isset($_GET['events']) && isset($_GET['usergroup'])){
        getEventEvalByUsergroup($_GET['usergroup']);
    } else if (isset($_GET['events']) && isset($_GET['id']) && isset($_GET['u_id'])){
        getEventEvalById($_GET['id'], $_GET['u_id']);
    }   
    exit();
case 'POST':
    if(!isset($_GET['event_id'])){
        http_response_code(405);
    } else {
        setEventEval($_GET['event_id'], $data);
    }
    break;
default:
    http_response_code(501);
    exit();
}

function getEventEvalByUsergroup($usergroup_id)
{
    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "SELECT event_id, type, location, date FROM tblEvents WHERE date >= curdate() AND accepted=1 AND usergroup_id=:usergroup_id ORDER BY date";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":usergroup_id", $usergroup_id);
    $statement->execute();

    $events = array();
    $eval = array();

    while($row = $statement->fetch(PDO::FETCH_ASSOC)){
        extract($row);
        array_push($events, array(intval($event_id), $type, $location, $date));
    }

    foreach($events as $event_item){
        $consent = getEventConsentCount($event_item[0], $usergroup_id);
        $refusal = getEventRefusalCount($event_item[0], $usergroup_id);
        $maybe = getEventMaybeCount($event_item[0], $usergroup_id);
        $missing = getEventMissingCount($event_item[0], $usergroup_id);

        $instruments = getEventInstruments($event_item[0]);

        $event = array(
            "Event_ID" => intval($event_item[0]),
            "Type"     => $event_item[1],
            "Location" => $event_item[2],
            "Date"     => $event_item[3],
            "Consent"  => $consent,
            "Refusal"  => $refusal,
            "Maybe"    => $maybe,
            "Missing"  => $missing,
            "Instruments" => $instruments
        );

        array_push($eval, $event);
    }

    response_with_data(200, $eval);
}

function getEventEvalById($event_id, $usergroup_id)
{
    $consent = getEventConsentCount($event_id, $usergroup_id);
    $refusal = getEventRefusalCount($event_id, $usergroup_id);
    $maybe = getEventMaybeCount($event_id, $usergroup_id);
    $missing = getEventMissingCount($event_id, $usergroup_id);

    $event = array(
        "Event_ID" => intval($event_id),
        "Consent"  => $consent,
        "Refusal"  => $refusal,
        "Maybe"    => $maybe,
        "Missing"  => $missing
    );

    response_with_data(200, $event);
}

function getEventConsentCount($event_id, $usergroup_id)
{
    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "SELECT COUNT(attendence) AS consent 
    FROM (SELECT tm.member_id FROM tblMembers tm 
    left join tblUsergroupAssignments tua 
    on tm.member_id = tua.member_id 
    where tua.usergroup_id = :usergroup_id) as users 
    LEFT JOIN 
    (SELECT * FROM tblAttendence WHERE event_id=:event_id) AS a 
    ON users.member_id=a.member_id 
    WHERE attendence=1";

    $statement = $db_conn->prepare($query);
    $statement->bindParam(":usergroup_id", $usergroup_id);
    $statement->bindParam(":event_id", $event_id);
    $statement->execute();
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    return $row["consent"];
}

function getEventRefusalCount($event_id, $usergroup_id)
{
    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "SELECT COUNT(attendence) AS refusal 
    FROM (SELECT tm.member_id FROM tblMembers tm 
    left join tblUsergroupAssignments tua 
    on tm.member_id = tua.member_id 
    where tua.usergroup_id = :usergroup_id) as users 
    LEFT JOIN 
    (SELECT * FROM tblAttendence WHERE event_id=:event_id) AS a 
    ON users.member_id=a.member_id 
    WHERE attendence=0";

    $statement = $db_conn->prepare($query);
    $statement->bindParam(":usergroup_id", $usergroup_id);
    $statement->bindParam(":event_id", $event_id);
    $statement->execute();
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    return $row["refusal"];
}

function getEventMaybeCount($event_id, $usergroup_id)
{
    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "SELECT COUNT(attendence) AS maybe 
    FROM (SELECT tm.member_id FROM tblMembers tm 
    left join tblUsergroupAssignments tua 
    on tm.member_id = tua.member_id 
    where tua.usergroup_id = :usergroup_id) as users
    LEFT JOIN
    (SELECT * FROM tblAttendence WHERE event_id=:event_id) AS a
    ON users.member_id=a.member_id 
    WHERE attendence=2";

    $statement = $db_conn->prepare($query);
    $statement->bindParam(":usergroup_id", $usergroup_id);
    $statement->bindParam(":event_id", $event_id);
    $statement->execute();
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    return $row["maybe"];
}

function getEventmissingCount($event_id, $usergroup_id)
{
    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "SELECT SUM(CASE WHEN attendence IS NULL THEN 1 ELSE 0 END) AS missing 
    FROM (SELECT tm.member_id FROM tblMembers tm 
    left join tblUsergroupAssignments tua 
    on tm.member_id = tua.member_id 
    where tua.usergroup_id = :usergroup_id) as users 
    LEFT JOIN (SELECT * FROM tblAttendence WHERE event_id=:event_id) AS a
    ON users.member_id=a.member_id";

    $statement = $db_conn->prepare($query);
    $statement->bindParam(":usergroup_id", $usergroup_id);
    $statement->bindParam(":event_id", $event_id);
    $statement->execute();
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    $missing = intval($row["missing"]);

    $query = "SELECT COUNT(attendence) AS missing
    FROM (SELECT tm.member_id FROM tblMembers tm 
    left join tblUsergroupAssignments tua 
    on tm.member_id = tua.member_id 
    where tua.usergroup_id = :usergroup_id) as users 
    LEFT JOIN 
    (SELECT * FROM tblAttendence WHERE event_id=:event_id) AS a 
    ON users.member_id=a.member_id WHERE attendence=-1";

    $statement = $db_conn->prepare($query);
    $statement->bindParam(":usergroup_id", $usergroup_id);
    $statement->bindParam(":event_id", $event_id);
    $statement->execute();
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    $missing = $missing + intval($row["missing"]);
    return $missing;
}

function getEventInstruments($event_id)
{
    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "SELECT COUNT(instrument) AS instrument FROM tblAttendence LEFT JOIN tblMembers ON tblAttendence.member_id=tblMembers.member_id WHERE event_id = :event_id AND attendence = 1 AND instrument=:instrument";
    
    //major
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":event_id", $event_id);
    $statement->bindValue(":instrument", "major");
    $statement->execute();
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    $major = $row['instrument'];

    //sopran
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":event_id", $event_id);
    $statement->bindValue(":instrument", "sopran");
    $statement->execute();
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    $sopran = $row['instrument'];

    //diskant
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":event_id", $event_id);
    $statement->bindValue(":instrument", "diskant");
    $statement->execute();
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    $diskant = $row['instrument'];

    //alt
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":event_id", $event_id);
    $statement->bindValue(":instrument", "alt");
    $statement->execute();
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    $alt = $row['instrument'];

    //tenor
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":event_id", $event_id);
    $statement->bindValue(":instrument", "tenor");
    $statement->execute();
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    $tenor = $row['instrument'];

    //trommel
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":event_id", $event_id);
    $statement->bindValue(":instrument", "trommel");
    $statement->execute();
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    $trommel = $row['instrument'];

    //becken
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":event_id", $event_id);
    $statement->bindValue(":instrument", "becken");
    $statement->execute();
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    $becken = $row['instrument'];

    //pauke
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":event_id", $event_id);
    $statement->bindValue(":instrument", "pauke");
    $statement->execute();
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    $pauke = $row['instrument'];

    //lyra
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":event_id", $event_id);
    $statement->bindValue(":instrument", "lyra");
    $statement->execute();
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    $lyra = $row['instrument'];

    return array(
        "Major"     => $major,
        "Sopran"    => $sopran,
        "Diskant"   => $diskant,
        "Alt"       => $alt,
        "Tenor"     => $tenor,
        "Trommel"   => $trommel,
        "Becken"    => $becken,
        "Pauke"     => $pauke,
        "Lyra"      => $lyra
    );
}

function getVersionEval()
{
    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "SELECT last_version, COUNT(*) AS count FROM tblMembers GROUP BY last_version";
    $statement = $db_conn->prepare($query);
    if(!$statement->execute()){
        http_response_code(500);
    }

    $versions = array();

    while($row = $statement->fetch(PDO::FETCH_ASSOC)){
        extract($row);
        $versions[$last_version] = $count;
    }

    response_with_data(200, $versions);
}

function setEventEval($event_id, $evaluation)
{
    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "INSERT INTO tblAttendence (member_id, event_id, evaluation) VALUES (:member_id, :event_id, :evaluation) ON DUPLICATE KEY UPDATE evaluation=:evaluation";
    
    foreach($evaluation as $member_id => $eval){
        $statement = $db_conn->prepare($query);
        $statement->bindParam(":member_id", $member_id);
        $statement->bindParam(":event_id", $event_id);
        $statement->bindParam(":evaluation", $eval);
        
        if(!$statement->execute()){
            http_response_code(500);
            exit();
        }
    }

    $query = "UPDATE tblEvents SET evaluated=1 WHERE event_id=:event_id";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":event_id", $event_id);
    if(!$statement->execute()){
        http_response_code(500);
        exit();
    }
}

?>