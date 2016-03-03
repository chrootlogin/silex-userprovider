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
use rootLogin\UserProvider\Tests\AbstractTests\AbstractUserManagerTest;
use rootLogin\UserProvider\Tests\Entity\Orm\CustomUser;
use Silex\Application;
use Silex\Provider\DoctrineServiceProvider;
use Silex\Provider\SecurityServiceProvider;
use Doctrine\DBAL\Connection;
use Silex\Provider\ValidatorServiceProvider;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Security\Http\Logout\DefaultLogoutSuccessHandler;
use Symfony\Component\Validator\Validator;

class OrmUserManagerTest extends AbstractUserManagerTest
{
    /**
     * @var OrmUserManager
     */
    protected $userManager;

    public function setUp()
    {
        $app = parent::setUp(true);

        $this->userManager = $app['user.manager'] = new OrmUserManager($app);

        $this->createSchema();
    }

    public function testUserManager()
    {
        $this->assertInstanceOf('rootLogin\UserProvider\Manager\OrmUserManager', $this->userManager);
    }

    public function testCustomUserClass()
    {
        $this->userManager->setUserClass('\rootLogin\UserProvider\Tests\Entity\Orm\CustomUser');

        /** @var CustomUser $user */
        $user = $this->userManager->create('test@example.com', 'password');
        $this->assertInstanceOf('rootLogin\UserProvider\Tests\Entity\Orm\CustomUser', $user);

        $user->setTwitterUsername('foo');
        $errors = $this->validator->validate($user);
        $this->assertTrue($errors->count() == 1); /*@TODO*/

        $user->setTwitterUsername('@foo');
        $errors = $this->validator->validate($user);
        $this->assertEmpty($errors);
    }

    public function testSupportsSubClass()
    {
        $this->userManager->setUserClass('\rootLogin\UserProvider\Tests\Entity\Orm\CustomUser');

        $user = $this->userManager->create('test@example.com', 'password');

        $supportsObject = $this->userManager->supportsClass(get_class($user));
        $this->assertTrue($supportsObject);

        $this->userManager->save($user);
        $freshUser = $this->userManager->refreshUser($user);

        $supportsRefreshedObject = $this->userManager->supportsClass(get_class($freshUser));
        $this->assertTrue($supportsRefreshedObject);

        $this->assertTrue($freshUser instanceof CustomUser);
    }

    public function testRoleSystem()
    {
        $user = $this->userManager->create('admin@example.com', 'adminpassword');
        $this->userManager->save($user);

        $id = $user->getId();

        unset($user);

        $user = $this->userManager->getUser($id);
        $this->assertNotContains("ROLE_ADMIN", $user->getRoles());
        $user->addRole("ROLE_ADMIN");
        $this->userManager->save($user);

        unset($user);

        $user = $this->userManager->getUser($id);
        $this->assertContains("ROLE_ADMIN", $user->getRoles());
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
