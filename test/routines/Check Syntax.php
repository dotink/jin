<?php namespace Dotink\Lab
{
	use RecursiveDirectoryIterator;
	use RecursiveIteratorIterator;
	use RegexIterator;
	use Exception;

	return [

		'tests' => [

			/**
			 *
			 */
			'Checking all PHP Files (.php)' => function($data, $shared)
			{
				$dir       = new RecursiveDirectoryIterator(getcwd());
				$ite       = new RecursiveIteratorIterator($dir);
				$files     = new RegexIterator($ite, '#(?:.*)\.php$#', RegexIterator::GET_MATCH);
				$file_list = array();

				foreach ($files as $file) {
					$file_list = array_merge($file_list, $file);
				}

				foreach ($file_list as $file) {
					$output = [];

					exec(sprintf('%s -l %s 2>&1', PHP_BINARY, escapeshellarg($file)), $output);

					if (preg_match_all(REGEX_PARSE_ERROR, implode("\n", $output), $matches)) {
						throw new Exception(
							$matches[1][0] . // The syntax error
							_(' @ ', 'green') . // @
							_($matches[2][0], 'yellow') . // File
							'#' . // #
							_($matches[3][0], 'yellow') // Line number
						);
					}
				}
			}
		]
	];
}