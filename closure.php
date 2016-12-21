<?php

/**
 * Communication library for Google's online JavaScript compiler.
 *
 * This small library is used to send JavaScript files or URL list to the
 * server and compile it with specified options. Library only handles
 * communication and will not handle caching for you.
 *
 * Library requires PHP version 5.3+
 *
 * For additional information refer to:
 *	https://developers.google.com/closure/compiler/
 *
 * Copyright © 2016 Way2CU. All Rights Reserved.
 * Author: Mladen Mijatov <mladen@way2cu.com>
 */

namespace Library\Closure;


class RemoteServerError extends \Exception {};
class InvalidResponseError extends \Exception {};


class Level {
	const WHITESPACE = 'WHITESPACE_ONLY';
	const SIMPLE = 'SIMPLE_OPTIMIZATIONS';
	const ADVANCED = 'ADVANCED_OPTIMIZATIONS';
}


class Compiler {
	private $secure = false;
	private $level = Level::SIMPLE;
	private $use_library = false;
	private $warning_level = 'QUIET';
	private $externals = null;
	private $externals_url = null;
	private $language = 'ECMASCRIPT5';
	private $errors = array();
	private $warnings = array();
	private $files = array();
	private $links = array();
	private $input_list = array();

	private $optimization_levels = array('WHITESPACE_ONLY', 'SIMPLE_OPTIMIZATIONS', 'ADVANCED_OPTIMIZATIONS');
	private $warning_levels = array('QUIET', 'DEFAULT', 'VERBOSE');
	private $supported_languages = array('ECMASCRIPT3', 'ECMASCRIPT5', 'ECMASCRIPT5_STRICT');

	// communication endpoint
	private $endpoint = '/compile';
	private $hostname = 'closure-compiler.appspot.com';

	/**
	 * Add local file to be compiled. If at least one file is specified
	 * compilation from source instead from URL list will take precedence.
	 *
	 * @param string $file_name
	 */
	public function add_file($file_name) {
		if (!in_array($file_name, $this->files) && file_exists($file_name)) {
			$index = 'Input_'.count($this->files);
			$this->input_list[$index] = $file_name;
			$this->files[] = $file_name;
		}
	}

	/**
	 * Add file to be compiled from specified URL.
	 *
	 * @param string $url
	 */
	public function add_url($url) {
		$this->links[] = $url;
	}

	/**
	 * Use secure connection to send request to server.
	 *
	 * @param boolean $use_ssl
	 */
	public function set_secure($use_ssl) {
		if (is_bool($use_ssl))
			$this->secure = $use_ssl;
	}

	/**
	 * Set optimization level.
	 *
	 * @param string $level
	 */
	public function set_level($level) {
		if (in_array($level, $this->optimization_levels))
			$this->level = $level;
	}

	/**
	 * Specify which version of ECMAScript to assume when checking for errors.
	 * Currently available: ECMASCRIPT3, ECMASCRIPT5, ECMASCRIPT5_STRICT
	 *
	 * @param string $language
	 */
	public function set_language($language) {
		if (in_array($language, $this->supported_languages))
			$this->language = $language;
	}

	/**
	 * Set JavaScript code that declares function names or other symbols. Use this feature to
	 * preserve symbols that are defined outside of code you are compiling.
	 *
	 * @param string $code
	 */
	public function set_externals($code) {
		$this->externals = $code;
	}

	/**
	 * Set URL for JavaScript file containing function names and/or other symbols. Use this
	 * feature to preserve symbols that are defined outside of code you are compiling.
	 *
	 * @param string $url
	 */
	public function set_externals_url($url) {
		$this->externals_url = $url;
	}

	/**
	 * Get list of errors reported by the compiler.
	 *
	 * @return array
	 */
	public function get_errors() {
		return $this->errors;
	}

	/**
	 * Get list of warnings reported by the compiler.
	 *
	 * @return array
	 */
	public function get_warnings() {
		return $this->warnings;
	}

	/**
	 * Get list of files sent for compilation along with their index
	 * names. These index names will appear in compilation warnings and errors.
	 *
	 * @return array
	 */
	public function get_input_list() {
		return $this->input_list;
	}

	/**
	 * Compile added files and code and return result as string.
	 *
	 * @return string
	 */
	public function compile() {
		$result = '';
		$response = null;

		// prepare headers and content
		$params = $this->prepare_params();
		$content = $this->build_query($params);
		$headers = $this->prepare_headers($content);

		// open connection
		$port = $this->secure ? 443 : 80;
		$prefix = $this->secure ? 'ssl://' : '';
		$socket = fsockopen($prefix.$this->hostname, $port, $error_number, $error_string, 2);

		// send data for compilation
		if ($socket && $error_number == 0) {
			// send and receive data
			fwrite($socket, $headers."\r\n\r\n".$content);
			$raw_data = stream_get_contents($socket);
			$data_pos = strpos($raw_data, '{"compiledCode":');

			// parse response
			list($header, $body) = preg_split("/\R\R/", $raw_data, 2);
			$response = json_decode($body, true);

			// close connection
			fclose($socket);
			$result = false;
		}

		// bail if we didn't get any response
		if (is_null($response)) {
			throw new InvalidResponseError('Closure compilation server did not provide response!');
			return $result;
		}

		// handle server side errors
		if (array_key_exists('serverErrors', $response)) {
			$count = count($response['serverErrors']);
			foreach ($response['serverErrors'] as $index => $error) {
				$message = 'Compilation error '.$index.'/'.$count.': ';
				$message .= (int) $error['code'].' - ';
				$message .= (string) $error['message'];

				error_log($message);
			}
			throw new RemoteServerError('Error compiling provided files!');

		} else {
			if (!empty($response->errors))
				$this->errors = $response->errors;

			if (!empty($response->warnings))
				$this->warnings = $response->warnings;

			// store response
			$result = $response->compiledCode;
		}

		return $result;
	}

	/**
	 * Compile added files and code and save them to specified file name.
	 * Return boolean value denotes success in saving to specified file.
	 *
	 * @param string $file_name
	 * @return boolean
	 * @throws InvalidResponseError
	 * @throws RemoteServerError
	 */
	public function compile_and_save($file_name) {
		$result = false;
		$response = null;

		// prepare headers and content
		$params = $this->prepare_params();
		$content = $this->build_query($params);
		$headers = $this->prepare_headers($content);

		// open connection
		$port = $this->secure ? 443 : 80;
		$prefix = $this->secure ? 'ssl://' : '';
		$socket = fsockopen($prefix.$this->hostname, $port, $error_number, $error_string, 2);

		// send data for compilation
		if ($socket && $error_number == 0) {
			// send and receive data
			fwrite($socket, $headers."\r\n\r\n".$content);
			$raw_data = stream_get_contents($socket);

			// parse response
			list($header, $body) = preg_split("/\R\R/", $raw_data, 2);
			$response = json_decode($body, true);

			// close connection
			fclose($socket);
			$result = false;
		}

		// bail if we didn't get any response
		if (is_null($response)) {
			throw new InvalidResponseError('Closure compilation server did not provide response!');
			return $result;
		}

		// handle server side errors
		if (array_key_exists('serverErrors', $response)) {
			$count = count($response['serverErrors']);
			foreach ($response['serverErrors'] as $index => $error) {
				$message = 'Compilation error '.$index.'/'.$count.': ';
				$message .= (int) $error['code'].' - ';
				$message .= (string) $error['message'];

				error_log($message);
			}
			throw new RemoteServerError('Error compiling provided files!');

		} else {
			if (!empty($response->errors))
				$this->errors = $response->errors;

			if (!empty($response->warnings))
				$this->warnings = $response->warnings;

			// store response
			if (count($this->errors) == 0 && file_put_contents($file_name, $response->compiledCode))
				$result = true;
		}

		return $result;
	}

	/**
	 * Prepare HTTP headers.
	 *
	 * @param string $content
	 * @return string
	 */
	private function prepare_headers($content) {
		$header = array();
		$content_length = strlen($content);

		// compile default headers
		$header[] = "POST {$this->endpoint} HTTP/1.1";
		$header[] = 'Host: '.$this->hostname;
		$header[] = 'Content-Type: application/x-www-form-urlencoded';
		$header[] = 'Content-Length: '.$content_length;
		$header[] = 'Connect-time: 0';
		$header[] = 'Connection: close';

		return implode("\r\n", $header);
	}

	/**
	 * Prepare paramters for sending.
	 *
	 * @return array
	 */
	private function prepare_params() {
		$result = array();

		// include code to be compiled
		if (count($this->files) > 0) {
			// join all the files
			$code = '';
			foreach ($this->files as $file_name)
				$code .= file_get_contents($file_name);

			// add combined files as parameter
			$result['js_code'] = $code;

		} else {
			// add links
			$result['code_url'] = $this->links;
		}

		// required configuration
		$result['compilation_level'] = $this->level;
		$result['output_format'] = 'json';
		$result['output_info'] = array('compiled_code', 'warnings', 'errors');

		// configure externals
		if (!is_null($this->externals))
			$result['js_externs'] = $this->externals; else
			$result['externs_url'] = $this->externals_url;

		// optional configuration
		$result['use_closure_library'] = $this->use_library ? 'true' : 'false';
		$result['warning_level'] = $this->warning_level;
		$result['language'] = $this->language;

		return $result;
	}

	/**
	 * Build query string the proper way. PHP's http_build_query doesn't know
	 * how to properly create list items so we have to do it manually.
	 *
	 * @param array $params
	 * @return string
	 */
	private function build_query($params) {
		$result = array();

		foreach ($params as $key => $value)
			if (!is_array($value)) {
				// add normal value
				$result[] = $key.'='.urlencode($value);

			} else {
				// add param list
				foreach ($value as $list_item)
					$result[] = $key.'='.urlencode($list_item);
			}

		return implode('&', $result);
	}
}

?>
