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

class OrmUserManager extends UserManager
{
    /** @var EntityManager */
    protected $em;

    /**
     * Constructor.
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        parent::__construct($app);

        $this->em = $app['orm.em'];
    }

    // ----- UserProviderInterface -----

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

    /**
     * Get a User instance by its ID.
     *
     * @param int $id
     * @return User|null The User, or null if there is no User with that ID.
     */
    public function getUser($id)
    {
        return $this->em->getRepository($this->userClass)->find($id);
    }

    /**
     * @inheritdoc
     */
    public function findOneBy(array $criteria, array $orderBy = null)
    {
        return $this->em->getRepository($this->userClass)->findOneBy($criteria, $orderBy);
    }

    /**
     * @inheritdoc
     */
    public function findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
    {
        return $this->em->getRepository($this->userClass)->findBy($criteria, $orderBy, $limit, $offset);
    }

    /**
     * @inheritdoc
     */
    public function findCount(array $criteria = array())
    {
        return count($this->findBy($criteria));
    }

    public function save(User $user) {
        $id = $user->getId();
        if($id != null) {
            $this->dispatcher->dispatch(UserEvents::BEFORE_UPDATE, new UserEvent($user));
        } else {
            $this->dispatcher->dispatch(UserEvents::BEFORE_INSERT, new UserEvent($user));
        }

        $this->em->persist($user);
        $this->em->flush();

        if($id != null) {
            $this->dispatcher->dispatch(UserEvents::AFTER_UPDATE, new UserEvent($user));
        } else {
            $this->dispatcher->dispatch(UserEvents::AFTER_INSERT, new UserEvent($user));
        }
    }

    /**
     * @inheritdoc
     */
    public function delete(User $user)
    {
        $this->dispatcher->dispatch(UserEvents::BEFORE_DELETE, new UserEvent($user));

        $this->em->remove($user);
        $this->em->flush();

        $this->dispatcher->dispatch(UserEvents::AFTER_DELETE, new UserEvent($user));
    }

    /**
     * @inheritdoc
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

        // Ensure username is unique or null.
        if($user->hasRealUsername()) {
            $duplicates = $this->findBy(array('username' => $user->getRealUsername()));
            if (!empty($duplicates)) {
                foreach ($duplicates as $dup) {
                    if ($user->getId() && $dup->getId() == $user->getId()) {
                        continue;
                    }
                    $errors['username'] = 'An account with that username already exists.';
                }
            }
        }

        // If username is required, ensure it is set.
        if ($this->isUsernameRequired && !$user->getRealUsername()) {
            $errors['username'] = 'Username is required.';
        }

        return $errors;
    }

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
}
