<?php
include_once './config/database.php';

header('content-type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: if-modified-since');

if(!isset($_GET['api_token'])){
    http_response_code(403);
    exit();
}

$data = json_decode(file_get_contents('php://input'));

switch($_SERVER['REQUEST_METHOD'])
{
    case 'OPTIONS':
        http_response_code(200);
        break;
    case 'POST':
        if(newAssociation($_GET['api_token'], $data)){
            http_response_code(201);
        } else {
            http_response_code(500);
        }
        break;
    case 'PUT':
        if(isset($_GET['assign'])){
            if(updateAssignments($_GET['api_token'], $data)){
                http_response_code(200);
            } else {
                http_response_code(500);
            }

        }

        if(updateAssociation($_GET['api_token'], $_GET['id'], $data)){
            http_response_code(200);
        } else {
            http_response_code(500);
        }
        break;
    case 'GET':
        if(isset($_GET['assign'])){
            if(!getAssociationAssignment($_GET['api_token'])){
                http_response_code(500);
            }
        } elseif (!getAssociations($_GET['api_token'])) {
            http_response_code(500);
        }
        break;
}

function newAssociation($api_token, $data){
    if(!isAdmin($api_token)){
        http_response_code(403);
        exit();
    }

    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "INSERT INTO tblAssociations (title, firstchair, treasurer, clerk) VALUES (:title, :firstchair, :treasurer, :clerk)";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":title", $data->Title);
    $statement->bindParam(":firstchair", $data->FirstChair);
    $statement->bindParam(":treasurer", $data->Treasurer);
    $statement->bindParam(":clerk", $data->Clerk);
    
    if($statement->execute()){
        return true;
    }

    return false;
}

function updateAssociation($api_token, $id, $data){
    if(!isAdmin($api_token)){
        http_response_code(403);
        exit();
    }

    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "UPDATE tblAssociations SET title=:title, firstchair=:firstchair, treasurer=:treasurer, clerk=:clerk WHERE association_id = :association_id";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":title", $data->Title);
    $statement->bindParam(":firstchair", $data->FirstChair);
    $statement->bindParam(":treasurer", $data->Treasurer);
    $statement->bindParam(":clerk", $data->Clerk);
    $statement->bindParam(":association_id", $id);
    return $statement->execute();
}

function getAssociations($api_token){
    if(!isAdmin($api_token)){
        http_response_code(403);
        exit();
    }

    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "SELECT * FROM tblAssociations";

    $statement = $db_conn->prepare($query);

    if(!$statement->execute()){
        return false;
    }

    if($statement->rowCount() < 1){
        http_response_code(204);
        exit();
    }

    $associations_array = array();

    while($row = $statement->fetch(PDO::FETCH_ASSOC)){
        extract($row);
        $association = array(
            "Association_ID"    => intval($association_id),
            "Title"             => $title,
            "FirstChair"        => $firstchair,
            "Clerk"             => $clerk,
            "Treasurer"         => $treasurer
        );

        array_push($associations_array, $association);
    }

    response_with_data(200, $associations_array);
    return true;
}

function getAssociationAssignment($api_token)
{
    # cross associations with users, join assingments on member&assoc_id
    if(!isAdmin($api_token)){
        http_response_code(403);
        exit();
    }
    
    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "SELECT assign.association_id, title, assign.member_id, taa.member_id AS assigned, assign.forename, assign.surname FROM 
    (SELECT ta.association_id, ta.title, tm.member_id, tm.forename, tm.surname  
    FROM tblAssociations ta 
    JOIN tblMembers tm) AS assign
    LEFT JOIN tblAssociationAssignments taa 
    ON assign.association_id = taa.association_id 
    AND assign.member_id = taa.member_id
    ORDER BY surname, forename, association_id";

    $statement = $db_conn->prepare($query);
    
    // wenn statement erfolgreich:
    if($statement->execute()){
        $success = processAssociationAssignmentStatement($statement);
    }

    return $success;
}

function processAssociationAssignmentStatement($statement)
{
    $assignments = array();
    $associations = array();
    
    if($row = $statement->fetch(PDO::FETCH_ASSOC)){
        $curr_member_id = intval($row['member_id']);

        extract($row);
        $association = array(
            "Association_ID"    => intval($association_id),
            "Title"             => $title,
            "Assigned"          => (is_null($assigned)) ? false : true
        );

        array_push($associations, $association);

        while($row = $statement->fetch(PDO::FETCH_ASSOC)){
            if($curr_member_id == $row['member_id']){
                extract($row);
                $association = array(
                    "Association_ID"    => $association_id,
                    "Title"             => $title,
                    "Assigned"          => (is_null($assigned)) ? false : true
                );
                array_push($associations, $association);
            } else {
                $member = array(
                    "Member_ID"     => $member_id,
                    "Fullname"      => $forename . " " . $surname,
                    "Associations"  => $associations
                );

                array_push($assignments, $member);

                extract($row);
                $curr_member_id = $member_id;
                $associations = array();
                $association = array(
                    "Association_ID"    => intval($association_id),
                    "Title"             => $title,
                    "Assigned"          => (is_null($assigned)) ? false : true
                );

                array_push($associations, $association);
            }
        }

        $member = array(
            "Member_ID"     => $member_id,
            "Fullname"      => $forename . " " . $surname,
            "Associations"  => $associations
        );

        array_push($assignments, $member);

        response_with_data(200, $assignments);

        return true;
    }

    return false;
}

function updateAssignments($api_token, $assignments)
{
    if(!isAdmin($api_token)){
        http_response_code(403);
        exit();
    }

    foreach($assignments as $member => $changes){
        processAssociationAssignments($changes, $member);
    }

    return true;
}

function processAssociationAssignments($changes, $member_id)
{
    foreach($changes as $association_id => $assignment){
        $assignment ? setAssociationAssignment($association_id, $member_id) : deleteAssociationAssignment($association_id, $member_id);
    }
}

function setAssociationAssignment($association_id, $member_id)
{
    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "INSERT INTO tblAssociationAssignments (association_id, member_id) VALUES (:association_id, :member_id)";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":association_id", $association_id);
    $statement->bindParam(":member_id", $member_id);

    $statement->execute();
}

function deleteAssociationAssignment($association_id, $member_id)
{
    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "DELETE FROM tblAssociationAssignments WHERE association_id=:association_id AND member_id=:member_id";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":association_id", $association_id);
    $statement->bindParam(":member_id", $member_id);

    $statement->execute();
}
?>