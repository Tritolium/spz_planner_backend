<?php

require_once __DIR__ . '/../config/database.php';

$body = file_get_contents('php://input');
$data = json_decode($body, true);

if (!isset($data['email']) || !isset($data['token'])) {
    http_response_code(400);
    echo "Error 400: Bad request.";
    exit();
}

$email = $data['email'];
$token = $data['token'];

$db = new Database();
$connection = $db->getConnection();

$query = "SELECT token FROM tblAuth WHERE email = :email";
$statement = $connection->prepare($query);
$statement->bindParam(":email", $email);

if ($statement->execute()) {
    $result = $statement->fetch(PDO::FETCH_ASSOC);

    if ($result['token'] === $token) {
        $query = "UPDATE tblAuth SET token = NULL WHERE email = :email";
        $statement = $connection->prepare($query);
        $statement->bindParam(":email", $email);

        if ($statement->execute()) {
            http_response_code(200);
            echo "Logout successful.";
        } else {
            http_response_code(500);
            echo "Error 500: Internal server error.";
        }
    } else {
        http_response_code(401);
        echo "Error 401: Unauthorized.";
    }
} else {
    http_response_code(500);
    echo "Error 500: Internal server error.";
}

?>