<?php
class Event {
    private $conn;
    private $table_name = "spzroenkhausen_planer.tblEvents";

    public int $event_id;
    public string $type;
    public string $location;
    public string $date;
    public string $begin;
    public string $departure;
    public string $leave_dep;

    public function __construct(PDO $db)
    {
        $this->conn = $db;
    }

    function read($id, $filter) : PDOStatement
    {
        if ($id >= 0) {
            $query = "SELECT * FROM " . $this->table_name . " WHERE event_id = :event_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":event_id", $id);
        } else {
            switch($filter){
            case "current":
                $query = "SELECT * FROM " . $this->table_name . " WHERE date >= :_now AND accepted = 1 ORDER BY date";
                $stmt = $this->conn->prepare($query);
                $stmt->bindValue(":_now", date("Y-m-d"));
                break;
            case "past":
                $query = "SELECT * FROM " . $this->table_name . " WHERE date < :_now ORDER BY date";
                $stmt = $this->conn->prepare($query);
                $stmt->bindValue(":_now", date("Y-m-d"));
                break;
            default:
            case "all":
                $query = "SELECT * FROM " . $this->table_name . " ORDER BY date";
                $stmt = $this->conn->prepare($query);
                break;
            }
        }
        $stmt->execute();
        return $stmt;
    }

    function update($event_data) : bool
    {
        $query = "UPDATE " . $this->table_name . " SET type = :type, location = :location, date = :date, begin = :begin, departure = :departure, leave_dep = :leave_dep, accepted = :accepted WHERE event_id = :event_id";
        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":event_id", $event_data->Event_ID);
        $stmt->bindParam(":type", $event_data->Type);
        $stmt->bindParam(":location", $event_data->Location);
        $stmt->bindParam(":date", $event_data->Date);
        $stmt->bindParam(":begin", $event_data->Begin);
        $stmt->bindParam(":departure", $event_data->Departure);
        $stmt->bindParam(":leave_dep", $event_data->Leave_dep);
        $stmt->bindParam(":accepted", $event_data->Accepted);

        if($stmt->execute()){
            return true;
        }

        return false;
    }

    function create($event_data) : bool
    {
        $query = "INSERT INTO " . $this->table_name . " (type, location, date, begin, departure, leave_dep) VALUES (:type, :location, :date, :begin, :departure, :leave_dep)";
        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":type", $event_data->Type);
        $stmt->bindParam(":location", $event_data->Location);
        $stmt->bindParam(":date", $event_data->Date);
        $stmt->bindParam(":begin", $event_data->Begin);
        $stmt->bindParam(":departure", $event_data->Departure);
        $stmt->bindParam(":leave_dep", $event_data->Leave_dep);

        if($stmt->execute()){
            return true;
        }

        return false;
    }
}
?>