<?php
/**
 * Timestamp behaviour tests
 *
 * @package Test
 * @author Andreas Götz <cpuidle@gmx.de>
 */

namespace Tests;

class TimestampTest extends Middleware
{
	use InterpreterTrait;

	protected $uuid;

	function testMatchingMinutes() {
		// create
		$uuid = $this->createChannel('Sensor', 'powersensor');

		$tuples = array();
		for ($i=50; $i<=130; $i++) {
			$tuples[] = $i * 60 * 1000;
		}

		foreach ($tuples as $ts) {
			$this->getJson('/data/' . $uuid . '.json', array(
				'operation' => 'add',
				'ts' => $ts,
				'value' => 1
			));
		}

		// get result
		$from = 1*3600*1000;
		$to = 2*3600*1000;

		// chart mode
		$interpreter = $this->createInterpreter($uuid, $from, $to, null, null);
		$tuples = $this->getInterpreterResult($interpreter);

		$this->assertEquals(61, count($tuples));
		$this->assertEquals($from - 60*1000, $interpreter->getFrom());
		$this->assertEquals($from, $tuples[0][0]);
		$this->assertEquals($to, $tuples[count($tuples)-1][0]);

		// export mode
		$interpreter = $this->createInterpreter($uuid, $from, $to, null, null, ['exact']);
		$tuples = $this->getInterpreterResult($interpreter);

		$this->assertEquals(60, count($tuples));
		// $this->assertEquals($from - 60*1000, $interpreter->getFrom());
		$this->assertEquals($from, $interpreter->getFrom());
		// $this->assertEquals($from, $tuples[0][0]);
		$this->assertEquals($from + 60*1000, $tuples[0][0]);
		// $this->assertEquals($to - 60*1000, $tuples[count($tuples)-1][0]);
		$this->assertEquals($to, $tuples[count($tuples)-1][0]);

		// delete
		$url = '/channel/' . $uuid . '.json?operation=delete';
		$this->getJson($url);
	}

	function testNonMatchingMinutes() {
		// create
		$uuid = $this->createChannel('Sensor', 'powersensor');

		$tuples = array();
		for ($i=50; $i<=130; $i++) {
			$tuples[] = ($i * 60 + 30) * 1000;
		}

		foreach ($tuples as $ts) {
			$this->getJson('/data/' . $uuid . '.json', array(
				'operation' => 'add',
				'ts' => $ts,
				'value' => 1
			));
		}

		// get result
		$from = 1*3600*1000;
		$to = 2*3600*1000;

		// chart mode
		$interpreter = $this->createInterpreter($uuid, $from, $to, null, null);
		$tuples = $this->getInterpreterResult($interpreter);

		$this->assertEquals(62, count($tuples));
		$this->assertEquals($from - (60+30)*1000, $interpreter->getFrom());
		$this->assertEquals($from - 30*1000, $tuples[0][0]);
		$this->assertEquals($to + 30*1000, $tuples[count($tuples)-1][0]);

		// export mode
		$interpreter = $this->createInterpreter($uuid, $from, $to, null, null, ['exact']);
		$tuples = $this->getInterpreterResult($interpreter);

		$this->assertEquals(60, count($tuples));
		$this->assertEquals($from - 30*1000, $interpreter->getFrom());
		$this->assertEquals($from + 30*1000, $tuples[0][0]);
		$this->assertEquals($to - 30*1000, $tuples[count($tuples)-1][0]);

		// delete
		$url = '/channel/' . $uuid . '.json?operation=delete';
		$this->getJson($url);
	}
}

?>
