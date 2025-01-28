<?php

require_once __DIR__ . '/config/database.php';

header('Content-Type: text/calendar; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

if (!isset($_GET['api_token'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Bad Request']);
    exit;
}

$database = new Database();
$db_conn = $database->getConnection();

$query = "SELECT events.event_id, state, type, location, address, date, begin, departure, leave_dep, attendence FROM (SELECT event_id, category, state, t4.member_id, type, location, address, date, plusone as ev_plusone, begin, departure, leave_dep, t2.usergroup_id, clothing FROM tblEvents t 
            LEFT JOIN tblUsergroupAssignments t2 
            ON t.usergroup_id = t2.usergroup_id
            LEFT JOIN tblMembers t4 
            ON t2.member_id = t4.member_id 
            WHERE api_token = :api_token
            AND state < 2) 
            AS events
            LEFT JOIN tblAttendence t3
            ON events.event_id = t3.event_id AND events.member_id = t3.member_id 
            LEFT JOIN tblUsergroups t5
            ON events.usergroup_id = t5.usergroup_id
            WHERE date >= curdate()
            AND attendence != 0 
            ORDER BY date, begin";
$statement = $db_conn->prepare($query);
$statement->bindParam(":api_token", $_GET['api_token']);

if ($statement->execute()) {
    $events = $statement->fetchAll(PDO::FETCH_ASSOC);
    $calendar = "BEGIN:VCALENDAR\n";
    $calendar .= "VERSION:2.0\n";
    $calendar .= "PRODID:SPZ Roenkhausen\n";

    foreach ($events as $event) {
        $date = date('Ymd', strtotime($event['date']));
        $begin = $event['departure'] ? date('His', strtotime($event['departure'])) : ($event['begin'] ? date('His', strtotime($event['begin'])) : '120000');
        $end = $event['end'] ? date('His', strtotime($event['departures'])) : date('His', strtotime($event['begin'] . ' + 2 hours'));
        $summary = $event['type'] . ' ' . $event['location'];
        $location = $event['address'] ? $event['address'] : $event['location'];
        
        $calendar .= "BEGIN:VEVENT\n";
        $calendar .= "DTSTART:" . $date . "T" . $begin . "\n";
        $calendar .= "DTEND:" . $date . "T" . $end . "\n";
        $calendar .= "SUMMARY:" . $summary . "\n";
        $calendar .= "DESCRIPTION:\n";
        $calendar .= "LOCATION:" . $location . "\n";
        $calendar .= "END:VEVENT\n";
    }

    $calendar .= "END:VCALENDAR";
    echo $calendar;
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Internal Server Error']);
}

?>