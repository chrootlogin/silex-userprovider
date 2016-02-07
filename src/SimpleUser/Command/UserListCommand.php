<?php

namespace SimpleUser\Command;

use Silex\Application;
use SimpleUser\User;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Easily lists users.
 *
 * @author Simon Erhardt <hello@rootlogin.ch>
 */
class UserListCommand extends Command
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
            ->setName('simpleuser:list')
            ->setHelp(<<<EOT
The <info>simpleuser:list</info> command lists all users.
EOT
            );
    }
    /**
     * @see Command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $users = $this->app['user.manager']->findBy();

        $output->writeln("User list\n");

        foreach($users as $user) {
            /** @var User */
            $output->writeln(sprintf(" - %s (ID: %d)", $user->getEmail(), $user->getId()));
        }
    }
}