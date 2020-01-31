<?php

use PHPUnit\Framework\TestCase;

final class ParsingTest extends TestCase
{
	public function setUp()
	{
		putenv('FOOBAR=foo');
		putenv('VALUE=value');

		$sample_file       = file_get_contents(__DIR__ . '/../resources/sample.jin');
		$this->parser      = new Dotink\Jin\Parser([
			'foo' => 'bar'
		], [
			'hello' => function($name) {
				return 'Hello ' . $name . '!';
			}
		]);

		$this->objData     = $this->parser->parse($sample_file, FALSE);
		$this->arrayData   = $this->parser->parse($sample_file);
	}

	public function testSimpleValue()
	{
		$this->assertSame(
			$this->arrayData->get('simpleValue'),
			'value'
		);
	}

	public function testQuotedValue()
	{
		$this->assertSame(
			'value',
			$this->arrayData->get('quotedValue')
		);
	}

	public function testIntValue()
	{
		$this->assertSame(
			1,
			$this->arrayData->get('intValue')
		);
	}

	public function testComValue()
	{
		$this->assertSame(
			'This value is commented',
			$this->arrayData->get('comValue')
		);
	}

	public function testMultiValue()
	{
		$this->assertSame(
			"This is multiple lines of text.  Line endings should be\npreserved until `foo=bar` or `[section]` or `\\n\\n`.",
			$this->arrayData->get('multiValue')
		);
	}

	public function testInclude()
	{
		$this->assertSame(
			[
				'value1' => 1,
				'value2' => 2
			],
			$this->arrayData->get('complex.include')
		);

		$this->assertSame(
			2,
			$this->objData->get('complex.include')->value2
		);
	}


	public function testRun()
	{
		$this->assertSame($this->arrayData->get('complex.run'), 'bar');
	}


	public function testEnv()
	{
		$this->assertSame($this->arrayData->get('complex.envWithDefault'), 'bar');
		$this->assertSame($this->arrayData->get('complex.envWithoutDefault'), NULL);
		$this->assertSame($this->arrayData->get('complex.envSetWithDefault'), 'foo');
	}


	public function testFunction()
	{
		$this->assertSame($this->arrayData->get('complex.customFunction'), 'Hello Matt!');
	}

	public function testMapping()
	{
		$this->assertSame(
			[
				[
					'value1' => 1,
					'value2' => 2
				],
				[
					'value1' => 3,
					'value2' => 'value'
				]
			],
			$this->arrayData->get('complex.mapping')
		);

		$this->assertSame(
			2,
			$this->objData->get('complex.mapping')[0]->value2
		);
	}

	public function testReference()
	{
		$this->assertSame(
			'value',
			$this->arrayData->get('reference.sub1.sub1.simpleValue')
		);

		$this->assertSame(
			'value',
			$this->arrayData->get('reference.sub2.simpleValue')
		);
	}


	public function testDashSection()
	{
		$this->assertSame(
			$this->arrayData->get('dash-section.simpleValue'),
			'value'
		);

		$this->assertSame(
			'value',
			$this->arrayData->get('dash-section.quotedValue')
		);

		$this->assertSame(
			1,
			$this->arrayData->get('dash-section.intValue')
		);

		$this->assertSame(
			'This value is commented',
			$this->arrayData->get('dash-section.comValue')
		);

		$this->assertSame(
			"This is multiple lines of text.  Line endings should be\npreserved until `foo=bar` or `[section]` or `\\n\\n`.",
			$this->arrayData->get('multiValue')
		);
	}
}
