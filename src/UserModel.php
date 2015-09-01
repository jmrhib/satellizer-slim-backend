<?php

Class UserModel extends Model{

    public function __construct($database){ 
        parent::__construct($database);
    }

    function __destruct() {
        parent::__destruct();
    }

    public function createUser($email, $password, $displayName){
        try {
            $data = array('email'=>$email, 'password'=>$password, 'displayName'=>$displayName);
            $sql = 'INSERT INTO users (email, password, displayName) value (:email, :password, :displayName)';
            $statement = $this->connection->prepare($sql);
            $statement->execute($data);
        }
        catch(PDOException $e) {
            echo $e;
        }
        return $this->connection->lastInsertId();
    }

    public function createUserTwitter($twitter_id, $displayName){
        try {
            $data = array('twitter'=>$twitter_id, 'displayName'=>$displayName);
            $sql = 'INSERT INTO users (twitter, displayName) value (:twitter, :displayName)';
            $statement = $this->connection->prepare($sql);
            $statement->bindParam(':twitter', $twitter_id);
            $statement->bindParam(':displayName', $displayName);
            $statement->execute($data);
        }
        catch(PDOException $e) {
            echo $e;
        }
        return $this->connection->lastInsertId();
    }

    public function createUserFacebook($facebook_id, $displayName){
        try {
            $data = array('facebook'=>$facebook_id, 'displayName'=>$displayName);
            $sql = 'INSERT INTO users (facebook, displayName) value (:facebook, :displayName)';
            $statement = $this->connection->prepare($sql);
            $statement->bindParam(':facebook_id', $facebook_id);
            $statement->bindParam(':displayName', $displayName);
            $statement->execute($data);
        }
        catch(PDOException $e) {
            echo $e;
        }
        return $this->connection->lastInsertId();
    }

    public function updateDisplayName($user_id, $displayName){
        try {
            $statement = $this->connection->prepare('UPDATE users SET displayName=:displayName WHERE id=:id');
            $statement->bindParam(':displayName', $displayName);
            $statement->bindParam(':id', $user_id);
            $statement->execute();
        }
        catch(PDOException $e) {
            echo $e;
        }
    }

    public function updateEmail($user_id, $email){
        try {
            $statement = $this->connection->prepare('UPDATE users SET email=:email WHERE id=:id');
            $statement->bindParam(':email', $email);
            $statement->bindParam(':id', $user_id);
            $statement->execute();
        }
        catch(PDOException $e) {
            echo $e;
        }
    }

    public function getAuthProvider($user_id){
        try {
            $statement = $this->connection->prepare('SELECT * FROM users WHERE id=:user_id');
            $statement->bindParam(':user_id', $user_id);
            $statement->execute();
            $statement->setFetchMode(PDO::FETCH_ASSOC);
            $results = $statement->fetchAll();
            if(count($results) === 1){
                if(!empty($results[0]['twitter'])){
                    return 'twitter';
                }
                if(!empty($results[0]['facebook'])){
                    return 'facebook';
                }   
                return 'local';
            }
            else {
                return null;
            }
        }
        catch(PDOException $e) {
            echo $e;
        }
    }

    public function getUserWithId($user_id){
        try {
            $statement = $this->connection->prepare('SELECT 
            id, 
            email, 
            displayName,
            facebook,
            foursquare,
            github,
            google,
            linkedin,
            twitter
            FROM users WHERE id=:user_id');
            $statement->bindParam(':user_id', $user_id);
            $statement->execute();
            $statement->setFetchMode(PDO::FETCH_ASSOC);
            $results = $statement->fetchAll();
            if(count($results) === 1){
                return $results[0];
            }
            else {
                return null;
            }
        }
        catch(PDOException $e) {
            echo $e;
        }
    }

    public function updateUser($user_id, $email, $displayName){
        try {
            if(empty($email)){
                $statement = $this->connection->prepare('UPDATE users SET displayName=:displayName WHERE id=:user_id');
                $statement->bindParam(':displayName', $displayName);
                $statement->bindParam(':user_id', $user_id);
                $statement->execute();
            }
            else {
                $statement = $this->connection->prepare('UPDATE users SET email=:email, displayName=:displayName WHERE id=:user_id');
                $statement->bindParam(':email', $email);
                $statement->bindParam(':displayName', $displayName);
                $statement->bindParam(':user_id', $user_id);
                $statement->execute();
            }

        }
        catch(PDOException $e){
            echo $e;
        }
    }

    public function getUserWithEmail($email){
        try {
            $statement = $this->connection->prepare('SELECT 
            id, 
            email, 
            displayName,
            facebook,
            foursquare,
            github,
            google,
            linkedin,
            twitter
            FROM users WHERE email=:email');
            $statement->bindParam(':email', $email);
            $statement->execute();
            $statement->setFetchMode(PDO::FETCH_ASSOC);
            $results = $statement->fetchAll();

            if(count($results) === 1){
                return $results[0];
            }
            else if(count($results) > 1){
                throw new MultipleUsersWithEmailException();
            }
            else {
                throw new UserNotFoundException();
            }
        }
        catch(PDOException $e) {
            echo $e;
        }
    }

    //TODO make generic "getUserSocial(provider)"
    public function getUserTwitter($twitter_id){
        try {
            $statement = $this->connection->prepare('SELECT 
            id, 
            email, 
            displayName,
            facebook,
            foursquare,
            github,
            google,
            linkedin,
            twitter
            FROM users WHERE twitter=:twitter_id');
            $statement->bindParam(':twitter_id', $twitter_id);
            $statement->execute();
            $statement->setFetchMode(PDO::FETCH_ASSOC);
            $results = $statement->fetchAll();

            if(count($results) === 1){
                return $results[0];
            }
            else {
                throw new UserNotFoundException();
            }
        }
        catch(PDOException $e) {
            echo $e;
        }
    }

    //TODO make generic "getUserSocial(provider)"
    public function getUserFacebook($facebook_id){
        try {
            $statement = $this->connection->prepare('SELECT 
            id, 
            email, 
            displayName,
            facebook,
            foursquare,
            github,
            google,
            linkedin,
            twitter
            FROM users WHERE facebook=:facebook_id');
            $statement->bindParam(':facebook_id', $facebook_id);
            $statement->execute();
            $statement->setFetchMode(PDO::FETCH_ASSOC);
            $results = $statement->fetchAll();

            if(count($results) === 1){
                return $results[0];
            }
            else {
                return null;
            }
        }
        catch(PDOException $e) {
            echo $e;
        }
    }

    public function isUserLocal($email){
        $user = $this->getUserWithEmail($email);
        return (!$user['facebook'] 
                && !$user['foursquare']
                && !$user['github']
                && !$user['google']
                && !$user['linkedin']
                && !$user['twitter']);
    }

    public function userWithDisplayNameExists($displayName, $user_id=-1){ 
        try {
            $statement = $this->connection->prepare('SELECT * FROM users WHERE displayName=:displayName AND id<>:user_id');
            $statement->bindParam(':displayName', $displayName);
            $statement->bindParam(':user_id', $user_id);
            $statement->execute();
            $statement->setFetchMode(PDO::FETCH_ASSOC);
            $results = $statement->fetchAll();
        }
        catch(PDOException $e) {
            echo $e;
        }

        if(count($results) == 1){
            return true;
        }
        else {
            return false;
        }
    }

    public function userWithEmailExists($email, $user_id=-1){ 
        try {
            $statement = $this->connection->prepare('SELECT * FROM users WHERE email=:email AND id<>:user_id');
            $statement->bindParam(':email', $email);
            $statement->bindParam(':user_id', $user_id);
            $statement->execute();
            $statement->setFetchMode(PDO::FETCH_ASSOC);
            $results = $statement->fetchAll();
        }
        catch(PDOException $e) {
            echo $e;
        }

        if(count($results) == 1){
            return true;
        }
        else {
            return false;
        }
    }

    public function getDisplayName($user_id){
        try {
            $statement = $this->connection->prepare('SELECT displayName FROM users WHERE id=:user_id');
            $statement->bindParam(':user_id', $user_id);
            $statement->execute();
            $statement->setFetchMode(PDO::FETCH_ASSOC);
            $results = $statement->fetchAll();

            if(count($results) === 1){
                return $results[0]['displayName'];
            }
            else {
                throw new UserNotFoundException();
            }
        }
        catch(PDOException $e) {
            echo $e;
        }
    }

    public function getUserId($email){
        try {
            $statement = $this->connection->prepare('SELECT id FROM users WHERE email=:email');
            $statement->bindParam(':email', $email);
            $statement->execute();
            $statement->setFetchMode(PDO::FETCH_ASSOC);
            $results = $statement->fetchAll();

            if(count($results) === 1){
                return $results[0]['id'];
            }
            else if(count($results) > 1){
                throw new MultipleUsersWithEmailException();
            }
            else {
                throw new UserNotFoundException();
            }
        }
        catch(PDOException $e) {
            echo $e;
        }
    }

    public function hasTwitter($twitter_id){
        try {
            $statement = $this->connection->prepare('SELECT * FROM users WHERE twitter=:twitter_id');
            $statement->bindParam(':twitter_id', $twitter_id);
            $statement->execute();
            $statement->setFetchMode(PDO::FETCH_ASSOC);
            $results = $statement->fetchAll();
            return (count($results) > 0);
        }
        catch(PDOException $e) {
            echo $e;
        }
    }

    public function linkTwitter($user_id, $twitter_id, $displayName){
        try {
            $statement = $this->connection->prepare('UPDATE users SET twitter=:twitter_id, displayName=:displayName WHERE id=:user_id');
            $statement->bindParam(':twitter_id', $twitter_id);
            $statement->bindParam(':displayName', $displayName);
            $statement->bindParam(':user_id', $user_id);
            $statement->execute();
        }
        catch(PDOException $e){
            echo $e;
        }
    }

    public function linkFacebook($user_id, $facebook_id, $displayName){
        try {
            $statement = $this->connection->prepare('UPDATE users SET facebook=:facebook_id, displayName=:displayName WHERE id=:user_id');
            $statement->bindParam(':facebook_id', $facebook_id);
            $statement->bindParam(':displayName', $displayName);
            $statement->bindParam(':user_id', $user_id);
            $statement->execute();
        }
        catch(PDOException $e){
            echo $e;
        }
    }

    public function updatePassword($user_id, $password){
        try {
            $statement = $this->connection->prepare('UPDATE users SET password=:password WHERE id=:id');
            $statement->bindParam(':password', $password);
            $statement->bindParam(':id', $user_id);
            $statement->execute();
        }
        catch(PDOException $e) {
            echo $e;
        }
    }

    public function deletePasswordReset($user_id){
        try {
            $statement = $this->connection->prepare('DELETE FROM passresets WHERE user_id=:user_id');
            $statement->bindParam(':user_id', $user_id);
            $statement->execute();
        }
        catch(PDOException $e) {
            echo $e;
        }
    }

    public function getPassword($email){
        try {
            $statement = $this->connection->prepare('SELECT password FROM users WHERE email=:email');
            $statement->bindParam(':email', $email);
            $statement->execute();
            $statement->setFetchMode(PDO::FETCH_ASSOC);
            $results = $statement->fetchAll();

            if(count($results) === 1){
                return $results[0]['password'];
            }
            else {
                return 'Error: More than one user with email ' . $email . ' in database';
            }
        }
        catch(PDOException $e) {
            echo $e;
        }
    }

}

class MultipleUsersWithEmailException extends Exception {

}

class UserNotFoundException extends Exception {

}

?>