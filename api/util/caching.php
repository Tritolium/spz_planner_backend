<?php
include_once './config/database.php';

function checkIfModified($tables) : void
{
    $modifiedsince = NULL;
    if(isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])){
        $modifiedsince = new DateTime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
    }

    $database = new Database();
    $db_conn = $database->getConnection();

    date_default_timezone_set('Europe/London');
    $lastmodified = new DateTime();

    $query = "SELECT max(UPDATE_TIME) FROM information_schema.tables WHERE TABLE_NAME = '" . $tables[0] . "'";

    if(sizeof($tables) > 1){
        foreach($tables as $table){
            $query .= " OR TABLE_NAME = '" . $table . "'";
        }
    }

    $statement = $db_conn->prepare($query);
    $statement->execute();
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    if($row['max(UPDATE_TIME)'] != NULL){
        $lastmodified = new DateTime($row['max(UPDATE_TIME)'] . ' Europe/Berlin');
        if($modifiedsince >= $lastmodified){
            http_response_code(304);
            exit();
        }
    }

    $lastmodified->setTimezone(new DateTimeZone("Europe/London"));
    header('DB-Last-Modified: ' . $lastmodified->format('D, d M y H:i:s T'));
}
?>