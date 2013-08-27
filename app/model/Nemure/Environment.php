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


	public function readReactorConfiguration($reactorName)
	{
		$configPath = "$this->tempPath/$reactorName/$this->configFile";

		if (!is_file($configPath)) {
			throw new \Exception("File '$configPath' with reactor's configuration wasn't found.");
		} elseif (!is_writable($configPath)) {
			throw new \Exception("File '$configPath' with reactor's configuration isn't writable.");
		} else {
			$configuration = unserialize(file_get_contents($configPath));

			if (!$configuration instanceof Configuration) {
				throw new \Exception("There is no valid configuration in the file '$configPath'.");
			}
		}

		return $configuration;
	}


	public function writeReactorConfiguration(Configuration $configuration)
	{
		umask();
		file_put_contents("$this->tempPath/$configuration->name/$this->configFile", serialize($configuration));

		return TRUE;
	}


	public function readReactorClients($reactorName)
	{
		$clientsPath = "$this->tempPath/$reactorName/$this->clientsFile";

		if (!is_file($clientsPath)) {
			throw new \Exception("File '$clientsPath' with reactor's clients wasn't found.");
		} elseif (!is_writable($clientsPath)) {
			throw new \Exception("File '$clientsPath' with reactor's clients isn't writable.");
		} else {
			$clients = unserialize(file_get_contents($clientsPath));

			if (!is_array($clients)) {
				throw new \Exception("There is no array of clients in the file '$clientsPath'.");
			}

			foreach ($clients as $user => $client) {
				if ($client !== FALSE && !$client instanceof ReactorClient) {
					throw new \Exception("Invalid client data for user '$user' in file '$clientsPath'.");
				}
			}
		}

		return $clients;
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
