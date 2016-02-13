<?php

/**
 * Silex User Provider
 *
 *  Copyright 2016 by Simon Erhardt <hello@rootlogin.ch>
 *
 * This file is part of the silex user provider.
 *
 * The silex user provider is free software: you can redistribute
 * it and/or modify it under the terms of the Lesser GNU General Public
 * License version 3 as published by the Free Software Foundation.
 *
 * The silex user provider is distributed in the hope that it will
 * be useful, but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * You should have received a copy of the Lesser GNU General Public
 * License along with the silex user provider.  If not, see
 * <http://www.gnu.org/licenses/>.
 *
 * @license LGPL-3.0 <http://spdx.org/licenses/LGPL-3.0>
 */

namespace rootLogin\UserProvider\Tests;

use Dflydev\Silex\Provider\DoctrineOrm\DoctrineOrmServiceProvider;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use rootLogin\UserProvider\Entity\User;
use rootLogin\UserProvider\Event\UserEvent;
use rootLogin\UserProvider\Event\UserEvents;
use rootLogin\UserProvider\Manager\OrmUserManager;
use rootLogin\UserProvider\Manager\UserManager;
use rootLogin\UserProvider\Tests\Entity\Orm\CustomUser;
use Silex\Application;
use Silex\Provider\DoctrineServiceProvider;
use Silex\Provider\SecurityServiceProvider;
use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Security\Http\Logout\DefaultLogoutSuccessHandler;

class OrmUserManagerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var UserManager
     */
    protected $userManager;

    /**
     * @var EntityManager
     */
    protected $em;

    /** @var EventDispatcher */
    protected $dispatcher;

    public function setUp()
    {
        $app = new Application();
        $app->register(new SecurityServiceProvider());
        $app->register(new DoctrineServiceProvider(), [
            'db.options' => [
                'driver' => 'pdo_sqlite',
                'memory' => true,
            ],
        ]);
        $app->register(new DoctrineOrmServiceProvider(), [
            'orm.em.options' => [
                'mappings' => [
                    [
                        'type' => 'annotation',
                        'namespace' => 'rootLogin\UserProvider\Entity',
                        'path' => __DIR__ . '/../../../../src/rootLogin/UserProvider/Entity',
                        'use_simple_annotation_reader' => false
                    ],
                    [
                        'type' => 'annotation',
                        'namespace' => 'rootLogin\UserProvider\Tests\Entity\Orm',
                        'path' => __DIR__ . '/Entity',
                        'use_simple_annotation_reader' => false
                    ]
                ]
            ]
        ]);

        $this->userManager = new OrmUserManager($app);
        $this->em = $app['orm.em'];
        $this->dispatcher = $app['dispatcher'];

        $this->createSchema();
    }

    public function testUserManager()
    {
        $this->assertInstanceOf('rootLogin\UserProvider\Interfaces\UserManagerInterface', $this->userManager);
    }

    public function testCreateUser()
    {
        $user = $this->userManager->create('test@example.com', 'pass');

        $this->assertInstanceOf('rootLogin\UserProvider\Entity\User', $user);
    }

    public function testStoreAndFetchUser()
    {
        $user = $this->userManager->create('test@example.com', 'password');
        $this->assertNull($user->getId());

        $this->userManager->insert($user);
        $this->assertGreaterThan(0, $user->getId());

        $storedUser = $this->userManager->getUser($user->getId());
        $this->assertEquals($storedUser, $user);
    }

    public function testUpdateUser()
    {
        $user = $this->userManager->create('test@example.com', 'pass');
        $this->userManager->insert($user);

        $user->setName('Foo');
        $this->userManager->update($user);

        $storedUser = $this->userManager->getUser($user->getId());

        $this->assertEquals('Foo', $storedUser->getName());
    }

    public function testDeleteUser()
    {
        $email = 'test@example.com';

        $user = $this->userManager->create($email, 'password');
        $this->userManager->insert($user);
        $this->assertEquals($user, $this->userManager->findOneBy(array('email' => $email)));

        $this->userManager->delete($user);
        $this->assertNull($this->userManager->findOneBy(array('email' => $email)));
    }

    public function testLoadUserByUsernamePassingEmailAddress()
    {
        $email = 'test@example.com';

        $user = $this->userManager->create($email, 'password');
        $this->userManager->insert($user);

        $foundUser = $this->userManager->loadUserByUsername($email);
        $this->assertEquals($user, $foundUser);
    }

    public function testLoadUserByUsernamePassingUsername()
    {
        $username = 'foo';

        $user = $this->userManager->create('test@example.com', 'password');
        $user->setUsername($username);
        $this->userManager->insert($user);

        $foundUser = $this->userManager->loadUserByUsername($username);
        $this->assertEquals($user, $foundUser);
    }

    /**
     * @expectedException Symfony\Component\Security\Core\Exception\UsernameNotFoundException
     */
    public function testLoadUserByUsernameThrowsExceptionIfUserNotFound()
    {
        $this->userManager->loadUserByUsername('does-not-exist@example.com');
    }

    public function testGetUsernameReturnsEmailIfUsernameIsNull()
    {
        $email = 'test@example.com';

        $user = $this->userManager->create($email, 'password');

        $this->assertNull($user->getRealUsername());
        $this->assertEquals($email, $user->getUsername());

        $user->setUsername(null);
        $this->assertEquals($email, $user->getUsername());
    }

    public function testGetUsernameReturnsUsernameIfNotNull()
    {
        $username = 'joe';

        $user = $this->userManager->create('test@example.com', 'password');
        $user->setUsername($username);

        $this->assertEquals($username, $user->getUsername());
    }

    public function testUsernameCannotContainAtSymbol()
    {
        $user = $this->userManager->create('test@example.com', 'password');
        $errors = $user->validate();
        $this->assertEmpty($errors);

        $user->setUsername('foo@example.com');
        $errors = $user->validate();
        $this->assertArrayHasKey('username', $errors);
    }

    public function testValidationFailsOnDuplicateEmail()
    {
        $email = 'test@example.com';

        $user1 = $this->userManager->create($email, 'password');
        $this->userManager->insert($user1);
        $errors = $this->userManager->validate($user1);
        $this->assertEmpty($errors);

        // Validation fails because a different user already exists in the database with that email address.
        $user2 = $this->userManager->create($email, 'password');
        $errors = $this->userManager->validate($user2);
        $this->assertArrayHasKey('email', $errors);
    }

    public function testValidationFailsOnDuplicateUsername()
    {
        $username = 'foo';

        $user1 = $this->userManager->create('test1@example.com', 'password');
        $user1->setUsername($username);
        $this->userManager->insert($user1);
        $errors = $this->userManager->validate($user1);
        $this->assertEmpty($errors);

        // Validation fails because a different user already exists in the database with that email address.
        $user2 = $this->userManager->create('test2@example.com', 'password');
        $user2->setUsername($username);
        $errors = $this->userManager->validate($user2);
        $this->assertArrayHasKey('username', $errors);
    }

    public function testFindAndCount()
    {
        $email1 = 'test1@example.com';
        $email2 = 'test2@example.com';

        $user1 = $this->userManager->create($email1, 'password');
        $this->userManager->insert($user1);

        $user2 = $this->userManager->create($email2, 'password');
        $this->userManager->insert($user2);

        $criteria = array('email' => $email1);
        $results = $this->userManager->findBy($criteria);
        $numResults = $this->userManager->findCount($criteria);
        $this->assertCount(1, $results);
        $this->assertEquals(1, $numResults);
        $this->assertEquals($user1, reset($results));
    }

    public function testCustomUserClass()
    {
        $this->userManager->setUserClass('\rootLogin\UserProvider\Tests\Entity\Orm\CustomUser');

        /** @var CustomUser $user */
        $user = $this->userManager->create('test@example.com', 'password');
        $this->assertInstanceOf('rootLogin\UserProvider\Tests\Entity\Orm\CustomUser', $user);

        $user->setTwitterUsername('foo');
        $errors = $this->userManager->validate($user);
        $this->assertArrayHasKey('twitterUsername', $errors);

        $user->setTwitterUsername('@foo');
        $errors = $this->userManager->validate($user);
        $this->assertEmpty($errors);
    }


    public function testSupportsBaseClass()
    {
        $user = $this->userManager->create('test@example.com', 'password');

        $supportsObject = $this->userManager->supportsClass(get_class($user));
        $this->assertTrue($supportsObject);

        $this->userManager->insert($user);
        $freshUser = $this->userManager->refreshUser($user);

        $supportsRefreshedObject = $this->userManager->supportsClass(get_class($freshUser));
        $this->assertTrue($supportsRefreshedObject);

        $this->assertTrue($freshUser instanceof User);
    }

    public function testSupportsSubClass()
    {
        $this->userManager->setUserClass('\rootLogin\UserProvider\Tests\Entity\Orm\CustomUser');

        $user = $this->userManager->create('test@example.com', 'password');

        $supportsObject = $this->userManager->supportsClass(get_class($user));
        $this->assertTrue($supportsObject);

        $this->userManager->insert($user);
        $freshUser = $this->userManager->refreshUser($user);

        $supportsRefreshedObject = $this->userManager->supportsClass(get_class($freshUser));
        $this->assertTrue($supportsRefreshedObject);

        $this->assertTrue($freshUser instanceof CustomUser);
    }

    public function testValidationWhenUsernameIsRequired()
    {
        $user = $this->userManager->create('test@example.com', 'password');
        $this->userManager->setUsernameRequired(true);

        $errors = $this->userManager->validate($user);
        $this->assertArrayHasKey('username', $errors);

        $user->setUsername('username');
        $errors = $this->userManager->validate($user);
        $this->assertEmpty($errors);
    }

    public function testBeforeInsertEvents()
    {
        $this->dispatcher->addListener(UserEvents::BEFORE_INSERT, function(UserEvent $event) {
           $event->getUser()->setCustomField('foo', 'bar');
        });

        $user = $this->userManager->create('test@example.com', 'password');

        // After insert, the custom field set by the listener is available.
        $this->assertFalse($user->hasCustomField('foo'));
        $this->userManager->insert($user);
        $this->assertEquals('bar', $user->getCustomField('foo'));

        // The user was stored with the custom field (since we set it BEFORE insert).
        $storedUser = $this->userManager->getUser($user->getId());
        $this->assertEquals('bar', $storedUser->getCustomField('foo'));
    }

    public function testAfterInsertEvents()
    {
        $this->dispatcher->addListener(UserEvents::AFTER_INSERT, function(UserEvent $event) {
            $event->getUser()->setName("Foo Bar");
        });

        $user = $this->userManager->create('test@example.com', 'password');

        // After insert, the custom field set by the listener is available.
        $this->userManager->insert($user);
        $this->assertEquals('Foo Bar', $user->getName());
    }

    public function testBeforeUpdateEvents()
    {
        $this->dispatcher->addListener(UserEvents::BEFORE_UPDATE, function(UserEvent $event) {
            $event->getUser()->setCustomField('foo', 'bar');
        });

        $user = $this->userManager->create('test@example.com', 'password');
        $this->userManager->insert($user);

        // After update, the custom field set by the listener is available.
        $this->assertFalse($user->hasCustomField('foo'));
        $this->userManager->update($user);
        $this->assertEquals('bar', $user->getCustomField('foo'));

        // The user was stored with the custom field (since we set it BEFORE insert).
        $storedUser = $this->userManager->getUser($user->getId());
        $this->assertEquals('bar', $storedUser->getCustomField('foo'));
    }

    public function testAfterUpdateEvents()
    {
        $this->dispatcher->addListener(UserEvents::AFTER_UPDATE, function(UserEvent $event) {
            $event->getUser()->setName("Foo Bar");
        });

        $user = $this->userManager->create('test@example.com', 'password');
        $this->userManager->insert($user);

        // After update, the custom field set by the listener is available on the existing user instance.
        $this->userManager->update($user);
        $this->assertEquals('Foo Bar', $user->getName());
    }

    public function testPasswordStrengthValidator()
    {
        $user = new User('test@example.com');

        // By default, an empty password is not allowed.
        $error = $this->userManager->validatePasswordStrength($user, '');
        $this->assertNotEmpty($error);

        // By default, any non-empty password is allowed.
        $error = $this->userManager->validatePasswordStrength($user, 'a');
        $this->assertNull($error);

        // Test setting a custom validator.
        $this->userManager->setPasswordStrengthValidator(function(User $user, $password) {
            if (strlen($password) < 2) {
                return 'Password must have at least 2 characters.';
            }
        });

        $error = $this->userManager->validatePasswordStrength($user, 'a');
        $this->assertEquals('Password must have at least 2 characters.', $error);
    }

    protected function createSchema() {
        $metadatas = $this->em->getMetadataFactory()->getAllMetadata();

        if (!empty($metadatas)) {
            // Create SchemaTool
            $tool = new SchemaTool($this->em);
            $tool->createSchema($metadatas);
        }
    }
}
