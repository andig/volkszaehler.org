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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ParameterBag;
use Doctrine\ORM\EntityManager;

use Volkszaehler\Model;
use Volkszaehler\Util\EntityFactory;
use Volkszaehler\Interpreter\Virtual\InterpreterCoordinatorTrait;

// http://localhost/vz/htdocs/middleware.php/data/batterycharge.json?debug=1&define=battery&batterycharge=82fb2540-60df-11e2-8a9f-0b9d1e30ccc6&batterydischarge=2a93a9a0-60df-11e2-83cc-2b8029d72006&batterycapacity=10

/**
 * Battery block
 *
 * @author Andreas Goetz <cpuidle@gmx.de>
 * @package default
 */
class Battery implements BlockInterface {

	use InterpreterCoordinatorTrait;

	const SENSOR = 'universalsensor';
	const CONSUMPTION = 'consumptionsensor';
	// const SENSOR = 'virtualsensor';
	// const CONSUMPTION = 'virtualconsumption';

	protected $em;
	protected $ef;

	protected $name;
	protected $parameters;

	public function __construct($name, ParameterBag $parameters) {
		$this->name = $name;
		$this->parameters = $parameters;

		// required parameters
		foreach (array('charge', 'discharge', 'capacity') as $param) {
			if (!$parameters->has($param)) {
				throw new \Exception('Missing parameter ' . $param . ' for ' . $name);
			}
		}
	}

	/**
	 * Add entities to entity manager
	 */
	public function createEntities(BlockManager $blockManager) {
		$friendlyName = ucfirst($this->name);

		// output
		$group = new DefinableGroup('group', $this->name);
		$group->setProperty('title', $friendlyName);

		foreach (array('charge', 'discharge') as $function) {
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
	 * Create input and output interpreters
	 * Requires access to the request
	 */
	public function createInterpreters(EntityManager $em, ParameterBag $parameters) {
		if (isset($this->em)) {
			return;
		}

		$this->parameters->add($parameters->all());
		$this->em = $em;
		$this->ef = EntityFactory::getInstance($em);

		// InterpreterCoordinatorTrait hack
		$this->groupBy = $this->parameters->get('group');

		// input
		foreach (array('charge', 'discharge') as $function) {
			$channel = $this->createChannel(self::CONSUMPTION, $function, array(
				'unit' => 'W'
			));

			$this->addInput($function);
		}
	}

	/**
	 * Create channel dynamically
	 */
	protected function createChannel($type, $function, $properties = array()) {
		$shortName = $this->name . $function;
		$channel = new DefinableEntity($type, $shortName, $this);
		$channel->setProperty('title', ucwords($this->name .' '. $function));

		foreach ($properties as $key => $value) {
			$channel->setProperty($key, $value);
		}

		return $channel;
	}

	/**
	 * Create input interpreter
	 */
	protected function addInput($key) {
		$from = $this->parameters->get('from');
		$to = $this->parameters->get('to');
		$tuples = $this->parameters->get('tuples');
		$groupBy = $this->parameters->get('group');
		$options = (array) $this->parameters->get('options');

		$channel = $this->getParameter($key);

		$entity = $this->ef->get($channel, true);
		$class = $entity->getDefinition()->getInterpreter();
		$interpreter = new $class($entity, $this->em, $from, $to, $tuples, $groupBy, $options);

		// create proxy iterator
		$this->addCoordinatedInterpreter($key, $interpreter);
	}

	/**
	 * Create output interpreter
	 */
	protected function createOutputInterpreter($channel, $function) {
		$interpreter = new BatteryInterpreter($this, $channel, $function);
		$channel->setInterpreter($interpreter);
	}

	/**
	 * Get properties for input parameters
	 */
	public function getParameter($parameter, $default = null) {
		if ($this->parameters->has($parameter)) {
			return $this->parameters->get($parameter);
		}
		return $default;
	}
}

?>
