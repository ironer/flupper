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
	public $reactors = [];

	/** @var array (port => reactorName) used ports of running reactors */
	public $usedPorts = [];

	/** @var string default name for reactor */
	public $reactorName;

	/** @var string password for root access to reactors */
	public $rootPassword = 'secret';


	public function __construct($tempDir, $reactorName)
	{
		$time = microtime(TRUE);

		$this->environment = new Environment($tempDir);

		list($this->reactors, $this->usedPorts) = $this->environment->getReactorsConfigurations();

		$this->reactorName = !empty($reactorName) ? $reactorName : 'Default';

		echo "Reading of reactors' configurations (" . count($this->reactors) . ") took: "
				. number_format(1000 * (microtime(TRUE) - $time), 2, '.', ' ') . ' ms<br>';
	}


	public function startReactor()
	{
		list($reactorName, $port) = $this->runReactor();

		$time = microtime(TRUE);

		$socket = $this->connectReactor($port);

		echo "Connecting to reactor took: " . number_format(1000 * (microtime(TRUE) - $time), 2, '.', ' ') . " ms<br>";

		if ($socket === FALSE) {
			echo "Connecting to reactor failed<br>";
		} else {
			$configuration = $this->environment->readReactorConfiguration($reactorName);

			if (!$this->greetReactor($configuration->name, $socket)) {
				echo "Request for root communication failed<br>";
			} elseif (!$this->initReactor($socket)) {
				echo "Request for reactor initialization failed<br>";
			} else {
				echo "Reactor was initialized correctly and expects connections from clients<br>";
			}

			$this->reactors[$configuration->name] = $configuration;
			$this->usedPorts[$configuration->port] = $configuration->name;
		}
	}


	public function killReactor($reactorName)
	{
		if (empty($this->reactors[$reactorName])) {
			return FALSE;
		}

		$configuration = $this->reactors[$reactorName];

		if (posix_kill($configuration->pid, 15)) {
			$this->environment->deleteReactorDirectory($reactorName);

			unset($this->reactors[$reactorName]);
			unset($this->usedPorts[$configuration->port]);

			return TRUE;
		}

		return FALSE;
	}


	private function runReactor()
	{
		if (count($this->reactors) > 30) {
			throw new \Exception('No more than 30 reactor servers allowed.');
		}
		for ($reactorNumber = 1; isset($this->reactors[$reactorName = $this->reactorName . str_pad($reactorNumber, 3, '0', STR_PAD_LEFT)]); ) {
			++$reactorNumber;
		}
		do {
			$port = rand(1300, 1400);
		} while (isset($this->usedPorts[$port]));

		mkdir($temp = $this->environment->tempPath . "/$reactorName", 0777);

		$query = "php " . $this->environment->nemurePath . "/Reactor.php $reactorName $port $this->rootPassword > $temp/"
				. $this->environment->logFile . " &";

		echo "Starting reactor $reactorName on port $port<br>";
		proc_close(proc_open($query, [], $pipes, $temp, []));

		return [$reactorName, $port];
	}


	private function connectReactor($port)
	{
		for ($timeout = 0, $step = 10; TRUE; $timeout += $step, $step *= 1.3) {

			echo "Creating TCP socket => ";
			if (FALSE !== $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) {

				echo "Setting timeout to 1 second => ";
				if (socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => 1, 'usec' => 0])) {

					echo "Connecting to port $port => ";
					if (@socket_connect($socket, $_SERVER["HTTP_HOST"], $port)) {

						echo "Connection successful<br>";
						return $socket;
					}
				}
			}

			echo socket_strerror(socket_last_error()) . '<br>';
			socket_close($socket);

			if ($timeout > 2000) {
				break;
			}
			usleep(1000 * $step);
		}

		return FALSE;
	}


	private function greetReactor($reactorName, $socket)
	{
		list($halo, $haloError) = $this->readData($socket);

		if ($haloError !== 0) {
			echo "Reading of reactor's identification failed: $haloError<br>";
		} elseif ($halo === $reactorName) {
			echo "$reactorName greets correctly => ";

			if ($this->sendData($socket, $this->rootPassword) === strlen($this->rootPassword)) {
				echo "Root password sent => ";
			} else {
				echo "Sending of root password failed<br>";
				return FALSE;
			}

			list($confirm, $confirmError) = $this->readData($socket);
			if ($confirmError !== 0) {
				echo "Reading the confirmation of root access failed: $haloError<br>";
			} elseif ($confirm !== 'root') {
				echo "Wrong reply for confirmation of root access: '$confirm'<br>";
			} else {
				echo "Root access authorized<br>";
				return TRUE;
			}

			return FALSE;
		} else {
			echo "Expecting reactor's name '$reactorName', but received '$halo'<br>";
		}

		return FALSE;
	}


	private function initReactor($socket)
	{
		echo "Sending init request => ";

		if ($this->sendData($socket, "init") !== 4) {
			echo " Sending of init command failed<br>";
		} else {
			list($init, $initError) = $this->readData($socket);

			if ($initError !== 0) {
				echo "Attempt to read response failed: $initError<br>";
			} elseif ($init !== 'init') {
				echo "Wrong response: $init<br>";
			} else {
				echo "Initialization successful<br>";
				return TRUE;
			}
		}

		return FALSE;
	}


	private function sendData($socket, $data)
	{
		return socket_send($socket, $data, strlen($data), 0);
	}


	private function readData($socket)
	{
		$response = '';
		$chunkSize = 1024;

		while ($byteCnt = socket_recv($socket, $buf, $chunkSize, 0)) {
			$response .= $buf;
			if ($byteCnt < $chunkSize) {
				break;
			}
		}

		if ($byteCnt === FALSE) {
			return [$response, socket_strerror(socket_last_error($socket))];
		}

		return [$response, 0];
	}
}
