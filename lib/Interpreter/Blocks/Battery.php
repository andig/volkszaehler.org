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
use Doctrine\ORM\EntityManager;

use Volkszaehler\Model;
use Volkszaehler\Util;
use Volkszaehler\Controller\EntityController;
use Volkszaehler\Interpreter\Interpreter;

/**
 * Battery block
 *
 * @author Andreas Goetz <cpuidle@gmx.de>
 * @package default
 */
class Battery implements BlockInterface {

	protected $entities;
	protected $blockManager;

	public function __construct(Request $request, EntityManager $em, $name) {
		$this->em = $em;
		$this->request = $request;
		$this->name = $name;

		$this->entities = array();

		$this->getParameters(array('in'));

		$this->entities['in'] = EntityController::factory($this->em, $this->ioin, true);

		$this->blockManager = BlockManager::getInstance();
		$this->blockManager->add($name, $this->entities['in']);
	}

	protected function getParameters($parameters) {
		foreach ($parameters as $parameter) {
			$parameterName = $this->name . $parameter;

			if (!$this->request->query->has($parameterName)) {
				throw new \Exception('Missing parameter ' . $parameterName . ' for battery');
			}

			$propertyName = 'io' . $parameter;
			$this->$propertyName = $this->request->query->get($parameterName);
		}
	}
}

?>
