<?php
if (count($argv) !== 6) die(1);

$options = array(
	'script' => $argv[0],
	'name' => $argv[1],
	'configFile' => "$argv[3]/$argv[4]" ,
	'clientsFile' => "$argv[3]/$argv[5]",
	'config' => array(
		'pid' => getmypid(),
		'port' => intval($argv[2]),
		'error' => 0,
		'status' => "REACT_STARTING",
		'clients' => 0
	)
);

umask();
file_put_contents($options['configFile'], json_encode($options['config']));

require __DIR__.'/../libs/autoload.php';

$i = 0;
$app = function ($request, $response) use (&$i) {
	$i++;
	$text = "This is request number $i.\n";
	$headers = array('Content-Type' => 'text/plain');

	$response->writeHead(200, $headers);
	$response->end($text);
	if ($i === 10) die();
};

$loop = React\EventLoop\Factory::create();
$socket = new React\Socket\Server($loop);
$http = new React\Http\Server($socket);

$http->on('request', $app);

$socket->listen($options['config']['port']);
$loop->run();