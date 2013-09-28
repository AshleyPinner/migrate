<?php
class LoadShell extends AppShell {

	public $uses = [
		'Lighthouse.LHProject'
	];

	public function getOptionParser() {
		$parser = Shell::getOptionParser();
		$parser
			->description('Process a lighthouse account export')
			->addArgument('export file', [
				'help' => 'The account export file from lighthouse',
				'required' => true
			])
			->epilog('Before starting request an account export file from lighthouse via https://<your-account>.lighthouseapp.com/export. Once the export file has been received, load the export file using this command.');

		return $parser;
	}

	public function main() {
		$this->LHProject->load($this->args[0]);
	}

}
