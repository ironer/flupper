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
	/** @var ReactorMessenger */
	public $messenger;

	/** @var React\Socket\Connection */
	public $connection;

	public $data = ['greet' => FALSE, 'close' => FALSE, 'user' => FALSE, 'address' => FALSE];


	public function __construct(React\Socket\Connection $connection)
	{
		$this->connection = $connection;
		$this->messenger = new RootMessenger($connection);
		$this->data['address'] = $this->connection->getRemoteAddress();
	}


	public function __sleep()
	{
		return ['data'];
	}


	public function __destruct()
	{
		unset($this->messenger);
		unset($this->connection);
	}
}
