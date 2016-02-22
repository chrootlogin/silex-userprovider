<?php

namespace rootLogin\UserProvider\Tests;

use rootLogin\UserProvider\Provider\UserProviderServiceProvider;
use rootLogin\UserProvider\Provider\UserServiceProvider;
use Silex\Application;
use Silex\Provider;

class UserServiceProviderTest extends \PHPUnit_Framework_TestCase
{
    protected function getMinimalApp()
    {
        $app = new Application();

        $app->register(new Provider\FormServiceProvider());
        $app->register(new Provider\SecurityServiceProvider(),
            array('security.firewalls' => array('dummy-firewall' => array('form' => array())))
        );
        $app->register(new Provider\DoctrineServiceProvider());
        $app->register(new UserProviderServiceProvider(), array(
            'db.options' => array(
                'driver' => 'pdo_sqlite',
                'memory' => true,
            ),
        ));

        return $app;
    }

    protected function getAppWithAllDependencies()
    {
        $app = $this->getMinimalApp();

        $app->register(new Provider\RememberMeServiceProvider());
        $app->register(new Provider\SessionServiceProvider());
        $app->register(new Provider\ServiceControllerServiceProvider());
        $app->register(new Provider\UrlGeneratorServiceProvider());
        $app->register(new Provider\TwigServiceProvider());
        $app->register(new Provider\SwiftmailerServiceProvider());

        return $app;
    }

    public function testWithDefaults()
    {
        $app = $this->getMinimalApp();
        $app->boot();

        $this->assertInstanceOf('rootLogin\UserProvider\Manager\UserManager', $app['user.manager']);
        $this->assertInstanceOf('rootLogin\UserProvider\Controller\UserController', $app['user.controller']);
        $this->assertNull($app['user']);
    }

    public function testMailer()
    {
        $app = $this->getAppWithAllDependencies();
        $app->boot();

        $this->assertInstanceOf('rootLogin\UserProvider\Lib\Mailer', $app['user.mailer']);
    }

}