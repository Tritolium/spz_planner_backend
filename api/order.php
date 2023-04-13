<?php
include_once './config/database.php';

header('content-type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: if-modified-since');

$data = json_decode(file_get_contents('php://input'));

switch($_SERVER['REQUEST_METHOD'])
{
    case 'OPTIONS':
        http_response_code(200);
        break;
    case 'POST':
        if(newOrder($_GET['api_token'], $data)){
            http_response_code(201);
        } else {
            http_response_code(500);
        }
        break;
    case 'PUT':
        if(updateOrder($_GET['api_token'], $_GET['id'], $data)){
            http_response_code(200);
        } else {
            http_response_code(500);
        }
        break;
    case 'GET':
        if (!getOrders($_GET['api_token'], isset($_GET['own']))) {
            http_response_code(500);
        }
        break;
}

function newOrder($api_token, $data){
    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "SELECT member_id FROM tblMembers WHERE api_token = :token";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":token", $api_token);
    $statement->execute();

    if($statement->rowCount() < 1){
        http_response_code(403);
        exit();
    }
    
    $member_id = $statement->fetch(PDO::FETCH_ASSOC)['member_id'];
    echo $member_id;
    $query = "INSERT INTO tblOrders (member_id, article, size, count, placed, info, order_state) VALUES (:member_id, :article, :size, :count, CURDATE(), :info, 0)";

    $statement = $db_conn->prepare($query);
    $statement->bindParam(":member_id", $member_id);
    $statement->bindParam(":article", $data->Article);
    $statement->bindParam(":size", $data->Size);
    $statement->bindParam(":count", $data->Count);
    $statement->bindParam(":info", $data->Info);

    if(!$statement->execute()){
        print_r($statement->errorInfo());
        return false;
    }

    return true;
}

function updateOrder($api_token, $id, $data){
    $database = new Database();
    $db_conn = $database->getConnection();

    switch($data->Order_State){
    case 0:
        $query = "UPDATE tblOrders SET order_state=1, ordered=CURDATE() WHERE order_id = :order_id";
        break;
    case 1:
        $query = "UPDATE tblOrders SET order_state=2 WHERE order_id = :order_id";
        break;
    default:
        http_response_code(400);
        exit();
    }

    $statement = $db_conn->prepare($query);
    $statement->bindParam(":order_id", $id);

    $statement->execute();
    return true;
}

function getOrders($api_token, $own){
    $database = new Database();
    $db_conn = $database->getConnection();

    if($own){
        $query = "SELECT forename, surname, order_id, article, size, count, placed, ordered, info, order_state FROM tblOrders JOIN tblMembers ON tblOrders.member_id=tblMembers.member_id WHERE api_token = :token ORDER BY placed, order_id";
    } else {
        $query = "SELECT forename, surname, order_id, article, size, count, placed, ordered, info, order_state FROM tblOrders JOIN tblMembers ON tblOrders.member_id=tblMembers.member_id ORDER BY placed, order_id";
    }
    

    $statement = $db_conn->prepare($query);
    $statement->bindParam(":token", $api_token);

    if(!$statement->execute()){
        return false;
    }

    if($statement->rowCount() < 1){
        http_response_code(204);
        exit();
    }

    $orders_array = array();

    while($row = $statement->fetch(PDO::FETCH_ASSOC)){
        extract($row);
        $order = array(
            "Forename"      => $forename,
            "Surname"       => $surname,
            "Order_ID"      => $order_id,
            "Article"       => $article,
            "Size"          => $size,
            "Count"         => $count,
            "Placed"        => $placed,
            "Ordered"       => $ordered,
            "Info"          => $info,
            "Order_State"   => intval($order_state)
        );

        array_push($orders_array, $order);
    }

    response_with_data(200, $orders_array);
    return true;
}
?>