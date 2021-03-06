<?php

namespace Platformsh\Cli\Command;

use Platformsh\Cli\Service\Api;
use Platformsh\Cli\Application;
use Platformsh\Cli\Local\LocalApplication;
use Stecman\Component\Symfony\Console\BashCompletion\Completion;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionCommand as ParentCompletionCommand;

class CompletionCommand extends ParentCompletionCommand implements CanHideInListInterface
{

    /** @var Api */
    protected $api;

    /** @var CommandBase */
    protected $welcomeCommand;

    /**
     * A list of the user's projects.
     * @var array
     */
    protected $projects = [];

    /**
     * {@inheritdoc}
     */
    public function isHiddenInList()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->api = new Api();
        $this->projects = $this->api->isLoggedIn() ? $this->api->getProjects(false) : [];
        $this->welcomeCommand = new WelcomeCommand('welcome');
        $this->welcomeCommand->setApplication(new Application());
    }

    /**
     * @inheritdoc
     */
    protected function runCompletion()
    {
        $this->setUp();
        $projectIds = array_keys($this->projects);

        $this->handler->addHandlers([
            new Completion(
                Completion::ALL_COMMANDS,
                'project',
                Completion::TYPE_OPTION,
                $projectIds
            ),
            new Completion(
                Completion::ALL_COMMANDS,
                'project',
                Completion::TYPE_ARGUMENT,
                $projectIds
            ),
            new Completion(
                Completion::ALL_COMMANDS,
                'environment',
                Completion::TYPE_ARGUMENT,
                [$this, 'getEnvironments']
            ),
            new Completion(
                Completion::ALL_COMMANDS,
                'environment',
                Completion::TYPE_OPTION,
                [$this, 'getEnvironments']
            ),
            new Completion(
                'environment:branch',
                'parent',
                Completion::TYPE_ARGUMENT,
                [$this, 'getEnvironments']
            ),
            new Completion(
                'environment:checkout',
                'id',
                Completion::TYPE_ARGUMENT,
                [$this, 'getEnvironmentsForCheckout']
            ),
            new Completion(
                'user:role',
                'email',
                Completion::TYPE_ARGUMENT,
                [$this, 'getUserEmails']
            ),
            new Completion(
                'user:role',
                'level',
                Completion::TYPE_OPTION,
                ['project', 'environment']
            ),
            new Completion(
                'user:delete',
                'email',
                Completion::TYPE_ARGUMENT,
                [$this, 'getUserEmails']
            ),
            new Completion\ShellPathCompletion(
                'ssh-key:add',
                'path',
                Completion::TYPE_ARGUMENT
            ),
            new Completion\ShellPathCompletion(
                'domain:add',
                'cert',
                Completion::TYPE_OPTION
            ),
            new Completion\ShellPathCompletion(
                'domain:add',
                'key',
                Completion::TYPE_OPTION
            ),
            new Completion\ShellPathCompletion(
                'domain:add',
                'chain',
                Completion::TYPE_OPTION
            ),
            new Completion\ShellPathCompletion(
                'local:build',
                'source',
                Completion::TYPE_OPTION
            ),
            new Completion\ShellPathCompletion(
                'local:build',
                'destination',
                Completion::TYPE_OPTION
            ),
            new Completion\ShellPathCompletion(
                'environment:sql-dump',
                'file',
                Completion::TYPE_OPTION
            ),
            new Completion\ShellPathCompletion(
                'local:init',
                'directory',
                Completion::TYPE_ARGUMENT
            ),
            new Completion(
                Completion::ALL_COMMANDS,
                'app',
                Completion::TYPE_OPTION,
                [$this, 'getAppNames']
            ),
            new Completion(
                Completion::ALL_COMMANDS,
                'app',
                Completion::TYPE_OPTION,
                [$this, 'getAppNames']
            ),
            new Completion\ShellPathCompletion(
                Completion::ALL_COMMANDS,
                'identity-file',
                Completion::TYPE_OPTION
            ),
            new Completion\ShellPathCompletion(
                'server:run',
                'log',
                Completion::TYPE_OPTION
            ),
            new Completion\ShellPathCompletion(
                'server:start',
                'log',
                Completion::TYPE_OPTION
            )
        ]);

        try {
            return $this->handler->runCompletion();
        } catch (\Exception $e) {
            // Suppress exceptions so that they are not displayed during
            // completion.
        }

        return [];
    }

    /**
     * Get a list of environments IDs that can be checked out.
     *
     * @return string[]
     */
    public function getEnvironmentsForCheckout()
    {
        $project = $this->welcomeCommand->getCurrentProject();
        if (!$project) {
            return [];
        }
        try {
            $currentEnvironment = $this->welcomeCommand->getCurrentEnvironment($project, false);
        } catch (\Exception $e) {
            $currentEnvironment = false;
        }
        $environments = $this->api->getEnvironments($project, false, false);
        if ($currentEnvironment) {
            $environments = array_filter(
                $environments,
                function ($environment) use ($currentEnvironment) {
                    return $environment->id !== $currentEnvironment->id;
                }
            );
        }

        return array_keys($environments);
    }

    /**
     * Get a list of application names in the local project.
     *
     * @return string[]
     */
    public function getAppNames()
    {
        $apps = [];
        if ($projectRoot = $this->welcomeCommand->getProjectRoot()) {
            foreach (LocalApplication::getApplications($projectRoot) as $app) {
                $name = $app->getName();
                if ($name !== null) {
                    $apps[] = $name;
                }
            }
        }

        return $apps;
    }

    /**
     * Get the preferred project for autocompletion.
     *
     * The project is either defined by an ID that the user has specified in
     * the command (via the 'project' argument or '--project' option), or it is
     * determined from the current path.
     *
     * @return \Platformsh\Client\Model\Project|false
     */
    protected function getProject()
    {
        if (!$this->projects) {
            return false;
        }

        $commandLine = $this->handler->getContext()
            ->getCommandLine();
        $currentProjectId = $this->getProjectIdFromCommandLine($commandLine);
        if (!$currentProjectId && ($currentProject = $this->welcomeCommand->getCurrentProject())) {
            return $currentProject;
        } elseif (isset($this->projects[$currentProjectId])) {
            return $this->projects[$currentProjectId];
        }

        return false;
    }

    /**
     * Get a list of environment IDs.
     *
     * @return string[]
     */
    public function getEnvironments()
    {
        $project = $this->getProject();
        if (!$project) {
            return [];
        }

        return array_keys($this->api->getEnvironments($project, false, false));
    }

    /**
     * Get a list of user email addresses.
     *
     * @return string[]
     */
    public function getUserEmails()
    {
        $project = $this->getProject();
        if (!$project) {
            return [];
        }

        $emails = [];
        foreach ($project->getUsers() as $projectAccess) {
            $account = $this->api->getAccount($projectAccess);
            $emails[] = $account['email'];
        }

        return $emails;
    }

    /**
     * Get the project ID the user has already entered on the command line.
     *
     * @param string $commandLine
     *
     * @return string|false
     */
    protected function getProjectIdFromCommandLine($commandLine)
    {
        if (preg_match('/\W(\-\-project|\-p|get) ?=? ?[\'"]?([0-9a-z]+)[\'"]?/', $commandLine, $matches)) {
            return $matches[2];
        }

        return false;
    }
}
