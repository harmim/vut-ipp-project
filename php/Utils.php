<?php

declare(strict_types=1);

/**
 * VUT FIT IPP 2018/2019 project.
 *
 * Utilities.
 *
 * @author Dominik Harmim <xharmi00@stud.fit.vutbr.cz>
 */


/**
 * Class with utilities methods.
 */
final class Utils
{
	/**
	 * Prints error message to standard error output and terminates script with appropriate return code.
	 *
	 * @param int $returnCode Return code.
	 * @param string|null $message Message to be printed to standard error output.
	 * @return void
	 */
	public static function error(
		int $returnCode = ReturnCodes::INTERNAL_ERROR,
		?string $message = 'Internal error.'
	): void {
		if ($message) {
			fwrite(STDERR, "$message\n");
		}

		exit($returnCode);
	}


	/**
	 * Prints help message to standard output.
	 *
	 * @param array $arguments Program arguments.
	 * @param string $text Message to be printed to standard output.
	 * @return void
	 */
	public static function help(array $arguments, string $text): void
	{
		unset($arguments['help']);
		if ($arguments) {
			self::error(
				ReturnCodes::INVALID_PARAMETER,
				'If parameter --help is used, no other parameters are allowed.'
			);
		}

		fwrite(STDOUT, $text);
		exit(ReturnCodes::SUCCESS);
	}
}
