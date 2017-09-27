<?php
/**
 * @copyright Copyright (c) 2017, The volkszaehler.org project
 * @package default
 * @license http://www.opensource.org/licenses/gpl-license.php GNU Public License
 */
/*
 * This file is part of volkzaehler.org
 *
 * volkzaehler.org is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * volkzaehler.org is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with volkszaehler.org. If not, see <http://www.gnu.org/licenses/>.
 */

namespace Volkszaehler\BuildingBlocks;

use Volkszaehler\Util\UUID;
use Volkszaehler\Model\Channel;
use Volkszaehler\Interpreter\Interpreter;

/**
 * Pre-defined, non-persistent entity type
 *
 * @author Andreas Goetz <cpuidle@gmx.de>
 * @package default
 */
class DefinableChannel extends Channel {

	/**
	 * @var Volkszaehler\Interpreter\Blocks\BlockInterface
	 */
	protected $block;

	/**
	 * @var Volkszaehler\Interpreter\Interpreter
	 */
	protected $interpreter;

	/**
	 * Constructor
	 *
	 * @param string $type
	 */
	public function __construct($type, $shortName, BlockInterface $block) {
		parent::__construct($type);

		$this->block = $block;
		$this->uuid = (string) UUID::mint(UUID::MD5, $shortName, UUID::nsOID);
	}

	/*
	 * Setter & getter
	 */

	public function getBlock() {
		return $this->block;
	}

	public function setInterpreter(Interpreter $interpreter) {
		if (isset($this->interpreter)) {
			throw new \Exception('Block entity already initialized');
		}

		return $this->interpreter = $interpreter;
	}

	public function getInterpreter() {
		if (!isset($this->interpreter)) {
			throw new \Exception('Block entity not initialized');
		}

		return $this->interpreter;
	}
}

?>
