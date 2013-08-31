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
	const EOL = "\r\n"; // line separator in message
	const EOM = "\r\n\r\n"; // end of message

	const STATUS_EXPECTING_ROOT_INIT = 'EXPECTING_ROOT_INIT';
	const STATUS_ACCEPTING_CLIENT_CONNECTIONS = 'ACCEPTING_CLIENT_CONNECTIONS';


	public $configFile = 'config.txt'; // default filename for storing reactor's configuration
	public $clientsFile = 'clients.txt'; // default filename for storing reactor's configuration
	public $logFile = 'log.txt'; // default filename for storing reactor's configuration

	public $nemurePath = __DIR__; // absolute path to Nemure scripts

	public $tempPath;


	public function __construct($tempDir)
	{
		$this->tempPath = realpath($tempDir) . '/reactors';
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
}
