<?php

/**
 * @license: LGPL-3.0
 **/

namespace rootLogin\UserProvider\Manager;

use Doctrine\ORM\EntityManager;
use rootLogin\UserProvider\Interfaces\UserManagerInterface;
use Saxulum\DoctrineOrmManagerRegistry\Doctrine\ManagerRegistry;
use rootLogin\UserProvider\Entity\User;
use rootLogin\UserProvider\Event\UserEvent;
use rootLogin\UserProvider\Event\UserEvents;
use Silex\Application;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Encoder\PasswordEncoderInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;

class OrmUserManager implements UserManagerInterface
{
    /** @var \Silex\Application */
    protected $app;

    /** @var EventDispatcher */
    protected $dispatcher;

    /** @var EntityManager */
    protected $em;

    /** @var string */
    protected $userClass = '\rootLogin\UserProvider\Entity\User';

    /** @var bool */
    protected $isUsernameRequired = false;

    /** @var Callable */
    protected $passwordStrengthValidator;

    /**
     * Constructor.
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->dispatcher = $app['dispatcher'];
        $this->em = $app['orm.em'];
    }

    // ----- UserProviderInterface -----

    /**
     * Loads the user for the given username or email address.
     *
     * Required by UserProviderInterface.
     *
     * @param string $username The username
     * @return UserInterface
     * @throws UsernameNotFoundException if the user is not found
     */
    public function loadUserByUsername($username)
    {
        if (strpos($username, '@') !== false) {
            $user = $this->findOneBy(array('email' => $username));
            if (!$user) {
                throw new UsernameNotFoundException(sprintf('Email "%s" does not exist.', $username));
            }

            return $user;
        }

        $user = $this->findOneBy(array('username' => $username));
        if (!$user) {
            throw new UsernameNotFoundException(sprintf('Username "%s" does not exist.', $username));
        }

        return $user;
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
     * Whether this provider supports the given user class
     *
     * @param string $class
     * @return Boolean
     */
    public function supportsClass($class)
    {
        return ($class === 'rootLogin\UserProvider\Entity\User') || is_subclass_of($class, 'rootLogin\UserProvider\Entity\User');
    }

    // ----- End UserProviderInterface -----

    public function create($email, $plainPassword, $name = null, $roles = array())
    {
        return $this->createUser($email, $plainPassword, $name, $roles);
    }
    /**
     * Factory method for creating a new User instance.
     *
     * @param string $email
     * @param string $plainPassword
     * @param string $name
     * @param array $roles
     * @return User
     */
    public function createUser($email, $plainPassword, $name = null, $roles = array())
    {

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
     */
    public function setUserPassword(User $user, $password)
    {
        $user->setPassword($this->encodeUserPassword($user, $password));
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
     */
    public function setPasswordStrengthValidator($callable)
    {
        if (!is_callable($callable)) {
            throw new \InvalidArgumentException('Password strength validator must be Callable.');
        }

        $this->passwordStrengthValidator = $callable;
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
     * Get a User instance by its ID.
     *
     * @param int $id
     * @return User|null The User, or null if there is no User with that ID.
     */
    public function getUser($id)
    {
        return $this->em->getRepository($this->userClass)->findOneById($id);
    }

    /**
     * @inheritdoc
     */
    public function findOneBy(array $criteria)
    {
        return $this->em->getRepository($this->userClass)->findOneBy($criteria);
    }

    /**
     * @inheritdoc
     */
    public function findBy(array $criteria = array(), array $options = array())
    {
        return $this->em->getRepository($this->userClass)->findBy($criteria);
    }

    /**
     * Count users that match the given criteria.
     *
     * @param array $criteria
     * @return int The number of users that match the criteria.
     */
    public function findCount(array $criteria = array())
    {
        return count($this->findBy($criteria));
    }

    /**
     * Insert a new User instance into the database.
     *
     * @param User $user
     */
    public function insert(User $user)
    {
        $this->dispatcher->dispatch(UserEvents::BEFORE_INSERT, new UserEvent($user));

        $this->em->persist($user);
        $this->em->flush();

        $this->dispatcher->dispatch(UserEvents::AFTER_INSERT, new UserEvent($user));
    }

    /**
     * Update data in the database for an existing user.
     *
     * @param User $user
     */
    public function update(User $user)
    {
        $this->dispatcher->dispatch(UserEvents::BEFORE_UPDATE, new UserEvent($user));

        $this->em->persist($user);
        $this->em->flush();

        $this->dispatcher->dispatch(UserEvents::AFTER_UPDATE, new UserEvent($user));
    }

    /**
     * Delete a User from the database.
     *
     * @param User $user
     */
    public function delete(User $user)
    {
        $this->dispatcher->dispatch(UserEvents::BEFORE_DELETE, new UserEvent($user));

        $this->em->remove($user);
        $this->em->flush();

        $this->dispatcher->dispatch(UserEvents::AFTER_DELETE, new UserEvent($user));
    }

    /**
     * Validate a user object.
     *
     * Invokes User::validate(),
     * and additionally tests that the User's email address and username (if set) are unique across all users.'.
     *
     * @param User $user
     * @return array An array of error messages, or an empty array if the User is valid.
     */
    public function validate(User $user)
    {
        $errors = $user->validate();

        // Ensure email address is unique.
        $duplicates = $this->findBy(array('email' => $user->getEmail()));
        if (!empty($duplicates)) {
            foreach ($duplicates as $dup) {
                if ($user->getId() && $dup->getId() == $user->getId()) {
                    continue;
                }
                $errors['email'] = 'An account with that email address already exists.';
            }
        }

        // Ensure username is unique.
        $duplicates = $this->findBy(array('username' => $user->getRealUsername()));
        if (!empty($duplicates)) {
            foreach ($duplicates as $dup) {
                if ($user->getId() && $dup->getId() == $user->getId()) {
                    continue;
                }
                $errors['username'] = 'An account with that username already exists.';
            }
        }

        // If username is required, ensure it is set.
        if ($this->isUsernameRequired && !$user->getRealUsername()) {
            $errors['username'] = 'Username is required.';
        }

        return $errors;
    }

    /**
     * @param string $userClass The class to use for the user model. Must extend rootLogin\UserProvider\Entity\User.
     */
    public function setUserClass($userClass)
    {
        $this->userClass = $userClass;
    }

    /**
     * @return string
     */
    public function getUserClass()
    {
        return $this->userClass;
    }

    public function setUsernameRequired($isRequired)
    {
        $this->isUsernameRequired = (bool) $isRequired;
    }

    public function getUsernameRequired()
    {
        return $this->isUsernameRequired;
    }

    public function setUserTableName($userTableName)
    {
        $this->userTableName = $userTableName;
    }

    public function getUserTableName()
    {
        return $this->userTableName;
    }


    public function setUserCustomFieldsTableName($userCustomFieldsTableName)
    {
        $this->userCustomFieldsTableName = $userCustomFieldsTableName;
    }

    public function getUserCustomFieldsTableName()
    {
        return $this->userCustomFieldsTableName;
    }

    /**
     * Log in as the given user.
     *
     * Sets the security token for the current request so it will be logged in as the given user.
     *
     * @param User $user
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
}
