<?php

namespace rootLogin\UserProvider\Tests\AbstractTests;

use rootLogin\UserProvider\Validator\Constraints\EMailExistsValidator;
use rootLogin\UserProvider\Validator\Constraints\EMailIsUniqueValidator;
use Silex\Application;
use Symfony\Component\Validator\Validator;

abstract class AbstractTest extends \PHPUnit_Framework_TestCase
{
    protected function addValidators(Application $app)
    {
        $app['validator.emailisunique'] = $app->share(function ($app) {
            $validator =  new EMailIsUniqueValidator();
            $validator->setUserManager($app['user.manager']);

            return $validator;
        });

        $app['validator.emailexists'] = $app->share(function ($app) {
            $validator =  new EMailExistsValidator();
            $validator->setUserManager($app['user.manager']);

            return $validator;
        });

        if(is_array($app['validator.validator_service_ids'])) {
            $app['validator.validator_service_ids'] = array_merge(
                $app['validator.validator_service_ids'],
                [
                    'validator.emailisunique' => 'validator.emailisunique',
                    'validator.emailexists' => 'validator.emailexists'
                ]
            );
        } else {
            $app['validator.validator_service_ids'] = [
                'validator.emailisunique' => 'validator.emailisunique',
                'validator.emailexists' => 'validator.emailexists'
            ];
        }
    }
}
