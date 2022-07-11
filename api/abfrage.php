<?php
include_once './config/database.php';
include_once './model/abfrage.php';

$database = new Database();

$db_conn = $database->getConnection();

$abfrage = new Abfrage($db_conn);

$data = json_decode(file_get_contents("php://input"));

header('content-type: application/json');

switch($_SERVER['REQUEST_METHOD'])
{
    case 'POST':
        // INSERT
        if(!empty($data->name))
        {
            $abfrage->u_name = $data->name;
            $abfrage->ft_oeling = $data->ft_oeling;
            $abfrage->sf_ennest = $data->sf_ennest;

            if($abfrage->create())
            {
                response(201, "");
            } else {
                response(500, "");
            }
        } else {
            response(400, "");
        }
        break;
    case 'GET':
        // SELECT
        $stmt = $abfrage->read();
        $num = $stmt->rowCount();

        if($num > 0) {
            $abfrage_arr = array();
            while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                extract($row);
                $abfrage_item = array(
                    "Name" => $u_name,
                    "ft_oeling" => intval($ft_oeling),
                    "sf_ennest" => intval($sf_ennest),
                    "timestamp" => $timestamp
                );
                array_push($abfrage_arr, $abfrage_item);
            }

            response_with_data(200, $abfrage_arr);
        } else {
            http_response_code(204);
        }
        break;
    case 'DELETE':
        // DELETE
        break;
}
?>