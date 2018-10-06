<?php namespace Dotink\Lab
{
	use Dotink\Jin;
	use Dotink\Flourish;

	return [

		'setup' => function($data, $shared) {
			needs($data['root'] . '/src/Parser.php');
			needs($data['root'] . '/vendor/adbario/php-dot-notation/src/Dot.php');

			$shared->jin = new Jin\Parser();
		},

		'tests' => [

			/**
			 *
			 */
			'Kitchen Sink' => function($data, $shared)
			{
				$jin_file = $data['root'] . '/test/resources/kitchen_sink.jin';
				$contents = file_get_contents($jin_file);
				$data     = $shared->jin->parse($contents)->get();

				accept(is_array($data))->equals(TRUE);
				accept(isset($data['person']['education']['level']))->equals(TRUE);
				accept(is_bool($data['person']['single']))->equals(TRUE);
				accept(is_int($data['person']['age']))->equals(TRUE);
				accept(is_float($data['person']['pi']))->equals(TRUE);

				accept($data['person']['education']['level'])->equals('Associates');
				accept($data['person']['vehicles'])->contains('2005 Mazda 6');
			}
		]
	];
}
