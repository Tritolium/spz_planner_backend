<?php
include_once './config/database.php';

header('content-type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, OPTIONS');

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
    case 'GET':
        if(isAdmin($_GET['api_token'])){
            getSubscriptions();
        }
        break;
    case 'PUT':
        updateSubscription($_GET['api_token'], $data);
        break;
}

function getSubscriptions()
{
    $database = new Database();
    $db_conn = $database->getConnection();

    $query = "SELECT * FROM tblSubscription ORDER BY member_id";
    $statement = $db_conn->prepare($query);
    if($statement->execute()){
        $subscriptions_array = array();
        if($row = $statement->fetch(PDO::FETCH_ASSOC)){
            extract($row);
            $current_member_id = $member_id;
            $subscriptions = array();
            $subscription = array(
                "endpoint"  => $endpoint,
                "authToken" => $authToken,
                "publicKey" => $publicKey
            );
            array_push($subscriptions, $subscription);
            while($row = $statement->fetch(PDO::FETCH_ASSOC)){
                if($current_member_id == $row['member_id']){
                    extract($row);
                    $subscription = array(
                        "endpoint"  => $endpoint,
                        "authToken" => $authToken,
                        "publicKey" => $publicKey
                    );
                    array_push($subscriptions, $subscription);
                } else {
                    array_push($subscriptions_array, array($current_member_id => $subscriptions));
                    extract($row);
                    $current_member_id = $member_id;
                    $subscriptions = array();
                    $subscription = array(
                        "endpoint"  => $endpoint,
                        "authToken" => $authToken,
                        "publicKey" => $publicKey
                    );
                    array_push($subscriptions, $subscription);
                }
            }
            array_push($subscriptions_array, array($current_member_id => $subscriptions));

            response_with_data(200, $subscriptions_array);
        }
    } else {
        http_response_code(500);
    }

}

function updateSubscription($api_token, $data)
{
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

    $query = "INSERT INTO tblSubscription (endpoint, authToken, publicKey, member_id, last_updated) VALUES (:endpoint, :authToken, :publicKey, :member_id, current_timestamp()) ON DUPLICATE KEY UPDATE authToken=:authToken, publicKey=:publicKey, member_id=:member_id, last_updated=current_timestamp()";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":endpoint", $data->endpoint);
    $statement->bindParam(":authToken", $data->authToken);
    $statement->bindParam(":publicKey", $data->publicKey);
    $statement->bindValue(":member_id", $member_id);
    $statement->execute();
}

?>