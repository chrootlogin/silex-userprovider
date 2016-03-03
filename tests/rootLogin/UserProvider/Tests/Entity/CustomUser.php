<?php

namespace rootLogin\UserProvider\Tests\Entity;

use rootLogin\UserProvider\Entity\LegacyUser;

class CustomUser extends LegacyUser
{
    public function getTwitterUsername()
    {
        return $this->getCustomField('twitterUsername');
    }

    public function setTwitterUsername($twitterUsername)
    {
        $this->setCustomField('twitterUsername', $twitterUsername);
    }
}