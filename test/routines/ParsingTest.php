<?php

use PHPUnit\Framework\TestCase;

final class ParsingTest extends TestCase
{
	public function setUp()
	{
		putenv('FOOBAR=foo');
		putenv('VALUE=value');

		$sample_jin_content = file_get_contents(__DIR__ . '/../resources/sample.jin');
		$this->arrayParser  = new Dotink\Jin\Parser([
			'foo' => 'bar'
		], [
			'hello' => function($name) {
				return 'Hello ' . $name . '!';
			}
		]);

		$this->objParser = new Dotink\Jin\Parser([
			'foo' => 'bar'
		], [
			'hello' => function ($name) {
				return 'Hello ' . $name . '!';
			}
		], FALSE);

		$this->arrayData1 = $this->arrayParser->parse($sample_jin_content);
		$this->arrayData2 = $this->arrayParser->parse($sample_jin_content);
		$this->objData    = $this->objParser->parse($sample_jin_content);
	}

	public function testSimpleValue()
	{
		$this->assertSame(
			$this->arrayData1->get('simpleValue'),
			'value'
		);
	}

	public function testQuotedValue()
	{
		$this->assertSame(
			'value',
			$this->arrayData1->get('quotedValue')
		);
	}

	public function testIntValue()
	{
		$this->assertSame(
			1,
			$this->arrayData1->get('intValue')
		);
	}


	public function testFloatValue()
	{
		$this->assertSame(
			1.03,
			$this->arrayData1->get('floatValue')
		);
	}


	public function testHexValue()
	{
		$this->assertSame(
			13,
			$this->arrayData1->get('hexValue')
		);
	}

	public function testBinValue()
	{
		$this->assertSame(
			13,
			$this->arrayData1->get('binValue')
		);
	}

	public function testOctValue()
	{
		$this->assertSame(
			13,
			$this->arrayData1->get('octValue')
		);
	}


	public function testComplexValue()
	{
		$this->assertSame(
			'This value is commented',
			$this->arrayData1->get('comValue')
		);
	}


	public function testComplexQuotedValue()
	{
		$this->assertSame(
			'This value is quoted ; with a " so should be seen',
			$this->arrayData1->get('comQuotedValue')
		);
	}


	public function testMultiValue()
	{
		$this->assertSame(
			"This is multiple lines of text.  Line endings should be\npreserved until `foo=bar` or `[section]` or `\\n\\n`.",
			$this->arrayData1->get('multiValue')
		);
	}

	public function testInclude()
	{
		$this->assertSame(
			[
				'value1' => 1,
				'value2' => 2
			],
			$this->arrayData1->get('complex.include')
		);

		$this->assertSame(
			[
				'value1' => 1,
				'value2' => 2
			],
			$this->arrayData2->get('complex.include')
		);

		$this->assertSame(
			2,
			$this->objData->get('complex.include')->value2
		);
	}


	public function testRun()
	{
		$this->assertSame($this->arrayData1->get('complex.run'), 'bar');
	}


	public function testEnv()
	{
		$this->assertSame($this->arrayData1->get('complex.envWithDefault'), 'bar');
		$this->assertSame($this->arrayData1->get('complex.envWithoutDefault'), NULL);
		$this->assertSame($this->arrayData1->get('complex.envSetWithDefault'), 'foo');
	}


	public function testFunction()
	{
		$this->assertSame($this->arrayData1->get('complex.customFunction'), 'Hello Matt!');
	}


	public function testMerging()
	{
		$this->assertSame($this->arrayData1->get('nesting.dotKey')['butt.test'], 'bar');
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
			$this->arrayData1->get('complex.mapping')
		);

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
			$this->arrayData2->get('complex.mapping')
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
			$this->arrayData1->get('reference.sub1.sub1.simpleValue')
		);

		$this->assertSame(
			'value',
			$this->arrayData1->get('reference.sub2.simpleValue')
		);
	}


	public function testDashSection()
	{
		$this->assertSame(
			$this->arrayData1->get('dash-section.simpleValue'),
			'value'
		);

		$this->assertSame(
			'value',
			$this->arrayData1->get('dash-section.quotedValue')
		);

		$this->assertSame(
			1,
			$this->arrayData1->get('dash-section.intValue')
		);

		$this->assertSame(
			'This value is commented',
			$this->arrayData1->get('dash-section.comValue')
		);

		$this->assertSame(
			"This is multiple lines of text.  Line endings should be\npreserved until `foo=bar` or `[section]` or `\\n\\n`.",
			$this->arrayData1->get('multiValue')
		);
	}
}
