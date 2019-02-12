<?php
/**
 * @author Andreas Goetz <cpuidle@gmx.de>
 * @copyright Copyright (c) 2011-2018, The volkszaehler.org project
 * @license https://www.gnu.org/licenses/gpl-3.0.txt GNU General Public License version 3
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

namespace Volkszaehler\Interpreter;

use Volkszaehler\Controller;
use Volkszaehler\Model;
use Volkszaehler\Util;
use Volkszaehler\Interpreter\Virtual;
use Doctrine\ORM;
use RR\Shunt;

/**
 * Interpreter for channels of type 'virtual'
 *
 * VirtualInterpreter is able to calculate data on the fly
 * using the provided `rule` and `in1`..`in9` inputs.
 */
class VirtualInterpreter extends Interpreter {

	use Virtual\InterpreterCoordinatorTrait;

	const PRIMARY = 'in1';

	/**
	 * @var Doctrine\ORM\EntityManager
	 */
	protected $em;

	protected $ctx;
	protected $parser;

	protected $count; 			// number of rows
	protected $consumption; 	// in Wms (Watt milliseconds)
	protected $ts_last; 		// previous tuple timestamp

	/**
	 * Constructor
	 *
	 * @param Channel $channel
	 * @param EntityManager $em
	 */
	public function __construct(Model\Channel $channel, ORM\EntityManager $em, $from, $to, $tupleCount = null, $groupBy = null, $options = array()) {
		parent::__construct($channel, $em, $from, $to, $tupleCount, $groupBy, $options);

		$this->em = $em;

		// create parser for rule
		$rule = $channel->getProperty('rule');
		$this->parser = new Shunt\Parser(new Shunt\Scanner($rule));

		// create parser context
		$this->ctx = new Shunt\Context();
		$this->createStaticContextFunctions();
		$this->createDynamicContextFunctions($channel->getPropertiesByRegex('/in\d/'));
	}

	/**
	 * Create static, non-data context functions
	 */
	protected function createStaticContextFunctions() {
		// php function wrappers
		// math functions (see php manual for arguments)
		$this->ctx->def('abs');           //Absolute value
		$this->ctx->def('acos');          //Arc cosine
		$this->ctx->def('acosh');         //Inverse hyperbolic cosine
		$this->ctx->def('asin');          //Arc sine
		$this->ctx->def('asinh');         //Inverse hyperbolic sine
		$this->ctx->def('atan2');         //Arc tangent of two variables
		$this->ctx->def('atan');          //Arc tangent
		$this->ctx->def('atanh');         //Inverse hyperbolic tangent
		$this->ctx->def('base_convert');  //Convert a number between arbitrary bases
		$this->ctx->def('bindec');        //Binary to decimal
		$this->ctx->def('ceil');          //Round fractions up
		$this->ctx->def('cos');           //Cosine
		$this->ctx->def('cosh');          //Hyperbolic cosine
		$this->ctx->def('decbin');        //Decimal to binary
		$this->ctx->def('dechex');        //Decimal to hexadecimal
		$this->ctx->def('decoct');        //Decimal to octal
		$this->ctx->def('deg2rad');       //Converts the number in degrees to the radian equivalent
		$this->ctx->def('exp');           //Calculates the exponent of e
		$this->ctx->def('expm1');         //Returns exp(number) - 1, computed in a way that is accurate even when the value of number is close to zero
		$this->ctx->def('floor');         //Round fractions down
		$this->ctx->def('fmod');          //Returns the floating point remainder (modulo) of the division of the arguments
		$this->ctx->def('getrandmax');    //Show largest possible random value
		$this->ctx->def('hexdec');        //Hexadecimal to decimal
		$this->ctx->def('hypot');         //Calculate the length of the hypotenuse of a right-angle triangle
		$this->ctx->def('intdiv');        //Integer division
		$this->ctx->def('is_finite');     //Finds whether a value is a legal finite number
		$this->ctx->def('is_infinite');   //Finds whether a value is infinite
		$this->ctx->def('is_nan');        //Finds whether a value is not a number
		$this->ctx->def('lcg_value');     //Combined linear congruential generator
		$this->ctx->def('log10');         //Base-10 logarithm
		$this->ctx->def('log1p');         //Returns log(1 + number), computed in a way that is accurate even when the value of number is close to zero
		$this->ctx->def('log');           //Natural logarithm
		$this->ctx->def('max');           //Find highest value
		$this->ctx->def('min');           //Find lowest value
		$this->ctx->def('mt_getrandmax'); //Show largest possible random value
		$this->ctx->def('mt_rand');       //Generate a random value via the Mersenne Twister Random Number Generator
		$this->ctx->def('mt_srand');      //Seeds the Mersenne Twister Random Number Generator
		$this->ctx->def('octdec');        //Octal to decimal
		$this->ctx->def('pi');            //Get value of pi
		$this->ctx->def('pow');           //Exponential expression
		$this->ctx->def('rad2deg');       //Converts the radian number to the equivalent number in degrees
		$this->ctx->def('rand');          //Generate a random integer
		$this->ctx->def('round');         //Rounds a float
		$this->ctx->def('sin');           //Sine
		$this->ctx->def('sinh');          //Hyperbolic sine
		$this->ctx->def('sqrt');          //Square root
		$this->ctx->def('srand');         //Seed the random number generator
		$this->ctx->def('tan');           //Tangent
		$this->ctx->def('tanh');          //Hyperbolic tangent
		
		// non-php mathematical functions
		$this->ctx->def('sgn', function($v) { if ($v == 0) return 0; return ($v > 0) ? 1 : -1; }); // signum
		$this->ctx->def('avg', function() { return (array_sum(func_get_args()) / func_num_args()); }); // avg

		// logical functions
		$this->ctx->def('if', function($if, $then, $else = 0) { return $if ? $then : $else; });
		$this->ctx->def('ifnull', function($if, $then) { return $if ?: $then; });

		// date/time functions
		$this->ctx->def('year', function($ts) { return (int) date('Y', (int) $ts); });
		$this->ctx->def('month', function($ts) { return (int) date('n', (int) $ts); });
		$this->ctx->def('day', function($ts) { return (int) date('d', (int) $ts); });
		$this->ctx->def('hour', function($ts) { return (int) date('H', (int) $ts); });
		$this->ctx->def('minutes', function($ts) { return (int) date('i', (int) $ts); });
		$this->ctx->def('seconds', function($ts) { return (int) date('s', (int) $ts); });
	}

	/**
	 * Create interpreters and parser and assign inputs and functions
	 *
	 * @param Iterable $uuids list of input channel uuids
	 */
	protected function createDynamicContextFunctions($uuids) {
		// assign data functions
		$this->ctx->def('val', array($this, '_val'));	// value
		$this->ctx->def('ts', array($this, '_ts')); 	// timestamp
		$this->ctx->def('prev', array($this, '_prev')); // previous timestamp
		$this->ctx->def('from', array($this, '_from')); // from timestamp
		$this->ctx->def('to', array($this, '_to')); 	// to timestamp

		$this->ctx->def('cons', array($this, '_consumption')); 	// period consumption

		// child interpreter options for calculation consumption
		// at virtual interpreter level
		$options = $this->options;
		if (false !== $idx = array_search('consumption', $options)) {
			$options[$idx] = 'consumptionto';
		}

		$ef = Util\EntityFactory::getInstance($this->em);

		// assign input channel functions
		foreach ($uuids as $key => $value) {
			$this->ctx->def($key, $key, 'string'); // as key constant
			$this->ctx->def($key, function() use ($key) { return $this->_val($key); }); // as value function

			// get chached entity
			$entity = $ef->get($value, true);

			// define named parameters
			$title = preg_replace('/\s*/', '', $entity->getProperty('title'));
			$this->ctx->def($title, $key, 'string'); // as key constant
			$this->ctx->def($title, function() use ($key) { return $this->_val($key); }); // as value function

			$class = $entity->getDefinition()->getInterpreter();
			$interpreter = new $class($entity, $this->em, $this->from, $this->to, $this->tupleCount, $this->groupBy, $options);

			// add interpreter to timestamp coordination
			$this->addCoordinatedInterpreter($key, $interpreter);
		}
	}

	/*
	 * Context functions
	 */

	// get channel timestamp
	public function _ts($key = self::PRIMARY) {
		return $this->ts;
	}

	// get previous channel timestamp
	public function _prev() {
		return $this->ts_last;
	}

	// get channel value
	public function _val($key = self::PRIMARY) {
		return $this->getCoordinatedInterpreter($key)->getValueForTimestamp($this->ts);
	}

	// get channel first timestamp
	public function _from($key = self::PRIMARY) {
		return $this->getCoordinatedInterpreter($key)->getFrom();
	}

	// get channel last timestamp
	public function _to($key = self::PRIMARY) {
		return $this->getCoordinatedInterpreter($key)->getTo();
	}

	// get period consumption
	public function _consumption($value) {
		if (null === $prev = $this->_prev()) {
			throw new \LogicException("_consumption could not determine previous timestamp");
			return 0;
		}

		$period = $this->ts() - $prev;
		$consumption = $value * $period / 3.6e6;
		return $consumption;
	}

	/**
	 * Generate database tuples
	 *
	 * @return \Generator
	 */
	public function getIterator() {
		$this->rowCount = 0;
		$this->ts_last = null;

		foreach ($this->getTimestampGenerator() as $this->ts) {
			if (!isset($this->ts_last)) {
				// create first timestmap as min from interpreters
				$this->ts_last = $this->from = $this->getCoordinatedFrom();
			}

			// calculate
			$value = $this->parser->reduce($this->ctx);

			if (!is_numeric($value)) {
				throw new \Exception("Virtual channel rule must yield numeric value.");
			}

			if ($this->output == self::CONSUMPTION_VALUES) {
				$value *= ($this->ts - $this->ts_last) / 3.6e6;
				$this->consumption += $value;
			}
			else {
				$this->consumption += $value * ($this->ts - $this->ts_last) / 3.6e6;
			}

			$tuple = array($this->ts, $value, 1);
			$this->ts_last = $this->ts;

			$this->updateMinMax($tuple);
			$this->rowCount++;

			yield $tuple;
		}

		$this->to = $this->ts_last;
	}

	/**
	 * Convert raw meter readings
	 */
	public function convertRawTuple($row) {
		return $this->current = $row;
	}

	/*
	 * From/to timestamps delegated to leading interpreter
	 */

	public function getFrom() {
		return $this->from;
	}

	public function getTo() {
		return $this->to;
	}

	/**
	 * Calculates the consumption
	 *
	 * @return float total consumption in Wh
	 */
	public function getConsumption() {
		return $this->channel->getDefinition()->hasConsumption ? $this->consumption : NULL; // convert to Wh
	}

	/**
	 * Get Average
	 *
	 * @return float average
	 */
	public function getAverage() {
		if (!$this->consumption) {
			return 0;
		}

		if ($this->output == self::CONSUMPTION_VALUES) {
			return $this->getConsumption() / $this->rowCount;
		}
		else {
			$delta = $this->getTo() - $this->getFrom();
			return $this->consumption / $delta;
		}
	}

	/**
	 * Return sql grouping expression.
	 *
	 * Override Interpreter->groupExpr
	 *
	 * @author Andreas Götz <cpuidle@gmx.de>
	 * @param string $expression sql parameter
	 * @return string grouped sql expression
	 */
	public static function groupExprSQL($expression) {
		return 'AVG(' . $expression . ')';
	}
}

?>
