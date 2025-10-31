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
    $calendar = "BEGIN:VCALENDAR\r\n";
    $calendar .= "VERSION:2.0\r\n";
    $calendar .= "PRODID:-//SPZ Roenkhausen//NONSGML v1.0//DE\r\n";
    $calendar .= "BEGIN:VTIMEZONE\r\n";
    $calendar .= "TZID:Europe/Berlin\r\n";
    $calendar .= "BEGIN:DAYLIGHT\r\n";
    $calendar .= "TZOFFSETFROM:+0100\r\n";
    $calendar .= "TZOFFSETTO:+0200\r\n";
    $calendar .= "TZNAME:CEST\r\n";
    $calendar .= "DTSTART:19700329T020000\r\n";
    $calendar .= "RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU\r\n";
    $calendar .= "END:DAYLIGHT\r\n";
    $calendar .= "BEGIN:STANDARD\r\n";
    $calendar .= "TZOFFSETFROM:+0200\r\n";
    $calendar .= "TZOFFSETTO:+0100\r\n";
    $calendar .= "TZNAME:CET\r\n";
    $calendar .= "DTSTART:19701025T030000\r\n";
    $calendar .= "RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU\r\n";
    $calendar .= "END:STANDARD\r\n";
    $calendar .= "END:VTIMEZONE\r\n";

    foreach ($events as $event) {
        $eventDate = $event['date'] ? date('Y-m-d', strtotime($event['date'])) : date('Y-m-d');

        $startSource = $event['departure'] ?: ($event['begin'] ?: null);
        $startTime = $startSource ? date('H:i:s', strtotime($startSource)) : '12:00:00';

        $start = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $eventDate . ' ' . $startTime, $tzBerlin);
        if (!$start) {
            $start = new DateTimeImmutable($eventDate . ' ' . $startTime, $tzBerlin);
        }

        // check if leave_dep and or end are set
        if ($event['leave_dep']) {
            $leaveTime = date('H:i:s', strtotime($event['leave_dep']));
            $end = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $eventDate . ' ' . $leaveTime, $tzBerlin);
            if (!$end) {
                $end = new DateTimeImmutable($eventDate . ' ' . $leaveTime, $tzBerlin);
            }

            // if leave_dep is earlier than begin, add 1 day
            if ($end <= $start) {
                $end = $end->add(new DateInterval('P1D'));
            }
        } else if ($event['end']) {
            $endDate = date('Y-m-d', strtotime($event['end']));
            $endTime = date('H:i:s', strtotime($event['end']));
            $end = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $endDate . ' ' . $endTime, $tzBerlin);
            if (!$end) {
                $end = new DateTimeImmutable($endDate . ' ' . $endTime, $tzBerlin);
            }
        } else {
            $end = $start->add(new DateInterval('PT2H'));
        }

        $summary = $event['type'] . ' ' . $event['location'];
        $location = $event['address'] ? $event['address'] : $event['location'];

        $startUtc = $start->setTimezone($tzUTC);
        $begin = $startUtc->format('Ymd\THis\Z');

        $endUtc = $end->setTimezone($tzUTC);
        $end = $endUtc->format('Ymd\THis\Z');
        
        $calendar .= "BEGIN:VEVENT\r\n";
        $calendar .= "UID:" . $event['event_id'] . "@spz-roenkhausen.de\r\n";
        $calendar .= "DTSTART:" . $begin . "\r\n";
        $calendar .= "DTEND:" . $end . "\r\n";
        $calendar .= "SUMMARY:" . $summary . "\r\n";
        $calendar .= "DESCRIPTION:\r\n";
        $calendar .= "LOCATION:" . $location . "\r\n";
        $calendar .= "END:VEVENT\r\n";
    }

    $calendar .= "END:VCALENDAR\r\n";
    echo $calendar;
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Internal Server Error']);
}

?>