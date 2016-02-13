<?php

/**
 * @license: LGPL-3.0
 **/

namespace rootLogin\UserProvider\Manager;

use rootLogin\UserProvider\Entity\User;
use rootLogin\UserProvider\Interfaces\UserManagerInterface;
use Silex\Application;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Encoder\PasswordEncoderInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;

abstract class UserManager implements UserManagerInterface
{
    /** @var Application */
    protected $app;

    /** @var EventDispatcher */
    protected $dispatcher;

    /** @var string */
    protected $userClass = '\rootLogin\UserProvider\Entity\User';

    /** @var Callable */
    protected $passwordStrengthValidator;

    /** @var bool */
    protected $isUsernameRequired = false;

    /**
     * Constructor.
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->dispatcher = $app['dispatcher'];
    }

    /**
     * Refreshes the user for the account interface.
     *
     * It is up to the implementation to decide if the user data should be
     * totally reloaded (e.g. from the database), or if the UserInterface
     * object can just be merged into some internal array of users / identity
     * map.
     *
     * @param UserInterface $user
     * @return UserInterface
     * @throws UnsupportedUserException if the account is not supported
     */
    public function refreshUser(UserInterface $user)
    {
        if (!$this->supportsClass(get_class($user))) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_class($user)));
        }

        return $this->getUser($user->getId());
    }

    /**
     * @inheritdoc
     */
    public function loginAsUser(User $user)
    {
        if (null !== ($current_token = $this->app['security']->getToken())) {
            $providerKey = method_exists($current_token, 'getProviderKey') ? $current_token->getProviderKey() : $current_token->getKey();
            $token = new UsernamePasswordToken($user, null, $providerKey);
            $this->app['security']->setToken($token);

            $this->app['user'] = $user;
        }
    }

    /**
     * @inheritdoc
     */
    public function create($email, $plainPassword, $name = null, $roles = array()) {
        $userClass = $this->getUserClass();

        $user = new $userClass($email);

        if (!empty($plainPassword)) {
            $this->setUserPassword($user, $plainPassword);
        }

        if ($name !== null) {
            $user->setName($name);
        }
        if (!empty($roles)) {
            $user->setRoles($roles);
        }

        return $user;
    }

    /**
     * Get the password encoder to use for the given user object.
     *
     * @param UserInterface $user
     * @return PasswordEncoderInterface
     */
    protected function getEncoder(UserInterface $user)
    {
        return $this->app['security.encoder_factory']->getEncoder($user);
    }

    /**
     * Encode a plain text password for a given user. Hashes the password with the given user's salt.
     *
     * @param User $user
     * @param string $password A plain text password.
     * @return string An encoded password.
     */
    public function encodeUserPassword(User $user, $password)
    {
        $encoder = $this->getEncoder($user);

        return $encoder->encodePassword($password, $user->getSalt());
    }

    /**
     * Encode a plain text password and set it on the given User object.
     *
     * @param User $user
     * @param string $password A plain text password.
     * @return UserManager
     */
    public function setUserPassword(User $user, $password)
    {
        $user->setPassword($this->encodeUserPassword($user, $password));

        return $this;
    }

    /**
     * Test whether a plain text password is strong enough.
     *
     * Note that controllers must call this explicitly,
     * it's NOT called automatically when setting a password or validating a user.
     *
     * This is just a proxy for the Callable set by setPasswordStrengthValidator().
     * If no password strength validator Callable is explicitly set,
     * by default the only requirement is that the password not be empty.
     *
     * @param User $user
     * @param $password
     * @return string|null An error message if validation fails, null if validation succeeds.
     */
    public function validatePasswordStrength(User $user, $password)
    {
        return call_user_func($this->getPasswordStrengthValidator(), $user, $password);
    }

    /**
     * @return callable
     */
    public function getPasswordStrengthValidator()
    {
        if (!is_callable($this->passwordStrengthValidator)) {
            return function(User $user, $password) {
                if (empty($password)) {
                    return 'Password cannot be empty.';
                }

                return null;
            };
        }

        return $this->passwordStrengthValidator;
    }

    /**
     * Specify a callable to test whether a given password is strong enough.
     *
     * Must take a User instance and a password string as arguments,
     * and return an error string on failure or null on success.
     *
     * @param Callable $callable
     * @throws \InvalidArgumentException
     * @return UserManager
     */
    public function setPasswordStrengthValidator($callable)
    {
        if (!is_callable($callable)) {
            throw new \InvalidArgumentException('Password strength validator must be Callable.');
        }

        $this->passwordStrengthValidator = $callable;

        return $this;
    }

    /**
     * Test whether a given plain text password matches a given User's encoded password.
     *
     * @param User $user
     * @param string $password
     * @return bool
     */
    public function checkUserPassword(User $user, $password)
    {
        return $user->getPassword() === $this->encodeUserPassword($user, $password);
    }

    /**
     * Get a User instance for the currently logged in User, if any.
     *
     * @return UserInterface|null
     */
    public function getCurrentUser()
    {
        if ($this->isLoggedIn()) {
            return $this->app['security']->getToken()->getUser();
        }

        return null;
    }

    /**
     * Test whether the current user is authenticated.
     *
     * @return boolean
     */
    function isLoggedIn()
    {
        $token = $this->app['security']->getToken();
        if (null === $token) {
            return false;
        }

        return $this->app['security']->isGranted('IS_AUTHENTICATED_REMEMBERED');
    }

    /**
     * @param string $userClass The class to use for the user model. Must extend rootLogin\UserProvider\Entity\User.
     * @return UserManager
     */
    public function setUserClass($userClass)
    {
        $this->userClass = $userClass;

        return $this;
    }

    /**
     * @return string
     */
    public function getUserClass()
    {
        return $this->userClass;
    }

    /**
     * @param $isRequired
     * @return UserManager
     */
    public function setUsernameRequired($isRequired)
    {
        $this->isUsernameRequired = (bool) $isRequired;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getUsernameRequired()
    {
        return $this->isUsernameRequired;
    }
}