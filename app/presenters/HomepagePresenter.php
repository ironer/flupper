<?php

// $this->getSession('User/Reacts')->members[$userId] = $reactId;

use Nette\Utils\Finder;


/**
 * Homepage presenter.
 */
class HomepagePresenter extends BasePresenter
{
	const REACT_CONFIG = 'config.json', // default filename for storing react configuration
		REACT_CLIENTS = 'clients.json', // default filename for storing react clients
		REACT_LOG = 'stdout.txt', // default filename for redirecting react's output
		CMD_INIT = 'init', // initializing command for react server to respond to clients
		CMD_INFO = 'info', // info command for recieving config array of react server in serialized json
		CMD_DIE = 'die', // die command for react server to stop itself
		CMD_ADD_CLIENT = 'add', // command like add SSID to allow given client access to react server
		CMD_REMOVE_CLIENT = 'remove', // remove given SSID client (connection to client will be closed immediately)
		CMD_GET_USERS = 'clients'; // get the clients array

	/** @var string path to react server php script */
	private $scriptDir = '';

	/** @var string path to temp directory of reacts */
	private $tempDir = '';

	/** @var array (reactServerName => ['pid', 'port', 'error', 'status', 'clientCnt', 'path']) of currently runing reacts */
	private $reacts = [];

	/** @var array (port => reactServerName) used ports of running reacts */
	private $usedPorts = [];

	/** @var string default name for react */
	private $reactName = '';

	/** @var string password for root access to reacts */
	public $rootPwd = 'secret';


	protected function startup()
	{
		parent::startup();

		$time = microtime(TRUE);

		$this->scriptDir = dirname($this->context->parameters['appDir']) . '/bin';
		$this->tempDir = realpath($this->context->parameters['tempDir']) . '/reacts';
		list($this->reacts, $this->usedPorts) = self::getReactsData($this->tempDir);
		$this->reactName = !empty($this->name) ? $this->name : 'Default';

		echo 'Nacteni reactu (' . count($this->reacts) . '): ' . number_format(1000 * (microtime(TRUE) - $time), 2, '.', ' ') . ' ms<br>';
	}


	public function renderDefault()
	{
		\Nette\Diagnostics\Debugger::dump($this->scriptDir);
		\Nette\Diagnostics\Debugger::dump($this->tempDir);
		\Nette\Diagnostics\Debugger::dump($this->reacts);
		\Nette\Diagnostics\Debugger::dump($this->reactName);
	}


	public function actionStartReact()
	{
		list($reactName, $port) = $this->startReact();

		$time = microtime(TRUE);

		$socket = $this->connectReact($port);

		echo 'Pripojeni k reactu trvalo: ' . number_format(1000 * (microtime(TRUE) - $time), 2, '.', ' ') . ' ms<br>';

		if ($socket === FALSE) {
			echo 'Pripojeni na react selhalo<br>';
		} else {
			list($react, $port) = self::getReactConfig($reactName, $this->tempDir);
			if ($port !== NULL) {
				$this->usedPorts[] = $port;
			}

			if (!$this->greetReact($reactName, $socket)) {
				echo 'Pokus o root komunikaci s reactem selhal<br>';
			} elseif (!$this->initReact($reactName, $socket)) {
				echo 'Pokus o inicializaci reactu selhal<br>';
			} else {
				echo 'React byl radne inicializovan a prijima pripojeni<br>';
			}

			$this->reacts[$reactName] = $react;
		}

		$this->setView('default');
	}


	public function actionKillReact()
	{
		if (!count($this->reacts)) {
			echo "Vsechny reacty jsou vypnute<br>";
		} elseif ($this->killReact($reactName = array_keys($this->reacts)[0])) {
			echo "Byl vypnut react $reactName<br>";
		} else {
			echo "Vypnuti reactu $reactName selhalo<br>";
		}

		$this->setView('default');
	}


	private function startReact()
	{
		if (count($this->reacts) > 30) {
			throw new Exception('Hele, neblbni.');
		}
		for ($timeout = 1; isset($this->reacts[$reactName = $this->reactName . str_pad($timeout, 3, '0', STR_PAD_LEFT)]); ) {
			++$timeout;
		}
		while (in_array($port = rand(1300, 1400), $this->usedPorts)) {
		}

		mkdir($temp = "$this->tempDir/$reactName", 0777);

		$query = "php $this->scriptDir/testServer.php $reactName $port $temp " . self::REACT_CONFIG . " " . self::REACT_CLIENTS . " "
			. $this->rootPwd ." > $temp/" . self::REACT_LOG . " &";

		echo "Asynchronni spusteni reactu $reactName na portu $port<br>";
		proc_close(proc_open($query, [], $pipes, $temp, []));

		return [$reactName, $port];
	}


	private function killReact($reactName)
	{
		if (empty($this->reacts[$reactName])) {
			return FALSE;
		}
		$react = $this->reacts[$reactName];

		if (isset($react['pid'], $react['path']) && posix_kill($react['pid'], 15)) {

			foreach (Finder::findFiles('*')->from($react['path'])->childFirst() as $path => $file) {
				unlink($path);
			}

			rmdir($react['path']);
			unset($this->reacts[$reactName]);

			return TRUE;
		}

		return FALSE;
	}


	private function connectReact($port)
	{
		for ($timeout = 0, $step = 10; TRUE; $timeout += $step, $step *= 1.3) {

			echo "Vytvoreni socketu => ";
			if (FALSE !== $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) {

				echo "Nastaveni timeoutu (1s) => ";
				if (socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => 1, 'usec' => 0])) {

					echo "Pokus o pripojeni (port $port) => ";
					if (@socket_connect($socket, $_SERVER["HTTP_HOST"], $port)) {

						echo "Spojeni navazano<br>";
						return $socket;
					}
				}
			}

			echo socket_strerror(socket_last_error()) . '<br>';
			socket_close($socket);

			if ($timeout > 2000) {
				break;
			}
			usleep(1000 * $step);
		}

		return FALSE;
	}


	private function initReact($reactName, $socket)
	{
		echo "Odesilam inicializaci reactu => ";

//		if ($this->sendData($socket, "init") !== 4) {
//			echo " Odeslani selhalo<br>";
//		} else {
//			list($init, $initError) = $this->readData($socket);
//
//			if ($initError !== 0) {
//				echo "Chyba pri cteni odpovedi reactu: $initError<br>";
//			} else
//		}


		return FALSE;
	}


	private function greetReact($reactName, $socket)
	{
		list($halo, $haloError) = $this->readData($socket);

		if ($haloError !== 0) {
			echo "Chyba pri cteni identifikace reactu: $haloError<br>";
		} elseif ($halo === $reactName) {
			echo "$reactName korektne zdravi => ";

			if ($this->sendData($socket, $this->rootPwd) === strlen($this->rootPwd)) {
				echo "Odeslano root heslo => ";
			} else {
				echo 'Selhalo odeslani root hesla<br>';
				return FALSE;
			}

			list($confirm, $confirmError) = $this->readData($socket);
			if ($confirmError !== 0) {
				echo "Chyba pri cteni potvrzeni autorizace root uctu na reactu: $haloError<br>";
			} elseif ($confirm !== 'root') {
				echo "Chybna odpoved pri potvrzeni autorizace root uctu na reactu: '$confirm'<br>";
			} else {
				echo "Autorizovan root pristup na react<br>";
				return TRUE;
			}

			return FALSE;
		} else {
			echo "Ocekavan pozdrav '$reactName', ale doslo '$halo'<br>";
		}

		return FALSE;
	}


	private function sendData($socket, $data)
	{
		return socket_send($socket, $data, strlen($data), 0);
	}


	private function readData($socket)
	{
		$response = '';
		$chunkSize = 1024;

		while ($byteCnt = socket_recv($socket, $buf, $chunkSize, 0)) {
			$response .= $buf;
			if ($byteCnt < $chunkSize) {
				break;
			}
		}

		if ($byteCnt === FALSE) {
			return [$response, socket_strerror(socket_last_error($socket))];
		}

		return [$response, 0];
	}


	private static function getReactsData($reactTemp)
	{
		self::checkReactTemp($reactTemp);

		$reacts = [];
		$usedPorts = [];

		foreach (Finder::findDirectories('*')->in($reactTemp) as $path => $dir) {
			$reactName = $dir->getFilename();

			list($react, $port) = self::getReactConfig($reactName, $reactTemp);
			if ($port !== NULL) {
				$usedPorts[] = $port;
			}

			$reacts[$reactName] = $react;
		}

		return [$reacts, $usedPorts];
	}


	private static function checkReactTemp($reactTemp)
	{
		if (!is_dir($reactTemp)) {
			if (is_dir($parentDir = dirname($reactTemp)) && is_writable($parentDir)) {
				mkdir($reactTemp, 0777);
			} else {
				throw new Exception("V TEMP adresari ($parentDir) nelze vytvorit podadresar pro react servery.");
			}
		} elseif (!is_writable($reactTemp)) {
			throw new Exception("Do TEMP adresare pro react servery ($reactTemp) nelze zapisovat.");
		}
	}


	private static function getReactConfig($reactName, $reactTemp)
	{
		$port = NULL;

		if (!is_file($statusFile = "$reactTemp/$reactName/" . self::REACT_CONFIG)) {
			$react = ['error' => 1, 'status' => "Nelze nalezt soubor " . self::REACT_CONFIG . " s konfiguraci reactu."];
		} elseif (!is_writable($statusFile)) {
			$react = ['error' => 2, 'status' => "Soubor " . self::REACT_CONFIG . " s konfiguraci reactu neni zapisovatelny."];
		} else {
			$react = json_decode(file_get_contents($statusFile), TRUE);

			if (!is_array($react) || count($react) !== 5 || empty($react['port'])) {
				$react = ['error' => 3, 'status' => "Nelze nacist validni konfiguraci reactu ze souboru " . self::REACT_CONFIG . "."];
			} else {
				$port = $react['port'];
			}
		}
		$react['path'] = "$reactTemp/$reactName";

		return [$react, $port];
	}
}
