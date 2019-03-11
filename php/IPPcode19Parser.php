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
	// regular expressions
	private const
		COMMENT = '\s*(#.*)?$',
		TYPE_NIL = '(?:(nil)@(nil))',
		TYPE_INT = '(?:(int)@((?:\+|\-)?\d+))',
		TYPE_BOOL = '(?:(bool)@(true|false))',
		TYPE_STRING = '(?:(string)@((?:[^\s#\\\\]|\\\\\d{3})*))',
		CONST =
			'(?:' . self::TYPE_NIL . '|' . self::TYPE_INT . '|' . self::TYPE_BOOL . '|' . self::TYPE_STRING . ')',
		IDENTIFIER_SPECIAL_CHARS = '_\-\$&%\*!\?',
		IDENTIFIER =
			'(?:[[:alpha:]' . self::IDENTIFIER_SPECIAL_CHARS . '][[:alnum:]' . self::IDENTIFIER_SPECIAL_CHARS . ']*)',
		TYPE = '(nil|int|bool|string)',
		VAR = '((?:GF|LF|TF)@' . self::IDENTIFIER . ')',
		SYMB = '(' . self::CONST . '|' . self::VAR . ')',
		LABEL = '(' . self::IDENTIFIER . ')';

	// opcodes
	private const OPCODES = [
		'MOVE', 'CREATEFRAME', 'PUSHFRAME', 'POPFRAME', 'DEFVAR', 'CALL', 'RETURN', 'PUSHS', 'POPS', 'ADD', 'SUB',
		'MUL', 'IDIV', 'LT', 'GT', 'EQ', 'AND', 'OR', 'NOT', 'INT2CHAR', 'STRI2INT', 'READ', 'WRITE', 'CONCAT',
		'STRLEN', 'GETCHAR', 'SETCHAR', 'TYPE', 'LABEL', 'JUMP', 'JUMPIFEQ', 'JUMPIFNEQ', 'EXIT', 'DPRINT', 'BREAK',
	];


	/**
	 * Parses IPPcode19 source code, checks lexical and syntactic rules and returns XML representation.
	 *
	 * @param array $arguments Input arguments.
	 * @return DOMDocument DOM document object with XML representation of input program.
	 */
	public static function parse(array $arguments): DOMDocument
	{
		$statsFile = isset($arguments['stats']) ? self::openStatsFile($arguments['stats']['value']) : null;
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
			foreach (self::OPCODES as $opcode) {
				if (preg_match('~^\s*' . $opcode . '([^#]*)' . self::COMMENT . '~ui', $line, $m)) {
					$invalidOpcode = false;

					if (isset($m[2])) {
						$comments++;
					}

					$instruction = [
						'@attributes' => [
							'order' => $loc + 1,
							'opcode' => $opcode,
						],
					];

					switch ($opcode) {
						// without operands
						case 'CREATEFRAME':
						case 'PUSHFRAME':
						case 'POPFRAME':
						case 'RETURN':
						case 'BREAK':
							if (!preg_match('~^\s*$~u', $m[1])) {
								self::parseError($lineNumber);
							}

							break;

						// <var> <symb>
						case 'MOVE':
						case 'INT2CHAR':
						case 'STRLEN':
						case 'TYPE':
							if (!preg_match('~^\s+' . self::VAR . '\s+' . self::SYMB . '\s*$~u', $m[1], $mOperands)) {
								self::parseError($lineNumber);
							}
							self::removeEmptyStrings($mOperands);

							$instruction['arg1'] = [
								'@attributes' => [
									'type' => 'var',
								],
								'@value' => $mOperands[1],
							];

							$isArg2Var = self::isVar($mOperands[2]);
							$instruction['arg2'] = [
								'@attributes' => [
									'type' => $isArg2Var ? 'var' : $mOperands[3],
								],
								'@value' => $isArg2Var ? $mOperands[2] : $mOperands[4],
							];

							break;

						// <var>
						case 'DEFVAR':
						case 'POPS':
							if (!preg_match('~^\s+' . self::VAR . '\s*$~u', $m[1], $mOperands)) {
								self::parseError($lineNumber);
							}

							$instruction['arg1'] = [
								'@attributes' => [
									'type' => 'var',
								],
								'@value' => $mOperands[1],
							];

							break;

						// <label>
						case 'CALL':
						case 'LABEL':
						case 'JUMP':
							if (!preg_match('~^\s+' . self::LABEL . '\s*$~u', $m[1], $mOperands)) {
								self::parseError($lineNumber);
							}

							$instruction['arg1'] = [
								'@attributes' => [
									'type' => 'label',
								],
								'@value' => $mOperands[1],
							];

							if ($opcode === 'LABEL' && empty($definedLabels[$m[1]])) {
								$definedLabels[$m[1]] = true;
								$labels++;
							}

							break;

						// <symb>
						case 'PUSHS':
						case 'WRITE':
						case 'EXIT':
						case 'DPRINT':
							if (!preg_match('~^\s+' . self::SYMB . '\s*$~u', $m[1], $mOperands)) {
								self::parseError($lineNumber);
							}
							self::removeEmptyStrings($mOperands);

							$isArg1Var = self::isVar($mOperands[1]);
							$instruction['arg1'] = [
								'@attributes' => [
									'type' => $isArg1Var ? 'var' : $mOperands[2],
								],
								'@value' => $isArg1Var ? $mOperands[1] : $mOperands[3],
							];

							break;

						// <var> <symb1> <symb2>
						case 'ADD':
						case 'SUB':
						case 'MUL':
						case 'IDIV':
						case 'LT':
						case 'GT':
						case 'EQ':
						case 'AND':
						case 'OR':
						case 'NOT':
						case 'STRI2INT':
						case 'CONCAT':
						case 'GETCHAR':
						case 'SETCHAR':
							if (
								!preg_match(
									'~^\s+' . self::VAR . '\s+' . self::SYMB . '\s+' . self::SYMB . '\s*$~u',
									$m[1],
									$mOperands
								)
							) {
								self::parseError($lineNumber);
							}
							self::removeEmptyStrings($mOperands);

							$instruction['arg1'] = [
								'@attributes' => [
									'type' => 'var',
								],
								'@value' => $mOperands[1],
							];

							$isArg2Var = self::isVar($mOperands[2]);
							$instruction['arg2'] = [
								'@attributes' => [
									'type' => $isArg2Var ? 'var' : $mOperands[3],
								],
								'@value' => $isArg2Var ? $mOperands[2] : $mOperands[4],
							];

							$isArg3Var = self::isVar($mOperands[$isArg2Var ? 3 : 5]);
							$instruction['arg3'] = [
								'@attributes' => [
									'type' => $isArg3Var ? 'var' : $mOperands[$isArg2Var ? 4 : 6],
								],
								'@value' => $isArg3Var ?
									$mOperands[$isArg2Var ? 3 : 5]
									: $mOperands[$isArg2Var ? 5 : 7],
							];

							break;

						// <var> <type>
						case 'READ':
							if (!preg_match('~^\s+' . self::VAR . '\s+' . self::TYPE . '\s*$~u', $m[1], $mOperands)) {
								self::parseError($lineNumber);
							}

							$instruction['arg1'] = [
								'@attributes' => [
									'type' => 'var',
								],
								'@value' => $mOperands[1],
							];

							$instruction['arg2'] = [
								'@attributes' => [
									'type' => 'type',
								],
								'@value' => $mOperands[2],
							];

							break;

						// <label> <symb1> <symb2>
						case 'JUMPIFEQ':
						case 'JUMPIFNEQ':
							if (
								!preg_match(
									'~^\s+' . self::LABEL . '\s+' . self::SYMB . '\s+' . self::SYMB . '\s*$~u',
									$m[1],
									$mOperands
								)
							) {
								self::parseError($lineNumber);
							}
							self::removeEmptyStrings($mOperands);

							$instruction['arg1'] = [
								'@attributes' => [
									'type' => 'label',
								],
								'@value' => $mOperands[1],
							];

							$isArg2Var = self::isVar($mOperands[2]);
							$instruction['arg2'] = [
								'@attributes' => [
									'type' => $isArg2Var ? 'var' : $mOperands[3],
								],
								'@value' => $isArg2Var ? $mOperands[2] : $mOperands[4],
							];

							$isArg3Var = self::isVar($mOperands[$isArg2Var ? 3 : 5]);
							$instruction['arg3'] = [
								'@attributes' => [
									'type' => $isArg3Var ? 'var' : $mOperands[$isArg2Var ? 4 : 6],
								],
								'@value' => $isArg3Var ?
									$mOperands[$isArg2Var ? 3 : 5]
									: $mOperands[$isArg2Var ? 5 : 7],
							];

							break;

						default:
							break;
					}

					$xml['instruction'][] = $instruction;
					$loc++;
					if (in_array($opcode, ['CALL', 'RETURN', 'JUMP', 'JUMPIFEQ', 'JUMPIFNEQ'], true)) {
						$jumps++;
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
	 * Writes statistics to file.
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
	 * Determines whether given operand is variable.
	 *
	 * @param string $operand Operand to be parsed.
	 * @return bool True if operand is variable, false otherwise.
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
	public static function removeEmptyStrings(array &$array): void
	{
		$array = array_values(array_filter($array, function (string $string): bool {
			return $string !== '';
		}));
	}
}
