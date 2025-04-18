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

$query = "SELECT events.event_id, state, type, location, address, date, begin, end, departure, leave_dep, attendence FROM (SELECT event_id, category, state, t4.member_id, type, location, address, date, plusone as ev_plusone, begin, end, departure, leave_dep, t2.usergroup_id, clothing FROM tblEvents t 
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
            AND (attendence != 0 
            OR attendence IS NULL)
            ORDER BY date, begin";
$statement = $db_conn->prepare($query);
$statement->bindParam(":api_token", $_GET['api_token']);

if ($statement->execute()) {
    $tzBerlin = new DateTimeZone('Europe/Berlin');
    $tzUTC = new DateTimeZone('UTC');

    $events = $statement->fetchAll(PDO::FETCH_ASSOC);
    $calendar = "BEGIN:VCALENDAR\n";
    $calendar .= "VERSION:2.0\n";
    $calendar .= "PRODID:-//SPZ Roenkhausen//NONSGML v1.0//DE\n";
    $calendar .= "BEGIN:VTIMEZONE\n";
    $calendar .= "TZID:Europe/Berlin\n";
    $calendar .= "BEGIN:DAYLIGHT\n";
    $calendar .= "TZOFFSETFROM:+0100\n";
    $calendar .= "TZOFFSETTO:+0200\n";
    $calendar .= "TZNAME:CEST\n";
    $calendar .= "DTSTART:19700329T020000\n";
    $calendar .= "RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU\n";
    $calendar .= "END:DAYLIGHT\n";
    $calendar .= "BEGIN:STANDARD\n";
    $calendar .= "TZOFFSETFROM:+0200\n";
    $calendar .= "TZOFFSETTO:+0100\n";
    $calendar .= "TZNAME:CET\n";
    $calendar .= "DTSTART:19701025T030000\n";
    $calendar .= "RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU\n";
    $calendar .= "END:STANDARD\n";
    $calendar .= "END:VTIMEZONE\n";

    foreach ($events as $event) {
        $date = date('Ymd', strtotime($event['date']));
        $begin = $event['departure'] ? date('His', strtotime($event['departure'])) : ($event['begin'] ? date('His', strtotime($event['begin'])) : '120000');
        // check if leave_dep and or end are set
        if ($event['leave_dep']) {
            // if leave_dep is earlier than begin, add 1 day
            if (strtotime($event['leave_dep']) < strtotime($begin)) {
                $date_end = date('Ymd', strtotime($event['date'] . ' + 1 day'));
                $end = new DateTime($date_end . 'T' . date('His', strtotime($event['leave_dep'])), $tzBerlin);
            } else {
                $end = new DateTime($date . 'T' . date('His', strtotime($event['leave_dep'])), $tzBerlin);
            }
        } else if ($event['end']) {
            $end_time = date('His', strtotime($event['end']));
            $end_date = date('Ymd', strtotime($event['end']));
            $end = new DateTime($end_date . 'T' . $end_time, $tzBerlin);
        } else {
            $end = date('His', strtotime($begin . ' + 2 hours'));
            $end = new DateTime($date . 'T' . $end, $tzBerlin);
        }

        $summary = $event['type'] . ' ' . $event['location'];
        $location = $event['address'] ? $event['address'] : $event['location'];

        $start = new DateTime($date . 'T' . $begin, $tzBerlin);
        $start->setTimezone($tzUTC);
        $begin = $start->format('Ymd\THis\Z');

        $end->setTimezone($tzUTC);
        $end = $end->format('Ymd\THis\Z');
        
        $calendar .= "BEGIN:VEVENT\n";
        $calendar .= "UID:" . $event['event_id'] . "@spz-roenkhausen.de\n";
        $calendar .= "DTSTART:" . $begin . "\n";
        $calendar .= "DTEND:" . $end . "\n";
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