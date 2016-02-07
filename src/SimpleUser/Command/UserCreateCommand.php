<?php

namespace SimpleUser\Command;

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
class UserCreateCommand extends Command
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
            ->setName('simpleuser:create')
            ->setDefinition(array(
                new InputArgument('email', InputArgument::REQUIRED, 'The email'),
                new InputArgument('password', InputArgument::REQUIRED, 'The password')
            ))
            ->setHelp(<<<EOT
The <info>simpleuser:create</info> command creates a user:
  <info>php app/console simpleuser:create test@example.org</info>
This interactive shell will ask you for a password.
You can alternatively specify the password as the second and third arguments:
  <info>php app/console simpleuser:create test@example.org mypassword</info>
EOT
            );
    }
    /**
     * @see Command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $email      = $input->getArgument('email');
        $password   = $input->getArgument('password');

        $user = $this->app['user.manager']->createUser($email, $password);
        $user->setEnabled(true);
        $this->app['user.manager']->insert($user);

        $output->writeln(sprintf('Created user <comment>%s</comment>', $email));
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

        if (!$input->getArgument('password')) {
            $question = new Question('Please choose a password:');
            $question->setValidator(function($password) {
                if (empty($password)) {
                    throw new \Exception('Password can not be empty');
                }
                return $password;
            });
            $question->setHidden(true);
            $questions['password'] = $question;
        }
        foreach ($questions as $name => $question) {
            $answer = $this->getHelper('question')->ask($input, $output, $question);
            $input->setArgument($name, $answer);
        }
    }
}