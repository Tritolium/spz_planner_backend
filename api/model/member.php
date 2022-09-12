<?php
class Member {
    private $conn;
    private $table_name = "spzroenkhausen_planer.tblMembers";

    public string $forename;
    public string $surname;

    public function __construct(PDO $db)
    {
        $this->conn = $db;
    }

    function create($member_data) : bool
    {
        $query = "INSERT INTO " . $this->table_name . " (forename, surname, auth_level, nicknames, instrument, api_token) VALUES (:fname, :sname, :auth, :nick, :instrument, :api)";
        $stmt = $this->conn->prepare($query);
        echo json_encode($member_data);
        $stmt->bindParam(":fname", $member_data->Forename);
        $stmt->bindParam(":sname", $member_data->Surname);
        $stmt->bindParam(":auth", $member_data->Auth_level);
        $stmt->bindParam(":nick", $member_data->Nicknames);
        $stmt->bindParam(":instrument", $member_data->Instrument);
        $stmt->bindValue(":api", hash("md5", $member_data->Forename . $member_data->Surname . $member_data->Auth_level . $member_data->Nicknames));
        
        if($stmt->execute())
        {
            return true;
        }

        return false;
    }

    function update($member_data) : bool
    {
        $query = "UPDATE " . $this->table_name . " SET forename = :fname, surname = :sname, auth_level = :auth, nicknames = :nick, instrument= :instrument, api_token = :api WHERE member_id = :m_id";
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(":m_id",  $member_data->Member_ID);
        $stmt->bindParam(":fname", $member_data->Forename);
        $stmt->bindParam(":sname", $member_data->Surname);
        $stmt->bindParam(":auth", $member_data->Auth_level);
        $stmt->bindParam(":nick", $member_data->Nicknames);
        $stmt->bindParam(":instrument", $member_data->Instrument);
        $stmt->bindValue(":api", hash("md5", $member_data->Forename . $member_data->Surname . $member_data->Auth_level . $member_data->Nicknames));

        if($stmt->execute()){
            return true;
        }

        return false;
    }

    function delete() : bool
    {
        return false;
    }
}
?>