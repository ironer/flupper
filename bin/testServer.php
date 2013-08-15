<?php

$container = require_once __DIR__ . '/../app/bootstrap.php';
/** @var \Nette\DI\Container $container */

\Nette\Diagnostics\Debugger::$productionMode = FALSE;
$container->getByType('Nette\Http\IResponse')->setContentType('text/plain');

class BoardServer extends Nette\Object
{

	private $options;

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

		umask();
		file_put_contents($this->options['configFile'], json_encode($this->options['config']));
		$this->container = $container;
	}


	public function start()
	{
		$loop = React\EventLoop\Factory::create();
		$socket = new React\Socket\Server($loop);
		//$http = new React\Http\Server($socket);

		$socket->on('connection', $this->onConnection);

		//$http->on('request', $app);

		$socket->listen($this->options['config']['port']);
		$loop->run();
	}


	public function onConnection(\React\Socket\Connection $conn)
	{
		$conn->write($this->options['name']);

		$conn->on('data', function ($data) use ($conn) {
			$this->onConnectionData($conn, $data);
		});
	}


	public function onConnectionData(\React\Socket\Connection $conn, $data)
	{
		echo "$data\n";
		//  $conn->close();
	}
}


$server = new BoardServer($container, $argv);
$server->start();