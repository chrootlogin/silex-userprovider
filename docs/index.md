User Provider for Silex
=======================

[![Build Status](https://travis-ci.org/chrootLogin/silex-userprovider.svg?branch=nextgen)](https://travis-ci.org/chrootLogin/silex-userprovider)
[![Total Downloads](https://poser.pugx.org/rootlogin/silex-userprovider/downloads.svg)](https://packagist.org/packages/rootlogin/silex-userprovider)
[![Latest Stable Version](https://poser.pugx.org/rootlogin/silex-userprovider/v/stable)](https://packagist.org/packages/rootlogin/silex-userprovider)
[![Latest Unstable Version](https://poser.pugx.org/rootlogin/silex-userprovider/v/unstable.svg)](https://packagist.org/packages/rootLogin/silex-userprovider)

A simple, extensible, database-backed user provider for the Silex [security service](http://silex.sensiolabs.org/doc/providers/security.html).

The User Provider is an easy way to set up user accounts (authentication, authorization, and user administration) in the Silex PHP micro-framework. It provides drop-in services for Silex that implement the missing user management pieces for the Security component. It includes a basic User model, a database-backed user manager, controllers and views for user administration, and various supporting features.

Dependencies
------------

* PHP ~5.4
* Silex ~1.0
* Doctrine DBAL ~2.4
* Symfony Security ~2.3


Demo
----

* [Online demo](http://demoapp.rootlogin.ch/)
* [Demo code](https://github.com/chrootLogin/silex-demoapp)

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

//
// Application setup
//

$app = new Application();
$app->register(new Provider\DoctrineServiceProvider());
$app->register(new Provider\SecurityServiceProvider());
$app->register(new Provider\RememberMeServiceProvider());
$app->register(new Provider\SessionServiceProvider());
$app->register(new Provider\ServiceControllerServiceProvider());
$app->register(new Provider\UrlGeneratorServiceProvider());
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


Config options
--------------

All of these options are _optional_.
The UserProvider can work without any configuration at all,
or you can customize one or more of the following options.
The default values are shown below.

```
$app['user.options'] = array(

    // Specify custom view templates here.
    'templates' => array(
        'layout' => '@user/layout.twig',
        'register' => '@user/register.twig',
        'register-confirmation-sent' => '@user/register-confirmation-sent.twig',
        'login' => '@user/login.twig',
        'login-confirmation-needed' => '@user/login-confirmation-needed.twig',
        'forgot-password' => '@user/forgot-password.twig',
        'reset-password' => '@user/reset-password.twig',
        'view' => '@user/view.twig',
        'edit' => '@user/edit.twig',
        'list' => '@user/list.twig',
    ),

    // Configure the user mailer for sending password reset and email confirmation messages.
    'mailer' => array(
        'enabled' => true, // When false, email notifications are not sent (they're silently discarded).
        'fromEmail' => array(
            'address' => 'do-not-reply@' . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : gethostname()),
            'name' => null,
        ),
    ),

    'emailConfirmation' => array(
        'required' => false, // Whether to require email confirmation before enabling new accounts.
        'template' => '@user/email/confirm-email.twig',
    ),

    'passwordReset' => array(
        'template' => '@user/email/reset-password.twig',
        'tokenTTL' => 86400, // How many seconds the reset token is valid for. Default: 1 day.
    ),

    // Set this to use a custom User class.
    'userClass' => 'rootLogin\UserProvider\Entity\User',

    // Whether to require that users have a username (default: false).
    // By default, users sign in with their email address instead.
    'isUsernameRequired' => false,

	/** 
	 * Advanced Settings for DBAL mode.
	 *
	 * This settings don't have impact in ORM mode
	 */

    // A list of custom fields to support in the edit controller.
    'editCustomFields' => array(),

    // Override table names, if necessary.
    'userTableName' => 'users',
    'userCustomFieldsTableName' => 'user_custom_fields',

    //Override Column names, if necessary
    'userColumns' => array(
        'id' => 'id',
        'email' => 'email',
        'password' => 'password',
        'salt' => 'salt',
        'roles' => 'roles',
        'name' => 'name',
        'time_created' => 'time_created',
        'username' => 'username',
        'isEnabled' => 'isEnabled',
        'confirmationToken' => 'confirmationToken',
        'timePasswordResetRequested' => 'timePasswordResetRequested',
        //Custom Fields
        'user_id' => 'user_id',
        'attribute' => 'attribute',
        'value' => 'value',
    )
);
```

Commandline
-----------

If you have enabled the symfony console, as with [saxulum-console](https://github.com/saxulum/saxulum-console) for example, the provider will add some commands to the console:

* `userprovider:create`: Create an user
* `userprovider:list`: List users
* `userprovider:delete`: Delete an user

Use Doctrine ORM instead of DBAL
--------------------------------

The provider uses the Doctrine Orm (Object-relational mapper) automatically, if the necessairy providers are found. (See Additional Dependencies)

ATTENTION! An auto migration is no possible.

### Additional dependencies

* A Doctrine Orm Provider, like [dflydev/dflydev-doctrine-orm-service-provider](https://github.com/dflydev/dflydev-doctrine-orm-service-provider).

#### Usage

If the server provider finds another orm service provider it will automatically add itself.

### Force DBAL

If you use Doctrine ORM in your project and you want to force the DBAL handling for the UserProvider, register the provider like this:

```
// ...

$app->register(new UserProviderServiceProvider(true));

// ... 
```