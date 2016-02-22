<?php

namespace rootLogin\UserProvider\Provider;

use rootLogin\UserProvider\Command\UserCreateCommand;
use rootLogin\UserProvider\Command\UserDeleteCommand;
use rootLogin\UserProvider\Command\UserListCommand;
use rootLogin\UserProvider\Controller\UserController;
use rootLogin\UserProvider\Form\Type\UserType;
use rootLogin\UserProvider\Lib\Mailer;
use rootLogin\UserProvider\Lib\TokenGenerator;
use rootLogin\UserProvider\Manager\DBALUserManager;
use rootLogin\UserProvider\Manager\OrmUserManager;
use rootLogin\UserProvider\Voter\EditUserVoter;
use Silex\Application;
use Silex\ServiceProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\Voter\RoleHierarchyVoter;
use Symfony\Component\Security\Core\SecurityContextInterface;

class UserProviderServiceProvider implements ServiceProviderInterface
{
    /**
     * @var bool
     */
    protected $forceDBAL;

    public function __construct($forceDBAL = false)
    {
        $this->forceDBAL = $forceDBAL;
    }

    /**
     * Registers services on the given app.
     *
     * This method should only be used to configure services and parameters.
     * It should not get services.
     *
     * @param Application $app An Application instance
     */
    public function register(Application $app)
    {
        // Default options.
        $app['user.options.default'] = array(

            // Specify custom view templates here.
            'templates' => array(
                'layout' => '@user/layout.twig',
                'register' => '@user/register.twig',
                'register-confirmation-sent' => '@user/register-confirmation-sent.twig',
                'login' => '@user/login.twig',
                'login-confirmation-needed' => '@user/login-confirmation-needed.twig',
                'forgot-password' => '@user/forgot-password.twig',
                'reset-password' => '@user/reset-password.twig',
                'view' => '@user/view.twig',
                'edit' => '@user/edit.twig',
                'list' => '@user/list.twig',
            ),

            // Configure the user mailer for sending password reset and email confirmation messages.
            'mailer' => array(
                'enabled' => true, // When false, email notifications are not sent (they're silently discarded).
                'fromEmail' => array(
                    'address' => 'do-not-reply@' . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : gethostname()),
                    'name' => null,
                ),
            ),

            'emailConfirmation' => array(
                'required' => false, // Whether to require email confirmation before enabling new accounts.
                'template' => '@user/email/confirm-email.twig',
            ),

            'passwordReset' => array(
                'template' => '@user/email/reset-password.twig',
                'tokenTTL' => 86400, // How many seconds the reset token is valid for. Default: 1 day.
            ),

            // Set this to use a custom User class.
            'userClass' => 'rootLogin\UserProvider\Entity\User',

            // Whether to require that users have a username (default: false).
            // By default, users sign in with their email address instead.
            'isUsernameRequired' => false,

            // A list of custom fields to support in the edit controller. (dbal mode only)
            'editCustomFields' => array(),

            // Override table names, if necessary.
            'userTableName' => 'users',
            'userCustomFieldsTableName' => 'user_custom_fields',

            // Override column names if necessary. (dbal mode only)
            'userColumns' => array(
                'id' => 'id',
                'email' => 'email',
                'password' => 'password',
                'salt' => 'salt',
                'roles' => 'roles',
                'name' => 'name',
                'time_created' => 'time_created',
                'username' => 'username',
                'isEnabled' => 'isEnabled',
                'confirmationToken' => 'confirmationToken',
                'timePasswordResetRequested' => 'timePasswordResetRequested',
                //Custom Fields
                'user_id' => 'user_id',
                'attribute' => 'attribute',
                'value' => 'value',
            )
        );

        // Initialize $app['user.options'].
        $app['user.options.init'] = $app->protect(function() use ($app) {
            $options = $app['user.options.default'];
            if (isset($app['user.options'])) {
                // Merge default and configured options
                $options = array_replace_recursive($options, $app['user.options']);

                // Migrate deprecated options for backward compatibility
                if (isset($app['user.options']['layoutTemplate']) && !isset($app['user.options']['templates']['layout'])) {
                    $options['templates']['layout'] = $app['user.options']['layoutTemplate'];
                }
                if (isset($app['user.options']['loginTemplate']) && !isset($app['user.options']['templates']['login'])) {
                    $options['templates']['login'] = $app['user.options']['loginTemplate'];
                }
                if (isset($app['user.options']['registerTemplate']) && !isset($app['user.options']['templates']['register'])) {
                    $options['templates']['register'] = $app['user.options']['registerTemplate'];
                }
                if (isset($app['user.options']['viewTemplate']) && !isset($app['user.options']['templates']['view'])) {
                    $options['templates']['view'] = $app['user.options']['viewTemplate'];
                }
                if (isset($app['user.options']['editTemplate']) && !isset($app['user.options']['templates']['edit'])) {
                    $options['templates']['edit'] = $app['user.options']['editTemplate'];
                }
                if (isset($app['user.options']['listTemplate']) && !isset($app['user.options']['templates']['list'])) {
                    $options['templates']['list'] = $app['user.options']['listTemplate'];
                }
            }
            $app['user.options'] = $options;
        });

        // Token generator.
        $app['user.tokenGenerator'] = $app->share(function($app) { return new TokenGenerator($app['logger']); });

        $app['user.manager'] = $app->share(function($app) {
            $app['user.options.init']();

            if($this->useOrm($app)) {
                $userManager = new OrmUserManager($app);
                $userManager->setUserClass($app['user.options']['userClass']);
                $userManager->setUsernameRequired($app['user.options']['isUsernameRequired']);
            } else {
                $userManager = new DBALUserManager($app['db'], $app);
                $userManager->setUserClass($app['user.options']['userClass']);
                $userManager->setUsernameRequired($app['user.options']['isUsernameRequired']);
                $userManager->setUserTableName($app['user.options']['userTableName']);
                $userManager->setUserCustomFieldsTableName($app['user.options']['userCustomFieldsTableName']);
                $userManager->setUserColumns($app['user.options']['userColumns']);
            }

            return $userManager;
        });

        // Enable orm mappings
        if($this->useOrm($app)) {
            $this->addDoctrineOrmMappings($app);
        }

        // Current user.
        $app['user'] = $app->share(function($app) {
            return ($app['user.manager']->getCurrentUser());
        });

        // User controller service.
        $app['user.controller'] = $app->share(function ($app) {
            $app['user.options.init']();

            $controller = new UserController($app['user.manager'], $app['form.factory']);
            $controller->setUsernameRequired($app['user.options']['isUsernameRequired']);
            $controller->setEmailConfirmationRequired($app['user.options']['emailConfirmation']['required']);
            $controller->setTemplates($app['user.options']['templates']);
            $controller->setEditCustomFields($app['user.options']['editCustomFields']);

            return $controller;
        });

        // Add the form types
        $app['form.types'] = $app->share($app->extend('form.types', function ($types) use ($app) {
            $types[] = new UserType();

            return $types;
        }));

        // User mailer.
        $app['user.mailer'] = $app->share(function($app) {
            $app['user.options.init']();

            $missingDeps = array();
            if (!isset($app['mailer'])) $missingDeps[] = 'SwiftMailerServiceProvider';
            if (!isset($app['url_generator'])) $missingDeps[] = 'UrlGeneratorServiceProvider';
            if (!isset($app['twig'])) $missingDeps[] = 'TwigServiceProvider';
            if (!empty($missingDeps)) {
                throw new \RuntimeException('To access the UserProvider mailer you must enable the following missing dependencies: ' . implode(', ', $missingDeps));
            }

            $mailer = new Mailer($app['mailer'], $app['url_generator'], $app['twig']);
            $mailer->setFromAddress($app['user.options']['mailer']['fromEmail']['address'] ?: null);
            $mailer->setFromName($app['user.options']['mailer']['fromEmail']['name'] ?: null);
            $mailer->setConfirmationTemplate($app['user.options']['emailConfirmation']['template']);
            $mailer->setResetTemplate($app['user.options']['passwordReset']['template']);
            $mailer->setResetTokenTtl($app['user.options']['passwordReset']['tokenTTL']);
            if (!$app['user.options']['mailer']['enabled']) {
                $mailer->setNoSend(true);
            }

            return $mailer;
        });

        // Add a custom security voter to support testing user attributes.
        $app['security.voters'] = $app->extend('security.voters', function($voters) use ($app) {
            foreach ($voters as $voter) {
                if ($voter instanceof RoleHierarchyVoter) {
                    $roleHierarchyVoter = $voter;
                    break;
                }
            }
            $voters[] = new EditUserVoter($roleHierarchyVoter);
            return $voters;
        });

        // Helper function to get the last authentication exception thrown for the given request.
        // It does the same thing as $app['security.last_error'](),
        // except it returns the whole exception instead of just $exception->getMessage()
        $app['user.last_auth_exception'] = $app->protect(function (Request $request) {
            if ($request->attributes->has(SecurityContextInterface::AUTHENTICATION_ERROR)) {
                return $request->attributes->get(SecurityContextInterface::AUTHENTICATION_ERROR);
            }

            $session = $request->getSession();
            if ($session && $session->has(SecurityContextInterface::AUTHENTICATION_ERROR)) {
                $exception = $session->get(SecurityContextInterface::AUTHENTICATION_ERROR);
                $session->remove(SecurityContextInterface::AUTHENTICATION_ERROR);

                return $exception;
            }
        });

        // If symfony console is available, enable them
        if (isset($app['console.commands'])) {
            $app['console.commands'] = $app->share(
                $app->extend('console.commands', function ($commands) use ($app) {
                    $commands[] = new UserCreateCommand($app);
                    $commands[] = new UserListCommand($app);
                    $commands[] = new UserDeleteCommand($app);

                    return $commands;
                })
            );
        }
    }

    /**
     * Bootstraps the application.
     *
     * This method is called after all services are registered
     * and should be used for "dynamic" configuration (whenever
     * a service must be requested).
     */
    public function boot(Application $app)
    {
        // Add twig template path.
        if (isset($app['twig.loader.filesystem'])) {
            $app['twig.loader.filesystem']->addPath(__DIR__ . '/../Resources/views/', 'user');
        }

        // Validate the mailer configuration.
        $app['user.options.init']();
        try {
            $mailer = $app['user.mailer'];
            $mailerExists = true;
        } catch (\RuntimeException $e) {
            $mailerExists = false;
            $mailerError = $e->getMessage();
        }
        if ($app['user.options']['emailConfirmation']['required'] && !$mailerExists) {
            throw new \RuntimeException('Invalid configuration. Cannot require email confirmation because user mailer is not available. ' . $mailerError);
        }
        if ($app['user.options']['mailer']['enabled'] && !$app['user.options']['mailer']['fromEmail']['address']) {
            throw new \RuntimeException('Invalid configuration. Mailer fromEmail address is required when mailer is enabled.');
        }
        if (!$mailerExists) {
            $app['user.controller']->setPasswordResetEnabled(false);
        }

        if (isset($app['user.passwordStrengthValidator'])) {
            $app['user.manager']->setPasswordStrengthValidator($app['user.passwordStrengthValidator']);
        }
    }

    protected function addDoctrineOrmMappings(Application $app)
    {
        if (!isset($app['orm.ems.options'])) {
            $app['orm.ems.options'] = $app->share(function () use ($app) {
                $options = array(
                    'default' => $app['orm.em.default_options']
                );
                return $options;
            });
        }

        $app['orm.ems.options'] = $app->share($app->extend('orm.ems.options', function (array $options) {
            $options['default']['mappings'][] = array(
                'type' => 'annotation',
                'namespace' => 'rootLogin\UserProvider\Entity',
                'path' => $this->getEntityPath(),
                'use_simple_annotation_reader' => false,
            );
            return $options;
        }));
    }

    /**
     * @return string
     */
    protected function getEntityPath()
    {
        return realpath(__DIR__ . "/../Entity/");
    }

    /**
     * True if Orm is available.
     *
     * @return bool
     */
    protected function useOrm($app)
    {
        return (isset($app['orm.em']) && !$this->forceDBAL);
    }
}