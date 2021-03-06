<?php
App::uses('AppShell', 'Console/Command');

/**
 * CommandListShell
 *
 * Overriden to change the help text if no arguments are passed
 */
class CommandListShell extends AppShell {

	public function main() {
		$this->out();
		$this->out('Lighthouse functions:', 2);
		$this->out('<info>Console/cake lighthouse.load</info>');
		$this->out('<info>Console/cake lighthouse.renumber</info>');
		$this->out('<info>Console/cake lighthouse.accept</info>');
		$this->out('<info>Console/cake lighthouse.skip</info> <comment>(optional)</comment>');
		$this->out('<info>Console/cake lighthouse.review</info> <comment>(optional)</comment>');
		$this->out('<info>Console/cake lighthouse.names</info> <comment>(optional)</comment>');

		$this->hr();
		$this->out();

		$this->out('Github functions:', 2);
		$this->out('<info>Console/cake github.setup</info>');
		$this->out('<info>Console/cake github.import</info>');
		$this->out('<info>Console/cake github.reset</info>');
		$this->hr();
		$this->out();
	}

	protected function _welcome() {
		$this->out();
		$this->out('<info>Lighthouse migration shell</info>', 2);

		$description =	'The commands should be called in the order shown.' .
			' To get more information about what the shell is doing, use the' .
			' `--verbose` flag. For help with each command append `--help`.';

		$description = wordwrap($description, 63);
		$this->out($description);
		$this->hr();
	}
}
