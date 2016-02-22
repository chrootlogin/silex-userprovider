<?php

namespace Liip\InApp\Validator\Constraints;

use Doctrine\ORM\EntityManager;
use rootLogin\UserProvider\Interfaces\UserManagerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class EMailIsUniqueValidator extends ConstraintValidator
{
    /**
     * @var UserManagerInterface
     */
    private $userManager;

    public function validate($value, Constraint $constraint)
    {
        $exists = $this->userManager->findOneBy(
            array('email' => $value)
        );

        if ($exists != null) {
            $this->context->addViolation($constraint->eMailExists, array('{{ email }}' => $value));

            return false;
        }

        return true;
    }

    public function setUserManager(UserManagerInterface $userManager)
    {
        $this->userManager = $userManager;
    }
}