<?php

$app->group('/auth', function () use ($app, $user_model, $config) {

    $app->post('/signup', function() use($app, $user_model, $config){

        $data = json_decode($app->request()->getBody(), true);
        $email = $data['email'];
        $password = $data['password'];
        $displayName = filter_var($data['displayName'], FILTER_SANITIZE_STRING);

        $response = $app->response();
        $response->headers->set('Content-Type', 'application/json');

        $errors = array();

        if(strlen($displayName)==0){
            array_push($errors, 'Display name required');
        }
        if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
            array_push($errors, 'Invalid email');
        }
        if($user_model->userWithEmailExists($email)){
            array_push($errors, 'Email already exists');
        }
        if($user_model->userWithDisplayNameExists($displayName)){
            array_push($errors, 'Display name already exists');
        }
        if(!verify_password_strength($password)){
            array_push($errors, 'Password too short');
        }

        if(count($errors) > 0){
            $json_errors = json_encode($errors);
            $response->setBody('{"success": "false", "errors": ' . $json_errors . '}');
            $response->setStatus(422);
            $app->stop();
        }

        $hashed_pass = password_hash($password, PASSWORD_BCRYPT);
        $user_id = $user_model->createUser($email, $hashed_pass, $displayName);

        if($user_id){
            $token = create_token($user_id, $app->request->getUrl(), $config->getSecret('TOKEN_SECRET'));
            $response->setBody('{"token": "' . $token . '"}');
            $response->setStatus(200);
        }
        else {
            $response->setBody('{"message": "Error creating user", "success": "false"}');
            $response->setStatus(500);
            $app->stop();
        }

    }); 

    $app->post('/login', function() use($app, $user_model, $config){

        $data = json_decode($app->request()->getBody(), true);
        $email = $data['email'];
        $password = $data['password'];

        $response = $app->response();
        $response->headers->set('Content-Type', 'application/json');

        try{
            $user = $user_model->getUserWithEmail($email);
        }
        catch(UserNotFoundException $e){
            $response->setBody('{"message": "No user with email: ' . $email . '", "success": "false"}');
            $response->setStatus(404);
            $app->stop();
        }
        catch(MultipleUsersWithEmailException $e){
            $response->setBody('{"message": "Multiple users with email ' . $email . '", "success": "false"}');
            $response->setStatus(500);
            $app->stop();
        }

        $hashed_pass = $user['password'];
        $user_id = $user['id'];

        if(password_verify($password, $hashed_pass)){
            $token = create_token($user_id, $app->request->getUrl(), $config->getSecret('TOKEN_SECRET'));
            $response->setBody('{"token": "' . $token . '"}');
            $response->setStatus(200);
            $app->stop();
        }
        else {
            $response->setBody('{"message": "Wrong password", "success": "false"}');
            $response->setStatus(401);
            $app->stop();
        }
    });

    $app->post('/facebook', function() use($app, $config, $user_model){

        $response = $app->response();
        $request = $app->request();
        $response->headers->set('Content-Type', 'application/json');

        $accessTokenUrl = 'https://graph.facebook.com/oauth/access_token';
        $graphApiUrl = 'https://graph.facebook.com/me';

        $data = json_decode($app->request()->getBody(), true);

        $params = array(
            'code' => $data['code'],
            'client_id' => $data['clientId'],
            'redirect_uri' => $data['redirectUri'],
            'client_secret' => $config->getSecret('FACEBOOK_SECRET')
        );

        $client = new GuzzleHttp\Client();

        // Step 1. Exchange authorization code for access token.
        $accessTokenResponse = $client->get($accessTokenUrl, ['query' => $params]);

        $accessToken = array();
        parse_str($accessTokenResponse->getBody(), $accessToken);

        // Step 2. Retrieve profile information about the current user.
        $graphiApiResponse = $client->get($graphApiUrl, ['query' => $accessToken]);
        $profile = $graphiApiResponse->json();

        // Step 3a. If user is already signed in then link accounts.
        if($request->headers->get($config->getAuthHeader())){

            if($user_model->getUserFacebook($profile['id'])){
                $response->setBody('{ "message": "There is already a Facebook account that belongs to you" }');
                $response->setStatus(409);
                $app->stop();
            }

            $user_id = findUserId($request->headers->get($config->getAuthHeader()), $config->getSecret('TOKEN_SECRET'));

            $user_model->linkFacebook(
                $user_id,
                $profile['id'],
                $profile['name']
            );

            $response->setBody('{ "token": "' . create_token($user_id, $app->request->getUrl(), $config->getSecret('TOKEN_SECRET')) . '"}');

        }
        else {

            $user = $user_model->getUserFacebook($profile['id']);

            if($user){
                $response->setBody('{ "token": "' . create_token($user['id'], $app->request->getUrl(), $config->getSecret('TOKEN_SECRET')) . '"}');
                $app->stop();
            }

            $user_id = $user_model->createUserFacebook($profile['id'], $profile['name']);
            $response->setBody('{ "token": "' . create_token($user_id, $app->request->getUrl(), $config->getSecret('TOKEN_SECRET')) . '"}');

        }
    });

    $app->get('/twitter', function() use($app, $config, $user_model){

        $response = $app->response();
        $request = $app->request();
        $response->headers->set('Content-Type', 'application/json');

        $requestTokenUrl = 'https://api.twitter.com/oauth/request_token';
        $accessTokenUrl = 'https://api.twitter.com/oauth/access_token';
        $authenticateUrl = 'https://api.twitter.com/oauth/authenticate';

        $client = new GuzzleHttp\Client();

        if(empty($request->get('oauth_token')) || empty($request->get('oauth_verifier'))){

            $oauth = new GuzzleHttp\Subscriber\Oauth\Oauth1([
                'consumer_key' => $config->getSecret('TWITTER_KEY'),
                'consumer_secret' => $config->getSecret('TWITTER_SECRET'),
                'callback' => ''
            ]);

            $client->getEmitter()->attach($oauth);

            // Step 1. Obtain request token for the authorization popup.
            $requestTokenResponse = $client->post($requestTokenUrl, ['auth' => 'oauth']);

            $oauthToken = array();
            parse_str($requestTokenResponse->getBody(), $oauthToken);

            $params = http_build_query(array(
                'oauth_token' => $oauthToken['oauth_token']
            ));

            return $response->redirect($authenticateUrl . '?' . $params, 302);

        }
        else {

            $oauth = new GuzzleHttp\Subscriber\Oauth\Oauth1([
                'consumer_key' =>$config->getSecret('TWITTER_KEY'),
                'consumer_secret' => $config->getSecret('TWITTER_SECRET'),
                'token' => $request->get('oauth_token'),
                'verifier' => $request->get('oauth_verifier')
            ]);

            $client->getEmitter()->attach($oauth);

            // Step 3. Exchange oauth token and oauth verifier for access token.
            $accessTokenResponse = $client->post($accessTokenUrl, ['auth' => 'oauth']);

            $profile = array();
            parse_str($accessTokenResponse, $profile);

            // Step 4a. If user is already signed in then link accounts.
            if($request->headers->get($config->getAuthHeader())){

                if($user_model->hasTwitter($profile['user_id'])){
                    $response->setBody('{ "message": "There is already a Twitter account that belongs to you" }');
                    $response->setStatus(409);
                    $app->stop();
                }

                $token = explode(' ', $request->headers->get($config->getAuthHeader()))[1];
                $payloadObject = JWT::decode($token, 'secret');
                $payload = json_decode(json_encode($payloadObject), true);

                $user_model->linkTwitter($payload['sub'], $profile['user_id'], $profile['screen_name']); 

                $response->setBody('{ "token": "' . create_token($payload['sub'], $app->request->getUrl(), $config->getSecret('TOKEN_SECRET')) . '"}');
            }
            // Step 4b. Create a new user account or return an existing one.
            else {

                if($user_model->hasTwitter($profile['user_id'])){
                    $user_id = $user_model->getUserTwitter($profile['user_id'])['id'];
                    $response->setBody('{ "token": "' . create_token($user_id, $app->request->getUrl(), $config->getSecret('TOKEN_SECRET')) . '"}');
                }
                else {
                    $user_id = $user_model->createUserTwitter($profile['user_id'], $profile['screen_name']);
                    $response->setBody('{ "token": "' . create_token($user_id, $app->request->getUrl(), $config->getSecret('TOKEN_SECRET')) . '"}');
                }
            }

        }

    });
});

$app->get('/me', function() use($app, $config, $user_model){
    $response = $app->response();
    $response->headers->set('Content-Type', 'application/json');
    $user_id = findUserId($app->request->headers->get($config->getAuthHeader()), $config->getSecret('TOKEN_SECRET'));
    $response->setBody(json_encode($user_model->getUserWithId($user_id)));
});

$app->put('/me', function() use($app, $config, $user_model){
    $response = $app->response();
    $response->headers->set('Content-Type', 'application/json');

    $data = json_decode($app->request()->getBody(), true);

    $errors = array();

    if(!isset($data['displayName'])){
        array_push($errors, 'Display name required');
    } 
    
    if(count($errors) > 0){
        $json_errors = json_encode($errors);
        $response->setBody($json_errors);
        $response->setStatus(422);
        $app->stop();
    }

    $displayName = filter_var($data['displayName'], FILTER_SANITIZE_STRING);
    $email = $data['email'];

    $user_id = findUserId($app->request->headers->get($config->getAuthHeader()), $config->getSecret('TOKEN_SECRET'));

    if(strlen($displayName)==0){
        array_push($errors, 'Display name required');
    }
    if(!empty($email)){
        if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
            array_push($errors, 'Invalid email');
        }
        if($user_model->userWithEmailExists($email, $user_id)){
            array_push($errors, 'Email already exists');
        }
    }
    if($user_model->userWithDisplayNameExists($displayName, $user_id)){
        array_push($errors, 'Display name already exists');
    }

    if(count($errors) > 0){
        $json_errors = json_encode($errors);
        $response->setBody($json_errors);
        $response->setStatus(422);
        $app->stop();
    }

    $user_model->updateUser($user_id, $email, $displayName);
    $response->setBody('{"message": "User info updated"}');

});

$app->get('/authprovider', function() use($app, $user_model, $config){ 
    $user_id = findUserId($app->request->headers->get($config->getAuthHeader()), $config->getSecret('TOKEN_SECRET'));
    $response = $app->response();
    $response->setBody($user_model->getAuthProvider($user_id));
});

function findUserId($token, $secret){
    if($token){
        try {
            $payloadObject = JWT::decode($token, $secret);
            $payload = json_decode(json_encode($payloadObject), true);
            return $payload['sub'];
        }
        catch (Exception $e){
            return null;
        }
    }
    else {
        return null;
    }
}

function create_token($user_id, $issuer, $secret){
    $payload = array(
        'iss' => $issuer,
        'sub' => $user_id,
        'iat' => time(),
        'exp' => time() + (2 * 7 * 24 * 60 * 60)
    );
    return JWT::encode($payload, $secret);
}

function verify_password_strength($password){
    return (strlen($password) >= 8);
}

?>