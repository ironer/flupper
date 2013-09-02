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
 * Abstract class for sending and receiving messages through custom protocol.
 * @author Stefan Fiedler
 */
abstract class Messenger extends Nette\Object
{
	/** @var Message */
	protected $message;

	protected $status = Environment::MESSENGER_READY;

	protected $timeout = FALSE;


	public function getStatus()
	{
		return $this->status;
	}


	protected function resetMessage()
	{
		$reseted = FALSE;

		if (isset($this->message)) {
			unset($this->message);
			$reseted = TRUE;
		}

		$this->message = new Message;

		return $reseted;
	}


	protected function prepareMessage($command, $data = FALSE)
	{
		if (!Environment::isValidCommand($command)) {
			return FALSE;
		}
		if ($this->status !== Environment::MESSENGER_READY) {
			$this->stop();
		}

		$this->message = new Message;

		try {
			$this->message->setCommand($command)->setData($data)->compile();
			return TRUE;
		} catch (\Exception $e) {
			return FALSE;
		}
	}


	abstract public function send($command, $data = FALSE);

	abstract public function receive(&$command, &$data);

	abstract protected function stop();
}
