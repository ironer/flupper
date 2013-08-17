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
		dump($this->root->conf);
		dump($this->root->reacts);
	}


	public function actionStartReact()
	{
		$this->root->startReact();

		$this->setView('default');
	}


	public function actionKillReact()
	{
		if (!count($this->root->reacts)) {
			echo "All react servers are already shut down<br>";
		} elseif ($this->root->killReact($reactName = array_keys($this->root->reacts)[0])) {
			echo "$reactName was shut down<br>";
		} else {
			echo "Shutting down of $reactName failed<br>";
		}

		$this->setView('default');
	}
}
