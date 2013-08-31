<?php
namespace Nemure;

/**
 * Copyright (c) 2013 Stefan Fiedler
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

use Nette;

/**
 * Command-line script for running reactor.
 * @author Stefan Fiedler
 */

$container = require_once __DIR__ . '/../../bootstrap.php';
/** @var Nette\DI\Container $container */

Nette\Diagnostics\Debugger::$productionMode = FALSE;
$container->getByType('Nette\Http\IResponse')->setContentType('text/plain');

$server = new Reactor($container, $_SERVER['argv']);
$server->start();