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
 * Class for storing clients' connections to react server.
 * @author Stefan Fiedler
 */
class ReactorClient extends Nette\Object
{
	/** @var React\Socket\Connection */
	public $connection;

	public $data = ['greet' => FALSE, 'close' => FALSE, 'user' => FALSE];

	public function __construct(React\Socket\Connection $connection)
	{
		$this->connection = $connection;
	}
}
