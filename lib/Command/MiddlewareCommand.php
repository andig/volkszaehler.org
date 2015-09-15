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

use GuzzleHttp\Client;

use Volkszaehler\Util\UUID;

/**
 * Basic console command
 */
class MiddlewareCommand extends BaseCommand {

	// @todo Upgrade guzzle when PHP 5.5 is available
	const GUZZLE_HTTP_ERROR = 'exceptions';		// Guzzle 6.0: http_errors

	/**
	 * GuzzleHttp\Client
	 */
	protected $client;			// http client

	protected $source;			// source api url
	protected $target;			// target api url

	protected $entities;		// hashmap of source entities

	protected function configure() {
		parent::configure();

		$this
			->addOption('public', 'p', InputOption::VALUE_NONE, 'Include public entities if uuids are specified')
			->addArgument('source', InputArgument::REQUIRED, 'Source middleware url')
			->addArgument('target', InputArgument::OPTIONAL, 'Target middleware url')
			->addArgument('uuid', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'uuid(s)');
	}

	/*
	 * Helper functions
	 */

	protected function readMiddlewareAndUuidOptions() {
		$this->clientFactory();
		$this->entities = array();

		// source middleware and capabilities
		if ($this->source = $this->input->getArgument('source')) {
			// shortcut
			if ($this->source == 'local' || $this->source == 'localhost') {
				$this->source = 'http://localhost/';
			}

			if (false === $this->findMiddleware($this->source)) {
				throw new \RuntimeException('Could not find a volkszaehler middleware at ' . $this->source);
			}
		}

		// target middleware
		$this->target = $this->input->getArgument('target');
		$uuids = $this->input->getArgument('uuid');

		if (UUID::validate($this->target)) {
			// target is optional- this might already be a uuid
			$uuids[] = $this->target;
			$this->target = null;
		}
		elseif ($this->target) {
			// not a uuid
			if (false === $this->findMiddleware($this->target)) {
				throw new \RuntimeException('Could not find a volkszaehler middleware at ' . $this->target);
			}
		}
		// target is optional- use source if not specified
		if (!$this->target) {
			$this->target = $this->source;
			$this->verbose('Target middleware not specified, using source instead');
		}

		// uuids
		if ($uuids) {
			$this->verbose('Reading specified entities from source');

			foreach ($uuids as $uuid) {
				$entity = $this->getChannels($this->source, $uuid);
				$this->entities[$entity['uuid']] = $entity;
			}
		}

		// include public entities
		if (count($uuids) === 0 || $this->input->getOption('public')) {
			$this->verbose('Reading public entities from source');

			$entities = $this->getChannels($this->source);
			$this->entities += array_combine(
				array_column($entities, 'uuid'),
				$entities
			);
		}
	}

	/**
	 * Create http client
	 */
	protected function clientFactory() {
		$this->client = new Client([
			// 'timeout' => 10.0,
			'debug' => $this->output->getVerbosity() >= OutputInterface::VERBOSITY_DEBUG,
			'headers' => ['Accept' => 'application/json'],
		]);

		return $this->client;
	}

	/**
	 * Return json or http errors from middleware call
	 */
	protected function middlewareError($response, &$json) {
		// response is json with exception, return exception message
		if (($json = json_decode($response->getBody(), true)) && isset($json['exception'])) {
			return $json['exception']['message'];
		}

		// otherwise return response body
		if ($response->getStatusCode() !== 200) {
			return (string)$response->getBody();
		}

		return false;
	}

	/**
	 * Check if url can returns middleware response
	 */
	private function isMiddleware($api) {
		$response = $this->client->get($api . 'data.json', [
			self::GUZZLE_HTTP_ERROR => false
		]);

		if ($response->getStatusCode() == 200 && ($json = json_decode($response->getBody()))) {
			return isset($json->version);
		}

		return false;
	}

	/**
	 * Find middleware url for given url. Takes care of redirects not being implemented.
	 */
	protected function findMiddleware(&$api) {
		if (substr($api, -1) !== '/') {
			$api .= '/';
		}

		if (!$this->isMiddleware($api)) {
			$api .= 'middleware.php/';
			return $this->isMiddleware($api);
		}

		return true;
	}

	/**
	 * Get channel definition from middleware
	 */
	protected function getChannels($api, $uuid = null, $allowMiddlewareException = false) {
		$response = $this->client->get($api . 'channel/' . $uuid . '.json', [
			self::GUZZLE_HTTP_ERROR => false
		]);

		if ($error = $this->middlewareError($response, $json)) {
			// channel does not exist
			if ($allowMiddlewareException && 200 == $response->getStatusCode()) {
				return false;
			}

			throw new \RuntimeException('Could not read entity ' . $uuid . "\n" . $error);
		}

		return $uuid == null ? $json['channels'] : $json['entity'];
	}
}

?>
