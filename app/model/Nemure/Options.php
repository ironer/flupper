<?php
namespace Nemure;

/**
 * Copyright (c) 2013 Stefan Fiedler
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

use Nette;

/**
 * Class for storing options data for reactor server.
 * @author Stefan Fiedler
 */
class Options extends Nette\Object
{
	/** @var string name of reactor server */
	public $name;

	/** @var string root password for reactor server */
	public $rootPassword;

	/** @var int PID of process running reactor */
	public $pid;

	/** @var int port for connecting to reactor */
	public $port = 0;

	/** @var int current error code */
	public $error = -2;

	/** @var string status message */
	public $status = Configuration::STATUS_EXPECTING_REACTOR_SPECIFIC_OPTIONS;

	/** @var int number of clients added to the reactor except root */
	public $clientCount = 0;

	/** @var string absolute path to reactor's runtime directory */
	public $path;

	/** @var string absolute path of reactor server's options file */
	public $optionsPath;

	/** @var string absolute path of reactor server's clients file */
	public $clientsPath;


	public function __construct($reactorName, Configuration $conf)
	{
		$this->name = $reactorName;
		$this->path = $conf->tempPath . "/$reactorName";
		$this->optionsPath = $this->path . "/" . $conf->files['options'];
		$this->clientsPath = $this->path . "/" . $conf->files['clients'];
	}


	public function setup($pid, $port, $rootPassword)
	{
		if ($this->error <> -2) {
			throw new \LogicException("Didn't expect setting of server specific options, current error code $this->error and status $this->status.");
		}

		$this->pid = $pid;
		$this->port = $port;
		$this->rootPassword = $rootPassword;

		$this->error = -1;
		$this->status = Configuration::STATUS_EXPECTING_ROOT_INIT;
	}


	public function read()
	{
		if (!is_file($this->optionsPath)) {
			$reactorOptions = ['error' => 1, 'status' => "File '$this->optionsPath' with reactor's options wasn't found."];
		} elseif (!is_writable($this->optionsPath)) {
			$reactorOptions = ['error' => 2, 'status' => "File '$this->optionsPath' with reactor's options isn't writable."];
		} else {
			$reactorOptions = json_decode(file_get_contents($this->optionsPath), TRUE);

			if (!is_array($reactorOptions) || count($reactorOptions) !== 5) {
				$reactorOptions = ['error' => 3, 'status' => "There are no valid options in the file '$this->optionsPath'."];
			}
		}

		foreach ($reactorOptions as $option => $value) {
			$this->$option = $value;
		}

		return TRUE;
	}


	public function write()
	{
		$storedOptions = [
			'pid' => $this->pid,
			'port' => $this->port,
			'error' => $this->error,
			'status' => $this->status,
			'clientCount' => $this->clientCount
		];

		umask();
		file_put_contents($this->optionsPath, json_encode($storedOptions));

		return TRUE;
	}
}
