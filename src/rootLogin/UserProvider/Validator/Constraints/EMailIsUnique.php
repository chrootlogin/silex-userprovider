<?php

namespace rootLogin\UserProvider\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

class EMailIsUnique extends Constraint
{
    public $eMailExists = 'E-Mail {{ email }} does already exist.';
    public $entity;
    public $field;

    public function validatedBy()
    {
        return 'validator.emailisunique';
    }
}