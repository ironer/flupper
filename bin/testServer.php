<?php

$container = require_once __DIR__ . '/../app/bootstrap.php';
/** @var \Nette\DI\Container $container */

\Nette\Diagnostics\Debugger::$productionMode = FALSE;
$container->getByType('Nette\Http\IResponse')->setContentType('text/plain');

class BoardServer extends Nette\Object
{

	private $options;

	private $clients = ['root' => NULL];

	/** @var Nette\DI\Container */
	private $container;


	public function __construct(\Nette\DI\Container $container, $argv)
	{
		if (count($argv) !== 7) {
			throw new LogicException("Invalid number of arguments, expected 7");
		}

		$this->options = [
			'script' => $argv[0],
			'name' => $argv[1],
			'rootPwd' => $argv[6],
			'configFile' => "$argv[3]/$argv[4]",
			'clientsFile' => "$argv[3]/$argv[5]",
			'config' => [
				'pid' => getmypid(),
				'port' => intval($argv[2]),
				'error' => -1,
				'status' => "EXPECTING_ROOT_INIT",
				'clientCnt' => 0
			]
		];

		umask();
		file_put_contents($this->options['configFile'], json_encode($this->options['config']));
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


	public function onConnection(\React\Socket\Connection $conn)
	{
		$conn->write($this->options['name']);

		$client = new BoardClient($conn);

		$conn->on('data', function ($data) use ($client) {
			$this->onConnectionData($client, $data);
		});
	}


	public function onConnectionData(\BoardClient $client, $data)
	{
		$response = FALSE;

		if (!$client->data['greet']) {
			if (!$this->handleGreet($client, $data)) {
				$client->conn->close();
				return FALSE;
			}
		} elseif ($this->authorizeAccess($client->data['user'])) {
			$response = $client->data['user'] === 'root' ? $this->handleRootData($client, $data) : $this->handleData($client, $data);
		} else {
			$this->disconnectClient($client);
			return FALSE;
		}

		if ($response !== FALSE) {
			$client->conn->write($response);
			echo "Request:\n$data\n\nResponse:\n$response\n\n\n";
		} else {
			echo "Request:\n$data\n\n\n";
		}

		if ($client->data['close']) {
			echo "Closing connection for user " . $client->data['user'] . "\n\n\n";
			$client->conn->close();
		}
		return TRUE;
	}


	private function handleGreet(\BoardClient $client, $data)
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


	private function addClient(\BoardClient $client)
	{
		if (!isset($this->clients[$client->data['user']])) {
			return FALSE;
		} elseif ($this->clients[$client->data['user']] !== NULL) {
			$this->disconnectClient($this->clients[$client->data['user']]);
		}

		$this->clients[$client->data['user']] = $client;

		return TRUE;
	}


	private function disconnectClient(\BoardClient $client)
	{
		if (isset($this->clients[$client->data['user']])) {
			$this->clients[$client->data['user']] = NULL;
		}

		echo "Closing connection for user " . $client->data['user'] . "\n\n\n";

		$client->conn->close();
		unset($client->conn);
		unset($client);

		return TRUE;
	}


	private function authorizeAccess($user)
	{
		return $user === 'root' || ($this->options['config']['error'] === 0 && isset($this->clients[$user]));
	}


	private function handleRootData($data, &$conData)
	{
		return FALSE;
	}


	private function handleData($data, &$conData)
	{
		return FALSE;
	}
}


class BoardClient extends Nette\Object
{
	public $conn;

	public $data = ['greet' => FALSE, 'close' => FALSE, 'user' => NULL];

	public function __construct(\React\Socket\Connection $connection)
	{
		$this->conn = $connection;
	}
}


$server = new BoardServer($container, $argv);
$server->start();