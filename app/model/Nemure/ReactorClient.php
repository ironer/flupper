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
 * Class for storing clients' connections to reactor server.
 * @author Stefan Fiedler
 */
class ReactorClient extends Nette\Object
{
	/** @var React\Socket\Connection */
	public $connection;

	public $data = ['greet' => FALSE, 'close' => FALSE, 'user' => FALSE, 'address' => FALSE];


	public function __construct(React\Socket\Connection $connection)
	{
		$this->connection = $connection;
		$this->data['address'] = $this->connection->getRemoteAddress();
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


	public function __sleep()
	{
		return ['data'];
	}
}
