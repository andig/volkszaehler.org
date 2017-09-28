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

use Symfony\Component\HttpFoundation\ParameterBag;

use Volkszaehler\Model\Entity;
use Volkszaehler\Util;

/**
 * Block manager
 *
 * Block manager is responsible for loading and maintaining
 * inventory of block entities
 *
 * @author Andreas Goetz <cpuidle@gmx.de>
 * @package default
 */
class BlockManager {

	private static $instance;

	protected $entities;

	/**
	 * Get singleton instance
	 */
	public static function getInstance() {
		if (!isset(self::$instance)) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor
	 */
	protected function __construct() {
		$this->entities = array();
		$this->loadBlockDefinitions();
	}

	/**
	 * Load block definitions
	 */
	protected function loadBlockDefinitions() {
		if (!file_exists($file = VZ_DIR . '/etc/devices.json')) {
			return;
		}

		$json = Util\JSON::decode(file_get_contents($file), true);

		foreach ($json as $name => $definition) {
			$type = $definition['type'] ?? $name;

			$class = __NAMESPACE__ .'\\'. ucfirst($type) .'\\'. ucfirst($type);
			if (!class_exists($class)) {
				throw new \Exception('Invalid block definition ' . $type);
			}

			$block = new $class($name, new ParameterBag($definition));
			$block->createEntities($this);
		}
	}

	/*
	 * Entities
	 */

	public function add($name, Entity $entity) {
		if (isset($this->entities[$name])) {
			throw new \Exception('Block ' . $name . ' already defined');
		}
		$this->entities[$name] = $entity;
	}

	public function has($name) {
		try {
			$this->get($name);
			return true;
		}
		catch (\Exception $e) {
			return false;
		}
	}

	public function get($name) {
		foreach ($this->entities as $entity) {
			if ($entity->getUuid() == $name)
				return $entity;
			if ($entity->getProperty('title') == $name &&
				$entity->hasProperty('public') &&
				$entity->getProperty('public'))
				return $entity;
		}

		// name
		if (isset($this->entities[$name])) {
			return $this->entities[$name];
		}

		throw new \Exception('Block interpreter ' . $name . ' doesn\'t exist');
	}

	/*
	 * Singleton
	 */

	private function __clone() { }
}

?>
