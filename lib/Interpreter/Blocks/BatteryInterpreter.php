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

use Volkszaehler\Model;
use Volkszaehler\Interpreter\InterpreterInterface;

/**
 * Battery interpreter
 *
 * @author Andreas Goetz <cpuidle@gmx.de>
 * @package default
 */
class BatteryInterpreter implements \IteratorAggregate, InterpreterInterface {

	protected $battery;

	public function __construct(Battery $battery, Model\Entity $entity, $function) {
		$this->battery = $battery;
		$this->entity = $entity;
		$this->function = $function;

		$this->capacity = 10;
		$this->efficiency = 100;
		$this->chargeLevel = 0;
		$this->chargeMinLevel = 0.5;
		$this->chargeMaxLevel = $this->capacity;
	}

	/**
	 * Generate database tuples
	 *
	 * @return \Generator
	 */
	public function getIterator() {
		$this->rowCount = 0;
		$ts_last = null;

		$charge = $this->battery->getCoordinatedInterpreter('charge');
		$discharge = $this->battery->getCoordinatedInterpreter('discharge');

		foreach ($this->battery->getTimestampCoordinator() as $ts) {
			if (!isset($ts_last)) {
				$this->from = $this->battery->getCoordinatedFrom();
				$ts_last = $this->from;
			}

			$period = $ts - $ts_last;

			$chargeValue = $charge->getValueForTimestamp($ts);
			$dischargeValue = $discharge->getValueForTimestamp($ts);

			$netValue = $chargeValue - $dischargeValue;
			$netCharge = $netValue * $period / 3.6e6;

			if ($netCharge > 0) {
				$targetCharge = $this->chargeLevel + $netCharge;
				$effectiveCharge = max($this->chargeMinLevel, min($this->chargeMaxLevel, $targetCharge));

				// partial charge/discharge
				if ($targetCharge != $effectiveCharge) {
					$deltaCharge = $effectiveCharge - $this->chargeLevel;
					$deltaPercent = $deltaCharge / $netCharge;

					$netCharge *= $deltaPercent;
					$netValue *= $deltaPercent;
				}

				$this->chargeLevel = $effectiveCharge;
			}

			$value = 0;
			switch ($this->function) {
				case 'charge':
					if ($netCharge > 0) {
						$value = $netValue;
					}
					break;
				case 'discharge':
					if ($netCharge < 0) {
						$value = -$netValue;
					}
					break;
				case 'net':
					$value = $netValue;
					break;
				case 'level':
					$value = $this->chargeLevel;
					break;
			}

			$tuple = array($ts, $value, 1);
			$ts_last = $ts;

			// $this->updateMinMax($tuple);
			$this->rowCount++;

			yield $tuple;
		}

		$this->to = $ts_last;
	}

	/*
	 * InterpreterInterface
	 */

	public function getEntity() {
		return $this->entity;
	}

	public function convertRawTuple($row) {

	}

	public function getRowCount() {
		$this->rowCount;
	}

	public function getFrom() {
		return $this->from;
	}

	public function getTo() {
		return $this->to;
	}

	public function getMin() {

	}

	public function getMax() {

	}

	public function getConsumption() {

	}

	public function getAverage() {

	}
}

?>
