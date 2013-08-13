<?php

use Nette\Utils\Finder;


/**
 * Homepage presenter.
 */
class HomepagePresenter extends BasePresenter
{
	const REACT_CONFIG = 'config.json', // default filename for storing react configuration
		REACT_LOG = 'log.txt'; // default filename for redirecting react's output


	/** @var string path to react server php script */
	private $scriptDir = '';

	/** @var string path to temp directory of reacts */
	private $tempDir = '';

	/** @var array (reactServerName => ['path', 'pid', 'port', 'error', 'status', 'clients']) of currently runing reacts */
	private $reacts = [];

	/** @var array (port => reactServerName) used ports of running reacts */
	private $usedPorts = [];

	/** @var string default name for react */
	private $reactName = '';


	protected function startup()
	{
		parent::startup();
		$time = microtime(TRUE);
		$this->scriptDir = dirname($this->context->parameters['appDir'])  . '/bin';
		$this->tempDir = realpath($this->context->parameters['tempDir']) . '/reacts';
		list($this->reacts, $this->usedPorts) = self::getReactsData($this->tempDir);

		$this->reactName = !empty($this->name) ? $this->name : 'Default';
		echo 'Nacteni reactu (' . count($this->reacts) . '): ' . number_format(1000 * (microtime(TRUE) - $time), 2, '.', ' ') . ' ms';
	}

	public function renderDefault()
	{
		\Nette\Diagnostics\Debugger::dump($this->scriptDir);
		\Nette\Diagnostics\Debugger::dump($this->tempDir);
		\Nette\Diagnostics\Debugger::dump($this->reacts);
		\Nette\Diagnostics\Debugger::dump($this->reactName);
	}

	public function actionStartReact() {
		$this->startReact();
	}

	private function startReact()
	{
		if (count($this->reacts) > 10) throw new Exception('Hele, neblbni.');
		for ($i = 1; isset($this->reacts[$reactName = $this->reactName . str_pad($i, 3, '0', STR_PAD_LEFT)]); ) ++$i;
		while (in_array($port = rand(1300, 1400), $this->usedPorts)) {}

		$query = "php $this->scriptDir/testServer.php > $this->tempDir/" . self::REACT_LOG . " &"; // $this->tempDir/$reactName/
		proc_close(proc_open($query, [], $pipes, '/tmp', []));
	}

	private static function getReactsData($reactTemp)
	{
		if (!is_dir($reactTemp)) {
			if (is_dir($parentDir = dirname($reactTemp)) && is_writable($parentDir)) mkdir($reactTemp, 0777);
			else throw new Exception("V TEMP adresari ($parentDir) nelze vytvorit podadresar pro react servery.");
		} elseif (!is_writable($reactTemp)) throw new Exception("Do TEMP adresare pro react servery ($reactTemp) nelze zapisovat.");

		$reacts = [];
		$usedPorts = [];

		foreach (Finder::findDirectories('*')->in($reactTemp) as $path => $dir) {

			if (!is_file($statusFile = $path . '/' . self::REACT_CONFIG)) {
				$react = ['error' => 1, 'status' => "Nelze nalezt soubor " . self::REACT_CONFIG . " s konfiguraci reactu."];
			} elseif (!is_writable($statusFile)) {
				$react = ['error' => 2, 'status' => "Soubor " . self::REACT_CONFIG . " s konfiguraci reactu neni zapisovatelny."];
			} else {
				$react = json_decode(file_get_contents($statusFile), TRUE);
				if (!is_array($react) || count($react) !== 5 || empty($react['port'])) {
					$react = ['error' => 3, 'status' => "Nelze nacist validni konfiguraci reactu ze souboru " . self::REACT_CONFIG . "."];
				} else $usedPorts[] = $react['port'];
			}

			$react['path'] = $path;
			$reacts[$dir->getFilename()] = $react;
		}

		return [$reacts, $usedPorts];
	}
}
