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
		$out = $this->getMock('ConsoleOutput', array(), array(), '', false);
		$in = $this->getMock('ConsoleInput', array(), array(), '', false);

		$this->Shell = $this->getMock(
			'ImportShell',
			array('in', 'out', 'hr', 'err', 'createFile', '_stop', '_checkUnitTest'),
			array($out, $out, $in)
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
		return array(
			array('one', ['one']),
			array('one "ONE"', ['one']),
			array('one two', ['one', 'two']),
			array('one TWO', ['one', 'two']),
			array('one two TWO', ['one', 'two']),
			array('"quoted"', ['quoted']),
			array('"multi word"', ['multi word']),
			array('"multi word" one two', ['multi word', 'one', 'two']),
			array('notquoted "quoted"', ['notquoted', 'quoted']),
			array('"quoted" notquoted', ['notquoted', 'quoted']),
			array('notquoted "quoted" another', ['another', 'notquoted', 'quoted']),
			array('notquoted another "quoted"', ['another', 'notquoted', 'quoted']),
			array('"multi word" notquoted', ['multi word', 'notquoted']),
			array(
				'one "multi word" two three "more words" four "even more words" "last tag"',
				['even more words', 'four', 'last tag', 'more words', 'multi word', 'one', 'three', 'two']
			)
		);
	}

}
