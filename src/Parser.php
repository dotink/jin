<?php namespace Dotink\Jin
{
	use Dotink\Flourish;

	/**
	 *
	 */
	class Parser
	{
		/**
		 * Create a new Jin
		 *
		 * @access public
		 * @return void
		 */
		public function __construct(Flourish\Collection $collection)
		{
			$this->collection = $collection;
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
			$collection  = clone $this->collection;
			$jin_string  = $this->removeWhitespace($jin_string);
			$jin_string  = $this->removeNewLines($jin_string);

			foreach (parse_ini_string($jin_string, TRUE, INI_SCANNER_RAW) as $index => $values) {
				foreach ($values as $key => $value) {
					if ($value[0] == '{' || $value[0] == '[') {
						$values[$key] = json_decode($value, $assoc);

					} elseif (is_numeric($value)) {
						$values[$key] = json_decode($value);

					} else {
						$leadch = strtolower($value[0]);
						$length = strlen($value);

						if (in_array($leadch, ['t', 'f']) && in_array($length, [4, 5])) {
							if (strtolower($value) == 'true') {
								$values[$key] = TRUE;
							} elseif (strtolower($value) == 'false') {
								$values[$key] = FALSE;
							}
						}
					}
				}

				$collection->set($index, $values);
			}

			return $collection;
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
			return preg_replace('#(^|\n)(?!([a-zA-Z_]+\s*=|\[[a-zA-Z._]+\]))#', '$2', $string);
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
			return preg_replace('#(^|\n)(\t|\s)*#', '$1', $string);
		}
	}
}
