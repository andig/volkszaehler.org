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
use Volkszaehler\Interpreter\InterpreterInterface;

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

		$this->blockManager = BlockManager::getInstance();
		$this->setupCoordinator();

		$this->createParameters(array('charge', 'discharge', 'capacity'));

		// output channel
		$channel = $this->channelFactory('virtualsensor');

		foreach (array('charge', 'discharge') as $function) {
			$this->addInput($function);
			$interpreter = new BatteryInterpreter($this, $channel, $function);
			$this->blockManager->add($name . $function, $interpreter);
		}
	}

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

	// public function addProxy($key, $interpreter) {
	// 	$proxy = new Virtual\InterpreterProxy($interpreter);
	// 	$this->interpreters[$key] = $proxy;

	// 	// add timestamp iterator to generator
	// 	$iterator = new Virtual\TimestampIterator($proxy->getIterator());
	// 	$this->timestampGenerator->add($iterator);
	// }

	// public function getProxy($key) {
	// 	return $this->interpreters[$key];
	// }

	protected function createParameters($parameters) {
		foreach ($parameters as $parameter) {
			$parameterName = $this->name . $parameter;

			if (!$this->request->query->has($parameterName)) {
				throw new \Exception('Missing parameter ' . $parameterName . ' for battery');
			}

			$this->$parameterName = $this->request->query->get($parameterName);
		}
	}

	protected function getParameter($parameter) {
		$parameterName = $this->name . $parameter;
		return $this->$parameterName;
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

	public function getIterator() {

	}


	public function getFrom() {
		return 0;
		return $this->from;
	}

	public function getTo() {
		return 0;
		return $this->to;
	}

	/**
	 * Calculates the consumption
	 *
	 * @return float total consumption in Wh
	 */
	public function getConsumption() {
		return 0;
	}

	/**
	 * Get Average
	 *
	 * @return float average
	 */
	public function getAverage() {
		return 0;
	}
}

?>
