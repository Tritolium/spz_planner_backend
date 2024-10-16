<?php

require_once './config/database.php';

function predictAttendence($event_id) {
    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "SELECT tblUsergroupAssignments.member_id FROM `tblEvents` 
        LEFT JOIN tblUsergroupAssignments 
        ON tblEvents.usergroup_id=tblUsergroupAssignments.usergroup_id 
        LEFT JOIN tblAttendence 
        ON tblEvents.event_id=tblAttendence.event_id 
        AND tblUsergroupAssignments.member_id=tblAttendence.member_id 
        WHERE tblEvents.event_id=:event_id 
        AND (attendence IS NULL 
        OR attendence=-1)";

    $statement = $db_conn->prepare($query);
    $statement->bindParam(":event_id", $event_id);

    if (!$statement->execute()) {
        http_response_code(500);
        return;
    }

    $prob_missing = 0;
    $prob_attending = 0;
    $prob_signout = 0;

    $members = array();

    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        array_push($members, $row['member_id']);
    }

    // iterate over members
    foreach ($members as $member_id) {
        [$_att, $_miss, $_sign] = predictAttendencePerMember($member_id, $event_id);
        $prob_attending += $_att;
        $prob_missing += $_miss;
        $prob_signout += $_sign;
    }

    return [$prob_attending, $prob_missing, $prob_signout];
}

function predictAttendencePerMember($member_id, $event_id) {
    $database = new Database();
    $db_conn = $database->getConnection();
    
    /*
    * Get the last 10 attendences of the member for events of the same category
    * and calculate the probability of the member attending the event, based on the
    * evaluation of the attendences.
    * Limit the attendences to the last 10 to prevent the model from being biased.
    *
    * Adjust the limit over time to get a better prediction.
    */
    $query = "SELECT evaluation, COUNT(*) FROM 
        (SELECT evaluation FROM tblAttendence 
        LEFT JOIN tblEvents 
        ON tblAttendence.event_id=tblEvents.event_id 
        WHERE member_id=:member_id 
        AND evaluation IS NOT NULL 
        AND category=(SELECT category FROM tblEvents WHERE event_id=:event_id)
        ORDER BY date
        LIMIT 10) att 
        GROUP BY evaluation";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":member_id", $member_id);
    $statement->bindParam(":event_id", $event_id);

    if (!$statement->execute()) {
        http_response_code(500);
        return;
    }

    $okay = 0;
    $not_okay = 0;
    $signout = 0;

    $prob_attending = 0;
    $prob_missing = 0;
    $prob_signout = 0;

    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        switch ($row['evaluation']) {
        case 0:
        case 1:
            $not_okay += intval($row['COUNT(*)']);
            break;
        case 2:
            $signout += intval($row['COUNT(*)']);
            break;
        default:
            $okay += intval($row['COUNT(*)']);
            break;
        }
    }

    if ($okay + $not_okay + $signout == 0) {
        // no evaluated attendences
        $prob_missing += 1;
        return [$prob_attending, $prob_missing, $prob_signout];
    }

    if ($signout / ($okay + $not_okay + $signout) >= 0.9) {
        $prob_signout += 1;
    } else if ($not_okay / ($not_okay + $okay + $signout) >= 0.1) {
        $prob_missing += 1;
    } else {
        $prob_attending += 1;
    }

    return [$prob_attending, $prob_missing, $prob_signout];
}

?>