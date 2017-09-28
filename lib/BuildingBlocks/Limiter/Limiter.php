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

namespace Volkszaehler\BuildingBlocks\Limiter;

use Symfony\Component\HttpFoundation\ParameterBag;
use Doctrine\ORM\EntityManager;

use Volkszaehler\Util\EntityFactory;
use Volkszaehler\BuildingBlocks\AbstractBuildingBlock;
use Volkszaehler\BuildingBlocks\BlockManager;

/**
 * Limiter building block
 *
 * @author Andreas Goetz <cpuidle@gmx.de>
 * @package default
 */
class Limiter extends AbstractBuildingBlock {

	protected $em;
	protected $ef;

	protected $channel;

	public function __construct($name, ParameterBag $parameters) {
		parent::__construct($name, $parameters);

		// required parameters
		foreach (array('input', 'cutoff') as $param) {
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
		foreach (array('output') as $function) {
			$channel = $this->createChannel(self::CONSUMPTION, $function, array(
				'unit' => 'W'
			));
			$this->createOutputInterpreter($channel, $function);

			$blockManager->add($this->name . $function, $channel);

			// entity has one channel only
			$this->channel = $channel;
		}
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

		// input channels
		foreach (array('input') as $function) {
			$interpreter = $this->interpreterForInput($function);
			$this->inputInterpreter = $interpreter;
		}
	}

	/**
	 * Create output interpreter
	 */
	protected function createOutputInterpreter($channel, $function) {
		$interpreter = new LimiterInterpreter($this, $channel, $this->parameters->get('cutoff'));
		$channel->setInterpreter($interpreter);
	}

	public function getInputInterpreter() {
		return $this->inputInterpreter;
	}
}

?>
