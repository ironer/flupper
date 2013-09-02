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


	public function __destruct()
	{
		unset($this->message);
		unset($this->socket);
	}


	public function send($command, $data = FALSE)
	{
		if (!$this->prepareMessage($command, $data)) {
			return FALSE;
		}

		$this->status = Environment::MESSENGER_SENDING;
		$success = FALSE;

		if ($this->message->chunkCount() === 1) {
			$chunk = Environment::MESSAGE_BOM . $this->message->shiftFirstChunk() . Environment::MESSAGE_EOM;
			$success = $this->connectionWriteChunk($chunk);
		} else {
			throw new \Exception("Only one chunk per message allower for now. ;-)");
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
			return $confirmedBytes === Environment::MESSAGE_CHUNK_ALTERNATE_CONFIRMATION || strlen($chunk) === (int) $confirmedBytes;
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

			if (strlen($received) > Environment::MESSAGE_MAX_CHUNK_SIZE) {
				return FALSE;
			}
		}

		$received = trim($received);

		if ($bufferedBytes !== FALSE && strlen($received)) {
			$data = $received;
			return TRUE;
		}

		return FALSE;
	}


	public function sendMessage($command, $data = [])
	{
		if (!is_array($data)) {
			$text = (string) $data;
			$data = strlen($text) ? [$text] : [];
		}

		foreach ($data as &$row) {
			$row = base64_encode($row);
		}

		array_unshift($data, (string) $command);

		$message = implode(Environment::EOL, $data) . Environment::EOM;

		return $this->connection->write($message);
	}


	public function readMessage($message)
	{
		$message = trim($message);
		$length = strlen((string) $message);
		$eomLength = strlen(Environment::EOM);

		if ($length <= $eomLength || substr($message, $length - $eomLength) !== Environment::EOM) {
			return FALSE;
		}

		$rows = explode(Environment::EOL, $message);

		for ($i = 1, $rowsCount = count($rows); $i < $rowsCount; ++$i) {
			if (FALSE === $rows[$i] = base64_decode($rows[$i])) {
				return FALSE;
			};
		}

		return $rows;
	}
}
