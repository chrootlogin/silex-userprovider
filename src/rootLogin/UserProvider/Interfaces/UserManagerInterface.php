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
use Symfony\Component\Security\Core\User\UserProviderInterface;

interface UserManagerInterface extends UserProviderInterface {

    public function create($email, $plainPassword, $name = null, $roles = array());

    /**
     * Find User instances that match the given criteria.
     *
     * @param array $criteria
     * @param array $options An array of the following options (all optional):<pre>
     *      limit (int|array) The maximum number of results to return, or an array of (offset, limit).
     *      order_by (string|array) The name of the column to order by, or an array of column name and direction, ex. array(time_created, DESC)
     * </pre>
     * @return User[] An array of matching User instances, or an empty array if no matching users were found.
     */
    public function findBy(array $criteria = array(), array $options = array());

    /**
     * Get a single User instance that matches the given criteria. If more than one User matches, the first result is returned.
     *
     * @param array $criteria
     * @return User|null
     */
    public function findOneBy(array $criteria);
}