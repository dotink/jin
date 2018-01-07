<?php namespace Dotink\Jin
{
	use Dotink\Flourish;

	/**
	 *
	 */
	class Parser
	{
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
		public function __construct(Flourish\Collection $collection = NULL)
		{
			$this->collection = $collection ?: new Flourish\Collection();
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
		protected function parseValue($data, $assoc)
		{
			$value  = $data;
			$length = strlen($data);
			$leadch = $length ? strtolower($data[0]) : '';

			if (in_array($leadch, ['n', 't', 'f']) && in_array($length, [4, 5])) {
				if (strtolower($data) == 'null') {
					$value = NULL;
				} elseif (strtolower($data) == 'true') {
					$value = TRUE;
				} elseif (strtolower($data) == 'false') {
					$value = FALSE;
				}

			} elseif (in_array($leadch, ['{', '[', '"']) || is_numeric($data)) {
				$value = json_decode($data, $assoc);

				if ($value === NULL) {
					throw new Flourish\ProgrammerException(
						'Error parsing JSON data: %s',
						$data
					);
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
			), ' $2', $string);
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
