<?php

require_once './config/database.php';
require_once './config/utils.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header("Content-Type: application/json; charset=UTF-8");

switch ($_SERVER['REQUEST_METHOD']) {
    case 'OPTIONS':
        http_response_code(200);
        break;
    case 'GET':
        getPEvaluation();
        break;
    default:
        http_response_code(405);
        break;
}

// TODO: allow member_id and usergroup_id to be passed as parameters
//       check if the user is allowed to see the evaluations of other members
//       move calculations from the frontend to the backend
function getPEvaluation() {
    $database = new Database();
    $db_conn = $database->getConnection();
    $member_id;
    $year = date('Y');

    if (isset($_GET['year'])) {
        $year = $_GET['year'];
    }

    $date_from = $year . '-01-01';
    $date_to = $year . '-12-31';

    // get the usergroup for the user
    $member_id = apitokenToMemberID($_GET['api_token']);

    if ($member_id == null) {
        http_response_code(401);
        exit();
    }

    // get the usergroups for the user
    $query = "SELECT tblUsergroups.usergroup_id, title FROM tblUsergroupAssignments JOIN tblUsergroups ON tblUsergroupAssignments.usergroup_id = tblUsergroups.usergroup_id WHERE member_id = :member_id";
    $stmt = $db_conn->prepare($query);
    $stmt->bindParam(':member_id', $member_id);
    
    if (!$stmt->execute()) {
        http_response_code(500);
        exit();
    }

    $usergroups = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $usergroup_ids = array();
    $titles = array();
    foreach ($usergroups as $usergroup) {
        array_push($usergroup_ids, $usergroup['usergroup_id']);
        $titles[$usergroup['usergroup_id']] = $usergroup['title'];
    }

    $p_evaluation = array();

    // get the attendence for each usergroup
    foreach ($usergroup_ids as $usergroup_id) {

        // for each usergroup, get the member_ids and evaluate each member
        $query = "SELECT member_id FROM tblUsergroupAssignments WHERE usergroup_id = :usergroup_id";
        $stmt = $db_conn->prepare($query);
        $stmt->bindParam(':usergroup_id', $usergroup_id);

        if (!$stmt->execute()) {
            http_response_code(500);
            exit();
        }

        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $member_p_evaluation = array();

        foreach($members as $member) {
            $m_id = $member['member_id'];

            if($m_id == $member_id) {
                continue;
            }

            $member_peval = getPEvaluationByUsergroup($usergroup_id, $m_id, $date_from, $date_to);
            $member_p_evaluation[$m_id] = $member_peval;
        }

        $query = "SELECT evaluation, COUNT(*) FROM tblAttendence 
            LEFT JOIN tblEvents 
            ON tblAttendence.event_id = tblEvents.event_id 
            WHERE usergroup_id = :usergroup_id 
            AND member_id = :member_id 
            AND evaluation IS NOT NULL
            AND date BETWEEN :date_from AND :date_to 
            AND category = 'event'
            GROUP BY evaluation";
        $stmt = $db_conn->prepare($query);
        $stmt->bindParam(':usergroup_id', $usergroup_id);
        $stmt->bindParam(':member_id', $member_id);
        $stmt->bindParam(':date_from', $date_from);
        $stmt->bindParam(':date_to', $date_to);

        if (!$stmt->execute()) {
            http_response_code(500);
            exit();
        }

        $event_evaluations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $query = "SELECT evaluation, COUNT(*) FROM tblAttendence 
            LEFT JOIN tblEvents 
            ON tblAttendence.event_id = tblEvents.event_id 
            WHERE member_id IN 
                (SELECT member_id FROM tblMembers
                 WHERE api_token = :api_token)
            AND usergroup_id = :usergroup_id 
            AND evaluation IS NOT NULL
            AND date BETWEEN :date_from AND :date_to 
            AND category = 'practice'
            GROUP BY evaluation";
        $stmt = $db_conn->prepare($query);
        $stmt->bindParam(':api_token', $_GET['api_token']);
        $stmt->bindParam(':usergroup_id', $usergroup_id);
        $stmt->bindParam(':date_from', $date_from);
        $stmt->bindParam(':date_to', $date_to);

        if (!$stmt->execute()) {
            http_response_code(500);
            exit();
        }

        $practice_evaluations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $query = "SELECT evaluation, COUNT(*) FROM tblAttendence 
            LEFT JOIN tblEvents 
            ON tblAttendence.event_id = tblEvents.event_id 
            WHERE usergroup_id = :usergroup_id 
            AND member_id = :member_id 
            AND evaluation IS NOT NULL
            AND date BETWEEN :date_from AND :date_to 
            AND category = 'other'
            GROUP BY evaluation";
        $stmt = $db_conn->prepare($query);
        $stmt->bindParam(':usergroup_id', $usergroup_id);
        $stmt->bindParam(':member_id', $member_id);
        $stmt->bindParam(':date_from', $date_from);
        $stmt->bindParam(':date_to', $date_to);

        if (!$stmt->execute()) {
            http_response_code(500);
            exit();
        }

        $other_evaluations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $p_evaluation[$titles[$usergroup_id]] = array();
        $p_evaluation[$titles[$usergroup_id]]['event'] = array();
        $p_evaluation[$titles[$usergroup_id]]['practice'] = array();
        $p_evaluation[$titles[$usergroup_id]]['other'] = array();


        [$more, $less, $equal] = gradeAttendence($event_evaluations, $member_p_evaluation, 'event');
        $p_evaluation[$titles[$usergroup_id]]['event']['more'] = $more;
        $p_evaluation[$titles[$usergroup_id]]['event']['less'] = $less;
        $p_evaluation[$titles[$usergroup_id]]['event']['equal'] = $equal;

        [$more, $less, $equal] = gradeAttendence($practice_evaluations, $member_p_evaluation, 'practice');
        $p_evaluation[$titles[$usergroup_id]]['practice']['more'] = $more;
        $p_evaluation[$titles[$usergroup_id]]['practice']['less'] = $less;
        $p_evaluation[$titles[$usergroup_id]]['practice']['equal'] = $equal;

        [$more, $less, $equal] = gradeAttendence($other_evaluations, $member_p_evaluation, 'other');
        $p_evaluation[$titles[$usergroup_id]]['other']['more'] = $more;
        $p_evaluation[$titles[$usergroup_id]]['other']['less'] = $less;
        $p_evaluation[$titles[$usergroup_id]]['other']['equal'] = $equal;

        foreach ($event_evaluations as $evaluation) {
            $p_evaluation[$titles[$usergroup_id]]['event'][$evaluation['evaluation']] = $evaluation['COUNT(*)'];

        }

        foreach ($practice_evaluations as $evaluation) {
            $p_evaluation[$titles[$usergroup_id]]['practice'][$evaluation['evaluation']] = $evaluation['COUNT(*)'];
        }

        foreach ($other_evaluations as $evaluation) {
            $p_evaluation[$titles[$usergroup_id]]['other'][$evaluation['evaluation']] = $evaluation['COUNT(*)'];
        }
    }

    // remove empty evaluations
    foreach ($p_evaluation as $usergroup => $evaluations) {
        if (empty($evaluations['event'])) {
            unset($p_evaluation[$usergroup]['event']);
        }
        if (empty($evaluations['practice'])) {
            unset($p_evaluation[$usergroup]['practice']);
        }
        if (empty($evaluations['other'])) {
            unset($p_evaluation[$usergroup]['other']);
        }
    }

    // remove empty usergroups
    foreach ($p_evaluation as $usergroup => $evaluations) {
        if (empty($evaluations['event']) && empty($evaluations['practice']) && empty($evaluations['other'])) {
            unset($p_evaluation[$usergroup]);
        }
    }

    response_with_data(200, $p_evaluation);
}

function getPEvaluationByUsergroup($usergroup_id, $member_id, $date_from, $date_to) {
    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "SELECT evaluation, COUNT(*) FROM tblAttendence 
        LEFT JOIN tblEvents 
        ON tblAttendence.event_id = tblEvents.event_id 
        WHERE usergroup_id = :usergroup_id 
        AND member_id = :member_id 
        AND evaluation IS NOT NULL
        AND date BETWEEN :date_from AND :date_to 
        AND category = 'event'
        GROUP BY evaluation";
    $stmt = $db_conn->prepare($query);
    $stmt->bindParam(':usergroup_id', $usergroup_id);
    $stmt->bindParam(':member_id', $member_id);
    $stmt->bindParam(':date_from', $date_from);
    $stmt->bindParam(':date_to', $date_to);

    if (!$stmt->execute()) {
        http_response_code(500);
        exit();
    }

    $event_evaluations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $query = "SELECT evaluation, COUNT(*) FROM tblAttendence 
        LEFT JOIN tblEvents 
        ON tblAttendence.event_id = tblEvents.event_id 
        WHERE usergroup_id = :usergroup_id
        AND member_id = :member_id
        AND evaluation IS NOT NULL
        AND date BETWEEN :date_from AND :date_to 
        AND category = 'practice'
        GROUP BY evaluation";
    $stmt = $db_conn->prepare($query);
    $stmt->bindParam(':usergroup_id', $usergroup_id);
    $stmt->bindParam(':member_id', $member_id);
    $stmt->bindParam(':date_from', $date_from);
    $stmt->bindParam(':date_to', $date_to);

    if (!$stmt->execute()) {
        http_response_code(500);
        exit();
    }

    $practice_evaluations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $query = "SELECT evaluation, COUNT(*) FROM tblAttendence 
        LEFT JOIN tblEvents 
        ON tblAttendence.event_id = tblEvents.event_id 
        WHERE usergroup_id = :usergroup_id 
        AND member_id = :member_id 
        AND evaluation IS NOT NULL
        AND date BETWEEN :date_from AND :date_to
        AND category = 'other'
        GROUP BY evaluation";
    $stmt = $db_conn->prepare($query);
    $stmt->bindParam(':usergroup_id', $usergroup_id);
    $stmt->bindParam(':member_id', $member_id);
    $stmt->bindParam(':date_from', $date_from);
    $stmt->bindParam(':date_to', $date_to);

    if (!$stmt->execute()) {
        http_response_code(500);
        exit();
    }

    $other_evaluations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $p_evaluation = array();
    $p_evaluation['event'] = array();
    $p_evaluation['practice'] = array();
    $p_evaluation['other'] = array();

    foreach ($event_evaluations as $evaluation) {
        $p_evaluation['event'][$evaluation['evaluation']] = $evaluation['COUNT(*)'];
    }

    foreach ($practice_evaluations as $evaluation) {
        $p_evaluation['practice'][$evaluation['evaluation']] = $evaluation['COUNT(*)'];
    }

    foreach ($other_evaluations as $evaluation) {
        $p_evaluation['other'][$evaluation['evaluation']] = $evaluation['COUNT(*)'];
    }

    // remove empty evaluations
    if (empty($p_evaluation['event'])) {
        unset($p_evaluation['event']);
    }

    if (empty($p_evaluation['practice'])) {
        unset($p_evaluation['practice']);
    }

    if (empty($p_evaluation['other'])) {
        unset($p_evaluation['other']);
    }

    return $p_evaluation;
}

function gradeAttendence($evaluations, $member_p_evaluation, $type) {
    $attendence = 0;
    $missing = 0;
    $total = 0;

    $more = 0;
    $less = 0;
    $equal = 0;

    foreach ($evaluations as $evaluation) {
        switch($evaluation['evaluation']){
        case '0':
        case '1':
        case '2':
            $missing += $evaluation['COUNT(*)'];
            break;
        case '3':
        case '4':
            $attendence += $evaluation['COUNT(*)'];
            break;
        }
    }

    $total = $attendence + $missing;

    $others = array();

    foreach($member_p_evaluation as $member_id => $member_peval) {
        $m_attendence = 0;
        $m_missing = 0;

        foreach($member_peval as $ev_type => $peval) {
            if ($ev_type != $type) {
                continue;
            }

            foreach($peval as $evaluation => $count) {
                switch($evaluation){
                case '0':
                case '1':
                case '2':
                    $m_missing += $count;
                    break;
                case '3':
                case '4':
                    $m_attendence += $count;
                    break;
                }
            }
        }

        if ($m_attendence > $attendence) {
            $more++;
        } else if ($m_attendence == $attendence && $m_attendence != 0) {
            $equal++;
        } else if ($m_attendence < $attendence) {
            $less++;
        }
    }

    return [$more, $less, $equal];
}

?>