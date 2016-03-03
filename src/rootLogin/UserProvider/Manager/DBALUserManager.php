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

namespace rootLogin\UserProvider\Manager;

use rootLogin\UserProvider\Entity\LegacyUser;
use rootLogin\UserProvider\Entity\User;
use rootLogin\UserProvider\Event\UserEvent;
use rootLogin\UserProvider\Event\UserEvents;
use Doctrine\DBAL\Connection;
use Silex\Application;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;

class DBALUserManager extends UserManager
{
    /** @var Connection */
    protected $conn;

    /** @var User[] */
    protected $identityMap = array();

    /** @var string */
    protected $userClass = '\rootLogin\UserProvider\Entity\LegacyUser';

    /** @var string */
    protected $userTableName = 'users';

    /** @var string */
    protected $userCustomFieldsTableName = 'user_custom_fields';

    /** @var array */
    protected $userColumns = array(
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
    );

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
            $user = $this->findOneBy(array($this->getUserColumns('email') => $username));
            if (!$user) {
                throw new UsernameNotFoundException(sprintf('Email "%s" does not exist.', $username));
            }

            return $user;
        }

        $user = $this->findOneBy(array($this->getUserColumns('username') => $username));
        if (!$user) {
            throw new UsernameNotFoundException(sprintf('Username "%s" does not exist.', $username));
        }

        return $user;
    }

    /**
     * Whether this provider supports the given user class
     *
     * @param string $class
     * @return Boolean
     */
    public function supportsClass($class)
    {
        return ($class === 'rootLogin\UserProvider\Entity\LegacyUser') || is_subclass_of($class, 'rootLogin\UserProvider\Entity\LegacyUser');
    }

    // ----- End UserProviderInterface -----

    /**
     * Reconstitute a User object from stored data.
     *
     * @param array $data
     * @return User
     * @throws \RuntimeException if database schema is out of date.
     */
    protected function hydrateUser(array $data)
    {
        // Test for new columns added in v2.0.
        // If they're missing, throw an exception and explain that migration is needed.
        foreach (array(
                    $this->getUserColumns('username'),
                    $this->getUserColumns('isEnabled'),
                    $this->getUserColumns('confirmationToken'),
                    $this->getUserColumns('timePasswordResetRequested')
                ) as $col) {
            if (!array_key_exists($col, $data)) {
                throw new \RuntimeException('Internal error: database schema appears out of date.');
            }
        }

        $userClass = $this->getUserClass();

        /** @var User $user */
        $user = new $userClass();

        $user->setId($data['id']);
        $user->setEmail($data['email']);
        $user->setPassword($data['password']);
        $user->setSalt($data['salt']);
        $user->setName($data['name']);
        if ($roles = explode(',', $data['roles'])) {
            $user->setRoles($roles);
        }
        $user->setTimeCreated((new \DateTime())->setTimestamp($data['time_created']));
        $user->setUsername($data['username']);
        $user->setEnabled($data['isEnabled']);
        $user->setConfirmationToken($data['confirmationToken']);
        $user->setTimePasswordResetRequested((new \DateTime())->setTimestamp($data['timePasswordResetRequested']));

        if (!empty($data['customFields'])) {
            $user->setCustomFields($data['customFields']);
        }

        return $user;
    }

    /**
     * @inheritdoc
     */
    public function getUser($id)
    {
        return $this->findOneBy(array($this->getUserColumns('id') => $id));
    }

    /**
     * @inheritdoc
     */
    public function findOneBy(array $criteria, array $orderBy = null)
    {
        $users = $this->findBy($criteria, $orderBy);

        if (empty($users)) {
            return null;
        }

        return reset($users);
    }

    /**
     * @inheritdoc
     */
    public function findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
    {
        // Check the identity map first.
        if (array_key_exists($this->getUserColumns('id'), $criteria) 
            && array_key_exists($criteria[$this->getUserColumns('id')], $this->identityMap)) {
            return array($this->identityMap[$criteria[$this->getUserColumns('id')]]);
        }

        list ($common_sql, $params) = $this->createCommonFindSql($criteria);

        $sql = 'SELECT * ' . $common_sql;

        if (is_array($orderBy)) {
            foreach($orderBy as $attribute => $direction)
            $sql .= 'ORDER BY ' . $this->conn->quoteIdentifier($attribute) . ' ' . ($direction == 'DESC' ? 'DESC' : 'ASC') . ' ';
        }
        if ($limit !== null) {
            $offset = ($offset === null ? 0 : $offset);

            $sql .=   ' LIMIT ' . (int) $limit . ' ' .' OFFSET ' . (int) $offset ;
        }

        $data = $this->conn->fetchAll($sql, $params);

        $users = array();
        foreach ($data as $userData) {
            if (array_key_exists($userData[$this->getUserColumns('id')], $this->identityMap)) {
                $user = $this->identityMap[$userData[$this->getUserColumns('id')]];
            } else {
                $userData['customFields'] = $this->getUserCustomFields($userData[$this->getUserColumns('id')]);
                $user = $this->hydrateUser($userData);
                $this->identityMap[$user->getId()] = $user;
            }
            $users[] = $user;
        }

        return $users;
    }

    /**
     * @param $userId
     * @return array
     */
    protected function getUserCustomFields($userId)
    {
        $customFields = array();

        $rows = $this->conn->fetchAll('SELECT * FROM ' . $this->conn->quoteIdentifier($this->userCustomFieldsTableName). ' WHERE user_id = ?', array($userId));
        foreach ($rows as $row) {
            $customFields[$row[$this->getUserColumns('attribute')]] = $row[$this->getUserColumns('value')];
        }

        return $customFields;
    }

    /**
     * Get SQL query fragment common to both find and count querires.
     *
     * @param array $criteria
     * @return array An array of SQL and query parameters, in the form array($sql, $params)
     */
    protected function createCommonFindSql(array $criteria = array())
    {
        $params = array();

        $sql = 'FROM ' . $this->conn->quoteIdentifier($this->userTableName). ' ';
        // JOIN on custom fields, if needed.
        if (array_key_exists('customFields', $criteria)) {
            $i = 0;
            foreach ($criteria['customFields'] as $attribute => $value) {
                $i++;
                $alias = 'custom' . $i;
                $sql .= 'JOIN ' . $this->conn->quoteIdentifier($this->userCustomFieldsTableName). ' ' . $alias . ' ';
                $sql .= 'ON ' . $this->conn->quoteIdentifier($this->userTableName). '.' . $this->getUserColumns('id') . ' = ' . $alias . '.'. $this->getUserColumns('user_id').' ';
                $sql .= 'AND ' . $alias . '.'.$this->getUserColumns('attribute').' = :attribute' . $i . ' ';
                $sql .= 'AND ' . $alias . '.'.$this->getUserColumns('value').' = :value' . $i . ' ';
                $params['attribute' . $i] = $attribute;
                $params['value' . $i] = $value;
            }
        }

        $first_crit = true;
        foreach ($criteria as $key => $val) {
            if ($key == 'customFields') {
                continue;
            } else {
                $sql .= ($first_crit ? 'WHERE' : 'AND') . ' ' . $key . ' = :' . $key . ' ';
                $params[$key] = $val;
            }
            $first_crit = false;
        }

        return array ($sql, $params);
    }

    /**
     * Count users that match the given criteria.
     *
     * @param array $criteria
     * @return int The number of users that match the criteria.
     */
    public function findCount(array $criteria = array())
    {
        list ($common_sql, $params) = $this->createCommonFindSql($criteria);

        $sql = 'SELECT COUNT(*) ' . $common_sql;

        return $this->conn->fetchColumn($sql, $params) ?: 0;
    }

    public function save(User $user) {
        if($user->getId() != null) {
            $this->update($user);
        } else {
            $this->insert($user);
        }
    }

    /**
     * Insert a new User instance into the database.
     *
     * @param User $user
     */
    protected function insert(User $user)
    {
        $this->dispatcher->dispatch(UserEvents::BEFORE_INSERT, new UserEvent($user));

        $sql = 'INSERT INTO ' . $this->conn->quoteIdentifier($this->userTableName) . '
            ('.$this->getUserColumns('email').', '.$this->getUserColumns('password').', '.$this->getUserColumns('salt').', '.$this->getUserColumns('name').
                ', '.$this->getUserColumns('roles').', '.$this->getUserColumns('time_created').', '.$this->getUserColumns('username').', '.$this->getUserColumns('isEnabled').
                ', '.$this->getUserColumns('confirmationToken').', '.$this->getUserColumns('timePasswordResetRequested').')
            VALUES (:email, :password, :salt, :name, :roles, :timeCreated, :username, :isEnabled, :confirmationToken, :timePasswordResetRequested) ';

        $timePasswordResetRequested = 0;
        if($user->getTimePasswordResetRequested() !== null) {
            $timePasswordResetRequested = $user->getTimePasswordResetRequested()->getTimestamp();
        }

        $params = array(
            'email' => $user->getEmail(),
            'password' => $user->getPassword(),
            'salt' => $user->getSalt(),
            'name' => $user->getName(),
            'roles' => implode(',', $user->getRoles()),
            'timeCreated' => $user->getTimeCreated()->getTimestamp(),
            'username' => $user->getRealUsername(),
            'isEnabled' => $user->isEnabled(),
            'confirmationToken' => $user->getConfirmationToken(),
            'timePasswordResetRequested' => $timePasswordResetRequested,
        );

        $this->conn->executeUpdate($sql, $params);

        $user->setId($this->conn->lastInsertId());

        $this->saveUserCustomFields($user);

        $this->identityMap[$user->getId()] = $user;

        $this->dispatcher->dispatch(UserEvents::AFTER_INSERT, new UserEvent($user));
    }

    /**
     * Update data in the database for an existing user.
     *
     * @param User $user
     */
    protected function update(User $user)
    {
        $this->dispatcher->dispatch(UserEvents::BEFORE_UPDATE, new UserEvent($user));

        $sql = 'UPDATE ' . $this->conn->quoteIdentifier($this->userTableName). '
            SET '.$this->getUserColumns('email').' = :email
            , '.$this->getUserColumns('password').' = :password
            , '.$this->getUserColumns('salt').' = :salt
            , '.$this->getUserColumns('name').' = :name
            , '.$this->getUserColumns('roles').' = :roles
            , '.$this->getUserColumns('time_created').' = :timeCreated
            , '.$this->getUserColumns('username').' = :username
            , '.$this->getUserColumns('isEnabled').' = :isEnabled
            , '.$this->getUserColumns('confirmationToken').' = :confirmationToken
            , '.$this->getUserColumns('timePasswordResetRequested').' = :timePasswordResetRequested
            WHERE '.$this->getUserColumns('id').' = :id';

        $timePasswordResetRequested = 0;
        if($user->getTimePasswordResetRequested() !== null) {
            $timePasswordResetRequested = $user->getTimePasswordResetRequested()->getTimestamp();
        }

        $params = array(
            'email' => $user->getEmail(),
            'password' => $user->getPassword(),
            'salt' => $user->getSalt(),
            'name' => $user->getName(),
            'roles' => implode(',', $user->getRoles()),
            'timeCreated' => $user->getTimeCreated()->getTimestamp(),
            'username' => $user->getRealUsername(),
            'isEnabled' => $user->isEnabled(),
            'confirmationToken' => $user->getConfirmationToken(),
            'timePasswordResetRequested' => $timePasswordResetRequested,
            'id' => $user->getId(),
        );

        $this->conn->executeUpdate($sql, $params);

        $this->saveUserCustomFields($user);

        $this->dispatcher->dispatch(UserEvents::AFTER_UPDATE, new UserEvent($user));
    }

    /**
     * @param LegacyUser $user
     */
    protected function saveUserCustomFields(LegacyUser $user)
    {
        $this->conn->executeUpdate('DELETE FROM ' . $this->conn->quoteIdentifier($this->userCustomFieldsTableName). ' 
            WHERE '.$this->getUserColumns('user_id').' = ?', array($user->getId()));

        foreach ($user->getCustomFields() as $attribute => $value) {
            $this->conn->executeUpdate('INSERT INTO ' . $this->conn->quoteIdentifier($this->userCustomFieldsTableName). 
                    ' ('.$this->getUserColumns('user_id').', '.$this->getUserColumns('attribute').', '.$this->getUserColumns('value').') VALUES (?, ?, ?) ',
                array($user->getId(), $attribute, $value));
        }
    }

    /**
     * Delete a User from the database.
     *
     * @param User $user
     */
    public function delete(User $user)
    {
        $this->dispatcher->dispatch(UserEvents::BEFORE_DELETE, new UserEvent($user));

        $this->clearIdentityMap($user);

        $this->conn->executeUpdate('DELETE FROM ' . $this->conn->quoteIdentifier($this->userTableName). ' WHERE '.$this->getUserColumns('id').' = ?', array($user->getId()));
        $this->conn->executeUpdate('DELETE FROM ' . $this->conn->quoteIdentifier($this->userCustomFieldsTableName). ' WHERE '.$this->getUserColumns('user_id').' = ?', array($user->getId()));

        $this->dispatcher->dispatch(UserEvents::AFTER_DELETE, new UserEvent($user));
    }

    /**
     * @inheritdoc
     */
    public function validate(User $user)
    {
        //$errors = $user->validate();

        // Ensure email address is unique.
        $duplicates = $this->findBy(array($this->getUserColumns('email') => $user->getEmail()));
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
            $duplicates = $this->findBy(array($this->getUserColumns('username') => $user->getRealUsername()));
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
        if ($this->isUsernameRequired && !$user->hasRealUsername()) {
            $errors['username'] = 'Username is required.';
        }

        return $errors;
    }

    /**
     * Clear User instances from the identity map, so that they can be read again from the database.
     *
     * Call with no arguments to clear the entire identity map.
     * Pass a single user to remove just that user from the identity map.
     *
     * @param mixed $user Either a User instance, an integer user ID, or null.
     */
    public function clearIdentityMap($user = null)
    {
        if ($user === null) {
            $this->identityMap = array();
        } else if ($user instanceof User && array_key_exists($user->getId(), $this->identityMap)) {
            unset($this->identityMap[$user->getId()]);
        } else if (is_numeric($user) && array_key_exists($user, $this->identityMap)) {
            unset($this->identityMap[$user]);
        }
    }

    public function setUserTableName($userTableName)
    {
        $this->userTableName = $userTableName;
    }

    public function getUserTableName()
    {
        return $this->userTableName;
    }

    public function setUserColumns(array $userColumns){
        $conn = $this->conn;
        //Escape the column names

        $escapedUserColumns = array_map(function($column) use ($conn){
            return $conn->quoteIdentifier($column,\PDO::PARAM_STR);
        }, $userColumns);

        //Merge the existing column names
        $this->userColumns = array_merge($this->userColumns, $escapedUserColumns);
    }

    public function getUserColumns($column = ""){
        if ($column == "") return $this->userColumns;
        else return $this->userColumns[$column];
    }

    public function setUserCustomFieldsTableName($userCustomFieldsTableName)
    {
        $this->userCustomFieldsTableName = $userCustomFieldsTableName;
    }

    public function getUserCustomFieldsTableName()
    {
        return $this->userCustomFieldsTableName;
    }
}
