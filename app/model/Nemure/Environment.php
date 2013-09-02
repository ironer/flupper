<?php
namespace Nemure;

/**
 * Copyright (c) 2013 Stefan Fiedler
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

use Nette;
use Nette\Utils\Finder;

/**
 * Class containing environment of reactor servers, processing their configurations and clients.
 * @author Stefan Fiedler
 */
class Environment extends Nette\Object
{
	const CMD_INIT = 'init'; // initializing command for reactor server to start accepting client connections
	const CMD_DIE = 'die'; // die command for reactor server to stop itself
	const CMD_ADD_CLIENT = 'add'; // command like 'add [SSID]' to allow the access to given client
	const CMD_REMOVE_CLIENT = 'remove'; // remove given SSID client (connection to client will be closed immediately)
	const CMD_GET_CONFIGURATION = 'config'; // command for recieving serialized configuration of reactor server
	const CMD_GET_CLIENTS = 'clients'; // get array of serialized reactor's clients

	const MESSAGE_BOM = "@"; // start character of message
	const MESSAGE_EOL = "#"; // data parts separator character in message
	const MESSAGE_EOM = "$"; // end of message character
	const MESSAGE_ERR = "&"; // error in message (stop receiving) character
	const MESSAGE_MAX_CHUNKS = 9; // maximum number of chunks (max. chunk lenght incl. control chars) in message (1 for command and)
	const MESSAGE_MAX_CHUNK_SIZE = 1024; // maximum byte size of one chunk including control chararacters
	const MESSAGE_MAX_DATA_IN_CHUNK = 1000; // maximum number of data bytes in one chunk
	public static $messageMaxDataSize; // maximum bytes of all encoded data in one message

	const MESSENGER_TYPE_REACTOR = 0; // messenger is using asynchronous reactor communication
	const MESSENGER_TYPE_SOCKET = 1; // messenger is using blocked socket communication
	const MESSENGER_READY = 0; // messenger is ready for sending or receiving message
	const MESSENGER_SENDING = 1; // messenger is sending a message
	const MESSENGER_RECEIVING = 2; // messenger is receiving a message
	const MESSENGER_TIMEOUT_MS = 2000; // maximum timeout in miliseconds for sending or receiving one message

	const REACTOR_EXPECTING_ROOT_INIT = 'EXPECTING_ROOT_INIT';
	const REACTOR_ACCEPTING_CLIENT_CONNECTIONS = 'ACCEPTING_CLIENT_CONNECTIONS';


	public $configFile = 'config.txt'; // default filename for storing reactor's configuration
	public $clientsFile = 'clients.txt'; // default filename for storing reactor's configuration
	public $logFile = 'log.txt'; // default filename for storing reactor's configuration

	public $nemurePath = __DIR__; // absolute path to Nemure scripts

	public $tempPath;


	public function __construct($tempDir)
	{
		$this->tempPath = realpath($tempDir) . '/reactors';
		self::$messageMaxDataSize = self::MESSAGE_MAX_DATA_IN_CHUNK * (self::MESSAGE_MAX_CHUNKS - 1);
	}


	public function getReactorsConfigurations()
	{
		$this->checkReactorsTemp();

		$reactors = [];
		$usedPorts = [];

		foreach (Finder::findDirectories('*')->in($this->tempPath) as $dir) {
			/** @var \SplFileInfo $dir */
			$reactorName = $dir->getFilename();

			$configuration = $this->readReactorConfiguration($reactorName);

			$usedPorts[$configuration->port] = $reactorName;
			$reactors[$reactorName] = $configuration;
		}

		return [$reactors, $usedPorts];
	}


	public function checkReactorsTemp()
	{
		if (!is_dir($this->tempPath)) {
			if (is_dir($parentDir = dirname($this->tempPath)) && is_writable($parentDir)) {
				mkdir($this->tempPath, 0777);
			} else {
				throw new \Exception("Attempt to create subdirectory for reactor servers failed in TEMP ($parentDir).");
			}
		} elseif (!is_writable($this->tempPath)) {
			throw new \Exception("Working directory for reactor servers ($this->tempPath) isn't writable.");
		}

		return TRUE;
	}


	public function readReactorConfiguration($reactorName, $timeout = 0)
	{
		$configPath = "$this->tempPath/$reactorName/$this->configFile";

		do {
			if (is_file($configPath) && is_writable($configPath)) {
				$configuration = unserialize(file_get_contents($configPath));

				if ($configuration instanceof Configuration) {
					return $configuration;
				}

				unset($configuration);
			}

			usleep(10000);
		} while (($timeout -= 10) > 0);

		return FALSE;
	}


	public function writeReactorConfiguration(Configuration $configuration)
	{
		umask();
		file_put_contents("$this->tempPath/$configuration->name/$this->configFile", serialize($configuration));

		return TRUE;
	}


	public function readReactorClients($reactorName, $timeout)
	{
		$clientsPath = "$this->tempPath/$reactorName/$this->clientsFile";

		do {
			if (is_file($clientsPath) && is_writable($clientsPath)) {
				$clients = unserialize(file_get_contents($clientsPath));
				$valid = TRUE;

				if (is_array($clients)) {
					foreach ($clients as $client) {
						if ($client !== FALSE && !$client instanceof ReactorClient) {
							$valid = FALSE;
						}
					}
				} else {
					$valid = FALSE;
				}

				if ($valid) {
					return $clients;
				}

				unset($clients);
			}

			usleep(10000);
		} while (($timeout -= 10) > 0);

		return FALSE;
	}


	public function writeReactorClients(Reactor $reactor)
	{
		umask();
		file_put_contents("$this->tempPath/" . $reactor->configuration->name . "/$this->configFile", serialize($reactor->clients));

		return TRUE;
	}


	public function deleteReactorDirectory($reactorName)
	{
		$dirPath = "$this->tempPath/$reactorName";

		foreach (Finder::findFiles('*')->from($dirPath)->childFirst() as $path => $file) {
			unlink($path);
		}

		rmdir($dirPath);
	}


	public static function isValidCommand($command = FALSE)
	{
		return is_string($command) && strlen($command) <= self::MESSAGE_MAX_DATA_IN_CHUNK && 0 === preg_match('#[^a-z0-9 ]#i', $command);
	}
}
