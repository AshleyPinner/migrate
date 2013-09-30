<?php
App::uses('ConsoleOutput', 'Console');
App::uses('ConsoleInput', 'Console');
App::uses('ImportShell', 'Github.Console/Command');
App::uses('AccessibilityHelperTrait', 'CakephpTestUtilities.Lib');


class ImportShellTest extends CakeTestCase {

	use AccessibilityHelperTrait;

/**
 * setup test
 *
 * @return void
 */
	public function setUp() {
		parent::setUp();
		$out = $this->getMock('ConsoleOutput', [], [], '', false);
		$in = $this->getMock('ConsoleInput', [], [], '', false);

		$this->Shell = $this->getMock(
			'ImportShell',
			['in', 'out', 'hr', 'err', 'createFile', '_stop', '_checkUnitTest'],
			[$out, $out, $in]
		);
		$this->setReflectionClassInstance($this->Shell);
	}

/**
 * tearDown method
 *
 * @return void
 */
	public function tearDown() {
		parent::tearDown();
		unset($this->Shell);
	}

/**
 * testDeriveTags
 *
 * @dataProvider tagStringProvider
 * @param string $input
 * @param string $expected
 */
	public function testDeriveTags($input, $expected) {
		$output = $this->callProtectedMethod('_deriveTags', [$input], $this->Shell);
		$this->assertSame($expected, $output);
	}

	public function tagStringProvider() {
		return [
			['one', ['one']],
			['one "ONE"', ['one']],
			['one two', ['one', 'two']],
			['one TWO', ['one', 'two']],
			['one two TWO', ['one', 'two']],
			['"quoted"', ['quoted']],
			['"multi word"', ['multi word']],
			['"multi word" one two', ['multi word', 'one', 'two']],
			['notquoted "quoted"', ['notquoted', 'quoted']],
			['"quoted" notquoted', ['notquoted', 'quoted']],
			['notquoted "quoted" another', ['another', 'notquoted', 'quoted']],
			['notquoted another "quoted"', ['another', 'notquoted', 'quoted']],
			['"multi word" notquoted', ['multi word', 'notquoted']],
			[
				'one "multi word" two three "more words" four "even more words" "last tag"',
				['even more words', 'four', 'last tag', 'more words', 'multi word', 'one', 'three', 'two']
			]
		];
	}

}
