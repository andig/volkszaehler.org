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

use Volkszaehler\Model;
use Volkszaehler\Interpreter\Interpreter;

// http://localhost/vz/htdocs/middleware.php/data/batterycharge.json?debug=1&define=battery&batterycharge=82fb2540-60df-11e2-8a9f-0b9d1e30ccc6&batterydischarge=2a93a9a0-60df-11e2-83cc-2b8029d72006&batterycapacity=10&from=2017-08-01

/**
 * Battery interpreter
 *
 * @author Andreas Goetz <cpuidle@gmx.de>
 * @package default
 */
class BatteryInterpreter extends Interpreter {

	protected $battery;

	public function __construct(Battery $battery, Model\Entity $channel, $function) {
		$this->battery = $battery;
		$this->channel = $channel;
		$this->function = $function;

		$this->chargeLevel = 0;

		$this->capacity = $battery->getParameter('capacity');
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

		// input channels
		$charge = $this->battery->getCoordinatedInterpreter('charge');
		$discharge = $this->battery->getCoordinatedInterpreter('discharge');

		foreach ($this->battery->getTimestampGenerator() as $ts) {
			if (!isset($ts_last)) {
				$this->from = $this->battery->getCoordinatedFrom();
				$ts_last = $this->from;
			}

			$period = $ts - $ts_last;

			// available charge/ discharge power
			$chargePower = $charge->getValueForTimestamp($ts);
			$dischargePower = $discharge->getValueForTimestamp($ts);

			// limit charge/ discharge power
			if (isset($this->maxCharge)) {
				$chargePower = min($chargePower, $this->maxCharge);
			}
			if (isset($this->maxDischarge)) {
				$dischargePower = min($dischargePower, $this->maxDischarge);
			}

			$netPower = $chargePower - $dischargePower;

			// efficiency (symmetric losses)
			$effectiveChargePower = $this->efficiency * $chargePower;
			$chargeDelta = ($effectiveChargePower - $dischargePower / $this->efficiency) * $period / 3.6e6;

			// charge delta limited by min/max levels
			$resultingChargeLevel = max($this->minChargeLevel, min($this->maxChargeLevel, $this->chargeLevel + $chargeDelta));

 			$actualChargeDelta = $resultingChargeLevel - $this->chargeLevel;
			$this->chargeLevel = $resultingChargeLevel;

			// output value for interpreter function
			$value = 0;
			switch ($this->function) {
				case 'charge':
					if ($actualChargeDelta > 0) {
						$value = $netPower;
					}
					break;
				case 'discharge':
					if ($actualChargeDelta < 0) {
						$value = -$netPower;
					}
					break;
				case 'level':
					$value = $this->chargeLevel;
					break;
			}

			$tuple = array($ts, $value, 1);
			$ts_last = $ts;

			$this->updateMinMax($tuple);
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
