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
		REACT_LOG = 'stdout.txt'; // default filename for redirecting react's output


	/** @var string path to react server php script */
	private $scriptDir = '';

	/** @var string path to temp directory of reacts */
	private $tempDir = '';

	/** @var array (reactServerName => ['path', 'pid', 'port', 'error', 'status', 'clients']) of currently runing reacts */
	private $reacts = array();

	/** @var array (port => reactServerName) used ports of running reacts */
	private $usedPorts = array();

	/** @var string default name for react */
	private $reactName = '';


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

		if ($socket === FALSE) echo 'Neco se posralo!<br>';
		else {
			echo 'Pripojeni na react se povedlo ;-)<br>';

			list($react, $port) = self::getReactConfig($reactName, $this->tempDir);
			if ($port !== NULL) $this->usedPorts[] = $port;

			$this->reacts[$reactName] = $react;
		}

		$this->setView('default');
	}

	public function actionKillReact()
	{
		if (count($this->reacts)) $this->killReact(array_keys($this->reacts)[0]);

		$this->setView('default');
	}

	private function startReact()
	{
		if (count($this->reacts) > 30) throw new Exception('Hele, neblbni.');
		for ($timeout = 1; isset($this->reacts[$reactName = $this->reactName . str_pad($timeout, 3, '0', STR_PAD_LEFT)]); ) ++$timeout;
		while (in_array($port = rand(1300, 1400), $this->usedPorts)) {}

		mkdir($temp = "$this->tempDir/$reactName", 0777);

		$query = "php $this->scriptDir/testServer.php $reactName $port $temp " . self::REACT_CONFIG . " " . self::REACT_CLIENTS
			."> $temp/" . self::REACT_LOG . " &";

		proc_close(proc_open($query, array(), $pipes, $temp, array()));
		return array($reactName, $port);
	}

	private function killReact($reactName)
	{
		if (empty($this->reacts[$reactName])) return FALSE;
		$react = $this->reacts[$reactName];

		if (isset($react['pid'], $react['path']) && posix_kill($react['pid'], 9)) {

			foreach (Finder::findFiles('*')->from($react['path'])->childFirst() as $path => $file) unlink($path);

			rmdir($react['path']);
			unset($this->reacts[$reactName]);

			return TRUE;
		}

		return FALSE;
	}

	private function connectReact($port)
	{
		for ($timeout = 0, $step = 10; TRUE; $timeout += $step, $step *= 1.3) {
			echo 'Pokus o pripojeni...<br>';

			$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
			socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => 1, 'usec' => 0]);
			if (@socket_connect($socket, $_SERVER["HTTP_HOST"], $port)) return $socket;

			echo socket_strerror(socket_last_error()) . '<br>';
			socket_close($socket);

			if ($timeout > 2000) break;
			usleep(1000 * $step);
		}

		return FALSE;
	}

	private static function getReactsData($reactTemp)
	{
		self::checkReactTemp($reactTemp);

		$reacts = array();
		$usedPorts = array();

		foreach (Finder::findDirectories('*')->in($reactTemp) as $path => $dir) {
			$reactName = $dir->getFilename();

			list($react, $port) = self::getReactConfig($reactName, $reactTemp);
			if ($port !== NULL) $usedPorts[] = $port;

			$reacts[$reactName] = $react;
		}

		return array($reacts, $usedPorts);
	}

	private static function checkReactTemp($reactTemp)
	{
		if (!is_dir($reactTemp)) {
			if (is_dir($parentDir = dirname($reactTemp)) && is_writable($parentDir)) mkdir($reactTemp, 0777);
			else throw new Exception("V TEMP adresari ($parentDir) nelze vytvorit podadresar pro react servery.");
		} elseif (!is_writable($reactTemp)) throw new Exception("Do TEMP adresare pro react servery ($reactTemp) nelze zapisovat.");
	}

	private static function getReactConfig($reactName, $reactTemp)
	{
		$port = NULL;

		if (!is_file($statusFile = "$reactTemp/$reactName/" . self::REACT_CONFIG)) {
			$react = array('error' => 1, 'status' => "Nelze nalezt soubor " . self::REACT_CONFIG . " s konfiguraci reactu.");
		} elseif (!is_writable($statusFile)) {
			$react = array('error' => 2, 'status' => "Soubor " . self::REACT_CONFIG . " s konfiguraci reactu neni zapisovatelny.");
		} else {
			$react = json_decode(file_get_contents($statusFile), TRUE);

			if (!is_array($react) || count($react) !== 5 || empty($react['port'])) {
				$react = array('error' => 3, 'status' => "Nelze nacist validni konfiguraci reactu ze souboru " . self::REACT_CONFIG . ".");
			} else $port = $react['port'];
		}
		$react['path'] = "$reactTemp/$reactName";

		return array($react, $port);
	}
}
