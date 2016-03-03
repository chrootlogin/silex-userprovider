<?php

/**
 * @license: LGPL-3.0
 **/

namespace rootLogin\UserProvider\Tests\Entity\Orm;

use rootLogin\UserProvider\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A custom user
 *
 * @ORM\Entity()
 * @ORM\Table(name="customuser")
 *
 * @package rootLogin\UserProvider
 */
class CustomUser extends User
{
    /**
     * @var string
     *
     * @ORM\Column(name="twitterUsername", type="string", nullable=true)
     */
    protected $twitterUsername;

    public function getTwitterUsername()
    {
        return $this->twitterUsername;
    }

    public function setTwitterUsername($twitterUsername)
    {
        $this->twitterUsername = $twitterUsername;
    }

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        parent::loadValidatorMetadata($metadata);
        $metadata->addPropertyConstraint('twitterUsername', new Assert\Regex('/^@(\w){1,15}$/s'));
    }
}