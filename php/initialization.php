<?php

declare(strict_types=1);

/**
 * VUT FIT IPP 2018/2019 project.
 *
 * Initialization of scripts.
 *
 * @author Dominik Harmim <xharmi00@stud.fit.vutbr.cz>
 */

use LSS\Array2XML;


// automatic class loading
spl_autoload_register(function (string $class): void {
	// ignore namespace
	$explodedClass = explode('\\', $class);
	$classWithoutNS = end($explodedClass);

	include __DIR__ . "/$classWithoutNS.php";
});


// Array2XML initialization
Array2XML::init('1.0', 'UTF-8');


// UTF-8 encoding for mb extension
mb_internal_encoding('UTF-8');
