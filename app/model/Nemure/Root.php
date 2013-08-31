<?php
namespace Nemure;

/**
 * Copyright (c) 2013 Stefan Fiedler
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

use Nette;
use Nette\Diagnostics\Debugger;

/**
 * Root access class for starting, killing and configuring of reactor servers.
 * @author Stefan Fiedler
 */
class Root extends Nette\Object
{
	/** @var Environment */
	public $environment;

	/** @var Configuration[] array (reactorName => Configuration) of currently running reactors */
	public $configurations = [];

	/** @var array (port => reactorName) used ports of running reactors */
	public $usedPorts = [];

	/** @var RootClient[] array (reactorName => RootClient) of connected reactors */
	public $reactors = [];

	/** @var string default name for reactor */
	public $defaultReactorName;

	/** @var string password for root access to reactors */
	public $rootPassword = 'secret';


	public function __construct($tempDir, $defaultReactorName)
	{
		$time = microtime(TRUE);

		$this->environment = new Environment($tempDir);

		list($this->configurations, $this->usedPorts) = $this->environment->getReactorsConfigurations();

		$this->defaultReactorName = !empty($defaultReactorName) ? $defaultReactorName : 'Default';

		echo "Reading of reactors' configurations (" . count($this->configurations) . ") took: "
				. number_format(1000 * (microtime(TRUE) - $time), 2, '.', ' ') . ' ms<br>';
	}


	public function startReactor()
	{
		if (count($this->configurations) > 30) {
			throw new \Exception('No more than 30 reactor servers allowed.');
		}

		$time = microtime(TRUE);

		if (FALSE === $reactor = $this->createRootClient()) {
			throw new \Exception('Starting of reactor server failed.');
		}

		echo "Creating of reactor took: " . number_format(1000 * (microtime(TRUE) - $time), 2, '.', ' ') . " ms<br>";

		$time = microtime(TRUE);

		if (!$reactor->connect()) {
			echo "Connecting to reactor failed<br>";
		} else {
			echo "Connecting to reactor took: " . number_format(1000 * (microtime(TRUE) - $time), 2, '.', ' ') . " ms<br>";

			if (!$reactor->greet()) {
				echo "Request for root communication failed<br>";
			} elseif (!$reactor->init()) {
				echo "Request for reactor initialization failed<br>";
			} else {
				echo "Reactor was initialized correctly and expects connections from clients<br>";
			}

			$this->configurations[$reactor->configuration->name] = $reactor->configuration;
			$this->reactors[$reactor->configuration->name] = $reactor;
			$this->usedPorts[$reactor->configuration->port] = $reactor->configuration->name;

			return $reactor;
		};

		return FALSE;
	}


	private function createRootClient()
	{
		for ($i = 1; isset($this->configurations[$reactorName = $this->defaultReactorName . str_pad($i, 3, '0', STR_PAD_LEFT)]); ) {
			++$i;
		}
		do {
			$port = rand(1300, 1400);
		} while (isset($this->usedPorts[$port]));

		mkdir($temp = $this->environment->tempPath . "/$reactorName", 0777);

		$query = "php " . $this->environment->nemurePath . "/ReactorRun.php $reactorName $port $this->rootPassword > $temp/"
				. $this->environment->logFile . " &";

		echo "Starting reactor $reactorName on port $port<br>";
		proc_close(proc_open($query, [], $pipes, $temp, []));

		$configuration = $this->environment->readReactorConfiguration($reactorName, 5000);

		if ($configuration instanceof Configuration) {
			return new RootClient($configuration);
		}

		return FALSE;
	}


	public function killReactor($reactorName)
	{
		if (empty($this->configurations[$reactorName])) {
			return FALSE;
		}

		if (isset($this->reactors[$reactorName]) && $this->reactors[$reactorName]->socket !== FALSE) {
			socket_close($this->reactors[$reactorName]->socket);
			$this->reactors[$reactorName]->socket = FALSE;
			unset($this->reactors[$reactorName]);
		}

		$configuration = $this->configurations[$reactorName];

		if (posix_kill($configuration->pid, 15)) {
			$this->environment->deleteReactorDirectory($reactorName);

			unset($this->configurations[$reactorName]);
			unset($this->usedPorts[$configuration->port]);

			return TRUE;
		}

		return FALSE;
	}
}
