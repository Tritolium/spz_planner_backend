<?php

function apitokenToMemberID($api_token) {
    require_once __DIR__ . '/database.php';

    $database = new Database();
    $conn = $database->getConnection();

    $query = "SELECT member_id FROM tblMembers WHERE api_token = :api_token";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':api_token', $api_token);

    if (!$stmt->execute()) {
        return null;
    }

    if ($stmt->rowCount() < 1) {
        return null;
    }

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row['member_id'];
}

?>