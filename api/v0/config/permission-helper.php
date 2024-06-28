<?php
require_once __DIR__ . '/database.php';

function hasPermission($api_token, $permission, $association_id = null) {
    $database = new Database();
    $db_conn = $database->getConnection();

    if ($association_id == null){
        $query = "SELECT permission_id FROM tblUserRoles
            LEFT JOIN tblRolePermissions
            ON tblUserRoles.role_id=tblRolePermissions.role_id
            LEFT JOIN tblMembers
            ON tblUserRoles.member_id=tblMembers.member_id
            WHERE api_token=:api_token
            AND permission_id=:permission_id";
    } else {
        $query = "SELECT permission_id FROM tblUserRoles
            LEFT JOIN tblRolePermissions
            ON tblUserRoles.role_id=tblRolePermissions.role_id
            LEFT JOIN tblMembers
            ON tblUserRoles.member_id=tblMembers.member_id
            WHERE api_token=:api_token
            AND permission_id=:permission_id
            AND association_id=:association_id";
    }

    $stmt = $db_conn->prepare($query);
    $stmt->bindParam(':api_token', $api_token);
    $stmt->bindParam(':permission_id', $permission);

    if ($association_id != null) {
        $stmt->bindParam(':association_id', $association_id);
    }

    if (!$stmt->execute()) {
        http_response_code(500);
        exit();
    }

    return $stmt->rowCount() > 0;
}
?>