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
		$this->out('<info>Console/cake lighthouse.skip</info> (optional)');

		$this->hr();
		$this->out();

		$this->out('Github functions:', 2);
		$this->out('<info>Console/cake github.import</info>');
		$this->hr();
		$this->out();
	}

	protected function _welcome() {
		$this->out();
		$this->out('<info>Lighthouse migration shell</info>', 2);

		$description = 'For help with each command - call with no arguments.' .
			' The commands should be called in the order shown, each asks for' .
			' confirmation before doing anything for your own piece of mind.' .
			' To get more information about what the shell is doing, use the' .
			' `--verbose` flag';
		$description = wordwrap($description, 63);
		$this->out($description);
		$this->hr();
	}
}
