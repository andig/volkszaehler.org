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
use GuzzleHttp\Psr7\StreamWrapper;

use Volkszaehler\Controller\DataController;

use Volkszaehler\Util\JsonStreamListener;
use Volkszaehler\Util\JsonStreamingParser\Parser;

/**
 * Middleware entity definition synchronization command
 */
class SyncEntityCommand extends MiddlewareCommand {

	protected function configure() {
		parent::configure();

		$this->setName('sync')
			->setDescription('Sync entity definitions between middlewares')
			->addOption('apply', 'a', InputOption::VALUE_NONE, 'Apply delta changes on target entities');
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		parent::execute($input, $output);

		// get source/ target/ uuid
		$this->readMiddlewareAndUuidOptions();

		// validate and sync entity definitions
		if (false == $this->validateAndSyncEntities($input->getOption('apply'))) {
			$this->output->writeln('Entities not in sync. Consider applying delta using the --apply option.');
		}
	}

	/*
	 * Helper functions
	 */

	/**
	 * Validate if source and target entity definitions are in sync and update target definition if not
	 *
	 * @return boolean true if channel definitions are in sync, false if not
	 */
	public function validateAndSyncEntities($apply = false) {
		$this->verbose('Validating that entity definitions are in sync');
		$result = true;

		foreach ($this->entities as $uuid => $source_entity) {
			$this->verbose('Validating channel ' . $uuid . ' (' . $source_entity['title'] . ')');

			$target_entity = $this->getChannels($this->target, $uuid.'XX', true);

			// entity does not exist at target
			if (false === $target_entity) {
				$this->output->writeln('Channel ' . $uuid . ' (' . $source_entity['title'] . ') does not exist at target');

				if ($apply) {
					$this->createChannel($this->target, $source_entity);
				}
				else {
					$result = false;	// channels remain unsynced
				}
			}
			// entity does exist at target
			elseif (!self::compareEntities($source_entity, $target_entity)) {
				$this->output->writeln('Channel ' . $uuid . ' (' . $source_entity['title'] . ') not in sync');

				if ($apply) {
					$this->syncEntities($this->target, $source_entity, $target_entity);
				}
				else {
					$result = false;	// channels remain unsynced
				}
			}
		}

		return $result;
	}

	/**
	 * Compare entity definitions
	 *
	 * @return boolean true on equal, false on unequal
	 */
	public static function compareEntities($a, $b) {
		// check source properties exist and are equal with target properties
		foreach ($a as $key => $val) {
			if (!isset($b[$key]) || $b[$key] !== $val) {
				return false;
			}
		}

		// check target does not have additional properties
		foreach ($b as $key => $val) {
			if (!isset($a[$key])) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Calculate definition delta
	 */
	private function getSyncDelta($source_entity, $target_entity) {
		// populate empty properties list to delete non-existing properties
		$properties = array_map(function($a) {
			return '';
		}, $target_entity);

		// overwrite with and add source properties
		$properties = array_merge($properties, $source_entity);

		// remove non-updatable properties
		unset($properties['uuid']);
		unset($properties['type']);

		return $properties;
	}

	/**
	 * Synchronize source with target
	 */
	private function syncEntities($api, $source_entity, $target_entity) {
		$uuid = $source_entity['uuid'];
		$this->verbose('Synchronizing entity ' . $uuid . ' (' . $source_entity['title'] . ')');

		// cannot update type
		if ($source_entity['type'] !== $target_entity['type']) {
			throw new \RuntimeException('Entity type mismatch ' . $uuid . ' (' . $source_entity['title'] . ')');
		}

		$properties = $this->getSyncDelta($source_entity, $target_entity);

		// update entity
		$response = $this->client->pull($api . 'channel/' . $uuid . '.json', [
			self::GUZZLE_HTTP_ERROR => false,
			'query' => $properties
		]);

		if ($error = $this->middlewareError($response, $json)) {
			throw new \RuntimeException('Could not update channel ' . $uuid . ' (' . $source_entity['title'] . ")\n" . $error);
		}
	}

	/**
	 * Create new channel
	 */
	private function createChannel($api, $properties) {
		$uuid = isset($properties['uuid']) ? $properties['uuid'] : '';
		$this->verbose('Creating entity ' . $uuid . ' (' . $properties['title'] . ')');

		$response = $this->client->post($api . 'channel.json', [
			self::GUZZLE_HTTP_ERROR => false,
			'query' => $properties
		]);

		if ($error = $this->middlewareError($response, $json)) {
			throw new \RuntimeException('Could not create channel ' . $uuid . ' (' . $properties['title'] . ")\n" . $error);
		}
	}
}

?>
