<?php

$container = require_once __DIR__ . '/../app/bootstrap.php';
/** @var \Nette\DI\Container $container */

\Nette\Diagnostics\Debugger::$productionMode = FALSE;
$container->getByType('Nette\Http\IResponse')->setContentType('text/plain');

class BoardServer extends Nette\Object
{

	private $options;
	private $clients;

	/** @var Nette\DI\Container */
	private $container;


	public function __construct(\Nette\DI\Container $container, $argv)
	{
		if (count($argv) !== 7) {
			throw new LogicException("Invalid number of arguments, expected 7");
		}

		$this->options = array(
			'script' => $argv[0],
			'name' => $argv[1],
			'rootPwd' => $argv[6],
			'configFile' => "$argv[3]/$argv[4]",
			'clientsFile' => "$argv[3]/$argv[5]",
			'config' => array(
				'pid' => getmypid(),
				'port' => intval($argv[2]),
				'error' => 0,
				'status' => "EXPECTING_ROOT_INIT",
				'clientCnt' => 0
			)
		);

		$this->clients = [];

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

		$status = ['init' => TRUE, 'close' => FALSE, 'root' => FALSE];

		$conn->on('data', function ($data) use ($conn, &$status) {
			$this->onConnectionData($conn, $data, $status);
		});
	}


	public function onConnectionData(\React\Socket\Connection $conn, $data, &$status)
	{
		if ($status['init']) {
			if (!$this->handleInit($conn, $data, $status)) return FALSE;
			$response = FALSE;
		} else {
			$response = $status['root'] ? $this->handleRootData($data, $status) : $this->handleData($data, $status);
		}

		if ($response !== FALSE) {
			$conn->write($response);
			echo "Request:\n$data\n\nResponse:\n$response\n\n\n";
		} else {
			echo "Request:\n$data\n\n\n";
		}

		if ($status['close']) {
			echo "Closing connection\n\n\n";
			$conn->close();
		}
	}


	private function handleInit(\React\Socket\Connection $conn, $data, &$status)
	{
		if ($data === $this->options['rootPwd']) {
			echo "Starting root connection\n\n\n";
			$conn->write('root');
			$status['root'] = TRUE;
		} elseif (in_array($data, $this->clients)) {
			echo "Starting connection for user '$data'\n\n\n";
			$conn->write($data);
		} else {
			$conn->close();
			return FALSE;
		}
		$status['init'] = FALSE;
		return TRUE;
	}

}


$server = new BoardServer($container, $argv);
$server->start();