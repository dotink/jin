<?php namespace Dotink\Jin
{
	use Adbar;

	/**
	 *
	 */
	class Parser
	{
		const COLLAPSE_CHARACTER        = "\xC2\xA0";
		const REGEX_MAP                 = '#map\s*\((?<keys>(?:\n|.)+)\)\s*\{(?<data>(?:\n|.)*)\}#';
		const REGEX_CATEGORY_IDENTIFIER = '[\\\\a-zA-Z-_.]+';
		const REGEX_FIELD_IDENTIFIER    = '[a-zA-Z-_]+';
		const REGEX_NEW_LINE            = '\n';
		const REGEX_WHITESPACE          = '\t|\s';

		/**
		 * Create a new Jin
		 *
		 * @access public
		 * @return void
		 */
		public function __construct(Adbar\Dot $collection = NULL)
		{
			$this->collection = $collection ?: new Adbar\Dot();
		}


		/**
		 * Parse a Jin string
		 *
		 * @acces public
		 * @param string $jin_string The Jin string to parse
		 * @param boolean $assoc Whether JSON objects should be associative arrays
		 * @return array The parsed Jin string as an associative array
		 */
		public function parse($jin_string, $assoc = FALSE)
		{
			$collection = clone $this->collection;
			$jin_string = $this->removeComments($jin_string);
			$jin_string = $this->removeWhitespace($jin_string);
			$jin_string = $this->removeNewLines($jin_string);
			$jin_string = trim($jin_string);


			foreach (parse_ini_string($jin_string, TRUE, INI_SCANNER_RAW) as $index => $values) {
				if (!is_array($values)) {
					$data = $this->parseValue($values, $assoc);

				} else {
					$data = array();

					foreach ($values as $key => $value) {
						$data[$key] = $this->parseValue($value, $assoc);
					}
				}

				$collection->set($index, $data);
			}

			return $collection;
		}


		/**
		 *
		 */
		protected function parseMap($keys, $data, $assoc)
		{
			$keys  = array_map('trim', explode(',', $keys));
			$value = explode("\n", trim($data));

			foreach ($value as $i => $row) {
				$value[$i] = str_getcsv(preg_replace('/\t+/', "\t", rtrim($row, ',')), "\t");

				if (count($value[$i]) != count($keys)) {
					throw new \RuntimeException(sprintf(
						'Error parsing map(), row %s, the number of columns ' .
						'does not match the number of keys: %s',
						$i + 1,
						$row
					));
				}

				foreach ($value[$i] as $j => $column) {
					$value[$i][$j] = $this->parseValue($column, $assoc);
				}

				$value[$i] = array_combine($keys, $value[$i]);
			}

			return $value;
		}


		/**
		 *
		 */
		protected function parseValue($value, $assoc)
		{
			$value  = trim(str_replace(static::COLLAPSE_CHARACTER, "\n", $value));
			$leadch = ($length = strlen($value)) ? strtolower($value[0]) : '';

			if (in_array($leadch, ['m']) && preg_match(static::REGEX_MAP, $value, $matches)) {
				$value = $this->parseMap($matches['keys'], $matches['data'], $assoc);

			} elseif (in_array($leadch, ['n', 't', 'f']) && in_array($length, [4, 5])) {
				if (strtolower($value) == 'null') {
					$value = NULL;
				} elseif (strtolower($value) == 'true') {
					$value = TRUE;
				} elseif (strtolower($value) == 'false') {
					$value = FALSE;
				}

			} elseif (in_array($leadch, ['{', '[', '"']) || is_numeric($value)) {
				$value = json_decode($value, $assoc);

				if ($value === NULL) {
					throw new \RuntimeException(sprintf(
						'Error parsing JSON data: %s',
						$value
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
				'#(^|%s)(\s*;.*)#',
				self::REGEX_NEW_LINE
			), '$1', $string);
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
}
