<?php

namespace rootLogin\UserProvider\Tests;

use Doctrine\DBAL\Connection;
use rootLogin\UserProvider\Entity\User;
use rootLogin\UserProvider\Manager\DBALUserManager;
use rootLogin\UserProvider\Tests\AbstractTests\AbstractTest;
use Silex\Application;
use Silex\Provider\DoctrineServiceProvider;
use Silex\Provider\ValidatorServiceProvider;
use Symfony\Component\Validator\Validator;

class UserTest extends AbstractTest
{
    /**
     * @var DBALUserManager
     */
    protected $userManager;

    /**
     * @var Connection
     */
    protected $conn;

    /**
     * @var Validator
     */
    protected $validator;

    public function setUp()
    {
        $app = new Application();
        $app->register(new ValidatorServiceProvider());
        $app->register(new DoctrineServiceProvider(), array(
            'db.options' => array(
                'driver' => 'pdo_sqlite',
                'memory' => true,
            ),
        ));
        $this->addValidators($app);

        $app['db']->executeUpdate(file_get_contents(__DIR__ . '/../../../../sql/sqlite.sql'));

        $this->userManager = $app['user.manager'] = new DBALUserManager($app);
        $this->conn = $app['db'];
        $this->validator = $app['validator'];
    }

    public function testNewUserHasInitialValues()
    {
        $user = new User('email@example.com');

        $this->assertInstanceOf("DateTime", $user->getTimeCreated());
        $this->assertNull($user->getTimePasswordResetRequested());
        $this->assertNotEmpty($user->getSalt());
        $this->assertTrue($user->hasRole('ROLE_USER'));
    }


    public function getValidUser()
    {

        $user = new User();
        $user->setEmail('email@example.com');
        $user->setPassword('test');

        return $user;
    }

    /**
     * @dataProvider getValidUserData
     */
    public function testValidationSuccess($data)
    {
        $user = $this->getValidUser();

        $this->assertEmpty($this->validator->validate($user));

        foreach ($data as $setter => $val) {
            $user->$setter($val);
        }

        $this->assertEmpty($this->validator->validate($user));
    }

    public function getValidUserData()
    {
        return array(
            array(array('setEmail' => str_repeat('x', 88) . '@example.com')), // 100 character email is valid
            array(array('setPassword' => str_repeat('x', 255)), array('password')), // 255 character password is valid
            array(array('setName' => str_repeat('x', 100)), array('name')),
        );
    }

    /**
     * @dataProvider getInvalidUserData
     */
    public function testValidationFailure($data, $expectedErrors)
    {
        $user = $this->getValidUser();

        foreach ($data as $setter => $val) {
            $user->$setter($val);
        }

        $errors = $this->validator->validate($user, ['full','Default']);
        foreach ($expectedErrors as $expected) {
            $this->assertSame($expected, $errors[0]->getMessage());
        }
    }

    public function getInvalidUserData()
    {
        // Format: array(array($setterMethod => $value, ...), array($expectedErrorKey, ...))
        return [
            [['setEmail' => null], ['This value should not be blank.']],
            [['setEmail' => ''], ['This value should not be blank.']],
            [['setEmail' => 'invalidEmail'], ['This value is not a valid email address.']],
            [['setPlainPassword' => null], ['This value should not be blank.']],
            [['setPlainPassword' => ''], ['This value should not be blank.']]
        ];
    }

    public function testUserIsEnabledByDefault()
    {
        $user = new User('test@example.com');

        $this->assertTrue($user->isEnabled());
    }

    public function testUserIsDisabled()
    {
        $user = new User('test@example.com');

        $user->setEnabled(false);
        $this->assertFalse($user->isEnabled());

        $user->setEnabled(true);
        $this->assertTrue($user->isEnabled());
    }
}
