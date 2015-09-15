#!/usr/bin/env php
<?php

use Volkszaehler\Util;
use Volkszaehler\Command;

define('VZ_DIR', realpath(__DIR__ . '/../..'));

require VZ_DIR . '/lib/bootstrap.php';

$app = new Util\ConsoleApplication('Volkszaehler middleware synchronization tool');
$app->addCommands([
	new Command\SyncEntityCommand,
	new Command\CopyDataCommand
]);
$app->run();
