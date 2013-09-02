<?php
namespace Nemure;

/**
 * Copyright (c) 2013 Stefan Fiedler
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

use Nette;

/**
 * Class for representing one message for sending or receiving.
 * @author Stefan Fiedler
 */
class Message extends Nette\Object
{
	private $command = FALSE; // command with arguments

	private $data = FALSE; // data which are converted to JSON and base64 encoded for transmission in the message

	private $chunks = []; // chunks of compiled message


	public function hasValidCommand()
	{
		return Environment::isValidCommand($this->command);
	}


	public function setCommand($command)
	{
		$command = (string) $command;

		if (($length = strlen($command)) > Environment::MESSAGE_MAX_DATA_IN_CHUNK) {
			throw new \Exception("Invalid command length (max. " . Environment::MESSAGE_MAX_DATA_IN_CHUNK . "): $length.");
		}

		if (0 !== preg_match('#[^a-z0-9 ]#i', $command)) {
			throw new \Exception("Command contains invalid character(s). Just alphanumeric and space are allowed.");
		}

		$this->command = $command;
		return $this;
	}


	public function setData($data = FALSE)
	{
		$this->data = $data;
		return $this;
	}


	public function compile()
	{
		if (!$this->hasValidCommand()) {
			throw new \Exception("Command needs to be set before compiling message.");
		}

		$this->chunks = [$this->command];

		if ($this->data !== FALSE) {
			$encoded = base64_encode(json_encode($this->data));

			if (($length = strlen($encoded)) > Environment::$messageMaxDataSize) {
				throw new \Exception("Invalid encoded data length (max. " . Environment::$messageMaxDataSize	. "): $length.");
			}

			$this->chunks += str_split($encoded, Environment::MESSAGE_MAX_DATA_IN_CHUNK);
		}

		return $this;
	}


	public function chunkCount()
	{
		return count($this->chunks);
	}


	public function allowAddChunk()
	{
		return count($this->chunks) < Environment::MESSAGE_MAX_CHUNKS;
	}


	public function addChunk($chunk)
	{
		if (!$this->allowAddChunk()) {
			throw new \Exception("Cannot add another chunk to message (max. " . Environment::MESSAGE_MAX_CHUNKS . ")");
		}

		$this->chunks[] = (string) $chunk;
		return $this;
	}


	public function shiftFirstChunk()
	{
		return array_shift($this->chunks);
	}


	public function decompile()
	{
		if (!count($this->chunks) || !Environment::isValidCommand($this->chunks[0])) {
			return FALSE;
		}

		$this->command = $this->shiftFirstChunk();

		if (count($this->chunks)) {
			try {
				$encoded = implode('', $this->chunks);
				$data = json_decode(base64_decode($encoded), TRUE);
				$this->data = $data;
				$this->chunks = [];
			} catch (\Exception $e) {
				return FALSE;
			}
		}

		return TRUE;
	}


	public function getCommand()
	{
		return $this->command;
	}


	public function getData()
	{
		return $this->data;
	}
}
