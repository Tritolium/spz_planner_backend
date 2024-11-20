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

$challenge = bin2hex(random_bytes(16));

$db = new Database();
$connection = $db->getConnection();

$query = "UPDATE tblAuth SET challenge = :challenge WHERE email = :email";
$statement = $connection->prepare($query);
$statement->bindParam(":challenge", $challenge);
$statement->bindParam(":email", $email);

if ($statement->execute()) {
    http_response_code(200);
    echo json_encode(array("challenge" => $challenge));
} else {
    http_response_code(500);
    echo "Error 500: Internal server error.";
}
?>