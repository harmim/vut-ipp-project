<?php

declare(strict_types=1);

/**
 * VUT FIT IPP 2018/2019 project.
 *
 * Processing of input arguments.
 *
 * @author Dominik Harmim <xharmi00@stud.fit.vutbr.cz>
 */


/**
 * Class for processing input arguments.
 */
final class ArgumentProcessor
{
	/**
	 * Process input arguments ($argv).
	 *
	 * @param array $arguments Input arguments ($argv).
	 * @param array $options Allowed options. ['option' => true, 'option2' => ['value' => true], ...].
	 * @return array Processed input arguments.
	 *               ['option' => ['position' => 1], 'option2' => ['position' => 2, 'value' => 'value']]
	 */
	public static function process(array $arguments, array $options): array
	{
		$processedArguments = [];
		array_shift($arguments);

		foreach ($options as $optionName => $parameters) {
			if (!empty($parameters['value'])) {
				foreach ($arguments as $key => $argument) {
					if (preg_match('~^\-\-' . $optionName . '=(.*)|(?:"(.*)")\z~u', $argument, $m)) {
						$processedArguments[$optionName] = [
							'position' => $key,
							'value' => $m[1],
						];
						unset($arguments[$key]);
						break;
					}
				}

			} else {
				if (($key = array_search("--$optionName", $arguments, true)) !== false) {
					$processedArguments[$optionName] = [
						'position' => $key,
					];
					unset($arguments[$key]);
				}
			}
		}

		if ($arguments) {
			Utils::error(ReturnCodes::INVALID_PARAMETER, 'Invalid input parameters.');
		}

		uasort($processedArguments, function (array $a, array $b): int {
			return $a['position'] <=> $b['position'];
		});

		return $processedArguments;
	}
}
