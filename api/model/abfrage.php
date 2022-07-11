<?php
class Abfrage {
    private $conn;
    private $table_name = "spzroenkhausen_planer.tblAbfrage";

    public string $u_name;
    public int $ft_oeling;
    public int $sf_ennest;
    public date $timestamp;

    public function __construct(PDO $db)
    {
        $this->conn = $db;
    }

    function read() : PDOStatement
    {
        $query = "SELECT * FROM " . $this->table_name . " ORDER BY u_name, timestamp";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    function create() : bool
    {
        $query = "INSERT INTO " . $this->table_name . " (u_name, ft_oeling, sf_ennest) VALUES (:u_name, :ft_oeling, :sf_ennest)";
        $stmt = $this->conn->prepare($query);
        $this->u_name=htmlspecialchars(strip_tags($this->u_name));
        $this->ft_oeling=htmlspecialchars(strip_tags($this->ft_oeling));
        $this->sf_ennest=htmlspecialchars(strip_tags($this->sf_ennest));
        $stmt->bindParam(":u_name", $this->u_name);
        $stmt->bindParam(":ft_oeling", $this->ft_oeling);
        $stmt->bindParam(":sf_ennest", $this->sf_ennest);
        
        if($stmt->execute())
        {
            return true;
        }

        return false;
    }

    function delete() : bool
    {
        $query = "DELETE FROM " . $this->table_name . " WHERE name=:name";
        $stmt = $this->conn->prepare($query);
        $this->name=htmlspecialchars(strip_tags($this->name));
        $stmt->bindParam(":name", $this->name);

        if($stmt->execute())
        {
            return true;
        }

        return false;
    }
}
?>