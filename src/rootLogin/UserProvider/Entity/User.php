<?php

/**
 * Silex User Provider
 *
 *  Copyright 2016 by Simon Erhardt <hello@rootlogin.ch>
 *
 * This file is part of the silex user provider.
 *
 * The silex user provider is free software: you can redistribute
 * it and/or modify it under the terms of the Lesser GNU General Public
 * License version 3 as published by the Free Software Foundation.
 *
 * The silex user provider is distributed in the hope that it will
 * be useful, but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * You should have received a copy of the Lesser GNU General Public
 * License along with the silex user provider.  If not, see
 * <http://www.gnu.org/licenses/>.
 *
 * @license LGPL-3.0 <http://spdx.org/licenses/LGPL-3.0>
 */

namespace rootLogin\UserProvider\Entity;

use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\AdvancedUserInterface;
use rootLogin\UserProvider\Validator\Constraints\EMailIsUnique;
/**
 * A user
 *
 * @ORM\Entity()
 * @ORM\Table(name="user")
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="discr", type="string")
 * @EMailIsUnique()
 *
 * @package rootLogin\UserProvider
 */
class User implements AdvancedUserInterface, \Serializable
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var string
     *
     * @ORM\Column(name="username", type="string", nullable=true)
     */
    protected $username;

    /**
     * @var string
     *
     * @Assert\NotBlank()
     * @Assert\Email()
     *
     * @ORM\Column(name="email", type="string", unique=true)
     */
    protected $email;

    /**
     * @var string
     *
     * @ORM\Column(name="password", type="string")
     */
    protected $password;

    /**
     * Plain password. Used for model validation. Must not be persisted.
     *
     * @Assert\NotBlank(groups={"full"})
     * @Assert\Length(min=8, groups={"full"})
     * @var string
     */
    protected $plainPassword;

    /**
     * @var string
     *
     * @ORM\Column(name="salt", type="string")
     */
    protected $salt;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", nullable=true)
     */
    protected $name;

    /**
     * @var array
     *
     * @ORM\Column(name="roles", type="array")
     */
    protected $roles = array();

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created", type="datetime")
     */
    protected $timeCreated;

    /**
     * @var string
     *
     * @ORM\Column(name="enabled", type="boolean")
     */
    protected $isEnabled = true;

    /**
     * @var string
     *
     * @ORM\Column(name="token", type="string", nullable=true)
     */
    protected $confirmationToken;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="timeResetRequested", type="datetime", nullable=true)
     */
    protected $timePasswordResetRequested;

    /**
     * Constructor.
     *
     * @param string $email
     */
    public function __construct()
    {
        $this->timeCreated = new \DateTime();
        $this->salt = base_convert(sha1(uniqid(mt_rand(), true)), 16, 36);
    }

    /**
     * Get the user ID.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }


    /**
     * Set the user ID.
     *
     * @param int $id
     * @return User
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Returns the username, if not empty, otherwise the email address.
     *
     * Email is returned as a fallback because username is optional,
     * but the Symfony Security system depends on getUsername() returning a value.
     * Use getRealUsername() to get the actual username value.
     *
     * This method is required by the UserInterface.
     *
     * @see getRealUsername
     * @return string The username, if not empty, otherwise the email.
     */
    public function getUsername()
    {
        return $this->username ?: $this->email;
    }

    /**
     * Get the actual username value that was set,
     * or null if no username has been set.
     * Compare to getUsername, which returns the email if username is not set.
     *
     * @see getUsername
     * @return string|null
     */
    public function getRealUsername()
    {
        return $this->username;
    }

    /**
     * Test whether username has ever been set (even if it's currently empty).
     *
     * @return bool
     */
    public function hasRealUsername()
    {
        return !is_null($this->username);
    }

    /**
     * Sets the username
     *
     * @param string $username
     * @return User
     */
    public function setUsername($username)
    {
        $this->username = $username;

        return $this;
    }

    /**
     * Gets the Email
     *
     * @return string The user's email address.
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * Sets the Email
     *
     * @param string $email
     * @return User
     */
    public function setEmail($email)
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Get the encoded password used to authenticate the user.
     *
     * On authentication, a plain-text password will be salted,
     * encoded, and then compared to this value.
     *
     * @return string The encoded password.
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * Sets the encoded password.
     *
     * @param string $password
     * @return User
     */
    public function setPassword($password)
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Gets the plain password.
     * Used for model validation. Must not be persisted.
     *
     * @return string
     */
    public function getPlainPassword()
    {
        return $this->plainPassword;
    }

    /**
     * Sets the plain password.
     * Used for model validation. Must not be persisted.
     *
     * @param $plainPassword
     * @return User
     */
    public function setPlainPassword($plainPassword)
    {
        $this->plainPassword = $plainPassword;

        return $this;
    }

    /**
     * Returns the salt that was originally used to encode the password.
     *
     * This can return null if the password was not encoded using a salt.
     *
     * @return string The salt
     */
    public function getSalt()
    {
        return $this->salt;
    }

    /**
     * Set the salt that should be used to encode the password.
     *
     * @param string $salt
     * @return User
     */
    public function setSalt($salt)
    {
        $this->salt = $salt;

        return $this;
    }

    /**
     * Get the name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Returns the name, if set, or else "Anonymous {id}".
     *
     * @return string
     */
    public function getDisplayName()
    {
        return $this->name ?: 'Anonymous ' . $this->id;
    }

    /**
     * Sets the name
     *
     * @param string $name
     * @return User
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Returns the roles granted to the user. Note that all users have the ROLE_USER role.
     *
     * @return array A list of the user's roles.
     */
    public function getRoles()
    {
        $roles = $this->roles;

        // Every user must have at least one role, per Silex security docs.
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * Set the user's roles to the given list.
     *
     * @param array $roles
     * @return User
     */
    public function setRoles(array $roles)
    {
        $this->roles = array();

        foreach ($roles as $role) {
            $this->addRole($role);
        }

        return $this;
    }

    /**
     * Test whether the user has the given role.
     *
     * @param string $role
     * @return bool
     */
    public function hasRole($role)
    {
        return in_array(strtoupper($role), $this->getRoles(), true);
    }

    /**
     * Add the given role to the user.
     *
     * @param string $role
     * @return User
     */
    public function addRole($role)
    {
        $role = strtoupper($role);

        if ($role === 'ROLE_USER') {
            return $this;
        }

        if (!$this->hasRole($role)) {
            $this->roles[] = $role;
        }

        return $this;
    }

    /**
     * Remove the given role from the user.
     *
     * @param string $role
     * @return User
     */
    public function removeRole($role)
    {
        if (false !== $key = array_search(strtoupper($role), $this->roles, true)) {
            unset($this->roles[$key]);
            $this->roles = array_values($this->roles);
        }

        return $this;
    }

    /**
     * Set the time the user was originally created.
     *
     * @param \DateTime $timeCreated A timestamp value.
     * @return User
     */
    public function setTimeCreated(\DateTime $timeCreated)
    {
        $this->timeCreated = $timeCreated;

        return $this;
    }

    /**
     * Set the time the user was originally created.
     *
     * @return \DateTime
     */
    public function getTimeCreated()
    {
        return $this->timeCreated;
    }

    /**
     * Checks whether the user is enabled.
     *
     * Internally, if this method returns false, the authentication system
     * will throw a DisabledException and prevent login.
     *
     * Users are enabled by default.
     *
     * @return bool    true if the user is enabled, false otherwise
     *
     * @see DisabledException
     */
    public function isEnabled()
    {
        return $this->isEnabled;
    }

    /**
     * Set whether the user is enabled.
     *
     * @param bool $isEnabled
     * @return User
     */
    public function setEnabled($isEnabled)
    {
        $this->isEnabled = (bool) $isEnabled;

        return $this;
    }

    /**
     * Gets the confirmation token
     *
     * @return string
     */
    public function getConfirmationToken()
    {
        return $this->confirmationToken;
    }

    /**
     * Sets the confirmation token
     *
     * @param string $token
     * @return User
     */
    public function setConfirmationToken($token)
    {
        $this->confirmationToken = $token;

        return $this;
    }

    /**
     * @return \DateTime|null
     */
    public function getTimePasswordResetRequested()
    {
        return $this->timePasswordResetRequested;
    }

    /**
     * @param int $ttl Password reset request TTL, in seconds.
     *
     * @return bool
     */
    public function isPasswordResetRequestExpired($ttl)
    {
        $timeRequested = $this->getTimePasswordResetRequested();
        if (!$timeRequested) {
            return true;
        }

        return $timeRequested->getTimestamp() + $ttl < time();
    }

    /**
     * Sets the password requested time
     *
     * @param \DateTime|null $dateTime
     * @return User
     */
    public function setTimePasswordResetRequested(\DateTime $dateTime)
    {
        $this->timePasswordResetRequested = $dateTime ?: null;

        return $this;
    }

    /**
     * Removes sensitive data from the user.
     *
     * @return void
     */
    public function eraseCredentials()
    {
        unset($this->plainPassword);
    }

    /**
     * The Symfony Security component stores a serialized User object in the session.
     * We only need it to store the user ID, because the user provider's refreshUser() method is called on each request
     * and reloads the user by its ID.
     *
     * @see \Serializable::serialize()
     */
    public function serialize()
    {
        return serialize(array(
            $this->id,
        ));
    }

    /**
     * @see \Serializable::unserialize()
     */
    public function unserialize($serialized)
    {
        list (
            $this->id,
            ) = unserialize($serialized);
    }

    // @TODO Not implemented stuff

    /**
     * Checks whether the user's account has expired.
     *
     * Internally, if this method returns false, the authentication system
     * will throw an AccountExpiredException and prevent login.
     *
     * @return bool    true if the user's account is non expired, false otherwise
     *
     * @see AccountExpiredException
     */
    public function isAccountNonExpired()
    {
        return true;
    }

    /**
     * Checks whether the user is locked.
     *
     * Internally, if this method returns false, the authentication system
     * will throw a LockedException and prevent login.
     *
     * @return bool    true if the user is not locked, false otherwise
     *
     * @see LockedException
     */
    public function isAccountNonLocked()
    {
        return true;
    }

    /**
     * Checks whether the user's credentials (password) has expired.
     *
     * Internally, if this method returns false, the authentication system
     * will throw a CredentialsExpiredException and prevent login.
     *
     * @return bool    true if the user's credentials are non expired, false otherwise
     *
     * @see CredentialsExpiredException
     */
    public function isCredentialsNonExpired()
    {
        return true;
    }

    // @TODO Deprecated stuff

    /**
     * Validate the user object.
     *
     * @deprecated
     * @return array An array of error messages, or an empty array if there were no errors.
     */
    public function validate()
    {
        $errors = array();

        if (!$this->getEmail()) {
            $errors['email'] = 'Email address is required.';
        } else if (!strpos($this->getEmail(), '@')) {
            // Basic email format sanity check. Real validation comes from sending them an email with a link they have to click.
            $errors['email'] = 'Email address appears to be invalid.';
        } else if (strlen($this->getEmail()) > 100) {
            $errors['email'] = 'Email address can\'t be longer than 100 characters.';
        }

        if (!$this->getPassword()) {
            $errors['password'] = 'Password is required.';
        } else if (strlen($this->getPassword()) > 255) {
            $errors['password'] = 'Password can\'t be longer than 255 characters.';
        }

        if (strlen($this->getName()) > 100) {
            $errors['name'] = 'Name can\'t be longer than 100 characters.';
        }

        // Username can't contain "@",
        // because that's how we distinguish between email and username when signing in.
        // (It's possible to sign in by providing either one.)
        if ($this->getRealUsername() && strpos($this->getRealUsername(), '@') !== false) {
            $errors['username'] = 'Username cannot contain the "@" symbol.';
        }

        return $errors;
    }
}