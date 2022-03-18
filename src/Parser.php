<?php

namespace Dotink\Jin;

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
	const TOKEN_PROMISE             = '___PROMISE//%s';
	const TOKEN_TEMPLATE            = '___TEMPLATE %s';


	/**
	 *
	 */
	protected $collection = NULL;


	/**
	 *
	 */
	protected $context = array();


	/**
	 *
	 */
	protected $functions = array();


	/**
	 *
	 */
	protected $index = NULL;


	/**
	 *
	 */
	protected $data = NULL;


	/**
	 *
	 */
	protected $promises = array();


	/**
	 *
	 */
	protected $templates = array();


	/**
	 * Create a new Jin
	 *
	 * @access public
	 * @return void
	 */
	public function __construct(array $context = [], array $functions = [])
	{
		$this->collection  = new Collection();
		$builtin_functions = [
			'env' => [$this, 'extract'],
			'run' => [$this, 'execute']
		];

		foreach ($context as $name => $value) {
			$this->context[strtolower($name)] = $value;
		}

		foreach ($functions + $builtin_functions as $name => $value) {
			if (!is_callable($value)) {
				throw new \RuntimeException(
					'Cannot register function "%s", must map to callable',
					$name
				);
			}

			$this->functions[strtolower($name)] = $value;
		}
	}


	/**
	 * Parse a Jin string
	 *
	 * @acces public
	 * @param string $jin_string The Jin string to parse
	 * @param boolean $assoc Whether JSON objects should be associative arrays
	 * @return Collection The parsed Jin string as a Collection
	 */
	public function parse($jin_string, $assoc = TRUE)
	{
		//
		// The order of this parsing is important, don't change without significant contemplation
		//

		$jin_data   = clone $this->collection;
		$jin_string = str_replace("\r\n", "\n", trim($jin_string));
		$jin_string = $this->removeComments($jin_string, FALSE);
		$jin_string = $this->tokenizeQuotes($jin_string, $parts);
		$jin_string = $this->removeInlineComments($jin_string);
		$jin_string = $this->removeReferences($jin_string);
		$jin_string = $this->removeWhitespace($jin_string);
		$jin_string = $this->untokenizeQuotes($jin_string, $parts);
		$jin_string = $this->prepareNewLines($jin_string);
		$jin_string = $this->prepareSemiColons($jin_string);

		$jin_data->on('set', function ($key, $value) use ($jin_data) {
			if (!is_string($value) || !sscanf($value, self::TOKEN_TEMPLATE, $body)) {
				return;
			}

			foreach (array_keys($this->promises) as $index) {
				if (!sscanf($jin_data->get($index), self::TOKEN_PROMISE, $template)) {
					continue;
				}

				if ($template !== $key) {
					continue;
				}

				$jin_data->set($this->index = $index, $this->promises[$index]());
				unset($this->promises[$index]);
			}
		});

		foreach (parse_ini_string($jin_string, TRUE, INI_SCANNER_RAW) as $index => $values) {
			$this->index = $index;

			if (is_scalar($values)) {
				$jin_data->set($this->index, $this->parseValue(NULL, $values, $assoc));

			} else {
				$jin_data->set($this->index, array());

				foreach ($values as $key => $value) {
					$this->index = $index . '.' . $key;

					$jin_data->set($this->index, $this->parseValue(NULL, $value, $assoc));
				}
			}
		}

		if ($jin_data->has('--extends')) {
			$path     = $jin_data->get('--extends');
			$merged   = $this->parse(file_get_contents($path), $assoc);

			$merged->mergeRecursiveDistinct($jin_data);
			$merged->delete($jin_data->get('--without', []));

			return $merged;
		}

		return $jin_data;
	}


	/**
	 *
	 */
	protected function execute($php)
	{
		extract($this->context);

		return eval("return $php;");
	}


	/**
	 *
	 */
	protected function extract($name, $default = NULL)
	{
		return getenv($name) !== FALSE
			? getenv($name)
			: $default;
	}


	/**
	 *
	 */
	protected function parseCall($type, $args, $assoc)
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
			function($arg) use ($assoc, $parts) {
				return $this->parseValue(NULL, $this->untokenizeQuotes($arg, $parts), $assoc);
			},
			explode(',', $args)
		));
	}


	/**
	 *
	 */
	protected function parseDef($args, $body, $assoc)
	{
		$this->templates[$this->index] = [
			'args' => array_map('trim', explode(',', $args)),
			'body' => $body
		];

		return sprintf(self::TOKEN_TEMPLATE, $body);
	}


	/**
	 *
	 */
	protected function parseInc($args, $body, $assoc)
	{
		if (!isset($this->templates[$args])) {
			$this->promises[$this->index] = function () use ($args, $body, $assoc) {
				return $this->parseInc($args, $body, $assoc);
			};

			return sprintf(self::TOKEN_PROMISE, $args);
		}

		$map    = $this->templates[$args];
		$values = explode("\n", $body);
		$json   = $map['body'];

		foreach ($values as $i => $value) {
			$value = $this->parseValue(NULL, $value, $assoc);
			$json  = str_replace('$' . $map['args'][$i], json_encode($value), $json);
		}

		return $this->parseValue(NULL, $json, $assoc);
	}


	/**
	 *
	 */
	protected function parseMap($args, $body, $assoc)
	{
		if (!isset($this->templates[$args])) {
			$this->promises[$this->index] = function() use ($args, $body, $assoc) {
				return $this->parseMap($args, $body, $assoc);
			};

			return sprintf(self::TOKEN_PROMISE, $args);
		}

		$map    = $this->templates[$args];
		$values = explode("\n", $body);

		foreach ($values as $i => $row) {
			$row  = trim(rtrim($row, ','), "\t");

			if (!$row) {
				continue;
			}

			$data = str_getcsv(preg_replace('/\t+/', "\t", $row), "\t");
			$json = $map['body'];

			if (count($data) != count($map['args'])) {
				throw new \RuntimeException(sprintf(
					'Error parsing map(), row %s, the number of values does not match the ' .
					'the number of map arguments: %s',
					$i + 1,
					$row
				));
			}

			foreach ($data as $j => $value) {
				$value = $this->parseValue(NULL, $value, $assoc);
				$json  = str_replace(
					'$' . $map['args'][$j],
					json_encode($value, JSON_UNESCAPED_SLASHES),
					$json
				);
			}

			$values[$i] = $this->parseValue(NULL, $json, $assoc);
		}

		return array_filter($values);
	}


	/**trim(
	 *
	 */
	protected function parseValue($args, $value, $assoc)
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
					trim($matches['args'] ?? NULL),
					trim($matches['body'] ?? NULL),
					$assoc
				);

			} else {
				$value = $this->parseCall(
					trim($matches['type'] ?? NULL),
					trim($matches['args'] ?? NULL),
					$assoc
				);

			}

		} elseif (strtolower($value) == 'null') {
			$value = NULL;

		} elseif (strtolower($value) == 'true') {
			$value = TRUE;

		} elseif (strtolower($value) == 'false') {
			$value = FALSE;

		} elseif (substr($value, 0, 2) == '0b' && preg_match('#[0-1]*#', $value)) {
			$value = bindec($value);

		} elseif (substr($value, 0, 2) == '0x' && ctype_xdigit(substr($value, 2))) {
			$value = hexdec($value);

		} elseif ($fch == '0' && preg_match('#[0-7]*#', $value)) {
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

			if (is_null($value = json_decode($original = $value, $assoc))) {
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
