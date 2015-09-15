<?php
/**
 * @copyright Copyright (c) 2015, The volkszaehler.org project
 * @package default
 * @author Andreas Goetz <cpuidle@gmx.de>
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

namespace Volkszaehler\Util;

use JsonStreamingParser\Listener;

/**
 * Listener class for parsing streamed json responses into [timestamp,value] tuples
 */
class JsonStreamListener implements Listener {

	const TUPLES_OBJECT = 1;
	const TUPLES_OUTER_ARRAY = 2;
	const TUPLES_INNER_ARRAY = 3;
	const TUPLES_VALUE = 4;
	const TUPLES_NO_REPORT = 5;

	private $callback;

	private $tuples;			// parser state machine
	private $ts;				// tuple timestamp

	public function __construct($callback) {
		$this->callback = $callback;
	}

	public function onDocumentStart() {
	}

	public function onDocumentEnd() {
	}

	public function onObjectStart() {
	}

	public function onObjectEnd() {
	}

	public function onArrayStart() {
		if ($this->tuples == self::TUPLES_OBJECT || $this->tuples == self::TUPLES_OUTER_ARRAY) {
			$this->tuples++;
		}
	}

	public function onArrayEnd() {
		if ($this->tuples) {
			$this->tuples = min(self::TUPLES_OUTER_ARRAY, $this->tuples-1);
		}
	}

	// Key will always be a string
	public function key($key) {
		if ($key == 'tuples') {
			$this->tuples = self::TUPLES_OBJECT;
		}
	}

	// Note that value may be a string, integer, boolean, array, etc.
	public function value($value) {
		if ($this->tuples == self::TUPLES_INNER_ARRAY) {
			$this->tuples = self::TUPLES_VALUE;
			$this->ts = $value;
		}
		elseif ($this->tuples == self::TUPLES_VALUE) {
			$this->tuples = self::TUPLES_NO_REPORT;

			// notify tuple found
			$this->callback->onJsonTuple($this->ts, $value);
		}
	}
}
