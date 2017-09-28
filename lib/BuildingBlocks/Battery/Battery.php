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

namespace Volkszaehler\BuildingBlocks\Battery;

use Symfony\Component\HttpFoundation\ParameterBag;
use Doctrine\ORM\EntityManager;

use Volkszaehler\Model\Entity;
use Volkszaehler\Util\EntityFactory;
use Volkszaehler\Interpreter\Virtual\InterpreterCoordinatorTrait;
use Volkszaehler\BuildingBlocks\DefinableGroup;
use Volkszaehler\BuildingBlocks\DefinableChannel;
use Volkszaehler\BuildingBlocks\AbstractBuildingBlock;
use Volkszaehler\BuildingBlocks\BlockInterface;
use Volkszaehler\BuildingBlocks\BlockManager;

/**
 * Battery building block
 *
 * @author Andreas Goetz <cpuidle@gmx.de>
 * @package default
 */
class Battery extends AbstractBuildingBlock {

	use InterpreterCoordinatorTrait;

	protected $em;
	protected $ef;

	public function __construct($name, ParameterBag $parameters) {
		parent::__construct($name, $parameters);

		// required parameters
		foreach (array('charge', 'discharge', 'capacity') as $param) {
			if (!$parameters->has($param)) {
				throw new \Exception('Missing parameter ' . $param . ' for ' . $name);
			}
		}
	}

	/**
	 * Create entities associated with block type
	 * and add entities to block manager for retrieval
	 */
	public function createEntities(BlockManager $blockManager) {
		$friendlyName = ucfirst($this->name);

		// output
		$group = new DefinableGroup('group', $this->name);
		$group->setProperty('title', $friendlyName);

		foreach (array('charge', 'discharge', 'not used', 'not delivered') as $function) {
			$channel = $this->createChannel(self::CONSUMPTION, $function, array(
				'unit' => 'W'
			));
			$this->createOutputInterpreter($channel, $function);

			$blockManager->add($this->name . $function, $channel);
			$group->addChild($channel);
		}

		$function = 'level';
		$channel = $this->createChannel(self::SENSOR, $function, array(
			'unit' => 'Wh'
		));
		$this->createOutputInterpreter($channel, $function);

		$blockManager->add($this->name . $function, $channel);
		$group->addChild($channel);

		$blockManager->add($this->name, $group);
	}

	/**
	 * Create input and output interpreters for data retrieval
	 */
	public function createInterpreters(EntityManager $em, ParameterBag $parameters) {
		if (isset($this->em)) {
			return;
		}

		$this->em = $em;
		$this->ef = EntityFactory::getInstance($em);
		$this->parameters->add($parameters->all());

		// InterpreterCoordinatorTrait hack
		$this->groupBy = $this->parameters->get('group');

		// input channels
		foreach (array('charge', 'discharge') as $function) {
			$interpreter = $this->interpreterForInput($function);
			$this->addCoordinatedInterpreter($function, $interpreter);
		}
	}

	/**
	 * Create channel dynamically
	 */
	protected function createChannel($type, $function, $properties = array()) {
		$shortName = $this->name . $function;
		$channel = new DefinableChannel($type, $shortName, $this);
		$channel->setProperty('title', ucwords($function));

		// required properties
		foreach ($properties as $key => $value) {
			$channel->setProperty($key, $value);
		}

		// user-defined properties
		if ($this->parameters->has('properties')) {
			$properties = $this->parameters->get('properties');
			if (isset($properties[$function])) {
				foreach ($properties[$function] as $key => $value) {
					$channel->setProperty($key, $value);
				}
			}
		}

		return $channel;
	}

	/**
	 * Create input interpreter
	 */
	protected function interpreterForInput($function) {
		$uuid = $this->getParameter($function);
		$channel = $this->ef->get($uuid, true);
		$interpreter = $this->ef->createInterpreter($channel, $this->parameters);
		return $interpreter;
	}

	/**
	 * Create output interpreter
	 */
	protected function createOutputInterpreter($channel, $function) {
		$interpreter = new BatteryInterpreter($this, $channel, $function);
		$channel->setInterpreter($interpreter);
	}

	/**
	 * Get block parameters
	 */
	public function getParameter($parameter, $default = null) {
		if ($this->parameters->has($parameter)) {
			return $this->parameters->get($parameter);
		}
		return $default;
	}
}

?>
