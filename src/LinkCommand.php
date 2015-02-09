<?php

namespace Magext;

require_once __DIR__ . '/../vendor/autoload.php';
require_once 'AbstractCommand.php';

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class LinkCommand extends AbstractCommand
{
    protected function configure()
    {
        $this->setName('link');
        $this->setDescription('Link extension files');
        $this->addArgument('modman-dir', InputArgument::OPTIONAL, 'Root directory of modman file');
        $this->addArgument('basedir', InputArgument::OPTIONAL, 'Lets you symlink in a sub-directory of the project root');
        $this->addOption('absolute-path', 'a', InputOption::VALUE_NONE, 'Create symlinks with absolute path');
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
            $output->writeln("<extra_info>Importing " . $importArgs['modman_dir'] . " into " . $importArgs['basedir'] . "</extra_info>");
            self::_execute($importArgs, $options, $output);
        }

        if (sizeof($config['imports']) != 0) {
            $output->writeln("<extra_info>Import processing finished</extra_info>");
        }

        $atLeastOneItemWasLinked = false;

        for ($i = 0; $i < sizeof($config['mapping']); $i++) {
            $src = $config['mapping'][$i]['src'];
            $absSrc = $args['modman_dir'] . self::PS . $src;

            $dst = $config['mapping'][$i]['dst'];
            $absDst = $args['basedir'] ? getcwd() . self::PS . $args['basedir'] . self::PS . $dst : getcwd() . self::PS . $dst;

            if (!is_file($absSrc) && !is_dir($absSrc)) {
                $msg = self::getFailedFormattedText() . $dst;
                if ($options['verbose']) {
                    $msg .= " " . self::getDetailsFormattedText("File or directory not found: ".$absSrc);
                }
                $output->writeln($msg);
            }
            else {
                if (is_file($absSrc)) {
                    if (is_file($absDst)) {
                        $msg = self::getSkippedFormattedText() . $dst;
                        if ($options['verbose']) {
                            $msg .= " " . self::getDetailsFormattedText("File exists: " . $absDst);
                        }
                        $output->writeln($msg);
                    }
                    else {
                        self::createRequiredDirectoryForPath($absDst, $output);

                        if ($options['absolute_path']) {
                            if (self::symlink($absSrc, $absDst)) {
                                $output->writeln(self::getSuccessFormattedText() . $dst);
                                $atLeastOneItemWasLinked = true;
                            }
                        }
                        else {
                            $relativePath = self::getRelativePath($absDst, $absSrc);
                            if (self::symlink($relativePath, $absDst)) {
                                $output->writeln(self::getSuccessFormattedText() . $dst);
                                $atLeastOneItemWasLinked = true;
                            }
                        }
                    }
                }
                else if (is_dir($absSrc)) {
                    if (is_dir($absDst)) {
                        $msg = self::getSkippedFormattedText() . $dst;
                        if ($options['verbose']) {
                            $msg .= " " . self::getDetailsFormattedText("Directory exists: " . $absDst);
                        }
                        $output->writeln($msg);
                    }
                    else {
                        self::createRequiredDirectoryForPath($absDst, $output);

                        if ($options['absolute_path']) {
                            if (self::symlink($absSrc, $absDst)) {
                                $output->writeln(self::getSuccessFormattedText() . $dst);
                                $atLeastOneItemWasLinked = true;
                            }
                        }
                        else {
                            $relativePath = self::removeTrailingSlash(self::getRelativePath($absDst, $absSrc));
                            if (self::symlink($relativePath, $absDst)) {
                                $output->writeln(self::getSuccessFormattedText() . $dst);
                                $atLeastOneItemWasLinked = true;
                            }
                        }
                    }
                }
            }
        }

        if (!$atLeastOneItemWasLinked) {
            $output->writeln(self::getNoOperationFormattedText());
        }

        // TODO : use parent method
        for ($i = 0; $i < sizeof($config['shell_commands']); $i++) {
            $command = $config['shell_commands'][$i];

            $projectVar = $args['basedir'] ? getcwd() . self::PS . $args['basedir'] : getcwd();
            $command = str_replace('$PROJECT', $projectVar, $command);
            $command = str_replace('$MODULE', $args['modman-dir'], $command);

            $output->write(shell_exec($command));
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->setStyles($output);

        $args = array();
        $args['modman_dir'] = self::removeDoubleSlash($input->getArgument('modman-dir'));
        $args['basedir'] = $input->getArgument("basedir") ? self::removeTrailingSlash($input->getArgument("basedir")) : null;

        $options = array();
        $options['verbose'] = $input->getOption('verbose');
        $options['absolute_path'] = $input->getOption('absolute-path');

        self::_execute($args, $options, $output);
    }
} 
