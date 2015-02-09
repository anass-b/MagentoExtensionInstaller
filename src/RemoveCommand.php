<?php

namespace Magext;

require_once __DIR__ . '/../vendor/autoload.php';
require_once 'AbstractCommand.php';

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RemoveCommand extends AbstractCommand
{
    protected function configure()
    {
        $this->setName('remove');
        $this->setDescription('Remove an extension');
        $this->addArgument('modman-dir', InputArgument::REQUIRED, 'Path of the extension to remove');
        $this->addArgument('basedir', InputArgument::OPTIONAL, 'Lets you remove from a sub-directory of the project root');
        $this->addOption('run-shell', 's', InputOption::VALUE_NONE, 'Create symlinks with absolute path');
    }

    public static function _execute($args, $options, OutputInterface $output)
    {
        self::setStyles($output);

        $config = self::parseModmanFile($output, $args['modman_dir']);
        //print_r($config);return;
        //print_r($config);

        for ($i = 0; $i < sizeof($config['imports']); $i++) {
            $importArgs = array();
            $importArgs['modman_dir'] = $config['imports'][$i]['source'];
            $importArgs['basedir'] = $config['imports'][$i]['basedir'];
            $output->writeln("<extra_info>Removing import " . $importArgs['modman_dir'] . " from " . $importArgs['basedir'] . "</extra_info>");
            self::_execute($importArgs, $options, $output);
        }

        if (sizeof($config['imports']) != 0) {
            $output->writeln("<extra_info>Import processing finished</extra_info>");
        }

        $atLeastOneItemWasDeleted = false;
        for ($i = 0; $i < sizeof($config['mapping']); $i++) {
            $targetDir = $args['basedir'] ? $args['basedir'] . self::PS . $config['mapping'][$i]['removal_path'] : $config['mapping'][$i]['removal_path'];
            $targetDir = self::removeDoubleSlash($targetDir);

            if (!is_link($targetDir) && !is_file($targetDir) && !is_dir($targetDir)) {
                $msg = self::getSkippedFormattedText() . $targetDir;
                if ($options['verbose']) {
                    $msg .= " " . self::getDetailsFormattedText("Doesn't exist");
                }
                $output->writeln($msg);
            }
            else {
                if (is_link($targetDir)) {
                    if (unlink($targetDir)) {
                        $output->writeln(self::getSuccessFormattedText() . $targetDir);
                        $atLeastOneItemWasDeleted = true;
                    }
                }
                else if (is_file($targetDir)) {
                    if (unlink($targetDir)) {
                        $output->writeln(self::getSuccessFormattedText() . $targetDir);
                        $atLeastOneItemWasDeleted = true;
                    }
                }
                else if (is_dir($targetDir)) {
                    if (self::deleteDir($targetDir)) {
                        $output->writeln(self::getSuccessFormattedText() . $targetDir);
                        $atLeastOneItemWasDeleted = true;
                    }
                }
            }
        }

        if (!$atLeastOneItemWasDeleted) {
            $output->writeln(self::getNoOperationFormattedText());
        }

        if ($options['run_shell']) {
            // TODO : use parent method
            for ($i = 0; $i < sizeof($config['shell_commands']); $i++) {
                $command = $config['shell_commands'][$i];

                $projectVar = $args['basedir'] ? getcwd() . self::PS . $args['basedir'] : getcwd();
                $command = str_replace('$PROJECT', $projectVar, $command);
                $command = str_replace('$MODULE', $args['modman-dir'], $command);

                $output->write(shell_exec($command));
            }
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->setStyles($output);

        $args = array();
        $args['modman_dir'] = $input->getArgument('modman-dir');
        $args['basedir'] = $input->getArgument("basedir") ? $input->getArgument("basedir") : null;

        $options = array();
        $options['verbose'] = $input->getOption('verbose');
        $options['run_shell'] = $input->getOption('run-shell');

        self::_execute($args, $options, $output);
    }
} 
