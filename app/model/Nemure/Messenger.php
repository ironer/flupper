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


	abstract public function send($command, $data = FALSE);

	abstract public function receive(&$command, &$data);
}
