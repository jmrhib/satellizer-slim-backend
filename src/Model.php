<?php
class Model {
    
    protected $connection;

    public function __construct($database){ 

        $server = $database['server']; 
        $user = $database['user'];
        $pass = $database['password'];
        $database = $database['database']; 

        try {
            $this->connection = new PDO("mysql:host=$server;dbname=$database;charset=utf8", $user, $pass);
            $this->connection->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
        }
        catch(PDOException $e){
            echo $e;
        }

    }

    function __destruct() {
        $this->connection = null;
    }

}

?>