<?php

require_once __DIR__ . '/../config/database.php';

$body = file_get_contents('php://input');
$data = json_decode($body, true);

if (!isset($data['email'])) {
    http_response_code(400);
    echo "Error 400: Bad request.";
    exit();
}

$email = $data['email'];
$challenge_cli = $data['response'];

$db = new Database();
$connection = $db->getConnection();

$query = "SELECT challenge, secret FROM tblAuth WHERE email = :email";
$statement = $connection->prepare($query);
$statement->bindParam(":email", $email);

if ($statement->execute()) {
    $result = $statement->fetch(PDO::FETCH_ASSOC);
    $challenge_db = hash_hmac('sha256', $result['challenge'], $result['secret']);

    if ($challenge_cli === $challenge_db) {
        $token = bin2hex(random_bytes(16));

        $query = "UPDATE tblAuth SET challenge = NULL, token = :token WHERE email = :email";
        $statement = $connection->prepare($query);
        $statement->bindParam(":token", $token);
        $statement->bindParam(":email", $email);

        if ($statement->execute()) {
            http_response_code(200);
            echo json_encode(array("token" => $token));
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