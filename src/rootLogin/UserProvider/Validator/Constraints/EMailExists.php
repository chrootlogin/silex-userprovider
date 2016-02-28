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

use Symfony\Component\Validator\Constraint;
use Doctrine\Common\Annotations\Annotation;

/**
 * Class EMailExists
 *
 * @Annotation()
 * @package rootLogin\UserProvider\Validator\Constraints
 */
class EMailExists extends Constraint
{
    public $eMailDoesNotExist = 'Email {{ email }} doesn\'t exist.';
    public $entity;
    public $field;

    public function validatedBy()
    {
        return 'validator.emailexists';
    }

    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }
}