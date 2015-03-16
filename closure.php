<?php

/**
 * Communication library for Google's online JavaScript compiler.
 *
 * This small library is used to send JavaScript files or URL list to the
 * server and compile it with specified options. Library only handles
 * communication and will not handle caching for you.
 *
 * For additional information refer to:
 *	https://developers.google.com/closure/compiler/
 *
 * Copyright © 2015 Way2CU. All Rights Reserved.
 * Author: Mladen Mijatov <mladen@way2cu.com>
 */

namespace Library/Closure;

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

	private $optimization_levels = array('WHITESPACE_ONLY', 'SIMPLE_OPTIMIZATIONS', 'ADVANCED_OPTIMIZATIONS');
	private $warning_levels = array('QUIET', 'DEFAULT', 'VERBOSE');
	private $supported_languages = array('ECMASCRIPT3', 'ECMASCRIPT5', 'ECMASCRIPT5_STRICT');

	// communication endpoint
	const URL = 'closure-compiler.appspot.com/compile';

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
	 * Compile added files and code and return result as string.
	 *
	 * @return string
	 */
	public function compile() {
	}

	/**
	 * Compile added files and code and save them to specified file name.
	 * Return boolean value denotes success in saving to specified file.
	 *
	 * @param string $file_name
	 * @return boolean
	 */
	public function compile_and_save($file_name) {
	}
}

?>
