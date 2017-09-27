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
use Volkszaehler\Interpreter\Interpreter;
use Volkszaehler\Interpreter\InterpreterInterface;

// http://localhost/vz/htdocs/middleware.php/data/batterycharge.json?debug=1&define=battery&batterycharge=82fb2540-60df-11e2-8a9f-0b9d1e30ccc6&batterydischarge=2a93a9a0-60df-11e2-83cc-2b8029d72006&batterycapacity=10&from=2017-08-01

/**
 * Battery interpreter
 *
 * @author Andreas Goetz <cpuidle@gmx.de>
 * @package default
 */
// class BatteryInterpreter implements \IteratorAggregate, InterpreterInterface {
class BatteryInterpreter extends Interpreter {

	protected $battery;

	public function __construct(Battery $battery, Model\Entity $channel, $function) {
		$this->battery = $battery;
		$this->channel = $channel;
		$this->function = $function;

		$this->chargeLevel = 0;

		$this->capacity = $battery->getParameter('capacity') * 1e3;
		$this->minChargeLevel = $battery->getParameter('minlevel', 0.0) * $this->capacity;
		$this->maxChargeLevel = $battery->getParameter('maxlevel', 1.0) * $this->capacity;

		$this->maxCharge = $battery->getParameter('maxcharge');
		$this->maxDischarge = $battery->getParameter('maxdischarge');

		$this->efficiency = $battery->getParameter('efficiency', 1.0); // 0.95
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

		foreach ($this->battery->getTimestampGenerator() as $ts) {
			if (!isset($ts_last)) {
				$this->from = $this->battery->getCoordinatedFrom();
				$ts_last = $this->from;
			}

			$period = $ts - $ts_last;

			$chargeValue = $charge->getValueForTimestamp($ts);
			$dischargeValue = $discharge->getValueForTimestamp($ts);

			if (isset($this->maxCharge)) {
				$chargeValue = min($chargeValue, $this->maxCharge);
			}
			if (isset($this->maxDischarge)) {
				$dischargeValue = min($dischargeValue, $this->maxDischarge);
			}

			$netValue = $chargeValue - $dischargeValue;
			$netCharge = $netValue * $period / 3.6e6;

			if ($netCharge != 0) {
				$targetCharge = $this->chargeLevel + $netCharge;

				if ($netCharge > 0) {
					// charging
					$effectiveCharge = min($this->maxChargeLevel, $targetCharge);
				}
				elseif ($this->chargeLevel > $this->minChargeLevel) {
					// discharging
					$effectiveCharge = max($this->minChargeLevel, $targetCharge);
				}
				else {
					// no change
					$effectiveCharge = $this->chargeLevel;
				}

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
		return $this->channel;
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
