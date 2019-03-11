<?php

declare(strict_types=1);

/**
 * VUT FIT IPP 2018/2019 project.
 *
 * Parser of IPPcode19 source code.
 *
 * @author Dominik Harmim <xharmi00@stud.fit.vutbr.cz>
 */

use LSS\Array2XML;


/**
 * Class for parsing IPPcode19 source code.
 */
final class IPPcode19Parser
{
	// patterns
	private const
		COMMENT = '\s*(#.*)?$',
		TYPE_NIL = '(?:(nil)@(nil))',
		TYPE_INT = '(?:(int)@((?:\+|\-)?\d+))',
		TYPE_BOOL = '(?:(bool)@(true|false))',
		TYPE_STRING = '(?:(string)@((?:[^\s#\\\\]|(?:\\\\\d{3}))*))',
		CONST =
			'(?:' . self::TYPE_NIL . '|' . self::TYPE_INT . '|' . self::TYPE_BOOL . '|' . self::TYPE_STRING . ')',
		IDENTIFIER_SPECIAL_CHARS = '_\-\$&%\*!\?',
		IDENTIFIER =
			'(?:[[:alpha:]' . self::IDENTIFIER_SPECIAL_CHARS . '][[:alnum:]' . self::IDENTIFIER_SPECIAL_CHARS . ']*)',
		TYPE = '(nil|int|bool|string)',
		VAR = '((?:GF|LF|TF)@' . self::IDENTIFIER . ')',
		SYMB = '(' . self::CONST . '|' . self::VAR . ')',
		LABEL = '(' . self::IDENTIFIER . ')';

	// instructions and theirs operands
	private const INSTRUCTIONS = [
		'MOVE' => ['var', 'symb'],
		'CREATEFRAME' => [],
		'PUSHFRAME' => [],
		'POPFRAME' => [],
		'DEFVAR' => ['var'],
		'CALL' => ['label'],
		'RETURN' => [],
		'PUSHS' => ['symb'],
		'POPS' => ['var'],
		'ADD' => ['var', 'symb', 'symb'],
		'SUB' => ['var', 'symb', 'symb'],
		'MUL' => ['var', 'symb', 'symb'],
		'IDIV' => ['var', 'symb', 'symb'],
		'LT' => ['var', 'symb', 'symb'],
		'GT' => ['var', 'symb', 'symb'],
		'EQ' => ['var', 'symb', 'symb'],
		'AND' => ['var', 'symb', 'symb'],
		'OR' => ['var', 'symb', 'symb'],
		'NOT' => ['var', 'symb'],
		'INT2CHAR' => ['var', 'symb'],
		'STRI2INT' => ['var', 'symb', 'symb'],
		'READ' => ['var', 'type'],
		'WRITE' => ['symb'],
		'CONCAT' => ['var', 'symb', 'symb'],
		'STRLEN' => ['var', 'symb'],
		'GETCHAR' => ['var', 'symb', 'symb'],
		'SETCHAR' => ['var', 'symb', 'symb'],
		'TYPE' => ['var', 'symb'],
		'LABEL' => ['label'],
		'JUMP' => ['label'],
		'JUMPIFEQ' => ['label', 'symb', 'symb'],
		'JUMPIFNEQ' => ['label', 'symb', 'symb'],
		'EXIT' => ['symb'],
		'DPRINT' => ['symb'],
		'BREAK' => [],
	];


	/**
	 * Parses IPPcode19 source code, checks lexical and syntactic rules and returns XML representation.
	 *
	 * @param array $arguments Input arguments.
	 * @return DOMDocument DOM document object with XML representation of input program.
	 */
	public static function parse(array $arguments): DOMDocument
	{
		$statsFile = isset($arguments['stats']['value']) ? self::openStatsFile($arguments['stats']['value']) : null;
		$lineNumber = $loc = $comments = $labels = $jumps = 0;
		$definedLabels = [];
		$header = false;
		$xml = [
			'@attributes' => [
				'language' => 'IPPcode19',
			],
		];

		while (($line = fgets(STDIN)) !== false) {
			$lineNumber++;

			if (preg_match('~^\s*$~u', $line)) {
				continue;

			} elseif (preg_match('~^\s*#~u', $line)) {
				$comments++;
				continue;

			} elseif (!$header) {
				if (preg_match('~^\.IPPcode19' . self::COMMENT . '~ui', $line, $m)) {
					if (isset($m[1])) {
						$comments++;
					}

					$header = true;
					continue;

				} else {
					self::missingHeaderError();
				}
			}

			$invalidOpcode = true;
			foreach (self::INSTRUCTIONS as $opcode => $operands) {
				if (preg_match('~^\s*' . $opcode . '([^#]*)' . self::COMMENT . '~ui', $line, $m)) {
					$invalidOpcode = false;
					$instruction = [];

					if (isset($m[2])) {
						$comments++;
					}

					$operandsPattern = '~^';
					foreach ($operands as $operand) {
						switch ($operand) {
							case 'var':
								$operandsPattern .= '\s+' . self::VAR;
								break;

							case 'type':
								$operandsPattern .= '\s+' . self::TYPE;
								break;

							case 'symb':
								$operandsPattern .= '\s+' . self::SYMB;
								break;

							case 'label':
								$operandsPattern .= '\s+' . self::LABEL;
								break;

							default:
								break;
						}
					}
					$operandsPattern .= '\s*$~u';

					if (!preg_match($operandsPattern, $m[1], $mOperands)) {
						self::parseError($lineNumber);
					}
					self::removeEmptyStrings($mOperands);

					$argNumber = $mIndex = 0;
					foreach ($operands as $operand) {
						$argIndex = 'arg' . ++$argNumber;

						if ($operand === 'symb') {
							$isVar = self::isVar($mOperands[++$mIndex]);
							$instruction[$argIndex] = [
								'@attributes' => [
									'type' => $isVar ? 'var' : $mOperands[++$mIndex],
								],
								'@value' => $isVar ? $mOperands[$mIndex] : $mOperands[++$mIndex],
							];

						} else {
							$instruction[$argIndex] = [
								'@attributes' => [
									'type' => $operand,
								],
								'@value' => $mOperands[++$mIndex],
							];
						}
					}

					$instruction['@attributes'] = [
						'order' => ++$loc,
						'opcode' => $opcode,
					];
					$xml['instruction'][] = $instruction;

					if (in_array($opcode, ['CALL', 'RETURN', 'JUMP', 'JUMPIFEQ', 'JUMPIFNEQ'], true)) {
						$jumps++;

					} elseif ($opcode === 'LABEL') {
						$label = trim($m[1]);
						if (empty($definedLabels[$label])) {
							$definedLabels[$label] = true;
							$labels++;
						}
					}

					break;
				}
			}

			if ($invalidOpcode) {
				Utils::error(
					ReturnCodes::PARSE_UNKNOWN_OPCODE,
					"Invalid or unknown opcode in source file at line $lineNumber."
				);
			}
		}

		if (!$header) {
			self::missingHeaderError();
		}

		self::writeStats($statsFile, $loc, $comments, $labels, $jumps, $arguments);

		return Array2XML::createXML('program', $xml);
	}


	/**
	 * Opens file for statistics and returns it's handle.
	 *
	 * @param string $statsFile File name for statistics.
	 * @return resource Handle to opened file for statistics.
	 */
	private static function openStatsFile(string $statsFile)
	{
		if (!($statsFileHandle = fopen($statsFile, 'w'))) {
			Utils::error(ReturnCodes::OUTPUT_FILE_ERROR, "File '$statsFile' can not be opened.");
		}

		return $statsFileHandle;
	}


	/**
	 * Writes statistics to a file.
	 *
	 * @param resource|null $statsFile Handle to opened file for statistics.
	 * @param int $loc Number of lines with instructions.
	 * @param int $comments Number of lines with comments.
	 * @param int $labels Number of labels in code.
	 * @param int $jumps Number of jumps in code.
	 * @param array $arguments Input arguments.
	 * @return void
	 */
	private static function writeStats(
		$statsFile,
		int $loc,
		int $comments,
		int $labels,
		int $jumps,
		array $arguments
	): void {
		if (!$statsFile) {
			return;
		}

		foreach ($arguments as $optionName => $flags) {
			switch ($optionName) {
				case 'loc':
					$data = $loc;
					break;

				case 'comments':
					$data = $comments;
					break;

				case 'labels':
					$data = $labels;
					break;

				case 'jumps':
					$data = $jumps;
					break;

				default:
					continue 2;
			}

			if (fwrite($statsFile, "$data\n") === false) {
				Utils::error();
			}
		}

		if (!fclose($statsFile)) {
			Utils::error();
		}
	}


	/**
	 * Prints invalid or missing header error.
	 *
	 * @return void
	 */
	private static function missingHeaderError(): void
	{
		Utils::error(ReturnCodes::PARSE_HEADER_ERROR, "Invalid or missing '.IPPcode19' header in source file.");
	}


	/**
	 * Prints lexical or syntactic error.
	 *
	 * @param int $lineNumber Number of line with error.
	 * @return void
	 */
	private static function parseError(int $lineNumber): void
	{
		Utils::error(ReturnCodes::PARSE_ERROR, "Lexical or syntactic error at line $lineNumber.");
	}


	/**
	 * Determines whether given operand is a variable.
	 *
	 * @param string $operand Operand to be parsed.
	 * @return bool True if operand is a variable, false otherwise.
	 */
	private static function isVar(string $operand): bool
	{
		return (bool) preg_match('~^' . self::VAR . '$~u', $operand);
	}


	/**
	 * Removes empty string elements from an array.
	 *
	 * @param array $array An array for removing empty strings.
	 * @return void
	 */
	private static function removeEmptyStrings(array &$array): void
	{
		$array = array_values(array_filter($array, function (string $string): bool {
			return $string !== '';
		}));
	}
}
