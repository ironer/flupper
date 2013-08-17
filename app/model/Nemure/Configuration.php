<?php
namespace Nemure;

/**
 * Copyright (c) 2012 Stefan Fiedler
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

use Nette;

/**
 * @author Stefan Fiedler
 */
class Configuration extends Nette\Object
{
	const CMD_INIT = 'init'; // initializing command for react server to respond to clients
	const CMD_INFO = 'info'; // info command for recieving config array of react server in serialized json
	const CMD_DIE = 'die'; // die command for react server to stop itself
	const CMD_ADD_CLIENT = 'add'; // command like add SSID to allow given client access to react server
	const CMD_REMOVE_CLIENT = 'remove'; // remove given SSID client (connection to client will be closed immediately)
	const CMD_GET_USERS = 'clients'; // get the clients array


	public $files = [
		'config' => 'config.json', // default filename for storing react configuration
		'clients' => 'clients.json', // default filename for storing react clients
		'log' => 'stdout.txt', // default filename for redirecting react's output
	];

	public $nemurePath = __DIR__; // absolute path to Nemure scripts

	public $tempPath;


	public function __construct($tempDir)
	{
		$this->tempPath = realpath($tempDir) . '/reacts';
	}
}
