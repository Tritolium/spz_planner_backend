<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');

$request = $_SERVER['REQUEST_URI'];
// remove /api/v0 from the request
$request = str_replace('/api/v0', '', $request);
// remove query string from the request
$request = explode('?', $request)[0];
// split the request to get the id
$request_exploded = explode('/', $request);

$member_id = null;

if (isset($request_exploded[2])) {
    $member_id = $request_exploded[2];
}

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        getMember($member_id);
        break;
    case 'POST':
        createMember();
        break;
    case 'PUT':
        updateMember($member_id);
        break;
    case 'OPTIONS':
        http_response_code(200);
        break;
    default:
        http_response_code(405);
        break;
}

function getMember($member_id) {
    require_once __DIR__ . '/../config/database.php';

    $database = new Database();
    $conn = $database->getConnection();

    if ($member_id == null) {
        
        if(isset($_GET['association_id'])) {
            // check if the user is assigned to the association
            $query = "SELECT * FROM tblAssociationAssignments taa 
                LEFT JOIN tblMembers tm 
                ON taa.member_id = tm.member_id 
                WHERE api_token = :api_token 
                AND association_id = :association_id";

            $stmt = $conn->prepare($query);
            $stmt->bindParam(':api_token', $_GET['api_token']);
            $stmt->bindParam(':association_id', $_GET['association_id']);

            if (!$stmt->execute()) {
                http_response_code(500);
                exit();
            }

            if ($stmt->rowCount() < 1) {
                http_response_code(403);
                exit();
            }

            // get all members that are assigned to the association
            $query = "SELECT tm.member_id, forename, surname, birthdate FROM tblMembers tm 
                LEFT JOIN tblAssociationAssignments taa 
                ON tm.member_id = taa.member_id 
                WHERE taa.association_id = :association_id 
                GROUP BY tm.member_id 
                ORDER BY surname, forename";

            $stmt = $conn->prepare($query);
            $stmt->bindParam(':association_id', $_GET['association_id']);
        } else {
            // get all members that are assigned to the same associations as the user
            $query = "SELECT tm.member_id, forename, surname, birthdate FROM tblMembers tm 
                LEFT JOIN tblAssociationAssignments taa 
                ON tm.member_id = taa.member_id 
                WHERE taa.association_id IN 
                    (SELECT association_id FROM tblAssociationAssignments taa 
                    LEFT JOIN tblMembers tm 
                    ON taa.member_id = tm.member_id 
                    WHERE api_token = :api_token) 
                GROUP BY tm.member_id 
                ORDER BY surname, forename";
            
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':api_token', $_GET['api_token']);
        }

        if (!$stmt->execute()) {
            http_response_code(500);
            exit();
        }
        
        $members = array();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            extract($row);
            $member = array(
                'Member_ID' => $member_id,
                'Forename' => $forename,
                'Surname' => $surname,
                'Birthdate' => $birthdate
            );

            array_push($members, $member);
        }

        if (count($members) < 1) {
            http_response_code(204);
            exit();
        }

        // for each member get the roles
        foreach ($members as $index => $member) {
            $query = "SELECT role_id, association_id FROM tblUserRoles WHERE member_id = :member_id 
                AND association_id IN 
                    (SELECT association_id FROM tblAssociationAssignments taa 
                    LEFT JOIN tblMembers tm 
                    ON taa.member_id = tm.member_id 
                    WHERE api_token = :api_token)
                ORDER BY association_id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':member_id', $member['Member_ID']);
            $stmt->bindParam(':api_token', $_GET['api_token']);

            if (!$stmt->execute()) {
                http_response_code(500);
                exit();
            }
            
            $members[$index]['Roles'] = array();

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                extract($row);
                if (!isset($members[$index]['Roles'][$association_id])) {
                    $members[$index]['Roles'][$association_id] = [];
                }
                array_push($members[$index]['Roles'][$association_id], $role_id);
            }
        }

        http_response_code(200);
        echo json_encode($members);
    } else {
        // get a specific member
        $query = "SELECT member_id, forename, surname, auth_level, nicknames, birthdate, instrument, theme
            FROM tblMembers WHERE member_id = :member_id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':member_id', $member_id);

        if (!$stmt->execute()) {
            http_response_code(500);
            exit();
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row == null) {
            http_response_code(204);
            exit();
        }

        extract($row);

        $member = array(
            'Member_ID' => $member_id,
            'Forename' => $forename,
            'Surname' => $surname,
            'Auth_level' => $auth_level,
            'Nicknames' => $nicknames,
            'Birthdate' => $birthdate,
            'Instrument' => $instrument,
            'Theme' => $theme
        );

        http_response_code(200);
        echo json_encode($member);
    }
}

function createMember() {
    require_once __DIR__ . '/../config/database.php';

    $database = new Database();
    $conn = $database->getConnection();

    $data = json_decode(file_get_contents('php://input'));

    $query = "INSERT INTO tblMembers (forename, surname, auth_level, nicknames, birthdate, api_token) 
        VALUES (:forename, :surname, :auth_level, :nicknames, :birthdate, :api_token)";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':forename', $data->Forename);
    $stmt->bindParam(':surname', $data->Surname);
    $stmt->bindParam(':auth_level', $data->Auth_level);
    $stmt->bindParam(':nicknames', $data->Nicknames);
    $stmt->bindValue(':birthdate', ($data->Birthdate == "") ? null : $data->Birthdate);
    $stmt->bindValue(':api_token', hash('md5', rand()));
    
    if (!$stmt->execute()) {
        http_response_code(500);
        exit();
    }
    
    $data->Member_ID = $conn->lastInsertId();

    http_response_code(201);
    echo json_encode($data);
}

?>