<?php
class Config{

    private $database_info;
    private $secrets;
    private $auth_header;

    public function __construct(){ 

        $this->database_info = array(
            'server' => '127.0.0.1',
            'user' => '',
            'password' => '', 
            'database' => ''
        );
        
        $this->secrets = array(
            'TOKEN_SECRET' => '',
            'TWITTER_KEY' => '',
            'TWITTER_SECRET' => '',
            'FACEBOOK_SECRET' => ''
        );
        
        $this->auth_header = 'Authorization';

    }
    
    public function getAuthHeader(){
        return $this->auth_header;
    }
    
    public function getDatabaseInfo(){
        return $this->database_info;
    }

    public function getSecret($secret_name){
        return $this->secrets[$secret_name];
    }

    
}



?>