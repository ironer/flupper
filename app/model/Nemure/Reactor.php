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

Nette\Diagnostics\Debugger::$productionMode = FALSE;
$container->getByType('Nette\Http\IResponse')->setContentType('text/plain');

/**
 * Server script for running react.
 * @author Stefan Fiedler
 * @property mixed onConnection
 */
class Reactor extends Nette\Object
{
	/** @var Nette\DI\Container */
	private $container;

	/** @var Configuration */
	private $configuration;

	/** @var Options */
	private $options;

	private $clients = ['root' => FALSE];


	public function __construct(Nette\DI\Container $container, $argv)
	{
		if (count($argv) !== 4) {
			throw new \LogicException("Invalid number of arguments, expected 4");
		}

		$this->container = $container;
		
		$this->configuration = new Configuration($this->container->parameters['tempDir']);

		$this->options = new Options($argv[1], $this->configuration);
		$this->options->setup(getmypid(), intval($argv[2]), $argv[3]);
		$this->options->write();
	}


	public function start()
	{
		$loop = React\EventLoop\Factory::create();

		$socket = new React\Socket\Server($loop);
		$socket->on('connection', $this->onConnection);
		$socket->listen($this->options->port);

		$loop->run();
	}


	public function onConnection(React\Socket\Connection $connection)
	{
		$connection->write($this->options->name);

		$client = new ReactorClient($connection);

		$connection->on('data', function ($data) use ($client) {
			$this->onConnectionData($client, $data);
		});
	}


	public function onConnectionData(ReactorClient $client, $data)
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
				$client->connection->write($response);
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


	private function handleGreet(ReactorClient $client, $data)
	{
		if ($data === $this->options->rootPassword) {
			echo "Starting root connection\n\n\n";
			$client->connection->write('root');
			$client->data['user'] = 'root';
		} elseif ($this->options['config']['error'] === 0 && isset($this->clients[$data])) {
			echo "Starting connection for user '$data'\n\n\n";
			$client->connection->write($data);
			$client->data['user'] = $data;
		} else {
			return FALSE;
		}

		$client->data['greet'] = TRUE;
		return $this->addClient($client);
	}


	private function addClient(ReactorClient $client)
	{
		if (!isset($this->clients[$client->data['user']])) {
			return FALSE;
		} elseif ($this->clients[$client->data['user']] !== FALSE) {
			$this->disconnectClient($this->clients[$client->data['user']]);
		}

		$this->clients[$client->data['user']] = $client;

		return TRUE;
	}


	private function disconnectClient(ReactorClient $client)
	{
		if (isset($this->clients[$client->data['user']])) {
			echo "Removing active connection for " . $client->data['user'] . ". User can reconnect later.\n\n\n";
			$this->clients[$client->data['user']] = FALSE;
		}

		$this->closeConnection($client);

		return TRUE;
	}


	private function closeConnection(ReactorClient $client)
	{
		echo "Closing connection for user " . ($client->data['user'] ?: 'unknown') . ".\n\n\n";

		$client->connection->close();
		unset($client->connection);
		unset($client);

		return TRUE;
	}


	private function authorizeAccess($user)
	{
		return $user === 'root' || ($this->options['config']['error'] === 0 && isset($this->clients[$user]));
	}


	private function handleRootData(ReactorClient $client, $data)
	{
		if ($data === 'init') {
			return 'init';
		}

		return FALSE;
	}


	private function handleData(ReactorClient $client, $data)
	{
		return FALSE;
	}
}

$server = new Reactor($container, $_SERVER['argv']);
$server->start();