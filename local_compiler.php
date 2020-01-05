<?php

/**
 * Local Compiler Script
 *
 * This script can be used to simulate Google's API endpoint and
 * provides same functionality and request parameters by utilizing
 * standalone compiler. Script is designed to work with other scripts
 * from this project but is not exclusive to.
 *
 * In order for this script to work JRE 1.8 or greater is needed and
 * compiler JAR file downloaded from:
 *
 * https://github.com/google/closure-compiler
 *
 * WARNING: Even though great care has been put into writing this
 * script to be as safe as possible it is still not recommended to
 * expose this script to the public. If such functionality is needed
 * it is highly advisable to configure IP whitelist, firewall and
 * as many other precautions as possible. PHP should not be given
 * ability to execute commands on the system.
 *
 * Copyright © 2020 Way2CU. All Rights Reserved.
 * Author: Mladen Mijatov <mladen@way2cu.com>
 */

define('COMMAND', 'java -jar '.dirname(__FILE__).'/compiler.jar');
define('WORKING_DIRECTORY', '/tmp');

$whitelist = array();
$data = '';
$externs = '';
$parameters = array();

// parameter to flag map
$flags = array(
	'compilation_level' => '--compilation_level',
	'language'          => '--language_in',
	'warning_level'     => '--warning_level',
);

// make sure requester is allowed to access
if (count($whitelist) > 0 && !in_array($_SERVER['REMOTE_ADDR'], $whitelist)) {
	error_log('Blocked access from '.$_SERVER['REMOTE_ADDR'].' to service!');
	die();
}

// collect data
foreach ($_POST as $key => $value) {
	switch ($key) {
		case 'js_code':
			$data .= $value;
			break;

		case 'code_url':
			if (!empty($value))
				$data .= file_get_contents($value);
			break;

		case 'js_externs':
			$externs .= $value;
			break;

		case 'externs_url':
			if (!empty($value))
				$externs .= file_get_contents($value);
			break;

		default:
			if (!array_key_exists($key, $flags))
				break;

			$parameters[] = $flags[$key].' '.escapeshellarg($value);
	}
}

// ensure we have data to work with
if (empty($data) || count($parameters) == 0) {
	$response = array(
		'serverErrors' => array(
			array('code' => 0, 'error' => 'Missing required parameters.')
		)
	);

	header('Content-Type: application/json');
	print(json_encode($response));
	die();
}

// save externals to a file
if (!empty($externs)) {
	$externs_file = tmpfile();
	$externs_path = stream_get_meta_data($externs_file)['uri'];
	fwrite($externs_file, $externs);
	$parameters[] = '--externs '.$externs_path;
}

// prepare for compilation
$pipes = null;
$final_parameters = implode(' ', $parameters);
$process = proc_open(
	COMMAND.' '.$final_parameters, array(
		0 => array('pipe', 'r'),
		1 => array('pipe', 'w'),
		2 => array('pipe', 'w')
	),
	$pipes, WORKING_DIRECTORY);

// make sure we have a compiler present and it's working
if (!is_resource($process)) {
	$response = array(
		'serverErrors' => array(
			array('code' => 0, 'error' => 'Missing compiler or issue with configuration.')
		)
	);

	header('Content-Type: application/json');
	print(json_encode($response));

	error_log('Unable to open process for '.COMMAND.' '.$final_parameters);
	die();
}

$stdin = $pipes[0];
$stdout = $pipes[1];
$stderr = $pipes[2];

// send data to compiler
fwrite($stdin, $data);
fclose($stdin);

stream_set_blocking($stdout, false);
stream_set_blocking($stderr, false);

$stdout_eof = false;
$stderr_eof = false;

$error_buffer = '';
$output_buffer = '';

do {
	$read = array($stdout, $stderr);
	$write = null;
	$except = null;

	stream_select($read, $write, $except, 1, 0);

	$stdout_eof = $stdout_eof || feof($stdout);
	$stderr_eof = $stderr_eof || feof($stderr);

	if (!$stdout_eof)
		$output_buffer .= fgets($stdout);

	if (!$stderr_eof)
		$error_buffer .= fgets($stderr);
} while (!$stdout_eof || !$stderr_eof);

fclose($stdout);
fclose($stderr);

proc_close($process);

// prepare response
// TODO: Parse error buffer and populate list properly.
$response = array(
	'compiledCode' => $output_buffer,
	'errors' => array(),
	'warnings' => array()
);

header('Content-Type: application/json');
print(json_encode($response));

?>
