<?php

namespace SimpleUser\Command;

use Silex\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * Easily deletes users.
 *
 * @author Simon Erhardt <hello@rootlogin.ch>
 */
class UserDeleteCommand extends Command
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
            ->setName('simpleuser:delete')
            ->setDefinition(array(
                new InputArgument('email', InputArgument::REQUIRED, 'The email')
            ))
            ->setHelp(<<<EOT
The <info>simpleuser:delete</info> command deletes a user:
  <info>php app/console simpleuser:delete test@example.org</info>
EOT
            );
    }
    /**
     * @see Command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $email = $input->getArgument('email');

        $user = $this->app['user.manager']->findOneBy(["email" => $email]);
        if($user == null) {
            $output->writeln(sprintf('User <comment>%s</comment> not found!', $email));
        }
        $this->app['user.manager']->delete($user);

        $output->writeln(sprintf('Deleted user <comment>%s</comment>', $email));
    }
    /**
     * @see Command
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $questions = array();
        if (!$input->getArgument('email')) {
            $question = new Question('Please enter an email:');
            $question->setValidator(function($email) {
                if (empty($email)) {
                    throw new \Exception('Email can not be empty');
                }
                return $email;
            });
            $questions['email'] = $question;
        }

        foreach ($questions as $name => $question) {
            $answer = $this->getHelper('question')->ask($input, $output, $question);
            $input->setArgument($name, $answer);
        }
    }
}