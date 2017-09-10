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
use Doctrine\ORM\EntityManager;

use Volkszaehler\Model;
use Volkszaehler\Controller\EntityController;
use Volkszaehler\Interpreter\Virtual\InterpreterCoordinator;

// http://localhost/vz/htdocs/middleware.php/data/batterycharge.json?debug=1&define=battery&batterycharge=82fb2540-60df-11e2-8a9f-0b9d1e30ccc6&batterydischarge=2a93a9a0-60df-11e2-83cc-2b8029d72006&batterycapacity=10

/**
 * Battery block
 *
 * @author Andreas Goetz <cpuidle@gmx.de>
 * @package default
 */
class Battery implements BlockInterface {

	use InterpreterCoordinator;

	public function __construct(Request $request, EntityManager $em, $name) {
		$this->em = $em;
		$this->request = $request;
		$this->name = $name;

		$this->groupBy = $this->request->query->get('group');

		$this->setupCoordinator();

		$this->createParameters(array('charge', 'discharge', 'capacity'));
		$this->createParameters(array('efficiency'), true);

		$channel = $this->channelFactory('virtualconsumption', array('unit' => 'W'));
		foreach (array('charge', 'discharge') as $function) {
			$this->addInput($function);
			$this->addOutput($channel, $function);
		}

		$channel = $this->channelFactory('virtualsensor', array('unit' => 'Wh'));
		$this->addOutput($channel, 'level');
	}

	/**
	 * Create input interpreter
	 */
	protected function addInput($key) {
		$from = $this->request->query->get('from');
		$to = $this->request->query->get('to');
		$tuples = $this->request->query->get('tuples');
		$groupBy = $this->request->query->get('group');
		$options = $this->request->query->get('options');
		$options = array();

		$channel = $this->getParameter($key);

		$entity = EntityController::factory($this->em, $channel, true);
		$class = $entity->getDefinition()->getInterpreter();
		$interpreter = new $class($entity, $this->em, $from, $to, $tuples, $groupBy, $options);

		// create proxy iterator
		$this->addCoordinatedInterpreter($key, $interpreter);
	}

	/**
	 * Create output interpreter
	 */
	protected function addOutput($channel, $function) {
		$interpreter = new BatteryInterpreter($this, $channel, $function);
		BlockManager::getInstance()->add($this->name . $function, $interpreter);
	}

	/**
	 * Create properties from request parameters
	 */
	protected function createParameters($parameters, $optional = false) {
		foreach ($parameters as $parameter) {
			$parameterName = $this->name . $parameter;

			if (!$this->request->query->has($parameterName)) {
				if ($optional) {
					continue;
				}
				throw new \Exception('Missing parameter ' . $parameterName . ' for battery');
			}

			$this->$parameterName = $this->request->query->get($parameterName);
		}
	}

	/**
	 * Get properties for input parameters
	 */
	public function getParameter($parameter, $default = null) {
		$parameterName = $this->name . $parameter;

		if (isset($this->$parameterName)) {
			return $this->$parameterName;
		}

		return $default;
	}

	/**
	 * Create channel dynamically
	 */
	protected function channelFactory($type, $properties = array()) {
		$channel = new Model\Channel($type);

		foreach ($properties as $key => $value) {
			$channel->setProperty($key, $value);
		}

		return $channel;
	}
}

?>
