<?php
include_once './config/database.php';
include_once './v0/predictionlog.php';

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
    if(isset($_GET['statistics'])){
        getStatistics($_GET['api_token']);
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

    $query = "SELECT event_id, type, location, date, plusone AS ev_plusone FROM tblEvents WHERE date >= curdate() AND accepted=1 AND usergroup_id=:usergroup_id ORDER BY date";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":usergroup_id", $usergroup_id);
    $statement->execute();

    $events = array();
    $eval = array();

    while($row = $statement->fetch(PDO::FETCH_ASSOC)){
        extract($row);
        array_push($events, array(intval($event_id), $type, $location, $date, $ev_plusone));
    }

    foreach($events as $event_item){
        $consent = getEventConsentCount($event_item[0], $usergroup_id);
        $refusal = getEventRefusalCount($event_item[0], $usergroup_id);
        $maybe = getEventMaybeCount($event_item[0], $usergroup_id);
        $missing = getEventMissingCount($event_item[0], $usergroup_id);
        $plusone = getEventPlusOneCount($event_item[0], $usergroup_id);

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
            "PlusOne"  => boolval($event_item[4]) ? intval($plusone) : null,
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
    $plusone = getEventPlusOneCount($event_id, $usergroup_id);

    $event = array(
        "Event_ID" => intval($event_id),
        "Consent"  => $consent,
        "Refusal"  => $refusal,
        "Maybe"    => $maybe,
        "Missing"  => $missing,
        "PlusOne"  => intval($plusone)
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

function getEventPlusOneCount($event_id, $usergroup_id)
{
    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "SELECT COUNT(plusone) AS plusone 
    FROM (SELECT tm.member_id FROM tblMembers tm 
    left join tblUsergroupAssignments tua 
    on tm.member_id = tua.member_id 
    where tua.usergroup_id = :usergroup_id) as users 
    LEFT JOIN 
    (SELECT * FROM tblAttendence WHERE event_id=:event_id) AS a 
    ON users.member_id=a.member_id 
    WHERE attendence=1 AND plusone=1";

    $statement = $db_conn->prepare($query);
    $statement->bindParam(":usergroup_id", $usergroup_id);
    $statement->bindParam(":event_id", $event_id);
    $statement->execute();
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    return $row["plusone"];
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

function getStatistics($api_token)
{
    $database = new Database();
    $db_conn = $database->getConnection();

    $date = date("Y-m-d");

    if(isset($_GET['date'])){
        $date = $_GET['date'];        
    }

    if(isset($_GET['date'])){
        $query = "SELECT COUNT(*) as count, version 
            FROM (SELECT t1.* FROM tblLogin t1
            JOIN (SELECT member_id, max(timestamp)
                AS max_timestamp FROM tblLogin 
                WHERE timestamp < :date 
                GROUP BY member_id) t2
            ON t1.member_id=t2.member_id 
            AND t1.timestamp = t2.max_timestamp
        ORDER by member_id) t3 GROUP BY version";

        $statement = $db_conn->prepare($query);
        $statement->bindParam(":date", $date);

        if(!$statement->execute()){
            http_response_code(500);
        }

        $versions = array();

        while($row = $statement->fetch(PDO::FETCH_ASSOC)){
            extract($row);
            $versions[$version] = $count;
        }
    } else {
        $query = "SELECT last_version, COUNT(*) AS count FROM (SELECT tblMembers.member_id, last_version FROM tblMembers LEFT JOIN tblUsergroupAssignments ON tblMembers.member_id=tblUsergroupAssignments.member_id WHERE usergroup_id IS NOT null GROUP BY tblMembers.member_id ORDER BY last_version) AS members GROUP BY last_version";
        $statement = $db_conn->prepare($query);
        if(!$statement->execute()){
            http_response_code(500);
        }

        $versions = array();

        while($row = $statement->fetch(PDO::FETCH_ASSOC)){
            extract($row);
            $versions[$last_version] = $count;
        }
    }

    $query = "SELECT last_display, COUNT(*) AS count FROM (SELECT last_display FROM tblMembers LEFT JOIN tblUsergroupAssignments ON tblMembers.member_id=tblUsergroupAssignments.member_id WHERE usergroup_id IS NOT null GROUP BY tblMembers.member_id ORDER BY last_version) AS members GROUP BY last_display";
    $statement = $db_conn->prepare($query);
    if(!$statement->execute()){
        http_response_code(500);
    }

    $displays = array();

    while($row = $statement->fetch(PDO::FETCH_ASSOC)){
        extract($row);
        $displays[$last_display] = intval($count);
    }

    // today

    $query = "SELECT COUNT(*) AS calls FROM tblLogin WHERE DATE(timestamp) = :date";

    $statement = $db_conn->prepare($query);
    $statement->bindParam(":date", $date);
    if(!$statement->execute()){
        http_response_code(500);
    }

    $row = $statement->fetch(PDO::FETCH_ASSOC);
    $today_calls = intval($row['calls']);

    $query = "SELECT COUNT(*) AS daily FROM tblLogin WHERE DATE(timestamp) = :date GROUP BY member_id";
    
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":date", $date);
    if(!$statement->execute()){
        http_response_code(500);
    }

    $today_daily = $statement->rowCount();

    // yesterday

    $query = "SELECT COUNT(*) AS calls FROM tblLogin WHERE DATE(timestamp) = DATE_SUB(:date, INTERVAL 1 DAY)";

    $statement = $db_conn->prepare($query);
    $statement->bindParam(":date", $date);
    if(!$statement->execute()){
        http_response_code(500);
    }

    $row = $statement->fetch(PDO::FETCH_ASSOC);
    $yesterday_calls = intval($row['calls']);

    $query = "SELECT COUNT(*) AS daily FROM tblLogin WHERE DATE(timestamp) = DATE_SUB(:date, INTERVAL 1 DAY) GROUP BY member_id";

    $statement = $db_conn->prepare($query);
    $statement->bindParam(":date", $date);
    if(!$statement->execute()){
        http_response_code(500);
    }

    $yesterday_daily = $statement->rowCount();

    // 7 days

    $query = "SELECT COUNT(*) AS calls FROM tblLogin WHERE DATE(timestamp) >= DATE_SUB(:date, INTERVAL 8 DAY) AND DATE(timestamp) < :date";

    $statement = $db_conn->prepare($query);
    $statement->bindParam(":date", $date);
    if(!$statement->execute()){
        http_response_code(500);
    }

    $row = $statement->fetch(PDO::FETCH_ASSOC);
    $seven_calls = intval($row['calls']);

    $query = "SELECT COUNT(*) AS daily FROM tblLogin WHERE DATE(timestamp) >= DATE_SUB(:date, INTERVAL 8 DAY) AND DATE(timestamp) < :date GROUP BY member_id";

    $statement = $db_conn->prepare($query);
    $statement->bindParam(":date", $date);
    if(!$statement->execute()){
        http_response_code(500);
    }

    $seven_daily = $statement->rowCount();

    // 30 days

    $query = "SELECT COUNT(*) AS calls FROM tblLogin WHERE DATE(timestamp) >= DATE_SUB(:date, INTERVAL 31 DAY) AND DATE(timestamp) < :date";

    $statement = $db_conn->prepare($query);
    $statement->bindParam(":date", $date);
    if(!$statement->execute()){
        http_response_code(500);
    }

    $row = $statement->fetch(PDO::FETCH_ASSOC);
    $thirty_calls = intval($row['calls']);
    
    $query = "SELECT COUNT(*) AS daily FROM tblLogin WHERE DATE(timestamp) >= DATE_SUB(:date, INTERVAL 31 DAY) AND DATE(timestamp) < :date GROUP BY member_id";

    $statement = $db_conn->prepare($query);
    $statement->bindParam(":date", $date);
    if(!$statement->execute()){
        http_response_code(500);
    }

    $thirty_daily = $statement->rowCount();

    $users_today = NULL;

    if(isAdmin($api_token)) {
        $query = "SELECT forename, surname, timestamp FROM tblLogin LEFT JOIN tblMembers ON tblLogin.member_id=tblMembers.member_id WHERE DATE(timestamp) = :date ORDER BY timestamp DESC";

        $statement = $db_conn->prepare($query);
        $statement->bindParam(":date", $date);
        if(!$statement->execute()){
            http_response_code(500);
        }

        $users_today = array();

        while($row = $statement->fetch(PDO::FETCH_ASSOC)){
            extract($row);
            $user = array(
                "Fullname" => $forename . " " . $surname,
                "Timestamp" => $timestamp
            );
            array_push($users_today, $user);
        }
    }

    $stats = array(
        "Versions" => $versions,
        "Displays" => $displays,
        "Users" => [
            "Today" => [
                "Calls" => $today_calls,
                "Daily" => $today_daily,
                "Users" => $users_today
            ],
            "Yesterday" => [
                "Calls" => $yesterday_calls,
                "Daily" => $yesterday_daily
            ],
            "Seven" => [
                "Calls" => $seven_calls,
                "Daily" => $seven_daily
            ],
            "Thirty" => [
                "Calls" => $thirty_calls,
                "Daily" => $thirty_daily
            ]
        ]
    );

    response_with_data(200, $stats);
}

function setEventEval($event_id, $evaluation)
{
    $database = new Database();
    $db_conn = $database->getConnection();

    //get the predicted number of attendences
    $query = "SELECT COUNT(*) AS consent FROM tblAttendence WHERE event_id=:event_id AND attendence=1";

    $statement = $db_conn->prepare($query);
    $statement->bindParam(":event_id", $event_id);
    if(!$statement->execute()){
        http_response_code(500);
        exit();
    }

    $row = $statement->fetch(PDO::FETCH_ASSOC);
    $consent = intval($row['consent']);

    [$prob_attending, $prob_missing] = predictAttendence($event_id);

    $prediction = $consent + $prob_attending;

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

    $query = "UPDATE tblEvents SET evaluated=1, prediction=:prediction WHERE event_id=:event_id";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":event_id", $event_id);
    $statement->bindParam(":prediction", $prediction);
    if(!$statement->execute()){
        http_response_code(500);
        exit();
    }
}

?>