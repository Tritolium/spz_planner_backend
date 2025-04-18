<?php
class Database{
    private $host = "";
    private $db_name = "spzroenkhausen_planer";
    private $username = "spzroenkhausen_admin";
    private $password;
    public $conn;

    function __construct() {
        $env = parse_ini_file(__DIR__ . '/../.env');
        $this->host = $env['DB_HOST'];
        $this->password = $env['DB_PASS'];
    }


    /**
     * @return PDO Database connection
     */
    public function getConnection() : PDO {
        $this->conn = null;
    
        try {
            $this->createConnection();
        } catch (PDOException $exception) {
            $this->handleConnectionError($exception);
        }
    
        return $this->conn;
    }
    
    private function createConnection() {
        $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
        $this->conn->exec("set names utf8mb4");
    }
    
    private function handleConnectionError(PDOException $exception) {
        http_response_code(503);
        echo "Connection error: " . $exception->getMessage();
        exit();
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

function isAdmin($api_token) : bool
{
    $db = new Database();
    $connection = $db->getConnection();

    $query = "SELECT * FROM 
    (SELECT member_id from tblMembers tm 
    WHERE tm.api_token = :api_token) AS member
    left join tblUsergroupAssignments tua 
    on member.member_id = tua.member_id 
    left join tblUsergroups tu 
    on tua.usergroup_id = tu.usergroup_id
    where is_admin = 1";
    
    $statement = $connection->prepare($query);
    $statement->bindParam(":api_token", $api_token);

    if($statement->execute()){
        if($statement->rowCount() > 0){
            return true;
        }
    }

    return false;
}

function isMod($api_token) : bool
{
    $db = new Database();
    $connection = $db->getConnection();

    $query = "SELECT * FROM 
    (SELECT member_id from tblMembers tm 
    WHERE tm.api_token = :api_token) AS member
    left join tblUsergroupAssignments tua 
    on member.member_id = tua.member_id 
    left join tblUsergroups tu 
    on tua.usergroup_id = tu.usergroup_id
    where is_moderator = 1";
    
    $statement = $connection->prepare($query);
    $statement->bindParam(":api_token", $api_token);

    if($statement->execute()){
        if($statement->rowCount() > 0){
            return true;
        }
    }

    return isAdmin($api_token);
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
    $etag = '"' . md5(json_encode($data)) . '"';

    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === $etag) {
        header('ETag: ' . $etag);
        header('Content-Length: 0');
        http_response_code(304);
        error_log("304 Not Modified: " . json_encode($data));
        exit();
    }

    header('ETag: ' . $etag);
    header('Content-Type: application/json');
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
}
?>