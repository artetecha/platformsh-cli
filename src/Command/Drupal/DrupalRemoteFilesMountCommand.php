<?php

namespace Platformsh\Cli\Command\Drupal;

use Platformsh\Cli\Command\ExtendedCommandBase;
use Platformsh\Cli\Local\LocalApplication;
use Platformsh\Client\Model\Project;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class DrupalRemoteFilesMountCommand extends ExtendedCommandBase {

  protected function configure() {
    $this->setName('drupal:mount-files')
         ->setAliases(array('mount'))
         ->setDescription('Mount files from a remote environment (via SSHFS)')
         ->addOption('app', NULL, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Specify application(s) to build');
    $this->addDirectoryArgument();
    $this->addEnvironmentOption();
    $this->addExample('Mounts remote file share for a Drupal project via SSHFS', '-e environmentID /path/to/project');
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->validateInput($input);
    $apps = $input->getOption('app');
    foreach (LocalApplication::getApplications($this->getProjectRoot(), self::$config) as $app) {
      if ($apps && !in_array($app->getId(), $apps)) {
        continue;
      }
      if ($app->getConfig()['build']['flavor'] == 'drupal') {
        $this->_execute($input, $app);
      }
    }
  }

  /**
   * Check if the remote file share for the project is already mounted.
   * @param \Platformsh\Cli\Model\Project
   * @return bool
   */
  private function isMounted($check) {
    $mounts = file('/proc/mounts');
    foreach ($mounts as $mount) {
      if (strpos($mount, $check) > -1) {
        return TRUE;
      }
    }
    return FALSE;
  }

  private function _execute(InputInterface $input, LocalApplication $app) {
    $project = $this->getSelectedProject();

    // Mount remote file share only if not mounted already.
    if (!$this->isMounted($project->id . '-' . $input->getOption('environment') . '--' . $app->getId())) {
      // Account for Legacy projects CLI < 3.x
      if (!($sharedPath = $this->localProject->getLegacyProjectRoot())) {
        $sharedPath = $this->getProjectRoot() . '/.platform/local';
      }
      $command = sprintf('sshfs %s-%s--%s@ssh.%s.platform.sh:/app/%s/sites/default/files %s/shared/%s/files -o allow_other -o workaround=all -o nonempty -o reconnect -o umask=0000', $project->id, $input->getOption('environment'), $app->getId(), $project->getProperty('region'), $app->getDocumentRoot(), $sharedPath, $app->getId());
      $sshfs = new Process($command);
      $sshfs->setTimeout(self::$config->get('local.deploy.external_process_timeout'));
      try {
        $this->stdErr->writeln("Mounting files from environment <info>" . $project->id . '-' . $input->getOption('environment') . '--' . $app->getId() . "</info> to <info>" . $project->id . '-' . "local--" . $app->getId() . "</info>.");
        $sshfs->mustRun();
      }
      catch (ProcessFailedException $e) {
        echo $e->getMessage();
        return 1;
      }
    }
    else {
      $this->stdErr->writeln("Project $project->id already has remote files mounted to <info>local</info>");
    }
    return 0;
  }
}
