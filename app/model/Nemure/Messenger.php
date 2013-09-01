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
 * Class for sending and receiving messages through custom protocol build on sockets.
 * @author Stefan Fiedler
 */
class Messenger extends Nette\Object
{
	/** @var React\Socket\Connection */
	private $connection = FALSE;

	/** @var resource */
	private $socket = FALSE;

	/** @var Message */
	private $message;

	private $status = Environment::MESSENGER_READY;

	private $type;

	private $timeout = FALSE;

	/** @var callable function for reading up to 1kB from pipe */
	private $read;

	/** @var callable function for writing up to 1kB to pipe */
	private $write;

	/** @var callable */
	public $onSent;

	/** @var callable */
	public $onError;

	/** @var callable */
	public $onReceived;

	/** @var callable */
	public $onStop;

	/** @var callable */
	public $onTimeout;

	public function __construct($pipe)
	{
		if ($pipe instanceof React\Socket\Connection) {
			$this->connection = $pipe;
			$this->type = Environment::MESSENGER_TYPE_REACTOR;
			$this->write = $this->connectionWrite;
			$this->read = $this->connectionRead;
		} elseif (is_resource($pipe)) {
			$this->socket = $pipe;
			$this->type = Environment::MESSENGER_TYPE_SOCKET;
			$this->write = $this->socketWrite;
			$this->read = $this->socketRead;
		} else {
			throw new \InvalidArgumentException('Expecting one argument of class React\Socket\Connection or socket type.');
		}
	}


	public function getStatus()
	{
		return $this->status;
	}


	public function send($command, $data = FALSE)
	{
		if (!Environment::isValidCommand($command)) {
			return FALSE;
		}
		if ($this->status !== Environment::MESSENGER_READY) {
			$this->stop();
		}

		$this->message = new Message;

		try {
			$chunkCount = $this->message->setCommand($command)->setData($data)->compile()->chunkCount();
		} catch (\Exception $e) {
			return FALSE;
		}

		if ($chunkCount === 1) {
			$part = Environment::MESSAGE_BOM . $this->message->getFirstChunk() . Environment::MESSAGE_EOM;
			return $this->write($part) === strlen($part);
		} elseif ($this->type === Environment::MESSENGER_TYPE_SOCKET) {
			$part = Environment::MESSAGE_BOM . $this->message->getFirstChunk() . Environment::MESSAGE_EOL;
			$partLength = $this->write($part);
			while
		};

		return TRUE;
	}


	public function stop()
	{
		if ($this->status !== Environment::MESSENGER_READY && isset($this->onStop)) {
			$this->onStop($this->message);
		}

		if ($this->status === Environment::MESSENGER_SENDING) {
		} elseif ($this->status === Environment::MESSENGER_RECEIVING) {
		} else {
			return FALSE;
		}

		unset($this->message);
		$this->status = Environment::MESSENGER_READY;

		return TRUE;
	}


	public function receive($timeout)
	{

	}


	private function socketWrite($chunk)
	{
		$chunkLength = min(1024, strlen($chunk));

		return socket_send($this->socket, substr($chunk, 0, $chunkLength), $chunkLength, 0);
	}


	private function socketRead()
	{
		$chunkLength = @socket_recv($this->socket, $chunk, 1024, 0);

		return !$chunkLength ? FALSE : $chunk;
	}



}
