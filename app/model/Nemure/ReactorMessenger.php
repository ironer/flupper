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
 * Class for sending and receiving messages through custom protocol build using reactor's connection.
 * @author Stefan Fiedler
 */
class ReactorMessenger extends Messenger
{
	/** @var React\Socket\Connection */
	private $connection = FALSE;

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


	public function __construct(React\Socket\Connection $connection)
	{
		$this->connection = $connection;
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
			$writtenBytes = $this->write($part);

			while ($writtenBytes && $writtenBytes === strlen($part) && $chunkCount = $this->message->chunkCount()) {
				if ($this->read() !== (string) $writtenBytes) {
					return FALSE;
				}
				$part = $this->message->getFirstChunk();
				$part .= $chunkCount === 1 ? Environment::MESSAGE_EOM : Environment::MESSAGE_EOL;
				$writtenBytes = $this->write($part);
			}

			if (!$writtenBytes || $this->read() !== (string) $writtenBytes || $this->message->chunkCount()) {
				return FALSE;
			}
		} elseif ($this->type === Environment::MESSENGER_TYPE_REACTOR) {
			return FALSE;
		} else {
			return FALSE;
		}

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


	public function receive()
	{

	}
}
