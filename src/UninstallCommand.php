<?php

namespace Magext;

require_once __DIR__ . '/../vendor/autoload.php';
require_once 'AbstractCommand.php';
require_once 'RemoveCommand.php';

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UninstallCommand extends AbstractCommand
{
    protected function configure()
    {
        $this->setName('uninstall');
        $this->setDescription('Uninstall all extensions in composer.json');
        $this->addArgument('basedir', InputArgument::OPTIONAL, 'Subdirectory to uninstall extensions from');
        $this->addOption('run-shell', 's', InputOption::VALUE_NONE, 'Create symlinks with absolute path');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->setStyles($output);

        $args = array();
        $args['basedir'] = $input->getArgument("basedir") ? $input->getArgument("basedir") : null;

        $options = array();
        $options['verbose'] = $input->getOption('verbose');
        $options['run_shell'] = $input->getOption('run-shell');

        $json = json_decode(file_get_contents("composer.json"));

        foreach ($json->require as $key => $value) {
            $output->writeln("");
            $output->writeln("<title>[$key]</title>");
            $args['modman_dir'] = getcwd() . self::PS . "vendor" . self::PS . $key;
            RemoveCommand::_execute($args, $options, $output);
        }
    }
} 
