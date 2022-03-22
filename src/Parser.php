<?php

namespace Dotink\Jin;

use RuntimeException;
use SplObjectStorage;

/**
 *
 */
class Parser
{
	const COLLAPSE_CHARACTER        = "\xC2\xA0";
	const SEMICOLON_CHARACTER       = "\xC2\xAD";

	const REGEX_STRUCTURE           = '#^(?<type>[a-z]+)\s*\((?<args>.*)\)\s*(?:\{(?<body>.*)\})?$#s';
	const REGEX_QUOTED_STRING       = '#"((?:""|[^"])*)"#s';
	const REGEX_TRAILING_COMMA      = '#,\s*(\\]|\\})#';

	const REGEX_CATEGORY_IDENTIFIER = '[\\\\\\/a-zA-Z0-9-_.&]+';
	const REGEX_FIELD_IDENTIFIER    = '[a-zA-Z0-9-_]+';
	const REGEX_NEW_LINE            = '\n';
	const REGEX_WHITESPACE          = '\t|\s';

	const TOKEN_QUOTED              = '___QUOTED@(%s)';
	const TOKEN_TEMPLATE            = '___TEMPLATE=%s';
	const TOKEN_PROMISE_RENDER      = '___PROMISE_RENDER:%s';


	/**
	 *
	 */
	static protected $builtinFunctions = [
		'env' => 'callEnv',
		'run' => 'callRun'
	];


	/**
	 *
	 */
	protected $activeKey = NULL;


	/**
	 *
	 */
	protected $activePath = NULL;


	/**
	 *
	 */
	protected $collection = NULL;


	/**
	 *
	 */
	protected $data = array();


	/**
	 *
	 */
	protected $functions = array();


	/**
	 *
	 */
	protected $promises = array();


	/**
	 *
	 */
	protected $templates = array();


	/**
	 *
	 */
	protected $variables = array();


	/**
	 * Create a new Jin
	 *
	 * @access public
	 * @return void
	 */
	public function __construct(array $context = [], array $functions = [], $assoc = TRUE)
	{
		$this->collection = new Collection();
		$this->assoc      = $assoc;

		foreach ($context as $name => $value) {
			$this->variables[strtolower($name)] = $value;
		}

		foreach (static::$builtinFunctions as $name => $value) {
			$this->functions[strtolower($name)] = [$this, $value];
		}

		foreach ($functions as $name => $value) {
			$this->functions[strtolower($name)] = $value;
		}

		foreach ($this->functions as $name => $value) {
			if (!is_callable($value)) {
				throw new \RuntimeException(
					'Cannot register function "%s", must map to callable',
					$name
				);
			}
		}
	}


	/**
	 *
	 */
	public function all()
	{
		return $this->data;
	}


	/**
	 * Parse a Jin string
	 *
	 * @acces public
	 * @param string $string The Jin string to parse
	 * @param string $key A string with which to identify the collection
	 * @return Collection The parsed Jin string as a Collection
	 */
	public function parse($string, $key = NULL)
	{
		//
		// Boot
		//

		$collection = $this->init($string, $key);

		//
		// Main parsing loop
		//

		foreach (parse_ini_string($string, TRUE, INI_SCANNER_RAW) as $path => $values) {
			$this->activePath = $path;

			if (is_scalar($values)) {
				$this->dataSet($this->activePath, $this->parseValue($values, NULL));

			} else {
				$this->dataSet($this->activePath, array());

				foreach ($values as $sub_path => $value) {
					$this->activePath  = $path. '.' . $sub_path;

					$this->dataSet($this->activePath, $this->parseValue($value, NULL));
				}
			}
		}

		//
		// Extend
		//

		if ($collection->has('--extends')) {
			$file   = $collection->get('--extends');

			$merged = $this->parse(file_get_contents($file));
			$merged->delete($collection->get('--without', []));

			foreach ($merged->flatten() as $key => $value) {
				$collection->set($key, $value);
			}
		}


		return $collection;
	}


	/**
	 *
	 */
	protected function callEnv($name, $default = NULL)
	{
		return getenv($name) !== FALSE
			? getenv($name)
			: $default;
	}


	/**
	 *
	 */
	protected function callRun($php)
	{
		extract($this->variables);

		return eval("return $php;");
	}


	/**
	 *
	 */
	public function dataGet($index)
	{
		list($path, $key) = explode('@', $index) + [NULL, $this->activeKey];

		return $this->data[$key]->get($path);
	}


	/**
	 *
	 */
	public function dataSet($index, $value)
	{
		list($path, $key) = explode('@', $index) + [NULL, $this->activeKey];

		$this->data[$key]->set($path, $value);

		return $this;
	}


	/**
	 *
	 */
	public function index($include_key = FALSE)
	{
		if ($include_key) {
			return sprintf('%s@%s', $this->activePath, $this->activeKey);
		}

		return $this->activePath;
	}


	/**
	 *
	 */
	protected function init(&$body, $key)
	{
		$body = str_replace("\r\n", "\n", trim($body));
		$body = $this->removeComments($body, FALSE);
		$body = $this->tokenizeQuotes($body, $parts);
		$body = $this->removeInlineComments($body);
		$body = $this->removeReferences($body);
		$body = $this->removeWhitespace($body);
		$body = $this->untokenizeQuotes($body, $parts);
		$body = $this->prepareNewLines($body);
		$body = $this->prepareSemiColons($body);
		$data = clone $this->collection;

		if ($key) {
			$this->activeKey = $key;

			if (isset($this->data[$this->activeKey])) {
				return $this->data[$this->activeKey];
			}
		} else {
			$this->activeKey = spl_object_hash($data);
		}

		return $this->data[$this->activeKey] = $data;
	}


	/**
	 *
	 */
	protected function parseCall($type, $args)
	{
		$type = strtolower($type);

		if (!isset($this->functions[$type])) {
			throw new \RuntimeException(sprintf(
				'Unable to call configuration function "%s", no such function registered',
				$type
			));
		}

		$args = $this->tokenizeQuotes($args, $parts);

		return $this->functions[$type](...array_map(
			function($arg) use ($parts) {
				return $this->parseValue($this->untokenizeQuotes($arg, $parts), NULL);
			},
			explode(',', $args)
		));
	}


	/**
	 *
	 */
	protected function parseDef($body, $args)
	{
		//
		// When a template is defined, we want to define a global path which alway references the
		// latest iteration of the template via the more specific index.  When we try to resolve it
		// for any sort of mapping we'll use the global index to reference the more specific one if
		// if only the path is provided.
		//

		$this->templates[$this->index()]     = $this->index(TRUE);
		$this->templates[$this->index(TRUE)] = array_map('trim', explode(',', $args));

		foreach ($this->promises as $index => $promise) {
			if (!sscanf($this->dataGet($index), self::TOKEN_PROMISE_RENDER, $template_index)) {
				continue;
			}

			if (!in_array($template_index, [$this->index(), $this->index(TRUE)])) {
				continue;
			}

			$this->dataSet($index, $promise($body));

			unset($this->promises[$index]);
		}

		return sprintf(self::TOKEN_TEMPLATE, $body);
	}


	/**
	 *
	 */
	protected function parseInc($body, $index, $template = NULL)
	{
		if (!isset($this->templates[$index])) {
			$this->promises[$this->index(TRUE)] = function ($template) use ($body, $index) {
				return $this->parseInc($body, $index, $template);
			};

			return sprintf(self::TOKEN_PROMISE_RENDER, $index);
		}

		//
		// Resolve global template if necessary
		//

		if (!strpos($index, '@')) {
			$index = $this->templates[$index];
		}

		if (!$template) {
			$template = substr($this->dataGet($index), strpos(self::TOKEN_TEMPLATE, '=') + 1);
		}

		$json   = $template;
		$values = explode("\n", $body);
		$args   = $this->templates[$index];

		foreach ($values as $i => $value) {
			$value = $this->parseValue($value, NULL);
			$json  = str_replace('$' . $args[$i], json_encode($value), $json);
		}

		return $this->parseValue($json, NULL);
	}


	/**
	 *
	 */
	protected function parseMap($body, $index, $template = NULL)
	{
		if (!isset($this->templates[$index])) {
			$this->promises[$this->index(TRUE)] = function ($template) use ($body, $index) {
				return $this->parseMap($body, $index, $template);
			};

			return sprintf(self::TOKEN_PROMISE_RENDER, $index);
		}

		//
		// Resolve global template if necessary
		//

		if (!strpos($index, '@')) {
			$index = $this->templates[$index];
		}

		if (!$template) {
			$template = substr($this->dataGet($index), strpos(self::TOKEN_TEMPLATE, '=') + 1);
		}

		$values = explode("\n", $body);
		$args   = $this->templates[$index];

		foreach ($values as $i => $row) {
			$json = $template;
			$row  = trim(rtrim($row, ','), "\t");

			if (!$row) {
				continue;
			}

			$data = str_getcsv(preg_replace('/\t+/', "\t", $row), "\t");

			if (count($data) != count($args)) {
				throw new \RuntimeException(sprintf(
					'Error parsing map(), row %s, the number of values does not match the ' .
					'the number of map arguments: %s',
					$i + 1,
					$row
				));
			}

			foreach ($data as $j => $value) {
				$value = $this->parseValue($value, NULL);
				$json  = str_replace(
					'$' . $args[$j],
					json_encode($value, JSON_UNESCAPED_SLASHES),
					$json
				);
			}

			$values[$i] = $this->parseValue($json, NULL);
		}

		return array_filter($values);
	}


	/**
	 *
	 */
	protected function parseValue($value, $args)
	{
		$value = trim($value);
		$value = str_replace(static::COLLAPSE_CHARACTER, "\n", $value);
		$value = str_replace(static::SEMICOLON_CHARACTER, ";", $value);
		$fch   = ($l = strlen($value)) ? strtolower($value[0]) : '';
		$lch   = ($l = strlen($value)) ? strtolower($value[$l - 1]) : '';

		if (preg_match(static::REGEX_STRUCTURE, $value, $matches)) {
			$method = 'parse' . $matches['type'];

			if (strtolower($method) !== 'parsecall' && is_callable([$this, $method])) {
				$value = $this->$method(
					trim($matches['body'] ?? NULL),
					trim($matches['args'] ?? NULL)
				);

			} else {
				$value = $this->parseCall(
					trim($matches['type'] ?? NULL),
					trim($matches['args'] ?? NULL)
				);
			}

		} elseif (strtolower($value) == 'null') {
			$value = NULL;

		} elseif (strtolower($value) == 'true') {
			$value = TRUE;

		} elseif (strtolower($value) == 'false') {
			$value = FALSE;

		} elseif (substr($value, 0, 2) == '0b' && preg_match('#^[0-1]*$#', substr($value, 2))) {
			$value = bindec($value);

		} elseif (substr($value, 0, 2) == '0x' && ctype_xdigit(substr($value, 2))) {
			$value = hexdec($value);

		} elseif ($fch == '0' && preg_match('#^[0-7]*$#', $value)) {
			$value = octdec($value);

		} elseif (is_numeric($value)) {
			if (strpos($value, '.') !== FALSE) {
				$value = floatval($value);
			} else {
				$value = intval($value);
			}

		} elseif (in_array([$fch, $lch], [['{', '}'], ['[', ']']])) {
			$value = str_replace(
				["\n", "\t", "\\\\", "\\",],
				[" ",  " ",  "\\",   "\\\\"],
				$value
			);

			$value = $this->tokenizeQuotes($value, $parts);
			$value = preg_replace(self::REGEX_TRAILING_COMMA, '$1', $value);
			$value = $this->untokenizeQuotes($value, array_map(function($part) {
				return $part == '""' ? $part : str_replace('""', "\\\"", $part);
			}, $parts));

			if (is_null($value = json_decode($original = $value, $this->assoc))) {
				throw new \RuntimeException(sprintf(
					'Error parsing JSON data: %s',
					$original
				));
			}

		} else {
			$value = str_replace("\"\"", "\"", $value);
		}

		return $value;
	}


	/**
	 * Prepares newlines for parsing by replacing them with a safe character
	 *
	 * @access protected
	 * @param string $string The string from which to prepare
	 * @return string The string, prepared
	 */
	protected function prepareNewLines($string)
	{
		return preg_replace(sprintf(
			'#(^|%s)(?!(\[%s\]|%s\s*=|;))#',
			self::REGEX_NEW_LINE,
			self::REGEX_CATEGORY_IDENTIFIER,
			self::REGEX_FIELD_IDENTIFIER
		), static::COLLAPSE_CHARACTER . '$2', $string); // replace with non-breaking space
	}


	/**
	 * Prepares semi-colons for parsing by replacing them with a safe character
	 *
	 * @access protected
	 * @param string $string The string from which to prepare
	 * @return string The string, prepared
	 */
	protected function prepareSemiColons($string)
	{
		return str_replace(';', self::SEMICOLON_CHARACTER, $string);
	}


	/**
	 * Removes line comments
	 *
	 * @access protected
	 * @param string $string The string from which to remove comments
	 * @return string The string, stripped of comments
	 */
	protected function removeComments($string)
	{
		return preg_replace(sprintf(
			'#((?:^|%s)\s*);.*#',
			self::REGEX_NEW_LINE
		), '', $string);
	}


	/**
	 * Removes inline comments
	 *
	 * @access protected
	 * @param string $string The string from which to remove comments
	 * @return string The string, stripped of comments
	 */
	protected function removeInlineComments($string)
	{
		return preg_replace(sprintf(
			'#((?:^|%s)[^;]*);.*#',
			self::REGEX_NEW_LINE
		), '$1', $string);
	}


	/**
	 *
	 */
	protected function removeReferences($string)
	{
		$lines = explode("\n", $string);
		$regex = sprintf('#^\s*\[(%s)\]\s*$#', self::REGEX_CATEGORY_IDENTIFIER);
		$stack = array();

		foreach ($lines as $i => $line) {
			if (!preg_match($regex, $line, $matches)) {
				continue;
			}

			$section  = $matches[1];
			$refcount = 0;

			if (preg_match('#^&+\.#', $section)) {
				$refcount = strlen(explode('.', $section)[0]);

				for ($x = count($stack) - 1; $x >= 0; $x--) {
					if ($stack[$x][0] >= $refcount) {
						continue;
					}

					if ($refcount - $stack[$x][0] > 1) {
						throw new \RuntimeException(sprintf(
							'Invalid number of references found, nesting too deeply, in ' .
							'section: %s',
							$matches[0]
						));
					}

					$section   = preg_replace('#^&+\.#', $stack[$x][1] . '.', $section);
					$lines[$i] = str_replace($matches[1], $section, $lines[$i]);

					break;
				}
			}

			$stack[] = [$refcount, $section];
		}

		return implode("\n", $lines);
	}


	/**
	 * Removes leading whitespace from the proper places
	 *
	 * @access protected
	 * @param string $string The string from which to remove leading whitespace
	 * @return string The string, stripped of leading whitespace
	 */
	protected function removeWhitespace($string)
	{
		return preg_replace(sprintf(
			'#(^|%s)(%s)*#',
			self::REGEX_NEW_LINE,
			self::REGEX_WHITESPACE
		), '$1', $string);
	}


	/**
	 *
	 */
	protected function tokenizeQuotes($string, &$parts)
	{
		$parts = array();

		if (preg_match_all(self::REGEX_QUOTED_STRING, $string, $matches)) {
			$parts = $matches[0];

			foreach ($parts as $i => $sub_string) {
				$string = str_replace($sub_string, sprintf(self::TOKEN_QUOTED, $i), $string);
			}
		}

		return $string;
	}


	/**
	 *
	 */
	protected function untokenizeQuotes($string, $parts)
	{
		foreach ($parts as $i => $sub_string) {
			$string = str_replace(sprintf(self::TOKEN_QUOTED, $i), $sub_string, $string);
		}

		return $string;
	}
}
