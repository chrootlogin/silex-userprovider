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

namespace rootLogin\UserProvider\Form\DataTransformer;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;
use Liip\InApp\Entity\Place;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

class RolesToRoleListTransformer implements DataTransformerInterface
{
    /**
     * @var array
     */
    protected $userOptions;

    public function __construct(array $userOptions)
    {
        $this->userOptions = $userOptions;
    }

    /**
     * Transforms an array to integer (number).
     *
     * @param  array $place
     * @return ArrayCollection
     */
    public function transform($roles)
    {
        $rolesAssoc = [];
        foreach($this->userOptions['roles'] as $role => $description) {
            $rolesAssoc[$role] = false;
            if(in_array($role, $roles)) {
                $rolesAssoc[$role] = true;
            }
        }

        return $rolesAssoc;
    }

    /**
     * Transforms a postcode to a place.
     *
     * @param  int $postcode
     * @return Place|null
     * @throws TransformationFailedException if place is not found.
     */
    public function reverseTransform($rolesAssoc)
    {
        $roles = [];
        foreach($rolesAssoc as $role => $enabled) {
            if($enabled) {
                $roles[] = $role;
            }
        }

        return $roles;
    }
}