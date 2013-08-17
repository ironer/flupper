<?php
namespace Nemure;

/**
 * Copyright (c) 2013 Stefan Fiedler
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

// $this->getSession('User/Reacts')->members[$userId] = $reactId;

use Nette;

use Nette\Utils\Finder;
use Nette\Diagnostics\Debugger;

/**
 * Homepage presenter.
 */
class Root extends Nette\Object
{
	/** @var Configuration */
	public $conf;

	/** @var array (reactServerName => ['pid', 'port', 'error', 'status', 'clientCnt', 'path']) of currently runing reacts */
	public $reacts = [];

	/** @var array (port => reactServerName) used ports of running reacts */
	public $usedPorts = [];

	/** @var string default name for react */
	public $reactName;

	/** @var string password for root access to reacts */
	public $rootPwd = 'secret';


	public function __construct($tempDir, $reactName)
	{
		$time = microtime(TRUE);

		$this->conf = new Configuration($tempDir);

		list($this->reacts, $this->usedPorts) = $this->getReactsData();

		$this->reactName = !empty($reactName) ? $reactName : 'Default';

		echo "Reading of reacts' configurations (" . count($this->reacts) . ") took: " . number_format(1000 * (microtime(TRUE) - $time), 2, '.', ' ') . ' ms<br>';
	}


	public function startReact()
	{
		list($reactName, $port) = $this->runReact();

		$time = microtime(TRUE);

		$socket = $this->connectReact($port);

		echo "Connecting to react took: " . number_format(1000 * (microtime(TRUE) - $time), 2, '.', ' ') . " ms<br>";

		if ($socket === FALSE) {
			echo "Connecting to react failed<br>";
		} else {
			list($react, $port) = self::getReactConfig($reactName, $this->conf->tempPath);
			if ($port !== NULL) {
				$this->usedPorts[] = $port;
			}

			if (!$this->greetReact($reactName, $socket)) {
				echo "Request for root communication failed<br>";
			} elseif (!$this->initReact($socket)) {
				echo "Request for react initialization failed<br>";
			} else {
				echo "React was initialized correctly and expects connections from clients<br>";
			}

			$this->reacts[$reactName] = $react;
		}
	}


	public function killReact($reactName)
	{
		if (empty($this->reacts[$reactName])) {
			return FALSE;
		}
		$react = $this->reacts[$reactName];

		if (isset($react['pid'], $react['path']) && posix_kill($react['pid'], 15)) {

			foreach (Finder::findFiles('*')->from($react['path'])->childFirst() as $path => $file) {
				unlink($path);
			}

			rmdir($react['path']);
			unset($this->reacts[$reactName]);

			return TRUE;
		}

		return FALSE;
	}


	private function runReact()
	{
		if (count($this->reacts) > 30) {
			throw new \Exception('No more than 30 react servers allowed.');
		}
		for ($timeout = 1; isset($this->reacts[$reactName = $this->reactName . str_pad($timeout, 3, '0', STR_PAD_LEFT)]); ) {
			++$timeout;
		}
		do {
			$port = rand(1300, 1400);
		} while (in_array($port, $this->usedPorts));

		mkdir($temp = $this->conf->tempPath . "/$reactName", 0777);

		$query = "php " . $this->conf->nemurePath . "/Server.php $reactName $port $this->rootPwd > $temp/" . $this->conf->files['log'] . " &";

		echo "Starting react $reactName on port $port<br>";
		proc_close(proc_open($query, [], $pipes, $temp, []));

		return [$reactName, $port];
	}


	private function connectReact($port)
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

			if ($timeout > 3000) {
				break;
			}
			usleep(1000 * $step);
		}

		return FALSE;
	}


	private function greetReact($reactName, $socket)
	{
		list($halo, $haloError) = $this->readData($socket);

		if ($haloError !== 0) {
			echo "Reading of react's identification failed: $haloError<br>";
		} elseif ($halo === $reactName) {
			echo "$reactName greets correctly => ";

			if ($this->sendData($socket, $this->rootPwd) === strlen($this->rootPwd)) {
				echo "Root password sent => ";
			} else {
				echo "Sending of root password failed<br>";
				return FALSE;
			}

			list($confirm, $confirmError) = $this->readData($socket);
			if ($confirmError !== 0) {
				echo "Reading the confirmation of root access failed: $haloError<br>";
			} elseif ($confirm !== 'root') {
				echo "Wrong reply for confirmation or root access: '$confirm'<br>";
			} else {
				echo "Root access authorized<br>";
				return TRUE;
			}

			return FALSE;
		} else {
			echo "Expecting react's identity '$reactName', but received '$halo'<br>";
		}

		return FALSE;
	}


	private function initReact($socket)
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


	private function getReactsData()
	{
		$this->checkReactTemp();

		$reacts = [];
		$usedPorts = [];

		foreach (Finder::findDirectories('*')->in($this->conf->tempPath) as $dir) {
			/** @var \SplFileInfo $dir */
			$reactName = $dir->getFilename();

			list($react, $port) = $this->getReactConfig($reactName);
			if ($port !== NULL) {
				$usedPorts[] = $port;
			}

			$reacts[$reactName] = $react;
		}

		return [$reacts, $usedPorts];
	}


	private function checkReactTemp()
	{
		if (!is_dir($reactTemp = $this->conf->tempPath)) {
			if (is_dir($parentDir = dirname($reactTemp)) && is_writable($parentDir)) {
				mkdir($reactTemp, 0777);
			} else {
				throw new \Exception("Attemtp to create subdirectory for react servers failed in TEMP ($parentDir).");
			}
		} elseif (!is_writable($reactTemp)) {
			throw new \Exception("TEMP directory for react servers ($reactTemp) isn'r writable.");
		}

		return TRUE;
	}


	private function getReactConfig($reactName)
	{
		$port = NULL;

		$reactPath = $this->conf->tempPath . "/$reactName";
		$configName = $this->conf->files['config'];
		$reactConfig = "$reactPath/$configName";

		if (!is_file($reactConfig)) {
			$react = ['error' => 1, 'status' => "File $configName with react config wasn't found."];
		} elseif (!is_writable($reactConfig)) {
			$react = ['error' => 2, 'status' => "File $configName with react config isn't writable."];
		} else {
			$react = json_decode(file_get_contents($reactConfig), TRUE);

			if (!is_array($react) || count($react) !== 6 || empty($react['port'])) {
				$react = ['error' => 3, 'status' => "There is no valid configuration in the file $configName."];
			} else {
				$port = $react['port'];
			}
		}
		$react['path'] = $reactPath;

		return [$react, $port];
	}
}
