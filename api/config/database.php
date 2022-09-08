<?php
class Database{
    private $host = "localhost";
    private $db_name = "spzroenkhausen_planer";
    private $username = "spzroenkhausen_admin";
    private $password = "Spielmannszug";
    public $conn;


    /**
     * @return PDO Database connection
     */
    public function getConnection() : PDO{

        $this->conn = null;

        try{
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8mb4");
        } catch(PDOException $exception){
            echo "Connection error: " . $exception->getMessage();
        }

        return $this->conn;
    }
}

/** 
 * @param string $api_token
 * @return int Level of authorization
 */
function authorize($api_token) : int
{
    $db = new Database();
    $connection = $db->getConnection();
    $statement = $connection->prepare('SELECT auth_level FROM tblMembers WHERE api_token = :token');
    $statement->bindParam(":token", $api_token);

    if($statement->execute()){
        if($statement->rowCount() == 1){
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            extract($row);
            return intval($auth_level);
        }
    }

    return 0;
}

/**
 * @param int $code
 * @param string $response
 */
function response($code, $response) 
{
    http_response_code($code);
    echo json_encode(array("response" => $response));
}

/**
 * @param int $code
 * @param string $response
 */
function response_with_data($code, $data)
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
}
?>