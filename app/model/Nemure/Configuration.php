<?php
namespace Nemure;

/**
 * Copyright (c) 2013 Stefan Fiedler
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

use Nette;

/**
 * Class for storing configuration data for reactor servers.
 * @author Stefan Fiedler
 */
class Configuration extends Nette\Object
{
	const CMD_INIT = 'init'; // initializing command for reactor server to start accepting client connections
	const CMD_DIE = 'die'; // die command for reactor server to stop itself
	const CMD_ADD_CLIENT = 'add'; // command like 'add [SSID]' to allow the access to given client
	const CMD_REMOVE_CLIENT = 'remove'; // remove given SSID client (connection to client will be closed immediately)
	const CMD_GET_OPTIONS = 'options'; // command for recieving options array of reactor server in serialized json
	const CMD_GET_USERS = 'clients'; // get the clients array in serialized json

	const STATUS_EXPECTING_REACTOR_SPECIFIC_OPTIONS = 'EXPECTING_REACTOR_SPECIFIC_OPTIONS';
	const STATUS_EXPECTING_ROOT_INIT = 'EXPECTING_ROOT_INIT';
	const STATUS_ACCEPTING_CLIENT_CONNECTIONS = 'ACCEPTING_CLIENT_CONNECTIONS';


	public $files = [
		'options' => 'options.json', // default filename for storing reactor's options
		'clients' => 'clients.json', // default filename for storing reactor's clients
		'log' => 'stdout.txt' // default filename for redirecting reactor's output
	];

	public $nemurePath = __DIR__; // absolute path to Nemure scripts

	public $tempPath;


	public function __construct($tempDir)
	{
		$this->tempPath = realpath($tempDir) . '/reactors';
	}
}
