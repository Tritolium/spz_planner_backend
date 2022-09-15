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

    function read($id, $filter, $api_token) : PDOStatement
    {
        //TODO get all events for admin user
        if ($id >= 0) {
            $query = "SELECT * FROM " . $this->table_name . " WHERE event_id = :event_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":event_id", $id);
        } else {
            switch($filter){
            case "current":
                $query = "SELECT event_id, type, location, date, accepted, begin, departure, leave_dep, t.usergroup_id FROM tblEvents t 
                LEFT JOIN tblUsergroupAssignments t2 
                ON t.usergroup_id = t2.usergroup_id
                LEFT JOIN tblMembers t4 
                ON t2.member_id = t4.member_id 
                WHERE api_token = :api_token 
                AND accepted=1
                AND date >= curdate()
                ORDER BY date, begin";

                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(":api_token", $api_token);
                break;
            case "past":
                $query = "SELECT event_id, type, location, date, accepted, begin, departure, leave_dep, t.usergroup_id FROM tblEvents t 
                LEFT JOIN tblUsergroupAssignments t2 
                ON t.usergroup_id = t2.usergroup_id
                LEFT JOIN tblMembers t4 
                ON t2.member_id = t4.member_id 
                WHERE api_token = :api_token 
                AND accepted=1
                AND date < curdate()
                ORDER BY date, begin";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(":api_token", $api_token);
                break;
            default:
            case "all":
                $query = "SELECT event_id, type, location, date, accepted, begin, departure, leave_dep, t.usergroup_id FROM tblEvents t 
                LEFT JOIN tblUsergroupAssignments t2 
                ON t.usergroup_id = t2.usergroup_id
                LEFT JOIN tblMembers t4 
                ON t2.member_id = t4.member_id 
                WHERE api_token = :api_token
                ORDER BY date, begin";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(":api_token", $api_token);
                break;
            }
        }
        $stmt->execute();
        return $stmt;
    }

    function update($event_data) : bool
    {
        $query = "UPDATE " . $this->table_name . " SET type = :type, location = :location, date = :date, begin = :begin, departure = :departure, leave_dep = :leave_dep, accepted = :accepted, usergroup_id = :usergroup_id WHERE event_id = :event_id";
        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":event_id", $event_data->Event_ID);
        $stmt->bindParam(":type", $event_data->Type);
        $stmt->bindParam(":location", $event_data->Location);
        $stmt->bindParam(":date", $event_data->Date);
        $stmt->bindParam(":begin", $event_data->Begin);
        $stmt->bindParam(":departure", $event_data->Departure);
        $stmt->bindParam(":leave_dep", $event_data->Leave_dep);
        $stmt->bindParam(":accepted", $event_data->Accepted);
        $stmt->bindParam(":usergroup_id", $event_data->Usergroup_ID);

        if($stmt->execute()){
            return true;
        }

        return false;
    }

    function create($event_data) : bool
    {
        $query = "INSERT INTO " . $this->table_name . " (type, location, date, begin, departure, leave_dep, usergroup_id) VALUES (:type, :location, :date, :begin, :departure, :leave_dep, :usergroup_id)";
        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":type", $event_data->Type);
        $stmt->bindParam(":location", $event_data->Location);
        $stmt->bindParam(":date", $event_data->Date);
        $stmt->bindParam(":begin", $event_data->Begin);
        $stmt->bindParam(":departure", $event_data->Departure);
        $stmt->bindParam(":leave_dep", $event_data->Leave_dep);
        $stmt->bindParam(":usergroup_id", $event_data->Usergroup_ID);

        if(!$stmt->execute()){
            return false;
        }

        $event_id = $this->conn->lastInsertId();

        $query = "SELECT member_id FROM tblAbsence WHERE from_date <= :event_date AND until_date >= :event_date";
        $statement = $this->conn->prepare($query);
        $statement->bindParam(":event_date", $event_data->Date);
        $statement->execute();
        
        $members = array();

        while($row = $statement->fetch(PDO::FETCH_ASSOC)){
            array_push($members, $row['member_id']);
        }

        for($i = 0; $i < count($members); $i++){
            updateSingleAttendence($members[$i], $event_id, 0);
        }

        return true;
    }
}

function updateSingleAttendence($member_id, $event_id, $attendence)
{
    $database = new Database();
    $db_conn = $database->getConnection();
    $query = "SELECT * FROM tblAttendence WHERE member_id=:member_id AND event_id=:event_id";
    $statement = $db_conn->prepare($query);
    $statement->bindParam(":member_id", $member_id);
    $statement->bindParam(":event_id", $event_id);
    $statement->execute();
    if($statement->rowCount() < 1){
        $query = "INSERT INTO tblAttendence (attendence, member_id, event_id) VALUES (:attendence, :member_id, :event_id)";
        $statement = $db_conn->prepare($query);
        $statement->bindParam(":attendence", $attendence);
        $statement->bindParam(":member_id", $member_id);
        $statement->bindParam(":event_id", $event_id);
    } else {
        $query = "UPDATE tblAttendence SET attendence=:attendence WHERE member_id=:member_id AND event_id=:event_id";
        $statement = $db_conn->prepare($query);
        $statement->bindParam(":attendence", $attendence);
        $statement->bindParam(":member_id", $member_id);
        $statement->bindParam(":event_id", $event_id);
    }
    
    $statement->execute();
}
?>