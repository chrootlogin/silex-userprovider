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

namespace rootLogin\UserProvider\Validator\Constraints;

use rootLogin\UserProvider\Entity\User;
use rootLogin\UserProvider\Interfaces\UserManagerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class EMailIsUniqueValidator extends ConstraintValidator
{
    /**
     * @var UserManagerInterface
     */
    private $userManager;

    public function validate($user, Constraint $constraint)
    {
        /** @var User $user */
        $exists = $this->userManager->findOneBy(
            array('email' => $user->getEmail())
        );

        if ($exists !== null && $user !== $exists) {
            $this->context->addViolationAt('email', $constraint->eMailExists, array('{{ email }}' => $user->getEmail()));

            return false;
        }

        return true;
    }

    public function setUserManager(UserManagerInterface $userManager)
    {
        $this->userManager = $userManager;
    }
}