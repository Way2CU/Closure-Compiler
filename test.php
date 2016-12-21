<?php

/**
 * Automated test for Closure compiler.
 */

require_once('closure.php');

use Library\Closure\Compiler;
use Library\Closure\Level;
use Library\Closure\RemoteServerError;
use Library\Closure\InvalidResponseError;

$sample = <<<EOF
/**
 * Sample header.
 */

/**
 * Function declaration.
 * @param string name
 */
function hello(name) {
	var message = 'Hello, ' + name;
	show_alert(message);

	// log to console
	if (console.log)
		console.log(name);
}

hello('New user');

// export function
window['hello'] = hello;
EOF;

$externals = <<<EOF
function show_alert(text){};
EOF;

// create compiler
$compiler = new Compiler();

// compile code and test it
$levels = array(Level::WHITESPACE, Level::SIMPLE, Level::ADVANCED);
$compiler->set_code($sample);
$compiler->set_externals($externals);

foreach ($levels as $level) {
	$compiler->set_level($level);

	// show compile level
	print "> Level: {$level}\n";

	try {
		$code = $compiler->compile();

	} catch (InvalidResponseError $error) {
		print "Server returned unfamiliar response.\n";
		print $error->get_message();

	} catch (RemoteServerError $error) {
		print "There was a server side problem.\n";
		print $error->get_message();
	}

	// show compilation outcome
	$error_count = count($compiler->get_errors());
	$warning_count = count($compiler->get_warnings());

	print "\tErrors: {$error_count}\n";
	print "\tWarnings: {$warning_count}\n\n";
	print $code."\n\n";
}

?>
