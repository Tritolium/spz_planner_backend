<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');

$request = $_SERVER['REQUEST_URI'];
// remove /api/v0 from the request
$request = str_replace('/api/v0', '', $request);
// remove query string from the request
$request = explode('?', $request)[0];
// split the request to get the id
$request_exploded = explode('/', $request);

$association_id = null;

if (isset($request_exploded[2])) {
    $association_id = $request_exploded[2];
}

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        getAssociation($association_id);
        break;
    case 'OPTIONS':
        http_response_code(200);
        break;
    default:
        http_response_code(405);
        break;
}

function getAssociation($association_id) {
    require_once __DIR__ . '/../config/database.php';

    $database = new Database();
    $conn = $database->getConnection();

    if ($association_id == null) {
        // get all associations that are assigned to the member
        $query = "SELECT * FROM tblAssociations ta 
            LEFT JOIN tblAssociationAssignments taa 
            ON ta.association_id = taa.association_id 
            LEFT JOIN tblMembers tm
            ON taa.member_id = tm.member_id
            WHERE api_token = :api_token";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':api_token', $_GET['api_token']);

        if (!$stmt->execute()) {
            http_response_code(500);
            echo json_encode(array("message" => "Internal Server Error"));
            exit();
        }

        $associations = array();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            extract($row);
            $association = array(
                "Association_ID"    => intval($association_id),
                "Title"             => $title,
                "FirstChair"        => $firstchair,
                "Clerk"             => $clerk,
                "Treasurer"         => $treasurer
            );

            array_push($associations, $association);
        }

        http_response_code(200);
        echo json_encode($associations);
    } else {
        // get association by id
        $query = "SELECT * FROM tblAssociations WHERE association_id = :association_id";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':association_id', $association_id);

        if (!$stmt->execute()) {
            http_response_code(500);
            echo json_encode(array("message" => "Internal Server Error"));
            exit();
        }

        $association = $stmt->fetch(PDO::FETCH_ASSOC);

        http_response_code(200);
        echo json_encode($association);
    }
}

?>