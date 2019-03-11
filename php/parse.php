<?php

declare(strict_types=1);

/**
 * VUT FIT IPP 2018/2019 project.
 *
 * Parsing IPPcode19 source code.
 *
 * @author Dominik Harmim <xharmi00@stud.fit.vutbr.cz>
 */


require __DIR__ . '/initialization.php';


// parse input arguments
$arguments = ArgumentProcessor::process($argv, [
	'help' => true,
	'stats' => [
		'value' => true,
	],
	'loc' => true,
	'comments' => true,
	'labels' => true,
	'jumps' => true,
]);

if (isset($arguments['help'])) {
	Utils::help(
		$arguments,
		<<<EOT
		Script parse.php loads IPPcode19 source code from standard input,
		checks lexical and syntactic rules and prints XML representation
		of program to standard output.

		usage: php parse.php [--help] [--stats=file] [--loc] [--comments] [--labels] [--jumps]  

		  `--help` prints help to standard output.
		  `--stats=file` prints statistics to file.
		  `--loc` prints number of lines with instructions to statistics.
		  `--comments` prints number of lines with comments to statistics.
		  `--labels` prints number of labels in code to statistics.
		  `--jumps` prints number of jumps in code to statistics.
		\n
		EOT
	);

} elseif (
	!isset($arguments['stats'])
	&& (
		isset($arguments['loc'])
		|| isset($arguments['comments'])
		|| isset($arguments['labels'])
		|| isset($arguments['jumps'])
	)
) {
	Utils::error(ReturnCodes::INVALID_PARAMETER, 'Input parameter --stats is missing.');
}


// process source code and prints output XML
fwrite(STDOUT, IPPcode19Parser::parse($arguments)->saveXML());
exit(ReturnCodes::SUCCESS);
