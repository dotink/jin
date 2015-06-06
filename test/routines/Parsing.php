<?php namespace Dotink\Lab
{
	use Dotink\Jin;
	use Dotink\Flourish;

	return [

		'setup' => function($data, $shared) {
			needs($data['root'] . '/src/Parser.php');
			needs($data['root'] . '/vendor/dotink/flourish-collection/src/Collection.php');

			$shared->jin = new Jin\Parser(new Flourish\Collection());
		},

		'tests' => [

			/**
			 *
			 */
			'Person Mapper Example' => function($data, $shared)
			{
				$jin_file = $data['root'] . '/test/resources/kitchen_sink.jin';
				$contents = file_get_contents($jin_file);
				$data     = $shared->jin->parse($contents)->get();

				assert(is_array($data))->equals(TRUE);
				assert(isset($data['person']['education']['level']))->equals(TRUE);
				assert(is_bool($data['person']['single']))->equals(TRUE);
				assert(is_int($data['person']['age']))->equals(TRUE);
				assert(is_float($data['person']['pi']))->equals(TRUE);

				assert($data['person']['education']['level'])->equals('Associates');
			}
		]
	];
}
