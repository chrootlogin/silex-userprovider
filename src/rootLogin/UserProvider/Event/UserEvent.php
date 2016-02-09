<?php

namespace rootLogin\UserProvider\Event;

use rootLogin\UserProvider\Entity\User;
use Symfony\Component\EventDispatcher\Event;

class UserEvent extends Event
{
    protected $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }
}