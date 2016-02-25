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

namespace rootLogin\UserProvider\Interfaces;

use rootLogin\UserProvider\Entity\User;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

interface UserManagerInterface extends UserProviderInterface {

    /**
     * @return User
     */
    public function getEmptyUser();

    /**
     * Factory method for creating a new User instance.
     *
     * @param string $email
     * @param string $plainPassword
     * @param string $name
     * @param array $roles
     * @return User
     */
    public function create($email, $plainPassword, $name = null, $roles = array());

    /**
     * Persist a user
     *
     * @param User $user
     * @return mixed
     */
    public function save(User $user);

    /**
     * Delete a User from the database.
     *
     * @param User $user
     */
    public function delete(User $user);

    /**
     * Get a User instance by its ID.
     *
     * @param int $id
     * @return User|null The User, or null if there is no User with that ID.
     */
    public function getUser($id);

    /**
     * Find User instances that match the given criteria.
     *
     * @param array      $criteria
     * @param array|null $orderBy
     * @param int|null   $limit
     * @param int|null   $offset
     *
     * @return array The users.
     */
    public function findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null);

    /**
     * Get a single User instance that matches the given criteria. If more than one User matches, the first result is returned.
     *
     * @param array $criteria
     * @param array|null $orderBy
     * @return User|null
     */
    public function findOneBy(array $criteria, array $orderBy = null);

    /**
     * Count users that match the given criteria.
     *
     * @param array $criteria
     * @return int The number of users that match the criteria.
     */
    public function findCount(array $criteria);

    /**
     * Log in as the given user.
     *
     * Sets the security token for the current request so it will be logged in as the given user.
     *
     * @param User $user
     */
    public function loginAsUser(User $user);

    /**
     * Validate a user object.
     *
     * Invokes User::validate(),
     * and additionally tests that the User's email address and username (if set) are unique across all users.'.
     *
     * @param User $user
     * @deprecated
     * @return array An array of error messages, or an empty array if the User is valid.
     */
    public function validate(User $user);

    /**
     * Encode a plain text password and set it on the given User object.
     *
     * @param User $user
     * @param string $password A plain text password.
     * @return UserManagerInterface
     */
    public function setUserPassword(User $user, $password);

    /**
     * Get a User instance for the currently logged in User, if any.
     *
     * @return UserInterface|null
     */
    public function getCurrentUser();
}