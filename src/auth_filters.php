<?php

function is_logged_in($token, $secret, $user_model){
    if($token){
        try {
            $payloadObject = JWT::decode($token, $secret);
            $payload = json_decode(json_encode($payloadObject), true);
            if(!$user_model->getUserWithId($payload['sub'])){
                return false;
            }
            return $payload['sub'];
        }
        catch (Exception $e){
            return false;
        }
    }
    else {
        return false;
    }

}

$app->hook('slim.before.router', function() use ($app, $config, $user_model){

    $token = $app->request->headers->get($config->getAuthHeader());
    $response = $app->response();
    $response->headers->set('Content-Type', 'application/json');

    $auth_needed = false;

    if (strpos($app->request()->getPathInfo(), '/me') === 0) {
        $auth_needed = true;
    }

    if (strpos($app->request()->getPathInfo(), '/authprovider') === 0) {
        $auth_needed = true;
    }

    if($auth_needed){
        if(!is_logged_in($token, $config->getSecret('TOKEN_SECRET'), $user_model)){
            $response->setStatus(401);
            $response->setBody('{"message": "Invalid token"}');
            $app->stop(); 
        }
    };
});

?>