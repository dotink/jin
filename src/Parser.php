<?php

namespace Dotink\Jin;

/**
 *
 */
class Parser
{
	const COLLAPSE_CHARACTER        = "\xC2\xA0";
	const REGEX_STRUCTURE           = '#^(?<type>[a-z]+)\s*\((?<args>(?:\n|.)*)\)\s*(?:\{(?<body>(?:\n|.)*)\})?$#';
	const REGEX_CATEGORY_IDENTIFIER = '[\\\\\\/a-zA-Z0-9-_.&]+';
	const REGEX_FIELD_IDENTIFIER    = '[a-zA-Z0-9-_]+';
	const REGEX_NEW_LINE            = '\n';
	const REGEX_WHITESPACE          = '\t|\s';


	/**
	 *
	 */
	protected $collection = NULL;


	/**
	 *
	 */
	protected $context = NULL;


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
	protected $templates = array();


	/**
	 * Create a new Jin
	 *
	 * @access public
	 * @return void
	 */
	public function __construct(array $context = [])
	{
		$this->collection = new Collection();
		$this->templates  = new Collection();
		$this->context    = $context;
	}


	/**
	 * Parse a Jin string
	 *
	 * @acces public
	 * @param string $jin_string The Jin string to parse
	 * @param boolean $assoc Whether JSON objects should be associative arrays
	 * @return array The parsed Jin string as an associative array
	 */
	public function parse($jin_string, $assoc = TRUE)
	{
		$this->data = clone $this->collection;
		$jin_string = $this->removeComments($jin_string);
		$jin_string = $this->removeReferences($jin_string);
		$jin_string = $this->removeWhitespace($jin_string);
		$jin_string = $this->removeNewLines($jin_string);
		$jin_string = trim($jin_string);

		foreach (parse_ini_string($jin_string, TRUE, INI_SCANNER_RAW) as $index => $values) {
			$this->index = $index;

			if (!is_array($values)) {
				$this->data->set($this->index, $this->parseValue(NULL, $values, $assoc));

			} else {
				$this->data->set($this->index, array());

				foreach ($values as $key => $value) {
					$this->index = $index . '.' . $key;

					$this->data->set($this->index, $this->parseValue(NULL, $value, $assoc));
				}
			}
		}

		return $this->data;
	}


	/**
	 *
	 */
	protected function parseDef($args, $body, $assoc)
	{
		$this->templates->set($this->index, [
			'args' => array_map('trim', explode(',', $args)),
			'body' => trim($body)
		]);

		return 'def(' . $this->index . ')';
	}


	/**
	 *
	 */
	protected function parseEnv($args, $body, $assoc)
	{
		//
		// validate
		//

		$args    = array_map('trim', explode(',', $args));
		$default = $args[1] ?? NULL;
		$varname = $args[0];
		$value   = getenv($varname);

		return $value === FALSE
			? $this->parseValue(NULL, $default, $assoc)
			: $value;
	}


	/**
	 *
	 */
	protected function parseInc($args, $body, $assoc)
	{
		$map    = $this->templates->get(trim($args));
		$values = explode("\n", trim($body));
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
		$map    = $this->templates->get(trim($args));
		$values = explode("\n", trim($body));

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


	/**
	 *
	 */
	protected function parseRun($args, $body, $assoc)
	{
		extract($this->context);

		return eval("return $args;");
	}


	/**
	 *
	 */
	protected function parseValue($args, $body, $assoc)
	{
		$value  = trim(str_replace(static::COLLAPSE_CHARACTER, "\n", $body));
		$leadch = ($length = strlen($value)) ? strtolower($value[0]) : '';

		if (preg_match(static::REGEX_STRUCTURE, $value, $matches)) {
			$method = 'parse' . $matches['type'];

			if (!is_callable([$this, $method])) {
				throw new \RuntimeException(sprintf(
					'Invalid structure %s used: %s',
					$matches['type'],
					$matches['body']
				));
			}

			$value = $this->$method($matches['args'] ?? NULL, $matches['body'] ?? NULL, $assoc);

		} elseif (in_array($leadch, ['n', 't', 'f']) && in_array($length, [4, 5])) {
			if (strtolower($value) == 'null') {
				$value = NULL;
			} elseif (strtolower($value) == 'true') {
				$value = TRUE;
			} elseif (strtolower($value) == 'false') {
				$value = FALSE;
			}

		} elseif (in_array($leadch, ['{', '[', '"']) || is_numeric($value)) {
			if (!is_numeric($value)) {
				$value = str_replace('\\\\', '\\', $value);
				$value = str_replace('\\', '\\\\', $value);

			} elseif ($leadch == '0') {
				if ($value[1] == 'x') {
					$value = hdexdec($value);
				} elseif ($value[1] == 'b') {
					$value = bindec($value);
				} else {
					$value = octdec($value);
				}
			}

			$value = json_decode($value, $assoc);

			if ($value === NULL) {
				throw new \RuntimeException(sprintf(
					'Error parsing JSON data: %s',
					$body
				));
			}
		}

		return $value;
	}


	/**
	 * Removes all comments
	 *
	 * @access protected
	 * @param string $string The string from which to remove comments
	 * @return string The string, stripped of comments
	 */
	protected function removeComments($string)
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
	 * Removes newlines in the proper places
	 *
	 * @access protected
	 * @param string $string The string from which to remove new lines
	 * @return string The string, stripped of new lines
	 */
	protected function removeNewLines($string)
	{
		return preg_replace(sprintf(
			'#(^|%s)(?!(\[%s\]|%s\s*=|;))#',
			self::REGEX_NEW_LINE,
			self::REGEX_CATEGORY_IDENTIFIER,
			self::REGEX_FIELD_IDENTIFIER
		), static::COLLAPSE_CHARACTER . '$2', $string); // replace with non-breaking space
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
}
