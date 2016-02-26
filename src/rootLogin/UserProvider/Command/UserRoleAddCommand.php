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

namespace rootLogin\UserProvider\Command;

use rootLogin\UserProvider\Entity\User;
use Silex\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * Easily create users.
 *
 * @author Simon Erhardt <hello@rootlogin.ch>
 */
class UserRoleAddCommand extends Command
{
    /**
     * @var Application
     */
    private $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
        parent::__construct();
    }

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this
            ->setName('user:role:add')
            ->setDefinition([
                new InputArgument('email', InputArgument::REQUIRED, 'The email'),
                new InputArgument('role', InputArgument::REQUIRED, 'The role')
            ])
            ->setHelp(<<<EOT
The <info>user:role:add</info> adds a role to an user:
  <info>php app/console user:role:add test@example.org</info>
This interactive shell will ask you for the role you want to add.
You can alternatively specify the role as the second:
  <info>php app/console user:role:add test@example.org ROLE_ADMIN</info>
EOT
            );
    }
    /**
     * @see Command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $email = $input->getArgument('email');
        $role = $input->getArgument('role');

        /** @var User $user */
        $user = $this->app['user.manager']->findOneBy(['email' => $email]);
        if($user === null) {
            $output->writeln(sprintf('User <comment>%s</comment> not found!', $email));
            return 1;
        }
        if($user->hasRole($role)) {
            $output->writeln(sprintf('User <comment>%s</comment> has already the <comment>%s</comment> role.', $email, $role));
            return 0;
        }

        $user->addRole($role);
        $this->app['user.manager']->save($user);

        $output->writeln(sprintf('Added role <comment>%s</comment> to user <comment>%s</comment>', $role, $email));

        return 0;
    }
    /**
     * @see Command
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $questions = array();
        if (!$input->getArgument('email')) {
            $question = new Question('Please choose an email:');
            $question->setValidator(function($email) {
                if (empty($email)) {
                    throw new \Exception('Email can not be empty');
                }
                return $email;
            });
            $questions['email'] = $question;
        }

        if (!$input->getArgument('role')) {
            $question = new Question('Please choose a role:');
            $question->setValidator(function($role) {
                if (empty($role)) {
                    throw new \Exception('Role can not be empty');
                }
                return $role;
            });
            $questions['role'] = $question;
        }
        foreach ($questions as $name => $question) {
            $answer = $this->getHelper('question')->ask($input, $output, $question);
            $input->setArgument($name, $answer);
        }
    }
}