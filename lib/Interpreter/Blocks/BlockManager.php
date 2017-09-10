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

namespace Volkszaehler\Interpreter\Blocks;

use Volkszaehler\Model\Entity;
use Volkszaehler\Interpreter\Interpreter;

/**
 * Block manager
 *
 * @author Andreas Goetz <cpuidle@gmx.de>
 * @package default
 */
class BlockManager {

	protected $entities;

	private static $instance;

	public static function getInstance() {
		if (!isset(self::$instance)) {
			self::$instance = new BlockManager();
		}

		return self::$instance;
	}

	protected function __construct() {
		$this->entities = array();
	}

	private function __clone() {
	}

	private function __wakeup() {
	}

	public function has($name) {
		return isset($this->entities[$name]);
	}

	public function get($name) {
		if (!$this->has($name)) {
			throw new \Exception('Block entity ' . $name . ' doesn\'t exist');
		}

		return $this->entities[$name];
	}

	public function add($name, /*Interpreter*/ $entity) {
		if (isset($this->entities[$name])) {
			throw new \Exception('Block entity ' . $name . ' already defined');
		}

		$this->entities[$name] = $entity;
	}
}

?>
