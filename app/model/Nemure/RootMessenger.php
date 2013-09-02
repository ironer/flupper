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
 * Class for sending and receiving messages through custom protocol build using sockets.
 * @author Stefan Fiedler
 */
class RootMessenger extends Messenger
{
	/** @var resource */
	private $socket = FALSE;


	public function __construct($socket)
	{
		if (!is_resource($socket)) {
			throw new \InvalidArgumentException('Expecting one argument of resource type.');
		}

		$this->socket = $socket;
	}


	public function __destruct()
	{
		unset($this->message);
		unset($this->socket);
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

		$this->status = Environment::MESSENGER_SENDING;
		$success = FALSE;

		if ($chunkCount === 1) {
			$chunk = Environment::MESSAGE_BOM . $this->message->shiftFirstChunk() . Environment::MESSAGE_EOM;
			$success = $this->socketWriteChunk($chunk);
		} else {
			$chunk = Environment::MESSAGE_BOM . $this->message->shiftFirstChunk() . Environment::MESSAGE_EOL;

			while ($success = $this->socketWriteChunk($chunk) && $chunkCount = $this->message->chunkCount()) {
				$chunk = $this->message->shiftFirstChunk();
				$chunk .= $chunkCount === 1 ? Environment::MESSAGE_EOM : Environment::MESSAGE_EOL;
			}
		}

		unset($this->message);
		$this->status = Environment::MESSENGER_READY;

		return $success;
	}


	public function receive(&$command, &$data)
	{
		$command = $data = '';

		if ($this->status !== Environment::MESSENGER_READY) {
			$this->stop();
		}

		$this->status = Environment::MESSENGER_RECEIVING;
		$success = FALSE;

		while ($this->socketReadChunk($chunk)) {
			if ($chunk[0] === Environment::MESSAGE_BOM) {
				$this->resetMessage();
				$chunk = substr($chunk, 1);
			} elseif (!$this->message->allowAddChunk()) {
				break;
			}

			$endChar = substr($chunk, -1);

			if ($endChar === Environment::MESSAGE_EOL) {
				$this->message->addChunk(substr($chunk, 0, -1));
				continue;
			} elseif ($endChar === Environment::MESSAGE_EOM) {
				$this->message->addChunk(substr($chunk, 0, -1));
				$success = TRUE;
			}

			break;
		}

		if ($success && $success = $this->message->decompile()) {
			$command = $this->message->getCommand();
			$data = $this->message->getData();
		}

		unset($this->message);
		$this->status = Environment::MESSENGER_READY;

		return $success;
	}


	public function stop()
	{
		if ($this->status === Environment::MESSENGER_SENDING) {
		} elseif ($this->status === Environment::MESSENGER_RECEIVING) {
		} else {
			return FALSE;
		}

		unset($this->message);
		$this->status = Environment::MESSENGER_READY;

		return TRUE;
	}


	private function socketWriteChunk($chunk)
	{
		if ($this->socketWrite($chunk) && $this->socketRead($confirmedBytes)) {
			return strlen($chunk) === (int) $confirmedBytes;
		}
		return FALSE;
	}


	private function socketReadChunk(&$chunk)
	{
		$chunk = '';

		if ($this->socketRead($received)) {
			if ($this->socketWrite((string) strlen($received))) {
				$chunk = $received;
				return TRUE;
			}
		}

		return FALSE;
	}


	private function socketWrite($data)
	{
		if (($bytesToSend = strlen($data)) > Environment::MESSAGE_MAX_CHUNK_SIZE) {
			return FALSE;
		}

		$writtenBytes = @socket_send($this->socket, $data, $bytesToSend, 0);

		return $writtenBytes === FALSE ? FALSE : $writtenBytes === $bytesToSend;
	}


	private function socketRead(&$data)
	{
		$data = $received = '';
		$receivedBytes = 0;

		while ($bufferedBytes = @socket_recv($this->socket, $buffer, Environment::MESSAGE_MAX_CHUNK_SIZE, 0)) {
			$received .= $buffer;

			if (($receivedBytes = strlen($received)) > Environment::MESSAGE_MAX_CHUNK_SIZE) {
				return FALSE;
			}
		}

		if ($bufferedBytes !== FALSE && $receivedBytes) {
			$data = $received;
			return TRUE;
		}

		return FALSE;
	}
}
