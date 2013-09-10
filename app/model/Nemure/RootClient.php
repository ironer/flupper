<?php
namespace Nemure;

/**
 * Copyright (c) 2013 Stefan Fiedler
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

use Nette;
use React;

/**
 * Class for handling TCP connection to reactor server and storing asociated data.
 * @author Stefan Fiedler
 */
class RootClient extends Nette\Object
{
	/** @var Configuration */
	public $configuration;

	/** @var RootMessenger */
	public $messenger;

	public $clients = ['root' => FALSE];

	/** @var resource */
	public $socket = FALSE;


	public function __construct(Configuration $configuration)
	{
		$this->configuration = $configuration;
	}


	public function __destruct()
	{
		unset($this->messenger);
		unset($this->socket);
	}


	public function connect()
	{
		for ($timeout = 0, $step = 10; TRUE; $timeout += $step, $step *= 1.2) {

			echo "Creating TCP socket => ";
			if (FALSE !== $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) {

				echo "Setting timeout to 1 second => ";
				if (socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => 1, 'usec' => 0])) {

					echo "Connecting to port " . $this->configuration->port . " => ";
					if (@socket_connect($socket, $_SERVER["HTTP_HOST"], $this->configuration->port)) {

						echo "Connection successful (creating RootMessenger)<br>";
						$this->socket = $socket;
						$this->messenger = new RootMessenger($socket);
						return TRUE;
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


	public function greet()
	{
		if (!$this->messenger->receive($command, $data)) {
			echo "Reading of reactor's identification failed<br>";
			return FALSE;
		} elseif ($command !== $this->configuration->name) {
			echo "Expecting reactor's name '" . $this->configuration->name . "', but received '$command'<br>";
			return FALSE;
		} else {
			echo "$command greets correctly => ";
		}

		if (!$this->messenger->send($this->configuration->rootPassword)) {
			echo "Sending of root password failed<br>";
			return FALSE;
		} else {
			echo "Root password sent => ";
		}

		if (!$this->messenger->receive($command, $data)) {
			echo "Reading the confirmation of root access failed<br>";
			return FALSE;
		} elseif ($command !== $hash = sha1($this->configuration->rootPassword)) {
			echo "Expecting root password confirmation '" . $hash . "', but received '$command'<br>";
			return FALSE;
		} else {
			echo "Root access authorized<br>";
		}

		return TRUE;
	}


	public function init()
	{
		if (!$this->messenger->send(Environment::CMD_INIT)) {
			echo "Sending of init request failed<br>";
			return FALSE;
		} else {
			echo "Init request sent => ";
		}

		if (!$this->messenger->receive($command, $data)) {
			echo "Reading the confirmation of init request failed<br>";
			return FALSE;
		} elseif ($command !== Environment::CMD_INIT) {
			echo "Expecting init request confirmation '" . Environment::CMD_INIT . "', but received '$command'<br>";
			return FALSE;
		} else {
			echo "Initialization successful<br>";
		}

		return TRUE;
	}
}