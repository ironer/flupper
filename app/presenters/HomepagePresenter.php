<?php

// $this->getSession('User/Reacts')->members[$userId] = $reactId;

use Nette\Diagnostics\Debugger;

/**
 * Homepage presenter.
 */
class HomepagePresenter extends BasePresenter
{
	/** @var \Nemure\Root */
	private $root;


	protected function startup()
	{
		parent::startup();

		$this->root = new \Nemure\Root($this->context->parameters['tempDir'], $this->name);
	}


	public function renderDefault()
	{
		dump($this->root->environment);
		dump($this->root->configurations);
		dump($this->root->usedPorts);
		dump($this->root->reactors);
	}


	public function actionStartReactor()
	{
		$this->root->startReactor();

		$this->setView('default');
	}


	public function actionKillReactor()
	{
		if (!count($this->root->configurations)) {
			echo "All reactor servers are already shut down<br>";
		} elseif ($this->root->killReactor($reactorName = array_keys($this->root->configurations)[0])) {
			echo "$reactorName was shut down<br>";
		} else {
			echo "Shutting down of $reactorName failed<br>";
		}

		$this->setView('default');
	}
}
