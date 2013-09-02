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
 * Reactor server class.
 * @author Stefan Fiedler
 * @property callable onConnection
 */
class Reactor extends Nette\Object
{
	/** @var Nette\DI\Container */
	private $container;

	/** @var Environment */
	private $environment;

	/** @var Configuration */
	public $configuration;

	public $clients = ['root' => FALSE];


	public function __construct(Nette\DI\Container $container, $argv)
	{
		if (count($argv) !== 4) {
			throw new \LogicException("Invalid number of arguments, expected 4");
		}

		$this->container = $container;
		
		$this->environment = new Environment($this->container->parameters['tempDir']);

		$this->configuration = new Configuration($argv[1], getmypid(), intval($argv[2]), $argv[3]);

		$this->environment->writeReactorConfiguration($this->configuration);
	}


	public function start()
	{
		$loop = React\EventLoop\Factory::create();

		$socket = new React\Socket\Server($loop);
		$socket->on('connection', $this->onConnection);
		$socket->listen($this->configuration->port);

		$loop->run();
	}


	public function onConnection(React\Socket\Connection $connection)
	{
		$client = new ReactorClient($connection);

		$client->connection->write($this->configuration->name);

		$connection->on('data', function ($data) use ($client) {
			$this->onConnectionData($client, trim($data));
		});
	}


	public function onConnectionData(ReactorClient $client, $data)
	{
		if (FALSE === $message = $client->readMessage($data)) {
			echo "Invalid request recieved from user '" . ($client->data['user'] ?: 'unknown') . "'.\n\n\n";
			$this->closeConnection($client);
			return FALSE;
		} elseif (!$client->data['greet']) {
			if (!$this->handleGreet($client, $data)) {
				$this->closeConnection($client);
				return FALSE;
			}
			echo "Connection for '" . $client->data['user'] . "' started.\n\n\n";
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
		if ($data === $this->configuration->rootPassword) {
			echo "Valid greeting of 'root' accepted.\n\n\n";
			$client->connection->write('root');
			$client->data['user'] = 'root';
		} elseif ($this->configuration->error === 0 && isset($this->clients[$data])) {
			echo "Valid greeting of user '$data' accepted.\n\n\n";
			$client->connection->write($data);
			$client->data['user'] = $data;
		} else {
			echo "Invalid greeting user '$data' denied.\n\n\n";
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
			echo "Removing active connection for user '" . $client->data['user'] . "'. User can reconnect later.\n\n\n";
			$this->clients[$client->data['user']] = FALSE;
		}

		$this->closeConnection($client);

		return TRUE;
	}


	private function closeConnection(ReactorClient $client)
	{
		echo "Closing connection for user '" . ($client->data['user'] ?: 'unknown') . "'.\n\n\n";

		$client->connection->close();
		unset($client);

		return TRUE;
	}


	private function authorizeAccess($user)
	{
		$authorised = $user === 'root' || ($this->configuration->error === 0 && isset($this->clients[$user]));

		if ($authorised) {
			echo "Request authorized for user '$user'.\n\n\n";
		} else {
			echo "Request denied for user '$user'.\n\n\n";
		}

		return $authorised;
	}


	private function handleRootData(ReactorClient $client, $data)
	{
		if ($data === 'init' && $this->configuration->error === -1) {
			$this->configuration->error = 0;
			$this->configuration->status = Environment::REACTOR_ACCEPTING_CLIENT_CONNECTIONS;
			$this->environment->writeReactorConfiguration($this->configuration);
			return 'init';
		}

		return FALSE;
	}


	private function handleData(ReactorClient $client, $data)
	{
		return FALSE;
	}
}
