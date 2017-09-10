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
use Doctrine\ORM\ORMException;

use Volkszaehler\Model;
use Volkszaehler\Util;
use Volkszaehler\Interpreter\Interpreter;
use Volkszaehler\View\View;

/**
 * Block controller
 *
 * @author Andreas Goetz <cpuidle@gmx.de>
 * @package default
 */
trait BlockControllerTrait {

	public static $blockAccessor;

	public function setupBlocks() {
		if (!is_array(self::$blockAccessor)) {
			self::$blockAccessor = array();
		}

		$definitions = self::makeArray($this->request->get('define'));
		foreach ($definitions as $definition) {
			list ($name, $type) = explode(',', $definition . ',');
			if (!$type) {
				$type = $name;
			}

			$class = __NAMESPACE__ .'\\'. ucfirst($type);
			if (!class_exists($class)) {
				throw new \Exception('Block ' . $type . ' doesn\'t exist');
			}

			$block = new $class($this->request, $this->em, $name);
		}
	}

}

?>
