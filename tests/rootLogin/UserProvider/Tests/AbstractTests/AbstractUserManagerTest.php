<?php

namespace rootLogin\UserProvider\Tests\AbstractTests;

use Dflydev\Silex\Provider\DoctrineOrm\DoctrineOrmServiceProvider;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use rootLogin\UserProvider\Entity\User;
use rootLogin\UserProvider\Event\UserEvent;
use rootLogin\UserProvider\Event\UserEvents;
use rootLogin\UserProvider\Interfaces\UserManagerInterface;
use Silex\Application;
use Silex\Provider;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Validator\Validator;

abstract class AbstractUserManagerTest extends AbstractTest
{
    /**
     * @var Connection
     */
    protected $conn;

    /**
     * @var EventDispatcher
     */
    protected $dispatcher;

    /**
     * @var Validator
     */
    protected $validator;

    /**
     * @var EntityManager
     */
    protected $em;

    /**
     * @var UserManagerInterface
     */
    protected $userManager;

    /**
     * @param boolean $withOrm Enables Doctrine Orm.
     * @return Application
     */
    public function setUp($withOrm = false)
    {
        $app = new Application();

        $app->register(new Provider\SecurityServiceProvider());
        $app->register(new Provider\ValidatorServiceProvider());
        $app->register(new Provider\DoctrineServiceProvider(), [
            'db.options' => [
                'driver' => 'pdo_sqlite',
                'memory' => true,
            ],
        ]);
        if($withOrm) {
            $app->register(new DoctrineOrmServiceProvider(), [
                'orm.em.options' => [
                    'mappings' => [
                        [
                            'type' => 'yml',
                            'namespace' => 'rootLogin\UserProvider\Entity',
                            'path' => __DIR__ . '/../../../../../src/rootLogin/UserProvider/Resources/mappings/',
                            'alias' => 'rootLogin.UserProvider.Entity.User.orm.yml'
                        ],
                        [
                            'type' => 'annotation',
                            'namespace' => 'rootLogin\UserProvider\Tests\Entity\Orm',
                            'path' => __DIR__ . '/../Entity',
                            'use_simple_annotation_reader' => false
                        ]
                    ]
                ]
            ]);
        }

        $this->addValidators($app);

        $this->conn = $app['db'];
        $this->dispatcher = $app['dispatcher'];
        $this->validator = $app['validator'];
        if($withOrm) {
            $this->em = $app['orm.em'];
        }

        return $app;
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

        $this->userManager->save($user);
        $this->assertGreaterThan(0, $user->getId());

        $storedUser = $this->userManager->getUser($user->getId());
        $this->assertEquals($storedUser, $user);
    }

    public function testUpdateUser()
    {
        $user = $this->userManager->create('test@example.com', 'pass');
        $this->userManager->save($user);

        $user->setName('Foo');
        $this->userManager->save($user);

        $storedUser = $this->userManager->getUser($user->getId());

        $this->assertEquals('Foo', $storedUser->getName());
    }

    public function testDeleteUser()
    {
        $email = 'test@example.com';

        $user = $this->userManager->create($email, 'password');
        $this->userManager->save($user);
        $this->assertEquals($user, $this->userManager->findOneBy(array('email' => $email)));

        $this->userManager->delete($user);
        $this->assertNull($this->userManager->findOneBy(array('email' => $email)));
    }

    public function testLoadUserByUsernamePassingEmailAddress()
    {
        $email = 'test@example.com';

        $user = $this->userManager->create($email, 'password');
        $this->userManager->save($user);

        $foundUser = $this->userManager->loadUserByUsername($email);
        $this->assertEquals($user, $foundUser);
    }

    public function testLoadUserByUsernamePassingUsername()
    {
        $username = 'foo';

        $user = $this->userManager->create('test@example.com', 'password');
        $user->setUsername($username);
        $this->userManager->save($user);

        $foundUser = $this->userManager->loadUserByUsername($username);
        $this->assertEquals($user, $foundUser);
    }

    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\UsernameNotFoundException
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

        $errors = $this->validator->validate($user);
        $this->assertEmpty($errors);

        $user->setUsername('foo@example.com');
        $errors = $this->validator->validate($user);
        $this->assertTrue($errors->count() == 1); /*@TODO*/
    }

    public function testValidationFailsOnDuplicateEmail()
    {
        $email = 'test@example.com';

        $user1 = $this->userManager->create($email, 'password');
        $this->userManager->save($user1);
        $errors = $this->validator->validate($user1);
        $this->assertEmpty($errors);

        // Validation fails because a different user already exists in the database with that email address.
        $user2 = $this->userManager->create($email, 'password');
        $errors = $this->validator->validate($user2);
        $this->assertTrue($errors->count() == 1); /*@TODO*/
    }

    /**
     * @TODO fix
    public function testValidationFailsOnDuplicateUsername()
    {
        $username = 'foo';

        $user1 = $this->userManager->create('test1@example.com', 'password');
        $user1->setUsername($username);
        $this->userManager->save($user1);
        $errors = $this->userManager->validate($user1);
        $this->assertEmpty($errors);

        // Validation fails because a different user already exists in the database with that email address.
        $user2 = $this->userManager->create('test2@example.com', 'password');
        $user2->setUsername($username);
        $errors = $this->userManager->validate($user2);
        $this->assertArrayHasKey('username', $errors);
    }
    */

    public function testSupportsBaseClass()
    {
        $user = $this->userManager->create('test@example.com', 'password');

        $supportsObject = $this->userManager->supportsClass(get_class($user));
        $this->assertTrue($supportsObject);

        $this->userManager->save($user);
        $freshUser = $this->userManager->refreshUser($user);

        $supportsRefreshedObject = $this->userManager->supportsClass(get_class($freshUser));
        $this->assertTrue($supportsRefreshedObject);

        $this->assertTrue($freshUser instanceof User);
    }

    public function testFindAndCount()
    {
        $email1 = 'test1@example.com';
        $email2 = 'test2@example.com';

        $user1 = $this->userManager->create($email1, 'password');
        $this->userManager->save($user1);

        $user2 = $this->userManager->create($email2, 'password');
        $this->userManager->save($user2);

        $criteria = array('email' => $email1);
        $results = $this->userManager->findBy($criteria);
        $numResults = $this->userManager->findCount($criteria);
        $this->assertCount(1, $results);
        $this->assertEquals(1, $numResults);
        $this->assertEquals($user1, reset($results));
    }

    public function testBeforeInsertEvents()
    {
        $this->dispatcher->addListener(UserEvents::BEFORE_INSERT, function(UserEvent $event) {
            $event->getUser()->setName('Foo Bar');
        });

        $user = $this->userManager->create('test@example.com', 'password');

        // After insert, the custom field set by the listener is available.
        $this->userManager->save($user);
        $this->assertEquals('Foo Bar', $user->getName());
    }

    public function testAfterInsertEvents()
    {
        $this->dispatcher->addListener(UserEvents::AFTER_INSERT, function(UserEvent $event) {
            $event->getUser()->setName("Foo Bar");
        });

        $user = $this->userManager->create('test@example.com', 'password');

        // After insert, the custom field set by the listener is available.
        $this->userManager->save($user);
        $this->assertEquals('Foo Bar', $user->getName());
    }

    public function testBeforeUpdateEvents()
    {
        $this->dispatcher->addListener(UserEvents::BEFORE_UPDATE, function(UserEvent $event) {
            $event->getUser()->setName('Foo Bar');
        });

        $user = $this->userManager->create('test@example.com', 'password');
        $this->userManager->save($user);

        // After update, the custom field set by the listener is available.
        $this->assertNotEquals('Foo Bar', $user->getName());
        $this->userManager->save($user);
        $this->assertEquals('Foo Bar', $user->getName('foo'));
    }

    public function testAfterUpdateEvents()
    {
        $this->dispatcher->addListener(UserEvents::AFTER_UPDATE, function(UserEvent $event) {
            $event->getUser()->setName("Foo Bar");
        });

        $user = $this->userManager->create('test@example.com', 'password');
        $this->userManager->save($user);

        // After update, the custom field set by the listener is available on the existing user instance.
        $this->userManager->save($user);
        $this->assertEquals('Foo Bar', $user->getName());
    }

    /**
     * @TODO
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
     */
}