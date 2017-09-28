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

use Volkszaehler\Model;
use Volkszaehler\Interpreter\Interpreter;

// http://localhost/vz/htdocs/middleware.php/data/batterycharge.json?debug=1&define=battery&batterycharge=82fb2540-60df-11e2-8a9f-0b9d1e30ccc6&batterydischarge=2a93a9a0-60df-11e2-83cc-2b8029d72006&batterycapacity=10&from=2017-08-01

/**
 * Battery interpreter
 *
 * @author Andreas Goetz <cpuidle@gmx.de>
 * @package default
 */
class LimiterInterpreter extends Interpreter {

	protected $limiter;
	protected $channel;
	protected $cutoff;
	protected $consumption;

	public function __construct(Limiter $limiter, Model\Entity $channel, $cutoff) {
		$this->limiter = $limiter;
		$this->channel = $channel;
		$this->cutoff = $cutoff;
	}

	/**
	 * Generate database tuples
	 *
	 * @return \Generator
	 */
	public function getIterator() {
		$this->rowCount = 0;
		$ts_last = null;

		foreach ($this->limiter->getInputInterpreter() as $tuple) {
			$ts = $tuple[0];

			if (!isset($ts_last)) {
				$this->from = $this->channel->getInterpreter()->getFrom();
				$ts_last = $this->from;
			}

			$tuple[1] = min($this->cutoff, $tuple[1]);

			$period = $ts - $ts_last;
			$this->consumption += $tuple[1] * $period;
			$ts_last = $ts;

			$this->updateMinMax($tuple);
			$this->rowCount += $tuple[2];

			yield $tuple;
		}

		$this->to = $ts_last;
	}

	/*
	 * InterpreterInterface
	 */

	public function convertRawTuple($row) { }

	public function getEntity() {
		return $this->channel;
	}

	public function getRowCount() {
		return $this->rowCount;
	}

	public function getFrom() {
		return $this->from;
	}

	public function getTo() {
		return $this->to;
	}

	public function getConsumption() {
		return ($this->consumption) ? $this->consumption / 3.6e6 : NULL;
	}

	public function getAverage() {
		$delta = $this->getTo() - $this->getFrom();
		return $this->consumption / $delta;
	}
}

?>
