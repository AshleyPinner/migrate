<?php
class LoadShell extends AppShell {

	public $tasks = [
		'Lighthouse.LH'
	];

	public function main() {
		$this->LH->load($this->args[0]);
	}

	public function getOptionParser() {
		$parser = parent::getOptionParser();
		$parser
			->description('Process a lighthouse account export')
			->addArgument('export file', array(
				'help' => 'The account export file from lighthouse',
				'required' => true
			));

		return $parser;
	}
}
