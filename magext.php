#!/usr/bin/env php
<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/LinkCommand.php';
require_once __DIR__ . '/src/CopyCommand.php';
require_once __DIR__ . '/src/RemoveCommand.php';
require_once __DIR__ . '/src/InstallCommand.php';
require_once __DIR__ . '/src/UninstallCommand.php';

use Magext\CopyCommand;
use Magext\LinkCommand;
use Magext\RemoveCommand;
use Magext\InstallCommand;
use Magext\UninstallCommand;
use Symfony\Component\Console\Application;

$application = new Application();
$application->add(new LinkCommand());
$application->add(new CopyCommand());
$application->add(new RemoveCommand());
$application->add(new InstallCommand());
$application->add(new UninstallCommand());
$application->run();