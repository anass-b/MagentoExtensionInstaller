<?php

namespace Magext;

require_once __DIR__ . '/../vendor/autoload.php';
require_once 'AbstractCommand.php';
require_once 'LinkCommand.php';
require_once 'CopyCommand.php';
require_once 'RemoveCommand.php';

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class InstallCommand extends AbstractCommand
{
    protected function configure()
    {
        $this->setName('install');
        $this->setDescription('Install all extensions in composer.json');
        $this->addArgument('basedir', InputArgument::OPTIONAL, 'Subdirectory where to install');
        $this->addOption('copy', 'c', InputOption::VALUE_NONE, 'Copy instead of symlinking');
        $this->addOption('absolute-path', 'a', InputOption::VALUE_NONE, 'Create symlinks with absolute path');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->setStyles($output);

        $args = array();
        $args['basedir'] = $input->getArgument("basedir") ? $input->getArgument("basedir") : null;

        $options = array();
        $options['verbose'] = $input->getOption('verbose');
        $options['absolute_path'] = $input->getOption('absolute-path');

        $json = json_decode(file_get_contents("composer.json"));

        foreach ($json->require as $key => $value) {
            $output->writeln("");
            $output->writeln("<title>[$key]</title>");

            $args['modman_dir'] = getcwd() . self::PS . "vendor" . self::PS . $key;
            if ($input->getOption('copy')) {
                CopyCommand::_execute($args, $options, $output);
            }
            else {
                LinkCommand::_execute($args, $options, $output);
            }
        }
    }
} 
