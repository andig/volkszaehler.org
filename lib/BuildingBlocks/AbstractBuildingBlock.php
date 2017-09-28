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

use Volkszaehler\BuildingBlocks\DefinableChannel;
use Volkszaehler\BuildingBlocks\BlockInterface;

/**
 * Base class for building blocks
 *
 * @author Andreas Goetz <cpuidle@gmx.de>
 * @package default
 */
abstract class AbstractBuildingBlock implements BlockInterface {

	const SENSOR = 'universalsensor';
	const CONSUMPTION = 'consumptionsensor';

	protected $name;
	protected $parameters;

	public function __construct($name, ParameterBag $parameters) {
		$this->name = $name;
		$this->parameters = $parameters;
	}

	/**
	 * Create channel with properties
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
