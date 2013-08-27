<?php
namespace Nemure;

/**
 * Copyright (c) 2013 Stefan Fiedler
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

use Nette;

/**
 * Class for storing configuration data for reactor server.
 * @author Stefan Fiedler
 */
class Configuration extends Nette\Object
{
	/** @var string name of reactor server */
	public $name;

	/** @var int PID of process running reactor */
	public $pid;

	/** @var int port for connecting to reactor */
	public $port;

	/** @var string root password for reactor server */
	public $rootPassword;

	/** @var int current error code */
	public $error = -1;

	/** @var string status message */
	public $status = Environment::STATUS_EXPECTING_ROOT_INIT;

	/** @var int number of clients added to the reactor except root */
	public $clientCount = 0;


	public function __construct($name, $pid, $port, $rootPassword)
	{
		$this->name = $name;
		$this->pid = $pid;
		$this->port = $port;
		$this->rootPassword = $rootPassword;
	}
}
