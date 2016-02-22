<?php

/**
 * @license: LGPL-3.0
 **/

namespace rootLogin\UserProvider\Tests\Entity\Orm;

use rootLogin\UserProvider\Entity\User;
use Doctrine\ORM\Mapping as ORM;

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

    public function validate()
    {
        $errors = parent::validate();

        if ($this->getTwitterUsername() && strpos($this->getTwitterUsername(), '@') !== 0) {
            $errors['twitterUsername'] = 'Twitter username must begin with @.';
        }

        return $errors;
    }
}