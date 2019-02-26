<?php
/**
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

namespace Volkszaehler\Controller;

use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMException;

use Volkszaehler\Model;
use Volkszaehler\Util;
use Volkszaehler\Interpreter\Interpreter;
use Volkszaehler\View\View;

use Andig\GrafanaSerializer\Model as GrafanaModel;
use Andig\GrafanaSerializer\Request as GrafanaRequest;

use Doctrine\Common\Annotations\AnnotationRegistry;
use JMS\Serializer\SerializerBuilder;
use GuzzleHttp\Client;

/**
 * Grafana controller
 *
 * @author Andreas Götz <cpuidle@gmx.de>
 */
class GrafanaController extends Controller {

	public function __construct(Request $request, EntityManager $em, View $view) {
		parent::__construct($request, $em, $view);
		$this->options = (array) strtolower($this->getParameters()->get('options'));
	}

	/**
	 * Add single or multiple tuples
	 *
	 * @param string|array uuid
	 * @return array
	 * @throws \Exception
	 */
	public function add($uuid) {
		// $uuids = is_string($uuid) ? [$uuid] : $uuid;
		$channels = array_map(function ($uuid) {
			return $this->ef->get($uuid, true);
		}, (array) $uuid);

		$title = Util\Configuration::read('grafana.dashboard.title', 'Volkszaehler');
		$tags = Util\Configuration::read('grafana.dashboard.tags', ['Volkszaehler']);
		$dashboard = new GrafanaModel\Dashboard($title, $tags);
		$this->assignConfiguredProperties($dashbaord, 'dashboard');

		$title = Util\Configuration::read('grafana.panel.title', 'Volkszaehler');
		$type = Util\Configuration::read('grafana.panel.type', GrafanaModel\Panel::TYPE_GRAPH);
		$panel = new GrafanaModel\Panel($title, $type);
		$this->assignConfiguredProperties($panel, 'panel');

		$panel->datasource = Util\Configuration::read('grafana.panel.datasource', 'gravo');
		$panel->gridPos = Util\Configuration::read('grafana.panel.gridPos', new GrafanaModel\Dimensions(0, 0, 20, 10));
		$dashboard->panels[] = $panel;

		foreach ($channels as $channel) {
			$target = new GrafanaModel\Target($channel->getUuid());
			$this->assignConfiguredProperties($target, 'target');
			$panel->targets[] = $target;
		}

		$this->post($dashboard);
	}

	private function post($dashboard) {
		AnnotationRegistry::registerLoader('class_exists');

		$request = new GrafanaRequest\CreateUpdateDashboard($dashboard, true);
		$serializer = SerializerBuilder::create()->build();
		$json = $serializer->serialize($request, 'json');

		if (!($apikey = Util\Configuration::read('grafana.key'))) {
            throw new \Exception('Missing grafana api key');
		}
		if (!($api = Util\Configuration::read('grafana.uri'))) {
            throw new \Exception('Missing grafana api url');
		}

		$client = new Client(['headers' => [
			'Authorization' => 'Bearer ' . $apikey,
			'Content-type' => 'application/json',
		]]);

		$url = sprintf('%s/dashboards/db', rtrim($api, '/'));
		$resp = $client->request('POST', $url, ['body' => $json]);
		$json = (string)$resp->getBody();
		// echo $json;
		$json = json_decode($json);

	    $url = sprintf('%s/%s', rtrim($api, '/api'), ltrim($json->url, '/'));
		// echo $url . PHP_EOL;

		return ['data' => $json];
	}

	/**
	 * Assign configuration entries to object
	 *
	 * @param stdClass $o
	 * @param string $type
	 * @return void
	 */
	private function assignConfiguredProperties(&$o, $type) {
		$configKey = 'grafana.' . $type;
		foreach (Util\Configuration::read($configKey) as $key => $val) {
			$o[$key] = $val;
		}
	}
}
