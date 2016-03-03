Installation
------------

Install with composer. This command will automatically install the latest stable version:

```
$ composer require rootlogin/silex-userprovider
```

Quick start example config
--------------------------

This configuration should work out of the box to get you up and running quickly. See below for additional details.

Set up your Silex application something like this:

```
<?php

use Silex\Application;
use Silex\Provider;
use Symfony\Component\Validator\Mapping\Factory\LazyLoadingMetadataFactory;
use Symfony\Component\Validator\Mapping\Loader\AnnotationLoader;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Cache\ApcuCache;

//
// Application setup
//

$app = new Application();
$app->register(new Provider\DoctrineServiceProvider());
$app->register(new Provider\SecurityServiceProvider());
$app->register(new Provider\RememberMeServiceProvider());
$app->register(new Provider\SessionServiceProvider());
$app->register(new Provider\ServiceControllerServiceProvider());
$app->register(new Provider\RouteControllerProvider());
$app->register(new Provider\UrlGeneratorServiceProvider());
$app->register(new Provider\FormServiceProvider());
$app->register(new Provider\ValidatorServiceProvider());
$app->register(new Provider\TwigServiceProvider());
$app->register(new Provider\SwiftmailerServiceProvider());

// Register the UserProvider service provider.
$app->register(new \rootLogin\UserProvider\Provider\UserProviderServiceProvider());

// ...

//
// Controllers
//

// Mount the user controller routes:
$app->mount('/user', new \rootLogin\UserProvider\Provider\UserProviderControllerProvider());

/*
// Other routes and controllers...
$app->get('/', function () use ($app) {
    return $app['twig']->render('index.twig', array());
});
*/

// ...

//
// Configuration
//

// UserProvider options. See config reference below for details.
$app['user.options'] = array();

// Security config. See http://silex.sensiolabs.org/doc/providers/security.html for details.
$app['security.firewalls'] = array(
    /* // Ensure that the login page is accessible to all, if you set anonymous => false below.
    'login' => array(
        'pattern' => '^/user/login$',
    ), */
    'secured_area' => array(
        'pattern' => '^.*$',
        'anonymous' => true,
        'remember_me' => array(),
        'form' => array(
            'login_path' => '/user/login',
            'check_path' => '/user/login_check',
        ),
        'logout' => array(
            'logout_path' => '/user/logout',
        ),
        'users' => $app->share(function($app) { return $app['user.manager']; }),
    ),
);

// Mailer config. See http://silex.sensiolabs.org/doc/providers/swiftmailer.html
$app['swiftmailer.options'] = array();

// Database config. See http://silex.sensiolabs.org/doc/providers/doctrine.html
$app['db.options'] = array(
    'driver'   => 'pdo_mysql',
    'host' => 'localhost',
    'dbname' => 'mydbname',
    'user' => 'mydbuser',
    'password' => 'mydbpassword',
    'charset' => 'utf8'
);

return $app;
```

Create the user database:

```
mysql -uUSER -pPASSWORD MYDBNAME < vendor/rootlogin/silex-userprovider/sql/mysql.sql
```

You should now be able to create an account at the `/user/register` URL.
Make the new account an administrator by editing the record directly in the database and setting the `users.roles` column to `ROLE_USER,ROLE_ADMIN`.
(After you have one admin account, it can grant the admin role to others via the web interface.)