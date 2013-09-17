<?php
App::uses('AppShell', 'Console/Command');

/**
 * CommandListShell
 *
 * Overriden to change the help text if no arguments are passed
 */
class CommandListShell extends AppShell {

	public function main() {
		$this->out('For Lighthouse functions, run <info>Console/cake lighthouse</info>');
		$this->out('For Github functions, run <info>Console/cake github</info>');
		$this->out('');
	}

}
