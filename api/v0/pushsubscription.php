<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS, DELETE');
header("Content-Type: application/json; charset=UTF-8");

$request = $_SERVER['REQUEST_URI'];
// remove /api/v0 from the request
$request = str_replace('/api/v0', '', $request);
// remove query string from the request
$request = explode('?', $request)[0];
// split the request to get the id
$request_exploded = explode('/', $request);

if (isset($request_exploded[2])) {
    $subscription_id = $request_exploded[2];
} else {
    $subscription_id = null;
}

if (isset($_GET['member_id'])) {
    $member_id = $_GET['member_id'];
} else {
    $member_id = null;
}

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        getSubscription($subscription_id, $member_id);
        break;
    case 'OPTIONS':
        http_response_code(200);
        break;
    case 'DELETE':
        deleteSubscription($subscription_id);
        break;
    default:
        http_response_code(405);
        break;
}

function getSubscription($subscription_id, $member_id) {

    require_once __DIR__ . '/../config/database.php';

    $database = new Database();
    $conn = $database->getConnection();

    if ($subscription_id == null && $member_id == null) {
        $query = "SELECT * FROM tblSubscription";

        $stmt = $conn->prepare($query);
        
        if (!$stmt->execute()) {
            http_response_code(500);
            echo json_encode(array("message" => "Unable to get subscriptions."));
            return;
        }

        if ($stmt->rowCount() == 0) {
            http_response_code(204);
            echo json_encode(array("message" => "No subscriptions found."));
        }

        $subscriptions = array();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            array_push($subscriptions, $row);
        }

        http_response_code(200);
        echo json_encode($subscriptions);

    } else if ($subscription_id != null && $member_id == null) {
        $query = "SELECT * FROM tblSubscription WHERE subscription_id = :subscription_id";

        $stmt = $conn->prepare($query);
        $stmt->bindParam(':subscription_id', $subscription_id);

        if (!$stmt->execute()) {
            http_response_code(500);
            echo json_encode(array("message" => "Unable to get subscription."));
            return;
        }

        if ($stmt->rowCount() == 0) {
            http_response_code(404);
            echo json_encode(array("message" => "Subscription not found."));
            return;
        }

        $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

        http_response_code(200);
        echo json_encode($subscription);

    } else if ($subscription_id == null && $member_id != null) {
        $query = "SELECT * FROM tblSubscription WHERE member_id = :member_id";

        $stmt = $conn->prepare($query);
        $stmt->bindParam(':member_id', $member_id);

        if (!$stmt->execute()) {
            http_response_code(500);
            echo json_encode(array("message" => "Unable to get subscription."));
            return;
        }

        if ($stmt->rowCount() == 0) {
            http_response_code(204);
            echo json_encode(array("message" => "Subscription not found."));
            return;
        }

        $subscriptions = array();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            array_push($subscriptions, $row);
        }

        http_response_code(200);
        echo json_encode($subscriptions);
    }
}

function deleteSubscription($subscription_id) {
    if ($subscription_id == null) {
        http_response_code(400);
        echo json_encode(array("message" => "Subscription ID is required."));
        return;
    }

    require_once __DIR__ . '/../config/database.php';

    $database = new Database();
    $conn = $database->getConnection();

    $query = "DELETE FROM tblSubscription WHERE subscription_id = :subscription_id";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':subscription_id', $subscription_id);

    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(array("message" => "Unable to delete subscription."));
        return;
    }

    if ($stmt->rowCount() == 0) {
        http_response_code(404);
        echo json_encode(array("message" => "Subscription not found."));
        return;
    }

    http_response_code(200);
}

?>