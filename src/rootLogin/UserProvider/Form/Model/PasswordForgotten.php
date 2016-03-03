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

namespace rootLogin\UserProvider\Form\Model;

use rootLogin\UserProvider\Validator\Constraints\EMailExists;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * Class PasswordForgotten
 *
 * @EMailExists()
 *
 * @package rootLogin\UserProvider\Form\Model
 */
class PasswordForgotten {

    /**
     * @var string
     *
     * @Assert\Email()
     */
    protected $email;

    /**
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @param string $email
     * @return PasswordForgotten
     */
    public function setEmail($email)
    {
        $this->email = $email;

        return $this;
    }

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addConstraints([
            new EMailExists()
        ]);
    }
}