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

/**
 * Basic console command
 */
class BaseCommand extends Command {

	protected $input;
	protected $output;

	protected function configure() {
		$this
			->addOption('--quiet', '-q', InputOption::VALUE_NONE, 'Do not output any message')
			->addOption('--verbose', '-v|vv|vvv', InputOption::VALUE_NONE, 'Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug');
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->input = $input;
		$this->output = $output;
	}

	/*
	 * Helper functions
	 */

	/**
	 * Console output if at least verbose mode
	 */
	protected function verbose($str) {
		if ($this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
			$this->output->writeln($str);
		}
	}

	/**
	 * Console output if at least debug mode
	 */
	protected function debug($str) {
		if ($this->output->getVerbosity() >= OutputInterface::VERBOSITY_DEBUG) {
			$this->output->writeln($str);
		}
	}
}

?>
