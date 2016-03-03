<?php

namespace rootLogin\UserProvider\Tests;

use rootLogin\UserProvider\Entity\LegacyUser;
use rootLogin\UserProvider\Entity\User;
use rootLogin\UserProvider\Event\UserEvent;
use rootLogin\UserProvider\Event\UserEvents;
use rootLogin\UserProvider\Manager\DBALUserManager;
use rootLogin\UserProvider\Tests\AbstractTests\AbstractUserManagerTest;
use rootLogin\UserProvider\Tests\Entity\CustomUser;
use Silex\Application;
use Silex\Provider\DoctrineServiceProvider;
use Silex\Provider\SecurityServiceProvider;
use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Validator\Validator;

class DBALUserManagerTest extends AbstractUserManagerTest
{
    /**
     * @var DBALUserManager
     */
    protected $userManager;

    public function setUp()
    {
        $app = parent::setUp();

        $this->userManager = $app['user.manager'] = new DBALUserManager($app);
        $this->conn->executeUpdate(file_get_contents(__DIR__ . '/../../../../sql/sqlite.sql'));
    }

    public function testUserManager()
    {
        $this->assertInstanceOf('rootLogin\UserProvider\Manager\DBALUserManager', $this->userManager);
    }

    public function testCustomFields()
    {
        /** @var LegacyUser $user */
        $user = $this->userManager->create('test@example.com', 'pass');

        $user->setCustomField('field1', 'foo');
        $user->setCustomField('field2', 'bar');

        $this->userManager->save($user);

        $storedUser = $this->userManager->getUser($user->getId());
        $this->assertEquals('foo', $storedUser->getCustomField('field1'));
        $this->assertEquals('bar', $storedUser->getCustomField('field2'));

        // Search by two custom fields.
        $foundUser = $this->userManager->findOneBy(array('customFields' => array('field1' => 'foo', 'field2' => 'bar')));
        $this->assertEquals($user, $foundUser);

        // Search by one custom field and one standard property.
        $foundUser = $this->userManager->findOneBy(array('id' => $user->getId(), 'customFields' => array('field2' => 'bar')));
        $this->assertEquals($user, $foundUser);

        // Failed search returns null.
        $foundUser = $this->userManager->findOneBy(array('customFields' => array('field1' => 'foo', 'field2' => 'does-not-exist')));
        $this->assertNull($foundUser);
    }

    public function testFindAndCount()
    {
        $customField = 'foo';
        $customVal = 'bar';
        $email1 = 'test1@example.com';
        $email2 = 'test2@example.com';

        /** @var LegacyUser $user1 */
        $user1 = $this->userManager->create($email1, 'password');
        $user1->setCustomField($customField, $customVal);
        $this->userManager->save($user1);

        /** @var LegacyUser $user2 */
        $user2 = $this->userManager->create($email2, 'password');
        $user2->setCustomField($customField, $customVal);
        $this->userManager->save($user2);

        $criteria = array('email' => $email1);
        $results = $this->userManager->findBy($criteria);
        $numResults = $this->userManager->findCount($criteria);
        $this->assertCount(1, $results);
        $this->assertEquals(1, $numResults);
        $this->assertEquals($user1, reset($results));

        $criteria = array('customFields' => array($customField => $customVal));
        $results = $this->userManager->findBy($criteria);
        $numResults = $this->userManager->findCount($criteria);
        $this->assertCount(2, $results);
        $this->assertEquals(2, $numResults);
        $this->assertContains($user1, $results);
        $this->assertContains($user2, $results);
    }

    /*public function testCustomUserClass()
    {
        $this->userManager->setUserClass('\rootLogin\UserProvider\Tests\Entity\CustomUser');

        /** @var CustomUser $user *//*
        $user = $this->userManager->create('test@example.com', 'password');
        $this->assertInstanceOf('rootLogin\UserProvider\Tests\Entity\CustomUser', $user);

        $user->setTwitterUsername('foo');
        $errors = $this->validator->validate($user);
        $this->assertTrue($errors->count() == 1); /*@TODO*//*

        $user->setTwitterUsername('@foo');
        $errors = $this->validator->validate($user);
        $this->assertEmpty($errors);
    }*/

    public function testSupportsSubClass()
    {
        $this->userManager->setUserClass('\rootLogin\UserProvider\Tests\Entity\CustomUser');

        $user = $this->userManager->create('test@example.com', 'password');

        $supportsObject = $this->userManager->supportsClass(get_class($user));
        $this->assertTrue($supportsObject);

        $this->userManager->save($user);
        $freshUser = $this->userManager->refreshUser($user);

        $supportsRefreshedObject = $this->userManager->supportsClass(get_class($freshUser));
        $this->assertTrue($supportsRefreshedObject);

        $this->assertTrue($freshUser instanceof CustomUser);
    }

    public function testBeforeInsertEvents()
    {
        $this->dispatcher->addListener(UserEvents::BEFORE_INSERT, function(UserEvent $event) {
            $event->getUser()->setCustomField('foo', 'bar');
        });

        $user = $this->userManager->create('test@example.com', 'password');

        // After insert, the custom field set by the listener is available.
        $this->assertFalse($user->hasCustomField('foo'));
        $this->userManager->save($user);
        $this->assertEquals('bar', $user->getCustomField('foo'));

        // The user was stored with the custom field (since we set it BEFORE insert).
        $this->userManager->clearIdentityMap(); // Clear the cache to force a fresh lookup from the database.
        $storedUser = $this->userManager->getUser($user->getId());
        $this->assertEquals('bar', $storedUser->getCustomField('foo'));
    }

    public function testAfterInsertEvents()
    {
        $this->dispatcher->addListener(UserEvents::AFTER_INSERT, function(UserEvent $event) {
            $event->getUser()->setCustomField('foo', 'bar');
        });

        $user = $this->userManager->create('test@example.com', 'password');

        // After insert, the custom field set by the listener is available.
        $this->assertFalse($user->hasCustomField('foo'));
        $this->userManager->save($user);
        $this->assertEquals('bar', $user->getCustomField('foo'));

        // The user was NOT stored with the custom field (because we set it AFTER insert).
        // We'd have to save it again from within the after listener for it to be stored.
        $this->userManager->clearIdentityMap(); // Clear the cache to force a fresh lookup from the database.
        $storedUser = $this->userManager->getUser($user->getId());
        $this->assertFalse($storedUser->hasCustomField('foo'));
    }

    public function testBeforeUpdateEvents()
    {
        $this->dispatcher->addListener(UserEvents::BEFORE_UPDATE, function(UserEvent $event) {
            $event->getUser()->setCustomField('foo', 'bar');
        });

        $user = $this->userManager->create('test@example.com', 'password');
        $this->userManager->save($user);

        // After update, the custom field set by the listener is available.
        $this->assertFalse($user->hasCustomField('foo'));
        $this->userManager->save($user);
        $this->assertEquals('bar', $user->getCustomField('foo'));

        // The user was stored with the custom field (since we set it BEFORE insert).
        $this->userManager->clearIdentityMap(); // Clear the cache to force a fresh lookup from the database.
        $storedUser = $this->userManager->getUser($user->getId());
        $this->assertEquals('bar', $storedUser->getCustomField('foo'));
    }

    public function testAfterUpdateEvents()
    {
        $this->dispatcher->addListener(UserEvents::AFTER_UPDATE, function(UserEvent $event) {
            $event->getUser()->setCustomField('foo', 'bar');
        });

        $user = $this->userManager->create('test@example.com', 'password');
        $this->userManager->save($user);

        // After update, the custom field set by the listener is available on the existing user instance.
        $this->assertFalse($user->hasCustomField('foo'));
        $this->userManager->save($user);
        $this->assertEquals('bar', $user->getCustomField('foo'));

        // The user was NOT stored with the custom field (because we set it AFTER update).
        // We'd have to save it again from within the after listener for it to be stored.
        $this->userManager->clearIdentityMap(); // Clear the cache to force a fresh lookup from the database.
        $storedUser = $this->userManager->getUser($user->getId());
        $this->assertFalse($storedUser->hasCustomField('foo'));
    }

    public function testChangeUserColumns()
    {
        $this->userManager->setUserColumns(array('email' => 'foo'));
        $this->assertEquals('"foo"', $this->userManager->getUserColumns('email'));
    }

    public function testRoleSystem()
    {
        $user = $this->userManager->create('admin@example.com', 'adminpassword');
        $this->userManager->save($user);

        $id = $user->getId();

        unset($user);
        $this->userManager->clearIdentityMap();

        $user = $this->userManager->getUser($id);
        $this->assertNotContains("ROLE_ADMIN", $user->getRoles());
        $user->addRole("ROLE_ADMIN");
        $this->userManager->save($user);

        unset($user);
        $this->userManager->clearIdentityMap();

        $user = $this->userManager->getUser($id);
        $this->assertContains("ROLE_ADMIN", $user->getRoles());
    }

    /* @TODO
     *
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
     *
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
        } */
}
