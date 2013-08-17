<?php
namespace Nemure;

/**
 * Copyright (c) 2013 Stefan Fiedler
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

use Nette;
use React;

$container = require_once __DIR__ . '/../../bootstrap.php';
/** @var Nette\DI\Container $container */

\Nette\Diagnostics\Debugger::$productionMode = FALSE;
$container->getByType('Nette\Http\IResponse')->setContentType('text/plain');

/**
 * Server script for running react.
 * @author Stefan Fiedler
 */
class Server extends Nette\Object
{
	/** @var Configuration */
	private $conf;

	private $options;

	private $clients = ['root' => FALSE];

	/** @var Nette\DI\Container */
	private $container;


	public function __construct(Nette\DI\Container $container, $argv)
	{
		if (count($argv) !== 4) {
			throw new \LogicException("Invalid number of arguments, expected 4");
		}

		$this->conf = new Configuration($container->parameters['tempDir']);

		$path = $this->conf->tempPath . '/' . $argv[1];

		$this->options = [
			'name' => $argv[1],
			'rootPwd' => $argv[3],
			'config' => [
				'pid' => getmypid(),
				'port' => intval($argv[2]),
				'error' => -1,
				'status' => "EXPECTING_ROOT_INIT",
				'clientCnt' => 0,
				'path' => $path
			],
			'files' => [
				'script' => $argv[0],
				'config' => $path . '/' . $this->conf->files['config'],
				'clients' => $path . '/' . $this->conf->files['clients']
			]
		];

		umask();
		file_put_contents($this->options['files']['config'], json_encode($this->options['config']));

		$this->container = $container;
	}


	public function start()
	{
		$loop = React\EventLoop\Factory::create();

		$socket = new React\Socket\Server($loop);
		$socket->on('connection', $this->onConnection);
		$socket->listen($this->options['config']['port']);

		$loop->run();
	}


	public function onConnection(React\Socket\Connection $conn)
	{
		$conn->write($this->options['name']);

		$client = new Client($conn);

		$conn->on('data', function ($data) use ($client) {
			$this->onConnectionData($client, $data);
		});
	}


	public function onConnectionData(Client $client, $data)
	{
		if (!$client->data['greet']) {
			if (!$this->handleGreet($client, $data)) {
				$this->closeConnection($client);
				return FALSE;
			}
		} elseif (!$this->authorizeAccess($client->data['user'])) {
			$this->disconnectClient($client);
			return FALSE;
		} else {
			$response = $client->data['user'] === 'root' ? $this->handleRootData($client, $data) : $this->handleData($client, $data);

			if ($response !== FALSE) {
				$client->conn->write($response);
				echo "Request:\n$data\n\nResponse:\n$response\n\n\n";
			} else {
				echo "Request:\n$data\n\n\n";
			}
		}

		if ($client->data['close']) {
			$this->disconnectClient($client);
		}
		return TRUE;
	}


	private function handleGreet(Client $client, $data)
	{
		if ($data === $this->options['rootPwd']) {
			echo "Starting root connection\n\n\n";
			$client->conn->write('root');
			$client->data['user'] = 'root';
		} elseif ($this->options['config']['error'] === 0 && isset($this->clients[$data])) {
			echo "Starting connection for user '$data'\n\n\n";
			$client->conn->write($data);
			$client->data['user'] = $data;
		} else {
			return FALSE;
		}

		$client->data['greet'] = TRUE;
		return $this->addClient($client);
	}


	private function addClient(Client $client)
	{
		if (!isset($this->clients[$client->data['user']])) {
			return FALSE;
		} elseif ($this->clients[$client->data['user']] !== FALSE) {
			$this->disconnectClient($this->clients[$client->data['user']]);
		}

		$this->clients[$client->data['user']] = $client;

		return TRUE;
	}


	private function disconnectClient(Client $client)
	{
		if (isset($this->clients[$client->data['user']])) {
			echo "Removing active connection for " . $client->data['user'] . ". User can reconnect later.\n\n\n";
			$this->clients[$client->data['user']] = FALSE;
		}

		$this->closeConnection($client);

		return TRUE;
	}


	private function closeConnection(Client $client)
	{
		echo "Closing connection for user " . ($client->data['user'] ?: 'unknown') . ".\n\n\n";

		$client->conn->close();
		unset($client->conn);
		unset($client);

		return TRUE;
	}


	private function authorizeAccess($user)
	{
		return $user === 'root' || ($this->options['config']['error'] === 0 && isset($this->clients[$user]));
	}


	private function handleRootData(Client $client, $data)
	{
		if ($data === 'init') {
			return 'init';
		}

		return FALSE;
	}


	private function handleData(Client $client, $data)
	{
		return FALSE;
	}
}

$server = new Server($container, $_SERVER['argv']);
$server->start();