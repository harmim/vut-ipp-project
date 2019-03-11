<?php

declare(strict_types=1);

/**
 * VUT FIT IPP 2018/2019 project.
 *
 * Return codes.
 *
 * @author Dominik Harmim <xharmi00@stud.fit.vutbr.cz>
 */


/**
 * Class with constants of return codes.
 */
final class ReturnCodes
{
	public const
		SUCCESS = 0, // without error
		INVALID_PARAMETER = 10, // missing script parameter or invalid combination of parameters
		INPUT_FILE_ERROR = 11, // error during opening input file
		OUTPUT_FILE_ERROR = 12, // error during opening output file
		PARSE_HEADER_ERROR = 21, // invalid or missing header in source code in IPPcode19
		PARSE_UNKNOWN_OPCODE = 22, // unknown or invalid opcode in source code in IPPcode19
		PARSE_ERROR = 23, // other lexical or syntactic error in source code in IPPcode19
		INTERNAL_ERROR = 99; // other internal error
}
