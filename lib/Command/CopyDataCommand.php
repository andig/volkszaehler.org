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

namespace Volkszaehler\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Component\Console\Helper\ProgressBar;

use GuzzleHttp\Exception\ClientException;
// @todo Upgrade guzzle when PHP 5.5 is available
// use GuzzleHttp\Psr7\StreamWrapper;			// Guzzle 6.0
use GuzzleHttp\Stream\GuzzleStreamWrapper;		// Guzzle 5.3

use JsonStreamingParser\Parser;

use Volkszaehler\Util\JsonStreamListener;
use Volkszaehler\Controller\DataController;

/**
 * Middleware data copy command
 */
class CopyDataCommand extends MiddlewareCommand {

	protected $tupleBuffer;		// tuples for transfer

	protected function configure() {
		parent::configure();

		$this->setName('copy')
			->setDescription('Copy channel data between middlewares')
			->addOption('duplicate', 'd', InputOption::VALUE_NONE, 'Skip duplicate date on copy')
			->addOption('no-validate', 'n', InputOption::VALUE_NONE, 'Skip validating that channel definitions are in sync')
			->addOption('clone', 'c', InputOption::VALUE_REQUIRED, 'Clone channel data on source middleware, i.e. create a copy in a second channel');
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		parent::execute($input, $output);

		// get source/ target/ uuid
		$this->readMiddlewareAndUuidOptions();

		// middleware capabilites
		$this->verbose('Getting middleware capabilities');
		$this->database = json_decode($this->client->get($this->source . 'capabilities/database.json')->getBody())->capabilities->database;

		// validate and sync entity definitions
		if (!$input->getOption('no-validate')) {
			// @todo check reuse of SyncEntityCommand implementation
			foreach ($this->entities as $uuid => $source_entity) {
				$this->verbose('Validating channel ' . $uuid . ' (' . $source_entity['title'] . ')');
				$target_entity = $this->getChannels($this->target, $uuid, true);

				if (false == $target_entity || !SyncEntityCommand::compareEntities($source_entity, $target_entity)) {
					throw new \RuntimeException(
						'Channel ' . $uuid . ' (' . $source_entity['title'] . ') not in sync - aborting' . "\n" .
						'Skip validation using the --no-validate option or run `vzcopy sync` first.'
					);
				}
			}
		}

		// copy data
		$this->verbose('Copying entity data from source to target');
		if ($input->getOption('clone')) {
			if (count($this->entities) !== 1) {
				throw new \RuntimeException('Can only clone a single channel at a time');
			}
		}
		else {
			foreach ($this->entities as $uuid => $entity) {
				$this->output->writeln('Copying channel '. $uuid . ' (' . $entity['title'] . ')');
				$this->copyDataForEntity($entity, $this->source, $this->target);
			}
		}
	}

	/*
	 * Helper functions
	 */

	/**
	 * Check if middleware aggregation is enabled and populated
	 */
	private function useAggregation() {
		return $this->database->aggregation_enabled && $this->database->aggregation_rows;
	}

	/**
	 * Copy data from source to destination
	 */
	private function copyDataForEntity($entity, $source, $target) {
		$this->uuid = $entity['uuid'];
		$contextUri = 'data/' . $this->uuid . '.json';

		// get head of copy target
		$json = json_decode($this->client->get($target . $contextUri, [
			'query' => ['from' => 'now']
		])->getBody());
		$this->last_ts = isset($json->data->tuples) && count($json->data->tuples) ? $json->data->tuples[0][0] : 0;

		// get head of copy source
		$json = json_decode($this->client->get($source . $contextUri, [
			'query' => ['from' => 'now']
		])->getBody());
		$current_ts = isset($json->data->tuples) && count($json->data->tuples) ? $json->data->tuples[0][0] : 0;

		if ($this->last_ts == $current_ts) {
			$op = 'en par with';
		}
		elseif ($this->last_ts > $current_ts) {
			$op = 'ahead of';
		}
		else {
			$op = 'behind';
		}

		$this->debug(sprintf('Target [%.0f] %s source [%.0f]', $this->last_ts, $op, $current_ts));

		// compare head timestamps - data found?
		if ($this->last_ts == $current_ts) {
			$this->verbose('No updates - skipping.');
			return;
		}
		elseif ($this->last_ts > $current_ts) {
			$this->output->writeln("Target is ahead of source - aborting.\nConsider using the --duplicate option.");
			return;
		}

		// get number of tuples to copy
		$count = null;
		if ($this->database->aggregation_enabled && $this->database->aggregation_rows) {
			$json = json_decode($this->client->get($source . $contextUri, [
				'query' => [
					'from' => $this->last_ts+1,
					'to' => 'now',
					'tuples' => 1,
					'options' => 'raw'
				]
			])->getBody());
			$count = isset($json->data->tuples) && count($json->data->tuples) ? $json->data->tuples[0][2] : null;
			$this->verbose($count . ' tuple(s) to transfer');
		}

		$this->tupleBuffer = array();
		$this->progress = new ProgressBar($this->output, $count);
		$this->progress->start();

		// select entire source data for copying (streamed response, no memory overflow)
		$stream = $this->client->get($source . $contextUri, [
			'query' => [
				'from' => $this->last_ts+1,
				'to' => 'now',
				'options' => 'raw'
			]
		])->getBody();

		// wrap StreamInterface with native stream
		// @todo Upgrade guzzle when PHP 5.5 is available
		// $parser = new Parser(StreamWrapper::getResource($stream), new JsonStreamListener($this)); 	// Guzzle 6.0
		$parser = new Parser(GuzzleStreamWrapper::getResource($stream), new JsonStreamListener($this));	// Guzzle 5.3
		$parser->parse();

		// transfer remaining tuples
		$this->transferBufferedTuples();
		$this->progress->finish();
		$this->output->writeln('');
	}

	/**
	 * Transfer tuple buffer to target middleware
	 */
	private function transferBufferedTuples() {
		if ($count = sizeof($this->tupleBuffer)) {
			$response = $this->client->post($this->target . 'data/' . $this->uuid . '.json', [
				self::GUZZLE_HTTP_ERROR => false,
				'query' => $this->input->getOption('duplicate') ? 'options=' . DataController::OPT_SKIP_DUPLICATES : '',
				'json' => $this->tupleBuffer
			]);

			if ($error = $this->middlewareError($response, $json)) {
				throw new \RuntimeException("Could update channel data\n" . $error);
			}

			$this->progress->advance($count);
			$this->tupleBuffer = array();
		}
	}

	/**
	 * Callback from streaming json parser
	 */
	public function onJsonTuple($ts, $val) {
		// skip first tuple which already exists at target, this is due to Interpreter logic
		if ($ts == $this->last_ts) {
			return;
		}

		$this->tupleBuffer[] = [$ts, $val];

		if (sizeof($this->tupleBuffer) >= 1024) {
			$this->transferBufferedTuples();
		}
	}
}

?>
