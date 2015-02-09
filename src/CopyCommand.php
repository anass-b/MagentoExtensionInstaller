<?php

namespace Magext;

require_once __DIR__ . '/../vendor/autoload.php';
require_once 'AbstractCommand.php';

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CopyCommand extends AbstractCommand
{
    protected function configure()
    {
        $this->setName('copy');
        $this->setDescription('Copy extension files');
        $this->addArgument('modman-dir', InputArgument::OPTIONAL, 'Root directory of modman file');
        $this->addArgument('basedir', InputArgument::OPTIONAL, 'Lets you copy in a sub-directory of the project root');
    }

    public static function _execute($args, $options, OutputInterface $output)
    {
        self::setStyles($output);

        $config = self::parseModmanFile($output, $args['modman_dir']);

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

        $atLeastOneItemWasCopied = false;
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
                            $msg .= " " . self::getDetailsFormattedText("File exists: " .$absDst );
                        }
                        $output->writeln($msg);
                    }
                    else {
                        self::createRequiredDirectoryForPath($absDst, $output);
                        if (copy($absSrc, $absDst)) {
                            $output->writeln(self::getSuccessFormattedText() . $dst);
                            $atLeastOneItemWasCopied = true;
                        }
                    }
                }
                else if (is_dir($absSrc)) {
                    if (is_dir($absDst)) {
                        $msg = self::getSkippedFormattedText() . $dst;
                        if ($options['verbose']) {
                            $msg .= " " . self::getDetailsFormattedText("Directory exists: " . $dst);
                        }
                        $output->writeln($msg);
                    }
                    else {
                        self::createRequiredDirectoryForPath($absDst, $output);
                        if (self::copyDir($absSrc, $absDst)) {
                            $output->writeln(self::getSuccessFormattedText() . $dst);
                            $atLeastOneItemWasCopied = true;
                        }
                    }
                }
            }
        }
        if (!$atLeastOneItemWasCopied) {
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

        self::_execute($args, $options, $output);
    }
} 
