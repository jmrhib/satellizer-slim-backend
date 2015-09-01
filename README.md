
Example php-backend for [Satellizer](https://github.com/sahat/satellizer) using [Slim Framework](http://www.slimframework.com/) 

It is a work in progress, and currently supports only Twitter and Facebook.

Installation
------------
1) Import users.sql in a database.
2) Open src/Config.php and edit settings for server, user, password, database.
3) Copy all files **except users.sql** to server.
4) Run `composer install` on the server you copied the files to.
5) Edit .htaccess file to suit your needs. If you are installing to a sub directory (for example, */api*), it should look something like this:

    RewriteEngine On
    RewriteBase /api
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [QSA,L]

Use the file auth_filters.php to add routes you wish to require authentification for. For example, if you add a 'save' option in Slim like this:

    $app->post('/save', function(){ /* Save user's data */ });

You would add this in auth_filters.php:

    if (strpos($app->request()->getPathInfo(), "/save") === 0) {
        $auth_needed = true;
    }


