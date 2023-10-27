<?php
include_once './config/database.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, POST, GET, OPTIONS, DELETE');

if(!isset($_GET['api_token']) || !isAdmin($_GET['api_token'])){
    http_response_code(403);
    exit();
}

switch($_SERVER['REQUEST_METHOD'])
{
    case 'OPTIONS':
        http_response_code(200);
        break;
    case 'GET':
        header('content-type: application/json');
        if(!getDateTemplates()){
            http_response_code(500);
        }
        break;
    case 'POST':
        $data = json_decode(file_get_contents('php://input'));
        if(!newDateTemplate($data)){
            http_response_code(500);
        }
        break;
    case 'PUT':
        $data = json_decode(file_get_contents('php://input'));
        if(!isset($_GET['template_id'])){
            http_response_code(400);
            break;
        }

        if(!updateDateTemplate($_GET['template_id'], $data)){
            http_response_code(500);
        }
        break;
}

function getDateTemplates()
{
    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "SELECT * FROM tblDatetemplates";

    $statement = $db_conn->prepare($query);

    if($statement->execute()){

        if($statement->rowCount() < 1){
            http_response_code(204);
            return true;
        }

        $templates = array();

        while($row = $statement->fetch(PDO::FETCH_ASSOC)){
            extract($row);
            $template = array(
                "DateTemplate_ID"   => intval($datetemplate_id),
                "Title"             => $title,
                "Description"       => $description,
                "Category"          => $category,
                "Type"              => $type,
                "Location"          => $location,
                "Begin"             => $begin,
                "Departure"         => $departure,
                "Leave_dep"         => $leave_dep,
                "Usergroup_ID"      => $usergroup_id
            );

            array_push($templates, $template);
        }

        response_with_data(200, $templates);
        
        return true;
    }

    return false;
}

function newDateTemplate($template_data)
{
    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "INSERT INTO tblDatetemplates (title, description, category, type, location, begin, departure, leave_dep, usergroup_id) 
    VALUES (:title, :description, :category, :type, :location, :begin, :departure, :leave_dep, :usergroup_id)";

    $statement = $db_conn->prepare($query);
    $statement->bindParam(":title", $template_data->Title);
    $statement->bindParam(":description", $template_data->Description);
    $statement->bindParam(":category", $template_data->Category);
    $statement->bindParam(":type", $template_data->Type);
    $statement->bindParam(":location", $template_data->Location);
    $statement->bindParam(":begin", $template_data->Begin);
    $statement->bindParam(":departure", $template_data->Departure);
    $statement->bindParam(":leave_dep", $template_data->Leave_dep);
    $statement->bindParam(":usergroup_id", $template_data->Usergroup_ID);

    if($statement->execute()){
        http_response_code(201);
        return true;
    }

    return false;
}

function updateDateTemplate($template_id, $template_data)
{
    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "UPDATE tblDatetemplates SET
    title=:title, description=:description, category=:category, type=:type, location=:location, begin=:begin, departure=:departure, leave_dep=:leave_dep, usergroup_id=:usergroup_id
    WHERE datetemplate_id=:datetemplate_id";

    $statement = $db_conn->prepare($query);
    $statement->bindParam(":datetemplate_id", $template_id);
    $statement->bindParam(":title", $template_data->Title);
    $statement->bindParam(":description", $template_data->Description);
    $statement->bindParam(":category", $template_data->Category);
    $statement->bindParam(":type", $template_data->Type);
    $statement->bindParam(":location", $template_data->Location);
    $statement->bindParam(":begin", $template_data->Begin);
    $statement->bindParam(":departure", $template_data->Departure);
    $statement->bindParam(":leave_dep", $template_data->Leave_dep);
    $statement->bindParam(":usergroup_id", $template_data->Usergroup_ID);

    if($statement->execute()){
        http_response_code(200);
        return true;
    }

    return false;
}
?>