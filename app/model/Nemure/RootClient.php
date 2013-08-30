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
 * Class for handling TCP connection to react server and storing asociated data.
 * @author Stefan Fiedler
 */
class RootClient extends Nette\Object
{
	/** @var Configuration */
	public $configuration;

	public $clients = ['root' => FALSE];

	/** @var resource */
	public $socket = FALSE;


	public function __construct(Configuration $configuration) {
		$this->configuration = $configuration;
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

						echo "Connection successful<br>";
						$this->socket = $socket;
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
		list($halo, $haloError) = $this->readData();

		if ($haloError !== 0) {
			echo "Reading of reactor's identification failed: $haloError<br>";
		} elseif ($halo === $this->configuration->name) {
			echo "$halo greets correctly => ";

			if ($this->sendData($this->configuration->rootPassword) === strlen($this->configuration->rootPassword)) {
				echo "Root password sent => ";
			} else {
				echo "Sending of root password failed<br>";
				return FALSE;
			}

			list($confirm, $confirmError) = $this->readData();
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
			echo "Expecting reactor's name '" . $this->configuration->name . "', but received '$halo'<br>";
		}

		return FALSE;
	}


	public function init()
	{
		echo "Sending init request => ";

		if ($this->sendData("init") !== 4) {
			echo " Sending of init command failed<br>";
		} else {
			list($init, $initError) = $this->readData();

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


	private function readData()
	{
		if ($this->socket === FALSE) {
			throw new \Exception("Root client has now active socket for communication.");
		}

		$response = '';
		$chunkSize = 1024;

		while ($byteCnt = socket_recv($this->socket, $buf, $chunkSize, 0)) {
			$response .= $buf;
			if ($byteCnt < $chunkSize) {
				break;
			}
		}

		if ($byteCnt === FALSE) {
			return [$response, socket_strerror(socket_last_error($this->socket))];
		}

		return [$response, 0];
	}


	private function sendData($data)
	{
		if ($this->socket === FALSE) {
			throw new \Exception("Root client has now active socket for communication.");
		}

		return socket_send($this->socket, $data, strlen($data), 0);
	}
}