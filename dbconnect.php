<?php
  
class DBConnect {
  
    private  $con;

    // constructor
    function __construct() {  
    }
  
    // destructor
    function __destruct() {
        // $this->close();
    }
  
    // Connecting to database
    public function connect() {
        require_once 'config.php';
        // connecting to mysql
        $this->con = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_DATABASE, 3306, '') or die ('Could not connect to the database server' . mysqli_connect_error());  
        // return database handler
        return $this->con;
    }
  
    // Closing database connection
    public function close() {
        $this->con->close();
    }
  
} 
?>