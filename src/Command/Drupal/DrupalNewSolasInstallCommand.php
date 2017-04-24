<?php

namespace Platformsh\Cli\Command\Drupal;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Platformsh\Cli\Command\CommandBase;
use Platformsh\Cli\Helper\GitHelper;
use Platformsh\Cli\Helper\ShellHelper;
use Platformsh\Cli\Local\LocalApplication;
use Platformsh\Cli\Command\ExtendedCommandBase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Console\Helper\Table;

class DrupalNewSolasInstallCommand extends ExtendedCommandBase {

    protected function configure() {
        $this->setName('drupal:new-solas')
             ->setDescription('New Solas installation')
             ->setAliases(array('new'))
             ->addExample('Create the initial site configuration files for a Solas project', '-p myproject123');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $this->validateInput($input, TRUE);
        $project = $this->getSelectedProject();
     
        $helper = $this->getHelper('question');

        $siteNameQuestion = new Question('<question>Site name:</question> ');
        $siteNameQuestion->setValidator(function ($answer) {
            if (empty($answer)) {
                throw new \Exception('Site name cannot be empty.');
            }
            return $answer;
        });
        $siteNameAnswer = $helper->ask($input, $output, $siteNameQuestion);

        $siteCodeQuestion = new Question('<question>Site code:</question> ');
        $siteCodeQuestion->setValidator(function ($answer) {
            if (empty($answer)) {
                throw new \Exception('Site code cannot be empty.');
            }
            return $answer;
        });
        $siteCodeAnswer = $helper->ask($input, $output, $siteCodeQuestion);

        $timezoneQuestion = new Question('<question>Timezone:</question> ');
        $timezoneQuestion->setValidator(function ($answer) {
            if (empty($answer)) {
                throw new \Exception('Timezone cannot be empty.');
            }
            if (!in_array($answer, timezone_identifiers_list())) {
                throw new \Exception('Timezone must be a valid timezone.');
                
            }
            return $answer;
        });
        $timezoneAnswer = $helper->ask($input, $output, $timezoneQuestion);

        $isoCodeQuestion = new Question('<question>ISO-ALPHA2 code:</question> ');
        $isoCodeQuestion->setValidator(function ($answer) {
            if (empty($answer)) {
                throw new \Exception('ISO-ALPHA2 code cannot be empty.');
            }
            return $answer;
        });
        $isoCodeAnswer = $helper->ask($input, $output, $isoCodeQuestion);

        $fqdnQuestion = new Question('<question>FQDN:</question> ');
        $fqdnQuestion->setValidator(function ($answer) {
            if (empty($answer)) {
                throw new \Exception('Site name cannot be empty.');
            }
            return $answer;
        });
        $fqdnAnswer = $helper->ask($input, $output, $fqdnQuestion);

        $homepageOptions = array(
            'none',
            'britannia',
            'conqueror', 
            'dreadnought', 
            'endeavour', 
            'golden_hind', 
            'macbeth',
            'custom'
        );

        $homepageQuestion = new ChoiceQuestion('<question>Homepage: [none]</question> ', $homepageOptions, 0);
        $homepageAnswer = $helper->ask($input, $output, $homepageQuestion);

        if ($homepageAnswer == 'custom') {
            $homepageCustomQuestion = new Question('<question>Custom homepage:</question> solas_ct_homepage_');
            $homepageCustomQuestion->setValidator(function ($answer) {
                if (empty($answer)) {
                    throw new \Exception('Homepage cannot be empty.');
                }
                return $answer;
            });
            $homepageAnswer = $helper->ask($input, $output, $homepageCustomQuestion);
        }

        $languages = array();
        $additionalLanguageQuestion = new ConfirmationQuestion('<question>Would you like to add an additional language? [y/N]</question> ', FALSE);

        while ($helper->ask($input, $output, $additionalLanguageQuestion)) {
            // Loop through and gather information about the other languages we need.
            $language = array();
            $languageNameQuestion = new Question('<question>Language name in English:</question> ');
            $languageNameQuestion->setValidator(function ($answer) {
                if (empty($answer)) {
                    throw new \Exception('Language name cannot be empty.');
                }
                return $answer;
            });
            $language['english'] = $helper->ask($input, $output, $languageNameQuestion);

            $languageNativeNameQuestion = new Question('<question>Native language’s endonym:</question> ');
            $languageNativeNameQuestion->setValidator(function ($answer) {
                if (empty($answer)) {
                    throw new \Exception('Native language endonym cannot be empty.');
                }
                return $answer;
            });
            $language['native'] = $helper->ask($input, $output, $languageNativeNameQuestion);

            $languageCodeQuestion = new Question('<question>Language code:</question> ');
            $languageCodeQuestion->setValidator(function ($answer) {
                if (empty($answer)) {
                    throw new \Exception('Language code cannot be empty.');
                }
                return $answer;
            });
            $language['code'] = $helper->ask($input, $output, $languageCodeQuestion);

            $languageDirectionQuestion = new ChoiceQuestion('<question>Language direction: [ltr]</question> ',
                array('ltr', 'rtl'), 0);
            $language['dir'] = $helper->ask($input, $output, $languageDirectionQuestion);

            $languageDefaultQuestion = new ConfirmationQuestion('<question>Is this language the default? [y/N]</question> ', FALSE);
            $language['default'] = $helper->ask($input, $output, $languageDefaultQuestion);

            $languageSiteNameQuestion = new Question('<question>Site name in language:</question> ');
            $languageSiteNameQuestion->setValidator(function ($answer) {
                if (empty($answer)) {
                    throw new \Exception('Site name cannot be empty.');
                }
                return $answer;
            });
            $language['siteName'] = $helper->ask($input, $output, $languageSiteNameQuestion);

            // Save the inputted language
            $languages[$language['code']] = $language;
        }

        $table = new Table($output);
        $table->setRows(array(
                array('<info>Site name:</info>', $siteNameAnswer),
                array('<info>Site code:</info>', $siteCodeAnswer),
                array('<info>Timezone:</info>', $timezoneAnswer),
                array('<info>ISO-ALPHA2:</info>', $isoCodeAnswer),
                array('<info>FQDN:</info>', $fqdnAnswer),
                array('<info>Homepage:</info>', $homepageAnswer)
            ))
        ;
        $table->setStyle('compact')->render();

        if (!empty($languages)) {
            $output->writeln('Additional languages');
            $languageTable = new Table($output);
            $languageTable
                ->setHeaders(array('Name', 'Native', 'Code', 'Direction', 'Default', 'Site Name'))
                ->setRows($languages);
            $languageTable->render();
        }

        $confirmationQuestion = new ConfirmationQuestion('<question>Are these details correct? [y/N]</question> ', FALSE);
        if (!$helper->ask($input, $output, $confirmationQuestion)) {
            $output->writeln('Aborting.');
            return;
        }

        $this->stdErr->writeln("Root Dir = {$this->extCurrentProject['root_dir']}");
        $this->stdErr->writeln("Profiles Dir = {$this->profilesRootDir}");


        // Copy the default platform configuration files.
        if (!is_dir($this->extCurrentProject['root_dir'] . '/.platform')) {
            mkdir($this->extCurrentProject['root_dir'] . '/.platform');
        }
        copy($this->profilesRootDir . '/solas2/sites/site-template/.platform/routes.yaml', $this->extCurrentProject['root_dir'] . '/.platform/routes.yaml');
        copy($this->profilesRootDir . '/solas2/sites/site-template/.platform/services.yaml', $this->extCurrentProject['root_dir'] . '/.platform/services.yaml');
        copy($this->profilesRootDir . '/solas2/sites/site-template/.platform.app.yaml', $this->extCurrentProject['root_dir'] . '/site/.platform.app.yaml');


        // // Make the module folders
        if (!is_dir($this->extCurrentProject['root_dir'] . '/site/modules/custom')) {
            mkdir($this->extCurrentProject['root_dir'] . '/site/modules/custom');
        }
        if (!is_dir($this->extCurrentProject['root_dir'] . '/site/modules/features')) {
            mkdir($this->extCurrentProject['root_dir'] . '/site/modules/features');
        }

        // Copy the project.make file into the correct location
        copy($this->profilesRootDir . '/solas2/sites/site-template/project.make', $this->extCurrentProject['root_dir'] . '/site/project.make');

        // // Copy the folders into place and map variables.

        // sc_deploy
        $output->writeln("Writing sc_deploy module.");
        $this->writeScdeploy($project, $siteNameAnswer, $siteCodeAnswer, $timezoneAnswer, $isoCodeAnswer, $fqdnAnswer, $homepageAnswer, $languages);
        $output->writeln("sc_deploy done.");

        // General settings
        $output->writeln("Writing general settings module.");
        $this->writeGeneralSettings($project, $siteNameAnswer, $siteCodeAnswer, $timezoneAnswer, $isoCodeAnswer, $fqdnAnswer, $homepageAnswer, $languages);
        $output->writeln("General settings done.");

        // Region
        $output->writeln("Writing region module.");
        $this->writeRegion($project, $siteNameAnswer, $siteCodeAnswer, $timezoneAnswer, $isoCodeAnswer, $fqdnAnswer, $homepageAnswer, $languages);
        $output->writeln("Region done.");

        $output->writeln('<info>Initial site setup complete.</info>'); 

    }

    private function writeScdeploy($project, $name, $code, $timezone, $isocode, $fqdn, $homepage, $languages) {
        $result = true;
        if (!is_dir($this->extCurrentProject['root_dir'] . '/site/modules/custom/sc_deploy')) {
            $result = mkdir($this->extCurrentProject['root_dir'] . '/site/modules/custom/sc_deploy');
        }
        $result = copy($this->profilesRootDir . '/solas2/sites/site-template/modules/custom/sc_deploy/sc_deploy.info', $this->extCurrentProject['root_dir'] . '/site/modules/custom/sc_deploy/sc_deploy.info');
        $result = copy($this->profilesRootDir . '/solas2/sites/site-template/modules/custom/sc_deploy/sc_deploy.module', $this->extCurrentProject['root_dir'] . '/site/modules/custom/sc_deploy/sc_deploy.module');
        $scDeployInstall = file_get_contents($this->profilesRootDir . '/solas2/sites/site-template/modules/custom/sc_deploy/sc_deploy.install');
        $scDeployInstallSearch = array(
            '[solas:homepage]',
            '[solas:country:name:lowercase]',
            '[solas:country:name:english]',
            '[solas:languages:nameslogan]'
            );
        $scDeployInstallReplace = array(
            $homepage,
            $code,
            $name,
            );
        if (count($languages)) {
            $nameSlogan = '';
            foreach ($languages as $key => $language) {
                $nameSlogan .= "\n  i18n_variable_set('site_name', 'British Council', '{$key}');\n  i18n_variable_set('site_slogan', '{$language['siteName']}', '{$key}');";
            }
            $scDeployInstallReplace[] = $nameSlogan;
        } else {
            $scDeployInstallReplace[] = '';
        }
        $scDeployInstall = str_replace($scDeployInstallSearch, $scDeployInstallReplace, $scDeployInstall);
        $result = file_put_contents($this->extCurrentProject['root_dir'] . '/site/modules/custom/sc_deploy/sc_deploy.install', $scDeployInstall);

        return $result;
    }

    private function writeGeneralSettings($project, $name, $code, $timezone, $isocode, $fqdn, $homepage, $languages) {
        $search = array(
            '[solas:country:name:english]',
            '[solas:country:name:lowercase]',
            'SOLAS_COUNTRY',
            '[solas:country:live_fqdn]'
            );
        $replace = array(
            $name,
            $code,
            $code,
            $fqdn,
            );

        if (!is_dir($this->extCurrentProject['root_dir'] . '/site/modules/features/solas_' . $code . '_general_settings')) {
            mkdir($this->extCurrentProject['root_dir'] . '/site/modules/features/solas_' . $code . '_general_settings');
        }

        $info = str_replace($search, $replace, file_get_contents($this->profilesRootDir . '/solas2/sites/site-template/modules/features/solas_SOLAS_COUNTRY_general_settings/solas_SOLAS_COUNTRY_general_settings.info'));
        file_put_contents($this->extCurrentProject['root_dir'] . '/site/modules/features/solas_' . $code . '_general_settings/solas_' . $code . '_general_settings.info', $info);

        $module = str_replace($search, $replace, file_get_contents($this->profilesRootDir . '/solas2/sites/site-template/modules/features/solas_SOLAS_COUNTRY_general_settings/solas_SOLAS_COUNTRY_general_settings.module'));
        file_put_contents($this->extCurrentProject['root_dir'] . '/site/modules/features/solas_' . $code . '_general_settings/solas_' . $code . '_general_settings.module', $module);


        $features = str_replace($search, $replace, file_get_contents($this->profilesRootDir . '/solas2/sites/site-template/modules/features/solas_SOLAS_COUNTRY_general_settings/solas_SOLAS_COUNTRY_general_settings.features.inc'));
        file_put_contents($this->extCurrentProject['root_dir'] . '/site/modules/features/solas_' . $code . '_general_settings/solas_' . $code . '_general_settings.features.inc', $features);


        $strongarm = str_replace($search, $replace, file_get_contents($this->profilesRootDir . '/solas2/sites/site-template/modules/features/solas_SOLAS_COUNTRY_general_settings/solas_SOLAS_COUNTRY_general_settings.strongarm.inc'));
        file_put_contents($this->extCurrentProject['root_dir'] . '/site/modules/features/solas_' . $code . '_general_settings/solas_' . $code . '_general_settings.features.inc', $strongarm);
    }

    private function writeRegion($project, $name, $code, $timezone, $isocode, $fqdn, $homepage, $languages) {
    
        $languageTemplate = "    
  // Exported language: [solas:language:code].
  \$languages['[solas:language:code]'] = array(
    'language' => '[solas:language:code]',
    'name' => '[solas:language:name]',
    'native' => '[solas:language:native_name]',
    'direction' => '[solas:language:direction]',
    'enabled' => 1,
    'plurals' => 0,
    'formula' => '',
    'domain' => '',
    'prefix' => '',
    'weight' => -10,
  );";
        $languageSearch = array(
            '[solas:language:code]',
            '[solas:language:name]',
            '[solas:language:native_name]',
            '[solas:language:direction]',
            );
        $languageReplace = array(
            $language['code'],
            $language['english'],
            $language['native'],
            $language['dir'],
            );
        $languageOut = '';
        $languageInfoOut = '';
        $languageDateOut = '';
        $languageDefault = array();
        foreach ($languages as $key => $language) {
            $languageOut .= str_replace($languageSearch, $languageReplace, $languageTemplate);
            $languageInfoOut .= "features[language][] = {$language['code']}\n";
            $languageDateOut .= ", '{$language['code']}'";
            if ($language['default']) {
                $languageDefault = $language;
            }
        }
        if (empty($languageDefault)) {
            $languageDefault = array(
                'code' => 'en',
                'name' => 'English',
                'native' => 'English',
                'dir' => '0'
                );
        }
        $search = array(
            '[solas:country:name:english]',
            '[solas:country:name:lowercase]',
            'SOLAS_COUNTRY',
            '[solas:country:live_fqdn]',
            '[solas:languages]',
            '[solas:language:info]',
            '[solas:language:count]',
            '[solas:date:languages]',
            '[solas:country:timezone]',
            '[solas:country:default]',
            '[solas:language:default:code]',
            '[solas:language:default:name]',
            '[solas:language:default:native_name]',
            '[solas:language:default:direction]',
            );
        $replace = array(
            $name,
            $code,
            $code,
            $fqdn,
            $languageOut,
            $languageInfoOut,
            count($languages) + 1,
            $languageDateOut,
            $timezone,
            $isocode,
            $languageDefault['code'],
            $languageDefault['name'],
            $languageDefault['native'],
            $languageDefault['dir'],
            );
        mkdir($this->extCurrentProject['root_dir'] . '/site/modules/features/solas_' . $code . '_region');

        $info = str_replace($search, $replace, file_get_contents($this->profilesRootDir . '/solas2/sites/site-template/modules/features/solas_SOLAS_COUNTRY_region/solas_SOLAS_COUNTRY_region.info'));
        file_put_contents($this->extCurrentProject['root_dir'] . '/site/modules/features/solas_' . $code . '_region/solas_' . $code . '_region.info', $info);

        $module = str_replace($search, $replace, file_get_contents($this->profilesRootDir . '/solas2/sites/site-template/modules/features/solas_SOLAS_COUNTRY_region/solas_SOLAS_COUNTRY_region.module'));
        file_put_contents($this->extCurrentProject['root_dir'] . '/site/modules/features/solas_' . $code . '_region/solas_' . $code . '_region.module', $module);

        $strongarm = str_replace($search, $replace, file_get_contents($this->profilesRootDir . '/solas2/sites/site-template/modules/features/solas_SOLAS_COUNTRY_region/solas_SOLAS_COUNTRY_region.strongarm.inc'));
        file_put_contents($this->extCurrentProject['root_dir'] . '/site/modules/features/solas_' . $code . '_region/solas_' . $code . '_region.strongarm.inc', $strongarm);

        $features = str_replace($search, $replace, file_get_contents($this->profilesRootDir . '/solas2/sites/site-template/modules/features/solas_SOLAS_COUNTRY_region/solas_SOLAS_COUNTRY_region.features.inc'));
        file_put_contents($this->extCurrentProject['root_dir'] . '/site/modules/features/solas_' . $code . '_region/solas_' . $code . '_region.features.inc', $features);

        $language = str_replace($search, $replace, file_get_contents($this->profilesRootDir . '/solas2/sites/site-template/modules/features/solas_SOLAS_COUNTRY_region/solas_SOLAS_COUNTRY_region.features.language.inc'));
        file_put_contents($this->extCurrentProject['root_dir'] . '/site/modules/features/solas_' . $code . '_region/solas_' . $code . '_region.features.language.inc', $language);

        return TRUE;
    }
}

